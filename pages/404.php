<?php
/**
 * Nexus 2.0 — Error 404
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');
?>

<div class="error-page">
    <div class="error-code">404</div>
    <h1 class="error-title"><?= __('errors.404_title') ?></h1>
    <p class="error-description"><?= __('errors.404_description') ?></p>
    <a href="<?= url('home') ?>" class="btn btn-primary"><?= __('errors.go_home') ?></a>
</div>
