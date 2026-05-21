/**
 * Nexus — Service Worker de alertas de calendario
 * Versión 1.0
 */

const FETCH_INTERVAL  = 20 * 60 * 1000; // refresca eventos cada 20 minutos
const CHECK_INTERVAL  =      60 * 1000; // revisa si hay que alertar cada minuto
const ALERT_MINUTES   = [15, 5];

// uid-minutes -> true (para no repetir alertas)
const alerted = {};

// Cache local de eventos
let cachedEvents = [];

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', e => {
    e.waitUntil(self.clients.claim());
    schedulePoll();
});

self.addEventListener('message', () => {});

// Notificación clickeada: enfocar la pestaña de Nexus
self.addEventListener('notificationclick', e => {
    e.notification.close();
    e.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clients => {
            if (clients.length > 0) {
                clients[0].focus();
            }
        })
    );
});

let fetchTimer = null;
let checkTimer = null;

function schedulePoll() {
    if (fetchTimer) return;

    // Carga inicial inmediata, luego cada 20 min
    fetchEvents();
    fetchTimer = setInterval(fetchEvents, FETCH_INTERVAL);

    // Revisión de alertas cada minuto sobre la caché local
    checkTimer = setInterval(checkAlerts, CHECK_INTERVAL);
}

async function fetchEvents() {
    try {
        const res = await fetch('includes/calendar_actions.php?action=get_events', {
            credentials: 'include',
            cache: 'no-store',
        });
        if (!res.ok) return;
        const data = await res.json();
        cachedEvents = data.events || [];
    } catch (_) {
        // fallo silencioso: sin conexion o sesion expirada
    }
}

function checkAlerts() {
    if (cachedEvents.length === 0) return;

    const now       = Math.floor(Date.now() / 1000);
    const threshold = now - 3600;

    for (const ev of cachedEvents) {
        // Limpiar alertas de eventos que ya pasaron hace más de 1 hora
        if (ev.start_ts < threshold) {
            for (const min of ALERT_MINUTES) {
                delete alerted[`${ev.uid || ev.title}-${min}`];
            }
            continue;
        }

        for (const min of ALERT_MINUTES) {
            const key     = `${ev.uid || ev.title}-${min}`;
            const alertAt = ev.start_ts - min * 60;
            const diff    = alertAt - now;

            if (diff >= 0 && diff < 60 && !alerted[key]) {
                alerted[key] = true;
                fireAlert(ev.title, min);
            }
        }
    }
}

async function fireAlert(title, minutes) {
    const label = minutes === 1 ? '1 minuto' : `${minutes} minutos`;

    await self.registration.showNotification(`Reunion en ${label}`, {
        body:              title,
        tag:               `cal-${minutes}-${title}`,
        requireInteraction: true,
        silent:            false,
        icon:              'assets/img/nexus-icon.png',
    });

    // Avisar a los clientes para que reproduzcan sonido y muestren banner
    const clients = await self.clients.matchAll({ includeUncontrolled: true, type: 'window' });
    for (const client of clients) {
        client.postMessage({ type: 'CALENDAR_ALERT', title, minutes });
    }
}
