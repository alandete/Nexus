<?php
/**
 * Nexus 2.0 — Import / Export de time entries
 * GET  ?action=export&format=nexus|clockify&start=YYYY-MM-DD&end=YYYY-MM-DD  → CSV download
 * POST action=import  entries=JSON                                            → JSON result
 * GET  ?action=alliances_list                                                 → JSON
 */
define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if (!isLoggedIn()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($isPost && !validateCsrf()) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$db = getDB();
if (!$db) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Base de datos no disponible']);
    exit;
}

$currentUser = getCurrentUser();
$userStmt = $db->prepare("SELECT id, name, email FROM users WHERE username = ?");
$userStmt->execute([$currentUser['username']]);
$me = $userStmt->fetch(PDO::FETCH_ASSOC);
if (!$me) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
    exit;
}
$userId = (int) $me['id'];

$action = $isPost ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');

switch ($action) {
    case 'export':         handleExport($db, $userId, $me);  break;
    case 'import':         handleImport($db, $userId);        break;
    case 'alliances_list': alliancesList($db);                break;
    default:
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

// ─────────────────────────────────────────────────────────────────
// EXPORT
// ─────────────────────────────────────────────────────────────────

function handleExport(PDO $db, int $userId, array $me): void
{
    $format = in_array($_GET['format'] ?? '', ['nexus', 'clockify']) ? $_GET['format'] : 'nexus';
    $start  = $_GET['start'] ?? date('Y-m-01');
    $end    = $_GET['end']   ?? date('Y-m-d');

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = date('Y-m-01');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end   = date('Y-m-d');

    $stmt = $db->prepare("
        SELECT
            a.name          AS alliance_name,
            t.title         AS task_title,
            GROUP_CONCAT(tg.name ORDER BY tg.name SEPARATOR ', ') AS tags,
            te.start_time,
            te.end_time,
            te.duration_seconds
        FROM time_entries te
        JOIN tasks t ON te.task_id = t.id
        LEFT JOIN alliances a ON t.alliance_id = a.id
        LEFT JOIN task_tags tt ON tt.task_id = t.id
        LEFT JOIN tags tg ON tg.id = tt.tag_id
        WHERE te.user_id = ?
          AND te.end_time IS NOT NULL
          AND DATE(te.start_time) >= ?
          AND DATE(te.start_time) <= ?
        GROUP BY te.id
        ORDER BY te.start_time ASC
    ");
    $stmt->execute([$userId, $start, $end]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $slug     = $format === 'clockify' ? 'clockify' : 'nexus';
    $filename = "{$slug}_entradas_{$start}_{$end}.csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM para Excel

    $out = fopen('php://output', 'w');

    if ($format === 'clockify') {
        fputcsv($out, [
            'Proyecto', 'Cliente', 'Descripción', 'Tarea', 'Usuario', 'Grupo',
            'Correo electrónico', 'Etiquetas',
            'Fecha de inicio', 'Hora de inicio',
            'Fecha de finalización', 'Hora de finalización',
            'Duración (h)', 'Duración (decimal)', 'Fecha de creación',
        ]);
        foreach ($rows as $row) {
            $s   = new DateTime($row['start_time']);
            $e   = new DateTime($row['end_time']);
            $dur = (int) $row['duration_seconds'];
            fputcsv($out, [
                $row['alliance_name'] ?? '',
                'Scala Learning',
                $row['task_title'],
                '',
                $me['name'] ?? '',
                '',
                $me['email'] ?? '',
                $row['tags'] ?? '',
                $s->format('d/m/Y'),
                $s->format('g:i A'),
                $e->format('d/m/Y'),
                $e->format('g:i A'),
                sprintf('%d:%02d', intdiv($dur, 3600), intdiv($dur % 3600, 60)),
                round($dur / 3600, 2),
                $s->format('d/m/Y'),
            ]);
        }
    } else {
        // Nexus native
        fputcsv($out, ['Alianza', 'Tarea', 'Etiquetas', 'Fecha inicio', 'Hora inicio', 'Fecha fin', 'Hora fin', 'Duracion (hh:mm)']);
        foreach ($rows as $row) {
            $s   = new DateTime($row['start_time']);
            $e   = new DateTime($row['end_time']);
            $dur = (int) $row['duration_seconds'];
            fputcsv($out, [
                $row['alliance_name'] ?? '',
                $row['task_title'],
                $row['tags'] ?? '',
                $s->format('Y-m-d'),
                $s->format('H:i'),
                $e->format('Y-m-d'),
                $e->format('H:i'),
                sprintf('%d:%02d', intdiv($dur, 3600), intdiv($dur % 3600, 60)),
            ]);
        }
    }

    fclose($out);
    exit;
}

// ─────────────────────────────────────────────────────────────────
// IMPORT
// ─────────────────────────────────────────────────────────────────

function handleImport(PDO $db, int $userId): void
{
    header('Content-Type: application/json; charset=utf-8');

    $raw = $_POST['entries'] ?? '';
    if (!$raw) {
        echo json_encode(['success' => false, 'message' => 'No se recibieron datos']);
        return;
    }

    $entries = json_decode($raw, true);
    if (!is_array($entries) || empty($entries)) {
        echo json_encode(['success' => false, 'message' => 'Formato de datos inválido']);
        return;
    }

    $inserted  = 0;
    $skippedDup  = 0;
    $skippedOver = 0;

    // Cache para evitar re-queries: "allianceId:titulominusculas" → taskId
    $taskCache = [];
    // Cache de tags nuevas creadas en esta importación: "nombre_lower" → id
    $newTagCache = [];

    $db->beginTransaction();
    try {
        foreach ($entries as $entry) {
            $allianceId = isset($entry['alliance_id']) && $entry['alliance_id'] !== null
                          ? (int) $entry['alliance_id'] : null;
            $taskTitle  = trim($entry['task_title'] ?? '');
            $tagIds     = array_map('intval', (array) ($entry['tag_ids'] ?? []));
            $newTags    = (array) ($entry['new_tags'] ?? []);
            $startTime  = $entry['start_time'] ?? null;
            $endTime    = $entry['end_time']   ?? null;
            $duration   = isset($entry['duration_seconds']) ? (int) $entry['duration_seconds'] : 0;

            // Entradas descartadas (alianza mapeada a null) o incompletas
            if (!$allianceId || !$taskTitle || !$startTime || !$endTime) continue;

            // Crear tags nuevas (deduplicando dentro del lote)
            foreach ($newTags as $tagName) {
                $tagName = trim($tagName);
                if ($tagName === '') continue;
                $lower = mb_strtolower($tagName);
                if (isset($newTagCache[$lower])) {
                    $tagIds[] = $newTagCache[$lower];
                    continue;
                }
                $chk = $db->prepare("SELECT id FROM tags WHERE LOWER(name) = ? LIMIT 1");
                $chk->execute([$lower]);
                $existId = $chk->fetchColumn();
                if ($existId) {
                    $newTagCache[$lower] = (int) $existId;
                } else {
                    $db->prepare("INSERT INTO tags (name, color) VALUES (?, '#585d8a')")
                       ->execute([$tagName]);
                    $newTagCache[$lower] = (int) $db->lastInsertId();
                }
                $tagIds[] = $newTagCache[$lower];
            }
            $tagIds = array_unique(array_filter($tagIds));

            // Buscar o crear tarea por (user + alliance + título + fecha)
            // Una entrada por día = instancia separada, igual que el comportamiento del timer
            $entryDate = substr($startTime, 0, 10);
            $cacheKey  = "{$allianceId}:" . mb_strtolower($taskTitle) . ":{$entryDate}";
            if (!isset($taskCache[$cacheKey])) {
                $ts = $db->prepare("SELECT id FROM tasks WHERE user_id = ? AND alliance_id = ? AND LOWER(title) = ? AND due_date = ? LIMIT 1");
                $ts->execute([$userId, $allianceId, mb_strtolower($taskTitle), $entryDate]);
                $existTaskId = $ts->fetchColumn();
                if ($existTaskId) {
                    $taskCache[$cacheKey] = (int) $existTaskId;
                } else {
                    $db->prepare("INSERT INTO tasks (user_id, alliance_id, title, status, due_date) VALUES (?, ?, ?, 'completed', ?)")
                       ->execute([$userId, $allianceId, $taskTitle, $entryDate]);
                    $taskCache[$cacheKey] = (int) $db->lastInsertId();
                }
            }
            $taskId = $taskCache[$cacheKey];

            // Vincular tags a la tarea (INSERT IGNORE evita duplicados)
            foreach ($tagIds as $tid) {
                $db->prepare("INSERT IGNORE INTO task_tags (task_id, tag_id) VALUES (?, ?)")
                   ->execute([$taskId, $tid]);
            }

            // Saltar duplicados exactos (mismo task + mismo start_time)
            $dupStmt = $db->prepare("SELECT id FROM time_entries WHERE task_id = ? AND start_time = ? AND user_id = ? LIMIT 1");
            $dupStmt->execute([$taskId, $startTime, $userId]);
            if ($dupStmt->fetchColumn()) { $skippedDup++; continue; }

            // Saltar solapamientos con otros entries del usuario
            $ovStmt = $db->prepare("
                SELECT id FROM time_entries
                WHERE user_id = ? AND end_time IS NOT NULL
                  AND start_time < ? AND end_time > ?
                LIMIT 1
            ");
            $ovStmt->execute([$userId, $endTime, $startTime]);
            if ($ovStmt->fetchColumn()) { $skippedOver++; continue; }

            $db->prepare("INSERT INTO time_entries (task_id, user_id, start_time, end_time, duration_seconds) VALUES (?, ?, ?, ?, ?)")
               ->execute([$taskId, $userId, $startTime, $endTime, $duration]);
            $inserted++;
        }

        $db->commit();
    } catch (Exception $ex) {
        $db->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $ex->getMessage()]);
        return;
    }

    $msg = "{$inserted} entradas importadas.";
    if ($skippedDup)  $msg .= " {$skippedDup} duplicadas omitidas.";
    if ($skippedOver) $msg .= " {$skippedOver} con solapamiento omitidas.";

    echo json_encode([
        'success'            => true,
        'inserted'           => $inserted,
        'skipped_duplicates' => $skippedDup,
        'skipped_overlaps'   => $skippedOver,
        'message'            => $msg,
    ]);
}

// ─────────────────────────────────────────────────────────────────
// ALLIANCES LIST
// ─────────────────────────────────────────────────────────────────

function alliancesList(PDO $db): void
{
    header('Content-Type: application/json; charset=utf-8');
    $stmt = $db->query("SELECT id, name, color FROM alliances ORDER BY name ASC");
    echo json_encode(['success' => true, 'alliances' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}
