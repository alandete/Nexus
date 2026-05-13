<?php
/**
 * Nexus 2.0 — Utilidades: Corrector Rise
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();
if (!hasPermission($currentUser, 'utilities', 'read')) {
    include BASE_PATH . '/pages/403.php'; return;
}
$canWrite = hasPermission($currentUser, 'utilities', 'write');
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= __('utilities.rise_title') ?></h1>
    </div>
</div>

<div class="compress-layout rise-layout">

    <!-- Izquierda: información del módulo -->
    <div class="compress-optside card">
        <div class="card-body">

            <p class="form-label"><?= __('utilities.rise_section_what') ?></p>
            <ol class="guide-steps mt-050">
                <li><?= __('utilities.rise_info_1') ?></li>
                <li><?= __('utilities.rise_info_2') ?></li>
                <li><?= __('utilities.rise_info_3') ?></li>
            </ol>

            <div class="rise-info-section">
                <p class="form-label"><?= __('utilities.rise_section_affects') ?></p>
                <p class="text-sm text-subtle"><?= __('utilities.rise_info_warning') ?></p>
            </div>

            <div class="rise-info-section">
                <p class="form-label"><?= __('utilities.rise_section_delivers') ?></p>
                <p class="text-sm text-subtle"><?= __('utilities.rise_info_scope') ?></p>
            </div>

            <div class="callout callout-info mt-200">
                <p class="text-sm"><?= __('utilities.rise_impact_note') ?></p>
            </div>

        </div>
    </div>

    <!-- Derecha: zona de carga -->
    <div class="compress-dropside">
        <div class="img-drop-zone" id="riseDropZone">
            <input type="file" id="riseFileInput" accept=".zip,application/zip" hidden>
            <i class="bi bi-file-zip img-drop-icon" aria-hidden="true"></i>
            <p class="img-drop-title"><?= __('utilities.rise_drop_title') ?></p>
            <p class="img-drop-subtitle"><?= __('utilities.rise_drop_subtitle') ?></p>
            <button type="button" class="btn btn-subtle btn-sm" id="btnRisePick">
                <?= __('utilities.rise_drop_btn') ?>
            </button>
            <p class="img-drop-help text-subtle text-sm"><?= __('utilities.rise_drop_help') ?></p>
        </div>
        <div class="compress-actions mt-200">
            <button type="button" class="btn btn-primary" id="btnRisePatch" disabled>
                <i class="bi bi-file-earmark-code" aria-hidden="true"></i>
                <?= __('utilities.rise_btn_patch') ?>
            </button>
            <button type="button" class="btn btn-subtle" id="btnRiseClear" hidden>
                <?= __('utilities.img_btn_clear') ?>
            </button>
        </div>
    </div>

</div><!-- /.compress-layout -->

<!-- Resultado -->
<div id="riseResult" class="mt-200" hidden>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= __('utilities.rise_result_title') ?></h3>
        </div>
        <div class="card-body">
            <div id="riseResultContent"></div>
        </div>
    </div>
</div>
