<?php
/**
 * Nexus — Acciones de Calendario
 * Endpoint GET para el Service Worker: devuelve eventos del dia
 * No requiere CSRF (GET read-only, autenticado por sesion)
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'events' => []]);
    exit;
}

$action = $_GET['action'] ?? '';

if ($action === 'get_events') {
    $currentUser = getCurrentUser();
    $username    = $currentUser['username'] ?? '';
    $extras      = getUserExtras($username);
    $icalUrl     = $extras['calendar_ical_url'] ?? '';

    if (empty($icalUrl)) {
        echo json_encode(['success' => true, 'events' => []]);
        exit;
    }

    // Validar que es una URL de Google Calendar
    if (!filter_var($icalUrl, FILTER_VALIDATE_URL) || strpos($icalUrl, 'calendar.google.com') === false) {
        echo json_encode(['success' => false, 'events' => [], 'error' => 'URL no valida']);
        exit;
    }

    $ical = fetchIcal($icalUrl);
    if ($ical === null) {
        echo json_encode(['success' => false, 'events' => [], 'error' => 'No se pudo obtener el calendario']);
        exit;
    }

    $events = parseIcal($ical);

    // Filtrar eventos de los proximos N dias (max 3)
    $days  = max(1, min(3, (int)($_GET['days'] ?? 1)));
    $now   = time();
    $limit = $now + $days * 86400;
    $upcoming = array_values(array_filter($events, function ($e) use ($now, $limit) {
        return $e['start_ts'] >= $now && $e['start_ts'] <= $limit;
    }));
    usort($upcoming, fn($a, $b) => $a['start_ts'] - $b['start_ts']);

    echo json_encode(['success' => true, 'events' => $upcoming]);
    exit;
}

echo json_encode(['success' => false, 'events' => [], 'error' => 'Accion no valida']);

// ---- Funciones ----

function fetchIcal(string $url): ?string
{
    $ctx = stream_context_create([
        'http' => [
            'timeout'        => 8,
            'follow_location' => 1,
            'user_agent'     => 'Nexus-CalendarBot/1.0',
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);
    $result = @file_get_contents($url, false, $ctx);
    return $result !== false ? $result : null;
}

function parseIcal(string $ical): array
{
    // Desplegar lineas continuadas (RFC 5545: linea que inicia con espacio/tab)
    $raw   = str_replace(["\r\n", "\r"], "\n", $ical);
    $lines = explode("\n", $raw);

    $unfolded = [];
    foreach ($lines as $line) {
        if ($line !== '' && ($line[0] === ' ' || $line[0] === "\t")) {
            if (!empty($unfolded)) {
                $unfolded[count($unfolded) - 1] .= substr($line, 1);
            }
        } else {
            $unfolded[] = rtrim($line);
        }
    }

    $events  = [];
    $inEvent = false;
    $ev      = [];

    foreach ($unfolded as $line) {
        if ($line === 'BEGIN:VEVENT') {
            $inEvent = true;
            $ev      = [];
            continue;
        }
        if ($line === 'END:VEVENT') {
            $inEvent = false;
            if (!empty($ev['start_ts']) && !empty($ev['title']) && ($ev['status'] ?? '') !== 'CANCELLED') {
                $events[] = $ev;
            }
            continue;
        }
        if (!$inEvent) continue;

        $colonPos = strpos($line, ':');
        if ($colonPos === false) continue;
        $prop  = substr($line, 0, $colonPos);
        $value = substr($line, $colonPos + 1);

        $propName = strtoupper(explode(';', $prop)[0]);

        switch ($propName) {
            case 'DTSTART':
                $ts = parseDtValue($prop, $value);
                if ($ts !== null) $ev['start_ts'] = $ts;
                break;
            case 'SUMMARY':
                $ev['title'] = unescapeIcal($value);
                break;
            case 'UID':
                $ev['uid'] = $value;
                break;
            case 'STATUS':
                $ev['status'] = strtoupper(trim($value));
                break;
        }
    }

    return $events;
}

function parseDtValue(string $prop, string $value): ?int
{
    // Eventos de dia completo (VALUE=DATE) → ignorar
    if (stripos($prop, 'VALUE=DATE') !== false && stripos($prop, 'DATE-TIME') === false) {
        return null;
    }

    $value = trim($value);

    // Formato: YYYYMMDDTHHMMSSZ o YYYYMMDDTHHMMSS
    if (!preg_match('/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})(Z?)$/', $value, $m)) {
        return null;
    }

    $dateStr = "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}";

    if ($m[7] === 'Z') {
        $tz = new DateTimeZone('UTC');
    } else {
        $tzid = null;
        if (preg_match('/TZID=([^;:]+)/', $prop, $tm)) {
            $tzid = $tm[1];
        }
        try {
            $tz = new DateTimeZone($tzid ?: date_default_timezone_get());
        } catch (\Exception) {
            $tz = new DateTimeZone(date_default_timezone_get());
        }
    }

    $dt = new DateTime($dateStr, $tz);
    return $dt->getTimestamp();
}

function unescapeIcal(string $value): string
{
    return str_replace(['\\,', '\\;', '\\n', '\\N', '\\\\'], [',', ';', "\n", "\n", '\\'], $value);
}
