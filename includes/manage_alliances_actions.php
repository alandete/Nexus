<?php
/**
 * S4Learning - Gestión de Alianzas (CRUD)
 * Maneja creación, edición, eliminación de alianzas y gestión de archivos de apoyo
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Validar token CSRF
if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$currentUser = getCurrentUser();
$action = $_POST['action'] ?? '';

// ============================================================
// ACCIONES
// ============================================================

match ($action) {
    'create'      => handleCreate($currentUser),
    'update'      => handleUpdate($currentUser),
    'delete'      => handleDelete($currentUser),
    'upload-file' => handleUploadFile($currentUser),
    'delete-file' => handleDeleteFile($currentUser),
    default       => respond(false, 'Acción no válida'),
};

// ============================================================
// HANDLERS
// ============================================================

function handleCreate(array $currentUser): void
{
    if (!hasPermission($currentUser, 'settings', 'write')) {
        respond(false, 'Sin permisos para crear alianzas');
    }

    $name     = sanitize($_POST['name'] ?? '');
    $fullname = sanitize($_POST['fullname'] ?? '');

    if (empty($name) || empty($fullname)) {
        respond(false, 'Nombre y nombre completo son obligatorios');
    }

    // Generar slug desde el nombre
    $slug = generateSlug($name);
    if (empty($slug)) {
        respond(false, 'El nombre no genera un identificador válido');
    }

    $alliances = getAlliances();

    if (isset($alliances[$slug])) {
        respond(false, 'Ya existe una alianza con ese nombre');
    }

    $alliance = buildAllianceData($_POST);
    $alliance['sections']   = [];
    $alliance['files']      = [];
    $alliance['created_at'] = date('Y-m-d H:i:s');
    $alliance['updated_at'] = date('Y-m-d H:i:s');

    $alliances[$slug] = $alliance;
    saveAlliances($alliances);
    logActivity('manage_alliances', 'create', $slug);

    respond(true, __('manage_alliances.success_create'), ['slug' => $slug]);
}

function handleUpdate(array $currentUser): void
{
    if (!hasPermission($currentUser, 'settings', 'write')) {
        respond(false, 'Sin permisos para editar alianzas');
    }

    $slug = sanitize($_POST['slug'] ?? '');
    if (empty($slug)) {
        respond(false, 'Identificador de alianza requerido');
    }

    $alliances = getAlliances();
    if (!isset($alliances[$slug])) {
        respond(false, 'Alianza no encontrada');
    }

    $name     = sanitize($_POST['name'] ?? '');
    $fullname = sanitize($_POST['fullname'] ?? '');

    if (empty($name) || empty($fullname)) {
        respond(false, 'Nombre y nombre completo son obligatorios');
    }

    $updated = buildAllianceData($_POST);

    // Preservar campos que no se editan desde este módulo
    $updated['sections']   = $alliances[$slug]['sections'] ?? [];
    $updated['files']      = $alliances[$slug]['files'] ?? [];
    $updated['created_at'] = $alliances[$slug]['created_at'] ?? date('Y-m-d H:i:s');
    $updated['updated_at'] = date('Y-m-d H:i:s');

    // Si el nombre cambió, regenerar slug
    $newSlug = generateSlug($name);
    if ($newSlug && $newSlug !== $slug) {
        if (isset($alliances[$newSlug])) {
            respond(false, 'Ya existe una alianza con ese nombre');
        }
        // Mover archivos si existen
        $oldDir = ALLIANCES_UPLOADS_PATH . '/' . $slug;
        $newDir = ALLIANCES_UPLOADS_PATH . '/' . $newSlug;
        if (is_dir($oldDir)) {
            rename($oldDir, $newDir);
        }
        unset($alliances[$slug]);
        $alliances[$newSlug] = $updated;

        // Actualizar slug en BD si existe
        $db = getDB();
        if ($db) {
            $db->prepare("UPDATE alliances SET slug = ? WHERE slug = ?")->execute([$newSlug, $slug]);
        }
    } else {
        $alliances[$slug] = $updated;
    }

    saveAlliances($alliances);
    logActivity('manage_alliances', 'update', $newSlug ?: $slug);

    respond(true, __('manage_alliances.success_update'), ['slug' => $newSlug ?: $slug]);
}

function handleDelete(array $currentUser): void
{
    if (!hasPermission($currentUser, 'settings', 'delete')) {
        respond(false, 'Sin permisos para eliminar alianzas');
    }

    $slug = sanitize($_POST['slug'] ?? '');
    if (empty($slug)) {
        respond(false, 'Identificador de alianza requerido');
    }

    $alliances = getAlliances();
    if (!isset($alliances[$slug])) {
        respond(false, 'Alianza no encontrada');
    }

    // Eliminar archivos de apoyo del disco
    $uploadDir = ALLIANCES_UPLOADS_PATH . '/' . $slug;
    if (is_dir($uploadDir)) {
        $files = glob($uploadDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($uploadDir);
    }

    unset($alliances[$slug]);
    saveAlliances($alliances);
    logActivity('manage_alliances', 'delete', $slug);

    respond(true, __('manage_alliances.success_delete'));
}

function handleUploadFile(array $currentUser): void
{
    if (!hasPermission($currentUser, 'settings', 'write')) {
        respond(false, 'Sin permisos para subir archivos');
    }

    $slug = sanitize($_POST['slug'] ?? '');
    if (empty($slug)) {
        respond(false, 'Identificador de alianza requerido');
    }

    $alliances = getAlliances();
    if (!isset($alliances[$slug])) {
        respond(false, 'Alianza no encontrada');
    }

    $currentFiles = $alliances[$slug]['files'] ?? [];
    if (count($currentFiles) >= 5) {
        respond(false, __('manage_alliances.error_max_files'));
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        respond(false, 'No se recibió ningún archivo');
    }

    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        respond(false, __('manage_alliances.error_upload'));
    }

    // Validar tamaño (5 MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        respond(false, 'El archivo excede el tamaño máximo de 5 MB');
    }

    // Validar tipo MIME
    $allowedTypes = [
        'application/pdf',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',    // xlsx
        'application/vnd.ms-excel',                                              // xls
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document', // docx
        'application/msword',                                                    // doc
        'text/plain',                                                            // txt
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!in_array($mimeType, $allowedTypes, true)) {
        respond(false, 'Tipo de archivo no permitido. Use PDF, Excel, Word o TXT.');
    }

    // Crear directorio si no existe
    $uploadDir = ALLIANCES_UPLOADS_PATH . '/' . $slug;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Nombre seguro: slug_timestamp_original.ext
    $originalName = $file['name'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $filename = $slug . '_' . time() . '_' . $safeName . '.' . $ext;
    $destination = $uploadDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        respond(false, __('manage_alliances.error_upload'));
    }

    // Registrar en JSON
    $currentFiles[] = [
        'name'        => $originalName,
        'filename'    => $filename,
        'uploaded_at' => date('Y-m-d H:i:s'),
    ];

    $alliances[$slug]['files'] = $currentFiles;
    $alliances[$slug]['updated_at'] = date('Y-m-d H:i:s');
    saveAlliances($alliances);

    respond(true, __('manage_alliances.success_upload'), [
        'file' => end($currentFiles),
    ]);
}

function handleDeleteFile(array $currentUser): void
{
    if (!hasPermission($currentUser, 'settings', 'write')) {
        respond(false, 'Sin permisos para eliminar archivos');
    }

    $slug     = sanitize($_POST['slug'] ?? '');
    $filename = sanitize($_POST['filename'] ?? '');

    if (empty($slug) || empty($filename)) {
        respond(false, 'Datos incompletos');
    }

    $alliances = getAlliances();
    if (!isset($alliances[$slug])) {
        respond(false, 'Alianza no encontrada');
    }

    // Eliminar archivo del disco
    $filepath = ALLIANCES_UPLOADS_PATH . '/' . $slug . '/' . $filename;
    if (file_exists($filepath)) {
        unlink($filepath);
    }

    // Eliminar del array
    $alliances[$slug]['files'] = array_values(
        array_filter($alliances[$slug]['files'] ?? [], fn($f) => $f['filename'] !== $filename)
    );
    $alliances[$slug]['updated_at'] = date('Y-m-d H:i:s');
    saveAlliances($alliances);

    respond(true, __('manage_alliances.success_delete_file'));
}

// ============================================================
// UTILIDADES
// ============================================================

/**
 * Construye el array de datos de una alianza desde $_POST
 */
function buildAllianceData(array $post): array
{
    $active   = isset($post['active']) && ($post['active'] === '1' || $post['active'] === 'on');
    $billable = isset($post['billable']) && ($post['billable'] === '1' || $post['billable'] === 'on');

    return [
        'name'        => sanitize($post['name'] ?? ''),
        'fullname'    => sanitize($post['fullname'] ?? ''),
        'country'     => sanitize($post['country'] ?? ''),
        'color'       => sanitize($post['color'] ?? '#3B7DDD'),
        'website'     => sanitize($post['website'] ?? ''),
        'lms_url'     => sanitize($post['lms_url'] ?? ''),
        'manager'     => buildResponsible($post, 'manager'),
        'coordinator' => buildResponsible($post, 'coordinator'),
        'migrator'    => buildResponsible($post, 'migrator'),
        'active'      => $active,
        'billable'    => $billable,
    ];
}

/**
 * Construye los datos de un responsable (manager o coordinator)
 */
function buildResponsible(array $post, string $prefix): array
{
    $isUser   = ($post[$prefix . '_type'] ?? '') === 'user';
    $username = sanitize($post[$prefix . '_username'] ?? '');

    if ($isUser && !empty($username)) {
        // Resolver nombre y email del usuario
        $users = getUsers();
        $user  = $users[$username] ?? null;

        return [
            'name'     => $user ? $user['name'] : $username,
            'email'    => $user ? $user['email'] : '',
            'is_user'  => true,
            'username' => $username,
        ];
    }

    return [
        'name'     => sanitize($post[$prefix . '_name'] ?? ''),
        'email'    => sanitize($post[$prefix . '_email'] ?? ''),
        'is_user'  => false,
        'username' => null,
    ];
}

/**
 * Genera un slug desde un nombre
 */
function generateSlug(string $name): string
{
    $slug = mb_strtolower($name, 'UTF-8');
    // Reemplazar caracteres acentuados comunes
    $slug = strtr($slug, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ñ' => 'n', 'ü' => 'u',
    ]);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    return trim($slug, '-');
}

/**
 * Respuesta JSON estandarizada
 */
function respond(bool $success, string $message, array $extra = []): void
{
    echo json_encode(array_merge(
        ['success' => $success, 'message' => $message],
        $extra
    ), JSON_UNESCAPED_UNICODE);
    exit;
}
