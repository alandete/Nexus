<?php
/**
 * Nexus 2.0 — Ajustes > Integraciones
 * Gestion de claves API (iLovePDF / iLoveIMG)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();

// Solo admin puede gestionar integraciones
if (($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    include 'pages/403.php';
    return;
}

$apiSettings = getApiSettings();

// Estado de configuracion (true si hay valor, sin exponer claves)
$hasEmail    = !empty($apiSettings['ilp_email']);
$hasPassword = !empty($apiSettings['ilp_password']);
$hasProject  = !empty($apiSettings['ilp_project']);
$hasPublic   = !empty($apiSettings['ilp_public_key']);
$hasSecret   = !empty($apiSettings['ilp_secret_key']);

$ilpConfigured = $hasPublic; // Publico es el minimo necesario para llamadas API

// Para mostrar valores seguros en los campos
$emailValue   = $hasEmail    ? $apiSettings['ilp_email']   : '';
$projectValue = $hasProject  ? $apiSettings['ilp_project'] : '';
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

<!-- ============ iLovePDF / iLoveIMG ============ -->
<section class="card integration-card" aria-labelledby="ilp-title">
    <header class="integration-header">
        <div class="integration-header-icon">
            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
        </div>
        <div class="integration-header-info">
            <h2 class="integration-title" id="ilp-title">iLovePDF / iLoveIMG</h2>
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

        <!-- Nota de seguridad -->
        <div class="alert alert-info">
            <i class="bi bi-shield-lock alert-icon" aria-hidden="true"></i>
            <span class="alert-content"><?= __('integrations.security_note') ?></span>
        </div>

        <!-- Cuenta -->
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

        <!-- Claves API -->
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

        <!-- Resultado de prueba de conexion -->
        <div class="integration-test-result d-none" id="testResult" role="status" aria-live="polite"></div>

        <!-- Alert error general -->
        <div class="alert alert-danger d-none" id="integrationFormError" role="alert">
            <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
            <span class="alert-content" id="integrationFormErrorText"></span>
        </div>

        <!-- Acciones -->
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

    <!-- Guia colapsable: como obtener las claves -->
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
</section>
