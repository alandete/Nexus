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

/**
 * Genera un token de recuperación de contraseña (64 hex chars, 24h de validez).
 * Añade las columnas necesarias si aún no existen.
 */
function generatePasswordResetToken(string $username): ?string
{
    $db = getDB();
    if (!$db) return null;

    // Garantizar que las columnas existen (tolerante a "duplicate column" si ya existen)
    try { $db->exec("ALTER TABLE users ADD COLUMN reset_token VARCHAR(100) DEFAULT NULL"); }
    catch (PDOException $e) { /* ya existe */ }
    try { $db->exec("ALTER TABLE users ADD COLUMN reset_expires DATETIME DEFAULT NULL"); }
    catch (PDOException $e) { /* ya existe */ }

    try {
        $check = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $check->execute([$username]);
        if (!$check->fetch()) return null;

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE username = ?")
           ->execute([$token, $expires, $username]);

        return $token;
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Busca usuario por email y genera token de recuperación.
 * Siempre retorna true por seguridad (no revelar si el email existe).
 */
function requestPasswordReset(string $email): bool
{
    $db = getDB();
    if (!$db) return false;

    try {
        $stmt = $db->prepare("SELECT username, name, lang FROM users WHERE LOWER(email) = ? AND active = 1 LIMIT 1");
        $stmt->execute([strtolower(trim($email))]);
        $user = $stmt->fetch();
        if (!$user) return true;

        $token = generatePasswordResetToken($user['username']);
        if ($token) {
            sendPasswordResetEmail($email, $user['name'], $token, $user['lang'] ?? 'es');
        }
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Valida un token de recuperación. Retorna el username o null si inválido/expirado.
 */
function validateResetToken(string $token): ?string
{
    if (empty($token)) return null;

    $db = getDB();
    if (!$db) return null;

    try {
        $stmt = $db->prepare("SELECT username FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ? $row['username'] : null;
    } catch (PDOException $e) {
        return null; // columnas aún no existen
    }
}

/**
 * Restablece la contraseña usando un token válido. Invalida el token al usarlo.
 */
function resetPassword(string $token, string $newPassword): bool
{
    $db = getDB();
    if (!$db) return false;

    if (!validateResetToken($token)) return false;
    if (strlen($newPassword) < 6) return false;

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    try {
        $stmt = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE reset_token = ?");
        $stmt->execute([$hash, $token]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}
