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

    // Sanitizar campos de texto
    $info['app_name']        = sanitize($_POST['app_name'] ?? $info['app_name']);
    $info['tagline']         = sanitize($_POST['tagline'] ?? '');
    $info['description']     = sanitize($_POST['description'] ?? '');
    $info['company_name']    = sanitize($_POST['company_name'] ?? '');
    $info['company_address'] = sanitize($_POST['company_address'] ?? '');
    $info['contact_phone']   = sanitize($_POST['contact_phone'] ?? '');

    // Validar email si no está vacío
    $email = trim($_POST['contact_email'] ?? '');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email no válido']);
        exit;
    }
    $info['contact_email'] = $email;

    // Validar URL si no está vacía
    $website = trim($_POST['website'] ?? '');
    if ($website !== '' && !filter_var($website, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'message' => 'URL no válida']);
        exit;
    }
    $info['website'] = $website;

    // Nombre de app no puede estar vacío
    if (empty($info['app_name'])) {
        $info['app_name'] = 'S4Learning';
    }

    // Procesar logo
    if (isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
        deleteLogo();
        $info['logo'] = null;
    } else {
        $logo = processLogoUpload();
        if ($logo !== null) {
            $info['logo'] = $logo;
        }
    }

    $info['updated_at'] = date('Y-m-d H:i:s');

    if (saveProjectInfo($info)) {
        logActivity('projectinfo', 'update');
        echo json_encode(['success' => true, 'message' => __('projectinfo.success_save')]);
    } else {
        echo json_encode(['success' => false, 'message' => __('projectinfo.error_save')]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
