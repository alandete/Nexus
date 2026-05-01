<?php
/**
 * Nexus 2.0 — Password Reset Actions
 * Endpoint para solicitar / usar / generar links de recuperación.
 */
define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

runMigrations();

if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$action = $_POST['action'] ?? '';

// ── Solicitar recuperación por email ──────────────────────────────────────
if ($action === 'request') {
    $email = trim($_POST['email'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Correo inválido']);
        exit;
    }
    requestPasswordReset($email);
    echo json_encode(['success' => true]);
    exit;
}

// ── Restablecer contraseña con token ─────────────────────────────────────
if ($action === 'reset') {
    $token    = trim($_POST['token']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirm']       ?? '';

    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Token inválido']);
        exit;
    }
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
        exit;
    }
    if ($password !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden']);
        exit;
    }

    $ok = resetPassword($token, $password);
    echo json_encode($ok
        ? ['success' => true]
        : ['success' => false, 'message' => 'El enlace ha expirado o ya fue utilizado']
    );
    exit;
}

// ── Generar enlace manual (solo admin) ───────────────────────────────────
if ($action === 'generate_link') {
    if (!isLoggedIn() || (getCurrentUser()['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sin permisos']);
        exit;
    }

    $username = trim($_POST['username'] ?? '');
    if (empty($username)) {
        echo json_encode(['success' => false, 'message' => 'Usuario inválido']);
        exit;
    }

    $token = generatePasswordResetToken($username);
    if (!$token) {
        echo json_encode(['success' => false, 'message' => 'No se pudo generar el enlace. Verifica que el usuario exista.']);
        exit;
    }

    $resetUrl = rtrim(APP_BASE_URL, '/') . '/?page=reset-password&token=' . urlencode($token);

    logActivity('users', 'reset_link_generated', $username);
    echo json_encode(['success' => true, 'url' => $resetUrl]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Acción no válida']);
