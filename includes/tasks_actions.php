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
    $dateFrom = $_POST['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
    $dateTo   = $_POST['date_to'] ?? date('Y-m-d');
    $allianceFilter = $_POST['alliance_id'] ?? '';

    // Entradas de tiempo con info completa
    $where = "te.user_id = ? AND DATE(te.start_time) BETWEEN ? AND ? AND te.end_time IS NOT NULL";
    $params = [$userId, $dateFrom, $dateTo];

    if ($allianceFilter !== '') {
        $where .= " AND t.alliance_id = ?";
        $params[] = (int) $allianceFilter;
    }

    $entriesStmt = $db->prepare("
        SELECT te.*, t.title AS task_title, t.description AS task_description, t.status AS task_status, t.alliance_id,
               a.name AS alliance_name, a.color AS alliance_color,
               GROUP_CONCAT(DISTINCT tg.name ORDER BY tg.name SEPARATOR ', ') AS tag_names
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
    $scheduledWhere = "t.user_id = ? AND t.status IN ('pending', 'in_progress')";
    $scheduledParams = [$userId];
    if ($allianceFilter !== '') {
        $scheduledWhere .= " AND t.alliance_id = ?";
        $scheduledParams[] = (int) $allianceFilter;
    }

    // Auto-urgente: actualizar prioridad de tareas vencidas
    $db->prepare("UPDATE tasks SET priority = 'urgent' WHERE user_id = ? AND due_date < CURDATE() AND status IN ('pending', 'in_progress') AND priority != 'urgent'")
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
    $title = trim($_POST['title'] ?? '');
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'El título es obligatorio']);
        return;
    }

    $stmt = $db->prepare("INSERT INTO tasks (user_id, alliance_id, title, description, due_date, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId,
        !empty($_POST['alliance_id']) ? (int) $_POST['alliance_id'] : null,
        $title,
        trim($_POST['description'] ?? '') ?: null,
        !empty($_POST['due_date']) ? $_POST['due_date'] : null,
        in_array($_POST['priority'] ?? '', ['low', 'medium', 'high', 'urgent']) ? $_POST['priority'] : 'medium',
        $_POST['status'] ?? 'pending',
    ]);

    $taskId = (int) $db->lastInsertId();

    // Asignar etiquetas
    if (!empty($_POST['tag_ids'])) {
        assignTags($db, $taskId, $_POST['tag_ids']);
    }

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
        echo json_encode(['success' => true, 'results' => []]);
        return;
    }

    $stmt = $db->prepare("
        SELECT t.id, t.title, a.name AS alliance_name
        FROM tasks t
        LEFT JOIN alliances a ON t.alliance_id = a.id
        WHERE t.user_id = ? AND t.title LIKE ? AND t.status != 'cancelled'
        ORDER BY t.updated_at DESC
        LIMIT 10
    ");
    $stmt->execute([$userId, "%{$q}%"]);

    echo json_encode(['success' => true, 'results' => $stmt->fetchAll()]);
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
            $ins = $db->prepare("INSERT INTO tasks (user_id, alliance_id, title, status) VALUES (?, ?, ?, 'in_progress')");
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

    // Verificar propiedad
    $check = $db->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
    $check->execute([$taskId, $userId]);
    if (!$check->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Tarea no válida']);
        return;
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
        SELECT t.title, t.alliance_id, a.name AS alliance_name,
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
    // TODO [prioritario, antes de reportes 4.5]: reset del status de la tarea tras descartar.
    // Si la tarea no tiene mas entries despues del DELETE, debe volver a 'pending' para
    // evitar tareas fantasma en el listado de Activas (status=in_progress sin sesiones).
    // Actualmente el frontend filtra las fantasma pero esto falsea totales/reportes.
    $db->prepare("DELETE FROM time_entries WHERE user_id = ? AND end_time IS NULL")->execute([$userId]);
    echo json_encode(['success' => true, 'message' => 'Entrada descartada']);
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
    $startTime = $date . ' 09:00:00';
    $endTime = date('Y-m-d H:i:s', strtotime($startTime) + $durationSeconds);

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
