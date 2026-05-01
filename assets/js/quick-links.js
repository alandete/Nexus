(function () {
    'use strict';

    const ql = window.__QUICKLINKS__;
    if (!ql || !ql.isAdmin) return;

    // ── Topbar ───────────────────────────────────────────────────────────────

    function renderTopbar(items) {
        const nav = document.getElementById('topbar-quicklinks');
        if (!nav) return;
        nav.innerHTML = items
            .map(page => {
                const m = ql.meta[page];
                if (!m) return '';
                return `<a href="${m.href}" class="topbar-ql-item"` +
                    ` data-tooltip="${m.label}" data-tooltip-position="bottom"` +
                    ` aria-label="${m.label}">` +
                    `<i class="bi ${m.icon}" aria-hidden="true"></i></a>`;
            })
            .join('');
    }

    // ── Bookmark button ───────────────────────────────────────────────────────

    function updateBtnState(btn, isActive) {
        const label = isActive ? ql.i18n.btn_remove : ql.i18n.btn_add;
        btn.className = 'ql-toggle-btn' + (isActive ? ' is-active' : '');
        btn.setAttribute('data-tooltip', label);
        btn.setAttribute('aria-label', label);
        btn.innerHTML = `<i class="bi ${isActive ? 'bi-bookmark-fill' : 'bi-bookmark'}" aria-hidden="true"></i>`;
    }

    function injectToggleBtn() {
        if (!ql.meta[ql.current]) return;

        const titleEl = document.querySelector('h1.page-title');
        if (!titleEl) return;

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id   = 'qlToggleBtn';
        updateBtnState(btn, ql.items.includes(ql.current));
        btn.addEventListener('click', handleToggle);

        const wrapper = document.createElement('div');
        wrapper.className = 'page-title-row';
        titleEl.parentNode.insertBefore(wrapper, titleEl);
        wrapper.appendChild(titleEl);
        wrapper.appendChild(btn);
    }

    function handleToggle() {
        const btn = document.getElementById('qlToggleBtn');
        if (!btn || btn.disabled) return;
        btn.disabled = true;

        const fd = new FormData();
        fd.append('action', 'toggle');
        fd.append('page', ql.current);
        fd.append('csrf_token', ql.csrf);

        fetch(ql.endpoint, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    Toast.show(ql.i18n.error, 'danger');
                    return;
                }

                ql.items = data.links;
                updateBtnState(btn, ql.items.includes(ql.current));
                renderTopbar(ql.items);

                if (data.action === 'added') {
                    Toast.show(ql.i18n.added, 'success');
                } else if (data.action === 'removed') {
                    Toast.show(ql.i18n.removed, 'info');
                } else if (data.action === 'replaced') {
                    const oldLabel = ql.meta[data.removed]?.label ?? data.removed;
                    Toast.show(ql.i18n.replaced.replace('{old}', oldLabel), 'warning');
                }
            })
            .catch(() => Toast.show(ql.i18n.error, 'danger'))
            .finally(() => { btn.disabled = false; });
    }

    document.addEventListener('DOMContentLoaded', () => {
        injectToggleBtn();
    });
})();
