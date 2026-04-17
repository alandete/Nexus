<?php
/**
 * Nexus 2.0 — Footer
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');
?>

<footer class="footer">
    &copy; <?= date('Y') ?> <?= htmlspecialchars($projectInfo['app_name']) ?><?= !empty($projectInfo['company_name']) ? ' &mdash; ' . htmlspecialchars($projectInfo['company_name']) : '' ?>
    &middot; <?= __('version') ?> <?= APP_VERSION ?>
</footer>
