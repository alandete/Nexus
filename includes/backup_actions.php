<?php
/**
 * S4Learning - Backup Actions Handler
 * Maneja creación, restauración, descarga y eliminación de backups
 *
 * Tipos de backup:
 *   - data: datos JSON (users, roles, alliances) + plantillas HTML
 *   - full: proyecto completo (excluye backups/ y archivos temporales)
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

// Download usa GET; las demás acciones usan POST
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Solo download usa GET, el resto responde JSON
if ($action !== 'download') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

// Validar token CSRF (excepto download que es lectura via GET)
if ($action !== 'download' && !validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

// ============================================================
// ACCIONES
// ============================================================

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

    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'La extensión ZipArchive no está disponible']);
        exit;
    }

    $type = $_POST['type'] ?? 'data';
    if (!in_array($type, ['data', 'full'], true)) {
        $type = 'data';
    }

    // Asegurar que el directorio de backups exista
    if (!is_dir(BACKUP_PATH)) {
        mkdir(BACKUP_PATH, 0755, true);
    }

    $prefix   = $type === 'full' ? 'nexusapp-full-' : 'nexusapp-backup-';
    $filename = $prefix . date('Ymd-His') . '.zip';
    $filepath = BACKUP_PATH . '/' . $filename;

    $zip = new ZipArchive();
    if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        echo json_encode(['success' => false, 'message' => 'Error al crear el archivo ZIP']);
        exit;
    }

    if ($type === 'full') {
        crearBackupCompleto($zip);
    } else {
        crearBackupDatos($zip);
    }

    // Compresión máxima (DEFLATE nivel 9) para cada archivo del ZIP
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $zip->setCompressionIndex($i, ZipArchive::CM_DEFLATE, 9);
    }

    $zip->close();

    // Rotar backups antiguos (por tipo)
    rotateBackups($type);

    // Guardar notas si se proporcionaron
    $notes = trim($_POST['notes'] ?? '');
    if ($notes !== '') {
        $allNotes = getNotes();
        $allNotes[$filename] = $notes;
        saveNotes($allNotes);
    }

    logActivity('backup', 'create', $filename);

    echo json_encode([
        'success'  => true,
        'message'  => 'Backup creado exitosamente',
        'filename' => $filename,
        'size'     => formatFileSize(filesize($filepath)),
    ]);
}

/**
 * Backup de datos: archivos JSON + plantillas HTML
 */
function crearBackupDatos(ZipArchive $zip): void
{
    // Archivos de datos JSON
    $dataFiles = [
        'data/users.json'       => USERS_FILE,
        'data/roles.json'       => ROLES_FILE,
        'data/alliances.json'   => ALLIANCES_FILE,
        'data/projectinfo.json' => PROJECTINFO_FILE,
    ];

    foreach ($dataFiles as $zipPath => $realPath) {
        if (file_exists($realPath)) {
            $zip->addFile($realPath, $zipPath);
        }
    }

    // Directorio de plantillas
    $templatesDir = BASE_PATH . '/templates';
    if (is_dir($templatesDir)) {
        addDirectoryToZip($zip, $templatesDir, 'templates');
    }

    // Dump de base de datos MySQL (si disponible)
    if (isDBAvailable()) {
        $sqlDump = generarDumpSQL();
        if ($sqlDump) {
            $zip->addFromString('database/nexusapp.sql', $sqlDump);
        }
    }
}

/**
 * Backup completo: todo el proyecto excepto backups/ y archivos temporales
 */
function crearBackupCompleto(ZipArchive $zip): void
{
    $basePath = realpath(BASE_PATH);

    // Directorios a excluir
    $excludeDirs = [
        realpath(BACKUP_PATH),           // backups/
    ];
    // Eliminar falsos (por si el directorio no existe)
    $excludeDirs = array_filter($excludeDirs);

    // Archivos a excluir por patrón
    $excludePatterns = [
        '/^\.claude/',     // archivos de Claude
        '/^\.git/',        // repositorio git
        '/\.zip$/',        // archivos zip sueltos en raíz
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $realItemPath = $item->getRealPath();

        // Excluir directorios completos
        foreach ($excludeDirs as $excludeDir) {
            if (strpos($realItemPath, $excludeDir) === 0) {
                continue 2;
            }
        }

        // Ruta relativa para el ZIP
        $relativePath = str_replace('\\', '/', substr($realItemPath, strlen($basePath) + 1));

        // Excluir por patrón
        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $relativePath)) {
                continue 2;
            }
        }

        if ($item->isDir()) {
            $zip->addEmptyDir($relativePath);
        } else {
            $zip->addFile($realItemPath, $relativePath);
        }
    }
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

    // Solo se pueden restaurar backups de datos desde la UI
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

    // Verificar que el ZIP contenga al menos los archivos esperados
    if ($zip->locateName('data/users.json') === false || $zip->locateName('data/roles.json') === false) {
        $zip->close();
        echo json_encode(['success' => false, 'message' => 'El archivo ZIP no contiene un backup válido']);
        exit;
    }

    // Limpiar directorio de plantillas antes de restaurar
    $templatesDir = BASE_PATH . '/templates';
    if (is_dir($templatesDir)) {
        deleteDirectoryContents($templatesDir);
    }

    // Extraer archivos de forma segura (sin extractTo directo)
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entryName = $zip->getNameIndex($i);

        // Seguridad: rechazar path traversal y rutas absolutas
        if (strpos($entryName, '..') !== false || $entryName[0] === '/' || $entryName[0] === '\\') {
            continue;
        }

        // Solo extraer prefijos conocidos
        if (strncmp($entryName, 'data/', 5) !== 0 && strncmp($entryName, 'templates/', 10) !== 0) {
            continue;
        }

        // Si es un directorio
        if (substr($entryName, -1) === '/') {
            $dirPath = BASE_PATH . '/' . $entryName;
            if (!is_dir($dirPath)) {
                mkdir($dirPath, 0755, true);
            }
            continue;
        }

        // Extraer archivo
        $targetPath = BASE_PATH . '/' . $entryName;
        $parentDir = dirname($targetPath);

        if (!is_dir($parentDir)) {
            mkdir($parentDir, 0755, true);
        }

        $content = $zip->getFromIndex($i);
        if ($content !== false) {
            file_put_contents($targetPath, $content);
        }
    }

    // Restaurar dump SQL si existe en el backup y hay BD disponible
    $sqlDump = $zip->getFromName('database/nexusapp.sql');
    $dbRestored = false;
    if ($sqlDump && isDBAvailable()) {
        $dbRestored = importarDumpSQL($sqlDump);
    }

    $zip->close();

    logActivity('backup', 'restore', $filename);

    $msg = 'Backup restaurado exitosamente';
    if ($sqlDump && $dbRestored) {
        $msg .= ' (incluida base de datos)';
    } elseif ($sqlDump && !$dbRestored) {
        $msg .= ' (base de datos no se pudo restaurar)';
    }

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
        // Quitar de favoritos si estaba marcado
        $favorites = getFavorites();
        if (in_array($filename, $favorites, true)) {
            $favorites = array_values(array_diff($favorites, [$filename]));
            saveFavorites($favorites);
        }
        // Quitar notas si existían
        $allNotes = getNotes();
        if (isset($allNotes[$filename])) {
            unset($allNotes[$filename]);
            saveNotes($allNotes);
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

    $filepath = BACKUP_PATH . '/' . $filename;
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Archivo no encontrado']);
        exit;
    }

    $favorites = getFavorites();
    $isFavorite = in_array($filename, $favorites, true);

    if ($isFavorite) {
        $favorites = array_values(array_diff($favorites, [$filename]));
        $isFavorite = false;
    } else {
        $favorites[] = $filename;
        $isFavorite = true;
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
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
        echo json_encode(['success' => false, 'message' => 'Sin permisos para descargar backups']);
        exit;
    }

    $filename = $_GET['filename'] ?? '';

    if (!validateBackupFilename($filename)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
        echo json_encode(['success' => false, 'message' => 'Nombre de archivo no válido']);
        exit;
    }

    $filepath = BACKUP_PATH . '/' . $filename;

    if (!file_exists($filepath)) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
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

// ============================================================
// FUNCIONES AUXILIARES
// ============================================================

/**
 * Valida que el nombre de archivo siga alguno de los patrones esperados
 * nexusapp-backup-YYYYMMDD-HHmmss.zip (datos)
 * nexusapp-full-YYYYMMDD-HHmmss.zip   (completo)
 */
function validateBackupFilename(string $filename): bool
{
    return (bool) preg_match('/^nexusapp-(backup|full)-\d{8}-\d{6}\.zip$/', $filename);
}

/**
 * Determina el tipo de backup según el nombre del archivo
 */
function getBackupType(string $filename): string
{
    return strpos($filename, 'nexusapp-full-') === 0 ? 'full' : 'data';
}

/**
 * Formatea tamaño de archivo en unidades legibles
 */
function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 1) . ' MB';
    }
    if ($bytes >= 1024) {
        return round($bytes / 1024, 1) . ' KB';
    }
    return $bytes . ' B';
}

/**
 * Elimina backups excedentes por tipo, conservando solo MAX_BACKUPS de cada tipo.
 * Los backups marcados como favoritos se excluyen del conteo y no se eliminan.
 */
function rotateBackups(string $type = 'data'): void
{
    $pattern = $type === 'full'
        ? BACKUP_PATH . '/nexusapp-full-*.zip'
        : BACKUP_PATH . '/nexusapp-backup-*.zip';

    $files = glob($pattern);
    $favorites = getFavorites();

    // Filtrar favoritos: solo rotar los NO favoritos
    $nonFavorites = array_filter($files, function ($file) use ($favorites) {
        return !in_array(basename($file), $favorites, true);
    });

    if (count($nonFavorites) <= MAX_BACKUPS) {
        return;
    }

    sort($nonFavorites); // Más antiguos primero (por timestamp en nombre)
    $toDelete = array_slice($nonFavorites, 0, count($nonFavorites) - MAX_BACKUPS);
    foreach ($toDelete as $file) {
        unlink($file);
    }
}

/**
 * Lee la lista de backups favoritos
 */
function getFavorites(): array
{
    $file = BACKUP_PATH . '/favorites.json';
    if (!file_exists($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

/**
 * Guarda la lista de backups favoritos
 */
function saveFavorites(array $favorites): void
{
    $file = BACKUP_PATH . '/favorites.json';
    file_put_contents($file, json_encode(array_values($favorites), JSON_PRETTY_PRINT));
}

/**
 * Lee las notas de los backups
 */
function getNotes(): array
{
    $file = BACKUP_PATH . '/notes.json';
    if (!file_exists($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

/**
 * Guarda las notas de los backups
 */
function saveNotes(array $notes): void
{
    $file = BACKUP_PATH . '/notes.json';
    file_put_contents($file, json_encode($notes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Agrega un directorio completo al archivo ZIP de forma recursiva
 */
function addDirectoryToZip(ZipArchive $zip, string $dirPath, string $zipBasePath): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relativePath = $zipBasePath . '/' . $iterator->getSubPathname();
        // Normalizar a barras hacia adelante para compatibilidad ZIP
        $relativePath = str_replace('\\', '/', $relativePath);

        if ($item->isDir()) {
            $zip->addEmptyDir($relativePath);
        } else {
            $zip->addFile($item->getRealPath(), $relativePath);
        }
    }
}

/**
 * Elimina todo el contenido de un directorio (sin eliminar el directorio raíz)
 */
function deleteDirectoryContents(string $dir): bool
{
    if (!is_dir($dir)) {
        return false;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getRealPath());
        } else {
            unlink($item->getRealPath());
        }
    }

    return true;
}

/**
 * Genera un dump SQL de todas las tablas de la BD (sin depender de mysqldump CLI)
 */
function generarDumpSQL(): ?string
{
    $db = getDB();
    if (!$db) return null;

    try {
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) return null;

        $dump = "-- NexusApp Database Dump\n";
        $dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $dump .= "-- Tables: " . count($tables) . "\n";
        $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            // CREATE TABLE
            $create = $db->query("SHOW CREATE TABLE `{$table}`")->fetch();
            $dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $dump .= $create['Create Table'] . ";\n\n";

            // INSERT rows
            $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll();
            if (!empty($rows)) {
                $cols = array_keys($rows[0]);
                $colList = '`' . implode('`, `', $cols) . '`';

                foreach ($rows as $row) {
                    $values = array_map(function ($v) use ($db) {
                        if ($v === null) return 'NULL';
                        return $db->quote($v);
                    }, array_values($row));
                    $dump .= "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(', ', $values) . ");\n";
                }
                $dump .= "\n";
            }
        }

        $dump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        return $dump;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Importa un dump SQL en la BD actual
 */
function importarDumpSQL(string $sql): bool
{
    $db = getDB();
    if (!$db || empty(trim($sql))) return false;

    try {
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");

        // Separar por sentencias (split en ; seguido de salto de linea)
        $statements = preg_split('/;\s*\n/', $sql);

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt) || strpos($stmt, '--') === 0) continue;
            // Ignorar SET que ya ejecutamos
            if (stripos($stmt, 'SET FOREIGN_KEY_CHECKS') === 0) continue;
            $db->exec($stmt);
        }

        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        return true;
    } catch (PDOException $e) {
        $db->exec("SET FOREIGN_KEY_CHECKS = 1");
        return false;
    }
}
