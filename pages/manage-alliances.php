<?php
/**
 * Nexus 2.0 — Gestion de Alianzas
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();

if (!canAccessModule($currentUser, 'settings')) {
    http_response_code(403);
    include 'pages/403.php';
    return;
}

$canWrite = canEditModule($currentUser, 'settings');
$canDelete = canDeleteModule($currentUser, 'settings');

$alliances = getAlliances();
$users = getUsers();

// Normalizar para JS
$alliancesJs = [];
foreach ($alliances as $slug => $a) {
    $alliancesJs[$slug] = [
        'slug'       => $slug,
        'name'       => $a['name'] ?? '',
        'fullname'   => $a['fullname'] ?? '',
        'country'    => $a['country'] ?? '',
        'color'      => $a['color'] ?? '#585d8a',
        'website'    => $a['website'] ?? '',
        'lms_url'    => $a['lms_url'] ?? '',
        'active'     => !empty($a['active']),
        'billable'   => !empty($a['billable']),
        'manager'    => $a['manager'] ?? ['name' => '', 'email' => '', 'is_user' => false, 'username' => null],
        'coordinator'=> $a['coordinator'] ?? ['name' => '', 'email' => '', 'is_user' => false, 'username' => null],
        'migrator'   => $a['migrator'] ?? ['name' => '', 'email' => '', 'is_user' => false, 'username' => null],
        'files'      => $a['files'] ?? [],
        'created_at' => $a['created_at'] ?? null,
        'updated_at' => $a['updated_at'] ?? null,
    ];
}

$usersForSelect = [];
foreach ($users as $username => $u) {
    if (!empty($u['active'])) {
        $usersForSelect[$username] = [
            'username' => $username,
            'name'     => $u['name'] ?? '',
            'email'    => $u['email'] ?? '',
        ];
    }
}

// Stats
$totalAlliances = count($alliances);
$activeAlliances = count(array_filter($alliances, fn($a) => !empty($a['active'])));
$billableCount = count(array_filter($alliances, fn($a) => !empty($a['billable'])));
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= url('settings') ?>" class="breadcrumb-link"><?= __('menu.settings') ?></a>
    <i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>
    <span class="breadcrumb-current" aria-current="page"><?= __('menu.manage_alliances') ?></span>
</nav>

<div class="page-header d-flex items-center justify-between flex-wrap gap-200">
    <div>
        <h1 class="page-title"><?= __('manage_alliances.page_title') ?></h1>
        <p class="page-description"><?= __('manage_alliances.page_subtitle') ?></p>
    </div>
    <div class="d-flex gap-100">
        <?php if ($canWrite): ?>
        <a href="includes/alliance_export_actions.php" class="btn btn-secondary" id="btnExportAlliances">
            <i class="bi bi-download" aria-hidden="true"></i>
            <?= __('manage_alliances.btn_export') ?>
        </a>
        <button type="button" class="btn btn-secondary" id="btnImportAlliances">
            <i class="bi bi-upload" aria-hidden="true"></i>
            <?= __('manage_alliances.btn_import') ?>
        </button>
        <input type="file" id="importAlliancesInput" accept=".json" style="display:none">
        <button type="button" class="btn btn-primary" id="btnCreateAlliance">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <?= __('manage_alliances.btn_create') ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- Stats -->
<div class="users-stats">
    <div class="users-stat">
        <span class="users-stat-value"><?= $totalAlliances ?></span>
        <span class="users-stat-label"><?= __('manage_alliances.stat_total') ?></span>
    </div>
    <div class="users-stat">
        <span class="users-stat-value"><?= $activeAlliances ?></span>
        <span class="users-stat-label"><?= __('manage_alliances.stat_active') ?></span>
    </div>
    <div class="users-stat">
        <span class="users-stat-value"><?= $billableCount ?></span>
        <span class="users-stat-label"><?= __('manage_alliances.stat_billable') ?></span>
    </div>
</div>

<!-- Filtros + toggle de vista -->
<div class="filter-bar" role="search">
    <div class="filter-bar-search">
        <i class="bi bi-search filter-bar-search-icon" aria-hidden="true"></i>
        <input type="search" class="form-control" id="allianceSearch"
               placeholder="<?= __('manage_alliances.search_placeholder') ?>"
               aria-label="<?= __('manage_alliances.search_placeholder') ?>">
    </div>
    <select class="form-control filter-bar-select" id="allianceStatusFilter" aria-label="<?= __('manage_alliances.filter_status') ?>">
        <option value=""><?= __('manage_alliances.filter_all_status') ?></option>
        <option value="active"><?= __('manage_alliances.status_active') ?></option>
        <option value="inactive"><?= __('manage_alliances.status_inactive') ?></option>
    </select>
    <div class="view-toggle" role="group" aria-label="<?= __('manage_alliances.view_toggle') ?>">
        <button type="button" class="view-toggle-btn active" data-view="cards"
                data-tooltip="<?= __('manage_alliances.view_cards') ?>" aria-label="<?= __('manage_alliances.view_cards') ?>">
            <i class="bi bi-grid" aria-hidden="true"></i>
        </button>
        <button type="button" class="view-toggle-btn" data-view="table"
                data-tooltip="<?= __('manage_alliances.view_table') ?>" aria-label="<?= __('manage_alliances.view_table') ?>">
            <i class="bi bi-list-ul" aria-hidden="true"></i>
        </button>
    </div>
</div>

<!-- Vista tarjetas -->
<div class="alliances-grid" id="alliancesGrid">
    <?php foreach ($alliancesJs as $slug => $a):
        $fileCount = count($a['files']);
    ?>
    <article class="alliance-card"
             data-slug="<?= htmlspecialchars($slug) ?>"
             data-status="<?= !empty($a['active']) ? 'active' : 'inactive' ?>"
             data-search="<?= htmlspecialchars(strtolower(($a['name']) . ' ' . ($a['fullname']) . ' ' . ($a['country']))) ?>"
             style="--alliance-color: <?= htmlspecialchars($a['color']) ?>;">
        <header class="alliance-card-header">
            <div class="alliance-card-title-row">
                <h2 class="alliance-card-title">
                    <?php if (!empty($a['country'])): ?><span class="fi fi-<?= strtolower(htmlspecialchars($a['country'])) ?> alliance-flag" aria-hidden="true"></span><?php endif; ?>
                    <?= htmlspecialchars($a['name']) ?>
                </h2>
                <?php if (!empty($a['active'])): ?>
                <span class="lozenge lozenge-success"><?= __('manage_alliances.status_active') ?></span>
                <?php else: ?>
                <span class="lozenge lozenge-default"><?= __('manage_alliances.status_inactive') ?></span>
                <?php endif; ?>
            </div>
            <p class="alliance-card-subtitle"><?= htmlspecialchars($a['fullname']) ?></p>
        </header>

        <div class="alliance-card-body">
            <div class="alliance-responsible-stack" aria-label="<?= __('manage_alliances.responsibles') ?>">
                <?php foreach (['manager', 'coordinator', 'migrator'] as $role):
                    $person = $a[$role] ?? null;
                    if (empty($person['name'])) continue;
                    $initial = mb_strtoupper(mb_substr($person['name'], 0, 1));
                    $label = __("manage_alliances.field_$role") . ': ' . $person['name'];
                ?>
                <span class="alliance-responsible-avatar" title="<?= htmlspecialchars($label) ?>" data-tooltip="<?= htmlspecialchars($label) ?>">
                    <?= $initial ?>
                </span>
                <?php endforeach; ?>
            </div>

            <div class="alliance-card-meta">
                <?php if ($fileCount > 0): ?>
                <span class="alliance-meta-item">
                    <i class="bi bi-paperclip" aria-hidden="true"></i>
                    <?= $fileCount ?> <?= $fileCount === 1 ? __('manage_alliances.file') : __('manage_alliances.files') ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($a['billable'])): ?>
                <span class="alliance-meta-item">
                    <i class="bi bi-cash-coin" aria-hidden="true"></i>
                    <?= __('manage_alliances.billable_short') ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($a['updated_at'])): ?>
                <span class="alliance-meta-item text-subtlest" title="<?= htmlspecialchars($a['updated_at']) ?>">
                    <i class="bi bi-clock" aria-hidden="true"></i>
                    <?= htmlspecialchars(relativeTime($a['updated_at'])) ?>
                </span>
                <?php endif; ?>
            </div>
        </div>

        <footer class="alliance-card-footer">
            <?php if ($canWrite): ?>
            <button type="button" class="btn btn-subtle btn-sm btn-edit-alliance" data-slug="<?= htmlspecialchars($slug) ?>">
                <i class="bi bi-pencil" aria-hidden="true"></i> <?= __('manage_alliances.btn_edit') ?>
            </button>
            <?php endif; ?>
            <?php if ($canDelete): ?>
            <button type="button" class="btn-icon btn-icon-danger btn-delete-alliance"
                    data-slug="<?= htmlspecialchars($slug) ?>"
                    data-name="<?= htmlspecialchars($a['name']) ?>"
                    data-tooltip="<?= __('manage_alliances.btn_delete') ?>"
                    aria-label="<?= __('manage_alliances.btn_delete') ?>">
                <i class="bi bi-trash" aria-hidden="true"></i>
            </button>
            <?php endif; ?>
        </footer>
    </article>
    <?php endforeach; ?>

    <?php if (empty($alliancesJs)): ?>
    <div class="empty-state card" style="grid-column: 1 / -1;">
        <div class="empty-state-icon"><i class="bi bi-building" aria-hidden="true"></i></div>
        <h2 class="empty-state-title"><?= __('manage_alliances.empty_title') ?></h2>
        <p class="empty-state-description"><?= __('manage_alliances.empty_desc') ?></p>
        <?php if ($canWrite): ?>
        <button type="button" class="btn btn-primary" id="btnCreateAllianceEmpty">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
            <?= __('manage_alliances.btn_create') ?>
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Empty state filtrado -->
<div class="empty-state d-none" id="alliancesEmptyFiltered">
    <div class="empty-state-icon"><i class="bi bi-search" aria-hidden="true"></i></div>
    <h2 class="empty-state-title"><?= __('manage_alliances.empty_filtered_title') ?></h2>
    <p class="empty-state-description"><?= __('manage_alliances.empty_filtered_desc') ?></p>
</div>

<script>
window.__ALLIANCES_DATA__ = <?= json_encode($alliancesJs, JSON_UNESCAPED_UNICODE) ?>;
window.__ALLIANCE_USERS__ = <?= json_encode(array_values($usersForSelect), JSON_UNESCAPED_UNICODE) ?>;
window.__ALLIANCES_CAN_WRITE__ = <?= $canWrite ? 'true' : 'false' ?>;
window.__ALLIANCES_CAN_DELETE__ = <?= $canDelete ? 'true' : 'false' ?>;
</script>
