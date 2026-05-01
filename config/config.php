<?php
/**
 * S4Learning -Configuración Principal
 */

defined('APP_ACCESS') or die('Acceso directo no permitido');

// Configuración de zona horaria
date_default_timezone_set('America/Bogota');

// Configuración de sesión segura
$isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly'  => true,
    'samesite'  => 'Strict',
]);
session_start();

// Regenerar ID de sesión periódicamente para prevenir fijación de sesión
if (!isset($_SESSION['_created'])) {
    $_SESSION['_created'] = time();
} elseif (time() - $_SESSION['_created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['_created'] = time();
}

// Configuración de idioma
$lang = $_SESSION['lang'] ?? 'es';

// Rutas
define('BASE_PATH', __DIR__ . '/..');

// URL base de la app (fiable desde cualquier script, incluyendo endpoints en includes/)
if (!defined('APP_BASE_URL')) {
    $scheme  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $docRoot = rtrim(str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
    $appRoot = rtrim(str_replace('\\', '/', (string) realpath(BASE_PATH)), '/');
    $webPath = $docRoot !== '' ? str_replace($docRoot, '', $appRoot) : '';
    define('APP_BASE_URL', $scheme . '://' . $host . $webPath);
}
define('LANG_PATH', BASE_PATH . '/lang');
define('BACKUP_PATH', BASE_PATH . '/backups');
define('DATA_PATH', BASE_PATH . '/data');

// Configuración de backups (máximo 3 versiones)
define('MAX_BACKUPS', 3);

// Usuarios del sistema
define('USERS_FILE',      DATA_PATH . '/users.json');
define('ROLES_FILE',      DATA_PATH . '/roles.json');
define('ALLIANCES_FILE',  DATA_PATH . '/alliances.json');

// Plantillas Jinja2 de alianzas
define('TEMPLATES_PATH',  BASE_PATH . '/templates');

// Directorio de uploads (fotos de perfil)
define('AVATARS_PATH',    BASE_PATH . '/assets/uploads/avatars');

// Información del proyecto
define('PROJECTINFO_FILE', DATA_PATH . '/projectinfo.json');
define('LOGOS_PATH',       BASE_PATH . '/assets/uploads/logos');

// Uploads de alianzas (archivos de apoyo)
define('ALLIANCES_UPLOADS_PATH', BASE_PATH . '/assets/uploads/alliances');

// Registro de actividad
define('ACTIVITY_LOG_FILE', DATA_PATH . '/activity_log.json');
define('MAX_LOG_ENTRIES',   1000);

// Configuracion de APIs externas
define('API_SETTINGS_FILE', DATA_PATH . '/api_settings.json');

// Clave de encriptacion para datos sensibles (unica por instalacion)
// Se carga desde config/secret.php (gitignored)
if (!defined('APP_SECRET_KEY')) {
    $secretFile = __DIR__ . '/secret.php';
    if (file_exists($secretFile)) {
        require_once $secretFile;
    } else {
        define('APP_SECRET_KEY', 'CHANGE_ME_GENERATE_A_RANDOM_64_CHAR_HEX_STRING');
    }
}

// Cargar archivos de idioma (se fusionan todos los .php del directorio)
$translations = [];
$langDir = LANG_PATH . "/{$lang}";
if (is_dir($langDir)) {
    foreach (glob("{$langDir}/*.php") as $langFile) {
        $partial = require $langFile;
        if (is_array($partial)) {
            $translations = array_replace_recursive($translations, $partial);
        }
    }
}

// Cargar sistema de autenticación
require_once BASE_PATH . '/includes/auth.php';
