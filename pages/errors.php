<?php
/**
 * Nexus 2.0 — Registro de errores
 * Accesible a cualquier usuario con sesión (enlace visible solo para admin en Ajustes).
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$initial = getErrorLog([], 1, 25);
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= url('settings') ?>" class="breadcrumb-link"><?= __('menu.settings') ?></a>
    <i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>
    <span class="breadcrumb-current" aria-current="page"><?= __('errors.page_title') ?></span>
</nav>

<div class="page-header d-flex items-center justify-between flex-wrap gap-200">
    <div>
        <h1 class="page-title"><?= __('errors.page_title') ?></h1>
        <p class="page-description"><?= __('errors.page_subtitle') ?></p>
    </div>
    <button type="button" class="btn btn-subtle btn-sm" id="btnClearErrorLog"
            data-tooltip="<?= __('errors.btn_clear_help') ?>">
        <i class="bi bi-trash" aria-hidden="true"></i>
        <?= __('errors.btn_clear') ?>
    </button>
</div>

<form class="filter-bar activity-filter-bar" role="search" id="errorFilters">
    <div class="activity-filter-group">
        <label for="fErrDateFrom" class="visually-hidden"><?= __('errors.filter_date_from') ?></label>
        <input type="date" id="fErrDateFrom" name="date_from" class="form-control"
               aria-label="<?= __('errors.filter_date_from') ?>">
    </div>
    <div class="activity-filter-group">
        <label for="fErrDateTo" class="visually-hidden"><?= __('errors.filter_date_to') ?></label>
        <input type="date" id="fErrDateTo" name="date_to" class="form-control"
               aria-label="<?= __('errors.filter_date_to') ?>">
    </div>
    <select class="form-control filter-bar-select" id="fErrLevel" name="level"
            aria-label="<?= __('errors.filter_level') ?>">
        <option value=""><?= __('errors.filter_all_levels') ?></option>
        <option value="fatal"><?= __('errors.level_fatal') ?></option>
        <option value="exception"><?= __('errors.level_exception') ?></option>
        <option value="error"><?= __('errors.level_error') ?></option>
        <option value="warning"><?= __('errors.level_warning') ?></option>
    </select>
    <button type="button" class="btn btn-subtle btn-sm" id="btnClearErrFilters"
            data-tooltip="<?= __('errors.btn_clear_filters') ?>"
            aria-label="<?= __('errors.btn_clear_filters') ?>">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>

    <div class="activity-results" id="errorResults" aria-live="polite">
        <span class="activity-results-badge d-none" id="errorResultsFiltered">
            <i class="bi bi-funnel-fill" aria-hidden="true"></i>
            <?= __('errors.results_filtered') ?>
        </span>
        <span class="activity-results-count" id="errorResultsCount"></span>
    </div>
</form>

<div class="card table-card">
    <div class="table-wrapper">
        <table class="table data-table" id="errorTable" aria-label="<?= __('errors.page_title') ?>">
            <thead>
                <tr>
                    <th scope="col" class="activity-col-date"><?= __('errors.col_timestamp') ?></th>
                    <th scope="col"><?= __('errors.col_level') ?></th>
                    <th scope="col"><?= __('errors.col_message') ?></th>
                    <th scope="col" class="d-none-mobile"><?= __('errors.col_origin') ?></th>
                </tr>
            </thead>
            <tbody id="errorTableBody"></tbody>
        </table>
    </div>

    <div class="empty-state d-none" id="errorEmpty">
        <div class="empty-state-icon"><i class="bi bi-check-circle" aria-hidden="true"></i></div>
        <h2 class="empty-state-title"><?= __('errors.empty_title') ?></h2>
        <p class="empty-state-description"><?= __('errors.empty_desc') ?></p>
    </div>

    <div class="activity-loading d-none" id="errorLoading">
        <span class="spinner" aria-hidden="true"></span>
        <span><?= __('errors.loading') ?></span>
    </div>

    <nav class="pagination" id="errorPagination" aria-label="<?= __('errors.pagination_label') ?>"></nav>
</div>

<script>
window.__ERRORLOG_INITIAL__ = <?= json_encode($initial, JSON_UNESCAPED_UNICODE) ?>;
</script>
