<?php
/**
 * S4Learning - Cleanup Actions Handler
 * Escanea y elimina archivos temporales de procesamiento y el log de actividad
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

// Verificar sesión
if (!isLoggedIn()) {
    header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = getCurrentUser();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Validar CSRF
if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

// Verificar permiso de backup
if (!hasPermission($currentUser, 'backup', null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso para esta acción']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'scan':  cleanupScan();  break;
    case 'clean': cleanupClean(); break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción inválida']);
}

// ── Escanear ────────────────────────────────────────────────────────────────
function cleanupScan(): void
{
    $tempPath = BASE_PATH . '/temp';
    $logPath  = BASE_PATH . '/data/activity_log.json';

    $tempCount = 0;
    $tempSize  = 0;

    if (is_dir($tempPath)) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempPath, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iter as $file) {
            if ($file->isFile()) {
                $tempCount++;
                $tempSize += $file->getSize();
            }
        }
    }

    $logEntries = 0;
    $logSize    = 0;
    if (file_exists($logPath)) {
        $logSize = filesize($logPath);
        $logData = json_decode(file_get_contents($logPath), true);
        $logEntries = is_array($logData) ? count($logData) : 0;
    }

    echo json_encode([
        'success'     => true,
        'temp_count'  => $tempCount,
        'temp_size'   => $tempSize,
        'log_entries' => $logEntries,
        'log_size'    => $logSize,
    ]);
}

// ── Limpiar ─────────────────────────────────────────────────────────────────
function cleanupClean(): void
{
    $tempPath = BASE_PATH . '/temp';
    $logPath  = BASE_PATH . '/data/activity_log.json';

    // Eliminar archivos en temp/
    $deleted = 0;
    if (is_dir($tempPath)) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempPath, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iter as $file) {
            if ($file->isFile()) {
                unlink($file->getPathname());
                $deleted++;
            } elseif ($file->isDir()) {
                @rmdir($file->getPathname());
            }
        }
    }

    // Vaciar log de actividad
    file_put_contents($logPath, json_encode([], JSON_PRETTY_PRINT));

    logActivity('cleanup', 'clean', "{$deleted} archivos temporales eliminados");

    echo json_encode(['success' => true, 'deleted' => $deleted]);
}
