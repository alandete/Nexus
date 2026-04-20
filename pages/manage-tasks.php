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

// Precargar etiquetas para render inicial (fallback si JS falla)
$allTags = [];
$tagsStats = ['total' => 0, 'in_use' => 0];
if (isDBAvailable()) {
    try {
        $stmt = getDB()->prepare("
            SELECT t.id, t.name, t.color,
                   (SELECT COUNT(*) FROM task_tags tt WHERE tt.tag_id = t.id) AS usage_count
            FROM tags t
            ORDER BY t.name ASC
        ");
        $stmt->execute();
        $allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $tagsStats['total']  = count($allTags);
        $tagsStats['in_use'] = count(array_filter($allTags, fn($t) => (int) $t['usage_count'] > 0));
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
    <p class="manage-section-desc"><?= __('manage_tasks.io_desc') ?></p>
    <div class="empty-state p-300">
        <div class="empty-state-icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
        <h3 class="empty-state-title"><?= __('manage_tasks.io_placeholder_title') ?></h3>
        <p class="empty-state-description"><?= __('manage_tasks.io_placeholder_desc') ?></p>
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
    tags: <?= json_encode($allTags, JSON_UNESCAPED_UNICODE) ?>,
    canWrite: <?= $canWrite ? 'true' : 'false' ?>,
    canDelete: <?= $canDelete ? 'true' : 'false' ?>,
};
</script>
