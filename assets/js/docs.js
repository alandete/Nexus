/**
 * Nexus 2.0 — Documentation JS
 * Scroll-spy, TOC toggle, search filter
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {

        var toc = document.getElementById('docsToc');
        var searchInput = document.getElementById('docsSearch');
        if (!toc) return;

        // --- TOC group toggle ---
        toc.querySelectorAll('.docs-toc-parent').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var group = this.closest('.docs-toc-group');
                if (!group) return;

                // If has children, toggle expand
                var sublist = group.querySelector('.docs-toc-sublist');
                if (sublist) {
                    var expanded = group.getAttribute('aria-expanded') === 'true';
                    group.setAttribute('aria-expanded', !expanded);
                }
            });
        });

        // --- Smooth scroll for TOC links ---
        toc.querySelectorAll('.docs-toc-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var href = this.getAttribute('href');
                if (!href || href.charAt(0) !== '#') return;

                var target = document.getElementById(href.substring(1));
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    history.replaceState(null, '', href);
                }
            });
        });

        // --- Scroll-spy: highlight active TOC item ---
        var articles = document.querySelectorAll('.docs-article, .docs-section');
        var tocLinks = toc.querySelectorAll('.docs-toc-link');

        function updateScrollSpy() {
            var scrollPos = window.scrollY + 100;
            var activeId = '';

            for (var i = articles.length - 1; i >= 0; i--) {
                if (articles[i].offsetTop <= scrollPos) {
                    activeId = articles[i].id;
                    break;
                }
            }

            tocLinks.forEach(function(link) {
                var section = link.getAttribute('data-section');
                var isActive = section === activeId;
                link.classList.toggle('active', isActive);

                // Auto-expand parent group
                if (isActive) {
                    var group = link.closest('.docs-toc-group');
                    if (group) group.setAttribute('aria-expanded', 'true');
                }
            });
        }

        window.addEventListener('scroll', updateScrollSpy, { passive: true });
        updateScrollSpy();

        // --- Expand group from URL hash ---
        if (window.location.hash) {
            var hashTarget = document.querySelector('.docs-toc-link[href="' + window.location.hash + '"]');
            if (hashTarget) {
                var group = hashTarget.closest('.docs-toc-group');
                if (group) group.setAttribute('aria-expanded', 'true');

                setTimeout(function() {
                    var el = document.getElementById(window.location.hash.substring(1));
                    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }, 100);
            }
        }

        // --- Search filter ---
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var query = this.value.toLowerCase().trim();
                var groups = toc.querySelectorAll('.docs-toc-group');

                if (!query) {
                    // Reset: show all, collapse all
                    groups.forEach(function(g) {
                        g.style.display = '';
                        g.setAttribute('aria-expanded', 'false');
                        g.querySelectorAll('.docs-toc-child').forEach(function(c) {
                            c.closest('li').style.display = '';
                        });
                    });
                    updateScrollSpy();
                    return;
                }

                groups.forEach(function(group) {
                    var parentText = group.querySelector('.docs-toc-parent span').textContent.toLowerCase();
                    var children = group.querySelectorAll('.docs-toc-child');
                    var parentMatch = parentText.includes(query);
                    var anyChildMatch = false;

                    children.forEach(function(child) {
                        var childText = child.textContent.toLowerCase();
                        var match = childText.includes(query);
                        child.closest('li').style.display = match ? '' : 'none';
                        if (match) anyChildMatch = true;
                    });

                    group.style.display = (parentMatch || anyChildMatch) ? '' : 'none';
                    if (anyChildMatch) group.setAttribute('aria-expanded', 'true');
                });
            });
        }
    });

})();
