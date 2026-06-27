/**
 * Nexus 2.0 — Error Log
 * Tabla filtrable de errores capturados en tiempo de ejecución.
 */

(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const initial = window.__ERRORLOG_INITIAL__ || { entries: [], total: 0, page: 1, pages: 0 };

    const state = {
        filters: { date_from: '', date_to: '', level: '' },
        page: 1,
        pages: 1,
        total: 0,
    };

    // ── Helpers ──────────────────────────────────────────────────────────────

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function formatTimestamp(ts) {
        if (!ts) return '';
        const [date, time] = ts.split(' ');
        return `<span class="activity-ts-date">${escapeHtml(date)}</span>`
             + `<span class="activity-ts-time">${escapeHtml((time || '').substring(0, 5))}</span>`;
    }

    function levelVariant(level) {
        switch (level) {
            case 'fatal':
            case 'exception':
            case 'error':
                return 'danger';
            case 'warning':
                return 'warning';
            default:
                return 'default';
        }
    }

    function levelLabel(level) {
        const labels = {
            fatal:     document.title, // fallback
            exception: 'Excepción',
            error:     'Error',
            warning:   'Advertencia',
        };
        // Use PHP-rendered labels if available via option elements
        const opt = document.querySelector(`#fErrLevel option[value="${level}"]`);
        return opt ? opt.textContent.trim() : (level || '');
    }

    function buildOriginCell(e) {
        if (!e.file) return '<span class="text-subtle">—</span>';
        const file = e.file.replace(/\\/g, '/').split('/').slice(-2).join('/');
        const line = e.line ? `:${e.line}` : '';
        return `<code class="activity-ip" title="${escapeHtml(e.file)}">${escapeHtml(file)}${escapeHtml(line)}</code>`;
    }

    function buildDetailRows(e) {
        const rows = [];
        if (e.url)   rows.push(`<span class="text-subtle">URL:</span> ${escapeHtml(e.url)}`);
        if (e.user)  rows.push(`<span class="text-subtle">Usuario:</span> ${escapeHtml(e.user)}`);
        if (e.ip)    rows.push(`<span class="text-subtle">IP:</span> ${escapeHtml(e.ip)}`);
        if (e.type)  rows.push(`<span class="text-subtle">Tipo:</span> ${escapeHtml(e.type)}`);
        return rows.join(' &nbsp;·&nbsp; ');
    }

    // ── Render ───────────────────────────────────────────────────────────────

    function renderRows(entries) {
        const tbody = document.getElementById('errorTableBody');
        if (!tbody) return;

        if (entries.length === 0) {
            tbody.innerHTML = '';
            return;
        }

        const html = entries.map((e, i) => {
            const variant = levelVariant(e.level);
            const label   = levelLabel(e.level);
            const detailId = `err-detail-${i}`;
            const hasExtra = e.url || e.user || e.ip || e.type || e.trace;

            return `
                <tr class="error-row" data-idx="${i}">
                    <td class="activity-col-date">${formatTimestamp(e.timestamp)}</td>
                    <td><span class="lozenge lozenge-${variant}">${escapeHtml(label)}</span></td>
                    <td>
                        <div class="error-message-cell">
                            <span class="activity-detail">${escapeHtml(e.message)}</span>
                            ${hasExtra ? `<button type="button" class="btn-link error-detail-toggle" aria-expanded="false" aria-controls="${detailId}">
                                <i class="bi bi-chevron-down" aria-hidden="true"></i>
                            </button>` : ''}
                        </div>
                        ${hasExtra ? `<div class="error-detail-panel d-none" id="${detailId}">
                            <div class="error-detail-meta text-subtle">${buildDetailRows(e)}</div>
                            ${e.trace ? `<pre class="error-trace">${escapeHtml(e.trace)}</pre>` : ''}
                        </div>` : ''}
                    </td>
                    <td class="d-none-mobile">${buildOriginCell(e)}</td>
                </tr>
            `;
        }).join('');

        tbody.innerHTML = html;

        // Expandir/colapsar detalles
        tbody.querySelectorAll('.error-detail-toggle').forEach(btn => {
            btn.addEventListener('click', () => {
                const panelId = btn.getAttribute('aria-controls');
                const panel   = document.getElementById(panelId);
                const open    = btn.getAttribute('aria-expanded') === 'true';
                btn.setAttribute('aria-expanded', !open);
                panel.classList.toggle('d-none', open);
                btn.querySelector('i').className = open ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
            });
        });
    }

    function hasActiveFilters() {
        return Object.values(state.filters).some(v => v !== '');
    }

    function renderResultsCount() {
        const countEl   = document.getElementById('errorResultsCount');
        const badge     = document.getElementById('errorResultsFiltered');
        if (!countEl) return;
        const total = state.total;
        countEl.textContent = total === 1 ? '1 registro' : `${total.toLocaleString()} registros`;
        if (badge) badge.classList.toggle('d-none', !hasActiveFilters());
    }

    function renderPagination() {
        const pag = document.getElementById('errorPagination');
        if (!pag) return;

        if (state.pages <= 1) { pag.innerHTML = ''; return; }

        const current = state.page;
        const total   = state.pages;
        const prevDis = current <= 1 ? 'disabled' : '';
        const nextDis = current >= total ? 'disabled' : '';

        const pageNums = [];
        if (total <= 7) {
            for (let i = 1; i <= total; i++) pageNums.push(i);
        } else {
            pageNums.push(1);
            if (current > 3) pageNums.push('...');
            const start = Math.max(2, current - 1);
            const end   = Math.min(total - 1, current + 1);
            for (let i = start; i <= end; i++) pageNums.push(i);
            if (current < total - 2) pageNums.push('...');
            pageNums.push(total);
        }

        const numBtns = pageNums.map(n => {
            if (n === '...') return `<span class="pagination-ellipsis" aria-hidden="true">…</span>`;
            const active = n === current ? 'pagination-btn-active' : '';
            return `<button type="button" class="pagination-btn ${active}" data-page="${n}"
                    ${n === current ? 'aria-current="page"' : ''}>${n}</button>`;
        }).join('');

        pag.innerHTML = `
            <div class="pagination-info text-sm text-subtle">
                Página ${current} de ${total} — ${state.total} registros
            </div>
            <div class="pagination-controls">
                <button type="button" class="pagination-btn" data-page="${current - 1}" ${prevDis}
                        aria-label="Anterior"><i class="bi bi-chevron-left" aria-hidden="true"></i></button>
                ${numBtns}
                <button type="button" class="pagination-btn" data-page="${current + 1}" ${nextDis}
                        aria-label="Siguiente"><i class="bi bi-chevron-right" aria-hidden="true"></i></button>
            </div>
        `;

        pag.querySelectorAll('.pagination-btn[data-page]:not([disabled])').forEach(btn => {
            btn.addEventListener('click', () => {
                const np = parseInt(btn.dataset.page, 10);
                if (np && np !== state.page && np > 0 && np <= state.pages) {
                    state.page = np;
                    fetchEntries();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });
    }

    // ── Fetch ────────────────────────────────────────────────────────────────

    async function fetchEntries() {
        const tbody     = document.getElementById('errorTableBody');
        const emptyEl   = document.getElementById('errorEmpty');
        const loadingEl = document.getElementById('errorLoading');
        const table     = document.getElementById('errorTable');
        if (!tbody) return;

        loadingEl.classList.remove('d-none');
        emptyEl.classList.add('d-none');
        table.classList.remove('d-none');

        try {
            const params = new URLSearchParams({
                action: 'fetch',
                page: state.page,
                ...state.filters,
            });
            const res  = await fetch('includes/error_log_actions.php?' + params.toString(), {
                headers: { 'X-CSRF-TOKEN': csrfToken },
            });
            const data = await res.json();
            loadingEl.classList.add('d-none');

            if (!data.success) {
                Toast.error(data.message || 'No se pudo cargar el registro.');
                return;
            }

            state.total = data.total || 0;
            state.pages = data.pages || 1;
            state.page  = data.page  || 1;

            renderRows(data.entries || []);
            renderPagination();
            renderResultsCount();

            if ((data.entries || []).length === 0) {
                table.classList.add('d-none');
                emptyEl.classList.remove('d-none');
            }
        } catch {
            loadingEl.classList.add('d-none');
            Toast.error('Error de red. Intenta de nuevo.');
        }
    }

    // ── Filtros ──────────────────────────────────────────────────────────────

    function applyFilters() {
        state.filters.date_from = document.getElementById('fErrDateFrom').value;
        state.filters.date_to   = document.getElementById('fErrDateTo').value;
        state.filters.level     = document.getElementById('fErrLevel').value;
        state.page = 1;
        fetchEntries();
    }

    ['fErrDateFrom', 'fErrDateTo', 'fErrLevel'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', applyFilters);
    });

    document.getElementById('btnClearErrFilters')?.addEventListener('click', () => {
        document.getElementById('fErrDateFrom').value = '';
        document.getElementById('fErrDateTo').value   = '';
        document.getElementById('fErrLevel').value    = '';
        state.filters = { date_from: '', date_to: '', level: '' };
        state.page = 1;
        fetchEntries();
    });

    // ── Limpiar log ──────────────────────────────────────────────────────────

    document.getElementById('btnClearErrorLog')?.addEventListener('click', async () => {
        const confirmed = await ConfirmModal.show({
            title:         'Limpiar registro de errores',
            message:       'Se eliminarán TODOS los registros de errores. Esta acción no se puede deshacer.',
            acceptText:    'Eliminar todo',
            acceptVariant: 'danger',
            icon:          'bi-trash',
            variant:       'danger',
        });
        if (!confirmed) return;

        try {
            const fd = new FormData();
            fd.append('action', 'clear');
            const res  = await fetch('includes/error_log_actions.php', {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body:    fd,
            });
            const data = await res.json();
            if (data.success) {
                Toast.success(data.message || 'Registro limpiado.');
                state.page = 1;
                fetchEntries();
            } else {
                Toast.error(data.message || 'No se pudo limpiar.');
            }
        } catch {
            Toast.error('Error de red. Intenta de nuevo.');
        }
    });

    // ── Init SSR ─────────────────────────────────────────────────────────────

    state.total = initial.total || 0;
    state.pages = initial.pages || 0;
    state.page  = initial.page  || 1;
    renderRows(initial.entries || []);
    renderPagination();
    renderResultsCount();

    if ((initial.entries || []).length === 0 && state.total === 0) {
        document.getElementById('errorTable')?.classList.add('d-none');
        document.getElementById('errorEmpty')?.classList.remove('d-none');
    }

})();
