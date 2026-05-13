/**
 * Nexus 2.0 — System
 * Ejecuta diagnostico y renderiza resultados
 */

(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const T = window.__T__ || {};
    const t = (key, fallback) => T[key] || fallback;

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    const iconForStatus = {
        ok:      'bi-check-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        error:   'bi-x-circle-fill',
        info:    'bi-info-circle-fill',
    };

    async function runDiagnostics() {
        const btn = document.getElementById('btnRunDiag');
        if (!btn) return;

        btn.disabled = true;
        const btnText = btn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('system.running', 'Ejecutando...');

        try {
            const res = await fetch('includes/diagnostics_actions.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            });
            const data = await res.json();

            if (!data.success) {
                Toast.error(data.message || t('system.err_run', 'No se pudo ejecutar el diagnostico.'));
                return;
            }

            // Actualizar resumen
            const summary = data.summary || { ok: 0, warning: 0, error: 0, info: 0 };
            ['ok', 'warning', 'error'].forEach(key => {
                const el = document.querySelector(`.system-summary-item[data-count="${key}"] .system-summary-value`);
                if (el) el.textContent = summary[key] ?? 0;
            });

            // Renderizar issues
            renderChecks(data.checks || []);

            // Actualizar timestamp
            const lastRun = document.getElementById('diagLastRun');
            if (lastRun) lastRun.textContent = t('system.just_now', 'hace un momento');

            Toast.success(t('system.run_success', 'Diagnostico ejecutado.'));
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            btn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    function renderChecks(checks) {
        const container = document.getElementById('diagChecks');
        if (!container) return;

        const issues = checks.filter(c => c.status === 'error' || c.status === 'warning');

        if (issues.length === 0) {
            container.innerHTML = `
                <div class="system-all-ok">
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    <span>${t('system.all_ok', 'Todos los chequeos pasaron correctamente.')}</span>
                </div>
            `;
            return;
        }

        container.innerHTML = issues.map(c => {
            const icon = iconForStatus[c.status] || 'bi-info-circle-fill';
            return `
                <div class="system-check system-check-${escapeHtml(c.status)}">
                    <i class="bi ${icon} system-check-icon" aria-hidden="true"></i>
                    <div class="system-check-body">
                        <div class="system-check-header">
                            <span class="system-check-category text-subtle text-sm">${escapeHtml(c.category || '')}</span>
                            <span class="system-check-name">${escapeHtml(c.name || '')}</span>
                        </div>
                        ${c.detail ? `<p class="system-check-detail">${escapeHtml(c.detail)}</p>` : ''}
                        ${c.fix ? `
                        <div class="system-check-fix">
                            <i class="bi bi-lightbulb-fill" aria-hidden="true"></i>
                            <span>${escapeHtml(c.fix)}</span>
                        </div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    }

    async function checkDeps() {
        const btn = document.getElementById('btnCheckDeps');
        if (!btn) return;

        btn.disabled = true;
        const btnText = btn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('system.checking_deps', 'Verificando...');

        try {
            const res = await fetch('includes/deps_check_actions.php', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken },
            });
            const data = await res.json();

            if (!data.success) {
                Toast.error(data.message || t('system.err_check_deps', 'No se pudo verificar las dependencias.'));
                return;
            }

            renderDepsGrid(data.deps || {});

            const ts = document.getElementById('depsCheckedAt');
            if (ts) ts.textContent = t('system.just_now', 'hace un momento');

            Toast.success(t('system.deps_checked', 'Verificación completada.'));
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            btn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    function renderDepsGrid(deps) {
        const grid = document.getElementById('depsGrid');
        if (!grid) return;

        const items = {
            ghostscript: { label: 'Ghostscript',       icon: 'bi-file-earmark-pdf' },
            imagemagick: { label: 'ImageMagick CLI',    icon: 'bi-image' },
            imagick_ext: { label: 'Imagick (PHP)',      icon: 'bi-image-fill' },
            gd_ext:      { label: 'GD (PHP)',           icon: 'bi-image' },
        };

        // Actualizar solo las tarjetas de herramientas (no MySQL)
        Object.entries(items).forEach(([key, info]) => {
            const installed = deps[key]?.installed ?? null;
            const isOk = !!installed;
            const card = grid.querySelector(`[data-dep="${key}"]`);
            if (!card) return;

            card.className = `system-dep ${isOk ? 'system-dep-ok' : 'system-dep-missing'}`;
            const versionEl = card.querySelector('.system-dep-version');
            if (versionEl) {
                versionEl.innerHTML = isOk
                    ? `<span class="lozenge lozenge-success">${t('system.deps_installed', 'Instalado')}</span>
                       <code class="text-sm text-subtle">${escapeHtml(installed)}</code>`
                    : `<span class="lozenge lozenge-warning">${t('system.deps_missing', 'No encontrado')}</span>`;
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('btnRunDiag');
        if (btn) btn.addEventListener('click', runDiagnostics);

        const btnDeps = document.getElementById('btnCheckDeps');
        if (btnDeps) btnDeps.addEventListener('click', checkDeps);
    });

})();
