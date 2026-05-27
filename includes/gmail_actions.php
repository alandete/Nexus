<?php
/**
 * Nexus 2.0 — Gmail IMAP Integration Actions
 * Acciones: get, save, test, sync
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

function gmailSave(array $newData): bool
{
    global $userApiFile;
    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    $current = file_exists($userApiFile)
        ? (json_decode(file_get_contents($userApiFile), true) ?? [])
        : [];
    $merged = array_merge($current, $newData);
    return file_put_contents($userApiFile, json_encode($merged, JSON_PRETTY_PRINT), LOCK_EX) !== false;
}

// ── Leer configuracion ─────────────────────────────────────────────────────
if ($action === 'get') {
    $raw     = gmailGetRaw();
    $appPass = decryptApiValue($raw['gmail_app_password'] ?? '');
    $preview = '';
    if (!empty($appPass)) {
        $len     = strlen($appPass);
        $preview = str_repeat('*', max(4, $len - 4)) . substr($appPass, -4);
    }
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

// ── Guardar ────────────────────────────────────────────────────────────────
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
    ];

    if (empty($password) || str_contains($password, '****')) {
        $data['gmail_app_password'] = $raw['gmail_app_password'] ?? '';
    } else {
        $data['gmail_app_password'] = encryptApiValue($password);
    }

    if (!gmailSave($data)) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar la configuracion']);
        exit;
    }

    logActivity('settings', 'update', 'api_settings:gmail');
    echo json_encode(['success' => true, 'message' => 'Configuracion de Gmail guardada']);
    exit;
}

// ── Probar conexion ────────────────────────────────────────────────────────
if ($action === 'test') {
    if (!extension_loaded('imap')) {
        echo json_encode(['success' => false, 'message' => 'La extension IMAP de PHP no esta habilitada en el servidor']);
        exit;
    }

    $raw     = gmailGetRaw();
    $email   = $raw['gmail_email'] ?? '';
    $appPass = decryptApiValue($raw['gmail_app_password'] ?? '');
    $label   = $raw['gmail_label'] ?? 'Nexus';

    if (empty($email) || empty($appPass)) {
        echo json_encode(['success' => false, 'message' => 'Faltan credenciales. Guarda primero el correo y la contrasena de aplicacion.']);
        exit;
    }

    $mbox = @imap_open('{imap.gmail.com:993/imap/ssl}INBOX', $email, $appPass, OP_HALFOPEN, 1);
    if (!$mbox) {
        $errors = imap_errors() ?: [];
        $msg    = !empty($errors) ? implode(' | ', $errors) : 'Credenciales incorrectas o IMAP no habilitado en Gmail.';
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

// ── Sincronizar ────────────────────────────────────────────────────────────
if ($action === 'sync') {
    if (!extension_loaded('imap')) {
        echo json_encode(['success' => false, 'synced' => 0, 'message' => 'Extension IMAP no disponible']);
        exit;
    }

    $raw     = gmailGetRaw();
    $email   = $raw['gmail_email'] ?? '';
    $appPass = decryptApiValue($raw['gmail_app_password'] ?? '');
    $label   = $raw['gmail_label'] ?? 'Nexus';

    if (empty($email) || empty($appPass)) {
        echo json_encode(['success' => false, 'synced' => 0, 'message' => 'Gmail no configurado']);
        exit;
    }

    // dismissed_ids: message_ids que el usuario descartó eliminando la tarea en Nexus
    $dismissedIds = $raw['gmail_dismissed_ids'] ?? [];

    $mailbox = '{imap.gmail.com:993/imap/ssl}' . $label;
    $mbox    = @imap_open($mailbox, $email, $appPass, 0, 1);
    if (!$mbox) {
        $errors = imap_errors() ?: [];
        echo json_encode(['success' => false, 'synced' => 0, 'message' => 'Error IMAP: ' . implode(' | ', $errors)]);
        exit;
    }

    // Buscar TODOS los mensajes de la etiqueta (leidos y no leidos)
    $msgs   = imap_search($mbox, 'ALL') ?: [];
    $userId = (int) $currentUser['id'];
    $synced = 0;
    $db     = getDb();

    // Cargar headers y construir presentIds en una sola pasada
    $headers    = [];
    $presentIds = [];
    foreach ($msgs as $msgnum) {
        $h = imap_headerinfo($mbox, $msgnum);
        $headers[$msgnum] = $h;
        if (!empty($h->message_id)) {
            $presentIds[] = trim($h->message_id);
        }
    }

    // ── Limpieza por DB (fuente de verdad) ────────────────────────────────
    // Elimina tareas cuyo correo ya no tiene la etiqueta, incluyendo huérfanas
    // cuya referencia en el mapa pudo haberse perdido.
    if (!empty($presentIds)) {
        $ph = implode(',', array_fill(0, count($presentIds), '?'));
        $db->prepare(
            "DELETE FROM tasks WHERE user_id = ? AND gmail_message_id IS NOT NULL AND gmail_message_id NOT IN ($ph)"
        )->execute(array_merge([$userId], $presentIds));
    } else {
        $db->prepare(
            "DELETE FROM tasks WHERE user_id = ? AND gmail_message_id IS NOT NULL"
        )->execute([$userId]);
    }

    // Limpiar dismissed_ids que ya no están en la etiqueta (el correo fue quitado)
    $dismissedIds = array_values(array_intersect($dismissedIds, $presentIds));

    if ($msgs) {
        // ── Cruzar alianzas por etiqueta de Gmail ─────────────────────────
        $messageAllianceMap = [];

        try {
            $allianceStmt    = $db->query("SELECT id, name FROM alliances WHERE active = 1");
            $activeAlliances = $allianceStmt->fetchAll(PDO::FETCH_ASSOC);

            $gmailBoxes    = @imap_getmailboxes($mbox, '{imap.gmail.com:993/imap/ssl}', '*') ?: [];
            $gmailLabelMap = [];
            foreach ($gmailBoxes as $box) {
                $boxLabel = imap_utf7_decode(str_replace('{imap.gmail.com:993/imap/ssl}', '', $box->name));
                $gmailLabelMap[mb_strtolower($boxLabel)] = $boxLabel;
            }

            foreach ($activeAlliances as $alliance) {
                $lowerName = mb_strtolower($alliance['name']);
                if (!isset($gmailLabelMap[$lowerName])) continue;

                $alliancePath = '{imap.gmail.com:993/imap/ssl}' . $gmailLabelMap[$lowerName];
                if (!@imap_reopen($mbox, $alliancePath)) { @imap_errors(); continue; }

                $allianceMsgs = @imap_search($mbox, 'ALL') ?: [];
                foreach ($allianceMsgs as $mn) {
                    $ah  = @imap_headerinfo($mbox, $mn);
                    $mid = trim($ah->message_id ?? '');
                    if (!empty($mid) && in_array($mid, $presentIds, true)) {
                        $messageAllianceMap[$mid] = $alliance['id'];
                    }
                }
                @imap_errors();
            }
        } catch (Exception $e) {}

        // ── Tag "Correo" — find or create ─────────────────────────────────
        $tagStmt = $db->prepare("SELECT id FROM tags WHERE LOWER(name) = 'correo' LIMIT 1");
        $tagStmt->execute();
        $correoTagId = $tagStmt->fetchColumn();
        if (!$correoTagId) {
            $db->prepare("INSERT INTO tags (name, color) VALUES ('Correo', '#1a73e8')")->execute();
            $correoTagId = (int) $db->lastInsertId();
        }

        $existsStmt = $db->prepare("SELECT id FROM tasks WHERE gmail_message_id = ? AND user_id = ?");
        $tagInsStmt = $db->prepare("INSERT IGNORE INTO task_tags (task_id, tag_id) VALUES (?, ?)");

        foreach ($msgs as $msgnum) {
            $header    = $headers[$msgnum];
            $messageId = trim($header->message_id ?? '');

            // Descartado por el usuario en Nexus: no recrear
            if (!empty($messageId) && in_array($messageId, $dismissedIds, true)) continue;

            // Tarea ya existe en DB para este mensaje: no duplicar
            if (!empty($messageId)) {
                $existsStmt->execute([$messageId, $userId]);
                if ($existsStmt->fetchColumn()) continue;
            }

            // Ignorar respuestas a mensajes que también están en la etiqueta
            $inReplyTo = trim($header->in_reply_to ?? '');
            if (!empty($inReplyTo) && in_array($inReplyTo, $presentIds, true)) continue;

            $subject = isset($header->subject) ? imap_utf8($header->subject) : '';
            $subject = mb_substr(trim($subject), 0, 200);
            if (empty($subject)) $subject = '(sin asunto)';

            $dueDate = null;
            if (!empty($header->date)) {
                $ts = strtotime($header->date);
                if ($ts !== false) $dueDate = date('Y-m-d', $ts);
            }

            $allianceId = $messageAllianceMap[$messageId] ?? null;

            $stmt = $db->prepare(
                "INSERT INTO tasks (user_id, alliance_id, title, status, due_date, gmail_message_id, created_at, updated_at)
                 VALUES (?, ?, ?, 'pending', ?, ?, NOW(), NOW())"
            );
            $stmt->execute([$userId, $allianceId, $subject, $dueDate, $messageId ?: null]);
            $newTaskId = (int) $db->lastInsertId();

            $tagInsStmt->execute([$newTaskId, $correoTagId]);
            $synced++;
        }
    }

    imap_close($mbox);

    gmailSave([
        'gmail_last_sync'     => date('Y-m-d H:i:s'),
        'gmail_dismissed_ids' => $dismissedIds,
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
