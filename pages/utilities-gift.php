<?php
/**
 * Nexus 2.0 — Utilidades: Convertir Preguntas
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();
if (!hasPermission($currentUser, 'utilities', 'read')) {
    include BASE_PATH . '/pages/403.php'; return;
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= __('utilities.tab_gift') ?></h1>
    </div>
</div>

<div class="card">
    <div class="card-body empty-state p-400">
        <div class="empty-state-icon"><i class="bi bi-file-earmark-text" aria-hidden="true"></i></div>
        <p class="empty-state-description">Próximamente</p>
    </div>
</div>
