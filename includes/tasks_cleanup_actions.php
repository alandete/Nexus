<?php
/**
 * Nexus 2.0 — Limpieza de tareas en bloque
 * POST action=preview  → {success, task_count, entry_count}
 * POST action=execute  → {success, deleted, message}
 * POST action=nuke     → {success, deleted, message}
 */
define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$db = getDB();
if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Base de datos no disponible']);
    exit;
}

$currentUser = getCurrentUser();
if (!canEditModule($currentUser, 'settings')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin permiso']);
    exit;
}

$stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute([$currentUser['username']]);
$userId = (int) $stmt->fetchColumn();
if (!$userId) {
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'preview':       cleanupPreview($db, $userId);      break;
    case 'execute':       cleanupExecute($db, $userId);      break;
    case 'nuke':          cleanupNuke($db, $userId);         break;
    case 'detect_dupes':  cleanupDetectDupes($db, $userId);  break;
    case 'fix_dupes':     cleanupFixDupes($db, $userId);     break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción inválida']);
}

// ── Helpers ─────────────────────────────────────────────────────────────────

function parseCleanupParams(): array {
    return [
        'alliance_id' => (int) ($_POST['alliance_id'] ?? 0),
        'statuses'    => array_filter(explode(',', $_POST['statuses'] ?? '')),
        'before_date' => trim($_POST['before_date'] ?? ''),
    ];
}

function buildTaskIds(PDO $db, int $userId, int $allianceId, array $statuses, string $beforeDate): array {
    $statusClauses = [];
    if (in_array('in_progress', $statuses)) $statusClauses[] = "t.status = 'in_progress'";
    if (in_array('paused',      $statuses)) $statusClauses[] = "t.status = 'paused'";
    if (in_array('completed',   $statuses)) $statusClauses[] = "t.status = 'completed'";
    if (in_array('cancelled',   $statuses)) $statusClauses[] = "t.status = 'cancelled'";
    if (in_array('no_activity', $statuses)) $statusClauses[] = "(t.status = 'pending' AND last_te.task_id IS NULL)";

    if (!$statusClauses) return [];

    $sql = "
        SELECT t.id
        FROM tasks t
        LEFT JOIN (
            SELECT task_id, MAX(start_time) AS last_entry
            FROM time_entries
            GROUP BY task_id
        ) last_te ON last_te.task_id = t.id
        WHERE t.user_id = :userId
    ";
    $params = ['userId' => $userId];

    if ($allianceId > 0) {
        $sql .= " AND t.alliance_id = :allianceId";
        $params['allianceId'] = $allianceId;
    }

    $sql .= " AND (" . implode(' OR ', $statusClauses) . ")";

    if ($beforeDate) {
        $sql .= " AND COALESCE(last_te.last_entry, t.created_at) < :beforeDate";
        $params['beforeDate'] = $beforeDate;
    }

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ── Acciones ─────────────────────────────────────────────────────────────────

function cleanupPreview(PDO $db, int $userId): void {
    ['alliance_id' => $allianceId, 'statuses' => $statuses, 'before_date' => $beforeDate] = parseCleanupParams();

    if (!$statuses) {
        echo json_encode(['success' => true, 'task_count' => 0, 'entry_count' => 0]);
        return;
    }

    $taskIds = buildTaskIds($db, $userId, $allianceId, $statuses, $beforeDate);
    if (!$taskIds) {
        echo json_encode(['success' => true, 'task_count' => 0, 'entry_count' => 0]);
        return;
    }

    $in   = implode(',', array_fill(0, count($taskIds), '?'));
    $stmt = $db->prepare("SELECT COUNT(*) FROM time_entries WHERE task_id IN ($in)");
    $stmt->execute($taskIds);

    echo json_encode([
        'success'     => true,
        'task_count'  => count($taskIds),
        'entry_count' => (int) $stmt->fetchColumn(),
    ]);
}

function cleanupExecute(PDO $db, int $userId): void {
    ['alliance_id' => $allianceId, 'statuses' => $statuses, 'before_date' => $beforeDate] = parseCleanupParams();

    if (!$statuses) {
        echo json_encode(['success' => false, 'message' => 'Ningún estado seleccionado.']);
        return;
    }

    $taskIds = buildTaskIds($db, $userId, $allianceId, $statuses, $beforeDate);
    if (!$taskIds) {
        echo json_encode(['success' => true, 'deleted' => 0, 'message' => 'No hay tareas que coincidan con los filtros.']);
        return;
    }

    $in = implode(',', array_fill(0, count($taskIds), '?'));
    $db->prepare("DELETE FROM tasks WHERE id IN ($in)")->execute($taskIds);

    logActivity('tasks', 'cleanup', count($taskIds) . ' tareas eliminadas en bloque');

    echo json_encode([
        'success' => true,
        'deleted' => count($taskIds),
        'message' => count($taskIds) . ' tareas eliminadas.',
    ]);
}

function cleanupDetectDupes(PDO $db, int $userId): void {
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(cnt - 1), 0) AS surplus
        FROM (
            SELECT COUNT(*) AS cnt
            FROM time_entries
            WHERE user_id = :userId
            GROUP BY task_id, start_time
            HAVING cnt > 1
        ) dupes
    ");
    $stmt->execute(['userId' => $userId]);
    $surplus = (int) $stmt->fetchColumn();

    echo json_encode(['success' => true, 'surplus' => $surplus]);
}

function cleanupFixDupes(PDO $db, int $userId): void {
    // Cuenta antes de borrar
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(cnt - 1), 0) AS surplus
        FROM (
            SELECT COUNT(*) AS cnt
            FROM time_entries
            WHERE user_id = :userId
            GROUP BY task_id, start_time
            HAVING cnt > 1
        ) dupes
    ");
    $stmt->execute(['userId' => $userId]);
    $surplus = (int) $stmt->fetchColumn();

    if ($surplus === 0) {
        echo json_encode(['success' => true, 'deleted' => 0]);
        return;
    }

    // Elimina los duplicados conservando el entry con el id más bajo
    $db->prepare("
        DELETE te FROM time_entries te
        INNER JOIN (
            SELECT MIN(id) AS keep_id, task_id, start_time
            FROM time_entries
            WHERE user_id = :userId
            GROUP BY task_id, start_time
            HAVING COUNT(*) > 1
        ) keep ON te.task_id = keep.task_id AND te.start_time = keep.start_time
        WHERE te.user_id = :userId AND te.id <> keep.keep_id
    ")->execute(['userId' => $userId]);

    logActivity('tasks', 'fix_dupes', $surplus . ' entradas duplicadas eliminadas');

    echo json_encode(['success' => true, 'deleted' => $surplus]);
}

function cleanupNuke(PDO $db, int $userId): void {
    $stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ?");
    $stmt->execute([$userId]);
    $count = (int) $stmt->fetchColumn();

    $db->prepare("DELETE FROM tasks WHERE user_id = ?")->execute([$userId]);

    logActivity('tasks', 'nuke', $count . ' tareas eliminadas (borrado total)');

    echo json_encode([
        'success' => true,
        'deleted' => $count,
        'message' => $count . ' tareas eliminadas.',
    ]);
}
