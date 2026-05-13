<?php
/**
 * Nexus — Instalador Web
 * Se bloquea automáticamente una vez que config/database.php existe.
 */

$configDb     = __DIR__ . '/config/database.php';
$configSecret = __DIR__ . '/config/secret.php';

// Ya instalado → redirigir a la app
if (file_exists($configDb)) {
    header('Location: ./');
    exit;
}

// ── Acciones AJAX ─────────────────────────────────────────────────────────────

if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'];

    // ── Paso 1: Verificar requisitos ─────────────────────────────────────────
    if ($action === 'requirements') {
        $checks = [];

        $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
        $checks[] = [
            'label'  => 'PHP 8.0 o superior',
            'detail' => 'Versión actual: ' . PHP_VERSION,
            'ok'     => $phpOk,
        ];

        foreach (['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'] as $ext) {
            $ok = extension_loaded($ext);
            $checks[] = [
                'label'  => "Extensión $ext",
                'detail' => $ok ? 'Disponible' : 'No encontrada',
                'ok'     => $ok,
            ];
        }

        foreach (['config' => __DIR__ . '/config', 'data' => __DIR__ . '/data'] as $label => $path) {
            $ok = is_dir($path) && is_writable($path);
            $checks[] = [
                'label'  => "Permisos de escritura en $label/",
                'detail' => $ok ? 'Permitidos' : 'Sin permisos o directorio inexistente',
                'ok'     => $ok,
            ];
        }

        $allOk = array_reduce($checks, fn($carry, $c) => $carry && $c['ok'], true);
        echo json_encode(['ok' => $allOk, 'checks' => $checks]);
        exit;
    }

    // ── Paso 2: Probar conexión a la BD ──────────────────────────────────────
    if ($action === 'test_db') {
        $host   = trim($input['host']   ?? 'localhost');
        $port   = (int)($input['port']  ?? 3306);
        $dbname = trim($input['dbname'] ?? '');
        $user   = trim($input['user']   ?? '');
        $pass   = $input['pass']        ?? '';

        if (!$dbname || !$user) {
            echo json_encode(['ok' => false, 'error' => 'Nombre de BD y usuario son requeridos']);
            exit;
        }

        try {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
            new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            echo json_encode(['ok' => true]);
        } catch (PDOException $e) {
            echo json_encode(['ok' => false, 'error' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Paso 3: Instalación completa ─────────────────────────────────────────
    if ($action === 'install') {
        $host     = trim($input['host']     ?? 'localhost');
        $port     = (int)($input['port']    ?? 3306);
        $dbname   = trim($input['dbname']   ?? '');
        $dbUser   = trim($input['dbUser']   ?? '');
        $dbPass   = $input['dbPass']        ?? '';
        $name     = trim($input['name']     ?? '');
        $email    = trim($input['email']    ?? '');
        $password = $input['password']      ?? '';
        $lang     = in_array($input['lang'] ?? '', ['es', 'en']) ? $input['lang'] : 'es';

        if (!$dbname || !$dbUser || !$name || !$email || strlen($password) < 8) {
            echo json_encode(['ok' => false, 'error' => 'Datos incompletos o contraseña muy corta (mínimo 8 caracteres)']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'error' => 'El email no es válido']);
            exit;
        }

        // Escribir config/secret.php
        $secretKey    = bin2hex(random_bytes(32));
        $secretContent = "<?php\n// Clave de encriptacion — unica por instalacion\ndefine('APP_SECRET_KEY', '{$secretKey}');\n";
        if (file_put_contents($configSecret, $secretContent) === false) {
            echo json_encode(['ok' => false, 'error' => 'No se pudo escribir config/secret.php — verifique permisos']);
            exit;
        }

        // Escribir config/database.php
        $cfg = [
            'host'     => $host,
            'port'     => $port,
            'dbname'   => $dbname,
            'username' => $dbUser,
            'password' => $dbPass,
            'charset'  => 'utf8mb4',
        ];
        $dbContent = "<?php\n/**\n * Nexus — Configuración de base de datos (generado por el instalador)\n */\nreturn " . var_export($cfg, true) . ";\n";
        if (file_put_contents($configDb, $dbContent) === false) {
            @unlink($configSecret);
            echo json_encode(['ok' => false, 'error' => 'No se pudo escribir config/database.php — verifique permisos']);
            exit;
        }

        // Bootstrapping mínimo para functions.php
        if (!defined('APP_ACCESS'))          define('APP_ACCESS', true);
        if (!defined('BASE_PATH'))           define('BASE_PATH', __DIR__);
        if (!defined('DATA_PATH'))           define('DATA_PATH', __DIR__ . '/data');
        if (!defined('BACKUP_PATH'))         define('BACKUP_PATH', __DIR__ . '/backups');
        if (!defined('LANG_PATH'))           define('LANG_PATH', __DIR__ . '/lang');
        if (!defined('TEMPLATES_PATH'))      define('TEMPLATES_PATH', __DIR__ . '/templates');
        if (!defined('AVATARS_PATH'))        define('AVATARS_PATH', __DIR__ . '/assets/uploads/avatars');
        if (!defined('LOGOS_PATH'))          define('LOGOS_PATH', __DIR__ . '/assets/uploads/logos');
        if (!defined('ALLIANCES_UPLOADS_PATH')) define('ALLIANCES_UPLOADS_PATH', __DIR__ . '/assets/uploads/alliances');
        if (!defined('ACTIVITY_LOG_FILE'))   define('ACTIVITY_LOG_FILE', __DIR__ . '/data/activity_log.json');
        if (!defined('MAX_LOG_ENTRIES'))     define('MAX_LOG_ENTRIES', 1000);
        if (!defined('USERS_FILE'))          define('USERS_FILE', __DIR__ . '/data/users.json');
        if (!defined('ROLES_FILE'))          define('ROLES_FILE', __DIR__ . '/data/roles.json');
        if (!defined('ALLIANCES_FILE'))      define('ALLIANCES_FILE', __DIR__ . '/data/alliances.json');
        if (!defined('PROJECTINFO_FILE'))    define('PROJECTINFO_FILE', __DIR__ . '/data/projectinfo.json');
        if (!defined('API_SETTINGS_FILE'))   define('API_SETTINGS_FILE', __DIR__ . '/data/api_settings.json');
        if (!defined('APP_SECRET_KEY'))      define('APP_SECRET_KEY', $secretKey);
        if (!defined('MAX_BACKUPS'))         define('MAX_BACKUPS', 3);

        $translations = [];
        require_once __DIR__ . '/includes/functions.php';

        // Copiar archivos .example.json a sus versiones reales (si no existen)
        foreach (glob(__DIR__ . '/data/*.example.json') as $src) {
            $dest = str_replace('.example.json', '.json', $src);
            if (!file_exists($dest)) {
                @copy($src, $dest);
            }
        }

        // Ejecutar migraciones
        if (!runMigrations()) {
            @unlink($configDb);
            @unlink($configSecret);
            echo json_encode(['ok' => false, 'error' => 'Error al ejecutar migraciones — verifique las credenciales de BD']);
            exit;
        }

        // Crear usuario administrador
        $db = getDB();
        if (!$db) {
            @unlink($configDb);
            @unlink($configSecret);
            echo json_encode(['ok' => false, 'error' => 'No se pudo conectar a la BD después de instalar']);
            exit;
        }

        $existingCount = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($existingCount === 0) {
            $username = strtolower(preg_replace('/[^a-z0-9._-]/i', '', strstr($email, '@', true) ?: $email));
            if (!$username) $username = 'admin';
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO users (username, password, name, email, role, lang, active) VALUES (?, ?, ?, ?, 'admin', ?, 1)")
               ->execute([$username, $hashed, $name, $email, $lang]);
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Acción desconocida']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalar Nexus</title>
    <link rel="stylesheet" href="assets/css/variables.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--ds-background-neutral);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
            color: var(--ds-text);
        }

        .installer-wrap {
            width: 100%;
            max-width: 520px;
            padding: 24px 16px;
        }

        /* Header */
        .installer-brand {
            text-align: center;
            margin-bottom: 32px;
        }
        .installer-brand-name {
            font-size: 28px;
            font-weight: 700;
            color: var(--app-brand);
            letter-spacing: -0.5px;
        }
        .installer-brand-sub {
            font-size: 13px;
            color: var(--ds-text-subtlest);
            margin-top: 4px;
        }

        /* Card */
        .installer-card {
            background: var(--ds-surface);
            border: 1px solid var(--ds-border);
            border-radius: 8px;
            overflow: hidden;
        }

        /* Stepper */
        .stepper {
            display: flex;
            border-bottom: 1px solid var(--ds-border);
            background: var(--ds-background-neutral);
        }
        .stepper-item {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 14px 16px;
            font-size: 13px;
            color: var(--ds-text-subtlest);
            border-bottom: 2px solid transparent;
            transition: color 0.15s, border-color 0.15s;
        }
        .stepper-item.active {
            color: var(--ds-text);
            border-bottom-color: var(--app-brand);
        }
        .stepper-item.done {
            color: var(--ds-text-success);
        }
        .stepper-num {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 1.5px solid currentColor;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 600;
            flex-shrink: 0;
        }
        .stepper-item.active .stepper-num {
            background: var(--app-brand);
            border-color: var(--app-brand);
            color: #fff;
        }
        .stepper-item.done .stepper-num {
            background: var(--ds-background-success);
            border-color: var(--ds-background-success);
        }
        .stepper-label { font-weight: 500; }

        /* Steps */
        .step { display: none; padding: 28px 24px; }
        .step.active { display: block; }

        /* Form */
        .field { margin-bottom: 16px; }
        .field label {
            display: block;
            font-weight: 500;
            margin-bottom: 5px;
            font-size: 13px;
            color: var(--ds-text-subtle);
        }
        .field input, .field select {
            width: 100%;
            padding: 8px 10px;
            border: 1.5px solid var(--ds-border);
            border-radius: 4px;
            font-size: 14px;
            background: var(--ds-background-input);
            color: var(--ds-text);
            transition: border-color 0.15s;
            outline: none;
        }
        .field input:focus, .field select:focus {
            border-color: var(--app-brand);
        }
        .field-row { display: flex; gap: 12px; }
        .field-row .field { flex: 1; }
        .field-row .field-port { flex: 0 0 100px; }

        /* Alerts */
        .alert {
            padding: 10px 14px;
            border-radius: 4px;
            font-size: 13px;
            margin-bottom: 16px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }
        .alert-error {
            background: var(--ds-background-danger);
            color: var(--ds-text-danger);
            border: 1px solid var(--ds-border-danger);
        }
        .alert-success {
            background: var(--ds-background-success);
            color: var(--ds-text-success);
            border: 1px solid var(--ds-border-success);
        }

        /* Requirements list */
        .req-list { list-style: none; padding: 0; margin: 0 0 8px; }
        .req-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid var(--ds-border-subtle);
            font-size: 13px;
        }
        .req-item:last-child { border-bottom: none; }
        .req-icon { font-size: 15px; flex-shrink: 0; }
        .req-ok   { color: var(--ds-text-success); }
        .req-fail { color: var(--ds-text-danger); }
        .req-detail { font-size: 12px; color: var(--ds-text-subtlest); margin-left: auto; }
        .req-loading { color: var(--ds-text-subtlest); font-size: 13px; padding: 16px 0; text-align: center; }

        /* Buttons */
        .btn-row {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 24px;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 18px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: background 0.15s, opacity 0.15s;
        }
        .btn:disabled { opacity: 0.45; cursor: not-allowed; }
        .btn-primary {
            background: var(--app-brand);
            color: #fff;
        }
        .btn-primary:not(:disabled):hover { filter: brightness(1.1); }
        .btn-secondary {
            background: var(--ds-background-neutral-hovered);
            color: var(--ds-text);
        }
        .btn-secondary:not(:disabled):hover { background: var(--ds-background-neutral-pressed); }
        .btn-ghost {
            background: transparent;
            color: var(--ds-text-subtle);
            padding: 8px 12px;
        }
        .btn-ghost:not(:disabled):hover { color: var(--ds-text); }
        .spin { animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        /* Step title */
        .step-title {
            font-size: 17px;
            font-weight: 600;
            color: var(--ds-text);
            margin: 0 0 6px;
        }
        .step-desc {
            font-size: 13px;
            color: var(--ds-text-subtle);
            margin: 0 0 20px;
        }

        /* Success state */
        .success-icon {
            font-size: 48px;
            color: var(--ds-text-success);
            text-align: center;
            margin-bottom: 16px;
        }
        .success-title {
            font-size: 20px;
            font-weight: 600;
            text-align: center;
            margin: 0 0 8px;
        }
        .success-desc {
            font-size: 13px;
            color: var(--ds-text-subtle);
            text-align: center;
            margin: 0 0 24px;
        }
    </style>
</head>
<body>
<div class="installer-wrap">
    <div class="installer-brand">
        <div class="installer-brand-name">Nexus</div>
        <div class="installer-brand-sub">Asistente de instalación</div>
    </div>

    <div class="installer-card">
        <!-- Stepper -->
        <div class="stepper" id="stepper">
            <div class="stepper-item active" data-step="1">
                <div class="stepper-num">1</div>
                <span class="stepper-label">Requisitos</span>
            </div>
            <div class="stepper-item" data-step="2">
                <div class="stepper-num">2</div>
                <span class="stepper-label">Base de datos</span>
            </div>
            <div class="stepper-item" data-step="3">
                <div class="stepper-num">3</div>
                <span class="stepper-label">Cuenta admin</span>
            </div>
        </div>

        <!-- Paso 1: Requisitos -->
        <div class="step active" id="step-1">
            <p class="step-title">Verificación del entorno</p>
            <p class="step-desc">Comprobando que el servidor cumple los requisitos mínimos.</p>

            <div id="req-list-wrap">
                <div class="req-loading"><i class="bi bi-arrow-repeat spin"></i> Verificando...</div>
            </div>

            <div id="req-error" class="alert alert-error" style="display:none"></div>

            <div class="btn-row">
                <button class="btn btn-primary" id="btn-req-next" disabled>
                    Siguiente <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Paso 2: Base de datos -->
        <div class="step" id="step-2">
            <p class="step-title">Conexión a la base de datos</p>
            <p class="step-desc">Ingresa las credenciales que te proporcionó el administrador del servidor. La base de datos debe existir previamente.</p>

            <div class="field-row">
                <div class="field">
                    <label for="db-host">Host</label>
                    <input id="db-host" type="text" value="localhost" autocomplete="off">
                </div>
                <div class="field field-port">
                    <label for="db-port">Puerto</label>
                    <input id="db-port" type="number" value="3306" autocomplete="off">
                </div>
            </div>
            <div class="field">
                <label for="db-name">Nombre de la base de datos</label>
                <input id="db-name" type="text" placeholder="nexus" autocomplete="off">
            </div>
            <div class="field">
                <label for="db-user">Usuario</label>
                <input id="db-user" type="text" placeholder="root" autocomplete="off">
            </div>
            <div class="field">
                <label for="db-pass">Contraseña</label>
                <input id="db-pass" type="password" placeholder="Dejar vacío si no tiene" autocomplete="new-password">
            </div>

            <div id="db-alert" class="alert" style="display:none"></div>

            <div class="btn-row">
                <button class="btn btn-ghost" id="btn-db-back">
                    <i class="bi bi-arrow-left"></i> Anterior
                </button>
                <button class="btn btn-secondary" id="btn-db-test">
                    <i class="bi bi-plug"></i> Probar conexión
                </button>
                <button class="btn btn-primary" id="btn-db-next" disabled>
                    Siguiente <i class="bi bi-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- Paso 3: Cuenta administrador -->
        <div class="step" id="step-3">
            <p class="step-title">Cuenta de administrador</p>
            <p class="step-desc">Este usuario tendrá acceso completo a la aplicación.</p>

            <div class="field">
                <label for="admin-name">Nombre completo</label>
                <input id="admin-name" type="text" placeholder="Tu nombre" autocomplete="name">
            </div>
            <div class="field">
                <label for="admin-email">Email</label>
                <input id="admin-email" type="email" placeholder="correo@empresa.com" autocomplete="email">
            </div>
            <div class="field">
                <label for="admin-pass">Contraseña <small style="font-weight:400;color:var(--ds-text-subtlest)">(mín. 8 caracteres)</small></label>
                <input id="admin-pass" type="password" autocomplete="new-password">
            </div>
            <div class="field">
                <label for="admin-pass2">Confirmar contraseña</label>
                <input id="admin-pass2" type="password" autocomplete="new-password">
            </div>
            <div class="field">
                <label for="admin-lang">Idioma de la interfaz</label>
                <select id="admin-lang">
                    <option value="es" selected>Español</option>
                    <option value="en">English</option>
                </select>
            </div>

            <div id="admin-alert" class="alert" style="display:none"></div>

            <div class="btn-row">
                <button class="btn btn-ghost" id="btn-admin-back">
                    <i class="bi bi-arrow-left"></i> Anterior
                </button>
                <button class="btn btn-primary" id="btn-install">
                    <i class="bi bi-rocket-takeoff"></i> Instalar Nexus
                </button>
            </div>
        </div>

        <!-- Paso 4: Éxito -->
        <div class="step" id="step-done">
            <div class="success-icon"><i class="bi bi-check-circle-fill"></i></div>
            <p class="success-title">Instalación completada</p>
            <p class="success-desc">Nexus está listo. Serás redirigido en unos segundos.</p>
            <div class="btn-row" style="justify-content:center">
                <a href="./" class="btn btn-primary">
                    <i class="bi bi-box-arrow-in-right"></i> Ir a Nexus
                </a>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    let currentStep  = 1;
    let dbTestPassed = false;

    // ── Navegación ────────────────────────────────────────────────────────────

    function goToStep(n) {
        document.querySelectorAll('.step').forEach(el => el.classList.remove('active'));
        document.getElementById('step-' + (n === 4 ? 'done' : n)).classList.add('active');

        document.querySelectorAll('.stepper-item').forEach(el => {
            const s = parseInt(el.dataset.step);
            el.classList.remove('active', 'done');
            if (s === n) el.classList.add('active');
            else if (s < n) el.classList.add('done');
        });

        currentStep = n;
    }

    // ── Paso 1: Requisitos ────────────────────────────────────────────────────

    async function checkRequirements() {
        const wrap = document.getElementById('req-list-wrap');
        wrap.innerHTML = '<div class="req-loading"><i class="bi bi-arrow-repeat spin"></i> Verificando...</div>';

        try {
            const res  = await fetch('?action=requirements');
            const data = await res.json();

            let html = '<ul class="req-list">';
            for (const c of data.checks) {
                const cls  = c.ok ? 'req-ok' : 'req-fail';
                const icon = c.ok ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
                html += `<li class="req-item">
                    <i class="bi ${icon} req-icon ${cls}"></i>
                    <span>${c.label}</span>
                    <span class="req-detail">${c.detail}</span>
                </li>`;
            }
            html += '</ul>';
            wrap.innerHTML = html;

            document.getElementById('btn-req-next').disabled = !data.ok;

            if (!data.ok) {
                showAlert('req-error', 'error', 'Corrige los requisitos marcados antes de continuar.');
            }
        } catch (e) {
            wrap.innerHTML = '';
            showAlert('req-error', 'error', 'No se pudo verificar el entorno. Recarga la página.');
        }
    }

    document.getElementById('btn-req-next').addEventListener('click', () => goToStep(2));

    // ── Paso 2: Base de datos ─────────────────────────────────────────────────

    document.getElementById('btn-db-back').addEventListener('click', () => goToStep(1));

    document.getElementById('btn-db-test').addEventListener('click', async () => {
        const btn    = document.getElementById('btn-db-test');
        const nextBtn = document.getElementById('btn-db-next');
        dbTestPassed = false;
        nextBtn.disabled = true;

        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Probando...';
        hideAlert('db-alert');

        try {
            const res  = await fetch('?action=test_db', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    host:   document.getElementById('db-host').value.trim(),
                    port:   parseInt(document.getElementById('db-port').value) || 3306,
                    dbname: document.getElementById('db-name').value.trim(),
                    user:   document.getElementById('db-user').value.trim(),
                    pass:   document.getElementById('db-pass').value,
                }),
            });
            const data = await res.json();

            if (data.ok) {
                showAlert('db-alert', 'success', '<i class="bi bi-check-circle-fill"></i> Conexión exitosa. Base de datos verificada.');
                dbTestPassed = true;
                nextBtn.disabled = false;
            } else {
                showAlert('db-alert', 'error', data.error || 'Error de conexión');
            }
        } catch (e) {
            showAlert('db-alert', 'error', 'Error de red al probar la conexión');
        }

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-plug"></i> Probar conexión';
    });

    document.getElementById('btn-db-next').addEventListener('click', () => {
        if (dbTestPassed) goToStep(3);
    });

    // ── Paso 3: Cuenta admin ──────────────────────────────────────────────────

    document.getElementById('btn-admin-back').addEventListener('click', () => goToStep(2));

    document.getElementById('btn-install').addEventListener('click', async () => {
        const btn  = document.getElementById('btn-install');
        const pass = document.getElementById('admin-pass').value;
        const pass2 = document.getElementById('admin-pass2').value;
        hideAlert('admin-alert');

        if (pass !== pass2) {
            showAlert('admin-alert', 'error', 'Las contraseñas no coinciden');
            return;
        }
        if (pass.length < 8) {
            showAlert('admin-alert', 'error', 'La contraseña debe tener al menos 8 caracteres');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Instalando...';

        try {
            const res  = await fetch('?action=install', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    host:     document.getElementById('db-host').value.trim(),
                    port:     parseInt(document.getElementById('db-port').value) || 3306,
                    dbname:   document.getElementById('db-name').value.trim(),
                    dbUser:   document.getElementById('db-user').value.trim(),
                    dbPass:   document.getElementById('db-pass').value,
                    name:     document.getElementById('admin-name').value.trim(),
                    email:    document.getElementById('admin-email').value.trim(),
                    password: pass,
                    lang:     document.getElementById('admin-lang').value,
                }),
            });
            const data = await res.json();

            if (data.ok) {
                goToStep(4);
                setTimeout(() => { window.location.href = './'; }, 3000);
            } else {
                showAlert('admin-alert', 'error', data.error || 'Error durante la instalación');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-rocket-takeoff"></i> Instalar Nexus';
            }
        } catch (e) {
            showAlert('admin-alert', 'error', 'Error de red durante la instalación');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-rocket-takeoff"></i> Instalar Nexus';
        }
    });

    // ── Helpers ───────────────────────────────────────────────────────────────

    function showAlert(id, type, msg) {
        const el = document.getElementById(id);
        el.className = 'alert alert-' + type;
        el.innerHTML = msg;
        el.style.display = 'flex';
    }

    function hideAlert(id) {
        document.getElementById(id).style.display = 'none';
    }

    // Arrancar
    checkRequirements();
})();
</script>
</body>
</html>
