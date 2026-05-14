/**
 * Nexus 2.0 — Reportes de actividades (Fase 4.5)
 * Filtros: tipo de reporte, rango (semanal/mensual/personalizado), usuario.
 * Auto-carga inicial con defaults (resumido + mes actual + usuario logueado).
 */
(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const T = window.__T__ || {};
    const t = (key, fallback) => T[key] || fallback;
    const cfg = window.__REPORTS__ || { isAdmin: false, currentUser: {} };

    // Estado de filtros
    const state = {
        type:        'summary',  // 'summary' | 'detailed'
        range:       'monthly',  // 'weekly' | 'monthly' | 'custom'
        start:       null,
        end:         null,
        userId:      null,
        allianceIds: [],         // [] = todas
        tagIds:      [],         // [] = todas
    };

    let lastReport = null;
    let chartInstance = null;
    let barChartInstance = null;
    const multiSelectInstances = {};

    /* ========================================================
     * Helpers
     * ======================================================== */

    async function api(action, payload = {}) {
        const fd = new FormData();
        fd.append('action', action);
        Object.keys(payload).forEach(k => fd.append(k, payload[k] ?? ''));
        const res = await fetch('includes/tasks_report_actions.php', {
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

    function formatDuration(seconds) {
        seconds = parseInt(seconds, 10) || 0;
        if (seconds < 60) return '0m';
        const totalMin = Math.round(seconds / 60);
        const h = Math.floor(totalMin / 60);
        const m = totalMin % 60;
        if (h > 0 && m > 0) return `${h}h ${m}m`;
        if (h > 0) return `${h}h`;
        return `${m}m`;
    }

    // Traduce el status de MySQL (pending/in_progress/paused/completed) a etiqueta en ES
    function statusLabel(status) {
        const map = {
            pending:     t('tasks.status_pending',     'Pendiente'),
            in_progress: t('tasks.status_in_progress', 'En progreso'),
            paused:      t('tasks.status_paused',      'Pausada'),
            completed:   t('tasks.status_completed',   'Completada'),
        };
        return map[status] || status || '';
    }

    function localDateStr(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function computeRange() {
        const today = new Date();
        if (state.range === 'weekly') {
            const start = new Date(today); start.setDate(today.getDate() - 6);
            return { start: localDateStr(start), end: localDateStr(today) };
        }
        if (state.range === 'monthly') {
            // Mes anterior completo (es el caso de uso principal: reporte mensual)
            const first = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const last  = new Date(today.getFullYear(), today.getMonth(), 0);
            return { start: localDateStr(first), end: localDateStr(last) };
        }
        // custom
        return { start: state.start, end: state.end };
    }

    /* ========================================================
     * Multi-select genérico para alianzas y etiquetas
     * ======================================================== */

    function buildMultiSelect({ btnId, panelId, items, stateKey, allLabel }) {
        const btn   = document.getElementById(btnId);
        const panel = document.getElementById(panelId);
        if (!btn || !panel) return null;

        panel.innerHTML = items.map(item => {
            const dotStyle = item.color ? `style="background:${escapeHtml(item.color)};"` : '';
            return `
                <label class="report-ms-item" role="option">
                    <input type="checkbox" value="${item.id}">
                    <span class="report-ms-dot" ${dotStyle}></span>
                    <span>${escapeHtml(item.name)}</span>
                </label>
            `;
        }).join('');

        function updateLabel() {
            const checked = Array.from(panel.querySelectorAll('input[type="checkbox"]:checked'));
            const labelEl = btn.querySelector('.report-ms-label');
            const oldBadge = btn.querySelector('.report-ms-active-badge');
            if (oldBadge) oldBadge.remove();

            if (checked.length === 0) {
                labelEl.textContent = allLabel;
                state[stateKey] = [];
            } else {
                labelEl.textContent = checked.length === 1
                    ? checked[0].closest('label').querySelector('span:last-child').textContent.trim()
                    : allLabel;
                if (checked.length > 1) {
                    const badge = document.createElement('span');
                    badge.className = 'report-ms-active-badge';
                    badge.textContent = checked.length;
                    btn.insertBefore(badge, btn.querySelector('.report-ms-chevron'));
                }
                state[stateKey] = checked.map(c => parseInt(c.value, 10));
            }
        }

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = !panel.hidden;
            closeAllPanels();
            if (!isOpen) {
                panel.hidden = false;
                btn.setAttribute('aria-expanded', 'true');
            }
        });

        panel.addEventListener('change', () => {
            updateLabel();
            updateClearBtn();
            generate();
        });

        updateLabel();

        return {
            clear() {
                panel.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = false; });
                updateLabel();
            },
        };
    }

    function closeAllPanels() {
        document.querySelectorAll('.report-ms-panel').forEach(p => {
            p.hidden = true;
            const ms = p.closest('.report-ms');
            if (ms) ms.querySelector('.report-ms-btn')?.setAttribute('aria-expanded', 'false');
        });
    }

    /* ========================================================
     * Cargar alianzas y etiquetas
     * ======================================================== */

    async function loadAlliances() {
        try {
            const result = await api('alliances_list');
            if (!result.success || !result.alliances.length) return;
            multiSelectInstances.alliances = buildMultiSelect({
                btnId:    'allianceMsBtn',
                panelId:  'allianceMsPanel',
                items:    result.alliances,
                stateKey: 'allianceIds',
                allLabel: t('reports.all_alliances', 'Todas las alianzas'),
            });
        } catch (_) { /* ignore */ }
    }

    async function loadTags() {
        try {
            const result = await api('tags_list');
            if (!result.success || !result.tags.length) return;
            multiSelectInstances.tags = buildMultiSelect({
                btnId:    'tagMsBtn',
                panelId:  'tagMsPanel',
                items:    result.tags,
                stateKey: 'tagIds',
                allLabel: t('reports.all_tags', 'Todas las etiquetas'),
            });
        } catch (_) { /* ignore */ }
    }

    function updateClearBtn() {
        const btn = document.getElementById('reportClearBtn');
        if (!btn) return;
        const hasFilters = state.allianceIds.length > 0 || state.tagIds.length > 0;
        btn.classList.toggle('d-none', !hasFilters);
    }

    function clearFilters() {
        multiSelectInstances.alliances?.clear();
        multiSelectInstances.tags?.clear();
        updateClearBtn();
        generate();
    }

    /* ========================================================
     * Cargar usuarios (solo admin)
     * ======================================================== */

    async function loadUsers() {
        if (!cfg.isAdmin) return;
        const sel = document.getElementById('reportUser');
        if (!sel) return;
        try {
            const result = await api('users_list');
            if (!result.success) return;
            sel.innerHTML = result.users.map(u =>
                `<option value="${u.id}">${escapeHtml(u.name || u.username)}</option>`
            ).join('');
            // Marcar el usuario logueado si esta
            const mine = result.users.find(u => u.username === cfg.currentUser.username);
            if (mine) sel.value = mine.id;
            state.userId = parseInt(sel.value, 10) || null;
            sel.addEventListener('change', () => {
                state.userId = parseInt(sel.value, 10) || null;
                generate();
            });
        } catch (err) { /* ignore */ }
    }

    /* ========================================================
     * Generar reporte
     * ======================================================== */

    async function generate() {
        const range = computeRange();
        if (!range.start || !range.end) return; // custom sin fechas

        const view    = document.getElementById('reportView');
        const loading = document.getElementById('reportLoading');
        const content = document.getElementById('reportContent');
        if (loading) loading.classList.remove('d-none');
        if (view) view.setAttribute('aria-busy', 'true');

        try {
            const result = await api('monthly', {
                start:        range.start,
                end:          range.end,
                user_id:      state.userId || '',
                alliance_ids: state.allianceIds.join(','),
                tag_ids:      state.tagIds.join(','),
                include_tasks: state.type === 'detailed' ? 1 : 0,
                include_tags:  state.type === 'detailed' ? 1 : 0,
            });
            if (result.success) {
                lastReport = result;
                renderReport(result);
                document.getElementById('reportExportGroup').classList.remove('d-none');
            } else {
                if (content) content.innerHTML = `<div class="empty-state p-300" role="alert"><div class="empty-state-icon"><i class="bi bi-exclamation-triangle" aria-hidden="true"></i></div><h3 class="empty-state-title">${escapeHtml(result.message || t('reports.err_generate', 'No se pudo generar el reporte.'))}</h3></div>`;
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            if (loading) loading.classList.add('d-none');
            if (view) view.setAttribute('aria-busy', 'false');
        }
    }

    /* ========================================================
     * Render: header (2 cols: user+fecha | total) + grafico + tabla
     * ======================================================== */

    function renderReport(data) {
        const container = document.getElementById('reportContent');
        if (!container) return;

        const user = data.user || {};
        const period = data.period || {};
        const typeLabel = state.type === 'detailed'
            ? t('reports.type_detailed', 'Detallado')
            : t('reports.type_summary', 'Resumido');

        // Chips de filtros activos
        let filterChips = '';
        if (data.alliance_filter && data.alliance_filter.length) {
            filterChips += data.alliance_filter.map(a => {
                const cs = a.color ? `style="--alliance-color:${escapeHtml(a.color)};"` : '';
                return `<span class="report-alliance-chip ${a.color ? 'has-alliance-color' : ''}" ${cs}><span class="report-alliance-dot"></span>${escapeHtml(a.name)}</span>`;
            }).join(' ');
        }
        if (data.tag_filter && data.tag_filter.length) {
            filterChips += data.tag_filter.map(tg => {
                const cs = tg.color ? `style="--tag-color:${escapeHtml(tg.color)};"` : '';
                return `<span class="report-tag-chip" ${cs}><span class="report-tag-dot"></span>${escapeHtml(tg.name)}</span>`;
            }).join(' ');
        }

        // Header de pantalla: 2 columnas (user+fecha izq | total der)
        const header = `
            <header class="report-header">
                <div class="report-header-left">
                    <h2 class="report-user-name">${escapeHtml(user.name || user.username || '')}</h2>
                    <div class="report-period">${escapeHtml(period.label || '')}${filterChips ? ' &mdash; ' + filterChips : ''}</div>
                </div>
                <div class="report-header-right">
                    <div class="report-total-label">${escapeHtml(t('reports.meta_total', 'Tiempo total'))}</div>
                    <div class="report-total-time" aria-label="${escapeHtml(formatDuration(data.total_seconds))}">${escapeHtml(formatDuration(data.total_seconds))}</div>
                    <div class="report-total-tasks">${data.task_count || 0} ${escapeHtml(t('reports.meta_tasks', 'tareas'))}</div>
                </div>
            </header>
        `;

        // Cabecera de impresion: izq logo+app | der tipo de informe + nombre usuario
        const printHeader = `
            <div class="print-doc-header" aria-hidden="true">
                <div class="print-doc-branding">
                    <img src="assets/img/favicon.svg" class="print-doc-logo" alt="Nexus">
                    <span class="print-doc-app-name">Nexus</span>
                </div>
                <div class="print-doc-meta">
                    <div class="print-doc-report-type">Informe ${escapeHtml(typeLabel)} de actividades</div>
                    <div class="print-doc-username">${escapeHtml(user.name || user.username || '')}</div>
                </div>
            </div>
        `;

        // Fecha de generacion para el footer
        let genDateStr = '';
        if (data.generated_at) {
            try {
                const d = new Date(data.generated_at.replace(' ', 'T'));
                genDateStr = d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' })
                    + ' ' + d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });
            } catch (_) { genDateStr = data.generated_at; }
        }

        // Footer de pagina: fijo al fondo en cada pagina impresa, invisible en pantalla
        const printFooter = `
            <div class="print-page-footer" aria-hidden="true">
                <span class="print-footer-brand">Nexus</span>
                <span class="print-footer-generated">${genDateStr ? 'Generado: ' + escapeHtml(genDateStr) : ''}</span>
                <span class="print-footer-page"></span>
            </div>
        `;

        const allianceSection = renderAllianceSection(data);
        const tasksSection = data.tasks_by_alliance ? renderTasksSection(data.tasks_by_alliance) : '';
        const tagsSection  = data.by_tag ? renderTagsSection(data.by_tag) : '';

        container.innerHTML = printHeader + header + allianceSection + tasksSection + tagsSection + printFooter;
        const multipleAlliances = (data.by_alliance || []).length > 1;
        setTimeout(() => {
            if (multipleAlliances) {
                drawChart(data.by_alliance);
            } else if (chartInstance) {
                chartInstance.destroy();
                chartInstance = null;
            }
            renderBarChart(data);
        }, 50);
    }

    function renderAllianceSection(data) {
        const alliances = data.by_alliance || [];
        const showChart = alliances.length > 1;

        const rows = alliances.map(a => {
            const pct = data.total_seconds ? Math.round((a.total_seconds / data.total_seconds) * 100) : 0;
            const colorStyle = a.color ? `style="--alliance-color: ${escapeHtml(a.color)};"` : '';
            const colorCls   = a.color ? 'has-alliance-color' : '';
            const pctCell    = showChart ? `<td class="text-right text-mono">${pct}%</td>` : '';
            return `
                <tr>
                    <td>
                        <span class="report-alliance-chip ${colorCls}" ${colorStyle}>
                            <span class="report-alliance-dot"></span>
                            ${escapeHtml(a.name)}
                        </span>
                    </td>
                    <td class="text-right">${a.task_count}</td>
                    <td class="text-right text-mono">${escapeHtml(formatDuration(a.total_seconds))}</td>
                    ${pctCell}
                </tr>
            `;
        }).join('');

        const pctHeader = showChart ? `<th scope="col" class="text-right">%</th>` : '';

        // Leyenda para impresion (solo cuando hay grafico)
        const legendItems = showChart ? alliances.map(a => {
            const dotStyle = a.color ? `style="background:${escapeHtml(a.color)};"` : '';
            return `
                <div class="print-legend-item">
                    <span class="print-legend-dot" ${dotStyle}></span>
                    <span class="print-legend-name">${escapeHtml(a.name)}</span>
                </div>
            `;
        }).join('') : '';

        const period = data.period || {};

        const chartBlock = showChart ? `
            <div class="report-chart-row">
                <div class="report-chart-col">
                    <div class="report-chart-wrap">
                        <canvas id="reportChart" aria-label="${escapeHtml(t('reports.chart_label', 'Gráfico de distribución por alianza'))}"></canvas>
                    </div>
                    <div class="report-print-legend" aria-hidden="true">${legendItems}</div>
                </div>
                <div class="print-chart-info" aria-hidden="true">
                    <div class="print-chart-period">${escapeHtml(period.label || '')}</div>
                    <div class="print-chart-total">${escapeHtml(formatDuration(data.total_seconds))}</div>
                    <div class="print-chart-tasks">${data.task_count || 0} ${escapeHtml(t('reports.meta_tasks', 'tareas'))}</div>
                </div>
            </div>
        ` : '';

        return `
            <section class="report-section">
                ${chartBlock}
                <div class="report-alliance-table">
                    <table class="table report-table">
                        <caption class="sr-only">${escapeHtml(t('reports.section_alliances', 'Distribución por alianza'))}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="text-left">${escapeHtml(t('reports.col_alliance', 'Alianza'))}</th>
                                <th scope="col" class="text-right">${escapeHtml(t('reports.col_tasks', 'Tareas'))}</th>
                                <th scope="col" class="text-right">${escapeHtml(t('reports.col_time', 'Tiempo'))}</th>
                                ${pctHeader}
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </div>
            </section>
        `;
    }

    function renderTasksSection(tasks) {
        if (!tasks.length) {
            return `<section class="report-section"><h3 class="report-section-title"><i class="bi bi-list-task" aria-hidden="true"></i> ${escapeHtml(t('reports.section_tasks', 'Tareas por alianza'))}</h3><p class="text-subtle">${escapeHtml(t('reports.no_tasks', 'Sin tareas en el periodo.'))}</p></section>`;
        }
        // Agrupar tareas por alianza y calcular totales por grupo
        const grouped = {};
        tasks.forEach(tk => {
            const key = tk.alliance_name || '(Sin alianza)';
            if (!grouped[key]) grouped[key] = { tasks: [], total_seconds: 0 };
            grouped[key].tasks.push(tk);
            grouped[key].total_seconds += (parseInt(tk.total_seconds, 10) || 0);
        });

        const blocks = Object.keys(grouped).map(allianceName => {
            const g = grouped[allianceName];
            const rowsHtml = g.tasks.map(task => `
                <tr>
                    <td>${escapeHtml(task.title)}</td>
                    <td class="text-right">${task.sessions_count}</td>
                    <td class="text-right text-mono">${escapeHtml(formatDuration(task.total_seconds))}</td>
                </tr>
            `).join('');
            return `
                <article class="report-group-card">
                    <header class="report-group-header">
                        <span class="report-group-chip">${escapeHtml(allianceName)}</span>
                        <div class="report-group-meta">
                            <span class="report-group-meta-item"><strong>${g.tasks.length}</strong> ${escapeHtml(t('reports.col_tasks', 'Tareas').toLowerCase())}</span>
                            <span class="report-group-meta-sep" aria-hidden="true">·</span>
                            <span class="report-group-meta-item text-mono"><strong>${escapeHtml(formatDuration(g.total_seconds))}</strong></span>
                        </div>
                    </header>
                    <table class="table report-table report-group-table">
                        <caption class="sr-only">${escapeHtml(t('reports.section_tasks', 'Tareas por alianza'))}: ${escapeHtml(allianceName)}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="text-left">${escapeHtml(t('reports.col_task', 'Tarea'))}</th>
                                <th scope="col" class="text-right">${escapeHtml(t('reports.col_sessions', 'Sesiones'))}</th>
                                <th scope="col" class="text-right">${escapeHtml(t('reports.col_time', 'Tiempo'))}</th>
                            </tr>
                        </thead>
                        <tbody>${rowsHtml}</tbody>
                    </table>
                </article>
            `;
        }).join('');
        return `
            <section class="report-section">
                <h3 class="report-section-title"><i class="bi bi-list-task" aria-hidden="true"></i> ${escapeHtml(t('reports.section_tasks', 'Tareas por alianza'))}</h3>
                <div class="report-group-stack">${blocks}</div>
            </section>
        `;
    }

    function renderTagsSection(tags) {
        if (!tags.length) {
            return `<section class="report-section"><h3 class="report-section-title"><i class="bi bi-tags" aria-hidden="true"></i> ${escapeHtml(t('reports.section_tags', 'Total por etiqueta'))}</h3><p class="text-subtle">${escapeHtml(t('reports.no_tags', 'Sin etiquetas en el periodo.'))}</p></section>`;
        }
        const totalTasks = tags.reduce((s, tg) => s + (parseInt(tg.task_count, 10) || 0), 0);
        const totalSecs  = tags.reduce((s, tg) => s + (parseInt(tg.total_seconds, 10) || 0), 0);

        const rows = tags.map(tg => `
            <tr>
                <td>
                    <span class="report-tag-chip" ${tg.color ? `style="--tag-color: ${escapeHtml(tg.color)};"` : ''}>
                        <span class="report-tag-dot"></span>
                        ${escapeHtml(tg.name)}
                    </span>
                </td>
                <td class="text-right">${tg.task_count}</td>
                <td class="text-right text-mono">${escapeHtml(formatDuration(tg.total_seconds))}</td>
            </tr>
        `).join('');
        return `
            <section class="report-section">
                <h3 class="report-section-title"><i class="bi bi-tags" aria-hidden="true"></i> ${escapeHtml(t('reports.section_tags', 'Total por etiqueta'))}</h3>
                <article class="report-group-card">
                    <header class="report-group-header">
                        <span class="report-group-chip">${escapeHtml(t('reports.section_tags', 'Total por etiqueta'))}</span>
                        <div class="report-group-meta">
                            <span class="report-group-meta-item"><strong>${tags.length}</strong> ${escapeHtml(t('reports.col_tag', 'Etiqueta').toLowerCase())}${tags.length === 1 ? '' : 's'}</span>
                            <span class="report-group-meta-sep" aria-hidden="true">·</span>
                            <span class="report-group-meta-item text-mono"><strong>${escapeHtml(formatDuration(totalSecs))}</strong></span>
                        </div>
                    </header>
                    <table class="table report-table report-group-table">
                        <caption class="sr-only">${escapeHtml(t('reports.section_tags', 'Total por etiqueta'))}</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="text-left">${escapeHtml(t('reports.col_tag', 'Etiqueta'))}</th>
                                <th scope="col" class="text-right">${escapeHtml(t('reports.col_tasks', 'Tareas'))}</th>
                                <th scope="col" class="text-right">${escapeHtml(t('reports.col_time', 'Tiempo'))}</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
                </article>
            </section>
        `;
    }

    /* ========================================================
     * Chart.js doughnut con % dentro de cada segmento + leyenda bottom
     * ======================================================== */

    const pctLabelsPlugin = {
        id: 'pctLabels',
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const ds = chart.data.datasets[0];
            const total = ds.data.reduce((a, b) => a + b, 0);
            if (!total) return;
            ctx.save();
            ctx.font = 'bold 13px system-ui, -apple-system, Segoe UI, sans-serif';
            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            chart.getDatasetMeta(0).data.forEach((arc, i) => {
                const pct = Math.round((ds.data[i] / total) * 100);
                if (pct < 5) return;
                const { x, y } = arc.tooltipPosition();
                ctx.fillText(pct + '%', x, y);
            });
            ctx.restore();
        }
    };

    function drawChart(byAlliance) {
        const el = document.getElementById('reportChart');
        if (!el || typeof Chart === 'undefined') return;
        if (chartInstance) chartInstance.destroy();

        const labels = byAlliance.map(a => a.name);
        const values = byAlliance.map(a => a.total_seconds);
        const colors = byAlliance.map(a => a.color || '#8795a8');

        chartInstance = new Chart(el, {
            type: 'doughnut',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#ffffff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: 8 },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 14,
                            font: { size: 12 },
                            padding: 12,
                        },
                    },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const total = ctx.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total ? Math.round((ctx.parsed / total) * 100) : 0;
                                return `${ctx.label}: ${formatDuration(ctx.parsed)} (${pct}%)`;
                            },
                        },
                    },
                },
            },
            plugins: [pctLabelsPlugin],
        });
    }

    /* ========================================================
     * Gráfico de barras temporal
     * ======================================================== */

    function getMondayStr(d) {
        const day = new Date(d);
        const dow = day.getDay() || 7;
        day.setDate(day.getDate() - (dow - 1));
        return localDateStr(day);
    }

    function renderBarChart(data) {
        const section = document.getElementById('reportBarSection');
        const el      = document.getElementById('reportBarChart');
        if (!section || !el || typeof Chart === 'undefined') return;

        const byDay = data.by_day || [];
        if (!byDay.length) {
            section.classList.add('d-none');
            if (barChartInstance) { barChartInstance.destroy(); barChartInstance = null; }
            return;
        }

        const dayMap = {};
        byDay.forEach(d => { dayMap[d.date] = d.seconds; });

        const start    = new Date(data.period.start + 'T00:00:00');
        const end      = new Date(data.period.end   + 'T00:00:00');
        const diffDays = Math.round((end - start) / 86400000) + 1;

        const labels = [];
        const values = [];

        if (diffDays <= 31) {
            // Un punto por día
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                labels.push(d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short' }));
                values.push(((dayMap[localDateStr(d)] || 0) / 3600));
            }
        } else if (diffDays <= 90) {
            // Agrupado por semana (lunes como inicio)
            const weekMap = {};
            const weekOrder = [];
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                const key = getMondayStr(d);
                if (!weekMap[key]) { weekMap[key] = 0; weekOrder.push(key); }
                weekMap[key] += (dayMap[localDateStr(d)] || 0);
            }
            weekOrder.forEach(monStr => {
                const mon = new Date(monStr + 'T00:00:00');
                labels.push(mon.toLocaleDateString('es-ES', { day: '2-digit', month: 'short' }));
                values.push(weekMap[monStr] / 3600);
            });
        } else {
            // Agrupado por mes
            const monthMap = {};
            const monthOrder = [];
            const monthNames = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                const key = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
                if (!monthMap[key]) { monthMap[key] = 0; monthOrder.push(key); }
                monthMap[key] += (dayMap[localDateStr(d)] || 0);
            }
            monthOrder.forEach(key => {
                const [y, m] = key.split('-');
                labels.push(monthNames[parseInt(m, 10) - 1] + ' ' + y);
                values.push(monthMap[key] / 3600);
            });
        }

        const brand = getComputedStyle(document.documentElement).getPropertyValue('--app-brand').trim() || '#585d8a';

        section.classList.remove('d-none');
        if (barChartInstance) barChartInstance.destroy();

        barChartInstance = new Chart(el, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    data: values,
                    backgroundColor: brand + 'b3',
                    borderColor: brand,
                    borderWidth: 1,
                    borderRadius: 3,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => formatDuration(Math.round(ctx.raw * 3600)),
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { callback: (v) => v % 1 === 0 ? v + 'h' : '' },
                        grid: { color: 'rgba(9,30,66,0.06)' },
                    },
                    x: { grid: { display: false } },
                },
            },
        });
    }

    /* ========================================================
     * Exports
     * ======================================================== */

    function filename(ext) {
        const p = lastReport.period;
        const u = lastReport.user.username || 'user';
        return `reporte-${u}-${p.start}-a-${p.end}.${ext}`;
    }

    function downloadBlob(content, mime, name, withBom) {
        const parts = withBom ? ['﻿', content] : [content];
        const blob = new Blob(parts, { type: mime + ';charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = name;
        document.body.appendChild(a);
        a.click();
        setTimeout(() => { document.body.removeChild(a); URL.revokeObjectURL(a.href); }, 100);
    }

    function exportCSV() {
        if (!lastReport) return;
        const L = [];
        const esc = (v) => {
            if (v === null || v === undefined) return '';
            const s = String(v);
            return /[",\n;]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
        };
        const line = (...cols) => L.push(cols.map(esc).join(','));

        line('Reporte de actividades');
        line('Usuario', lastReport.user.name || lastReport.user.username);
        line('Periodo', lastReport.period.label);
        line('Tiempo total', formatDuration(lastReport.total_seconds));
        line('Tareas', lastReport.task_count);
        line('Generado', lastReport.generated_at);
        line('');

        line('Alianza', 'Tareas', 'Tiempo', 'Porcentaje');
        (lastReport.by_alliance || []).forEach(a => {
            const pct = lastReport.total_seconds ? Math.round((a.total_seconds / lastReport.total_seconds) * 100) : 0;
            line(a.name, a.task_count, formatDuration(a.total_seconds), pct + '%');
        });
        line('');

        if (lastReport.tasks_by_alliance) {
            line('Tareas por alianza');
            line('Alianza', 'Tarea', 'Sesiones', 'Tiempo', 'Estado');
            lastReport.tasks_by_alliance.forEach(task => {
                line(task.alliance_name, task.title, task.sessions_count, formatDuration(task.total_seconds), statusLabel(task.status));
            });
            line('');
        }

        if (lastReport.by_tag) {
            line('Total por etiqueta');
            line('Etiqueta', 'Tareas', 'Tiempo');
            lastReport.by_tag.forEach(tg => line(tg.name, tg.task_count, formatDuration(tg.total_seconds)));
        }

        downloadBlob(L.join('\r\n'), 'text/csv', filename('csv'), true);
        Toast.success(t('reports.export_csv_done', 'Reporte CSV descargado.'));
    }

    async function exportXLS() {
        if (!lastReport) return;
        if (typeof ExcelJS === 'undefined') {
            Toast.error(t('reports.err_xlsx_lib', 'La librería de Excel no se cargó. Revisa tu conexión.'));
            return;
        }
        const tt = lastReport;
        const pct = (s) => tt.total_seconds ? Math.round((s / tt.total_seconds) * 100) : 0;

        // Paleta de estilos
        const BRAND = 'FF585D8A';   // color brand
        const BRAND_LIGHT = 'FFEDEEF3'; // tinte brand para zebras y secciones
        const TEXT_DARK = 'FF172B4D';
        const TEXT_MUTED = 'FF6B778C';

        const border = { style: 'thin', color: { argb: 'FFDFE1E6' } };
        const bordersAll = { top: border, left: border, bottom: border, right: border };

        const wb = new ExcelJS.Workbook();
        wb.creator = 'Nexus';
        wb.created = new Date();

        const ws = wb.addWorksheet('Reporte', {
            pageSetup: { paperSize: 9, orientation: 'portrait', fitToPage: true, fitToWidth: 1 },
        });

        // Anchos de columna
        ws.columns = [
            { width: 36 }, { width: 40 }, { width: 14 }, { width: 14 }, { width: 16 },
        ];

        // ---- Titulo principal ----
        let r = ws.addRow(['Reporte de actividades']);
        ws.mergeCells(`A${r.number}:E${r.number}`);
        r.height = 28;
        r.getCell(1).font = { size: 18, bold: true, color: { argb: BRAND } };
        r.getCell(1).alignment = { horizontal: 'center', vertical: 'middle' };

        ws.addRow([]);

        // ---- Metadatos ----
        const metas = [
            ['Usuario',      tt.user.name || tt.user.username || ''],
            ['Periodo',      tt.period.label || ''],
            ['Tiempo total', formatDuration(tt.total_seconds)],
            ['Tareas',       tt.task_count || 0],
            ['Generado',     tt.generated_at || ''],
        ];
        metas.forEach(([label, value]) => {
            const row = ws.addRow([label, value]);
            row.getCell(1).font = { bold: true, color: { argb: TEXT_MUTED } };
            row.getCell(2).font = { color: { argb: TEXT_DARK } };
        });

        // Tiempo total en grande
        const totalRow = ws.getRow(metas.findIndex(([l]) => l === 'Tiempo total') + 3);
        totalRow.getCell(2).font = { bold: true, size: 14, color: { argb: BRAND } };

        ws.addRow([]);

        // ---- Seccion: Distribucion por alianza ----
        addSectionTitle(ws, 'Distribución por alianza', BRAND, BRAND_LIGHT);
        addTableHeader(ws, ['Alianza', 'Tareas', 'Tiempo', '%'], BRAND);

        let zebra = false;
        (tt.by_alliance || []).forEach(a => {
            const row = ws.addRow([a.name, a.task_count, formatDuration(a.total_seconds), pct(a.total_seconds) + '%']);
            row.eachCell((cell, col) => {
                cell.border = bordersAll;
                if (zebra) cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFAFAFB' } };
                if (col === 2 || col === 4) cell.alignment = { horizontal: 'right' };
                if (col === 3) { cell.alignment = { horizontal: 'right' }; cell.font = { name: 'Consolas' }; }
            });
            zebra = !zebra;
        });
        ws.addRow([]);

        // ---- Seccion: Tareas por alianza (detallado) ----
        if (tt.tasks_by_alliance) {
            addSectionTitle(ws, 'Tareas por alianza', BRAND, BRAND_LIGHT);
            addTableHeader(ws, ['Alianza', 'Tarea', 'Sesiones', 'Tiempo', 'Estado'], BRAND);
            zebra = false;
            tt.tasks_by_alliance.forEach(task => {
                const row = ws.addRow([task.alliance_name, task.title, task.sessions_count, formatDuration(task.total_seconds), statusLabel(task.status)]);
                row.eachCell((cell, col) => {
                    cell.border = bordersAll;
                    if (zebra) cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFAFAFB' } };
                    if (col === 3) cell.alignment = { horizontal: 'right' };
                    if (col === 4) { cell.alignment = { horizontal: 'right' }; cell.font = { name: 'Consolas' }; }
                });
                zebra = !zebra;
            });
            ws.addRow([]);
        }

        // ---- Seccion: Total por etiqueta (detallado) ----
        if (tt.by_tag) {
            addSectionTitle(ws, 'Total por etiqueta', BRAND, BRAND_LIGHT);
            addTableHeader(ws, ['Etiqueta', 'Tareas', 'Tiempo'], BRAND);
            zebra = false;
            tt.by_tag.forEach(tg => {
                const row = ws.addRow([tg.name, tg.task_count, formatDuration(tg.total_seconds)]);
                row.eachCell((cell, col) => {
                    cell.border = bordersAll;
                    if (zebra) cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFFAFAFB' } };
                    if (col === 2) cell.alignment = { horizontal: 'right' };
                    if (col === 3) { cell.alignment = { horizontal: 'right' }; cell.font = { name: 'Consolas' }; }
                });
                zebra = !zebra;
            });
        }

        // Generar y descargar
        const buf = await wb.xlsx.writeBuffer();
        const blob = new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename('xlsx');
        document.body.appendChild(a);
        a.click();
        setTimeout(() => { document.body.removeChild(a); URL.revokeObjectURL(a.href); }, 100);
        Toast.success(t('reports.export_xlsx_done', 'Reporte Excel descargado.'));
    }

    function addSectionTitle(ws, title, brandColor, lightColor) {
        const row = ws.addRow([title]);
        row.height = 22;
        ws.mergeCells(`A${row.number}:E${row.number}`);
        const cell = row.getCell(1);
        cell.font = { bold: true, size: 12, color: { argb: brandColor } };
        cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: lightColor } };
        cell.alignment = { horizontal: 'left', vertical: 'middle', indent: 1 };
    }

    function addTableHeader(ws, labels, brandColor) {
        const row = ws.addRow(labels);
        row.height = 20;
        row.eachCell((cell, col) => {
            cell.font = { bold: true, color: { argb: 'FFFFFFFF' } };
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: brandColor } };
            cell.alignment = { horizontal: col === 1 ? 'left' : 'right', vertical: 'middle', indent: col === 1 ? 1 : 0 };
            cell.border = {
                top:    { style: 'thin', color: { argb: 'FFDFE1E6' } },
                left:   { style: 'thin', color: { argb: 'FFDFE1E6' } },
                bottom: { style: 'thin', color: { argb: 'FFDFE1E6' } },
                right:  { style: 'thin', color: { argb: 'FFDFE1E6' } },
            };
        });
    }

    function exportPDF() {
        if (!lastReport) return;
        const originalTitle = document.title;
        const u = lastReport.user.username || lastReport.user.name || 'user';
        const p = lastReport.period;
        document.title = `Reporte ${u} ${p.start} a ${p.end}`;

        // Redimensionar el grafico a las proporciones de impresion (8cm ≈ 302px a 96dpi)
        // y ocultar la leyenda Chart.js. Quitar responsive para que el resize persista.
        if (chartInstance) {
            chartInstance.options.responsive = false;
            chartInstance.options.plugins.legend.display = false;
            chartInstance.resize(302, 302);
        }

        // Inyectar la fecha de generacion en @page @bottom-center via estilo dinamico
        let genDateStr = '';
        if (lastReport.generated_at) {
            try {
                const d = new Date(lastReport.generated_at.replace(' ', 'T'));
                genDateStr = d.toLocaleDateString('es-ES', { day: '2-digit', month: '2-digit', year: 'numeric' });
            } catch (_) {}
        }
        const printDateStyle = document.createElement('style');
        printDateStyle.id = '__nexus_print_date__';
        if (genDateStr) {
            printDateStyle.textContent = `@page { @bottom-center { content: "Generado: ${genDateStr}"; font-size: 7pt; color: #888; font-family: system-ui,-apple-system,sans-serif; vertical-align: top; } }`;
        }
        document.head.appendChild(printDateStyle);

        document.body.classList.add('is-printing-report');

        // requestAnimationFrame garantiza que el resize se haya pintado antes de imprimir
        requestAnimationFrame(() => {
            window.print();
            setTimeout(() => {
                document.body.classList.remove('is-printing-report');
                document.title = originalTitle;
                printDateStyle.remove();
                if (chartInstance) {
                    chartInstance.options.responsive = true;
                    chartInstance.options.plugins.legend.display = true;
                    chartInstance.resize();
                }
            }, 500);
        });
    }

    /* ========================================================
     * Litepicker flotante (popover sobre el boton "Personalizado")
     * con presets no redundantes con los botones de rango superiores
     * ======================================================== */

    let customPicker = null;

    // Nota: NO se incluyen "Últimos 7 días" ni "Mes anterior" porque ya existen
    // como botones de rango (weekly / monthly). Tampoco "Este mes" (redundante
    // con el caso de uso del reporte mensual) ni "Últimas 2 semanas" (~15 días).
    function buildPresets() {
        const today = new Date();
        const mk = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate());
        const daysBack = (n) => { const d = new Date(today); d.setDate(today.getDate() - n); return mk(d); };

        // La semana pasada: lunes a domingo naturales anteriores
        const dow = today.getDay();            // 0 dom ... 6 sab
        const diffToLastSunday = dow === 0 ? 7 : dow;
        const lastSunday = daysBack(diffToLastSunday);
        const lastMonday = new Date(lastSunday); lastMonday.setDate(lastSunday.getDate() - 6);

        const thisMonthStart = new Date(today.getFullYear(), today.getMonth(), 1);
        const thisYearStart  = new Date(today.getFullYear(), 0, 1);

        return {
            [t('reports.preset_thismonth', 'Este mes')]:        [thisMonthStart, mk(today)],
            [t('reports.preset_lastweek',  'La semana pasada')]: [lastMonday,   lastSunday],
            [t('reports.preset_last15',    'Últimos 15 días')]:  [daysBack(14), mk(today)],
            [t('reports.preset_last30',    'Últimos 30 días')]:  [daysBack(29), mk(today)],
            [t('reports.preset_thisyear',  'Este año')]:         [thisYearStart, mk(today)],
        };
    }

    function ensureCustomPicker() {
        if (customPicker) return;
        const trigger = document.querySelector('[data-range="custom"]');
        if (!trigger || typeof Litepicker === 'undefined') return;

        customPicker = new Litepicker({
            element: trigger,
            singleMode: false,
            numberOfMonths: 2,
            numberOfColumns: 2,
            lang: 'es-ES',
            format: 'YYYY-MM-DD',
            firstDay: 1,
            autoApply: true,
            plugins: ['ranges'],
            ranges: {
                position: 'left',
                customRanges: buildPresets(),
            },
            setup: (p) => {
                p.on('selected', (sd, ed) => {
                    state.start = sd.format('YYYY-MM-DD');
                    state.end   = ed.format('YYYY-MM-DD');
                    state.range = 'custom';
                    const customBtn = document.querySelector('[data-range="custom"]');
                    if (customBtn) activateGroupItem(customBtn);
                    generate();
                });
            },
        });
    }

    /* ========================================================
     * Toggle groups (radiogroup con roving tabindex + flechas)
     * ======================================================== */

    // Mueve el estado active/aria-checked y tabindex a `btn` dentro de su btn-group.
    function activateGroupItem(btn) {
        const group = btn.parentElement;
        group.querySelectorAll('.btn-group-item').forEach(b => {
            const isActive = b === btn;
            b.classList.toggle('active', isActive);
            b.setAttribute('aria-checked', isActive ? 'true' : 'false');
            b.setAttribute('tabindex', isActive ? '0' : '-1');
        });
    }

    // Keyboard navigation: flechas mueven el foco y activan el siguiente item.
    function setupRadioKeyboardNav(group) {
        group.addEventListener('keydown', (e) => {
            const items = Array.from(group.querySelectorAll('.btn-group-item'));
            const current = document.activeElement;
            const idx = items.indexOf(current);
            if (idx < 0) return;

            let next = null;
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                next = items[(idx + 1) % items.length];
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                next = items[(idx - 1 + items.length) % items.length];
            } else if (e.key === 'Home') {
                next = items[0];
            } else if (e.key === 'End') {
                next = items[items.length - 1];
            }
            if (next) {
                e.preventDefault();
                next.focus();
                next.click();
            }
        });
    }

    /* ========================================================
     * Bindings
     * ======================================================== */

    document.addEventListener('DOMContentLoaded', async () => {
        // Tipo de reporte
        document.querySelectorAll('[data-report-type]').forEach(btn => {
            btn.addEventListener('click', () => {
                activateGroupItem(btn);
                state.type = btn.dataset.reportType;
                generate();
            });
        });

        // Keyboard nav en ambos radiogroups
        document.querySelectorAll('.btn-group[role="radiogroup"]').forEach(setupRadioKeyboardNav);

        // Inicializar Litepicker una vez (lo bindea al boton "Personalizado")
        ensureCustomPicker();

        // Rango: semanal y mes anterior disparan generate; custom solo abre el
        // picker y NO cambia el estado hasta que el usuario seleccione un rango
        // (lo hace `onSelected` del Litepicker). Asi, si el usuario cierra el
        // picker sin elegir, seguimos operando con el rango previo.
        document.querySelectorAll('[data-range]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.dataset.range === 'custom') return; // Litepicker maneja el resto
                activateGroupItem(btn);
                state.range = btn.dataset.range;
                generate();
            });
        });

        // Exports
        document.querySelectorAll('[data-export]').forEach(btn => {
            btn.addEventListener('click', () => {
                const type = btn.dataset.export;
                if (type === 'csv') exportCSV();
                else if (type === 'xls') exportXLS();
                else if (type === 'pdf') exportPDF();
            });
        });

        // Cerrar paneles al click fuera
        document.addEventListener('click', closeAllPanels);

        // Limpiar filtros
        document.getElementById('reportClearBtn')?.addEventListener('click', clearFilters);

        // Cargar alianzas, etiquetas y usuarios (en paralelo)
        await Promise.all([loadAlliances(), loadTags(), loadUsers()]);

        // Auto-carga inicial: resumido del mes actual
        generate();
    });

})();
