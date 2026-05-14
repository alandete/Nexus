<?php
/**
 * Nexus 2.0 — Error 403: Acceso denegado
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');
?>

<div class="empty-state">
    <div class="empty-state-icon">
        <i class="bi bi-shield-lock" aria-hidden="true"></i>
    </div>
    <h2 class="empty-state-title"><?= __('errors.403_title') ?></h2>
    <p class="empty-state-description"><?= __('errors.403_description') ?></p>
    <a href="<?= url('settings') ?>" class="btn btn-subtle">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
        <?= __('errors.go_back') ?>
    </a>
</div>
