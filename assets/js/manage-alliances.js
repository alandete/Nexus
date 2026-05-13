/**
 * Nexus 2.0 — Manage Alliances
 * CRUD con vista tarjetas/tabla, busqueda, filtros, gestion de archivos
 */

(function () {
    'use strict';

    const alliancesData = window.__ALLIANCES_DATA__ || {};
    const usersAvailable = window.__ALLIANCE_USERS__ || [];
    const canWrite = window.__ALLIANCES_CAN_WRITE__ || false;
    const canDelete = window.__ALLIANCES_CAN_DELETE__ || false;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const T = window.__T__ || {};
    const t = (key, fallback) => T[key] || fallback;

    // Paises LATAM + Espana (con emoji flag)
    const COUNTRIES = [
        { code: 'AR', name: 'Argentina' },
        { code: 'BO', name: 'Bolivia' },
        { code: 'BR', name: 'Brasil' },
        { code: 'CL', name: 'Chile' },
        { code: 'CO', name: 'Colombia' },
        { code: 'CR', name: 'Costa Rica' },
        { code: 'CU', name: 'Cuba' },
        { code: 'DO', name: 'Republica Dominicana' },
        { code: 'EC', name: 'Ecuador' },
        { code: 'SV', name: 'El Salvador' },
        { code: 'ES', name: 'Espana' },
        { code: 'GT', name: 'Guatemala' },
        { code: 'HN', name: 'Honduras' },
        { code: 'MX', name: 'Mexico' },
        { code: 'NI', name: 'Nicaragua' },
        { code: 'PA', name: 'Panama' },
        { code: 'PY', name: 'Paraguay' },
        { code: 'PE', name: 'Peru' },
        { code: 'PR', name: 'Puerto Rico' },
        { code: 'UY', name: 'Uruguay' },
        { code: 'VE', name: 'Venezuela' },
    ];

    function countryFlag(code) {
        if (!code || code.length !== 2) return '';
        const cp = code.toUpperCase().split('').map(c => 0x1F1E6 + c.charCodeAt(0) - 65);
        return String.fromCodePoint(...cp);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function fileUrl(slug, filename) {
        return `assets/uploads/alliances/${slug}/${filename}`;
    }

    async function postAction(data, withFile = false) {
        const fd = new FormData();
        Object.keys(data).forEach(k => {
            if (data[k] instanceof File) fd.append(k, data[k]);
            else fd.append(k, data[k] ?? '');
        });
        const res = await fetch('includes/manage_alliances_actions.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: fd,
        });
        return res.json();
    }

    /** ========================================================
     * Filtros y vista
     * ======================================================== */

    function applyFilters() {
        const searchInput = document.getElementById('allianceSearch');
        const statusFilter = document.getElementById('allianceStatusFilter');
        const grid = document.getElementById('alliancesGrid');
        const emptyFiltered = document.getElementById('alliancesEmptyFiltered');
        if (!grid) return;

        const query = (searchInput?.value || '').trim().toLowerCase();
        const status = statusFilter?.value || '';

        const cards = grid.querySelectorAll('.alliance-card');
        let visibleCount = 0;

        cards.forEach(card => {
            const cardSearch = card.dataset.search || '';
            const cardStatus = card.dataset.status || '';
            const matchSearch = !query || cardSearch.includes(query);
            const matchStatus = !status || cardStatus === status;
            const visible = matchSearch && matchStatus;
            card.classList.toggle('d-none', !visible);
            if (visible) visibleCount++;
        });

        if (emptyFiltered) {
            const noResults = visibleCount === 0 && cards.length > 0;
            emptyFiltered.classList.toggle('d-none', !noResults);
            grid.classList.toggle('d-none', noResults);
        }
    }

    ['allianceSearch', 'allianceStatusFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', applyFilters);
        if (el) el.addEventListener('change', applyFilters);
    });

    // Toggle vista
    document.querySelectorAll('.view-toggle-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.view-toggle-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            const view = btn.dataset.view;
            const grid = document.getElementById('alliancesGrid');
            if (grid) {
                grid.classList.toggle('alliances-grid-table', view === 'table');
            }
        });
    });

    /** ========================================================
     * Formulario
     * ======================================================== */

    function buildResponsibleSection(prefix, label, data) {
        const isUser = !!data.is_user;
        const userOptions = usersAvailable.map(u =>
            `<option value="${escapeHtml(u.username)}" ${data.username === u.username ? 'selected' : ''}>${escapeHtml(u.name)} (${escapeHtml(u.email)})</option>`
        ).join('');

        return `
            <fieldset class="form-fieldset">
                <legend class="form-fieldset-legend">${label}</legend>
                <div class="form-field-tabs" role="tablist">
                    <label class="form-field-tab">
                        <input type="radio" name="${prefix}_type" value="user" ${isUser ? 'checked' : ''}>
                        <span>${t('alliances.resp_user', 'Usuario del sistema')}</span>
                    </label>
                    <label class="form-field-tab">
                        <input type="radio" name="${prefix}_type" value="external" ${!isUser ? 'checked' : ''}>
                        <span>${t('alliances.resp_external', 'Externo')}</span>
                    </label>
                </div>
                <div class="form-field-user-section ${isUser ? '' : 'd-none'}" data-section="${prefix}-user">
                    <select name="${prefix}_username" class="form-control">
                        <option value="">${t('alliances.resp_select_user', 'Seleccionar usuario...')}</option>
                        ${userOptions}
                    </select>
                </div>
                <div class="form-field-external-section ${isUser ? 'd-none' : ''}" data-section="${prefix}-external">
                    <div class="form-group">
                        <input type="text" name="${prefix}_name" class="form-control"
                               placeholder="${t('alliances.resp_name_placeholder', 'Nombre')}"
                               value="${escapeHtml(!isUser ? (data.name || '') : '')}">
                    </div>
                    <div class="form-group">
                        <input type="email" name="${prefix}_email" class="form-control"
                               placeholder="${t('alliances.resp_email_placeholder', 'Correo electronico')}"
                               value="${escapeHtml(!isUser ? (data.email || '') : '')}">
                    </div>
                </div>
            </fieldset>
        `;
    }

    function buildFilesSection(slug, files) {
        const filesList = (files || []).map(f => {
            const icon = getFileIcon(f.filename);
            return `
                <li class="alliance-file-item" data-filename="${escapeHtml(f.filename)}">
                    <i class="bi ${icon.class} alliance-file-icon" aria-hidden="true"></i>
                    <a href="${fileUrl(slug, f.filename)}" target="_blank" rel="noopener" class="alliance-file-name">
                        ${escapeHtml(f.name)}
                    </a>
                    <span class="alliance-file-date">${escapeHtml(f.uploaded_at || '')}</span>
                    <a href="${fileUrl(slug, f.filename)}" download class="btn-icon" data-tooltip="${t('common.download', 'Descargar')}" aria-label="${t('common.download', 'Descargar')}">
                        <i class="bi bi-download" aria-hidden="true"></i>
                    </a>
                    <button type="button" class="btn-icon btn-icon-danger btn-delete-alliance-file"
                            data-slug="${escapeHtml(slug)}" data-filename="${escapeHtml(f.filename)}"
                            data-tooltip="${t('common.delete', 'Eliminar')}" aria-label="${t('common.delete', 'Eliminar')}">
                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                    </button>
                </li>
            `;
        }).join('');

        return `
            <fieldset class="form-fieldset">
                <legend class="form-fieldset-legend">${t('alliances.files', 'Archivos de apoyo')}</legend>
                <ul class="alliance-files-list" id="allianceFilesList">
                    ${filesList}
                </ul>
                <div class="alliance-file-upload">
                    <input type="file" id="allianceFileInput"
                           accept=".pdf,.xlsx,.xls,.docx,.doc,.txt"
                           class="form-control flex-1">
                    <button type="button" class="btn btn-default" id="btnUploadAllianceFile">
                        <i class="bi bi-upload" aria-hidden="true"></i>
                        ${t('alliances.upload', 'Subir')}
                    </button>
                </div>
                <p class="form-helper">${t('alliances.files_help', 'PDF, Excel, Word o TXT. Max 5 archivos, 5 MB cada uno.')}</p>
                <p class="form-error d-none" id="allianceFileError" aria-live="polite"></p>
            </fieldset>
        `;
    }

    function getFileIcon(filename) {
        const ext = (filename || '').split('.').pop().toLowerCase();
        const map = {
            pdf: { class: 'bi-file-earmark-pdf', color: 'danger' },
            xlsx: { class: 'bi-file-earmark-excel', color: 'success' },
            xls: { class: 'bi-file-earmark-excel', color: 'success' },
            docx: { class: 'bi-file-earmark-word', color: 'info' },
            doc: { class: 'bi-file-earmark-word', color: 'info' },
            txt: { class: 'bi-file-earmark-text', color: 'default' },
        };
        return map[ext] || { class: 'bi-file-earmark', color: 'default' };
    }

    function buildAllianceForm(mode, alliance) {
        const isCreate = mode === 'create';
        const title = isCreate ? t('alliances.form_create', 'Nueva alianza') : t('alliances.form_edit', 'Editar alianza');

        const countryOptions = `<option value="">${t('alliances.country_none', 'Sin especificar')}</option>` +
            COUNTRIES.map(c =>
                `<option value="${c.code}" ${alliance.country === c.code ? 'selected' : ''}>${countryFlag(c.code)} ${escapeHtml(c.name)}</option>`
            ).join('');

        const color = alliance.color || '#585d8a';

        const html = `
            <form id="allianceForm" novalidate>
                <input type="hidden" name="action" value="${isCreate ? 'create' : 'update'}">
                <input type="hidden" name="slug" value="${escapeHtml(alliance.slug || '')}">

                <!-- Identidad -->
                <div class="form-group">
                    <label for="fAName" class="form-label">
                        ${t('alliances.field_name', 'Nombre corto')} <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="fAName" name="name" class="form-control" required
                           value="${escapeHtml(alliance.name || '')}"
                           aria-describedby="fANameHelp fANameError">
                    <p class="form-helper" id="fANameHelp">${t('alliances.field_name_help', 'Identificador corto, visible en listados.')}</p>
                    <p class="form-error" id="fANameError" aria-live="polite"></p>
                </div>

                <div class="form-group">
                    <label for="fAFullname" class="form-label">
                        ${t('alliances.field_fullname', 'Nombre completo')} <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="fAFullname" name="fullname" class="form-control" required
                           value="${escapeHtml(alliance.fullname || '')}"
                           aria-describedby="fAFullnameError">
                    <p class="form-error" id="fAFullnameError" aria-live="polite"></p>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="fACountry" class="form-label">${t('alliances.field_country', 'Pais')}</label>
                        <select id="fACountry" name="country" class="form-control">${countryOptions}</select>
                    </div>

                    <div class="form-group">
                        <label for="fAColor" class="form-label">${t('alliances.field_color', 'Color')}</label>
                        <div class="color-field">
                            <input type="color" id="fAColor" class="color-field-picker" value="${color}">
                            <input type="text" id="fAColorHex" name="color" class="form-control"
                                   pattern="^#[0-9A-Fa-f]{6}$" maxlength="7" value="${color}">
                        </div>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="fAWebsite" class="form-label">${t('alliances.field_website', 'Sitio web')}</label>
                        <input type="url" id="fAWebsite" name="website" class="form-control"
                               placeholder="https://..."
                               value="${escapeHtml(alliance.website || '')}">
                    </div>

                    <div class="form-group">
                        <label for="fALmsUrl" class="form-label">${t('alliances.field_lms_url', 'URL del LMS')}</label>
                        <input type="url" id="fALmsUrl" name="lms_url" class="form-control"
                               placeholder="https://..."
                               value="${escapeHtml(alliance.lms_url || '')}">
                    </div>
                </div>

                <!-- Responsables -->
                <h3 class="form-section-heading">${t('alliances.responsibles', 'Responsables')}</h3>

                ${buildResponsibleSection('manager', t('alliances.field_manager', 'Gerente'), alliance.manager || {})}
                ${buildResponsibleSection('coordinator', t('alliances.field_coordinator', 'Coordinador'), alliance.coordinator || {})}
                ${buildResponsibleSection('migrator', t('alliances.field_migrator', 'Migrador'), alliance.migrator || {})}

                <!-- Estado -->
                <h3 class="form-section-heading">${t('alliances.status', 'Estado')}</h3>

                <div class="form-group">
                    <label class="toggle-field">
                        <input type="checkbox" name="active" id="fAActive" value="1" ${alliance.active !== false ? 'checked' : ''}>
                        <span>${t('alliances.field_active', 'Alianza activa')}</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="toggle-field">
                        <input type="checkbox" name="billable" id="fABillable" value="1" ${alliance.billable ? 'checked' : ''}>
                        <span>${t('alliances.field_billable', 'Facturable')}</span>
                    </label>
                    <p class="form-helper">${t('alliances.field_billable_help', 'Las alianzas no facturables solo aparecen en Tareas para tareas internas.')}</p>
                </div>

                ${!isCreate ? buildFilesSection(alliance.slug, alliance.files) : ''}

                <div class="alert alert-danger d-none" id="allianceFormError" role="alert">
                    <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
                    <span class="alert-content" id="allianceFormErrorText"></span>
                </div>
            </form>
        `;

        return { html, title };
    }

    function openAllianceForm(mode, slug = null) {
        const alliance = slug ? (alliancesData[slug] || {}) : {};
        const { html, title } = buildAllianceForm(mode, alliance);

        SlidePanel.open(title, html);

        SlidePanel.setFooter(`
            <button type="button" class="btn btn-subtle" id="allianceCancelBtn">
                ${t('common.cancel', 'Cancelar')}
            </button>
            <button type="button" class="btn btn-primary" id="allianceSubmitBtn">
                <i class="bi bi-check2" aria-hidden="true"></i>
                <span class="btn-text">${mode === 'create' ? t('common.create', 'Crear') : t('common.save', 'Guardar')}</span>
            </button>
        `);

        setupAllianceFormHandlers(mode, alliance);
    }

    function setupAllianceFormHandlers(mode, alliance) {
        const form = document.getElementById('allianceForm');
        if (!form) return;

        document.getElementById('allianceCancelBtn').addEventListener('click', () => SlidePanel.close());
        document.getElementById('allianceSubmitBtn').addEventListener('click', (e) => handleAllianceSubmit(e, mode));

        // Color picker <-> hex
        const colorPicker = document.getElementById('fAColor');
        const colorHex = document.getElementById('fAColorHex');
        if (colorPicker && colorHex) {
            colorPicker.addEventListener('input', () => colorHex.value = colorPicker.value);
            colorHex.addEventListener('input', () => {
                if (/^#[0-9A-Fa-f]{6}$/.test(colorHex.value)) {
                    colorPicker.value = colorHex.value;
                }
            });
        }

        // Toggle tipo de responsable
        ['manager', 'coordinator', 'migrator'].forEach(prefix => {
            form.querySelectorAll(`input[name="${prefix}_type"]`).forEach(radio => {
                radio.addEventListener('change', () => {
                    const isUser = form.querySelector(`input[name="${prefix}_type"]:checked`).value === 'user';
                    form.querySelector(`[data-section="${prefix}-user"]`).classList.toggle('d-none', !isUser);
                    form.querySelector(`[data-section="${prefix}-external"]`).classList.toggle('d-none', isUser);
                });
            });
        });

        // Active/Billable: si no esta activa, billable se deshabilita
        const activeCheckbox = document.getElementById('fAActive');
        const billableCheckbox = document.getElementById('fABillable');
        if (activeCheckbox && billableCheckbox) {
            const syncBillable = () => {
                if (!activeCheckbox.checked) {
                    billableCheckbox.checked = false;
                    billableCheckbox.disabled = true;
                } else {
                    billableCheckbox.disabled = false;
                }
            };
            activeCheckbox.addEventListener('change', syncBillable);
            syncBillable();
        }

        // Archivos
        if (mode === 'edit' && alliance.slug) {
            const uploadBtn = document.getElementById('btnUploadAllianceFile');
            if (uploadBtn) {
                uploadBtn.addEventListener('click', () => uploadFile(alliance.slug));
            }

            form.addEventListener('click', (e) => {
                const deleteBtn = e.target.closest('.btn-delete-alliance-file');
                if (deleteBtn) {
                    deleteFile(deleteBtn.dataset.slug, deleteBtn.dataset.filename);
                }
            });
        }
    }

    async function uploadFile(slug) {
        const input = document.getElementById('allianceFileInput');
        const errorEl = document.getElementById('allianceFileError');
        if (!input || !input.files[0]) return;

        const file = input.files[0];
        errorEl.classList.add('d-none');
        errorEl.textContent = '';

        try {
            const result = await postAction({ action: 'upload-file', slug: slug, file: file });
            if (result.success) {
                // Append to list
                const list = document.getElementById('allianceFilesList');
                if (list && result.file) {
                    const icon = getFileIcon(result.file.filename);
                    const li = document.createElement('li');
                    li.className = 'alliance-file-item';
                    li.dataset.filename = result.file.filename;
                    li.innerHTML = `
                        <i class="bi ${icon.class} alliance-file-icon" aria-hidden="true"></i>
                        <a href="${fileUrl(slug, result.file.filename)}" target="_blank" rel="noopener" class="alliance-file-name">
                            ${escapeHtml(result.file.name)}
                        </a>
                        <span class="alliance-file-date">${escapeHtml(result.file.uploaded_at || '')}</span>
                        <a href="${fileUrl(slug, result.file.filename)}" download class="btn-icon" aria-label="${t('common.download', 'Descargar')}">
                            <i class="bi bi-download" aria-hidden="true"></i>
                        </a>
                        <button type="button" class="btn-icon btn-icon-danger btn-delete-alliance-file"
                                data-slug="${slug}" data-filename="${escapeHtml(result.file.filename)}"
                                aria-label="${t('common.delete', 'Eliminar')}">
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </button>
                    `;
                    list.appendChild(li);
                }
                input.value = '';
                Toast.success(result.message || t('alliances.file_uploaded', 'Archivo cargado.'));
            } else {
                errorEl.textContent = result.message || t('alliances.err_upload', 'Error al cargar.');
                errorEl.classList.remove('d-none');
            }
        } catch (err) {
            errorEl.textContent = t('common.err_network', 'Error de red.');
            errorEl.classList.remove('d-none');
        }
    }

    async function deleteFile(slug, filename) {
        const confirmed = await ConfirmModal.show({
            title: t('alliances.delete_file_title', 'Eliminar archivo'),
            message: t('alliances.delete_file_message', 'El archivo sera eliminado permanentemente.'),
            acceptText: t('common.delete', 'Eliminar'),
            acceptVariant: 'danger',
            icon: 'bi-trash',
            variant: 'danger',
        });
        if (!confirmed) return;

        try {
            const result = await postAction({ action: 'delete-file', slug: slug, filename: filename });
            if (result.success) {
                const li = document.querySelector(`.alliance-file-item[data-filename="${filename}"]`);
                if (li) li.remove();
                Toast.success(result.message || t('alliances.file_deleted', 'Archivo eliminado.'));
            } else {
                Toast.error(result.message || t('alliances.err_delete_file', 'Error al eliminar archivo.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        }
    }

    function clearAllianceErrors() {
        ['fANameError', 'fAFullnameError'].forEach(id => {
            const el = document.getElementById(id); if (el) el.textContent = '';
        });
        document.querySelectorAll('#allianceForm .form-control-error').forEach(el => el.classList.remove('form-control-error'));
        const err = document.getElementById('allianceFormError');
        if (err) err.classList.add('d-none');
    }

    async function handleAllianceSubmit(e, mode) {
        e.preventDefault();
        const form = document.getElementById('allianceForm');
        const submitBtn = document.getElementById('allianceSubmitBtn');
        if (!form || !submitBtn) return;

        clearAllianceErrors();

        const name = form.name.value.trim();
        const fullname = form.fullname.value.trim();
        let hasError = false;

        if (!name) {
            document.getElementById('fAName').classList.add('form-control-error');
            document.getElementById('fANameError').textContent = t('alliances.err_name', 'El nombre es obligatorio.');
            hasError = true;
        }
        if (!fullname) {
            document.getElementById('fAFullname').classList.add('form-control-error');
            document.getElementById('fAFullnameError').textContent = t('alliances.err_fullname', 'El nombre completo es obligatorio.');
            hasError = true;
        }
        if (hasError) return;

        // Construir data
        const data = { action: form.action.value, slug: form.slug.value };
        const fields = ['name', 'fullname', 'country', 'color', 'website', 'lms_url'];
        fields.forEach(f => data[f] = form[f].value);

        ['manager', 'coordinator', 'migrator'].forEach(prefix => {
            data[`${prefix}_type`] = form.querySelector(`input[name="${prefix}_type"]:checked`)?.value || 'external';
            data[`${prefix}_username`] = form[`${prefix}_username`]?.value || '';
            data[`${prefix}_name`] = form[`${prefix}_name`]?.value || '';
            data[`${prefix}_email`] = form[`${prefix}_email`]?.value || '';
        });

        data.active = form.active.checked ? '1' : '0';
        data.billable = form.billable?.checked ? '1' : '0';

        submitBtn.disabled = true;
        const btnText = submitBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('common.saving', 'Guardando...');

        try {
            const result = await postAction(data);
            if (result.success) {
                Toast.success(result.message || t('alliances.saved', 'Alianza guardada.'));
                SlidePanel.close();
                setTimeout(() => window.location.reload(), 500);
            } else {
                const err = document.getElementById('allianceFormError');
                document.getElementById('allianceFormErrorText').textContent = result.message || t('alliances.err_generic', 'Error al guardar.');
                err.classList.remove('d-none');
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            submitBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    async function deleteAlliance(slug, name) {
        const confirmed = await ConfirmModal.show({
            title: t('alliances.delete_title', 'Eliminar alianza'),
            message: t('alliances.delete_message', 'La alianza "{name}" sera eliminada permanentemente junto con sus archivos asociados.').replace('{name}', name),
            acceptText: t('common.delete', 'Eliminar'),
            acceptVariant: 'danger',
            icon: 'bi-trash',
            variant: 'danger',
        });
        if (!confirmed) return;

        try {
            const result = await postAction({ action: 'delete', slug: slug });
            if (result.success) {
                Toast.success(result.message || t('alliances.deleted', 'Alianza eliminada.'));
                setTimeout(() => window.location.reload(), 300);
            } else {
                Toast.error(result.message || t('alliances.err_delete', 'Error al eliminar.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        }
    }

    /** ========================================================
     * Bindings
     * ======================================================== */

    async function importAlliances(file) {
        const confirmed = await Toast.confirm(
            t('manage_alliances.import_confirm', '¿Importar alianzas desde este archivo? Las alianzas existentes con el mismo identificador se actualizarán.')
        );
        if (!confirmed) return;

        const formData = new FormData();
        formData.append('action', 'import');
        formData.append('file', file);

        try {
            const res = await fetch('includes/manage_alliances_actions.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: formData,
            });
            const data = await res.json();
            if (data.success) {
                Toast.success(data.message);
                setTimeout(() => location.reload(), 1200);
            } else {
                Toast.error(data.message || t('common.error', 'Error'));
            }
        } catch (e) {
            Toast.error(t('common.err_network', 'Error de red'));
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const btnCreate = document.getElementById('btnCreateAlliance');
        const btnCreateEmpty = document.getElementById('btnCreateAllianceEmpty');
        if (btnCreate && canWrite) btnCreate.addEventListener('click', () => openAllianceForm('create'));
        if (btnCreateEmpty && canWrite) btnCreateEmpty.addEventListener('click', () => openAllianceForm('create'));

        const btnImport = document.getElementById('btnImportAlliances');
        const importInput = document.getElementById('importAlliancesInput');
        if (btnImport && importInput && canWrite) {
            btnImport.addEventListener('click', () => importInput.click());
            importInput.addEventListener('change', () => {
                if (importInput.files[0]) {
                    importAlliances(importInput.files[0]);
                    importInput.value = '';
                }
            });
        }

        document.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.btn-edit-alliance');
            const deleteBtn = e.target.closest('.btn-delete-alliance');
            if (editBtn) openAllianceForm('edit', editBtn.dataset.slug);
            else if (deleteBtn) deleteAlliance(deleteBtn.dataset.slug, deleteBtn.dataset.name);
        });
    });

})();
