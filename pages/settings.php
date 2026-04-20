<?php
/**
 * Nexus 2.0 — Ajustes (Overview)
 * Dashboard de ajustes con accesos directos a cada sub-modulo
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();

$settingsSections = [];

if (canAccessModule($currentUser, 'users')) {
    $settingsSections[] = [
        'url'   => url('users'),
        'icon'  => 'bi-people',
        'title' => __('menu.users'),
        'desc'  => __('settings_overview.users_desc'),
    ];
}

$settingsSections[] = [
    'url'   => url('manage-alliances'),
    'icon'  => 'bi-building-gear',
    'title' => __('menu.manage_alliances'),
    'desc'  => __('settings_overview.alliances_desc'),
];

$settingsSections[] = [
    'url'   => url('manage-tasks'),
    'icon'  => 'bi-list-check',
    'title' => __('menu.manage_tasks'),
    'desc'  => __('settings_overview.manage_tasks_desc'),
];

$settingsSections[] = [
    'url'   => url('application'),
    'icon'  => 'bi-app-indicator',
    'title' => __('menu.application'),
    'desc'  => __('settings_overview.application_desc'),
];

$settingsSections[] = [
    'url'   => url('integrations'),
    'icon'  => 'bi-plug',
    'title' => __('menu.integrations'),
    'desc'  => __('settings_overview.integrations_desc'),
];

if (canAccessModule($currentUser, 'backup')) {
    $settingsSections[] = [
        'url'   => url('snapshots'),
        'icon'  => 'bi-cloud-arrow-down',
        'title' => __('menu.backups'),
        'desc'  => __('settings_overview.backups_desc'),
    ];
}

$settingsSections[] = [
    'url'   => url('system'),
    'icon'  => 'bi-cpu',
    'title' => __('menu.system'),
    'desc'  => __('settings_overview.system_desc'),
];

$settingsSections[] = [
    'url'   => url('activity'),
    'icon'  => 'bi-clock-history',
    'title' => __('menu.activity'),
    'desc'  => __('settings_overview.activity_desc'),
];
?>

<div class="page-header">
    <h1 class="page-title"><?= __('menu.settings') ?></h1>
    <p class="page-description"><?= __('settings_overview.subtitle') ?></p>
</div>

<div class="settings-overview-grid">
    <?php foreach ($settingsSections as $section): ?>
    <a href="<?= $section['url'] ?>" class="settings-overview-card">
        <div class="settings-overview-icon">
            <i class="bi <?= $section['icon'] ?>" aria-hidden="true"></i>
        </div>
        <div class="settings-overview-body">
            <h2 class="settings-overview-title"><?= $section['title'] ?></h2>
            <p class="settings-overview-desc"><?= $section['desc'] ?></p>
        </div>
        <i class="bi bi-chevron-right settings-overview-chevron" aria-hidden="true"></i>
    </a>
    <?php endforeach; ?>
</div>
