<?php
/**
 * NexusApp - Diagnóstico del sistema
 * Ejecuta verificaciones y retorna resultados con sugerencias de solución
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo administradores']);
    exit;
}

if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

// ═════════════════════════════════════════════════════════════════════════════
// VERIFICACIONES
// ═════════════════════════════════════════════════════════════════════════════

$checks = [];

// ── Entorno ─────────────────────────────────────────────────────────────────

$phpVersion = PHP_VERSION;
$phpOk = version_compare($phpVersion, '8.0.0', '>=');
$checks[] = [
    'category' => 'Entorno',
    'name'     => 'Versión de PHP',
    'status'   => $phpOk ? 'ok' : 'error',
    'detail'   => "PHP {$phpVersion}",
    'fix'      => $phpOk ? null : 'Se requiere PHP 8.0 o superior. Actualice PHP en su servidor.',
];

$requiredExts = ['json', 'openssl', 'gd', 'session', 'zip', 'curl', 'pdo', 'pdo_mysql'];
foreach ($requiredExts as $ext) {
    $loaded = extension_loaded($ext);
    $checks[] = [
        'category' => 'Entorno',
        'name'     => "Extensión {$ext}",
        'status'   => $loaded ? 'ok' : 'error',
        'detail'   => $loaded ? 'Cargada' : 'No disponible',
        'fix'      => $loaded ? null : "Active la extensión {$ext} en php.ini y reinicie el servidor web.",
    ];
}

$writeDirs = [
    'data'             => DATA_PATH,
    'backups'          => BACKUP_PATH,
    'avatars'          => BASE_PATH . '/assets/uploads/avatars',
    'logos'            => BASE_PATH . '/assets/uploads/logos',
    'alliances uploads' => BASE_PATH . '/assets/uploads/alliances',
];
foreach ($writeDirs as $label => $path) {
    $writable = is_dir($path) && is_writable($path);
    $checks[] = [
        'category' => 'Entorno',
        'name'     => "Escritura en /{$label}",
        'status'   => $writable ? 'ok' : 'error',
        'detail'   => $writable ? 'Permitida' : 'Sin permisos',
        'fix'      => $writable ? null : "Otorgue permisos de escritura a la carpeta {$path} (chmod 755 o equivalente).",
    ];
}

// ── Base de datos ───────────────────────────────────────────────────────────

$dbConfigExists = file_exists(BASE_PATH . '/config/database.php');
$checks[] = [
    'category' => 'Base de datos',
    'name'     => 'Archivo de configuración',
    'status'   => $dbConfigExists ? 'ok' : 'warning',
    'detail'   => $dbConfigExists ? 'config/database.php encontrado' : 'No existe',
    'fix'      => $dbConfigExists ? null : 'Copie config/database.example.php como config/database.php y configure las credenciales de su base de datos.',
];

$dbConnected = isDBAvailable();
$checks[] = [
    'category' => 'Base de datos',
    'name'     => 'Conexión',
    'status'   => $dbConnected ? 'ok' : ($dbConfigExists ? 'error' : 'warning'),
    'detail'   => $dbConnected ? 'Conectada' : 'No disponible',
    'fix'      => $dbConnected ? null : 'Verifique las credenciales en config/database.php. Asegúrese de que MySQL esté corriendo y que el usuario tenga permisos sobre la base de datos.',
];

if ($dbConnected) {
    $db = getDB();

    // Tablas esperadas
    $expectedTables = ['migrations', 'users', 'roles', 'activity_log', 'alliances', 'tasks', 'time_entries'];
    $existingTables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $missingTables = array_diff($expectedTables, $existingTables);

    $checks[] = [
        'category' => 'Base de datos',
        'name'     => 'Tablas',
        'status'   => empty($missingTables) ? 'ok' : 'error',
        'detail'   => empty($missingTables)
            ? count($existingTables) . ' tablas creadas'
            : 'Faltan: ' . implode(', ', $missingTables),
        'fix'      => empty($missingTables) ? null : 'Recargue la aplicación para que las migraciones se ejecuten automáticamente. Si el problema persiste, verifique los permisos del usuario de base de datos.',
    ];

    // Migraciones pendientes
    $executed = $db->query("SELECT name FROM migrations")->fetchAll(PDO::FETCH_COLUMN);
    $allMigrations = array_keys(getMigrations());
    $pending = array_diff($allMigrations, $executed);

    $checks[] = [
        'category' => 'Base de datos',
        'name'     => 'Migraciones',
        'status'   => empty($pending) ? 'ok' : 'warning',
        'detail'   => empty($pending)
            ? count($executed) . ' ejecutadas, ninguna pendiente'
            : count($pending) . ' pendientes: ' . implode(', ', $pending),
        'fix'      => empty($pending) ? null : 'Recargue la aplicación. Las migraciones pendientes se ejecutan automáticamente al cargar.',
    ];
}

// ── Datos ───────────────────────────────────────────────────────────────────

$dataFiles = [
    'users.json'     => USERS_FILE,
    'roles.json'     => ROLES_FILE,
    'alliances.json' => ALLIANCES_FILE,
    'projectinfo.json' => PROJECTINFO_FILE,
];
foreach ($dataFiles as $label => $path) {
    $exists = file_exists($path);
    $valid = false;
    $count = 0;
    if ($exists) {
        $data = json_decode(file_get_contents($path), true);
        $valid = is_array($data);
        $count = $valid ? count($data) : 0;
    }
    $checks[] = [
        'category' => 'Datos',
        'name'     => $label,
        'status'   => $exists && $valid ? 'ok' : ($exists ? 'error' : 'warning'),
        'detail'   => $exists
            ? ($valid ? "{$count} registros" : 'JSON inválido')
            : 'No existe',
        'fix'      => ($exists && $valid) ? null
            : ($exists ? "El archivo {$label} contiene JSON inválido. Restaure desde una copia de seguridad."
                       : "El archivo {$label} no existe. Cree uno vacío o restaure desde backup."),
    ];
}

// Verificar que exista al menos un admin
$users = getUsers();
$adminCount = 0;
foreach ($users as $u) {
    if (($u['role'] ?? '') === 'admin' && !empty($u['active'])) $adminCount++;
}
$checks[] = [
    'category' => 'Datos',
    'name'     => 'Usuario administrador',
    'status'   => $adminCount > 0 ? 'ok' : 'error',
    'detail'   => $adminCount > 0 ? "{$adminCount} admin(s) activo(s)" : 'Sin administradores activos',
    'fix'      => $adminCount > 0 ? null : 'No hay usuarios administradores activos. Edite data/users.json manualmente para activar o crear un usuario con rol "admin".',
];

// ── Seguridad ───────────────────────────────────────────────────────────────

$defaultKey = 'nexusapp-s4l-secret-key-change-me-2024';
$keyChanged = defined('APP_SECRET_KEY') && APP_SECRET_KEY !== $defaultKey;
$checks[] = [
    'category' => 'Seguridad',
    'name'     => 'Clave de encriptación',
    'status'   => $keyChanged ? 'ok' : 'warning',
    'detail'   => $keyChanged ? 'Personalizada' : 'Usando clave por defecto',
    'fix'      => $keyChanged ? null : 'Cambie APP_SECRET_KEY en config/config.php por una clave única. La clave por defecto es insegura para producción.',
];

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
$checks[] = [
    'category' => 'Seguridad',
    'name'     => 'HTTPS',
    'status'   => $isHttps ? 'ok' : 'warning',
    'detail'   => $isHttps ? 'Activo' : 'No detectado',
    'fix'      => $isHttps ? null : 'Se recomienda HTTPS en producción para proteger credenciales y sesiones. En desarrollo local no es necesario.',
];

// ── Dependencias opcionales ─────────────────────────────────────────────────

$optDeps = [
    ['Imagick (PHP)', extension_loaded('imagick'), 'Optimización avanzada de imágenes. Active la extensión imagick en cPanel > Select PHP Version.'],
    ['GD (PHP)',      extension_loaded('gd'),       'Procesamiento básico de imágenes. Active la extensión gd en cPanel > Select PHP Version.'],
    ['Ghostscript',  (function() {
        if (PHP_OS_FAMILY === 'Windows') {
            foreach (['gswin64c', 'gswin32c', 'gs'] as $cmd) {
                $out = @shell_exec("where {$cmd} 2>&1");
                if ($out) { $line = trim(explode("\n", $out)[0]); if (file_exists($line)) return true; }
            }
            foreach (['C:/Program Files/gs/gs*/bin/gswin64c.exe', 'C:/Program Files (x86)/gs/gs*/bin/gswin32c.exe'] as $p) {
                if (!empty(glob($p))) return true;
            }
        } else {
            // Intentar con shell_exec primero
            $out = @shell_exec('which gs 2>/dev/null');
            if ($out && file_exists(trim($out))) return true;
            // Fallback: rutas comunes en Linux (por si shell_exec está deshabilitado en web)
            foreach (['/usr/bin/gs', '/usr/local/bin/gs', '/bin/gs'] as $path) {
                if (file_exists($path)) return true;
            }
        }
        return false;
    })(), 'Optimización de PDF vía línea de comandos. Solicítelo al administrador del servidor.'],
    ['ImageMagick CLI', (function() {
        if (PHP_OS_FAMILY === 'Windows') {
            $out = @shell_exec('where magick 2>&1') ?: @shell_exec('where convert 2>&1');
            if ($out) { $path = trim(explode("\n", $out)[0]); if (file_exists($path)) return true; }
        } else {
            $out = @shell_exec('which magick 2>/dev/null') ?: @shell_exec('which convert 2>/dev/null');
            if ($out && file_exists(trim($out))) return true;
            // Fallback: rutas comunes en Linux
            foreach (['/usr/bin/convert', '/usr/local/bin/convert', '/usr/bin/magick', '/usr/local/bin/magick'] as $path) {
                if (file_exists($path)) return true;
            }
        }
        return false;
    })(), 'Optimización de imágenes vía línea de comandos. Solicítelo al administrador del servidor.'],
];
foreach ($optDeps as [$name, $available, $fix]) {
    $checks[] = [
        'category' => 'Dependencias opcionales',
        'name'     => $name,
        'status'   => $available ? 'ok' : 'info',
        'detail'   => $available ? 'Disponible' : 'No instalada',
        'fix'      => $available ? null : $fix,
    ];
}

// ═════════════════════════════════════════════════════════════════════════════
// RESULTADO
// ═════════════════════════════════════════════════════════════════════════════

$summary = ['ok' => 0, 'warning' => 0, 'error' => 0, 'info' => 0];
foreach ($checks as $c) {
    $summary[$c['status']]++;
}

// Cachear resultado
$cacheFile = DATA_PATH . '/diagnostics_cache.json';
$cacheData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'summary'   => $summary,
    'checks'    => $checks,
];
@file_put_contents($cacheFile, json_encode($cacheData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);

logActivity('settings', 'diagnostics', 'ok:' . $summary['ok'] . ' warn:' . $summary['warning'] . ' err:' . $summary['error']);

echo json_encode([
    'success' => true,
    'summary' => $summary,
    'checks'  => $checks,
]);
