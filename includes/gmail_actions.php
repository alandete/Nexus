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

// ── Helpers ────────────────────────────────────────────────────────────────

function gmailGetRaw(): array
{
    if (!file_exists(API_SETTINGS_FILE)) return [];
    return json_decode(file_get_contents(API_SETTINGS_FILE), true) ?? [];
}

function gmailSave(array $newData): bool
{
    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    $current = file_exists(API_SETTINGS_FILE)
        ? (json_decode(file_get_contents(API_SETTINGS_FILE), true) ?? [])
        : [];
    $merged = array_merge($current, $newData);
    return file_put_contents(API_SETTINGS_FILE, json_encode($merged, JSON_PRETTY_PRINT), LOCK_EX) !== false;
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
    if ($currentUser['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Solo el administrador puede gestionar las integraciones']);
        exit;
    }

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

    // Mapa message_id => task_id para verificar si la tarea sigue existiendo
    $processedMap = $raw['gmail_processed_map'] ?? [];

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

    if ($msgs) {
        $db = getDb();

        // Cargar todos los headers de esta tanda para deduplicar hilos
        $headers = [];
        foreach ($msgs as $msgnum) {
            $headers[$msgnum] = imap_headerinfo($mbox, $msgnum);
        }

        // Construir el set de Message-IDs presentes en esta etiqueta
        $presentIds = [];
        foreach ($headers as $h) {
            if (!empty($h->message_id)) {
                $presentIds[] = trim($h->message_id);
            }
        }

        // ── Cruzar alianzas por etiqueta de Gmail ─────────────────────────
        // Usa X-GM-LABELS para buscar mensajes con etiqueta de alianza
        // SIN cambiar de carpeta — seguimos en la carpeta Nexus
        $messageAllianceMap = []; // message_id => alliance_id

        try {
            $allianceStmt    = $db->query("SELECT id, name FROM alliances WHERE active = 1");
            $activeAlliances = $allianceStmt->fetchAll(PDO::FETCH_ASSOC);

            // Listar carpetas reales de Gmail para matching case-insensitive
            $gmailBoxes    = @imap_getmailboxes($mbox, '{imap.gmail.com:993/imap/ssl}', '*') ?: [];
            $gmailLabelMap = []; // lowercase => nombre exacto en Gmail
            foreach ($gmailBoxes as $box) {
                $label = imap_utf7_decode(str_replace('{imap.gmail.com:993/imap/ssl}', '', $box->name));
                $gmailLabelMap[mb_strtolower($label)] = $label;
            }

            foreach ($activeAlliances as $alliance) {
                $lowerName = mb_strtolower($alliance['name']);
                if (!isset($gmailLabelMap[$lowerName])) continue;

                // Abrir la carpeta de la alianza y buscar los Message-IDs que coincidan
                $alliancePath = '{imap.gmail.com:993/imap/ssl}' . $gmailLabelMap[$lowerName];
                if (!@imap_reopen($mbox, $alliancePath)) {
                    @imap_errors(); // limpiar errores acumulados
                    continue;
                }

                $allianceMsgs = @imap_search($mbox, 'ALL') ?: [];
                foreach ($allianceMsgs as $mn) {
                    $ah  = @imap_headerinfo($mbox, $mn);
                    $mid = trim($ah->message_id ?? '');
                    if (!empty($mid) && in_array($mid, $presentIds, true)) {
                        $messageAllianceMap[$mid] = $alliance['id'];
                    }
                }
                @imap_errors(); // limpiar errores tras cada carpeta
            }
            // $mbox queda en la última carpeta de alianza abierta
            // — no importa, ya no se usa para IMAP después de este bloque
        } catch (Exception $e) {
            // Continuar sin alianza si algo falla
        }

        // ── Tag "Correo" — find or create ─────────────────────────────────
        $tagStmt = $db->prepare("SELECT id FROM tags WHERE LOWER(name) = 'correo' LIMIT 1");
        $tagStmt->execute();
        $correoTagId = $tagStmt->fetchColumn();
        if (!$correoTagId) {
            $db->prepare("INSERT INTO tags (name, color) VALUES ('Correo', '#1a73e8')")->execute();
            $correoTagId = (int) $db->lastInsertId();
        }

        $checkStmt  = $db->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
        $tagInsStmt = $db->prepare("INSERT IGNORE INTO task_tags (task_id, tag_id) VALUES (?, ?)");

        foreach ($msgs as $msgnum) {
            $header    = $headers[$msgnum];
            $messageId = trim($header->message_id ?? '');

            // Si ya fue procesado, verificar que la tarea siga existiendo
            if (!empty($messageId) && isset($processedMap[$messageId])) {
                $checkStmt->execute([$processedMap[$messageId], $userId]);
                if ($checkStmt->fetchColumn()) {
                    continue; // Tarea existe, no duplicar
                }
                // Tarea fue eliminada: limpiar el registro y volver a crear
                unset($processedMap[$messageId]);
            }

            // Si es una respuesta a otro mensaje que también tiene la etiqueta, saltarlo
            $inReplyTo = trim($header->in_reply_to ?? '');
            if (!empty($inReplyTo) && in_array($inReplyTo, $presentIds, true)) {
                continue;
            }

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
                "INSERT INTO tasks (user_id, alliance_id, title, status, due_date, created_at, updated_at)
                 VALUES (?, ?, ?, 'pending', ?, NOW(), NOW())"
            );
            $stmt->execute([$userId, $allianceId, $subject, $dueDate]);
            $newTaskId = (int) $db->lastInsertId();

            // Etiqueta "Correo" en todas las tareas creadas desde Gmail
            $tagInsStmt->execute([$newTaskId, $correoTagId]);

            if (!empty($messageId)) {
                $processedMap[$messageId] = $newTaskId;
            }
            $synced++;
        }
    }

    imap_close($mbox);

    // Mantener solo los ultimos 500 registros para no crecer indefinidamente
    if (count($processedMap) > 500) {
        $processedMap = array_slice($processedMap, -500, null, true);
    }

    gmailSave([
        'gmail_last_sync'       => date('Y-m-d H:i:s'),
        'gmail_processed_map'   => $processedMap,
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
