<?php
/**
 * Nexus 2.0 — Ajustes > Registro de actividad
 * Tabla filtrable con paginacion. Solo accesible para admins.
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();

if (($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    include 'pages/403.php';
    return;
}

$users = getUsers();

// Modulos y acciones conocidos (usados como filtros)
$knownModules = [
    'auth'             => __('activity.mod_auth'),
    'users'            => __('activity.mod_users'),
    'manage_alliances' => __('activity.mod_manage_alliances'),
    'alliances'        => __('activity.mod_alliances'),
    'backup'           => __('activity.mod_backup'),
    'settings'         => __('activity.mod_settings'),
    'application'      => __('activity.mod_application'),
    'projectinfo'      => __('activity.mod_application'),
    'utilities'        => __('activity.mod_utilities'),
    'cleanup'          => __('activity.mod_cleanup'),
    'reports'          => __('activity.mod_reports'),
];

$knownActions = [
    'login'       => __('activity.act_login'),
    'logout'      => __('activity.act_logout'),
    'create'      => __('activity.act_create'),
    'update'      => __('activity.act_update'),
    'delete'      => __('activity.act_delete'),
    'restore'     => __('activity.act_restore'),
    'process'     => __('activity.act_process'),
    'clean'       => __('activity.act_clean'),
    'clear'       => __('activity.act_clear'),
    'diagnostics' => __('activity.act_diagnostics'),
];

// Cargar pagina 1 inicial
$initial = getActivityLog([], 1, 25);
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= url('settings') ?>" class="breadcrumb-link"><?= __('menu.settings') ?></a>
    <i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>
    <span class="breadcrumb-current" aria-current="page"><?= __('menu.activity') ?></span>
</nav>

<div class="page-header d-flex items-center justify-between flex-wrap gap-200">
    <div>
        <h1 class="page-title"><?= __('activity.page_title') ?></h1>
        <p class="page-description"><?= __('activity.page_subtitle') ?></p>
    </div>
    <button type="button" class="btn btn-subtle btn-sm" id="btnClearLog" data-tooltip="<?= __('activity.btn_clear_help') ?>">
        <i class="bi bi-trash" aria-hidden="true"></i>
        <?= __('activity.btn_clear') ?>
    </button>
</div>

<!-- Filtros + contador en la misma linea -->
<form class="filter-bar activity-filter-bar" role="search" id="activityFilters">
    <div class="activity-filter-group">
        <label for="fActDateFrom" class="visually-hidden"><?= __('activity.filter_date_from') ?></label>
        <input type="date" id="fActDateFrom" name="date_from" class="form-control" aria-label="<?= __('activity.filter_date_from') ?>">
    </div>
    <div class="activity-filter-group">
        <label for="fActDateTo" class="visually-hidden"><?= __('activity.filter_date_to') ?></label>
        <input type="date" id="fActDateTo" name="date_to" class="form-control" aria-label="<?= __('activity.filter_date_to') ?>">
    </div>
    <select class="form-control filter-bar-select" id="fActUser" name="user" aria-label="<?= __('activity.filter_user') ?>">
        <option value=""><?= __('activity.filter_all_users') ?></option>
        <?php foreach ($users as $username => $user): ?>
        <option value="<?= htmlspecialchars($username) ?>"><?= htmlspecialchars($user['name'] ?? $username) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-control filter-bar-select" id="fActModule" name="module" aria-label="<?= __('activity.filter_module') ?>">
        <option value=""><?= __('activity.filter_all_modules') ?></option>
        <?php foreach ($knownModules as $key => $label): ?>
        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-control filter-bar-select" id="fActAction" name="action_filter" aria-label="<?= __('activity.filter_action') ?>">
        <option value=""><?= __('activity.filter_all_actions') ?></option>
        <?php foreach ($knownActions as $key => $label): ?>
        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="button" class="btn btn-subtle btn-sm" id="btnClearFilters"
            data-tooltip="<?= __('activity.btn_clear_filters') ?>"
            aria-label="<?= __('activity.btn_clear_filters') ?>">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>

    <div class="activity-results" id="activityResults" aria-live="polite">
        <span class="activity-results-badge d-none" id="activityResultsFiltered">
            <i class="bi bi-funnel-fill" aria-hidden="true"></i>
            <?= __('activity.results_filtered') ?>
        </span>
        <span class="activity-results-count" id="activityResultsCount"></span>
    </div>
</form>

<!-- Tabla -->
<div class="card table-card">
    <div class="table-wrapper">
        <table class="table data-table" id="activityTable" aria-label="<?= __('activity.page_title') ?>">
            <thead>
                <tr>
                    <th scope="col" class="activity-col-date"><?= __('activity.col_timestamp') ?></th>
                    <th scope="col"><?= __('activity.col_user') ?></th>
                    <th scope="col" class="d-none-mobile"><?= __('activity.col_module') ?></th>
                    <th scope="col"><?= __('activity.col_action') ?></th>
                    <th scope="col" class="d-none-mobile"><?= __('activity.col_detail') ?></th>
                    <th scope="col" class="d-none-mobile activity-col-ip"><?= __('activity.col_ip') ?></th>
                </tr>
            </thead>
            <tbody id="activityTableBody">
                <!-- Se llena via JS -->
            </tbody>
        </table>
    </div>

    <!-- Estados de resultado -->
    <div class="empty-state d-none" id="activityEmpty">
        <div class="empty-state-icon"><i class="bi bi-inbox" aria-hidden="true"></i></div>
        <h2 class="empty-state-title"><?= __('activity.empty_title') ?></h2>
        <p class="empty-state-description"><?= __('activity.empty_desc') ?></p>
    </div>

    <div class="activity-loading d-none" id="activityLoading">
        <span class="spinner" aria-hidden="true"></span>
        <span><?= __('activity.loading') ?></span>
    </div>

    <!-- Paginacion -->
    <nav class="pagination" id="activityPagination" aria-label="<?= __('activity.pagination_label') ?>"></nav>
</div>

<script>
window.__ACTIVITY_INITIAL__ = <?= json_encode($initial, JSON_UNESCAPED_UNICODE) ?>;
window.__ACTIVITY_MODULES__ = <?= json_encode($knownModules, JSON_UNESCAPED_UNICODE) ?>;
window.__ACTIVITY_ACTIONS__ = <?= json_encode($knownActions, JSON_UNESCAPED_UNICODE) ?>;
</script>
