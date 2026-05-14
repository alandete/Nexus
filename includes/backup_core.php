<?php
/**
 * Nexus 2.0 — Backup Core
 * Funciones puras de backup reutilizables por backup_actions.php y cron/backup_cron.php
 */

defined('APP_ACCESS') or die('Acceso directo no permitido');

// ============================================================
// EJECUCIÓN DIRECTA (sin contexto HTTP)
// ============================================================

/**
 * Crea un backup sin depender de sesión, $_POST ni salida HTTP.
 * Retorna array con success, message, filename, size.
 */
function ejecutarBackupDirecto(string $type = 'data', string $source = 'auto'): array
{
    if (!class_exists('ZipArchive')) {
        return ['success' => false, 'message' => 'La extensión ZipArchive no está disponible'];
    }

    if (!in_array($type, ['data', 'full'], true)) {
        $type = 'data';
    }

    if (!is_dir(BACKUP_PATH)) {
        mkdir(BACKUP_PATH, 0755, true);
    }

    $prefix   = $type === 'full' ? 'nexusapp-full-' : 'nexusapp-backup-';
    $filename = $prefix . date('Ymd-His') . '.zip';
    $filepath = BACKUP_PATH . '/' . $filename;

    $zip = new ZipArchive();
    if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return ['success' => false, 'message' => 'Error al crear el archivo ZIP'];
    }

    if ($type === 'full') {
        crearBackupCompleto($zip);
    } else {
        crearBackupDatos($zip);
    }

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $zip->setCompressionIndex($i, ZipArchive::CM_DEFLATE, 9);
    }

    $zip->close();
    rotateBackups($type);

    $sources = getSources();
    $sources[$filename] = $source;
    saveSources($sources);

    logActivity('backup', 'create', $filename);

    return [
        'success'  => true,
        'message'  => 'Backup creado exitosamente',
        'filename' => $filename,
        'size'     => formatFileSize(filesize($filepath)),
    ];
}

// ============================================================
// BACKUP DE DATOS
// ============================================================

function crearBackupDatos(ZipArchive $zip): void
{
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

    $templatesDir = BASE_PATH . '/templates';
    if (is_dir($templatesDir)) {
        addDirectoryToZip($zip, $templatesDir, 'templates');
    }

    if (isDBAvailable()) {
        $sqlDump = generarDumpSQL();
        if ($sqlDump) {
            $zip->addFromString('database/nexusapp.sql', $sqlDump);
        }
    }
}

// ============================================================
// BACKUP COMPLETO
// ============================================================

function crearBackupCompleto(ZipArchive $zip): void
{
    $basePath = realpath(BASE_PATH);

    $excludeDirs = array_filter([realpath(BACKUP_PATH)]);

    $excludePatterns = [
        '/^\.claude/',
        '/^\.git/',
        '/\.zip$/',
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $realItemPath = $item->getRealPath();

        foreach ($excludeDirs as $excludeDir) {
            if (strpos($realItemPath, $excludeDir) === 0) {
                continue 2;
            }
        }

        $relativePath = str_replace('\\', '/', substr($realItemPath, strlen($basePath) + 1));

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
// ROTACIÓN
// ============================================================

function rotateBackups(string $type = 'data'): void
{
    $pattern = $type === 'full'
        ? BACKUP_PATH . '/nexusapp-full-*.zip'
        : BACKUP_PATH . '/nexusapp-backup-*.zip';

    $files     = glob($pattern);
    $favorites = getFavorites();

    $nonFavorites = array_filter($files, fn($file) => !in_array(basename($file), $favorites, true));

    if (count($nonFavorites) <= MAX_BACKUPS) {
        return;
    }

    sort($nonFavorites);
    $toDelete = array_slice($nonFavorites, 0, count($nonFavorites) - MAX_BACKUPS);
    if ($toDelete) {
        $sources = getSources();
        foreach ($toDelete as $file) {
            unlink($file);
            unset($sources[basename($file)]);
        }
        saveSources($sources);
    }
}

// ============================================================
// FAVORITOS Y NOTAS
// ============================================================

function getSources(): array
{
    $file = BACKUP_PATH . '/sources.json';
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveSources(array $sources): void
{
    file_put_contents(BACKUP_PATH . '/sources.json', json_encode($sources, JSON_PRETTY_PRINT));
}

function getFavorites(): array
{
    $file = BACKUP_PATH . '/favorites.json';
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveFavorites(array $favorites): void
{
    file_put_contents(BACKUP_PATH . '/favorites.json', json_encode(array_values($favorites), JSON_PRETTY_PRINT));
}

function getNotes(): array
{
    $file = BACKUP_PATH . '/notes.json';
    if (!file_exists($file)) return [];
    $data = json_decode(file_get_contents($file), true);
    return is_array($data) ? $data : [];
}

function saveNotes(array $notes): void
{
    file_put_contents(BACKUP_PATH . '/notes.json', json_encode($notes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// ============================================================
// VALIDACIÓN Y UTILIDADES
// ============================================================

function validateBackupFilename(string $filename): bool
{
    return (bool) preg_match('/^nexusapp-(backup|full)-\d{8}-\d{6}\.zip$/', $filename);
}

function getBackupType(string $filename): string
{
    return strpos($filename, 'nexusapp-full-') === 0 ? 'full' : 'data';
}

function formatFileSize(int $bytes): string
{
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function addDirectoryToZip(ZipArchive $zip, string $dirPath, string $zipBasePath): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $relativePath = str_replace('\\', '/', $zipBasePath . '/' . $iterator->getSubPathname());
        if ($item->isDir()) {
            $zip->addEmptyDir($relativePath);
        } else {
            $zip->addFile($item->getRealPath(), $relativePath);
        }
    }
}

function deleteDirectoryContents(string $dir): bool
{
    if (!is_dir($dir)) return false;

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
    }

    return true;
}

// ============================================================
// BASE DE DATOS
// ============================================================

function generarDumpSQL(): ?string
{
    $db = getDB();
    if (!$db) return null;

    try {
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($tables)) return null;

        $dump  = "-- NexusApp Database Dump\n";
        $dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $dump .= "-- Tables: " . count($tables) . "\n";
        $dump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            $create = $db->query("SHOW CREATE TABLE `{$table}`")->fetch();
            $dump  .= "DROP TABLE IF EXISTS `{$table}`;\n";
            $dump  .= $create['Create Table'] . ";\n\n";

            $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll();
            if (!empty($rows)) {
                $cols    = array_keys($rows[0]);
                $colList = '`' . implode('`, `', $cols) . '`';
                foreach ($rows as $row) {
                    $values = array_map(fn($v) => $v === null ? 'NULL' : $db->quote($v), array_values($row));
                    $dump  .= "INSERT INTO `{$table}` ({$colList}) VALUES (" . implode(', ', $values) . ");\n";
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

function importarDumpSQL(string $sql): bool
{
    $db = getDB();
    if (!$db || empty(trim($sql))) return false;

    try {
        $db->exec("SET FOREIGN_KEY_CHECKS = 0");
        $statements = preg_split('/;\s*\n/', $sql);
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if (empty($stmt) || strpos($stmt, '--') === 0) continue;
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
