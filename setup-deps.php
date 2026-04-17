<?php
/**
 * S4Learning - Instalador de dependencias
 * Detecta, descarga y configura Ghostscript e Imagick para el optimizador de archivos.
 *
 * Uso:  php setup-deps.php
 */

if (PHP_SAPI !== 'cli') {
    echo "Este script solo puede ejecutarse desde la consola.\n";
    echo "Uso: php setup-deps.php\n";
    exit(1);
}

// ── Colores ANSI ────────────────────────────────────────────────────────────
function c(string $text, string $color): string
{
    $codes = ['green' => '32', 'red' => '31', 'yellow' => '33', 'cyan' => '36', 'bold' => '1', 'dim' => '90'];
    return "\033[" . ($codes[$color] ?? '0') . "m{$text}\033[0m";
}

function ok(string $msg): void   { echo "  " . c('[OK]', 'green') . "  {$msg}\n"; }
function fail(string $msg): void { echo "  " . c('[--]', 'red') . "  {$msg}\n"; }
function info(string $msg): void { echo "  " . c('[ii]', 'cyan') . "  {$msg}\n"; }
function warn(string $msg): void { echo "  " . c('[!!]', 'yellow') . "  {$msg}\n"; }

function ask(string $question): bool
{
    echo "\n  {$question} [s/N]: ";
    $answer = strtolower(trim(fgets(STDIN)));
    return in_array($answer, ['s', 'si', 'y', 'yes']);
}

function banner(string $title): void
{
    $line = str_repeat('─', 60);
    echo "\n" . c($line, 'dim') . "\n";
    echo "  " . c($title, 'bold') . "\n";
    echo c($line, 'dim') . "\n\n";
}

// ── Info del entorno ────────────────────────────────────────────────────────
function getPhpEnv(): array
{
    return [
        'version'  => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
        'full'     => PHP_VERSION,
        'nts'      => !PHP_ZTS,
        'ts_label' => PHP_ZTS ? 'TS' : 'NTS',
        'arch'     => PHP_INT_SIZE * 8,
        'dir'      => dirname(PHP_BINARY),
        'ext_dir'  => ini_get('extension_dir') ?: dirname(PHP_BINARY) . '/ext',
        'ini_path' => php_ini_loaded_file(),
    ];
}

// ── Deteccion de Ghostscript ────────────────────────────────────────────────
function findGhostscript(): ?string
{
    foreach (['gswin64c', 'gswin32c', 'gs'] as $cmd) {
        $out = @shell_exec("where {$cmd} 2>&1");
        if ($out) {
            $line = trim(explode("\n", $out)[0]);
            if (file_exists($line)) return $line;
        }
    }
    $patterns = [
        'C:/Program Files/gs/gs*/bin/gswin64c.exe',
        'C:/Program Files (x86)/gs/gs*/bin/gswin64c.exe',
        'C:/Program Files/gs/gs*/bin/gswin32c.exe',
        'C:/Program Files (x86)/gs/gs*/bin/gswin32c.exe',
    ];
    foreach ($patterns as $pattern) {
        $matches = glob($pattern);
        if (!empty($matches)) return end($matches);
    }
    return null;
}

function gsVersion(string $path): string
{
    $out = @shell_exec('"' . $path . '" --version 2>&1');
    return $out ? trim($out) : 'desconocida';
}

// ── Deteccion de Imagick ────────────────────────────────────────────────────
function findImagickCli(): ?string
{
    $out = @shell_exec('where magick 2>&1');
    if ($out) {
        $line = trim(explode("\n", $out)[0]);
        if (file_exists($line)) return $line;
    }
    $patterns = ['C:/Program Files/ImageMagick*/magick.exe', 'C:/Program Files (x86)/ImageMagick*/magick.exe'];
    foreach ($patterns as $pattern) {
        $matches = glob($pattern);
        if (!empty($matches)) return end($matches);
    }
    return null;
}

function imagickCliVersion(string $path): string
{
    $out = @shell_exec('"' . $path . '" -version 2>&1');
    if ($out && preg_match('/ImageMagick\s+([\d.\-]+)/', $out, $m)) return $m[1];
    return 'desconocida';
}

// ── Descarga de DLL de Imagick ──────────────────────────────────────────────

/**
 * Obtiene las versiones disponibles de Imagick desde el servidor de descargas.
 * Escanea el listado del directorio y retorna las versiones ordenadas de mas reciente a mas antigua.
 */
function obtenerVersionesImagick(): array
{
    $base = 'https://downloads.php.net/~windows/pecl/releases/imagick/';
    $html = @file_get_contents($base);
    if (!$html) return [];

    // El listado de directorio tiene enlaces como href="3.7.0/"
    preg_match_all('/href="(\d+\.\d+\.\d+)\/"/', $html, $matches);
    if (empty($matches[1])) return [];

    $versions = array_unique($matches[1]);
    usort($versions, 'version_compare');
    return array_reverse($versions); // Mas reciente primero
}

function buildImagickDllUrl(array $php): ?string
{
    $base = 'https://downloads.php.net/~windows/pecl/releases/imagick';

    // Detectar compilador segun version de PHP
    $vs = match (true) {
        version_compare($php['version'], '8.4', '>=') => 'vs17',
        default => 'vs16',
    };

    info("Consultando versiones disponibles de Imagick...");
    $versions = obtenerVersionesImagick();

    if (empty($versions)) {
        warn("No se pudo obtener el listado de versiones. Intentando con version conocida...");
        $versions = ['3.7.0'];
    } else {
        info("Versiones encontradas: " . implode(', ', array_slice($versions, 0, 5)));
    }

    foreach ($versions as $ver) {
        $file = "php_imagick-{$ver}-{$php['version']}-" . strtolower($php['ts_label']) . "-{$vs}-x{$php['arch']}.zip";
        $url  = "{$base}/{$ver}/{$file}";
        $headers = @get_headers($url, true);
        if ($headers && str_contains($headers[0], '200')) {
            return $url;
        }
    }
    return null;
}

function downloadAndInstallImagick(string $url, array $php): bool
{
    $tmpZip = sys_get_temp_dir() . '/php_imagick.zip';
    $tmpDir = sys_get_temp_dir() . '/php_imagick_extract';

    info("Descargando: " . basename($url));
    $data = @file_get_contents($url);
    if (!$data) {
        fail("No se pudo descargar el archivo");
        return false;
    }
    file_put_contents($tmpZip, $data);
    info("Descargado: " . round(strlen($data) / 1024 / 1024, 1) . " MB");

    // Extraer
    if (is_dir($tmpDir)) {
        array_map('unlink', glob("{$tmpDir}/*"));
    } else {
        mkdir($tmpDir, 0777, true);
    }

    $zip = new ZipArchive();
    if ($zip->open($tmpZip) !== true) {
        fail("No se pudo abrir el ZIP");
        return false;
    }
    $zip->extractTo($tmpDir);
    $zip->close();

    // Copiar php_imagick.dll a ext/
    $extDll = $tmpDir . '/php_imagick.dll';
    if (!file_exists($extDll)) {
        fail("php_imagick.dll no encontrado en el ZIP");
        return false;
    }

    $destExt = rtrim($php['ext_dir'], '/\\') . '/php_imagick.dll';
    if (!@copy($extDll, $destExt)) {
        fail("No se pudo copiar php_imagick.dll a {$destExt}");
        warn("Intenta ejecutar el script como administrador");
        return false;
    }
    ok("php_imagick.dll copiado a ext/");

    // Copiar DLLs de soporte a la carpeta raiz de PHP
    $copied = 0;
    foreach (glob("{$tmpDir}/*.dll") as $dll) {
        $name = basename($dll);
        if ($name === 'php_imagick.dll') continue;
        if (@copy($dll, $php['dir'] . '/' . $name)) $copied++;
    }
    ok("{$copied} DLLs de soporte copiadas a " . $php['dir']);

    // Limpiar
    @unlink($tmpZip);
    array_map('unlink', glob("{$tmpDir}/*"));
    @rmdir($tmpDir);

    return true;
}

function enableIniExtension(string $iniPath, string $extension): bool
{
    $content = file_get_contents($iniPath);
    if ($content === false) return false;

    $line = "extension={$extension}";

    // Ya esta habilitada
    if (preg_match('/^\s*extension\s*=\s*' . preg_quote($extension, '/') . '\s*$/m', $content)) {
        info("{$line} ya existe en php.ini");
        return true;
    }

    // Esta comentada -> descomentar
    if (preg_match('/^;\s*extension\s*=\s*' . preg_quote($extension, '/') . '\s*$/m', $content)) {
        $content = preg_replace(
            '/^;\s*extension\s*=\s*' . preg_quote($extension, '/') . '\s*$/m',
            $line,
            $content
        );
        file_put_contents($iniPath, $content);
        ok("{$line} descomentada en php.ini");
        return true;
    }

    // Agregar despues de la ultima linea extension=
    $content = preg_replace(
        '/(^extension=[^\n]+$)/m',
        '$1',
        $content
    );
    // Insertar antes de la seccion de extensiones, despues de la ultima extension=
    if (preg_match_all('/^extension=.+$/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
        $last   = end($matches[0]);
        $pos    = $last[1] + strlen($last[0]);
        $content = substr($content, 0, $pos) . "\n{$line}" . substr($content, $pos);
        file_put_contents($iniPath, $content);
        ok("{$line} agregada a php.ini");
        return true;
    }

    return false;
}

// ── Utilidades de actualizacion ──────────────────────────────────────────────

function hasWinget(): bool
{
    static $result = null;
    if ($result === null) {
        $out = @shell_exec('where winget 2>&1');
        $result = $out && !str_contains($out, 'no se') && !str_contains($out, 'Could not');
    }
    return $result;
}

/**
 * Consulta a winget si hay actualizacion disponible para un paquete.
 * Retorna la version disponible o null si esta al dia.
 */
function wingetUpgradeAvailable(string $packageId): ?string
{
    $out = @shell_exec("winget upgrade --id {$packageId} --accept-source-agreements 2>&1");
    if (!$out) return null;
    // Si la salida contiene "No applicable update found" o similar, no hay actualizacion
    if (str_contains($out, 'No applicable') || str_contains($out, 'No available')
        || str_contains($out, 'actualización aplicable') || str_contains($out, 'No installed')) {
        return null;
    }
    // Buscar la linea con la version disponible
    if (preg_match('/(\d+[\d.]+\S*)\s+(\d+[\d.]+\S*)\s+winget/', $out, $m)) {
        $installed = $m[1];
        $available = $m[2];
        if (version_compare($available, $installed, '>')) return $available;
    }
    return null;
}

/**
 * Obtiene la version mas reciente de Imagick disponible para este PHP.
 */
function getLatestImagickVersion(array $php): ?array
{
    $versions = obtenerVersionesImagick();
    if (empty($versions)) return null;

    $base = 'https://downloads.php.net/~windows/pecl/releases/imagick';
    $vs = match (true) {
        version_compare($php['version'], '8.4', '>=') => 'vs17',
        default => 'vs16',
    };

    foreach ($versions as $ver) {
        $file = "php_imagick-{$ver}-{$php['version']}-" . strtolower($php['ts_label']) . "-{$vs}-x{$php['arch']}.zip";
        $url  = "{$base}/{$ver}/{$file}";
        $headers = @get_headers($url, true);
        if ($headers && str_contains($headers[0], '200')) {
            return ['version' => $ver, 'url' => $url];
        }
    }
    return null;
}

/**
 * Extrae la version de Imagick del string completo.
 * Ej: "ImageMagick 7.1.0-18 Q16 x64 ..." → "7.1.0-18"
 */
function parseImagickVersion(string $versionString): string
{
    if (preg_match('/ImageMagick\s+([\d.\-]+)/', $versionString, $m)) return $m[1];
    return '';
}

// ═════════════════════════════════════════════════════════════════════════════
// EJECUCION PRINCIPAL
// ═════════════════════════════════════════════════════════════════════════════

echo "\n";
echo c("  S4Learning — Instalador de dependencias", 'bold') . "\n";
echo c("  Verifica y configura Ghostscript e Imagick", 'dim') . "\n";

$php = getPhpEnv();

banner("Entorno PHP");
info("Version: PHP {$php['full']} ({$php['ts_label']}, x{$php['arch']})");
info("Directorio: {$php['dir']}");
info("php.ini: {$php['ini_path']}");
info("Ext dir: {$php['ext_dir']}");

// ── 1. Ghostscript ──────────────────────────────────────────────────────────
banner("Ghostscript");

$gsPath = findGhostscript();
if ($gsPath) {
    $gsVer = gsVersion($gsPath);
    ok("Ghostscript encontrado: {$gsPath}");
    ok("Version instalada: {$gsVer}");

    // Verificar actualizacion
    if (hasWinget()) {
        info("Verificando actualizaciones...");
        $gsUpgrade = wingetUpgradeAvailable('ArtifexSoftware.GhostScript');
        if ($gsUpgrade) {
            warn("Nueva version disponible: {$gsUpgrade} (instalada: {$gsVer})");
            if (ask("Actualizar Ghostscript a {$gsUpgrade}?")) {
                passthru('winget upgrade --id ArtifexSoftware.GhostScript --accept-source-agreements --accept-package-agreements');
                $gsPath = findGhostscript();
                if ($gsPath) ok("Actualizado a: " . gsVersion($gsPath));
            }
        } else {
            ok("Esta al dia");
        }
    }
} else {
    fail("Ghostscript no encontrado");

    if (hasWinget() && ask("Instalar Ghostscript via winget?")) {
        info("Ejecutando: winget install ArtifexSoftware.GhostScript");
        passthru('winget install ArtifexSoftware.GhostScript --accept-source-agreements --accept-package-agreements');

        $gsPath = findGhostscript();
        if ($gsPath) {
            ok("Ghostscript instalado correctamente: " . gsVersion($gsPath));
        } else {
            warn("Ghostscript instalado pero no detectado en PATH");
            warn("Reinicia la terminal o Laragon e intenta de nuevo");
        }
    } else {
        info("Puedes instalar manualmente:");
        info("  winget install ArtifexSoftware.GhostScript");
        info("  choco install ghostscript");
        info("  https://ghostscript.com/releases/gsdnld.html");
    }
}

// ── 2. ImageMagick CLI ──────────────────────────────────────────────────────
banner("ImageMagick CLI");

$imPath = findImagickCli();
if ($imPath) {
    $imVer = imagickCliVersion($imPath);
    ok("ImageMagick CLI encontrado: {$imPath}");
    ok("Version instalada: {$imVer}");

    // Verificar actualizacion
    if (hasWinget()) {
        info("Verificando actualizaciones...");
        $imUpgrade = wingetUpgradeAvailable('ImageMagick.ImageMagick');
        if ($imUpgrade) {
            warn("Nueva version disponible: {$imUpgrade} (instalada: {$imVer})");
            if (ask("Actualizar ImageMagick a {$imUpgrade}?")) {
                passthru('winget upgrade --id ImageMagick.ImageMagick --accept-source-agreements --accept-package-agreements');
                $imPath = findImagickCli();
                if ($imPath) ok("Actualizado a: " . imagickCliVersion($imPath));
            }
        } else {
            ok("Esta al dia");
        }
    }
} else {
    fail("ImageMagick CLI no encontrado");

    if (hasWinget() && ask("Instalar ImageMagick via winget?")) {
        info("Ejecutando: winget install ImageMagick.ImageMagick");
        passthru('winget install ImageMagick.ImageMagick --accept-source-agreements --accept-package-agreements');

        $imPath = findImagickCli();
        if ($imPath) {
            ok("ImageMagick instalado correctamente: " . imagickCliVersion($imPath));
        } else {
            warn("ImageMagick instalado pero no detectado en PATH");
            warn("Reinicia la terminal o Laragon e intenta de nuevo");
        }
    } else {
        info("Puedes instalar manualmente:");
        info("  winget install ImageMagick.ImageMagick");
        info("  choco install imagemagick");
        info("  https://imagemagick.org/script/download.php#windows");
    }
}

// ── 3. Extension PHP Imagick ────────────────────────────────────────────────
banner("Extension PHP Imagick");

if (extension_loaded('imagick')) {
    $v = Imagick::getVersion();
    $installedVer = parseImagickVersion($v['versionString']);
    ok("Extension Imagick cargada");
    ok("Version instalada: {$installedVer}");

    // Verificar si hay version mas reciente de la DLL
    info("Verificando actualizaciones...");
    $latest = getLatestImagickVersion($php);
    if ($latest && version_compare($latest['version'], $installedVer, '>')) {
        warn("Nueva version disponible: Imagick {$latest['version']} (instalada: {$installedVer})");
        if (ask("Actualizar la extension Imagick a {$latest['version']}?")) {
            if (downloadAndInstallImagick($latest['url'], $php)) {
                ok("Reinicia Apache/Laragon para usar la nueva version");
            }
        }
    } else {
        ok("Esta al dia");
    }
} else {
    fail("Extension Imagick no cargada");

    // Verificar si la DLL existe pero no esta habilitada
    $dllExists = file_exists(rtrim($php['ext_dir'], '/\\') . '/php_imagick.dll');

    if ($dllExists) {
        warn("php_imagick.dll existe en ext/ pero no esta habilitada");
        if (ask("Habilitar extension=imagick en php.ini?")) {
            if (enableIniExtension($php['ini_path'], 'imagick')) {
                ok("Reinicia Apache/Laragon para activar la extension");
            } else {
                fail("No se pudo modificar php.ini");
            }
        }
    } else {
        info("Buscando DLL compatible para PHP {$php['version']} {$php['ts_label']} x{$php['arch']}...");
        $url = buildImagickDllUrl($php);

        if ($url) {
            info("DLL encontrada: " . basename($url));
            if (ask("Descargar e instalar la extension Imagick?")) {
                if (downloadAndInstallImagick($url, $php)) {
                    if (ask("Habilitar extension=imagick en php.ini?")) {
                        enableIniExtension($php['ini_path'], 'imagick');
                    }
                    ok("Reinicia Apache/Laragon para activar la extension");
                }
            }
        } else {
            fail("No se encontro DLL precompilada para PHP {$php['version']} {$php['ts_label']} x{$php['arch']}");
            info("Descarga manual: https://mlocati.github.io/articles/php-windows-imagick.html");
        }
    }
}

// ── Resumen ─────────────────────────────────────────────────────────────────
banner("Resumen");

$gsOk  = findGhostscript() !== null;
$imOk  = findImagickCli() !== null;
$extOk = extension_loaded('imagick') || file_exists(rtrim($php['ext_dir'], '/\\') . '/php_imagick.dll');

($gsOk  ? 'ok' : 'fail')("Ghostscript" . ($gsOk ? '' : ' — instalar con: winget install ArtifexSoftware.GhostScript'));
($imOk  ? 'ok' : 'fail')("ImageMagick CLI" . ($imOk ? '' : ' — instalar con: winget install ImageMagick.ImageMagick'));
($extOk ? 'ok' : 'fail')("Extension PHP Imagick" . ($extOk ? ($extOk && !extension_loaded('imagick') ? ' — reiniciar Apache para activar' : '') : ' — ejecutar este script de nuevo'));

if ($gsOk && $imOk && $extOk) {
    echo "\n  " . c("Todas las dependencias estan listas.", 'green') . "\n";
} else {
    echo "\n  " . c("Faltan dependencias. Revisa los pasos anteriores.", 'yellow') . "\n";
}

// ── Guardar cache para la pagina de ajustes ─────────────────────────────────
$gsPath2    = findGhostscript();
$imPath2    = findImagickCli();
$imkLoaded  = extension_loaded('imagick');

// Versiones instaladas
$gsVerFinal  = $gsPath2 ? gsVersion($gsPath2) : null;
$imVerFinal  = $imPath2 ? imagickCliVersion($imPath2) : null;
$extVerFinal = $imkLoaded ? parseImagickVersion(Imagick::getVersion()['versionString']) : null;
$gdVer       = extension_loaded('gd') ? (gd_info()['GD Version'] ?? null) : null;

// Versiones disponibles (ya consultadas durante la ejecucion)
$gsUpgradeVer  = isset($gsUpgrade)  ? $gsUpgrade  : null;
$imUpgradeVer  = isset($imUpgrade)  ? $imUpgrade  : null;
$latestImk     = isset($latest)     ? $latest      : getLatestImagickVersion($php);
$extUpgradeVer = null;
if ($extVerFinal && $latestImk && version_compare($latestImk['version'], $extVerFinal, '>')) {
    $extUpgradeVer = $latestImk['version'];
}

$cache = [
    'checked_at' => date('Y-m-d H:i:s'),
    'php'        => $php['full'],
    'deps'       => [
        'ghostscript'  => ['installed' => $gsVerFinal,  'available' => $gsUpgradeVer],
        'imagemagick'  => ['installed' => $imVerFinal,  'available' => $imUpgradeVer],
        'imagick_ext'  => ['installed' => $extVerFinal, 'available' => $extUpgradeVer],
        'gd_ext'       => ['installed' => $gdVer,       'available' => null],
    ],
];

$dataDir = __DIR__ . '/data';
if (is_dir($dataDir)) {
    $written = file_put_contents($dataDir . '/deps_check.json', json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    if ($written) {
        ok("Cache guardado en data/deps_check.json");
    }
}

echo "\n";
