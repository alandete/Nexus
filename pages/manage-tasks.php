<?php
/**
 * Nexus 2.0 — Administrar tareas (Ajustes)
 * Sub-fase 5: CRUD de etiquetas + import/export/cleanup (pendiente)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();

if (!canAccessModule($currentUser, 'settings')) {
    http_response_code(403);
    include 'pages/403.php';
    return;
}

$canWrite  = canEditModule($currentUser, 'settings');
$canDelete = canDeleteModule($currentUser, 'settings');

// Precargar etiquetas y alianzas para el JS
$allTags = [];
$tagsStats = ['total' => 0, 'in_use' => 0];
$allAlliances = [];
if (isDBAvailable()) {
    $db = getDB();
    try {
        $stmt = $db->prepare("
            SELECT t.id, t.name, t.color,
                   (SELECT COUNT(*) FROM task_tags tt WHERE tt.tag_id = t.id) AS usage_count
            FROM tags t ORDER BY t.name ASC
        ");
        $stmt->execute();
        $allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tagsStats['total']  = count($allTags);
        $tagsStats['in_use'] = count(array_filter($allTags, fn($t) => (int) $t['usage_count'] > 0));
    } catch (Exception $e) { /* ignore */ }
    try {
        $stmt2 = $db->query("SELECT id, name, color FROM alliances ORDER BY name ASC");
        $allAlliances = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* ignore */ }
}
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= url('settings') ?>" class="breadcrumb-link"><?= __('menu.settings') ?></a>
    <i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>
    <span class="breadcrumb-current" aria-current="page"><?= __('menu.manage_tasks') ?></span>
</nav>

<div class="page-header">
    <h1 class="page-title"><?= __('manage_tasks.page_title') ?></h1>
    <p class="page-description"><?= __('manage_tasks.page_subtitle') ?></p>
</div>

<!-- ========== TABS ========== -->
<div class="tabs manage-tabs" role="tablist" aria-label="<?= __('manage_tasks.tabs_label') ?>">
    <button type="button" class="tab active" id="tabTags" role="tab" aria-selected="true" aria-controls="panelTags" data-tab="tags">
        <i class="bi bi-tags" aria-hidden="true"></i>
        <?= __('manage_tasks.tags_title') ?>
    </button>
    <button type="button" class="tab" id="tabIo" role="tab" aria-selected="false" aria-controls="panelIo" data-tab="io">
        <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
        <?= __('manage_tasks.io_title') ?>
    </button>
    <button type="button" class="tab" id="tabCleanup" role="tab" aria-selected="false" aria-controls="panelCleanup" data-tab="cleanup">
        <i class="bi bi-trash" aria-hidden="true"></i>
        <?= __('manage_tasks.cleanup_title') ?>
    </button>
</div>

<!-- ========== PANEL: ETIQUETAS ========== -->
<section class="manage-panel" id="panelTags" role="tabpanel" aria-labelledby="tabTags">
    <p class="manage-section-desc"><?= __('manage_tasks.tags_desc') ?></p>

    <!-- Stats -->
    <div class="users-stats">
        <div class="users-stat">
            <span class="users-stat-value" id="statTagsTotal"><?= (int) $tagsStats['total'] ?></span>
            <span class="users-stat-label"><?= __('manage_tasks.stat_total') ?></span>
        </div>
        <div class="users-stat">
            <span class="users-stat-value" id="statTagsInUse"><?= (int) $tagsStats['in_use'] ?></span>
            <span class="users-stat-label"><?= __('manage_tasks.stat_in_use') ?></span>
        </div>
        <div class="users-stat">
            <span class="users-stat-value" id="statTagsUnused"><?= (int) ($tagsStats['total'] - $tagsStats['in_use']) ?></span>
            <span class="users-stat-label"><?= __('manage_tasks.stat_unused') ?></span>
        </div>
    </div>

    <!-- Tabla de etiquetas inline-editable -->
    <div class="tags-grid" id="tagsGrid"></div>
</section>

<!-- ========== PANEL: IMPORTAR/EXPORTAR ========== -->
<section class="manage-panel d-none" id="panelIo" role="tabpanel" aria-labelledby="tabIo" hidden>

    <!-- ── EXPORTAR ── -->
    <div class="manage-section">
        <h3 class="manage-section-title"><i class="bi bi-download" aria-hidden="true"></i> <?= __('manage_tasks.export_title') ?></h3>
        <p class="manage-section-desc"><?= __('manage_tasks.export_desc') ?></p>

        <div class="io-export-bar">
            <div class="report-filter-group">
                <span class="report-filter-label" id="ioRangeLabel"><?= __('manage_tasks.export_range_label') ?></span>
                <div class="btn-group" role="radiogroup" aria-labelledby="ioRangeLabel" id="ioRangeGroup">
                    <button type="button" class="btn-group-item" data-range="today" role="radio" aria-checked="false" tabindex="-1"><?= __('manage_tasks.range_today') ?></button>
                    <button type="button" class="btn-group-item active" data-range="week" role="radio" aria-checked="true" tabindex="0"><?= __('manage_tasks.range_week') ?></button>
                    <button type="button" class="btn-group-item" data-range="month" role="radio" aria-checked="false" tabindex="-1"><?= __('manage_tasks.range_month') ?></button>
                    <button type="button" class="btn-group-item" data-range="last_month" role="radio" aria-checked="false" tabindex="-1"><?= __('manage_tasks.range_last_month') ?></button>
                    <button type="button" class="btn-group-item" data-range="custom" role="radio" aria-checked="false" tabindex="-1"><i class="bi bi-calendar-range" aria-hidden="true"></i> <?= __('manage_tasks.range_custom') ?></button>
                </div>
            </div>

            <div class="io-custom-range d-none" id="ioCustomRange">
                <label class="io-date-label" for="ioCustomStart"><?= __('manage_tasks.range_from') ?></label>
                <input type="date" id="ioCustomStart" class="form-control form-control-sm">
                <label class="io-date-label" for="ioCustomEnd"><?= __('manage_tasks.range_to') ?></label>
                <input type="date" id="ioCustomEnd" class="form-control form-control-sm">
            </div>

            <div class="report-filter-group">
                <span class="report-filter-label" id="ioFormatLabel"><?= __('manage_tasks.export_format_label') ?></span>
                <div class="btn-group" role="radiogroup" aria-labelledby="ioFormatLabel" id="ioExportFormatGroup">
                    <button type="button" class="btn-group-item active" data-format="nexus" role="radio" aria-checked="true" tabindex="0">Nexus CSV</button>
                    <button type="button" class="btn-group-item" data-format="clockify" role="radio" aria-checked="false" tabindex="-1">Clockify CSV</button>
                </div>
            </div>

            <button type="button" class="btn btn-default" id="btnExport">
                <i class="bi bi-download" aria-hidden="true"></i> <?= __('manage_tasks.export_btn') ?>
            </button>
        </div>
    </div>

    <!-- ── IMPORTAR ── -->
    <div class="manage-section">
        <h3 class="manage-section-title"><i class="bi bi-upload" aria-hidden="true"></i> <?= __('manage_tasks.import_title') ?></h3>
        <p class="manage-section-desc"><?= __('manage_tasks.import_desc') ?></p>

        <div class="report-filter-group io-import-format-group">
            <span class="report-filter-label" id="ioImportFormatLabel"><?= __('manage_tasks.import_format_label') ?></span>
            <div class="btn-group" role="radiogroup" aria-labelledby="ioImportFormatLabel" id="ioImportFormatGroup">
                <button type="button" class="btn-group-item active" data-format="nexus" role="radio" aria-checked="true" tabindex="0">Nexus CSV</button>
                <button type="button" class="btn-group-item" data-format="clockify" role="radio" aria-checked="false" tabindex="-1">Clockify CSV</button>
            </div>
        </div>

        <div class="io-dropzone" id="ioDropzone" tabindex="0" role="button"
             aria-label="<?= __('manage_tasks.import_drop_aria') ?>">
            <i class="bi bi-cloud-upload io-dropzone-icon" aria-hidden="true"></i>
            <p class="io-dropzone-text"><?= __('manage_tasks.import_drop_text') ?></p>
            <p class="io-dropzone-hint" id="ioDropzoneHint"><?= __('manage_tasks.import_drop_hint_clockify') ?></p>
            <input type="file" id="ioFileInput" accept=".csv,text/csv" aria-hidden="true">
        </div>

        <!-- Resultado del parseo: oculto hasta que se cargue un archivo -->
        <div id="ioParseResult" class="d-none">
            <div class="io-stats" id="ioStats"></div>

            <div id="ioAllianceMapping" class="d-none io-mapping-section">
                <h4 class="io-mapping-title"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> <?= __('manage_tasks.import_unknown_alliances') ?></h4>
                <p class="io-mapping-desc"><?= __('manage_tasks.import_unknown_alliances_desc') ?></p>
                <div id="ioAllianceMappingRows"></div>
            </div>

            <div id="ioTagMapping" class="d-none io-mapping-section">
                <h4 class="io-mapping-title"><i class="bi bi-tag-fill" aria-hidden="true"></i> <?= __('manage_tasks.import_unknown_tags') ?></h4>
                <p class="io-mapping-desc"><?= __('manage_tasks.import_unknown_tags_desc') ?></p>
                <div id="ioTagMappingRows"></div>
            </div>

            <h4 class="io-mapping-title"><?= __('manage_tasks.import_preview_title') ?></h4>
            <div class="table-container io-preview-wrap" id="ioPreviewWrap"></div>

            <div class="io-import-actions">
                <button type="button" class="btn btn-primary" id="btnImport">
                    <i class="bi bi-upload" aria-hidden="true"></i>
                    <span id="ioImportLabel"><?= __('manage_tasks.import_btn') ?></span>
                </button>
                <button type="button" class="btn btn-subtle" id="btnImportCancel">
                    <?= __('common.cancel') ?>
                </button>
            </div>
        </div>
    </div>

</section>

<!-- ========== PANEL: LIMPIEZA ========== -->
<section class="manage-panel d-none" id="panelCleanup" role="tabpanel" aria-labelledby="tabCleanup" hidden>
    <p class="manage-section-desc"><?= __('manage_tasks.cleanup_desc') ?></p>
    <div class="empty-state p-300">
        <div class="empty-state-icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
        <h3 class="empty-state-title"><?= __('manage_tasks.cleanup_placeholder_title') ?></h3>
        <p class="empty-state-description"><?= __('manage_tasks.cleanup_placeholder_desc') ?></p>
    </div>
</section>

<script>
window.__MANAGE_TASKS__ = {
    tags:      <?= json_encode($allTags,      JSON_UNESCAPED_UNICODE) ?>,
    alliances: <?= json_encode($allAlliances, JSON_UNESCAPED_UNICODE) ?>,
    canWrite:  <?= $canWrite  ? 'true' : 'false' ?>,
    canDelete: <?= $canDelete ? 'true' : 'false' ?>,
};
</script>
