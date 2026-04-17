<?php
/**
 * Nexus 2.0 — Error page (standalone wrapper)
 * Renderiza 403 / 404 / 500 sin layout principal
 *
 * Recibe $errorCode desde index.php (ej: '403', '404', '500')
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$errorCode = $errorCode ?? '404';
if (!in_array($errorCode, ['403', '404', '500'], true)) {
    $errorCode = '404';
}

$appName = $projectInfo['app_name'] ?? 'Nexus';

$errorConfig = [
    '404' => ['icon' => 'bi-compass',            'variant' => 'info'],
    '403' => ['icon' => 'bi-shield-lock',        'variant' => 'warning'],
    '500' => ['icon' => 'bi-exclamation-octagon', 'variant' => 'danger'],
];
$cfg = $errorConfig[$errorCode];
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($appName) ?> — <?= htmlspecialchars(__('errors.' . $errorCode . '_title')) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/variables.css?v=<?= filemtime('assets/css/variables.css') ?>">
    <link rel="stylesheet" href="assets/css/styles.css?v=<?= filemtime('assets/css/styles.css') ?>">
</head>
<body class="error-standalone">

    <main class="error-container" role="main">
        <div class="error-standalone-icon error-standalone-icon-<?= $cfg['variant'] ?>" aria-hidden="true">
            <i class="bi <?= $cfg['icon'] ?>"></i>
        </div>
        <div class="error-standalone-code"><?= $errorCode ?></div>
        <h1 class="error-standalone-title"><?= htmlspecialchars(__('errors.' . $errorCode . '_title')) ?></h1>
        <p class="error-standalone-desc"><?= htmlspecialchars(__('errors.' . $errorCode . '_description')) ?></p>

        <div class="error-standalone-actions">
            <a href="<?= url('home') ?>" class="btn btn-primary">
                <i class="bi bi-house" aria-hidden="true"></i>
                <?= __('errors.go_home') ?>
            </a>
            <button type="button" class="btn btn-subtle" onclick="history.back()">
                <i class="bi bi-arrow-left" aria-hidden="true"></i>
                <?= __('errors.go_back') ?>
            </button>
        </div>
    </main>

</body>
</html>
