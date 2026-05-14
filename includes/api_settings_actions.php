<?php
/**
 * S4Learning - API Settings Actions
 * Gestiona la lectura y guardado de claves de APIs externas (encriptadas)
 */
ini_set('display_errors', '0');
ob_start();
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        ob_end_clean();
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['success' => false, 'message' => 'Error: ' . $error['message']]);
    } else {
        ob_end_flush();
    }
});

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

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo el administrador puede gestionar las APIs']);
    exit;
}

if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalido']);
    exit;
}

$action = $_POST['action'] ?? '';

// ── Leer configuracion actual ──────────────────────────────────────────────
if ($action === 'get') {
    $settings = getApiSettings();
    // Enmascarar los valores para la UI (no enviar la clave completa)
    $raw = getApiSettingsRaw();
    echo json_encode([
        'success' => true,
        'ilp_email'             => $raw['ilp_email'] ?? '',
        'ilp_password_preview'  => maskPassword(decryptApiValue($raw['ilp_password'] ?? '')),
        'ilp_project'           => $raw['ilp_project'] ?? '',
        'ilp_public_configured' => !empty($settings['ilp_public_key']),
        'ilp_secret_configured' => !empty($settings['ilp_secret_key']),
        'ilp_public_preview'    => maskKey($settings['ilp_public_key']),
        'ilp_secret_preview'    => maskKey($settings['ilp_secret_key']),
    ]);
    exit;
}

// ── Guardar configuracion ──────────────────────────────────────────────────
if ($action === 'save') {
    $email     = trim($_POST['ilp_email'] ?? '');
    $password  = trim($_POST['ilp_password'] ?? '');
    $project   = trim($_POST['ilp_project'] ?? '');
    $publicKey = trim($_POST['ilp_public_key'] ?? '');
    $secretKey = trim($_POST['ilp_secret_key'] ?? '');

    // Leer valores actuales para preservar los que no se envien
    $current = getApiSettingsRaw();

    // Campos de cuenta: email y proyecto se guardan en texto plano, contrasena encriptada
    $ilpEmail   = !empty($email) ? $email : ($current['ilp_email'] ?? '');
    $ilpProject = !empty($project) ? $project : ($current['ilp_project'] ?? '');

    if (empty($password) || str_contains($password, '****')) {
        $encPassword = $current['ilp_password'] ?? '';
    } else {
        $encPassword = encryptApiValue($password);
    }

    // Si el campo llega vacio o con mascara, conservar el valor existente
    if (empty($publicKey) || str_contains($publicKey, '****')) {
        $encPublic = $current['ilp_public_key'] ?? '';
    } else {
        $encPublic = encryptApiValue($publicKey);
    }

    if (empty($secretKey) || str_contains($secretKey, '****')) {
        $encSecret = $current['ilp_secret_key'] ?? '';
    } else {
        $encSecret = encryptApiValue($secretKey);
    }

    $data = [
        'ilp_email'      => $ilpEmail,
        'ilp_password'   => $encPassword,
        'ilp_project'    => $ilpProject,
        'ilp_public_key' => $encPublic,
        'ilp_secret_key' => $encSecret,
        'gs_quality'     => $current['gs_quality'] ?? 'ebook',
    ];

    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    $saved = file_put_contents(
        API_SETTINGS_FILE,
        json_encode($data, JSON_PRETTY_PRINT),
        LOCK_EX
    );

    if ($saved === false) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la configuracion']);
        exit;
    }

    logActivity('settings', 'update', 'api_settings:ilp');
    echo json_encode(['success' => true, 'message' => 'Configuracion guardada correctamente']);
    exit;
}

// ── Probar conexion con iLovePDF ───────────────────────────────────────────
if ($action === 'test') {
    $settings = getApiSettings();
    if (empty($settings['ilp_public_key'])) {
        echo json_encode(['success' => false, 'message' => 'No hay clave publica configurada']);
        exit;
    }

    $token = ilpAuth($settings['ilp_public_key']);
    if ($token === null) {
        echo json_encode(['success' => false, 'message' => 'No se pudo conectar con iLovePDF. Verifica la clave publica.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Conexion con iLovePDF verificada correctamente',
        'plan'    => ilpDecodeToken($token),
    ]);
    exit;
}

// ── Guardar calidad Ghostscript ───────────────────────────────────────────
if ($action === 'save_gs') {
    $allowed = ['screen', 'ebook', 'printer', 'prepress', 'default'];
    $quality = trim($_POST['gs_quality'] ?? 'ebook');
    if (!in_array($quality, $allowed, true)) $quality = 'ebook';

    $current = getApiSettingsRaw();
    $current['gs_quality'] = $quality;

    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    $saved = file_put_contents(API_SETTINGS_FILE, json_encode($current, JSON_PRETTY_PRINT), LOCK_EX);

    if ($saved === false) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la configuracion']);
        exit;
    }

    logActivity('settings', 'update', 'api_settings:gs_quality:' . $quality);
    echo json_encode(['success' => true, 'message' => 'Calidad de Ghostscript guardada']);
    exit;
}

// ── Leer configuracion SMTP ───────────────────────────────────────────────
if ($action === 'smtp_get') {
    $raw  = getApiSettingsRaw();
    $pass = decryptApiValue($raw['smtp_pass'] ?? '');
    echo json_encode([
        'success'           => true,
        'smtp_host'         => $raw['smtp_host']      ?? '',
        'smtp_port'         => $raw['smtp_port']      ?? 587,
        'smtp_user'         => $raw['smtp_user']      ?? '',
        'smtp_pass_preview' => empty($pass) ? '' : str_repeat('*', max(4, strlen($pass) - 2)) . substr($pass, -2),
        'smtp_has_pass'     => !empty($pass),
        'smtp_secure'       => $raw['smtp_secure']    ?? 'tls',
        'smtp_from'         => $raw['smtp_from']      ?? '',
        'smtp_from_name'    => $raw['smtp_from_name'] ?? '',
    ]);
    exit;
}

// ── Guardar configuracion SMTP ────────────────────────────────────────────
if ($action === 'smtp_save') {
    $ok = saveSmtpSettings([
        'smtp_host'      => trim($_POST['smtp_host']      ?? ''),
        'smtp_port'      => (int)($_POST['smtp_port']     ?? 587),
        'smtp_user'      => trim($_POST['smtp_user']      ?? ''),
        'smtp_pass'      => $_POST['smtp_pass']            ?? '',
        'smtp_secure'    => trim($_POST['smtp_secure']    ?? 'tls'),
        'smtp_from'      => trim($_POST['smtp_from']      ?? ''),
        'smtp_from_name' => trim($_POST['smtp_from_name'] ?? ''),
    ]);

    if ($ok) {
        logActivity('settings', 'update', 'api_settings:smtp');
        echo json_encode(['success' => true, 'message' => 'Configuración SMTP guardada correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la configuración SMTP']);
    }
    exit;
}

// ── Probar conexion SMTP ──────────────────────────────────────────────────
if ($action === 'smtp_test') {
    $smtp = getSmtpSettings();
    if (empty($smtp['host']) || empty($smtp['user'])) {
        echo json_encode(['success' => false, 'message' => 'Configura el servidor SMTP antes de probar']);
        exit;
    }

    require_once __DIR__ . '/mailer.php';
    $mailer = new Mailer($smtp);

    $to      = $smtp['from_email'] ?: $smtp['user'];
    $appName = defined('APP_NAME') ? APP_NAME : 'Nexus';
    $ok = $mailer->send(
        $to,
        "Prueba SMTP — {$appName}",
        "<p>La configuración SMTP de <strong>{$appName}</strong> funciona correctamente.</p>",
        "La configuración SMTP de {$appName} funciona correctamente."
    );

    if ($ok) {
        echo json_encode(['success' => true, 'message' => "Correo de prueba enviado a {$to}"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $mailer->lastError]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Accion no valida']);

// ── Funciones auxiliares ───────────────────────────────────────────────────

/**
 * Lee el JSON crudo sin desencriptar (para conservar valores al guardar parcialmente)
 */
if (!function_exists('getApiSettingsRaw')) {
function getApiSettingsRaw(): array
{
    if (!file_exists(API_SETTINGS_FILE)) return [];
    return json_decode(file_get_contents(API_SETTINGS_FILE), true) ?? [];
}
}

/**
 * Enmascara una contrasena: muestra solo los ultimos 2 chars
 */
function maskPassword(string $value): string
{
    if (empty($value)) return '';
    $len = strlen($value);
    if ($len <= 2) return str_repeat('*', $len);
    return str_repeat('*', $len - 2) . substr($value, -2);
}

/**
 * Enmascara una clave para mostrar en la UI: muestra primeros 4 y ultimos 4 chars
 */
function maskKey(string $value): string
{
    if (empty($value)) return '';
    $len = strlen($value);
    if ($len <= 8) return str_repeat('*', $len);
    return substr($value, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($value, -4);
}

/**
 * Decodifica el payload del JWT (sin verificar firma) y retorna los campos útiles del plan
 */
function ilpDecodeToken(string $token): array
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) return [];

    $padded  = str_pad(strtr($parts[1], '-_', '+/'), (int)ceil(strlen($parts[1]) / 4) * 4, '=');
    $payload = json_decode(base64_decode($padded), true);
    if (!is_array($payload)) return [];

    // Campos que retornamos (excluye metadatos técnicos del JWT)
    // Retornar todo excepto metadatos técnicos del JWT estándar
    $skip = ['iss', 'aud', 'iat', 'exp', 'jti', 'nbf'];
    $info = [];
    foreach ($payload as $k => $v) {
        if (!in_array($k, $skip)) {
            $info[$k] = is_array($v) ? json_encode($v) : $v;
        }
    }
    return $info;
}

/**
 * Autentica con la API de iLovePDF y devuelve el token JWT o null si falla
 */
function ilpAuth(string $publicKey): ?string
{
    $ch = curl_init('https://api.ilovepdf.com/v1/auth');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['public_key' => $publicKey]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) return null;
    $data = json_decode($response, true);
    return $data['token'] ?? null;
}
