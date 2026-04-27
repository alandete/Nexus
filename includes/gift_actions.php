<?php
/**
 * Nexus 2.0 — Gift Actions
 * Procesa archivos .docx/.xlsx y genera GIFT (Moodle) o QTI 1.2 (Canvas)
 */
define('APP_ACCESS', true);
ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/question_parser.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    giftOut(['success' => false, 'message' => 'No autorizado']);
}

if (!validateCsrf()) {
    http_response_code(403);
    giftOut(['success' => false, 'message' => 'Token CSRF inválido']);
}

$currentUser = getCurrentUser();
if (!hasPermission($currentUser, 'utilities', 'write')) {
    http_response_code(403);
    giftOut(['success' => false, 'message' => 'Sin permiso']);
}

$action = $_POST['action'] ?? '';
match ($action) {
    'process' => giftHandleProcess(),
    default   => giftOut(['success' => false, 'message' => 'Acción no válida']),
};

// ═════════════════════════════════════════════════════════════════════════════
// OUTPUT HELPER
// ═════════════════════════════════════════════════════════════════════════════

function giftOut(array $data): never
{
    ob_end_clean();
    echo json_encode($data);
    exit;
}

// ═════════════════════════════════════════════════════════════════════════════
// PROCESAMIENTO PRINCIPAL
// ═════════════════════════════════════════════════════════════════════════════

function giftHandleProcess(): void
{
    if (!isset($_FILES['gift_file']) || $_FILES['gift_file']['error'] !== UPLOAD_ERR_OK) {
        $errCode = $_FILES['gift_file']['error'] ?? -1;
        $msg     = match ($errCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'El archivo supera el tamaño máximo permitido',
            UPLOAD_ERR_NO_FILE => 'No se recibió ningún archivo',
            default            => 'Error al recibir el archivo',
        };
        giftOut(['success' => false, 'message' => $msg]);
    }

    if (!class_exists('ZipArchive')) {
        giftOut(['success' => false, 'message' => 'ZipArchive no disponible en el servidor']);
    }

    $file   = $_FILES['gift_file'];
    $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $format = ($_POST['output_format'] ?? 'gift') === 'qti' ? 'qti' : 'gift';

    if (!in_array($ext, ['docx', 'xlsx'])) {
        giftOut(['success' => false, 'message' => 'El archivo debe ser .docx o .xlsx']);
    }

    if ($file['size'] > 10 * 1024 * 1024) {
        giftOut(['success' => false, 'message' => 'El archivo excede 10 MB']);
    }

    $cursivas = array_filter(array_map('trim', explode(',', $_POST['italic_words'] ?? '')));
    $negritas = array_filter(array_map('trim', explode(',', $_POST['bold_words'] ?? '')));
    $bankName = trim($_POST['qti_bank_name'] ?? '') ?: 'Nexus Questions';
    $qtiMode  = ($_POST['qti_mode'] ?? 'bank') === 'quiz' ? 'quiz' : 'bank';

    if ($ext === 'docx') {
        giftProcessDocx($file['tmp_name'], $format, $cursivas, $negritas, $bankName, $qtiMode);
    } else {
        giftProcessXlsx($file['tmp_name'], $format, $cursivas, $negritas, $bankName, $qtiMode);
    }
}

function giftProcessDocx(string $tmpName, string $format, array $cursivas, array $negritas, string $bankName, string $qtiMode): void
{
    $texto = extraerTextoDocx($tmpName);
    if (empty(trim($texto))) {
        giftOut(['success' => false, 'message' => 'No se pudo extraer texto del archivo']);
    }

    $resultado = parsearDocxMixto($texto);
    $preguntas = $resultado['preguntas'];
    $warnings  = $resultado['warnings'];

    if (empty($preguntas)) {
        giftOut(['success' => false, 'message' => 'No se encontraron preguntas (FV, OM o EM) en el archivo']);
    }

    $omitidas = $sinRetroCorr = $sinRetroIncorr = 0;
    foreach ($preguntas as $p) {
        match ($p['tipo']) {
            'OM' => (function () use ($p, &$omitidas, &$sinRetroCorr, &$sinRetroIncorr) {
                if (count($p['opciones']) !== 4)   $omitidas++;
                if (empty($p['retro_correcta']))   $sinRetroCorr++;
                if (empty($p['retro_incorrecta'])) $sinRetroIncorr++;
            })(),
            'FV' => (function () use ($p, &$sinRetroCorr) {
                if (empty($p['retro_verdadero']) || empty($p['retro_falso'])) $sinRetroCorr++;
            })(),
            'EM' => (function () use ($p, &$omitidas) {
                if (empty($p['pares'])) $omitidas++;
            })(),
            default => null,
        };
    }

    $preguntasPreview = formatearPreguntasPreview($preguntas, $cursivas, $negritas);
    $stats = [
        'total'              => count($preguntas),
        'omitted'            => $omitidas,
        'no_retro_correct'   => $sinRetroCorr,
        'no_retro_incorrect' => $sinRetroIncorr,
    ];

    if ($format === 'qti') {
        $qtiResult    = generarQtiTodos($preguntas, $cursivas, $negritas);
        $assessXml    = qtiEnvolverAssessment($qtiResult['items'], $bankName, $qtiMode);
        $stats += $qtiResult['stats'];
        giftOut([
            'success'   => true,
            'format'    => 'qti',
            'gift'      => $assessXml,
            'zip'       => qtiCrearZip($assessXml, $qtiMode),
            'preguntas' => $preguntasPreview,
            'warnings'  => $warnings,
            'stats'     => $stats,
        ]);
    } else {
        $giftResult = generarGiftTodos($preguntas, $cursivas, $negritas);
        $stats += $giftResult['stats'];
        giftOut([
            'success'   => true,
            'format'    => 'gift',
            'gift'      => $giftResult['gift'],
            'preguntas' => $preguntasPreview,
            'warnings'  => $warnings,
            'stats'     => $stats,
        ]);
    }
}

function giftProcessXlsx(string $tmpName, string $format, array $cursivas, array $negritas, string $bankName, string $qtiMode): void
{
    $filas = extraerDatosXlsx($tmpName);
    if (count($filas) <= 1) {
        giftOut(['success' => false, 'message' => 'No se encontraron datos en el archivo']);
    }

    $resultado = parsearPreguntasExcel($filas);
    $preguntas = $resultado['preguntas'];
    $warnings  = $resultado['warnings'];

    if (empty($preguntas)) {
        giftOut(['success' => false, 'message' => 'No se encontraron preguntas en el archivo']);
    }

    $omitidas = $sinRetro = 0;
    foreach ($preguntas as $p) {
        if (count($p['opciones']) !== 4) $omitidas++;
        foreach ($p['retros'] as $retro) {
            if (empty($retro)) $sinRetro++;
        }
    }

    $preguntasPreview = formatearPreguntasPreview($preguntas, $cursivas, $negritas);
    $stats = ['total' => count($preguntas), 'omitted' => $omitidas, 'no_retro' => $sinRetro];

    if ($format === 'qti') {
        $qtiResult = generarQtiExcel($preguntas, $cursivas, $negritas);
        $assessXml = qtiEnvolverAssessment($qtiResult['items'], $bankName, $qtiMode);
        $stats    += $qtiResult['stats'];
        giftOut([
            'success'   => true,
            'format'    => 'qti',
            'gift'      => $assessXml,
            'zip'       => qtiCrearZip($assessXml, $qtiMode),
            'preguntas' => $preguntasPreview,
            'warnings'  => $warnings,
            'stats'     => $stats,
        ]);
    } else {
        $giftResult = generarGiftExcel($preguntas, $cursivas, $negritas);
        $stats     += $giftResult['stats'];
        giftOut([
            'success'   => true,
            'format'    => 'gift',
            'gift'      => $giftResult['gift'],
            'preguntas' => $preguntasPreview,
            'warnings'  => $warnings,
            'stats'     => $stats,
        ]);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// GENERADORES GIFT
// ═════════════════════════════════════════════════════════════════════════════

function escaparGift(string $texto): string
{
    return str_replace(
        ['\\', '~', '=', '#', '{', '}', ':'],
        ['\\\\', '\\~', '\\=', '\\#', '\\{', '\\}', '\\:'],
        $texto
    );
}

function giftProcesarTexto(string $texto, array $cursivas, array $negritas, array &$stats): array
{
    $imgResult = procesarImagenes($texto);
    $texto     = $imgResult['texto'];
    $usarHtml  = $imgResult['tieneImagen'];
    $stats['images'] += $imgResult['count'];

    // Proteger <img> con placeholders antes de escapar
    $placeholders = [];
    if ($usarHtml) {
        $texto = preg_replace_callback('/<img\s[^>]+>/', function ($m) use (&$placeholders) {
            $key                 = 'GIFTIMG' . count($placeholders) . 'ENDIMG';
            $placeholders[$key] = $m[0];
            return $key;
        }, $texto);
    }

    $texto = escaparGift($texto);
    if (!empty($placeholders)) {
        $texto = str_replace(array_keys($placeholders), array_values($placeholders), $texto);
    }

    // Detectar y contar formato auto-detectado del archivo fuente
    if (tieneFormatoHtml($texto)) {
        $usarHtml = true;
        $stats['bolds']   += substr_count($texto, '<strong>');
        $stats['italics'] += substr_count($texto, '<em>');
    }

    // Aplicar palabras manuales adicionales
    if (!empty($cursivas) || !empty($negritas)) {
        $fmt = aplicarFormato($texto, $cursivas, $negritas);
        $texto = $fmt['texto'];
        if ($fmt['tieneFormato']) $usarHtml = true;
        $stats['italics'] += $fmt['italicCount'];
        $stats['bolds']   += $fmt['boldCount'];
    }

    return ['texto' => $texto, 'usarHtml' => $usarHtml];
}

function generarGiftTodos(array $preguntas, array $cursivas, array $negritas): array
{
    $gift  = '';
    $stats = ['images' => 0, 'italics' => 0, 'bolds' => 0];

    foreach ($preguntas as $preg) {
        $result = match ($preg['tipo']) {
            'FV'    => generarGiftBloqueFV($preg, $cursivas, $negritas, $stats),
            'OM'    => generarGiftBloqueOM($preg, $cursivas, $negritas, $stats),
            'EM'    => generarGiftBloqueEM($preg, $cursivas, $negritas, $stats),
            default => null,
        };
        if ($result !== null) $gift .= $result;
    }

    return ['gift' => rtrim($gift) . "\n", 'stats' => $stats];
}

function generarGiftExcel(array $preguntas, array $cursivas, array $negritas): array
{
    $gift  = '';
    $stats = ['images' => 0, 'italics' => 0, 'bolds' => 0];

    foreach ($preguntas as $preg) {
        $res      = giftProcesarTexto($preg['enunciado'], $cursivas, $negritas, $stats);
        $enunc    = $res['usarHtml'] ? str_replace("\n", '<br>', $res['texto']) : str_replace("\n", ' ', $res['texto']);
        $prefix   = $res['usarHtml'] ? '[html]' : '';
        $usarHtml = $res['usarHtml'];

        $opciones = [];
        foreach ($preg['opciones'] as $opc) {
            $r        = giftProcesarTexto($opc, $cursivas, $negritas, $stats);
            if ($r['usarHtml']) $usarHtml = true;
            $opciones[] = $r['texto'];
        }
        $retros = [];
        foreach ($preg['retros'] as $retro) {
            $r = giftProcesarTexto($retro, $cursivas, $negritas, $stats);
            if ($r['usarHtml']) $usarHtml = true;
            $retros[] = $r['texto'];
        }
        $rg = '';
        if (!empty($preg['retro_general'])) {
            $r = giftProcesarTexto($preg['retro_general'], $cursivas, $negritas, $stats);
            if ($r['usarHtml']) $usarHtml = true;
            $rg = $r['texto'];
        }

        // Recalcular prefix después de procesar todo
        $prefix = $usarHtml ? '[html]' : '';

        $gift .= '::OM Pregunta ' . $preg['numero'] . '::' . "\n";
        $gift .= $prefix . $enunc . " {\n";
        if (isset($opciones[0])) {
            $gift .= '=' . $opciones[0];
            if (!empty($retros[0])) $gift .= '#' . $retros[0];
            $gift .= "\n";
        }
        for ($i = 1; $i < count($opciones); $i++) {
            $gift .= '~' . $opciones[$i];
            if (!empty($retros[$i])) $gift .= '#' . $retros[$i];
            $gift .= "\n";
        }
        if (!empty($rg)) $gift .= '####' . $rg . "\n";
        $gift .= '}' . "\n\n";
    }

    return ['gift' => rtrim($gift) . "\n", 'stats' => $stats];
}

function generarGiftBloqueFV(array $preg, array $cursivas, array $negritas, array &$stats): string
{
    $resE = giftProcesarTexto($preg['enunciado'], $cursivas, $negritas, $stats);
    $resV = giftProcesarTexto($preg['retro_verdadero'], $cursivas, $negritas, $stats);
    $resF = giftProcesarTexto($preg['retro_falso'], $cursivas, $negritas, $stats);

    $usarHtml = $resE['usarHtml'] || $resV['usarHtml'] || $resF['usarHtml'];
    $enunc    = $usarHtml ? str_replace("\n", '<br>', $resE['texto']) : str_replace("\n", ' ', $resE['texto']);
    $prefix   = $usarHtml ? '[html]' : '';

    $inner = $preg['respuesta'];
    if (!empty($resF['texto']) || !empty($resV['texto'])) {
        $inner .= '#' . $resF['texto'] . '#' . $resV['texto'];
    }
    $gift  = '::FV Pregunta ' . $preg['numero'] . '::' . "\n";
    $gift .= $prefix . $enunc . " {\n" . $inner . "\n}\n\n";
    return $gift;
}

function generarGiftBloqueOM(array $preg, array $cursivas, array $negritas, array &$stats): string
{
    $resE = giftProcesarTexto($preg['enunciado'], $cursivas, $negritas, $stats);
    $resC = giftProcesarTexto($preg['retro_correcta'], $cursivas, $negritas, $stats);
    $resI = giftProcesarTexto($preg['retro_incorrecta'], $cursivas, $negritas, $stats);

    $opciones = [];
    $usarHtml = $resE['usarHtml'] || $resC['usarHtml'] || $resI['usarHtml'];
    foreach ($preg['opciones'] as $opc) {
        $r = giftProcesarTexto($opc, $cursivas, $negritas, $stats);
        if ($r['usarHtml']) $usarHtml = true;
        $opciones[] = $r['texto'];
    }

    $enunc  = $usarHtml ? str_replace("\n", '<br>', $resE['texto']) : str_replace("\n", ' ', $resE['texto']);
    $prefix = $usarHtml ? '[html]' : '';

    $gift  = '::OM Pregunta ' . $preg['numero'] . '::' . "\n";
    $gift .= $prefix . $enunc . " {\n";
    if (isset($opciones[0])) {
        $gift .= '=' . $opciones[0];
        if (!empty($resC['texto'])) $gift .= '#' . $resC['texto'];
        $gift .= "\n";
    }
    for ($i = 1; $i < count($opciones); $i++) {
        $gift .= '~' . $opciones[$i];
        if (!empty($resI['texto'])) $gift .= '#' . $resI['texto'];
        $gift .= "\n";
    }
    $gift .= "}\n\n";
    return $gift;
}

function generarGiftBloqueEM(array $preg, array $cursivas, array $negritas, array &$stats): string
{
    $resE = giftProcesarTexto($preg['enunciado'], $cursivas, $negritas, $stats);

    $pares    = [];
    $usarHtml = $resE['usarHtml'];
    foreach ($preg['pares'] as $par) {
        $rI = giftProcesarTexto($par['izq'], $cursivas, $negritas, $stats);
        $rD = giftProcesarTexto($par['der'], $cursivas, $negritas, $stats);
        if ($rI['usarHtml'] || $rD['usarHtml']) $usarHtml = true;
        $pares[] = ['izq' => $rI['texto'], 'der' => $rD['texto']];
    }

    $enunc  = $usarHtml ? str_replace("\n", '<br>', $resE['texto']) : str_replace("\n", ' ', $resE['texto']);
    $prefix = $usarHtml ? '[html]' : '';

    $gift  = '::EM Pregunta ' . $preg['numero'] . '::' . "\n";
    $gift .= $prefix . $enunc . " {\n";
    foreach ($pares as $par) {
        $gift .= '=' . $par['izq'] . ' -> ' . $par['der'] . "\n";
    }
    $gift .= "}\n\n";
    return $gift;
}

// ═════════════════════════════════════════════════════════════════════════════
// GENERADORES QTI 1.2
// ═════════════════════════════════════════════════════════════════════════════

function qtiId(string $prefix = 'g'): string
{
    return $prefix . bin2hex(random_bytes(8));
}

function qtiEscape(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function qtiPrepararTexto(string $texto, array $cursivas, array $negritas, array &$stats): string
{
    $imgResult = procesarImagenes($texto);
    $texto     = $imgResult['texto'];
    $stats['images'] += $imgResult['count'];

    if (!empty($cursivas) || !empty($negritas)) {
        $fmt = aplicarFormato($texto, $cursivas, $negritas);
        $texto = $fmt['texto'];
        $stats['italics'] += $fmt['italicCount'];
        $stats['bolds']   += $fmt['boldCount'];
    }

    return str_replace("\n", '<br/>', $texto);
}

function generarQtiTodos(array $preguntas, array $cursivas, array $negritas): array
{
    $stats = ['images' => 0, 'italics' => 0, 'bolds' => 0];
    $items = '';

    foreach ($preguntas as $preg) {
        $items .= match ($preg['tipo']) {
            'FV'    => qtiGenerarFV($preg, $cursivas, $negritas, $stats),
            'OM'    => qtiGenerarOM($preg, $cursivas, $negritas, $stats),
            'EM'    => qtiGenerarEM($preg, $cursivas, $negritas, $stats),
            default => '',
        };
    }

    return ['items' => $items, 'stats' => $stats];
}

function generarQtiExcel(array $preguntas, array $cursivas, array $negritas): array
{
    $stats = ['images' => 0, 'italics' => 0, 'bolds' => 0];
    $items = '';
    foreach ($preguntas as $preg) {
        $items .= qtiGenerarOMExcel($preg, $cursivas, $negritas, $stats);
    }
    return ['items' => $items, 'stats' => $stats];
}

function qtiGenerarOM(array $preg, array $cursivas, array $negritas, array &$stats): string
{
    $itemId    = qtiId('q');
    $titulo    = 'OM Pregunta ' . $preg['numero'];
    $enunciado = qtiPrepararTexto($preg['enunciado'], $cursivas, $negritas, $stats);
    $opciones  = $preg['opciones'];
    $retroCorr  = $preg['retro_correcta'] ?? '';
    $retroIncorr = $preg['retro_incorrecta'] ?? '';

    $opcionIds  = array_map(fn () => qtiId('a'), $opciones);
    $correctaId = $opcionIds[0] ?? '';

    $xml  = '  <item ident="' . $itemId . '" title="' . qtiEscape($titulo) . '">' . "\n";
    $xml .= '    <itemmetadata><qtimetadata><qtimetadatafield><fieldlabel>question_type</fieldlabel><fieldentry>multiple_choice_question</fieldentry></qtimetadatafield></qtimetadata></itemmetadata>' . "\n";
    $xml .= '    <presentation><material><mattext texttype="text/html"><![CDATA[' . $enunciado . ']]></mattext></material>' . "\n";
    $xml .= '      <response_lid ident="response1" rcardinality="Single"><render_choice>' . "\n";
    foreach ($opciones as $i => $opc) {
        $opcTexto = qtiPrepararTexto($opc, $cursivas, $negritas, $stats);
        $xml .= '        <response_label ident="' . $opcionIds[$i] . '"><material><mattext texttype="text/html"><![CDATA[' . $opcTexto . ']]></mattext></material></response_label>' . "\n";
    }
    $xml .= '      </render_choice></response_lid></presentation>' . "\n";
    $xml .= '    <resprocessing><outcomes><decvar maxvalue="100" minvalue="0" varname="SCORE" vartype="Decimal"/></outcomes>' . "\n";
    $xml .= '      <respcondition continue="No"><conditionvar><varequal respident="response1">' . $correctaId . '</varequal></conditionvar><setvar action="Set" varname="SCORE">100</setvar>';
    if (!empty($retroCorr)) $xml .= '<displayfeedback feedbacktype="Response" linkrefid="' . $correctaId . '_fb"/>';
    $xml .= '</respcondition>' . "\n";
    for ($i = 1; $i < count($opciones); $i++) {
        $xml .= '      <respcondition continue="No"><conditionvar><varequal respident="response1">' . $opcionIds[$i] . '</varequal></conditionvar>';
        if (!empty($retroIncorr)) $xml .= '<displayfeedback feedbacktype="Response" linkrefid="' . $opcionIds[$i] . '_fb"/>';
        $xml .= '</respcondition>' . "\n";
    }
    $xml .= '    </resprocessing>' . "\n";
    if (!empty($retroCorr)) {
        $rt   = qtiPrepararTexto($retroCorr, $cursivas, $negritas, $stats);
        $xml .= '    <itemfeedback ident="' . $correctaId . '_fb"><flow_mat><material><mattext texttype="text/html"><![CDATA[' . $rt . ']]></mattext></material></flow_mat></itemfeedback>' . "\n";
    }
    if (!empty($retroIncorr)) {
        $rt = qtiPrepararTexto($retroIncorr, $cursivas, $negritas, $stats);
        for ($i = 1; $i < count($opciones); $i++) {
            $xml .= '    <itemfeedback ident="' . $opcionIds[$i] . '_fb"><flow_mat><material><mattext texttype="text/html"><![CDATA[' . $rt . ']]></mattext></material></flow_mat></itemfeedback>' . "\n";
        }
    }
    $xml .= '  </item>' . "\n";
    return $xml;
}

function qtiGenerarOMExcel(array $preg, array $cursivas, array $negritas, array &$stats): string
{
    $itemId    = qtiId('q');
    $titulo    = 'OM Pregunta ' . $preg['numero'];
    $enunciado = qtiPrepararTexto($preg['enunciado'], $cursivas, $negritas, $stats);
    $opciones  = $preg['opciones'];
    $retros    = $preg['retros'];
    $opcionIds = array_map(fn () => qtiId('a'), $opciones);

    $xml  = '  <item ident="' . $itemId . '" title="' . qtiEscape($titulo) . '">' . "\n";
    $xml .= '    <itemmetadata><qtimetadata><qtimetadatafield><fieldlabel>question_type</fieldlabel><fieldentry>multiple_choice_question</fieldentry></qtimetadatafield></qtimetadata></itemmetadata>' . "\n";
    $xml .= '    <presentation><material><mattext texttype="text/html"><![CDATA[' . $enunciado . ']]></mattext></material>' . "\n";
    $xml .= '      <response_lid ident="response1" rcardinality="Single"><render_choice>' . "\n";
    foreach ($opciones as $i => $opc) {
        $ot   = qtiPrepararTexto($opc, $cursivas, $negritas, $stats);
        $xml .= '        <response_label ident="' . $opcionIds[$i] . '"><material><mattext texttype="text/html"><![CDATA[' . $ot . ']]></mattext></material></response_label>' . "\n";
    }
    $xml .= '      </render_choice></response_lid></presentation>' . "\n";
    $xml .= '    <resprocessing><outcomes><decvar maxvalue="100" minvalue="0" varname="SCORE" vartype="Decimal"/></outcomes>' . "\n";
    foreach ($opciones as $i => $opc) {
        $xml .= '      <respcondition continue="No"><conditionvar><varequal respident="response1">' . $opcionIds[$i] . '</varequal></conditionvar>';
        if ($i === 0) $xml .= '<setvar action="Set" varname="SCORE">100</setvar>';
        if (!empty($retros[$i])) $xml .= '<displayfeedback feedbacktype="Response" linkrefid="' . $opcionIds[$i] . '_fb"/>';
        $xml .= '</respcondition>' . "\n";
    }
    $xml .= '    </resprocessing>' . "\n";
    foreach ($retros as $i => $retro) {
        if (!empty($retro)) {
            $rt   = qtiPrepararTexto($retro, $cursivas, $negritas, $stats);
            $xml .= '    <itemfeedback ident="' . $opcionIds[$i] . '_fb"><flow_mat><material><mattext texttype="text/html"><![CDATA[' . $rt . ']]></mattext></material></flow_mat></itemfeedback>' . "\n";
        }
    }
    if (!empty($preg['retro_general'])) {
        $rt   = qtiPrepararTexto($preg['retro_general'], $cursivas, $negritas, $stats);
        $xml .= '    <itemfeedback ident="general_fb"><flow_mat><material><mattext texttype="text/html"><![CDATA[' . $rt . ']]></mattext></material></flow_mat></itemfeedback>' . "\n";
    }
    $xml .= '  </item>' . "\n";
    return $xml;
}

function qtiGenerarFV(array $preg, array $cursivas, array $negritas, array &$stats): string
{
    $itemId    = qtiId('q');
    $titulo    = 'FV Pregunta ' . $preg['numero'];
    $enunciado = qtiPrepararTexto($preg['enunciado'], $cursivas, $negritas, $stats);
    $respuesta = $preg['respuesta'];
    $retroV    = $preg['retro_verdadero'];
    $retroF    = $preg['retro_falso'];
    $trueId  = qtiId('a');
    $falseId = qtiId('a');

    $xml  = '  <item ident="' . $itemId . '" title="' . qtiEscape($titulo) . '">' . "\n";
    $xml .= '    <itemmetadata><qtimetadata><qtimetadatafield><fieldlabel>question_type</fieldlabel><fieldentry>true_false_question</fieldentry></qtimetadatafield></qtimetadata></itemmetadata>' . "\n";
    $xml .= '    <presentation><material><mattext texttype="text/html"><![CDATA[' . $enunciado . ']]></mattext></material>' . "\n";
    $xml .= '      <response_lid ident="response1" rcardinality="Single"><render_choice>' . "\n";
    $xml .= '        <response_label ident="' . $trueId  . '"><material><mattext>Verdadero</mattext></material></response_label>' . "\n";
    $xml .= '        <response_label ident="' . $falseId . '"><material><mattext>Falso</mattext></material></response_label>' . "\n";
    $xml .= '      </render_choice></response_lid></presentation>' . "\n";
    $xml .= '    <resprocessing><outcomes><decvar maxvalue="100" minvalue="0" varname="SCORE" vartype="Decimal"/></outcomes>' . "\n";

    foreach ([[$trueId, $retroV, $respuesta === 'TRUE'], [$falseId, $retroF, $respuesta === 'FALSE']] as [$id, $retro, $correcta]) {
        $xml .= '      <respcondition continue="No"><conditionvar><varequal respident="response1">' . $id . '</varequal></conditionvar>';
        if ($correcta) $xml .= '<setvar action="Set" varname="SCORE">100</setvar>';
        if (!empty($retro)) $xml .= '<displayfeedback feedbacktype="Response" linkrefid="' . $id . '_fb"/>';
        $xml .= '</respcondition>' . "\n";
    }
    $xml .= '    </resprocessing>' . "\n";
    if (!empty($retroV)) {
        $rt   = qtiPrepararTexto($retroV, $cursivas, $negritas, $stats);
        $xml .= '    <itemfeedback ident="' . $trueId . '_fb"><flow_mat><material><mattext texttype="text/html"><![CDATA[' . $rt . ']]></mattext></material></flow_mat></itemfeedback>' . "\n";
    }
    if (!empty($retroF)) {
        $rt   = qtiPrepararTexto($retroF, $cursivas, $negritas, $stats);
        $xml .= '    <itemfeedback ident="' . $falseId . '_fb"><flow_mat><material><mattext texttype="text/html"><![CDATA[' . $rt . ']]></mattext></material></flow_mat></itemfeedback>' . "\n";
    }
    $xml .= '  </item>' . "\n";
    return $xml;
}

function qtiGenerarEM(array $preg, array $cursivas, array $negritas, array &$stats): string
{
    $itemId    = qtiId('q');
    $titulo    = 'EM Pregunta ' . $preg['numero'];
    $enunciado = qtiPrepararTexto($preg['enunciado'], $cursivas, $negritas, $stats);
    $pares     = $preg['pares'];
    $respIds   = array_map(fn () => qtiId('r'), $pares);
    $answerIds = array_map(fn () => qtiId('a'), $pares);

    $xml  = '  <item ident="' . $itemId . '" title="' . qtiEscape($titulo) . '">' . "\n";
    $xml .= '    <itemmetadata><qtimetadata><qtimetadatafield><fieldlabel>question_type</fieldlabel><fieldentry>matching_question</fieldentry></qtimetadatafield></qtimetadata></itemmetadata>' . "\n";
    $xml .= '    <presentation>';
    if (!empty($enunciado)) {
        $xml .= '<material><mattext texttype="text/html"><![CDATA[' . $enunciado . ']]></mattext></material>';
    }
    $xml .= "\n";
    foreach ($pares as $i => $par) {
        $izq  = qtiPrepararTexto($par['izq'], $cursivas, $negritas, $stats);
        $xml .= '      <response_lid ident="' . $respIds[$i] . '">' . "\n";
        $xml .= '        <material><mattext texttype="text/html"><![CDATA[' . $izq . ']]></mattext></material>' . "\n";
        $xml .= '        <render_choice>' . "\n";
        foreach ($pares as $j => $parJ) {
            $der  = qtiPrepararTexto($parJ['der'], $cursivas, $negritas, $stats);
            $xml .= '          <response_label ident="' . $answerIds[$j] . '"><material><mattext texttype="text/html"><![CDATA[' . $der . ']]></mattext></material></response_label>' . "\n";
        }
        $xml .= '        </render_choice></response_lid>' . "\n";
    }
    $xml .= '    </presentation>' . "\n";
    $xml .= '    <resprocessing><outcomes><decvar maxvalue="100" minvalue="0" varname="SCORE" vartype="Decimal"/></outcomes>' . "\n";
    $xml .= '      <respcondition continue="No"><conditionvar><and>' . "\n";
    foreach ($pares as $i => $par) {
        $xml .= '        <varequal respident="' . $respIds[$i] . '">' . $answerIds[$i] . '</varequal>' . "\n";
    }
    $xml .= '      </and></conditionvar><setvar action="Set" varname="SCORE">100</setvar></respcondition>' . "\n";
    $xml .= '    </resprocessing>' . "\n";
    $xml .= '  </item>' . "\n";
    return $xml;
}

function qtiEnvolverAssessment(string $items, string $titulo = 'Nexus Questions', string $mode = 'bank'): string
{
    $ident = qtiId($mode === 'bank' ? 'bank' : 'assess');

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<questestinterop xmlns="http://www.imsglobal.org/xsd/ims_qtiasiv1p2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.imsglobal.org/xsd/ims_qtiasiv1p2 http://www.imsglobal.org/xsd/ims_qtiasiv1p2p1.xsd">' . "\n";

    if ($mode === 'bank') {
        $bankIdent = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $titulo) ?: $ident;
        $xml .= '<objectbank ident="' . qtiEscape($bankIdent) . '" title="' . qtiEscape($titulo) . '">' . "\n";
        $xml .= $items;
        $xml .= '</objectbank>' . "\n";
    } else {
        $sectionId = qtiId('sec');
        $xml .= '<assessment ident="' . $ident . '" title="' . qtiEscape($titulo) . '">' . "\n";
        $xml .= '  <qtimetadata><qtimetadatafield><fieldlabel>cc_maxattempts</fieldlabel><fieldentry>1</fieldentry></qtimetadatafield></qtimetadata>' . "\n";
        $xml .= '  <section ident="' . $sectionId . '">' . "\n";
        $xml .= $items;
        $xml .= '  </section>' . "\n";
        $xml .= '</assessment>' . "\n";
    }

    $xml .= '</questestinterop>' . "\n";
    return $xml;
}

function qtiCrearZip(string $assessmentXml, string $mode = 'bank'): ?string
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'qti_');
    $zip     = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) return null;

    $assessFile = $mode === 'bank' ? 'objectbank.xml' : 'assessment.xml';
    $manifestId = qtiId('man');
    $resourceId = qtiId('res');
    $resType    = $mode === 'bank' ? 'imsqti_xmlv1p2/imscc_xmlv1p1/question-bank' : 'imsqti_xmlv1p2/imscc_xmlv1p1/assessment';

    $manifest  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $manifest .= '<manifest identifier="' . $manifestId . '" xmlns="http://www.imsglobal.org/xsd/imsccv1p1/imscp_v1p1">' . "\n";
    $manifest .= '  <metadata><schema>IMS Content</schema><schemaversion>1.1.3</schemaversion></metadata>' . "\n";
    $manifest .= '  <organizations/><resources>' . "\n";
    $manifest .= '  <resource identifier="' . $resourceId . '" type="' . $resType . '"><file href="' . htmlspecialchars($assessFile, ENT_XML1) . '"/></resource>' . "\n";
    $manifest .= '  </resources></manifest>' . "\n";

    $zip->addFromString($assessFile, $assessmentXml);
    $zip->addFromString('imsmanifest.xml', $manifest);
    $zip->close();

    $content = file_get_contents($tmpFile);
    @unlink($tmpFile);
    return base64_encode($content);
}
