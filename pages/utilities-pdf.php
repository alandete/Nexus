<?php
/**
 * Nexus 2.0 — Utilidades: Optimizar PDF
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();
if (!hasPermission($currentUser, 'utilities', 'read')) {
    include BASE_PATH . '/pages/403.php'; return;
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= __('utilities.tab_pdf_optimizer') ?></h1>
    </div>
</div>

<div class="compress-layout">

    <!-- Izquierda: método de optimización -->
    <div class="compress-optside card">
        <div class="card-body">
            <div class="form-group">
                <label class="form-label"><?= __('utilities.pdf_step1_label') ?></label>
                <div class="img-method-stack">

                    <!-- Ghostscript -->
                    <div class="img-method-card" data-method="ghostscript" tabindex="0" role="radio" aria-checked="false">
                        <div class="img-method-card-header">
                            <span class="img-method-icon img-method-icon--gs">
                                <i class="bi bi-cpu-fill" aria-hidden="true"></i>
                            </span>
                            <span class="img-method-name"><?= __('utilities.pdf_method_gs_name') ?></span>
                            <span class="lozenge lozenge-default img-method-badge">...</span>
                        </div>
                        <ul class="img-method-pros">
                            <li><?= __('utilities.pdf_method_gs_pro1') ?></li>
                            <li><?= __('utilities.pdf_method_gs_pro2') ?></li>
                            <li><?= __('utilities.pdf_method_gs_con1') ?></li>
                        </ul>
                        <p class="img-method-unavailable" hidden><?= __('utilities.pdf_method_gs_unavailable') ?></p>
                        <button class="img-method-guide btn-icon" type="button" data-guide="ghostscript"
                                data-tooltip="<?= htmlspecialchars(__('utilities.pdf_guide_gs_title')) ?>"
                                data-tooltip-position="top"
                                aria-label="<?= htmlspecialchars(__('utilities.pdf_guide_gs_title')) ?>">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                        </button>
                    </div>

                    <!-- API iLovePDF -->
                    <div class="img-method-card" data-method="api" tabindex="0" role="radio" aria-checked="false">
                        <div class="img-method-card-header">
                            <span class="img-method-icon img-method-icon--api">
                                <i class="bi bi-cloud-upload-fill" aria-hidden="true"></i>
                            </span>
                            <span class="img-method-name"><?= __('utilities.pdf_method_api_name') ?></span>
                            <span class="lozenge lozenge-default img-method-badge">...</span>
                        </div>
                        <ul class="img-method-pros">
                            <li><?= __('utilities.pdf_method_api_pro1') ?></li>
                            <li><?= __('utilities.pdf_method_api_pro2') ?></li>
                            <li><?= __('utilities.pdf_method_api_con2') ?></li>
                        </ul>
                        <p class="img-method-unavailable" hidden><?= __('utilities.pdf_method_api_unavailable') ?></p>
                        <button class="img-method-guide btn-icon" type="button" data-guide="api"
                                data-tooltip="<?= htmlspecialchars(__('utilities.pdf_guide_api_title')) ?>"
                                data-tooltip-position="top"
                                aria-label="<?= htmlspecialchars(__('utilities.pdf_guide_api_title')) ?>">
                            <i class="bi bi-info-circle" aria-hidden="true"></i>
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Derecha: zona de carga + acciones -->
    <div class="compress-dropside">
        <div class="img-drop-zone" id="pdfDropZone">
            <input type="file" id="pdfFileInput" accept="application/pdf,.pdf" hidden>
            <i class="bi bi-file-earmark-pdf img-drop-icon" aria-hidden="true"></i>
            <p class="img-drop-title"><?= __('utilities.pdf_drop_title') ?></p>
            <p class="img-drop-subtitle"><?= __('utilities.pdf_drop_subtitle') ?></p>
            <button type="button" class="btn btn-subtle btn-sm" id="btnPdfPick">
                <?= __('utilities.pdf_drop_btn') ?>
            </button>
            <p class="img-drop-help text-subtle text-sm"><?= __('utilities.pdf_upload_help') ?></p>
        </div>
        <div class="compress-actions mt-200">
            <button type="button" class="btn btn-primary" id="btnPdfProcess" disabled>
                <i class="bi bi-lightning" aria-hidden="true"></i>
                <?= __('utilities.pdf_btn_optimize') ?>
            </button>
            <button type="button" class="btn btn-subtle" id="btnPdfClear" hidden>
                <?= __('utilities.img_btn_clear') ?>
            </button>
        </div>
    </div>

</div><!-- /.compress-layout -->

<!-- Resultados -->
<div id="pdfResults" class="img-results mt-200" hidden>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title"><?= __('utilities.img_results_title') ?></h3>
        </div>
        <div class="card-body p-0">
            <p id="pdfResError" class="text-danger text-sm" style="padding: var(--ds-space-200)" hidden></p>
            <table class="data-table" id="pdfResSuccess" hidden>
                <thead>
                    <tr>
                        <th><?= __('utilities.img_col_file') ?></th>
                        <th><?= __('utilities.pdf_result_original') ?></th>
                        <th><?= __('utilities.pdf_result_optimized') ?></th>
                        <th><?= __('utilities.img_col_savings') ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-sm" id="pdfResFilename">—</td>
                        <td class="text-sm" id="pdfResOriginal">—</td>
                        <td class="text-sm" id="pdfResOptimized">—</td>
                        <td class="text-sm" id="pdfResSavings">—</td>
                        <td>
                            <button type="button" class="btn-dl" id="btnPdfDownload" aria-label="<?= __('utilities.pdf_btn_download') ?>">
                                <i class="bi bi-download" aria-hidden="true"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ── Slide panel: Guía Ghostscript ─────────────────────────────────────── -->
<div class="slide-panel" id="guidePdfGs" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="slide-panel-header">
        <h2 class="slide-panel-title"><?= __('utilities.pdf_guide_gs_title') ?></h2>
        <button class="slide-panel-close btn-icon" type="button" aria-label="<?= __('a11y.close_panel') ?>">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <div class="slide-panel-body">
        <p><?= __('utilities.pdf_guide_gs_intro') ?></p>
        <ol class="guide-steps">
            <li><?= __('utilities.pdf_guide_gs_step1') ?> <a href="https://www.ghostscript.com/releases/gsdnld.html" target="_blank" rel="noopener noreferrer">ghostscript.com</a></li>
            <li><?= __('utilities.pdf_guide_gs_step2') ?></li>
            <li><?= __('utilities.pdf_guide_gs_step3') ?></li>
            <li><?= __('utilities.pdf_guide_gs_step4') ?></li>
            <li><?= __('utilities.pdf_guide_gs_step5') ?></li>
        </ol>
        <div class="callout callout-warning mt-200">
            <p class="text-sm"><?= __('utilities.pdf_guide_gs_note') ?></p>
        </div>
        <h3 class="mt-300 text-sm"><?= __('utilities.pdf_guide_gs_cli_title') ?></h3>
        <p class="text-sm text-subtle mt-050"><?= __('utilities.pdf_guide_gs_cli_intro') ?></p>
        <p class="text-sm mt-100"><?= __('utilities.pdf_guide_gs_cli_winget') ?></p>
        <pre class="code-block mt-050"><code>winget install ArtifexSoftware.GhostScript</code></pre>
        <p class="text-sm mt-100"><?= __('utilities.pdf_guide_gs_cli_choco') ?></p>
        <pre class="code-block mt-050"><code>choco install ghostscript</code></pre>
        <p class="text-sm mt-100"><?= __('utilities.pdf_guide_gs_cli_scoop') ?></p>
        <pre class="code-block mt-050"><code>scoop install ghostscript</code></pre>
        <div class="callout callout-info mt-200">
            <p class="text-sm"><?= __('utilities.pdf_guide_gs_cli_note') ?></p>
        </div>
    </div>
</div>

<!-- ── Slide panel: Guía API ─────────────────────────────────────────────── -->
<div class="slide-panel" id="guidePdfApi" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="slide-panel-header">
        <h2 class="slide-panel-title"><?= __('utilities.pdf_guide_api_title') ?></h2>
        <button class="slide-panel-close btn-icon" type="button" aria-label="<?= __('a11y.close_panel') ?>">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <div class="slide-panel-body">
        <p><?= __('utilities.pdf_guide_api_intro') ?></p>

        <!-- Cómo funciona -->
        <h3 class="text-sm mt-300"><?= __('utilities.pdf_guide_api_how_title') ?></h3>
        <ol class="guide-steps mt-050">
            <li><?= __('utilities.pdf_guide_api_how_1') ?></li>
            <li><?= __('utilities.pdf_guide_api_how_2') ?></li>
            <li><?= __('utilities.pdf_guide_api_how_3') ?></li>
        </ol>

        <!-- Características -->
        <h3 class="text-sm mt-300"><?= __('utilities.pdf_guide_api_features_title') ?></h3>
        <table class="guide-feature-table">
            <tbody>
                <tr>
                    <td><?= __('utilities.pdf_guide_api_feat_compression') ?></td>
                    <td><?= __('utilities.pdf_guide_api_feat_comp_val') ?></td>
                </tr>
                <tr>
                    <td><?= __('utilities.pdf_guide_api_feat_limit') ?></td>
                    <td><?= __('utilities.pdf_guide_api_feat_limit_val') ?></td>
                </tr>
                <tr>
                    <td><?= __('utilities.pdf_guide_api_feat_size') ?></td>
                    <td><?= __('utilities.pdf_guide_api_feat_size_val') ?></td>
                </tr>
                <tr>
                    <td><?= __('utilities.pdf_guide_api_feat_install') ?></td>
                    <td><?= __('utilities.pdf_guide_api_feat_install_val') ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Privacidad -->
        <h3 class="text-sm mt-300"><?= __('utilities.pdf_guide_api_privacy_title') ?></h3>
        <div class="callout callout-warning mt-100">
            <p class="text-sm"><?= __('utilities.pdf_guide_api_privacy_note') ?></p>
        </div>

        <!-- Cómo activar -->
        <h3 class="text-sm mt-300"><?= __('utilities.pdf_guide_api_activate_title') ?></h3>
        <ol class="guide-steps mt-050">
            <li><?= __('utilities.pdf_guide_api_activate_1') ?></li>
            <li><?= __('utilities.pdf_guide_api_activate_2') ?></li>
            <li><?= __('utilities.pdf_guide_api_activate_3') ?>
                <a href="<?= url('integrations') ?>"><?= __('utilities.pdf_guide_api_settings_path') ?></a>.
            </li>
        </ol>

        <div class="callout callout-info mt-200">
            <p class="text-sm"><?= __('utilities.pdf_guide_api_admin_note') ?></p>
        </div>
    </div>
</div>

<div class="slide-panel-overlay" id="slidePanelOverlay"></div>
