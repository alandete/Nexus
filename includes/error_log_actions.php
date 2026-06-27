<?php
/**
 * Nexus 2.0 — Error Log Actions
 * Devuelve y limpia entradas del registro de errores.
 * Accesible a cualquier usuario con sesión iniciada.
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

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'fetch':
        $filters = [
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to']   ?? '',
            'level'     => $_GET['level']      ?? '',
        ];
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $result  = getErrorLog($filters, $page, 25);
        echo json_encode(['success' => true] + $result, JSON_UNESCAPED_UNICODE);
        break;

    case 'clear':
        if (!validateCsrf()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }
        clearErrorLog();
        logActivity('settings', 'clear', 'error_log');
        echo json_encode(['success' => true, 'message' => __('errors.clear_success')]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
