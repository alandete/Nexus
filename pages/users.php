<?php
/**
 * Nexus 2.0 — Gestion de Usuarios
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();

// Permiso: solo usuarios con acceso pueden ver
if (!canAccessModule($currentUser, 'users')) {
    http_response_code(403);
    include 'pages/403.php';
    return;
}

$canWrite = canEditModule($currentUser, 'users');
$canDelete = canDeleteModule($currentUser, 'users');
$canEditOwn = canEditOwnProfile($currentUser, $currentUser['id'] ?? 0);

$users = getUsers();
$roles = getRoles();

// Estadisticas
$totalUsers = count($users);
$activeUsers = count(array_filter($users, fn($u) => !empty($u['active'])));
$adminCount = count(array_filter($users, fn($u) => ($u['role'] ?? '') === 'admin'));
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= url('settings') ?>" class="breadcrumb-link"><?= __('menu.settings') ?></a>
    <i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>
    <span class="breadcrumb-current" aria-current="page"><?= __('menu.users') ?></span>
</nav>

<!-- Page header -->
<div class="page-header d-flex items-center justify-between flex-wrap gap-200">
    <div>
        <h1 class="page-title"><?= __('users.page_title') ?></h1>
        <p class="page-description"><?= __('users.page_subtitle') ?></p>
    </div>
    <?php if ($canWrite): ?>
    <button type="button" class="btn btn-primary" id="btnCreateUser">
        <i class="bi bi-plus-lg" aria-hidden="true"></i>
        <?= __('users.btn_create') ?>
    </button>
    <?php endif; ?>
</div>

<!-- Stats compactas -->
<div class="users-stats">
    <div class="users-stat">
        <span class="users-stat-value"><?= $totalUsers ?></span>
        <span class="users-stat-label"><?= __('users.stat_total') ?></span>
    </div>
    <div class="users-stat">
        <span class="users-stat-value"><?= $activeUsers ?></span>
        <span class="users-stat-label"><?= __('users.stat_active') ?></span>
    </div>
    <div class="users-stat">
        <span class="users-stat-value"><?= $adminCount ?></span>
        <span class="users-stat-label"><?= __('users.stat_admins') ?></span>
    </div>
</div>

<!-- Filtros -->
<div class="filter-bar" role="search">
    <div class="filter-bar-search">
        <i class="bi bi-search filter-bar-search-icon" aria-hidden="true"></i>
        <input type="search" class="form-control" id="userSearch"
               placeholder="<?= __('users.search_placeholder') ?>"
               aria-label="<?= __('users.search_placeholder') ?>">
    </div>
    <select class="form-control filter-bar-select" id="userRoleFilter" aria-label="<?= __('users.filter_role') ?>">
        <option value=""><?= __('users.filter_all_roles') ?></option>
        <?php foreach ($roles as $roleKey => $role): ?>
        <option value="<?= htmlspecialchars($roleKey) ?>"><?= htmlspecialchars(ucfirst($roleKey)) ?></option>
        <?php endforeach; ?>
    </select>
    <select class="form-control filter-bar-select" id="userStatusFilter" aria-label="<?= __('users.filter_status') ?>">
        <option value=""><?= __('users.filter_all_status') ?></option>
        <option value="active"><?= __('users.filter_active') ?></option>
        <option value="inactive"><?= __('users.filter_inactive') ?></option>
    </select>
</div>

<!-- Tabla de usuarios -->
<div class="card table-card">
    <div class="table-wrapper">
        <table class="table data-table" id="usersTable" aria-label="<?= __('users.page_title') ?>">
            <thead>
                <tr>
                    <th scope="col" class="data-table-col-user"><?= __('users.col_user') ?></th>
                    <th scope="col"><?= __('users.col_role') ?></th>
                    <th scope="col" class="d-none-mobile"><?= __('users.col_lang') ?></th>
                    <th scope="col" class="d-none-mobile"><?= __('users.col_last_login') ?></th>
                    <th scope="col"><?= __('users.col_status') ?></th>
                    <th scope="col" class="data-table-col-actions" aria-label="<?= __('users.col_actions') ?>"></th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <?php foreach ($users as $username => $user):
                    $isSelf = ($currentUser['username'] ?? '') === $username;
                    $initial = mb_strtoupper(mb_substr($user['name'] ?? $username, 0, 1));
                    $roleLozenge = match ($user['role'] ?? '') {
                        'admin'  => 'lozenge-bold',
                        'editor' => 'lozenge-info',
                        'viewer' => 'lozenge-default',
                        default  => 'lozenge-default',
                    };
                    $lastLogin = $user['last_login'] ?? null;
                ?>
                <tr data-username="<?= htmlspecialchars($username) ?>"
                    data-role="<?= htmlspecialchars($user['role'] ?? '') ?>"
                    data-status="<?= !empty($user['active']) ? 'active' : 'inactive' ?>"
                    data-search="<?= htmlspecialchars(strtolower(($user['name'] ?? '') . ' ' . ($user['email'] ?? '') . ' ' . $username)) ?>">
                    <td>
                        <div class="user-cell">
                            <?php if (!empty($user['photo'])): ?>
                                <img src="assets/uploads/avatars/<?= htmlspecialchars($user['photo']) ?>"
                                     alt="" class="avatar avatar-md">
                            <?php else: ?>
                                <span class="avatar avatar-md" aria-hidden="true"><?= $initial ?></span>
                            <?php endif; ?>
                            <div class="user-cell-info">
                                <span class="user-cell-name">
                                    <?= htmlspecialchars($user['name'] ?? '') ?>
                                    <?php if ($isSelf): ?>
                                    <span class="lozenge lozenge-info" title="<?= __('users.you') ?>"><?= __('users.you') ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="user-cell-meta">@<?= htmlspecialchars($username) ?> &middot; <?= htmlspecialchars($user['email'] ?? '') ?></span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="lozenge <?= $roleLozenge ?>"><?= htmlspecialchars(ucfirst($user['role'] ?? '')) ?></span>
                    </td>
                    <td class="d-none-mobile">
                        <span class="text-sm text-subtle"><?= htmlspecialchars(strtoupper($user['lang'] ?? 'ES')) ?></span>
                    </td>
                    <td class="d-none-mobile">
                        <?php if ($lastLogin): ?>
                        <span class="text-sm text-subtle" title="<?= htmlspecialchars($lastLogin) ?>">
                            <?= htmlspecialchars(relativeTime($lastLogin)) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-sm text-subtlest"><?= __('users.never_logged') ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($user['active'])): ?>
                        <span class="lozenge lozenge-success"><?= __('users.status_active') ?></span>
                        <?php else: ?>
                        <span class="lozenge lozenge-default"><?= __('users.status_inactive') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="data-table-col-actions">
                        <?php if ($canWrite || $canEditOwn): ?>
                        <button type="button" class="btn-icon btn-edit-user"
                                data-username="<?= htmlspecialchars($username) ?>"
                                data-tooltip="<?= __('users.btn_edit') ?>"
                                aria-label="<?= __('users.btn_edit') ?>">
                            <i class="bi bi-pencil" aria-hidden="true"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($canDelete && !$isSelf): ?>
                        <button type="button" class="btn-icon btn-icon-danger btn-delete-user"
                                data-username="<?= htmlspecialchars($username) ?>"
                                data-name="<?= htmlspecialchars($user['name'] ?? $username) ?>"
                                data-tooltip="<?= __('users.btn_delete') ?>"
                                aria-label="<?= __('users.btn_delete') ?>">
                            <i class="bi bi-trash" aria-hidden="true"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Empty state (filtros sin resultados) -->
    <div class="empty-state d-none" id="usersEmptyFiltered">
        <div class="empty-state-icon"><i class="bi bi-search" aria-hidden="true"></i></div>
        <h2 class="empty-state-title"><?= __('users.empty_filtered_title') ?></h2>
        <p class="empty-state-description"><?= __('users.empty_filtered_desc') ?></p>
    </div>
</div>

<!-- Datos iniciales para JS -->
<script>
window.__USERS_DATA__ = <?= json_encode(array_map(fn($u) => [
    'id'            => $u['id'] ?? 0,
    'username'      => $u['username'] ?? '',
    'name'          => $u['name'] ?? '',
    'email'         => $u['email'] ?? '',
    'role'          => $u['role'] ?? '',
    'lang'          => $u['lang'] ?? 'es',
    'photo'         => $u['photo'] ?? '',
    'active'        => !empty($u['active']),
    'work_schedule' => $u['work_schedule'] ?? [],
], $users), JSON_UNESCAPED_UNICODE) ?>;
window.__USERS_ROLES__ = <?= json_encode(array_keys($roles)) ?>;
window.__USERS_CAN_WRITE__ = <?= $canWrite ? 'true' : 'false' ?>;
window.__USERS_CAN_DELETE__ = <?= $canDelete ? 'true' : 'false' ?>;
window.__CURRENT_USERNAME__ = <?= json_encode($currentUser['username'] ?? '') ?>;
</script>
