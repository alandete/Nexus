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
     * Bindings
     * ======================================================== */

    document.getElementById('integrationsForm').addEventListener('submit', handleSubmit);

    const testBtn = document.getElementById('testConnectionBtn');
    if (testBtn) testBtn.addEventListener('click', handleTest);

})();
