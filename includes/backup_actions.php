<?php
/**
 * Nexus 2.0 — Backup Actions Handler
 * Maneja creación, restauración, descarga y eliminación de backups
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/backup_core.php';

// Verificar sesión
if (!isLoggedIn()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = getCurrentUser();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action !== 'download') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

if ($action !== 'download' && !validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

switch ($action) {
    case 'create':
        crearBackup($currentUser);
        break;
    case 'restore':
        restaurarBackup($currentUser);
        break;
    case 'delete':
        eliminarBackup($currentUser);
        break;
    case 'download':
        descargarBackup($currentUser);
        break;
    case 'favorite':
        toggleFavorite($currentUser);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

// ============================================================
// CREAR BACKUP
// ============================================================

function crearBackup(array $currentUser): void
{
    if (!hasPermission($currentUser, 'backup', null)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sin permisos para crear backups']);
        exit;
    }

    $type   = $_POST['type'] ?? 'data';
    $result = ejecutarBackupDirecto($type, 'manual');

    if ($result['success']) {
        $notes = trim($_POST['notes'] ?? '');
        if ($notes !== '') {
            $allNotes = getNotes();
            $allNotes[$result['filename']] = $notes;
            saveNotes($allNotes);
        }
    }

    echo json_encode($result);
}

// ============================================================
// RESTAURAR BACKUP (solo backups de datos)
// ============================================================

function restaurarBackup(array $currentUser): void
{
    if (!hasPermission($currentUser, 'restore', null)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sin permisos para restaurar backups']);
        exit;
    }

    $filename = $_POST['filename'] ?? '';

    if (!validateBackupFilename($filename)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nombre de archivo no válido']);
        exit;
    }

    if (getBackupType($filename) !== 'data') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Solo se pueden restaurar copias de datos. Las copias completas se restauran manualmente.']);
        exit;
    }

    $filepath = BACKUP_PATH . '/' . $filename;

    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Archivo de backup no encontrado']);
        exit;
    }

    $zip = new ZipArchive();
    if ($zip->open($filepath) !== true) {
        echo json_encode(['success' => false, 'message' => 'Error al abrir el archivo ZIP']);
        exit;
    }

    if ($zip->locateName('data/users.json') === false || $zip->locateName('data/roles.json') === false) {
        $zip->close();
        echo json_encode(['success' => false, 'message' => 'El archivo ZIP no contiene un backup válido']);
        exit;
    }

    $templatesDir = BASE_PATH . '/templates';
    if (is_dir($templatesDir)) {
        deleteDirectoryContents($templatesDir);
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = $zip->getNameIndex($i);

        if (strpos($entryName, '..') !== false || $entryName[0] === '/' || $entryName[0] === '\\') {
            continue;
        }

        if (strncmp($entryName, 'data/', 5) !== 0 && strncmp($entryName, 'templates/', 10) !== 0) {
            continue;
        }

        if (substr($entryName, -1) === '/') {
            $dirPath = BASE_PATH . '/' . $entryName;
            if (!is_dir($dirPath)) mkdir($dirPath, 0755, true);
            continue;
        }

        $targetPath = BASE_PATH . '/' . $entryName;
        $parentDir  = dirname($targetPath);
        if (!is_dir($parentDir)) mkdir($parentDir, 0755, true);

        $content = $zip->getFromIndex($i);
        if ($content !== false) {
            file_put_contents($targetPath, $content);
        }
    }

    $sqlDump   = $zip->getFromName('database/nexusapp.sql');
    $dbRestored = false;
    if ($sqlDump && isDBAvailable()) {
        $dbRestored = importarDumpSQL($sqlDump);
    }

    $zip->close();

    logActivity('backup', 'restore', $filename);

    $msg = 'Backup restaurado exitosamente';
    if ($sqlDump && $dbRestored)  $msg .= ' (incluida base de datos)';
    elseif ($sqlDump && !$dbRestored) $msg .= ' (base de datos no se pudo restaurar)';

    echo json_encode(['success' => true, 'message' => $msg]);
}

// ============================================================
// ELIMINAR BACKUP
// ============================================================

function eliminarBackup(array $currentUser): void
{
    if (!hasPermission($currentUser, 'backup', null)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sin permisos para eliminar backups']);
        exit;
    }

    $filename = $_POST['filename'] ?? '';

    if (!validateBackupFilename($filename)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nombre de archivo no válido']);
        exit;
    }

    $filepath = BACKUP_PATH . '/' . $filename;

    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Archivo de backup no encontrado']);
        exit;
    }

    if (unlink($filepath)) {
        $favorites = getFavorites();
        if (in_array($filename, $favorites, true)) {
            saveFavorites(array_values(array_diff($favorites, [$filename])));
        }
        $allNotes = getNotes();
        if (isset($allNotes[$filename])) {
            unset($allNotes[$filename]);
            saveNotes($allNotes);
        }
        $sources = getSources();
        if (isset($sources[$filename])) {
            unset($sources[$filename]);
            saveSources($sources);
        }
        logActivity('backup', 'delete', $filename);
        echo json_encode(['success' => true, 'message' => 'Backup eliminado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el backup']);
    }
}

// ============================================================
// TOGGLE FAVORITO
// ============================================================

function toggleFavorite(array $currentUser): void
{
    if (!hasPermission($currentUser, 'backup', null)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sin permisos']);
        exit;
    }

    $filename = $_POST['filename'] ?? '';

    if (!validateBackupFilename($filename)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nombre de archivo no válido']);
        exit;
    }

    if (!file_exists(BACKUP_PATH . '/' . $filename)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Archivo no encontrado']);
        exit;
    }

    $favorites  = getFavorites();
    $isFavorite = in_array($filename, $favorites, true);

    if ($isFavorite) {
        $favorites  = array_values(array_diff($favorites, [$filename]));
        $isFavorite = false;
    } else {
        $favorites[]  = $filename;
        $isFavorite   = true;
    }

    saveFavorites($favorites);
    echo json_encode(['success' => true, 'favorited' => $isFavorite]);
}

// ============================================================
// DESCARGAR BACKUP
// ============================================================

function descargarBackup(array $currentUser): void
{
    if (!hasPermission($currentUser, 'backup', null)) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Sin permisos para descargar backups']);
        exit;
    }

    $filename = $_GET['filename'] ?? '';

    if (!validateBackupFilename($filename)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Nombre de archivo no válido']);
        exit;
    }

    $filepath = BACKUP_PATH . '/' . $filename;

    if (!file_exists($filepath)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Archivo no encontrado']);
        exit;
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: no-cache, must-revalidate');
    readfile($filepath);
    exit;
}
