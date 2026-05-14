<?php
/**
 * Nexus 2.0 — Backup automático vía cron
 *
 * HTTP:  curl -s "https://dominio.com/cron/backup_cron.php?token=TOKEN"
 * CLI:   php cron/backup_cron.php token=TOKEN
 */

define('APP_ACCESS', true);

// Soporte CLI: php backup_cron.php token=xxx
if (php_sapi_name() === 'cli') {
    parse_str(implode('&', array_slice($_SERVER['argv'] ?? [], 1)), $_GET);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/backup_core.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$scheduleFile = DATA_PATH . '/backup_schedule.json';

if (!file_exists($scheduleFile)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Sin configuración de backup automático']);
    exit;
}

$schedule = json_decode(file_get_contents($scheduleFile), true);
if (!is_array($schedule)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Configuración inválida']);
    exit;
}

// Validar token
$token       = $_GET['token'] ?? '';
$storedToken = $schedule['token'] ?? '';
if ($token === '' || $storedToken === '' || !hash_equals($storedToken, $token)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Token inválido']);
    exit;
}

// Verificar que esté activado
if (empty($schedule['enabled'])) {
    echo json_encode(['success' => false, 'message' => 'Backup automático desactivado']);
    exit;
}

// Verificar frecuencia mínima (evitar ejecuciones dobles)
$frequencyMap = ['daily' => 86400, 'weekly' => 604800, 'monthly' => 2592000];
$minSeconds   = $frequencyMap[$schedule['frequency'] ?? 'daily'] ?? 86400;
$lastRun      = !empty($schedule['last_run']) ? strtotime($schedule['last_run']) : 0;

if ($lastRun && (time() - $lastRun) < $minSeconds) {
    $nextRun = date('Y-m-d H:i:s', $lastRun + $minSeconds);
    echo json_encode(['success' => false, 'message' => "Muy pronto. Próximo backup: $nextRun"]);
    exit;
}

// Ejecutar backup
$result = ejecutarBackupDirecto($schedule['type'] ?? 'data');

// Actualizar last_run
if ($result['success']) {
    $schedule['last_run'] = date('Y-m-d H:i:s');
    file_put_contents($scheduleFile, json_encode($schedule, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

echo json_encode($result);
