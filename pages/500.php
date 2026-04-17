<?php
/**
 * Nexus 2.0 — Error 500
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');
?>

<div class="error-page">
    <div class="error-code">500</div>
    <h1 class="error-title"><?= __('errors.500_title') ?></h1>
    <p class="error-description"><?= __('errors.500_description') ?></p>
    <a href="<?= url('home') ?>" class="btn btn-primary"><?= __('errors.go_home') ?></a>
</div>
