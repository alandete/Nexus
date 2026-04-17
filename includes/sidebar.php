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
$settingsPages = ['settings'];
$isSettingsActive = in_array($currentPage, $settingsPages);
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
                        data-tooltip="<?= __('menu.alliances') ?>"
                        role="menuitem">
                    <i class="bi bi-building sidebar-link-icon" aria-hidden="true"></i>
                    <span class="sidebar-link-text"><?= __('menu.alliances') ?></span>
                    <i class="bi bi-chevron-down sidebar-chevron" aria-hidden="true"></i>
                </button>
                <ul class="sidebar-submenu <?= $currentPage === 'alliances' ? 'show' : '' ?>" role="menu">
                    <li role="none">
                        <a class="sidebar-sublink" href="<?= url('alliances') ?>#unis" role="menuitem">
                            <span class="sidebar-link-text">UNIS</span>
                        </a>
                    </li>
                    <li role="none">
                        <a class="sidebar-sublink" href="<?= url('alliances') ?>#unab" role="menuitem">
                            <span class="sidebar-link-text">UNAB</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Utilidades (con sub-items) -->
            <li class="sidebar-item sidebar-group" role="none">
                <button class="sidebar-link sidebar-group-toggle <?= $currentPage === 'utilities' ? 'active' : '' ?>"
                        type="button" aria-expanded="<?= $currentPage === 'utilities' ? 'true' : 'false' ?>"
                        data-tooltip="<?= __('menu.utilities') ?>"
                        role="menuitem">
                    <i class="bi bi-tools sidebar-link-icon" aria-hidden="true"></i>
                    <span class="sidebar-link-text"><?= __('menu.utilities') ?></span>
                    <i class="bi bi-chevron-down sidebar-chevron" aria-hidden="true"></i>
                </button>
                <ul class="sidebar-submenu <?= $currentPage === 'utilities' ? 'show' : '' ?>" role="menu">
                    <li role="none">
                        <a class="sidebar-sublink" href="<?= url('utilities') ?>#preguntas" role="menuitem">
                            <i class="bi bi-file-earmark-text sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.questions') ?></span>
                        </a>
                    </li>
                    <li role="none">
                        <a class="sidebar-sublink" href="<?= url('utilities') ?>#pdf" role="menuitem">
                            <i class="bi bi-file-earmark-pdf sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.pdf_optimizer') ?></span>
                        </a>
                    </li>
                    <li role="none">
                        <a class="sidebar-sublink" href="<?= url('utilities') ?>#imagenes" role="menuitem">
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
                        data-tooltip="<?= __('menu.settings') ?>"
                        role="menuitem">
                    <i class="bi bi-gear sidebar-link-icon" aria-hidden="true"></i>
                    <span class="sidebar-link-text"><?= __('menu.settings') ?></span>
                    <i class="bi bi-chevron-down sidebar-chevron" aria-hidden="true"></i>
                </button>
                <ul class="sidebar-submenu <?= $isSettingsActive ? 'show' : '' ?>" role="menu">
                    <?php if (canAccessModule($currentUser, 'users')): ?>
                    <li role="none">
                        <a class="sidebar-sublink" href="<?= url('settings') ?>#usuarios" role="menuitem">
                            <i class="bi bi-people sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.users') ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li role="none">
                        <a class="sidebar-sublink" href="<?= url('settings') ?>#alianzas" role="menuitem">
                            <i class="bi bi-building-gear sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.manage_alliances') ?></span>
                        </a>
                    </li>
                    <li role="none">
                        <a class="sidebar-sublink" href="<?= url('settings') ?>#proyecto" role="menuitem">
                            <i class="bi bi-info-circle sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.project') ?></span>
                        </a>
                    </li>
                    <li role="none">
                        <a class="sidebar-sublink" href="<?= url('settings') ?>#integraciones" role="menuitem">
                            <i class="bi bi-plug sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.integrations') ?></span>
                        </a>
                    </li>
                    <?php if (canAccessModule($currentUser, 'backup')): ?>
                    <li role="none">
                        <a class="sidebar-sublink" href="<?= url('settings') ?>#backups" role="menuitem">
                            <i class="bi bi-cloud-arrow-down sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.backups') ?></span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li role="none">
                        <a class="sidebar-sublink" href="<?= url('settings') ?>#sistema" role="menuitem">
                            <i class="bi bi-cpu sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.system') ?></span>
                        </a>
                    </li>
                    <li role="none">
                        <a class="sidebar-sublink" href="<?= url('settings') ?>#actividad" role="menuitem">
                            <i class="bi bi-clock-history sidebar-link-icon" aria-hidden="true"></i>
                            <span class="sidebar-link-text"><?= __('menu.activity') ?></span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <!-- Footer del sidebar -->
    <div class="sidebar-footer">
        <span class="sidebar-footer-text"><?= __('version') ?> <?= APP_VERSION ?></span>
    </div>
</aside>

<!-- Overlay para mobile -->
<div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>
