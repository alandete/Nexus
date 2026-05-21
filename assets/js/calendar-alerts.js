/**
 * Nexus — Alertas de calendario
 * Registra el service worker y maneja notificaciones en-app
 */

(function () {
    'use strict';

    if (!('serviceWorker' in navigator) || !('Notification' in window)) return;

    let swReg = null;

    // ── Registro del Service Worker ──────────────────────────────────────────

    async function init() {
        try {
            swReg = await navigator.serviceWorker.register('sw-calendar.js', { scope: './' });
        } catch (e) {
            return;
        }

        // Solicitar permiso de notificaciones si aún no está concedido
        if (Notification.permission === 'default') {
            // Diferir la solicitud para no bloquear la carga inicial
            setTimeout(() => Notification.requestPermission(), 3000);
        }

        // Escuchar mensajes del SW
        navigator.serviceWorker.addEventListener('message', onSwMessage);

        // El SW maneja sus propios timers; no necesitamos pings externos
    }

    // ── Receptor de mensajes ─────────────────────────────────────────────────

    function onSwMessage(e) {
        if (e.data?.type !== 'CALENDAR_ALERT') return;
        const { title, minutes } = e.data;
        playAlertSound();
        showAlertBanner(title, minutes);
    }

    // ── Sonido (Web Audio API) ────────────────────────────────────────────────

    function playAlertSound() {
        try {
            const ctx  = new (window.AudioContext || window.webkitAudioContext)();
            const now  = ctx.currentTime;

            // Dos tonos cortos: do-mi (C5-E5)
            [[523.25, 0], [659.25, 0.25]].forEach(([freq, delay]) => {
                const osc  = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.type = 'sine';
                osc.frequency.value = freq;
                gain.gain.setValueAtTime(0.4, now + delay);
                gain.gain.exponentialRampToValueAtTime(0.001, now + delay + 0.4);
                osc.start(now + delay);
                osc.stop(now + delay + 0.4);
            });
        } catch (_) {
            // Sin soporte de Web Audio
        }
    }

    // ── Banner en-app ─────────────────────────────────────────────────────────

    function showAlertBanner(title, minutes) {
        // Eliminar banner anterior del mismo tipo si existe
        document.querySelector('.cal-alert-banner')?.remove();

        const label   = minutes === 1 ? '1 minuto' : `${minutes} minutos`;
        const banner  = document.createElement('div');
        banner.className = 'cal-alert-banner';
        banner.setAttribute('role', 'alert');
        banner.setAttribute('aria-live', 'assertive');
        banner.innerHTML = `
            <div class="cal-alert-icon">
                <i class="bi bi-calendar-event-fill" aria-hidden="true"></i>
            </div>
            <div class="cal-alert-body">
                <strong class="cal-alert-title">Reunion en ${label}</strong>
                <span class="cal-alert-event">${escapeHtml(title)}</span>
            </div>
            <button type="button" class="cal-alert-close" aria-label="Cerrar">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        `;

        banner.querySelector('.cal-alert-close').addEventListener('click', () => banner.remove());
        document.body.appendChild(banner);

        // Auto-cierre a los 30 segundos
        setTimeout(() => banner?.remove(), 30 * 1000);
    }

    function escapeHtml(str) {
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Estilos del banner ────────────────────────────────────────────────────

    function injectStyles() {
        if (document.getElementById('cal-alert-styles')) return;
        const style = document.createElement('style');
        style.id = 'cal-alert-styles';
        style.textContent = `
.cal-alert-banner {
    position: fixed;
    bottom: 24px;
    right: 24px;
    z-index: 9999;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: var(--color-surface-overlay, #fff);
    border: 1px solid var(--color-border-accent, #0052cc);
    border-left: 4px solid var(--app-brand, #0052cc);
    border-radius: 6px;
    box-shadow: 0 8px 24px rgba(0,0,0,.18);
    min-width: 280px;
    max-width: 380px;
    animation: cal-slide-in .25s ease;
}
@keyframes cal-slide-in {
    from { opacity: 0; transform: translateY(16px); }
    to   { opacity: 1; transform: translateY(0); }
}
.cal-alert-icon {
    font-size: 1.4rem;
    color: var(--app-brand, #0052cc);
    flex-shrink: 0;
}
.cal-alert-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
    overflow: hidden;
}
.cal-alert-title {
    font-size: .875rem;
    color: var(--color-text, #172b4d);
    white-space: nowrap;
}
.cal-alert-event {
    font-size: .8125rem;
    color: var(--color-text-subtle, #5e6c84);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cal-alert-close {
    background: none;
    border: none;
    cursor: pointer;
    color: var(--color-text-subtle, #5e6c84);
    padding: 2px;
    line-height: 1;
    flex-shrink: 0;
}
.cal-alert-close:hover { color: var(--color-text, #172b4d); }
        `;
        document.head.appendChild(style);
    }

    injectStyles();
    init();
})();
