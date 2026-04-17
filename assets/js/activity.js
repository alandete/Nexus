/**
 * Nexus 2.0 — Activity Log
 * Tabla filtrable con paginacion via AJAX
 */

(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const T = window.__T__ || {};
    const t = (key, fallback) => T[key] || fallback;

    const initial = window.__ACTIVITY_INITIAL__ || { entries: [], total: 0, page: 1, pages: 0 };
    const MODULES = window.__ACTIVITY_MODULES__ || {};
    const ACTIONS = window.__ACTIVITY_ACTIONS__ || {};

    const state = {
        filters: { date_from: '', date_to: '', user: '', module: '', action_filter: '' },
        page: 1,
        pages: 1,
        total: 0,
    };

    /** ========================================================
     * Helpers
     * ======================================================== */

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function formatTimestamp(ts) {
        if (!ts) return '';
        // Formato de entrada: "2026-04-17 15:30:00"
        const [date, time] = ts.split(' ');
        return `<span class="activity-ts-date">${escapeHtml(date)}</span><span class="activity-ts-time">${escapeHtml((time || '').substring(0, 5))}</span>`;
    }

    function actionLozengeVariant(action) {
        const map = {
            login:       'info',
            logout:      'default',
            create:      'success',
            update:      'warning',
            delete:      'danger',
            restore:     'info',
            process:     'discovery',
            clean:       'warning',
            clear:       'danger',
            diagnostics: 'info',
        };
        return map[action] || 'default';
    }

    async function fetchEntries() {
        const tbody = document.getElementById('activityTableBody');
        const emptyEl = document.getElementById('activityEmpty');
        const loadingEl = document.getElementById('activityLoading');
        const table = document.getElementById('activityTable');

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

            const res = await fetch('includes/reports_actions.php?' + params.toString(), {
                headers: { 'X-CSRF-TOKEN': csrfToken },
            });
            const data = await res.json();

            loadingEl.classList.add('d-none');

            if (!data.success) {
                Toast.error(data.message || t('activity.err_generic', 'No se pudo cargar.'));
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
        } catch (err) {
            loadingEl.classList.add('d-none');
            Toast.error(t('common.err_network', 'Error de red.'));
        }
    }

    function renderRows(entries) {
        const tbody = document.getElementById('activityTableBody');
        if (!tbody) return;

        if (entries.length === 0) {
            tbody.innerHTML = '';
            return;
        }

        const html = entries.map(e => {
            const moduleLabel = MODULES[e.module] || e.module;
            const actionLabel = ACTIONS[e.action] || e.action;
            const variant = actionLozengeVariant(e.action);
            const userName = e.user_name || e.user || '-';
            const userHandle = e.user ? '@' + escapeHtml(e.user) : '';

            return `
                <tr>
                    <td class="activity-col-date">${formatTimestamp(e.timestamp)}</td>
                    <td>
                        <div class="activity-user-cell">
                            <span class="activity-user-name">${escapeHtml(userName)}</span>
                            ${userHandle ? `<span class="activity-user-handle">${userHandle}</span>` : ''}
                        </div>
                    </td>
                    <td class="d-none-mobile">
                        <span class="text-subtle">${escapeHtml(moduleLabel)}</span>
                    </td>
                    <td>
                        <span class="lozenge lozenge-${variant}">${escapeHtml(actionLabel)}</span>
                    </td>
                    <td class="d-none-mobile">
                        <span class="activity-detail text-subtle">${escapeHtml(e.detail || '')}</span>
                    </td>
                    <td class="d-none-mobile activity-col-ip">
                        <code class="activity-ip">${escapeHtml(e.ip || '')}</code>
                    </td>
                </tr>
            `;
        }).join('');

        tbody.innerHTML = html;
    }

    function hasActiveFilters() {
        return Object.values(state.filters).some(v => v !== '');
    }

    function renderResultsCount() {
        const countEl = document.getElementById('activityResultsCount');
        const filteredBadge = document.getElementById('activityResultsFiltered');
        if (!countEl) return;

        const total = state.total;
        const label = total === 1
            ? t('activity.results_one', '1 registro')
            : t('activity.results_many', '{n} registros').replace('{n}', total.toLocaleString());

        countEl.textContent = label;
        if (filteredBadge) {
            filteredBadge.classList.toggle('d-none', !hasActiveFilters());
        }
    }

    function renderPagination() {
        const pag = document.getElementById('activityPagination');
        if (!pag) return;

        if (state.pages <= 1) {
            pag.innerHTML = '';
            return;
        }

        const prevDisabled = state.page <= 1 ? 'disabled' : '';
        const nextDisabled = state.page >= state.pages ? 'disabled' : '';

        // Paginacion con ellipsis si hay muchas paginas
        const pageNums = [];
        const total = state.pages;
        const current = state.page;

        if (total <= 7) {
            for (let i = 1; i <= total; i++) pageNums.push(i);
        } else {
            pageNums.push(1);
            if (current > 3) pageNums.push('...');
            const start = Math.max(2, current - 1);
            const end = Math.min(total - 1, current + 1);
            for (let i = start; i <= end; i++) pageNums.push(i);
            if (current < total - 2) pageNums.push('...');
            pageNums.push(total);
        }

        const numBtns = pageNums.map(n => {
            if (n === '...') {
                return `<span class="pagination-ellipsis" aria-hidden="true">…</span>`;
            }
            const active = n === current ? 'pagination-btn-active' : '';
            return `<button type="button" class="pagination-btn ${active}" data-page="${n}" ${n === current ? 'aria-current="page"' : ''}>${n}</button>`;
        }).join('');

        pag.innerHTML = `
            <div class="pagination-info text-sm text-subtle">
                ${t('activity.showing_of', 'Pagina {page} de {pages} - {total} registros')
                    .replace('{page}', current)
                    .replace('{pages}', total)
                    .replace('{total}', state.total)}
            </div>
            <div class="pagination-controls">
                <button type="button" class="pagination-btn" data-page="${current - 1}" ${prevDisabled}
                        aria-label="${t('activity.pagination_prev', 'Anterior')}">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </button>
                ${numBtns}
                <button type="button" class="pagination-btn" data-page="${current + 1}" ${nextDisabled}
                        aria-label="${t('activity.pagination_next', 'Siguiente')}">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </button>
            </div>
        `;

        pag.querySelectorAll('.pagination-btn[data-page]:not([disabled])').forEach(btn => {
            btn.addEventListener('click', () => {
                const newPage = parseInt(btn.dataset.page, 10);
                if (newPage && newPage !== state.page && newPage > 0 && newPage <= state.pages) {
                    state.page = newPage;
                    fetchEntries();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });
    }

    /** ========================================================
     * Filtros
     * ======================================================== */

    function applyFilters() {
        state.filters.date_from = document.getElementById('fActDateFrom').value;
        state.filters.date_to   = document.getElementById('fActDateTo').value;
        state.filters.user      = document.getElementById('fActUser').value;
        state.filters.module    = document.getElementById('fActModule').value;
        state.filters.action_filter = document.getElementById('fActAction').value;
        state.page = 1;
        fetchEntries();
    }

    ['fActDateFrom', 'fActDateTo', 'fActUser', 'fActModule', 'fActAction'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', applyFilters);
    });

    document.getElementById('btnClearFilters').addEventListener('click', () => {
        ['fActDateFrom', 'fActDateTo'].forEach(id => document.getElementById(id).value = '');
        ['fActUser', 'fActModule', 'fActAction'].forEach(id => document.getElementById(id).value = '');
        state.filters = { date_from: '', date_to: '', user: '', module: '', action_filter: '' };
        state.page = 1;
        fetchEntries();
    });

    /** ========================================================
     * Limpiar log
     * ======================================================== */

    document.getElementById('btnClearLog').addEventListener('click', async () => {
        const confirmed = await ConfirmModal.show({
            title:     t('activity.clear_title', 'Limpiar registro'),
            message:   t('activity.clear_message', 'Se eliminaran TODOS los registros de actividad. Esta accion no se puede deshacer.'),
            acceptText: t('common.delete', 'Eliminar todo'),
            acceptVariant: 'danger',
            icon: 'bi-trash',
            variant: 'danger',
        });
        if (!confirmed) return;

        try {
            const fd = new FormData();
            fd.append('action', 'clear');
            const res = await fetch('includes/reports_actions.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                body: fd,
            });
            const data = await res.json();
            if (data.success) {
                Toast.success(data.message || t('activity.clear_success', 'Registro limpiado.'));
                state.page = 1;
                fetchEntries();
            } else {
                Toast.error(data.message || t('activity.clear_error', 'No se pudo limpiar.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        }
    });

    /** ========================================================
     * Init con datos SSR
     * ======================================================== */

    state.total = initial.total || 0;
    state.pages = initial.pages || 0;
    state.page  = initial.page  || 1;
    renderRows(initial.entries || []);
    renderPagination();
    renderResultsCount();

    if ((initial.entries || []).length === 0 && state.total === 0) {
        document.getElementById('activityTable').classList.add('d-none');
        document.getElementById('activityEmpty').classList.remove('d-none');
    }

})();
