<?php
/**
 * Nexus 2.0 — Integración Gmail IMAP
 *
 * GET/POST actions:
 *   get    → devuelve config del usuario
 *   save   → guarda credenciales
 *   test   → verifica conexión IMAP
 *   sync   → importa correos etiquetados como tareas
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

if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalido']);
    exit;
}

$currentUser = getCurrentUser();
$action      = $_POST['action'] ?? '';
$safe        = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $currentUser['username']);
$userApiFile = DATA_PATH . '/user_api_' . $safe . '.json';

// ── Helpers ────────────────────────────────────────────────────────────────

function gmailGetRaw(): array
{
    global $userApiFile;
    if (!file_exists($userApiFile)) return [];
    return json_decode(file_get_contents($userApiFile), true) ?? [];
}

function gmailSave(array $data): bool
{
    global $userApiFile;
    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    $current = file_exists($userApiFile)
        ? (json_decode(file_get_contents($userApiFile), true) ?? [])
        : [];
    return file_put_contents(
        $userApiFile,
        json_encode(array_merge($current, $data), JSON_PRETTY_PRINT),
        LOCK_EX
    ) !== false;
}

function gmailCredentials(): array
{
    $raw = gmailGetRaw();
    return [
        'email'  => $raw['gmail_email'] ?? '',
        'pass'   => decryptApiValue($raw['gmail_app_password'] ?? ''),
        'label'  => $raw['gmail_label'] ?? 'Nexus',
    ];
}

// ── GET: leer configuración ────────────────────────────────────────────────
if ($action === 'get') {
    $raw     = gmailGetRaw();
    $appPass = decryptApiValue($raw['gmail_app_password'] ?? '');
    $preview = $appPass
        ? str_repeat('*', max(4, strlen($appPass) - 4)) . substr($appPass, -4)
        : '';

    echo json_encode([
        'success'                    => true,
        'gmail_email'                => $raw['gmail_email'] ?? '',
        'gmail_label'                => $raw['gmail_label'] ?? 'Nexus',
        'gmail_app_password_preview' => $preview,
        'gmail_configured'           => !empty($raw['gmail_email']) && !empty($raw['gmail_app_password']),
        'gmail_last_sync'            => $raw['gmail_last_sync'] ?? null,
    ]);
    exit;
}

// ── SAVE: guardar credenciales ─────────────────────────────────────────────
if ($action === 'save') {
    $email    = trim($_POST['gmail_email'] ?? '');
    $password = trim($_POST['gmail_app_password'] ?? '');
    $label    = trim($_POST['gmail_label'] ?? 'Nexus');

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Correo no valido']);
        exit;
    }

    $raw  = gmailGetRaw();
    $data = [
        'gmail_email' => !empty($email) ? $email : ($raw['gmail_email'] ?? ''),
        'gmail_label' => !empty($label) ? $label : 'Nexus',
        'gmail_app_password' => (empty($password) || str_contains($password, '****'))
            ? ($raw['gmail_app_password'] ?? '')
            : encryptApiValue($password),
    ];

    if (!gmailSave($data)) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la configuracion']);
        exit;
    }

    logActivity('settings', 'update', 'api_settings:gmail');
    echo json_encode(['success' => true, 'message' => 'Configuracion de Gmail guardada']);
    exit;
}

// ── TEST: verificar conexión IMAP ──────────────────────────────────────────
if ($action === 'test') {
    if (!extension_loaded('imap')) {
        echo json_encode(['success' => false, 'message' => 'La extension IMAP de PHP no esta habilitada en el servidor']);
        exit;
    }

    ['email' => $email, 'pass' => $appPass, 'label' => $label] = gmailCredentials();

    if (empty($email) || empty($appPass)) {
        echo json_encode(['success' => false, 'message' => 'Faltan credenciales. Guarda primero el correo y la contrasena de aplicacion.']);
        exit;
    }

    $mbox = @imap_open('{imap.gmail.com:993/imap/ssl}INBOX', $email, $appPass, OP_HALFOPEN, 1);
    if (!$mbox) {
        $errors = imap_errors() ?: [];
        $msg    = $errors ? implode(' | ', $errors) : 'Credenciales incorrectas o IMAP no habilitado en Gmail.';
        echo json_encode(['success' => false, 'message' => 'No se pudo conectar: ' . $msg]);
        exit;
    }
    imap_close($mbox);

    echo json_encode([
        'success' => true,
        'message' => 'Conexion con Gmail verificada. Etiqueta configurada: "' . htmlspecialchars($label, ENT_QUOTES) . '".',
    ]);
    exit;
}

// ── SYNC: importar correos como tareas ────────────────────────────────────
if ($action === 'sync') {
    if (!extension_loaded('imap')) {
        echo json_encode(['success' => false, 'synced' => 0, 'message' => 'Extension IMAP no disponible']);
        exit;
    }

    ['email' => $email, 'pass' => $appPass, 'label' => $label] = gmailCredentials();

    if (empty($email) || empty($appPass)) {
        echo json_encode(['success' => false, 'synced' => 0, 'message' => 'Gmail no configurado']);
        exit;
    }

    $raw       = gmailGetRaw();
    // Legado: mapa de tareas creadas antes de que gmail_message_id existiera en la DB.
    // Se purga automáticamente al hacer backfill en cada sync.
    $legacyMap = $raw['gmail_processed_map'] ?? [];

    // ── Conectar ───────────────────────────────────────────────────────────
    $mbox = @imap_open('{imap.gmail.com:993/imap/ssl}' . $label, $email, $appPass, 0, 1);
    if (!$mbox) {
        $errors = imap_errors() ?: [];
        echo json_encode(['success' => false, 'synced' => 0, 'message' => 'Error IMAP: ' . implode(' | ', $errors)]);
        exit;
    }

    $userId = (int) $currentUser['id'];
    $db     = getDb();
    $synced = 0;

    // ── 1. Leer mensajes presentes en la etiqueta ──────────────────────────
    $msgs       = imap_search($mbox, 'ALL') ?: [];
    $headers    = [];
    $presentIds = [];

    foreach ($msgs as $num) {
        $h = imap_headerinfo($mbox, $num);
        $headers[$num] = $h;
        if (!empty($h->message_id)) {
            $presentIds[] = trim($h->message_id);
        }
    }

    // ── 2. Eliminar tareas cuyo correo ya no tiene la etiqueta ─────────────

    // 2a. Tareas post-migración: gmail_message_id en DB como fuente de verdad
    if (!empty($presentIds)) {
        $ph = implode(',', array_fill(0, count($presentIds), '?'));
        $db->prepare(
            "DELETE FROM tasks
             WHERE user_id = ? AND gmail_message_id IS NOT NULL AND gmail_message_id NOT IN ($ph)"
        )->execute(array_merge([$userId], $presentIds));
    } else {
        $db->prepare(
            "DELETE FROM tasks WHERE user_id = ? AND gmail_message_id IS NOT NULL"
        )->execute([$userId]);
    }

    // 2b. Tareas pre-migración (gmail_message_id = NULL): limpiar via legacyMap
    foreach ($legacyMap as $mid => $taskId) {
        if (!in_array($mid, $presentIds, true)) {
            if ($taskId > 0) {
                $db->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?")->execute([$taskId, $userId]);
            }
            unset($legacyMap[$mid]);
        }
    }

    // ── 3. Procesar mensajes (si hay alguno) ───────────────────────────────
    if ($msgs) {

        // ── 3a. Mapear mensaje → alianza via etiquetas de Gmail ───────────
        $messageAllianceMap = [];
        try {
            $activeAlliances = $db->query("SELECT id, name FROM alliances WHERE active = 1")
                                  ->fetchAll(PDO::FETCH_ASSOC);

            // Índice de carpetas Gmail por nombre en minúsculas
            $gmailFolders = [];
            foreach (@imap_getmailboxes($mbox, '{imap.gmail.com:993/imap/ssl}', '*') ?: [] as $box) {
                $name = imap_utf7_decode(str_replace('{imap.gmail.com:993/imap/ssl}', '', $box->name));
                $gmailFolders[mb_strtolower($name)] = $name;
            }

            foreach ($activeAlliances as $alliance) {
                $folderKey = mb_strtolower($alliance['name']);
                if (!isset($gmailFolders[$folderKey])) continue;

                $path = '{imap.gmail.com:993/imap/ssl}' . $gmailFolders[$folderKey];
                if (!@imap_reopen($mbox, $path)) { @imap_errors(); continue; }

                foreach (@imap_search($mbox, 'ALL') ?: [] as $mn) {
                    $mid = trim(@imap_headerinfo($mbox, $mn)->message_id ?? '');
                    if ($mid && in_array($mid, $presentIds, true)) {
                        $messageAllianceMap[$mid] = $alliance['id'];
                    }
                }
                @imap_errors();
            }
        } catch (Exception $e) {
            // Continuar sin alianza si algo falla
        }

        // ── 3b. Tag "Correo" — obtener o crear ────────────────────────────
        $tagStmt = $db->prepare("SELECT id FROM tags WHERE LOWER(name) = 'correo' LIMIT 1");
        $tagStmt->execute();
        $correoTagId = $tagStmt->fetchColumn();
        if (!$correoTagId) {
            $db->prepare("INSERT INTO tags (name, color) VALUES ('Correo', '#1a73e8')")->execute();
            $correoTagId = (int) $db->lastInsertId();
        }

        // Prepared statements reutilizables
        $stmtExists   = $db->prepare("SELECT id FROM tasks WHERE gmail_message_id = ? AND user_id = ?");
        $stmtCheck    = $db->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
        $stmtBackfill = $db->prepare("UPDATE tasks SET gmail_message_id = ? WHERE id = ? AND user_id = ? AND gmail_message_id IS NULL");
        $stmtInsert   = $db->prepare(
            "INSERT INTO tasks (user_id, alliance_id, title, status, due_date, gmail_message_id, created_at, updated_at)
             VALUES (?, ?, ?, 'pending', ?, ?, NOW(), NOW())"
        );
        $stmtTag      = $db->prepare("INSERT IGNORE INTO task_tags (task_id, tag_id) VALUES (?, ?)");

        foreach ($msgs as $num) {
            $header    = $headers[$num];
            $messageId = trim($header->message_id ?? '');

            // a) Ya existe en DB por gmail_message_id (sistema nuevo)
            if ($messageId) {
                $stmtExists->execute([$messageId, $userId]);
                if ($stmtExists->fetchColumn()) continue;
            }

            // c) Backfill de tarea pre-migración registrada en legacyMap
            if ($messageId && isset($legacyMap[$messageId])) {
                $legacyId = $legacyMap[$messageId];
                $stmtCheck->execute([$legacyId, $userId]);
                if ($stmtCheck->fetchColumn()) {
                    $stmtBackfill->execute([$messageId, $legacyId, $userId]);
                    unset($legacyMap[$messageId]);
                    continue; // tarea existente migrada, no duplicar
                }
                // Tarea del legacyMap ya no existe: descartar el correo
                $dismissedIds[] = $messageId;
                unset($legacyMap[$messageId]);
                continue;
            }

            // d) Ignorar respuestas a mensajes que también están en la etiqueta
            $inReplyTo = trim($header->in_reply_to ?? '');
            if ($inReplyTo && in_array($inReplyTo, $presentIds, true)) continue;

            // e) Crear nueva tarea
            $subject = $header->subject ? mb_substr(trim(imap_utf8($header->subject)), 0, 200) : '(sin asunto)';
            $dueDate = null;
            if (!empty($header->date)) {
                $ts = strtotime($header->date);
                if ($ts !== false) $dueDate = date('Y-m-d', $ts);
            }

            $stmtInsert->execute([$userId, $messageAllianceMap[$messageId] ?? null, $subject, $dueDate, $messageId ?: null]);
            $stmtTag->execute([(int) $db->lastInsertId(), $correoTagId]);
            $synced++;
        }
    }

    imap_close($mbox);

    // ── 4. Persistir estado ────────────────────────────────────────────────
    gmailSave([
        'gmail_last_sync'     => date('Y-m-d H:i:s'),
        'gmail_processed_map' => $legacyMap, // vacío cuando todos los registros hayan hecho backfill
    ]);

    if ($synced > 0) {
        logActivity('tasks', 'gmail_sync', "synced:$synced");
    }

    echo json_encode([
        'success' => true,
        'synced'  => $synced,
        'message' => $synced > 0
            ? "$synced tarea(s) creada(s) desde Gmail"
            : 'Sin correos nuevos en la etiqueta "' . htmlspecialchars($label, ENT_QUOTES) . '"',
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Accion no valida']);
