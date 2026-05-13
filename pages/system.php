<?php
/**
 * Nexus 2.0 — Ajustes > Sistema
 * Diagnostico, dependencias, base de datos, informacion PHP
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();

if (($currentUser['role'] ?? '') !== 'admin') {
    http_response_code(403);
    include 'pages/error.php';
    return;
}

// Cache de diagnostico (ultima ejecucion)
$diagCachePath = DATA_PATH . '/diagnostics_cache.json';
$diagCache = [];
if (file_exists($diagCachePath)) {
    $diagCache = json_decode(file_get_contents($diagCachePath), true) ?: [];
}

$lastDiagRun = $diagCache['timestamp'] ?? null;
$summary = $diagCache['summary'] ?? ['ok' => 0, 'warning' => 0, 'error' => 0, 'info' => 0];
$checks  = $diagCache['checks'] ?? [];

// Cache de dependencias
$depsCachePath = DATA_PATH . '/deps_check.json';
$depsCache = [];
if (file_exists($depsCachePath)) {
    $depsCache = json_decode(file_get_contents($depsCachePath), true) ?: [];
}
$depsChecked = $depsCache['checked_at'] ?? null;
$depsPhp = $depsCache['php'] ?? phpversion();
$deps = $depsCache['deps'] ?? [];

// Detección en tiempo real cuando no hay cache (ej. primer arranque en servidor Linux)
if (empty($deps)) {
    $deps = ['ghostscript' => [], 'imagemagick' => [], 'imagick_ext' => [], 'gd_ext' => []];

    if (extension_loaded('gd')) {
        $gdInfo = function_exists('gd_info') ? gd_info() : [];
        $deps['gd_ext']['installed'] = $gdInfo['GD Version'] ?? 'disponible';
    }
    if (extension_loaded('imagick')) {
        $deps['imagick_ext']['installed'] = 'disponible';
    }

    if (PHP_OS_FAMILY === 'Windows') {
        foreach (['gswin64c', 'gswin32c', 'gs'] as $cmd) {
            $out = @shell_exec("where {$cmd} 2>&1");
            if ($out) { $line = trim(explode("\n", $out)[0]); if (@file_exists($line)) { $deps['ghostscript']['installed'] = $cmd; break; } }
        }
        $out = @shell_exec('where magick 2>&1') ?: @shell_exec('where convert 2>&1');
        if ($out) { $line = trim(explode("\n", $out)[0]); if (@file_exists($line)) $deps['imagemagick']['installed'] = 'disponible'; }
    } else {
        $gsCode = -1; $gsOut = [];
        if (function_exists('exec'))       { @exec('gs --version 2>/dev/null', $gsOut, $gsCode); }
        if ($gsCode === 0 && !empty($gsOut)) {
            $deps['ghostscript']['installed'] = trim($gsOut[0]);
        } elseif (function_exists('shell_exec')) {
            $o = @shell_exec('gs --version 2>/dev/null');
            if (trim((string)$o) !== '') $deps['ghostscript']['installed'] = trim($o);
        }

        $imCode = -1; $imOut = [];
        if (function_exists('exec'))       { @exec('convert --version 2>/dev/null', $imOut, $imCode); }
        if ($imCode !== 0 && function_exists('exec')) { @exec('magick --version 2>/dev/null', $imOut, $imCode); }
        if ($imCode === 0 && !empty($imOut)) {
            preg_match('/ImageMagick\s+([\d.\-]+)/', $imOut[0], $m);
            $deps['imagemagick']['installed'] = $m[1] ?? 'disponible';
        } elseif (function_exists('shell_exec')) {
            $o = @shell_exec('convert --version 2>/dev/null') ?: @shell_exec('magick --version 2>/dev/null');
            if (trim((string)$o) !== '') {
                preg_match('/ImageMagick\s+([\d.\-]+)/', $o, $m);
                $deps['imagemagick']['installed'] = $m[1] ?? 'disponible';
            }
        }
    }

    // Persistir para no re-detectar en cada carga
    @file_put_contents($depsCachePath, json_encode([
        'checked_at' => date('Y-m-d H:i:s'),
        'php'        => phpversion(),
        'deps'       => $deps,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $depsChecked = date('Y-m-d H:i:s');
}

// Info de PHP y entorno
$phpExtensions = [
    'json'       => extension_loaded('json'),
    'openssl'    => extension_loaded('openssl'),
    'gd'         => extension_loaded('gd'),
    'session'    => extension_loaded('session'),
    'zip'        => extension_loaded('zip'),
    'curl'       => extension_loaded('curl'),
    'pdo'        => extension_loaded('pdo'),
    'pdo_mysql'  => extension_loaded('pdo_mysql'),
    'mbstring'   => extension_loaded('mbstring'),
    'intl'       => extension_loaded('intl'),
];

// Info BD (sin password)
$dbInfo = null;
if (file_exists(__DIR__ . '/../config/database.php')) {
    $dbConf = require __DIR__ . '/../config/database.php';
    $dbInfo = [
        'host' => $dbConf['host'] ?? '',
        'port' => $dbConf['port'] ?? '3306',
        'name' => $dbConf['dbname'] ?? ($dbConf['database'] ?? ''),
        'user' => $dbConf['username'] ?? '',
    ];
}
$dbAvailable = isDBAvailable();
$dbVersion = null;
if ($dbAvailable) {
    try {
        $dbVersion = getDB()->query('SELECT VERSION()')->fetchColumn();
    } catch (Exception $e) { /* ignore */ }
}

// Permisos de directorios clave
$writables = [
    'data/'                     => is_writable(DATA_PATH),
    'backups/'                  => is_writable(BACKUP_PATH),
    'assets/uploads/avatars/'   => is_writable(BASE_PATH . '/assets/uploads/avatars'),
    'assets/uploads/logos/'     => is_writable(BASE_PATH . '/assets/uploads/logos'),
    'assets/uploads/alliances/' => is_writable(BASE_PATH . '/assets/uploads/alliances'),
];
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= url('settings') ?>" class="breadcrumb-link"><?= __('menu.settings') ?></a>
    <i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>
    <span class="breadcrumb-current" aria-current="page"><?= __('menu.system') ?></span>
</nav>

<div class="page-header">
    <h1 class="page-title"><?= __('system.page_title') ?></h1>
    <p class="page-description"><?= __('system.page_subtitle') ?></p>
</div>

<!-- ============ DIAGNOSTICO ============ -->
<section class="card system-section" aria-labelledby="sec-diag-title">
    <header class="system-section-header">
        <div>
            <h2 class="system-section-title" id="sec-diag-title"><?= __('system.sec_diagnostics') ?></h2>
            <p class="system-section-desc">
                <?php if ($lastDiagRun): ?>
                <?= __('system.last_run') ?>: <span id="diagLastRun" title="<?= htmlspecialchars($lastDiagRun) ?>"><?= htmlspecialchars(relativeTime($lastDiagRun)) ?></span>
                <?php else: ?>
                <?= __('system.never_run') ?>
                <?php endif; ?>
            </p>
        </div>
        <button type="button" class="btn btn-primary" id="btnRunDiag">
            <i class="bi bi-play-fill" aria-hidden="true"></i>
            <span class="btn-text"><?= __('system.btn_run') ?></span>
        </button>
    </header>

    <!-- Resumen -->
    <div class="system-summary" id="diagSummary" aria-live="polite">
        <div class="system-summary-item system-summary-ok" data-count="ok">
            <div class="system-summary-icon"><i class="bi bi-check-circle-fill" aria-hidden="true"></i></div>
            <div class="system-summary-body">
                <span class="system-summary-value"><?= (int) $summary['ok'] ?></span>
                <span class="system-summary-label"><?= __('system.status_ok') ?></span>
            </div>
        </div>
        <div class="system-summary-item system-summary-warning" data-count="warning">
            <div class="system-summary-icon"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i></div>
            <div class="system-summary-body">
                <span class="system-summary-value"><?= (int) $summary['warning'] ?></span>
                <span class="system-summary-label"><?= __('system.status_warning') ?></span>
            </div>
        </div>
        <div class="system-summary-item system-summary-error" data-count="error">
            <div class="system-summary-icon"><i class="bi bi-x-circle-fill" aria-hidden="true"></i></div>
            <div class="system-summary-body">
                <span class="system-summary-value"><?= (int) $summary['error'] ?></span>
                <span class="system-summary-label"><?= __('system.status_error') ?></span>
            </div>
        </div>
    </div>

    <!-- Lista de checks con issues -->
    <div class="system-checks" id="diagChecks">
        <?php
        $issues = array_filter($checks, fn($c) => in_array($c['status'] ?? '', ['error', 'warning'], true));
        if (empty($issues) && !empty($checks)):
        ?>
        <div class="system-all-ok">
            <i class="bi bi-check-circle" aria-hidden="true"></i>
            <span><?= __('system.all_ok') ?></span>
        </div>
        <?php elseif (!empty($issues)): foreach ($issues as $check):
            $st = $check['status'] ?? 'info';
            $icon = match($st) { 'error' => 'bi-x-circle-fill', 'warning' => 'bi-exclamation-triangle-fill', default => 'bi-info-circle-fill' };
        ?>
        <div class="system-check system-check-<?= $st ?>">
            <i class="bi <?= $icon ?> system-check-icon" aria-hidden="true"></i>
            <div class="system-check-body">
                <div class="system-check-header">
                    <span class="system-check-category text-subtle text-sm"><?= htmlspecialchars($check['category'] ?? '') ?></span>
                    <span class="system-check-name"><?= htmlspecialchars($check['name'] ?? '') ?></span>
                </div>
                <?php if (!empty($check['detail'])): ?>
                <p class="system-check-detail"><?= htmlspecialchars($check['detail']) ?></p>
                <?php endif; ?>
                <?php if (!empty($check['fix'])): ?>
                <div class="system-check-fix">
                    <i class="bi bi-lightbulb-fill" aria-hidden="true"></i>
                    <span><?= htmlspecialchars($check['fix']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>
</section>

<!-- ============ DEPENDENCIAS ============ -->
<section class="card system-section" aria-labelledby="sec-deps-title">
    <header class="system-section-header">
        <div>
            <h2 class="system-section-title" id="sec-deps-title"><?= __('system.sec_dependencies') ?></h2>
            <p class="system-section-desc"><?= __('system.sec_dependencies_desc') ?></p>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
            <?php if ($depsChecked): ?>
            <span class="text-subtle text-sm" id="depsCheckedAt" title="<?= htmlspecialchars($depsChecked) ?>">
                <i class="bi bi-clock" aria-hidden="true"></i>
                <?= htmlspecialchars(relativeTime($depsChecked)) ?>
            </span>
            <?php endif; ?>
            <button type="button" class="btn btn-secondary btn-sm" id="btnCheckDeps">
                <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                <span class="btn-text"><?= __('system.btn_check_deps') ?></span>
            </button>
        </div>
    </header>

    <div class="system-deps-grid" id="depsGrid">
        <?php
        $depItems = [
            'ghostscript' => ['label' => 'Ghostscript',            'use' => __('system.deps_use_pdf'),  'icon' => 'bi-file-earmark-pdf'],
            'imagemagick' => ['label' => 'ImageMagick CLI',        'use' => __('system.deps_use_img'),  'icon' => 'bi-image'],
            'imagick_ext' => ['label' => 'Imagick (PHP)',          'use' => __('system.deps_use_img'),  'icon' => 'bi-image-fill'],
            'gd_ext'      => ['label' => 'GD (PHP)',               'use' => __('system.deps_use_img'),  'icon' => 'bi-image'],
        ];
        foreach ($depItems as $key => $info):
            $installed = $deps[$key]['installed'] ?? null;
            $isOk = !empty($installed);
        ?>
        <div class="system-dep <?= $isOk ? 'system-dep-ok' : 'system-dep-missing' ?>" data-dep="<?= $key ?>">
            <i class="bi <?= $info['icon'] ?> system-dep-icon" aria-hidden="true"></i>
            <div class="system-dep-body">
                <div class="system-dep-name"><?= $info['label'] ?></div>
                <div class="system-dep-version">
                    <?php if ($isOk): ?>
                    <span class="lozenge lozenge-success"><?= __('system.deps_installed') ?></span>
                    <code class="text-sm text-subtle"><?= htmlspecialchars($installed) ?></code>
                    <?php else: ?>
                    <span class="lozenge lozenge-warning"><?= __('system.deps_missing') ?></span>
                    <?php endif; ?>
                </div>
                <p class="system-dep-use text-sm text-subtle"><?= $info['use'] ?></p>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- MySQL -->
        <div class="system-dep <?= $dbAvailable ? 'system-dep-ok' : 'system-dep-missing' ?>">
            <i class="bi bi-database-fill system-dep-icon" aria-hidden="true"></i>
            <div class="system-dep-body">
                <div class="system-dep-name">MySQL</div>
                <div class="system-dep-version">
                    <?php if ($dbAvailable): ?>
                    <span class="lozenge lozenge-success"><?= __('system.deps_connected') ?></span>
                    <?php if ($dbVersion): ?>
                    <code class="text-sm text-subtle"><?= htmlspecialchars($dbVersion) ?></code>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="lozenge lozenge-danger"><?= __('system.deps_no_connection') ?></span>
                    <?php endif; ?>
                </div>
                <p class="system-dep-use text-sm text-subtle"><?= __('system.deps_use_db') ?></p>
            </div>
        </div>
    </div>

    <?php if (PHP_OS_FAMILY === 'Windows'): ?>
    <div class="alert alert-info">
        <i class="bi bi-terminal alert-icon" aria-hidden="true"></i>
        <span class="alert-content">
            <?= __('system.deps_install_hint') ?>
            <code>php setup-deps.php</code>
        </span>
    </div>
    <?php endif; ?>
</section>

<!-- ============ ENTORNO PHP ============ -->
<section class="card system-section" aria-labelledby="sec-php-title">
    <header class="system-section-header">
        <div>
            <h2 class="system-section-title" id="sec-php-title"><?= __('system.sec_php') ?></h2>
            <p class="system-section-desc"><?= __('system.sec_php_desc') ?></p>
        </div>
    </header>

    <div class="system-info-grid">
        <div class="system-info-item">
            <span class="system-info-label"><?= __('system.php_version') ?></span>
            <strong class="system-info-value"><?= htmlspecialchars($depsPhp) ?></strong>
        </div>
        <div class="system-info-item">
            <span class="system-info-label"><?= __('system.memory_limit') ?></span>
            <strong class="system-info-value"><?= htmlspecialchars(ini_get('memory_limit')) ?></strong>
        </div>
        <div class="system-info-item">
            <span class="system-info-label"><?= __('system.upload_max') ?></span>
            <strong class="system-info-value"><?= htmlspecialchars(ini_get('upload_max_filesize')) ?></strong>
        </div>
        <div class="system-info-item">
            <span class="system-info-label"><?= __('system.post_max') ?></span>
            <strong class="system-info-value"><?= htmlspecialchars(ini_get('post_max_size')) ?></strong>
        </div>
        <div class="system-info-item">
            <span class="system-info-label"><?= __('system.max_execution') ?></span>
            <strong class="system-info-value"><?= htmlspecialchars(ini_get('max_execution_time')) ?>s</strong>
        </div>
        <div class="system-info-item">
            <span class="system-info-label"><?= __('system.timezone') ?></span>
            <strong class="system-info-value"><?= htmlspecialchars(date_default_timezone_get()) ?></strong>
        </div>
    </div>

    <h3 class="system-subsection-title"><?= __('system.extensions') ?></h3>
    <div class="system-ext-grid">
        <?php foreach ($phpExtensions as $ext => $loaded): ?>
        <div class="system-ext <?= $loaded ? 'system-ext-ok' : 'system-ext-missing' ?>">
            <i class="bi <?= $loaded ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>" aria-hidden="true"></i>
            <code><?= htmlspecialchars($ext) ?></code>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- ============ BASE DE DATOS ============ -->
<section class="card system-section" aria-labelledby="sec-db-title">
    <header class="system-section-header">
        <div>
            <h2 class="system-section-title" id="sec-db-title"><?= __('system.sec_database') ?></h2>
            <p class="system-section-desc"><?= __('system.sec_database_desc') ?></p>
        </div>
        <?php if ($dbAvailable): ?>
        <span class="lozenge lozenge-success">
            <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
            <?= __('system.db_connected') ?>
        </span>
        <?php else: ?>
        <span class="lozenge lozenge-danger">
            <i class="bi bi-x-circle-fill" aria-hidden="true"></i>
            <?= __('system.db_disconnected') ?>
        </span>
        <?php endif; ?>
    </header>

    <?php if ($dbInfo): ?>
    <div class="system-info-grid">
        <div class="system-info-item">
            <span class="system-info-label"><?= __('system.db_host') ?></span>
            <strong class="system-info-value"><?= htmlspecialchars($dbInfo['host']) ?>:<?= htmlspecialchars($dbInfo['port']) ?></strong>
        </div>
        <div class="system-info-item">
            <span class="system-info-label"><?= __('system.db_name') ?></span>
            <strong class="system-info-value"><?= htmlspecialchars($dbInfo['name']) ?></strong>
        </div>
        <div class="system-info-item">
            <span class="system-info-label"><?= __('system.db_user') ?></span>
            <strong class="system-info-value"><?= htmlspecialchars($dbInfo['user']) ?></strong>
        </div>
        <?php if ($dbVersion): ?>
        <div class="system-info-item">
            <span class="system-info-label"><?= __('system.db_version') ?></span>
            <strong class="system-info-value"><?= htmlspecialchars($dbVersion) ?></strong>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle alert-icon" aria-hidden="true"></i>
        <span class="alert-content"><?= __('system.db_no_config') ?></span>
    </div>
    <?php endif; ?>
</section>

<!-- ============ PERMISOS DE ARCHIVOS ============ -->
<section class="card system-section" aria-labelledby="sec-perms-title">
    <header class="system-section-header">
        <div>
            <h2 class="system-section-title" id="sec-perms-title"><?= __('system.sec_permissions') ?></h2>
            <p class="system-section-desc"><?= __('system.sec_permissions_desc') ?></p>
        </div>
    </header>

    <div class="system-perms-list">
        <?php foreach ($writables as $path => $writable): ?>
        <div class="system-perm <?= $writable ? 'system-perm-ok' : 'system-perm-error' ?>">
            <i class="bi <?= $writable ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>" aria-hidden="true"></i>
            <code><?= htmlspecialchars($path) ?></code>
            <span class="lozenge <?= $writable ? 'lozenge-success' : 'lozenge-danger' ?>">
                <?= $writable ? __('system.perm_writable') : __('system.perm_readonly') ?>
            </span>
        </div>
        <?php endforeach; ?>
    </div>
</section>
