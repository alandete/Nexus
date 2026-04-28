(function () {
    'use strict';

    const ENDPOINT  = 'includes/alliance_actions.php';
    const cfg       = window.__ALLIANCE_PAGE__ || {};
    const csrfToken = cfg.csrf || document.querySelector('meta[name="csrf-token"]')?.content || '';
    const t         = (key, fallback) => cfg.t?.[key] || fallback || key;

    let activeLms     = null;
    let activeSection = cfg.sections?.[0] || null;

    // ── Init ──────────────────────────────────────────────────────────────────

    function init() {
        initLmsSelector();
        initSectionTabs();
        initUrlValidation();
        initToggleGroups();
        initActionBar();
        initResultPanel();
        initEvalTableHover();
    }

    // ── LMS selector ─────────────────────────────────────────────────────────

    function initLmsSelector() {
        document.querySelectorAll('.alliance-lms-btn').forEach(btn => {
            btn.addEventListener('click', () => selectLms(btn.dataset.lms));
        });
    }

    function selectLms(lms) {
        activeLms = lms;

        document.querySelectorAll('.alliance-lms-btn').forEach(btn => {
            btn.classList.toggle('is-selected', btn.dataset.lms === lms);
            btn.setAttribute('aria-pressed', btn.dataset.lms === lms ? 'true' : 'false');
        });

        // Comprobar si hay plantillas para la sección activa
        refreshContent();
    }

    function refreshContent() {
        if (!activeLms || !activeSection) return;

        const sectionAvailable = cfg.sectionsLms?.[activeLms]?.[activeSection] ?? false;
        const content  = document.getElementById('allianceContent');
        const pending  = document.getElementById('alliancePendingNotice');

        if (content)  content.hidden  = !sectionAvailable;
        if (pending)  pending.hidden  = sectionAvailable;
    }

    // ── Section tabs ──────────────────────────────────────────────────────────

    function initSectionTabs() {
        document.querySelectorAll('#sectionTabs .tab[data-section]').forEach(btn => {
            btn.addEventListener('click', () => activateSection(btn.dataset.section));
        });
    }

    function activateSection(sec) {
        activeSection = sec;

        document.querySelectorAll('#sectionTabs .tab[data-section]').forEach(btn => {
            const isThis = btn.dataset.section === sec;
            btn.classList.toggle('active', isThis);
            btn.setAttribute('aria-selected', isThis ? 'true' : 'false');
        });

        (cfg.sections || []).forEach(s => {
            const panel = document.getElementById('tabPanel-' + s);
            if (panel) panel.classList.toggle('d-none', s !== sec);
        });

        if (activeLms) refreshContent();
    }

    // ── URL validation ────────────────────────────────────────────────────────

    function initUrlValidation() {
        const root = document.getElementById('allianceContent');
        if (!root) return;
        root.addEventListener('input', e => {
            if (e.target.classList.contains('alliance-url-field')) validateUrlField(e.target);
        });
        root.addEventListener('blur', e => {
            if (e.target.classList.contains('alliance-url-field')) validateUrlField(e.target);
        }, true);
    }

    function validateUrlField(input) {
        const val = input.value.trim();
        if (!val) {
            input.classList.remove('is-valid', 'is-invalid');
            return;
        }
        try {
            new URL(val);
            input.classList.add('is-valid');
            input.classList.remove('is-invalid');
        } catch (_) {
            input.classList.add('is-invalid');
            input.classList.remove('is-valid');
        }
    }

    // ── Action bar ────────────────────────────────────────────────────────────

    function initActionBar() {
        const btnProcess = document.getElementById('btnAllianceProcess');
        const btnClean   = document.getElementById('btnAllianceClean');
        if (btnProcess) btnProcess.addEventListener('click', handleProcess);
        if (btnClean)   btnClean.addEventListener('click', handleClean);
    }

    // ── Process ───────────────────────────────────────────────────────────────

    async function handleProcess() {
        if (!activeLms) {
            showToast(t('selectLms', 'Seleccione un LMS'), 'warning');
            return;
        }
        if (!activeSection) {
            showToast(t('noActiveSection', 'No hay sección activa'), 'warning');
            return;
        }

        const data = collectFormData();
        if (!data) return;

        setProcessing(true);

        try {
            const fd = new FormData();
            fd.append('action',   'process');
            fd.append('alliance', cfg.slug || 'unis');
            fd.append('lms',      activeLms);
            fd.append('section',  activeSection);
            for (const [k, v] of Object.entries(data)) fd.append(k, v);

            const res  = await fetch(ENDPOINT, {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body:    fd,
            });
            const json = await res.json();

            renderResult(json);
        } catch (_) {
            showToast(t('errorConnection', 'Error de conexión'), 'danger');
        } finally {
            setProcessing(false);
        }
    }

    function collectFormData() {
        const panel = document.getElementById('tabPanel-' + activeSection);
        if (!panel) return null;

        const data = {};
        panel.querySelectorAll('input, textarea, select').forEach(el => {
            if (!el.name || el.disabled) return;
            if (el.type === 'checkbox') {
                if (el.checked) data[el.name] = el.value || 'on';
            } else {
                data[el.name] = el.value;
            }
        });
        return data;
    }

    function setProcessing(loading) {
        const btn   = document.getElementById('btnAllianceProcess');
        const label = document.getElementById('btnAllianceProcessLabel');
        if (!btn) return;
        btn.disabled = loading;
        if (label) label.textContent = loading
            ? t('processing', 'Procesando...')
            : t('process', 'Procesar');
    }

    // ── Result panel ──────────────────────────────────────────────────────────

    function initResultPanel() {
        const btnCopy    = document.getElementById('btnAllianceCopy');
        const overlay    = document.getElementById('slidePanelOverlay');
        if (btnCopy) btnCopy.addEventListener('click', handleCopy);
        document.querySelectorAll('#allianceResultPanel .slide-panel-close').forEach(btn => {
            btn.addEventListener('click', closePanel);
        });
        if (overlay) overlay.addEventListener('click', closePanel);
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closePanel(); });
    }

    function renderResult(json) {
        const panel        = document.getElementById('allianceResultPanel');
        const title        = document.getElementById('allianceResultTitle');
        const warningsBox  = document.getElementById('allianceWarnings');
        const warningsList = document.getElementById('allianceWarningsList');
        const resultGroup  = document.getElementById('allianceResultGroup');
        const textarea     = document.getElementById('allianceResultHtml');

        if (!panel) return;

        const titleKey = 'panelTitle' + capitalize(activeSection);
        if (title) title.textContent = t(titleKey, 'Resultado');

        const warnings    = json.warnings || [];
        const emptyFields = json.emptyFields || [];

        if (warningsBox)  warningsBox.hidden = warnings.length === 0;
        if (warningsList) {
            warningsList.innerHTML = warnings.map(w =>
                '<div class="alliance-warning-item">' +
                '<i class="bi bi-exclamation-triangle" aria-hidden="true"></i> ' + esc(w) + '</div>'
            ).join('');
        }

        document.querySelectorAll('.alliance-url-field, .alliance-eval-field').forEach(el => {
            el.classList.remove('is-invalid');
        });
        emptyFields.forEach(name => {
            const el = document.querySelector(`[name="${name}"]`);
            if (el) el.classList.add('is-invalid');
        });

        const html = json.html || '';
        if (resultGroup) resultGroup.hidden = !html;
        if (textarea)    textarea.value = html;

        openPanel('allianceResultPanel');
    }

    async function handleCopy() {
        const textarea = document.getElementById('allianceResultHtml');
        const btn      = document.getElementById('btnAllianceCopy');
        if (!textarea || !btn) return;

        try {
            await navigator.clipboard.writeText(textarea.value);
            const original = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg" aria-hidden="true"></i> ' + t('btnCopied', '¡Copiado!');
            btn.classList.add('btn-success');
            setTimeout(() => {
                btn.innerHTML = original;
                btn.classList.remove('btn-success');
            }, 2000);
        } catch (_) {
            textarea.select();
            document.execCommand('copy');
        }
    }

    // ── Toggle groups ─────────────────────────────────────────────────────────

    function initToggleGroups() {
        document.querySelectorAll('.alliance-toggle-input').forEach(cb => {
            cb.addEventListener('change', () => applyToggleGroup(cb));
        });
    }

    function applyToggleGroup(cb) {
        const fields = cb.dataset.controls ? document.getElementById(cb.dataset.controls) : null;
        if (!fields) return;
        fields.hidden = !cb.checked;
        fields.querySelectorAll('input, textarea, select').forEach(el => {
            el.disabled = !cb.checked;
        });
    }

    // ── Clean ─────────────────────────────────────────────────────────────────

    function handleClean() {
        showToastConfirm(t('confirmClean', '¿Limpiar todos los campos?'), () => {
            document.querySelectorAll('.tab-content input, .tab-content textarea, .tab-content select').forEach(el => {
                if (el.tagName === 'SELECT') el.selectedIndex = 0;
                else el.value = '';
                el.classList.remove('is-valid', 'is-invalid');
            });
            document.querySelectorAll('.alliance-toggle-input').forEach(cb => {
                cb.checked = false;
                applyToggleGroup(cb);
            });
        }, { labelConfirm: t('btnClean', 'Limpiar'), labelCancel: t('btnCancel', 'Cancelar') });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function capitalize(str) {
        return str ? str.charAt(0).toUpperCase() + str.slice(1) : '';
    }

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function openPanel(id) {
        const panel   = document.getElementById(id);
        const overlay = document.getElementById('slidePanelOverlay');
        if (!panel) return;
        panel.classList.add('active');
        panel.setAttribute('aria-hidden', 'false');
        if (overlay) overlay.classList.add('active');
    }

    function closePanel() {
        const panel   = document.getElementById('allianceResultPanel');
        const overlay = document.getElementById('slidePanelOverlay');
        if (panel)   { panel.classList.remove('active'); panel.setAttribute('aria-hidden', 'true'); }
        if (overlay) overlay.classList.remove('active');
    }

    function showToast(msg, type) {
        window.Toast?.show?.(msg, type);
    }

    function showToastConfirm(msg, onConfirm, opts) {
        window.Toast?.confirm?.(msg, onConfirm, opts);
    }

    // ── Eval table hover ─────────────────────────────────────────────────────
    // tr:hover no funciona bien con rowspan=2; manejamos el highlight por pares

    function initEvalTableHover() {
        const tbody = document.querySelector('.alliance-eval-table tbody');
        if (!tbody) return;

        tbody.querySelectorAll('.eval-row-first').forEach(row => {
            const next = row.nextElementSibling;
            if (!next?.classList.contains('eval-row-second')) return;

            const pair = [row, next];
            const on  = () => pair.forEach(r => r.classList.add('eval-unit-hover'));
            const off = () => pair.forEach(r => r.classList.remove('eval-unit-hover'));

            pair.forEach(r => {
                r.addEventListener('mouseenter', on);
                r.addEventListener('mouseleave', off);
            });
        });
    }

    // ── Start ─────────────────────────────────────────────────────────────────
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

}());
