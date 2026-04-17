<?php
/**
 * S4Learning - Acciones de Información del Proyecto
 * Solo accesible para administradores
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Verificar sesión
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = getCurrentUser();

// Validar token CSRF
if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

// Solo administradores pueden modificar
if (!hasPermission($currentUser, 'settings', 'write')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

$action = $_POST['action'] ?? '';

/**
 * Procesa la subida del logo del proyecto
 */
function processLogoUpload(): ?string
{
    if (!isset($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES['logo'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }

    // Validar tamaño (2 MB)
    if ($file['size'] > 2 * 1024 * 1024) {
        return null;
    }

    // Validar tipo MIME
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes, true)) {
        return null;
    }

    // Extensión según MIME
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $ext = $extensions[$mimeType];

    // Crear directorio si no existe
    if (!is_dir(LOGOS_PATH)) {
        mkdir(LOGOS_PATH, 0755, true);
    }

    // Eliminar logo anterior (cualquier extensión)
    deleteLogo();

    // Guardar como logo.{ext}
    $filename = 'logo.' . $ext;
    $destination = LOGOS_PATH . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $filename;
    }

    return null;
}

/**
 * Elimina el logo del proyecto
 */
function deleteLogo(): void
{
    $extensions = ['jpg', 'png', 'webp'];
    foreach ($extensions as $ext) {
        $path = LOGOS_PATH . '/logo.' . $ext;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

/**
 * Procesa la subida del favicon
 */
function processFaviconUpload(): ?string
{
    if (!isset($_FILES['favicon']) || $_FILES['favicon']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES['favicon'];
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 512 * 1024) return null; // 512 KB

    $allowedTypes = ['image/png', 'image/svg+xml', 'image/x-icon', 'image/vnd.microsoft.icon'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedTypes, true)) return null;

    $extensions = [
        'image/png' => 'png',
        'image/svg+xml' => 'svg',
        'image/x-icon' => 'ico',
        'image/vnd.microsoft.icon' => 'ico',
    ];
    $ext = $extensions[$mimeType];

    if (!is_dir(LOGOS_PATH)) mkdir(LOGOS_PATH, 0755, true);
    deleteFavicon();

    $filename = 'favicon.' . $ext;
    $destination = LOGOS_PATH . '/' . $filename;
    if (move_uploaded_file($file['tmp_name'], $destination)) return $filename;
    return null;
}

function deleteFavicon(): void
{
    $extensions = ['png', 'svg', 'ico'];
    foreach ($extensions as $ext) {
        $path = LOGOS_PATH . '/favicon.' . $ext;
        if (file_exists($path)) unlink($path);
    }
}

function isValidHexColor(string $color): bool
{
    return (bool) preg_match('/^#[0-9A-Fa-f]{6}$/', $color);
}

function isValidTimezone(string $tz): bool
{
    return in_array($tz, timezone_identifiers_list(), true);
}

// Guardar información del proyecto
if ($action === 'save_privacy') {
    $data = getProjectInfo();
    $data['privacy_mode'] = ($_POST['privacy_mode'] ?? '0') === '1';
    saveProjectInfo($data);
    logActivity('settings', 'update', 'privacy_mode:' . ($data['privacy_mode'] ? 'on' : 'off'));
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'save') {
    $info = getProjectInfo();

    // ============ IDENTIDAD ============
    $info['app_name']        = sanitize($_POST['app_name'] ?? $info['app_name']);
    $info['tagline']         = sanitize($_POST['tagline'] ?? '');
    $info['description']     = sanitize($_POST['description'] ?? '');

    if (empty($info['app_name'])) $info['app_name'] = 'Nexus';

    // Colores de marca
    $brandColor = trim($_POST['brand_color'] ?? '');
    if ($brandColor !== '' && !isValidHexColor($brandColor)) {
        echo json_encode(['success' => false, 'message' => __('application.err_brand_color')]);
        exit;
    }
    $info['brand_color'] = $brandColor ?: null;

    $accentColor = trim($_POST['accent_color'] ?? '');
    if ($accentColor !== '' && !isValidHexColor($accentColor)) {
        echo json_encode(['success' => false, 'message' => __('application.err_accent_color')]);
        exit;
    }
    $info['accent_color'] = $accentColor ?: null;

    // Logo
    if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
        deleteLogo();
        $info['logo'] = null;
    } else {
        $logo = processLogoUpload();
        if ($logo !== null) $info['logo'] = $logo;
    }

    // Favicon
    if (isset($_POST['remove_favicon']) && $_POST['remove_favicon'] === '1') {
        deleteFavicon();
        $info['favicon'] = null;
    } else {
        $favicon = processFaviconUpload();
        if ($favicon !== null) $info['favicon'] = $favicon;
    }

    // ============ EMPRESA ============
    $info['company_name']    = sanitize($_POST['company_name'] ?? '');
    $info['company_address'] = sanitize($_POST['company_address'] ?? '');
    $info['contact_phone']   = sanitize($_POST['contact_phone'] ?? '');

    $email = trim($_POST['contact_email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => __('application.err_email')]);
        exit;
    }
    $info['contact_email'] = $email;

    $website = trim($_POST['website'] ?? '');
    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => __('application.err_website')]);
        exit;
    }
    $info['website'] = $website;

    // ============ OPERACION ============
    $timezone = trim($_POST['timezone'] ?? 'America/Bogota');
    if (!isValidTimezone($timezone)) {
        echo json_encode(['success' => false, 'message' => __('application.err_timezone')]);
        exit;
    }
    $info['timezone'] = $timezone;

    $defaultLang = trim($_POST['default_lang'] ?? 'es');
    if (!in_array($defaultLang, ['es', 'en'], true)) $defaultLang = 'es';
    $info['default_lang'] = $defaultLang;

    // Modo mantenimiento
    $info['maintenance_mode']    = ($_POST['maintenance_mode'] ?? '0') === '1';
    $info['maintenance_message'] = sanitize($_POST['maintenance_message'] ?? '');

    // Lista de IPs permitidas (una por linea)
    $ipsRaw = $_POST['maintenance_allowed_ips'] ?? '';
    $ips = array_filter(array_map('trim', explode("\n", str_replace("\r", '', $ipsRaw))));
    $validIps = [];
    foreach ($ips as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP)) $validIps[] = $ip;
    }
    $info['maintenance_allowed_ips'] = $validIps;

    // ============ PRIVACIDAD ============
    $info['privacy_mode'] = ($_POST['privacy_mode'] ?? '0') === '1';

    $info['updated_at'] = date('Y-m-d H:i:s');

    if (saveProjectInfo($info)) {
        logActivity('application', 'update');
        echo json_encode(['success' => true, 'message' => __('application.success_save')]);
    } else {
        echo json_encode(['success' => false, 'message' => __('application.err_save')]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
