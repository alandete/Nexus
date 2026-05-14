<?php
/**
 * Nexus 2.0 — User API Actions
 * Gestiona las claves iLovePDF por usuario (cada usuario administra las suyas)
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

if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalido']);
    exit;
}

$currentUser    = getCurrentUser();
$action         = $_POST['action'] ?? '';
$targetUsername = trim($_POST['username'] ?? $currentUser['username']);

// Solo el propio usuario o un admin puede gestionar claves de otro usuario
if ($targetUsername !== $currentUser['username'] && ($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$safe     = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $targetUsername);
$userFile = DATA_PATH . '/user_api_' . $safe . '.json';

// ── GET ─────────────────────────────────────────────────────────────────────
if ($action === 'get') {
    $raw        = file_exists($userFile) ? (json_decode(file_get_contents($userFile), true) ?? []) : [];
    $hasPublic  = !empty($raw['ilp_public_key']);
    $hasSecret  = !empty($raw['ilp_secret_key']);
    echo json_encode([
        'success'         => true,
        'has_public'      => $hasPublic,
        'has_secret'      => $hasSecret,
        'public_preview'  => $hasPublic ? maskApiKey(decryptApiValue($raw['ilp_public_key'])) : '',
        'secret_preview'  => $hasSecret ? maskApiKey(decryptApiValue($raw['ilp_secret_key'])) : '',
    ]);
    exit;
}

// ── SAVE ────────────────────────────────────────────────────────────────────
if ($action === 'save') {
    $publicKey = trim($_POST['ilp_public_key'] ?? '');
    $secretKey = trim($_POST['ilp_secret_key'] ?? '');

    $raw = file_exists($userFile) ? (json_decode(file_get_contents($userFile), true) ?? []) : [];

    if (!empty($publicKey) && !str_contains($publicKey, '****')) {
        $raw['ilp_public_key'] = encryptApiValue($publicKey);
    }
    if (!empty($secretKey) && !str_contains($secretKey, '****')) {
        $raw['ilp_secret_key'] = encryptApiValue($secretKey);
    }

    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    $saved = file_put_contents($userFile, json_encode($raw, JSON_PRETTY_PRINT), LOCK_EX);

    if ($saved === false) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar']);
        exit;
    }

    logActivity('settings', 'update', 'user_api:ilp:' . $targetUsername);
    echo json_encode(['success' => true, 'message' => 'Claves guardadas correctamente']);
    exit;
}

// ── CLEAR ───────────────────────────────────────────────────────────────────
if ($action === 'clear') {
    if (file_exists($userFile)) {
        unlink($userFile);
    }
    logActivity('settings', 'update', 'user_api:clear:' . $targetUsername);
    echo json_encode(['success' => true, 'message' => 'Claves eliminadas. Se usarán las del sistema.']);
    exit;
}

// ── TEST ────────────────────────────────────────────────────────────────────
if ($action === 'test') {
    $settings = getEffectiveApiSettings($targetUsername);
    if (empty($settings['ilp_public_key'])) {
        echo json_encode(['success' => false, 'message' => 'No hay clave publica configurada']);
        exit;
    }

    $ch = curl_init('https://api.ilovepdf.com/v1/auth');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(['public_key' => $settings['ilp_public_key']]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        echo json_encode(['success' => false, 'message' => 'No se pudo conectar con iLovePDF. Verifica las claves.']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => 'Conexion verificada correctamente']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Accion no valida']);

// ── Helpers ─────────────────────────────────────────────────────────────────

function maskApiKey(string $value): string
{
    if (empty($value)) return '';
    $len = strlen($value);
    if ($len <= 8) return str_repeat('*', $len);
    return substr($value, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($value, -4);
}
