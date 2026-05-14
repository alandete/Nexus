<?php
/**
 * Nexus 2.0 — Backup Schedule Actions
 * Guarda y actualiza la configuración de backup automático
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$currentUser = getCurrentUser();
if (!hasPermission($currentUser, 'backup', null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

$action       = $_POST['action'] ?? '';
$scheduleFile = DATA_PATH . '/backup_schedule.json';

function loadScheduleConfig(string $file): array
{
    if (!file_exists($file)) {
        return ['enabled' => false, 'token' => '', 'type' => 'data', 'frequency' => 'daily', 'last_run' => null];
    }
    $d = json_decode(file_get_contents($file), true);
    return is_array($d) ? $d : [];
}

function saveScheduleConfig(string $file, array $data): bool
{
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function generateCronToken(): string
{
    return bin2hex(random_bytes(24));
}

if ($action === 'save') {
    $schedule = loadScheduleConfig($scheduleFile);

    $schedule['enabled']   = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    $schedule['type']      = in_array($_POST['type'] ?? '', ['data', 'full'], true) ? $_POST['type'] : 'data';
    $schedule['frequency'] = in_array($_POST['frequency'] ?? '', ['daily', 'weekly', 'monthly'], true)
        ? $_POST['frequency'] : 'daily';

    if (empty($schedule['token'])) {
        $schedule['token'] = generateCronToken();
    }

    if (saveScheduleConfig($scheduleFile, $schedule)) {
        echo json_encode(['success' => true, 'message' => 'Configuración guardada', 'token' => $schedule['token']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la configuración']);
    }
    exit;
}

if ($action === 'regen_token') {
    $schedule = loadScheduleConfig($scheduleFile);
    $schedule['token'] = generateCronToken();

    if (saveScheduleConfig($scheduleFile, $schedule)) {
        echo json_encode(['success' => true, 'token' => $schedule['token']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al regenerar el token']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
