(function () {
    'use strict';

    const ENDPOINT  = 'includes/gift_actions.php';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const T = window.__T__ || {};
    const t = (key, fallback) => T[key] || fallback;

    let activeFormat = null;
    let giftFile     = null;
    let lastResult   = null;

    // ── Init ──────────────────────────────────────────────────────────────────

    function init() {
        initFormatCards();
        initDropZone();
        initActions();
        initSlidePanels();
        selectFormat('gift');
    }

    // ── Format cards ──────────────────────────────────────────────────────────

    function initFormatCards() {
        document.querySelectorAll('#giftFormatStack .img-method-card[data-format]').forEach(card => {
            card.addEventListener('click', () => selectFormat(card.dataset.format));
            card.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); selectFormat(card.dataset.format); }
            });
        });
    }

    function selectFormat(fmt) {
        activeFormat = fmt;
        document.querySelectorAll('#giftFormatStack .img-method-card[data-format]').forEach(card => {
            const sel = card.dataset.format === fmt;
            card.classList.toggle('is-selected', sel);
            card.setAttribute('aria-checked', String(sel));
        });
        const qtiOpts = document.getElementById('giftQtiOptions');
        if (qtiOpts) qtiOpts.hidden = (fmt !== 'qti');
        updateResultTitle();
    }

    function updateResultTitle() {
        const el = document.getElementById('giftResultTitle');
        if (!el) return;
        el.textContent = activeFormat === 'qti'
            ? t('utilities.result_title_qti',  'Resultado QTI')
            : t('utilities.result_title_gift', 'Resultado GIFT');
    }

    // ── Drop zone ─────────────────────────────────────────────────────────────

    function initDropZone() {
        const zone    = document.getElementById('giftDropZone');
        const input   = document.getElementById('giftFileInput');
        const pickBtn = document.getElementById('btnGiftPick');

        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            const files = Array.from(e.dataTransfer.files).filter(isValidFile);
            if (files.length) setFile(files[0]);
        });
        zone.addEventListener('click', e => {
            if (e.target.closest('button') || e.target.closest('a')) return;
            input.click();
        });

        input.addEventListener('change', () => {
            if (input.files[0] && isValidFile(input.files[0])) setFile(input.files[0]);
            input.value = '';
        });

        pickBtn.addEventListener('click', e => { e.stopPropagation(); input.click(); });
    }

    function isValidFile(file) {
        const name = file.name.toLowerCase();
        return name.endsWith('.docx') || name.endsWith('.xlsx');
    }

    function setFile(file) {
        giftFile = file;
        const zone  = document.getElementById('giftDropZone');
        const title = zone.querySelector('.img-drop-title');
        const sub   = document.getElementById('giftFilename');
        if (title) title.textContent = file.name;
        if (sub)   sub.textContent   = fmtSize(file.size);
        zone.classList.add('has-file');
        document.getElementById('btnGiftClear').hidden  = false;
        document.getElementById('btnGiftProcess').disabled = false;
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    function initActions() {
        document.getElementById('btnGiftProcess').addEventListener('click', processFile);
        document.getElementById('btnGiftClear').addEventListener('click', clearAll);
        document.getElementById('btnGiftCopy').addEventListener('click', copyGift);
        document.getElementById('btnGiftDownload').addEventListener('click', handleDownload);
        document.getElementById('btnGiftPreview').addEventListener('click', showPreview);
        document.getElementById('btnGiftGuide').addEventListener('click', () => openPanel('guideGiftFormats'));
    }

    async function processFile() {
        if (!giftFile || !activeFormat) return;

        const btn = document.getElementById('btnGiftProcess');
        btn.disabled  = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> ' +
            t('utilities.btn_processing', 'Procesando...');

        hideResults();

        const fd = new FormData();
        fd.append('action',        'process');
        fd.append('output_format', activeFormat);
        fd.append('gift_file',     giftFile);
        fd.append('italic_words',  document.getElementById('giftItalic').value || '');
        fd.append('bold_words',    document.getElementById('giftBold').value   || '');

        if (activeFormat === 'qti') {
            fd.append('qti_bank_name', document.getElementById('giftBankName').value || '');
            fd.append('qti_mode',      document.getElementById('giftQtiQuiz').checked ? 'quiz' : 'bank');
        }

        let data;
        try {
            const res = await fetch(ENDPOINT, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: fd,
            });
            data = await res.json();
        } catch (_) {
            data = { success: false, message: t('utilities.error_request', 'Error de conexión al procesar') };
        }

        btn.disabled  = false;
        btn.innerHTML = '<i class="bi bi-lightning" aria-hidden="true"></i> ' +
            t('utilities.btn_process', 'Procesar');

        if (!data.success) {
            showError(data.message || t('utilities.error_process', 'Error al procesar el archivo'));
            return;
        }

        lastResult = data;
        renderResults(data);
    }

    function renderResults(data) {
        const resultsEl = document.getElementById('giftResults');
        const giftSec   = document.getElementById('giftResGift');
        const qtiSec    = document.getElementById('giftResQti');
        const errEl     = document.getElementById('giftResError');
        const isGift    = data.format === 'gift';

        errEl.hidden = true;
        updateResultTitle();

        // Mostrar/ocultar secciones de salida
        if (isGift) {
            document.getElementById('giftOutput').value = data.gift || '';
            giftSec.hidden = false;
            qtiSec.hidden  = true;
        } else {
            giftSec.hidden = true;
            qtiSec.hidden  = false;
        }

        // Botones en cabecera
        document.getElementById('btnGiftCopy').hidden    = !isGift;
        document.getElementById('btnGiftPreview').hidden = !(data.preguntas && data.preguntas.length > 0);

        const warnings = data.warnings || [];
        renderWarnings(warnings);
        renderReport(data.stats || {}, data.format, warnings.length);

        resultsEl.hidden = false;
        resultsEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function showError(msg) {
        const errEl = document.getElementById('giftResError');
        errEl.textContent = msg;
        errEl.hidden = false;
        document.getElementById('giftResults').hidden = false;
    }

    function hideResults() {
        document.getElementById('giftResults').hidden    = true;
        document.getElementById('giftResError').hidden   = true;
        document.getElementById('giftResGift').hidden    = true;
        document.getElementById('giftResQti').hidden     = true;
        document.getElementById('giftReport').hidden     = true;
        document.getElementById('btnGiftCopy').hidden    = true;
        document.getElementById('btnGiftPreview').hidden = true;
    }

    function clearAll() {
        giftFile   = null;
        lastResult = null;
        const zone  = document.getElementById('giftDropZone');
        const title = zone.querySelector('.img-drop-title');
        const sub   = document.getElementById('giftFilename');
        if (title && title.dataset.orig) title.textContent = title.dataset.orig;
        else if (title) title.textContent = t('utilities.drop_title') || 'Arrastre su archivo aquí o haga clic';
        if (sub) sub.textContent = 'Word (.docx) · Excel (.xlsx)';
        zone.classList.remove('has-file');
        document.getElementById('btnGiftClear').hidden   = true;
        document.getElementById('btnGiftProcess').disabled = true;
        hideResults();
        Toast.success(t('utilities.cleared', 'Formulario limpiado'));
    }

    // ── Informe de procesamiento ──────────────────────────────────────────────

    function renderReport(stats, format, warningCount) {
        const report = document.getElementById('giftReport');
        const items  = document.getElementById('giftReportItems');
        if (!stats.total) { report.hidden = true; return; }

        const formatLabel = format === 'qti' ? 'QTI (Canvas)' : 'GIFT (Moodle)';
        const parts = [];

        parts.push(reportItem(stats.total,          t('utilities.stats_questions', 'preguntas procesadas')));
        parts.push(reportItem(stats.italics || 0,   t('utilities.stats_italics',   'cursivas aplicadas')));
        parts.push(reportItem(stats.bolds   || 0,   t('utilities.stats_bolds',     'negritas aplicadas')));
        parts.push(reportItem(formatLabel,           'formato', true));

        if (warningCount) {
            parts.push(
                '<button type="button" class="gift-stats-item is-danger" id="btnGiftWarnings">' +
                '<i class="bi bi-exclamation-triangle" aria-hidden="true"></i> ' +
                '<strong>' + warningCount + '</strong> ' +
                (warningCount === 1 ? 'alerta' : 'alertas') +
                '</button>'
            );
        }

        items.innerHTML = parts.join('');

        if (warningCount) {
            document.getElementById('btnGiftWarnings')
                ?.addEventListener('click', () => openPanel('guideGiftWarnings'));
        }

        report.hidden = false;
    }

    function reportItem(value, label, labelFirst = false, warn = false) {
        const cls   = 'gift-stats-item' + (warn ? ' is-warning' : '');
        const val   = '<strong>' + esc(String(value)) + '</strong>';
        const lbl   = esc(label);
        const inner = labelFirst ? (lbl + ' ' + val) : (val + ' ' + lbl);
        return '<span class="' + cls + '">' + inner + '</span>';
    }

    // ── Warnings ──────────────────────────────────────────────────────────────

    function renderWarnings(warnings) {
        const body = document.getElementById('giftWarningsBody');
        if (!body) return;
        if (!warnings.length) { body.innerHTML = ''; return; }
        body.innerHTML = '<ul class="gift-warnings-list">' +
            warnings.map(w =>
                '<li><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> ' + esc(w) + '</li>'
            ).join('') +
            '</ul>';
    }

    // ── Copy / Download ───────────────────────────────────────────────────────

    async function copyGift() {
        const text = document.getElementById('giftOutput').value;
        if (!text) return;
        try {
            await navigator.clipboard.writeText(text);
            Toast.success(t('utilities.copied_gift', 'GIFT copiado al portapapeles'));
        } catch (_) {
            Toast.error('No se pudo copiar al portapapeles');
        }
    }

    function handleDownload() {
        if (!lastResult) return;
        if (lastResult.format === 'qti') {
            if (!lastResult.zip) return;
            const name = giftFile ? giftFile.name.replace(/\.[^.]+$/, '') + '_qti.zip' : 'preguntas_qti.zip';
            downloadBase64(lastResult.zip, name, 'application/zip');
        } else {
            const text = document.getElementById('giftOutput').value;
            if (!text) return;
            const blob = new Blob([text], { type: 'text/plain;charset=utf-8' });
            const url  = URL.createObjectURL(blob);
            const name = giftFile ? giftFile.name.replace(/\.[^.]+$/, '') + '.gift.txt' : 'preguntas.gift.txt';
            const a    = Object.assign(document.createElement('a'), { href: url, download: name });
            a.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
        }
        Toast.success(t('utilities.downloaded', 'Archivo descargado'));
    }

    // ── Preview ───────────────────────────────────────────────────────────────

    function showPreview() {
        if (!lastResult || !lastResult.preguntas) return;
        const body = document.getElementById('giftPreviewBody');
        body.innerHTML = renderPreguntas(lastResult.preguntas);
        openPanel('guideGiftPreview');
    }

    function renderPreguntas(preguntas) {
        return preguntas.map(p => renderPregunta(p)).join('');
    }

    function renderPregunta(p) {
        const lozengeClass = { OM: 'lozenge-default', FV: 'lozenge-info', EM: 'lozenge-success' };
        const lozClass = lozengeClass[p.tipo] || 'lozenge-default';
        let html = '<div class="preview-q">';
        html += '<div class="preview-q-header">';
        html += '<span class="lozenge ' + lozClass + '">' + esc(p.tipo) + '</span>';
        html += '<span class="preview-q-num">Pregunta ' + esc(p.numero || '') + '</span>';
        html += '</div>';
        html += '<div class="preview-q-body">' + sanitizePreview(p.enunciado || '') + '</div>';

        if (p.tipo === 'OM' && p.opciones) {
            html += '<ul class="preview-options">';
            p.opciones.forEach((opc, i) => {
                const isCorrect = i === 0;
                html += '<li class="preview-option' + (isCorrect ? ' is-correct' : '') + '">';
                html += String.fromCharCode(97 + i) + ') ' + sanitizePreview(opc);
                let retro = '';
                if (p.retros && p.retros[i] !== undefined) {
                    retro = p.retros[i];
                } else {
                    retro = isCorrect ? (p.retro_correcta || '') : (p.retro_incorrecta || '');
                }
                if (retro) html += '<span class="preview-feedback">' + sanitizePreview(retro) + '</span>';
                html += '</li>';
            });
            html += '</ul>';
        }

        if (p.tipo === 'FV') {
            const resp = p.respuesta === 'TRUE' ? 'Verdadero' : 'Falso';
            html += '<div class="preview-fv-answer">Respuesta: <strong>' + esc(resp) + '</strong></div>';
            if (p.retro_verdadero) html += '<div class="preview-feedback preview-feedback--fv"><span class="preview-feedback-label">Retro verdadero:</span> ' + sanitizePreview(p.retro_verdadero) + '</div>';
            if (p.retro_falso)     html += '<div class="preview-feedback preview-feedback--fv"><span class="preview-feedback-label">Retro falso:</span> '     + sanitizePreview(p.retro_falso)     + '</div>';
        }

        if (p.tipo === 'EM' && p.pares) {
            html += '<table class="preview-em-table"><tbody>';
            p.pares.forEach(par => {
                html += '<tr><td>' + sanitizePreview(par.izq) + '</td><td>→</td><td>' + sanitizePreview(par.der) + '</td></tr>';
            });
            html += '</tbody></table>';
        }

        html += '</div>';
        return html;
    }

    // ── Slide panels ──────────────────────────────────────────────────────────

    function initSlidePanels() {
        const overlay = document.getElementById('slidePanelOverlay');
        document.querySelectorAll('.slide-panel-close').forEach(btn => {
            btn.addEventListener('click', closeAllPanels);
        });
        if (overlay) overlay.addEventListener('click', closeAllPanels);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAllPanels(); });
    }

    function openPanel(id) {
        const panel   = document.getElementById(id);
        const overlay = document.getElementById('slidePanelOverlay');
        if (!panel) return;
        panel.classList.add('active');
        panel.setAttribute('aria-hidden', 'false');
        if (overlay) overlay.classList.add('active');
    }

    function closeAllPanels() {
        document.querySelectorAll('.slide-panel.active').forEach(p => {
            p.classList.remove('active');
            p.setAttribute('aria-hidden', 'true');
        });
        const overlay = document.getElementById('slidePanelOverlay');
        if (overlay) overlay.classList.remove('active');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function sanitizePreview(str) {
        if (!str) return '';
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/&lt;strong&gt;/g, '<strong>')
            .replace(/&lt;\/strong&gt;/g, '</strong>')
            .replace(/&lt;em&gt;/g, '<em>')
            .replace(/&lt;\/em&gt;/g, '</em>');
    }

    function fmtSize(bytes) {
        if (bytes < 1024)        return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function downloadBase64(b64, filename, mime) {
        const bin   = atob(b64);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        const blob = new Blob([bytes], { type: mime });
        const url  = URL.createObjectURL(blob);
        const a    = Object.assign(document.createElement('a'), { href: url, download: filename });
        a.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }

    // ── Start ─────────────────────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());
