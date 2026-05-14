<?php
/**
 * Nexus 2.0 — Rise Patch Actions
 * Estrategia: escanea solo los JS, copia el ZIP original íntegro y reemplaza
 * únicamente los archivos parcheados. Nunca lee binarios grandes en memoria PHP.
 */
define('APP_ACCESS', true);
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

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
if (!hasPermission($currentUser, 'utilities', 'write')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

set_time_limit(0);

register_shutdown_function(function () {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_USER_ERROR], true)) {
        while (ob_get_level()) ob_end_clean();
        echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e['message']]);
    }
});

$action = $_POST['action'] ?? '';

define('RISE_PATRON',    'window.parent.RiseLMSInterface');
define('RISE_PARCHE',    '(function(){try{return window.parent.RiseLMSInterface}catch(e){return void 0}})()');
define('RISE_MAX_BYTES', 200 * 1024 * 1024);

match ($action) {
    'patch'  => risePatch($currentUser),
    default  => riseOut(['success' => false, 'message' => 'Acción no válida']),
};

// ─────────────────────────────────────────────────────────────────────────────

function riseOut(array $data): void
{
    ob_end_clean();
    echo json_encode($data);
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────

function risePatch(array $currentUser): void
{
    if (!class_exists('ZipArchive')) {
        riseOut(['success' => false, 'message' => 'La extensión ZipArchive no está disponible en este servidor']);
    }

    $f = $_FILES['zip_file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        $msg = match ($f['error'] ?? -1) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido',
            UPLOAD_ERR_NO_FILE => 'No se recibió ningún archivo',
            default            => 'Error al recibir el archivo',
        };
        riseOut(['success' => false, 'message' => $msg]);
    }

    if ($f['size'] > RISE_MAX_BYTES) {
        riseOut(['success' => false, 'message' => 'El archivo supera el límite de 200 MB']);
    }

    $originalName = pathinfo($f['name'], PATHINFO_FILENAME);

    $zipIn = new ZipArchive();
    if ($zipIn->open($f['tmp_name']) !== true) {
        riseOut(['success' => false, 'message' => 'El archivo no es un ZIP válido']);
    }

    $total          = $zipIn->numFiles;
    $alreadyPatched = false;
    $patches        = [];

    error_log('[Rise] zip abierto: ' . $total . ' entradas, ' . $f['size'] . ' bytes');

    // ── Escanear solo archivos JS pequeños (< 2 MB) ───────────────────────────
    for ($i = 0; $i < $total; $i++) {
        $name = $zipIn->getNameIndex($i);
        if (!$name || !str_ends_with(strtolower($name), '.js')) continue;

        $stat = $zipIn->statIndex($i);
        if (!$stat || ($stat['size'] ?? 0) > 2 * 1024 * 1024) continue;

        $content = $zipIn->getFromIndex($i);
        if ($content === false) continue;

        if (str_contains($content, RISE_PARCHE)) { $alreadyPatched = true; continue; }
        if (str_contains($content, RISE_PATRON)) {
            $patches[$name] = str_replace(RISE_PATRON, RISE_PARCHE, $content);
        }
    }

    $zipIn->close();
    error_log('[Rise] escaneo completo: ' . count($patches) . ' parches');

    $modified = array_keys($patches);

    // ── Resultados sin modificaciones ─────────────────────────────────────────
    if (empty($modified) && $alreadyPatched) {
        riseOut(['success' => true, 'status' => 'already_patched', 'files_modified' => [], 'message' => __('utilities.rise_status_already')]);
    }

    if (empty($modified)) {
        riseOut(['success' => true, 'status' => 'not_applicable', 'files_modified' => [], 'message' => __('utilities.rise_status_na')]);
    }

    // ── Construir ZIP corregido ───────────────────────────────────────────────
    $lang     = $currentUser['lang'] ?? 'es';
    $suffix   = $lang === 'en' ? '_fixed' : '_corregido';
    $filename = $originalName . $suffix . '.zip';

    $baseTemp = DATA_PATH . '/tmp';
    if (!is_dir($baseTemp)) mkdir($baseTemp, 0755, true);
    $outPath = $baseTemp . '/nexusrise_out_' . uniqid() . '.zip';

    // rename() es O(1) en el mismo disco — no copia datos, no activa Defender
    error_log('[Rise] iniciando rename a: ' . $outPath);
    if (!rename($f['tmp_name'], $outPath)) {
        error_log('[Rise] rename falló, intentando copy');
        if (!copy($f['tmp_name'], $outPath)) {
            riseOut(['success' => false, 'message' => 'No se pudo crear el archivo de salida']);
        }
    }
    error_log('[Rise] archivo base listo');

    $zipOut = new ZipArchive();
    if ($zipOut->open($outPath) !== true) {
        @unlink($outPath);
        riseOut(['success' => false, 'message' => 'No se pudo abrir el archivo de salida para modificación']);
    }

    foreach ($patches as $entryName => $patchedContent) {
        $zipOut->addFromString($entryName, $patchedContent);
    }

    error_log('[Rise] cerrando zip de salida...');
    $zipOut->close();
    error_log('[Rise] zip cerrado OK');

    // ── Token de descarga ─────────────────────────────────────────────────────
    $token = bin2hex(random_bytes(16));
    $_SESSION['rise_downloads'][$token] = [
        'path'    => $outPath,
        'name'    => $filename,
        'expires' => time() + 600,
    ];

    riseOut([
        'success'        => true,
        'status'         => 'patched',
        'files_modified' => $modified,
        'filename'       => $filename,
        'token'          => $token,
        'message'        => sprintf(__('utilities.rise_status_patched'), count($modified)),
    ]);
}
