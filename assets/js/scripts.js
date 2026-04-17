/**
 * Nexus 2.0 — Scripts principales
 * Top bar + Sidebar layout
 */

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

            // Keep within viewport
            if (left + tw > window.innerWidth - 8) left = window.innerWidth - tw - 8;
            if (left < 8) left = 8;
            if (top < 8) top = 8;

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
