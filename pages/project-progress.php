<?php
/**
 * Nexus 2.0 — Ajustes > Progreso del proyecto
 * Solo visible para administradores.
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();

if (($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    include 'pages/403.php';
    return;
}

$lang      = $_SESSION['lang'] ?? 'es';
$stagesRaw = json_decode(file_get_contents(DATA_PATH . '/stages.json'), true) ?: [];

$countedStages   = array_filter($stagesRaw, fn($s) => ($s['status'] ?? '') !== 'deferred');
$totalStages     = count($countedStages);
$completedStages = count(array_filter($countedStages, fn($s) => ($s['status'] ?? '') === 'completed'));
$inProgressStages= array_filter($stagesRaw, fn($s) => ($s['status'] ?? '') === 'in-progress');
$progressPct     = $totalStages > 0 ? round(($completedStages / $totalStages) * 100) : 0;
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= url('settings') ?>" class="breadcrumb-link"><?= __('menu.settings') ?></a>
    <i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>
    <span class="breadcrumb-current" aria-current="page"><?= __('menu.project_progress') ?></span>
</nav>

<div class="page-header">
    <h1 class="page-title"><?= __('menu.project_progress') ?></h1>
    <p class="page-description"><?= __('dashboard.stages_completed', '{count} de {total} fases completadas') ?></p>
</div>

<div class="card">
    <div class="card-header d-flex items-center justify-between flex-wrap gap-100">
        <h2 class="card-title"><?= __('dashboard.project_progress') ?></h2>
        <span class="text-sm text-subtle">
            <?= str_replace(['{count}', '{total}'], [$completedStages, $totalStages], __('dashboard.stages_completed')) ?>
            (<?= $progressPct ?>%)
        </span>
    </div>
    <div class="card-body">
        <div class="progress progress-brand mb-200" role="progressbar"
             aria-valuenow="<?= $progressPct ?>" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width: <?= $progressPct ?>%;"></div>
        </div>

        <?php if (!empty($inProgressStages)): ?>
        <div class="progress-stages mb-200">
            <?php foreach ($inProgressStages as $stage): ?>
            <div class="progress-stage-item">
                <span class="lozenge lozenge-info"><?= __('dashboard.status_in_progress') ?></span>
                <span class="text-sm"><?= htmlspecialchars($stage['title'][$lang] ?? $stage['title']['es']) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

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
    </div>
</div>
