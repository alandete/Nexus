<?php
/**
 * Nexus 2.0 — Restablecer contraseña
 * Página standalone: nueva contraseña con token de un solo uso.
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$projectInfo = getProjectInfo();

if (isLoggedIn()) {
    header('Location: ' . url('home'));
    exit;
}

$token     = sanitize($_GET['token'] ?? '');
$tokenUser = $token ? validateResetToken($token) : null;
$success   = false;
$error     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $error = 'Solicitud no válida. Recargue la página e intente de nuevo.';
    } else {
        $postToken = trim($_POST['token'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm   = $_POST['confirm']  ?? '';

        if (strlen($password) < 6) {
            $error = __('reset_password.error_too_short');
        } elseif ($password !== $confirm) {
            $error = __('reset_password.error_mismatch');
        } else {
            $ok = resetPassword($postToken, $password);
            if ($ok) {
                $success = true;
            } else {
                $error = __('reset_password.error_expired');
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <title><?= htmlspecialchars($projectInfo['app_name']) ?> — <?= __('reset_password.page_title') ?></title>
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

                <?php if ($success): ?>
                <div class="alert alert-success" role="status">
                    <i class="bi bi-check-circle-fill alert-icon" aria-hidden="true"></i>
                    <span class="alert-content"><?= __('reset_password.success_message') ?></span>
                </div>
                <p style="margin-top: var(--ds-space-300); text-align: center;">
                    <a href="<?= url('login') ?>" class="btn btn-primary"><?= __('reset_password.btn_login') ?></a>
                </p>

                <?php elseif (!$tokenUser && !$_POST): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
                    <span class="alert-content"><?= __('reset_password.error_expired') ?></span>
                </div>
                <p style="margin-top: var(--ds-space-300); text-align: center;">
                    <a href="<?= url('forgot-password') ?>"><?= __('reset_password.request_new') ?></a>
                </p>

                <?php else: ?>
                <h2 class="login-form-title"><?= __('reset_password.form_title') ?></h2>
                <p class="login-form-subtitle"><?= __('reset_password.form_subtitle') ?></p>

                <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
                    <span class="alert-content"><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('reset-password') ?>?token=<?= urlencode($token) ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="login-field">
                        <label for="password" class="form-label"><?= __('reset_password.field_password') ?></label>
                        <input type="password"
                               class="form-control <?= $error ? 'form-control-error' : '' ?>"
                               id="password" name="password"
                               placeholder="<?= __('reset_password.placeholder_password') ?>"
                               autocomplete="new-password"
                               required autofocus minlength="6">
                    </div>

                    <div class="login-field">
                        <label for="confirm" class="form-label"><?= __('reset_password.field_confirm') ?></label>
                        <input type="password"
                               class="form-control <?= $error ? 'form-control-error' : '' ?>"
                               id="confirm" name="confirm"
                               placeholder="<?= __('reset_password.placeholder_confirm') ?>"
                               autocomplete="new-password"
                               required minlength="6">
                    </div>

                    <button type="submit" class="btn btn-primary login-submit">
                        <?= __('reset_password.btn_submit') ?>
                    </button>
                </form>
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
