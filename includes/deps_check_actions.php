<?php
/**
 * Nexus — Verificación de dependencias del sistema (web)
 * Detecta Ghostscript, ImageMagick, Imagick y GD de forma cross-platform.
 * Escribe data/deps_check.json y devuelve el resultado como JSON.
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}
$currentUser = getCurrentUser();
if (($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo administradores']);
    exit;
}
if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

// ── Detección ─────────────────────────────────────────────────────────────────

$deps = [];

// GD (PHP extension)
if (extension_loaded('gd')) {
    $gdInfo = function_exists('gd_info') ? gd_info() : [];
    $deps['gd_ext']['installed'] = $gdInfo['GD Version'] ?? 'disponible';
}

// Imagick (PHP extension)
if (extension_loaded('imagick')) {
    try {
        $v = Imagick::getVersion();
        preg_match('/ImageMagick\s+([\d.\-]+)/', $v['versionString'] ?? '', $m);
        $deps['imagick_ext']['installed'] = $m[1] ?? 'disponible';
    } catch (Throwable $e) {
        $deps['imagick_ext']['installed'] = 'disponible';
    }
}

if (PHP_OS_FAMILY === 'Windows') {

    // Ghostscript — Windows
    foreach (['gswin64c', 'gswin32c', 'gs'] as $cmd) {
        $out = @shell_exec("where {$cmd} 2>&1");
        if ($out) {
            $line = trim(explode("\n", $out)[0]);
            if (@file_exists($line)) {
                $ver = @shell_exec('"' . $line . '" --version 2>&1');
                $deps['ghostscript']['installed'] = trim((string)$ver) ?: $cmd;
                break;
            }
        }
    }
    if (empty($deps['ghostscript'])) {
        foreach (['C:/Program Files/gs/gs*/bin/gswin64c.exe', 'C:/Program Files (x86)/gs/gs*/bin/gswin32c.exe'] as $p) {
            $matches = glob($p);
            if (!empty($matches)) {
                $ver = @shell_exec('"' . end($matches) . '" --version 2>&1');
                $deps['ghostscript']['installed'] = trim((string)$ver) ?: 'disponible';
                break;
            }
        }
    }

    // ImageMagick CLI — Windows
    foreach (['magick', 'convert'] as $cmd) {
        $out = @shell_exec("where {$cmd} 2>&1");
        if ($out) {
            $line = trim(explode("\n", $out)[0]);
            if (@file_exists($line)) {
                $ver = @shell_exec('"' . $line . '" --version 2>&1');
                preg_match('/ImageMagick\s+([\d.\-]+)/', (string)$ver, $m);
                $deps['imagemagick']['installed'] = $m[1] ?? 'disponible';
                break;
            }
        }
    }

} else {

    // Ghostscript — Linux/macOS
    $code = -1; $out = [];
    if (function_exists('exec'))       { @exec('gs --version 2>/dev/null', $out, $code); }
    if ($code !== 0 && function_exists('shell_exec')) {
        $raw = @shell_exec('gs --version 2>/dev/null');
        if (trim((string)$raw) !== '') { $out = [trim($raw)]; $code = 0; }
    }
    if ($code === 0 && !empty($out)) {
        $deps['ghostscript']['installed'] = trim($out[0]);
    }

    // ImageMagick CLI — Linux/macOS
    $code = -1; $out = [];
    if (function_exists('exec'))       { @exec('convert --version 2>/dev/null', $out, $code); }
    if ($code !== 0 && function_exists('exec'))  { @exec('magick --version 2>/dev/null', $out, $code); }
    if ($code !== 0 && function_exists('shell_exec')) {
        $raw = @shell_exec('convert --version 2>/dev/null') ?: @shell_exec('magick --version 2>/dev/null');
        if (trim((string)$raw) !== '') { $out = [trim($raw)]; $code = 0; }
    }
    if ($code === 0 && !empty($out)) {
        preg_match('/ImageMagick\s+([\d.\-]+)/', $out[0], $m);
        $deps['imagemagick']['installed'] = $m[1] ?? 'disponible';
    }
}

// ── Guardar cache ─────────────────────────────────────────────────────────────

$cache = [
    'checked_at' => date('Y-m-d H:i:s'),
    'php'        => phpversion(),
    'deps'       => $deps,
];
@file_put_contents(DATA_PATH . '/deps_check.json', json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode(['success' => true, 'deps' => $deps, 'checked_at' => $cache['checked_at']]);
