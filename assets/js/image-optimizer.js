(function () {
    'use strict';

    const ENDPOINT   = 'includes/image_optimizer_actions.php';
    const csrfToken  = document.querySelector('meta[name="csrf-token"]')?.content || '';

    let activeMethod = null;
    let compressQueue = [];
    let resizeFile   = null;
    let convertFile  = null;
    let resizeMode   = 'percent';
    let resizeOrigW  = null;
    let resizeOrigH  = null;

    // ── Init ─────────────────────────────────────────────────────────────────

    function init() {
        initMainTabs();
        initSubTabs();
        initMethodCards();
        initCompressPanel();
        initResizePanel();
        initConvertPanel();
        initSlidePanels();
        fetchStatus();
    }

    // ── Main tabs ─────────────────────────────────────────────────────────────

    function initMainTabs() {
        const tabs     = document.querySelectorAll('.utils-tab[data-utab]');
        const sections = document.querySelectorAll('.utils-section');
        const hash     = location.hash.replace('#', '') || 'imagenes';

        tabs.forEach(tab => {
            const active = tab.dataset.utab === hash;
            tab.classList.toggle('active', active);
            if (active) tab.setAttribute('aria-current', 'true');
        });
        sections.forEach(s => { s.hidden = (s.id !== hash); });

        tabs.forEach(tab => {
            tab.addEventListener('click', e => {
                e.preventDefault();
                const id = tab.dataset.utab;
                tabs.forEach(t => { t.classList.remove('active'); t.removeAttribute('aria-current'); });
                tab.classList.add('active');
                tab.setAttribute('aria-current', 'true');
                sections.forEach(s => { s.hidden = (s.id !== id); });
                history.replaceState(null, '', '#' + id);
            });
        });
    }

    // ── Sub-tabs ──────────────────────────────────────────────────────────────

    function initSubTabs() {
        const panels = {
            compress: document.getElementById('panelCompress'),
            resize:   document.getElementById('panelResize'),
            convert:  document.getElementById('panelConvert'),
        };
        document.querySelectorAll('.img-subtab[data-imgtab]').forEach(tab => {
            tab.addEventListener('click', () => {
                const id = tab.dataset.imgtab;
                document.querySelectorAll('.img-subtab').forEach(t => {
                    t.classList.toggle('active', t.dataset.imgtab === id);
                    t.setAttribute('aria-selected', String(t.dataset.imgtab === id));
                });
                Object.entries(panels).forEach(([key, panel]) => { panel.hidden = (key !== id); });
            });
        });
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
                const guide     = card.querySelector('.img-method-guide');

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
        updateSizeLimit();
        revalidateQueue();
        updateCompressBtn();
    }

    function maxBytes() {
        return activeMethod === 'api' ? 5 * 1024 * 1024 : 20 * 1024 * 1024;
    }

    function updateSizeLimit() {
        const help = document.getElementById('imgCompressZone')?.querySelector('.img-drop-help');
        if (!help) return;
        if (activeMethod === 'api') {
            help.textContent = 'JPEG, PNG, WebP o GIF. Máximo 5 MB por imagen.';
        } else {
            help.textContent = 'JPEG, PNG, WebP o GIF. Máximo 20 MB por imagen.';
        }
    }

    function revalidateQueue() {
        const limit = maxBytes();
        compressQueue = compressQueue.filter(f => f.size <= limit);
        renderCompressQueue();
    }

    // ── Compress panel ────────────────────────────────────────────────────────

    function initCompressPanel() {
        const zone    = document.getElementById('imgCompressZone');
        const input   = document.getElementById('imgCompressInput');
        const pickBtn = document.getElementById('btnImgCompressPick');

        storeZoneText(zone);
        setupDropZone(zone, true, files => addToCompressQueue(files));
        pickBtn.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            addToCompressQueue(Array.from(input.files));
            input.value = '';
        });

        document.getElementById('btnImgCompress').addEventListener('click', processCompress);
        document.getElementById('btnImgCompressClear').addEventListener('click', clearCompress);
    }

    function addToCompressQueue(files) {
        const limit = maxBytes();
        files.forEach(f => {
            if (f.size > limit) return;
            if (!compressQueue.some(q => q.name === f.name && q.size === f.size)) {
                compressQueue.push(f);
            }
        });
        renderCompressQueue();
    }

    function renderCompressQueue() {
        const summary  = document.getElementById('imgCompressQueue');
        const btnClear = document.getElementById('btnImgCompressClear');
        const zone     = document.getElementById('imgCompressZone');

        if (compressQueue.length === 0) {
            if (summary)  { summary.hidden = true; summary.textContent = ''; }
            if (btnClear) btnClear.hidden = true;
            resetZoneText(zone);
            updateCompressBtn();
            return;
        }

        const totalSize = compressQueue.reduce((s, f) => s + f.size, 0);
        const label     = compressQueue.length === 1 ? 'archivo' : 'archivos';

        const title    = zone.querySelector('.img-drop-title');
        const subtitle = zone.querySelector('.img-drop-subtitle');
        if (title)    title.textContent    = compressQueue.length + ' ' + label + ' seleccionados';
        if (subtitle) subtitle.textContent = fmtSize(totalSize) + ' total';

        if (summary)  { summary.hidden = false; summary.textContent = compressQueue.length + ' ' + label + ' · ' + fmtSize(totalSize) + ' total'; }
        if (btnClear) btnClear.hidden = false;

        updateCompressBtn();
    }

    function updateCompressBtn() {
        document.getElementById('btnImgCompress').disabled = compressQueue.length === 0 || !activeMethod;
    }

    async function processCompress() {
        const btn        = document.getElementById('btnImgCompress');
        const quality    = document.getElementById('imgQuality').value;
        const resultsDiv = document.getElementById('imgCompressResults');
        const tbody      = document.getElementById('imgCompressResultsBody');

        btn.disabled   = true;
        btn.innerHTML  = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Procesando...';
        resultsDiv.hidden = true;
        tbody.innerHTML   = '';

        for (const file of compressQueue) {
            const fd = new FormData();
            fd.append('action',  'compress');
            fd.append('method',  activeMethod);
            fd.append('quality', quality);
            fd.append('image',   file);

            let data;
            try {
                const res = await fetch(ENDPOINT, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken }, body: fd });
                data = await res.json();
            } catch (_) {
                data = { success: false, message: 'Error de red' };
            }

            const tr = document.createElement('tr');
            if (data.success) {
                const savings = data.savings_pct > 0
                    ? '<span class="lozenge lozenge-success">-' + data.savings_pct + '%</span>'
                    : '<span class="lozenge lozenge-default">Sin cambios</span>';
                tr.innerHTML =
                    '<td class="text-sm">' + escHtml(data.filename) + '</td>' +
                    '<td class="text-sm">' + fmtSize(data.original_size) + '</td>' +
                    '<td class="text-sm">' + fmtSize(data.result_size) + '</td>' +
                    '<td class="text-sm">' + savings + '</td>' +
                    '<td><button type="button" class="btn-dl" data-dl aria-label="Descargar">' +
                    '<i class="bi bi-download" aria-hidden="true"></i></button></td>';
                const dlBtn = tr.querySelector('[data-dl]');
                const snap  = { b64: data.data, name: data.filename, mime: data.mime };
                dlBtn.addEventListener('click', () => { downloadBase64(snap.b64, snap.name, snap.mime); markDownloaded(dlBtn); });
            } else {
                tr.innerHTML =
                    '<td class="text-sm">' + escHtml(file.name) + '</td>' +
                    '<td colspan="3" class="text-sm text-danger">' + escHtml(data.message || 'Error') + '</td>' +
                    '<td></td>';
            }
            tbody.appendChild(tr);
        }

        resultsDiv.hidden = false;
        btn.innerHTML = '<i class="bi bi-lightning" aria-hidden="true"></i> Procesar';
        updateCompressBtn();
    }

    function clearCompress() {
        compressQueue = [];
        renderCompressQueue();
        document.getElementById('imgCompressResults').hidden        = true;
        document.getElementById('imgCompressResultsBody').innerHTML = '';
    }

    // ── Resize panel ──────────────────────────────────────────────────────────

    function initResizePanel() {
        const zone    = document.getElementById('imgResizeZone');
        const input   = document.getElementById('imgResizeInput');
        const pickBtn = document.getElementById('btnImgResizePick');

        storeZoneText(zone);
        setupDropZone(zone, false, files => setResizeFile(files[0]));
        pickBtn.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            if (input.files[0]) setResizeFile(input.files[0]);
            input.value = '';
        });

        document.querySelectorAll('[data-resize-mode]').forEach(btn => {
            btn.addEventListener('click', () => {
                resizeMode = btn.dataset.resizeMode;
                document.querySelectorAll('[data-resize-mode]').forEach(b => {
                    b.classList.toggle('active', b.dataset.resizeMode === resizeMode);
                });
                document.getElementById('resizeModePercent').hidden = (resizeMode !== 'percent');
                document.getElementById('resizeModeCustom').hidden  = (resizeMode !== 'custom');
            });
        });

        document.querySelectorAll('.resize-pct-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.resize-pct-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });

        document.getElementById('imgResizeW').addEventListener('input', updateResizePreview);
        document.getElementById('imgResizeH').addEventListener('input', updateResizePreview);

        document.getElementById('btnImgResize').addEventListener('click', processResize);
        document.getElementById('btnImgResizeClear').addEventListener('click', clearResize);
    }

    function setResizeFile(file) {
        resizeFile = file;
        const url  = URL.createObjectURL(file);
        const img  = new Image();
        img.onload = () => {
            resizeOrigW = img.naturalWidth;
            resizeOrigH = img.naturalHeight;

            // Mostrar preview
            const previewImg = document.getElementById('resizePreviewImg');
            previewImg.src   = url;
            document.getElementById('resizeOrigDims').textContent =
                resizeOrigW + ' × ' + resizeOrigH + ' px';
            document.getElementById('resizePreviewPlaceholder').style.display = 'none';
            document.getElementById('resizePreviewContent').hidden             = false;

            // Desbloquear opciones
            document.getElementById('resizeOptionsPlaceholder').style.display = 'none';
            document.getElementById('resizeOptionsContent').hidden             = false;

            updateResizePreview();
        };
        img.src = url;

        showFileInZone(document.getElementById('imgResizeZone'), file);
        document.getElementById('btnImgResizeClear').hidden = false;
    }

    function updateResizePreview() {
        if (!resizeOrigW || !resizeOrigH) return;
        [30, 50, 70].forEach(pct => {
            const el = document.getElementById('resizePctDims' + pct);
            if (!el) return;
            const w = Math.round(resizeOrigW * pct / 100);
            const h = Math.round(resizeOrigH * pct / 100);
            el.textContent = w + ' × ' + h + ' px';
        });
    }

    async function processResize() {
        if (!resizeFile) return;
        const btn = document.getElementById('btnImgResize');
        btn.disabled  = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Procesando...';

        const fd = new FormData();
        fd.append('action', 'resize');
        fd.append('image',  resizeFile);

        if (resizeMode === 'percent') {
            const activeBtn = document.querySelector('.resize-pct-btn.active');
            fd.append('percent', activeBtn ? activeBtn.dataset.pct : '50');
        } else {
            const w = document.getElementById('imgResizeW').value;
            const h = document.getElementById('imgResizeH').value;
            if (w) fd.append('width',  w);
            if (h) fd.append('height', h);
        }

        const resultDiv = document.getElementById('imgResizeResult');
        const tbody     = document.getElementById('imgResizeResultBody');
        tbody.innerHTML = '';

        let data;
        try {
            const res = await fetch(ENDPOINT, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken }, body: fd });
            data = await res.json();
        } catch (_) {
            data = { success: false, message: 'Error de red' };
        }

        const tr = document.createElement('tr');
        if (data.success) {
            const renameId = 'resizeRename_' + Date.now();
            tr.innerHTML =
                '<td class="text-sm">' + escHtml(data.filename) + '</td>' +
                '<td class="text-sm">' + escHtml(data.original_dimensions) + ' → ' + escHtml(data.dimensions) + '</td>' +
                '<td><input type="text" class="form-control" id="' + renameId + '" value="' + escHtml(data.filename) + '"></td>' +
                '<td><button type="button" class="btn-dl" data-dl aria-label="Descargar">' +
                '<i class="bi bi-download" aria-hidden="true"></i></button></td>';
            const snap = { b64: data.data, mime: data.mime };
            tr.querySelector('[data-dl]').addEventListener('click', function () {
                const name = document.getElementById(renameId)?.value || data.filename;
                downloadBase64(snap.b64, name, snap.mime);
                markDownloaded(this);
            });
        } else {
            tr.innerHTML =
                '<td class="text-sm">' + escHtml(resizeFile.name) + '</td>' +
                '<td colspan="2" class="text-sm text-danger">' + escHtml(data.message || 'Error') + '</td>' +
                '<td></td>';
        }
        tbody.appendChild(tr);
        resultDiv.hidden = false;

        btn.disabled  = false;
        btn.innerHTML = '<i class="bi bi-aspect-ratio" aria-hidden="true"></i> Procesar';
    }

    function clearResize() {
        resizeFile  = null;
        resizeOrigW = null;
        resizeOrigH = null;

        // Volver a estado bloqueado
        document.getElementById('resizePreviewPlaceholder').style.display = '';
        document.getElementById('resizePreviewContent').hidden             = true;
        document.getElementById('resizePreviewImg').src                    = '';
        document.getElementById('resizeOrigDims').textContent              = '';

        document.getElementById('resizeOptionsPlaceholder').style.display = '';
        document.getElementById('resizeOptionsContent').hidden             = true;
        [30, 50, 70].forEach(pct => {
            const el = document.getElementById('resizePctDims' + pct);
            if (el) el.textContent = '—';
        });

        document.getElementById('imgResizeResult').hidden          = true;
        document.getElementById('imgResizeResultBody').innerHTML   = '';
        document.getElementById('btnImgResizeClear').hidden        = true;
        document.getElementById('resizeDimsPreview').textContent   = '';
        resetZoneText(document.getElementById('imgResizeZone'));
    }

    // ── Convert panel ─────────────────────────────────────────────────────────

    function initConvertPanel() {
        const zone    = document.getElementById('imgConvertZone');
        const input   = document.getElementById('imgConvertInput');
        const pickBtn = document.getElementById('btnImgConvertPick');

        storeZoneText(zone);
        setupDropZone(zone, false, files => setConvertFile(files[0]));
        pickBtn.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            if (input.files[0]) setConvertFile(input.files[0]);
            input.value = '';
        });

        document.querySelectorAll('[data-format]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('[data-format]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.getElementById('convertQualityGroup').hidden = (btn.dataset.format !== 'jpeg');
            });
        });

        document.getElementById('btnImgConvert').addEventListener('click', processConvert);
        document.getElementById('btnImgConvertClear').addEventListener('click', clearConvert);
    }

    function setConvertFile(file) {
        convertFile = file;
        showFileInZone(document.getElementById('imgConvertZone'), file);
        document.getElementById('btnImgConvert').disabled    = false;
        document.getElementById('btnImgConvertClear').hidden = false;
    }

    async function processConvert() {
        if (!convertFile) return;
        const btn = document.getElementById('btnImgConvert');
        btn.disabled  = true;
        btn.innerHTML = '<i class="bi bi-hourglass-split" aria-hidden="true"></i> Procesando...';

        const activeFormat = document.querySelector('[data-format].active');
        const format  = activeFormat?.dataset.format || 'webp';
        const quality = document.getElementById('imgConvertQuality').value;

        const fd = new FormData();
        fd.append('action',  'convert');
        fd.append('format',  format);
        fd.append('quality', quality);
        fd.append('image',   convertFile);

        const resultDiv = document.getElementById('imgConvertResult');
        const tbody     = document.getElementById('imgConvertResultBody');
        tbody.innerHTML = '';

        let data;
        try {
            const res = await fetch(ENDPOINT, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken }, body: fd });
            data = await res.json();
        } catch (_) {
            data = { success: false, message: 'Error de red' };
        }

        const tr = document.createElement('tr');
        if (data.success) {
            const renameId = 'convertRename_' + Date.now();
            tr.innerHTML =
                '<td class="text-sm">' + escHtml(data.filename) + '</td>' +
                '<td class="text-sm"><span class="lozenge lozenge-default">' + escHtml(data.converted_format) + '</span></td>' +
                '<td><input type="text" class="form-control" id="' + renameId + '" value="' + escHtml(data.filename) + '"></td>' +
                '<td><button type="button" class="btn-dl" data-dl aria-label="Descargar">' +
                '<i class="bi bi-download" aria-hidden="true"></i></button></td>';
            const snap = { b64: data.data, mime: data.mime };
            tr.querySelector('[data-dl]').addEventListener('click', function () {
                const name = document.getElementById(renameId)?.value || data.filename;
                downloadBase64(snap.b64, name, snap.mime);
                markDownloaded(this);
            });
        } else {
            tr.innerHTML =
                '<td class="text-sm">' + escHtml(convertFile.name) + '</td>' +
                '<td colspan="2" class="text-sm text-danger">' + escHtml(data.message || 'Error') + '</td>' +
                '<td></td>';
        }
        tbody.appendChild(tr);
        resultDiv.hidden = false;

        btn.disabled  = false;
        btn.innerHTML = '<i class="bi bi-arrow-left-right" aria-hidden="true"></i> Procesar';
    }

    function clearConvert() {
        convertFile = null;
        document.getElementById('imgConvertResult').hidden       = true;
        document.getElementById('imgConvertResultBody').innerHTML = '';
        document.getElementById('btnImgConvert').disabled         = true;
        document.getElementById('btnImgConvertClear').hidden      = true;
        resetZoneText(document.getElementById('imgConvertZone'));
    }

    // ── Drop zones ────────────────────────────────────────────────────────────

    function setupDropZone(zone, multiple, onFiles) {
        const input = zone.querySelector('input[type="file"]');
        zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag-over'); });
        zone.addEventListener('dragleave', ()  => zone.classList.remove('drag-over'));
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            const files = Array.from(e.dataTransfer.files).filter(isImage);
            if (files.length) onFiles(multiple ? files : [files[0]]);
        });
        if (input) {
            zone.addEventListener('click', e => {
                if (e.target.closest('button')) return;
                input.click();
            });
        }
    }

    function isImage(file) {
        return ['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type);
    }

    function storeZoneText(zone) {
        const title    = zone.querySelector('.img-drop-title');
        const subtitle = zone.querySelector('.img-drop-subtitle');
        if (title    && !title.dataset.orig)    title.dataset.orig    = title.textContent;
        if (subtitle && !subtitle.dataset.orig) subtitle.dataset.orig = subtitle.textContent;
    }

    function showFileInZone(zone, file) {
        const title    = zone.querySelector('.img-drop-title');
        const subtitle = zone.querySelector('.img-drop-subtitle');
        if (title)    title.textContent    = file.name;
        if (subtitle) subtitle.textContent = fmtSize(file.size);
        zone.classList.add('has-file');
    }

    function resetZoneText(zone) {
        const title    = zone.querySelector('.img-drop-title');
        const subtitle = zone.querySelector('.img-drop-subtitle');
        if (title    && title.dataset.orig)    title.textContent    = title.dataset.orig;
        if (subtitle && subtitle.dataset.orig) subtitle.textContent = subtitle.dataset.orig;
        zone.classList.remove('has-file');
    }

    // ── Slide panels ──────────────────────────────────────────────────────────

    function initSlidePanels() {
        const overlay = document.getElementById('slidePanelOverlay');

        document.querySelectorAll('.img-method-guide[data-guide]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                openPanel(btn.dataset.guide === 'imagick' ? 'guideImagick' : 'guideImgApi');
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

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Start ─────────────────────────────────────────────────────────────────

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());
