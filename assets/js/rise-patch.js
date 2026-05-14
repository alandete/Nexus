(function () {
    'use strict';

    const PATRON = 'window.parent.RiseLMSInterface';
    const PARCHE = '(function(){try{return window.parent.RiseLMSInterface}catch(e){return void 0}})()';

    let riseFile = null;

    // ── Drop zone ──────────────────────────────────────────────────────────────

    function initDropZone() {
        const zone  = document.getElementById('riseDropZone');
        const input = document.getElementById('riseFileInput');
        const pick  = document.getElementById('btnRisePick');

        if (!zone) return;

        pick?.addEventListener('click', () => input?.click());
        input?.addEventListener('change', () => { if (input.files[0]) setFile(input.files[0]); });

        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            const file = e.dataTransfer?.files[0];
            if (file) setFile(file);
        });
    }

    function setFile(file) {
        if (!file.name.toLowerCase().endsWith('.zip')) {
            Toast.error(i18n('rise_err_not_zip', 'El archivo debe ser un ZIP'));
            return;
        }
        riseFile = file;
        const zone = document.getElementById('riseDropZone');
        if (zone) {
            zone.classList.add('has-file');
            const title    = zone.querySelector('.img-drop-title');
            const subtitle = zone.querySelector('.img-drop-subtitle');
            if (title)    title.textContent    = file.name;
            if (subtitle) subtitle.textContent = formatBytes(file.size);
        }
        document.getElementById('btnRisePatch').disabled = false;
        document.getElementById('btnRiseClear').hidden   = false;
        document.getElementById('riseResult').hidden     = true;
    }

    function clearFile() {
        riseFile = null;
        const zone  = document.getElementById('riseDropZone');
        const input = document.getElementById('riseFileInput');
        if (zone)  zone.classList.remove('has-file');
        if (input) input.value = '';

        const zone_ = document.getElementById('riseDropZone');
        if (zone_) {
            const title    = zone_.querySelector('.img-drop-title');
            const subtitle = zone_.querySelector('.img-drop-subtitle');
            if (title)    title.textContent    = i18n('rise_drop_title', 'Arrastra el ZIP aquí');
            if (subtitle) subtitle.textContent = i18n('rise_drop_subtitle', 'o selecciona un archivo');
        }

        document.getElementById('btnRisePatch').disabled = true;
        document.getElementById('btnRiseClear').hidden   = true;
        document.getElementById('riseResult').hidden     = true;
    }

    // ── Progress steps ─────────────────────────────────────────────────────────

    function renderSteps(file) {
        const wrap    = document.getElementById('riseResult');
        const content = document.getElementById('riseResultContent');
        if (!wrap || !content) return;

        wrap.hidden = false;
        content.innerHTML =
            `<div class="rise-steps">` +
            makeStep('upload', i18n('rise_step_uploading', 'Leyendo el archivo...') + ' &mdash; ' + escHtml(formatBytes(file.size))) +
            makeStep('scan',   i18n('rise_step_scanning',  'Analizando contenido...')) +
            makeStep('pack',   i18n('rise_step_packing',   'Corrigiendo y empaquetando...')) +
            `</div><div id="riseOutput" class="mt-150"></div>`;
    }

    function makeStep(key, label) {
        return `<div class="rise-step is-pending" id="rise-step-${key}">
            <span class="rise-step-icon"><i class="bi bi-circle" aria-hidden="true"></i></span>
            <span class="rise-step-label">${label}</span>
        </div>`;
    }

    function setStepState(key, state, label) {
        const el = document.getElementById(`rise-step-${key}`);
        if (!el) return;
        el.className = `rise-step is-${state}`;
        const iconEl  = el.querySelector('.rise-step-icon');
        const labelEl = el.querySelector('.rise-step-label');
        if (label && labelEl) labelEl.innerHTML = label;
        const icons = {
            active:  '<span class="spinner spinner-sm" aria-hidden="true"></span>',
            done:    '<i class="bi bi-check-circle-fill" aria-hidden="true"></i>',
            error:   '<i class="bi bi-x-circle-fill" aria-hidden="true"></i>',
            skipped: '<i class="bi bi-dash-circle" aria-hidden="true"></i>',
            pending: '<i class="bi bi-circle" aria-hidden="true"></i>',
        };
        if (iconEl) iconEl.innerHTML = icons[state] ?? icons.pending;
    }

    function setOutput(html) {
        const el = document.getElementById('riseOutput');
        if (el) el.innerHTML = html;
    }

    // ── Patch — procesamiento 100% en el navegador con JSZip ──────────────────

    async function doPatch() {
        if (!riseFile || typeof JSZip === 'undefined') return;

        const btn = document.getElementById('btnRisePatch');
        btn.disabled = true;
        btn.classList.add('is-loading');

        renderSteps(riseFile);

        try {
            // ── Paso 1: leer el ZIP desde el disco local ───────────────────────
            setStepState('upload', 'active');

            const zip = await JSZip.loadAsync(riseFile);

            setStepState('upload', 'done', i18n('rise_step_uploaded', 'Archivo cargado') +
                ' &mdash; ' + escHtml(formatBytes(riseFile.size)));

            // ── Paso 2: escanear archivos JS ───────────────────────────────────
            setStepState('scan', 'active');

            let alreadyPatched = false;
            const patches = {};

            for (const [path, entry] of Object.entries(zip.files)) {
                if (entry.dir || !path.toLowerCase().endsWith('.js')) continue;

                const content = await entry.async('string');

                if (content.includes(PARCHE)) { alreadyPatched = true; continue; }
                if (content.includes(PATRON))  { patches[path] = content.split(PATRON).join(PARCHE); }
            }

            const modified = Object.keys(patches);

            if (modified.length === 0) {
                setStepState('scan', 'done');
                setStepState('pack', 'skipped');
                const status = alreadyPatched ? 'already_patched' : 'not_applicable';
                const statusMap = {
                    already_patched: { cls: 'lozenge-warning', loz: i18n('rise_status_already_loz', 'Ya corregido') },
                    not_applicable:  { cls: 'lozenge-default', loz: i18n('rise_status_na_loz',      'No aplica')   },
                };
                const s = statusMap[status];
                setOutput(`<div style="display:flex;align-items:center;gap:var(--ds-space-150);">
                    <span class="lozenge ${escHtml(s.cls)}">${escHtml(s.loz)}</span>
                </div>`);
                return;
            }

            setStepState('scan', 'done',
                i18n('rise_step_scanned', 'Análisis completado') + ` &mdash; ${modified.length} archivo(s)`);

            // ── Paso 3: aplicar parches y generar ZIP ──────────────────────────
            setStepState('pack', 'active');

            for (const [path, content] of Object.entries(patches)) {
                zip.file(path, content);
            }

            const blob = await zip.generateAsync({ type: 'blob' });

            setStepState('pack', 'done', i18n('rise_step_packed', 'Listo'));

            showResult(modified, blob);

        } catch (err) {
            const activeStep = document.querySelector('.rise-step.is-active');
            const activeKey  = activeStep?.id?.replace('rise-step-', '') ?? 'upload';
            setStepState(activeKey, 'error');
            setOutput(errorHtml(err.message || i18n('rise_err_network', 'Error inesperado. Intenta de nuevo.')));
        } finally {
            btn.disabled = false;
            btn.classList.remove('is-loading');
        }
    }

    // ── Result ─────────────────────────────────────────────────────────────────

    function showResult(modified, blob) {
        let html = '';

        if (modified.length) {
            html += `<p class="text-sm mt-150" style="margin-bottom:var(--ds-space-075);">
                <strong>${i18n('rise_files_modified', 'Archivos modificados')}:</strong></p>
            <ul class="text-sm" style="margin:0 0 var(--ds-space-200) var(--ds-space-200);padding:0;">`;
            for (const f of modified) {
                html += `<li style="font-family:monospace;">${escHtml(f)}</li>`;
            }
            html += '</ul>';
        }

        const suffix   = i18n('rise_lang', 'es') === 'en' ? '_fixed' : '_corregido';
        const filename = riseFile.name.replace(/\.zip$/i, '') + suffix + '.zip';

        const url = URL.createObjectURL(blob);
        html += `<a href="${url}" class="btn btn-primary" download="${escHtml(filename)}">
            <i class="bi bi-download" aria-hidden="true"></i>
            ${i18n('rise_btn_download', 'Descargar ZIP corregido')}
        </a>`;

        setTimeout(() => URL.revokeObjectURL(url), 120000);

        setOutput(html);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    function errorHtml(msg) {
        return `<div class="alert alert-danger mt-150">
            <i class="bi bi-x-circle alert-icon" aria-hidden="true"></i>
            <span class="alert-content">${escHtml(msg)}</span>
        </div>`;
    }

    function formatBytes(bytes) {
        if (bytes < 1024)    return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function escHtml(str) {
        return String(str).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }

    function i18n(key, fallback) {
        return window.__RISE_I18N__?.[key] ?? fallback ?? key;
    }

    // ── Init ───────────────────────────────────────────────────────────────────

    function init() {
        initDropZone();
        document.getElementById('btnRisePatch')?.addEventListener('click', doPatch);
        document.getElementById('btnRiseClear')?.addEventListener('click', clearFile);
    }

    document.readyState === 'loading'
        ? document.addEventListener('DOMContentLoaded', init)
        : init();

})();
