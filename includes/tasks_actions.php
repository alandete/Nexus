<?php
/**
 * NexusApp - Tasks & Time Tracker API
 * Gestión de tareas, cronómetro, etiquetas y entradas de tiempo
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
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$currentUser = getCurrentUser();
$db = getDB();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Base de datos no disponible']);
    exit;
}

$userStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
$userStmt->execute([$currentUser['username']]);
$userId = (int) $userStmt->fetchColumn();

if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    // Tareas
    case 'list':           listTasks($db, $userId); break;
    case 'create':         createTask($db, $userId); break;
    case 'update':         updateTask($db, $userId); break;
    case 'delete':         deleteTask($db, $userId); break;
    case 'get':            getTask($db, $userId); break;
    case 'search':         searchTasks($db, $userId); break;

    // Cronómetro
    case 'timer_start':    timerStart($db, $userId); break;
    case 'timer_pause':    timerPause($db, $userId); break;
    case 'timer_stop':     timerStop($db, $userId); break;
    case 'timer_status':   timerStatus($db, $userId); break;
    case 'timer_discard':  timerDiscard($db, $userId); break;

    // Tiempo manual
    case 'log_manual':     logManual($db, $userId); break;

    // Entradas de tiempo
    case 'time_entries':   getTimeEntries($db, $userId); break;
    case 'time_entry_update': timeEntryUpdate($db, $userId); break;
    case 'time_entry_delete': timeEntryDelete($db, $userId); break;
    case 'day_summary':       daySummary($db, $userId); break;
    case 'day_summary_range': daySummaryRange($db, $userId); break;

    // Etiquetas
    case 'tags_list':      tagsList($db); break;
    case 'tags_create':    tagsCreate($db); break;
    case 'tags_update':    tagsUpdate($db); break;
    case 'tags_delete':    tagsDelete($db); break;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

// ═════════════════════════════════════════════════════════════════════════════
// TAREAS
// ═════════════════════════════════════════════════════════════════════════════

function getTask(PDO $db, int $userId): void
{
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $stmt = $db->prepare("
        SELECT t.*, a.name AS alliance_name,
               GROUP_CONCAT(DISTINCT tg.name ORDER BY tg.name SEPARATOR ', ') AS tag_names,
               GROUP_CONCAT(DISTINCT tg.id ORDER BY tg.name SEPARATOR ',') AS tag_ids
        FROM tasks t
        LEFT JOIN alliances a ON t.alliance_id = a.id
        LEFT JOIN task_tags tt ON t.id = tt.task_id
        LEFT JOIN tags tg ON tt.tag_id = tg.id
        WHERE t.id = ? AND t.user_id = ?
        GROUP BY t.id
    ");
    $stmt->execute([$taskId, $userId]);
    $task = $stmt->fetch();
    if (!$task) { echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']); return; }
    echo json_encode(['success' => true, 'task' => $task]);
}

function listTasks(PDO $db, int $userId): void
{
    // NOTA: el filtro de alianza y otros filtros del cliente NO se aplican en el backend.
    // El frontend los aplica localmente solo a las secciones historicas (Ayer + Historial).
    // Las secciones "actuales" (Proximas, Activas, Hoy) se muestran siempre completas.
    $dateFrom = $_POST['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
    $dateTo   = $_POST['date_to'] ?? date('Y-m-d');

    // Entradas de tiempo con info completa (rango de fechas aplica porque determina
    // que data cargar; la alianza/etiquetas/prioridad se filtran en cliente).
    $where = "te.user_id = ? AND DATE(te.start_time) BETWEEN ? AND ? AND te.end_time IS NOT NULL";
    $params = [$userId, $dateFrom, $dateTo];

    $entriesStmt = $db->prepare("
        SELECT te.*, t.title AS task_title, t.description AS task_description, t.status AS task_status, t.alliance_id, t.priority,
               a.name AS alliance_name, a.color AS alliance_color,
               GROUP_CONCAT(DISTINCT tg.name ORDER BY tg.name SEPARATOR ', ') AS tag_names,
               GROUP_CONCAT(DISTINCT tg.id ORDER BY tg.name SEPARATOR ',') AS tag_ids
        FROM time_entries te
        JOIN tasks t ON te.task_id = t.id
        LEFT JOIN alliances a ON t.alliance_id = a.id
        LEFT JOIN task_tags tt ON t.id = tt.task_id
        LEFT JOIN tags tg ON tt.tag_id = tg.id
        WHERE {$where}
        GROUP BY te.id
        ORDER BY te.start_time DESC
    ");
    $entriesStmt->execute($params);
    $entries = $entriesStmt->fetchAll();

    // Agrupar por fecha
    $byDate = [];
    foreach ($entries as $e) {
        $date = substr($e['start_time'], 0, 10);
        $byDate[$date][] = $e;
    }

    // Totales por fecha
    $dayTotals = [];
    foreach ($byDate as $date => $dayEntries) {
        $dayTotals[$date] = array_sum(array_column($dayEntries, 'duration_seconds'));
    }

    // Tareas programadas sin entradas (pendientes, en progreso)
    // Sin filtro de alianza: es vista "actual" y el usuario debe ver todas.
    $scheduledWhere = "t.user_id = ? AND t.status IN ('pending', 'in_progress')";
    $scheduledParams = [$userId];

    // Auto-urgente: actualizar prioridad de tareas vencidas (excluye recurrentes)
    $db->prepare("UPDATE tasks SET priority = 'urgent' WHERE user_id = ? AND due_date < CURDATE() AND status IN ('pending', 'in_progress') AND priority != 'urgent' AND is_recurring = 0")
       ->execute([$userId]);

    $scheduledStmt = $db->prepare("
        SELECT t.*, a.name AS alliance_name, a.color AS alliance_color,
               GROUP_CONCAT(DISTINCT tg.name ORDER BY tg.name SEPARATOR ', ') AS tag_names,
               GROUP_CONCAT(DISTINCT tg.id ORDER BY tg.name SEPARATOR ',') AS tag_ids,
               (SELECT COUNT(*) FROM time_entries WHERE task_id = t.id) AS entry_count
        FROM tasks t
        LEFT JOIN alliances a ON t.alliance_id = a.id
        LEFT JOIN task_tags tt ON t.id = tt.task_id
        LEFT JOIN tags tg ON tt.tag_id = tg.id
        WHERE {$scheduledWhere}
        GROUP BY t.id
        HAVING entry_count = 0
        ORDER BY FIELD(t.priority, 'urgent', 'high', 'medium', 'low'), t.due_date ASC
    ");
    $scheduledStmt->execute($scheduledParams);
    $scheduled = $scheduledStmt->fetchAll();

    // Tareas activas (en progreso o pausa)
    $activeStmt = $db->prepare("
        SELECT t.*, a.name AS alliance_name, a.color AS alliance_color,
               GROUP_CONCAT(DISTINCT tg.name ORDER BY tg.name SEPARATOR ', ') AS tag_names,
               GROUP_CONCAT(DISTINCT tg.id ORDER BY tg.name SEPARATOR ',') AS tag_ids,
               (SELECT SUM(duration_seconds) FROM time_entries WHERE task_id = t.id AND end_time IS NOT NULL) AS total_seconds
        FROM tasks t
        LEFT JOIN alliances a ON t.alliance_id = a.id
        LEFT JOIN task_tags tt ON t.id = tt.task_id
        LEFT JOIN tags tg ON tt.tag_id = tg.id
        WHERE t.user_id = ? AND t.status IN ('in_progress', 'paused')
        GROUP BY t.id
        ORDER BY t.updated_at DESC
    ");
    $activeStmt->execute([$userId]);
    $activeTasks = $activeStmt->fetchAll();

    echo json_encode([
        'success'    => true,
        'by_date'    => $byDate,
        'day_totals' => $dayTotals,
        'scheduled'  => $scheduled,
        'active'     => $activeTasks,
    ]);
}

function createTask(PDO $db, int $userId): void
{
    $title     = trim($_POST['title'] ?? '');
    $allianceId= !empty($_POST['alliance_id']) ? (int) $_POST['alliance_id'] : null;
    $tagIdsRaw = trim($_POST['tag_ids'] ?? '');
    $tagIds    = $tagIdsRaw !== '' ? array_filter(array_map('intval', explode(',', $tagIdsRaw))) : [];

    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'El título es obligatorio']);
        return;
    }

    // Regla: ninguna tarea programada puede crearse sin alianza + al menos una etiqueta
    $missing = [];
    if (!$allianceId)    $missing[] = 'alianza';
    if (empty($tagIds))  $missing[] = 'etiqueta';
    if (!empty($missing)) {
        echo json_encode([
            'success' => false,
            'missing' => $missing,
            'message' => 'Para programar una tarea debes indicar: ' . implode(' y ', $missing),
        ]);
        return;
    }

    $stmt = $db->prepare("INSERT INTO tasks (user_id, alliance_id, title, description, due_date, priority, is_recurring, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        $allianceId,
        $title,
        trim($_POST['description'] ?? '') ?: null,
        !empty($_POST['due_date']) ? $_POST['due_date'] : null,
        in_array($_POST['priority'] ?? '', ['low', 'medium', 'high', 'urgent']) ? $_POST['priority'] : 'medium',
        !empty($_POST['is_recurring']) ? 1 : 0,
        $_POST['status'] ?? 'pending',
    ]);

    $taskId = (int) $db->lastInsertId();
    assignTags($db, $taskId, $tagIdsRaw);

    echo json_encode(['success' => true, 'message' => 'Tarea creada', 'task_id' => $taskId]);
}

function updateTask(PDO $db, int $userId): void
{
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $check = $db->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $check->execute([$taskId, $userId]);
    if (!$check->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
        return;
    }

    $fields = [];
    $params = [];

    foreach (['title', 'description', 'due_date', 'priority', 'status'] as $f) {
        if (isset($_POST[$f])) {
            $fields[] = "{$f} = ?";
            $val = trim($_POST[$f]);
            $params[] = $val !== '' ? $val : null;
        }
    }
    if (isset($_POST['alliance_id'])) {
        $fields[] = "alliance_id = ?";
        $params[] = $_POST['alliance_id'] !== '' ? (int) $_POST['alliance_id'] : null;
    }
    if (isset($_POST['is_recurring'])) {
        $fields[] = "is_recurring = ?";
        $params[] = (int) $_POST['is_recurring'];
    }

    if (!empty($fields)) {
        $params[] = $taskId;
        $db->prepare("UPDATE tasks SET " . implode(', ', $fields) . " WHERE id = ?")->execute($params);
    }

    if (isset($_POST['tag_ids'])) {
        assignTags($db, $taskId, $_POST['tag_ids']);
    }

    echo json_encode(['success' => true, 'message' => 'Tarea actualizada']);
}

function deleteTask(PDO $db, int $userId): void
{
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $check = $db->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $check->execute([$taskId, $userId]);
    if (!$check->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Tarea no encontrada']);
        return;
    }

    $db->prepare("DELETE FROM tasks WHERE id = ?")->execute([$taskId]);
    echo json_encode(['success' => true, 'message' => 'Tarea eliminada']);
}

function searchTasks(PDO $db, int $userId): void
{
    $q = trim($_POST['q'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode(['success' => true, 'tasks' => []]);
        return;
    }

    $stmt = $db->prepare("
        SELECT t.id, t.title, t.alliance_id, t.status, t.is_recurring, a.name AS alliance_name,
               GROUP_CONCAT(DISTINCT tg.id   ORDER BY tg.name SEPARATOR ',')  AS tag_ids,
               GROUP_CONCAT(DISTINCT tg.name ORDER BY tg.name SEPARATOR ', ') AS tag_names
        FROM tasks t
        LEFT JOIN alliances a   ON t.alliance_id = a.id
        LEFT JOIN task_tags tt  ON t.id = tt.task_id
        LEFT JOIN tags tg       ON tt.tag_id = tg.id
        WHERE t.user_id = ? AND t.title LIKE ? AND t.status != 'cancelled'
        GROUP BY t.id
        ORDER BY t.updated_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId, "%{$q}%"]);

    echo json_encode(['success' => true, 'tasks' => $stmt->fetchAll()]);
}

function assignTags(PDO $db, int $taskId, string $tagIds): void
{
    $db->prepare("DELETE FROM task_tags WHERE task_id = ?")->execute([$taskId]);

    $ids = array_filter(array_map('intval', explode(',', $tagIds)));
    if (empty($ids)) return;

    $stmt = $db->prepare("INSERT IGNORE INTO task_tags (task_id, tag_id) VALUES (?, ?)");
    foreach ($ids as $tagId) {
        $stmt->execute([$taskId, $tagId]);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// CRONÓMETRO
// ═════════════════════════════════════════════════════════════════════════════

function timerStart(PDO $db, int $userId): void
{
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $title  = trim($_POST['title'] ?? '');

    // Si no hay task_id pero hay titulo, buscar o crear la tarea
    if (!$taskId && !empty($title)) {
        $existing = $db->prepare("SELECT id FROM tasks WHERE user_id = ? AND title = ? AND status != 'cancelled' LIMIT 1");
        $existing->execute([$userId, $title]);
        $taskId = (int) $existing->fetchColumn();

        if (!$taskId) {
            // Tareas creadas desde el rastreador toman la fecha del dia como vencimiento por defecto
            $ins = $db->prepare("INSERT INTO tasks (user_id, alliance_id, title, status, due_date) VALUES (?, ?, ?, 'in_progress', CURDATE())");
            $ins->execute([
                $userId,
                !empty($_POST['alliance_id']) ? (int) $_POST['alliance_id'] : null,
                $title,
            ]);
            $taskId = (int) $db->lastInsertId();

            // Asignar etiquetas si vienen
            if (!empty($_POST['tag_ids'])) {
                assignTags($db, $taskId, $_POST['tag_ids']);
            }
        }
    }

    if (!$taskId) {
        echo json_encode(['success' => false, 'message' => 'Indique el nombre de la tarea']);
        return;
    }

    // Verificar propiedad y obtener estado actual
    $check = $db->prepare("SELECT id, status, alliance_id, title, is_recurring FROM tasks WHERE id = ? AND user_id = ?");
    $check->execute([$taskId, $userId]);
    $existingTask = $check->fetch();
    if (!$existingTask) {
        echo json_encode(['success' => false, 'message' => 'Tarea no válida']);
        return;
    }

    // Tarea completada: comportamiento según si es recurrente y si fue el mismo día
    if ($existingTask['status'] === 'completed') {
        $recurring = (bool) $existingTask['is_recurring'];

        $lastDayStmt = $db->prepare("SELECT MAX(DATE(start_time)) FROM time_entries WHERE task_id = ? AND user_id = ?");
        $lastDayStmt->execute([$taskId, $userId]);
        $lastDay = $lastDayStmt->fetchColumn();
        $sameDay = ($lastDay === date('Y-m-d'));

        if (!$recurring && !$sameDay) {
            // No recurrente + día distinto → instancia nueva sin due_date
            $ins = $db->prepare("INSERT INTO tasks (user_id, alliance_id, title, status) VALUES (?, ?, ?, 'in_progress')");
            $ins->execute([$userId, $existingTask['alliance_id'], $existingTask['title']]);
            $newTaskId = (int) $db->lastInsertId();

            $tagStmt = $db->prepare("SELECT tag_id FROM task_tags WHERE task_id = ?");
            $tagStmt->execute([$taskId]);
            $existingTagIds = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
            if ($existingTagIds) {
                $insTag = $db->prepare("INSERT IGNORE INTO task_tags (task_id, tag_id) VALUES (?, ?)");
                foreach ($existingTagIds as $tid) { $insTag->execute([$newTaskId, $tid]); }
            }

            $taskId = $newTaskId;
        } elseif (!$recurring && $sameDay) {
            // No recurrente + mismo día → reabrir y limpiar due_date vencido
            $db->prepare("UPDATE tasks SET status = 'in_progress', due_date = IF(due_date < CURDATE(), NULL, due_date) WHERE id = ?")
               ->execute([$taskId]);
        } else {
            // Recurrente (cualquier día) → reabrir, conservar historial y due_date
            $db->prepare("UPDATE tasks SET status = 'in_progress' WHERE id = ?")
               ->execute([$taskId]);
        }
    }

    // Detener cualquier timer activo
    $db->prepare("UPDATE time_entries SET end_time = NOW(), duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW()) WHERE user_id = ? AND end_time IS NULL")
       ->execute([$userId]);

    // Crear nueva entrada
    $db->prepare("INSERT INTO time_entries (task_id, user_id, start_time) VALUES (?, ?, NOW())")
       ->execute([$taskId, $userId]);

    // Marcar tarea como en progreso
    $db->prepare("UPDATE tasks SET status = 'in_progress' WHERE id = ? AND status != 'cancelled'")->execute([$taskId]);

    // Retornar info completa de la tarea para el frontend
    $taskInfo = $db->prepare("
        SELECT t.title, t.alliance_id, t.is_recurring, a.name AS alliance_name,
               GROUP_CONCAT(DISTINCT tg.id ORDER BY tg.name SEPARATOR ',') AS tag_ids,
               GROUP_CONCAT(DISTINCT tg.name ORDER BY tg.name SEPARATOR ', ') AS tag_names
        FROM tasks t
        LEFT JOIN alliances a ON t.alliance_id = a.id
        LEFT JOIN task_tags tt ON t.id = tt.task_id
        LEFT JOIN tags tg ON tt.tag_id = tg.id
        WHERE t.id = ?
        GROUP BY t.id
    ");
    $taskInfo->execute([$taskId]);
    $info = $taskInfo->fetch();

    echo json_encode([
        'success'       => true,
        'message'       => 'Cronómetro iniciado',
        'task_id'       => $taskId,
        'title'         => $info['title'] ?? '',
        'alliance_id'   => $info['alliance_id'],
        'alliance_name' => $info['alliance_name'],
        'tag_ids'       => $info['tag_ids'],
        'tag_names'     => $info['tag_names'],
        'is_recurring'  => (int) ($info['is_recurring'] ?? 0),
    ]);
}

function timerPause(PDO $db, int $userId): void
{
    $stmt = $db->prepare("SELECT id, task_id FROM time_entries WHERE user_id = ? AND end_time IS NULL ORDER BY start_time DESC LIMIT 1");
    $stmt->execute([$userId]);
    $active = $stmt->fetch();

    if (!$active) {
        echo json_encode(['success' => false, 'message' => 'No hay cronómetro activo']);
        return;
    }

    // Validar que la tarea tenga alianza y al menos una etiqueta
    $check = validateTaskRequirements($db, (int) $active['task_id']);
    if (!$check['valid']) {
        echo json_encode([
            'success' => false,
            'requires_completion' => true,
            'missing' => $check['missing'],
            'message' => 'Antes de pausar debes completar: ' . implode(' y ', $check['missing']),
        ]);
        return;
    }

    $db->prepare("UPDATE time_entries SET end_time = NOW(), duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW()) WHERE id = ?")
       ->execute([$active['id']]);

    $entry = $db->prepare("SELECT duration_seconds FROM time_entries WHERE id = ?");
    $entry->execute([$active['id']]);
    $duration = (int) $entry->fetchColumn();

    // Estado se mantiene en in_progress
    echo json_encode(['success' => true, 'message' => 'Tarea pausada', 'duration' => $duration, 'task_id' => (int) $active['task_id']]);
}

function timerStop(PDO $db, int $userId): void
{
    $stmt = $db->prepare("SELECT id, task_id, start_time FROM time_entries WHERE user_id = ? AND end_time IS NULL ORDER BY start_time DESC LIMIT 1");
    $stmt->execute([$userId]);
    $active = $stmt->fetch();

    if (!$active) {
        echo json_encode(['success' => false, 'message' => 'No hay cronómetro activo']);
        return;
    }

    // Validar alianza + etiqueta antes de finalizar
    $check = validateTaskRequirements($db, (int) $active['task_id']);
    if (!$check['valid']) {
        echo json_encode([
            'success' => false,
            'requires_completion' => true,
            'missing' => $check['missing'],
            'message' => 'Antes de finalizar debes completar: ' . implode(' y ', $check['missing']),
        ]);
        return;
    }

    $notes = trim($_POST['notes'] ?? '') ?: null;

    $db->prepare("UPDATE time_entries SET end_time = NOW(), duration_seconds = TIMESTAMPDIFF(SECOND, start_time, NOW()), notes = ? WHERE id = ?")
       ->execute([$notes, $active['id']]);

    $entry = $db->prepare("SELECT duration_seconds FROM time_entries WHERE id = ?");
    $entry->execute([$active['id']]);
    $duration = (int) $entry->fetchColumn();

    // Marcar tarea como completada
    $db->prepare("UPDATE tasks SET status = 'completed' WHERE id = ?")->execute([$active['task_id']]);

    echo json_encode(['success' => true, 'message' => 'Tarea completada', 'duration' => $duration, 'task_id' => (int) $active['task_id']]);
}

function timerStatus(PDO $db, int $userId): void
{
    $stmt = $db->prepare("
        SELECT te.id, te.task_id, te.start_time, t.title,
               a.name AS alliance_name, a.color AS alliance_color,
               GROUP_CONCAT(DISTINCT tg.name ORDER BY tg.name SEPARATOR ', ') AS tag_names
        FROM time_entries te
        JOIN tasks t ON te.task_id = t.id
        LEFT JOIN alliances a ON t.alliance_id = a.id
        LEFT JOIN task_tags tt ON t.id = tt.task_id
        LEFT JOIN tags tg ON tt.tag_id = tg.id
        WHERE te.user_id = ? AND te.end_time IS NULL
        GROUP BY te.id
        ORDER BY te.start_time DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $active = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'running' => $active ? true : false,
        'entry'   => $active ?: null,
    ]);
}

function timerDiscard(PDO $db, int $userId): void
{
    // Obtener el task_id y status del timer activo antes de borrarlo
    $stmt = $db->prepare("
        SELECT te.task_id, t.status
        FROM time_entries te
        JOIN tasks t ON te.task_id = t.id
        WHERE te.user_id = ? AND te.end_time IS NULL
        ORDER BY te.start_time DESC LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $discardedTaskId = (int) ($row['task_id'] ?? 0);
    $prevStatus = $row['status'] ?? '';

    // Borrar la entrada activa
    $db->prepare("DELETE FROM time_entries WHERE user_id = ? AND end_time IS NULL")->execute([$userId]);

    if ($discardedTaskId) {
        $countStmt = $db->prepare("SELECT COUNT(*) FROM time_entries WHERE task_id = ?");
        $countStmt->execute([$discardedTaskId]);
        $remaining = (int) $countStmt->fetchColumn();

        if ($remaining === 0) {
            // Sin entries previas. Dos casos:
            // - La tarea estaba 'in_progress' (timer recien arrancado): eliminarla por completo.
            //   El usuario solo estaba probando y no quiere que quede como tarea programada.
            // - La tarea estaba 'paused' o 'pending': resetear status a 'pending' (se conserva la tarea).
            if ($prevStatus === 'in_progress') {
                $db->prepare("DELETE FROM task_tags WHERE task_id = ?")->execute([$discardedTaskId]);
                $db->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?")->execute([$discardedTaskId, $userId]);
            } else {
                $db->prepare("UPDATE tasks SET status = 'pending' WHERE id = ? AND user_id = ? AND status != 'cancelled'")
                   ->execute([$discardedTaskId, $userId]);
            }
        }
    }

    echo json_encode(['success' => true, 'message' => 'Entrada descartada']);
}

// ═════════════════════════════════════════════════════════════════════════════
// HELPERS DE VALIDACION
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Valida que una tarea tenga alianza asignada y al menos una etiqueta.
 * Regla de negocio: ninguna tarea se puede programar, pausar o finalizar sin estos datos.
 * Devuelve array con 'valid' bool y 'missing' lista de campos faltantes.
 */
function validateTaskRequirements(PDO $db, int $taskId): array
{
    $stmt = $db->prepare("
        SELECT t.alliance_id, (SELECT COUNT(*) FROM task_tags WHERE task_id = t.id) AS tag_count
        FROM tasks t WHERE t.id = ?
    ");
    $stmt->execute([$taskId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $missing = [];
    if (empty($row['alliance_id']))       $missing[] = 'alianza';
    if ((int) $row['tag_count'] === 0)    $missing[] = 'etiqueta';

    return ['valid' => empty($missing), 'missing' => $missing];
}

/**
 * Busca un time_entry del usuario que se solape con el rango [start, end].
 * Formula estandar de solapamiento: a.start < b.end AND b.start < a.end
 * Devuelve array con datos del entry conflictivo o null si no hay.
 */
function findOverlappingEntry(PDO $db, int $userId, string $startTime, string $endTime, ?int $excludeEntryId = null): ?array
{
    $sql = "SELECT te.id, te.task_id, te.start_time, te.end_time, t.title
            FROM time_entries te
            JOIN tasks t ON te.task_id = t.id
            WHERE te.user_id = ?
              AND te.end_time IS NOT NULL
              AND te.start_time < ?
              AND te.end_time > ?";
    $params = [$userId, $endTime, $startTime];
    if ($excludeEntryId !== null) {
        $sql .= " AND te.id != ?";
        $params[] = $excludeEntryId;
    }
    $sql .= " LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// ═════════════════════════════════════════════════════════════════════════════
// TIEMPO MANUAL
// ═════════════════════════════════════════════════════════════════════════════

function logManual(PDO $db, int $userId): void
{
    $taskId  = (int) ($_POST['task_id'] ?? 0);
    $hours   = (float) ($_POST['hours'] ?? 0);
    $minutes = (int) ($_POST['minutes'] ?? 0);
    $date    = $_POST['date'] ?? date('Y-m-d');
    $notes   = trim($_POST['notes'] ?? '') ?: null;

    if (!$taskId || ($hours <= 0 && $minutes <= 0)) {
        echo json_encode(['success' => false, 'message' => 'Tarea y duración son obligatorios']);
        return;
    }

    $check = $db->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $check->execute([$taskId, $userId]);
    if (!$check->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Tarea no válida']);
        return;
    }

    $durationSeconds = (int) ($hours * 3600) + ($minutes * 60);

    // Buscar la primera franja libre del dia (arrancando en la hora del ultimo entry cerrado)
    // para evitar siempre arrancar a las 09:00 y chocar con entries previos.
    $lastStmt = $db->prepare("
        SELECT end_time FROM time_entries
        WHERE user_id = ? AND DATE(start_time) = ? AND end_time IS NOT NULL
        ORDER BY end_time DESC LIMIT 1
    ");
    $lastStmt->execute([$userId, $date]);
    $lastEnd = $lastStmt->fetchColumn();
    $baseStart = $lastEnd ?: ($date . ' 09:00:00');

    $startTime = $baseStart;
    $endTime   = date('Y-m-d H:i:s', strtotime($startTime) + $durationSeconds);

    // Validar solapamiento con otros entries
    $overlap = findOverlappingEntry($db, $userId, $startTime, $endTime);
    if ($overlap) {
        $oStart = date('h:i A', strtotime($overlap['start_time']));
        $oEnd   = date('h:i A', strtotime($overlap['end_time']));
        echo json_encode([
            'success' => false,
            'message' => "El horario se solapa con \"{$overlap['title']}\" ({$oStart}-{$oEnd}). Ajusta la fecha o duracion.",
        ]);
        return;
    }

    $db->prepare("INSERT INTO time_entries (task_id, user_id, start_time, end_time, duration_seconds, notes) VALUES (?, ?, ?, ?, ?, ?)")
       ->execute([$taskId, $userId, $startTime, $endTime, $durationSeconds, $notes]);

    echo json_encode(['success' => true, 'message' => 'Tiempo registrado']);
}

// ═════════════════════════════════════════════════════════════════════════════
// ENTRADAS DE TIEMPO
// ═════════════════════════════════════════════════════════════════════════════

function getTimeEntries(PDO $db, int $userId): void
{
    $taskId = $_POST['task_id'] ?? '';

    $stmt = $db->prepare("
        SELECT te.*, t.title AS task_title
        FROM time_entries te
        JOIN tasks t ON te.task_id = t.id
        WHERE te.user_id = ? AND te.task_id = ? AND te.end_time IS NOT NULL
        ORDER BY te.start_time DESC
        LIMIT 50
    ");
    $stmt->execute([$userId, (int) $taskId]);

    echo json_encode(['success' => true, 'entries' => $stmt->fetchAll()]);
}

function daySummary(PDO $db, int $userId): void
{
    $date = $_POST['date'] ?? date('Y-m-d');

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(duration_seconds), 0) AS total
        FROM time_entries
        WHERE user_id = ? AND DATE(start_time) = ? AND end_time IS NOT NULL
    ");
    $stmt->execute([$userId, $date]);

    echo json_encode(['success' => true, 'total_seconds' => (int) $stmt->fetchColumn()]);
}

function timeEntryUpdate(PDO $db, int $userId): void
{
    $entryId = (int) ($_POST['entry_id'] ?? 0);

    // Verificar propiedad
    $check = $db->prepare("SELECT id FROM time_entries WHERE id = ? AND user_id = ?");
    $check->execute([$entryId, $userId]);
    if (!$check->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Entrada no encontrada']);
        return;
    }

    $startTime = $_POST['start_time'] ?? '';
    $endTime   = $_POST['end_time'] ?? '';

    if (empty($startTime) || empty($endTime)) {
        echo json_encode(['success' => false, 'message' => 'Hora de inicio y fin son obligatorias']);
        return;
    }

    $duration = strtotime($endTime) - strtotime($startTime);
    if ($duration <= 0) {
        echo json_encode(['success' => false, 'message' => 'La hora final debe ser posterior a la hora de inicio']);
        return;
    }

    // Validar solapamiento con otros entries del usuario (excluyendo el actual)
    $overlap = findOverlappingEntry($db, $userId, $startTime, $endTime, $entryId);
    if ($overlap) {
        $oStart = date('h:i A', strtotime($overlap['start_time']));
        $oEnd   = date('h:i A', strtotime($overlap['end_time']));
        echo json_encode([
            'success' => false,
            'message' => "Se solapa con otro registro: \"{$overlap['title']}\" ({$oStart}-{$oEnd})",
        ]);
        return;
    }

    $db->prepare("UPDATE time_entries SET start_time = ?, end_time = ?, duration_seconds = ? WHERE id = ?")
       ->execute([$startTime, $endTime, $duration, $entryId]);

    echo json_encode(['success' => true, 'message' => 'Entrada actualizada']);
}

function timeEntryDelete(PDO $db, int $userId): void
{
    $entryId = (int) ($_POST['entry_id'] ?? 0);

    $check = $db->prepare("SELECT id FROM time_entries WHERE id = ? AND user_id = ?");
    $check->execute([$entryId, $userId]);
    if (!$check->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Entrada no encontrada']);
        return;
    }

    $db->prepare("DELETE FROM time_entries WHERE id = ?")->execute([$entryId]);
    echo json_encode(['success' => true, 'message' => 'Entrada eliminada']);
}

function daySummaryRange(PDO $db, int $userId): void
{
    $dateFrom = $_POST['date_from'] ?? date('Y-m-01');
    $dateTo   = $_POST['date_to'] ?? date('Y-m-d');

    $stmt = $db->prepare("
        SELECT COALESCE(SUM(duration_seconds), 0) AS total
        FROM time_entries
        WHERE user_id = ? AND DATE(start_time) BETWEEN ? AND ? AND end_time IS NOT NULL
    ");
    $stmt->execute([$userId, $dateFrom, $dateTo]);

    echo json_encode(['success' => true, 'total_seconds' => (int) $stmt->fetchColumn()]);
}

// ═════════════════════════════════════════════════════════════════════════════
// ETIQUETAS
// ═════════════════════════════════════════════════════════════════════════════

function tagsList(PDO $db): void
{
    $tags = $db->query("SELECT * FROM tags ORDER BY name")->fetchAll();
    echo json_encode(['success' => true, 'tags' => $tags]);
}

function tagsCreate(PDO $db): void
{
    $name = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '') ?: null;

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio']);
        return;
    }

    $exists = $db->prepare("SELECT id FROM tags WHERE name = ?");
    $exists->execute([$name]);
    if ($exists->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'La etiqueta ya existe']);
        return;
    }

    $db->prepare("INSERT INTO tags (name, color) VALUES (?, ?)")->execute([$name, $color]);
    echo json_encode(['success' => true, 'message' => 'Etiqueta creada', 'tag_id' => (int) $db->lastInsertId()]);
}

function tagsUpdate(PDO $db): void
{
    $id = (int) ($_POST['tag_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $color = trim($_POST['color'] ?? '') ?: null;

    if (!$id || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
        return;
    }

    $db->prepare("UPDATE tags SET name = ?, color = ? WHERE id = ?")->execute([$name, $color, $id]);
    echo json_encode(['success' => true, 'message' => 'Etiqueta actualizada']);
}

function tagsDelete(PDO $db): void
{
    $id = (int) ($_POST['tag_id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID no válido']);
        return;
    }

    $db->prepare("DELETE FROM tags WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true, 'message' => 'Etiqueta eliminada']);
}
