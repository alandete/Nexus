<?php
/**
 * S4Learning - Image Optimizer Actions
 * Operaciones: compress, resize, convert (una imagen por llamada)
 */
define('APP_ACCESS', true);
ob_start(); // Captura cualquier warning/notice antes del JSON
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');

// ── Auth ──────────────────────────────────────────────────────────────────────
if (!isLoggedIn()) {
    imgOut(['success' => false, 'message' => 'No autorizado']);
}

// ── CSRF ─────────────────────────────────────────────────────────────────────
if (!validateCsrf()) {
    http_response_code(403);
    imgOut(['success' => false, 'message' => 'Token CSRF inválido']);
}

// ── Permiso ───────────────────────────────────────────────────────────────────
$currentUser = getCurrentUser();
if (!hasPermission($currentUser, 'utilities', 'write')) {
    http_response_code(403);
    imgOut(['success' => false, 'message' => 'Sin permiso']);
}

// ── Router ────────────────────────────────────────────────────────────────────
$action = $_POST['action'] ?? '';
match ($action) {
    'status'   => imgHandleStatus(),
    'compress' => imgHandleCompress(),
    'resize'   => imgHandleResize(),
    'convert'  => imgHandleConvert(),
    default    => imgOut(['success' => false, 'message' => 'Acción no válida']),
};

// ═════════════════════════════════════════════════════════════════════════════
// STATUS
// ═════════════════════════════════════════════════════════════════════════════
function imgHandleStatus(): void
{
    $apiSettings = getApiSettings();
    imgOut([
        'success' => true,
        'methods' => [
            'gd'      => extension_loaded('gd'),
            'imagick' => extension_loaded('imagick'),
            'api'     => !empty($apiSettings['ilp_public_key']) && !empty($apiSettings['ilp_secret_key']),
        ],
    ]);
}

// ═════════════════════════════════════════════════════════════════════════════
// COMPRESS
// ═════════════════════════════════════════════════════════════════════════════
function imgHandleCompress(): void
{
    $method = $_POST['method']  ?? 'imagick';
    $level  = $_POST['quality'] ?? 'medium'; // high | medium | low

    $file   = imgValidateUpload($method === 'api' ? 5 : 20);

    $qualityMap = ['high' => 85, 'medium' => 70, 'low' => 50];
    $quality    = $qualityMap[$level] ?? 70;

    $result = match ($method) {
        'imagick' => extension_loaded('imagick')
            ? imgCompressImagick($file, $quality)
            : imgCompressGD($file, $quality),
        'api'     => imgCompressAPI($file, $level),
        default   => imgCompressGD($file, $quality),
    };

    imgOut($result);
}

function imgCompressGD(array $file, int $quality): array
{
    $mime     = mime_content_type($file['tmp_name']);
    $original = file_get_contents($file['tmp_name']);
    $src      = @imagecreatefromstring($original);
    if (!$src) return ['success' => false, 'message' => 'No se pudo leer la imagen'];

    ob_start();
    switch ($mime) {
        case 'image/jpeg': imagejpeg($src, null, $quality); break;
        case 'image/png':
            imagesavealpha($src, true);
            imagepng($src, null, (int)round((100 - $quality) / 11)); break;
        case 'image/webp': imagewebp($src, null, $quality); break;
        case 'image/gif':  imagegif($src); break;
        default:           imagejpeg($src, null, $quality);
    }
    $data = ob_get_clean();
    imagedestroy($src);

    // Si el resultado es mayor que el original, devolver el original sin cambios
    if (strlen($data) >= strlen($original)) {
        $data = $original;
    }

    return imgBuildResult($file, $data, $mime, $file['name']);
}

function imgCompressImagick(array $file, int $quality): array
{
    try {
        $imagick = new Imagick();
        $imagick->readImageBlob(file_get_contents($file['tmp_name']));
        $imagick->stripImage();
        $imagick->setImageCompressionQuality($quality);

        $format = strtolower($imagick->getImageFormat());
        if ($format === 'jpeg') {
            $imagick->setInterlaceScheme(Imagick::INTERLACE_PLANE);
            $imagick->setSamplingFactors(['2x2', '1x1', '1x1']);
        }

        $data     = $imagick->getImagesBlob();
        $mime     = imgFormatToMime($format);
        $original = file_get_contents($file['tmp_name']);
        $imagick->clear();

        if (strlen($data) >= strlen($original)) {
            $data = $original;
        }

        return imgBuildResult($file, $data, $mime, $file['name']);
    } catch (Exception $e) {
        return imgCompressGD($file, $quality); // fallback
    }
}

function imgCompressAPI(array $file, string $level): array
{
    $levelMap = ['high' => 'low', 'medium' => 'recommended', 'low' => 'extreme'];
    $compressionLevel = $levelMap[$level] ?? 'recommended';

    $settings = getApiSettings();
    if (empty($settings['ilp_public_key']) || empty($settings['ilp_secret_key'])) {
        return ['success' => false, 'message' => 'API no configurada'];
    }

    try {
        $token          = imgAPIAuth($settings['ilp_public_key'], $settings['ilp_secret_key']);
        $start          = imgAPIStart($token, 'compressimage');
        $server         = $start['server'];
        $taskId         = $start['task'];

        $serverFilename = imgAPIUpload($server, $taskId, $token, $file);
        imgAPIProcess($server, $taskId, $token, [
            'task'              => $taskId,
            'tool'              => 'compressimage',
            'compression_level' => $compressionLevel,
            'files'             => [['server_filename' => $serverFilename, 'filename' => $file['name']]],
        ]);

        $data = imgAPIDownload($server, $taskId, $token);
        $data = imgExtractFromZip($data, $file['name']);
        $mime = mime_content_type($file['tmp_name']);

        return imgBuildResult($file, $data, $mime, $file['name']);
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error API: ' . $e->getMessage()];
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// RESIZE
// ═════════════════════════════════════════════════════════════════════════════
function imgHandleResize(): void
{
    $file    = imgValidateUpload(20);
    $percent = (int)($_POST['percent'] ?? 0);
    $newW    = (int)($_POST['width']   ?? 0);
    $newH    = (int)($_POST['height']  ?? 0);

    $mime = mime_content_type($file['tmp_name']);
    $src  = @imagecreatefromstring(file_get_contents($file['tmp_name']));
    if (!$src) imgOut(['success' => false, 'message' => 'No se pudo leer la imagen']);

    $srcW = imagesx($src);
    $srcH = imagesy($src);

    // Calcular dimensiones finales
    if ($percent > 0 && $percent <= 100) {
        // Modo porcentaje: escalar proporcionalmente
        $newW = max(1, (int)round($srcW * $percent / 100));
        $newH = max(1, (int)round($srcH * $percent / 100));
    } elseif ($newW > 0 || $newH > 0) {
        // Modo personalizado: calcular la dimensión faltante manteniendo proporción
        if ($newW > 0 && $newH <= 0) {
            $newH = max(1, (int)round($srcH * ($newW / $srcW)));
        } elseif ($newH > 0 && $newW <= 0) {
            $newW = max(1, (int)round($srcW * ($newH / $srcH)));
        }
    } else {
        imgOut(['success' => false, 'message' => 'Ingresa una dimensión o selecciona un porcentaje']);
    }

    $dst = imagecreatetruecolor($newW, $newH);

    // Preservar transparencia
    if (in_array($mime, ['image/png', 'image/gif', 'image/webp'])) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
    imagedestroy($src);

    ob_start();
    switch ($mime) {
        case 'image/jpeg': imagejpeg($dst, null, 90); break;
        case 'image/png':  imagesavealpha($dst, true); imagepng($dst, null, 6); break;
        case 'image/webp': imagewebp($dst, null, 85); break;
        case 'image/gif':  imagegif($dst); break;
        default:           imagejpeg($dst, null, 90);
    }
    $data = ob_get_clean();
    imagedestroy($dst);

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $basename = pathinfo($file['name'], PATHINFO_FILENAME);
    $result   = imgBuildResult($file, $data, $mime, $basename . '_resized.' . $ext);
    $result['dimensions']          = $newW . '×' . $newH;
    $result['original_dimensions'] = $srcW . '×' . $srcH;

    imgOut($result);
}

// ═════════════════════════════════════════════════════════════════════════════
// CONVERT
// ═════════════════════════════════════════════════════════════════════════════
function imgHandleConvert(): void
{
    $file    = imgValidateUpload(20);
    $format  = strtolower($_POST['format']  ?? 'webp');
    $quality = (int)($_POST['quality']      ?? 85);
    $quality = max(30, min(100, $quality));

    $mimeMap = ['jpeg' => 'image/jpeg', 'jpg' => 'image/jpeg',
                'png'  => 'image/png',  'webp' => 'image/webp', 'gif' => 'image/gif'];
    $targetMime = $mimeMap[$format] ?? 'image/webp';
    $ext        = ($format === 'jpeg') ? 'jpg' : $format;

    $src = imagecreatefromstring(file_get_contents($file['tmp_name']));
    if (!$src) imgOut(['success' => false, 'message' => 'No se pudo leer la imagen']);

    $w   = imagesx($src);
    $h   = imagesy($src);
    $dst = imagecreatetruecolor($w, $h);

    if (in_array($targetMime, ['image/png', 'image/webp'])) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefilledrectangle($dst, 0, 0, $w, $h, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    } else {
        // Fondo blanco para formatos sin transparencia
        imagefilledrectangle($dst, 0, 0, $w, $h, imagecolorallocate($dst, 255, 255, 255));
    }

    imagecopy($dst, $src, 0, 0, 0, 0, $w, $h);
    imagedestroy($src);

    ob_start();
    switch ($targetMime) {
        case 'image/jpeg': imagejpeg($dst, null, $quality); break;
        case 'image/png':  imagesavealpha($dst, true); imagepng($dst); break;
        case 'image/webp': imagewebp($dst, null, $quality); break;
        case 'image/gif':  imagegif($dst); break;
    }
    $data = ob_get_clean();
    imagedestroy($dst);

    $basename = pathinfo($file['name'], PATHINFO_FILENAME);
    $result   = imgBuildResult($file, $data, $targetMime, $basename . '.' . $ext);
    $result['converted_format'] = strtoupper($ext);

    imgOut($result);
}

// ═════════════════════════════════════════════════════════════════════════════
// iLoveIMG API HELPERS
// ═════════════════════════════════════════════════════════════════════════════
function imgAPIAuth(string $publicKey, string $secretKey): string
{
    $res = imgCurl('POST', 'https://api.ilovepdf.com/v1/auth', ['public_key' => $publicKey]);
    if (empty($res['token'])) throw new RuntimeException('Auth fallida');
    return $res['token'];
}

function imgAPIStart(string $token, string $tool): array
{
    [$server] = explode('/v1', 'https://api.ilovepdf.com/v1'); // placeholder
    $res = imgCurl('GET', "https://api.ilovepdf.com/v1/start/{$tool}", [], $token);
    if (empty($res['task'])) throw new RuntimeException('Start fallido');
    return $res;
}

function imgAPIUpload(string $server, string $taskId, string $token, array $file): string
{
    $url = "https://{$server}/v1/upload";
    $res = imgCurlMultipart($url, $token, [
        'task'  => $taskId,
        'file'  => new CURLFile($file['tmp_name'], $file['type'], $file['name']),
    ]);
    if (empty($res['server_filename'])) throw new RuntimeException('Upload fallido');
    return $res['server_filename'];
}

function imgAPIProcess(string $server, string $taskId, string $token, array $params): void
{
    $url = "https://{$server}/v1/process";
    imgCurl('POST', $url, $params, $token);
}

function imgAPIDownload(string $server, string $taskId, string $token): string
{
    $ch = curl_init("https://{$server}/v1/download/{$taskId}");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}"],
        CURLOPT_TIMEOUT        => 60,
    ]);
    $data = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$data || $code >= 400) throw new RuntimeException('Descarga fallida');
    return $data;
}

function imgCurl(string $method, string $url, array $data = [], string $token = ''): array
{
    $ch = curl_init($url);
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($token) $headers[] = "Authorization: Bearer {$token}";

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res ?: '{}', true) ?? [];
}

function imgCurlMultipart(string $url, string $token, array $data): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $data,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}"],
        CURLOPT_TIMEOUT        => 60,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res ?: '{}', true) ?? [];
}

function imgExtractFromZip(string $data, string $originalName): string
{
    if (substr($data, 0, 4) !== "PK\x03\x04") return $data;

    $tmp = tempnam(sys_get_temp_dir(), 'ilimg_') . '.zip';
    file_put_contents($tmp, $data);
    $zip = new ZipArchive();
    $extracted = $data;
    if ($zip->open($tmp) === true) {
        $extracted = $zip->getFromIndex(0) ?: $data;
        $zip->close();
    }
    unlink($tmp);
    return $extracted;
}

// ═════════════════════════════════════════════════════════════════════════════
// UTILIDADES COMUNES
// ═════════════════════════════════════════════════════════════════════════════
function imgValidateUpload(int $maxMB = 5): array
{
    $f = $_FILES['image'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        imgOut(['success' => false, 'message' => 'No se recibió ninguna imagen']);
    }

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array(mime_content_type($f['tmp_name']), $allowed)) {
        imgOut(['success' => false, 'message' => "Formato no permitido: {$f['name']}"]);
    }

    if ($f['size'] > $maxMB * 1024 * 1024) {
        imgOut(['success' => false, 'message' => "{$f['name']} supera el límite de {$maxMB} MB"]);
    }

    return $f;
}

function imgBuildResult(array $file, string $data, string $mime, string $filename): array
{
    $origSize   = $file['size'];
    $resultSize = strlen($data);
    $savings    = max(0, $origSize - $resultSize);
    $savingsPct = $origSize > 0 ? round($savings / $origSize * 100) : 0;

    return [
        'success'       => true,
        'filename'      => $filename,
        'mime'          => $mime,
        'original_size' => $origSize,
        'result_size'   => $resultSize,
        'savings'       => $savings,
        'savings_pct'   => $savingsPct,
        'data'          => base64_encode($data),
    ];
}

function imgFormatToMime(string $format): string
{
    return match ($format) {
        'jpeg', 'jpg' => 'image/jpeg',
        'png'         => 'image/png',
        'webp'        => 'image/webp',
        'gif'         => 'image/gif',
        default       => 'image/jpeg',
    };
}

function imgOut(array $data): void
{
    ob_end_clean(); // Descarta cualquier warning/notice capturado
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
