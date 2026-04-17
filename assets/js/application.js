/**
 * Nexus 2.0 — Application Settings
 * Guarda configuracion, preview de colores en vivo, gestion de assets
 */

(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const T = window.__T__ || {};
    const t = (key, fallback) => T[key] || fallback;

    function hexToRgb(hex) {
        const m = /^#?([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/.exec(hex);
        if (!m) return null;
        return parseInt(m[1], 16) + ', ' + parseInt(m[2], 16) + ', ' + parseInt(m[3], 16);
    }

    function clearErrors() {
        ['fAppNameError', 'fContactEmailError', 'fWebsiteError'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = '';
        });
        document.querySelectorAll('.application-form .form-control-error').forEach(el => el.classList.remove('form-control-error'));
        const err = document.getElementById('applicationFormError');
        if (err) err.classList.add('d-none');
    }

    function setFieldError(fieldId, errorId, message) {
        const field = document.getElementById(fieldId);
        const error = document.getElementById(errorId);
        if (field) field.classList.add('form-control-error');
        if (error) error.textContent = message;
    }

    /** ========================================================
     * Color pickers: sync + preview live
     * ======================================================== */

    function setupColorField(pickerId, hexId, previewVar) {
        const picker = document.getElementById(pickerId);
        const hex = document.getElementById(hexId);
        if (!picker || !hex) return;

        const applyPreview = (color) => {
            if (!/^#[0-9A-Fa-f]{6}$/.test(color)) return;
            document.documentElement.style.setProperty(`--preview-${previewVar}`, color);
            if (previewVar === 'brand') {
                const rgb = hexToRgb(color);
                if (rgb) document.documentElement.style.setProperty('--preview-brand-rgb', rgb);
            }
        };

        picker.addEventListener('input', () => {
            hex.value = picker.value;
            applyPreview(picker.value);
        });
        hex.addEventListener('input', () => {
            if (/^#[0-9A-Fa-f]{6}$/.test(hex.value)) {
                picker.value = hex.value;
                applyPreview(hex.value);
            }
        });
        applyPreview(hex.value);
    }

    setupColorField('fBrandColor', 'fBrandColorHex', 'brand');
    setupColorField('fAccentColor', 'fAccentColorHex', 'accent');

    /** ========================================================
     * Logo / Favicon: preview y remove
     * ======================================================== */

    function setupAssetUpload(inputId, previewId, placeholderId, removeFlagId, removeBtnId, iconFallback) {
        const input = document.getElementById(inputId);
        const removeFlag = document.getElementById(removeFlagId);
        const removeBtn = document.getElementById(removeBtnId);

        if (input) {
            input.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;
                if (removeFlag) removeFlag.value = '0';

                const reader = new FileReader();
                reader.onload = (ev) => {
                    let preview = document.getElementById(previewId);
                    const placeholder = document.getElementById(placeholderId);

                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = previewId;
                        preview.alt = '';
                        if (placeholder) placeholder.replaceWith(preview);
                    }
                    preview.src = ev.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        if (removeBtn && removeFlag) {
            removeBtn.addEventListener('click', () => {
                removeFlag.value = '1';
                if (input) input.value = '';
                const preview = document.getElementById(previewId);
                if (preview) {
                    const placeholder = document.createElement('span');
                    placeholder.id = placeholderId;
                    placeholder.className = 'asset-upload-placeholder';
                    placeholder.setAttribute('aria-hidden', 'true');
                    placeholder.innerHTML = `<i class="bi ${iconFallback}"></i>`;
                    preview.replaceWith(placeholder);
                }
                removeBtn.remove();
            });
        }
    }

    setupAssetUpload('logoInput', 'logoPreview', 'logoPlaceholder', 'removeLogo', 'removeLogoBtn', 'bi-hexagon-fill');
    setupAssetUpload('faviconInput', 'faviconPreview', 'faviconPlaceholder', 'removeFavicon', 'removeFaviconBtn', 'bi-window');

    /** ========================================================
     * Modo mantenimiento: toggle visibilidad del cuerpo
     * ======================================================== */

    const maintCheckbox = document.getElementById('fMaintenance');
    const maintBox = document.getElementById('maintenanceBox');
    if (maintCheckbox && maintBox) {
        const sync = () => maintBox.classList.toggle('is-active', maintCheckbox.checked);
        maintCheckbox.addEventListener('change', sync);
        sync();
    }

    /** ========================================================
     * Submit
     * ======================================================== */

    async function handleSubmit(e) {
        e.preventDefault();
        clearErrors();

        const form = document.getElementById('applicationForm');
        const submitBtn = document.getElementById('applicationSubmitBtn');
        if (!form || !submitBtn) return;

        // Validacion basica
        const appName = form.app_name.value.trim();
        const email = form.contact_email.value.trim();
        const website = form.website.value.trim();
        let hasError = false;

        if (!appName) {
            setFieldError('fAppName', 'fAppNameError', t('application.err_app_name', 'El nombre de la aplicacion es obligatorio.'));
            hasError = true;
        }
        if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setFieldError('fContactEmail', 'fContactEmailError', t('application.err_email', 'Correo invalido.'));
            hasError = true;
        }
        if (website && !/^https?:\/\/.+/.test(website)) {
            setFieldError('fWebsite', 'fWebsiteError', t('application.err_website', 'URL invalida. Debe empezar con http:// o https://'));
            hasError = true;
        }
        if (hasError) return;

        // FormData para enviar archivos
        const fd = new FormData(form);
        fd.append('action', 'save');

        submitBtn.disabled = true;
        const btnText = submitBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('common.saving', 'Guardando...');

        try {
            const res = await fetch('includes/projectinfo_actions.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: fd,
            });
            const result = await res.json();
            if (result.success) {
                Toast.success(result.message || t('application.saved', 'Cambios guardados.'));
                setTimeout(() => window.location.reload(), 600);
            } else {
                const err = document.getElementById('applicationFormError');
                document.getElementById('applicationFormErrorText').textContent = result.message || t('application.err_generic', 'No se pudo guardar.');
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

    document.getElementById('applicationForm').addEventListener('submit', handleSubmit);

    // Reset = recargar
    document.getElementById('applicationResetBtn').addEventListener('click', () => {
        window.location.reload();
    });

})();
