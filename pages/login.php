<?php
/**
 * Nexus 2.0 — Login
 * Split-screen: branding | formulario
 * Atlassian Design System
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$projectInfo = getProjectInfo();

if (isLoggedIn()) {
    header('Location: ' . url('home'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Solicitud no valida. Recargue la pagina e intente de nuevo.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = __('login.error_empty');
        } else {
            $result = login($username, $password);
            if ($result['success']) {
                logActivity('auth', 'login', $username);
                header('Location: ' . url('home'));
                exit;
            } else {
                $error = $result['message'];
            }
        }
    }
}

$canonicalBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($projectInfo['description'] ?: __('site_title')) ?>">
    <meta name="author" content="<?= htmlspecialchars($projectInfo['company_name'] ?: $projectInfo['app_name']) ?>">
    <?php if (!empty($projectInfo['privacy_mode'])): ?>
    <meta name="robots" content="noindex, nofollow, noimageindex, noarchive, nosnippet">
    <meta name="googlebot" content="noindex, nofollow, noimageindex">
    <meta name="google" content="notranslate">
    <?php else: ?>
    <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
    <title><?= htmlspecialchars($projectInfo['app_name']) ?> — <?= __('login.page_title') ?></title>

    <link rel="canonical" href="<?= htmlspecialchars($canonicalBase . '/login') ?>">

    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($projectInfo['app_name']) ?> — <?= __('login.page_title') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($projectInfo['description'] ?: __('site_title')) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalBase . '/login') ?>">
    <?php if (!empty($projectInfo['logo'])): ?>
    <meta property="og:image" content="<?= htmlspecialchars($canonicalBase . '/assets/uploads/logos/' . $projectInfo['logo']) ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="<?= htmlspecialchars($projectInfo['app_name']) ?>">
    <meta property="og:locale" content="<?= $lang === 'es' ? 'es_ES' : 'en_US' ?>">

    <?php if (!empty($projectInfo['logo'])): ?>
    <link rel="icon" type="image/png" href="assets/uploads/logos/<?= htmlspecialchars($projectInfo['logo']) ?>">
    <?php endif; ?>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Nexus 2.0 CSS -->
    <link rel="stylesheet" href="assets/css/variables.css?v=<?= filemtime('assets/css/variables.css') ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= filemtime('assets/css/styles.css') ?>">
</head>
<body class="login-page">

    <div class="login-container">

        <!-- Panel izquierdo: Branding (visible en tablet+) -->
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

        <!-- Panel derecho: Formulario -->
        <div class="login-form-area">
            <div class="login-card">

                <!-- Header movil (visible en mobile) -->
                <div class="login-mobile-header">
                    <div class="login-mobile-logo">
                        <?php if (!empty($projectInfo['logo'])): ?>
                            <img src="assets/uploads/logos/<?= htmlspecialchars($projectInfo['logo']) ?>"
                                 alt="<?= htmlspecialchars($projectInfo['app_name']) ?>">
                        <?php else: ?>
                            <i class="bi bi-hexagon-fill logo-icon" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>
                    <h1 class="login-mobile-brand"><?= htmlspecialchars($projectInfo['app_name']) ?></h1>
                    <p class="login-mobile-tagline"><?= !empty($projectInfo['tagline']) ? htmlspecialchars($projectInfo['tagline']) : __('login.tagline') ?></p>
                </div>

                <h2 class="login-form-title"><?= __('login.form_title') ?></h2>
                <p class="login-form-subtitle"><?= __('login.form_subtitle') ?></p>

                <?php if ($error): ?>
                <div class="alert alert-danger" role="alert" id="login-error">
                    <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
                    <span class="alert-content"><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="<?= url('login') ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="login-field">
                        <label for="username" class="form-label"><?= __('login.field_username') ?></label>
                        <input type="text"
                               class="form-control <?= $error ? 'form-control-error' : '' ?>"
                               id="username"
                               name="username"
                               placeholder="<?= __('login.placeholder_user') ?>"
                               autocomplete="username"
                               required
                               autofocus
                               <?php if ($error): ?>aria-invalid="true" aria-describedby="login-error"<?php endif; ?>
                               value="<?= isset($_POST['username']) ? htmlspecialchars(sanitize($_POST['username'])) : '' ?>">
                    </div>

                    <div class="login-field">
                        <label for="password" class="form-label"><?= __('login.field_password') ?></label>
                        <input type="password"
                               class="form-control <?= $error ? 'form-control-error' : '' ?>"
                               id="password"
                               name="password"
                               placeholder="<?= __('login.placeholder_pass') ?>"
                               autocomplete="current-password"
                               required
                               <?php if ($error): ?>aria-invalid="true" aria-describedby="login-error"<?php endif; ?>>
                    </div>

                    <button type="submit" class="btn btn-primary login-submit">
                        <?= __('login.btn_submit') ?>
                    </button>
                </form>

                <!-- Usuarios demo -->
                <div class="login-demo">
                    <p class="login-demo-title"><?= __('login.demo_title') ?></p>
                    <ul class="login-demo-list">
                        <li>Admin: <code>admin / password</code></li>
                        <li>Editor: <code>editor1 / password</code></li>
                        <li>Viewer: <code>viewer / password</code></li>
                    </ul>
                </div>

                <!-- Selector de idioma -->
                <div class="login-lang">
                    <a class="login-lang-btn <?= $lang === 'es' ? 'active' : '' ?>" href="?lang=es">Español</a>
                    <a class="login-lang-btn <?= $lang === 'en' ? 'active' : '' ?>" href="?lang=en">English</a>
                </div>

            </div>
        </div>

    </div>

</body>
</html>
