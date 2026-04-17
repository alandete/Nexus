<?php
/**
 * S4Learning -Sistema de Autenticación
 */

defined('APP_ACCESS') or die('Acceso directo no permitido');

/**
 * Cargar roles
 */
function getRoles() {
    $db = getDB();
    if ($db) {
        try {
            $rows = $db->query("SELECT * FROM roles")->fetchAll();
            if (!empty($rows)) {
                $roles = [];
                foreach ($rows as $row) {
                    $perms = json_decode($row['permissions'], true) ?? [];
                    $roles[$row['name']] = ['permissions' => $perms];
                }
                return $roles;
            }
        } catch (PDOException $e) {}
    }

    $rolesFile = DATA_PATH . '/roles.json';
    if (!file_exists($rolesFile)) return [];
    return json_decode(file_get_contents($rolesFile), true);
}

/**
 * Verificar si el usuario tiene un permiso específico
 */
function hasPermission($user, $module, $action) {
    // Admin siempre tiene todos los permisos
    if ($user['role'] === 'admin') {
        return true;
    }

    // Cargar roles
    $roles = getRoles();
    $userRole = $roles[$user['role']] ?? null;

    if (!$userRole) {
        return false;
    }

    // Verificar permiso específico
    $modulePermissions = $userRole['permissions'][$module] ?? [];

    // Si es un permiso booleano (backup, restore)
    if (is_bool($modulePermissions)) {
        return $modulePermissions;
    }

    // Si es un array de permisos
    return in_array($action, $modulePermissions);
}

/**
 * Verificar si el usuario puede acceder a un módulo
 */
function canAccessModule($user, $module) {
    return hasPermission($user, $module, 'read');
}

/**
 * Verificar si el usuario puede editar en un módulo
 */
function canEditModule($user, $module) {
    return hasPermission($user, $module, 'write');
}

/**
 * Verificar si el usuario puede eliminar en un módulo
 */
function canDeleteModule($user, $module) {
    return hasPermission($user, $module, 'delete');
}

/**
 * Verificar si el usuario puede editar su propio perfil
 * Todos los usuarios pueden editar su propio perfil
 */
function canEditOwnProfile($currentUser, $targetUserId) {
    return $currentUser['id'] == $targetUserId;
}

/**
 * Verificar si el usuario puede editar otros usuarios
 */
function canEditUsers($user) {
    return hasPermission($user, 'users', 'write');
}

/**
 * Iniciar sesión
 */
function login($username, $password) {
    $users = getUsers();

    // Verificar si el usuario existe
    if (!isset($users[$username])) {
        return ['success' => false, 'message' => 'Usuario o contraseña incorrectos'];
    }

    $user = $users[$username];

    // Verificar si el usuario está activo
    if (!$user['active']) {
        return ['success' => false, 'message' => 'Usuario desactivado'];
    }

    // Verificar contraseña
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Usuario o contraseña incorrectos'];
    }

    // Iniciar sesión
    $_SESSION['user'] = [
        'id' => $user['id'],
        'username' => $user['username'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'photo' => $user['photo'] ?? null,
        'lang' => $user['lang'] ?? 'es',
    ];

    // Cargar idioma preferido del usuario
    $_SESSION['lang'] = $user['lang'] ?? 'es';

    // Actualizar último login
    $users[$username]['last_login'] = date('Y-m-d H:i:s');
    saveUsers($users);

    return ['success' => true, 'message' => 'Sesión iniciada correctamente'];
}

/**
 * Cerrar sesión
 */
function logout() {
    unset($_SESSION['user']);
    session_destroy();
}

/**
 * Verificar si hay una sesión activa
 */
function isLoggedIn() {
    return isset($_SESSION['user']);
}

/**
 * Obtener usuario actual
 */
function getCurrentUser() {
    return $_SESSION['user'] ?? null;
}

/**
 * Requerir autenticación
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: ' . url('login'));
        exit;
    }
}

/**
 * Requerir rol específico
 */
function requireRole($role) {
    requireAuth();
    $user = getCurrentUser();
    if ($user['role'] !== $role) {
        header('Location: ' . url('home'));
        exit;
    }
}

/**
 * Requerir permiso específico
 */
function requirePermission($module, $action) {
    requireAuth();
    $user = getCurrentUser();
    if (!hasPermission($user, $module, $action)) {
        header('Location: ' . url('home'));
        exit;
    }
}
