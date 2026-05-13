(function () {
    'use strict';

    const ENDPOINT  = 'includes/rise_patch_actions.php';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

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

    // ── Patch ──────────────────────────────────────────────────────────────────

    async function doPatch() {
        if (!riseFile) return;

        const btn = document.getElementById('btnRisePatch');
        btn.disabled = true;
        btn.classList.add('is-loading');

        try {
            const fd = new FormData();
            fd.append('action', 'patch');
            fd.append('zip_file', riseFile);

            const res  = await fetch(ENDPOINT, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken }, body: fd });
            const data = await res.json();

            showResult(data);
        } catch {
            Toast.error(i18n('rise_err_network', 'Error de red. Intenta de nuevo.'));
        } finally {
            btn.disabled = false;
            btn.classList.remove('is-loading');
        }
    }

    // ── Result ─────────────────────────────────────────────────────────────────

    function showResult(data) {
        const wrap    = document.getElementById('riseResult');
        const content = document.getElementById('riseResultContent');
        if (!wrap || !content) return;

        wrap.hidden = false;

        if (!data.success) {
            content.innerHTML = `<div class="alert alert-danger">
                <i class="bi bi-x-circle alert-icon"></i>
                <span class="alert-content">${escHtml(data.message)}</span>
            </div>`;
            return;
        }

        const statusMap = {
            patched:         { cls: 'lozenge-success', label: i18n('rise_status_patched_loz', 'Corregido') },
            already_patched: { cls: 'lozenge-warning', label: i18n('rise_status_already_loz', 'Ya corregido') },
            not_applicable:  { cls: 'lozenge-default', label: i18n('rise_status_na_loz', 'No aplica')  },
        };
        const s = statusMap[data.status] ?? { cls: 'lozenge-default', label: data.status };

        let html = `
            <div style="display:flex; align-items:center; gap: var(--ds-space-150); margin-bottom: var(--ds-space-200);">
                <span class="lozenge ${escHtml(s.cls)}">${escHtml(s.label)}</span>
                <span class="text-subtle text-sm">${escHtml(data.message)}</span>
            </div>`;

        if (data.files_modified?.length) {
            html += `<p class="text-sm" style="margin-bottom: var(--ds-space-100);"><strong>${i18n('rise_files_modified', 'Archivos modificados')}:</strong></p>
            <ul class="text-sm" style="margin: 0 0 var(--ds-space-200) var(--ds-space-200); padding:0;">`;
            for (const f of data.files_modified) {
                html += `<li style="font-family: monospace;">${escHtml(f)}</li>`;
            }
            html += '</ul>';
        }

        if (data.status === 'patched' && data.data && data.filename) {
            html += `<button type="button" class="btn btn-primary" id="btnRiseDownload">
                <i class="bi bi-download" aria-hidden="true"></i>
                ${i18n('rise_btn_download', 'Descargar ZIP corregido')}
            </button>`;
        }

        if (data.status === 'not_applicable') {
            html += `<div class="alert alert-info" style="margin-top: var(--ds-space-150);">
                <i class="bi bi-info-circle alert-icon"></i>
                <span class="alert-content">${i18n('rise_na_detail', 'Este paquete fue exportado antes de mayo 2025 y no contiene el código problemático.')}</span>
            </div>`;
        }

        content.innerHTML = html;

        if (data.status === 'patched') {
            document.getElementById('btnRiseDownload')?.addEventListener('click', () => {
                downloadBase64(data.data, data.filename, 'application/zip');
            });
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    function downloadBase64(b64, filename, mime) {
        const binary = atob(b64);
        const bytes  = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
        const blob = new Blob([bytes], { type: mime });
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href     = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 5000);
    }

    function formatBytes(bytes) {
        if (bytes < 1024)       return bytes + ' B';
        if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB';
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
