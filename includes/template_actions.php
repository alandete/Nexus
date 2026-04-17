<?php
/**
 * S4Learning - Template Generator
 * Genera plantillas de ejemplo (.docx / .xlsx) para el conversor de preguntas
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

if (!isLoggedIn()) {
    http_response_code(403);
    exit('No autorizado');
}

$type = $_GET['type'] ?? '';

if ($type === 'docx') {
    generarPlantillaDocx();
} elseif ($type === 'xlsx') {
    generarPlantillaXlsx();
} else {
    http_response_code(400);
    exit('Tipo no valido');
}

// ═════════════════════════════════════════════════════════════════════════════
// Generador de .docx
// ═════════════════════════════════════════════════════════════════════════════

function generarPlantillaDocx(): void
{
    $contenido = "OM Pregunta 01\n";
    $contenido .= "¿Cuál es el planeta más grande del sistema solar?\n";
    $contenido .= "a) Marte\n";
    $contenido .= "b) Júpiter\n";
    $contenido .= "c) Saturno\n";
    $contenido .= "d) Neptuno\n";
    $contenido .= "Retro correcta: Júpiter es el planeta más grande del sistema solar.\n";
    $contenido .= "Retro incorrecta: La respuesta correcta es Júpiter.\n";
    $contenido .= "\n";
    $contenido .= "OM Pregunta 02\n";
    $contenido .= "¿En qué año llegó el hombre a la Luna?\n";
    $contenido .= "a) 1965\n";
    $contenido .= "b) 1969\n";
    $contenido .= "c) 1972\n";
    $contenido .= "d) 1975\n";
    $contenido .= "Retro correcta: El 20 de julio de 1969, la misión Apolo 11 llegó a la Luna.\n";
    $contenido .= "Retro incorrecta: La respuesta correcta es 1969.\n";
    $contenido .= "\n";
    $contenido .= "FV Pregunta 03\n";
    $contenido .= "El agua hierve a 100 grados centígrados al nivel del mar.\n";
    $contenido .= "Respuesta correcta: Verdadero\n";
    $contenido .= "Retro verdadero: Correcto, el agua hierve a 100°C a presión atmosférica estándar.\n";
    $contenido .= "Retro falso: Incorrecto, el agua sí hierve a 100°C al nivel del mar.\n";
    $contenido .= "\n";
    $contenido .= "FV Pregunta 04\n";
    $contenido .= "La Gran Muralla China es visible desde el espacio a simple vista.\n";
    $contenido .= "Respuesta correcta: Falso\n";
    $contenido .= "Retro verdadero: Incorrecto, la Gran Muralla no es visible a simple vista desde el espacio.\n";
    $contenido .= "Retro falso: Correcto, es un mito popular; la muralla no se distingue desde la órbita.\n";
    $contenido .= "\n";
    $contenido .= "EM Pregunta 05\n";
    $contenido .= "Relacione cada inventor con su invento:\n";
    $contenido .= "Thomas Edison -> Bombilla eléctrica\n";
    $contenido .= "Alexander Graham Bell -> Teléfono\n";
    $contenido .= "Nikola Tesla -> Corriente alterna\n";
    $contenido .= "Guglielmo Marconi -> Radio";

    $tmpFile = tempnam(sys_get_temp_dir(), 'tpl_');
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::OVERWRITE);

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>');

    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>');

    $zip->addFromString('word/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:default="1" w:styleId="Normal">
    <w:name w:val="Normal"/>
    <w:rPr><w:sz w:val="24"/></w:rPr>
  </w:style>
</w:styles>');

    $ns = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $paragraphs = '';
    foreach (explode("\n", $contenido) as $line) {
        $escaped = htmlspecialchars($line, ENT_XML1, 'UTF-8');
        // Indentar opciones de respuesta (a-d) para que se vean como lista
        $isOption = preg_match('/^[a-d]\)/', $line);
        $pPr = $isOption ? '<w:pPr><w:ind w:left="720"/></w:pPr>' : '';
        $paragraphs .= '<w:p>' . $pPr . '<w:r><w:t xml:space="preserve">' . $escaped . '</w:t></w:r></w:p>';
    }

    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="' . $ns . '">
  <w:body>' . $paragraphs . '</w:body>
</w:document>');

    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="plantilla_preguntas.docx"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    @unlink($tmpFile);
}

// ═════════════════════════════════════════════════════════════════════════════
// Generador de .xlsx (3 hojas: OM, FV, EM)
// ═════════════════════════════════════════════════════════════════════════════

function generarPlantillaXlsx(): void
{
    $hojas = [
        'OM' => [
            ['Nombre', 'Enunciado', 'Opción 1 (correcta)', 'Retro 1', 'Opción 2', 'Retro 2', 'Opción 3', 'Retro 3', 'Opción 4', 'Retro 4', 'Retro general'],
            ['Pregunta 01', '¿Cuál es el planeta más grande del sistema solar?', 'Júpiter', 'Correcto, es el más grande.', 'Marte', 'Marte es el cuarto planeta.', 'Saturno', 'Saturno es el segundo más grande.', 'Neptuno', 'Neptuno es un gigante de hielo.', 'El planeta más grande es Júpiter.'],
            ['Pregunta 02', '¿En qué año llegó el hombre a la Luna?', '1969', 'Correcto, misión Apolo 11.', '1965', 'En 1965 aún no se había logrado.', '1972', '1972 fue la última misión Apolo.', '1975', '1975 fue el programa Apolo-Soyuz.', 'El hombre llegó a la Luna en 1969.'],
        ],
        'FV' => [
            ['Nombre', 'Enunciado', 'Respuesta (Verdadero/Falso)', 'Retro verdadero', 'Retro falso'],
            ['Pregunta 01', 'El agua hierve a 100 grados centígrados al nivel del mar.', 'Verdadero', 'Correcto, el agua hierve a 100°C a presión atmosférica estándar.', 'Incorrecto, el agua sí hierve a 100°C al nivel del mar.'],
            ['Pregunta 02', 'La Gran Muralla China es visible desde el espacio a simple vista.', 'Falso', 'Incorrecto, la Gran Muralla no es visible a simple vista desde el espacio.', 'Correcto, es un mito popular; la muralla no se distingue desde la órbita.'],
        ],
        'EM' => [
            ['Nombre', 'Enunciado', 'Elemento 1', 'Respuesta 1', 'Elemento 2', 'Respuesta 2', 'Elemento 3', 'Respuesta 3', 'Elemento 4', 'Respuesta 4'],
            ['Pregunta 01', 'Relacione cada inventor con su invento:', 'Thomas Edison', 'Bombilla eléctrica', 'Alexander Graham Bell', 'Teléfono', 'Nikola Tesla', 'Corriente alterna', 'Guglielmo Marconi', 'Radio'],
            ['Pregunta 02', 'Relacione cada país con su capital:', 'Francia', 'París', 'Alemania', 'Berlín', 'Italia', 'Roma', 'España', 'Madrid'],
        ],
    ];

    // Shared strings de todas las hojas
    $strings = [];
    $stringIndex = [];
    $totalCount = 0;
    foreach ($hojas as $filas) {
        foreach ($filas as $fila) {
            foreach ($fila as $cell) {
                if (!isset($stringIndex[$cell])) {
                    $stringIndex[$cell] = count($strings);
                    $strings[] = $cell;
                }
                $totalCount++;
            }
        }
    }

    $tmpFile = tempnam(sys_get_temp_dir(), 'tpl_');
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::OVERWRITE);

    // Content Types
    $ctOverrides = '';
    $sheetNum = 1;
    foreach ($hojas as $nombre => $filas) {
        $ctOverrides .= '<Override PartName="/xl/worksheets/sheet' . $sheetNum . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $sheetNum++;
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  ' . $ctOverrides . '
  <Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>
</Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>');

    // Workbook rels + sheets
    $wbRels = '';
    $wbSheets = '';
    $sheetNum = 1;
    foreach ($hojas as $nombre => $filas) {
        $rId = 'rId' . $sheetNum;
        $wbRels .= '<Relationship Id="' . $rId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $sheetNum . '.xml"/>';
        $wbSheets .= '<sheet name="' . htmlspecialchars($nombre, ENT_XML1) . '" sheetId="' . $sheetNum . '" r:id="' . $rId . '"/>';
        $sheetNum++;
    }
    $ssRId = 'rId' . $sheetNum;
    $wbRels .= '<Relationship Id="' . $ssRId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';

    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wbRels . '</Relationships>');

    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>' . $wbSheets . '</sheets>
</workbook>');

    // Shared strings
    $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $ssXml .= '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $totalCount . '" uniqueCount="' . count($strings) . '">';
    foreach ($strings as $s) {
        $ssXml .= '<si><t>' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
    }
    $ssXml .= '</sst>';
    $zip->addFromString('xl/sharedStrings.xml', $ssXml);

    // Worksheets
    $colLetters = range('A', 'Z');
    $sheetNum = 1;
    foreach ($hojas as $nombre => $filas) {
        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $sheetXml .= '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($filas as $rowIdx => $fila) {
            $rowNum = $rowIdx + 1;
            $sheetXml .= '<row r="' . $rowNum . '">';
            foreach ($fila as $colIdx => $cell) {
                $ref = $colLetters[$colIdx] . $rowNum;
                $si = $stringIndex[$cell];
                $sheetXml .= '<c r="' . $ref . '" t="s"><v>' . $si . '</v></c>';
            }
            $sheetXml .= '</row>';
        }

        $sheetXml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet' . $sheetNum . '.xml', $sheetXml);
        $sheetNum++;
    }

    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="plantilla_preguntas.xlsx"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    @unlink($tmpFile);
}
