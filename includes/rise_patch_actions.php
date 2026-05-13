<?php
/**
 * Nexus 2.0 — Rise Patch Actions
 * Corrige paquetes Rise Web para iframes cross-origin en Moodle
 * POST action=patch  → JSON con status, files_modified y data (ZIP base64)
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

$action = $_POST['action'] ?? '';

define('RISE_PATRON', 'window.parent.RiseLMSInterface');
define('RISE_PARCHE', '(function(){try{return window.parent.RiseLMSInterface}catch(e){return void 0}})()');
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
        riseOut(['success' => false, 'message' => 'La extensión ZipArchive de PHP no está disponible en este servidor']);
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
        riseOut(['success' => false, 'message' => 'El archivo supera el límite de 100 MB']);
    }

    $originalName = pathinfo($f['name'], PATHINFO_FILENAME);

    // Directorio temporal dentro de DATA_PATH (escribible por Apache)
    $baseTemp = DATA_PATH . '/tmp';
    if (!is_dir($baseTemp)) mkdir($baseTemp, 0755, true);
    $tmpDir = $baseTemp . '/nexusrise_' . uniqid();
    if (!mkdir($tmpDir, 0755, true)) {
        riseOut(['success' => false, 'message' => 'No se pudo crear directorio temporal']);
    }

    $response = null;

    try {
        // Extraer ZIP
        $zip = new ZipArchive();
        if ($zip->open($f['tmp_name']) !== true) {
            $response = ['success' => false, 'message' => 'El archivo no es un ZIP válido'];
            return;
        }
        $zip->extractTo($tmpDir);
        $zip->close();

        // Buscar y parchear .js
        $modified       = [];
        $alreadyPatched = false;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'js') continue;

            $content = file_get_contents($file->getPathname());
            if ($content === false) continue;

            if (str_contains($content, RISE_PARCHE)) {
                $alreadyPatched = true;
                continue;
            }

            if (str_contains($content, RISE_PATRON)) {
                $fixed = str_replace(RISE_PATRON, RISE_PARCHE, $content);
                file_put_contents($file->getPathname(), $fixed);
                $modified[] = substr($file->getPathname(), strlen($tmpDir) + 1);
            }
        }

        // Determinar estado sin modificaciones
        if (empty($modified) && $alreadyPatched) {
            $response = ['success' => true, 'status' => 'already_patched', 'files_modified' => [], 'message' => __('utilities.rise_status_already')];
            return;
        }

        if (empty($modified) && !$alreadyPatched) {
            $response = ['success' => true, 'status' => 'not_applicable', 'files_modified' => [], 'message' => __('utilities.rise_status_na')];
            return;
        }

        // Reempaquetar ZIP corregido
        $lang   = $currentUser['lang'] ?? 'es';
        $suffix = $lang === 'en' ? '_fixed' : '_corregido';
        $outZip = DATA_PATH . '/tmp/nexusrise_out_' . uniqid() . '.zip';

        $zipOut = new ZipArchive();
        $zipOut->open($outZip, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $outIterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($outIterator as $file) {
            $arcName = substr($file->getPathname(), strlen($tmpDir) + 1);
            $zipOut->addFile($file->getPathname(), str_replace('\\', '/', $arcName));
        }
        $zipOut->close();

        $encoded  = base64_encode(file_get_contents($outZip));
        $filename = $originalName . $suffix . '.zip';
        @unlink($outZip);

        $response = [
            'success'        => true,
            'status'         => 'patched',
            'files_modified' => $modified,
            'filename'       => $filename,
            'data'           => $encoded,
            'message'        => sprintf(__('utilities.rise_status_patched'), count($modified)),
        ];

    } catch (Exception $e) {
        $response = ['success' => false, 'message' => 'Error al procesar: ' . $e->getMessage()];
    } finally {
        // Cleanup siempre — con supresión para no contaminar la respuesta
        if (is_dir($tmpDir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tmpDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($tmpDir);
        }
    }

    riseOut($response ?? ['success' => false, 'message' => 'Error inesperado']);
}
