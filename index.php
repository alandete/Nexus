<?php
/**
 * Nexus 2.0 — Punto de entrada principal
 * PHP 8+ | Atlassian Design System
 */

// Constantes
define('APP_ACCESS', true);
define('APP_VERSION', '2.0.0');
define('APP_NAME', 'Nexus');

// Configuracion
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

// Migraciones de BD
runMigrations();

// Headers de seguridad HTTP
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

// Cambio de idioma via ?lang=
if (isset($_GET['lang']) && in_array($_GET['lang'], ['es', 'en'])) {
    $_SESSION['lang'] = $_GET['lang'];
    $cleanUrl = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $cleanUrl);
    exit;
}

// Token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Informacion del proyecto
$projectInfo = getProjectInfo();

// Routing
$page = isset($_GET['page']) ? sanitize($_GET['page']) : 'home';
$validPages = ['home', 'tasks', 'alliances', 'utilities', 'settings', 'users', 'login', 'logout'];

if (!in_array($page, $validPages)) {
    $page = 'home';
}

// Logout
if ($page === 'logout') {
    logActivity('auth', 'logout', getCurrentUser()['username'] ?? '');
    logout();
    header('Location: ' . url('login'));
    exit;
}

// Redirigir /users a settings#usuarios
if ($page === 'users') {
    header('Location: ' . url('settings') . '#usuarios');
    exit;
}

// Proteger paginas (excepto login)
if ($page !== 'login' && !isLoggedIn()) {
    header('Location: ' . url('login'));
    exit;
}

// Login sin layout principal
if ($page === 'login') {
    include 'pages/login.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
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
    <title><?= htmlspecialchars($projectInfo['app_name']) ?><?= !empty($projectInfo['tagline']) ? ' - ' . htmlspecialchars($projectInfo['tagline']) : '' ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">

    <?php
    $canonicalBase = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    $canonicalUrl = $canonicalBase . '/' . ($page !== 'home' ? urlencode($page) : '');
    ?>
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">

    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($projectInfo['app_name']) ?><?= !empty($projectInfo['tagline']) ? ' - ' . htmlspecialchars($projectInfo['tagline']) : '' ?>">
    <meta property="og:description" content="<?= htmlspecialchars($projectInfo['description'] ?: __('site_title')) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <?php if (!empty($projectInfo['logo'])): ?>
    <meta property="og:image" content="<?= htmlspecialchars($canonicalBase . '/assets/uploads/logos/' . $projectInfo['logo']) ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="<?= htmlspecialchars($projectInfo['app_name']) ?>">
    <meta property="og:locale" content="<?= $lang === 'es' ? 'es_ES' : 'en_US' ?>">

    <?php if (!empty($projectInfo['logo'])): ?>
    <link rel="icon" type="image/png" href="assets/uploads/logos/<?= htmlspecialchars($projectInfo['logo']) ?>">
    <?php endif; ?>

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Nexus 2.0 CSS -->
    <link rel="stylesheet" href="assets/css/variables.css?v=<?= filemtime('assets/css/variables.css') ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= filemtime('assets/css/styles.css') ?>">
</head>
<body class="app-layout">

    <a href="#main-content" class="skip-to-content"><?= __('a11y.skip_to_content') ?></a>

    <!-- Top Bar -->
    <?php include 'includes/header.php'; ?>

    <!-- App Shell: Sidebar + Content -->
    <div class="app-shell">
        <!-- Sidebar Navigation -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content" id="main-content">
            <?php
            $pageFile = "pages/{$page}.php";
            if (file_exists($pageFile)) {
                include $pageFile;
            } else {
                include 'pages/home.php';
            }
            ?>
        </main>
    </div>

    <!-- Slide Panel -->
    <?php include 'includes/slide-panel.php'; ?>

    <!-- JS -->
    <script src="assets/js/slide-panel.js?v=<?= filemtime('assets/js/slide-panel.js') ?>"></script>
    <script src="assets/js/scripts.js?v=<?= filemtime('assets/js/scripts.js') ?>"></script>

    <?php if ($page === 'home'): ?>
    <script src="assets/js/dashboard.js?v=<?= filemtime('assets/js/dashboard.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'tasks'): ?>
    <script src="assets/js/tasks.js?v=<?= filemtime('assets/js/tasks.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'utilities'): ?>
    <script src="assets/js/gift.js?v=<?= filemtime('assets/js/gift.js') ?>"></script>
    <script src="assets/js/pdf-optimizer.js?v=<?= filemtime('assets/js/pdf-optimizer.js') ?>"></script>
    <script src="assets/js/image-optimizer.js?v=<?= filemtime('assets/js/image-optimizer.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'settings'): ?>
    <script src="assets/js/api-settings.js?v=<?= filemtime('assets/js/api-settings.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page !== 'tasks'): ?>
    <a href="<?= url('tasks') ?>" class="timer-floater" id="timerFloater">
        <span class="floater-dot"></span>
        <span id="floaterAlliance" class="floater-alliance"></span>
        <span id="floaterTask" class="floater-task"></span>
        <span id="floaterTime" class="floater-time">00:00:00</span>
    </a>
    <?php endif; ?>

    <button type="button" class="btn-scroll-top" id="btnScrollTop" aria-label="<?= __('a11y.scroll_to_top') ?>" data-tooltip="<?= __('a11y.scroll_to_top') ?>" data-tooltip-position="left">
        <i class="bi bi-chevron-up" aria-hidden="true"></i>
    </button>

</body>
</html>
