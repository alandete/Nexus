<?php
/**
 * S4Learning - Acciones de Usuarios (CRUD)
 * Maneja creación, edición y eliminación de usuarios
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');

// Verificar sesión
if (!isLoggedIn()) {
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

/**
 * Procesa la subida de una foto de perfil
 * @return string|null Nombre del archivo guardado o null si no hay foto
 */
function processPhotoUpload(string $username): ?string
{
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES['photo'];

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
    if (!is_dir(AVATARS_PATH)) {
        mkdir(AVATARS_PATH, 0755, true);
    }

    // Eliminar foto anterior del usuario (cualquier extensión)
    deleteUserPhoto($username);

    // Guardar con nombre del usuario
    $filename = $username . '.' . $ext;
    $destination = AVATARS_PATH . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $filename;
    }

    return null;
}

/**
 * Elimina la foto de perfil de un usuario
 */
function deleteUserPhoto(string $username): void
{
    $extensions = ['jpg', 'png', 'webp'];
    foreach ($extensions as $ext) {
        $path = AVATARS_PATH . '/' . $username . '.' . $ext;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

// Crear usuario
if ($action === 'create') {
    if (!hasPermission($currentUser, 'users', 'write')) {
        echo json_encode(['success' => false, 'message' => 'No tiene permisos para crear usuarios']);
        exit;
    }

    $username = sanitize($_POST['username'] ?? '');
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitize($_POST['role'] ?? 'viewer');

    // Validaciones
    if (empty($username) || empty($name) || empty($email) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email inválido']);
        exit;
    }

    $users = getUsers();

    // Verificar si el usuario ya existe
    if (isset($users[$username])) {
        echo json_encode(['success' => false, 'message' => 'El nombre de usuario ya existe']);
        exit;
    }

    // Verificar si el email ya existe
    foreach ($users as $user) {
        if ($user['email'] === $email) {
            echo json_encode(['success' => false, 'message' => 'El email ya está registrado']);
            exit;
        }
    }

    // Obtener el siguiente ID
    $maxId = 0;
    foreach ($users as $user) {
        if ($user['id'] > $maxId) {
            $maxId = $user['id'];
        }
    }
    $newId = $maxId + 1;

    // Procesar foto de perfil
    $photo = processPhotoUpload($username);

    // Idioma preferido
    $userLang = in_array($_POST['lang'] ?? '', ['es', 'en'], true) ? $_POST['lang'] : 'es';

    // Crear nuevo usuario
    $users[$username] = [
        'id' => $newId,
        'username' => $username,
        'password' => password_hash($password, PASSWORD_BCRYPT),
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'photo' => $photo,
        'lang' => $userLang,
        'active' => true,
        'created_at' => date('Y-m-d H:i:s'),
        'last_login' => null
    ];

    if (saveUsers($users)) {
        logActivity('users', 'create', $username);
        echo json_encode(['success' => true, 'message' => 'Usuario creado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar el usuario']);
    }
    exit;
}

// Editar usuario
if ($action === 'update') {
    if (!hasPermission($currentUser, 'users', 'write')) {
        echo json_encode(['success' => false, 'message' => 'No tiene permisos para editar usuarios']);
        exit;
    }

    $username = sanitize($_POST['username'] ?? '');
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = sanitize($_POST['role'] ?? 'viewer');

    // Validaciones
    if (empty($username) || empty($name) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Nombre y email son obligatorios']);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email inválido']);
        exit;
    }

    $users = getUsers();

    // Verificar si el usuario existe
    if (!isset($users[$username])) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }

    // Verificar si el email ya existe en otro usuario
    foreach ($users as $uname => $user) {
        if ($uname !== $username && $user['email'] === $email) {
            echo json_encode(['success' => false, 'message' => 'El email ya está registrado']);
            exit;
        }
    }

    // Actualizar datos
    $users[$username]['name'] = $name;
    $users[$username]['email'] = $email;

    // Proteccion: el admin no puede cambiar su propio rol ni desactivarse
    $isSelf = ($currentUser['username'] ?? '') === $username;
    if (!$isSelf) {
        $users[$username]['role'] = $role;
        $users[$username]['active'] = isset($_POST['active']) && $_POST['active'] === '1';
    }

    // Idioma preferido
    $userLang = in_array($_POST['lang'] ?? '', ['es', 'en'], true) ? $_POST['lang'] : ($users[$username]['lang'] ?? 'es');
    $users[$username]['lang'] = $userLang;

    // Quitar foto si se solicitó
    if (isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1') {
        deleteUserPhoto($username);
        $users[$username]['photo'] = null;
    } else {
        // Procesar nueva foto si se subió
        $photo = processPhotoUpload($username);
        if ($photo !== null) {
            $users[$username]['photo'] = $photo;
        }
    }

    // Actualizar contraseña solo si se proporcionó una nueva
    if (!empty($password)) {
        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
            exit;
        }
        $users[$username]['password'] = password_hash($password, PASSWORD_BCRYPT);
    }

    // Actualizar sesión si el usuario se editó a sí mismo
    if ($username === $currentUser['username']) {
        $_SESSION['user']['name'] = $name;
        $_SESSION['user']['email'] = $email;
        $_SESSION['user']['role'] = $role;
        $_SESSION['user']['photo'] = $users[$username]['photo'] ?? null;
        $_SESSION['user']['lang'] = $userLang;
        $_SESSION['lang'] = $userLang;
    }

    if (saveUsers($users)) {
        logActivity('users', 'update', $username);
        echo json_encode(['success' => true, 'message' => 'Usuario actualizado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar el usuario']);
    }
    exit;
}

// Eliminar usuario
if ($action === 'delete') {
    if (!hasPermission($currentUser, 'users', 'delete')) {
        echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar usuarios']);
        exit;
    }

    $username = sanitize($_POST['username'] ?? '');

    // No permitir eliminar el propio usuario
    if ($username === $currentUser['username']) {
        echo json_encode(['success' => false, 'message' => 'No puede eliminar su propio usuario']);
        exit;
    }

    $users = getUsers();

    // Verificar si el usuario existe
    if (!isset($users[$username])) {
        echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        exit;
    }

    // Eliminar foto de perfil
    deleteUserPhoto($username);

    // Eliminar usuario
    unset($users[$username]);

    if (saveUsers($users)) {
        logActivity('users', 'delete', $username);
        echo json_encode(['success' => true, 'message' => 'Usuario eliminado exitosamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el usuario']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
