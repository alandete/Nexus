<?php
/**
 * Nexus 2.0 — Question Parser
 * Extrae y parsea preguntas de archivos .docx y .xlsx
 * Detecta negrita/cursiva automáticamente de los formatos fuente
 */

defined('APP_ACCESS') or die('Acceso directo no permitido');

// ── Extracción de texto ─────────────────────────────────────────────────────

/**
 * Extrae texto de un .docx preservando negrita/cursiva como <strong>/<em>
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

    $rels    = [];
    $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
    if ($relsXml !== false) {
        $relsDom = new DOMDocument();
        @$relsDom->loadXML($relsXml);
        foreach ($relsDom->getElementsByTagName('Relationship') as $rel) {
            $rels[$rel->getAttribute('Id')] = $rel->getAttribute('Target');
        }
    }

    $zip->close();

    $dom = new DOMDocument();
    @$dom->loadXML($xml);

    $ns  = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $nsR = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    $lines = [];
    foreach ($dom->getElementsByTagNameNS($ns, 'p') as $p) {
        $lineTexts = [];

        foreach ($p->childNodes as $child) {
            if ($child->localName === 'r') {
                [$isBold, $isItalic] = detectRunFormat($child, $ns);
                foreach ($child->getElementsByTagNameNS($ns, 't') as $t) {
                    $text = $t->textContent;
                    if ($isItalic) $text = '<em>' . $text . '</em>';
                    if ($isBold)   $text = '<strong>' . $text . '</strong>';
                    $lineTexts[] = $text;
                }
            }
            if ($child->localName === 'hyperlink') {
                $rId   = $child->getAttributeNS($nsR, 'id');
                $url   = $rels[$rId] ?? '';
                $hText = '';
                foreach ($child->getElementsByTagNameNS($ns, 't') as $ht) {
                    $hText .= $ht->textContent;
                }
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

/**
 * Devuelve true si el color hex (#RRGGBB sin #) es un tono rojo
 * Umbral: canal R >= 160, canales G y B <= 80
 */
function esColorRojo(string $hex): bool
{
    // Excel usa AARRGGBB (8 chars); Word usa RRGGBB (6 chars)
    if (strlen($hex) === 8) $hex = substr($hex, 2);
    if (strlen($hex) !== 6) return false;
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return $r >= 160 && $g <= 80 && $b <= 80;
}

/**
 * Devuelve [isBold, isItalic] analizando el <w:rPr> de un nodo <w:r>
 * Texto rojo también se trata como cursiva (convención de los DI)
 */
function detectRunFormat(DOMNode $run, string $ns): array
{
    $isBold   = false;
    $isItalic = false;

    foreach ($run->childNodes as $child) {
        if ($child->localName !== 'rPr') continue;
        foreach ($child->childNodes as $prop) {
            if ($prop->localName === 'b') {
                $val    = $prop->getAttributeNS($ns, 'val');
                $isBold = ($val === '' || ($val !== '0' && $val !== 'false'));
            }
            if ($prop->localName === 'i') {
                $val      = $prop->getAttributeNS($ns, 'val');
                $isItalic = ($val === '' || ($val !== '0' && $val !== 'false'));
            }
            if ($prop->localName === 'color' && !$isItalic) {
                $hex      = strtoupper($prop->getAttributeNS($ns, 'val'));
                $isItalic = esColorRojo($hex);
            }
        }
        break;
    }

    return [$isBold, $isItalic];
}

// ── Extracción Excel ────────────────────────────────────────────────────────

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
 * Extrae datos de un .xlsx preservando negrita/cursiva en shared strings con rich text
 */
function extraerDatosXlsx(string $filePath): array
{
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return [];
    }

    $sharedStrings = [];
    $ssXml         = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml !== false) {
        $dom = new DOMDocument();
        @$dom->loadXML($ssXml);
        foreach ($dom->getElementsByTagName('si') as $si) {
            $sharedStrings[] = extraerTextoSharedString($si);
        }
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if ($sheetXml === false) {
        $zip->close();
        return [];
    }
    $zip->close();

    $dom = new DOMDocument();
    @$dom->loadXML($sheetXml);

    $rows = [];
    foreach ($dom->getElementsByTagName('row') as $rowNode) {
        $rowData = [];
        foreach ($rowNode->getElementsByTagName('c') as $cell) {
            $ref    = $cell->getAttribute('r');
            $col    = preg_replace('/[0-9]/', '', $ref);
            $colIdx = colLetraAIndice($col);
            $type   = $cell->getAttribute('t');
            $value  = '';

            $vNodes = $cell->getElementsByTagName('v');
            if ($vNodes->length > 0) {
                $v     = $vNodes->item(0)->textContent;
                $value = ($type === 's') ? ($sharedStrings[(int) $v] ?? '') : $v;
            }

            if ($type === 'inlineStr') {
                $isNodes = $cell->getElementsByTagName('is');
                if ($isNodes->length > 0) {
                    $value = extraerTextoSharedString($isNodes->item(0));
                }
            }

            $rowData[$colIdx] = $value;
        }
        $rows[] = $rowData;
    }

    return $rows;
}

/**
 * Extrae texto de un nodo <si>, detectando rich text (<r><rPr><b/>) para negrita/cursiva
 */
function extraerTextoSharedString(DOMNode $si): string
{
    $rNodes = $si->getElementsByTagName('r');

    if ($rNodes->length === 0) {
        $text = '';
        foreach ($si->getElementsByTagName('t') as $t) {
            $text .= $t->textContent;
        }
        return $text;
    }

    $result = '';
    foreach ($rNodes as $r) {
        $isBold   = false;
        $isItalic = false;

        $rPrNodes = $r->getElementsByTagName('rPr');
        if ($rPrNodes->length > 0) {
            $rPr = $rPrNodes->item(0);
            foreach ($rPr->childNodes as $prop) {
                if ($prop->localName === 'b') {
                    $val    = $prop->getAttribute('val');
                    $isBold = ($val === '' || ($val !== '0' && $val !== 'false'));
                }
                if ($prop->localName === 'i') {
                    $val      = $prop->getAttribute('val');
                    $isItalic = ($val === '' || ($val !== '0' && $val !== 'false'));
                }
                if ($prop->localName === 'color' && !$isItalic) {
                    $hex      = strtoupper($prop->getAttribute('rgb') ?: $prop->getAttribute('val'));
                    $isItalic = esColorRojo($hex);
                }
            }
        }

        foreach ($r->getElementsByTagName('t') as $t) {
            $text = $t->textContent;
            if ($isItalic) $text = '<em>' . $text . '</em>';
            if ($isBold)   $text = '<strong>' . $text . '</strong>';
            $result .= $text;
        }
    }

    return $result;
}

// ── Parsers de preguntas ────────────────────────────────────────────────────

/**
 * Parsea preguntas OM desde filas de .xlsx
 * Col: A=nombre, B=enunciado, C=opcion1(correcta), D=retro1, E=opcion2, F=retro2, ...K=retro general
 */
function parsearPreguntasExcel(array $filas): array
{
    $preguntas = [];
    $warnings  = [];
    $autoNum   = 0;

    for ($r = 1; $r < count($filas); $r++) {
        $fila      = $filas[$r];
        $enunciado = trim($fila[1] ?? '');
        if (empty($enunciado)) continue;

        $autoNum++;
        $numStr   = str_pad($autoNum, 2, '0', STR_PAD_LEFT);
        $opciones = [];
        $retros   = [];

        for ($i = 0; $i < 4; $i++) {
            $opc   = trim($fila[2 + ($i * 2)] ?? '');
            $retro = trim($fila[3 + ($i * 2)] ?? '');
            if (!empty($opc)) {
                $opciones[] = $opc;
                $retros[]   = $retro;
            }
        }

        $retroGeneral = trim($fila[10] ?? '');
        $pregWarnings = [];

        if (count($opciones) !== 4) {
            $pregWarnings[] = "Pregunta {$numStr}: se encontraron " . count($opciones) . " opciones en lugar de 4";
        }
        $sinRetro = count(array_filter($retros, fn ($v) => $v === ''));
        if ($sinRetro > 0) {
            $pregWarnings[] = "Pregunta {$numStr}: {$sinRetro} opciones sin retroalimentación";
        }

        $preguntas[] = [
            'tipo'          => 'OM',
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

function parsearBloqueFV(string $bloque, int $autoNum): array
{
    preg_match('/^FV\s+(?:Reactivo|Pregunta)\s*(\d*)/i', $bloque, $m);
    $numero = !empty($m[1]) ? (int) $m[1] : $autoNum;
    $numStr = str_pad($numero, 2, '0', STR_PAD_LEFT);

    $contenido           = preg_replace('/^FV\s+(?:Reactivo|Pregunta)\s*\d*/i', '', $bloque, 1);
    $lineas              = preg_split('/\r?\n/', $contenido);
    $enunciadoLineas     = [];
    $enunciadoFinalizado = false;
    $respuesta           = '';
    $retroVerdadero      = '';
    $retroFalso          = '';

    foreach ($lineas as $linea) {
        $t = trim(strip_tags($linea));
        if ($t === '') continue;

        if (preg_match('/^Respuesta\s+correcta\s*:\s*(Verdadero|Falso)/iu', $t, $m)) {
            $enunciadoFinalizado = true;
            $respuesta           = mb_strtolower(trim($m[1]), 'UTF-8') === 'verdadero' ? 'TRUE' : 'FALSE';
        } elseif (preg_match('/^Retro(?:alimentaci[oó]n)?\s+verdadero\s*:\s*(.+)/iu', $t, $m)) {
            $enunciadoFinalizado = true;
            $desde          = extraerContenidoRetro($linea);
            $retroVerdadero = $desde !== '' ? $desde : trim($m[1]);
        } elseif (preg_match('/^Retro(?:alimentaci[oó]n)?\s+falso\s*:\s*(.+)/iu', $t, $m)) {
            $enunciadoFinalizado = true;
            $desde      = extraerContenidoRetro($linea);
            $retroFalso = $desde !== '' ? $desde : trim($m[1]);
        } elseif (!$enunciadoFinalizado) {
            $enunciadoLineas[] = trim($linea);
        }
    }

    $enunciado = implode("\n", $enunciadoLineas);
    $warnings  = [];
    if (empty($enunciado))      $warnings[] = "FV Pregunta {$numStr}: sin enunciado";
    if (empty($respuesta))      $warnings[] = "FV Pregunta {$numStr}: sin respuesta correcta";
    if (empty($retroVerdadero)) $warnings[] = "FV Pregunta {$numStr}: sin retro Verdadero";
    if (empty($retroFalso))     $warnings[] = "FV Pregunta {$numStr}: sin retro Falso";

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

function parsearBloqueOM(string $bloque, int $autoNum): array
{
    preg_match('/^OM\s+(?:Reactivo|Pregunta)\s*(\d*)/i', $bloque, $m);
    $numero = !empty($m[1]) ? (int) $m[1] : $autoNum;
    $numStr = str_pad($numero, 2, '0', STR_PAD_LEFT);

    $contenido        = preg_replace('/^OM\s+(?:Reactivo|Pregunta)\s*\d*/i', '', $bloque, 1);
    $lineas           = preg_split('/\r?\n/', $contenido);
    $posRetroCorr     = null;
    $posRetroIncorr   = null;
    $textoRetroCorr   = '';
    $textoRetroIncorr = '';

    foreach ($lineas as $i => $linea) {
        $t = trim(strip_tags($linea));
        if (preg_match('/^Retro(?:alimentaci[oó]n)?\s+correcta\s*:\s*(.*)/iu', $t, $m)) {
            $posRetroCorr   = $i;
            $desde          = extraerContenidoRetro($linea);
            $textoRetroCorr = $desde !== '' ? $desde : trim($m[1]);
        }
        if (preg_match('/^Retro(?:alimentaci[oó]n)?\s+incorrecta\s*:\s*(.*)/iu', $t, $m)) {
            $posRetroIncorr   = $i;
            $desde            = extraerContenidoRetro($linea);
            $textoRetroIncorr = $desde !== '' ? $desde : trim($m[1]);
        }
    }

    // Retro en línea siguiente si está vacía
    if ($posRetroCorr !== null && $textoRetroCorr === '') {
        for ($j = $posRetroCorr + 1; $j < count($lineas); $j++) {
            $s      = trim($lineas[$j]);
            $sPlain = strip_tags($s);
            if ($sPlain !== '') {
                if (!preg_match('/^(Retro|OM\s+|FV\s+|EM\s+)/i', $sPlain)) $textoRetroCorr = $s;
                break;
            }
        }
    }
    if ($posRetroIncorr !== null && $textoRetroIncorr === '') {
        for ($j = $posRetroIncorr + 1; $j < count($lineas); $j++) {
            $s      = trim($lineas[$j]);
            $sPlain = strip_tags($s);
            if ($sPlain !== '') {
                if (!preg_match('/^(Retro|OM\s+|FV\s+|EM\s+)/i', $sPlain)) $textoRetroIncorr = $s;
                break;
            }
        }
    }

    $finContenido    = $posRetroCorr ?? $posRetroIncorr ?? count($lineas);
    $lineasContenido = array_slice($lineas, 0, $finContenido);
    $opciones        = [];
    $primeraOpcion   = null;

    foreach ($lineasContenido as $i => $linea) {
        $t = trim($linea);
        if (preg_match('/^[a-dA-D][.)]\s+(.+)/', $t, $m)) {
            if ($primeraOpcion === null) $primeraOpcion = $i;
            $opciones[] = trim($m[1]);
        }
    }

    if (count($opciones) === 0) {
        $noVacias = [];
        foreach ($lineasContenido as $i => $linea) {
            $t = trim($linea);
            if ($t !== '') $noVacias[] = ['idx' => $i, 'texto' => $t];
        }
        if (count($noVacias) >= 4) {
            $opcionesRaw   = array_slice($noVacias, -4);
            $primeraOpcion = $opcionesRaw[0]['idx'];
            foreach ($opcionesRaw as $opc) $opciones[] = $opc['texto'];
        }
    }

    $enunciado = '';
    if ($primeraOpcion !== null) {
        $lineasE = [];
        for ($i = 0; $i < $primeraOpcion; $i++) {
            $t = trim($lineas[$i]);
            if ($t !== '') $lineasE[] = $t;
        }
        $enunciado = implode("\n", $lineasE);
    }

    $warnings = [];
    if (count($opciones) !== 4) {
        $warnings[] = "OM Pregunta {$numStr}: se encontraron " . count($opciones) . " opciones en lugar de 4";
    }
    if (empty($textoRetroCorr))   $warnings[] = "OM Pregunta {$numStr}: sin retroalimentación correcta";
    if (empty($textoRetroIncorr)) $warnings[] = "OM Pregunta {$numStr}: sin retroalimentación incorrecta";

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

function parsearBloqueEM(string $bloque, int $autoNum): array
{
    preg_match('/^EM\s+(?:Reactivo|Pregunta)\s*(\d*)/i', $bloque, $m);
    $numero = !empty($m[1]) ? (int) $m[1] : $autoNum;
    $numStr = str_pad($numero, 2, '0', STR_PAD_LEFT);

    $contenido       = preg_replace('/^EM\s+(?:Reactivo|Pregunta)\s*\d*/i', '', $bloque, 1);
    $lineas          = preg_split('/\r?\n/', $contenido);
    $pares           = [];
    $enunciadoLineas = [];
    $paresIniciados  = false;

    foreach ($lineas as $linea) {
        $t = trim($linea);
        if ($t === '') continue;

        if (preg_match('/^(.+?)\s*->\s*(.+)$/', $t, $m)) {
            $paresIniciados = true;
            $pares[]        = ['izq' => trim($m[1]), 'der' => trim($m[2])];
        } elseif (!$paresIniciados) {
            $enunciadoLineas[] = $t;
        }
    }

    $enunciado = implode("\n", $enunciadoLineas);
    $warnings  = [];
    if (empty($pares)) $warnings[] = "EM Pregunta {$numStr}: no se encontraron pares de emparejamiento";

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
 */
/**
 * Extrae el contenido que sigue al primer ':' de una línea HTML,
 * preservando las etiquetas HTML del contenido pero descartando las del prefijo.
 */
function extraerContenidoRetro(string $lineaHtml): string
{
    $len    = mb_strlen($lineaHtml);
    $pos    = 0;
    $inTag  = false;

    while ($pos < $len) {
        $char = mb_substr($lineaHtml, $pos, 1);
        if ($char === '<')      { $inTag = true; }
        elseif ($char === '>')  { $inTag = false; }
        elseif ($char === ':' && !$inTag) {
            $after = mb_substr($lineaHtml, $pos + 1);
            // Eliminar etiquetas de cierre sueltas al inicio (ej: </strong>)
            $after = preg_replace('/^\s*(?:<\/[^>]+>\s*)*/', '', $after);
            // Eliminar etiquetas de cierre sueltas al final
            $after = preg_replace('/(?:\s*<\/[^>]+>)+$/', '', rtrim($after));
            return trim($after);
        }
        $pos++;
    }
    return '';
}

function parsearDocxMixto(string $texto): array
{
    // Normaliza las líneas estructurales eliminando etiquetas HTML que
    // extraerTextoDocx inserta cuando el texto viene formateado (negrita/cursiva).
    // Solo se normalizan los delimitadores de estructura; el contenido del
    // enunciado conserva su HTML para la detección automática de formato.
    $lineas = explode("\n", $texto);
    foreach ($lineas as &$linea) {
        $plain = strip_tags($linea);
        if (
            preg_match('/^(FV|OM|EM)\s+(?:Reactivo|Pregunta)/i', $plain) ||
            preg_match('/^Respuesta\s+correcta\s*:/iu', $plain) ||
            preg_match('/^[a-dA-D][.)]\s+\S/u', $plain) ||
            preg_match('/^.+?\s*->\s*.+$/', $plain)
        ) {
            $linea = $plain;
        }
    }
    unset($linea);
    $texto = implode("\n", $lineas);

    $preguntas = [];
    $warnings  = [];
    $bloques   = preg_split('/^(?=(FV|OM|EM)\s+(?:Reactivo|Pregunta))/mi', $texto);
    $autoNum   = 0;

    foreach ($bloques as $bloque) {
        $bloque = trim($bloque);
        if (empty($bloque)) continue;
        if (!preg_match('/^(FV|OM|EM)\s+(?:Reactivo|Pregunta)/i', $bloque, $m)) continue;

        $tipo = strtoupper($m[1]);
        $autoNum++;

        $preg = match ($tipo) {
            'FV'    => parsearBloqueFV($bloque, $autoNum),
            'OM'    => parsearBloqueOM($bloque, $autoNum),
            'EM'    => parsearBloqueEM($bloque, $autoNum),
            default => null,
        };
        if ($preg === null) continue;

        $preguntas[] = $preg;
        $warnings    = array_merge($warnings, $preg['warnings']);
    }

    return ['preguntas' => $preguntas, 'warnings' => $warnings];
}

// ── Utilidades de formato ───────────────────────────────────────────────────

function procesarImagenes(string $texto): array
{
    $count   = 0;
    $pattern = '/https?:\/\/[^\s]+\.(?:jpg|jpeg|png|gif|webp|svg)(?:\?[^\s]*)?/i';

    $resultado = preg_replace_callback($pattern, function ($match) use (&$count) {
        $count++;
        $url = htmlspecialchars($match[0], ENT_QUOTES, 'UTF-8');
        return '<img src="' . $url . '" alt="" class="d-block mx-auto">';
    }, $texto);

    return ['texto' => $resultado, 'tieneImagen' => $count > 0, 'count' => $count];
}

/**
 * Aplica formato cursiva/negrita manual (suplemento al formato auto-detectado del archivo)
 */
function aplicarFormato(string $texto, array $cursivas, array $negritas): array
{
    $tieneFormato = false;
    $italicCount  = 0;
    $boldCount    = 0;

    $negritas = array_unique(array_filter(array_map('trim', $negritas)));
    $cursivas = array_unique(array_filter(array_map('trim', $cursivas)));

    foreach ($negritas as $palabra) {
        $escaped = preg_quote($palabra, '/');
        $count   = 0;
        $texto   = preg_replace('/(?<!<strong>)(' . $escaped . ')(?!<\/strong>)/iu', '<strong>$1</strong>', $texto, -1, $count);
        if ($count > 0) { $tieneFormato = true; $boldCount += $count; }
    }

    foreach ($cursivas as $palabra) {
        $escaped = preg_quote($palabra, '/');
        $count   = 0;
        $texto   = preg_replace('/(?<!<em>)(' . $escaped . ')(?!<\/em>)/iu', '<em>$1</em>', $texto, -1, $count);
        if ($count > 0) { $tieneFormato = true; $italicCount += $count; }
    }

    return ['texto' => $texto, 'tieneFormato' => $tieneFormato, 'italicCount' => $italicCount, 'boldCount' => $boldCount];
}

function tieneFormatoHtml(string $texto): bool
{
    return str_contains($texto, '<strong>') || str_contains($texto, '<em>') || str_contains($texto, '<img');
}

/**
 * Aplica formato adicional a preguntas para previsualización en el slide panel
 */
function formatearPreguntasPreview(array $preguntas, array $cursivas, array $negritas): array
{
    if (empty($cursivas) && empty($negritas)) return $preguntas;

    foreach ($preguntas as &$preg) {
        if (!empty($preg['enunciado'])) {
            $preg['enunciado'] = aplicarFormato($preg['enunciado'], $cursivas, $negritas)['texto'];
        }
        if (!empty($preg['opciones'])) {
            foreach ($preg['opciones'] as &$opc) {
                $opc = aplicarFormato($opc, $cursivas, $negritas)['texto'];
            }
            unset($opc);
        }
        if (!empty($preg['retros'])) {
            foreach ($preg['retros'] as &$retro) {
                if (!empty($retro)) $retro = aplicarFormato($retro, $cursivas, $negritas)['texto'];
            }
            unset($retro);
        }
        foreach (['retro_correcta', 'retro_incorrecta', 'retro_verdadero', 'retro_falso', 'retro_general'] as $key) {
            if (!empty($preg[$key])) {
                $preg[$key] = aplicarFormato($preg[$key], $cursivas, $negritas)['texto'];
            }
        }
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
