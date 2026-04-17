<?php
/**
 * S4Learning - GIFT Actions Handler
 * Procesa archivos .docx y genera formato GIFT para Moodle
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/question_parser.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar sesion
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Validar token CSRF
if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF invalido']);
    exit;
}

// Funciones de parsing compartidas (extraerTextoDocx, extraerDatosXlsx, parsear*, etc.)
// estan en question_parser.php via require_once

/**
 * Genera formato GIFT a partir de preguntas OM parseadas de Excel
 * Diferencia con Word: cada opcion tiene su propia retro individual
 */
function generarGiftExcel(array $preguntas, array $cursivas, array $negritas): array
{
    $gift = '';
    $tieneFormatoGlobal = !empty($cursivas) || !empty($negritas);
    $totalImagenes = 0;
    $totalCursivas = 0;
    $totalNegritas = 0;

    foreach ($preguntas as $preg) {
        $enunciado = $preg['enunciado'];
        $opciones = $preg['opciones'];
        $retros = $preg['retros'];
        $retroGeneral = $preg['retro_general'];

        // 1. Procesar imagenes ANTES de escapar
        $placeholders = [];
        $imgResult = procesarImagenes($enunciado);
        $enunciado = $imgResult['texto'];
        $usarHtml = $imgResult['tieneImagen'];
        $totalImagenes += $imgResult['count'];

        if ($usarHtml) {
            $enunciado = str_replace('alt=""', 'alt="Imagen de apoyo a la OM Pregunta ' . $preg['numero'] . '"', $enunciado);
        }

        // Proteger tags <img> con placeholders
        if ($usarHtml) {
            $enunciado = preg_replace_callback('/<img\s[^>]+>/', function ($match) use (&$placeholders) {
                $key = 'GIFTIMG' . count($placeholders) . 'ENDIMG';
                $placeholders[$key] = $match[0];
                return $key;
            }, $enunciado);
        }

        // 2. Escapar caracteres GIFT
        $enunciado = escaparGift($enunciado);
        $opciones = array_map('escaparGift', $opciones);
        $retros = array_map('escaparGift', $retros);
        $retroGeneral = escaparGift($retroGeneral);

        // 3. Restaurar tags <img>
        if (!empty($placeholders)) {
            $enunciado = str_replace(array_keys($placeholders), array_values($placeholders), $enunciado);
        }

        // 4. Aplicar formato cursiva/negrita
        if ($tieneFormatoGlobal) {
            $fmt = aplicarFormato($enunciado, $cursivas, $negritas);
            $enunciado = $fmt['texto'];
            if ($fmt['tieneFormato']) $usarHtml = true;
            $totalCursivas += $fmt['italicCount'];
            $totalNegritas += $fmt['boldCount'];

            foreach ($opciones as $idx => &$opc) {
                $fmtO = aplicarFormato($opc, $cursivas, $negritas);
                $opc = $fmtO['texto'];
                if ($fmtO['tieneFormato']) $usarHtml = true;
                $totalCursivas += $fmtO['italicCount'];
                $totalNegritas += $fmtO['boldCount'];
            }
            unset($opc);

            foreach ($retros as &$retro) {
                if (!empty($retro)) {
                    $fmtR = aplicarFormato($retro, $cursivas, $negritas);
                    $retro = $fmtR['texto'];
                    if ($fmtR['tieneFormato']) $usarHtml = true;
                    $totalCursivas += $fmtR['italicCount'];
                    $totalNegritas += $fmtR['boldCount'];
                }
            }
            unset($retro);

            if (!empty($retroGeneral)) {
                $fmtRG = aplicarFormato($retroGeneral, $cursivas, $negritas);
                $retroGeneral = $fmtRG['texto'];
                if ($fmtRG['tieneFormato']) $usarHtml = true;
                $totalCursivas += $fmtRG['italicCount'];
                $totalNegritas += $fmtRG['boldCount'];
            }
        }

        // 5. Saltos de linea en enunciado multiparrafo
        if ($usarHtml) {
            $enunciado = str_replace("\n", '<br>', $enunciado);
        } else {
            $enunciado = str_replace("\n", ' ', $enunciado);
        }

        // Construir pregunta GIFT
        $htmlPrefix = $usarHtml ? '[html]' : '';
        $gift .= '::OM Pregunta ' . $preg['numero'] . '::' . $htmlPrefix . $enunciado . '{' . "\n";

        // Primera opcion = correcta (con retro individual)
        if (isset($opciones[0])) {
            $gift .= '=' . $opciones[0];
            if (!empty($retros[0])) {
                $gift .= '#' . $retros[0];
            }
            $gift .= "\n";
        }

        // Opciones incorrectas (cada una con su retro individual)
        for ($i = 1; $i < count($opciones); $i++) {
            $gift .= '~' . $opciones[$i];
            if (!empty($retros[$i])) {
                $gift .= '#' . $retros[$i];
            }
            $gift .= "\n";
        }

        // Retro general (solo si tiene contenido)
        if (!empty($retroGeneral)) {
            $gift .= '####' . $retroGeneral . "\n";
        }

        $gift .= '}' . "\n\n";
    }

    return [
        'gift' => rtrim($gift) . "\n",
        'stats' => [
            'images'  => $totalImagenes,
            'italics' => $totalCursivas,
            'bolds'   => $totalNegritas,
        ],
    ];
}

// --- Funciones GIFT ---

/**
 * Escapa caracteres especiales del formato GIFT
 */
function escaparGift(string $texto): string
{
    $chars = ['\\', '~', '=', '#', '{', '}', ':'];
    $escaped = ['\\\\', '\\~', '\\=', '\\#', '\\{', '\\}', '\\:'];
    return str_replace($chars, $escaped, $texto);
}

/**
 * Genera GIFT para una pregunta FV
 *
 * Moodle GIFT T/F mapea las retroalimentaciones por RESPUESTA (no por resultado):
 *   {RESPUESTA #retroFalso #retroVerdadero}
 *   - Primer  # → siempre retroalimentacion de la respuesta FALSO
 *   - Segundo # → siempre retroalimentacion de la respuesta VERDADERO
 *
 * El orden es fijo independientemente de cual sea la respuesta correcta.
 * El DI escribe "Retro verdadero" = lo que ve el estudiante al elegir Verdadero,
 * y "Retro falso" = lo que ve al elegir Falso.
 */
function generarGiftBloqueFV(array $preg, array $cursivas, array $negritas): array
{
    $enunciado = $preg['enunciado'];
    $retroV    = $preg['retro_verdadero'];
    $retroF    = $preg['retro_falso'];
    $respuesta = $preg['respuesta']; // 'TRUE' o 'FALSE'

    $tieneFormatoGlobal = !empty($cursivas) || !empty($negritas);
    $usarHtml    = false;
    $totalImg    = 0;
    $totalCurs   = 0;
    $totalNeg    = 0;

    $imgResult = procesarImagenes($enunciado);
    $enunciado = $imgResult['texto'];
    $usarHtml  = $imgResult['tieneImagen'];
    $totalImg  = $imgResult['count'];

    $enunciado = escaparGift($enunciado);
    $retroV    = escaparGift($retroV);
    $retroF    = escaparGift($retroF);

    if ($tieneFormatoGlobal) {
        $fmt = aplicarFormato($enunciado, $cursivas, $negritas);
        $enunciado = $fmt['texto'];
        if ($fmt['tieneFormato']) $usarHtml = true;
        $totalCurs += $fmt['italicCount'];
        $totalNeg  += $fmt['boldCount'];

        $fmtV = aplicarFormato($retroV, $cursivas, $negritas);
        $retroV = $fmtV['texto'];
        if ($fmtV['tieneFormato']) $usarHtml = true;
        $totalCurs += $fmtV['italicCount'];
        $totalNeg  += $fmtV['boldCount'];

        $fmtF = aplicarFormato($retroF, $cursivas, $negritas);
        $retroF = $fmtF['texto'];
        if ($fmtF['tieneFormato']) $usarHtml = true;
        $totalCurs += $fmtF['italicCount'];
        $totalNeg  += $fmtF['boldCount'];
    }

    $enunciado = $usarHtml
        ? str_replace("\n", '<br>', $enunciado)
        : str_replace("\n", ' ', $enunciado);

    // Primer # = retroFalso, Segundo # = retroVerdadero (ver docblock)
    $htmlPrefix = $usarHtml ? '[html]' : '';
    $gift = '::FV Pregunta ' . $preg['numero'] . '::' . $htmlPrefix . $enunciado . '{' . $respuesta;
    if (!empty($retroF) || !empty($retroV)) {
        $gift .= '#' . $retroF . '#' . $retroV;
    }
    $gift .= '}' . "\n\n";

    return [
        'gift'  => $gift,
        'stats' => ['images' => $totalImg, 'italics' => $totalCurs, 'bolds' => $totalNeg],
    ];
}

/**
 * Genera GIFT para una pregunta EM
 * Formato: ::EM Pregunta NN:: enunciado { =ItemA -> RespA \n =ItemB -> RespB }
 */
function generarGiftBloqueEM(array $preg, array $cursivas, array $negritas): array
{
    $enunciado = $preg['enunciado'];
    $pares     = $preg['pares'];

    $tieneFormatoGlobal = !empty($cursivas) || !empty($negritas);
    $usarHtml  = false;
    $totalImg  = 0;
    $totalCurs = 0;
    $totalNeg  = 0;

    $imgResult = procesarImagenes($enunciado);
    $enunciado = $imgResult['texto'];
    $usarHtml  = $imgResult['tieneImagen'];
    $totalImg  = $imgResult['count'];

    $enunciado = escaparGift($enunciado);
    foreach ($pares as &$par) {
        $par['izq'] = escaparGift($par['izq']);
        $par['der'] = escaparGift($par['der']);
    }
    unset($par);

    if ($tieneFormatoGlobal) {
        $fmt = aplicarFormato($enunciado, $cursivas, $negritas);
        $enunciado = $fmt['texto'];
        if ($fmt['tieneFormato']) $usarHtml = true;
        $totalCurs += $fmt['italicCount'];
        $totalNeg  += $fmt['boldCount'];

        foreach ($pares as &$par) {
            $fmtI = aplicarFormato($par['izq'], $cursivas, $negritas);
            $par['izq'] = $fmtI['texto'];
            if ($fmtI['tieneFormato']) $usarHtml = true;
            $totalCurs += $fmtI['italicCount'];
            $totalNeg  += $fmtI['boldCount'];

            $fmtD = aplicarFormato($par['der'], $cursivas, $negritas);
            $par['der'] = $fmtD['texto'];
            if ($fmtD['tieneFormato']) $usarHtml = true;
            $totalCurs += $fmtD['italicCount'];
            $totalNeg  += $fmtD['boldCount'];
        }
        unset($par);
    }

    $enunciado = $usarHtml
        ? str_replace("\n", '<br>', $enunciado)
        : str_replace("\n", ' ', $enunciado);

    $htmlPrefix = $usarHtml ? '[html]' : '';
    $gift = '::EM Pregunta ' . $preg['numero'] . '::' . $htmlPrefix . $enunciado . '{' . "\n";
    foreach ($pares as $par) {
        $gift .= '=' . $par['izq'] . ' -> ' . $par['der'] . "\n";
    }
    $gift .= '}' . "\n\n";

    return [
        'gift'  => $gift,
        'stats' => ['images' => $totalImg, 'italics' => $totalCurs, 'bolds' => $totalNeg],
    ];
}

/**
 * Genera GIFT para una pregunta OM
 */
function generarGiftBloqueOM(array $preg, array $cursivas, array $negritas): array
{
    $enunciado  = $preg['enunciado'];
    $opciones   = $preg['opciones'];
    $retroCorr  = $preg['retro_correcta'];
    $retroIncorr = $preg['retro_incorrecta'];

    $tieneFormatoGlobal = !empty($cursivas) || !empty($negritas);
    $placeholders = [];
    $usarHtml     = false;
    $totalImg     = 0;
    $totalCurs    = 0;
    $totalNeg     = 0;

    $imgResult = procesarImagenes($enunciado);
    $enunciado = $imgResult['texto'];
    $usarHtml  = $imgResult['tieneImagen'];
    $totalImg  = $imgResult['count'];

    if ($usarHtml) {
        $enunciado = str_replace('alt=""', 'alt="Imagen de apoyo a la OM Pregunta ' . $preg['numero'] . '"', $enunciado);
        $enunciado = preg_replace_callback('/<img\s[^>]+>/', function ($match) use (&$placeholders) {
            $key = 'GIFTIMG' . count($placeholders) . 'ENDIMG';
            $placeholders[$key] = $match[0];
            return $key;
        }, $enunciado);
    }

    $enunciado  = escaparGift($enunciado);
    $retroCorr  = escaparGift($retroCorr);
    $retroIncorr = escaparGift($retroIncorr);
    $opciones   = array_map('escaparGift', $opciones);

    if (!empty($placeholders)) {
        $enunciado = str_replace(array_keys($placeholders), array_values($placeholders), $enunciado);
    }

    if ($tieneFormatoGlobal) {
        $fmt = aplicarFormato($enunciado, $cursivas, $negritas);
        $enunciado = $fmt['texto'];
        if ($fmt['tieneFormato']) $usarHtml = true;
        $totalCurs += $fmt['italicCount'];
        $totalNeg  += $fmt['boldCount'];

        foreach ($opciones as &$opc) {
            $fmtO = aplicarFormato($opc, $cursivas, $negritas);
            $opc  = $fmtO['texto'];
            if ($fmtO['tieneFormato']) $usarHtml = true;
            $totalCurs += $fmtO['italicCount'];
            $totalNeg  += $fmtO['boldCount'];
        }
        unset($opc);

        $fmtRC = aplicarFormato($retroCorr, $cursivas, $negritas);
        $retroCorr = $fmtRC['texto'];
        if ($fmtRC['tieneFormato']) $usarHtml = true;
        $totalCurs += $fmtRC['italicCount'];
        $totalNeg  += $fmtRC['boldCount'];

        $fmtRI = aplicarFormato($retroIncorr, $cursivas, $negritas);
        $retroIncorr = $fmtRI['texto'];
        if ($fmtRI['tieneFormato']) $usarHtml = true;
        $totalCurs += $fmtRI['italicCount'];
        $totalNeg  += $fmtRI['boldCount'];
    }

    $enunciado = $usarHtml
        ? str_replace("\n", '<br>', $enunciado)
        : str_replace("\n", ' ', $enunciado);

    $htmlPrefix = $usarHtml ? '[html]' : '';
    $gift = '::OM Pregunta ' . $preg['numero'] . '::' . $htmlPrefix . $enunciado . '{' . "\n";

    if (isset($opciones[0])) {
        $gift .= '=' . $opciones[0];
        if (!empty($retroCorr)) $gift .= '#' . $retroCorr;
        $gift .= "\n";
    }

    for ($i = 1; $i < count($opciones); $i++) {
        $gift .= '~' . $opciones[$i];
        if (!empty($retroIncorr)) $gift .= '#' . $retroIncorr;
        $gift .= "\n";
    }

    $gift .= '}' . "\n\n";

    return [
        'gift'  => $gift,
        'stats' => ['images' => $totalImg, 'italics' => $totalCurs, 'bolds' => $totalNeg],
    ];
}

/**
 * Genera GIFT para un array mixto de preguntas (FV, OM, EM)
 */
function generarGiftTodos(array $preguntas, array $cursivas, array $negritas): array
{
    $gift      = '';
    $totalImg  = 0;
    $totalCurs = 0;
    $totalNeg  = 0;

    foreach ($preguntas as $preg) {
        switch ($preg['tipo']) {
            case 'FV':
                $result = generarGiftBloqueFV($preg, $cursivas, $negritas);
                break;
            case 'OM':
                $result = generarGiftBloqueOM($preg, $cursivas, $negritas);
                break;
            case 'EM':
                $result = generarGiftBloqueEM($preg, $cursivas, $negritas);
                break;
            default:
                continue 2;
        }

        $gift      .= $result['gift'];
        $totalImg  += $result['stats']['images'];
        $totalCurs += $result['stats']['italics'];
        $totalNeg  += $result['stats']['bolds'];
    }

    return [
        'gift'  => rtrim($gift) . "\n",
        'stats' => ['images' => $totalImg, 'italics' => $totalCurs, 'bolds' => $totalNeg],
    ];
}

// --- Procesamiento principal ---

// --- Validaciones comunes ---

if (!isset($_FILES['gift_file']) || $_FILES['gift_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No se recibio el archivo']);
    exit;
}

$file = $_FILES['gift_file'];
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, ['docx', 'xlsx'])) {
    echo json_encode(['success' => false, 'message' => 'El archivo debe ser .docx o .xlsx']);
    exit;
}

if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'El archivo excede 10 MB']);
    exit;
}

if (!class_exists('ZipArchive')) {
    echo json_encode(['success' => false, 'message' => 'ZipArchive no disponible en el servidor']);
    exit;
}

$cursivas = array_filter(array_map('trim', explode(',', $_POST['italic_words'] ?? '')));
$negritas = array_filter(array_map('trim', explode(',', $_POST['bold_words'] ?? '')));

// --- Procesar Word (.docx) ---

if ($ext === 'docx') {
    $texto = extraerTextoDocx($file['tmp_name']);
    if (empty(trim($texto))) {
        echo json_encode(['success' => false, 'message' => 'No se pudo extraer texto del archivo']);
        exit;
    }

    $resultado = parsearDocxMixto($texto);
    $preguntas = $resultado['preguntas'];
    $warnings  = $resultado['warnings'];

    if (empty($preguntas)) {
        echo json_encode(['success' => false, 'message' => 'No se encontraron preguntas (FV, OM o EM) en el archivo']);
        exit;
    }

    $omitidas     = 0;
    $sinRetroCorr = 0;
    $sinRetroIncorr = 0;

    foreach ($preguntas as $p) {
        switch ($p['tipo']) {
            case 'OM':
                if (count($p['opciones']) !== 4) $omitidas++;
                if (empty($p['retro_correcta']))   $sinRetroCorr++;
                if (empty($p['retro_incorrecta'])) $sinRetroIncorr++;
                break;
            case 'FV':
                if (empty($p['retro_verdadero']) || empty($p['retro_falso'])) $sinRetroCorr++;
                break;
            case 'EM':
                if (empty($p['pares'])) $omitidas++;
                break;
        }
    }

    $resultado = generarGiftTodos($preguntas, $cursivas, $negritas);

    // Aplicar formato a las preguntas para la previsualización
    $preguntasPreview = formatearPreguntasPreview($preguntas, $cursivas, $negritas);

    echo json_encode([
        'success'   => true,
        'gift'      => $resultado['gift'],
        'preguntas' => $preguntasPreview,
        'warnings'  => $warnings,
        'stats'     => [
            'total'              => count($preguntas),
            'omitted'            => $omitidas,
            'no_retro_correct'   => $sinRetroCorr,
            'no_retro_incorrect' => $sinRetroIncorr,
            'images'             => $resultado['stats']['images'],
            'italics'            => $resultado['stats']['italics'],
            'bolds'              => $resultado['stats']['bolds'],
        ],
    ]);
    exit;
}

// --- Procesar Excel (.xlsx) ---

if ($ext === 'xlsx') {
    $filas = extraerDatosXlsx($file['tmp_name']);
    if (count($filas) <= 1) {
        echo json_encode(['success' => false, 'message' => 'No se encontraron datos en el archivo']);
        exit;
    }

    $resultado = parsearPreguntasExcel($filas);
    $preguntas = $resultado['preguntas'];
    $warnings = $resultado['warnings'];

    if (empty($preguntas)) {
        echo json_encode(['success' => false, 'message' => 'No se encontraron preguntas en el archivo']);
        exit;
    }

    $omitidas = 0;
    $sinRetro = 0;
    foreach ($preguntas as $p) {
        if (count($p['opciones']) !== 4) $omitidas++;
        foreach ($p['retros'] as $retro) {
            if (empty($retro)) $sinRetro++;
        }
    }

    $resultado = generarGiftExcel($preguntas, $cursivas, $negritas);

    $preguntasPreview = formatearPreguntasPreview($preguntas, $cursivas, $negritas);

    echo json_encode([
        'success'   => true,
        'gift'      => $resultado['gift'],
        'preguntas' => $preguntasPreview,
        'warnings'  => $warnings,
        'stats'     => [
            'total'    => count($preguntas),
            'omitted'  => $omitidas,
            'no_retro' => $sinRetro,
            'images'   => $resultado['stats']['images'],
            'italics'  => $resultado['stats']['italics'],
            'bolds'    => $resultado['stats']['bolds'],
        ],
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Formato de archivo no soportado']);
