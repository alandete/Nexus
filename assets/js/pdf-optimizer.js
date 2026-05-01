(function () {
    'use strict';

    const ENDPOINT  = 'includes/pdf_optimizer_actions.php';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let activeMethod = null;
    let pdfFile      = null;

    // ── Init ──────────────────────────────────────────────────────────────────

    function init() {
        initMethodCards();
        initDropZone();
        initSlidePanels();
        fetchStatus();
    }

    // ── Status ────────────────────────────────────────────────────────────────

    async function fetchStatus() {
        try {
            const fd = new FormData();
            fd.append('action', 'status');
            const res  = await fetch(ENDPOINT, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken }, body: fd });
            const data = await res.json();
            if (!data.success) return;

            const m = data.methods;
            document.querySelectorAll('.img-method-card[data-method]').forEach(card => {
                const method    = card.dataset.method;
                const available = m[method] ?? false;
                const badge     = card.querySelector('.img-method-badge');
                const noAvail   = card.querySelector('.img-method-unavailable');

                card.dataset.available = available ? '1' : '0';
                if (badge) {
                    badge.textContent = available ? 'Disponible' : 'No disponible';
                    badge.className   = 'lozenge img-method-badge ' + (available ? 'lozenge-success' : 'lozenge-danger');
                }
                if (noAvail) noAvail.hidden = available;
            });

            const firstAvail = Object.keys(m).find(
                k => m[k] && document.querySelector('.img-method-card[data-method="' + k + '"]')
            );
            if (firstAvail) selectMethod(firstAvail);

        } catch (_) { /* silent */ }
    }

    // ── Method cards ──────────────────────────────────────────────────────────

    function initMethodCards() {
        document.querySelectorAll('.img-method-card[data-method]').forEach(card => {
            card.addEventListener('click', e => {
                if (e.target.closest('.img-method-guide')) return;
                if (card.dataset.available === '0') return;
                selectMethod(card.dataset.method);
            });
            card.addEventListener('keydown', e => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    if (card.dataset.available !== '0') selectMethod(card.dataset.method);
                }
            });
        });
    }

    function selectMethod(method) {
        activeMethod = method;
        document.querySelectorAll('.img-method-card[data-method]').forEach(card => {
            const selected = card.dataset.method === method;
            card.classList.toggle('is-selected', selected);
            card.setAttribute('aria-checked', String(selected));
        });
        updateProcessBtn();
    }

    // ── Drop zone ─────────────────────────────────────────────────────────────

    function initDropZone() {
        const zone    = document.getElementById('pdfDropZone');
        const input   = document.getElementById('pdfFileInput');
        const pickBtn = document.getElementById('btnPdfPick');

        storeZoneText(zone);

        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            const files = Array.from(e.dataTransfer.files).filter(isPdf);
            if (files.length) setPdfFile(files[0]);
        });
        zone.addEventListener('click', e => {
            if (e.target.closest('button')) return;
            input.click();
        });

        input.addEventListener('change', () => {
            if (input.files[0] && isPdf(input.files[0])) setPdfFile(input.files[0]);
            input.value = '';
        });

        pickBtn.addEventListener('click', () => input.click());

        document.getElementById('btnPdfProcess').addEventListener('click', processPdf);
        document.getElementById('btnPdfClear').addEventListener('click', clearPdf);
    }

    function isPdf(file) {
        return file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
    }

    function setPdfFile(file) {
        pdfFile = file;
        const zone     = document.getElementById('pdfDropZone');
        const title    = zone.querySelector('.img-drop-title');
        const subtitle = zone.querySelector('.img-drop-subtitle');
        if (title)    title.textContent    = file.name;
        if (subtitle) subtitle.textContent = fmtSize(file.size);
        zone.classList.add('has-file');
        document.getElementById('btnPdfClear').hidden = false;
        updateProcessBtn();
    }

    function updateProcessBtn() {
        document.getElementById('btnPdfProcess').disabled = !pdfFile || !activeMethod;
    }

    // ── Process ───────────────────────────────────────────────────────────────

    async function processPdf() {
        if (!pdfFile || !activeMethod) return;

        const btn = document.getElementById('btnPdfProcess');
        btn.disabled  = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Procesando...';

        const fd = new FormData();
        fd.append('action',   'process');
        fd.append('method',   activeMethod);
        fd.append('pdf_file', pdfFile);

        const resultsDiv = document.getElementById('pdfResults');
        const errorEl    = document.getElementById('pdfResError');
        const successEl  = document.getElementById('pdfResSuccess');
        resultsDiv.hidden = true;
        errorEl.hidden    = true;
        successEl.hidden  = true;

        let data;
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 180000);
        try {
            const res = await fetch(ENDPOINT, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken }, body: fd, signal: controller.signal });
            data = await res.json();
        } catch (err) {
            data = { success: false, message: err.name === 'AbortError' ? 'El proceso tardó demasiado. Intenta con un archivo más pequeño.' : 'Error de red' };
        } finally {
            clearTimeout(timer);
        }

        if (data.success) {
            document.getElementById('pdfResFilename').textContent  = data.filename;
            document.getElementById('pdfResOriginal').textContent  = fmtSize(data.original_size);
            document.getElementById('pdfResOptimized').textContent = fmtSize(data.optimized_size);
            document.getElementById('pdfResSavings').innerHTML =
                data.savings_pct > 0
                    ? '<span class="lozenge lozenge-success">-' + data.savings_pct + '%</span>'
                    : '<span class="lozenge lozenge-default">Sin cambios</span>';

            const dlBtn = document.getElementById('btnPdfDownload');
            dlBtn.classList.remove('is-downloaded');
            dlBtn.querySelector('i').className = 'bi bi-download';
            const snap = { b64: data.data, name: data.filename };
            dlBtn.onclick = () => {
                downloadBase64(snap.b64, snap.name, 'application/pdf');
                markDownloaded(dlBtn);
            };

            successEl.hidden = false;
        } else {
            errorEl.textContent = data.message || 'Error al procesar el archivo';
            errorEl.hidden = false;
        }

        resultsDiv.hidden = false;
        btn.disabled  = false;
        btn.innerHTML = '<i class="bi bi-lightning" aria-hidden="true"></i> Optimizar';
    }

    function clearPdf() {
        pdfFile = null;
        resetZoneText(document.getElementById('pdfDropZone'));
        document.getElementById('pdfResults').hidden    = true;
        document.getElementById('pdfResError').hidden   = true;
        document.getElementById('pdfResSuccess').hidden = true;
        document.getElementById('btnPdfClear').hidden   = true;
        const dlBtn = document.getElementById('btnPdfDownload');
        dlBtn.onclick = null;
        dlBtn.classList.remove('is-downloaded');
        dlBtn.querySelector('i').className = 'bi bi-download';
        updateProcessBtn();
    }

    // ── Slide panels ──────────────────────────────────────────────────────────

    function initSlidePanels() {
        const overlay = document.getElementById('slidePanelOverlay');

        document.querySelectorAll('.img-method-guide[data-guide]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                openPanel(btn.dataset.guide === 'ghostscript' ? 'guidePdfGs' : 'guidePdfApi');
            });
        });

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

    // ── Zone helpers ──────────────────────────────────────────────────────────

    function storeZoneText(zone) {
        const title    = zone.querySelector('.img-drop-title');
        const subtitle = zone.querySelector('.img-drop-subtitle');
        if (title    && !title.dataset.orig)    title.dataset.orig    = title.textContent;
        if (subtitle && !subtitle.dataset.orig) subtitle.dataset.orig = subtitle.textContent;
    }

    function resetZoneText(zone) {
        const title    = zone.querySelector('.img-drop-title');
        const subtitle = zone.querySelector('.img-drop-subtitle');
        if (title    && title.dataset.orig)    title.textContent    = title.dataset.orig;
        if (subtitle && subtitle.dataset.orig) subtitle.textContent = subtitle.dataset.orig;
        zone.classList.remove('has-file');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function markDownloaded(btn) {
        btn.classList.add('is-downloaded');
        btn.querySelector('i').className = 'bi bi-check-lg';
        btn.setAttribute('aria-label', 'Descargado');
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

    function fmtSize(bytes) {
        if (bytes < 1024)         return bytes + ' B';
        if (bytes < 1024 * 1024)  return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    // ── Start ─────────────────────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());
