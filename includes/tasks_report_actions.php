<?php
/**
 * Nexus 2.0 — Reporte mensual de actividades de tareas
 * Endpoint JSON con estadisticas por mes y alianza (opcional: tareas y etiquetas).
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if (!isLoggedIn()) {
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
$meStmt = $db->prepare("SELECT id, username, name, role FROM users WHERE username = ?");
$meStmt->execute([$currentUser['username']]);
$me = $meStmt->fetch(PDO::FETCH_ASSOC);
if (!$me) { echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']); exit; }

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'monthly':        reportMonthly($db, $me);       break;
    case 'users_list':     reportUsersList($db, $me);     break;
    case 'alliances_list': reportAlliancesList($db, $me); break;
    case 'tags_list':      reportTagsList($db, $me);      break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Lista de alianzas activas.
 */
function reportAlliancesList(PDO $db, array $me): void
{
    $stmt = $db->query("SELECT id, name, color FROM alliances WHERE active = 1 ORDER BY name");
    echo json_encode([
        'success'   => true,
        'alliances' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
}

/**
 * Lista de etiquetas disponibles (con tiempo registrado en el sistema).
 */
function reportTagsList(PDO $db, array $me): void
{
    $stmt = $db->query("SELECT id, name, color FROM tags ORDER BY name");
    echo json_encode([
        'success' => true,
        'tags'    => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
}

/**
 * Lista de usuarios activos (solo admin). Usuario normal recibe solo a si mismo.
 */
function reportUsersList(PDO $db, array $me): void
{
    if (strtolower($me['role']) !== 'admin') {
        echo json_encode([
            'success' => true,
            'is_admin' => false,
            'users' => [['id' => (int) $me['id'], 'name' => $me['name'], 'username' => $me['username']]],
        ]);
        return;
    }
    $stmt = $db->query("SELECT id, username, name FROM users WHERE active = 1 ORDER BY name");
    echo json_encode([
        'success'  => true,
        'is_admin' => true,
        'users'    => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
}

/**
 * Reporte de actividades por rango de fechas.
 * POST params:
 *   - start (YYYY-MM-DD)  default: primer dia del mes actual
 *   - end   (YYYY-MM-DD)  default: ultimo dia del mes actual
 *   - user_id (int)       solo si admin, default: usuario logueado
 *   - include_tasks       "1"/"0"  (detallado)
 *   - include_tags        "1"/"0"  (detallado)
 * Nota: mantiene el action name `monthly` por compatibilidad; internamente
 * acepta cualquier rango.
 */
function reportMonthly(PDO $db, array $me): void
{
    $startDate = $_POST['start'] ?? '';
    $endDate   = $_POST['end']   ?? '';

    // Default: mes corriente
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
        $first = new DateTime('first day of this month');
        $last  = new DateTime('last day of this month');
        $startDate = $first->format('Y-m-d');
        $endDate   = $last->format('Y-m-d');
    }
    if ($startDate > $endDate) { [$startDate, $endDate] = [$endDate, $startDate]; }

    // Usuario objetivo
    $targetUserId = (int) $me['id'];
    $requested    = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
    if ($requested && $requested !== $targetUserId) {
        if (strtolower($me['role']) !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Solo un administrador puede consultar reportes de otros usuarios']);
            return;
        }
        $targetUserId = $requested;
    }

    $uStmt = $db->prepare("SELECT id, username, name, email, role FROM users WHERE id = ?");
    $uStmt->execute([$targetUserId]);
    $targetUser = $uStmt->fetch(PDO::FETCH_ASSOC);
    if (!$targetUser) { echo json_encode(['success' => false, 'message' => 'Usuario destino no encontrado']); return; }

    $includeTasks = !empty($_POST['include_tasks']);
    $includeTags  = !empty($_POST['include_tags']);

    // Filtros opcionales: múltiples alianzas y/o etiquetas (CSV de IDs)
    $allianceIds = array_values(array_filter(array_map('intval', explode(',', $_POST['alliance_ids'] ?? ''))));
    $tagIds      = array_values(array_filter(array_map('intval', explode(',', $_POST['tag_ids'] ?? ''))));

    $allianceFilter = '';
    if ($allianceIds) {
        $pls = implode(',', array_fill(0, count($allianceIds), '?'));
        $allianceFilter = " AND t.alliance_id IN ($pls)";
    }
    $tagFilter = '';
    if ($tagIds) {
        $pls = implode(',', array_fill(0, count($tagIds), '?'));
        $tagFilter = " AND t.id IN (SELECT task_id FROM task_tags WHERE tag_id IN ($pls))";
    }

    // Helper: arma params base + filtros
    $baseParams  = fn() => [$targetUserId, $startDate, $endDate];
    $extraParams = fn() => array_merge($allianceIds, $tagIds);

    // Total global del periodo
    $totalStmt = $db->prepare("
        SELECT COALESCE(SUM(te.duration_seconds), 0)
        FROM time_entries te
        JOIN tasks t ON te.task_id = t.id
        WHERE te.user_id = ? AND te.end_time IS NOT NULL
          AND DATE(te.start_time) BETWEEN ? AND ?
          $allianceFilter $tagFilter
    ");
    $totalStmt->execute(array_merge($baseParams(), $extraParams()));
    $totalSeconds = (int) $totalStmt->fetchColumn();

    // Resumen por alianza
    $byAllianceStmt = $db->prepare("
        SELECT
            a.id, a.name, a.color,
            COALESCE(SUM(te.duration_seconds), 0) AS total_seconds,
            COUNT(DISTINCT t.id) AS task_count
        FROM time_entries te
        JOIN tasks t ON te.task_id = t.id
        LEFT JOIN alliances a ON t.alliance_id = a.id
        WHERE te.user_id = ? AND te.end_time IS NOT NULL
          AND DATE(te.start_time) BETWEEN ? AND ?
          $allianceFilter $tagFilter
        GROUP BY a.id, a.name, a.color
        ORDER BY total_seconds DESC
    ");
    $byAllianceStmt->execute(array_merge($baseParams(), $extraParams()));
    $byAlliance = array_map(function($r) {
        return [
            'id'            => $r['id'] ? (int) $r['id'] : null,
            'name'          => $r['name'] ?: '(Sin alianza)',
            'color'         => $r['color'],
            'total_seconds' => (int) $r['total_seconds'],
            'task_count'    => (int) $r['task_count'],
        ];
    }, $byAllianceStmt->fetchAll(PDO::FETCH_ASSOC));

    // Info de filtros activos para mostrar en el reporte
    $allianceFilterInfo = [];
    if ($allianceIds) {
        $pls = implode(',', array_fill(0, count($allianceIds), '?'));
        $s = $db->prepare("SELECT id, name, color FROM alliances WHERE id IN ($pls) ORDER BY name");
        $s->execute($allianceIds);
        $allianceFilterInfo = $s->fetchAll(PDO::FETCH_ASSOC);
    }
    $tagFilterInfo = [];
    if ($tagIds) {
        $pls = implode(',', array_fill(0, count($tagIds), '?'));
        $s = $db->prepare("SELECT id, name, color FROM tags WHERE id IN ($pls) ORDER BY name");
        $s->execute($tagIds);
        $tagFilterInfo = $s->fetchAll(PDO::FETCH_ASSOC);
    }

    $response = [
        'success' => true,
        'period'  => [
            'start' => $startDate,
            'end'   => $endDate,
            'label' => formatRangeLabel($startDate, $endDate),
        ],
        'alliance_filter' => $allianceFilterInfo ?: null,
        'tag_filter'      => $tagFilterInfo      ?: null,
        'user' => [
            'id'       => (int) $targetUser['id'],
            'name'     => $targetUser['name'],
            'username' => $targetUser['username'],
            'email'    => $targetUser['email'],
            'role'     => $targetUser['role'],
        ],
        'generated_at' => date('Y-m-d H:i:s'),
        'generated_by' => ['id' => (int) $me['id'], 'name' => $me['name']],
        'total_seconds' => $totalSeconds,
        'by_alliance'   => $byAlliance,
        'task_count'    => array_sum(array_column($byAlliance, 'task_count')),
    ];

    // Tiempo por día (siempre incluido para el gráfico de barras)
    $dayStmt = $db->prepare("
        SELECT DATE(te.start_time) AS day, COALESCE(SUM(te.duration_seconds), 0) AS seconds
        FROM time_entries te
        JOIN tasks t ON te.task_id = t.id
        WHERE te.user_id = ? AND te.end_time IS NOT NULL
          AND DATE(te.start_time) BETWEEN ? AND ?
          $allianceFilter $tagFilter
        GROUP BY DATE(te.start_time)
        ORDER BY day ASC
    ");
    $dayStmt->execute(array_merge($baseParams(), $extraParams()));
    $response['by_day'] = array_map(
        fn($r) => ['date' => $r['day'], 'seconds' => (int) $r['seconds']],
        $dayStmt->fetchAll(PDO::FETCH_ASSOC)
    );

    // Opcional: listado de tareas por alianza
    if ($includeTasks) {
        // En esta query las fechas van primero (JOIN condition), user_id después
        $tasksStmt = $db->prepare("
            SELECT
                t.id, t.title, t.alliance_id, a.name AS alliance_name,
                t.status, t.priority, t.due_date,
                COALESCE(SUM(te.duration_seconds), 0) AS total_seconds,
                COUNT(te.id) AS sessions_count
            FROM tasks t
            JOIN time_entries te ON te.task_id = t.id AND te.end_time IS NOT NULL
                AND DATE(te.start_time) BETWEEN ? AND ?
            LEFT JOIN alliances a ON t.alliance_id = a.id
            WHERE te.user_id = ?
            $allianceFilter $tagFilter
            GROUP BY t.id, t.title, t.alliance_id, a.name, t.status, t.priority, t.due_date
            ORDER BY a.name ASC, total_seconds DESC
        ");
        $tasksStmt->execute(array_merge([$startDate, $endDate, $targetUserId], $extraParams()));
        $response['tasks_by_alliance'] = array_map(function($r) {
            return [
                'id'             => (int) $r['id'],
                'title'          => $r['title'],
                'alliance_id'    => $r['alliance_id'] ? (int) $r['alliance_id'] : null,
                'alliance_name'  => $r['alliance_name'] ?: '(Sin alianza)',
                'status'         => $r['status'],
                'priority'       => $r['priority'],
                'due_date'       => $r['due_date'],
                'total_seconds'  => (int) $r['total_seconds'],
                'sessions_count' => (int) $r['sessions_count'],
            ];
        }, $tasksStmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // Opcional: total de tareas/tiempo por etiqueta
    if ($includeTags) {
        // Igual que tasks: fechas primero, user_id después
        $tagsStmt = $db->prepare("
            SELECT
                tg.id, tg.name, tg.color,
                COUNT(DISTINCT t.id) AS task_count,
                COALESCE(SUM(te.duration_seconds), 0) AS total_seconds
            FROM tags tg
            JOIN task_tags tt ON tt.tag_id = tg.id
            JOIN tasks t ON tt.task_id = t.id
            JOIN time_entries te ON te.task_id = t.id AND te.end_time IS NOT NULL
                AND DATE(te.start_time) BETWEEN ? AND ?
            WHERE te.user_id = ?
            $allianceFilter $tagFilter
            GROUP BY tg.id, tg.name, tg.color
            ORDER BY task_count DESC, total_seconds DESC
        ");
        $tagsStmt->execute(array_merge([$startDate, $endDate, $targetUserId], $extraParams()));
        $response['by_tag'] = array_map(function($r) {
            return [
                'id'            => (int) $r['id'],
                'name'          => $r['name'],
                'color'         => $r['color'],
                'task_count'    => (int) $r['task_count'],
                'total_seconds' => (int) $r['total_seconds'],
            ];
        }, $tagsStmt->fetchAll(PDO::FETCH_ASSOC));
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

function formatMonthLabel(int $year, int $month): string
{
    $months = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];
    return ucfirst($months[$month] ?? '') . ' ' . $year;
}

/**
 * Devuelve una etiqueta legible del rango. Si start y end cubren exactamente un
 * mes completo, muestra "Marzo 2026". Si no, muestra "01/03/2026 — 07/03/2026".
 */
function formatRangeLabel(string $startDate, string $endDate): string
{
    try {
        $start = new DateTime($startDate);
        $end   = new DateTime($endDate);
    } catch (Exception $e) { return "$startDate — $endDate"; }

    // Mismo mes completo?
    $firstOfMonth = $start->format('d') === '01';
    $lastOfMonth  = $end->format('Y-m-d') === (new DateTime('last day of ' . $start->format('Y-m'), $start->getTimezone()))->format('Y-m-d');
    $sameMonth    = $start->format('Y-m') === $end->format('Y-m');
    if ($firstOfMonth && $lastOfMonth && $sameMonth) {
        return formatMonthLabel((int) $start->format('Y'), (int) $start->format('n'));
    }
    return $start->format('d/m/Y') . ' — ' . $end->format('d/m/Y');
}
