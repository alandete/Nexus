<?php
/**
 * Nexus 2.0 — Dashboard
 * Command center: timer, stats, tareas, accesos rapidos, actividad, progreso
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();
$alliances = getAlliances();
$users = getUsers();

// Greeting based on time
$hour = (int) date('H');
if ($hour < 12) {
    $greeting = __('dashboard.greeting_morning');
} elseif ($hour < 18) {
    $greeting = __('dashboard.greeting_afternoon');
} else {
    $greeting = __('dashboard.greeting_evening');
}

// Stats: counts from DB
$taskStats = ['pending' => 0, 'in_progress' => 0, 'overdue' => 0];
$upcomingTasks = [];
$recentActivity = [];
$topPairs  = [];
$w0        = ['tasks' => 0, 'total_secs' => 0];
$w1        = ['tasks' => 0, 'total_secs' => 0];

// Rangos semanales (lunes a lunes)
$dayOfWeek  = (int)(new DateTime())->format('N');
$weekStart0 = date('Y-m-d', strtotime('-' . ($dayOfWeek - 1) . ' days'));
$weekEnd0   = date('Y-m-d', strtotime($weekStart0 . ' +7 days'));
$weekStart1 = date('Y-m-d', strtotime($weekStart0 . ' -7 days'));

if (isDBAvailable()) {
    $db = getDB();
    $userId = $currentUser['id'] ?? 0;

    // Task counts by status
    $stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM tasks WHERE user_id = ? AND status NOT IN ('completed','cancelled') GROUP BY status");
    $stmt->execute([$userId]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['status'] === 'pending') $taskStats['pending'] = (int) $row['cnt'];
        if ($row['status'] === 'in_progress' || $row['status'] === 'paused') $taskStats['in_progress'] += (int) $row['cnt'];
    }

    // Overdue tasks
    $stmt = $db->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status NOT IN ('completed','cancelled') AND due_date IS NOT NULL AND due_date < CURDATE()");
    $stmt->execute([$userId]);
    $taskStats['overdue'] = (int) $stmt->fetchColumn();

    // Upcoming tasks (next 5 with due date)
    $stmt = $db->prepare("
        SELECT t.id, t.title, t.due_date, t.priority, t.status,
               a.name as alliance_name, a.color as alliance_color
        FROM tasks t
        LEFT JOIN alliances a ON t.alliance_id = a.id
        WHERE t.user_id = ? AND t.status NOT IN ('completed','cancelled')
          AND t.due_date IS NOT NULL
        ORDER BY t.due_date ASC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $upcomingTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tareas completadas este mes por alianza (para el gráfico)
    $tasksByAlliance = [];
    $stmt = $db->prepare("
        SELECT a.name, a.color, COUNT(t.id) AS cnt
        FROM tasks t
        JOIN alliances a ON t.alliance_id = a.id
        WHERE t.user_id = ?
          AND t.status = 'completed'
          AND YEAR(t.updated_at) = YEAR(CURDATE())
          AND MONTH(t.updated_at) = MONTH(CURDATE())
        GROUP BY a.id
        ORDER BY cnt DESC
    ");
    $stmt->execute([$userId]);
    $tasksByAlliance = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top 3 combinaciones de etiquetas del mes
    $stmt = $db->prepare("
        SELECT tg1.name AS name1, tg1.color AS color1,
               tg2.name AS name2, tg2.color AS color2,
               COUNT(*) AS cnt
        FROM task_tags tt1
        JOIN task_tags tt2 ON tt1.task_id = tt2.task_id AND tt1.tag_id < tt2.tag_id
        JOIN tags tg1 ON tt1.tag_id = tg1.id
        JOIN tags tg2 ON tt2.tag_id = tg2.id
        JOIN tasks t  ON tt1.task_id = t.id
        WHERE t.user_id = ?
          AND YEAR(t.created_at)  = YEAR(CURDATE())
          AND MONTH(t.created_at) = MONTH(CURDATE())
        GROUP BY tt1.tag_id, tt2.tag_id
        ORDER BY cnt DESC
        LIMIT 3
    ");
    $stmt->execute([$userId]);
    $topPairs    = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $topPairsMax = !empty($topPairs) ? (int)$topPairs[0]['cnt'] : 1;

    // Comparación semanal
    $stmtW = $db->prepare("
        SELECT COUNT(DISTINCT te.task_id)           AS tasks,
               COALESCE(SUM(te.duration_seconds), 0) AS total_secs
        FROM time_entries te
        JOIN tasks t ON te.task_id = t.id
        WHERE t.user_id = ?
          AND te.start_time >= ? AND te.start_time < ?
          AND te.duration_seconds IS NOT NULL
    ");
    $stmtW->execute([$userId, $weekStart0, $weekEnd0]);
    $w0 = $stmtW->fetch(PDO::FETCH_ASSOC);
    $stmtW->execute([$userId, $weekStart1, $weekStart0]);
    $w1 = $stmtW->fetch(PDO::FETCH_ASSOC);
}

// Active alliances count
$activeAlliances = count(array_filter($alliances, fn($a) => !empty($a['active'])));

// Today's date (IntlDateFormatter for PHP 8.1+)
$todayFormatted = '';
if (class_exists('IntlDateFormatter')) {
    $locale = $lang === 'es' ? 'es_ES' : 'en_US';
    $fmt = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE);
    $fmt->setPattern("EEEE d 'de' MMMM, yyyy");
    if ($lang === 'en') $fmt->setPattern("EEEE, MMMM d, yyyy");
    $todayFormatted = $fmt->format(new DateTime());
}
if (!$todayFormatted) {
    $todayFormatted = date(__('dashboard.date_format'));
}

$fmtMins = function(int $secs): string {
    if ($secs <= 0) return '—';
    $h = intdiv($secs, 3600);
    $m = intdiv($secs % 3600, 60);
    if ($h > 0 && $m > 0) return $h . 'h ' . $m . 'min';
    if ($h > 0) return $h . 'h';
    return $m . ' min';
};
$w0Tasks   = (int)($w0['tasks']      ?? 0);
$w1Tasks   = (int)($w1['tasks']      ?? 0);
$w0Avg     = $w0Tasks > 0 ? intdiv((int)($w0['total_secs'] ?? 0), $w0Tasks) : 0;
$w1Avg     = $w1Tasks > 0 ? intdiv((int)($w1['total_secs'] ?? 0), $w1Tasks) : 0;
$weekDelta = $w0Tasks - $w1Tasks;
?>

<div class="dashboard">

    <!-- Header -->
    <div class="dashboard-header">
        <div>
            <?php $firstName = explode(' ', trim($currentUser['name'] ?? ''))[0]; ?>
        <h1 class="dashboard-greeting"><?= $greeting ?>, <?= htmlspecialchars($firstName) ?></h1>
            <p class="dashboard-date"><?= ucfirst($todayFormatted) ?></p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="dashboard-stats">
        <a href="<?= url('tasks') ?>" class="stat-card">
            <div class="stat-card-icon stat-icon-brand">
                <i class="bi bi-clock" aria-hidden="true"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= $taskStats['pending'] ?></span>
                <span class="stat-card-label"><?= __('dashboard.stats_pending') ?></span>
            </div>
        </a>

        <a href="<?= url('tasks') ?>" class="stat-card">
            <div class="stat-card-icon stat-icon-info">
                <i class="bi bi-play-circle" aria-hidden="true"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= $taskStats['in_progress'] ?></span>
                <span class="stat-card-label"><?= __('dashboard.stats_in_progress') ?></span>
            </div>
        </a>

        <div class="stat-card">
            <div class="stat-card-icon stat-icon-success">
                <i class="bi bi-stopwatch" aria-hidden="true"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value" id="dashTodayTime">--:--</span>
                <span class="stat-card-label"><?= __('dashboard.stats_today_time') ?></span>
            </div>
        </div>

        <a href="<?= url('tasks') ?>" class="stat-card <?= $taskStats['overdue'] > 0 ? 'stat-card-alert' : '' ?>">
            <div class="stat-card-icon stat-icon-danger">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            </div>
            <div class="stat-card-body">
                <span class="stat-card-value"><?= $taskStats['overdue'] ?></span>
                <span class="stat-card-label"><?= __('dashboard.stats_overdue') ?></span>
            </div>
        </a>
    </div>

    <!-- Fila de insights: top etiquetas + comparación semanal -->
    <div class="dashboard-insights-row">

        <!-- Top 3 etiquetas del mes -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= __('dashboard.insights_top_tags') ?></h3>
            </div>
            <div class="card-body">
                <?php if (empty($topPairs)): ?>
                <p class="text-subtle text-sm"><?= __('dashboard.insights_no_tags') ?></p>
                <?php else: ?>
                <ol class="insight-tags-list">
                    <?php foreach ($topPairs as $i => $pair):
                        $fill = round(($pair['cnt'] / $topPairsMax) * 100) . '%';
                        $chipColor = $pair['color1'] ?: $pair['color2'];
                        $chipStyle = '--tag-fill: ' . $fill . ';' . ($chipColor ? ' --tag-color: ' . htmlspecialchars($chipColor) . ';' : '');
                    ?>
                    <li class="insight-tag-item">
                        <span class="insight-tag-rank text-subtle text-sm">#<?= $i + 1 ?></span>
                        <span class="insight-tag-chip<?= $chipColor ? ' has-tag-color' : '' ?>" style="<?= $chipStyle ?>">
                            <?= htmlspecialchars($pair['name1']) ?>
                            <span class="insight-pair-sep">+</span>
                            <?= htmlspecialchars($pair['name2']) ?>
                        </span>
                        <span class="insight-tag-count text-subtle text-sm"><?= $pair['cnt'] ?> <?= __('dashboard.insights_tasks') ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <?php endif; ?>
            </div>
        </div>

        <!-- Comparación semanal -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= __('dashboard.insights_weekly') ?></h3>
            </div>
            <div class="card-body">
                <div class="insight-weekly">
                    <div class="insight-week-col">
                        <span class="insight-week-label"><?= __('dashboard.insights_this_week') ?></span>
                        <span class="insight-week-value"><?= $w0Tasks ?> <?= __('dashboard.insights_tasks') ?></span>
                        <span class="insight-week-avg"><?= $fmtMins($w0Avg) ?> <?= __('dashboard.insights_avg_time') ?></span>
                    </div>
                    <div class="insight-week-delta <?= $weekDelta > 0 ? 'delta-up' : ($weekDelta < 0 ? 'delta-down' : 'delta-equal') ?>">
                        <?php if ($weekDelta > 0): ?>
                        <i class="bi bi-arrow-up" aria-hidden="true"></i><?= abs($weekDelta) ?> <?= __('dashboard.insights_delta_more') ?>
                        <?php elseif ($weekDelta < 0): ?>
                        <i class="bi bi-arrow-down" aria-hidden="true"></i><?= abs($weekDelta) ?> <?= __('dashboard.insights_delta_less') ?>
                        <?php else: ?>
                        <i class="bi bi-dash" aria-hidden="true"></i><?= __('dashboard.insights_delta_equal') ?>
                        <?php endif; ?>
                    </div>
                    <div class="insight-week-col insight-week-col-right">
                        <span class="insight-week-label"><?= __('dashboard.insights_prev_week') ?></span>
                        <span class="insight-week-value"><?= $w1Tasks ?> <?= __('dashboard.insights_tasks') ?></span>
                        <span class="insight-week-avg"><?= $fmtMins($w1Avg) ?> <?= __('dashboard.insights_avg_time') ?></span>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.dashboard-insights-row -->

    <!-- Fila tareas: gráfico (40%) + próximas (60%) -->
    <div class="dashboard-tasks-row">
        <!-- Gráfico por prioridad -->
        <div class="dashboard-chart-col">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="card-title"><?= __('dashboard.chart_title') ?></h3>
                </div>
                <div class="card-body dashboard-chart-body">
                    <canvas id="dashChart" aria-label="<?= __('dashboard.chart_label') ?>"></canvas>
                </div>
            </div>
        </div>

        <!-- Upcoming Tasks -->
        <div class="card">
            <div class="card-header d-flex items-center justify-between">
                <h3 class="card-title"><?= __('dashboard.upcoming_tasks') ?></h3>
                <a href="<?= url('tasks') ?>" class="btn btn-link btn-sm"><?= __('dashboard.view_all_tasks') ?></a>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingTasks)): ?>
                <div class="empty-state p-200">
                    <div class="empty-state-icon"><i class="bi bi-calendar-check" aria-hidden="true"></i></div>
                    <p class="empty-state-description"><?= __('dashboard.no_upcoming') ?></p>
                </div>
                <?php else: ?>
                <div class="task-list">
                    <?php foreach ($upcomingTasks as $task):
                        $dueDate = $task['due_date'];
                        $today = date('Y-m-d');
                        $tomorrow = date('Y-m-d', strtotime('+1 day'));
                        $daysUntil = (int) ((strtotime($dueDate) - strtotime($today)) / 86400);

                        if ($dueDate < $today) {
                            $dueLabel = __('dashboard.overdue');
                            $dueClass = 'lozenge-danger';
                        } elseif ($dueDate === $today) {
                            $dueLabel = __('dashboard.due_today');
                            $dueClass = 'lozenge-warning';
                        } elseif ($dueDate === $tomorrow) {
                            $dueLabel = __('dashboard.due_tomorrow');
                            $dueClass = 'lozenge-info';
                        } else {
                            $dueLabel = str_replace('{days}', $daysUntil, __('dashboard.due_days'));
                            $dueClass = 'lozenge-default';
                        }

                    ?>
                    <div class="task-row">
                        <span class="task-row-title"><?= htmlspecialchars($task['title']) ?></span>
                        <?php if ($task['alliance_name']): ?>
                        <span class="task-row-alliance<?= $task['alliance_color'] ? ' has-alliance-color' : '' ?>"<?= $task['alliance_color'] ? ' style="--alliance-color: ' . htmlspecialchars($task['alliance_color']) . ';"' : '' ?>>
                            <i class="bi bi-building" aria-hidden="true"></i>
                            <?= htmlspecialchars($task['alliance_name']) ?>
                        </span>
                        <?php endif; ?>
                        <span class="lozenge <?= $dueClass ?> flex-shrink-0"><?= $dueLabel ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /.dashboard-tasks-row -->

</div>

<script>
window.__DASHBOARD__ = {
    chartData: <?= json_encode($tasksByAlliance) ?>,
};
</script>
