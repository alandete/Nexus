<?php
/**
 * Nexus 2.0 — Pagina de mantenimiento
 * Standalone: se muestra sin layout cuando maintenance_mode esta activo
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$msg = trim($projectInfo['maintenance_message'] ?? '');
if ($msg === '') {
    $msg = __('application.maintenance_default_msg');
}
$appName = $projectInfo['app_name'] ?? 'Nexus';
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($appName) ?> — <?= __('application.maintenance_title') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/variables.css?v=<?= filemtime('assets/css/variables.css') ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= filemtime('assets/css/styles.css') ?>">
</head>
<body class="maintenance-page">

    <main class="maintenance-container" role="main">
        <div class="maintenance-icon" aria-hidden="true">
            <i class="bi bi-tools"></i>
        </div>
        <h1 class="maintenance-title"><?= __('application.maintenance_title') ?></h1>
        <p class="maintenance-message"><?= nl2br(htmlspecialchars($msg)) ?></p>
        <p class="maintenance-app"><?= htmlspecialchars($appName) ?></p>

        <?php if (!isLoggedIn()): ?>
        <a href="<?= url('login') ?>" class="btn btn-subtle btn-sm maintenance-login-link">
            <i class="bi bi-shield-lock" aria-hidden="true"></i>
            <?= __('application.maintenance_admin_access') ?>
        </a>
        <?php endif; ?>
    </main>

</body>
</html>
