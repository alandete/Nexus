<?php
/**
 * NexusApp - Migración de datos JSON a MySQL
 * Ejecutar una sola vez desde consola: php migrate-data.php
 */

define('APP_ACCESS', true);
define('APP_VERSION', '1.0.0');
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

echo "\n  NexusApp — Migración de datos JSON a MySQL\n\n";

$db = getDB();
if (!$db) {
    echo "  [ERROR] No se pudo conectar a la base de datos.\n";
    echo "  Verifique config/database.php\n\n";
    exit(1);
}

runMigrations();
echo "  [OK] Tablas verificadas\n\n";

// ── Roles ───────────────────────────────────────────────────────────────────
echo "  Roles\n";
$rolesFile = DATA_PATH . '/roles.json';
if (file_exists($rolesFile)) {
    $roles = json_decode(file_get_contents($rolesFile), true) ?? [];
    $count = 0;
    foreach ($roles as $name => $data) {
        $permissions = $data['permissions'] ?? $data;
        $stmt = $db->prepare("INSERT INTO roles (name, permissions) VALUES (?, ?) ON DUPLICATE KEY UPDATE permissions = VALUES(permissions)");
        $stmt->execute([$name, json_encode($permissions)]);
        $count++;
    }
    echo "  [OK] {$count} roles migrados\n";
} else {
    echo "  [--] roles.json no encontrado\n";
}

// ── Usuarios ────────────────────────────────────────────────────────────────
echo "\n  Usuarios\n";
$usersFile = USERS_FILE;
if (file_exists($usersFile)) {
    $users = json_decode(file_get_contents($usersFile), true) ?? [];
    $count = 0;
    foreach ($users as $username => $u) {
        $stmt = $db->prepare("INSERT INTO users (username, password, name, email, role, lang, photo, active, last_login, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE password=VALUES(password), name=VALUES(name), email=VALUES(email),
            role=VALUES(role), lang=VALUES(lang), photo=VALUES(photo), active=VALUES(active),
            last_login=VALUES(last_login)");
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
        $count++;
    }
    echo "  [OK] {$count} usuarios migrados\n";
} else {
    echo "  [--] users.json no encontrado\n";
}

// ── Activity Log ────────────────────────────────────────────────────────────
echo "\n  Activity Log\n";
$logFile = ACTIVITY_LOG_FILE;
if (file_exists($logFile)) {
    $log = json_decode(file_get_contents($logFile), true) ?? [];
    $existing = $db->query("SELECT COUNT(*) FROM activity_log")->fetchColumn();

    if ($existing > 0) {
        echo "  [--] La tabla ya tiene {$existing} registros. Se omite importación.\n";
    } else {
        $count = 0;
        $stmt = $db->prepare("INSERT INTO activity_log (timestamp, user, user_name, role, module, action, detail, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        foreach (array_reverse($log) as $e) {
            $stmt->execute([
                $e['timestamp'] ?? date('Y-m-d H:i:s'),
                $e['user'] ?? 'system',
                $e['user_name'] ?? '',
                $e['role'] ?? '',
                $e['module'] ?? '',
                $e['action'] ?? '',
                $e['detail'] ?? '',
                $e['ip'] ?? '',
            ]);
            $count++;
        }
        echo "  [OK] {$count} entradas migradas\n";
    }
} else {
    echo "  [--] activity_log.json no encontrado\n";
}

// ── Alianzas ────────────────────────────────────────────────────────────────
echo "\n  Alianzas\n";
$alliancesFile = ALLIANCES_FILE;
if (file_exists($alliancesFile)) {
    $alliances = json_decode(file_get_contents($alliancesFile), true) ?? [];
    $count = 0;
    foreach ($alliances as $slug => $a) {
        $stmt = $db->prepare("INSERT INTO alliances (slug, name, fullname, country, color, website, lms_url, manager, coordinator, migrator, sections, resource_types, config, active, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE name=VALUES(name), fullname=VALUES(fullname), country=VALUES(country),
            color=VALUES(color), website=VALUES(website), lms_url=VALUES(lms_url), manager=VALUES(manager),
            coordinator=VALUES(coordinator), migrator=VALUES(migrator), sections=VALUES(sections),
            resource_types=VALUES(resource_types), config=VALUES(config), active=VALUES(active), updated_at=VALUES(updated_at)");
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
            $a['created_at'] ?? date('Y-m-d H:i:s'),
            $a['updated_at'] ?? date('Y-m-d H:i:s'),
        ]);
        $count++;
    }
    echo "  [OK] {$count} alianzas migradas\n";
} else {
    echo "  [--] alliances.json no encontrado\n";
}

// ── Resumen ─────────────────────────────────────────────────────────────────
echo "\n  Migración completada.\n";
echo "  Los archivos JSON originales se conservan como respaldo.\n\n";
