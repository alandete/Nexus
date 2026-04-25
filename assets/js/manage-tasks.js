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

    /* ========================================================
     * Import / Export
     * ======================================================== */

    const alliances = Array.isArray(data.alliances) ? data.alliances : [];

    // Estado del módulo IO
    let parsedEntries  = [];   // { allianceName, taskTitle, tags:[], startTime, endTime, durationSeconds }
    let allianceMap    = {};   // allianceName.lower → id | null (null = descartar)
    let tagMap         = {};   // tagName.lower → { action:'create'|'map', tagId?:number, display:string }
    let exportRange    = 'week';
    let exportFormat   = 'nexus';
    let importFormat   = 'clockify';

    // ── Utilidades CSV ──────────────────────────────────────────

    function parseCSVText(text) {
        // Normalizar saltos de linea y strip BOM
        if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
        text = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n');
        const lines = text.split('\n');
        const result = [];
        for (const line of lines) {
            if (!line.trim()) continue;
            result.push(parseCSVRow(line));
        }
        return result;
    }

    function parseCSVRow(line) {
        const fields = [];
        let i = 0;
        while (i < line.length) {
            if (line[i] === '"') {
                i++;
                let val = '';
                while (i < line.length) {
                    if (line[i] === '"' && line[i + 1] === '"') { val += '"'; i += 2; }
                    else if (line[i] === '"') { i++; break; }
                    else val += line[i++];
                }
                fields.push(val);
                if (line[i] === ',') i++;
            } else {
                let val = '';
                while (i < line.length && line[i] !== ',') val += line[i++];
                fields.push(val.trim());
                if (line[i] === ',') i++;
            }
        }
        return fields;
    }

    // Convierte "22/04/2026" + "4:30 PM" → "2026-04-22 16:30:00"
    function parseClockifyDateTime(dateStr, timeStr) {
        const dp = dateStr.split('/');
        if (dp.length !== 3) return null;
        const datePart = `${dp[2]}-${dp[1].padStart(2,'0')}-${dp[0].padStart(2,'0')}`;
        const tm = timeStr.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
        if (!tm) return null;
        let h = parseInt(tm[1], 10);
        const m = tm[2];
        const ampm = tm[3].toUpperCase();
        if (ampm === 'PM' && h !== 12) h += 12;
        if (ampm === 'AM' && h === 12) h = 0;
        return `${datePart} ${String(h).padStart(2,'0')}:${m}:00`;
    }

    // Convierte "YYYY-MM-DD" + "HH:MM" → "YYYY-MM-DD HH:MM:00"
    function parseNexusDateTime(dateStr, timeStr) {
        if (!dateStr || !timeStr) return null;
        return `${dateStr} ${timeStr}:00`;
    }

    function durationSeconds(start, end) {
        return Math.max(0, Math.round((new Date(start.replace(' ', 'T')) - new Date(end.replace(' ', 'T'))) / -1000));
    }

    // ── Parseo por formato ──────────────────────────────────────

    function parseClockify(rows) {
        // Columnas: 0=Proyecto 2=Descripcion 7=Etiquetas 8=FechaIni 9=HoraIni 10=FechaFin 11=HoraFin
        const entries = [];
        for (let i = 1; i < rows.length; i++) {
            const r = rows[i];
            if (r.length < 12) continue;
            const startTime = parseClockifyDateTime(r[8], r[9]);
            const endTime   = parseClockifyDateTime(r[10], r[11]);
            if (!startTime || !endTime) continue;
            const rawTags = r[7] ? r[7].split(',').map(s => s.trim()).filter(Boolean) : [];
            entries.push({
                allianceName:    r[0].trim(),
                taskTitle:       r[2].trim(),
                tags:            rawTags,
                startTime,
                endTime,
                durationSeconds: durationSeconds(startTime, endTime),
            });
        }
        return entries;
    }

    function parseNexus(rows) {
        // Columnas: 0=Alianza 1=Tarea 2=Etiquetas 3=FechaIni 4=HoraIni 5=FechaFin 6=HoraFin
        const entries = [];
        for (let i = 1; i < rows.length; i++) {
            const r = rows[i];
            if (r.length < 7) continue;
            const startTime = parseNexusDateTime(r[3], r[4]);
            const endTime   = parseNexusDateTime(r[5], r[6]);
            if (!startTime || !endTime) continue;
            const rawTags = r[2] ? r[2].split(',').map(s => s.trim()).filter(Boolean) : [];
            entries.push({
                allianceName:    r[0].trim(),
                taskTitle:       r[1].trim(),
                tags:            rawTags,
                startTime,
                endTime,
                durationSeconds: durationSeconds(startTime, endTime),
            });
        }
        return entries;
    }

    // ── Mapping helpers ─────────────────────────────────────────

    function buildMappings(entries) {
        const allianceNames = [...new Set(entries.map(e => e.allianceName).filter(Boolean))];
        const tagNames      = [...new Set(entries.flatMap(e => e.tags).filter(Boolean))];

        allianceMap = {};
        for (const name of allianceNames) {
            const found = alliances.find(a => a.name.toLowerCase() === name.toLowerCase());
            allianceMap[name.toLowerCase()] = found ? found.id : null;
        }

        tagMap = {};
        for (const name of tagNames) {
            const found = tags.find(tg => tg.name.toLowerCase() === name.toLowerCase());
            tagMap[name.toLowerCase()] = found
                ? { action: 'map', tagId: found.id, display: name }
                : { action: 'create', display: name };
        }
    }

    function unknownAlliances() {
        return Object.entries(allianceMap)
            .filter(([, v]) => v === null)
            .map(([k]) => parsedEntries.find(e => e.allianceName.toLowerCase() === k)?.allianceName || k);
    }

    function unknownTags() {
        return Object.entries(tagMap)
            .filter(([, v]) => v.action === 'create')
            .map(([, v]) => v.display);
    }

    // ── Render UI ───────────────────────────────────────────────

    function renderIoStats() {
        const el = document.getElementById('ioStats');
        if (!el) return;
        const uniqueTasks     = new Set(parsedEntries.map(e => `${e.allianceName}::${e.taskTitle}`)).size;
        const uniqueAlliances = new Set(parsedEntries.map(e => e.allianceName)).size;
        const uniqueTags      = new Set(parsedEntries.flatMap(e => e.tags)).size;
        el.innerHTML = `
            <div class="io-stat"><span class="io-stat-value">${parsedEntries.length}</span><span class="io-stat-label">${t('manage_tasks.import_stat_entries','entradas')}</span></div>
            <div class="io-stat"><span class="io-stat-value">${uniqueTasks}</span><span class="io-stat-label">${t('manage_tasks.import_stat_tasks','tareas')}</span></div>
            <div class="io-stat"><span class="io-stat-value">${uniqueAlliances}</span><span class="io-stat-label">${t('manage_tasks.import_stat_alliances','alianzas')}</span></div>
            <div class="io-stat"><span class="io-stat-value">${uniqueTags}</span><span class="io-stat-label">${t('manage_tasks.import_stat_tags','etiquetas')}</span></div>
        `;
    }

    function allianceSelectHtml(unknownName) {
        const opts = alliances.map(a =>
            `<option value="${a.id}">${escapeHtml(a.name)}</option>`
        ).join('');
        return `
            <div class="io-mapping-row" data-unknown="${escapeHtml(unknownName)}">
                <span class="io-mapping-unknown">${escapeHtml(unknownName)}</span>
                <i class="bi bi-arrow-right io-mapping-arrow" aria-hidden="true"></i>
                <select class="form-control form-control-sm io-alliance-select" data-key="${escapeHtml(unknownName.toLowerCase())}">
                    <option value="">${t('manage_tasks.import_map_discard','Descartar entradas')}</option>
                    ${opts}
                </select>
            </div>`;
    }

    function tagSelectHtml(unknownName) {
        const opts = tags.map(tg =>
            `<option value="${tg.id}">${escapeHtml(tg.name)}</option>`
        ).join('');
        return `
            <div class="io-mapping-row" data-unknown="${escapeHtml(unknownName)}">
                <span class="io-mapping-unknown">${escapeHtml(unknownName)}</span>
                <i class="bi bi-arrow-right io-mapping-arrow" aria-hidden="true"></i>
                <select class="form-control form-control-sm io-tag-select" data-key="${escapeHtml(unknownName.toLowerCase())}">
                    <option value="__new__">${t('manage_tasks.import_map_create','✨ Crear nueva')}</option>
                    ${opts}
                </select>
            </div>`;
    }

    function renderAllianceMapping() {
        const section = document.getElementById('ioAllianceMapping');
        const container = document.getElementById('ioAllianceMappingRows');
        if (!section || !container) return;
        const unknown = unknownAlliances();
        if (!unknown.length) { section.classList.add('d-none'); return; }
        section.classList.remove('d-none');
        container.innerHTML = unknown.map(allianceSelectHtml).join('');
    }

    function renderTagMapping() {
        const section = document.getElementById('ioTagMapping');
        const container = document.getElementById('ioTagMappingRows');
        if (!section || !container) return;
        const unknown = unknownTags();
        if (!unknown.length) { section.classList.add('d-none'); return; }
        section.classList.remove('d-none');
        container.innerHTML = unknown.map(tagSelectHtml).join('');
    }

    function renderPreviewTable() {
        const wrap = document.getElementById('ioPreviewWrap');
        if (!wrap) return;
        const preview = parsedEntries.slice(0, 10);
        const rows = preview.map(e => `
            <tr>
                <td>${escapeHtml(e.allianceName)}</td>
                <td>${escapeHtml(e.taskTitle)}</td>
                <td>${escapeHtml(e.tags.join(', '))}</td>
                <td class="text-mono">${e.startTime.slice(0,16).replace('T',' ')}</td>
                <td class="text-mono">${formatDurPreview(e.durationSeconds)}</td>
            </tr>`).join('');
        wrap.innerHTML = `
            <table class="table table-sm">
                <thead><tr>
                    <th>Alianza</th><th>Tarea</th><th>Etiquetas</th><th>Inicio</th><th>Duración</th>
                </tr></thead>
                <tbody>${rows}</tbody>
            </table>`;
    }

    function formatDurPreview(s) {
        const h = Math.floor(s / 3600);
        const m = Math.floor((s % 3600) / 60);
        return `${h}:${String(m).padStart(2,'0')}`;
    }

    function updateImportBtn() {
        const lbl = document.getElementById('ioImportLabel');
        if (!lbl) return;
        // Contar entradas que no estarán descartadas
        const active = parsedEntries.filter(e => {
            const v = allianceMap[e.allianceName.toLowerCase()];
            return v !== null && v !== undefined && v !== '';
        }).length;
        lbl.textContent = `${t('manage_tasks.import_btn','Importar entradas')} (${active})`;
    }

    function showParseResult() {
        renderIoStats();
        renderAllianceMapping();
        renderTagMapping();
        renderPreviewTable();
        updateImportBtn();
        const result = document.getElementById('ioParseResult');
        if (result) result.classList.remove('d-none');
    }

    function resetImport() {
        parsedEntries = [];
        allianceMap = {};
        tagMap = {};
        const result = document.getElementById('ioParseResult');
        if (result) result.classList.add('d-none');
        const dz = document.getElementById('ioDropzone');
        if (dz) dz.classList.remove('has-file');
        const fi = document.getElementById('ioFileInput');
        if (fi) fi.value = '';
    }

    // ── Leer y procesar archivo ──────────────────────────────────

    function processFile(file) {
        if (!file || !file.name.endsWith('.csv')) {
            Toast.error(t('manage_tasks.import_err_parse', 'Archivo CSV inválido.'));
            return;
        }
        const reader = new FileReader();
        reader.onload = (ev) => {
            const rows = parseCSVText(ev.target.result);
            parsedEntries = importFormat === 'clockify'
                ? parseClockify(rows)
                : parseNexus(rows);
            if (!parsedEntries.length) {
                Toast.error(t('manage_tasks.import_err_empty', 'El archivo no contiene entradas válidas.'));
                return;
            }
            buildMappings(parsedEntries);
            showParseResult();
            const dz = document.getElementById('ioDropzone');
            if (dz) dz.classList.add('has-file');
        };
        reader.onerror = () => Toast.error(t('manage_tasks.import_err_parse', 'No se pudo leer el archivo.'));
        reader.readAsText(file, 'UTF-8');
    }

    // ── Export ───────────────────────────────────────────────────

    function getRangeDates() {
        const today = new Date();
        const pad = n => String(n).padStart(2, '0');
        const fmt = d => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
        if (exportRange === 'today') {
            return { start: fmt(today), end: fmt(today) };
        }
        if (exportRange === 'week') {
            const mon = new Date(today);
            mon.setDate(today.getDate() - ((today.getDay() + 6) % 7));
            return { start: fmt(mon), end: fmt(today) };
        }
        if (exportRange === 'month') {
            const s = new Date(today.getFullYear(), today.getMonth(), 1);
            return { start: fmt(s), end: fmt(today) };
        }
        if (exportRange === 'last_month') {
            const s = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const e = new Date(today.getFullYear(), today.getMonth(), 0);
            return { start: fmt(s), end: fmt(e) };
        }
        // custom
        const s = document.getElementById('ioCustomStart')?.value;
        const e = document.getElementById('ioCustomEnd')?.value;
        return { start: s || fmt(today), end: e || fmt(today) };
    }

    function doExport() {
        const { start, end } = getRangeDates();
        const url = `includes/io_actions.php?action=export&format=${exportFormat}&start=${start}&end=${end}`;
        const link = document.createElement('a');
        link.href = url;
        link.download = '';
        document.body.appendChild(link);
        link.click();
        link.remove();
    }

    // ── Import confirm ───────────────────────────────────────────

    async function doImport() {
        // Leer mappings actuales del DOM
        document.querySelectorAll('.io-alliance-select').forEach(sel => {
            const key = sel.dataset.key;
            allianceMap[key] = sel.value ? parseInt(sel.value, 10) : null;
        });
        document.querySelectorAll('.io-tag-select').forEach(sel => {
            const key = sel.dataset.key;
            if (sel.value === '__new__') {
                tagMap[key] = { action: 'create', display: tagMap[key]?.display || key };
            } else {
                tagMap[key] = { action: 'map', tagId: parseInt(sel.value, 10) };
            }
        });

        // Construir array de entries resueltas
        const resolved = [];
        for (const e of parsedEntries) {
            const allianceId = allianceMap[e.allianceName.toLowerCase()] ?? null;
            if (allianceId === null) continue; // descartado

            const tagIds  = [];
            const newTags = [];
            for (const tagName of e.tags) {
                const m = tagMap[tagName.toLowerCase()];
                if (!m) continue;
                if (m.action === 'map' && m.tagId) tagIds.push(m.tagId);
                else newTags.push(tagName);
            }

            resolved.push({
                alliance_id:      allianceId,
                task_title:       e.taskTitle,
                tag_ids:          tagIds,
                new_tags:         newTags,
                start_time:       e.startTime,
                end_time:         e.endTime,
                duration_seconds: e.durationSeconds,
            });
        }

        if (!resolved.length) {
            Toast.error('No hay entradas para importar (todas descartadas).');
            return;
        }

        const btn = document.getElementById('btnImport');
        if (btn) { btn.disabled = true; btn.classList.add('is-loading'); }

        try {
            const fd = new FormData();
            fd.append('action', 'import');
            fd.append('entries', JSON.stringify(resolved));
            const res = await fetch('includes/io_actions.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: fd,
            });
            const result = await res.json();
            if (result.success) {
                if (result.inserted === 0) {
                    Toast.warning(result.message);
                } else {
                    Toast.success(result.message);
                }
                resetImport();
            } else {
                Toast.error(result.message || 'Error al importar.');
            }
        } catch {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            if (btn) { btn.disabled = false; btn.classList.remove('is-loading'); }
        }
    }

    // ── Init IO tab ──────────────────────────────────────────────

    let ioInitialized = false;
    function initIo() {
        if (ioInitialized) return;
        ioInitialized = true;
        // Range selector export
        document.getElementById('ioRangeGroup')?.addEventListener('click', e => {
            const btn = e.target.closest('[data-range]');
            if (!btn) return;
            exportRange = btn.dataset.range;
            document.querySelectorAll('#ioRangeGroup .btn-group-item').forEach(b => {
                const active = b === btn;
                b.classList.toggle('active', active);
                b.setAttribute('aria-checked', String(active));
            });
            const custom = document.getElementById('ioCustomRange');
            if (custom) custom.classList.toggle('is-hidden', exportRange !== 'custom');
        });

        // Format export
        document.getElementById('ioExportFormatGroup')?.addEventListener('click', e => {
            const btn = e.target.closest('[data-format]');
            if (!btn) return;
            exportFormat = btn.dataset.format;
            document.querySelectorAll('#ioExportFormatGroup .btn-group-item').forEach(b => {
                const active = b === btn;
                b.classList.toggle('active', active);
                b.setAttribute('aria-checked', String(active));
            });
        });

        // Export button
        document.getElementById('btnExport')?.addEventListener('click', doExport);

        // Format import selector
        document.getElementById('ioImportFormatGroup')?.addEventListener('click', e => {
            const btn = e.target.closest('[data-format]');
            if (!btn) return;
            importFormat = btn.dataset.format;
            document.querySelectorAll('#ioImportFormatGroup .btn-group-item').forEach(b => {
                const active = b === btn;
                b.classList.toggle('active', active);
                b.setAttribute('aria-checked', String(active));
            });
            const hint = document.getElementById('ioDropzoneHint');
            if (hint) hint.textContent = importFormat === 'clockify'
                ? t('manage_tasks.import_drop_hint_clockify', 'Clockify: Reports → Detailed → Export CSV')
                : t('manage_tasks.import_drop_hint_nexus', 'Formato: Alianza, Tarea, Etiquetas, Fecha inicio, Hora inicio, Fecha fin, Hora fin');
            resetImport();
        });

        // Dropzone click
        const dz  = document.getElementById('ioDropzone');
        const fIn = document.getElementById('ioFileInput');
        dz?.addEventListener('click', () => fIn?.click());
        dz?.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fIn?.click(); } });

        // Drag & drop
        dz?.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('is-over'); });
        dz?.addEventListener('dragleave', () => dz.classList.remove('is-over'));
        dz?.addEventListener('drop', e => {
            e.preventDefault();
            dz.classList.remove('is-over');
            processFile(e.dataTransfer.files[0]);
        });

        // File input
        fIn?.addEventListener('change', () => processFile(fIn.files[0]));

        // Alliance select change → recount
        document.getElementById('ioAllianceMappingRows')?.addEventListener('change', e => {
            if (!e.target.matches('.io-alliance-select')) return;
            allianceMap[e.target.dataset.key] = e.target.value ? parseInt(e.target.value, 10) : null;
            updateImportBtn();
        });

        // Import / cancel
        document.getElementById('btnImport')?.addEventListener('click', doImport);
        document.getElementById('btnImportCancel')?.addEventListener('click', resetImport);
    }

    // ── Init Cleanup tab ────────────────────────────────────────

    let cleanupInitialized = false;
    function initCleanup() {
        if (cleanupInitialized) return;
        cleanupInitialized = true;

        let previewDone = false;

        async function apiCleanup(action, payload = {}) {
            const fd = new FormData();
            fd.append('action', action);
            Object.keys(payload).forEach(k => fd.append(k, payload[k]));
            const res = await fetch('includes/tasks_cleanup_actions.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: fd,
            });
            return res.json();
        }

        function getFilters() {
            return {
                alliance_id: document.getElementById('cleanupAlliance')?.value || '0',
                statuses:    [...document.querySelectorAll('.cleanup-status-cb:checked')].map(cb => cb.value).join(','),
                before_date: document.getElementById('cleanupBeforeDate')?.value || '',
            };
        }

        function setCounters(tasks, entries) {
            const t1 = document.getElementById('cleanupCountTasks');
            const t2 = document.getElementById('cleanupCountEntries');
            if (t1) t1.textContent = tasks;
            if (t2) t2.textContent = entries;
        }

        function resetPreview() {
            previewDone = false;
            setCounters(0, 0);
            const execBtn = document.getElementById('btnCleanupExecute');
            if (execBtn) execBtn.disabled = true;
        }

        document.getElementById('cleanupAlliance')?.addEventListener('change', resetPreview);
        document.getElementById('cleanupBeforeDate')?.addEventListener('change', resetPreview);
        document.querySelectorAll('.cleanup-status-cb').forEach(cb => cb.addEventListener('change', resetPreview));

        document.getElementById('btnCleanupPreview')?.addEventListener('click', async () => {
            const { alliance_id, statuses, before_date } = getFilters();
            if (!statuses) {
                Toast.error(t('manage_tasks.cleanup_err_no_status', 'Selecciona al menos un estado.'));
                return;
            }
            const btn = document.getElementById('btnCleanupPreview');
            btn.disabled = true;
            btn.classList.add('is-loading');
            try {
                const result = await apiCleanup('preview', { alliance_id, statuses, before_date });
                if (result.success) {
                    setCounters(result.task_count, result.entry_count);
                    const execBtn = document.getElementById('btnCleanupExecute');
                    if (execBtn) execBtn.disabled = result.task_count === 0;
                    previewDone = true;
                } else {
                    Toast.error(result.message || 'Error al calcular.');
                }
            } catch {
                Toast.error(t('common.err_network', 'Error de red.'));
            } finally {
                btn.disabled = false;
                btn.classList.remove('is-loading');
            }
        });

        document.getElementById('btnCleanupExecute')?.addEventListener('click', async () => {
            const { alliance_id, statuses, before_date } = getFilters();
            const nTasks   = document.getElementById('cleanupCountTasks')?.textContent   || '0';
            const nEntries = document.getElementById('cleanupCountEntries')?.textContent || '0';
            const summary  = t('manage_tasks.cleanup_preview_result', '{tasks} tareas y {entries} entradas serán eliminadas.')
                .replace('{tasks}', nTasks).replace('{entries}', nEntries);
            const confirmed = await ConfirmModal.show({
                title:         t('manage_tasks.cleanup_confirm_title', 'Confirmar limpieza'),
                message:       summary + ' ' + t('manage_tasks.cleanup_confirm_undo', 'Esta acción no se puede deshacer.'),
                acceptText:    t('manage_tasks.cleanup_btn_execute', 'Eliminar selección'),
                acceptVariant: 'danger',
                icon:          'bi-trash',
                variant:       'danger',
            });
            if (!confirmed) return;

            const btn = document.getElementById('btnCleanupExecute');
            btn.disabled = true;
            btn.classList.add('is-loading');
            try {
                const result = await apiCleanup('execute', { alliance_id, statuses, before_date });
                if (result.success) {
                    const msg = t('manage_tasks.cleanup_success', '{n} tareas eliminadas.').replace('{n}', result.deleted);
                    Toast.success(msg);
                    resetPreview();
                } else {
                    Toast.error(result.message || 'Error al eliminar.');
                }
            } catch {
                Toast.error(t('common.err_network', 'Error de red.'));
            } finally {
                btn.disabled = false;
                btn.classList.remove('is-loading');
            }
        });

        document.getElementById('btnCleanupNuke')?.addEventListener('click', async () => {
            const first = await ConfirmModal.show({
                title:         t('manage_tasks.cleanup_nuke_confirm_title', '¿Eliminar todas las tareas?'),
                message:       t('manage_tasks.cleanup_nuke_confirm_msg', 'Se eliminarán TODAS las tareas y entradas de tiempo. No hay vuelta atrás.'),
                acceptText:    t('manage_tasks.cleanup_nuke_confirm_btn', 'Sí, eliminar todo'),
                acceptVariant: 'danger',
                icon:          'bi-exclamation-triangle-fill',
                variant:       'danger',
            });
            if (!first) return;

            const second = await ConfirmModal.show({
                title:         t('manage_tasks.cleanup_nuke_confirm2_title', '¿Estás completamente seguro?'),
                message:       t('manage_tasks.cleanup_nuke_confirm2_msg', 'Esta es la confirmación final. No podrás recuperar ningún dato.'),
                acceptText:    t('manage_tasks.cleanup_nuke_confirm2_btn', 'Eliminar permanentemente'),
                acceptVariant: 'danger',
                icon:          'bi-exclamation-octagon-fill',
                variant:       'danger',
            });
            if (!second) return;

            const btn = document.getElementById('btnCleanupNuke');
            btn.disabled = true;
            btn.classList.add('is-loading');
            try {
                const result = await apiCleanup('nuke');
                if (result.success) {
                    const msg = t('manage_tasks.cleanup_success', '{n} tareas eliminadas.').replace('{n}', result.deleted);
                    Toast.success(msg);
                } else {
                    Toast.error(result.message || 'Error.');
                }
            } catch {
                Toast.error(t('common.err_network', 'Error de red.'));
            } finally {
                btn.disabled = false;
                btn.classList.remove('is-loading');
            }
        });

        // ── Duplicados ──────────────────────────────────────────
        let dupesCount = 0;

        document.getElementById('btnDupesDetect')?.addEventListener('click', async () => {
            const btn = document.getElementById('btnDupesDetect');
            const resultEl = document.getElementById('dupesResult');
            const fixBtn = document.getElementById('btnDupesFix');
            btn.disabled = true;
            btn.classList.add('is-loading');
            try {
                const result = await apiCleanup('detect_dupes');
                if (result.success) {
                    dupesCount = result.surplus;
                    if (dupesCount === 0) {
                        resultEl.textContent = t('manage_tasks.cleanup_dupes_none', 'No se encontraron duplicados.');
                        if (fixBtn) fixBtn.disabled = true;
                    } else {
                        resultEl.textContent = t('manage_tasks.cleanup_dupes_found', '{n} entradas duplicadas encontradas.').replace('{n}', dupesCount);
                        if (fixBtn) fixBtn.disabled = false;
                    }
                } else {
                    Toast.error(result.message || 'Error al detectar duplicados.');
                }
            } catch {
                Toast.error(t('common.err_network', 'Error de red.'));
            } finally {
                btn.disabled = false;
                btn.classList.remove('is-loading');
            }
        });

        document.getElementById('btnDupesFix')?.addEventListener('click', async () => {
            const confirmed = await ConfirmModal.show({
                title:         t('manage_tasks.cleanup_confirm_title', 'Confirmar limpieza'),
                message:       t('manage_tasks.cleanup_dupes_confirm', '¿Eliminar {n} entradas duplicadas? Se conservará la entrada más antigua de cada grupo.').replace('{n}', dupesCount),
                acceptText:    t('manage_tasks.cleanup_dupes_btn_fix', 'Eliminar duplicados'),
                acceptVariant: 'danger',
                icon:          'bi-trash',
                variant:       'danger',
            });
            if (!confirmed) return;

            const btn = document.getElementById('btnDupesFix');
            btn.disabled = true;
            btn.classList.add('is-loading');
            try {
                const result = await apiCleanup('fix_dupes');
                if (result.success) {
                    Toast.success(t('manage_tasks.cleanup_dupes_success', '{n} entradas duplicadas eliminadas.').replace('{n}', result.deleted));
                    document.getElementById('dupesResult').textContent = '';
                    dupesCount = 0;
                } else {
                    Toast.error(result.message || 'Error al eliminar duplicados.');
                    btn.disabled = false;
                }
            } catch {
                Toast.error(t('common.err_network', 'Error de red.'));
                btn.disabled = false;
            } finally {
                btn.classList.remove('is-loading');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        refreshAll();

        // Tabs
        document.querySelectorAll('.manage-tabs .tab').forEach(btn => {
            btn.addEventListener('click', () => {
                switchTab(btn.dataset.tab);
                if (btn.dataset.tab === 'io')      initIo();
                if (btn.dataset.tab === 'cleanup') initCleanup();
            });
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
