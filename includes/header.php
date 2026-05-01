<?php
/**
 * Nexus 2.0 — Top Bar
 * Barra superior minima: hamburger, logo, usuario
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();
?>

<header class="topbar" id="topbar">
    <div class="topbar-inner">
        <!-- Hamburger (mobile/tablet) + sidebar toggle (desktop) -->
        <button class="topbar-toggle" type="button" id="sidebarToggle"
                aria-controls="sidebar" aria-expanded="false"
                aria-label="<?= __('header.toggle_menu') ?>"
                data-tooltip="<?= __('header.toggle_menu') ?>" data-tooltip-position="bottom">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <!-- Brand -->
        <a class="topbar-brand" href="<?= url() ?>">
            <?php if (!empty($projectInfo['logo'])): ?>
                <img src="assets/uploads/logos/<?= htmlspecialchars($projectInfo['logo']) ?>"
                     alt="<?= htmlspecialchars($projectInfo['app_name']) ?>">
            <?php else: ?>
                <i class="bi bi-hexagon-fill" aria-hidden="true"></i>
            <?php endif; ?>
            <span class="topbar-brand-name"><?= htmlspecialchars($projectInfo['app_name']) ?></span>
        </a>

        <div class="topbar-spacer"></div>

        <!-- Quick links (configurados por el admin) -->
        <nav class="topbar-quicklinks" id="topbar-quicklinks" aria-label="<?= __('header.quick_links') ?>">
            <?php foreach ($quickLinks as $qlPage):
                $qlItem = $quickLinksMeta[$qlPage] ?? null;
                if (!$qlItem) continue;
            ?>
            <a href="<?= htmlspecialchars($qlItem['href']) ?>" class="topbar-ql-item"
               data-tooltip="<?= htmlspecialchars($qlItem['label']) ?>" data-tooltip-position="bottom"
               aria-label="<?= htmlspecialchars($qlItem['label']) ?>">
                <i class="bi <?= htmlspecialchars($qlItem['icon']) ?>" aria-hidden="true"></i>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="topbar-spacer"></div>

        <!-- Usuario -->
        <div class="topbar-user">
            <div class="dropdown">
                <button class="topbar-user-btn" type="button" id="userDropdownBtn"
                        aria-expanded="false" aria-haspopup="true">
                    <?php if ($currentUser && !empty($currentUser['photo'])): ?>
                        <img src="assets/uploads/avatars/<?= htmlspecialchars($currentUser['photo']) ?>"
                             alt="<?= htmlspecialchars($currentUser['name']) ?>"
                             class="topbar-avatar">
                    <?php else: ?>
                        <span class="topbar-avatar-fallback">
                            <?= $currentUser ? strtoupper(substr($currentUser['name'], 0, 1)) : '?' ?>
                        </span>
                    <?php endif; ?>
                    <span class="topbar-user-name"><?= $currentUser ? htmlspecialchars($currentUser['name']) : __('header.user_fallback') ?></span>
                    <i class="bi bi-chevron-down topbar-chevron" aria-hidden="true"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" id="userDropdownMenu">
                    <li class="dropdown-header"><?= __('header.change_lang') ?></li>
                    <li>
                        <a class="dropdown-item <?= $lang === 'es' ? 'active' : '' ?>" href="?lang=es">
                            <i class="bi bi-translate" aria-hidden="true"></i> Español
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item <?= $lang === 'en' ? 'active' : '' ?>" href="?lang=en">
                            <i class="bi bi-translate" aria-hidden="true"></i> English
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item dropdown-item-danger" href="<?= url('logout') ?>">
                            <i class="bi bi-box-arrow-right" aria-hidden="true"></i> <?= __('header.logout_title') ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
