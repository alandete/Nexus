<?php
/**
 * Nexus 2.0 — Ajustes > Integraciones
 * Gestion de claves API (iLovePDF / iLoveIMG / Gmail)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();

if (($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    include 'pages/403.php';
    return;
}

$apiSettings = getApiSettings();

// iLovePDF
$hasEmail    = !empty($apiSettings['ilp_email']);
$hasPassword = !empty($apiSettings['ilp_password']);
$hasProject  = !empty($apiSettings['ilp_project']);
$hasPublic   = !empty($apiSettings['ilp_public_key']);
$hasSecret   = !empty($apiSettings['ilp_secret_key']);
$ilpConfigured = $hasPublic;
$emailValue    = $hasEmail   ? $apiSettings['ilp_email']   : '';
$projectValue  = $hasProject ? $apiSettings['ilp_project'] : '';

// SMTP
$smtpRaw          = file_exists(API_SETTINGS_FILE) ? (json_decode(file_get_contents(API_SETTINGS_FILE), true) ?? []) : [];
$smtpConfigured   = !empty($smtpRaw['smtp_host']) && !empty($smtpRaw['smtp_user']);
$smtpHasPass      = !empty($smtpRaw['smtp_pass']);

// Gmail
$gmailRaw        = $smtpRaw;
$gmailConfigured = !empty($gmailRaw['gmail_email']) && !empty($gmailRaw['gmail_app_password']);
$gmailEmail      = $gmailRaw['gmail_email'] ?? '';
$gmailLabel      = $gmailRaw['gmail_label'] ?? 'Nexus';
$gmailLastSync   = $gmailRaw['gmail_last_sync'] ?? null;
$gmailHasPass    = !empty($gmailRaw['gmail_app_password']);

// Ghostscript
$gsQuality = $gmailRaw['gs_quality'] ?? 'ebook';
$gsPresets = [
    'screen'   => ['label' => 'Pantalla',        'dpi' => 72,  'desc' => 'Tamaño mínimo, calidad baja. Ideal para visualización en pantalla o adjunto de email liviano.'],
    'ebook'    => ['label' => 'eBook',            'dpi' => 150, 'desc' => 'Balance óptimo entre calidad y tamaño. Recomendado para la mayoría de casos.'],
    'printer'  => ['label' => 'Impresión',        'dpi' => 300, 'desc' => 'Alta calidad para impresión general. Genera archivos más grandes.'],
    'prepress' => ['label' => 'Preprensa',        'dpi' => 300, 'desc' => 'Máxima calidad, preserva perfil de color. Para publicación profesional.'],
    'default'  => ['label' => 'Sin cambios',      'dpi' => 150, 'desc' => 'Aplica compresión mínima sin modificar imágenes. Útil para PDFs con gráficos vectoriales.'],
];
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= url('settings') ?>" class="breadcrumb-link"><?= __('menu.settings') ?></a>
    <i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>
    <span class="breadcrumb-current" aria-current="page"><?= __('menu.integrations') ?></span>
</nav>

<div class="page-header">
    <h1 class="page-title"><?= __('integrations.page_title') ?></h1>
    <p class="page-description"><?= __('integrations.page_subtitle') ?></p>
</div>

<!-- Pestañas (fuera de la card, igual que manage-tasks) -->
<div class="tabs integration-tabs" role="tablist" aria-label="<?= __('integrations.page_title') ?>">
    <button type="button" class="tab active" role="tab" id="tab-ilp" data-tab="ilp"
            aria-selected="true" aria-controls="panel-ilp">
        <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
        iLovePDF / iLoveIMG
        <?php if ($ilpConfigured): ?>
        <span class="tab-status-dot tab-status-ok" aria-hidden="true"></span>
        <?php endif; ?>
    </button>
    <button type="button" class="tab" role="tab" id="tab-gs" data-tab="gs"
            aria-selected="false" aria-controls="panel-gs">
        <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
        Ghostscript
    </button>
    <button type="button" class="tab" role="tab" id="tab-gmail" data-tab="gmail"
            aria-selected="false" aria-controls="panel-gmail">
        <i class="bi bi-envelope-at" aria-hidden="true"></i>
        Gmail
        <?php if ($gmailConfigured): ?>
        <span class="tab-status-dot tab-status-ok" aria-hidden="true"></span>
        <?php endif; ?>
    </button>
    <button type="button" class="tab" role="tab" id="tab-smtp" data-tab="smtp"
            aria-selected="false" aria-controls="panel-smtp">
        <i class="bi bi-send" aria-hidden="true"></i>
        SMTP
        <?php if ($smtpConfigured): ?>
        <span class="tab-status-dot tab-status-ok" aria-hidden="true"></span>
        <?php endif; ?>
    </button>
</div>

<!-- ============ Panel iLovePDF ============ -->
<section id="panel-ilp" class="card integration-card integration-panel" role="tabpanel" aria-labelledby="tab-ilp">

    <header class="integration-header">
        <div class="integration-header-icon">
            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
        </div>
        <div class="integration-header-info">
            <h2 class="integration-title">iLovePDF / iLoveIMG</h2>
            <p class="integration-description"><?= __('integrations.ilp_description') ?></p>
        </div>
        <div class="integration-header-status">
            <?php if ($ilpConfigured): ?>
            <span class="lozenge lozenge-success">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <?= __('integrations.status_configured') ?>
            </span>
            <?php else: ?>
            <span class="lozenge lozenge-default">
                <i class="bi bi-dash-circle" aria-hidden="true"></i>
                <?= __('integrations.status_not_configured') ?>
            </span>
            <?php endif; ?>
        </div>
    </header>

    <form id="integrationsForm" class="integration-body" novalidate>
        <input type="hidden" name="action" value="save">

        <div class="alert alert-info">
            <i class="bi bi-shield-lock alert-icon" aria-hidden="true"></i>
            <span class="alert-content"><?= __('integrations.security_note') ?></span>
        </div>

        <h3 class="integration-section-title"><?= __('integrations.section_account') ?></h3>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="fIlpEmail" class="form-label"><?= __('integrations.field_email') ?></label>
                <input type="email" id="fIlpEmail" name="ilp_email" class="form-control"
                       placeholder="correo@ejemplo.com"
                       value="<?= htmlspecialchars($emailValue) ?>"
                       autocomplete="off"
                       aria-describedby="fIlpEmailError">
                <p class="form-error" id="fIlpEmailError" aria-live="polite"></p>
            </div>

            <div class="form-group">
                <label for="fIlpProject" class="form-label"><?= __('integrations.field_project') ?></label>
                <input type="text" id="fIlpProject" name="ilp_project" class="form-control"
                       placeholder="<?= __('integrations.field_project_placeholder') ?>"
                       value="<?= htmlspecialchars($projectValue) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="fIlpPassword" class="form-label"><?= __('integrations.field_password') ?></label>
            <div class="password-field">
                <input type="password" id="fIlpPassword" name="ilp_password" class="form-control"
                       placeholder="<?= $hasPassword ? __('integrations.placeholder_keep_current') : '' ?>"
                       autocomplete="new-password">
                <button type="button" class="password-toggle" id="togglePassword"
                        aria-label="<?= __('integrations.show_password') ?>"
                        data-tooltip="<?= __('integrations.show_password') ?>">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
            </div>
            <?php if ($hasPassword): ?>
            <p class="form-helper"><?= __('integrations.password_current_help') ?></p>
            <?php endif; ?>
        </div>

        <h3 class="integration-section-title"><?= __('integrations.section_keys') ?></h3>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="fIlpPublicKey" class="form-label">
                    <?= __('integrations.field_public_key') ?>
                    <?php if ($hasPublic): ?>
                    <span class="lozenge lozenge-success"><?= __('integrations.status_saved') ?></span>
                    <?php endif; ?>
                </label>
                <div class="password-field">
                    <input type="password" id="fIlpPublicKey" name="ilp_public_key" class="form-control"
                           placeholder="<?= $hasPublic ? __('integrations.placeholder_keep_current') : 'project_public_...' ?>"
                           autocomplete="off">
                    <button type="button" class="password-toggle" id="togglePublicKey"
                            aria-label="<?= __('integrations.show_value') ?>"
                            data-tooltip="<?= __('integrations.show_value') ?>">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <p class="form-helper"><?= __('integrations.public_key_help') ?></p>
            </div>

            <div class="form-group">
                <label for="fIlpSecretKey" class="form-label">
                    <?= __('integrations.field_secret_key') ?>
                    <?php if ($hasSecret): ?>
                    <span class="lozenge lozenge-success"><?= __('integrations.status_saved') ?></span>
                    <?php endif; ?>
                </label>
                <div class="password-field">
                    <input type="password" id="fIlpSecretKey" name="ilp_secret_key" class="form-control"
                           placeholder="<?= $hasSecret ? __('integrations.placeholder_keep_current') : 'secret_key_...' ?>"
                           autocomplete="off">
                    <button type="button" class="password-toggle" id="toggleSecretKey"
                            aria-label="<?= __('integrations.show_value') ?>"
                            data-tooltip="<?= __('integrations.show_value') ?>">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <p class="form-helper"><?= __('integrations.secret_key_help') ?></p>
            </div>
        </div>

        <div class="integration-test-result d-none" id="testResult" role="status" aria-live="polite"></div>

        <div class="alert alert-danger d-none" id="integrationFormError" role="alert">
            <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
            <span class="alert-content" id="integrationFormErrorText"></span>
        </div>

        <div class="integration-actions">
            <button type="button" class="btn btn-default" id="testConnectionBtn" <?= !$ilpConfigured ? 'disabled' : '' ?>>
                <i class="bi bi-wifi" aria-hidden="true"></i>
                <span class="btn-text"><?= __('integrations.btn_test') ?></span>
            </button>
            <button type="submit" class="btn btn-primary" id="integrationSubmitBtn">
                <i class="bi bi-check2" aria-hidden="true"></i>
                <span class="btn-text"><?= __('integrations.btn_save') ?></span>
            </button>
        </div>
    </form>

    <details class="integration-guide">
        <summary class="integration-guide-summary">
            <i class="bi bi-question-circle" aria-hidden="true"></i>
            <?= __('integrations.guide_title') ?>
        </summary>
        <div class="integration-guide-body">
            <p><?= __('integrations.guide_intro') ?></p>
            <ol class="integration-guide-steps">
                <li><?= __('integrations.guide_step1') ?>
                    <a href="https://developer.ilovepdf.com" target="_blank" rel="noopener noreferrer">developer.ilovepdf.com <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
                </li>
                <li><?= __('integrations.guide_step2') ?></li>
                <li><?= __('integrations.guide_step3') ?></li>
                <li><?= __('integrations.guide_step4') ?></li>
                <li><?= __('integrations.guide_step5') ?></li>
            </ol>
            <div class="alert alert-success">
                <i class="bi bi-gift alert-icon" aria-hidden="true"></i>
                <span class="alert-content"><?= __('integrations.guide_free_plan') ?></span>
            </div>
        </div>
    </details>

</section><!-- /panel-ilp -->

<!-- ============ Panel Ghostscript ============ -->
<section id="panel-gs" class="card integration-card integration-panel d-none" role="tabpanel" aria-labelledby="tab-gs" hidden>

    <header class="integration-header">
        <div class="integration-header-icon">
            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
        </div>
        <div class="integration-header-info">
            <h2 class="integration-title">Ghostscript</h2>
            <p class="integration-description">Calidad de optimización aplicada al procesar PDFs con el motor local.</p>
        </div>
    </header>

    <form id="gsForm" class="integration-body" novalidate>

        <h3 class="integration-section-title">Nivel de optimización</h3>

        <div class="form-group">
            <label for="fGsQuality" class="form-label">Preset de calidad</label>
            <select id="fGsQuality" name="gs_quality" class="form-control" style="max-width: 280px;">
                <?php foreach ($gsPresets as $val => $preset): ?>
                <option value="<?= $val ?>" <?= $gsQuality === $val ? 'selected' : '' ?>>
                    <?= htmlspecialchars($preset['label']) ?> — <?= $preset['dpi'] ?> dpi
                    <?= $val === 'ebook' ? '(predeterminado)' : '' ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="alert alert-info" id="gsQualityDesc">
            <i class="bi bi-info-circle alert-icon" aria-hidden="true"></i>
            <span class="alert-content" id="gsQualityDescText"><?= htmlspecialchars($gsPresets[$gsQuality]['desc']) ?></span>
        </div>

        <div class="table-wrapper" style="margin-top: var(--ds-space-200);">
            <table class="table" style="width: auto;">
                <thead>
                    <tr>
                        <th>Preset</th>
                        <th>Resolución</th>
                        <th>Uso</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gsPresets as $val => $preset): ?>
                    <tr <?= $gsQuality === $val ? 'style="font-weight: var(--ds-font-weight-semibold); background: var(--ds-background-selected);"' : '' ?> id="gsRow-<?= $val ?>">
                        <td><?= htmlspecialchars($preset['label']) ?><?= $val === 'ebook' ? ' <span class="lozenge lozenge-default" style="font-weight: normal; margin-left: 4px;">predeterminado</span>' : '' ?></td>
                        <td><?= $preset['dpi'] ?> dpi</td>
                        <td style="color: var(--ds-text-subtle);"><?= htmlspecialchars($preset['desc']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="integration-actions" style="margin-top: var(--ds-space-300);">
            <button type="submit" class="btn btn-primary" id="gsSubmitBtn">
                <i class="bi bi-check2" aria-hidden="true"></i>
                <span class="btn-text">Guardar</span>
            </button>
        </div>
    </form>

</section><!-- /panel-gs -->

<!-- ============ Panel Gmail ============ -->
<section id="panel-gmail" class="card integration-card integration-panel d-none" role="tabpanel" aria-labelledby="tab-gmail" hidden>

    <header class="integration-header">
        <div class="integration-header-icon">
            <i class="bi bi-envelope-at" aria-hidden="true"></i>
        </div>
        <div class="integration-header-info">
            <h2 class="integration-title">Gmail</h2>
            <p class="integration-description"><?= __('integrations.gmail_description') ?></p>
        </div>
        <div class="integration-header-status">
            <?php if ($gmailConfigured): ?>
            <span class="lozenge lozenge-success">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <?= __('integrations.status_configured') ?>
            </span>
            <?php else: ?>
            <span class="lozenge lozenge-default">
                <i class="bi bi-dash-circle" aria-hidden="true"></i>
                <?= __('integrations.status_not_configured') ?>
            </span>
            <?php endif; ?>
        </div>
    </header>

    <form id="gmailForm" class="integration-body" novalidate>
        <input type="hidden" name="action" value="save">

        <div class="alert alert-info">
            <i class="bi bi-shield-lock alert-icon" aria-hidden="true"></i>
            <span class="alert-content"><?= __('integrations.gmail_security_note') ?></span>
        </div>

        <h3 class="integration-section-title"><?= __('integrations.section_account') ?></h3>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="fGmailEmail" class="form-label"><?= __('integrations.gmail_field_email') ?></label>
                <input type="email" id="fGmailEmail" name="gmail_email" class="form-control"
                       placeholder="correo@empresa.com"
                       value="<?= htmlspecialchars($gmailEmail) ?>"
                       autocomplete="off"
                       aria-describedby="fGmailEmailError">
                <p class="form-error" id="fGmailEmailError" aria-live="polite"></p>
            </div>

            <div class="form-group">
                <label for="fGmailLabel" class="form-label"><?= __('integrations.gmail_field_label') ?></label>
                <input type="text" id="fGmailLabel" name="gmail_label" class="form-control"
                       placeholder="Nexus"
                       value="<?= htmlspecialchars($gmailLabel) ?>">
                <p class="form-helper"><?= __('integrations.gmail_label_help') ?></p>
            </div>
        </div>

        <div class="form-group">
            <label for="fGmailAppPassword" class="form-label"><?= __('integrations.gmail_field_app_password') ?></label>
            <div class="password-field">
                <input type="password" id="fGmailAppPassword" name="gmail_app_password" class="form-control"
                       placeholder="<?= $gmailHasPass ? __('integrations.placeholder_keep_current') : 'xxxx xxxx xxxx xxxx' ?>"
                       autocomplete="new-password">
                <button type="button" class="password-toggle" id="toggleGmailPassword"
                        aria-label="<?= __('integrations.show_password') ?>"
                        data-tooltip="<?= __('integrations.show_password') ?>">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                </button>
            </div>
            <?php if ($gmailHasPass): ?>
            <p class="form-helper"><?= __('integrations.password_current_help') ?></p>
            <?php endif; ?>
        </div>

        <?php if ($gmailLastSync): ?>
        <p class="form-helper">
            <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
            <?= __('integrations.gmail_last_sync') ?>: <?= htmlspecialchars($gmailLastSync) ?>
        </p>
        <?php endif; ?>

        <div class="integration-test-result d-none" id="gmailTestResult" role="status" aria-live="polite"></div>

        <div class="alert alert-danger d-none" id="gmailFormError" role="alert">
            <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
            <span class="alert-content" id="gmailFormErrorText"></span>
        </div>

        <div class="integration-actions">
            <button type="button" class="btn btn-default" id="gmailSyncBtn" <?= !$gmailConfigured ? 'disabled' : '' ?>>
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                <span class="btn-text"><?= __('integrations.gmail_btn_sync') ?></span>
            </button>
            <button type="button" class="btn btn-default" id="gmailTestBtn" <?= !$gmailConfigured ? 'disabled' : '' ?>>
                <i class="bi bi-wifi" aria-hidden="true"></i>
                <span class="btn-text"><?= __('integrations.btn_test') ?></span>
            </button>
            <button type="submit" class="btn btn-primary" id="gmailSubmitBtn">
                <i class="bi bi-check2" aria-hidden="true"></i>
                <span class="btn-text"><?= __('integrations.btn_save') ?></span>
            </button>
        </div>
    </form>

    <details class="integration-guide">
        <summary class="integration-guide-summary">
            <i class="bi bi-question-circle" aria-hidden="true"></i>
            <?= __('integrations.gmail_guide_title') ?>
        </summary>
        <div class="integration-guide-body">
            <p><?= __('integrations.gmail_guide_intro') ?></p>
            <ol class="integration-guide-steps">
                <li><?= __('integrations.gmail_guide_step1') ?></li>
                <li><?= __('integrations.gmail_guide_step2') ?></li>
                <li><?= __('integrations.gmail_guide_step3') ?></li>
                <li><?= __('integrations.gmail_guide_step4') ?></li>
                <li><?= __('integrations.gmail_guide_step5') ?></li>
            </ol>
            <div class="alert alert-warning">
                <i class="bi bi-info-circle alert-icon" aria-hidden="true"></i>
                <span class="alert-content"><?= __('integrations.gmail_guide_note') ?></span>
            </div>
        </div>
    </details>

</section><!-- /panel-gmail -->

<!-- ============ Panel SMTP ============ -->
<section id="panel-smtp" class="card integration-card integration-panel d-none" role="tabpanel" aria-labelledby="tab-smtp" hidden>

    <header class="integration-header">
        <div class="integration-header-icon">
            <i class="bi bi-send" aria-hidden="true"></i>
        </div>
        <div class="integration-header-info">
            <h2 class="integration-title"><?= __('integrations.smtp_title') ?></h2>
            <p class="integration-description"><?= __('integrations.smtp_description') ?></p>
        </div>
        <div class="integration-header-status">
            <?php if ($smtpConfigured): ?>
            <span class="lozenge lozenge-success">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <?= __('integrations.status_configured') ?>
            </span>
            <?php else: ?>
            <span class="lozenge lozenge-default">
                <i class="bi bi-dash-circle" aria-hidden="true"></i>
                <?= __('integrations.status_not_configured') ?>
            </span>
            <?php endif; ?>
        </div>
    </header>

    <form id="smtpForm" class="integration-body" novalidate>

        <div class="alert alert-info">
            <i class="bi bi-shield-lock alert-icon" aria-hidden="true"></i>
            <span class="alert-content"><?= __('integrations.smtp_security_note') ?></span>
        </div>

        <h3 class="integration-section-title"><?= __('integrations.smtp_section_server') ?></h3>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="fSmtpHost" class="form-label"><?= __('integrations.smtp_field_host') ?></label>
                <input type="text" id="fSmtpHost" name="smtp_host" class="form-control"
                       placeholder="smtp.gmail.com"
                       value="<?= htmlspecialchars($smtpRaw['smtp_host'] ?? '') ?>"
                       autocomplete="off">
            </div>
            <div class="form-grid-2" style="gap: var(--ds-space-200);">
                <div class="form-group">
                    <label for="fSmtpPort" class="form-label"><?= __('integrations.smtp_field_port') ?></label>
                    <input type="number" id="fSmtpPort" name="smtp_port" class="form-control"
                           placeholder="587" min="1" max="65535"
                           value="<?= htmlspecialchars((string)($smtpRaw['smtp_port'] ?? 587)) ?>">
                </div>
                <div class="form-group">
                    <label for="fSmtpSecure" class="form-label"><?= __('integrations.smtp_field_secure') ?></label>
                    <select id="fSmtpSecure" name="smtp_secure" class="form-control">
                        <option value="tls"  <?= ($smtpRaw['smtp_secure'] ?? 'tls') === 'tls'  ? 'selected' : '' ?>>STARTTLS (587)</option>
                        <option value="ssl"  <?= ($smtpRaw['smtp_secure'] ?? '') === 'ssl'  ? 'selected' : '' ?>>SSL (465)</option>
                        <option value="none" <?= ($smtpRaw['smtp_secure'] ?? '') === 'none' ? 'selected' : '' ?>>Sin cifrado</option>
                    </select>
                </div>
            </div>
        </div>

        <h3 class="integration-section-title"><?= __('integrations.smtp_section_auth') ?></h3>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="fSmtpUser" class="form-label"><?= __('integrations.smtp_field_user') ?></label>
                <input type="email" id="fSmtpUser" name="smtp_user" class="form-control"
                       placeholder="correo@empresa.com"
                       value="<?= htmlspecialchars($smtpRaw['smtp_user'] ?? '') ?>"
                       autocomplete="off">
            </div>
            <div class="form-group">
                <label for="fSmtpPass" class="form-label"><?= __('integrations.smtp_field_pass') ?></label>
                <div class="password-field">
                    <input type="password" id="fSmtpPass" name="smtp_pass" class="form-control"
                           placeholder="<?= $smtpHasPass ? __('integrations.placeholder_keep_current') : '' ?>"
                           autocomplete="new-password">
                    <button type="button" class="password-toggle" id="toggleSmtpPass"
                            aria-label="<?= __('integrations.show_password') ?>"
                            data-tooltip="<?= __('integrations.show_password') ?>">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <?php if ($smtpHasPass): ?>
                <p class="form-helper"><?= __('integrations.password_current_help') ?></p>
                <?php endif; ?>
            </div>
        </div>

        <h3 class="integration-section-title"><?= __('integrations.smtp_section_sender') ?></h3>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="fSmtpFrom" class="form-label"><?= __('integrations.smtp_field_from_email') ?></label>
                <input type="email" id="fSmtpFrom" name="smtp_from" class="form-control"
                       placeholder="noreply@empresa.com"
                       value="<?= htmlspecialchars($smtpRaw['smtp_from'] ?? '') ?>"
                       autocomplete="off">
            </div>
            <div class="form-group">
                <label for="fSmtpFromName" class="form-label"><?= __('integrations.smtp_field_from_name') ?></label>
                <input type="text" id="fSmtpFromName" name="smtp_from_name" class="form-control"
                       placeholder="Nexus"
                       value="<?= htmlspecialchars($smtpRaw['smtp_from_name'] ?? '') ?>">
            </div>
        </div>

        <div class="integration-test-result d-none" id="smtpTestResult" role="status" aria-live="polite"></div>

        <div class="integration-actions">
            <button type="button" class="btn btn-default" id="smtpTestBtn" <?= !$smtpConfigured ? 'disabled' : '' ?>>
                <i class="bi bi-wifi" aria-hidden="true"></i>
                <span class="btn-text"><?= __('integrations.btn_test') ?></span>
            </button>
            <button type="submit" class="btn btn-primary" id="smtpSubmitBtn">
                <i class="bi bi-check2" aria-hidden="true"></i>
                <span class="btn-text"><?= __('integrations.btn_save') ?></span>
            </button>
        </div>
    </form>

</section><!-- /panel-smtp -->
