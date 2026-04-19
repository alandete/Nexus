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

    // Recent activity (last 5)
    $stmt = $db->prepare("SELECT timestamp, user, module, action, detail FROM activity_log ORDER BY timestamp DESC LIMIT 5");
    $stmt->execute();
    $recentActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Stages progress (excluyendo 'deferred' del calculo de progreso)
$stagesRaw = json_decode(file_get_contents(DATA_PATH . '/stages.json'), true) ?: [];
$countedStages = array_filter($stagesRaw, fn($s) => ($s['status'] ?? '') !== 'deferred');
$totalStages = count($countedStages);
$completedStages = count(array_filter($countedStages, fn($s) => ($s['status'] ?? '') === 'completed'));
$inProgressStages = array_filter($stagesRaw, fn($s) => ($s['status'] ?? '') === 'in-progress');
$progressPct = $totalStages > 0 ? round(($completedStages / $totalStages) * 100) : 0;

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
?>

<div class="dashboard">

    <!-- Header -->
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-greeting"><?= $greeting ?>, <?= htmlspecialchars($currentUser['name'] ?? '') ?></h1>
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

    <!-- Quick Access (inline) -->
    <div class="quick-access-bar">
        <a href="<?= url('tasks') ?>" class="quick-access-item">
            <i class="bi bi-plus-circle quick-access-icon-inline" aria-hidden="true"></i>
            <span class="quick-access-label"><?= __('dashboard.quick_new_task') ?></span>
        </a>
        <a href="<?= url('utilities') ?>#preguntas" class="quick-access-item">
            <i class="bi bi-file-earmark-text quick-access-icon-inline" aria-hidden="true"></i>
            <span class="quick-access-label"><?= __('dashboard.quick_questions') ?></span>
        </a>
        <a href="<?= url('utilities') ?>#pdf" class="quick-access-item">
            <i class="bi bi-file-earmark-pdf quick-access-icon-inline" aria-hidden="true"></i>
            <span class="quick-access-label"><?= __('dashboard.quick_pdf') ?></span>
        </a>
        <a href="<?= url('alliances') ?>" class="quick-access-item">
            <i class="bi bi-building quick-access-icon-inline" aria-hidden="true"></i>
            <span class="quick-access-label"><?= __('dashboard.quick_alliances') ?></span>
        </a>
    </div>

    <!-- Two-column grid: Upcoming Tasks + Activity -->
    <div class="dashboard-grid-2">
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

                        $priorityColors = [
                            'urgent' => 'var(--ds-red-500)',
                            'high'   => 'var(--ds-orange-500)',
                            'medium' => 'var(--ds-blue-500)',
                            'low'    => 'var(--ds-neutral-400)',
                        ];
                        $dotColor = $priorityColors[$task['priority']] ?? 'var(--ds-neutral-400)';
                    ?>
                    <div class="task-row">
                        <span class="task-priority-dot" style="background: <?= $dotColor ?>;"></span>
                        <div class="task-row-body">
                            <span class="task-row-title"><?= htmlspecialchars($task['title']) ?></span>
                            <?php if ($task['alliance_name']): ?>
                            <span class="task-row-alliance" <?= $task['alliance_color'] ? 'style="color: ' . htmlspecialchars($task['alliance_color']) . ';"' : '' ?>>
                                <?= htmlspecialchars($task['alliance_name']) ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <span class="lozenge <?= $dueClass ?>"><?= $dueLabel ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header d-flex items-center justify-between">
                <h3 class="card-title"><?= __('dashboard.recent_activity') ?></h3>
                <a href="<?= url('settings') ?>#actividad" class="btn btn-link btn-sm"><?= __('dashboard.view_all_activity') ?></a>
            </div>
            <div class="card-body">
                <?php if (empty($recentActivity)): ?>
                <div class="empty-state p-200">
                    <div class="empty-state-icon"><i class="bi bi-clock-history" aria-hidden="true"></i></div>
                    <p class="empty-state-description"><?= __('dashboard.no_activity') ?></p>
                </div>
                <?php else: ?>
                <div class="activity-list">
                    <?php foreach ($recentActivity as $entry):
                        $moduleIcons = [
                            'auth'      => 'bi-box-arrow-in-right',
                            'tasks'     => 'bi-check2-square',
                            'users'     => 'bi-person',
                            'alliances' => 'bi-building',
                            'backup'    => 'bi-cloud-arrow-down',
                            'settings'  => 'bi-gear',
                        ];
                        $icon = $moduleIcons[$entry['module']] ?? 'bi-circle';
                        $timeAgo = '';
                        $ts = strtotime($entry['timestamp']);
                        $diff = time() - $ts;
                        if ($diff < 60) $timeAgo = '< 1 min';
                        elseif ($diff < 3600) $timeAgo = floor($diff / 60) . ' min';
                        elseif ($diff < 86400) $timeAgo = floor($diff / 3600) . ' h';
                        else $timeAgo = floor($diff / 86400) . ' d';
                    ?>
                    <div class="activity-row">
                        <div class="activity-icon">
                            <i class="bi <?= $icon ?>" aria-hidden="true"></i>
                        </div>
                        <div class="activity-body">
                            <span class="activity-user"><?= htmlspecialchars($entry['user']) ?></span>
                            <span class="activity-action"><?= htmlspecialchars($entry['action']) ?></span>
                            <?php if ($entry['detail']): ?>
                            <span class="activity-detail"><?= htmlspecialchars($entry['detail']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="activity-time"><?= $timeAgo ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Project Progress -->
    <div class="card">
        <div class="card-header d-flex items-center justify-between flex-wrap gap-100">
            <h3 class="card-title"><?= __('dashboard.project_progress') ?></h3>
            <span class="text-sm text-subtle">
                <?= str_replace(['{count}', '{total}'], [$completedStages, $totalStages], __('dashboard.stages_completed')) ?>
                (<?= $progressPct ?>%)
            </span>
        </div>
        <div class="card-body">
            <div class="progress progress-brand mb-200" role="progressbar" aria-valuenow="<?= $progressPct ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: <?= $progressPct ?>%;"></div>
            </div>

            <!-- Fases en progreso (siempre visibles) -->
            <?php if (!empty($inProgressStages)): ?>
            <div class="progress-stages">
                <?php foreach ($inProgressStages as $stage): ?>
                <div class="progress-stage-item">
                    <span class="lozenge lozenge-info"><?= __('dashboard.status_in_progress') ?></span>
                    <span class="text-sm"><?= htmlspecialchars($stage['title'][$lang] ?? $stage['title']['es']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Colapsable con todas las fases -->
            <details class="phases-collapse">
                <summary class="phases-collapse-summary">
                    <i class="bi bi-list-check phases-collapse-icon" aria-hidden="true"></i>
                    <span><?= __('dashboard.see_all_phases') ?></span>
                    <i class="bi bi-chevron-down phases-collapse-chevron" aria-hidden="true"></i>
                </summary>
                <ul class="phases-list">
                    <?php foreach ($stagesRaw as $stage):
                        $st = $stage['status'] ?? 'pending';
                        $lozengeClass = match($st) {
                            'completed'   => 'lozenge-success',
                            'in-progress' => 'lozenge-info',
                            'deferred'    => 'lozenge-default',
                            default       => 'lozenge-default',
                        };
                        $statusLabel = match($st) {
                            'completed'   => __('dashboard.status_completed'),
                            'in-progress' => __('dashboard.status_in_progress'),
                            'deferred'    => __('dashboard.status_deferred'),
                            default       => __('dashboard.status_pending'),
                        };
                        $iconClass = match($st) {
                            'completed'   => 'bi-check-circle-fill',
                            'in-progress' => 'bi-play-circle-fill',
                            'deferred'    => 'bi-pause-circle',
                            default       => 'bi-circle',
                        };
                    ?>
                    <li class="phase-item phase-item-<?= $st ?>">
                        <i class="bi <?= $iconClass ?> phase-item-icon" aria-hidden="true"></i>
                        <div class="phase-item-body">
                            <div class="phase-item-header">
                                <?php if (!empty($stage['phase'])): ?>
                                <span class="phase-item-number">Fase <?= (int) $stage['phase'] ?></span>
                                <?php endif; ?>
                                <span class="phase-item-title"><?= htmlspecialchars($stage['title'][$lang] ?? $stage['title']['es']) ?></span>
                                <span class="lozenge <?= $lozengeClass ?>"><?= $statusLabel ?></span>
                            </div>
                            <p class="phase-item-desc"><?= htmlspecialchars($stage['description'][$lang] ?? $stage['description']['es']) ?></p>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </details>
        </div>
    </div>

</div>
