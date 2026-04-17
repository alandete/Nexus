<?php
/**
 * S4Learning -Funciones Globales
 */

defined('APP_ACCESS') or die('Acceso directo no permitido');

/**
 * Función de traducción
 */
function __($key) {
    global $translations;
    $keys = explode('.', $key);
    $value = $translations;

    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $key;
        }
    }

    return $value;
}

// ── Base de datos ────────────────────────────────────────────────────────────

/**
 * Retorna la conexión PDO (singleton).
 * Si database.php no existe o la conexión falla, retorna null.
 * La app sigue funcionando con JSON cuando no hay BD disponible.
 */
function getDB(): ?PDO
{
    static $pdo = null;
    static $tried = false;

    if ($tried) return $pdo;
    $tried = true;

    $dbConfig = BASE_PATH . '/config/database.php';
    if (!file_exists($dbConfig)) return null;

    $cfg = require $dbConfig;
    if (empty($cfg['host']) || empty($cfg['dbname']) || empty($cfg['username'])) return null;

    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'] ?? 3306,
            $cfg['dbname'],
            $cfg['charset'] ?? 'utf8mb4'
        );

        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'] ?? '', [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        // Sincronizar timezone de MySQL con PHP
        $tz = date('P'); // ej: -05:00
        $pdo->exec("SET time_zone = '{$tz}'");

        return $pdo;
    } catch (PDOException $e) {
        $pdo = null;
        return null;
    }
}

/**
 * Verifica si la BD está disponible y conectada
 */
function isDBAvailable(): bool
{
    return getDB() !== null;
}

/**
 * Ejecuta las migraciones pendientes.
 * Crea las tablas si no existen. Seguro para llamar en cada request.
 */
function runMigrations(): bool
{
    $db = getDB();
    if (!$db) return false;

    try {
        // Tabla de control de migraciones
        $db->exec("CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL UNIQUE,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Obtener migraciones ya ejecutadas
        $executed = $db->query("SELECT name FROM migrations")->fetchAll(PDO::FETCH_COLUMN);

        // Definir migraciones en orden
        $migrations = getMigrations();

        foreach ($migrations as $name => $sql) {
            if (in_array($name, $executed)) continue;

            $db->exec($sql);
            $stmt = $db->prepare("INSERT INTO migrations (name) VALUES (?)");
            $stmt->execute([$name]);
        }

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Retorna el array de migraciones disponibles [nombre => SQL]
 */
function getMigrations(): array
{
    return [
        '001_create_users' => "
            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(150) NOT NULL UNIQUE,
                role VARCHAR(20) NOT NULL DEFAULT 'viewer',
                lang VARCHAR(5) NOT NULL DEFAULT 'es',
                photo VARCHAR(255) DEFAULT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                last_login DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_role (role),
                INDEX idx_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        '002_create_roles' => "
            CREATE TABLE IF NOT EXISTS roles (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(50) NOT NULL UNIQUE,
                permissions JSON NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        '003_create_activity_log' => "
            CREATE TABLE IF NOT EXISTS activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                user VARCHAR(50) NOT NULL,
                user_name VARCHAR(100) NOT NULL,
                role VARCHAR(20) NOT NULL,
                module VARCHAR(50) NOT NULL,
                action VARCHAR(50) NOT NULL,
                detail TEXT DEFAULT NULL,
                ip VARCHAR(45) DEFAULT NULL,
                INDEX idx_timestamp (timestamp),
                INDEX idx_user (user),
                INDEX idx_module (module),
                INDEX idx_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        '004_create_alliances' => "
            CREATE TABLE IF NOT EXISTS alliances (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(50) NOT NULL UNIQUE,
                name VARCHAR(100) NOT NULL,
                fullname VARCHAR(255) DEFAULT NULL,
                country VARCHAR(5) DEFAULT NULL,
                color VARCHAR(20) DEFAULT NULL,
                website VARCHAR(500) DEFAULT NULL,
                lms_url VARCHAR(500) DEFAULT NULL,
                manager JSON DEFAULT NULL,
                coordinator JSON DEFAULT NULL,
                migrator JSON DEFAULT NULL,
                sections JSON DEFAULT NULL,
                resource_types JSON DEFAULT NULL,
                config JSON DEFAULT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        '005_create_tasks' => "
            CREATE TABLE IF NOT EXISTS tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                alliance_id INT DEFAULT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                due_date DATE DEFAULT NULL,
                priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
                status ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_alliance (alliance_id),
                INDEX idx_due_date (due_date),
                INDEX idx_status (status),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (alliance_id) REFERENCES alliances(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        '006_create_time_entries' => "
            CREATE TABLE IF NOT EXISTS time_entries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                task_id INT NOT NULL,
                user_id INT NOT NULL,
                start_time DATETIME NOT NULL,
                end_time DATETIME DEFAULT NULL,
                duration_seconds INT DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_task (task_id),
                INDEX idx_user (user_id),
                INDEX idx_start (start_time),
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        '007_tasks_add_tags_and_urgent' => "
            ALTER TABLE tasks
                MODIFY COLUMN priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
                ADD COLUMN tags VARCHAR(500) DEFAULT NULL AFTER description",

        '008_create_tags' => "
            CREATE TABLE IF NOT EXISTS tags (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                color VARCHAR(20) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        '009_create_task_tags' => "
            CREATE TABLE IF NOT EXISTS task_tags (
                task_id INT NOT NULL,
                tag_id INT NOT NULL,
                PRIMARY KEY (task_id, tag_id),
                FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
                FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        '010_tasks_add_paused_status' => "
            ALTER TABLE tasks MODIFY COLUMN status ENUM('pending', 'in_progress', 'paused', 'completed', 'cancelled') NOT NULL DEFAULT 'pending'",

        '011_alliances_add_billable' => "
            ALTER TABLE alliances ADD COLUMN billable TINYINT(1) NOT NULL DEFAULT 1 AFTER active",
    ];
}

// ── Utilidades generales ────────────────────────────────────────────────────

/**
 * Sanitizar entrada
 */
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar token CSRF
 */
function validateCsrf(): bool {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generar URL
 * Genera URLs limpias sin query strings
 */
function url($page = '') {
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    if (empty($page) || $page === 'home') {
        return $base . '/';
    }
    return $base . '/' . urlencode($page);
}

/**
 * Obtener usuarios del sistema
 */
function getUsers() {
    $db = getDB();
    if ($db) {
        try {
            $rows = $db->query("SELECT * FROM users")->fetchAll();
            if (!empty($rows)) {
                $users = [];
                foreach ($rows as $row) {
                    $users[$row['username']] = [
                        'id'         => (int) $row['id'],
                        'username'   => $row['username'],
                        'password'   => $row['password'],
                        'name'       => $row['name'],
                        'email'      => $row['email'],
                        'role'       => $row['role'],
                        'lang'       => $row['lang'],
                        'photo'      => $row['photo'],
                        'active'     => (bool) $row['active'],
                        'last_login' => $row['last_login'],
                        'created_at' => $row['created_at'],
                    ];
                }
                return $users;
            }
        } catch (PDOException $e) {}
    }

    if (!file_exists(USERS_FILE)) return getDefaultUsers();
    return json_decode(file_get_contents(USERS_FILE), true);
}

/**
 * Guardar usuarios
 */
function saveUsers($users) {
    $db = getDB();
    if ($db) {
        try {
            foreach ($users as $username => $u) {
                $stmt = $db->prepare("INSERT INTO users (username, password, name, email, role, lang, photo, active, last_login, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE password=VALUES(password), name=VALUES(name), email=VALUES(email),
                    role=VALUES(role), lang=VALUES(lang), photo=VALUES(photo), active=VALUES(active), last_login=VALUES(last_login)");
                $stmt->execute([
                    $username,
                    $u['password'] ?? '',
                    $u['name'] ?? $username,
                    $u['email'] ?? '',
                    $u['role'] ?? 'viewer',
                    $u['lang'] ?? 'es',
                    $u['photo'] ?? null,
                    isset($u['active']) ? ($u['active'] ? 1 : 0) : 1,
                    $u['last_login'] ?? null,
                    $u['created_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }
            return true;
        } catch (PDOException $e) {}
    }

    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    return file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Genera emoji de bandera desde código de país ISO alpha-2
 */
function countryFlag(string $code): string
{
    if (strlen($code) !== 2) return '';
    $code = strtoupper($code);
    $flag = '';
    for ($i = 0; $i < 2; $i++) {
        $flag .= mb_chr(0x1F1E6 + ord($code[$i]) - ord('A'));
    }
    return $flag;
}

/**
 * Obtener alianzas del sistema
 */
function getAlliances(): array
{
    $db = getDB();
    if ($db) {
        try {
            $rows = $db->query("SELECT * FROM alliances ORDER BY name")->fetchAll();
            if (!empty($rows)) {
                $alliances = [];
                foreach ($rows as $row) {
                    $alliances[$row['slug']] = [
                        'id'             => (int) $row['id'],
                        'name'           => $row['name'],
                        'fullname'       => $row['fullname'],
                        'country'        => $row['country'],
                        'color'          => $row['color'],
                        'website'        => $row['website'],
                        'lms_url'        => $row['lms_url'],
                        'manager'        => json_decode($row['manager'], true),
                        'coordinator'    => json_decode($row['coordinator'], true),
                        'migrator'       => json_decode($row['migrator'], true),
                        'sections'       => json_decode($row['sections'], true) ?? [],
                        'resource_types' => json_decode($row['resource_types'], true),
                        'config'         => json_decode($row['config'], true),
                        'active'         => (bool) $row['active'],
                        'billable'       => isset($row['billable']) ? (bool) $row['billable'] : true,
                        'files'          => [],
                        'created_at'     => $row['created_at'],
                        'updated_at'     => $row['updated_at'],
                    ];
                }
                return $alliances;
            }
        } catch (PDOException $e) {}
    }

    if (!file_exists(ALLIANCES_FILE)) return [];
    return json_decode(file_get_contents(ALLIANCES_FILE), true) ?? [];
}

/**
 * Guardar alianzas
 */
function saveAlliances(array $alliances): bool
{
    $db = getDB();
    if ($db) {
        try {
            foreach ($alliances as $slug => $a) {
                $stmt = $db->prepare("INSERT INTO alliances (slug, name, fullname, country, color, website, lms_url, manager, coordinator, migrator, sections, resource_types, config, active, billable, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE name=VALUES(name), fullname=VALUES(fullname), country=VALUES(country),
                    color=VALUES(color), website=VALUES(website), lms_url=VALUES(lms_url), manager=VALUES(manager),
                    coordinator=VALUES(coordinator), migrator=VALUES(migrator), sections=VALUES(sections),
                    resource_types=VALUES(resource_types), config=VALUES(config), active=VALUES(active), billable=VALUES(billable), updated_at=VALUES(updated_at)");
                $stmt->execute([
                    $slug,
                    $a['name'] ?? $slug,
                    $a['fullname'] ?? null,
                    $a['country'] ?? null,
                    $a['color'] ?? null,
                    $a['website'] ?? null,
                    $a['lms_url'] ?? null,
                    json_encode($a['manager'] ?? null),
                    json_encode($a['coordinator'] ?? null),
                    json_encode($a['migrator'] ?? null),
                    json_encode($a['sections'] ?? []),
                    json_encode($a['resource_types'] ?? null),
                    json_encode($a['config'] ?? null),
                    isset($a['active']) ? ($a['active'] ? 1 : 0) : 1,
                    isset($a['billable']) ? ($a['billable'] ? 1 : 0) : 1,
                    $a['created_at'] ?? date('Y-m-d H:i:s'),
                    $a['updated_at'] ?? date('Y-m-d H:i:s'),
                ]);
            }
            return true;
        } catch (PDOException $e) {}
    }

    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    return (bool) file_put_contents(ALLIANCES_FILE, json_encode($alliances, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Obtener información del proyecto
 */
function getProjectInfo(): array
{
    $defaults = [
        'app_name'        => 'S4Learning',
        'tagline'         => '',
        'description'     => '',
        'logo'            => null,
        'company_name'    => '',
        'company_address' => '',
        'contact_email'   => '',
        'contact_phone'   => '',
        'website'         => '',
        'updated_at'      => null,
    ];

    if (!file_exists(PROJECTINFO_FILE)) {
        return $defaults;
    }

    $data = json_decode(file_get_contents(PROJECTINFO_FILE), true);
    return is_array($data) ? array_merge($defaults, $data) : $defaults;
}

/**
 * Guardar información del proyecto
 */
function saveProjectInfo(array $data): bool
{
    if (!is_dir(DATA_PATH)) {
        mkdir(DATA_PATH, 0755, true);
    }
    return (bool) file_put_contents(
        PROJECTINFO_FILE,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

/**
 * Registra una actividad en el log.
 */
function logActivity(string $module, string $action, string $detail = ''): void
{
    $user = getCurrentUser();
    $timestamp = date('Y-m-d H:i:s');
    $username  = $user['username'] ?? 'system';
    $userName  = $user['name'] ?? 'Sistema';
    $role      = $user['role'] ?? '';
    $ip        = $_SERVER['REMOTE_ADDR'] ?? '';

    $db = getDB();
    if ($db) {
        try {
            $stmt = $db->prepare("INSERT INTO activity_log (timestamp, user, user_name, role, module, action, detail, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$timestamp, $username, $userName, $role, $module, $action, $detail, $ip]);
            return;
        } catch (PDOException $e) {}
    }

    // Fallback JSON
    $entry = [
        'id'        => bin2hex(random_bytes(4)),
        'timestamp' => $timestamp,
        'user'      => $username,
        'user_name' => $userName,
        'role'      => $role,
        'module'    => $module,
        'action'    => $action,
        'detail'    => $detail,
        'ip'        => $ip,
    ];

    $log = [];
    if (file_exists(ACTIVITY_LOG_FILE)) {
        $log = json_decode(file_get_contents(ACTIVITY_LOG_FILE), true) ?? [];
    }
    array_unshift($log, $entry);
    if (count($log) > MAX_LOG_ENTRIES) {
        $log = array_slice($log, 0, MAX_LOG_ENTRIES);
    }
    if (!is_dir(DATA_PATH)) mkdir(DATA_PATH, 0755, true);
    file_put_contents(ACTIVITY_LOG_FILE, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Encripta un valor sensible con AES-256-CBC
 */
function encryptApiValue(string $value): string
{
    if (empty($value)) return '';
    $key       = hash('sha256', APP_SECRET_KEY, true);
    $iv        = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($value, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Desencripta un valor guardado con encryptApiValue
 */
function decryptApiValue(string $value): string
{
    if (empty($value)) return '';
    $key     = hash('sha256', APP_SECRET_KEY, true);
    $decoded = base64_decode($value);
    if (strlen($decoded) < 17) return '';
    $iv        = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);
    $result    = openssl_decrypt($encrypted, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $result !== false ? $result : '';
}

/**
 * Obtiene la configuracion de APIs externas (desencriptada)
 */
function getApiSettings(): array
{
    $defaults = [
        'ilp_public_key' => '',
        'ilp_secret_key' => '',
    ];
    if (!file_exists(API_SETTINGS_FILE)) {
        return $defaults;
    }
    $raw = json_decode(file_get_contents(API_SETTINGS_FILE), true);
    if (!is_array($raw)) return $defaults;
    return [
        'ilp_public_key' => decryptApiValue($raw['ilp_public_key'] ?? ''),
        'ilp_secret_key' => decryptApiValue($raw['ilp_secret_key'] ?? ''),
    ];
}

/**
 * Obtiene las entradas del log de actividad con filtros y paginacion.
 */
function getActivityLog(array $filters = [], int $page = 1, int $perPage = 25): array
{
    $db = getDB();
    if ($db) {
        try {
            $where = [];
            $params = [];

            if (!empty($filters['date_from'])) {
                $where[] = "timestamp >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            if (!empty($filters['date_to'])) {
                $where[] = "timestamp <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            if (!empty($filters['user'])) {
                $where[] = "user = ?";
                $params[] = $filters['user'];
            }
            if (!empty($filters['module'])) {
                $where[] = "module = ?";
                $params[] = $filters['module'];
            }
            if (!empty($filters['action'])) {
                $where[] = "action = ?";
                $params[] = $filters['action'];
            }

            $whereSQL = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            // Total
            $countStmt = $db->prepare("SELECT COUNT(*) FROM activity_log {$whereSQL}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $pages = $total > 0 ? (int) ceil($total / $perPage) : 0;
            $page  = max(1, min($page, max(1, $pages)));
            $offset = ($page - 1) * $perPage;

            // Entries
            $stmt = $db->prepare("SELECT * FROM activity_log {$whereSQL} ORDER BY timestamp DESC LIMIT {$perPage} OFFSET {$offset}");
            $stmt->execute($params);
            $entries = $stmt->fetchAll();

            return ['entries' => $entries, 'total' => $total, 'page' => $page, 'pages' => $pages];
        } catch (PDOException $e) {}
    }

    // Fallback JSON
    if (!file_exists(ACTIVITY_LOG_FILE)) {
        return ['entries' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
    }

    $log = json_decode(file_get_contents(ACTIVITY_LOG_FILE), true) ?? [];

    if (!empty($filters['date_from'])) {
        $from = $filters['date_from'] . ' 00:00:00';
        $log = array_filter($log, fn($e) => $e['timestamp'] >= $from);
    }
    if (!empty($filters['date_to'])) {
        $to = $filters['date_to'] . ' 23:59:59';
        $log = array_filter($log, fn($e) => $e['timestamp'] <= $to);
    }
    if (!empty($filters['user'])) {
        $log = array_filter($log, fn($e) => $e['user'] === $filters['user']);
    }
    if (!empty($filters['module'])) {
        $log = array_filter($log, fn($e) => $e['module'] === $filters['module']);
    }
    if (!empty($filters['action'])) {
        $log = array_filter($log, fn($e) => $e['action'] === $filters['action']);
    }

    $log   = array_values($log);
    $total = count($log);
    $pages = $total > 0 ? (int) ceil($total / $perPage) : 0;
    $page  = max(1, min($page, max(1, $pages)));
    $entries = array_slice($log, ($page - 1) * $perPage, $perPage);

    return ['entries' => $entries, 'total' => $total, 'page' => $page, 'pages' => $pages];
}
