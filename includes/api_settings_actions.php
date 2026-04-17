<?php
/**
 * S4Learning - API Settings Actions
 * Gestiona la lectura y guardado de claves de APIs externas (encriptadas)
 */
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

echo json_encode(['success' => false, 'message' => 'Accion no valida']);

// ── Funciones auxiliares ───────────────────────────────────────────────────

/**
 * Lee el JSON crudo sin desencriptar (para conservar valores al guardar parcialmente)
 */
function getApiSettingsRaw(): array
{
    if (!file_exists(API_SETTINGS_FILE)) return [];
    return json_decode(file_get_contents(API_SETTINGS_FILE), true) ?? [];
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
