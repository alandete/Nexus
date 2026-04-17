<?php
/**
 * S4Learning - Question Parser
 * Funciones compartidas para extraer y parsear preguntas de archivos .docx y .xlsx
 * Usado por gift_actions.php y qti_actions.php
 */

defined('APP_ACCESS') or die('Acceso directo no permitido');

// ── Extraccion de texto ─────────────────────────────────────────────────────

/**
 * Extrae texto plano de un archivo .docx
 * Un .docx es un ZIP que contiene word/document.xml
 */
function extraerTextoDocx(string $filePath): string
{
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return '';
    }

    $xml = $zip->getFromName('word/document.xml');
    if ($xml === false) {
        $zip->close();
        return '';
    }

    // Cargar relaciones para resolver hyperlinks
    $rels = [];
    $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
    if ($relsXml !== false) {
        $relsDom = new DOMDocument();
        $relsDom->loadXML($relsXml);
        $relNodes = $relsDom->getElementsByTagName('Relationship');
        foreach ($relNodes as $rel) {
            $rels[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
        }
    }

    $zip->close();

    $dom = new DOMDocument();
    $dom->loadXML($xml);

    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    $paragraphs = $dom->getElementsByTagNameNS($ns, 'p');

    $lines = [];
    foreach ($paragraphs as $p) {
        $lineTexts = [];

        foreach ($p->childNodes as $child) {
            // Texto normal (w:r)
            if ($child->localName === 'r') {
                $tNodes = $child->getElementsByTagNameNS($ns, 't');
                foreach ($tNodes as $t) {
                    $lineTexts[] = $t->textContent;
                }
            }
            // Hyperlinks (w:hyperlink)
            if ($child->localName === 'hyperlink') {
                $rId = $child->getAttributeNS($nsR, 'id');
                $url = $rels[$rId] ?? '';
                // Extraer texto del hyperlink
                $hRuns = $child->getElementsByTagNameNS($ns, 't');
                $hText = '';
                foreach ($hRuns as $ht) {
                    $hText .= $ht->textContent;
                }
                // Si el texto visible es la misma URL o esta vacio, usar solo la URL
                if ($url && (empty($hText) || $hText === $url)) {
                    $lineTexts[] = $url;
                } elseif ($url) {
                    $lineTexts[] = $hText . ' ' . $url;
                } else {
                    $lineTexts[] = $hText;
                }
            }
        }

        $lines[] = implode('', $lineTexts);
    }

    return implode("\n", $lines);
}

// ── Extraccion Excel ────────────────────────────────────────────────────────

/**
 * Convierte letra de columna Excel a indice numerico (A=0, B=1, ..., K=10)
 */
function colLetraAIndice(string $col): int
{
    $col = strtoupper($col);
    $idx = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $idx = $idx * 26 + (ord($col[$i]) - ord('A'));
    }
    return $idx;
}

/**
 * Extrae datos de un archivo .xlsx
 * Un .xlsx es un ZIP que contiene xl/sharedStrings.xml y xl/worksheets/sheet1.xml
 * Retorna array de filas, cada fila es un array indexado por columna (0=A, 1=B, ...)
 */
function extraerDatosXlsx(string $filePath): array
{
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return [];
    }

    // Leer shared strings (textos unicos compartidos entre celdas)
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $dom = new DOMDocument();
        $dom->loadXML($ssXml);
        $siNodes = $dom->getElementsByTagName('si');
        foreach ($siNodes as $si) {
            $text = '';
            $tNodes = $si->getElementsByTagName('t');
            foreach ($tNodes as $t) {
                $text .= $t->textContent;
            }
            $sharedStrings[] = $text;
        }
    }

    // Leer worksheet (sheet1)
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $zip->close();
        return [];
    }

    $zip->close();

    $dom = new DOMDocument();
    $dom->loadXML($sheetXml);

    $rows = [];
    $rowNodes = $dom->getElementsByTagName('row');

    foreach ($rowNodes as $rowNode) {
        $rowData = [];
        $cells = $rowNode->getElementsByTagName('c');

        foreach ($cells as $cell) {
            $ref = $cell->getAttribute('r');
            $col = preg_replace('/[0-9]/', '', $ref);
            $colIdx = colLetraAIndice($col);

            $type = $cell->getAttribute('t');
            $vNodes = $cell->getElementsByTagName('v');
            $value = '';

            if ($vNodes->length > 0) {
                $v = $vNodes->item(0)->textContent;
                if ($type === 's') {
                    $value = $sharedStrings[(int) $v] ?? '';
                } else {
                    $value = $v;
                }
            }

            // Inline string
            if ($type === 'inlineStr') {
                $isNodes = $cell->getElementsByTagName('is');
                if ($isNodes->length > 0) {
                    $tNodes = $isNodes->item(0)->getElementsByTagName('t');
                    $value = '';
                    foreach ($tNodes as $t) {
                        $value .= $t->textContent;
                    }
                }
            }

            $rowData[$colIdx] = $value;
        }

        $rows[] = $rowData;
    }

    return $rows;
}

// ── Parsers de preguntas ────────────────────────────────────────────────────

/**
 * Parsea preguntas OM desde filas extraidas de .xlsx
 * Columnas: A=nombre, B=enunciado, C=opcion1(correcta), D=retro1,
 *           E=opcion2, F=retro2, G=opcion3, H=retro3, I=opcion4, J=retro4, K=retro general
 */
function parsearPreguntasExcel(array $filas): array
{
    $preguntas = [];
    $warnings = [];

    $autoNum = 0;
    for ($r = 1; $r < count($filas); $r++) {
        $fila = $filas[$r];

        $enunciado = trim($fila[1] ?? '');
        if (empty($enunciado)) continue;

        $autoNum++;
        $numStr = str_pad($autoNum, 2, '0', STR_PAD_LEFT);

        $opciones = [];
        $retros = [];

        // C(2)=opcion1, D(3)=retro1, E(4)=opcion2, F(5)=retro2,
        // G(6)=opcion3, H(7)=retro3, I(8)=opcion4, J(9)=retro4
        for ($i = 0; $i < 4; $i++) {
            $opcCol = 2 + ($i * 2);
            $retroCol = 3 + ($i * 2);

            $opc = trim($fila[$opcCol] ?? '');
            $retro = trim($fila[$retroCol] ?? '');

            if (!empty($opc)) {
                $opciones[] = $opc;
                $retros[] = $retro;
            }
        }

        $retroGeneral = trim($fila[10] ?? '');

        // Warnings
        $pregWarnings = [];
        if (count($opciones) !== 4) {
            $pregWarnings[] = "Pregunta {$numStr}: se encontraron " . count($opciones) . " opciones en lugar de 4";
        }

        $sinRetro = 0;
        foreach ($retros as $retro) {
            if (empty($retro)) $sinRetro++;
        }
        if ($sinRetro > 0) {
            $pregWarnings[] = "Pregunta {$numStr}: {$sinRetro} opciones sin retroalimentacion";
        }

        $preguntas[] = [
            'numero'        => $numStr,
            'enunciado'     => $enunciado,
            'opciones'      => $opciones,
            'retros'        => $retros,
            'retro_general' => $retroGeneral,
            'warnings'      => $pregWarnings,
        ];

        $warnings = array_merge($warnings, $pregWarnings);
    }

    return ['preguntas' => $preguntas, 'warnings' => $warnings];
}

/**
 * Parsea un bloque FV (Falso/Verdadero) extraido de un .docx
 */
function parsearBloqueFV(string $bloque, int $autoNum): array
{
    preg_match('/^FV\s+(?:Reactivo|Pregunta)\s*(\d*)/i', $bloque, $matchMarker);
    $numero = !empty($matchMarker[1]) ? (int) $matchMarker[1] : $autoNum;
    $numStr = str_pad($numero, 2, '0', STR_PAD_LEFT);

    $contenido = preg_replace('/^FV\s+(?:Reactivo|Pregunta)\s*\d*/i', '', $bloque, 1);
    $lineas    = preg_split('/\r?\n/', $contenido);

    $enunciadoLineas    = [];
    $enunciadoFinalizado = false;
    $respuesta          = '';
    $retroVerdadero     = '';
    $retroFalso         = '';

    foreach ($lineas as $linea) {
        $lineaTrim = trim($linea);
        if ($lineaTrim === '') continue;

        if (preg_match('/^Respuesta\s+correcta\s*:\s*(Verdadero|Falso)/iu', $lineaTrim, $m)) {
            $enunciadoFinalizado = true;
            $respuesta = mb_strtolower(trim($m[1]), 'UTF-8') === 'verdadero' ? 'TRUE' : 'FALSE';
        } elseif (preg_match('/^Retro\s+verdadero\s*:\s*(.+)/iu', $lineaTrim, $m)) {
            $enunciadoFinalizado = true;
            $retroVerdadero = trim($m[1]);
        } elseif (preg_match('/^Retro\s+falso\s*:\s*(.+)/iu', $lineaTrim, $m)) {
            $enunciadoFinalizado = true;
            $retroFalso = trim($m[1]);
        } elseif (!$enunciadoFinalizado) {
            $enunciadoLineas[] = $lineaTrim;
        }
    }

    $enunciado = implode("\n", $enunciadoLineas);

    $warnings = [];
    if (empty($enunciado))     $warnings[] = "FV Pregunta {$numStr}: sin enunciado";
    if (empty($respuesta))     $warnings[] = "FV Pregunta {$numStr}: sin respuesta correcta";
    if (empty($retroVerdadero)) $warnings[] = "FV Pregunta {$numStr}: sin retro Verdadero";
    if (empty($retroFalso))    $warnings[] = "FV Pregunta {$numStr}: sin retro Falso";

    return [
        'tipo'            => 'FV',
        'numero'          => $numStr,
        'enunciado'       => $enunciado,
        'respuesta'       => $respuesta,
        'retro_verdadero' => $retroVerdadero,
        'retro_falso'     => $retroFalso,
        'warnings'        => $warnings,
    ];
}

/**
 * Parsea un bloque OM (Opcion Multiple) extraido de un .docx
 */
function parsearBloqueOM(string $bloque, int $autoNum): array
{
    preg_match('/^OM\s+(?:Reactivo|Pregunta)\s*(\d*)/i', $bloque, $matchMarker);
    $numero = !empty($matchMarker[1]) ? (int) $matchMarker[1] : $autoNum;
    $numStr = str_pad($numero, 2, '0', STR_PAD_LEFT);

    $contenido = preg_replace('/^OM\s+(?:Reactivo|Pregunta)\s*\d*/i', '', $bloque, 1);
    $lineas    = preg_split('/\r?\n/', $contenido);

    $posRetroCorr   = null;
    $posRetroIncorr = null;
    $textoRetroCorr   = '';
    $textoRetroIncorr = '';

    foreach ($lineas as $i => $linea) {
        $lineaTrim = trim($linea);
        if (preg_match('/^Retro(?:alimentaci[oó]n)?\s+correcta\s*:\s*(.*)/iu', $lineaTrim, $m)) {
            $posRetroCorr   = $i;
            $textoRetroCorr = trim($m[1]);
        }
        if (preg_match('/^Retro(?:alimentaci[oó]n)?\s+incorrecta\s*:\s*(.*)/iu', $lineaTrim, $m)) {
            $posRetroIncorr   = $i;
            $textoRetroIncorr = trim($m[1]);
        }
    }

    // Retro en linea siguiente si vacia
    if ($posRetroCorr !== null && $textoRetroCorr === '') {
        for ($j = $posRetroCorr + 1; $j < count($lineas); $j++) {
            $siguiente = trim($lineas[$j]);
            if ($siguiente !== '') {
                if (!preg_match('/^(Retro|OM\s+|FV\s+|EM\s+)/i', $siguiente)) {
                    $textoRetroCorr = $siguiente;
                }
                break;
            }
        }
    }

    if ($posRetroIncorr !== null && $textoRetroIncorr === '') {
        for ($j = $posRetroIncorr + 1; $j < count($lineas); $j++) {
            $siguiente = trim($lineas[$j]);
            if ($siguiente !== '') {
                if (!preg_match('/^(Retro|OM\s+|FV\s+|EM\s+)/i', $siguiente)) {
                    $textoRetroIncorr = $siguiente;
                }
                break;
            }
        }
    }

    $finContenido   = $posRetroCorr ?? $posRetroIncorr ?? count($lineas);
    $lineasContenido = array_slice($lineas, 0, $finContenido);

    // Detectar opciones con prefijo de letra
    $opciones     = [];
    $primeraOpcion = null;

    foreach ($lineasContenido as $i => $linea) {
        $lineaTrim = trim($linea);
        if (preg_match('/^[a-dA-D][.)]\s+(.+)/', $lineaTrim, $mOpc)) {
            if ($primeraOpcion === null) $primeraOpcion = $i;
            $opciones[] = trim($mOpc[1]);
        }
    }

    // Sin prefijo: tomar las 4 lineas no-vacias previas a retro
    if (count($opciones) === 0) {
        $lineasNoVacias = [];
        foreach ($lineasContenido as $i => $linea) {
            $lineaTrim = trim($linea);
            if ($lineaTrim !== '') {
                $lineasNoVacias[] = ['idx' => $i, 'texto' => $lineaTrim];
            }
        }
        if (count($lineasNoVacias) >= 4) {
            $opcionesRaw   = array_slice($lineasNoVacias, -4);
            $primeraOpcion = $opcionesRaw[0]['idx'];
            foreach ($opcionesRaw as $opc) {
                $opciones[] = $opc['texto'];
            }
        }
    }

    $enunciado = '';
    if ($primeraOpcion !== null) {
        $lineasEnunciado = [];
        for ($i = 0; $i < $primeraOpcion; $i++) {
            $lineaTrim = trim($lineas[$i]);
            if ($lineaTrim !== '') {
                $lineasEnunciado[] = $lineaTrim;
            }
        }
        $enunciado = implode("\n", $lineasEnunciado);
    }

    $warnings = [];
    if (count($opciones) !== 4) {
        $warnings[] = "OM Pregunta {$numStr}: se encontraron " . count($opciones) . " opciones en lugar de 4";
    }
    if (empty($textoRetroCorr)) {
        $warnings[] = "OM Pregunta {$numStr}: sin retroalimentacion correcta";
    }
    if (empty($textoRetroIncorr)) {
        $warnings[] = "OM Pregunta {$numStr}: sin retroalimentacion incorrecta";
    }

    return [
        'tipo'             => 'OM',
        'numero'           => $numStr,
        'enunciado'        => $enunciado,
        'opciones'         => $opciones,
        'retro_correcta'   => $textoRetroCorr,
        'retro_incorrecta' => $textoRetroIncorr,
        'warnings'         => $warnings,
    ];
}

/**
 * Parsea un bloque EM (Emparejamiento) extraido de un .docx
 */
function parsearBloqueEM(string $bloque, int $autoNum): array
{
    preg_match('/^EM\s+(?:Reactivo|Pregunta)\s*(\d*)/i', $bloque, $matchMarker);
    $numero = !empty($matchMarker[1]) ? (int) $matchMarker[1] : $autoNum;
    $numStr = str_pad($numero, 2, '0', STR_PAD_LEFT);

    $contenido = preg_replace('/^EM\s+(?:Reactivo|Pregunta)\s*\d*/i', '', $bloque, 1);
    $lineas    = preg_split('/\r?\n/', $contenido);

    $pares           = [];
    $enunciadoLineas = [];
    $paresIniciados  = false;

    foreach ($lineas as $linea) {
        $lineaTrim = trim($linea);
        if ($lineaTrim === '') continue;

        if (preg_match('/^(.+?)\s*->\s*(.+)$/', $lineaTrim, $m)) {
            $paresIniciados = true;
            $pares[] = ['izq' => trim($m[1]), 'der' => trim($m[2])];
        } elseif (!$paresIniciados) {
            $enunciadoLineas[] = $lineaTrim;
        }
    }

    $enunciado = implode("\n", $enunciadoLineas);

    $warnings = [];
    if (empty($pares)) {
        $warnings[] = "EM Pregunta {$numStr}: no se encontraron pares de emparejamiento";
    }

    return [
        'tipo'      => 'EM',
        'numero'    => $numStr,
        'enunciado' => $enunciado,
        'pares'     => $pares,
        'warnings'  => $warnings,
    ];
}

/**
 * Parsea todas las preguntas de un .docx con tipos mixtos (FV, OM, EM)
 * Preserva el orden del documento
 */
function parsearDocxMixto(string $texto): array
{
    $preguntas = [];
    $warnings  = [];

    $bloques = preg_split('/^(?=(FV|OM|EM)\s+(?:Reactivo|Pregunta))/mi', $texto);

    $autoNum = 0;
    foreach ($bloques as $bloque) {
        $bloque = trim($bloque);
        if (empty($bloque)) continue;

        if (!preg_match('/^(FV|OM|EM)\s+(?:Reactivo|Pregunta)/i', $bloque, $m)) continue;

        $tipo = strtoupper($m[1]);
        $autoNum++;

        switch ($tipo) {
            case 'FV':
                $preg = parsearBloqueFV($bloque, $autoNum);
                break;
            case 'OM':
                $preg = parsearBloqueOM($bloque, $autoNum);
                break;
            case 'EM':
                $preg = parsearBloqueEM($bloque, $autoNum);
                break;
            default:
                continue 2;
        }

        $preguntas[] = $preg;
        $warnings    = array_merge($warnings, $preg['warnings']);
    }

    return ['preguntas' => $preguntas, 'warnings' => $warnings];
}

// ── Utilidades de formato ───────────────────────────────────────────────────

/**
 * Detecta URLs de imagen en texto y las convierte a tags <img>
 */
function procesarImagenes(string $texto): array
{
    $count = 0;
    $pattern = '/https?:\/\/[^\s]+\.(?:jpg|jpeg|png|gif|webp|svg)(?:\?[^\s]*)?/i';

    $resultado = preg_replace_callback($pattern, function ($match) use (&$count) {
        $count++;
        $url = htmlspecialchars($match[0], ENT_QUOTES, 'UTF-8');
        return '<img src="' . $url . '" class="img-fluid d-block mx-auto my-2" alt="">';
    }, $texto);

    return ['texto' => $resultado, 'tieneImagen' => $count > 0, 'count' => $count];
}

/**
 * Aplica formato cursiva y negrita a un texto
 */
function aplicarFormato(string $texto, array $cursivas, array $negritas): array
{
    $tieneFormato = false;
    $italicCount = 0;
    $boldCount = 0;

    // Eliminar duplicados
    $negritas = array_unique(array_filter(array_map('trim', $negritas)));
    $cursivas = array_unique(array_filter(array_map('trim', $cursivas)));

    foreach ($negritas as $palabra) {
        $escaped = preg_quote($palabra, '/');
        $count = 0;
        // Solo aplicar si no está ya dentro de <strong>
        $texto = preg_replace('/(?<!<strong>)(' . $escaped . ')(?!<\/strong>)/iu', '<strong>$1</strong>', $texto, -1, $count);
        if ($count > 0) {
            $tieneFormato = true;
            $boldCount += $count;
        }
    }

    foreach ($cursivas as $palabra) {
        $escaped = preg_quote($palabra, '/');
        $count = 0;
        // Solo aplicar si no está ya dentro de <em>
        $texto = preg_replace('/(?<!<em>)(' . $escaped . ')(?!<\/em>)/iu', '<em>$1</em>', $texto, -1, $count);
        if ($count > 0) {
            $tieneFormato = true;
            $italicCount += $count;
        }
    }

    return ['texto' => $texto, 'tieneFormato' => $tieneFormato, 'italicCount' => $italicCount, 'boldCount' => $boldCount];
}

/**
 * Aplica formato cursiva/negrita a los campos de texto de las preguntas para previsualización.
 */
function formatearPreguntasPreview(array $preguntas, array $cursivas, array $negritas): array
{
    if (empty($cursivas) && empty($negritas)) return $preguntas;

    foreach ($preguntas as &$preg) {
        // Enunciado
        if (!empty($preg['enunciado'])) {
            $preg['enunciado'] = aplicarFormato($preg['enunciado'], $cursivas, $negritas)['texto'];
        }

        // Opciones (OM Word y Excel)
        if (!empty($preg['opciones'])) {
            foreach ($preg['opciones'] as &$opc) {
                $opc = aplicarFormato($opc, $cursivas, $negritas)['texto'];
            }
            unset($opc);
        }

        // Retros individuales (Excel)
        if (!empty($preg['retros'])) {
            foreach ($preg['retros'] as &$retro) {
                if (!empty($retro)) $retro = aplicarFormato($retro, $cursivas, $negritas)['texto'];
            }
            unset($retro);
        }

        // Retros Word (OM)
        if (!empty($preg['retro_correcta'])) $preg['retro_correcta'] = aplicarFormato($preg['retro_correcta'], $cursivas, $negritas)['texto'];
        if (!empty($preg['retro_incorrecta'])) $preg['retro_incorrecta'] = aplicarFormato($preg['retro_incorrecta'], $cursivas, $negritas)['texto'];

        // Retros FV
        if (!empty($preg['retro_verdadero'])) $preg['retro_verdadero'] = aplicarFormato($preg['retro_verdadero'], $cursivas, $negritas)['texto'];
        if (!empty($preg['retro_falso'])) $preg['retro_falso'] = aplicarFormato($preg['retro_falso'], $cursivas, $negritas)['texto'];

        // Retro general (Excel)
        if (!empty($preg['retro_general'])) $preg['retro_general'] = aplicarFormato($preg['retro_general'], $cursivas, $negritas)['texto'];

        // Pares EM
        if (!empty($preg['pares'])) {
            foreach ($preg['pares'] as &$par) {
                $par['izq'] = aplicarFormato($par['izq'], $cursivas, $negritas)['texto'];
                $par['der'] = aplicarFormato($par['der'], $cursivas, $negritas)['texto'];
            }
            unset($par);
        }
    }
    unset($preg);

    return $preguntas;
}
