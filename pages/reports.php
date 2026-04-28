<?php
/**
 * Nexus 2.0 — Reportes (Fase 4.5)
 * Filtros en una sola linea: tipo (resumido/detallado) + rango (semanal/mensual/personalizado) + usuario.
 * Exports: CSV, XLSX (SheetJS), PDF (window.print con @media print).
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();
$isAdmin = strtolower($currentUser['role'] ?? '') === 'admin';
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= url('tasks') ?>" class="breadcrumb-link"><?= __('menu.tasks') ?></a>
    <i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>
    <span class="breadcrumb-current" aria-current="page"><?= __('reports.page_title') ?></span>
</nav>

<div class="page-header">
    <h1 class="page-title"><?= __('reports.page_title') ?></h1>
    <p class="page-description"><?= __('reports.page_subtitle') ?></p>
</div>

<!-- Barra de filtros: cada grupo con su label encima -->
<section class="report-filters" aria-labelledby="reportFiltersTitle">
    <h2 class="visually-hidden" id="reportFiltersTitle"><?= __('reports.filters_label') ?></h2>

    <div class="report-filter-bar">
        <!-- Tipo de reporte -->
        <div class="report-filter-group">
            <span class="report-filter-label" id="labelReportType"><?= __('reports.field_type') ?></span>
            <div class="btn-group" role="radiogroup" aria-labelledby="labelReportType">
                <button type="button" class="btn-group-item active" data-report-type="summary" role="radio" aria-checked="true" tabindex="0">
                    <?= __('reports.type_summary') ?>
                </button>
                <button type="button" class="btn-group-item" data-report-type="detailed" role="radio" aria-checked="false" tabindex="-1">
                    <?= __('reports.type_detailed') ?>
                </button>
            </div>
        </div>

        <!-- Rango de fechas -->
        <div class="report-filter-group">
            <span class="report-filter-label" id="labelReportRange"><?= __('reports.field_range') ?></span>
            <div class="btn-group" role="radiogroup" aria-labelledby="labelReportRange">
                <button type="button" class="btn-group-item" data-range="weekly" role="radio" aria-checked="false" tabindex="-1">
                    <?= __('reports.range_weekly') ?>
                </button>
                <button type="button" class="btn-group-item active" data-range="monthly" role="radio" aria-checked="true" tabindex="0">
                    <?= __('reports.range_monthly') ?>
                </button>
                <button type="button" class="btn-group-item" data-range="custom" role="radio" aria-checked="false" tabindex="-1" aria-haspopup="dialog">
                    <i class="bi bi-calendar-range" aria-hidden="true"></i>
                    <?= __('reports.range_custom') ?>
                </button>
            </div>
        </div>

        <!-- Usuario (solo admin) -->
        <?php if ($isAdmin): ?>
        <div class="report-filter-group">
            <label class="report-filter-label" for="reportUser"><?= __('reports.field_user') ?></label>
            <select id="reportUser" class="form-control form-control-sm report-user-select">
                <!-- Se popula via JS -->
            </select>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Barra combinada: filtros a la izq, exports a la der -->
<div class="report-action-bar" id="reportActionBar">
    <div class="report-action-filters">
        <!-- Multi-select alianzas -->
        <div class="report-filter-group">
            <span class="report-filter-label"><?= __('reports.field_alliance') ?></span>
            <div class="report-ms" id="allianceMs">
                <button type="button" class="btn btn-default btn-sm report-ms-btn" id="allianceMsBtn"
                        aria-haspopup="listbox" aria-expanded="false">
                    <span class="report-ms-label"><?= __('reports.all_alliances') ?></span>
                    <i class="bi bi-chevron-down report-ms-chevron" aria-hidden="true"></i>
                </button>
                <div class="report-ms-panel" id="allianceMsPanel" role="listbox" aria-multiselectable="true" hidden>
                    <!-- Populado via JS -->
                </div>
            </div>
        </div>

        <!-- Multi-select etiquetas -->
        <div class="report-filter-group">
            <span class="report-filter-label"><?= __('reports.field_tags') ?></span>
            <div class="report-ms" id="tagMs">
                <button type="button" class="btn btn-default btn-sm report-ms-btn" id="tagMsBtn"
                        aria-haspopup="listbox" aria-expanded="false">
                    <span class="report-ms-label"><?= __('reports.all_tags') ?></span>
                    <i class="bi bi-chevron-down report-ms-chevron" aria-hidden="true"></i>
                </button>
                <div class="report-ms-panel" id="tagMsPanel" role="listbox" aria-multiselectable="true" hidden>
                    <!-- Populado via JS -->
                </div>
            </div>
        </div>

        <!-- Limpiar filtros (visible solo cuando hay filtros activos) -->
        <button type="button" class="btn btn-subtle btn-sm d-none" id="reportClearBtn">
            <i class="bi bi-x-circle" aria-hidden="true"></i>
            <?= __('reports.clear_filters') ?>
        </button>
    </div>

    <div class="report-export-group d-none" id="reportExportGroup">
        <span class="report-export-label"><?= __('reports.export_as') ?></span>
        <button type="button" class="btn btn-subtle btn-sm" data-export="csv">
            <i class="bi bi-filetype-csv" aria-hidden="true"></i> CSV
        </button>
        <button type="button" class="btn btn-subtle btn-sm" data-export="xls">
            <i class="bi bi-filetype-xlsx" aria-hidden="true"></i> Excel
        </button>
        <button type="button" class="btn btn-subtle btn-sm" data-export="pdf">
            <i class="bi bi-filetype-pdf" aria-hidden="true"></i> PDF
        </button>
    </div>
</div>

<!-- Gráfico temporal: siempre visible cuando hay datos -->
<section class="report-bar-section d-none" id="reportBarSection" aria-label="<?= __('reports.bar_chart_label') ?>">
    <canvas id="reportBarChart"></canvas>
</section>

<!-- Vista del reporte -->
<section class="report-view" id="reportView" aria-live="polite" aria-busy="false">
    <div class="report-loading d-none" id="reportLoading" role="status">
        <span class="spinner" aria-hidden="true"></span>
        <span><?= __('common.loading') ?>...</span>
    </div>
    <div class="report-content" id="reportContent"></div>
</section>

<script>
window.__REPORTS__ = {
    isAdmin: <?= $isAdmin ? 'true' : 'false' ?>,
    currentUser: {
        name: <?= json_encode($currentUser['name'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
        username: <?= json_encode($currentUser['username'] ?? '', JSON_UNESCAPED_UNICODE) ?>,
    },
};
</script>
