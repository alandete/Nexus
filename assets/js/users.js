/**
 * Nexus 2.0 — Users Management
 * CRUD con busqueda, filtros y validacion inline
 */

(function () {
    'use strict';

    const usersData = window.__USERS_DATA__ || {};
    const rolesAvailable = window.__USERS_ROLES__ || ['admin', 'editor', 'viewer'];
    const canWrite = window.__USERS_CAN_WRITE__ || false;
    const canDelete = window.__USERS_CAN_DELETE__ || false;
    const currentUsername = window.__CURRENT_USERNAME__ || '';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // i18n helper (textos inyectados desde data-attrs o window.__T__ si se necesita)
    // Para simplicidad, textos estaticos en las cadenas de error (pueden moverse a lang despues)
    const T = window.__T__ || {};
    const t = (key, fallback) => T[key] || fallback;

    /** ========================================================
     * Helpers
     * ======================================================== */

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    async function postAction(data) {
        const fd = new FormData();
        Object.keys(data).forEach(k => {
            if (data[k] instanceof File) fd.append(k, data[k]);
            else fd.append(k, data[k] ?? '');
        });
        const res = await fetch('includes/user_actions.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: fd,
        });
        return res.json();
    }

    /** ========================================================
     * Filtros (busqueda, rol, estado)
     * ======================================================== */

    function applyFilters() {
        const searchInput = document.getElementById('userSearch');
        const roleFilter  = document.getElementById('userRoleFilter');
        const statusFilter = document.getElementById('userStatusFilter');
        const tbody = document.getElementById('usersTableBody');
        const emptyState = document.getElementById('usersEmptyFiltered');

        if (!tbody) return;

        const query  = (searchInput?.value || '').trim().toLowerCase();
        const role   = roleFilter?.value || '';
        const status = statusFilter?.value || '';

        const rows = tbody.querySelectorAll('tr');
        let visibleCount = 0;

        rows.forEach(row => {
            const rowSearch = row.dataset.search || '';
            const rowRole   = row.dataset.role || '';
            const rowStatus = row.dataset.status || '';

            const matchSearch = !query || rowSearch.includes(query);
            const matchRole   = !role || rowRole === role;
            const matchStatus = !status || rowStatus === status;

            const visible = matchSearch && matchRole && matchStatus;
            row.classList.toggle('d-none', !visible);
            if (visible) visibleCount++;
        });

        // Empty state
        if (emptyState) {
            const noResults = visibleCount === 0 && rows.length > 0;
            emptyState.classList.toggle('d-none', !noResults);
            tbody.parentElement.classList.toggle('d-none', noResults);
        }
    }

    ['userSearch', 'userRoleFilter', 'userStatusFilter'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('input', applyFilters);
        if (el) el.addEventListener('change', applyFilters);
    });

    /** ========================================================
     * Jornada laboral
     * ======================================================== */

    const SCHEDULE_DAYS = [
        { key: 'monday',    label: t('users.day_monday',    'Lunes') },
        { key: 'tuesday',   label: t('users.day_tuesday',   'Martes') },
        { key: 'wednesday', label: t('users.day_wednesday', 'Miércoles') },
        { key: 'thursday',  label: t('users.day_thursday',  'Jueves') },
        { key: 'friday',    label: t('users.day_friday',    'Viernes') },
        { key: 'saturday',  label: t('users.day_saturday',  'Sábado') },
        { key: 'sunday',    label: t('users.day_sunday',    'Domingo') },
    ];

    function buildScheduleDays(schedule) {
        return SCHEDULE_DAYS.map(({ key, label }) => {
            const day    = schedule[key] || {};
            const active = !!day.active;
            const amS    = day.am_start || '08:00';
            const amE    = day.am_end   || '12:00';
            const pmS    = day.pm_start || '14:00';
            const pmE    = day.pm_end   || '18:00';
            const dis = active ? '' : 'disabled';
        return `
                <div class="schedule-day${active ? '' : ' schedule-day--off'}" data-day="${key}">
                    <label class="schedule-day-label">
                        <input type="checkbox" class="schedule-day-check" ${active ? 'checked' : ''}>
                        <span class="schedule-day-name">${escapeHtml(label)}</span>
                    </label>
                    <div class="schedule-day-times">
                        <div class="schedule-block">
                            <input type="time" class="form-control form-control-sm schedule-time" data-field="am_start" value="${amS}" ${dis}>
                            <span class="schedule-sep" aria-hidden="true">—</span>
                            <input type="time" class="form-control form-control-sm schedule-time" data-field="am_end" value="${amE}" ${dis}>
                        </div>
                        <div class="schedule-block">
                            <input type="time" class="form-control form-control-sm schedule-time" data-field="pm_start" value="${pmS}" ${dis}>
                            <span class="schedule-sep" aria-hidden="true">—</span>
                            <input type="time" class="form-control form-control-sm schedule-time" data-field="pm_end" value="${pmE}" ${dis}>
                        </div>
                    </div>
                </div>`;
        }).join('');
    }

    function serializeSchedule() {
        const schedule = {};
        document.querySelectorAll('#scheduleDays .schedule-day').forEach(row => {
            const key    = row.dataset.day;
            const active = row.querySelector('.schedule-day-check').checked;
            schedule[key] = active ? {
                active:   true,
                am_start: row.querySelector('[data-field="am_start"]').value,
                am_end:   row.querySelector('[data-field="am_end"]').value,
                pm_start: row.querySelector('[data-field="pm_start"]').value,
                pm_end:   row.querySelector('[data-field="pm_end"]').value,
            } : { active: false };
        });
        const input = document.getElementById('workScheduleInput');
        if (input) input.value = JSON.stringify(schedule);
    }

    function validateSchedule() {
        let error = null;
        document.querySelectorAll('#scheduleDays .schedule-day').forEach(row => {
            if (error) return;
            if (!row.querySelector('.schedule-day-check').checked) return;
            const label  = row.querySelector('.schedule-day-name').textContent;
            const amS    = row.querySelector('[data-field="am_start"]').value;
            const amE    = row.querySelector('[data-field="am_end"]').value;
            const pmS    = row.querySelector('[data-field="pm_start"]').value;
            const pmE    = row.querySelector('[data-field="pm_end"]').value;
            if (amS >= amE)  error = `${label}: la hora de fin de la mañana debe ser posterior al inicio.`;
            else if (pmS >= pmE)  error = `${label}: la hora de fin de la tarde debe ser posterior al inicio.`;
            else if (amE > pmS)   error = `${label}: el bloque de tarde debe iniciar después del fin de la mañana.`;
        });
        return error;
    }

    /** ========================================================
     * Formulario (crear / editar) en Slide Panel
     * ======================================================== */

    function buildUserForm(mode, user) {
        const isCreate = mode === 'create';
        const isSelf = !isCreate && user.username === currentUsername;
        const title = isCreate
            ? (t('users.form_create_title', 'Crear usuario'))
            : (t('users.form_edit_title', 'Editar usuario'));

        const roleOptions = rolesAvailable.map(r =>
            `<option value="${r}" ${user.role === r ? 'selected' : ''}>${r.charAt(0).toUpperCase() + r.slice(1)}</option>`
        ).join('');

        const photoUrl = user.photo ? `assets/uploads/avatars/${user.photo}` : '';
        const initial = ((user.name || user.username || '?').charAt(0) || '?').toUpperCase();

        const html = `
            <form id="userForm" novalidate autocomplete="off">
                <input type="hidden" name="action" value="${isCreate ? 'create' : 'update'}">
                <input type="hidden" name="original_username" value="${escapeHtml(user.username || '')}">
                <input type="hidden" name="remove_photo" id="removePhotoFlag" value="0">

                <!-- Avatar preview -->
                <div class="form-field-photo">
                    <div class="form-field-photo-preview">
                        ${photoUrl
                            ? `<img id="photoPreview" src="${photoUrl}" alt="">`
                            : `<span id="photoPreviewFallback" class="avatar avatar-xl" aria-hidden="true">${initial}</span>`
                        }
                    </div>
                    <div class="form-field-photo-actions">
                        <label for="photoInput" class="btn btn-default btn-sm">
                            <i class="bi bi-upload" aria-hidden="true"></i>
                            ${t('users.field_photo_change', 'Cambiar foto')}
                        </label>
                        <input type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/webp" class="d-none">
                        ${photoUrl ? `
                        <button type="button" class="btn btn-subtle btn-sm" id="removePhotoBtn">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                            ${t('users.field_photo_remove', 'Quitar foto')}
                        </button>
                        ` : ''}
                    </div>
                    <p class="form-helper">${t('users.field_photo_help', 'JPG, PNG o WebP. Max 2 MB.')}</p>
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label for="fUsername" class="form-label">
                        ${t('users.field_username', 'Usuario')} <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="fUsername" name="username"
                           class="form-control" required
                           pattern="[a-zA-Z0-9_]{3,20}"
                           value="${escapeHtml(user.username || '')}"
                           ${isCreate ? '' : 'readonly'}
                           aria-describedby="fUsernameHelp fUsernameError">
                    <p class="form-helper" id="fUsernameHelp">${t('users.field_username_help', '3-20 caracteres: letras, numeros y guion bajo.')}</p>
                    <p class="form-error" id="fUsernameError" aria-live="polite"></p>
                </div>

                <!-- Nombre -->
                <div class="form-group">
                    <label for="fName" class="form-label">
                        ${t('users.field_name', 'Nombre completo')} <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="fName" name="name" class="form-control" required
                           value="${escapeHtml(user.name || '')}"
                           aria-describedby="fNameError">
                    <p class="form-error" id="fNameError" aria-live="polite"></p>
                </div>

                <!-- Email -->
                <div class="form-group">
                    <label for="fEmail" class="form-label">
                        ${t('users.field_email', 'Correo')} <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input type="email" id="fEmail" name="email" class="form-control" required
                           value="${escapeHtml(user.email || '')}"
                           aria-describedby="fEmailError">
                    <p class="form-error" id="fEmailError" aria-live="polite"></p>
                </div>

                <!-- Contrasena -->
                <div class="form-group">
                    <label for="fPassword" class="form-label">
                        ${t('users.field_password', 'Contrasena')}
                        ${isCreate ? '<span class="form-required" aria-hidden="true">*</span>' : ''}
                    </label>
                    <input type="password" id="fPassword" name="password" class="form-control"
                           ${isCreate ? 'required' : ''} minlength="6"
                           autocomplete="new-password"
                           aria-describedby="fPasswordHelp fPasswordError">
                    <p class="form-helper" id="fPasswordHelp">${isCreate
                        ? t('users.field_password_help', 'Minimo 6 caracteres.')
                        : t('users.field_password_help_edit', 'Dejar vacio para mantener la actual.')}</p>
                    <p class="form-error" id="fPasswordError" aria-live="polite"></p>
                </div>

                <!-- Rol -->
                <div class="form-group">
                    <label for="fRole" class="form-label">
                        ${t('users.field_role', 'Rol')} <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <select id="fRole" name="role" class="form-control" required ${isSelf ? 'disabled' : ''}>
                        ${roleOptions}
                    </select>
                    ${isSelf ? `<p class="form-helper">${t('users.field_role_self', 'No puedes cambiar tu propio rol.')}</p>` : ''}
                </div>

                <!-- Idioma -->
                <div class="form-group">
                    <label for="fLang" class="form-label">${t('users.field_lang', 'Idioma')}</label>
                    <select id="fLang" name="lang" class="form-control">
                        <option value="es" ${(user.lang || 'es') === 'es' ? 'selected' : ''}>Español</option>
                        <option value="en" ${user.lang === 'en' ? 'selected' : ''}>English</option>
                    </select>
                </div>

                ${!isCreate ? `
                <!-- Estado -->
                <div class="form-group">
                    <label class="toggle-field">
                        <input type="checkbox" name="active" value="1" ${user.active ? 'checked' : ''} ${isSelf ? 'disabled' : ''}>
                        <span>${t('users.field_active', 'Usuario activo')}</span>
                    </label>
                    <p class="form-helper">${t('users.field_active_help', 'Los usuarios inactivos no pueden iniciar sesion.')}</p>
                </div>
                ` : ''}

                <!-- Jornada laboral -->
                <div class="form-group">
                    <label class="form-label">${t('users.field_schedule', 'Jornada laboral')}</label>
                    <p class="form-helper">${t('users.field_schedule_help', 'Activa los dias laborables e indica los bloques de horario.')}</p>
                    <div class="user-schedule" id="scheduleDays">
                        ${buildScheduleDays(user.work_schedule || {})}
                    </div>
                    <input type="hidden" name="work_schedule" id="workScheduleInput">
                </div>

                <!-- Alert de error general -->
                <div class="alert alert-danger d-none" id="formError" role="alert">
                    <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
                    <span class="alert-content" id="formErrorText"></span>
                </div>
            </form>
        `;

        return { html, title };
    }

    function openUserForm(mode, username = null) {
        const user = username ? (usersData[username] || {}) : {};
        const { html, title } = buildUserForm(mode, user);

        SlidePanel.open(title, html);

        // Footer con botones
        SlidePanel.setFooter(`
            <button type="button" class="btn btn-subtle" id="userCancelBtn">
                ${t('common.cancel', 'Cancelar')}
            </button>
            <button type="button" class="btn btn-primary" id="userSubmitBtn">
                <i class="bi bi-check2" aria-hidden="true"></i>
                <span class="btn-text">${mode === 'create' ? t('common.create', 'Crear') : t('common.save', 'Guardar')}</span>
            </button>
        `);

        setupUserFormHandlers(mode);
    }

    function setupUserFormHandlers(mode) {
        const form = document.getElementById('userForm');
        if (!form) return;

        // Cancelar
        document.getElementById('userCancelBtn').addEventListener('click', () => SlidePanel.close());

        // Submit
        document.getElementById('userSubmitBtn').addEventListener('click', (e) => {
            const scheduleError = validateSchedule();
            if (scheduleError) {
                const errEl = document.getElementById('formErrorText');
                const errBox = document.getElementById('formError');
                if (errEl) errEl.textContent = scheduleError;
                if (errBox) errBox.classList.remove('d-none');
                return;
            }
            serializeSchedule();
            handleUserSubmit(e, mode);
        });

        // Toggle días de jornada
        document.getElementById('scheduleDays')?.addEventListener('change', (e) => {
            if (e.target.classList.contains('schedule-day-check')) {
                const row     = e.target.closest('.schedule-day');
                const enabled = e.target.checked;
                row.classList.toggle('schedule-day--off', !enabled);
                row.querySelectorAll('.schedule-time').forEach(input => {
                    input.disabled = !enabled;
                });
            }
        });

        // Preview de foto
        const photoInput = document.getElementById('photoInput');
        if (photoInput) {
            photoInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (ev) => {
                    const preview = document.getElementById('photoPreview');
                    const fallback = document.getElementById('photoPreviewFallback');
                    if (preview) {
                        preview.src = ev.target.result;
                    } else if (fallback) {
                        const img = document.createElement('img');
                        img.id = 'photoPreview';
                        img.alt = '';
                        img.src = ev.target.result;
                        fallback.replaceWith(img);
                    }
                    document.getElementById('removePhotoFlag').value = '0';
                };
                reader.readAsDataURL(file);
            });
        }

        // Quitar foto
        const removeBtn = document.getElementById('removePhotoBtn');
        if (removeBtn) {
            removeBtn.addEventListener('click', () => {
                document.getElementById('removePhotoFlag').value = '1';
                const preview = document.getElementById('photoPreview');
                if (preview) {
                    const initial = (document.getElementById('fName')?.value || '?').charAt(0).toUpperCase();
                    const fallback = document.createElement('span');
                    fallback.id = 'photoPreviewFallback';
                    fallback.className = 'avatar avatar-xl';
                    fallback.setAttribute('aria-hidden', 'true');
                    fallback.textContent = initial;
                    preview.replaceWith(fallback);
                }
                if (photoInput) photoInput.value = '';
                removeBtn.remove();
            });
        }

        // Enter en campos hace submit
        form.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.tagName !== 'BUTTON') {
                e.preventDefault();
                serializeSchedule();
                handleUserSubmit(e, mode);
            }
        });
    }

    function clearFieldErrors() {
        ['fUsernameError', 'fNameError', 'fEmailError', 'fPasswordError'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = '';
        });
        document.querySelectorAll('#userForm .form-control-error').forEach(el => {
            el.classList.remove('form-control-error');
        });
        const formError = document.getElementById('formError');
        if (formError) formError.classList.add('d-none');
    }

    function setFieldError(fieldId, errorId, message) {
        const field = document.getElementById(fieldId);
        const error = document.getElementById(errorId);
        if (field) field.classList.add('form-control-error');
        if (error) error.textContent = message;
    }

    async function handleUserSubmit(e, mode) {
        e.preventDefault();
        const form = document.getElementById('userForm');
        const submitBtn = document.getElementById('userSubmitBtn');
        if (!form || !submitBtn) return;

        clearFieldErrors();

        // Validacion client-side
        const username = form.username.value.trim();
        const name = form.name.value.trim();
        const email = form.email.value.trim();
        const password = form.password.value;
        let hasError = false;

        if (mode === 'create' && !/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
            setFieldError('fUsername', 'fUsernameError', t('users.err_username', 'Usuario invalido.'));
            hasError = true;
        }
        if (!name) {
            setFieldError('fName', 'fNameError', t('users.err_name', 'El nombre es obligatorio.'));
            hasError = true;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            setFieldError('fEmail', 'fEmailError', t('users.err_email', 'Correo invalido.'));
            hasError = true;
        }
        if (mode === 'create' && password.length < 6) {
            setFieldError('fPassword', 'fPasswordError', t('users.err_password', 'Minimo 6 caracteres.'));
            hasError = true;
        }
        if (mode === 'edit' && password && password.length < 6) {
            setFieldError('fPassword', 'fPasswordError', t('users.err_password', 'Minimo 6 caracteres.'));
            hasError = true;
        }

        if (hasError) return;

        // Preparar datos
        const data = {
            action: form.action.value,
            original_username: form.original_username.value,
            username: username,
            name: name,
            email: email,
            role: form.role.value,
            lang: form.lang.value,
            remove_photo: form.remove_photo.value,
        };
        if (password) data.password = password;
        const photoFile = document.getElementById('photoInput')?.files[0];
        if (photoFile) data.photo = photoFile;
        if (form.active) data.active = form.active.checked ? '1' : '0';
        data.work_schedule = document.getElementById('workScheduleInput')?.value || '{}';

        // Loading
        submitBtn.disabled = true;
        const btnText = submitBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('common.saving', 'Guardando...');

        try {
            const result = await postAction(data);
            if (result.success) {
                Toast.success(result.message || t('users.success', 'Guardado correctamente.'));
                SlidePanel.close();
                setTimeout(() => window.location.reload(), 500);
            } else {
                const formError = document.getElementById('formError');
                document.getElementById('formErrorText').textContent = result.message || t('users.err_generic', 'Error al guardar.');
                formError.classList.remove('d-none');
            }
        } catch (err) {
            Toast.error(t('users.err_network', 'Error de red. Intenta de nuevo.'));
        } finally {
            submitBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    /** ========================================================
     * Eliminar usuario
     * ======================================================== */

    async function deleteUser(username, name) {
        const confirmed = await ConfirmModal.show({
            title: t('users.delete_title', 'Eliminar usuario'),
            message: (t('users.delete_message', 'Se eliminara a {name} de forma permanente. Esta accion no se puede deshacer.')).replace('{name}', name),
            acceptText: t('common.delete', 'Eliminar'),
            acceptVariant: 'danger',
            icon: 'bi-trash',
            variant: 'danger',
        });
        if (!confirmed) return;

        try {
            const result = await postAction({ action: 'delete', username: username });
            if (result.success) {
                Toast.success(result.message || t('users.deleted', 'Usuario eliminado.'));
                setTimeout(() => window.location.reload(), 300);
            } else {
                Toast.error(result.message || t('users.err_delete', 'Error al eliminar.'));
            }
        } catch (err) {
            Toast.error(t('users.err_network', 'Error de red.'));
        }
    }

    /** ========================================================
     * Event bindings
     * ======================================================== */

    document.addEventListener('DOMContentLoaded', () => {
        // Create
        const btnCreate = document.getElementById('btnCreateUser');
        if (btnCreate && canWrite) {
            btnCreate.addEventListener('click', () => openUserForm('create'));
        }

        // Edit / Delete / Reset link (delegation)
        document.addEventListener('click', (e) => {
            const editBtn   = e.target.closest('.btn-edit-user');
            const deleteBtn = e.target.closest('.btn-delete-user');
            const resetBtn  = e.target.closest('.btn-reset-link');

            if (editBtn) {
                openUserForm('edit', editBtn.dataset.username);
            } else if (deleteBtn) {
                deleteUser(deleteBtn.dataset.username, deleteBtn.dataset.name);
            } else if (resetBtn) {
                generateResetLink(resetBtn);
            }
        });
    });

    async function generateResetLink(btn) {
        if (btn.disabled) return;
        btn.disabled = true;

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const fd = new FormData();
        fd.append('action',      'generate_link');
        fd.append('username',    btn.dataset.username);
        fd.append('csrf_token',  csrfToken);

        try {
            const res    = await fetch('includes/password_reset_actions.php', { method: 'POST', body: fd });
            const result = await res.json();

            if (!result.success) {
                Toast.error(result.message || 'No se pudo generar el enlace.');
                return;
            }

            await navigator.clipboard.writeText(result.url);
            Toast.show('Enlace copiado al portapapeles. Compártelo con el usuario.', 'success');
        } catch {
            Toast.error('Error al generar el enlace.');
        } finally {
            btn.disabled = false;
        }
    }

})();
