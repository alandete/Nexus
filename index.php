<?php
/**
 * Nexus 2.0 — Punto de entrada principal
 * PHP 8+ | Atlassian Design System
 */

// Constantes
define('APP_ACCESS', true);
define('APP_VERSION', '2.0.0-alpha.2');
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

// Aplicar timezone configurada
if (!empty($projectInfo['timezone']) && in_array($projectInfo['timezone'], timezone_identifiers_list(), true)) {
    date_default_timezone_set($projectInfo['timezone']);
}

// Idioma por defecto para visitantes sin sesion (override al de config.php si esta definido)
if (empty($_SESSION['lang']) && !empty($projectInfo['default_lang']) && in_array($projectInfo['default_lang'], ['es', 'en'], true)) {
    $_SESSION['lang'] = $projectInfo['default_lang'];
    $lang = $projectInfo['default_lang'];
}

// Routing
$page = isset($_GET['page']) ? sanitize($_GET['page']) : 'home';
$validPages = [
    'home', 'tasks', 'alliances', 'utilities', 'documentation',
    'reports',
    'settings', 'users', 'manage-alliances', 'manage-tasks', 'application', 'integrations',
    'snapshots', 'system', 'activity',
    'login', 'logout',
    '403', '404', '500',
];

if (!in_array($page, $validPages)) {
    http_response_code(404);
    $page = '404';
}

// Logout
if ($page === 'logout') {
    logActivity('auth', 'logout', getCurrentUser()['username'] ?? '');
    logout();
    header('Location: ' . url('login'));
    exit;
}

// Proteger paginas (excepto login y paginas de error)
$publicPages = ['login', '403', '404', '500'];
if (!in_array($page, $publicPages, true) && !isLoggedIn()) {
    header('Location: ' . url('login'));
    exit;
}

// Login sin layout principal
if ($page === 'login') {
    include 'pages/login.php';
    exit;
}

// Paginas de error: standalone, sin sidebar ni topbar
if (in_array($page, ['403', '404', '500'], true)) {
    $errorCode = $page;
    include 'pages/error.php';
    exit;
}

// Modo mantenimiento: bloquear acceso excepto para admins y IPs permitidas
if (!empty($projectInfo['maintenance_mode'])) {
    $currentUser = getCurrentUser();
    $isAdmin = $currentUser && ($currentUser['role'] ?? '') === 'admin';
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowedIps = $projectInfo['maintenance_allowed_ips'] ?? [];
    $ipAllowed = in_array($clientIp, $allowedIps, true);
    $canBypassMaintenance = $isAdmin || $ipAllowed;

    if (!$canBypassMaintenance && !in_array($page, ['logout', 'application'], true)) {
        http_response_code(503);
        header('Retry-After: 3600');
        include 'pages/maintenance.php';
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <base href="/">

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

    <?php
    // Override de colores de marca si estan configurados
    $brandColor = $projectInfo['brand_color'] ?? null;
    $accentColor = $projectInfo['accent_color'] ?? null;
    if ($brandColor || $accentColor):
        $brandRgb = null;
        if ($brandColor && preg_match('/^#([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})([0-9A-Fa-f]{2})$/', $brandColor, $m)) {
            $brandRgb = hexdec($m[1]) . ', ' . hexdec($m[2]) . ', ' . hexdec($m[3]);
        }
    ?>
    <style id="appBrandOverride">
        :root {
            <?php if ($brandColor): ?>
            --app-brand: <?= htmlspecialchars($brandColor) ?>;
            <?php endif; ?>
            <?php if ($brandRgb): ?>
            --app-brand-rgb: <?= $brandRgb ?>;
            <?php endif; ?>
            <?php if ($accentColor): ?>
            --app-accent: <?= htmlspecialchars($accentColor) ?>;
            <?php endif; ?>
        }
    </style>
    <?php endif; ?>
</head>
<body class="app-layout">

    <a href="#main-content" class="skip-to-content"><?= __('a11y.skip_to_content') ?></a>

    <!-- Top Bar -->
    <?php include 'includes/header.php'; ?>

    <!-- Page Loader -->
    <div class="page-loader" id="pageLoader" aria-live="polite" aria-label="<?= __('a11y.loading') ?>">
        <div class="page-loader-spinner"></div>
    </div>

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

    <!-- Confirm modal -->
    <?php include 'includes/confirm-modal.php'; ?>

    <!-- JS -->
    <script src="assets/js/slide-panel.js?v=<?= filemtime('assets/js/slide-panel.js') ?>"></script>
    <script src="assets/js/confirm-modal.js?v=<?= filemtime('assets/js/confirm-modal.js') ?>"></script>
    <script src="assets/js/toast.js?v=<?= filemtime('assets/js/toast.js') ?>"></script>
    <script src="assets/js/scripts.js?v=<?= filemtime('assets/js/scripts.js') ?>"></script>

    <?php if ($page === 'home'): ?>
    <script src="assets/js/dashboard.js?v=<?= filemtime('assets/js/dashboard.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'documentation'): ?>
    <script src="assets/js/docs.js?v=<?= filemtime('assets/js/docs.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'users'): ?>
    <script src="assets/js/users.js?v=<?= filemtime('assets/js/users.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'manage-alliances'): ?>
    <script src="assets/js/manage-alliances.js?v=<?= filemtime('assets/js/manage-alliances.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'manage-tasks'): ?>
    <script src="assets/js/manage-tasks.js?v=<?= filemtime('assets/js/manage-tasks.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'reports'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/litepicker@2.0.12/dist/litepicker.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/litepicker@2.0.12/dist/plugins/ranges.js"></script>
    <script src="assets/js/reports.js?v=<?= filemtime('assets/js/reports.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'application'): ?>
    <script src="assets/js/application.js?v=<?= filemtime('assets/js/application.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'activity'): ?>
    <script src="assets/js/activity.js?v=<?= filemtime('assets/js/activity.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'snapshots'): ?>
    <script src="assets/js/snapshots.js?v=<?= filemtime('assets/js/snapshots.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'system'): ?>
    <script src="assets/js/system.js?v=<?= filemtime('assets/js/system.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'tasks'): ?>
    <script src="assets/js/tasks.js?v=<?= filemtime('assets/js/tasks.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'utilities'): ?>
    <script src="assets/js/gift.js?v=<?= filemtime('assets/js/gift.js') ?>"></script>
    <script src="assets/js/pdf-optimizer.js?v=<?= filemtime('assets/js/pdf-optimizer.js') ?>"></script>
    <script src="assets/js/image-optimizer.js?v=<?= filemtime('assets/js/image-optimizer.js') ?>"></script>
    <?php endif; ?>

    <?php if ($page === 'integrations'): ?>
    <script src="assets/js/integrations.js?v=<?= filemtime('assets/js/integrations.js') ?>"></script>
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
