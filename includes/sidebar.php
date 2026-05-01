<?php
/**
 * Nexus 2.0 — Sidebar Navigation
 * Navegacion principal con secciones colapsables
 * Atlassian DS: side-navigation pattern
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentPage = isset($_GET['page']) ? $_GET['page'] : 'home';
$currentUser = getCurrentUser();

// Determinar que secciones del sidebar estan expandidas
$settingsPages  = ['settings', 'users', 'manage-alliances', 'manage-tasks', 'application', 'integrations', 'snapshots', 'system', 'activity'];
$isSettingsActive  = in_array($currentPage, $settingsPages);
$utilitiesPages = ['utilities-images', 'utilities-pdf', 'utilities-gift'];
$isUtilitiesActive = in_array($currentPage, $utilitiesPages);
?>

<aside class="sidebar" id="sidebar" role="navigation" aria-label="<?= __('menu.main_nav') ?>">
    <nav class="sidebar-nav">
        <!-- Navegacion principal -->
        <ul class="sidebar-menu" role="menubar">
            <!-- Home -->
            <li class="sidebar-item" role="none">
                <a class="sidebar-link <?= $currentPage === 'home' ? 'active' : '' ?>"
                   href="<?= url('home') ?>" role="menuitem"
                   data-tooltip="<?= __('menu.home') ?>"
                   <?= $currentPage === 'home' ? 'aria-current="page"' : '' ?>>
                    <i class="bi bi-house sidebar-link-icon" aria-hidden="true"></i>
                    <span class="sidebar-link-text"><?= __('menu.home') ?></span>
                </a>
            </li>

            <!-- Tareas -->
            <li class="sidebar-item" role="none">
                <a class="sidebar-link <?= $currentPage === 'tasks' ? 'active' : '' ?>"
                   href="<?= url('tasks') ?>" role="menuitem"
                   data-tooltip="<?= __('menu.tasks') ?>"
                   <?= $currentPage === 'tasks' ? 'aria-current="page"' : '' ?>>
                    <i class="bi bi-check2-square sidebar-link-icon" aria-hidden="true"></i>
                    <span class="sidebar-link-text"><?= __('menu.tasks') ?></span>
                </a>
            </li>

            <!-- Separator -->
            <li class="sidebar-separator" role="separator"></li>

            <!-- Alianzas (con sub-items) -->
            <li class="sidebar-item sidebar-group" role="none">
                <button class="sidebar-link sidebar-group-toggle <?= $currentPage === 'alliances' ? 'active' : '' ?>"
                        type="button" aria-expanded="<?= $currentPage === 'alliances' ? 'true' : 'false' ?>"
                        aria-controls="sidebar-sub-alliances"
                        data-tooltip="<?= __('menu.alliances') ?>">
                    <i class="bi bi-building sidebar-link-icon" aria-hidden="true"></i>
                    <span class="sidebar-link-text"><?= __('menu.alliances') ?></span>
                    <i class="bi bi-chevron-down sidebar-chevron" aria-hidden="true"></i>
                </button>
                <ul class="sidebar-submenu <?= $currentPage === 'alliances' ? 'show' : '' ?>" id="sidebar-sub-alliances" role="menu">
                    <?php
                    $currentAlliance = $_GET['alliance'] ?? 'unis';
                    $readyAlliances  = ['unis', 'unab'];
                    $sidebarAlliances = getAlliances();
                    foreach ($sidebarAlliances as $aSlug => $aData):
                        if (empty($aData['active'])) continue;
                        $isCurrentAlliance = $currentPage === 'alliances' && $currentAlliance === $aSlug;
                        $isReady = in_array($aSlug, $readyAlliances);
                    ?>
                    <li role="none">
                        <a class="sidebar-sublink <?= $isCurrentAlliance ? 'active' : '' ?> <?= !$isReady ? 'sidebar-sublink--pending' : '' ?>"
                           href="<?= url('alliances') ?>?alliance=<?= urlencode($aSlug) ?>" role="menuitem">
                            <span class="sidebar-link-text"><?= htmlspecialchars($aData['name']) ?></span>
                            <?php if (!$isReady): ?>
                            <span class="sidebar-pending-dot" aria-label="En desarrollo"></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </li>

            <!-- Utilidades (con sub-items) -->
            <li class="sidebar-item sidebar-group" role="none">
                <button class="sidebar-link sidebar-group-toggle <?= $isUtilitiesActive ? 'active' : '' ?>"
                        type="button" aria-expanded="<?= $isUtilitiesActive ? 'true' : 'false' ?>"
                        aria-controls="sidebar-sub-utilities"
                        data-tooltip="<?= __('menu.utilities') ?>">
                    <i class="bi bi-tools sidebar-link-icon" aria-hidden="true"></i>
                    <span class="sidebar-link-text"><?= __('menu.utilities') ?></span>
                    <i class="bi bi-chevron-down sidebar-chevron" aria-hidden="true"></i>
                </button>
                <ul class="sidebar-submenu <?= $isUtilitiesActive ? 'show' : '' ?>" id="sidebar-sub-utilities" role="menu">
                    <li role="none">
                        <a class="sidebar-sublink <?= $currentPage === 'utilities-gift' ? 'active' : '' ?>" href="<?= url('utilities-gift') ?>" role="menuitem">
                            <i class="bi bi-file-earmark-text sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.questions') ?></span>
                        </a>
                    </li>
                    <li role="none">
                        <a class="sidebar-sublink <?= $currentPage === 'utilities-pdf' ? 'active' : '' ?>" href="<?= url('utilities-pdf') ?>" role="menuitem">
                            <i class="bi bi-file-earmark-pdf sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.pdf_optimizer') ?></span>
                        </a>
                    </li>
                    <li role="none">
                        <a class="sidebar-sublink <?= $currentPage === 'utilities-images' ? 'active' : '' ?>" href="<?= url('utilities-images') ?>" role="menuitem">
                            <i class="bi bi-image sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.image_optimizer') ?></span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Separator -->
            <li class="sidebar-separator" role="separator"></li>

            <!-- Ajustes (con sub-items) -->
            <?php if (canAccessModule($currentUser, 'settings')): ?>
            <li class="sidebar-item sidebar-group" role="none">
                <button class="sidebar-link sidebar-group-toggle <?= $isSettingsActive ? 'active' : '' ?>"
                        type="button" aria-expanded="<?= $isSettingsActive ? 'true' : 'false' ?>"
                        aria-controls="sidebar-sub-settings"
                        data-tooltip="<?= __('menu.settings') ?>">
                    <i class="bi bi-gear sidebar-link-icon" aria-hidden="true"></i>
                    <span class="sidebar-link-text"><?= __('menu.settings') ?></span>
                    <i class="bi bi-chevron-down sidebar-chevron" aria-hidden="true"></i>
                </button>
                <ul class="sidebar-submenu <?= $isSettingsActive ? 'show' : '' ?>" id="sidebar-sub-settings" role="menu">
                    <?php if (canAccessModule($currentUser, 'users')): ?>
                    <li role="none">
                        <a class="sidebar-sublink <?= $currentPage === 'users' ? 'active' : '' ?>" href="<?= url('users') ?>" role="menuitem">
                            <i class="bi bi-people sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.users') ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li role="none">
                        <a class="sidebar-sublink <?= $currentPage === 'manage-alliances' ? 'active' : '' ?>" href="<?= url('manage-alliances') ?>" role="menuitem">
                            <i class="bi bi-building-gear sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.manage_alliances') ?></span>
                        </a>
                    </li>
                    <li role="none">
                        <a class="sidebar-sublink <?= $currentPage === 'manage-tasks' ? 'active' : '' ?>" href="<?= url('manage-tasks') ?>" role="menuitem">
                            <i class="bi bi-list-check sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.manage_tasks') ?></span>
                        </a>
                    </li>
                    <li role="none">
                        <a class="sidebar-sublink <?= $currentPage === 'application' ? 'active' : '' ?>" href="<?= url('application') ?>" role="menuitem">
                            <i class="bi bi-app-indicator sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.application') ?></span>
                        </a>
                    </li>
                    <li role="none">
                        <a class="sidebar-sublink <?= $currentPage === 'integrations' ? 'active' : '' ?>" href="<?= url('integrations') ?>" role="menuitem">
                            <i class="bi bi-plug sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.integrations') ?></span>
                        </a>
                    </li>
                    <?php if (canAccessModule($currentUser, 'backup')): ?>
                    <li role="none">
                        <a class="sidebar-sublink <?= $currentPage === 'snapshots' ? 'active' : '' ?>" href="<?= url('snapshots') ?>" role="menuitem">
                            <i class="bi bi-cloud-arrow-down sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.backups') ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li role="none">
                        <a class="sidebar-sublink <?= $currentPage === 'system' ? 'active' : '' ?>" href="<?= url('system') ?>" role="menuitem">
                            <i class="bi bi-cpu sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.system') ?></span>
                        </a>
                    </li>
                    <li role="none">
                        <a class="sidebar-sublink <?= $currentPage === 'activity' ? 'active' : '' ?>" href="<?= url('activity') ?>" role="menuitem">
                            <i class="bi bi-clock-history sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.activity') ?></span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- Separator -->
            <li class="sidebar-separator" role="separator"></li>

            <!-- Documentacion -->
            <li class="sidebar-item" role="none">
                <a class="sidebar-link <?= $currentPage === 'documentation' ? 'active' : '' ?>"
                   href="<?= url('documentation') ?>" role="menuitem"
                   data-tooltip="<?= __('menu.docs') ?>"
                   <?= $currentPage === 'documentation' ? 'aria-current="page"' : '' ?>>
                    <i class="bi bi-book sidebar-link-icon" aria-hidden="true"></i>
                    <span class="sidebar-link-text"><?= __('menu.docs') ?></span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Footer del sidebar -->
    <div class="sidebar-footer">
        <span class="sidebar-footer-text"><?= __('version') ?> <?= APP_VERSION ?></span>
    </div>
</aside>

<!-- Overlay para mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
