/**
 * Nexus 2.0 — Integrations (iLovePDF / iLoveIMG)
 * Guarda claves encriptadas, prueba conexion, muestra info del plan
 */

(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const T = window.__T__ || {};
    const t = (key, fallback) => T[key] || fallback;

    /** ========================================================
     * Tab switching
     * ======================================================== */

    const integrationTabs   = ['ilp', 'gs', 'gmail', 'smtp'];

    function switchIntegrationTab(name) {
        integrationTabs.forEach(id => {
            const tab   = document.getElementById(`tab-${id}`);
            const panel = document.getElementById(`panel-${id}`);
            const isActive = id === name;
            if (tab)   { tab.classList.toggle('active', isActive); tab.setAttribute('aria-selected', String(isActive)); }
            if (panel) { panel.classList.toggle('d-none', !isActive); panel.hidden = !isActive; }
        });
    }

    document.querySelectorAll('.integration-tabs .tab').forEach(tab => {
        tab.addEventListener('click', () => switchIntegrationTab(tab.dataset.tab));
    });

    /** ========================================================
     * Password toggles (show/hide)
     * ======================================================== */

    function setupPasswordToggle(btnId, inputId) {
        const btn = document.getElementById(btnId);
        const input = document.getElementById(inputId);
        if (!btn || !input) return;

        btn.addEventListener('click', () => {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            const icon = btn.querySelector('i');
            if (icon) {
                icon.className = isPassword ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
            const newLabel = isPassword
                ? t('integrations.hide_value', 'Ocultar')
                : t('integrations.show_value', 'Mostrar');
            btn.setAttribute('aria-label', newLabel);
            btn.setAttribute('data-tooltip', newLabel);
        });
    }

    setupPasswordToggle('togglePassword', 'fIlpPassword');
    setupPasswordToggle('togglePublicKey', 'fIlpPublicKey');
    setupPasswordToggle('toggleSecretKey', 'fIlpSecretKey');
    setupPasswordToggle('toggleGmailPassword', 'fGmailAppPassword');
    setupPasswordToggle('toggleSmtpPass', 'fSmtpPass');

    /** ========================================================
     * Helpers
     * ======================================================== */

    function clearErrors() {
        const emailErr = document.getElementById('fIlpEmailError');
        if (emailErr) emailErr.textContent = '';
        document.querySelectorAll('#integrationsForm .form-control-error').forEach(el => el.classList.remove('form-control-error'));
        const err = document.getElementById('integrationFormError');
        if (err) err.classList.add('d-none');
    }

    async function postAction(data) {
        const fd = new FormData();
        Object.keys(data).forEach(k => fd.append(k, data[k] ?? ''));
        const res = await fetch('includes/api_settings_actions.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: fd,
        });
        return res.json();
    }

    /** ========================================================
     * Guardar
     * ======================================================== */

    async function handleSubmit(e) {
        e.preventDefault();
        clearErrors();

        const form = document.getElementById('integrationsForm');
        const submitBtn = document.getElementById('integrationSubmitBtn');
        if (!form || !submitBtn) return;

        const email = form.ilp_email.value.trim();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            const field = document.getElementById('fIlpEmail');
            const errEl = document.getElementById('fIlpEmailError');
            field.classList.add('form-control-error');
            errEl.textContent = t('integrations.err_email', 'Correo invalido.');
            return;
        }

        const data = {
            action: 'save',
            ilp_email: email,
            ilp_password: form.ilp_password.value,
            ilp_project: form.ilp_project.value.trim(),
            ilp_public_key: form.ilp_public_key.value.trim(),
            ilp_secret_key: form.ilp_secret_key.value.trim(),
        };

        submitBtn.disabled = true;
        const btnText = submitBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('common.saving', 'Guardando...');

        try {
            const result = await postAction(data);
            if (result.success) {
                Toast.success(result.message || t('integrations.saved', 'Configuracion guardada.'));
                setTimeout(() => window.location.reload(), 600);
            } else {
                const err = document.getElementById('integrationFormError');
                document.getElementById('integrationFormErrorText').textContent = result.message || t('integrations.err_generic', 'No se pudo guardar.');
                err.classList.remove('d-none');
                err.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            submitBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    /** ========================================================
     * Probar conexion
     * ======================================================== */

    async function handleTest() {
        const testBtn = document.getElementById('testConnectionBtn');
        const resultBox = document.getElementById('testResult');
        if (!testBtn || !resultBox) return;

        testBtn.disabled = true;
        const btnText = testBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('integrations.testing', 'Probando...');

        resultBox.className = 'integration-test-result';
        resultBox.innerHTML = `
            <div class="integration-test-loading">
                <span class="spinner spinner-sm" aria-hidden="true"></span>
                <span>${t('integrations.testing', 'Probando conexion...')}</span>
            </div>
        `;

        try {
            const result = await postAction({ action: 'test' });
            if (result.success) {
                let planHtml = '';
                if (result.plan && typeof result.plan === 'object' && Object.keys(result.plan).length > 0) {
                    const rows = Object.keys(result.plan).map(k => {
                        const label = escapeHtml(k).replace(/_/g, ' ');
                        const value = escapeHtml(String(result.plan[k]));
                        return `<dt>${label}</dt><dd>${value}</dd>`;
                    }).join('');
                    planHtml = `
                        <div class="integration-test-plan">
                            <h4 class="integration-test-plan-title">${t('integrations.plan_info', 'Informacion del plan')}</h4>
                            <dl class="integration-test-plan-list">${rows}</dl>
                        </div>
                    `;
                }
                resultBox.className = 'integration-test-result integration-test-success';
                resultBox.innerHTML = `
                    <div class="integration-test-header">
                        <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                        <span>${escapeHtml(result.message || t('integrations.test_ok', 'Conexion exitosa.'))}</span>
                    </div>
                    ${planHtml}
                `;
            } else {
                resultBox.className = 'integration-test-result integration-test-error';
                resultBox.innerHTML = `
                    <div class="integration-test-header">
                        <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                        <span>${escapeHtml(result.message || t('integrations.test_fail', 'No se pudo conectar.'))}</span>
                    </div>
                `;
            }
        } catch (err) {
            resultBox.className = 'integration-test-result integration-test-error';
            resultBox.innerHTML = `
                <div class="integration-test-header">
                    <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
                    <span>${escapeHtml(t('common.err_network', 'Error de red.'))}</span>
                </div>
            `;
        } finally {
            testBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    /** ========================================================
     * Gmail — helpers
     * ======================================================== */

    async function gmailPostAction(data) {
        const fd = new FormData();
        Object.keys(data).forEach(k => fd.append(k, data[k] ?? ''));
        const res = await fetch('includes/gmail_actions.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: fd,
        });
        return res.json();
    }

    function showGmailResult(resultBox, success, message) {
        resultBox.className = 'integration-test-result ' + (success ? 'integration-test-success' : 'integration-test-error');
        const icon = success ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
        resultBox.innerHTML = `
            <div class="integration-test-header">
                <i class="bi ${icon}" aria-hidden="true"></i>
                <span>${escapeHtml(message)}</span>
            </div>
        `;
    }

    /** ========================================================
     * Gmail — guardar
     * ======================================================== */

    async function handleGmailSubmit(e) {
        e.preventDefault();

        const form      = document.getElementById('gmailForm');
        const submitBtn = document.getElementById('gmailSubmitBtn');
        const errBox    = document.getElementById('gmailFormError');
        const errText   = document.getElementById('gmailFormErrorText');
        if (!form || !submitBtn) return;

        const email = form.gmail_email.value.trim();
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            const field = document.getElementById('fGmailEmail');
            const errEl = document.getElementById('fGmailEmailError');
            field.classList.add('form-control-error');
            errEl.textContent = t('integrations.err_email', 'Correo invalido.');
            return;
        }

        errBox.classList.add('d-none');

        const data = {
            action:              'save',
            gmail_email:         email,
            gmail_app_password:  form.gmail_app_password.value,
            gmail_label:         form.gmail_label.value.trim() || 'Nexus',
        };

        submitBtn.disabled = true;
        const btnText      = submitBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('common.saving', 'Guardando...');

        try {
            const result = await gmailPostAction(data);
            if (result.success) {
                Toast.success(result.message || t('integrations.saved', 'Configuracion guardada.'));
                setTimeout(() => window.location.reload(), 600);
            } else {
                errText.textContent = result.message || t('integrations.err_generic', 'No se pudo guardar.');
                errBox.classList.remove('d-none');
                errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        } catch {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            submitBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    /** ========================================================
     * Gmail — probar conexion
     * ======================================================== */

    async function handleGmailTest() {
        const testBtn   = document.getElementById('gmailTestBtn');
        const resultBox = document.getElementById('gmailTestResult');
        if (!testBtn || !resultBox) return;

        testBtn.disabled = true;
        const btnText      = testBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('integrations.testing', 'Probando...');

        resultBox.className = 'integration-test-result';
        resultBox.innerHTML = `
            <div class="integration-test-loading">
                <span class="spinner spinner-sm" aria-hidden="true"></span>
                <span>${t('integrations.testing', 'Probando conexion...')}</span>
            </div>
        `;

        try {
            const result = await gmailPostAction({ action: 'test' });
            showGmailResult(resultBox, result.success, result.message || (result.success ? t('integrations.test_ok', 'Conexion exitosa.') : t('integrations.test_fail', 'No se pudo conectar.')));
        } catch {
            showGmailResult(resultBox, false, t('common.err_network', 'Error de red.'));
        } finally {
            testBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    /** ========================================================
     * Gmail — sincronizar
     * ======================================================== */

    async function handleGmailSync() {
        const syncBtn   = document.getElementById('gmailSyncBtn');
        const resultBox = document.getElementById('gmailTestResult');
        if (!syncBtn || !resultBox) return;

        syncBtn.disabled = true;
        const btnText      = syncBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('integrations.gmail_syncing', 'Sincronizando...');

        resultBox.className = 'integration-test-result';
        resultBox.innerHTML = `
            <div class="integration-test-loading">
                <span class="spinner spinner-sm" aria-hidden="true"></span>
                <span>${t('integrations.gmail_syncing', 'Sincronizando...')}</span>
            </div>
        `;

        try {
            const result = await gmailPostAction({ action: 'sync' });
            showGmailResult(resultBox, result.success, result.message || '');
        } catch {
            showGmailResult(resultBox, false, t('common.err_network', 'Error de red.'));
        } finally {
            syncBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    /** ========================================================
     * Ghostscript — calidad
     * ======================================================== */

    const gsDescriptions = {
        screen:   'Tamaño mínimo, calidad baja. Ideal para visualización en pantalla o adjunto de email liviano.',
        ebook:    'Balance óptimo entre calidad y tamaño. Recomendado para la mayoría de casos.',
        printer:  'Alta calidad para impresión general. Genera archivos más grandes.',
        prepress: 'Máxima calidad, preserva perfil de color. Para publicación profesional.',
        default:  'Aplica compresión mínima sin modificar imágenes. Útil para PDFs con gráficos vectoriales.',
    };

    const gsSelect = document.getElementById('fGsQuality');
    if (gsSelect) {
        gsSelect.addEventListener('change', () => {
            const val = gsSelect.value;
            const descEl = document.getElementById('gsQualityDescText');
            if (descEl) descEl.textContent = gsDescriptions[val] || '';
            Object.keys(gsDescriptions).forEach(k => {
                const row = document.getElementById('gsRow-' + k);
                if (!row) return;
                row.style.fontWeight = k === val ? 'var(--ds-font-weight-semibold)' : '';
                row.style.background = k === val ? 'var(--ds-background-selected)' : '';
            });
        });
    }

    async function handleGsSubmit(e) {
        e.preventDefault();
        const form      = document.getElementById('gsForm');
        const submitBtn = document.getElementById('gsSubmitBtn');
        if (!form || !submitBtn) return;

        submitBtn.disabled = true;
        const btnText      = submitBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('common.saving', 'Guardando...');

        try {
            const result = await postAction({ action: 'save_gs', gs_quality: form.gs_quality.value });
            if (result.success) {
                Toast.success(result.message || 'Configuración guardada.');
            } else {
                Toast.error(result.message || 'No se pudo guardar.');
            }
        } catch {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            submitBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    /** ========================================================
     * Bindings
     * ======================================================== */

    document.getElementById('integrationsForm').addEventListener('submit', handleSubmit);

    const testBtn = document.getElementById('testConnectionBtn');
    if (testBtn) testBtn.addEventListener('click', handleTest);

    document.getElementById('gsForm')?.addEventListener('submit', handleGsSubmit);
    document.getElementById('gmailForm')?.addEventListener('submit', handleGmailSubmit);
    document.getElementById('gmailTestBtn')?.addEventListener('click', handleGmailTest);
    document.getElementById('gmailSyncBtn')?.addEventListener('click', handleGmailSync);

    /** ========================================================
     * SMTP — guardar
     * ======================================================== */

    async function handleSmtpSubmit(e) {
        e.preventDefault();
        const form      = document.getElementById('smtpForm');
        const submitBtn = document.getElementById('smtpSubmitBtn');
        if (!form || !submitBtn) return;

        submitBtn.disabled = true;
        const btnText      = submitBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('common.saving', 'Guardando...');

        const fd = new FormData();
        fd.append('action',          'smtp_save');
        fd.append('csrf_token',      csrfToken);
        fd.append('smtp_host',       form.smtp_host.value.trim());
        fd.append('smtp_port',       form.smtp_port.value);
        fd.append('smtp_user',       form.smtp_user.value.trim());
        fd.append('smtp_pass',       form.smtp_pass.value);
        fd.append('smtp_secure',     form.smtp_secure.value);
        fd.append('smtp_from',       form.smtp_from.value.trim());
        fd.append('smtp_from_name',  form.smtp_from_name.value.trim());

        try {
            const res    = await fetch('includes/api_settings_actions.php', { method: 'POST', body: fd });
            const result = await res.json();
            if (result.success) {
                Toast.success(result.message || t('integrations.saved', 'Configuración guardada.'));
                setTimeout(() => window.location.reload(), 600);
            } else {
                Toast.error(result.message || t('integrations.err_generic', 'No se pudo guardar.'));
            }
        } catch {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            submitBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    /** ========================================================
     * SMTP — probar conexion
     * ======================================================== */

    async function handleSmtpTest() {
        const testBtn   = document.getElementById('smtpTestBtn');
        const resultBox = document.getElementById('smtpTestResult');
        if (!testBtn || !resultBox) return;

        testBtn.disabled = true;
        const btnText      = testBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('integrations.testing', 'Probando...');

        resultBox.className = 'integration-test-result';
        resultBox.innerHTML = `
            <div class="integration-test-loading">
                <span class="spinner spinner-sm" aria-hidden="true"></span>
                <span>${t('integrations.testing', 'Enviando correo de prueba...')}</span>
            </div>`;

        const fd = new FormData();
        fd.append('action',     'smtp_test');
        fd.append('csrf_token', csrfToken);

        try {
            const res    = await fetch('includes/api_settings_actions.php', { method: 'POST', body: fd });
            const result = await res.json();
            const icon = result.success ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
            resultBox.className = 'integration-test-result ' + (result.success ? 'integration-test-success' : 'integration-test-error');
            resultBox.innerHTML = `
                <div class="integration-test-header">
                    <i class="bi ${icon}" aria-hidden="true"></i>
                    <span>${escapeHtml(result.message || '')}</span>
                </div>`;
        } catch {
            resultBox.className = 'integration-test-result integration-test-error';
            resultBox.innerHTML = `<div class="integration-test-header"><i class="bi bi-x-circle-fill" aria-hidden="true"></i><span>${escapeHtml(t('common.err_network', 'Error de red.'))}</span></div>`;
        } finally {
            testBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    document.getElementById('smtpForm')?.addEventListener('submit', handleSmtpSubmit);
    document.getElementById('smtpTestBtn')?.addEventListener('click', handleSmtpTest);

})();
