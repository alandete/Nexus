/**
 * Nexus 2.0 — Dashboard JS
 * Timer activo y tiempo del dia
 */

(function() {
    'use strict';

    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function postAction(action, extra) {
        var fd = new FormData();
        fd.append('action', action);
        if (extra) {
            Object.keys(extra).forEach(function(k) { fd.append(k, extra[k]); });
        }
        return fetch('includes/tasks_actions.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: fd
        }).then(function(r) { return r.json(); });
    }

    function formatTime(seconds) {
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        if (h > 0) return h + 'h ' + String(m).padStart(2, '0') + 'm';
        return m + 'm';
    }

    function formatTimeFull(seconds) {
        var h = String(Math.floor(seconds / 3600)).padStart(2, '0');
        var m = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
        var s = String(seconds % 60).padStart(2, '0');
        return h + ':' + m + ':' + s;
    }

    // Load today's time
    function loadTodayTime() {
        var el = document.getElementById('dashTodayTime');
        if (!el) return;

        postAction('day_summary', { date: new Date().toISOString().slice(0, 10) })
            .then(function(data) {
                if (data.success) {
                    el.textContent = formatTime(data.total_seconds || 0);
                }
            })
            .catch(function() {
                el.textContent = '0m';
            });
    }

    // Init
    document.addEventListener('DOMContentLoaded', function() {
        loadTodayTime();
    });

})();
