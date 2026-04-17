<?php
/**
 * S4Learning - PDF Optimizer Actions
 * Procesa PDFs usando tres metodos: Ghostscript, API iLovePDF o PHP puro
 */
define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$currentUser = getCurrentUser();
if (!hasPermission($currentUser, 'utilities', 'write')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permisos para usar esta herramienta']);
    exit;
}

if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalido']);
    exit;
}

$action = $_POST['action'] ?? '';

// ── Estado: qué metodos estan disponibles ─────────────────────────────────
if ($action === 'status') {
    $gsPath   = encontrarGhostscript();
    $settings = getApiSettings();
    echo json_encode([
        'success' => true,
        'methods' => [
            'ghostscript' => $gsPath !== null,
            'api'         => !empty($settings['ilp_public_key']) && !empty($settings['ilp_secret_key']),
            'php'         => true,
        ],
    ]);
    exit;
}

// ── Procesar PDF ──────────────────────────────────────────────────────────
if ($action === 'process') {
    // Validar archivo
    if (!isset($_FILES['pdf_file']) || $_FILES['pdf_file']['error'] !== UPLOAD_ERR_OK) {
        $errMsg = match($_FILES['pdf_file']['error'] ?? -1) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamano maximo permitido',
            UPLOAD_ERR_NO_FILE => 'No se recibio ningun archivo',
            default            => 'Error al recibir el archivo',
        };
        echo json_encode(['success' => false, 'message' => $errMsg]);
        exit;
    }

    $file = $_FILES['pdf_file'];

    // Validar tipo por extension y cabecera
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        echo json_encode(['success' => false, 'message' => 'El archivo debe tener extension .pdf']);
        exit;
    }

    // Verificar cabecera PDF (%PDF-)
    $handle = fopen($file['tmp_name'], 'rb');
    $header = fread($handle, 5);
    fclose($handle);
    if ($header !== '%PDF-') {
        echo json_encode(['success' => false, 'message' => 'El archivo no es un PDF valido']);
        exit;
    }

    if ($file['size'] > 20 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'El archivo supera el limite de 20 MB']);
        exit;
    }

    $method       = $_POST['method'] ?? 'php';
    $originalSize = $file['size'];
    $baseName     = pathinfo($file['name'], PATHINFO_FILENAME);
    $outputName   = $baseName . '-optimizado.pdf';

    switch ($method) {
        case 'ghostscript':
            $result = procesarConGhostscript($file['tmp_name']);
            break;
        case 'api':
            $result = procesarConAPI($file['tmp_name'], $file['name']);
            break;
        default:
            $result = procesarConPHP($file['tmp_name']);
    }

    if (!$result['success']) {
        echo json_encode($result);
        exit;
    }

    $optimizedSize = $result['size'];
    $savings       = max(0, $originalSize - $optimizedSize);
    $savingsPct    = $originalSize > 0 ? round(($savings / $originalSize) * 100, 1) : 0;

    logActivity('utilities', 'process', 'pdf_optimizer:' . $method . ':' . basename($file['name']));

    echo json_encode([
        'success'        => true,
        'original_size'  => $originalSize,
        'optimized_size' => $optimizedSize,
        'savings'        => $savings,
        'savings_pct'    => $savingsPct,
        'filename'       => $outputName,
        'method_used'    => $method,
        'data'           => $result['data'],
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Accion no valida']);

// ═══════════════════════════════════════════════════════════════════════════
// FUNCIONES DE OPTIMIZACION
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Busca el ejecutable de Ghostscript en el sistema
 */
function encontrarGhostscript(): ?string
{
    if (PHP_OS_FAMILY === 'Windows') {
        // Intentar por PATH
        foreach (['gswin64c', 'gswin32c', 'gs'] as $cmd) {
            $out = @shell_exec("where {$cmd} 2>&1");
            if ($out) {
                $line = trim(explode("\n", $out)[0]);
                if (file_exists($line)) return $line;
            }
        }
        // Buscar en rutas de instalacion tipicas
        $patterns = [
            'C:/Program Files/gs/gs*/bin/gswin64c.exe',
            'C:/Program Files (x86)/gs/gs*/bin/gswin64c.exe',
            'C:/Program Files/gs/gs*/bin/gswin32c.exe',
            'C:/Program Files (x86)/gs/gs*/bin/gswin32c.exe',
        ];
        foreach ($patterns as $pattern) {
            $matches = glob($pattern);
            if (!empty($matches)) {
                return end($matches); // Version mas reciente
            }
        }
    } else {
        $out = @shell_exec('which gs 2>&1');
        if ($out && !str_contains($out, 'not found') && !str_contains($out, 'no gs')) {
            $path = trim($out);
            if (file_exists($path)) return $path;
        }
    }
    return null;
}

/**
 * Optimiza el PDF usando Ghostscript (mejor calidad y compresion)
 * Usa perfil /ebook: 150dpi, excelente para documentos web
 */
function procesarConGhostscript(string $tmpFile): array
{
    $gsPath = encontrarGhostscript();
    if (!$gsPath) {
        return ['success' => false, 'message' => 'Ghostscript no esta disponible en este servidor'];
    }

    $outFile = sys_get_temp_dir() . '/pdfopt_' . uniqid() . '.pdf';

    $cmd = escapeshellarg($gsPath)
        . ' -dNOPAUSE -dBATCH -dSAFER -sDEVICE=pdfwrite'
        . ' -dCompatibilityLevel=1.4'
        . ' -dPDFSETTINGS=/ebook'
        . ' -dEmbedAllFonts=true -dSubsetFonts=true'
        . ' -dColorImageDownsampleType=/Bicubic -dColorImageResolution=150'
        . ' -dGrayImageDownsampleType=/Bicubic -dGrayImageResolution=150'
        . ' -dMonoImageDownsampleType=/Bicubic -dMonoImageResolution=150'
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

/**
 * Optimiza el PDF usando la API REST de iLovePDF
 * Flujo: auth → start task → upload → process → download
 */
function procesarConAPI(string $tmpFile, string $originalName): array
{
    $settings  = getApiSettings();
    $publicKey = $settings['ilp_public_key'] ?? '';
    $secretKey = $settings['ilp_secret_key'] ?? '';

    if (empty($publicKey) || empty($secretKey)) {
        return ['success' => false, 'message' => 'La API de iLovePDF no esta configurada'];
    }

    // Paso 1: Autenticacion
    $token = ilpRequest('POST', 'https://api.ilovepdf.com/v1/auth', ['public_key' => $publicKey]);
    if (!isset($token['token'])) {
        return ['success' => false, 'message' => 'Error de autenticacion con iLovePDF. Verifica las claves en Ajustes.'];
    }
    $jwt = $token['token'];

    // Paso 2: Iniciar tarea de compresion
    $task = ilpRequest('GET', 'https://api.ilovepdf.com/v1/start/compress', [], $jwt);
    if (!isset($task['task'], $task['server'])) {
        return ['success' => false, 'message' => 'Error al iniciar la tarea en iLovePDF'];
    }
    $taskId = $task['task'];
    $server = $task['server'];

    // Paso 3: Subir el archivo
    $uploaded = ilpUpload("https://{$server}/v1/upload", $jwt, $taskId, $tmpFile, basename($originalName));
    if (!isset($uploaded['server_filename'])) {
        return ['success' => false, 'message' => 'Error al subir el archivo a iLovePDF'];
    }

    // Paso 4: Procesar
    $processed = ilpRequest('POST', "https://{$server}/v1/process", [
        'task'              => $taskId,
        'tool'              => 'compress',
        'files'             => [['server_filename' => $uploaded['server_filename'], 'filename' => basename($originalName)]],
        'compression_level' => 'recommended',
        'output_filename'   => 'optimized',
    ], $jwt);
    if (!$processed) {
        return ['success' => false, 'message' => 'Error al procesar el archivo en iLovePDF'];
    }

    // Paso 5: Descargar resultado
    $pdfBinary = ilpDownload("https://{$server}/v1/download/{$taskId}", $jwt);
    if (!$pdfBinary || substr($pdfBinary, 0, 5) !== '%PDF-') {
        return ['success' => false, 'message' => 'Error al descargar el resultado de iLovePDF'];
    }

    return ['success' => true, 'data' => base64_encode($pdfBinary), 'size' => strlen($pdfBinary)];
}

/**
 * Optimiza el PDF con PHP puro (sin dependencias externas)
 * Elimina metadatos XMP y compacta el documento.
 * Efectividad limitada (5-20%), seguro para cualquier PDF.
 */
function procesarConPHP(string $tmpFile): array
{
    $pdf = file_get_contents($tmpFile);
    if ($pdf === false) {
        return ['success' => false, 'message' => 'Error al leer el archivo PDF'];
    }

    // Eliminar paquetes XMP (metadatos Adobe)
    $optimized = preg_replace(
        '/(<\?xpacket begin[="\xef\xbb\xbf \']*.*?\?xpacket end[^?]*\?>)/s',
        '',
        $pdf
    ) ?? $pdf;

    // Eliminar bloque /Metadata si esta presente como objeto independiente
    $optimized = preg_replace(
        '/\d+\s+\d+\s+obj\s*<<[^>]*\/Type\s*\/Metadata[^>]*>>\s*stream[\s\S]*?endstream\s*endobj/i',
        '',
        $optimized
    ) ?? $optimized;

    // Compactar espacios multiples en la estructura (fuera de streams binarios)
    // Solo entre palabras clave PDF para no corromper datos binarios
    $optimized = preg_replace('/(\bendobj\b)\s{2,}(\d)/', '$1' . "\n" . '$2', $optimized) ?? $optimized;

    return [
        'success' => true,
        'data'    => base64_encode($optimized),
        'size'    => strlen($optimized),
    ];
}

// ═══════════════════════════════════════════════════════════════════════════
// HELPERS API iLovePDF
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Realiza una peticion HTTP a la API de iLovePDF (GET o POST con JSON)
 */
function ilpRequest(string $method, string $url, array $body = [], string $token = ''): ?array
{
    $ch = curl_init($url);
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer {$token}";

    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $httpCode >= 400) return null;
    return json_decode($response, true);
}

/**
 * Sube un archivo a iLovePDF (multipart/form-data)
 */
function ilpUpload(string $url, string $token, string $taskId, string $filePath, string $fileName): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'task' => $taskId,
            'file' => new CURLFile($filePath, 'application/pdf', $fileName),
        ],
        CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}", 'Accept: application/json'],
        CURLOPT_TIMEOUT    => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$response || $httpCode >= 400) return null;
    return json_decode($response, true);
}

/**
 * Descarga el PDF resultante de iLovePDF (retorna binario)
 */
function ilpDownload(string $url, string $token): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}"],
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($response && $httpCode === 200) ? $response : null;
}
