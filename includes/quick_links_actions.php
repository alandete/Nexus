<?php
/**
 * Nexus 2.0 — Quick Links Actions
 * Gestiona los accesos rápidos del topbar por usuario (máx. 5 ítems).
 */
define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$currentUser = getCurrentUser();
$username    = $currentUser['username'] ?? '';

const MAX_QUICK_LINKS = 5;

$allowedPages = [
    'tasks', 'alliances', 'utilities-gift', 'utilities-pdf', 'utilities-images', 'utilities-rise',
    'reports', 'documentation', 'users', 'manage-alliances', 'manage-tasks',
    'application', 'integrations', 'snapshots', 'system', 'activity',
];

$action     = $_POST['action'] ?? '';
$targetPage = trim($_POST['page'] ?? '');

if ($action !== 'toggle' || !in_array($targetPage, $allowedPages, true)) {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
    exit;
}

$links = getQuickLinks($username);

if (in_array($targetPage, $links, true)) {
    $links = array_values(array_filter($links, fn($p) => $p !== $targetPage));
    saveQuickLinks($links, $username);
    echo json_encode(['success' => true, 'action' => 'removed', 'links' => $links]);
    exit;
}

if (count($links) < MAX_QUICK_LINKS) {
    $links[] = $targetPage;
    saveQuickLinks($links, $username);
    echo json_encode(['success' => true, 'action' => 'added', 'links' => $links]);
    exit;
}

// Límite alcanzado: reemplazar el más antiguo (primero en el array)
$removed = $links[0];
array_shift($links);
$links[] = $targetPage;
saveQuickLinks($links, $username);
echo json_encode(['success' => true, 'action' => 'replaced', 'removed' => $removed, 'links' => $links]);
