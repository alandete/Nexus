<?php
/**
 * Nexus 2.0 — Ajustes > Copias de seguridad
 * Listado, crear, restaurar, descargar, favorito, eliminar
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();

// Configuración de backup automático
$scheduleFile      = DATA_PATH . '/backup_schedule.json';
$schedule          = [];
if (file_exists($scheduleFile)) {
    $decoded = json_decode(file_get_contents($scheduleFile), true);
    if (is_array($decoded)) $schedule = $decoded;
}
$schedEnabled   = !empty($schedule['enabled']);
$schedType      = $schedule['type']      ?? 'data';
$schedFrequency = $schedule['frequency'] ?? 'daily';
$schedHour      = (int) ($schedule['hour'] ?? 23);
$schedToken     = $schedule['token']     ?? '';
$schedLastRun   = $schedule['last_run']  ?? null;

$cronDayPart = ['daily' => '* * *', 'weekly' => '* * 0', 'monthly' => '1 * *'];
$cronTime    = '0 ' . $schedHour . ' ' . ($cronDayPart[$schedFrequency] ?? '* * *');
$cronUrl     = rtrim(APP_BASE_URL, '/') . '/cron/backup_cron.php?token=' . urlencode($schedToken);
$cronCmd     = $schedToken ? ($cronTime . ' curl -s "' . $cronUrl . '" > /dev/null 2>&1') : '';

// Helpers locales para leer metadata sin cargar todo backup_actions.php
$__getFavorites = function (): array {
    $f = BACKUP_PATH . '/favorites.json';
    if (!file_exists($f)) return [];
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : [];
};
$__getNotes = function (): array {
    $f = BACKUP_PATH . '/notes.json';
    if (!file_exists($f)) return [];
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : [];
};

if (!canAccessModule($currentUser, 'backup')) {
    http_response_code(403);
    include 'pages/error.php';
    return;
}

$canCreate  = hasPermission($currentUser, 'backup', null);
$canRestore = hasPermission($currentUser, 'restore', null);

// Listar backups
$files = [];
if (is_dir(BACKUP_PATH)) {
    $all = scandir(BACKUP_PATH);
    foreach ($all as $f) {
        if ($f === '.' || $f === '..' || !preg_match('/\.zip$/i', $f)) continue;
        $files[] = $f;
    }
}

$favorites = $__getFavorites();
$notes     = $__getNotes();
$__getSources = function (): array {
    $f = BACKUP_PATH . '/sources.json';
    if (!file_exists($f)) return [];
    $d = json_decode(file_get_contents($f), true);
    return is_array($d) ? $d : [];
};
$sources = $__getSources();

// Construir array con metadata
$backups = [];
foreach ($files as $f) {
    $path = BACKUP_PATH . '/' . $f;
    if (!file_exists($path)) continue;

    $type = 'data';
    if (strpos($f, 'nexusapp-full-') === 0) $type = 'full';

    // Fecha desde nombre (YYYYMMDD-HHmmss) o mtime
    $date = filemtime($path);
    if (preg_match('/(\d{8})-(\d{6})/', $f, $m)) {
        $parsed = DateTime::createFromFormat('Ymd His', $m[1] . ' ' . $m[2]);
        if ($parsed) $date = $parsed->getTimestamp();
    }

    $backups[] = [
        'filename'   => $f,
        'type'       => $type,
        'size'       => filesize($path),
        'size_human' => formatFileSize(filesize($path)),
        'date'       => date('Y-m-d H:i:s', $date),
        'date_ts'    => $date,
        'favorite'   => in_array($f, $favorites, true),
        'note'       => $notes[$f] ?? '',
        'source'     => $sources[$f] ?? 'manual',
    ];
}

// Ordenar por fecha descendente
usort($backups, fn($a, $b) => $b['date_ts'] - $a['date_ts']);

// Stats
$totalBackups    = count($backups);
$totalData       = count(array_filter($backups, fn($b) => $b['type'] === 'data'));
$totalFull       = count(array_filter($backups, fn($b) => $b['type'] === 'full'));
$totalFavorites  = count(array_filter($backups, fn($b) => $b['favorite']));
$totalSize       = array_sum(array_column($backups, 'size'));

$lastBackup = $backups[0] ?? null;

// Limites (constante de config)
$maxPerType = defined('MAX_BACKUPS') ? MAX_BACKUPS : 3;
$nonFavData = count(array_filter($backups, fn($b) => $b['type'] === 'data' && !$b['favorite']));
$nonFavFull = count(array_filter($backups, fn($b) => $b['type'] === 'full' && !$b['favorite']));
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= url('settings') ?>" class="breadcrumb-link"><?= __('menu.settings') ?></a>
    <i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>
    <span class="breadcrumb-current" aria-current="page"><?= __('menu.backups') ?></span>
</nav>

<div class="page-header d-flex items-center justify-between flex-wrap gap-200">
    <div>
        <h1 class="page-title"><?= __('snapshots.page_title') ?></h1>
        <p class="page-description"><?= __('snapshots.page_subtitle') ?></p>
    </div>
    <?php if ($canCreate): ?>
    <button type="button" class="btn btn-primary" id="btnCreateSnapshot">
        <i class="bi bi-plus-lg" aria-hidden="true"></i>
        <?= __('snapshots.btn_create') ?>
    </button>
    <?php endif; ?>
</div>

<!-- Stats -->
<div class="users-stats">
    <div class="users-stat">
        <span class="users-stat-value"><?= $totalBackups ?></span>
        <span class="users-stat-label"><?= __('snapshots.stat_total') ?></span>
    </div>
    <div class="users-stat">
        <span class="users-stat-value"><?= formatFileSize($totalSize) ?></span>
        <span class="users-stat-label"><?= __('snapshots.stat_size') ?></span>
    </div>
    <div class="users-stat">
        <span class="users-stat-value">
            <?= $lastBackup ? htmlspecialchars(relativeTime($lastBackup['date'])) : '—' ?>
        </span>
        <span class="users-stat-label"><?= __('snapshots.stat_last') ?></span>
    </div>
    <div class="users-stat">
        <span class="users-stat-value"><?= $totalFavorites ?></span>
        <span class="users-stat-label"><?= __('snapshots.stat_favorites') ?></span>
    </div>
</div>

<!-- Indicador de rotacion -->
<div class="snapshots-rotation">
    <div class="snapshots-rotation-items">
        <div class="snapshots-rotation-item">
            <i class="bi bi-database" aria-hidden="true"></i>
            <span><?= __('snapshots.rotation_data') ?>:</span>
            <strong><?= $nonFavData ?> / <?= $maxPerType ?></strong>
            <span class="text-subtle"><?= __('snapshots.rotation_slots') ?></span>
        </div>
        <div class="snapshots-rotation-item">
            <i class="bi bi-hdd" aria-hidden="true"></i>
            <span><?= __('snapshots.rotation_full') ?>:</span>
            <strong><?= $nonFavFull ?> / <?= $maxPerType ?></strong>
            <span class="text-subtle"><?= __('snapshots.rotation_slots') ?></span>
        </div>
    </div>
    <div class="snapshots-rotation-hint">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        <?= __('snapshots.rotation_hint') ?>
    </div>
</div>

<!-- Backup automático -->
<?php if ($canCreate): ?>
<section class="card" id="scheduleCard" style="margin-bottom:var(--ds-space-300);">
    <div class="card-header d-flex items-center justify-between">
        <div class="d-flex items-center gap-150">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <div>
                <h2 class="card-title"><?= __('snapshots.schedule_title') ?></h2>
                <p class="card-subtitle"><?= __('snapshots.schedule_desc') ?></p>
            </div>
        </div>
        <label class="toggle" data-tooltip="<?= $schedEnabled ? __('snapshots.schedule_enabled') : __('snapshots.schedule_disabled') ?>" data-tooltip-position="left">
            <input type="checkbox" id="schedEnabled" <?= $schedEnabled ? 'checked' : '' ?>>
            <span class="toggle-slider"></span>
        </label>
    </div>

    <div class="card-body">
        <div class="d-flex gap-300 flex-wrap items-center">
            <div class="d-flex items-center gap-100">
                <label class="form-label" style="margin:0;white-space:nowrap;"><?= __('snapshots.field_sched_type') ?></label>
                <div class="chip-group">
                    <button type="button" class="chip <?= $schedType === 'data' ? 'chip-active' : '' ?>" data-sched-type="data">
                        <i class="bi bi-database" aria-hidden="true"></i>
                        <?= __('snapshots.type_data') ?>
                    </button>
                    <button type="button" class="chip <?= $schedType === 'full' ? 'chip-active' : '' ?>" data-sched-type="full">
                        <i class="bi bi-hdd" aria-hidden="true"></i>
                        <?= __('snapshots.type_full') ?>
                    </button>
                </div>
            </div>
            <div class="d-flex items-center gap-100">
                <label class="form-label" for="schedFrequency" style="margin:0;white-space:nowrap;"><?= __('snapshots.field_sched_freq') ?></label>
                <select class="form-control" id="schedFrequency" style="width:150px;">
                    <option value="daily"   <?= $schedFrequency === 'daily'   ? 'selected' : '' ?>><?= __('snapshots.freq_daily') ?></option>
                    <option value="weekly"  <?= $schedFrequency === 'weekly'  ? 'selected' : '' ?>><?= __('snapshots.freq_weekly') ?></option>
                    <option value="monthly" <?= $schedFrequency === 'monthly' ? 'selected' : '' ?>><?= __('snapshots.freq_monthly') ?></option>
                </select>
            </div>
            <div class="d-flex items-center gap-100">
                <label class="form-label" for="schedHour" style="margin:0;white-space:nowrap;"><?= __('snapshots.field_sched_hour') ?></label>
                <select class="form-control" id="schedHour" style="width:100px;">
                    <?php for ($h = 0; $h <= 23; $h++): ?>
                    <option value="<?= $h ?>" <?= $schedHour === $h ? 'selected' : '' ?>>
                        <?= str_pad($h, 2, '0', STR_PAD_LEFT) ?>:00
                    </option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <?php if ($schedToken): ?>
        <div class="form-group mt-150">
            <label class="form-label"><?= __('snapshots.cron_cmd_label') ?></label>
            <div class="d-flex gap-100 items-start">
                <input type="text" class="form-control font-mono text-sm" id="cronCmdInput"
                       value="<?= htmlspecialchars($cronCmd) ?>" readonly style="flex:1;">
                <button type="button" class="btn btn-default btn-sm" id="btnCopyCron" data-tooltip="<?= __('snapshots.btn_copy_cron') ?>">
                    <i class="bi bi-clipboard" aria-hidden="true"></i>
                </button>
            </div>
            <p class="form-helper"><?= __('snapshots.cron_cmd_help') ?></p>
        </div>
        <?php endif; ?>

        <div class="d-flex items-center justify-between flex-wrap gap-150 mt-200">
            <div class="text-subtle text-sm">
                <?php if ($schedLastRun): ?>
                    <?= __('snapshots.schedule_last_run') ?>:
                    <strong><?= htmlspecialchars(relativeTime($schedLastRun)) ?></strong>
                    <span class="text-subtle">(<?= htmlspecialchars($schedLastRun) ?>)</span>
                <?php else: ?>
                    <?= __('snapshots.schedule_last_run') ?>: <strong><?= __('snapshots.schedule_never') ?></strong>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-100">
                <?php if ($schedToken): ?>
                <button type="button" class="btn btn-subtle btn-sm" id="btnRegenToken">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                    <?= __('snapshots.btn_regen_token') ?>
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-primary btn-sm" id="btnSaveSchedule">
                    <?= __('snapshots.btn_save_schedule') ?>
                </button>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Filtros -->
<div class="filter-bar" role="search">
    <div class="filter-bar-search">
        <i class="bi bi-search filter-bar-search-icon" aria-hidden="true"></i>
        <input type="search" class="form-control" id="snapshotSearch"
               placeholder="<?= __('snapshots.search_placeholder') ?>"
               aria-label="<?= __('snapshots.search_placeholder') ?>">
    </div>
    <div class="chip-group" role="tablist" aria-label="<?= __('snapshots.filter_type') ?>">
        <button type="button" class="chip chip-active" data-filter="all" role="tab" aria-selected="true">
            <?= __('snapshots.filter_all') ?>
        </button>
        <button type="button" class="chip" data-filter="data" role="tab">
            <i class="bi bi-database" aria-hidden="true"></i>
            <?= __('snapshots.filter_data') ?>
        </button>
        <button type="button" class="chip" data-filter="full" role="tab">
            <i class="bi bi-hdd" aria-hidden="true"></i>
            <?= __('snapshots.filter_full') ?>
        </button>
        <button type="button" class="chip" data-filter="favorites" role="tab">
            <i class="bi bi-star-fill" aria-hidden="true"></i>
            <?= __('snapshots.filter_favorites') ?>
        </button>
    </div>
</div>

<!-- Listado -->
<div class="snapshots-list" id="snapshotsList">
    <?php if (empty($backups)): ?>
    <div class="empty-state card">
        <div class="empty-state-icon"><i class="bi bi-archive" aria-hidden="true"></i></div>
        <h2 class="empty-state-title"><?= __('snapshots.empty_title') ?></h2>
        <p class="empty-state-description"><?= __('snapshots.empty_desc') ?></p>
        <?php if ($canCreate): ?>
        <button type="button" class="btn btn-primary" id="btnCreateSnapshotEmpty">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <?= __('snapshots.btn_create') ?>
        </button>
        <?php endif; ?>
    </div>
    <?php else: foreach ($backups as $b):
        $icon = $b['type'] === 'full' ? 'bi-hdd-fill' : 'bi-database-fill';
        $typeLabel = $b['type'] === 'full' ? __('snapshots.type_full') : __('snapshots.type_data');
        $typeLozenge = $b['type'] === 'full' ? 'lozenge-discovery' : 'lozenge-info';
        $isAuto = ($b['source'] ?? 'manual') === 'auto';
    ?>
    <article class="snapshot-card <?= $b['favorite'] ? 'is-favorite' : '' ?>"
             data-filename="<?= htmlspecialchars($b['filename']) ?>"
             data-type="<?= $b['type'] ?>"
             data-favorite="<?= $b['favorite'] ? '1' : '0' ?>"
             data-search="<?= htmlspecialchars(strtolower($b['filename'] . ' ' . $b['note'])) ?>">
        <div class="snapshot-icon snapshot-icon-<?= $b['type'] ?>" aria-hidden="true">
            <i class="bi <?= $icon ?>"></i>
        </div>

        <div class="snapshot-body">
            <div class="snapshot-header">
                <span class="lozenge <?= $typeLozenge ?>"><?= $typeLabel ?></span>
                <?php if ($isAuto): ?>
                <span class="lozenge lozenge-success" data-tooltip="<?= __('snapshots.source_auto_tooltip') ?>">
                    <i class="bi bi-clock-history" aria-hidden="true"></i>
                    <?= __('snapshots.source_auto') ?>
                </span>
                <?php else: ?>
                <span class="lozenge lozenge-default" data-tooltip="<?= __('snapshots.source_manual_tooltip') ?>">
                    <i class="bi bi-hand-index" aria-hidden="true"></i>
                    <?= __('snapshots.source_manual') ?>
                </span>
                <?php endif; ?>
                <?php if ($b['favorite']): ?>
                <span class="lozenge lozenge-warning" data-protected="1" data-tooltip="<?= __('snapshots.protected_tooltip') ?>">
                    <i class="bi bi-shield-fill" aria-hidden="true"></i>
                    <?= __('snapshots.protected') ?>
                </span>
                <?php endif; ?>
                <?php if ($b['type'] === 'full'): ?>
                <span class="text-subtle text-sm" data-tooltip="<?= __('snapshots.full_hint') ?>">
                    <i class="bi bi-info-circle" aria-hidden="true"></i>
                </span>
                <?php endif; ?>
            </div>

            <h3 class="snapshot-filename"><?= htmlspecialchars($b['filename']) ?></h3>
        </div>

        <?php if (!empty($b['note'])): ?>
        <p class="snapshot-note"><?= htmlspecialchars($b['note']) ?></p>
        <?php endif; ?>

        <div class="snapshot-meta">
            <span class="snapshot-meta-item" title="<?= htmlspecialchars($b['date']) ?>">
                <i class="bi bi-clock" aria-hidden="true"></i>
                <?= htmlspecialchars(relativeTime($b['date'])) ?>
            </span>
            <span class="snapshot-meta-item">
                <i class="bi bi-file-earmark-zip" aria-hidden="true"></i>
                <?= $b['size_human'] ?>
            </span>
        </div>

        <div class="snapshot-actions">
            <button type="button" class="btn-icon btn-favorite <?= $b['favorite'] ? 'is-active' : '' ?>"
                    data-filename="<?= htmlspecialchars($b['filename']) ?>"
                    data-tooltip="<?= $b['favorite'] ? __('snapshots.btn_unfavorite') : __('snapshots.btn_favorite') ?>" data-tooltip-position="top"
                    aria-label="<?= __('snapshots.btn_favorite') ?>"
                    aria-pressed="<?= $b['favorite'] ? 'true' : 'false' ?>">
                <i class="bi <?= $b['favorite'] ? 'bi-star-fill' : 'bi-star' ?>" aria-hidden="true"></i>
            </button>

            <a href="<?= url('') ?>includes/backup_actions.php?action=download&filename=<?= urlencode($b['filename']) ?>"
               class="btn-icon" download
               data-tooltip="<?= __('snapshots.btn_download') ?>" data-tooltip-position="top"
               aria-label="<?= __('snapshots.btn_download') ?>">
                <i class="bi bi-download" aria-hidden="true"></i>
            </a>

            <?php if ($canRestore && $b['type'] === 'data'): ?>
            <button type="button" class="btn-icon btn-restore"
                    data-filename="<?= htmlspecialchars($b['filename']) ?>"
                    data-tooltip="<?= __('snapshots.btn_restore') ?>" data-tooltip-position="top"
                    aria-label="<?= __('snapshots.btn_restore') ?>">
                <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
            </button>
            <?php endif; ?>

            <?php if ($canCreate): ?>
            <button type="button" class="btn-icon btn-icon-danger btn-delete"
                    data-filename="<?= htmlspecialchars($b['filename']) ?>"
                    <?= $b['favorite'] ? 'disabled' : '' ?>
                    data-tooltip="<?= $b['favorite'] ? __('snapshots.delete_locked') : __('snapshots.btn_delete') ?>" data-tooltip-position="top"
                    aria-label="<?= __('snapshots.btn_delete') ?>">
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>
            <?php endif; ?>
        </div>
    </article>
    <?php endforeach; endif; ?>
</div>

<!-- Empty state filtrado -->
<div class="empty-state d-none" id="snapshotsEmptyFiltered">
    <div class="empty-state-icon"><i class="bi bi-search" aria-hidden="true"></i></div>
    <h2 class="empty-state-title"><?= __('snapshots.empty_filtered_title') ?></h2>
    <p class="empty-state-description"><?= __('snapshots.empty_filtered_desc') ?></p>
</div>

<script>
window.__SNAPSHOTS_CAN_CREATE__  = <?= $canCreate ? 'true' : 'false' ?>;
window.__SNAPSHOTS_CAN_RESTORE__ = <?= $canRestore ? 'true' : 'false' ?>;
window.__SCHEDULE__ = {
    token:     <?= json_encode($schedToken) ?>,
    frequency: <?= json_encode($schedFrequency) ?>,
    hour:      <?= $schedHour ?>,
    baseUrl:   <?= json_encode(rtrim(APP_BASE_URL, '/') . '/cron/backup_cron.php') ?>,
};
</script>
