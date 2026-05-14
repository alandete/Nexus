<?php
/**
 * Nexus 2.0 — Ajustes > Aplicacion
 * Identidad, empresa, operacion y privacidad
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();

if (!hasPermission($currentUser, 'settings', 'read')) {
    http_response_code(403);
    include 'pages/403.php';
    return;
}

$canWrite = canEditModule($currentUser, 'settings');

$info = getProjectInfo();

// Defaults
$brandColor  = $info['brand_color']  ?? '#585d8a';
$accentColor = $info['accent_color'] ?? '#f86e15';
$timezone    = $info['timezone']     ?? 'America/Bogota';
$defaultLang = $info['default_lang'] ?? 'es';
$logoFile    = $info['logo']         ?? null;
$faviconFile = $info['favicon']      ?? null;
$maintenance = !empty($info['maintenance_mode']);
$maintenanceMsg = $info['maintenance_message'] ?? '';
$maintenanceIps = implode("\n", $info['maintenance_allowed_ips'] ?? []);
$privacy = !empty($info['privacy_mode']);

// Lista de zonas horarias principales
$timezoneGroups = [
    'America'    => ['America/Bogota', 'America/Mexico_City', 'America/Lima', 'America/Santiago', 'America/Argentina/Buenos_Aires', 'America/Caracas', 'America/Sao_Paulo', 'America/New_York', 'America/Los_Angeles'],
    'Europe'     => ['Europe/Madrid', 'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'Europe/Rome'],
    'Asia'       => ['Asia/Tokyo', 'Asia/Shanghai', 'Asia/Dubai', 'Asia/Jerusalem'],
    'UTC'        => ['UTC'],
];
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= url('settings') ?>" class="breadcrumb-link"><?= __('menu.settings') ?></a>
    <i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>
    <span class="breadcrumb-current" aria-current="page"><?= __('menu.application') ?></span>
</nav>

<div class="page-header d-flex items-center justify-between flex-wrap gap-200">
    <div>
        <h1 class="page-title"><?= __('application.page_title') ?></h1>
        <p class="page-description"><?= __('application.page_subtitle') ?></p>
    </div>
</div>

<form id="applicationForm" class="application-form" enctype="multipart/form-data" novalidate>
<?php if (!$canWrite): ?>
<fieldset disabled style="border:none;padding:0;margin:0;">
<?php endif; ?>

    <!-- ============ IDENTIDAD ============ -->
    <section class="card application-section" aria-labelledby="sec-identity-title">
        <header class="application-section-header">
            <h2 class="application-section-title" id="sec-identity-title"><?= __('application.sec_identity') ?></h2>
            <p class="application-section-desc"><?= __('application.sec_identity_desc') ?></p>
        </header>

        <div class="application-section-body">
            <div class="form-grid-2">
                <div class="form-group">
                    <label for="fAppName" class="form-label">
                        <?= __('application.field_app_name') ?> <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input type="text" id="fAppName" name="app_name" class="form-control"
                           value="<?= htmlspecialchars($info['app_name'] ?? 'Nexus') ?>" required
                           aria-describedby="fAppNameError">
                    <p class="form-error" id="fAppNameError" aria-live="polite"></p>
                </div>

                <div class="form-group">
                    <label for="fTagline" class="form-label"><?= __('application.field_tagline') ?></label>
                    <input type="text" id="fTagline" name="tagline" class="form-control"
                           value="<?= htmlspecialchars($info['tagline'] ?? '') ?>"
                           aria-describedby="fTaglineHelp">
                    <p class="form-helper" id="fTaglineHelp"><?= __('application.field_tagline_help') ?></p>
                </div>
            </div>

            <div class="form-group">
                <label for="fDescription" class="form-label"><?= __('application.field_description') ?></label>
                <textarea id="fDescription" name="description" class="form-control" rows="3"><?= htmlspecialchars($info['description'] ?? '') ?></textarea>
            </div>

            <!-- Assets: Logo + Favicon en fila compacta -->
            <div class="form-group">
                <label class="form-label"><?= __('application.field_assets') ?></label>
                <div class="asset-row">
                    <!-- Logo -->
                    <div class="asset-row-item">
                        <div class="asset-row-preview">
                            <?php if ($logoFile): ?>
                                <img id="logoPreview" src="assets/uploads/logos/<?= htmlspecialchars($logoFile) ?>?v=<?= time() ?>" alt="">
                            <?php else: ?>
                                <span id="logoPlaceholder" class="asset-upload-placeholder" aria-hidden="true">
                                    <i class="bi bi-hexagon-fill"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="asset-row-info">
                            <span class="asset-row-title"><?= __('application.field_logo') ?></span>
                            <span class="asset-row-hint"><?= __('application.logo_help') ?></span>
                        </div>
                        <div class="asset-row-actions">
                            <label for="logoInput" class="btn btn-default btn-sm">
                                <i class="bi bi-upload" aria-hidden="true"></i>
                                <?= __('application.btn_upload') ?>
                            </label>
                            <input type="file" id="logoInput" name="logo" accept="image/jpeg,image/png,image/webp" class="sr-only">
                            <input type="hidden" name="remove_logo" id="removeLogo" value="0">
                            <?php if ($logoFile): ?>
                            <button type="button" class="btn-icon btn-icon-danger" id="removeLogoBtn"
                                    data-tooltip="<?= __('application.btn_remove') ?>" aria-label="<?= __('application.btn_remove') ?>">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Favicon -->
                    <div class="asset-row-item">
                        <div class="asset-row-preview asset-row-preview-sm">
                            <?php if ($faviconFile): ?>
                                <img id="faviconPreview" src="assets/uploads/logos/<?= htmlspecialchars($faviconFile) ?>?v=<?= time() ?>" alt="">
                            <?php else: ?>
                                <span id="faviconPlaceholder" class="asset-upload-placeholder" aria-hidden="true">
                                    <i class="bi bi-window"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="asset-row-info">
                            <span class="asset-row-title"><?= __('application.field_favicon') ?></span>
                            <span class="asset-row-hint"><?= __('application.favicon_help') ?></span>
                        </div>
                        <div class="asset-row-actions">
                            <label for="faviconInput" class="btn btn-default btn-sm">
                                <i class="bi bi-upload" aria-hidden="true"></i>
                                <?= __('application.btn_upload') ?>
                            </label>
                            <input type="file" id="faviconInput" name="favicon" accept="image/png,image/svg+xml,image/x-icon" class="sr-only">
                            <input type="hidden" name="remove_favicon" id="removeFavicon" value="0">
                            <?php if ($faviconFile): ?>
                            <button type="button" class="btn-icon btn-icon-danger" id="removeFaviconBtn"
                                    data-tooltip="<?= __('application.btn_remove') ?>" aria-label="<?= __('application.btn_remove') ?>">
                                <i class="bi bi-trash" aria-hidden="true"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Colores de marca + preview en una fila compacta -->
            <div class="form-group">
                <label class="form-label"><?= __('application.field_colors') ?></label>
                <div class="color-row">
                    <!-- Primario -->
                    <div class="color-row-field">
                        <label for="fBrandColor" class="color-row-label"><?= __('application.field_brand_color') ?></label>
                        <div class="color-field">
                            <input type="color" id="fBrandColor" class="color-field-picker" value="<?= htmlspecialchars($brandColor) ?>" aria-label="<?= __('application.field_brand_color') ?>">
                            <input type="text" id="fBrandColorHex" name="brand_color" class="form-control"
                                   pattern="^#[0-9A-Fa-f]{6}$" maxlength="7"
                                   value="<?= htmlspecialchars($brandColor) ?>">
                        </div>
                    </div>

                    <!-- Acento -->
                    <div class="color-row-field">
                        <label for="fAccentColor" class="color-row-label"><?= __('application.field_accent_color') ?></label>
                        <div class="color-field">
                            <input type="color" id="fAccentColor" class="color-field-picker" value="<?= htmlspecialchars($accentColor) ?>" aria-label="<?= __('application.field_accent_color') ?>">
                            <input type="text" id="fAccentColorHex" name="accent_color" class="form-control"
                                   pattern="^#[0-9A-Fa-f]{6}$" maxlength="7"
                                   value="<?= htmlspecialchars($accentColor) ?>">
                        </div>
                    </div>

                    <!-- Preview compacto -->
                    <div class="color-row-preview" aria-label="<?= __('application.color_preview') ?>">
                        <button type="button" class="btn btn-sm" disabled style="background: var(--preview-brand, var(--app-brand)); color: #fff;">
                            <?= __('application.preview_button') ?>
                        </button>
                        <span class="lozenge" style="background: rgba(var(--preview-brand-rgb, var(--app-brand-rgb)), 0.1); color: var(--preview-brand, var(--app-brand));">
                            <?= __('application.preview_lozenge') ?>
                        </span>
                        <span class="preview-accent-dot" style="background: var(--preview-accent, var(--app-accent));" aria-hidden="true"></span>
                    </div>
                </div>
                <p class="form-helper"><?= __('application.colors_help') ?></p>
            </div>
        </div>
    </section>

    <!-- ============ EMPRESA ============ -->
    <section class="card application-section" aria-labelledby="sec-company-title">
        <header class="application-section-header">
            <h2 class="application-section-title" id="sec-company-title"><?= __('application.sec_company') ?></h2>
            <p class="application-section-desc"><?= __('application.sec_company_desc') ?></p>
        </header>

        <div class="application-section-body">
            <div class="form-grid-2">
                <div class="form-group">
                    <label for="fCompanyName" class="form-label"><?= __('application.field_company_name') ?></label>
                    <input type="text" id="fCompanyName" name="company_name" class="form-control"
                           value="<?= htmlspecialchars($info['company_name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="fCompanyAddress" class="form-label"><?= __('application.field_company_address') ?></label>
                    <input type="text" id="fCompanyAddress" name="company_address" class="form-control"
                           value="<?= htmlspecialchars($info['company_address'] ?? '') ?>">
                </div>
            </div>

            <div class="form-grid-2">
                <div class="form-group">
                    <label for="fContactEmail" class="form-label"><?= __('application.field_contact_email') ?></label>
                    <input type="email" id="fContactEmail" name="contact_email" class="form-control"
                           value="<?= htmlspecialchars($info['contact_email'] ?? '') ?>"
                           aria-describedby="fContactEmailError">
                    <p class="form-error" id="fContactEmailError" aria-live="polite"></p>
                </div>

                <div class="form-group">
                    <label for="fContactPhone" class="form-label"><?= __('application.field_contact_phone') ?></label>
                    <input type="tel" id="fContactPhone" name="contact_phone" class="form-control"
                           value="<?= htmlspecialchars($info['contact_phone'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="fWebsite" class="form-label"><?= __('application.field_website') ?></label>
                <input type="url" id="fWebsite" name="website" class="form-control"
                       placeholder="https://..."
                       value="<?= htmlspecialchars($info['website'] ?? '') ?>"
                       aria-describedby="fWebsiteError">
                <p class="form-error" id="fWebsiteError" aria-live="polite"></p>
            </div>
        </div>
    </section>

    <!-- ============ OPERACION ============ -->
    <section class="card application-section" aria-labelledby="sec-operation-title">
        <header class="application-section-header">
            <h2 class="application-section-title" id="sec-operation-title"><?= __('application.sec_operation') ?></h2>
            <p class="application-section-desc"><?= __('application.sec_operation_desc') ?></p>
        </header>

        <div class="application-section-body">
            <div class="form-grid-2">
                <div class="form-group">
                    <label for="fTimezone" class="form-label"><?= __('application.field_timezone') ?></label>
                    <select id="fTimezone" name="timezone" class="form-control" aria-describedby="fTimezoneHelp">
                        <?php foreach ($timezoneGroups as $group => $tzList): ?>
                        <optgroup label="<?= htmlspecialchars($group) ?>">
                            <?php foreach ($tzList as $tz): ?>
                            <option value="<?= htmlspecialchars($tz) ?>" <?= $timezone === $tz ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tz) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <p class="form-helper" id="fTimezoneHelp"><?= __('application.field_timezone_help') ?></p>
                </div>

                <div class="form-group">
                    <label for="fDefaultLang" class="form-label"><?= __('application.field_default_lang') ?></label>
                    <select id="fDefaultLang" name="default_lang" class="form-control" aria-describedby="fDefaultLangHelp">
                        <option value="es" <?= $defaultLang === 'es' ? 'selected' : '' ?>>Español</option>
                        <option value="en" <?= $defaultLang === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                    <p class="form-helper" id="fDefaultLangHelp"><?= __('application.field_default_lang_help') ?></p>
                </div>
            </div>

            <!-- Modo mantenimiento -->
            <div class="maintenance-box <?= $maintenance ? 'is-active' : '' ?>" id="maintenanceBox">
                <div class="maintenance-box-header">
                    <label class="toggle-field toggle-field-lg">
                        <input type="checkbox" id="fMaintenance" name="maintenance_mode" value="1" <?= $maintenance ? 'checked' : '' ?>>
                        <span class="toggle-field-label">
                            <strong><?= __('application.field_maintenance') ?></strong>
                            <span class="form-helper"><?= __('application.field_maintenance_help') ?></span>
                        </span>
                    </label>
                </div>
                <div class="maintenance-box-body" id="maintenanceBody">
                    <div class="alert alert-warning">
                        <i class="bi bi-info-circle alert-icon" aria-hidden="true"></i>
                        <span class="alert-content"><?= __('application.maintenance_warning') ?></span>
                    </div>

                    <div class="form-group">
                        <label for="fMaintenanceMsg" class="form-label"><?= __('application.field_maintenance_msg') ?></label>
                        <textarea id="fMaintenanceMsg" name="maintenance_message" class="form-control" rows="3"
                                  placeholder="<?= __('application.field_maintenance_msg_placeholder') ?>"><?= htmlspecialchars($maintenanceMsg) ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="fMaintenanceIps" class="form-label"><?= __('application.field_maintenance_ips') ?></label>
                        <textarea id="fMaintenanceIps" name="maintenance_allowed_ips" class="form-control" rows="3"
                                  placeholder="192.168.1.1"><?= htmlspecialchars($maintenanceIps) ?></textarea>
                        <p class="form-helper"><?= __('application.field_maintenance_ips_help') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ PRIVACIDAD ============ -->
    <section class="card application-section" aria-labelledby="sec-privacy-title">
        <header class="application-section-header">
            <h2 class="application-section-title" id="sec-privacy-title"><?= __('application.sec_privacy') ?></h2>
            <p class="application-section-desc"><?= __('application.sec_privacy_desc') ?></p>
        </header>

        <div class="application-section-body">
            <div class="form-group">
                <label class="toggle-field toggle-field-lg">
                    <input type="checkbox" id="fPrivacy" name="privacy_mode" value="1" <?= $privacy ? 'checked' : '' ?>>
                    <span class="toggle-field-label">
                        <strong><?= __('application.field_privacy') ?></strong>
                        <span class="form-helper"><?= __('application.field_privacy_help') ?></span>
                    </span>
                </label>
            </div>
        </div>
    </section>

    <!-- Alert de error general -->
    <div class="alert alert-danger d-none" id="applicationFormError" role="alert">
        <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
        <span class="alert-content" id="applicationFormErrorText"></span>
    </div>

<?php if (!$canWrite): ?>
</fieldset>
<?php endif; ?>

    <!-- Barra de acciones (sticky) -->
    <?php if ($canWrite): ?>
    <div class="application-actions">
        <button type="button" class="btn btn-subtle" id="applicationResetBtn">
            <?= __('common.cancel') ?>
        </button>
        <button type="submit" class="btn btn-primary" id="applicationSubmitBtn">
            <i class="bi bi-check2" aria-hidden="true"></i>
            <span class="btn-text"><?= __('application.btn_save') ?></span>
        </button>
    </div>
    <?php endif; ?>

</form>
