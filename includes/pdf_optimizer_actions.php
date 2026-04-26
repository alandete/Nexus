<?php
/**
 * Nexus 2.0 — PDF Optimizer Actions
 * Métodos: Ghostscript (local), API iLovePDF (cloud)
 */
define('APP_ACCESS', true);
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    pdfOut(['success' => false, 'message' => 'No autorizado']);
}

if (!validateCsrf()) {
    http_response_code(403);
    pdfOut(['success' => false, 'message' => 'Token CSRF inválido']);
}

$currentUser = getCurrentUser();
if (!hasPermission($currentUser, 'utilities', 'write')) {
    http_response_code(403);
    pdfOut(['success' => false, 'message' => 'Sin permiso']);
}

$action = $_POST['action'] ?? '';
match ($action) {
    'status'  => pdfHandleStatus(),
    'process' => pdfHandleProcess(),
    default   => pdfOut(['success' => false, 'message' => 'Acción no válida']),
};

// ═════════════════════════════════════════════════════════════════════════════
// STATUS
// ═════════════════════════════════════════════════════════════════════════════
function pdfHandleStatus(): void
{
    $settings = getApiSettings();
    pdfOut([
        'success' => true,
        'methods' => [
            'ghostscript' => pdfFindGhostscript() !== null,
            'api'         => !empty($settings['ilp_public_key']) && !empty($settings['ilp_secret_key']),
        ],
    ]);
}

// ═════════════════════════════════════════════════════════════════════════════
// PROCESS
// ═════════════════════════════════════════════════════════════════════════════
function pdfHandleProcess(): void
{
    $f = $_FILES['pdf_file'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        $msg = match($f['error'] ?? -1) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido',
            UPLOAD_ERR_NO_FILE => 'No se recibió ningún archivo',
            default            => 'Error al recibir el archivo',
        };
        pdfOut(['success' => false, 'message' => $msg]);
    }

    if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'pdf') {
        pdfOut(['success' => false, 'message' => 'El archivo debe tener extensión .pdf']);
    }

    $handle = fopen($f['tmp_name'], 'rb');
    $header = fread($handle, 5);
    fclose($handle);
    if ($header !== '%PDF-') {
        pdfOut(['success' => false, 'message' => 'El archivo no es un PDF válido']);
    }

    if ($f['size'] > 100 * 1024 * 1024) {
        pdfOut(['success' => false, 'message' => 'El archivo supera el límite de 100 MB']);
    }

    $method = $_POST['method'] ?? 'ghostscript';
    $result = match ($method) {
        'ghostscript' => pdfCompressGhostscript($f['tmp_name']),
        'api'         => pdfCompressAPI($f['tmp_name'], $f['name']),
        default       => ['success' => false, 'message' => 'Método no válido'],
    };

    if (!$result['success']) {
        pdfOut($result);
    }

    $original  = $f['size'];
    $optimized = $result['size'];
    $savings   = max(0, $original - $optimized);
    $pct       = $original > 0 ? round($savings / $original * 100, 1) : 0;
    $basename  = pathinfo($f['name'], PATHINFO_FILENAME);

    logActivity('utilities', 'process', 'pdf_optimizer:' . $method . ':' . basename($f['name']));

    pdfOut([
        'success'        => true,
        'filename'       => $basename . '-optimizado.pdf',
        'original_size'  => $original,
        'optimized_size' => $optimized,
        'savings'        => $savings,
        'savings_pct'    => $pct,
        'method_used'    => $method,
        'data'           => $result['data'],
    ]);
}

// ═════════════════════════════════════════════════════════════════════════════
// GHOSTSCRIPT
// ═════════════════════════════════════════════════════════════════════════════
function pdfFindGhostscript(): ?string
{
    if (PHP_OS_FAMILY === 'Windows') {
        foreach (['gswin64c', 'gswin32c', 'gs'] as $cmd) {
            $out = @shell_exec("where {$cmd} 2>&1");
            if ($out) {
                $line = trim(explode("\n", $out)[0]);
                if (file_exists($line)) return $line;
            }
        }
        foreach ([
            'C:/Program Files/gs/gs*/bin/gswin64c.exe',
            'C:/Program Files (x86)/gs/gs*/bin/gswin64c.exe',
            'C:/Program Files/gs/gs*/bin/gswin32c.exe',
            'C:/Program Files (x86)/gs/gs*/bin/gswin32c.exe',
        ] as $pattern) {
            $matches = glob($pattern);
            if (!empty($matches)) return end($matches);
        }
    } else {
        $out = @shell_exec('which gs 2>&1');
        if ($out && !str_contains($out, 'not found')) {
            $path = trim($out);
            if (file_exists($path)) return $path;
        }
    }
    return null;
}

function pdfCompressGhostscript(string $tmpFile): array
{
    $gs = pdfFindGhostscript();
    if (!$gs) {
        return ['success' => false, 'message' => 'Ghostscript no está disponible en este servidor'];
    }

    $outFile = sys_get_temp_dir() . '/nexuspdf_' . uniqid() . '.pdf';
    $cmd = escapeshellarg($gs)
        . ' -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pdfwrite'
        . ' -dCompatibilityLevel=1.4 -dPDFSETTINGS=/ebook'
        . ' -dEmbedAllFonts=true -dSubsetFonts=true'
        . ' -dColorImageDownsampleType=/Bicubic -dColorImageResolution=150'
        . ' -dGrayImageDownsampleType=/Bicubic -dGrayImageResolution=150'
        . ' -sOutputFile=' . escapeshellarg($outFile)
        . ' ' . escapeshellarg($tmpFile)
        . ' 2>&1';

    @shell_exec($cmd);

    if (!file_exists($outFile) || filesize($outFile) < 100) {
        @unlink($outFile);
        return ['success' => false, 'message' => 'Ghostscript no pudo procesar el archivo'];
    }

    $data = base64_encode(file_get_contents($outFile));
    $size = filesize($outFile);
    @unlink($outFile);

    return ['success' => true, 'data' => $data, 'size' => $size];
}

// ═════════════════════════════════════════════════════════════════════════════
// API iLovePDF
// ═════════════════════════════════════════════════════════════════════════════
function pdfCompressAPI(string $tmpFile, string $originalName): array
{
    $settings = getApiSettings();
    if (empty($settings['ilp_public_key']) || empty($settings['ilp_secret_key'])) {
        return ['success' => false, 'message' => 'La API de iLovePDF no está configurada'];
    }

    $auth = pdfAPIRequest('POST', 'https://api.ilovepdf.com/v1/auth', ['public_key' => $settings['ilp_public_key']]);
    if (empty($auth['token'])) {
        return ['success' => false, 'message' => 'Error de autenticación con iLovePDF. Verifica las claves en Ajustes.'];
    }
    $jwt = $auth['token'];

    $task = pdfAPIRequest('GET', 'https://api.ilovepdf.com/v1/start/compress', [], $jwt);
    if (empty($task['task']) || empty($task['server'])) {
        return ['success' => false, 'message' => 'Error al iniciar la tarea en iLovePDF'];
    }
    [$taskId, $server] = [$task['task'], $task['server']];

    $uploaded = pdfAPIUpload("https://{$server}/v1/upload", $jwt, $taskId, $tmpFile, basename($originalName));
    if (empty($uploaded['server_filename'])) {
        return ['success' => false, 'message' => 'Error al subir el archivo a iLovePDF'];
    }

    pdfAPIRequest('POST', "https://{$server}/v1/process", [
        'task'              => $taskId,
        'tool'              => 'compress',
        'files'             => [['server_filename' => $uploaded['server_filename'], 'filename' => basename($originalName)]],
        'compression_level' => 'recommended',
        'output_filename'   => 'optimized',
    ], $jwt);

    $binary = pdfAPIDownload("https://{$server}/v1/download/{$taskId}", $jwt);
    if (!$binary || substr($binary, 0, 5) !== '%PDF-') {
        return ['success' => false, 'message' => 'Error al descargar el resultado de iLovePDF'];
    }

    return ['success' => true, 'data' => base64_encode($binary), 'size' => strlen($binary)];
}

function pdfAPIRequest(string $method, string $url, array $body = [], string $token = ''): ?array
{
    $ch      = curl_init($url);
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer {$token}";

    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 30, CURLOPT_SSL_VERIFYPEER => true];
    if ($method === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    curl_setopt_array($ch, $opts);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$res || $code >= 400) return null;
    return json_decode($res, true);
}

function pdfAPIUpload(string $url, string $token, string $taskId, string $file, string $name): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => ['task' => $taskId, 'file' => new CURLFile($file, 'application/pdf', $name)],
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$res || $code >= 400) return null;
    return json_decode($res, true);
}

function pdfAPIDownload(string $url, string $token): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}"],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($res && $code === 200) ? $res : null;
}

// ═════════════════════════════════════════════════════════════════════════════
// HELPER
// ═════════════════════════════════════════════════════════════════════════════
function pdfOut(array $data): void
{
    ob_end_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
