<?php
/**
 * Nexus 2.0 — Backup automático vía cron
 *
 * HTTP:  curl -s "https://dominio.com/cron/backup_cron.php?token=TOKEN"
 * CLI:   php cron/backup_cron.php token=TOKEN
 * Test:  curl -s "https://dominio.com/cron/backup_cron.php?token=TOKEN&force=1"
 */

define('APP_ACCESS', true);

// Soporte CLI: php backup_cron.php token=xxx [force=1]
if (php_sapi_name() === 'cli') {
    parse_str(implode('&', array_slice($_SERVER['argv'] ?? [], 1)), $_GET);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/backup_core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$scheduleFile = DATA_PATH . '/backup_schedule.json';
$cronLogFile  = DATA_PATH . '/cron_log.json';

function appendCronLog(string $file, array $entry): void
{
    $log = file_exists($file) ? (json_decode(file_get_contents($file), true) ?? []) : [];
    array_unshift($log, $entry);
    $log = array_slice($log, 0, 20); // conservar últimas 20 ejecuciones
    file_put_contents($file, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function cronRespond(array $result, string $logFile, string $source = 'cron'): void
{
    appendCronLog($logFile, [
        'ts'      => date('Y-m-d H:i:s'),
        'source'  => $source,
        'success' => $result['success'],
        'message' => $result['message'] ?? '',
        'file'    => $result['filename'] ?? null,
        'size'    => $result['size']     ?? null,
    ]);
    echo json_encode($result);
}

if (!file_exists($scheduleFile)) {
    $r = ['success' => false, 'message' => 'Sin configuración de backup automático'];
    cronRespond($r, $cronLogFile);
    http_response_code(404);
    exit;
}

$schedule = json_decode(file_get_contents($scheduleFile), true);
if (!is_array($schedule)) {
    $r = ['success' => false, 'message' => 'Configuración inválida'];
    cronRespond($r, $cronLogFile);
    http_response_code(500);
    exit;
}

// Validar token
$token       = $_GET['token'] ?? '';
$storedToken = $schedule['token'] ?? '';
if ($token === '' || $storedToken === '' || !hash_equals($storedToken, $token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token inválido']);
    exit; // No loguear intentos con token incorrecto
}

$force  = !empty($_GET['force']);
$source = $force ? 'manual' : 'cron';

// Verificar que esté activado (el force lo omite para poder probar sin activar)
if (!$force && empty($schedule['enabled'])) {
    cronRespond(['success' => false, 'message' => 'Backup automático desactivado'], $cronLogFile, $source);
    exit;
}

// Verificar frecuencia mínima (evitar ejecuciones dobles); ignorada con force=1
if (!$force) {
    $frequencyMap = ['daily' => 86400, 'weekly' => 604800, 'monthly' => 2592000];
    $minSeconds   = $frequencyMap[$schedule['frequency'] ?? 'daily'] ?? 86400;
    $lastRun      = !empty($schedule['last_run']) ? strtotime($schedule['last_run']) : 0;

    if ($lastRun && (time() - $lastRun) < $minSeconds) {
        $nextRun = date('Y-m-d H:i:s', $lastRun + $minSeconds);
        cronRespond(['success' => false, 'message' => "Muy pronto. Próximo backup: $nextRun"], $cronLogFile, $source);
        exit;
    }
}

// Ejecutar backup
$result = ejecutarBackupDirecto($schedule['type'] ?? 'data', $source);

// Actualizar last_run solo en ejecuciones automáticas
if ($result['success'] && !$force) {
    $schedule['last_run'] = date('Y-m-d H:i:s');
    file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

cronRespond($result, $cronLogFile, $source);
