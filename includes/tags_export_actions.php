<?php
/**
 * Nexus 2.0 — Exportar etiquetas como JSON
 */
define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo 'No autorizado';
    exit;
}

$db = getDB();
if (!$db) {
    http_response_code(500);
    echo 'Sin conexión a base de datos';
    exit;
}

$tags = $db->query("SELECT name, color FROM tags ORDER BY name")->fetchAll();

$filename = 'etiquetas-' . date('Y-m-d') . '.json';
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

echo json_encode($tags, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
exit;
