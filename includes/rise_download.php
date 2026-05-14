<?php
/**
 * Nexus 2.0 — Rise Download
 * Sirve el ZIP corregido a partir de un token de sesión y lo elimina tras la descarga.
 */
define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    http_response_code(403);
    exit;
}

$token = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');
$entry = $_SESSION['rise_downloads'][$token] ?? null;

if (!$entry || $entry['expires'] < time() || !file_exists($entry['path'])) {
    http_response_code(404);
    echo 'Enlace de descarga no válido o expirado.';
    exit;
}

$path     = $entry['path'];
$filename = basename($entry['name']);

unset($_SESSION['rise_downloads'][$token]);

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . addslashes($filename) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

readfile($path);
@unlink($path);
exit;
