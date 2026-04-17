<?php
/**
 * S4Learning - Reports Actions Handler
 * Devuelve entradas del log de actividad con filtros y paginacion.
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

// Verificar sesion
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = getCurrentUser();

// Solo admin puede acceder a reportes
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'fetch':
        $filters = [
            'date_from' => $_GET['date_from'] ?? '',
            'date_to'   => $_GET['date_to'] ?? '',
            'user'      => $_GET['user'] ?? '',
            'module'    => $_GET['module'] ?? '',
            'action'    => $_GET['action_filter'] ?? '',
        ];
        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 25;

        $result = getActivityLog($filters, $page, $perPage);
        echo json_encode(['success' => true] + $result, JSON_UNESCAPED_UNICODE);
        break;

    case 'clear':
        if (!validateCsrf()) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
            exit;
        }
        file_put_contents(ACTIVITY_LOG_FILE, '[]', LOCK_EX);
        logActivity('reports', 'clear', '');
        echo json_encode(['success' => true, 'message' => __('reports.success_clear')]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}
