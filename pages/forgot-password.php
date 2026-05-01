<?php
/**
 * Nexus 2.0 — Recuperar contraseña
 * Página standalone: solicitud de recuperación por email.
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$projectInfo = getProjectInfo();

if (isLoggedIn()) {
    header('Location: ' . url('home'));
    exit;
}

$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Solicitud no válida. Recargue la página e intente de nuevo.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = __('forgot_password.error_invalid_email');
        } else {
            requestPasswordReset($email);
            $sent = true;
        }
    }
}

$canonicalBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST']
    . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <title><?= htmlspecialchars($projectInfo['app_name']) ?> — <?= __('forgot_password.page_title') ?></title>
    <?php if (!empty($projectInfo['logo'])): ?>
    <link rel="icon" type="image/png" href="assets/uploads/logos/<?= htmlspecialchars($projectInfo['logo']) ?>">
    <?php endif; ?>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/variables.css?v=<?= filemtime('assets/css/variables.css') ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= filemtime('assets/css/styles.css') ?>">
</head>
<body class="login-page">

    <div class="login-container">

        <div class="login-branding">
            <div class="login-branding-content">
                <div class="login-branding-logo">
                    <?php if (!empty($projectInfo['logo'])): ?>
                        <img src="assets/uploads/logos/<?= htmlspecialchars($projectInfo['logo']) ?>"
                             alt="<?= htmlspecialchars($projectInfo['app_name']) ?>">
                    <?php else: ?>
                        <i class="bi bi-hexagon-fill logo-icon" aria-hidden="true"></i>
                    <?php endif; ?>
                </div>
                <h1 class="login-branding-name"><?= htmlspecialchars($projectInfo['app_name']) ?></h1>
                <p class="login-branding-tagline"><?= !empty($projectInfo['tagline']) ? htmlspecialchars($projectInfo['tagline']) : __('login.tagline') ?></p>
                <p class="login-branding-description"><?= __('login.description') ?></p>
            </div>
            <span class="login-branding-version"><?= __('login.version_prefix') ?> <?= APP_VERSION ?></span>
        </div>

        <div class="login-form-area">
            <div class="login-card">

                <div class="login-mobile-header">
                    <div class="login-mobile-logo">
                        <?php if (!empty($projectInfo['logo'])): ?>
                            <img src="assets/uploads/logos/<?= htmlspecialchars($projectInfo['logo']) ?>"
                                 alt="<?= htmlspecialchars($projectInfo['app_name']) ?>">
                        <?php else: ?>
                            <i class="bi bi-hexagon-fill logo-icon" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                    <h2 class="login-mobile-brand"><?= htmlspecialchars($projectInfo['app_name']) ?></h2>
                </div>

                <?php if ($sent): ?>
                <div class="alert alert-success" role="status">
                    <i class="bi bi-check-circle-fill alert-icon" aria-hidden="true"></i>
                    <span class="alert-content"><?= __('forgot_password.sent_message') ?></span>
                </div>
                <p style="margin-top: var(--ds-space-300); text-align: center;">
                    <a href="<?= url('login') ?>"><?= __('forgot_password.back_to_login') ?></a>
                </p>
                <?php else: ?>

                <h2 class="login-form-title"><?= __('forgot_password.form_title') ?></h2>
                <p class="login-form-subtitle"><?= __('forgot_password.form_subtitle') ?></p>

                <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
                    <span class="alert-content"><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('forgot-password') ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <div class="login-field">
                        <label for="email" class="form-label"><?= __('forgot_password.field_email') ?></label>
                        <input type="email"
                               class="form-control <?= $error ? 'form-control-error' : '' ?>"
                               id="email" name="email"
                               placeholder="<?= __('forgot_password.placeholder_email') ?>"
                               autocomplete="email"
                               required autofocus
                               value="<?= isset($_POST['email']) ? htmlspecialchars(trim($_POST['email'])) : '' ?>">
                    </div>
                    <button type="submit" class="btn btn-primary login-submit">
                        <?= __('forgot_password.btn_submit') ?>
                    </button>
                </form>

                <div style="margin-top: var(--ds-space-300); text-align: center;">
                    <a href="<?= url('login') ?>" class="text-sm" style="color: var(--ds-text-subtle);">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                        <?= __('forgot_password.back_to_login') ?>
                    </a>
                </div>

                <?php endif; ?>

                <div class="login-lang">
                    <a class="login-lang-btn <?= $lang === 'es' ? 'active' : '' ?>" href="?lang=es">Español</a>
                    <a class="login-lang-btn <?= $lang === 'en' ? 'active' : '' ?>" href="?lang=en">English</a>
                </div>

            </div>
        </div>

    </div>

</body>
</html>
