<?php
/**
 * Nexus — Exportar alianzas como JSON
 * GET: includes/alliance_export_actions.php
 * Solo requiere sesión activa + rol admin/editor.
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit('No autorizado');
}

$currentUser = getCurrentUser();
if (!canAccessModule($currentUser, 'settings')) {
    http_response_code(403);
    exit('Sin permisos');
}

$alliances = getAlliances();

// Limpiar rutas de archivos locales — no son válidas en otro servidor
foreach ($alliances as $slug => &$a) {
    if (!empty($a['files'])) {
        foreach ($a['files'] as &$file) {
            unset($file['path']); // ruta local no portable
        }
        unset($file);
    }
}
unset($a);

$filename = 'alianzas-' . date('Y-m-d') . '.json';
$json     = json_encode($alliances, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($json));
header('Cache-Control: no-store');

echo $json;
