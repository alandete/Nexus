<?php
/**
 * Nexus 2.0 — CSRF token refresh
 *
 * GET: devuelve el token CSRF activo de la sesión.
 * Llamado automáticamente por el interceptor JS cuando detecta un 403 CSRF.
 */
define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

echo json_encode(['success' => true, 'token' => $_SESSION['csrf_token']]);
