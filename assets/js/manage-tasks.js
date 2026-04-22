/**
 * Nexus 2.0 — Administrar tareas (Ajustes)
 * Sub-fase 5: CRUD de etiquetas (edicion inline) + tabs
 */
(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const T = window.__T__ || {};
    const t = (key, fallback) => T[key] || fallback;

    const data = window.__MANAGE_TASKS__ || { tags: [], canWrite: false, canDelete: false };
    let tags = Array.isArray(data.tags) ? data.tags.slice() : [];
    const canWrite  = !!data.canWrite;
    const canDelete = !!data.canDelete;

    const DEFAULT_COLOR = '#585d8a';

    async function api(action, payload = {}) {
        const fd = new FormData();
        fd.append('action', action);
        Object.keys(payload).forEach(k => fd.append(k, payload[k] ?? ''));
        const res = await fetch('includes/tasks_actions.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: fd,
        });
        return res.json();
    }

    function escapeHtml(str) {
        const d = document.createElement('div');
        d.textContent = str ?? '';
        return d.innerHTML;
    }

    /* ========================================================
     * Tabs
     * ======================================================== */

    function switchTab(tabName) {
        ['tags', 'io', 'cleanup'].forEach(name => {
            const tab = document.getElementById('tab' + name.charAt(0).toUpperCase() + name.slice(1));
            const panel = document.getElementById('panel' + name.charAt(0).toUpperCase() + name.slice(1));
            const isActive = name === tabName;
            if (tab) {
                tab.classList.toggle('active', isActive);
                tab.setAttribute('aria-selected', String(isActive));
            }
            if (panel) {
                panel.classList.toggle('d-none', !isActive);
                panel.hidden = !isActive;
            }
        });
    }

    /* ========================================================
     * Render inline de etiquetas
     * ======================================================== */

    function renderStats() {
        const total = tags.length;
        const inUse = tags.filter(tg => (parseInt(tg.usage_count, 10) || 0) > 0).length;
        const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        setText('statTagsTotal', total);
        setText('statTagsInUse', inUse);
        setText('statTagsUnused', total - inUse);
    }

    function colorFieldHtml(color, fieldKey, disabled) {
        const dis = disabled ? 'disabled' : '';
        return `
            <div class="tag-color-field">
                <input type="color" class="tag-swatch-input" data-field="${fieldKey}-color"
                       value="${escapeHtml(color)}" ${dis}
                       aria-label="${t('manage_tasks.field_color', 'Color')}">
                <input type="text" class="form-control form-control-sm text-mono tag-hex-input"
                       data-field="${fieldKey}-hex" value="${escapeHtml(color)}"
                       maxlength="7" pattern="#[0-9a-fA-F]{6}" ${dis}
                       aria-label="${t('manage_tasks.field_color_hex', 'Código hex')}">
            </div>
        `;
    }

    function renderTagRow(tag) {
        const color = tag.color || DEFAULT_COLOR;
        const usage = parseInt(tag.usage_count, 10) || 0;
        const usageLabel = usage === 0
            ? t('manage_tasks.not_in_use', 'Sin uso')
            : (usage === 1
                ? `1 ${t('manage_tasks.task_singular', 'tarea')}`
                : `${usage} ${t('manage_tasks.task_plural', 'tareas')}`);

        const deleteBtn = canDelete ? `
            <button type="button" class="btn-icon btn-icon-danger" data-action="delete-tag"
                    data-tag-id="${tag.id}" data-tag-name="${escapeHtml(tag.name)}" data-tag-usage="${usage}"
                    data-tooltip="${t('common.delete', 'Eliminar')}" data-tooltip-position="top"
                    aria-label="${t('common.delete', 'Eliminar')}">
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>` : '';

        const nameReadonly = canWrite ? '' : 'readonly';

        return `
            <div class="tag-row" data-tag-id="${tag.id}" data-original-name="${escapeHtml(tag.name)}" data-original-color="${escapeHtml(color)}">
                <input type="text" class="form-control tag-name-input" data-field="name" value="${escapeHtml(tag.name)}" maxlength="40" ${nameReadonly} aria-label="${t('manage_tasks.field_name', 'Nombre')}">
                ${colorFieldHtml(color, 'edit', !canWrite)}
                <span class="tag-usage ${usage === 0 ? 'is-unused' : ''}">${escapeHtml(usageLabel)}</span>
                <div class="tag-actions">${deleteBtn}</div>
            </div>
        `;
    }

    function renderNewRow() {
        if (!canWrite) return '';
        return `
            <div class="tag-row tag-row-new" id="tagNewRow">
                <input type="text" class="form-control tag-name-input" data-field="new-name"
                       placeholder="${t('manage_tasks.new_tag_placeholder', 'Nueva etiqueta...')}"
                       maxlength="40" aria-label="${t('manage_tasks.field_name', 'Nombre')}">
                ${colorFieldHtml(DEFAULT_COLOR, 'new', false)}
                <span class="tag-usage text-subtlest">—</span>
                <div class="tag-actions">
                    <button type="button" class="btn-icon btn-icon-success" id="btnAddTag"
                            data-tooltip="${t('common.add', 'Agregar')}" data-tooltip-position="top"
                            aria-label="${t('common.add', 'Agregar')}">
                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    </button>
                </div>
            </div>
        `;
    }

    function renderGrid() {
        const grid = document.getElementById('tagsGrid');
        if (!grid) return;

        const header = `
            <div class="tag-row tag-row-head" role="row">
                <span>${escapeHtml(t('manage_tasks.col_name', 'Nombre'))}</span>
                <span>${escapeHtml(t('manage_tasks.col_color', 'Color'))}</span>
                <span>${escapeHtml(t('manage_tasks.col_usage', 'Uso'))}</span>
                <span class="sr-only">${escapeHtml(t('common.actions', 'Acciones'))}</span>
            </div>
        `;

        const rows = tags.map(renderTagRow).join('') || '';
        const empty = tags.length === 0
            ? `<div class="tag-row-empty text-subtle">${escapeHtml(t('manage_tasks.empty_tags_desc', 'Crea la primera etiqueta abajo.'))}</div>`
            : '';

        grid.innerHTML = header + rows + empty + renderNewRow();
    }

    function refreshAll() {
        renderStats();
        renderGrid();
    }

    /* ========================================================
     * Edicion inline: update al salir del campo si hubo cambio
     * ======================================================== */

    function readRowColor(row, prefix) {
        const colorInput = row.querySelector(`[data-field="${prefix}-color"]`);
        const hexInput   = row.querySelector(`[data-field="${prefix}-hex"]`);
        const hex = (hexInput?.value || '').trim();
        if (/^#[0-9a-fA-F]{6}$/.test(hex)) return hex.toLowerCase();
        return (colorInput?.value || DEFAULT_COLOR).toLowerCase();
    }

    async function saveTagInline(row) {
        const tagId = parseInt(row.dataset.tagId, 10);
        const nameInput = row.querySelector('[data-field="name"]');
        const colorInput = row.querySelector('[data-field="edit-color"]');
        const hexInput   = row.querySelector('[data-field="edit-hex"]');

        const newName  = (nameInput.value || '').trim();
        const newColor = readRowColor(row, 'edit');
        const origName = row.dataset.originalName || '';
        const origColor = (row.dataset.originalColor || '').toLowerCase();

        if (newName === origName && newColor === origColor) return; // sin cambios

        if (!newName) {
            Toast.error(t('manage_tasks.err_name_required', 'El nombre es obligatorio.'));
            nameInput.value = origName;
            return;
        }

        try {
            const result = await api('tags_update', { tag_id: tagId, name: newName, color: newColor });
            if (result.success) {
                row.dataset.originalName = newName;
                row.dataset.originalColor = newColor;
                // Sincronizar ambos inputs por si uno cambio y el otro no
                if (colorInput) colorInput.value = newColor;
                if (hexInput)   hexInput.value   = newColor;
                const tag = tags.find(tg => tg.id == tagId);
                if (tag) { tag.name = newName; tag.color = newColor; }
                Toast.success(t('manage_tasks.tag_updated', 'Etiqueta actualizada.'));
            } else {
                Toast.error(result.message || t('manage_tasks.err_save_tag', 'No se pudo guardar la etiqueta.'));
                // Revertir
                nameInput.value  = origName;
                if (colorInput) colorInput.value = origColor;
                if (hexInput)   hexInput.value   = origColor;
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        }
    }

    /* ========================================================
     * Agregar nueva etiqueta
     * ======================================================== */

    async function addNewTag() {
        const row = document.getElementById('tagNewRow');
        if (!row) return;
        const nameInput = row.querySelector('[data-field="new-name"]');
        const btn = document.getElementById('btnAddTag');

        const name = (nameInput.value || '').trim();
        const color = readRowColor(row, 'new');

        if (!name) {
            Toast.error(t('manage_tasks.err_name_required', 'El nombre es obligatorio.'));
            nameInput.focus();
            return;
        }

        if (btn) btn.disabled = true;
        try {
            const result = await api('tags_create', { name, color });
            if (result.success) {
                tags.push({ id: result.tag_id, name, color, usage_count: 0 });
                tags.sort((a, b) => (a.name || '').localeCompare(b.name || ''));
                Toast.success(t('manage_tasks.tag_created', 'Etiqueta creada.'));
                refreshAll();
                // Enfocar el input de "nueva" para permitir agregar otra rapidamente
                setTimeout(() => document.querySelector('[data-field="new-name"]')?.focus(), 50);
            } else {
                Toast.error(result.message || t('manage_tasks.err_save_tag', 'No se pudo guardar la etiqueta.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            if (btn) btn.disabled = false;
        }
    }

    /* ========================================================
     * Eliminar
     * ======================================================== */

    async function deleteTag(tagId, tagName, usage) {
        let message = t('manage_tasks.delete_tag_message', 'La etiqueta "{name}" se eliminara.')
            .replace('{name}', tagName);
        if (usage > 0) {
            message += ' ' + t('manage_tasks.delete_tag_warn_usage',
                'Esta asignada a {n} {label} — se quitara de todas ellas.')
                .replace('{n}', usage)
                .replace('{label}', usage === 1
                    ? t('manage_tasks.task_singular', 'tarea')
                    : t('manage_tasks.task_plural', 'tareas'));
        }
        message += ' ' + t('manage_tasks.delete_tag_warn_undo', 'Esta accion no se puede deshacer.');

        const confirmed = await ConfirmModal.show({
            title:   t('manage_tasks.delete_tag_title', 'Eliminar etiqueta'),
            message,
            acceptText: t('common.delete', 'Eliminar'),
            acceptVariant: 'danger',
            icon: 'bi-trash',
            variant: 'danger',
        });
        if (!confirmed) return;

        try {
            const result = await api('tags_delete', { tag_id: tagId });
            if (result.success) {
                tags = tags.filter(tg => tg.id != tagId);
                Toast.success(t('manage_tasks.tag_deleted', 'Etiqueta eliminada.'));
                refreshAll();
            } else {
                Toast.error(result.message || t('manage_tasks.err_delete_tag', 'No se pudo eliminar la etiqueta.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        }
    }

    /* ========================================================
     * Bindings
     * ======================================================== */

    document.addEventListener('DOMContentLoaded', () => {
        refreshAll();

        // Tabs
        document.querySelectorAll('.manage-tabs .tab').forEach(btn => {
            btn.addEventListener('click', () => switchTab(btn.dataset.tab));
        });

        const grid = document.getElementById('tagsGrid');
        if (!grid) return;

        // Delegacion: guardar al salir del input (blur) o al presionar Enter
        grid.addEventListener('blur', (e) => {
            const target = e.target;
            if (!target.matches('.tag-name-input, .tag-swatch-input, .tag-hex-input')) return;
            const row = target.closest('.tag-row');
            if (!row) return;
            // Fila "nueva" no dispara save por blur — se agrega con el boton +
            if (row.classList.contains('tag-row-new')) return;
            saveTagInline(row);
        }, true);

        grid.addEventListener('keydown', (e) => {
            if (e.key !== 'Enter') return;
            const target = e.target;
            if (!target.matches('.tag-name-input, .tag-hex-input')) return;
            e.preventDefault();
            const row = target.closest('.tag-row');
            if (!row) return;
            if (row.classList.contains('tag-row-new')) {
                addNewTag();
            } else {
                target.blur(); // dispara save via el listener blur
            }
        });

        // Sincronizar picker <-> hex en vivo
        grid.addEventListener('input', (e) => {
            const target = e.target;
            const row = target.closest('.tag-row');
            if (!row) return;
            if (target.matches('.tag-swatch-input')) {
                // Del picker al hex
                const prefix = target.dataset.field.replace('-color', '');
                const hex = row.querySelector(`[data-field="${prefix}-hex"]`);
                if (hex) hex.value = target.value;
            } else if (target.matches('.tag-hex-input')) {
                // Del hex al picker (solo si el hex es valido)
                const v = (target.value || '').trim();
                if (/^#[0-9a-fA-F]{6}$/.test(v)) {
                    const prefix = target.dataset.field.replace('-hex', '');
                    const picker = row.querySelector(`[data-field="${prefix}-color"]`);
                    if (picker) picker.value = v.toLowerCase();
                }
            }
        });

        // Click en acciones (eliminar / agregar)
        grid.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-action], #btnAddTag');
            if (!btn) return;
            if (btn.id === 'btnAddTag') {
                addNewTag();
                return;
            }
            if (btn.dataset.action === 'delete-tag') {
                const tagId = parseInt(btn.dataset.tagId, 10);
                const tag = tags.find(tg => tg.id == tagId);
                if (tag) deleteTag(tagId, btn.dataset.tagName || tag.name, parseInt(btn.dataset.tagUsage, 10) || 0);
            }
        });
    });

})();
