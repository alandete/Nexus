<?php
/**
 * Nexus 2.0 — Utilidades: Optimizar Imágenes
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
        <h1 class="page-title"><?= __('utilities.tab_image_optimizer') ?></h1>
    </div>
</div>

<!-- Sub-tabs -->
<div class="img-subtab-bar" role="tablist">
    <button class="img-subtab active" data-imgtab="compress" role="tab" type="button">
        <i class="bi bi-file-earmark-zip" aria-hidden="true"></i>
        <?= __('utilities.img_tab_compress') ?>
    </button>
    <button class="img-subtab" data-imgtab="resize" role="tab" type="button">
        <i class="bi bi-aspect-ratio" aria-hidden="true"></i>
        <?= __('utilities.img_tab_resize') ?>
    </button>
    <button class="img-subtab" data-imgtab="convert" role="tab" type="button">
        <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
        <?= __('utilities.img_tab_convert') ?>
    </button>
</div>

<!-- ── Panel: Comprimir ───────────────────────────────────────────────── -->
<div id="panelCompress" class="img-panel">

    <div class="compress-layout">

        <!-- Izquierda: opciones (método + calidad) -->
        <div class="compress-optside card">
            <div class="card-body">

                <!-- Método -->
                <div class="form-group">
                    <label class="form-label"><?= __('utilities.img_method_label') ?></label>
                    <div class="img-method-stack">

                        <div class="img-method-card" data-method="imagick" tabindex="0" role="radio" aria-checked="false">
                            <div class="img-method-card-header">
                                <span class="img-method-icon img-method-icon--imagick">
                                    <i class="bi bi-cpu-fill" aria-hidden="true"></i>
                                </span>
                                <span class="img-method-name"><?= __('utilities.img_method_im_name') ?></span>
                                <span class="lozenge lozenge-default img-method-badge">...</span>
                            </div>
                            <p class="img-method-desc"><?= __('utilities.img_method_im_desc') ?></p>
                            <p class="img-method-unavailable" hidden><?= __('utilities.img_method_im_unavailable') ?></p>
                            <button class="img-method-guide btn-icon" type="button" data-guide="imagick"
                                    data-tooltip="<?= htmlspecialchars(__('utilities.img_guide_im_title')) ?>"
                                    data-tooltip-position="top"
                                    aria-label="<?= htmlspecialchars(__('utilities.img_guide_im_title')) ?>">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                            </button>
                        </div>

                        <div class="img-method-card" data-method="api" tabindex="0" role="radio" aria-checked="false">
                            <div class="img-method-card-header">
                                <span class="img-method-icon img-method-icon--api">
                                    <i class="bi bi-cloud-upload-fill" aria-hidden="true"></i>
                                </span>
                                <span class="img-method-name"><?= __('utilities.img_method_api_name') ?></span>
                                <span class="lozenge lozenge-default img-method-badge">...</span>
                            </div>
                            <p class="img-method-desc"><?= __('utilities.img_method_api_desc') ?></p>
                            <p class="img-method-unavailable" hidden><?= __('utilities.img_method_api_unavailable') ?></p>
                            <button class="img-method-guide btn-icon" type="button" data-guide="api"
                                    data-tooltip="<?= htmlspecialchars(__('utilities.img_guide_api_title')) ?>"
                                    data-tooltip-position="top"
                                    aria-label="<?= htmlspecialchars(__('utilities.img_guide_api_title')) ?>">
                                <i class="bi bi-info-circle" aria-hidden="true"></i>
                            </button>
                        </div>

                    </div><!-- /.img-method-stack -->
                </div>

                <!-- Calidad -->
                <div class="form-group mt-200">
                    <label class="form-label" for="imgQuality"><?= __('utilities.img_quality_label') ?></label>
                    <select class="form-control" id="imgQuality">
                        <option value="high"><?= __('utilities.img_quality_high') ?></option>
                        <option value="medium" selected><?= __('utilities.img_quality_medium') ?></option>
                        <option value="low"><?= __('utilities.img_quality_low') ?></option>
                    </select>
                </div>

            </div>
        </div><!-- /.compress-optside -->

        <!-- Derecha: zona de carga + cola + acciones -->
        <div class="compress-dropside">
            <div class="img-drop-zone" id="imgCompressZone">
                <input type="file" id="imgCompressInput" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
                <i class="bi bi-cloud-upload img-drop-icon" aria-hidden="true"></i>
                <p class="img-drop-title"><?= __('utilities.img_drop_title') ?></p>
                <p class="img-drop-subtitle"><?= __('utilities.img_drop_subtitle') ?></p>
                <button type="button" class="btn btn-subtle btn-sm" id="btnImgCompressPick">
                    <?= __('utilities.img_drop_btn') ?>
                </button>
                <p class="img-drop-help text-subtle text-sm"><?= __('utilities.img_upload_help') ?></p>
            </div>
            <p id="imgCompressQueue" class="img-queue-summary text-subtle text-sm mt-200" hidden></p>
            <div class="compress-actions mt-200">
                <button type="button" class="btn btn-primary" id="btnImgCompress" disabled>
                    <i class="bi bi-lightning" aria-hidden="true"></i>
                    <?= __('utilities.img_btn_process') ?>
                </button>
                <button type="button" class="btn btn-subtle" id="btnImgCompressClear" hidden>
                    <?= __('utilities.img_btn_clear') ?>
                </button>
            </div>
        </div>

    </div><!-- /.compress-layout -->

    <!-- Resultados comprimir -->
    <div id="imgCompressResults" class="img-results mt-200" hidden>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= __('utilities.img_results_title') ?></h3>
            </div>
            <div class="card-body p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?= __('utilities.img_col_file') ?></th>
                            <th><?= __('utilities.img_col_original') ?></th>
                            <th><?= __('utilities.img_col_result') ?></th>
                            <th><?= __('utilities.img_col_savings') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="imgCompressResultsBody"></tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /#panelCompress -->

<!-- ── Panel: Redimensionar ──────────────────────────────────────────── -->
<div id="panelResize" class="img-panel" hidden>

    <div class="resize-layout">

        <!-- Columna 1: Drop zone -->
        <div class="resize-col">
            <div class="img-drop-zone" id="imgResizeZone">
                <input type="file" id="imgResizeInput" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
                <i class="bi bi-cloud-upload img-drop-icon" aria-hidden="true"></i>
                <p class="img-drop-title"><?= __('utilities.img_resize_drop_title') ?></p>
                <p class="img-drop-subtitle"><?= __('utilities.img_resize_drop_subtitle') ?></p>
                <button type="button" class="btn btn-subtle btn-sm" id="btnImgResizePick">
                    <?= __('utilities.img_resize_drop_btn') ?>
                </button>
                <p class="img-drop-help text-subtle text-sm"><?= __('utilities.img_resize_upload_help') ?></p>
            </div>
        </div>

        <!-- Columna 2: Preview -->
        <div class="resize-col card">
            <div class="card-body resize-preview-body">
                <div class="resize-locked-placeholder" id="resizePreviewPlaceholder">
                    <i class="bi bi-image" aria-hidden="true"></i>
                    <span class="text-subtle text-sm"><?= __('utilities.img_resize_preview_locked') ?></span>
                </div>
                <div id="resizePreviewContent" hidden>
                    <img id="resizePreviewImg" class="resize-preview-img" alt="Preview" src="">
                    <p class="text-subtle text-sm text-center mt-100" id="resizeOrigDims"></p>
                </div>
            </div>
        </div>

        <!-- Columna 3: Opciones -->
        <div class="resize-col card">
            <div class="card-body resize-options-body">
                <div class="resize-locked-placeholder" id="resizeOptionsPlaceholder">
                    <i class="bi bi-sliders" aria-hidden="true"></i>
                    <span class="text-subtle text-sm"><?= __('utilities.img_resize_options_locked') ?></span>
                </div>

                <div id="resizeOptionsContent" hidden>
                <div class="d-flex flex-column" style="gap:var(--ds-space-200);height:100%">
                    <!-- Modo -->
                    <div class="form-group">
                        <label class="form-label"><?= __('utilities.img_resize_mode_label') ?></label>
                        <div class="btn-toggle-group">
                            <button type="button" class="btn btn-subtle btn-sm active" data-resize-mode="percent">
                                <?= __('utilities.img_resize_mode_percent') ?>
                            </button>
                            <button type="button" class="btn btn-subtle btn-sm" data-resize-mode="custom">
                                <?= __('utilities.img_resize_mode_custom') ?>
                            </button>
                        </div>
                    </div>

                    <!-- Modo porcentaje -->
                    <div id="resizeModePercent" class="resize-mode-panel">
                        <p class="form-help text-sm text-subtle"><?= __('utilities.img_resize_pct_help') ?></p>
                        <div class="resize-pct-options mt-050">
                            <button type="button" class="resize-pct-btn" data-pct="30">
                                <span class="resize-pct-value">30%</span>
                                <span class="resize-pct-dims" id="resizePctDims30">—</span>
                            </button>
                            <button type="button" class="resize-pct-btn active" data-pct="50">
                                <span class="resize-pct-value">50%</span>
                                <span class="resize-pct-dims" id="resizePctDims50">—</span>
                            </button>
                            <button type="button" class="resize-pct-btn" data-pct="70">
                                <span class="resize-pct-value">70%</span>
                                <span class="resize-pct-dims" id="resizePctDims70">—</span>
                            </button>
                        </div>
                    </div>

                    <!-- Modo personalizado -->
                    <div id="resizeModeCustom" class="resize-mode-panel" hidden>
                        <p class="form-help text-sm text-subtle"><?= __('utilities.img_resize_auto_note') ?></p>
                        <div class="d-flex gap-200 mt-050">
                            <div class="form-group">
                                <label class="form-label" for="imgResizeW"><?= __('utilities.img_resize_dim_width') ?></label>
                                <div class="d-flex align-items-center gap-075">
                                    <input type="number" class="form-control" id="imgResizeW" min="1" placeholder="—" style="max-width:100px">
                                    <span class="text-subtle text-sm">px</span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="imgResizeH"><?= __('utilities.img_resize_dim_height') ?></label>
                                <div class="d-flex align-items-center gap-075">
                                    <input type="number" class="form-control" id="imgResizeH" min="1" placeholder="—" style="max-width:100px">
                                    <span class="text-subtle text-sm">px</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Acciones (empujadas al fondo) -->
                    <div class="d-flex gap-100" style="margin-top:auto">
                        <button type="button" class="btn btn-primary" id="btnImgResize">
                            <i class="bi bi-aspect-ratio" aria-hidden="true"></i>
                            <?= __('utilities.img_btn_process') ?>
                        </button>
                        <button type="button" class="btn btn-subtle" id="btnImgResizeClear" hidden>
                            <?= __('utilities.img_btn_clear') ?>
                        </button>
                    </div>
                </div><!-- /.d-flex inner -->
                </div><!-- /#resizeOptionsContent -->

            </div>
        </div>

    </div><!-- /.resize-layout -->

    <!-- Resultado redimensionar -->
    <div id="imgResizeResult" class="img-results mt-200" hidden>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= __('utilities.img_results_title') ?></h3>
            </div>
            <div class="card-body p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?= __('utilities.img_col_file') ?></th>
                            <th><?= __('utilities.img_col_dimensions') ?></th>
                            <th><?= __('utilities.img_col_rename') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="imgResizeResultBody"></tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /#panelResize -->

<!-- ── Panel: Convertir ───────────────────────────────────────────────── -->
<div id="panelConvert" class="img-panel" hidden>

    <div class="compress-layout">

        <!-- Izquierda: opciones + acciones -->
        <div class="compress-optside card">
            <div class="card-body">

                <!-- Formato destino -->
                <div class="form-group">
                    <label class="form-label"><?= __('utilities.img_convert_to') ?></label>
                    <div class="btn-toggle-group">
                        <button type="button" class="btn btn-subtle btn-sm active" data-format="webp">WebP</button>
                        <button type="button" class="btn btn-subtle btn-sm" data-format="jpeg">JPEG</button>
                        <button type="button" class="btn btn-subtle btn-sm" data-format="png">PNG</button>
                        <button type="button" class="btn btn-subtle btn-sm" data-format="gif">GIF</button>
                    </div>
                </div>

                <!-- Calidad JPEG -->
                <div id="convertQualityGroup" class="form-group mt-200" hidden>
                    <label class="form-label" for="imgConvertQuality"><?= __('utilities.img_convert_quality') ?></label>
                    <div class="d-flex align-items-center gap-100">
                        <input type="number" class="form-control" id="imgConvertQuality" min="30" max="100" value="85" style="max-width:80px">
                        <span class="text-subtle text-sm">%</span>
                    </div>
                    <p class="form-help text-sm text-subtle mt-050"><?= __('utilities.img_convert_quality_help') ?></p>
                </div>

                <!-- Acciones -->
                <div class="compress-actions mt-300">
                    <button type="button" class="btn btn-primary" id="btnImgConvert" disabled>
                        <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                        <?= __('utilities.img_btn_process') ?>
                    </button>
                    <button type="button" class="btn btn-subtle" id="btnImgConvertClear" hidden>
                        <?= __('utilities.img_btn_clear') ?>
                    </button>
                </div>

            </div>
        </div>

        <!-- Derecha: zona de carga -->
        <div class="compress-dropside">
            <div class="img-drop-zone" id="imgConvertZone">
                <input type="file" id="imgConvertInput" accept="image/jpeg,image/png,image/webp,image/gif" hidden>
                <i class="bi bi-cloud-upload img-drop-icon" aria-hidden="true"></i>
                <p class="img-drop-title"><?= __('utilities.img_resize_drop_title') ?></p>
                <p class="img-drop-subtitle"><?= __('utilities.img_convert_drop_subtitle') ?></p>
                <button type="button" class="btn btn-subtle btn-sm" id="btnImgConvertPick">
                    <?= __('utilities.img_convert_drop_btn') ?>
                </button>
                <p class="img-drop-help text-subtle text-sm"><?= __('utilities.img_convert_upload_help') ?></p>
            </div>
        </div>

    </div><!-- /.compress-layout -->

    <!-- Resultado convertir -->
    <div id="imgConvertResult" class="img-results mt-200" hidden>
        <div class="card">
            <div class="card-header">
                <h3 class="card-title"><?= __('utilities.img_results_title') ?></h3>
            </div>
            <div class="card-body p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><?= __('utilities.img_col_file') ?></th>
                            <th><?= __('utilities.img_col_format') ?></th>
                            <th><?= __('utilities.img_col_rename') ?></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="imgConvertResultBody"></tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /#panelConvert -->

<!-- ── Slide panel: Guía ImageMagick ─────────────────────────────────────── -->
<div class="slide-panel" id="guideImagick" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="slide-panel-header">
        <h2 class="slide-panel-title"><?= __('utilities.img_guide_im_title') ?></h2>
        <button class="slide-panel-close btn-icon" type="button" aria-label="<?= __('a11y.close_panel') ?>">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <div class="slide-panel-body">
        <p><?= __('utilities.img_guide_im_intro') ?></p>
        <ol class="guide-steps">
            <li><?= __('utilities.img_guide_im_step1') ?></li>
            <li><?= __('utilities.img_guide_im_step2') ?></li>
            <li><?= __('utilities.img_guide_im_step3') ?></li>
            <li><?= __('utilities.img_guide_im_step4') ?></li>
            <li><?= __('utilities.img_guide_im_step5') ?></li>
        </ol>
        <div class="callout callout-warning mt-200">
            <p class="text-sm"><?= __('utilities.img_guide_im_note') ?></p>
        </div>
    </div>
</div>

<!-- ── Slide panel: Guía API ─────────────────────────────────────────────── -->
<div class="slide-panel" id="guideImgApi" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="slide-panel-header">
        <h2 class="slide-panel-title"><?= __('utilities.img_guide_api_title') ?></h2>
        <button class="slide-panel-close btn-icon" type="button" aria-label="<?= __('a11y.close_panel') ?>">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <div class="slide-panel-body">
        <p><?= __('utilities.img_guide_api_intro') ?></p>
        <p class="mt-150"><?= __('utilities.img_guide_api_settings_link') ?>
            <a href="<?= url('integrations') ?>"><?= __('utilities.img_guide_api_settings_path') ?></a>.
        </p>
    </div>
</div>

<div class="slide-panel-overlay" id="slidePanelOverlay"></div>
