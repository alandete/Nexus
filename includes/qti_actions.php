<?php
/**
 * S4Learning - QTI Actions Handler
 * Procesa archivos .docx/.xlsx y genera formato QTI 1.2 para Canvas LMS
 * Reutiliza las funciones de parsing de question_parser.php
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

// ═════════════════════════════════════════════════════════════════════════════
// GENERADORES QTI 1.2
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Genera un identificador unico para items QTI
 */
function qtiId(string $prefix = 'g'): string
{
    return $prefix . bin2hex(random_bytes(8));
}

/**
 * Escapa texto para uso dentro de XML/HTML en QTI
 */
function qtiEscape(string $texto): string
{
    return htmlspecialchars($texto, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

/**
 * Prepara el texto del enunciado: imagenes + formato + saltos de linea
 */
function qtiPrepararTexto(string $texto, array $cursivas, array $negritas, array &$stats): string
{
    // Procesar imagenes
    $imgResult = procesarImagenes($texto);
    $texto = $imgResult['texto'];
    $stats['images'] += $imgResult['count'];

    // Aplicar formato
    if (!empty($cursivas) || !empty($negritas)) {
        $fmt = aplicarFormato($texto, $cursivas, $negritas);
        $texto = $fmt['texto'];
        $stats['italics'] += $fmt['italicCount'];
        $stats['bolds']   += $fmt['boldCount'];
    }

    // Saltos de linea
    $texto = str_replace("\n", '<br/>', $texto);

    return $texto;
}

// ── Generador OM (Opcion Multiple) ──────────────────────────────────────────

/**
 * Genera un item QTI para pregunta de Opcion Multiple (Word: retro compartida)
 */
function qtiGenerarOM(array $preg, array $cursivas, array $negritas, array &$stats): string
{
    $itemId    = qtiId('q');
    $titulo    = 'OM Pregunta ' . $preg['numero'];
    $enunciado = $preg['enunciado'];
    $opciones  = $preg['opciones'];
    $retroCorr  = $preg['retro_correcta'] ?? '';
    $retroIncorr = $preg['retro_incorrecta'] ?? '';

    $enunciado = qtiPrepararTexto($enunciado, $cursivas, $negritas, $stats);

    // Generar IDs para opciones
    $opcionIds = [];
    foreach ($opciones as $i => $opc) {
        $opcionIds[] = qtiId('a');
    }
    $correctaId = $opcionIds[0] ?? '';

    $xml  = '  <item ident="' . $itemId . '" title="' . qtiEscape($titulo) . '">' . "\n";
    $xml .= '    <itemmetadata><qtimetadata>' . "\n";
    $xml .= '      <qtimetadatafield><fieldlabel>question_type</fieldlabel><fieldentry>multiple_choice_question</fieldentry></qtimetadatafield>' . "\n";
    $xml .= '    </qtimetadata></itemmetadata>' . "\n";

    // Presentacion
    $xml .= '    <presentation>' . "\n";
    $xml .= '      <material><mattext texttype="text/html"><![CDATA[' . $enunciado . ']]></mattext></material>' . "\n";
    $xml .= '      <response_lid ident="response1" rcardinality="Single">' . "\n";
    $xml .= '        <render_choice>' . "\n";

    foreach ($opciones as $i => $opc) {
        $opcTexto = qtiPrepararTexto($opc, $cursivas, $negritas, $stats);
        $xml .= '          <response_label ident="' . $opcionIds[$i] . '"><material><mattext texttype="text/html"><![CDATA[' . $opcTexto . ']]></mattext></material></response_label>' . "\n";
    }

    $xml .= '        </render_choice>' . "\n";
    $xml .= '      </response_lid>' . "\n";
    $xml .= '    </presentation>' . "\n";

    // Procesamiento de respuestas
    $xml .= '    <resprocessing>' . "\n";
    $xml .= '      <outcomes><decvar maxvalue="100" minvalue="0" varname="SCORE" vartype="Decimal"/></outcomes>' . "\n";

    // Respuesta correcta
    $xml .= '      <respcondition continue="No">' . "\n";
    $xml .= '        <conditionvar><varequal respident="response1">' . $correctaId . '</varequal></conditionvar>' . "\n";
    $xml .= '        <setvar action="Set" varname="SCORE">100</setvar>' . "\n";
    if (!empty($retroCorr)) {
        $xml .= '        <displayfeedback feedbacktype="Response" linkrefid="' . $correctaId . '_fb"/>' . "\n";
    }
    $xml .= '      </respcondition>' . "\n";

    // Respuestas incorrectas
    for ($i = 1; $i < count($opciones); $i++) {
        $xml .= '      <respcondition continue="No">' . "\n";
        $xml .= '        <conditionvar><varequal respident="response1">' . $opcionIds[$i] . '</varequal></conditionvar>' . "\n";
        if (!empty($retroIncorr)) {
            $xml .= '        <displayfeedback feedbacktype="Response" linkrefid="' . $opcionIds[$i] . '_fb"/>' . "\n";
        }
        $xml .= '      </respcondition>' . "\n";
    }

    $xml .= '    </resprocessing>' . "\n";

    // Feedback
    if (!empty($retroCorr)) {
        $retroCorrTexto = qtiPrepararTexto($retroCorr, $cursivas, $negritas, $stats);
        $xml .= '    <itemfeedback ident="' . $correctaId . '_fb"><flow_mat><material><mattext texttype="text/html"><![CDATA[' . $retroCorrTexto . ']]></mattext></material></flow_mat></itemfeedback>' . "\n";
    }
    if (!empty($retroIncorr)) {
        $retroIncorrTexto = qtiPrepararTexto($retroIncorr, $cursivas, $negritas, $stats);
        for ($i = 1; $i < count($opciones); $i++) {
            $xml .= '    <itemfeedback ident="' . $opcionIds[$i] . '_fb"><flow_mat><material><mattext texttype="text/html"><![CDATA[' . $retroIncorrTexto . ']]></mattext></material></flow_mat></itemfeedback>' . "\n";
        }
    }

    $xml .= '  </item>' . "\n";
    return $xml;
}

/**
 * Genera un item QTI para pregunta de Opcion Multiple (Excel: retro individual por opcion)
 */
function qtiGenerarOMExcel(array $preg, array $cursivas, array $negritas, array &$stats): string
{
    $itemId    = qtiId('q');
    $titulo    = 'OM Pregunta ' . $preg['numero'];
    $enunciado = $preg['enunciado'];
    $opciones  = $preg['opciones'];
    $retros    = $preg['retros'];

    $enunciado = qtiPrepararTexto($enunciado, $cursivas, $negritas, $stats);

    $opcionIds = [];
    foreach ($opciones as $i => $opc) {
        $opcionIds[] = qtiId('a');
    }
    $correctaId = $opcionIds[0] ?? '';

    $xml  = '  <item ident="' . $itemId . '" title="' . qtiEscape($titulo) . '">' . "\n";
    $xml .= '    <itemmetadata><qtimetadata>' . "\n";
    $xml .= '      <qtimetadatafield><fieldlabel>question_type</fieldlabel><fieldentry>multiple_choice_question</fieldentry></qtimetadatafield>' . "\n";
    $xml .= '    </qtimetadata></itemmetadata>' . "\n";

    $xml .= '    <presentation>' . "\n";
    $xml .= '      <material><mattext texttype="text/html"><![CDATA[' . $enunciado . ']]></mattext></material>' . "\n";
    $xml .= '      <response_lid ident="response1" rcardinality="Single">' . "\n";
    $xml .= '        <render_choice>' . "\n";

    foreach ($opciones as $i => $opc) {
        $opcTexto = qtiPrepararTexto($opc, $cursivas, $negritas, $stats);
        $xml .= '          <response_label ident="' . $opcionIds[$i] . '"><material><mattext texttype="text/html"><![CDATA[' . $opcTexto . ']]></mattext></material></response_label>' . "\n";
    }

    $xml .= '        </render_choice>' . "\n";
    $xml .= '      </response_lid>' . "\n";
    $xml .= '    </presentation>' . "\n";

    $xml .= '    <resprocessing>' . "\n";
    $xml .= '      <outcomes><decvar maxvalue="100" minvalue="0" varname="SCORE" vartype="Decimal"/></outcomes>' . "\n";

    foreach ($opciones as $i => $opc) {
        $xml .= '      <respcondition continue="No">' . "\n";
        $xml .= '        <conditionvar><varequal respident="response1">' . $opcionIds[$i] . '</varequal></conditionvar>' . "\n";
        if ($i === 0) {
            $xml .= '        <setvar action="Set" varname="SCORE">100</setvar>' . "\n";
        }
        if (!empty($retros[$i])) {
            $xml .= '        <displayfeedback feedbacktype="Response" linkrefid="' . $opcionIds[$i] . '_fb"/>' . "\n";
        }
        $xml .= '      </respcondition>' . "\n";
    }

    $xml .= '    </resprocessing>' . "\n";

    // Feedback individual
    foreach ($retros as $i => $retro) {
        if (!empty($retro)) {
            $retroTexto = qtiPrepararTexto($retro, $cursivas, $negritas, $stats);
            $xml .= '    <itemfeedback ident="' . $opcionIds[$i] . '_fb"><flow_mat><material><mattext texttype="text/html"><![CDATA[' . $retroTexto . ']]></mattext></material></flow_mat></itemfeedback>' . "\n";
        }
    }

    // Retro general
    if (!empty($preg['retro_general'])) {
        $retroGenTexto = qtiPrepararTexto($preg['retro_general'], $cursivas, $negritas, $stats);
        $xml .= '    <itemfeedback ident="general_fb"><flow_mat><material><mattext texttype="text/html"><![CDATA[' . $retroGenTexto . ']]></mattext></material></flow_mat></itemfeedback>' . "\n";
    }

    $xml .= '  </item>' . "\n";
    return $xml;
}

// ── Generador FV (Falso/Verdadero) ──────────────────────────────────────────

function qtiGenerarFV(array $preg, array $cursivas, array $negritas, array &$stats): string
{
    $itemId    = qtiId('q');
    $titulo    = 'FV Pregunta ' . $preg['numero'];
    $enunciado = $preg['enunciado'];
    $respuesta = $preg['respuesta']; // 'TRUE' o 'FALSE'
    $retroV    = $preg['retro_verdadero'];
    $retroF    = $preg['retro_falso'];

    $enunciado = qtiPrepararTexto($enunciado, $cursivas, $negritas, $stats);

    $trueId  = qtiId('a');
    $falseId = qtiId('a');
    $correctaId = ($respuesta === 'TRUE') ? $trueId : $falseId;

    $xml  = '  <item ident="' . $itemId . '" title="' . qtiEscape($titulo) . '">' . "\n";
    $xml .= '    <itemmetadata><qtimetadata>' . "\n";
    $xml .= '      <qtimetadatafield><fieldlabel>question_type</fieldlabel><fieldentry>true_false_question</fieldentry></qtimetadatafield>' . "\n";
    $xml .= '    </qtimetadata></itemmetadata>' . "\n";

    $xml .= '    <presentation>' . "\n";
    $xml .= '      <material><mattext texttype="text/html"><![CDATA[' . $enunciado . ']]></mattext></material>' . "\n";
    $xml .= '      <response_lid ident="response1" rcardinality="Single">' . "\n";
    $xml .= '        <render_choice>' . "\n";
    $xml .= '          <response_label ident="' . $trueId . '"><material><mattext>Verdadero</mattext></material></response_label>' . "\n";
    $xml .= '          <response_label ident="' . $falseId . '"><material><mattext>Falso</mattext></material></response_label>' . "\n";
    $xml .= '        </render_choice>' . "\n";
    $xml .= '      </response_lid>' . "\n";
    $xml .= '    </presentation>' . "\n";

    $xml .= '    <resprocessing>' . "\n";
    $xml .= '      <outcomes><decvar maxvalue="100" minvalue="0" varname="SCORE" vartype="Decimal"/></outcomes>' . "\n";

    // Verdadero
    $xml .= '      <respcondition continue="No">' . "\n";
    $xml .= '        <conditionvar><varequal respident="response1">' . $trueId . '</varequal></conditionvar>' . "\n";
    if ($respuesta === 'TRUE') {
        $xml .= '        <setvar action="Set" varname="SCORE">100</setvar>' . "\n";
    }
    if (!empty($retroV)) {
        $xml .= '        <displayfeedback feedbacktype="Response" linkrefid="' . $trueId . '_fb"/>' . "\n";
    }
    $xml .= '      </respcondition>' . "\n";

    // Falso
    $xml .= '      <respcondition continue="No">' . "\n";
    $xml .= '        <conditionvar><varequal respident="response1">' . $falseId . '</varequal></conditionvar>' . "\n";
    if ($respuesta === 'FALSE') {
        $xml .= '        <setvar action="Set" varname="SCORE">100</setvar>' . "\n";
    }
    if (!empty($retroF)) {
        $xml .= '        <displayfeedback feedbacktype="Response" linkrefid="' . $falseId . '_fb"/>' . "\n";
    }
    $xml .= '      </respcondition>' . "\n";

    $xml .= '    </resprocessing>' . "\n";

    // Feedback
    if (!empty($retroV)) {
        $retroVTexto = qtiPrepararTexto($retroV, $cursivas, $negritas, $stats);
        $xml .= '    <itemfeedback ident="' . $trueId . '_fb"><flow_mat><material><mattext texttype="text/html"><![CDATA[' . $retroVTexto . ']]></mattext></material></flow_mat></itemfeedback>' . "\n";
    }
    if (!empty($retroF)) {
        $retroFTexto = qtiPrepararTexto($retroF, $cursivas, $negritas, $stats);
        $xml .= '    <itemfeedback ident="' . $falseId . '_fb"><flow_mat><material><mattext texttype="text/html"><![CDATA[' . $retroFTexto . ']]></mattext></material></flow_mat></itemfeedback>' . "\n";
    }

    $xml .= '  </item>' . "\n";
    return $xml;
}

// ── Generador EM (Emparejamiento) ───────────────────────────────────────────

function qtiGenerarEM(array $preg, array $cursivas, array $negritas, array &$stats): string
{
    $itemId    = qtiId('q');
    $titulo    = 'EM Pregunta ' . $preg['numero'];
    $enunciado = $preg['enunciado'];
    $pares     = $preg['pares'];

    $enunciado = qtiPrepararTexto($enunciado, $cursivas, $negritas, $stats);

    // Generar IDs para cada par y cada respuesta posible
    $respIds = [];
    $answerIds = [];
    foreach ($pares as $i => $par) {
        $respIds[$i]   = qtiId('r');
        $answerIds[$i] = qtiId('a');
    }

    $xml  = '  <item ident="' . $itemId . '" title="' . qtiEscape($titulo) . '">' . "\n";
    $xml .= '    <itemmetadata><qtimetadata>' . "\n";
    $xml .= '      <qtimetadatafield><fieldlabel>question_type</fieldlabel><fieldentry>matching_question</fieldentry></qtimetadatafield>' . "\n";
    $xml .= '    </qtimetadata></itemmetadata>' . "\n";

    $xml .= '    <presentation>' . "\n";
    if (!empty($enunciado)) {
        $xml .= '      <material><mattext texttype="text/html"><![CDATA[' . $enunciado . ']]></mattext></material>' . "\n";
    }

    // Cada item izquierdo con sus opciones de respuesta (todos los derechos)
    foreach ($pares as $i => $par) {
        $izqTexto = qtiPrepararTexto($par['izq'], $cursivas, $negritas, $stats);
        $xml .= '      <response_lid ident="' . $respIds[$i] . '">' . "\n";
        $xml .= '        <material><mattext texttype="text/html"><![CDATA[' . $izqTexto . ']]></mattext></material>' . "\n";
        $xml .= '        <render_choice>' . "\n";
        foreach ($pares as $j => $parJ) {
            $derTexto = qtiPrepararTexto($parJ['der'], $cursivas, $negritas, $stats);
            $xml .= '          <response_label ident="' . $answerIds[$j] . '"><material><mattext texttype="text/html"><![CDATA[' . $derTexto . ']]></mattext></material></response_label>' . "\n";
        }
        $xml .= '        </render_choice>' . "\n";
        $xml .= '      </response_lid>' . "\n";
    }

    $xml .= '    </presentation>' . "\n";

    // Procesamiento: un respcondition por cada par correcto
    $xml .= '    <resprocessing>' . "\n";
    $xml .= '      <outcomes><decvar maxvalue="100" minvalue="0" varname="SCORE" vartype="Decimal"/></outcomes>' . "\n";

    // Condicion: todas las respuestas correctas
    $xml .= '      <respcondition continue="No">' . "\n";
    $xml .= '        <conditionvar><and>' . "\n";
    foreach ($pares as $i => $par) {
        $xml .= '          <varequal respident="' . $respIds[$i] . '">' . $answerIds[$i] . '</varequal>' . "\n";
    }
    $xml .= '        </and></conditionvar>' . "\n";
    $xml .= '        <setvar action="Set" varname="SCORE">100</setvar>' . "\n";
    $xml .= '      </respcondition>' . "\n";

    $xml .= '    </resprocessing>' . "\n";
    $xml .= '  </item>' . "\n";
    return $xml;
}

// ── Generador principal ─────────────────────────────────────────────────────

/**
 * Genera QTI para un array mixto de preguntas (FV, OM, EM) desde Word
 */
function generarQtiTodos(array $preguntas, array $cursivas, array $negritas): array
{
    $stats = ['images' => 0, 'italics' => 0, 'bolds' => 0];
    $items = '';

    foreach ($preguntas as $preg) {
        switch ($preg['tipo']) {
            case 'FV':
                $items .= qtiGenerarFV($preg, $cursivas, $negritas, $stats);
                break;
            case 'OM':
                $items .= qtiGenerarOM($preg, $cursivas, $negritas, $stats);
                break;
            case 'EM':
                $items .= qtiGenerarEM($preg, $cursivas, $negritas, $stats);
                break;
        }
    }

    return ['items' => $items, 'stats' => $stats];
}

/**
 * Genera QTI para preguntas OM desde Excel (con retro individual)
 */
function generarQtiExcel(array $preguntas, array $cursivas, array $negritas): array
{
    $stats = ['images' => 0, 'italics' => 0, 'bolds' => 0];
    $items = '';

    foreach ($preguntas as $preg) {
        $items .= qtiGenerarOMExcel($preg, $cursivas, $negritas, $stats);
    }

    return ['items' => $items, 'stats' => $stats];
}

/**
 * Envuelve los items en XML QTI 1.2
 *
 * @param string $mode  'bank' = solo banco de preguntas (objectbank),
 *                      'quiz' = evaluacion + banco (assessment)
 *
 * Canvas interpreta <objectbank> como "solo preguntas para un banco"
 * y <assessment> como "crear evaluacion + banco vinculado".
 */
function qtiEnvolverAssessment(string $items, string $titulo = 'S4Learning Questions', string $mode = 'bank'): string
{
    $ident = qtiId($mode === 'bank' ? 'bank' : 'assess');

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<questestinterop xmlns="http://www.imsglobal.org/xsd/ims_qtiasiv1p2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.imsglobal.org/xsd/ims_qtiasiv1p2 http://www.imsglobal.org/xsd/ims_qtiasiv1p2p1.xsd">' . "\n";

    if ($mode === 'bank') {
        // objectbank: items directos, sin section. Canvas crea solo el banco.
        // Canvas usa el ident (no el title) como nombre del banco
        $bankIdent = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $titulo) ?: $ident;
        $xml .= '<objectbank ident="' . qtiEscape($bankIdent) . '" title="' . qtiEscape($titulo) . '">' . "\n";
        $xml .= $items;
        $xml .= '</objectbank>' . "\n";
    } else {
        // assessment: con section. Canvas crea evaluacion + banco.
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

/**
 * Genera el imsmanifest.xml para el paquete QTI
 *
 * @param string $mode  'bank' usa tipo question-bank, 'quiz' usa tipo assessment
 */
function qtiGenerarManifest(string $assessmentFile, string $mode = 'bank'): string
{
    $manifestId = qtiId('man');
    $resourceId = qtiId('res');
    $resourceType = $mode === 'bank'
        ? 'imsqti_xmlv1p2/imscc_xmlv1p1/question-bank'
        : 'imsqti_xmlv1p2/imscc_xmlv1p1/assessment';

    $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<manifest identifier="' . $manifestId . '" xmlns="http://www.imsglobal.org/xsd/imsccv1p1/imscp_v1p1" xmlns:lom="http://ltsc.ieee.org/xsd/imsccv1p1/LOM/resource" xmlns:imsmd="http://www.imsglobal.org/xsd/imsmd_v1p2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.imsglobal.org/xsd/imsccv1p1/imscp_v1p1 http://www.imsglobal.org/xsd/imscp_v1p1.xsd http://www.imsglobal.org/xsd/imsmd_v1p2 http://www.imsglobal.org/xsd/imsmd_v1p2p2.xsd">' . "\n";
    $xml .= '  <metadata><schema>IMS Content</schema><schemaversion>1.1.3</schemaversion></metadata>' . "\n";
    $xml .= '  <organizations/>' . "\n";
    $xml .= '  <resources>' . "\n";
    $xml .= '    <resource identifier="' . $resourceId . '" type="' . $resourceType . '">' . "\n";
    $xml .= '      <file href="' . qtiEscape($assessmentFile) . '"/>' . "\n";
    $xml .= '    </resource>' . "\n";
    $xml .= '  </resources>' . "\n";
    $xml .= '</manifest>' . "\n";

    return $xml;
}

/**
 * Crea un ZIP con el paquete QTI completo y retorna su contenido base64
 */
function qtiCrearZip(string $assessmentXml, string $mode = 'bank'): ?string
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'qti_');

    $zip = new ZipArchive();
    if ($zip->open($tmpFile, ZipArchive::OVERWRITE) !== true) {
        return null;
    }

    $assessmentFile = $mode === 'bank' ? 'objectbank.xml' : 'assessment.xml';
    $zip->addFromString($assessmentFile, $assessmentXml);
    $zip->addFromString('imsmanifest.xml', qtiGenerarManifest($assessmentFile, $mode));
    $zip->close();

    $content = file_get_contents($tmpFile);
    @unlink($tmpFile);

    return base64_encode($content);
}

// ═════════════════════════════════════════════════════════════════════════════
// PROCESAMIENTO PRINCIPAL
// ═════════════════════════════════════════════════════════════════════════════

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

$cursivas  = array_filter(array_map('trim', explode(',', $_POST['italic_words'] ?? '')));
$negritas  = array_filter(array_map('trim', explode(',', $_POST['bold_words'] ?? '')));
$bankName  = trim($_POST['qti_bank_name'] ?? '') ?: 'S4Learning Questions';
$qtiMode   = ($_POST['qti_mode'] ?? 'bank') === 'quiz' ? 'quiz' : 'bank';

// ── Procesar Word (.docx) ───────────────────────────────────────────────────

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

    $qtiResult = generarQtiTodos($preguntas, $cursivas, $negritas);
    $assessmentXml = qtiEnvolverAssessment($qtiResult['items'], $bankName, $qtiMode);
    $zipBase64 = qtiCrearZip($assessmentXml, $qtiMode);

    echo json_encode([
        'success'   => true,
        'gift'      => $assessmentXml,
        'zip'       => $zipBase64,
        'preguntas' => formatearPreguntasPreview($preguntas, $cursivas, $negritas),
        'warnings'  => $warnings,
        'stats'     => [
            'total'              => count($preguntas),
            'omitted'            => $omitidas,
            'no_retro_correct'   => $sinRetroCorr,
            'no_retro_incorrect' => $sinRetroIncorr,
            'images'             => $qtiResult['stats']['images'],
            'italics'            => $qtiResult['stats']['italics'],
            'bolds'              => $qtiResult['stats']['bolds'],
        ],
    ]);
    exit;
}

// ── Procesar Excel (.xlsx) ──────────────────────────────────────────────────

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

    $qtiResult = generarQtiExcel($preguntas, $cursivas, $negritas);
    $assessmentXml = qtiEnvolverAssessment($qtiResult['items'], $bankName, $qtiMode);
    $zipBase64 = qtiCrearZip($assessmentXml, $qtiMode);

    echo json_encode([
        'success'   => true,
        'gift'      => $assessmentXml,
        'zip'       => $zipBase64,
        'preguntas' => formatearPreguntasPreview($preguntas, $cursivas, $negritas),
        'warnings'  => $warnings,
        'stats'     => [
            'total'    => count($preguntas),
            'omitted'  => $omitidas,
            'no_retro' => $sinRetro,
            'images'   => $qtiResult['stats']['images'],
            'italics'  => $qtiResult['stats']['italics'],
            'bolds'    => $qtiResult['stats']['bolds'],
        ],
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Formato de archivo no soportado']);
