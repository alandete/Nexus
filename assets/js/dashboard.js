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

        postAction('day_summary', { date: new Date().toLocaleDateString('en-CA') })
            .then(function(data) {
                if (data.success) {
                    el.textContent = formatTime(data.total_seconds || 0);
                }
            })
            .catch(function() {
                el.textContent = '0m';
            });
    }

    function drawDashChart() {
        var el = document.getElementById('dashChart');
        if (!el || typeof Chart === 'undefined') return;

        var rows = ((window.__DASHBOARD__ || {}).chartData) || [];
        if (!rows.length) return;

        var labels = rows.map(function(r) { return r.name; });
        var values = rows.map(function(r) { return parseInt(r.cnt, 10); });
        var colors = rows.map(function(r) { return r.color || '#8795a8'; });

        var pctPlugin = {
            id: 'dashPct',
            afterDatasetsDraw: function(chart) {
                var ctx = chart.ctx;
                var ds  = chart.data.datasets[0];
                var total = ds.data.reduce(function(a, b) { return a + b; }, 0);
                if (!total) return;
                ctx.save();
                ctx.font = 'bold 12px system-ui, sans-serif';
                ctx.fillStyle = '#ffffff';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                chart.getDatasetMeta(0).data.forEach(function(arc, i) {
                    var pct = Math.round((ds.data[i] / total) * 100);
                    if (pct < 5) return;
                    var pos = arc.tooltipPosition();
                    ctx.fillText(pct + '%', pos.x, pos.y);
                });
                ctx.restore();
            }
        };

        new Chart(el, {
            type: 'doughnut',
            data: {
                labels: labels,
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
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                var total = ctx.dataset.data.reduce(function(a, b) { return a + b; }, 0);
                                var pct = total ? Math.round((ctx.parsed / total) * 100) : 0;
                                return ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            },
                        },
                    },
                },
            },
            plugins: [pctPlugin],
        });
    }

    // Init
    document.addEventListener('DOMContentLoaded', function() {
        loadTodayTime();
        drawDashChart();
    });

})();
