<?php
/**
 * Nexus 2.0 — Modal de Confirmacion (reutilizable)
 * Dialogo accesible para confirmar acciones destructivas
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');
?>

<div class="confirm-modal-overlay" id="confirmModalOverlay" aria-hidden="true"></div>

<div class="confirm-modal" id="confirmModal" role="alertdialog" aria-modal="true"
     aria-labelledby="confirmModalTitle" aria-describedby="confirmModalMessage" aria-hidden="true">
    <div class="confirm-modal-icon" id="confirmModalIcon" aria-hidden="true">
        <i class="bi bi-exclamation-triangle"></i>
    </div>
    <h2 class="confirm-modal-title" id="confirmModalTitle">Confirmar</h2>
    <p class="confirm-modal-message" id="confirmModalMessage"></p>
    <div class="confirm-modal-actions">
        <button type="button" class="btn btn-subtle" id="confirmModalCancel">
            <?= __('common.cancel') ?>
        </button>
        <button type="button" class="btn btn-danger" id="confirmModalAccept">
            <?= __('common.confirm') ?>
        </button>
    </div>
</div>
