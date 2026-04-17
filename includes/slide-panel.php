<?php
/**
 * Nexus 2.0 — Slide Panel (Componente Reutilizable)
 * Panel deslizante desde la derecha
 * Atlassian DS: elevation.overlay
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');
?>

<div class="slide-panel-overlay" id="slidePanelOverlay" aria-hidden="true"></div>

<div class="slide-panel" id="slidePanel" role="dialog" aria-modal="true"
     aria-labelledby="slidePanelTitle" aria-hidden="true">
    <div class="slide-panel-header">
        <h2 class="slide-panel-title" id="slidePanelTitle">Panel</h2>
        <button type="button" class="slide-panel-close" id="slidePanelClose"
                aria-label="<?= __('a11y.close_panel') ?>">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>

    <div class="slide-panel-body" id="slidePanelBody"></div>

    <div class="slide-panel-footer" id="slidePanelFooter"></div>
</div>
