/**
 * Nexus 2.0 — Scripts principales
 * Top bar + Sidebar layout
 */

// ─── CSRF + session auto-refresh ─────────────────────────────────────────────
// Intercepta fetch:
//   403 + mensaje CSRF → obtiene token nuevo y reintenta.
//   401              → verifica si la sesión fue restaurada por petición
//                      concurrente (recuérdame); si sí, reintenta con token
//                      fresco; si no, redirige al login.
(function () {
    var _fetch       = window.fetch;
    var refreshingCs = false; // guard CSRF
    var refreshingSs = false; // guard session

    function csrfEndpoint() {
        var base = (document.querySelector('meta[name="app-base"]') || {}).content || '';
        return base.replace(/\/$/, '') + '/includes/csrf_token_actions.php';
    }

    function updateMetaToken(token) {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) meta.content = token;
    }

    window.fetch = function (url, opts) {
        return _fetch(url, opts).then(function (res) {

            // ── 401: sesión expirada ──────────────────────────────────────────
            if (res.status === 401 && !refreshingSs) {
                refreshingSs = true;
                // Consultar csrf_token_actions.php: devuelve 200 si la sesión
                // fue restaurada por una petición concurrente (recuérdame).
                return _fetch(csrfEndpoint(), { credentials: 'same-origin' })
                    .then(function (r) {
                        refreshingSs = false;
                        if (r.status !== 200) return res; // sesión realmente caducada
                        return r.json().then(function (td) {
                            if (!td || !td.token) return res;
                            updateMetaToken(td.token);
                            var newOpts = Object.assign({}, opts || {});
                            newOpts.headers = Object.assign({}, newOpts.headers || {}, {
                                'X-CSRF-TOKEN': td.token,
                            });
                            return _fetch(url, newOpts);
                        });
                    })
                    .catch(function () { refreshingSs = false; return res; });
            }

            // ── 403: token CSRF inválido ──────────────────────────────────────
            if (res.status !== 403 || refreshingCs) return res;
            return res.clone().json().then(function (data) {
                var msg = (data && data.message) ? data.message.toLowerCase() : '';
                if (!msg.includes('csrf')) return res;

                refreshingCs = true;
                return _fetch(csrfEndpoint(), { credentials: 'same-origin' })
                    .then(function (r) { return r.json(); })
                    .then(function (td) {
                        refreshingCs = false;
                        if (!td || !td.token) return res;
                        updateMetaToken(td.token);
                        var newOpts  = Object.assign({}, opts || {});
                        newOpts.headers = Object.assign({}, newOpts.headers || {}, {
                            'X-CSRF-TOKEN': td.token,
                        });
                        return _fetch(url, newOpts);
                    })
                    .catch(function () { refreshingCs = false; return res; });
            }).catch(function () { return res; });
        });
    };
})();

// ─── Skeleton ────────────────────────────────────────────────────────────────
var Skeleton = (function () {
    function _el(id) { return document.getElementById(id); }

    // Inyecta HTML de skeleton en el contenedor. Si template es omitido usa filas genéricas.
    function show(containerId, template) {
        var el = _el(containerId);
        if (!el) return;
        el.innerHTML = template !== undefined ? template : rows(4);
    }

    function hide(containerId) {
        var el = _el(containerId);
        if (el) el.innerHTML = '';
    }

    // Filas genéricas (para listados)
    function rows(count, extraClass) {
        var cls = 'skeleton skeleton-row' + (extraClass ? ' ' + extraClass : '');
        var html = '';
        for (var i = 0; i < (count || 3); i++) {
            var w = [85, 70, 90, 60, 78][i % 5];
            html += '<div class="' + cls + '" style="width:' + w + '%"></div>';
        }
        return html;
    }

    // Bloque de texto (título + líneas de texto)
    function textBlock(lines) {
        var html = '<div class="skeleton skeleton-title" style="width:55%"></div>';
        for (var i = 0; i < (lines || 3); i++) {
            var w = [80, 65, 75, 90, 50][i % 5];
            html += '<div class="skeleton skeleton-text" style="width:' + w + '%"></div>';
        }
        return html;
    }

    // Placeholder de chart
    function chart() {
        return '<div class="skeleton skeleton-chart"></div>';
    }

    // Placeholder de stat (número grande + etiqueta)
    function stat() {
        return '<div class="skeleton skeleton-stat" style="width:50%;margin-bottom:var(--ds-space-050)"></div>' +
               '<div class="skeleton skeleton-text" style="width:35%"></div>';
    }

    return { show: show, hide: hide, rows: rows, textBlock: textBlock, chart: chart, stat: stat };
})();

(function() {
    'use strict';

    // --- Page loader: hide when DOM is ready ---
    var pageLoader = document.getElementById('pageLoader');
    if (pageLoader) {
        window.addEventListener('load', function() {
            pageLoader.classList.add('hidden');
        });
        // Fallback: hide after 3s max
        setTimeout(function() {
            if (pageLoader) pageLoader.classList.add('hidden');
        }, 3000);
    }

    document.addEventListener('DOMContentLoaded', function() {

        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const btnScrollTop = document.getElementById('btnScrollTop');
        const userDropdownBtn = document.getElementById('userDropdownBtn');
        const userDropdownMenu = document.getElementById('userDropdownMenu');

        const DESKTOP_BREAKPOINT = 992;
        const TABLET_BREAKPOINT = 768;

        // --- Sidebar state ---
        function getViewport() {
            if (window.innerWidth >= DESKTOP_BREAKPOINT) return 'desktop';
            if (window.innerWidth >= TABLET_BREAKPOINT) return 'tablet';
            return 'mobile';
        }

        function initSidebar() {
            if (!sidebar) return;
            sidebar.classList.remove('open');
        }

        initSidebar();

        // --- Sidebar toggle (handled in hover section below) ---

        // --- Sidebar overlay click (mobile) ---
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                if (sidebar) sidebar.classList.remove('open');
                sidebarOverlay.classList.remove('active');
                if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
                isPinned = false;
            });
        }

        // --- Sidebar collapsible groups ---
        document.querySelectorAll('.sidebar-group-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                var expanded = this.getAttribute('aria-expanded') === 'true';
                this.setAttribute('aria-expanded', !expanded);
                var submenu = this.nextElementSibling;
                if (submenu && submenu.classList.contains('sidebar-submenu')) {
                    submenu.classList.toggle('show');
                }
            });
        });

        // --- Close sidebar on mobile when clicking a link ---
        if (sidebar) {
            sidebar.querySelectorAll('.sidebar-link:not(.sidebar-group-toggle), .sidebar-sublink').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (getViewport() === 'mobile') {
                        sidebar.classList.remove('open');
                        if (sidebarOverlay) sidebarOverlay.classList.remove('active');
                        if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
                    }
                });
            });
        }

        // --- Tooltip system (global) ---
        var tooltip = document.createElement('div');
        tooltip.className = 'app-tooltip';
        tooltip.setAttribute('role', 'tooltip');
        document.body.appendChild(tooltip);

        var tooltipTimeout;

        function showTooltip(el) {
            var text = el.getAttribute('data-tooltip');
            if (!text) return;

            // Sidebar tooltips: only when collapsed
            if (el.closest('.sidebar') && sidebar && sidebar.classList.contains('open')) return;

            var rect = el.getBoundingClientRect();
            var position = el.getAttribute('data-tooltip-position') || 'right';

            tooltip.textContent = text;
            tooltip.classList.add('visible');

            // Calculate position after making visible (to get tooltip dimensions)
            var tw = tooltip.offsetWidth;
            var th = tooltip.offsetHeight;
            var gap = 8;
            var top, left;

            switch (position) {
                case 'top':
                    top = rect.top - th - gap;
                    left = rect.left + (rect.width - tw) / 2;
                    break;
                case 'bottom':
                    top = rect.bottom + gap;
                    left = rect.left + (rect.width - tw) / 2;
                    break;
                case 'left':
                    top = rect.top + (rect.height - th) / 2;
                    left = rect.left - tw - gap;
                    break;
                default: // right
                    top = rect.top + (rect.height - th) / 2;
                    left = rect.right + gap;
            }

            // Keep within viewport (respect topbar height)
            var topbarEl = document.querySelector('.topbar');
            var topbarH = topbarEl ? topbarEl.getBoundingClientRect().bottom : 56;
            if (left + tw > window.innerWidth - 8) left = window.innerWidth - tw - 8;
            if (left < 8) left = 8;
            if (top < topbarH + 4) top = topbarH + 4;

            tooltip.style.top = top + 'px';
            tooltip.style.left = left + 'px';
        }

        function hideTooltip() {
            clearTimeout(tooltipTimeout);
            tooltip.classList.remove('visible');
        }

        document.addEventListener('mouseover', function(e) {
            var target = e.target.closest('[data-tooltip]');
            if (target) {
                clearTimeout(tooltipTimeout);
                tooltipTimeout = setTimeout(function() { showTooltip(target); }, 100);
            }
        });

        document.addEventListener('mouseout', function(e) {
            var target = e.target.closest('[data-tooltip]');
            if (target) hideTooltip();
        });

        // --- Sidebar expand on hover (desktop/tablet only) ---
        var hoverTimeout;
        var isPinned = false;

        if (sidebar) {
            sidebar.addEventListener('mouseenter', function() {
                if (getViewport() === 'mobile' || isPinned) return;
                clearTimeout(hoverTimeout);
                sidebar.classList.add('open');
            });

            sidebar.addEventListener('mouseleave', function() {
                if (getViewport() === 'mobile' || isPinned) return;
                hoverTimeout = setTimeout(function() {
                    sidebar.classList.remove('open');
                }, 200);
            });
        }

        // Update toggle to pin/unpin instead of open/close on desktop
        if (sidebarToggle && sidebar) {
            sidebarToggle.removeEventListener('click', sidebarToggle._handler);
            sidebarToggle._handler = function() {
                var viewport = getViewport();
                if (viewport === 'mobile') {
                    sidebar.classList.toggle('open');
                    var isOpen = sidebar.classList.contains('open');
                    sidebarToggle.setAttribute('aria-expanded', isOpen);
                    if (sidebarOverlay) sidebarOverlay.classList.toggle('active', isOpen);
                } else {
                    isPinned = !isPinned;
                    sidebar.classList.toggle('open', isPinned);
                    sidebarToggle.setAttribute('aria-expanded', isPinned);
                    sidebarToggle.querySelector('i').className = isPinned ? 'bi bi-layout-sidebar-inset' : 'bi bi-list';
                }
            };
            sidebarToggle.addEventListener('click', sidebarToggle._handler);
        }

        // --- Handle resize ---
        var resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                isPinned = false;
                if (sidebar) sidebar.classList.remove('open');
                if (sidebarOverlay) sidebarOverlay.classList.remove('active');
                if (sidebarToggle) {
                    sidebarToggle.setAttribute('aria-expanded', 'false');
                    sidebarToggle.querySelector('i').className = 'bi bi-list';
                }
            }, 100);
        });

        // --- Scroll to top ---
        if (btnScrollTop) {
            window.addEventListener('scroll', function() {
                btnScrollTop.classList.toggle('visible', window.scrollY > 200);
            }, { passive: true });

            btnScrollTop.addEventListener('click', function() {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        // --- User dropdown ---
        if (userDropdownBtn && userDropdownMenu) {
            userDropdownBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                var expanded = userDropdownBtn.getAttribute('aria-expanded') === 'true';
                userDropdownBtn.setAttribute('aria-expanded', !expanded);
                userDropdownMenu.classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                if (!userDropdownBtn.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                    userDropdownMenu.classList.remove('show');
                    userDropdownBtn.setAttribute('aria-expanded', 'false');
                }
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && userDropdownMenu.classList.contains('show')) {
                    userDropdownMenu.classList.remove('show');
                    userDropdownBtn.setAttribute('aria-expanded', 'false');
                    userDropdownBtn.focus();
                }
            });
        }

        // --- Timer floater ---
        var timerFloater = document.getElementById('timerFloater');
        if (timerFloater) {
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            async function checkFloater() {
                try {
                    var fd = new FormData();
                    fd.append('action', 'timer_status');
                    var res = await fetch('includes/tasks_actions.php', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': csrfToken },
                        body: fd,
                    });
                    var data = await res.json();
                    if (data.success && data.running) {
                        timerFloater.classList.add('active');
                        var startTime = new Date(data.entry.start_time.replace(' ', 'T'));
                        var floaterTime = document.getElementById('floaterTime');
                        var floaterTask = document.getElementById('floaterTask');
                        var floaterAlliance = document.getElementById('floaterAlliance');

                        if (floaterTask) floaterTask.textContent = data.entry.title || '';
                        if (floaterAlliance) floaterAlliance.textContent = data.entry.alliance_name || '';

                        setInterval(function() {
                            var elapsed = Math.floor((Date.now() - startTime.getTime()) / 1000);
                            var h = String(Math.floor(elapsed / 3600)).padStart(2, '0');
                            var m = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
                            var s = String(elapsed % 60).padStart(2, '0');
                            if (floaterTime) floaterTime.textContent = h + ':' + m + ':' + s;
                        }, 1000);
                    }
                } catch (_) {}
            }

            checkFloater();
        }
    });

})();
