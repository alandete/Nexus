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
        // Consistente con las horas de los registros (sin segundos).
        // Redondeamos al minuto mas cercano; sesiones muy cortas se marcan como "< 1m".
        if (seconds < 60) return '< 1m';
        const totalMin = Math.round(seconds / 60);
        const h = Math.floor(totalMin / 60);
        const m = totalMin % 60;
        if (h > 0 && m > 0) return `${h}h ${m}m`;
        if (h > 0) return `${h}h`;
        return `${m}m`;
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    // Convierte 'YYYY-MM-DD' (o 'YYYY-MM-DD HH:MM:SS') a 'DD/MM/YYYY'
    function formatDateDMY(value) {
        if (!value) return '';
        const s = String(value);
        if (s.length < 10) return s;
        const y = s.substr(0, 4);
        const m = s.substr(5, 2);
        const d = s.substr(8, 2);
        if (!/^\d{4}$/.test(y) || !/^\d{2}$/.test(m) || !/^\d{2}$/.test(d)) return s;
        return `${d}/${m}/${y}`;
    }

    // Devuelve el color institucional de una alianza dado su id, name o el color directo
    function resolveAllianceColor(task) {
        if (!task) return null;
        if (task.alliance_color) return task.alliance_color;
        if (task.alliance_id) {
            const found = alliances.find(a => a.id == task.alliance_id);
            if (found?.color) return found.color;
        }
        if (task.alliance_name) {
            const found = alliances.find(a => a.name === task.alliance_name);
            if (found?.color) return found.color;
        }
        return null;
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

            // Etiquetas: un solo chip con el conteo + tooltip custom del proyecto con los nombres.
            // Solo en el tracker — el listado de tareas sigue mostrando cada etiqueta.
            if (state.tagNames) {
                const tagList = state.tagNames.split(',').map(n => n.trim()).filter(Boolean);
                const count = tagList.length;
                const label = count === 1
                    ? t('tasks.tag_singular', 'etiqueta')
                    : t('tasks.tag_plural',   'etiquetas');
                chips.push(`<span class="tracker-meta-chip" data-tooltip="${escapeHtml(tagList.join(', '))}" data-tooltip-position="top"><i class="bi bi-tag" aria-hidden="true"></i>${count} ${escapeHtml(label)}</span>`);
            } else {
                chips.push(`<span class="tracker-meta-chip tracker-meta-empty"><i class="bi bi-tag" aria-hidden="true"></i>${escapeHtml(t('tasks.no_tags', 'Sin etiquetas'))}</span>`);
            }

            // Alianza
            if (state.allianceName) {
                const color = resolveAllianceColor({ alliance_id: state.allianceId, alliance_name: state.allianceName });
                const styleAttr = color ? ` style="--alliance-color: ${escapeHtml(color)};"` : '';
                const hasColorCls = color ? ' has-alliance-color' : '';
                chips.push(`<span class="tracker-meta-chip tracker-meta-alliance${hasColorCls}"${styleAttr}><i class="bi bi-building" aria-hidden="true"></i>${escapeHtml(state.allianceName)}</span>`);
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

            // NOTA: la fecha de vencimiento NO se muestra en el rastreador porque
            // la tarea activa se asume del dia en que se activa. Se sigue mostrando
            // en cards de "Proximas" y en la tabla de Activas/Ayer.

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

    // Poblar el state del tracker a partir de un objeto task (del endpoint 'get')
    function hydrateStateFromTask(task) {
        if (!task) return;
        if (task.id) state.taskId = parseInt(task.id, 10);
        if (task.title) state.title = task.title;
        state.description = task.description || '';
        state.allianceId = task.alliance_id ? parseInt(task.alliance_id, 10) : null;
        state.allianceName = task.alliance_name || null;
        state.tagIds = String(task.tag_ids || '').split(',').filter(Boolean).map(id => parseInt(id, 10));
        state.tagNames = task.tag_names || '';
        state.priority = task.priority || 'medium';
        state.dueDate = task.due_date || null;
    }

    // Fetch completo de la tarea del timer corriendo para asegurar state sincronizado
    // (timer_status y timer_start del backend no devuelven todos los campos)
    async function hydrateTimerTask(taskId) {
        if (!taskId) return;
        try {
            const result = await api('get', { task_id: taskId });
            if (result.success && result.task) {
                hydrateStateFromTask(result.task);
                renderActiveCard();
            }
        } catch (e) { /* ignore */ }
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

                // Hidratar el state con todos los campos (timer_start no devuelve priority/due_date/description)
                hydrateTimerTask(result.task_id);
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
        const isCreate = opts.create === true;

        // Tres modos:
        //  - create:  crear tarea programada desde cero (sin task existente)
        //  - task:    editar una tarea arbitraria (de la lista)
        //  - default: tarea del timer activo, valores del state
        let current;
        if (isCreate) {
            current = { id: null, title: '', description: '', allianceId: null, tagIds: [], priority: 'medium', dueDate: '' };
        } else if (opts.task) {
            current = {
                id:          opts.task.id,
                title:       opts.task.title || '',
                description: opts.task.description || '',
                allianceId:  opts.task.alliance_id ? parseInt(opts.task.alliance_id, 10) : null,
                tagIds:      String(opts.task.tag_ids || '').split(',').filter(Boolean).map(id => parseInt(id, 10)),
                priority:    opts.task.priority || 'medium',
                dueDate:     opts.task.due_date || '',
            };
        } else {
            current = {
                id:          state.taskId,
                title:       state.title || '',
                description: state.description || '',
                allianceId:  state.allianceId,
                tagIds:      state.tagIds,
                priority:    state.priority || 'medium',
                dueDate:     state.dueDate || '',
            };
        }

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

                <div class="form-grid-2">
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
                        <div class="tags-multiselect" id="formTagsWrapper">
                            <button type="button" class="form-control tags-multiselect-trigger" id="formTagsTrigger"
                                    aria-haspopup="listbox" aria-expanded="false">
                                <span class="tags-multiselect-label" id="formTagsLabel"></span>
                                <i class="bi bi-chevron-down tags-multiselect-chevron" aria-hidden="true"></i>
                            </button>
                            <div class="tags-multiselect-dropdown d-none" id="formTagsDropdown" role="listbox" aria-multiselectable="true">
                                <div class="tags-multiselect-options" id="formTagsOptions">
                                    ${allTags.map(tag => `
                                        <label class="tags-multiselect-option" role="option">
                                            <input type="checkbox" name="tag_ids[]" value="${tag.id}" ${current.tagIds.includes(tag.id) ? 'checked' : ''}>
                                            <span>${escapeHtml(tag.name)}</span>
                                        </label>
                                    `).join('') || `<p class="tags-multiselect-empty">${t('tasks.no_tags_yet', 'Aun no hay etiquetas.')}</p>`}
                                </div>
                                <div class="tags-multiselect-create">
                                    <input type="text" id="fNewTag" class="form-control form-control-sm"
                                           placeholder="${t('tasks.tag_new_placeholder', 'Nueva etiqueta...')}"
                                           maxlength="30">
                                    <button type="button" class="btn-icon" id="btnCreateTag"
                                            data-tooltip="${t('tasks.btn_add_tag', 'Agregar')}" data-tooltip-position="top"
                                            aria-label="${t('tasks.btn_add_tag', 'Agregar')}">
                                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p class="form-error" id="fTaskTagsError" aria-live="polite"></p>
                    </div>
                </div>

                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="fTaskPriority" class="form-label">${t('tasks.field_priority', 'Prioridad')}</label>
                        <select id="fTaskPriority" name="priority" class="form-control">
                            ${priorityOpts}
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fTaskDueDate" class="form-label">${t('tasks.field_vencimiento', 'Vencimiento')}</label>
                        <input type="date" id="fTaskDueDate" name="due_date" class="form-control"
                               value="${escapeHtml(current.dueDate)}">
                    </div>
                </div>

                ${current.id ? `
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">${escapeHtml(t('tasks.field_since', 'Atendida desde'))}</label>
                        <div class="form-static" id="fTaskSince">
                            <span class="text-subtle">${escapeHtml(t('common.loading', 'Cargando'))}...</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">${escapeHtml(t('tasks.field_total_time', 'Tiempo acumulado'))}</label>
                        <div class="form-static form-static-mono" id="fTaskTotal">
                            <span class="text-subtle">—</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">${escapeHtml(t('tasks.field_status', 'Estado'))}</label>
                        <div class="form-static" id="fTaskStatusBadge">
                            <span class="text-subtle">—</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">${escapeHtml(t('tasks.entries_title', 'Registros'))}</label>
                    <div id="fTaskEntries" class="task-entries-form">
                        <div class="task-entries-loading">
                            <span class="spinner spinner-sm" aria-hidden="true"></span> ${escapeHtml(t('common.loading', 'Cargando'))}...
                        </div>
                    </div>
                </div>
                ` : ''}

                <div class="alert alert-danger d-none" id="taskEditError" role="alert">
                    <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
                    <span class="alert-content" id="taskEditErrorText"></span>
                </div>
            </form>
        `;

        let panelTitle;
        if (isCreate) panelTitle = t('tasks.create_task_title', 'Nueva tarea');
        else if (forceComplete) panelTitle = t('tasks.complete_data_title', 'Completa la informacion');
        else panelTitle = t('tasks.edit_task_title', 'Editar tarea');

        SlidePanel.open(panelTitle, html);

        const submitLabel = isCreate
            ? t('tasks.btn_create', 'Crear tarea')
            : t('common.save', 'Guardar');

        SlidePanel.setFooter(`
            <button type="button" class="btn btn-subtle" id="taskEditCancel">
                ${t('common.cancel', 'Cancelar')}
            </button>
            <button type="button" class="btn btn-primary" id="taskEditSubmit">
                <i class="bi bi-check2" aria-hidden="true"></i>
                <span class="btn-text">${submitLabel}</span>
            </button>
        `);

        setupEditFormHandlers(opts);
    }

    function setupEditFormHandlers(opts = {}) {
        document.getElementById('taskEditCancel').addEventListener('click', () => SlidePanel.close());
        document.getElementById('taskEditSubmit').addEventListener('click', () => handleEditSubmit(opts));

        // Multiselect de etiquetas en el form
        setupFormTagsMultiselect();

        // Cargar entries y "Atendida desde" si la tarea existe
        const taskId = opts._current?.id;
        if (taskId) loadFormEntries(taskId);
    }

    function updateFormTagsLabel() {
        const wrapper = document.getElementById('formTagsWrapper');
        const label = document.getElementById('formTagsLabel');
        if (!wrapper || !label) return;
        const checked = Array.from(wrapper.querySelectorAll('input[type="checkbox"]:checked'));
        const names = checked.map(cb => {
            const tag = allTags.find(tg => tg.id == cb.value);
            return tag?.name || '';
        }).filter(Boolean);

        if (names.length === 0) {
            label.textContent = t('tasks.tags_select_placeholder', 'Seleccionar etiquetas...');
            label.classList.add('text-subtle');
        } else {
            label.textContent = names.join(', ');
            label.classList.remove('text-subtle');
        }
    }

    function setupFormTagsMultiselect() {
        const wrapper = document.getElementById('formTagsWrapper');
        const trigger = document.getElementById('formTagsTrigger');
        const dropdown = document.getElementById('formTagsDropdown');
        if (!wrapper || !trigger || !dropdown) return;

        updateFormTagsLabel();

        const close = () => { dropdown.classList.add('d-none'); trigger.setAttribute('aria-expanded', 'false'); };
        const open  = () => { dropdown.classList.remove('d-none'); trigger.setAttribute('aria-expanded', 'true'); };

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            if (dropdown.classList.contains('d-none')) open(); else close();
        });

        dropdown.addEventListener('change', (e) => {
            if (e.target.matches('input[type="checkbox"]')) updateFormTagsLabel();
        });

        // Click fuera cierra
        document.addEventListener('click', function handler(e) {
            if (!wrapper.contains(e.target)) {
                close();
                // Cleanup: si el wrapper desaparece (panel cerrado) remover listener
                if (!document.body.contains(wrapper)) document.removeEventListener('click', handler);
            }
        });

        // Crear nueva etiqueta dentro del dropdown
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
                        // Recordar seleccion actual
                        const selectedIds = Array.from(dropdown.querySelectorAll('input[type="checkbox"]:checked'))
                            .map(cb => parseInt(cb.value, 10));
                        selectedIds.push(result.tag.id);
                        // Re-render opciones
                        const optionsEl = document.getElementById('formTagsOptions');
                        optionsEl.innerHTML = allTags.map(tag => `
                            <label class="tags-multiselect-option" role="option">
                                <input type="checkbox" name="tag_ids[]" value="${tag.id}" ${selectedIds.includes(tag.id) ? 'checked' : ''}>
                                <span>${escapeHtml(tag.name)}</span>
                            </label>
                        `).join('');
                        inputNewTag.value = '';
                        updateFormTagsLabel();
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
                if (e.key === 'Enter') { e.preventDefault(); handleCreate(); }
            });
        }
    }

    async function loadFormEntries(taskId) {
        try {
            const [taskResult, entriesResult] = await Promise.all([
                api('get',          { task_id: taskId }),
                api('time_entries', { task_id: taskId }),
            ]);
            const task    = taskResult.success    ? taskResult.task        : null;
            const entries = entriesResult.success ? (entriesResult.entries || []) : [];
            renderFormEntries(taskId, entries);
            renderFormSummary(task, entries);
        } catch (err) {
            const el = document.getElementById('fTaskEntries');
            if (el) el.innerHTML = `<p class="text-subtle">${escapeHtml(t('common.err_network', 'Error de red.'))}</p>`;
        }
    }

    // Popular los 3 campos readonly: desde, tiempo acumulado y estado
    function renderFormSummary(task, entries) {
        const sinceEl  = document.getElementById('fTaskSince');
        const totalEl  = document.getElementById('fTaskTotal');
        const statusEl = document.getElementById('fTaskStatusBadge');

        // Desde
        if (sinceEl) {
            const sorted = [...entries].sort((a, b) => (a.start_time || '').localeCompare(b.start_time || ''));
            const firstDate = sorted.length ? (sorted[0].start_time || '').slice(0, 10) : null;
            sinceEl.innerHTML = firstDate
                ? escapeHtml(formatDateDMY(firstDate))
                : `<span class="text-subtle">${escapeHtml(t('tasks.no_sessions_yet', 'Sin sesiones aun'))}</span>`;
        }

        // Tiempo acumulado
        if (totalEl) {
            const totalSecs = entries.reduce((sum, e) => sum + (parseInt(e.duration_seconds, 10) || 0), 0);
            totalEl.innerHTML = totalSecs > 0
                ? formatDuration(totalSecs)
                : `<span class="text-subtle">—</span>`;
        }

        // Estado
        if (statusEl) {
            const badge = buildStatusBadge(task, entries);
            statusEl.innerHTML = badge || `<span class="text-subtle">${escapeHtml(t('tasks.no_due_date', 'Sin fecha'))}</span>`;
        }
    }

    function renderFormEntries(taskId, entries) {
        const el = document.getElementById('fTaskEntries');
        if (!el) return;

        if (entries.length === 0) {
            el.innerHTML = `<p class="text-subtle text-sm m-0">${escapeHtml(t('tasks.no_entries_yet', 'Aun no hay registros cerrados.'))}</p>`;
            return;
        }

        el.innerHTML = `
            <div class="task-entries-list">
                ${entries.map(e => renderFormEntryRow(e, taskId)).join('')}
            </div>
        `;
    }

    function renderFormEntryRow(e, taskId) {
        const date = (e.start_time || '').slice(0, 10);
        const startHour = (e.start_time || '').slice(11, 16);
        const endHour = e.end_time ? e.end_time.slice(11, 16) : '';
        const dur = e.duration_seconds ? formatDuration(parseInt(e.duration_seconds, 10)) : '—';
        return `
            <div class="task-entry-row" data-entry-id="${e.id}" data-task-id="${taskId}" data-date="${escapeHtml(date)}">
                <div class="task-entry-date text-mono text-sm text-subtle">${escapeHtml(formatDateDMY(date))}</div>
                <input type="time" class="form-control form-control-sm task-entry-input" data-field="start" value="${escapeHtml(startHour)}" step="60">
                <span class="task-entry-sep" aria-hidden="true">→</span>
                <input type="time" class="form-control form-control-sm task-entry-input" data-field="end" value="${escapeHtml(endHour)}" step="60">
                <div class="task-entry-duration text-mono" data-role="duration">${dur}</div>
                <div class="task-entry-actions">
                    <button type="button" class="btn-icon btn-icon-success" data-action="save-form-entry"
                            data-tooltip="${t('common.save', 'Guardar')}" data-tooltip-position="top" aria-label="${t('common.save', 'Guardar')}">
                        <i class="bi bi-check-lg" aria-hidden="true"></i>
                    </button>
                    <button type="button" class="btn-icon btn-icon-danger" data-action="delete-form-entry"
                            data-tooltip="${t('tasks.btn_delete', 'Eliminar')}" data-tooltip-position="top" aria-label="${t('tasks.btn_delete', 'Eliminar')}">
                        <i class="bi bi-trash" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        `;
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

        const isCreate = opts.create === true;

        // ID de la tarea que estamos editando (arbitraria o la del timer)
        const editingTaskId = opts._current?.id || state.taskId;
        // Si el timer esta corriendo y coincide con la tarea editada, hay que sincronizar
        // el state del tracker aunque la edicion haya venido desde la lista (opts.task).
        const isTimerTask = !isCreate && state.running && state.taskId == editingTaskId;

        submitBtn.disabled = true;
        const btnText = submitBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('common.saving', 'Guardando...');

        try {
            const payload = {
                title, description,
                alliance_id: allianceId,
                priority, due_date: dueDate,
                tag_ids: tagIds.join(','),
            };
            let result;
            if (isCreate) {
                payload.status = 'pending';
                result = await api('create', payload);
            } else {
                payload.task_id = editingTaskId;
                result = await api('update', payload);
            }

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
                Toast.success(isCreate
                    ? t('tasks.task_created', 'Tarea creada.')
                    : t('tasks.task_updated', 'Cambios guardados.'));
                if (typeof loadList === 'function') loadList();

                // Si venia de forceComplete, ejecutar la accion pendiente
                if (opts.onComplete) opts.onComplete();
            } else {
                const err = document.getElementById('taskEditError');
                document.getElementById('taskEditErrorText').textContent = result.message
                    || t(isCreate ? 'tasks.err_create' : 'tasks.err_update', 'No se pudo guardar.');
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
            if (!result.success || !result.running || !result.entry) return;

            const entry = result.entry;
            state.running = true;
            state.taskId = entry.task_id;
            state.title = entry.title;
            state.startTime = new Date(entry.start_time.replace(' ', 'T'));

            renderActiveCard();
            startTicking();

            // timer_status no devuelve alliance_id/tag_ids/priority/due_date/description,
            // hidratar con 'get' para tener el state completo.
            hydrateTimerTask(entry.task_id);
        } catch (err) { /* ignore */ }
    }

    /** ========================================================
     * LISTADO (sub-fase 4.2)
     * ======================================================== */

    const listState = {
        data: { active: [], scheduled: [], by_date: {}, day_totals: {} },
        filters: { search: '', alliance: '', priority: '', tags: [], dateFrom: '', dateTo: '' },
        historyPage: 1,
        historyPageSize: 7,
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
        const { search, priority, tags } = listState.filters;
        return items.filter(task => {
            if (search) {
                const hay = (task.title || '').toLowerCase() + ' ' + (task.alliance_name || '').toLowerCase();
                if (!hay.includes(search.toLowerCase())) return false;
            }
            if (priority && (task.priority || 'medium') !== priority) return false;
            if (tags && tags.length > 0) {
                // Semantica OR: la tarea pasa si tiene al menos una de las etiquetas seleccionadas
                const taskTagIds = String(task.tag_ids || '').split(',').filter(Boolean);
                const matches = tags.some(tagId => taskTagIds.includes(String(tagId)));
                if (!matches) return false;
            }
            return true;
        });
    }

    function renderAllPanels() {
        renderScheduledPanel();
        renderActivePanel();
        renderTodayPanel();
        renderYesterdayPanel();
        renderHistoryPanel();
        updateSectionCounts();
    }

    // Fechas en zona local (toISOString devuelve UTC y desfasa en timezones negativas)
    function localDateStr(d) {
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${yyyy}-${mm}-${dd}`;
    }

    function todayStr() {
        return localDateStr(new Date());
    }

    function yesterdayStr() {
        const d = new Date();
        d.setDate(d.getDate() - 1);
        return localDateStr(d);
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
                    alliance_id: e.alliance_id,
                    alliance_color: e.alliance_color,
                    tag_names: e.tag_names,
                    tag_ids: e.tag_ids,
                    status: e.task_status,
                    total_seconds: 0,
                    entry_count: 0,
                    priority: 'medium',
                    entries: [],
                };
            }
            grouped[id].total_seconds += parseInt(e.duration_seconds, 10) || 0;
            grouped[id].entry_count += 1;
            grouped[id].entries.push({
                start_time: e.start_time,
                end_time:   e.end_time,
                duration_seconds: e.duration_seconds,
            });
        });
        // Ordenar entries por hora asc
        Object.values(grouped).forEach(g => {
            g.entries.sort((a, b) => (a.start_time || '').localeCompare(b.start_time || ''));
        });
        return Object.values(grouped);
    }

    function updateSectionCounts() {
        const today = todayStr();
        const yday  = yesterdayStr();
        const active = applyLocalFilters(listState.data.active).length;
        const scheduled = applyLocalFilters(listState.data.scheduled).length;
        const todayTasks = applyLocalFilters(groupEntriesByTask(
            (listState.data.by_date[today] || []).filter(e => !isActiveEntry(e))
        )).length;
        const yesterdayTasks = applyLocalFilters(groupEntriesByTask(listState.data.by_date[yday] || [])).length;
        const history = Object.entries(listState.data.by_date)
            .filter(([date]) => date !== today && date !== yday)
            .reduce((acc, [, arr]) => acc + applyLocalFilters(arr.map(e => ({
                title: e.task_title,
                alliance_name: e.alliance_name,
                priority: 'medium',
                tag_ids: '',
            }))).length, 0);

        const setCount = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };
        setCount('countActive', active);
        setCount('countScheduled', scheduled);
        setCount('countToday', todayTasks);
        setCount('countYesterday', yesterdayTasks);
        setCount('countHistory', history);
    }

    // En "Hoy" no queremos tareas que siguen corriendo (esas van en Activas).
    function isActiveEntry(entry) {
        return listState.data.active.some(t => t.id == entry.task_id);
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

    /** ========================================================
     * renderTaskTable — Helper unificado para listados tipo tabla
     * (Activas, Ayer y futuros listados de tareas con el mismo schema)
     *
     * config:
     *   - container: HTMLElement destino
     *   - columns:   [{ key, label, width, align?, render(item) }]  // width = valor CSS grid-template
     *   - items:     array de tareas normalizadas (con task_id)
     *   - actions:   [{ key, icon, label, variant?, visible?(item) }]  // data-action = key
     *   - showHeaders: bool (default false, oculta labels de columna pero mantiene los anchos)
     *   - emptyState: { icon, title, desc }
     *   - expandable: bool (default false) — agrega chevron al final de actions y una
     *                 fila detalle oculta (hermana de la row) que se lazy-carga al abrir
     * ======================================================== */
    function renderTaskTable(config) {
        const { container, columns, items, actions = [], showHeaders = false, emptyState: empty, expandable = false } = config;
        if (!container) return;

        // grid-template-columns desde config + columna acciones al final (ancho fijo
        // para que el ancho sea identico entre filas aunque no todas tengan los mismos botones)
        const actionsWidth = expandable ? '140px' : '110px';
        const gridCols = columns.map(c => c.width).join(' ') + ' ' + actionsWidth;
        container.style.setProperty('--grid-columns', gridCols);

        if (!items.length) {
            container.innerHTML = emptyState(empty.icon, empty.title, empty.desc);
            return;
        }

        const headerHtml = showHeaders ? `
            <div class="grid-table-head" role="row">
                ${columns.map(c => `<span role="columnheader" class="${c.align === 'right' ? 'text-right' : ''}">${escapeHtml(c.label)}</span>`).join('')}
                <span role="columnheader" class="sr-only">${escapeHtml(t('common.actions', 'Acciones'))}</span>
            </div>
        ` : '';

        const actionsWithToggle = expandable
            ? [...actions, { key: 'toggle-detail', icon: 'bi-chevron-down', label: t('tasks.view_detail', 'Ver detalle') }]
            : actions;

        const renderActions = (item) => actionsWithToggle
            .filter(a => typeof a.visible !== 'function' || a.visible(item))
            .map(a => {
                const variantCls = a.variant ? ` btn-icon-${a.variant}` : '';
                const extraCls = a.key === 'toggle-detail' ? ' btn-icon-expand' : '';
                const dataTitle = item.title ? ` data-title="${escapeHtml(item.title)}"` : '';
                return `
                    <button type="button" class="btn-icon${variantCls}${extraCls}"
                            data-action="${a.key}" data-task-id="${item.task_id}"${dataTitle}
                            data-tooltip="${escapeHtml(a.label)}" data-tooltip-position="top"
                            aria-label="${escapeHtml(a.label)}">
                        <i class="bi ${a.icon}" aria-hidden="true"></i>
                    </button>
                `;
            }).join('');

        const rowsHtml = items.map(item => {
            const row = `
                <div class="grid-table-row" role="row" data-task-id="${item.task_id}">
                    ${columns.map(c => `
                        <span class="grid-cell cell-${c.key}${c.align === 'right' ? ' text-right' : ''}" role="gridcell">
                            ${c.render(item)}
                        </span>
                    `).join('')}
                    <span class="grid-cell cell-actions" role="gridcell">${renderActions(item)}</span>
                </div>
            `;
            const detail = expandable
                ? `<div class="grid-table-detail d-none" data-detail-for="${item.task_id}"></div>`
                : '';
            return row + detail;
        }).join('');

        container.innerHTML = headerHtml + rowsHtml;
    }

    /* ---- Cell renderers reutilizables ---- */
    function cellAlliance(item) {
        if (!item.alliance_name) {
            return `<span class="text-subtle">${escapeHtml(t('tasks.no_alliance', 'Sin alianza'))}</span>`;
        }
        const color = resolveAllianceColor(item);
        const styleAttr = color ? ` style="--alliance-color: ${escapeHtml(color)};"` : '';
        const hasColorCls = color ? ' has-alliance-color' : '';
        return `<span class="cell-alliance-chip${hasColorCls}"${styleAttr}><i class="bi bi-building" aria-hidden="true"></i> ${escapeHtml(item.alliance_name)}</span>`;
    }
    function cellTask(item) {
        const count = item.entry_count
            ? `<span class="task-entry-count" title="${t('tasks.entry_count_hint', 'Registros')}">${item.entry_count}</span>`
            : '';
        return `
            <div class="cell-task-body">
                <span class="cell-task-title">${escapeHtml(item.title)}</span>
                ${count}
            </div>
        `;
    }

    // Renderer de columna "Hora" (hora de inicio de la primera sesion del dia)
    function cellStartTime(item) {
        const first = (item.entries && item.entries[0] && item.entries[0].start_time) || item.first_time;
        if (!first) return `<span class="text-subtle">—</span>`;
        return `<span class="text-mono">${escapeHtml(first.slice(11, 16))}</span>`;
    }
    function cellStatus(item) {
        const isCurrent = state.running && state.taskId == item.task_id;
        if (isCurrent) return `<span class="lozenge lozenge-success"><i class="bi bi-record-fill" aria-hidden="true"></i> ${t('tasks.is_running', 'Corriendo')}</span>`;
        return statusLozenge(item.status);
    }
    function cellTags(item) {
        if (!item.tag_names) return `<span class="text-subtle text-sm">—</span>`;
        const tagList = item.tag_names.split(',').map(n => n.trim()).filter(Boolean);
        const count = tagList.length;
        if (!count) return `<span class="text-subtle text-sm">—</span>`;
        const label = count === 1
            ? t('tasks.tag_singular', 'etiqueta')
            : t('tasks.tag_plural',   'etiquetas');
        return `<span class="tracker-meta-chip" data-tooltip="${escapeHtml(tagList.join(', '))}" data-tooltip-position="top"><i class="bi bi-tag" aria-hidden="true"></i>${count} ${escapeHtml(label)}</span>`;
    }
    function cellTime(item) {
        return `<span class="text-mono">${item.total_seconds ? formatDuration(parseInt(item.total_seconds, 10)) : '—'}</span>`;
    }

    /* ---- Schema compartido para listados de tareas (Activas, Ayer, Hoy) ---- */
    function taskTableColumns() {
        return [
            { key: 'alliance', label: t('tasks.col_alliance',   'Alianza'),   width: 'minmax(90px, 0.7fr)',  render: cellAlliance },
            { key: 'task',     label: t('tasks.col_task',       'Tarea'),     width: 'minmax(260px, 3fr)',   render: cellTask },
            { key: 'status',   label: t('tasks.col_status',     'Estado'),    width: '110px',                render: cellStatus },
            { key: 'tags',     label: t('tasks.col_tags',       'Etiquetas'), width: '120px',                render: cellTags },
            { key: 'time',     label: t('tasks.col_total_time', 'Tiempo'),    width: '70px', align: 'right', render: cellTime },
        ];
    }

    // Columnas para Historial: mismo schema + columna "Hora de inicio" antes de Tiempo
    function historyTableColumns() {
        const base = taskTableColumns();
        const timeCol = {
            key: 'start_time',
            label: t('tasks.col_start_time', 'Hora'),
            width: '55px',
            align: 'right',
            render: cellStartTime,
        };
        // Insertar antes de la columna 'time' (tiempo acumulado)
        const idx = base.findIndex(c => c.key === 'time');
        const out = [...base];
        if (idx >= 0) out.splice(idx, 0, timeCol);
        else out.push(timeCol);
        return out;
    }

    function taskTableActions() {
        return [
            {
                key: 'resume',
                icon: 'bi-play-fill',
                label: t('tasks.btn_resume', 'Reanudar'),
                visible: (item) => !(state.running && state.taskId == item.task_id),
            },
            { key: 'edit',   icon: 'bi-pencil', label: t('tasks.btn_edit',   'Editar') },
            { key: 'delete', icon: 'bi-trash',  label: t('tasks.btn_delete', 'Eliminar'), variant: 'danger' },
        ];
    }

    function renderActivePanel() {
        const section   = document.getElementById('sectionActive');
        const container = document.getElementById('contentActive');
        if (!container || !section) return;

        // Normalizar: active tasks vienen con `id` (no task_id)
        const items = applyLocalFilters(listState.data.active).map(tk => {
            const entryCount = (listState.data.by_date && Object.values(listState.data.by_date)
                .flat().filter(e => e.task_id == tk.id).length) || 0;
            return { ...tk, task_id: tk.id, entry_count: entryCount || null };
        })
        // Filtrar tareas "fantasma": status in_progress/paused pero sin sesiones registradas.
        // Sucede cuando el usuario descarta un timer recien iniciado (timer_discard borra
        // el entry pero no resetea el status). Bug backend documentado en CHANGELOG.
        .filter(tk => parseInt(tk.total_seconds, 10) > 0 || tk.entry_count > 0);

        // Ocultar la seccion completa si no hay tareas activas
        if (items.length === 0) {
            section.classList.add('d-none');
            container.innerHTML = '';
            return;
        }

        section.classList.remove('d-none');

        renderTaskTable({
            container,
            columns: taskTableColumns(),
            items,
            actions: taskTableActions(),
            expandable: true,
            emptyState: {
                icon:  'bi-play-circle',
                title: t('tasks.empty_active_title', 'No hay tareas activas'),
                desc:  t('tasks.empty_active_desc',  'Aqui apareceran las tareas en progreso o pausadas. Inicia un cronometro para comenzar.'),
            },
        });
    }

    function renderTodayPanel() {
        const section = document.getElementById('sectionToday');
        const container = document.getElementById('contentToday');
        if (!container || !section) return;

        // Excluir entries de tareas que aun estan en "Activas" (no duplicar)
        const entries = (listState.data.by_date[todayStr()] || []).filter(e => !isActiveEntry(e));
        const items = applyLocalFilters(groupEntriesByTask(entries));

        if (items.length === 0) {
            section.classList.add('d-none');
            container.innerHTML = '';
            return;
        }

        section.classList.remove('d-none');
        // Alcance de dia: los detalles colapsables filtran entries a este dia
        container.dataset.dayScope = todayStr();

        renderTaskTable({
            container,
            columns: taskTableColumns(),
            items,
            actions: taskTableActions(),
            expandable: true,
            emptyState: {
                icon:  'bi-sun',
                title: t('tasks.empty_today_title', 'Sin actividad hoy aun'),
                desc:  t('tasks.empty_today_desc',  'Las tareas que completes o pauses hoy apareceran aqui.'),
            },
        });
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
        container.dataset.dayScope = yesterdayStr();

        renderTaskTable({
            container,
            columns: taskTableColumns(),
            items,
            actions: taskTableActions(),
            emptyState: {
                icon:  'bi-calendar-minus',
                title: t('tasks.empty_yesterday_title', 'No hubo actividad ayer'),
                desc:  t('tasks.empty_yesterday_desc',  'Aqui apareceran las tareas en las que trabajaste ayer, con total de tiempo y numero de sesiones.'),
            },
        });
    }

    function updateScrollMask(container) {
        if (!container) return;
        const canScroll = container.scrollWidth > container.clientWidth + 1;
        const atStart = !canScroll || container.scrollLeft <= 1;
        const atEnd   = !canScroll || container.scrollLeft + container.clientWidth >= container.scrollWidth - 1;
        container.classList.toggle('at-start', atStart);
        container.classList.toggle('at-end', atEnd);
    }

    function setupScrollMask(container) {
        if (!container || container._scrollMaskBound) return;
        container._scrollMaskBound = true;
        container.addEventListener('scroll', () => updateScrollMask(container), { passive: true });
        // Observar cambios de tamano por si el viewport o el contenido cambian
        if (typeof ResizeObserver !== 'undefined') {
            const ro = new ResizeObserver(() => updateScrollMask(container));
            ro.observe(container);
        } else {
            window.addEventListener('resize', () => updateScrollMask(container));
        }
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
            container.classList.remove('at-start', 'at-end');
            return;
        }

        setupScrollMask(container);

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
                    ${escapeHtml(formatDateDMY(task.due_date))}
                </span>` : `<span class="task-card-due task-card-due-empty">—</span>`;

            const overdueTag = overdue
                ? `<span class="task-card-overdue-tag">${escapeHtml(t('tasks.is_overdue', 'Vencida'))}</span>`
                : '';

            const allianceColor = resolveAllianceColor(task);
            const allianceStyle = allianceColor ? ` style="--alliance-color: ${escapeHtml(allianceColor)};"` : '';
            const allianceColorCls = allianceColor ? ' has-alliance-color' : '';
            const allianceBadge = task.alliance_name
                ? `<span class="task-card-alliance${allianceColorCls}"${allianceStyle} title="${escapeHtml(task.alliance_name)}">${escapeHtml(task.alliance_name)}</span>`
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

        // Actualizar mascara de fade despues de inyectar el DOM
        requestAnimationFrame(() => updateScrollMask(container));
    }

    // Formatea "Viernes, 17 de abril de 2026" (formato largo, capitalizado)
    function formatDayLabel(isoDate) {
        const d = new Date(isoDate + 'T00:00:00');
        const label = d.toLocaleDateString(undefined, {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
        return label.charAt(0).toUpperCase() + label.slice(1);
    }

    function renderHistoryPanel() {
        const container = document.getElementById('contentHistory');
        if (!container) return;
        const byDate = listState.data.by_date || {};
        const today = todayStr();
        const yday  = yesterdayStr();

        // Agrupar por task dentro de cada dia, ordenar por hora de inicio DESC y filtrar
        const datesWithTasks = Object.keys(byDate)
            .filter(d => d !== today && d !== yday)
            .sort().reverse()
            .map(date => {
                const tasks = applyLocalFilters(groupEntriesByTask(byDate[date]));
                // Tareas del dia mas recientes primero (hora de inicio desc)
                tasks.sort((a, b) => {
                    const ta = (a.entries && a.entries[0] && a.entries[0].start_time) || '';
                    const tb = (b.entries && b.entries[0] && b.entries[0].start_time) || '';
                    return tb.localeCompare(ta);
                });
                return { date, tasks };
            })
            .filter(d => d.tasks.length > 0);

        if (datesWithTasks.length === 0) {
            container.innerHTML = emptyState('bi-clock-history',
                t('tasks.empty_history_title', 'Sin historial en este rango'),
                t('tasks.empty_history_desc', 'Ajusta las fechas o empieza a registrar tiempo para ver el historial aqui.'));
            return;
        }

        // Paginar por dia
        const pageSize = listState.historyPageSize;
        const totalPages = Math.max(1, Math.ceil(datesWithTasks.length / pageSize));
        if (listState.historyPage > totalPages) listState.historyPage = totalPages;
        if (listState.historyPage < 1) listState.historyPage = 1;
        const startIdx = (listState.historyPage - 1) * pageSize;
        const visible = datesWithTasks.slice(startIdx, startIdx + pageSize);

        // Render grupos
        const groupsHtml = visible.map(({ date, tasks }) => {
            const dayTotal = tasks.reduce((a, tk) => a + (parseInt(tk.total_seconds, 10) || 0), 0);
            return `
                <div class="history-day" data-day="${escapeHtml(date)}">
                    <div class="history-day-header">
                        <span class="history-day-date">${escapeHtml(formatDayLabel(date))}</span>
                        <span class="history-day-total"><i class="bi bi-clock" aria-hidden="true"></i> ${formatDuration(dayTotal)}</span>
                    </div>
                    <div class="tasks-grid-table history-day-table" data-day-container="${escapeHtml(date)}"></div>
                </div>
            `;
        }).join('');

        const paginationHtml = totalPages > 1
            ? `<div class="history-pagination" id="historyPagination"></div>`
            : '';

        container.innerHTML = groupsHtml + paginationHtml;

        // Renderizar cada dia usando el helper (tabla con acciones)
        visible.forEach(({ date, tasks }) => {
            const dayContainer = container.querySelector(`[data-day-container="${date}"]`);
            if (!dayContainer) return;
            renderTaskTable({
                container: dayContainer,
                columns: historyTableColumns(),
                items: tasks,
                actions: taskTableActions(),
                expandable: true,
                emptyState: {
                    icon:  'bi-clock-history',
                    title: '',
                    desc:  '',
                },
            });
        });

        // Paginacion
        renderHistoryPagination(totalPages);
    }

    function renderHistoryPagination(totalPages) {
        const pag = document.getElementById('historyPagination');
        if (!pag) return;
        const current = listState.historyPage;

        const pageNums = [];
        if (totalPages <= 7) {
            for (let i = 1; i <= totalPages; i++) pageNums.push(i);
        } else {
            pageNums.push(1);
            if (current > 3) pageNums.push('...');
            const startN = Math.max(2, current - 1);
            const endN   = Math.min(totalPages - 1, current + 1);
            for (let i = startN; i <= endN; i++) pageNums.push(i);
            if (current < totalPages - 2) pageNums.push('...');
            pageNums.push(totalPages);
        }

        const numBtns = pageNums.map(n => {
            if (n === '...') return `<span class="pagination-ellipsis" aria-hidden="true">…</span>`;
            const active = n === current ? 'pagination-btn-active' : '';
            return `<button type="button" class="pagination-btn ${active}" data-history-page="${n}" ${n === current ? 'aria-current="page"' : ''}>${n}</button>`;
        }).join('');

        const prevDisabled = current <= 1 ? 'disabled' : '';
        const nextDisabled = current >= totalPages ? 'disabled' : '';

        pag.innerHTML = `
            <div class="pagination-info text-sm text-subtle">
                ${t('tasks.history_page_info', 'Pagina {page} de {pages}')
                    .replace('{page}', current).replace('{pages}', totalPages)}
            </div>
            <div class="pagination-controls">
                <button type="button" class="pagination-btn" data-history-page="${current - 1}" ${prevDisabled}
                        aria-label="${t('tasks.pagination_prev', 'Anterior')}">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>
                ${numBtns}
                <button type="button" class="pagination-btn" data-history-page="${current + 1}" ${nextDisabled}
                        aria-label="${t('tasks.pagination_next', 'Siguiente')}">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
        `;

        pag.querySelectorAll('[data-history-page]').forEach(btn => {
            btn.addEventListener('click', () => {
                const page = parseInt(btn.dataset.historyPage, 10);
                if (!isNaN(page) && page >= 1 && page <= totalPages) {
                    listState.historyPage = page;
                    renderHistoryPanel();
                    // Scroll al inicio de la seccion historial
                    document.getElementById('sectionHistory')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    }

    function setupListBindings() {
        // Filtros
        const searchInput = document.getElementById('filterSearch');
        let searchDebounce = null;
        searchInput?.addEventListener('input', () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(() => {
                listState.filters.search = searchInput.value.trim();
                listState.historyPage = 1;
                renderAllPanels();
            }, 200);
        });

        document.getElementById('filterDateFrom')?.addEventListener('change', (e) => {
            listState.filters.dateFrom = e.target.value;
            listState.historyPage = 1;
            loadList();
        });
        document.getElementById('filterDateTo')?.addEventListener('change', (e) => {
            listState.filters.dateTo = e.target.value;
            listState.historyPage = 1;
            loadList();
        });
        document.getElementById('filterAlliance')?.addEventListener('change', (e) => {
            listState.filters.alliance = e.target.value;
            listState.historyPage = 1;
            loadList();
        });
        document.getElementById('filterPriority')?.addEventListener('change', (e) => {
            listState.filters.priority = e.target.value;
            listState.historyPage = 1;
            renderAllPanels();
        });
        setupTagsMultiselect();
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
            } else if (action === 'toggle-detail') {
                toggleTaskDetail(btn, taskId);
            }
        });

        // Delegation de acciones dentro del slide panel (entries)
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action]');
            if (!btn) return;
            const action = btn.dataset.action;
            if (action === 'save-form-entry') saveFormEntry(btn);
            else if (action === 'delete-form-entry') deleteFormEntry(btn);
        });

        // Recalcular duracion en vivo al cambiar inputs de tiempo en el form
        document.addEventListener('input', (e) => {
            if (!e.target.matches('.task-entry-input')) return;
            const row = e.target.closest('.task-entry-row');
            if (!row) return;
            const s = row.querySelector('[data-field="start"]').value;
            const f = row.querySelector('[data-field="end"]').value;
            const durEl = row.querySelector('[data-role="duration"]');
            if (!s || !f) { durEl.textContent = '—'; return; }
            const secs = diffSeconds(s, f);
            durEl.textContent = secs > 0 ? formatDuration(secs) : '—';
        });
    }

    function diffSeconds(hhmmStart, hhmmEnd) {
        const [sh, sm] = hhmmStart.split(':').map(Number);
        const [eh, em] = hhmmEnd.split(':').map(Number);
        return (eh * 3600 + em * 60) - (sh * 3600 + sm * 60);
    }

    function hmToMinutes(hm) {
        const [h, m] = hm.split(':').map(Number);
        return h * 60 + m;
    }

    // Busca un entry del dia que se solape con el rango dado (excluyendo el propio).
    // Usa listState.data.by_date que ya trae los entries del user_id desde el backend.
    function findOverlappingEntry(datePart, startHour, endHour, excludeEntryId) {
        const dayEntries = listState.data.by_date[datePart] || [];
        const s = hmToMinutes(startHour);
        const e = hmToMinutes(endHour);
        for (const ent of dayEntries) {
            if (String(ent.id) === String(excludeEntryId)) continue;
            const eStart = (ent.start_time || '').slice(11, 16);
            const eEnd   = ent.end_time ? ent.end_time.slice(11, 16) : null;
            if (!eStart || !eEnd) continue;
            const es = hmToMinutes(eStart);
            const ee = hmToMinutes(eEnd);
            if (s < ee && es < e) return ent;
        }
        return null;
    }

    async function saveFormEntry(btn) {
        const row = btn.closest('.task-entry-row');
        if (!row) return;
        const entryId = row.dataset.entryId;
        const taskId  = parseInt(row.dataset.taskId, 10);
        const date    = row.dataset.date;
        const startHour = row.querySelector('[data-field="start"]').value;
        const endHour   = row.querySelector('[data-field="end"]').value;

        if (!startHour || !endHour) {
            Toast.error(t('tasks.entry_err_required', 'Completa hora de inicio y fin.'));
            return;
        }
        if (diffSeconds(startHour, endHour) <= 0) {
            Toast.error(t('tasks.entry_err_order', 'La hora de fin debe ser posterior al inicio.'));
            return;
        }

        // Validar solapamiento con otros entries del mismo dia del usuario
        const overlap = findOverlappingEntry(date, startHour, endHour, entryId);
        if (overlap) {
            const os = (overlap.start_time || '').slice(11, 16);
            const oe = (overlap.end_time   || '').slice(11, 16);
            Toast.error(t('tasks.entry_err_overlap', 'Se solapa con otro registro del dia ({task}, {start}-{end})')
                .replace('{task}',  overlap.task_title || '')
                .replace('{start}', os)
                .replace('{end}',   oe));
            return;
        }

        btn.disabled = true;
        try {
            const result = await api('time_entry_update', {
                entry_id: entryId,
                start_time: `${date} ${startHour}:00`,
                end_time:   `${date} ${endHour}:00`,
            });
            if (result.success) {
                Toast.success(t('tasks.entry_updated', 'Registro actualizado.'));
                // Recargar entries y recalcular total de la lista principal
                loadFormEntries(taskId);
                if (typeof loadList === 'function') loadList();
            } else {
                Toast.error(result.message || t('tasks.entry_err_update', 'No se pudo actualizar el registro.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            btn.disabled = false;
        }
    }

    async function deleteFormEntry(btn) {
        const row = btn.closest('.task-entry-row');
        if (!row) return;
        const entryId = row.dataset.entryId;
        const taskId  = parseInt(row.dataset.taskId, 10);

        const confirmed = await ConfirmModal.show({
            title:   t('tasks.entry_delete_title', 'Eliminar registro'),
            message: t('tasks.entry_delete_message', 'Este registro de tiempo se eliminara y el total de la tarea se recalculara. Esta accion no se puede deshacer.'),
            acceptText: t('tasks.btn_delete', 'Eliminar'),
            acceptVariant: 'danger',
            icon: 'bi-trash',
            variant: 'danger',
        });
        if (!confirmed) return;

        try {
            const result = await api('time_entry_delete', { entry_id: entryId });
            if (result.success) {
                Toast.success(t('tasks.entry_deleted', 'Registro eliminado.'));
                loadFormEntries(taskId);
                if (typeof loadList === 'function') loadList();
            } else {
                Toast.error(result.message || t('tasks.entry_err_delete', 'No se pudo eliminar el registro.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        }
    }

    // Helper para calcular dias entre dos fechas YYYY-MM-DD
    function daysBetweenDates(a, b) {
        const da = new Date(a + 'T00:00:00');
        const db = new Date(b + 'T00:00:00');
        return Math.abs(Math.round((db - da) / 86400000));
    }

    // Calcula el chip de estado "a tiempo / con retraso" a partir de la tarea y sus entries.
    // Devuelve '' si la tarea no tiene due_date.
    function buildStatusBadge(task, entries) {
        if (!task || !task.due_date) return '';
        const today = todayStr();
        const isCompleted = task.status === 'completed';
        const lastEntry = entries.slice().sort((a, b) => (b.end_time || '').localeCompare(a.end_time || ''))[0];
        const refDate = isCompleted && lastEntry ? (lastEntry.end_time || '').slice(0, 10) : today;

        if (refDate <= task.due_date) {
            const label = isCompleted
                ? t('tasks.status_delivered_ontime', 'Entregada a tiempo')
                : t('tasks.status_on_schedule',     'En plazo');
            return `<span class="task-detail-status-chip is-ontime"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> ${escapeHtml(label)}</span>`;
        }
        const days = daysBetweenDates(task.due_date, refDate);
        const label = isCompleted
            ? t('tasks.status_delivered_late', '{n} dias de retraso').replace('{n}', days)
            : t('tasks.status_overdue_days',  'Vencida hace {n} dias').replace('{n}', days);
        return `<span class="task-detail-status-chip is-late"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> ${escapeHtml(label)}</span>`;
    }

    async function toggleTaskDetail(btn, taskId) {
        const row = btn.closest('.grid-table-row');
        if (!row) return;
        const detail = row.nextElementSibling;
        if (!detail || !detail.classList.contains('grid-table-detail')) return;

        const isOpen = !detail.classList.contains('d-none');
        if (isOpen) {
            detail.classList.add('d-none');
            row.classList.remove('is-expanded');
            return;
        }

        row.classList.add('is-expanded');
        detail.classList.remove('d-none');

        if (detail.dataset.loaded === '1') return;

        // Alcance de dia: si la fila vive dentro de Hoy/Ayer/Historial, los registros
        // se filtran a esa fecha para no mezclar sesiones de dias distintos.
        // En Activas no hay day-scope y se muestra el historico completo.
        const scoped = row.closest('[data-day-scope], [data-day-container]');
        const dayScope = scoped?.dataset.dayScope || scoped?.dataset.dayContainer || null;

        detail.innerHTML = `<div class="task-detail-loading"><span class="spinner spinner-sm" aria-hidden="true"></span> ${escapeHtml(t('common.loading', 'Cargando...'))}</div>`;
        try {
            const [taskResult, entriesResult] = await Promise.all([
                api('get', { task_id: taskId }),
                api('time_entries', { task_id: taskId }),
            ]);
            const task = taskResult.success ? taskResult.task : null;
            let entries = entriesResult.success ? (entriesResult.entries || []) : [];
            if (dayScope) {
                entries = entries.filter(e => (e.start_time || '').slice(0, 10) === dayScope);
            }
            detail.innerHTML = renderTaskDetail(task, entries);
            detail.dataset.loaded = '1';
        } catch (err) {
            detail.innerHTML = `<div class="task-detail-error">${escapeHtml(t('common.err_network', 'Error de red.'))}</div>`;
        }
    }

    function renderTaskDetail(task, entries) {
        if (!task) return `<div class="task-detail-error">${escapeHtml(t('tasks.err_update', 'No se pudo cargar la tarea.'))}</div>`;

        const priorityLabels = {
            low:    t('tasks.priority_low', 'Baja'),
            medium: t('tasks.priority_medium', 'Media'),
            high:   t('tasks.priority_high', 'Alta'),
            urgent: t('tasks.priority_urgent', 'Urgente'),
        };
        const prio = task.priority || 'medium';
        const priorityHtml = `<span class="task-detail-priority task-detail-priority-${prio}">${escapeHtml(priorityLabels[prio] || prio)}</span>`;
        const descHtml = task.description
            ? escapeHtml(task.description).replace(/\n/g, '<br>')
            : `<span class="text-subtle">${escapeHtml(t('tasks.no_description', 'Sin descripcion'))}</span>`;

        // Vencimiento (sin icono, coherente con el resto de labels)
        const dueHtml = task.due_date
            ? escapeHtml(formatDateDMY(task.due_date))
            : `<span class="text-subtle">${escapeHtml(t('tasks.no_due_date', 'Sin fecha'))}</span>`;

        // "Desde": fecha del primer entry (el mas antiguo). Entries vienen DESC desde el backend.
        const sorted = [...entries].sort((a, b) => (a.start_time || '').localeCompare(b.start_time || ''));
        const firstDate = sorted.length ? (sorted[0].start_time || '').slice(0, 10) : null;
        const fromHtml = firstDate
            ? escapeHtml(formatDateDMY(firstDate))
            : `<span class="text-subtle">${escapeHtml(t('tasks.no_sessions_yet', 'Sin sesiones'))}</span>`;

        // Indicador a tiempo / retraso (reutiliza helper)
        const statusBadgeHtml = buildStatusBadge(task, entries);

        // Agrupar entries por fecha (descendente) para no repetir fecha en cada fila
        const byDate = {};
        entries.forEach(e => {
            const d = (e.start_time || '').slice(0, 10);
            if (!byDate[d]) byDate[d] = [];
            byDate[d].push(e);
        });
        const dates = Object.keys(byDate).sort().reverse();

        const entriesHtml = entries.length === 0
            ? `<p class="text-subtle text-sm m-0">${escapeHtml(t('tasks.no_entries_yet', 'Aun no hay registros cerrados.'))}</p>`
            : `
                <table class="task-detail-entries">
                    <thead>
                        <tr>
                            <th>${escapeHtml(t('tasks.entry_start', 'Inicio'))}</th>
                            <th>${escapeHtml(t('tasks.entry_end', 'Fin'))}</th>
                            <th class="text-right">${escapeHtml(t('tasks.col_total_time', 'Tiempo'))}</th>
                        </tr>
                    </thead>
                    ${dates.map(d => `
                        <tbody data-entry-group="${escapeHtml(d)}">
                            ${dates.length > 1 ? `<tr class="task-detail-entries-daysep"><td colspan="3">${escapeHtml(formatDateDMY(d))}</td></tr>` : ''}
                            ${byDate[d].map(e => renderEntryRow(e)).join('')}
                        </tbody>
                    `).join('')}
                </table>
            `;

        return `
            <div class="task-detail-content" data-task-id="${task.id}">
                <div class="task-detail-meta-row">
                    <div class="task-detail-meta-item">
                        <span class="task-detail-meta-label">${escapeHtml(t('tasks.field_priority', 'Prioridad'))}:</span>
                        ${priorityHtml}
                    </div>
                    <div class="task-detail-meta-item">
                        <span class="task-detail-meta-label">${escapeHtml(t('tasks.field_vencimiento', 'Vencimiento'))}:</span>
                        <span class="task-detail-due">${dueHtml}</span>
                    </div>
                    <div class="task-detail-meta-item">
                        <span class="task-detail-meta-label">${escapeHtml(t('tasks.field_since', 'Desde'))}:</span>
                        <span class="task-detail-since">${fromHtml}</span>
                    </div>
                    ${statusBadgeHtml ? `<div class="task-detail-meta-item task-detail-meta-badge">${statusBadgeHtml}</div>` : ''}
                </div>
                <div class="task-detail-body">
                    <div class="task-detail-block">
                        <h5 class="task-detail-label">${escapeHtml(t('tasks.field_description', 'Descripcion'))}</h5>
                        <div class="task-detail-desc">${descHtml}</div>
                    </div>
                    <div class="task-detail-block">
                        <h5 class="task-detail-label">${escapeHtml(t('tasks.entries_title', 'Registros'))}</h5>
                        ${entriesHtml}
                    </div>
                </div>
            </div>
        `;
    }

    function renderEntryRow(e) {
        const startHour = (e.start_time || '').slice(11, 16);
        const endHour = e.end_time ? e.end_time.slice(11, 16) : '—';
        const dur = e.duration_seconds ? formatDuration(parseInt(e.duration_seconds, 10)) : '—';
        return `
            <tr class="task-detail-entry-row" data-entry-id="${e.id}">
                <td class="text-mono">${startHour}</td>
                <td class="text-mono">${endHour}</td>
                <td class="text-right text-mono">${dur}</td>
            </tr>
        `;
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
        listState.filters = { search: '', alliance: '', priority: '', tags: [], dateFrom: '', dateTo: '' };
        listState.historyPage = 1;
        ['filterSearch', 'filterAlliance', 'filterPriority'].forEach(id => {
            const el = document.getElementById(id); if (el) el.value = '';
        });
        // Resetear multiselect de etiquetas
        document.querySelectorAll('#filterTagsDropdown input[type="checkbox"]').forEach(cb => { cb.checked = false; });
        updateTagsMultiselectLabel();
        initListDefaults();
        loadList();
    }

    function updateTagsMultiselectLabel() {
        const trigger = document.getElementById('filterTagsTrigger');
        const label = document.getElementById('filterTagsLabel');
        if (!trigger || !label) return;
        const count = listState.filters.tags.length;
        if (count === 0) {
            label.textContent = t('tasks.filter_all_tags', 'Todas las etiquetas');
            trigger.classList.remove('has-selection');
        } else if (count === 1) {
            // Mostrar el nombre de la unica etiqueta seleccionada
            const tag = allTags.find(tg => tg.id == listState.filters.tags[0]);
            label.textContent = tag?.name || `1 ${t('tasks.tag_singular', 'etiqueta')}`;
            trigger.classList.add('has-selection');
        } else {
            label.textContent = `${count} ${t('tasks.tag_plural', 'etiquetas')}`;
            trigger.classList.add('has-selection');
        }
    }

    function setupTagsMultiselect() {
        const wrapper  = document.getElementById('filterTagsWrapper');
        const trigger  = document.getElementById('filterTagsTrigger');
        const dropdown = document.getElementById('filterTagsDropdown');
        if (!wrapper || !trigger || !dropdown) return;

        const close = () => {
            dropdown.classList.add('d-none');
            trigger.setAttribute('aria-expanded', 'false');
        };
        const open = () => {
            dropdown.classList.remove('d-none');
            trigger.setAttribute('aria-expanded', 'true');
        };

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            if (dropdown.classList.contains('d-none')) open(); else close();
        });

        dropdown.addEventListener('change', (e) => {
            if (!e.target.matches('input[type="checkbox"]')) return;
            const selected = Array.from(dropdown.querySelectorAll('input[type="checkbox"]:checked'))
                .map(cb => parseInt(cb.value, 10));
            listState.filters.tags = selected;
            listState.historyPage = 1;
            updateTagsMultiselectLabel();
            renderAllPanels();
        });

        // Click fuera cierra
        document.addEventListener('click', (e) => {
            if (!wrapper.contains(e.target)) close();
        });
        // Escape cierra
        dropdown.addEventListener('keydown', (e) => { if (e.key === 'Escape') { close(); trigger.focus(); } });

        updateTagsMultiselectLabel();
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
        document.getElementById('btnNewTask')?.addEventListener('click', () => openEditForm({ create: true }));

        restoreTimer();

        // Listado
        initListDefaults();
        setupListBindings();
        loadList();
    });

})();
