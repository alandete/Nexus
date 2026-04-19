/**
 * Nexus 2.0 — Tareas
 * Sub-fase 4.1: Cronometro y tarea activa (flujo hibrido)
 *
 * Flujo:
 * 1. Usuario escribe titulo -> autocomplete sugiere tareas existentes
 * 2. Enter o clic en Play -> timer arranca inmediatamente
 * 3. Si faltan alianza/etiquetas, se muestra alerta sutil
 * 4. Editar abre slide panel completo con todos los campos
 * 5. Pausar/Completar valida; si falta info, abre panel forzando completar
 * 6. Descartar no requiere validacion
 */

(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const T = window.__T__ || {};
    const t = (key, fallback) => T[key] || fallback;

    const alliances = window.__TASKS_ALLIANCES__ || [];
    let allTags = window.__TASKS_TAGS__ || [];

    const state = {
        running: false,
        taskId: null,
        title: '',
        description: '',
        allianceId: null,
        allianceName: null,
        tagIds: [],
        tagNames: '',
        priority: 'medium',
        dueDate: null,
        startTime: null,
        tickInterval: null,
        selectedExistingTaskId: null, // Si el usuario selecciona del autocomplete
    };

    /** ========================================================
     * Helpers
     * ======================================================== */

    function formatTime(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;
        return [h, m, s].map(n => String(n).padStart(2, '0')).join(':');
    }

    function formatDuration(seconds) {
        const h = Math.floor(seconds / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        if (h > 0) return `${h}h ${m}m`;
        return `${m}m ${seconds % 60}s`;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    async function api(action, data = {}) {
        const fd = new FormData();
        fd.append('action', action);
        Object.keys(data).forEach(k => fd.append(k, data[k] ?? ''));
        const res = await fetch('includes/tasks_actions.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: fd,
        });
        return res.json();
    }

    /** ========================================================
     * Renderizado
     * ======================================================== */

    function renderActiveCard() {
        const row = document.getElementById('trackerInputRow');
        const active = document.getElementById('trackerActive');
        if (!row || !active) return;

        row.classList.add('d-none');
        active.classList.remove('d-none');

        document.getElementById('trackerTaskTitle').textContent = state.title || '—';

        // Render chips de meta compactos: orden etiquetas -> alianza -> prioridad -> fecha
        const meta = document.getElementById('trackerTaskMeta');
        if (meta) {
            const chips = [];

            // Etiquetas primero
            if (state.tagNames) {
                state.tagNames.split(',').map(n => n.trim()).filter(Boolean).forEach(name => {
                    chips.push(`<span class="tracker-meta-chip"><i class="bi bi-tag" aria-hidden="true"></i>${escapeHtml(name)}</span>`);
                });
            } else {
                chips.push(`<span class="tracker-meta-chip tracker-meta-empty"><i class="bi bi-tag" aria-hidden="true"></i>${escapeHtml(t('tasks.no_tags', 'Sin etiquetas'))}</span>`);
            }

            // Alianza
            if (state.allianceName) {
                chips.push(`<span class="tracker-meta-chip tracker-meta-alliance"><i class="bi bi-building" aria-hidden="true"></i>${escapeHtml(state.allianceName)}</span>`);
            } else {
                chips.push(`<span class="tracker-meta-chip tracker-meta-empty"><i class="bi bi-building" aria-hidden="true"></i>${escapeHtml(t('tasks.no_alliance', 'Sin alianza'))}</span>`);
            }

            // Prioridad (solo si difiere de media)
            if (state.priority && state.priority !== 'medium') {
                const priorityLabels = {
                    low:    t('tasks.priority_low', 'Baja'),
                    high:   t('tasks.priority_high', 'Alta'),
                    urgent: t('tasks.priority_urgent', 'Urgente'),
                };
                const label = priorityLabels[state.priority];
                if (label) {
                    chips.push(`<span class="tracker-meta-chip tracker-priority-${state.priority}"><i class="bi bi-flag-fill" aria-hidden="true"></i>${escapeHtml(label)}</span>`);
                }
            }

            // Fecha de vencimiento
            if (state.dueDate) {
                chips.push(`<span class="tracker-meta-chip"><i class="bi bi-calendar" aria-hidden="true"></i>${escapeHtml(state.dueDate)}</span>`);
            }

            meta.innerHTML = chips.join('');
        }

        // Alerta si faltan datos
        const incomplete = document.getElementById('trackerIncomplete');
        const incompleteText = document.getElementById('trackerIncompleteText');
        const missing = [];
        if (!state.allianceId) missing.push(t('tasks.field_alliance', 'alianza').toLowerCase());
        if (state.tagIds.length === 0) missing.push(t('tasks.field_tags', 'etiquetas').toLowerCase());

        if (missing.length > 0) {
            incomplete.classList.remove('d-none');
            incompleteText.textContent = t('tasks.incomplete_msg', 'Agrega {fields} para poder pausar o completar.')
                .replace('{fields}', missing.join(' y '));
        } else {
            incomplete.classList.add('d-none');
        }
    }

    function renderEmpty() {
        const row = document.getElementById('trackerInputRow');
        const active = document.getElementById('trackerActive');
        if (!row || !active) return;

        active.classList.add('d-none');
        row.classList.remove('d-none');

        const input = document.getElementById('trackerInput');
        if (input) {
            input.value = '';
            input.focus();
        }
    }

    function tick() {
        if (!state.startTime) return;
        const elapsed = Math.floor((Date.now() - state.startTime.getTime()) / 1000);
        const el = document.getElementById('trackerTime');
        if (el) el.textContent = formatTime(elapsed);
    }

    function startTicking() {
        stopTicking();
        tick();
        state.tickInterval = setInterval(tick, 1000);
    }

    function stopTicking() {
        if (state.tickInterval) {
            clearInterval(state.tickInterval);
            state.tickInterval = null;
        }
    }

    function resetState() {
        stopTicking();
        state.running = false;
        state.taskId = null;
        state.title = '';
        state.description = '';
        state.allianceId = null;
        state.allianceName = null;
        state.tagIds = [];
        state.tagNames = '';
        state.priority = 'medium';
        state.dueDate = null;
        state.startTime = null;
        state.selectedExistingTaskId = null;
    }

    /** ========================================================
     * Autocomplete
     * ======================================================== */

    let searchTimer = null;
    function setupAutocomplete() {
        const input = document.getElementById('trackerInput');
        const dropdown = document.getElementById('trackerAutocomplete');
        if (!input || !dropdown) return;

        input.addEventListener('input', () => {
            state.selectedExistingTaskId = null;
            const query = input.value.trim();
            clearTimeout(searchTimer);

            if (query.length < 2) {
                hideAutocomplete();
                return;
            }

            searchTimer = setTimeout(async () => {
                try {
                    const result = await api('search', { q: query });
                    if (result.success && result.tasks && result.tasks.length > 0) {
                        renderAutocomplete(result.tasks);
                    } else {
                        hideAutocomplete();
                    }
                } catch (err) { hideAutocomplete(); }
            }, 250);
        });

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                handlePlayClick();
            } else if (e.key === 'Escape') {
                hideAutocomplete();
            }
        });

        // Cerrar autocomplete al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (!input.contains(e.target) && !dropdown.contains(e.target)) {
                hideAutocomplete();
            }
        });
    }

    function renderAutocomplete(tasks) {
        const dropdown = document.getElementById('trackerAutocomplete');
        const input = document.getElementById('trackerInput');
        if (!dropdown || !input) return;

        const html = tasks.slice(0, 6).map(task => {
            const alliance = task.alliance_name ? `<span class="ac-alliance"><i class="bi bi-building" aria-hidden="true"></i> ${escapeHtml(task.alliance_name)}</span>` : '';
            const status = task.status || 'pending';
            const statusLabels = {
                pending:     t('tasks.status_pending', 'Pendiente'),
                in_progress: t('tasks.status_in_progress', 'En progreso'),
                paused:      t('tasks.status_paused', 'Pausada'),
                completed:   t('tasks.status_completed', 'Completada'),
            };
            const statusClasses = {
                pending:     'lozenge-default',
                in_progress: 'lozenge-info',
                paused:      'lozenge-warning',
                completed:   'lozenge-success',
            };
            return `
                <button type="button" class="ac-item" role="option" data-task-id="${task.id}" data-title="${escapeHtml(task.title)}">
                    <i class="bi bi-arrow-counterclockwise ac-icon" aria-hidden="true"></i>
                    <div class="ac-body">
                        <span class="ac-title">${escapeHtml(task.title)}</span>
                        <div class="ac-meta">
                            ${alliance}
                            <span class="lozenge ${statusClasses[status] || 'lozenge-default'}">${statusLabels[status] || status}</span>
                        </div>
                    </div>
                </button>
            `;
        }).join('');

        dropdown.innerHTML = html;
        dropdown.classList.remove('d-none');
        input.setAttribute('aria-expanded', 'true');

        dropdown.querySelectorAll('.ac-item').forEach(item => {
            item.addEventListener('click', () => {
                const taskId = parseInt(item.dataset.taskId, 10);
                const title = item.dataset.title;
                input.value = title;
                state.selectedExistingTaskId = taskId;
                hideAutocomplete();
                // Iniciar de inmediato
                handlePlayClick();
            });
        });
    }

    function hideAutocomplete() {
        const dropdown = document.getElementById('trackerAutocomplete');
        const input = document.getElementById('trackerInput');
        if (!dropdown || !input) return;
        dropdown.classList.add('d-none');
        dropdown.innerHTML = '';
        input.setAttribute('aria-expanded', 'false');
    }

    /** ========================================================
     * Play (iniciar timer inline)
     * ======================================================== */

    async function handlePlayClick() {
        const input = document.getElementById('trackerInput');
        if (!input) return;
        const title = input.value.trim();

        if (!title) {
            input.focus();
            Toast.warning(t('tasks.err_title_required', 'Escribe un nombre para la tarea.'));
            return;
        }

        const btn = document.getElementById('btnStartTimer');
        if (btn) btn.disabled = true;

        try {
            const payload = { title: title };
            if (state.selectedExistingTaskId) {
                payload.task_id = state.selectedExistingTaskId;
            }

            const result = await api('timer_start', payload);
            if (result.success) {
                state.running = true;
                state.taskId = result.task_id;
                state.title = result.title;
                state.allianceId = result.alliance_id || null;
                state.allianceName = result.alliance_name || null;
                state.tagIds = (result.tag_ids || '').split(',').filter(Boolean).map(id => parseInt(id, 10));
                state.tagNames = result.tag_names || '';
                state.priority = result.priority || 'medium';
                state.dueDate = result.due_date || null;
                state.description = result.description || '';
                state.startTime = new Date();

                renderActiveCard();
                startTicking();
                hideAutocomplete();
                Toast.success(t('tasks.timer_started', 'Cronometro iniciado.'));
                if (typeof loadList === 'function') loadList();
            } else {
                Toast.error(result.message || t('tasks.err_start', 'No se pudo iniciar.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    /** ========================================================
     * Slide panel de edicion (todos los campos)
     * ======================================================== */

    function openEditForm(opts = {}) {
        const forceComplete = opts.forceComplete || false;

        // Si se pasa opts.task editamos una tarea arbitraria (de la lista).
        // Si no, es la tarea del timer activo y tomamos los valores del state.
        const current = opts.task ? {
            id:          opts.task.id,
            title:       opts.task.title || '',
            description: opts.task.description || '',
            allianceId:  opts.task.alliance_id ? parseInt(opts.task.alliance_id, 10) : null,
            tagIds:      String(opts.task.tag_ids || '').split(',').filter(Boolean).map(id => parseInt(id, 10)),
            priority:    opts.task.priority || 'medium',
            dueDate:     opts.task.due_date || '',
        } : {
            id:          state.taskId,
            title:       state.title || '',
            description: state.description || '',
            allianceId:  state.allianceId,
            tagIds:      state.tagIds,
            priority:    state.priority || 'medium',
            dueDate:     state.dueDate || '',
        };

        const allianceOpts = alliances.map(a =>
            `<option value="${a.id}" ${current.allianceId == a.id ? 'selected' : ''}>${escapeHtml(a.name)}</option>`
        ).join('');

        const tagChips = allTags.map(tag => {
            const selected = current.tagIds.includes(tag.id);
            return `
                <label class="task-tag-chip ${selected ? 'is-selected' : ''}" data-tag-id="${tag.id}">
                    <input type="checkbox" name="tag_ids[]" value="${tag.id}" ${selected ? 'checked' : ''}>
                    <span>${escapeHtml(tag.name)}</span>
                </label>
            `;
        }).join('');

        const priorityLabels = {
            low:    t('tasks.priority_low',    'Baja'),
            medium: t('tasks.priority_medium', 'Media'),
            high:   t('tasks.priority_high',   'Alta'),
            urgent: t('tasks.priority_urgent', 'Urgente'),
        };
        const priorityOpts = ['low', 'medium', 'high', 'urgent'].map(p =>
            `<option value="${p}" ${current.priority === p ? 'selected' : ''}>${escapeHtml(priorityLabels[p])}</option>`
        ).join('');

        // Guardamos current en opts para que handleEditSubmit lo use
        opts._current = current;

        const completeNotice = forceComplete
            ? `<div class="alert alert-warning mb-200" role="alert">
                  <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
                  <span class="alert-content">${t('tasks.force_complete_msg', 'Completa la alianza y etiquetas antes de pausar o completar.')}</span>
               </div>`
            : '';

        const html = `
            <form id="taskEditForm" novalidate>
                ${completeNotice}

                <div class="form-group">
                    <label for="fTaskTitle" class="form-label">
                        ${t('tasks.field_task', 'Tarea')} <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="fTaskTitle" name="title" class="form-control" required
                           value="${escapeHtml(current.title)}">
                    <p class="form-error" id="fTaskTitleError" aria-live="polite"></p>
                </div>

                <div class="form-group">
                    <label for="fTaskDescription" class="form-label">${t('tasks.field_description', 'Descripcion')}</label>
                    <textarea id="fTaskDescription" name="description" class="form-control" rows="3"
                              placeholder="${t('tasks.description_placeholder', 'Agrega detalles, notas o contexto adicional...')}"
                    >${escapeHtml(current.description)}</textarea>
                </div>

                <div class="form-group">
                    <label for="fTaskAlliance" class="form-label">
                        ${t('tasks.field_alliance', 'Alianza')} <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <select id="fTaskAlliance" name="alliance_id" class="form-control" required>
                        <option value="">${t('tasks.alliance_placeholder', 'Seleccionar...')}</option>
                        ${allianceOpts}
                    </select>
                    <p class="form-error" id="fTaskAllianceError" aria-live="polite"></p>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        ${t('tasks.field_tags', 'Etiquetas')} <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <div class="task-tag-chips" id="taskTagChips" role="group">
                        ${tagChips || `<p class="text-subtle text-sm m-0">${t('tasks.no_tags_yet', 'Aun no hay etiquetas. Crea la primera:')}</p>`}
                    </div>
                    <div class="task-tag-create">
                        <input type="text" id="fNewTag" class="form-control"
                               placeholder="${t('tasks.tag_new_placeholder', 'Nueva etiqueta...')}"
                               maxlength="30">
                        <button type="button" class="btn btn-subtle btn-sm" id="btnCreateTag">
                            <i class="bi bi-plus" aria-hidden="true"></i>
                            ${t('tasks.btn_add_tag', 'Agregar')}
                        </button>
                    </div>
                    <p class="form-error" id="fTaskTagsError" aria-live="polite"></p>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="fTaskPriority" class="form-label">${t('tasks.field_priority', 'Prioridad')}</label>
                        <select id="fTaskPriority" name="priority" class="form-control">
                            ${priorityOpts}
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fTaskDueDate" class="form-label">${t('tasks.field_due_date', 'Fecha de vencimiento')}</label>
                        <input type="date" id="fTaskDueDate" name="due_date" class="form-control"
                               value="${escapeHtml(current.dueDate)}">
                    </div>
                </div>

                <div class="alert alert-danger d-none" id="taskEditError" role="alert">
                    <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
                    <span class="alert-content" id="taskEditErrorText"></span>
                </div>
            </form>
        `;

        SlidePanel.open(
            forceComplete
                ? t('tasks.complete_data_title', 'Completa la informacion')
                : t('tasks.edit_task_title', 'Editar tarea'),
            html
        );

        SlidePanel.setFooter(`
            <button type="button" class="btn btn-subtle" id="taskEditCancel">
                ${t('common.cancel', 'Cancelar')}
            </button>
            <button type="button" class="btn btn-primary" id="taskEditSubmit">
                <i class="bi bi-check2" aria-hidden="true"></i>
                <span class="btn-text">${t('common.save', 'Guardar')}</span>
            </button>
        `);

        setupEditFormHandlers(opts);
    }

    function setupEditFormHandlers(opts = {}) {
        document.getElementById('taskEditCancel').addEventListener('click', () => SlidePanel.close());
        document.getElementById('taskEditSubmit').addEventListener('click', () => handleEditSubmit(opts));

        // Chips toggle visual
        const chipContainer = document.getElementById('taskTagChips');
        if (chipContainer) {
            chipContainer.addEventListener('change', (e) => {
                if (e.target.matches('input[type="checkbox"]')) {
                    const chip = e.target.closest('.task-tag-chip');
                    if (chip) chip.classList.toggle('is-selected', e.target.checked);
                }
            });
        }

        // Crear nueva etiqueta
        const btnCreateTag = document.getElementById('btnCreateTag');
        const inputNewTag = document.getElementById('fNewTag');
        if (btnCreateTag && inputNewTag) {
            const handleCreate = async () => {
                const name = inputNewTag.value.trim();
                if (!name) return;

                btnCreateTag.disabled = true;
                try {
                    const result = await api('tags_create', { name: name, color: '#585d8a' });
                    if (result.success) {
                        allTags.push({ id: result.tag.id, name: result.tag.name, color: result.tag.color });
                        const currentSelection = Array.from(chipContainer.querySelectorAll('input:checked'))
                            .map(el => parseInt(el.value, 10));
                        currentSelection.push(result.tag.id);

                        chipContainer.innerHTML = allTags.map(tag => {
                            const selected = currentSelection.includes(tag.id);
                            return `
                                <label class="task-tag-chip ${selected ? 'is-selected' : ''}" data-tag-id="${tag.id}">
                                    <input type="checkbox" name="tag_ids[]" value="${tag.id}" ${selected ? 'checked' : ''}>
                                    <span>${escapeHtml(tag.name)}</span>
                                </label>
                            `;
                        }).join('');
                        inputNewTag.value = '';
                    } else {
                        Toast.error(result.message || t('tasks.err_create_tag', 'No se pudo crear.'));
                    }
                } catch (err) {
                    Toast.error(t('common.err_network', 'Error de red.'));
                } finally {
                    btnCreateTag.disabled = false;
                }
            };

            btnCreateTag.addEventListener('click', handleCreate);
            inputNewTag.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleCreate();
                }
            });
        }
    }

    async function handleEditSubmit(opts = {}) {
        const form = document.getElementById('taskEditForm');
        const submitBtn = document.getElementById('taskEditSubmit');
        if (!form || !submitBtn) return;

        // Limpiar errores
        ['fTaskTitleError', 'fTaskAllianceError', 'fTaskTagsError'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = '';
        });
        document.querySelectorAll('#taskEditForm .form-control-error').forEach(el => el.classList.remove('form-control-error'));

        const title = form.title.value.trim();
        const description = form.description.value.trim();
        const allianceId = form.alliance_id.value;
        const priority = form.priority.value;
        const dueDate = form.due_date.value;
        const tagIds = Array.from(form.querySelectorAll('input[name="tag_ids[]"]:checked')).map(el => el.value);

        let hasError = false;
        if (!title) {
            document.getElementById('fTaskTitle').classList.add('form-control-error');
            document.getElementById('fTaskTitleError').textContent = t('tasks.err_title', 'El titulo es obligatorio.');
            hasError = true;
        }
        if (opts.forceComplete && !allianceId) {
            document.getElementById('fTaskAlliance').classList.add('form-control-error');
            document.getElementById('fTaskAllianceError').textContent = t('tasks.err_alliance', 'Selecciona una alianza.');
            hasError = true;
        }
        if (opts.forceComplete && tagIds.length === 0) {
            document.getElementById('fTaskTagsError').textContent = t('tasks.err_tags', 'Selecciona al menos una etiqueta.');
            hasError = true;
        }
        if (hasError) return;

        // ID de la tarea que estamos editando (arbitraria o la del timer)
        const editingTaskId = opts._current?.id || state.taskId;
        // Es la tarea del timer si coincide con state.taskId Y hay timer corriendo
        const isTimerTask = !opts.task && state.running && state.taskId === editingTaskId;

        submitBtn.disabled = true;
        const btnText = submitBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('common.saving', 'Guardando...');

        try {
            const result = await api('update', {
                task_id: editingTaskId,
                title: title,
                description: description,
                alliance_id: allianceId,
                priority: priority,
                due_date: dueDate,
                tag_ids: tagIds.join(','),
            });
            if (result.success) {
                // Actualizar el estado del tracker SOLO si es la tarea del timer activo
                if (isTimerTask) {
                    state.title = title;
                    state.description = description;
                    state.allianceId = allianceId ? parseInt(allianceId, 10) : null;
                    const alliance = alliances.find(a => a.id == allianceId);
                    state.allianceName = alliance?.name || null;
                    state.priority = priority;
                    state.dueDate = dueDate || null;
                    state.tagIds = tagIds.map(id => parseInt(id, 10));
                    state.tagNames = tagIds.map(id => {
                        const tag = allTags.find(tg => tg.id == id);
                        return tag?.name || '';
                    }).filter(Boolean).join(', ');
                    renderActiveCard();
                }

                SlidePanel.close();
                Toast.success(t('tasks.task_updated', 'Cambios guardados.'));
                if (typeof loadList === 'function') loadList();

                // Si venia de forceComplete, ejecutar la accion pendiente
                if (opts.onComplete) opts.onComplete();
            } else {
                const err = document.getElementById('taskEditError');
                document.getElementById('taskEditErrorText').textContent = result.message || t('tasks.err_update', 'No se pudo actualizar.');
                err.classList.remove('d-none');
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            submitBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    /** ========================================================
     * Pausa / Detener / Descartar
     * ======================================================== */

    function isIncomplete() {
        return !state.allianceId || state.tagIds.length === 0;
    }

    async function handlePause() {
        if (isIncomplete()) {
            openEditForm({
                forceComplete: true,
                onComplete: () => setTimeout(handlePause, 300),
            });
            return;
        }

        const btn = document.getElementById('btnPauseTimer');
        if (btn) btn.disabled = true;

        try {
            const result = await api('timer_pause');
            if (result.success) {
                Toast.success(
                    t('tasks.timer_paused', 'Cronometro pausado ({duration})').replace('{duration}', formatDuration(result.duration || 0))
                );
                resetState();
                renderEmpty();
                if (typeof loadList === 'function') loadList();
            } else {
                Toast.error(result.message || t('tasks.err_pause', 'No se pudo pausar.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    async function handleStop() {
        if (isIncomplete()) {
            openEditForm({
                forceComplete: true,
                onComplete: () => setTimeout(handleStop, 300),
            });
            return;
        }

        const confirmed = await ConfirmModal.show({
            title:   t('tasks.stop_title', 'Completar tarea'),
            message: t('tasks.stop_message', 'Se guardara el tiempo registrado y la tarea se marcara como completada.'),
            acceptText: t('tasks.btn_stop', 'Completar'),
            acceptVariant: 'success',
            icon: 'bi-check-lg',
            variant: 'success',
        });
        if (!confirmed) return;

        const btn = document.getElementById('btnStopTimer');
        if (btn) btn.disabled = true;

        try {
            const result = await api('timer_stop');
            if (result.success) {
                Toast.success(
                    t('tasks.timer_stopped', 'Tarea completada ({duration})').replace('{duration}', formatDuration(result.duration || 0))
                );
                resetState();
                renderEmpty();
                if (typeof loadList === 'function') loadList();
            } else {
                Toast.error(result.message || t('tasks.err_stop', 'No se pudo completar.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    async function handleDiscard() {
        const confirmed = await ConfirmModal.show({
            title:   t('tasks.discard_title', 'Descartar cronometro'),
            message: t('tasks.discard_message', 'Se eliminara el tiempo registrado sin guardar. Esta accion no se puede deshacer.'),
            acceptText: t('tasks.btn_discard_confirm', 'Descartar'),
            acceptVariant: 'danger',
            icon: 'bi-trash',
            variant: 'danger',
        });
        if (!confirmed) return;

        try {
            const result = await api('timer_discard');
            if (result.success) {
                Toast.info(t('tasks.timer_discarded', 'Cronometro descartado.'));
                resetState();
                renderEmpty();
                if (typeof loadList === 'function') loadList();
            } else {
                Toast.error(result.message || t('tasks.err_discard', 'No se pudo descartar.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        }
    }

    /** ========================================================
     * Restaurar timer existente
     * ======================================================== */

    async function restoreTimer() {
        try {
            const result = await api('timer_status');
            if (result.success && result.running && result.entry) {
                const entry = result.entry;
                state.running = true;
                state.taskId = entry.task_id;
                state.title = entry.title;
                state.allianceName = entry.alliance_name || null;
                state.tagNames = entry.tag_names || '';
                state.priority = entry.priority || 'medium';
                state.dueDate = entry.due_date || null;
                state.description = entry.description || '';
                state.startTime = new Date(entry.start_time.replace(' ', 'T'));

                if (state.allianceName) {
                    const match = alliances.find(a => a.name === state.allianceName);
                    if (match) state.allianceId = match.id;
                }

                if (entry.tag_ids) {
                    state.tagIds = String(entry.tag_ids).split(',').filter(Boolean).map(id => parseInt(id, 10));
                }

                renderActiveCard();
                startTicking();
            }
        } catch (err) { /* ignore */ }
    }

    /** ========================================================
     * LISTADO (sub-fase 4.2)
     * ======================================================== */

    const listState = {
        data: { active: [], scheduled: [], by_date: {}, day_totals: {} },
        filters: { search: '', alliance: '', priority: '', tag: '', dateFrom: '', dateTo: '' },
    };

    function initListDefaults() {
        const to = new Date();
        const from = new Date();
        from.setDate(to.getDate() - 7);
        const fmt = d => d.toISOString().slice(0, 10);
        listState.filters.dateFrom = fmt(from);
        listState.filters.dateTo = fmt(to);
        const fromInput = document.getElementById('filterDateFrom');
        const toInput = document.getElementById('filterDateTo');
        if (fromInput) fromInput.value = listState.filters.dateFrom;
        if (toInput) toInput.value = listState.filters.dateTo;
    }

    async function loadList() {
        try {
            const result = await api('list', {
                date_from: listState.filters.dateFrom,
                date_to: listState.filters.dateTo,
                alliance_id: listState.filters.alliance,
            });
            if (result.success) {
                listState.data = {
                    active: result.active || [],
                    scheduled: result.scheduled || [],
                    by_date: result.by_date || {},
                    day_totals: result.day_totals || {},
                };
                renderAllPanels();
            }
        } catch (err) {
            console.error('List error:', err);
        }
    }

    function applyLocalFilters(items) {
        const { search, priority, tag } = listState.filters;
        return items.filter(task => {
            if (search) {
                const hay = (task.title || '').toLowerCase() + ' ' + (task.alliance_name || '').toLowerCase();
                if (!hay.includes(search.toLowerCase())) return false;
            }
            if (priority && (task.priority || 'medium') !== priority) return false;
            if (tag) {
                const ids = String(task.tag_ids || '').split(',').filter(Boolean);
                if (!ids.includes(String(tag))) return false;
            }
            return true;
        });
    }

    function renderAllPanels() {
        renderScheduledPanel();
        renderActivePanel();
        renderYesterdayPanel();
        renderHistoryPanel();
        updateSectionCounts();
    }

    function todayStr() {
        return new Date().toISOString().slice(0, 10);
    }

    function yesterdayStr() {
        const d = new Date();
        d.setDate(d.getDate() - 1);
        return d.toISOString().slice(0, 10);
    }

    // Agrupa entries de una fecha por task_id
    function groupEntriesByTask(entries) {
        const grouped = {};
        entries.forEach(e => {
            const id = e.task_id;
            if (!grouped[id]) {
                grouped[id] = {
                    task_id: id,
                    title: e.task_title,
                    alliance_name: e.alliance_name,
                    tag_names: e.tag_names,
                    status: e.task_status,
                    total_seconds: 0,
                    entry_count: 0,
                    priority: 'medium',
                };
            }
            grouped[id].total_seconds += parseInt(e.duration_seconds, 10) || 0;
            grouped[id].entry_count += 1;
        });
        return Object.values(grouped);
    }

    function updateSectionCounts() {
        const active = applyLocalFilters(listState.data.active).length;
        const scheduled = applyLocalFilters(listState.data.scheduled).length;
        const yesterdayEntries = listState.data.by_date[yesterdayStr()] || [];
        const yesterdayTasks = applyLocalFilters(groupEntriesByTask(yesterdayEntries)).length;
        const history = Object.entries(listState.data.by_date)
            .filter(([date]) => date !== yesterdayStr())
            .reduce((acc, [, arr]) => acc + applyLocalFilters(arr.map(e => ({
                title: e.task_title,
                alliance_name: e.alliance_name,
                priority: 'medium',
                tag_ids: '',
            }))).length, 0);

        document.getElementById('countActive').textContent = active;
        document.getElementById('countScheduled').textContent = scheduled;
        document.getElementById('countYesterday').textContent = yesterdayTasks;
        document.getElementById('countHistory').textContent = history;
    }

    function emptyState(icon, title, desc) {
        return `
            <div class="empty-state p-300">
                <div class="empty-state-icon"><i class="bi ${icon}" aria-hidden="true"></i></div>
                <h3 class="empty-state-title">${escapeHtml(title)}</h3>
                <p class="empty-state-description">${escapeHtml(desc)}</p>
            </div>
        `;
    }

    function priorityChip(priority) {
        if (!priority || priority === 'medium') return '';
        const labels = {
            low:    t('tasks.priority_low', 'Baja'),
            high:   t('tasks.priority_high', 'Alta'),
            urgent: t('tasks.priority_urgent', 'Urgente'),
        };
        const label = labels[priority];
        if (!label) return '';
        return `<span class="tracker-meta-chip tracker-priority-${priority}"><i class="bi bi-flag-fill" aria-hidden="true"></i>${escapeHtml(label)}</span>`;
    }

    function tagChipsHtml(tagNames) {
        if (!tagNames) return '';
        return tagNames.split(',').map(n => n.trim()).filter(Boolean).map(name =>
            `<span class="tracker-meta-chip"><i class="bi bi-tag" aria-hidden="true"></i>${escapeHtml(name)}</span>`
        ).join('');
    }

    function allianceChip(name) {
        if (!name) return `<span class="tracker-meta-chip tracker-meta-empty"><i class="bi bi-building" aria-hidden="true"></i>${escapeHtml(t('tasks.no_alliance', 'Sin alianza'))}</span>`;
        return `<span class="tracker-meta-chip tracker-meta-alliance"><i class="bi bi-building" aria-hidden="true"></i>${escapeHtml(name)}</span>`;
    }

    // Encabezados de la tabla-grid
    function gridTableHeader() {
        return `
            <div class="grid-table-head" role="row">
                <span role="columnheader">${escapeHtml(t('tasks.col_alliance', 'Alianza'))}</span>
                <span role="columnheader">${escapeHtml(t('tasks.col_task', 'Tarea'))}</span>
                <span role="columnheader">${escapeHtml(t('tasks.col_status', 'Estado'))}</span>
                <span role="columnheader">${escapeHtml(t('tasks.col_tags', 'Etiquetas'))}</span>
                <span role="columnheader" class="text-right">${escapeHtml(t('tasks.col_total_time', 'Tiempo'))}</span>
                <span role="columnheader" class="sr-only">${escapeHtml(t('common.actions', 'Acciones'))}</span>
            </div>
        `;
    }

    function statusLozenge(status) {
        const map = {
            in_progress: { cls: 'lozenge-info',    label: t('tasks.status_in_progress', 'En progreso') },
            paused:      { cls: 'lozenge-warning', label: t('tasks.status_paused',      'Pausada') },
            pending:     { cls: 'lozenge-default', label: t('tasks.status_pending',     'Pendiente') },
            completed:   { cls: 'lozenge-success', label: t('tasks.status_completed',   'Completada') },
        };
        const entry = map[status] || map.pending;
        return `<span class="lozenge ${entry.cls}">${entry.label}</span>`;
    }

    // Fila tabla-grid reutilizable para activas y ayer
    function gridTableRow(task, opts = {}) {
        const total = task.total_seconds ? formatDuration(parseInt(task.total_seconds, 10)) : '—';
        const isCurrent = state.running && state.taskId == task.task_id;
        const count = task.entry_count ? `<span class="task-entry-count" title="${t('tasks.entry_count_hint', 'Registros')}">${task.entry_count}</span>` : '';

        const runningBadge = isCurrent
            ? `<span class="lozenge lozenge-success"><i class="bi bi-record-fill" aria-hidden="true"></i> ${t('tasks.is_running', 'Corriendo')}</span>`
            : '';

        const resumeBtn = isCurrent ? '' : `
            <button type="button" class="btn-icon" data-action="resume" data-task-id="${task.task_id}" data-title="${escapeHtml(task.title)}"
                    data-tooltip="${t('tasks.btn_resume', 'Reanudar')}" data-tooltip-position="top" aria-label="${t('tasks.btn_resume', 'Reanudar')}">
                <i class="bi bi-play-fill" aria-hidden="true"></i>
            </button>`;

        return `
            <div class="grid-table-row" role="row">
                <span class="grid-cell cell-alliance" role="gridcell">
                    ${task.alliance_name
                        ? `<span class="cell-alliance-chip"><i class="bi bi-building" aria-hidden="true"></i> ${escapeHtml(task.alliance_name)}</span>`
                        : `<span class="text-subtle">${escapeHtml(t('tasks.no_alliance', 'Sin alianza'))}</span>`}
                </span>
                <span class="grid-cell cell-task" role="gridcell">
                    <span class="cell-task-title">${escapeHtml(task.title)}</span>
                    ${count}
                </span>
                <span class="grid-cell cell-status" role="gridcell">
                    ${runningBadge || statusLozenge(task.status)}
                </span>
                <span class="grid-cell cell-tags" role="gridcell">
                    ${task.tag_names ? tagChipsHtml(task.tag_names) : `<span class="text-subtle text-sm">—</span>`}
                </span>
                <span class="grid-cell cell-time text-right text-mono" role="gridcell">${total}</span>
                <span class="grid-cell cell-actions" role="gridcell">
                    ${resumeBtn}
                    <button type="button" class="btn-icon" data-action="edit" data-task-id="${task.task_id}"
                            data-tooltip="${t('tasks.btn_edit', 'Editar')}" data-tooltip-position="top" aria-label="${t('tasks.btn_edit', 'Editar')}">
                        <i class="bi bi-pencil" aria-hidden="true"></i>
                    </button>
                </span>
            </div>
        `;
    }

    function renderActivePanel() {
        const container = document.getElementById('contentActive');
        if (!container) return;
        // Normalizar: active tasks tienen id (no task_id)
        const items = applyLocalFilters(listState.data.active).map(t => ({
            ...t,
            task_id: t.id,
            entry_count: (listState.data.by_date && Object.values(listState.data.by_date)
                .flat().filter(e => e.task_id == t.id).length) || null,
        }));

        if (items.length === 0) {
            container.innerHTML = emptyState('bi-play-circle',
                t('tasks.empty_active_title', 'No hay tareas activas'),
                t('tasks.empty_active_desc', 'Aqui apareceran las tareas en progreso o pausadas. Inicia un cronometro para comenzar.'));
            return;
        }

        container.innerHTML = gridTableHeader() + items.map(task => gridTableRow(task)).join('');
    }

    function renderYesterdayPanel() {
        const section = document.getElementById('sectionYesterday');
        const container = document.getElementById('contentYesterday');
        if (!container || !section) return;

        const entries = listState.data.by_date[yesterdayStr()] || [];
        const items = applyLocalFilters(groupEntriesByTask(entries));

        // Ocultar la seccion si no hay contenido
        if (items.length === 0) {
            section.classList.add('d-none');
            container.innerHTML = '';
            return;
        }

        section.classList.remove('d-none');
        container.innerHTML = gridTableHeader() + items.map(task => gridTableRow(task)).join('');
    }

    function renderScheduledPanel() {
        const container = document.getElementById('contentScheduled');
        if (!container) return;

        const today = todayStr();
        const priorityOrder = { urgent: 1, high: 2, medium: 3, low: 4 };

        // Orden: vencidas -> urgent -> high -> medium -> low; dentro de cada bucket, due_date asc
        const sorted = applyLocalFilters(listState.data.scheduled).slice().sort((a, b) => {
            const aOverdue = a.due_date && a.due_date < today ? 0 : 1;
            const bOverdue = b.due_date && b.due_date < today ? 0 : 1;
            if (aOverdue !== bOverdue) return aOverdue - bOverdue;
            const aP = priorityOrder[a.priority || 'medium'] || 5;
            const bP = priorityOrder[b.priority || 'medium'] || 5;
            if (aP !== bP) return aP - bP;
            return (a.due_date || '9999-12-31').localeCompare(b.due_date || '9999-12-31');
        });

        if (sorted.length === 0) {
            container.innerHTML = emptyState('bi-calendar-check',
                t('tasks.empty_scheduled_title', 'No hay tareas proximas'),
                t('tasks.empty_scheduled_desc', 'Aqui apareceran las tareas pendientes sin tiempo registrado, ordenadas por prioridad y fecha de vencimiento.'));
            return;
        }

        container.innerHTML = sorted.map(task => {
            const overdue = task.due_date && task.due_date < today;
            const priority = task.priority || 'medium';
            const priorityLabels = {
                low:    t('tasks.priority_low', 'Baja'),
                medium: t('tasks.priority_medium', 'Media'),
                high:   t('tasks.priority_high', 'Alta'),
                urgent: t('tasks.priority_urgent', 'Urgente'),
            };

            const dueInfo = task.due_date ? `
                <span class="task-card-due ${overdue ? 'is-overdue' : ''}">
                    <i class="bi bi-calendar${overdue ? '-x-fill' : ''}" aria-hidden="true"></i>
                    ${escapeHtml(task.due_date)}
                </span>` : `<span class="task-card-due task-card-due-empty">—</span>`;

            const overdueTag = overdue
                ? `<span class="task-card-overdue-tag">${escapeHtml(t('tasks.is_overdue', 'Vencida'))}</span>`
                : '';

            const allianceBadge = task.alliance_name
                ? `<span class="task-card-alliance" title="${escapeHtml(task.alliance_name)}">${escapeHtml(task.alliance_name)}</span>`
                : `<span class="task-card-alliance task-card-alliance-empty">${escapeHtml(t('tasks.no_alliance', 'Sin alianza'))}</span>`;

            const tagsPlain = task.tag_names
                ? `<div class="task-card-tags">${task.tag_names.split(',').map(n => n.trim()).filter(Boolean).map(name =>
                        `<span class="task-card-tag">${escapeHtml(name)}</span>`
                    ).join('')}</div>`
                : '';

            return `
                <article class="task-card task-card-priority-${priority} ${overdue ? 'is-overdue' : ''}">
                    <div class="task-card-top">
                        <span class="task-card-priority-label">${escapeHtml(priorityLabels[priority] || priority)}</span>
                        ${overdueTag}
                        ${allianceBadge}
                    </div>
                    <h4 class="task-card-title">${escapeHtml(task.title)}</h4>
                    ${tagsPlain}
                    <div class="task-card-footer">
                        ${dueInfo}
                        <div class="task-card-actions">
                            <button type="button" class="btn-icon" data-action="edit" data-task-id="${task.id}"
                                    data-tooltip="${t('tasks.btn_edit', 'Editar')}" data-tooltip-position="top" aria-label="${t('tasks.btn_edit', 'Editar')}">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="btn-icon btn-icon-success" data-action="resume" data-task-id="${task.id}" data-title="${escapeHtml(task.title)}"
                                    data-tooltip="${t('tasks.btn_start', 'Iniciar')}" data-tooltip-position="top" aria-label="${t('tasks.btn_start', 'Iniciar')}">
                                <i class="bi bi-play-fill" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="btn-icon btn-icon-danger" data-action="delete" data-task-id="${task.id}" data-title="${escapeHtml(task.title)}"
                                    data-tooltip="${t('tasks.btn_delete', 'Eliminar')}" data-tooltip-position="top" aria-label="${t('tasks.btn_delete', 'Eliminar')}">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </article>
            `;
        }).join('');
    }

    function renderHistoryPanel() {
        const container = document.getElementById('contentHistory');
        if (!container) return;
        const byDate = listState.data.by_date || {};
        const yday = yesterdayStr();
        const dates = Object.keys(byDate).filter(d => d !== yday).sort().reverse();

        const filteredDates = dates.map(date => {
            const entries = byDate[date].filter(e => {
                const { search } = listState.filters;
                if (search) {
                    const hay = (e.task_title || '').toLowerCase() + ' ' + (e.alliance_name || '').toLowerCase();
                    if (!hay.includes(search.toLowerCase())) return false;
                }
                return true;
            });
            return { date, entries };
        }).filter(d => d.entries.length > 0);

        if (filteredDates.length === 0) {
            container.innerHTML = emptyState('bi-clock-history',
                t('tasks.empty_history_title', 'Sin historial en este rango'),
                t('tasks.empty_history_desc', 'Ajusta las fechas o empieza a registrar tiempo para ver el historial aqui.'));
            return;
        }

        container.innerHTML = filteredDates.map(({ date, entries }) => {
            const total = entries.reduce((a, e) => a + (parseInt(e.duration_seconds, 10) || 0), 0);
            const dateLabel = new Date(date + 'T00:00:00').toLocaleDateString(undefined, { weekday: 'long', day: '2-digit', month: 'short', year: 'numeric' });

            const rows = entries.map(e => {
                const dur = formatDuration(parseInt(e.duration_seconds, 10) || 0);
                const hour = e.start_time.slice(11, 16);
                return `
                    <tr>
                        <td class="text-mono text-subtle">${hour}</td>
                        <td>
                            <div class="task-item-title">${escapeHtml(e.task_title)}</div>
                            <div class="task-item-meta">
                                ${allianceChip(e.alliance_name)}
                                ${tagChipsHtml(e.tag_names)}
                            </div>
                        </td>
                        <td class="text-right text-mono">${dur}</td>
                    </tr>
                `;
            }).join('');

            return `
                <div class="history-day">
                    <div class="history-day-header">
                        <span class="history-day-date">${dateLabel}</span>
                        <span class="history-day-total"><i class="bi bi-clock" aria-hidden="true"></i> ${formatDuration(total)}</span>
                    </div>
                    <table class="table table-compact history-table">
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            `;
        }).join('');
    }

    function setupListBindings() {
        // Filtros
        const searchInput = document.getElementById('filterSearch');
        let searchDebounce = null;
        searchInput?.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                listState.filters.search = searchInput.value.trim();
                renderAllPanels();
            }, 200);
        });

        document.getElementById('filterDateFrom')?.addEventListener('change', (e) => {
            listState.filters.dateFrom = e.target.value;
            loadList();
        });
        document.getElementById('filterDateTo')?.addEventListener('change', (e) => {
            listState.filters.dateTo = e.target.value;
            loadList();
        });
        document.getElementById('filterAlliance')?.addEventListener('change', (e) => {
            listState.filters.alliance = e.target.value;
            loadList();
        });
        document.getElementById('filterPriority')?.addEventListener('change', (e) => {
            listState.filters.priority = e.target.value;
            renderAllPanels();
        });
        document.getElementById('filterTag')?.addEventListener('change', (e) => {
            listState.filters.tag = e.target.value;
            renderAllPanels();
        });
        document.getElementById('btnClearFilters')?.addEventListener('click', clearFilters);

        // Delegation para acciones por item
        document.querySelector('.tasks-list-section')?.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const action = btn.dataset.action;
            const taskId = parseInt(btn.dataset.taskId, 10);
            if (action === 'resume') {
                resumeTask(taskId, btn.dataset.title);
            } else if (action === 'edit') {
                editTaskById(taskId);
            } else if (action === 'delete') {
                deleteTaskById(taskId, btn.dataset.title);
            }
        });
    }

    async function deleteTaskById(taskId, title) {
        const confirmed = await ConfirmModal.show({
            title:   t('tasks.delete_title', 'Eliminar tarea'),
            message: t('tasks.delete_message', 'Se eliminara la tarea "{title}" y todas sus entradas de tiempo. Esta accion no se puede deshacer.')
                .replace('{title}', title || ''),
            acceptText: t('tasks.btn_delete', 'Eliminar'),
            acceptVariant: 'danger',
            icon: 'bi-trash',
            variant: 'danger',
        });
        if (!confirmed) return;

        try {
            const result = await api('delete', { task_id: taskId });
            if (result.success) {
                Toast.success(t('tasks.task_deleted', 'Tarea eliminada.'));
                loadList();
            } else {
                Toast.error(result.message || t('tasks.err_delete', 'No se pudo eliminar la tarea.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        }
    }

    function clearFilters() {
        listState.filters = { search: '', alliance: '', priority: '', tag: '', dateFrom: '', dateTo: '' };
        ['filterSearch', 'filterAlliance', 'filterPriority', 'filterTag'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
        initListDefaults();
        loadList();
    }

    async function resumeTask(taskId, title) {
        if (state.running) {
            Toast.warning(t('tasks.err_already_running', 'Hay un cronometro corriendo. Pausalo o completalo primero.'));
            return;
        }
        const input = document.getElementById('trackerInput');
        if (input) input.value = title || '';
        state.selectedExistingTaskId = taskId;
        handlePlayClick().then(() => loadList());
    }

    async function editTaskById(taskId) {
        try {
            const result = await api('get', { task_id: taskId });
            if (result.success && result.task) {
                // Abrir el form con la tarea como dato aislado (no toca state del timer)
                openEditForm({ task: result.task });
            } else {
                Toast.error(result.message || t('tasks.err_update', 'No se pudo abrir la tarea.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        }
    }

    /** ========================================================
     * Bindings
     * ======================================================== */

    document.addEventListener('DOMContentLoaded', () => {
        setupAutocomplete();
        document.getElementById('btnStartTimer')?.addEventListener('click', handlePlayClick);
        document.getElementById('btnPauseTimer')?.addEventListener('click', handlePause);
        document.getElementById('btnStopTimer')?.addEventListener('click', handleStop);
        document.getElementById('btnDiscardTimer')?.addEventListener('click', handleDiscard);
        document.getElementById('btnEditTimer')?.addEventListener('click', () => openEditForm());
        document.getElementById('btnCompleteData')?.addEventListener('click', () => openEditForm({ forceComplete: true }));

        restoreTimer();

        // Listado
        initListDefaults();
        setupListBindings();
        loadList();
    });

})();
