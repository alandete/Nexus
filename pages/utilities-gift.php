<?php
/**
 * Nexus 2.0 — Utilidades: Convertir Preguntas (GIFT / QTI)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();
if (!hasPermission($currentUser, 'utilities', 'read')) {
    include BASE_PATH . '/pages/403.php'; return;
}
?>

<div class="page-header page-header-with-action">
    <div class="page-header-text">
        <h1 class="page-title"><?= __('utilities.tab_gift') ?></h1>
    </div>
    <div class="page-header-actions">
        <button type="button" class="btn-icon" id="btnGiftGuide"
                data-tooltip="<?= htmlspecialchars(__('utilities.qti_guide_title')) ?>"
                data-tooltip-position="left"
                aria-label="<?= htmlspecialchars(__('utilities.qti_guide_title')) ?>">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
        </button>
    </div>
</div>

<div class="compress-layout gift-layout">

    <!-- Izquierda: formato + opciones -->
    <div class="compress-optside card">
        <div class="card-body">

            <!-- Selector de formato -->
            <div class="form-group">
                <label class="form-label"><?= __('utilities.format_label') ?></label>
                <div class="img-method-stack" id="giftFormatStack">

                    <div class="img-method-card" data-format="gift" tabindex="0" role="radio" aria-checked="false">
                        <div class="img-method-card-header">
                            <span class="img-method-icon img-method-icon--gift">
                                <i class="bi bi-braces" aria-hidden="true"></i>
                            </span>
                            <span class="img-method-name"><?= __('utilities.format_gift') ?></span>
                        </div>
                        <p class="img-method-desc"><?= __('utilities.qti_guide_gift_1') ?></p>
                        <p class="img-method-desc"><?= __('utilities.qti_guide_gift_3') ?></p>
                    </div>

                    <div class="img-method-card" data-format="qti" tabindex="0" role="radio" aria-checked="false">
                        <div class="img-method-card-header">
                            <span class="img-method-icon img-method-icon--qti">
                                <i class="bi bi-archive-fill" aria-hidden="true"></i>
                            </span>
                            <span class="img-method-name"><?= __('utilities.format_qti') ?></span>
                        </div>
                        <p class="img-method-desc"><?= __('utilities.qti_guide_qti_intro') ?></p>
                    </div>

                </div>
            </div>

            <!-- Opciones QTI (visible solo con QTI seleccionado) -->
            <div id="giftQtiOptions" hidden>
                <div class="form-group mt-200">
                    <label class="form-label" for="giftBankName"><?= __('utilities.qti_bank_label') ?></label>
                    <input type="text" class="form-control" id="giftBankName"
                           placeholder="<?= htmlspecialchars(__('utilities.qti_bank_placeholder')) ?>">
                    <p class="form-help"><?= __('utilities.qti_bank_help') ?></p>
                </div>
                <div class="form-group mt-100">
                    <label class="checkbox-label">
                        <input type="checkbox" id="giftQtiQuiz" class="checkbox-input">
                        <span><?= __('utilities.qti_mode_quiz') ?></span>
                    </label>
                    <p class="form-help mt-050"><?= __('utilities.qti_mode_help') ?></p>
                </div>
            </div>

            <!-- Formato de texto -->
            <div class="form-group mt-200">
                <label class="form-label"><?= __('utilities.text_format_label') ?></label>
                <div class="form-group mt-050">
                    <label class="form-label text-sm" for="giftItalic"><?= __('utilities.italic_label') ?></label>
                    <textarea class="form-control" id="giftItalic" rows="3"
                              placeholder="palabra1, palabra2"></textarea>
                    <p class="form-help"><?= __('utilities.italic_help') ?></p>
                </div>
                <div class="form-group mt-100">
                    <label class="form-label text-sm" for="giftBold"><?= __('utilities.bold_label') ?></label>
                    <textarea class="form-control" id="giftBold" rows="3"
                              placeholder="palabra1, palabra2"></textarea>
                    <p class="form-help"><?= __('utilities.bold_help') ?></p>
                </div>
            </div>

        </div>
    </div>

    <!-- Derecha: zona de carga + acciones -->
    <div class="compress-dropside">

        <div class="img-drop-zone" id="giftDropZone">
            <input type="file" id="giftFileInput" accept=".docx,.xlsx,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" hidden>
            <i class="bi bi-file-earmark-text img-drop-icon" aria-hidden="true"></i>
            <p class="img-drop-title"><?= __('utilities.drop_title') ?></p>
            <p id="giftFilename" class="img-drop-subtitle">Word (.docx) · Excel (.xlsx)</p>
            <button type="button" class="btn btn-subtle btn-sm" id="btnGiftPick">
                <?= __('utilities.drop_btn') ?>
            </button>
            <p class="img-drop-help text-subtle text-sm"><?= __('utilities.upload_help') ?></p>
        </div>

        <div class="gift-template-row mt-100">
            <span class="text-subtle text-sm gift-template-note"><?= __('utilities.template_note') ?></span>
            <div class="gift-template-btns">
                <a href="includes/template_actions.php?type=docx" class="btn btn-subtle btn-sm" download>
                    <i class="bi bi-file-earmark-word" aria-hidden="true"></i>
                    <?= __('utilities.btn_template_docx') ?>
                </a>
                <a href="includes/template_actions.php?type=xlsx" class="btn btn-subtle btn-sm" download>
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i>
                    <?= __('utilities.btn_template_xlsx') ?>
                </a>
            </div>
        </div>

        <div class="compress-actions mt-200">
            <button type="button" class="btn btn-primary" id="btnGiftProcess" disabled>
                <i class="bi bi-lightning" aria-hidden="true"></i>
                <?= __('utilities.btn_process') ?>
            </button>
            <button type="button" class="btn btn-subtle" id="btnGiftClear" hidden>
                <?= __('utilities.btn_clear') ?>
            </button>
        </div>

    </div>

</div><!-- /.compress-layout -->

<!-- Informe + acciones (debajo de las columnas, visible tras procesar) -->
<div id="giftReport" class="gift-report mt-200" hidden>
    <div class="gift-report-items" id="giftReportItems"></div>
    <div class="gift-report-actions">
        <button type="button" class="btn btn-subtle btn-sm" id="btnGiftCopy" hidden>
            <i class="bi bi-clipboard" aria-hidden="true"></i>
            <?= __('utilities.btn_copy') ?>
        </button>
        <button type="button" class="btn btn-subtle btn-sm" id="btnGiftDownload">
            <i class="bi bi-download" aria-hidden="true"></i>
            <?= __('utilities.btn_download') ?>
        </button>
        <button type="button" class="btn btn-subtle btn-sm" id="btnGiftPreview" hidden>
            <i class="bi bi-eye" aria-hidden="true"></i>
            <?= __('utilities.btn_preview') ?>
        </button>
    </div>
</div>

<!-- Resultado -->
<div id="giftResults" class="mt-200" hidden>
    <div class="card">
        <div class="card-header">
            <h3 class="card-title" id="giftResultTitle"><?= __('utilities.result_title_gift') ?></h3>
        </div>

        <div class="card-body">
            <p id="giftResError" class="text-danger text-sm" hidden></p>

            <!-- GIFT output -->
            <div id="giftResGift" hidden>
                <textarea id="giftOutput" class="gift-output" readonly spellcheck="false" rows="14"></textarea>
            </div>

            <!-- QTI output -->
            <div id="giftResQti" hidden>
                <p class="text-subtle text-sm"><?= __('utilities.qti_guide_qti_intro') ?></p>
            </div>
        </div>

    </div>
</div>

<!-- ── Slide panel: Guía de formatos ──────────────────────────────────────── -->
<div class="slide-panel" id="guideGiftFormats" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="slide-panel-header">
        <h2 class="slide-panel-title"><?= __('utilities.qti_guide_title') ?></h2>
        <button class="slide-panel-close btn-icon" type="button" aria-label="<?= __('a11y.close_panel') ?>">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <div class="slide-panel-body">
        <p><?= __('utilities.qti_guide_intro') ?></p>

        <h3 class="text-sm mt-300"><?= __('utilities.qti_guide_gift_title') ?></h3>
        <ol class="guide-steps mt-050">
            <li><?= __('utilities.qti_guide_gift_1') ?></li>
            <li><?= __('utilities.qti_guide_gift_2') ?></li>
            <li><?= __('utilities.qti_guide_gift_3') ?></li>
        </ol>

        <h3 class="text-sm mt-300"><?= __('utilities.qti_guide_qti_title') ?></h3>
        <p class="text-sm text-subtle mt-050"><?= __('utilities.qti_guide_qti_intro') ?></p>

        <h4 class="text-sm mt-200"><?= __('utilities.qti_guide_bank_title') ?></h4>
        <ol class="guide-steps mt-050">
            <li><?= __('utilities.qti_guide_bank_1') ?></li>
            <li><?= __('utilities.qti_guide_bank_2') ?></li>
            <li><?= __('utilities.qti_guide_bank_3') ?></li>
        </ol>

        <h4 class="text-sm mt-200"><?= __('utilities.qti_guide_quiz_title') ?></h4>
        <ol class="guide-steps mt-050">
            <li><?= __('utilities.qti_guide_quiz_1') ?></li>
            <li><?= __('utilities.qti_guide_quiz_2') ?></li>
        </ol>

        <h3 class="text-sm mt-300"><?= __('utilities.qti_guide_import_title') ?></h3>
        <ol class="guide-steps mt-050">
            <li><?= __('utilities.qti_guide_import_1') ?></li>
            <li><?= __('utilities.qti_guide_import_2') ?></li>
            <li><?= __('utilities.qti_guide_import_3') ?></li>
            <li><?= __('utilities.qti_guide_import_4') ?></li>
        </ol>

        <div class="callout callout-warning mt-200">
            <p class="text-sm"><?= __('utilities.qti_guide_warning') ?></p>
        </div>
    </div>
</div>

<!-- ── Slide panel: Alertas de procesamiento ──────────────────────────────── -->
<div class="slide-panel" id="guideGiftWarnings" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="slide-panel-header">
        <h2 class="slide-panel-title">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            Alertas de procesamiento
        </h2>
        <button class="slide-panel-close btn-icon" type="button" aria-label="<?= __('a11y.close_panel') ?>">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <div class="slide-panel-body" id="giftWarningsBody"></div>
</div>

<!-- ── Slide panel: Previsualización de preguntas ─────────────────────────── -->
<div class="slide-panel slide-panel--wide" id="guideGiftPreview" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="slide-panel-header">
        <h2 class="slide-panel-title"><?= __('utilities.btn_preview') ?></h2>
        <button class="slide-panel-close btn-icon" type="button" aria-label="<?= __('a11y.close_panel') ?>">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <div class="slide-panel-body" id="giftPreviewBody"></div>
</div>

<div class="slide-panel-overlay" id="slidePanelOverlay"></div>
