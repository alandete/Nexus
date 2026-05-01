<?php
/**
 * S4Learning - Alliance Actions Handler
 * Procesa datos de alianzas y genera contenido usando plantillas Jinja2
 */

define('APP_ACCESS', true);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Validar token CSRF
if (!validateCsrf()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

$currentUser = getCurrentUser();
$action = $_POST['action'] ?? '';

if ($action === 'process') {
    procesarAlianza($currentUser);
} elseif ($action === 'check_url') {
    checkUrl();
} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

// ============================================================
// FUNCIÓN PRINCIPAL
// ============================================================

/**
 * Procesa los datos del formulario delegando según la sección activa
 */
function procesarAlianza(array $currentUser): void
{
    if (!hasPermission($currentUser, 'alliances', 'write')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Sin permisos para procesar alianzas']);
        exit;
    }

    $alliance = sanitize($_POST['alliance'] ?? '');
    $lms      = sanitize($_POST['lms']      ?? '');
    $section  = sanitize($_POST['section']  ?? '');

    if (empty($alliance) || empty($lms) || empty($section)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Faltan parámetros: alliance, lms y section son requeridos']);
        exit;
    }

    if (!in_array($lms, ['moodle', 'canvas'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'LMS no válido. Use moodle o canvas']);
        exit;
    }

    // Validar que alliance y section sean alfanuméricos con guiones (evitar path traversal)
    if (!preg_match('/^[a-z0-9-]+$/', $alliance) || !preg_match('/^[a-z0-9-]+$/', $section)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
        exit;
    }

    // Verificar que la carpeta de plantillas exista
    $templateDir = TEMPLATES_PATH . "/{$alliance}/{$lms}/{$section}";
    if (!is_dir($templateDir)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => "Plantillas no encontradas: {$alliance}/{$lms}/{$section}/"]);
        exit;
    }

    // Recopilar datos del formulario (excluir campos de control)
    $exclude = ['action', 'alliance', 'lms', 'section'];
    $data    = [];
    foreach ($_POST as $key => $value) {
        if (!in_array($key, $exclude, true)) {
            $data[$key] = trim($value);
        }
    }

    // Delegar al procesador de cada sección
    $result = match (true) {
        $alliance === 'unab' && $section === 'inicio' => procesarInicioUnab($data, $alliance, $lms),
        $alliance === 'unab' && $section === 'curso'  => procesarCursoUnab($data, $alliance, $lms),
        $section === 'inicio' => procesarInicio($data, $alliance, $lms),
        $section === 'unidad' => procesarUnidad($data, $alliance, $lms),
        default  => ['html' => '', 'warnings' => ['Sección no implementada']],
    };

    $hasHtml = !empty($result['html']);

    if ($hasHtml) {
        logActivity('alliances', 'process', "{$alliance}/{$section}");
    }

    echo json_encode([
        'success'     => $hasHtml,
        'html'        => $result['html'],
        'warnings'    => $result['warnings'],
        'emptyFields' => $result['emptyFields'] ?? [],
        'lms'         => $lms,
        'alliance'    => $alliance,
        'section'     => $section,
        'message'     => $hasHtml ? 'Contenido generado exitosamente' : 'Revise las advertencias indicadas',
    ], JSON_UNESCAPED_UNICODE);
}

// ============================================================
// PROCESADOR: INICIO
// ============================================================

/**
 * Procesa la pestaña Inicio: Generalidades (3 URLs) + Evaluación (14 campos)
 */
function procesarInicio(array $data, string $alliance, string $lms): array
{
    $warnings = [];
    $htmlParts = [];

    // --- Generalidades: 3 campos URL independientes ---
    $urlFields = [
        'docente' => ['template' => '01-docente-v1.html', 'var' => 'Semblanza', 'label' => 'Docente'],
        'silabo'  => ['template' => '02-silabo-v1.html',  'var' => 'Silabo',    'label' => 'Sílabo'],
        'ruta'    => ['template' => '03-ruta-v1.html',    'var' => 'Ruta',      'label' => 'Ruta'],
    ];

    foreach ($urlFields as $field => $config) {
        $value = $data[$field] ?? '';

        if (empty($value)) {
            $warnings[] = "{$config['label']}: vacío";
            continue;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $warnings[] = "{$config['label']}: URL no válida";
            continue;
        }

        $template = cargarPlantilla($alliance, $lms, 'inicio', $config['template']);
        if ($template === null) {
            $warnings[] = "{$config['label']}: plantilla no encontrada";
            continue;
        }

        $safeUrl = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $htmlParts[] = renderizarPlantilla($template, [$config['var'] => $safeUrl]);
    }

    // --- Evaluación: 14 campos, todo-o-nada ---
    // Clave = id del campo, valor = etiqueta legible para el usuario
    $evalFields = [
        'eval_u1_act_1'  => 'Unidad 1 — Actividad 1',
        'eval_u1_pond_1' => 'Unidad 1 — Ponderación 1',
        'eval_u1_act_2'  => 'Unidad 1 — Actividad 2',
        'eval_u1_pond_2' => 'Unidad 1 — Ponderación 2',
        'eval_u2_act_1'  => 'Unidad 2 — Actividad 1',
        'eval_u2_pond_1' => 'Unidad 2 — Ponderación 1',
        'eval_u2_act_2'  => 'Unidad 2 — Actividad 2',
        'eval_u2_pond_2' => 'Unidad 2 — Ponderación 2',
        'eval_u3_act_1'  => 'Unidad 3 — Actividad 1',
        'eval_u3_pond_1' => 'Unidad 3 — Ponderación 1',
        'eval_u3_act_2'  => 'Unidad 3 — Actividad 2',
        'eval_u3_pond_2' => 'Unidad 3 — Ponderación 2',
        'eval_u4_act_1'  => 'Unidad 4 — Actividad 1',
        'eval_u4_pond_1' => 'Unidad 4 — Ponderación 1',
    ];

    $evalData  = [];
    $evalEmpty = [];

    foreach ($evalFields as $field => $label) {
        $value = $data[$field] ?? '';

        if (empty($value)) {
            $evalEmpty[$field] = $label;
            continue;
        }

        // Actividades: sanitizar + procesar #...# → <em>
        if (str_contains($field, '_act_')) {
            $evalData[$field] = sanitizarActividad($value);
        } else {
            // Ponderaciones: sanitizar sin HTML especial
            $evalData[$field] = sanitizarTexto($value);
        }
    }

    $emptyFields = [];

    if (!empty($evalEmpty)) {
        $emptyLabels = implode(', ', array_values($evalEmpty));
        $warnings[] = "Evaluación incompleta. Campos vacíos: {$emptyLabels}";
        $emptyFields = array_keys($evalEmpty);
    } else {
        $template = cargarPlantilla($alliance, $lms, 'inicio', '04-evaluacion-v1.html');
        if ($template !== null) {
            $htmlParts[] = renderizarPlantilla($template, $evalData);
        } else {
            $warnings[] = 'Evaluación: plantilla no encontrada';
        }
    }

    return [
        'html'        => implode("\n\n", $htmlParts),
        'warnings'    => $warnings,
        'emptyFields' => $emptyFields,
    ];
}

// ============================================================
// PROCESADOR: INICIO UNAB
// ============================================================

/**
 * Procesa la pestaña Inicio de UNAB: Banner, Generalidades (4 URLs), Conozcámonos (4 docentes)
 */
function procesarInicioUnab(array $data, string $alliance, string $lms): array
{
    $warnings    = [];
    $htmlParts   = [];
    $emptyFields = [];

    // --- Banner: URL + Nombre + Facultad ---
    $banner   = $data['unab_banner'] ?? '';
    $nombre   = $data['unab_nombre_curso'] ?? '';
    $facultad = $data['unab_facultad'] ?? '';

    if (empty($banner)) {
        $warnings[] = 'Banner: vacío';
        $emptyFields[] = 'unab_banner';
    } elseif (!filter_var($banner, FILTER_VALIDATE_URL)) {
        $warnings[] = 'Banner: URL no válida';
        $emptyFields[] = 'unab_banner';
    }

    if (empty($nombre)) {
        $warnings[] = 'Nombre del curso: vacío';
        $emptyFields[] = 'unab_nombre_curso';
    }

    if (empty($facultad)) {
        $warnings[] = 'Facultad: vacío';
        $emptyFields[] = 'unab_facultad';
    }

    if (!empty($banner) && filter_var($banner, FILTER_VALIDATE_URL) && !empty($nombre) && !empty($facultad)) {
        $template = cargarPlantilla($alliance, $lms, 'inicio', '01-banner-v1.html');
        if ($template !== null) {
            // Eliminar "Diplomado en" si el usuario lo incluyó (la plantilla ya lo tiene)
            $nombre = preg_replace('/^Diplomado\s+en\s+/iu', '', trim($nombre));

            // Sanitizar nombre y aplicar formato inline (#...# → cursiva, *...* → negrilla)
            $nombreSafe = sanitizarTexto($nombre);
            $nombreSafe = preg_replace('/#([^#]+)#/', '<em>$1</em>', $nombreSafe);
            $nombreSafe = preg_replace('/\*([^*]+)\*/', '<strong>$1</strong>', $nombreSafe);

            $htmlParts[] = "<!-- Banner -->\n" . renderizarPlantilla($template, [
                'Imagen'   => htmlspecialchars($banner, ENT_QUOTES, 'UTF-8'),
                'Nombre'   => $nombreSafe,
                'Facultad' => sanitizarTexto($facultad),
            ]);
        } else {
            $warnings[] = 'Banner: plantilla no encontrada';
        }
    }

    // --- Generalidades: Bienvenida + Generalidades (plantilla combinada) ---
    $bienvenida    = $data['unab_video'] ?? '';
    $generalidades = $data['unab_generalidades'] ?? '';
    $generalidadesOk = true;

    foreach ([
        'unab_video'         => ['value' => $bienvenida,    'label' => 'Video de bienvenida'],
        'unab_generalidades' => ['value' => $generalidades, 'label' => 'Generalidades'],
    ] as $field => $config) {
        if (empty($config['value'])) {
            $warnings[] = "{$config['label']}: vacío";
            $emptyFields[] = $field;
            $generalidadesOk = false;
        } elseif (!filter_var($config['value'], FILTER_VALIDATE_URL)) {
            $warnings[] = "{$config['label']}: URL no válida";
            $emptyFields[] = $field;
            $generalidadesOk = false;
        }
    }

    if ($generalidadesOk) {
        $template = cargarPlantilla($alliance, $lms, 'inicio', '02-generalidades-v1.html');
        if ($template !== null) {
            $htmlParts[] = "<!-- Generalidades -->\n" . renderizarPlantilla($template, [
                'Bienvenida'    => htmlspecialchars($bienvenida, ENT_QUOTES, 'UTF-8'),
                'Generalidades' => htmlspecialchars($generalidades, ENT_QUOTES, 'UTF-8'),
            ]);
        } else {
            $warnings[] = 'Generalidades: plantilla no encontrada';
        }
    }

    // --- Ruta y Evaluación: campos URL independientes ---
    $rutaEvalFields = [
        'unab_ruta'       => ['template' => '04-ruta-v1.html',       'var' => 'Ruta',       'label' => 'Ruta'],
        'unab_evaluacion' => ['template' => '05-evaluacion-v1.html', 'var' => 'Evaluacion', 'label' => 'Evaluación'],
    ];

    foreach ($rutaEvalFields as $field => $config) {
        $value = $data[$field] ?? '';

        if (empty($value)) {
            $warnings[] = "{$config['label']}: vacío";
            $emptyFields[] = $field;
            continue;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $warnings[] = "{$config['label']}: URL no válida";
            $emptyFields[] = $field;
            continue;
        }

        $template = cargarPlantilla($alliance, $lms, 'inicio', $config['template']);
        if ($template === null) {
            $warnings[] = "{$config['label']}: plantilla no encontrada";
            continue;
        }

        $safeUrl = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $htmlParts[] = "<!-- {$config['label']} -->\n" . renderizarPlantilla($template, [$config['var'] => $safeUrl]);
    }

    // --- Conozcámonos: hasta 4 docentes activados por checkbox ---
    // Primero validar y recopilar docentes activos
    $docentesValidos = [];
    for ($i = 1; $i <= 4; $i++) {
        $activeKey = "unab_docente_{$i}_active";

        if (!isset($data[$activeKey])) {
            continue;
        }

        $nombreDocente    = $data["unab_docente_{$i}_nombre"] ?? '';
        $fotoDocente      = $data["unab_docente_{$i}_foto"] ?? '';
        $semblanzaDocente = $data["unab_docente_{$i}_semblanza"] ?? '';

        $docenteLabel = "Docente {$i}";
        $docenteOk    = true;

        if (empty($nombreDocente)) {
            $warnings[] = "{$docenteLabel} — Nombre: vacío";
            $emptyFields[] = "unab_docente_{$i}_nombre";
            $docenteOk = false;
        }

        if (empty($fotoDocente)) {
            $warnings[] = "{$docenteLabel} — Foto: vacío";
            $emptyFields[] = "unab_docente_{$i}_foto";
            $docenteOk = false;
        } elseif (!filter_var($fotoDocente, FILTER_VALIDATE_URL)) {
            $warnings[] = "{$docenteLabel} — Foto: URL no válida";
            $emptyFields[] = "unab_docente_{$i}_foto";
            $docenteOk = false;
        }

        if (empty($semblanzaDocente)) {
            $warnings[] = "{$docenteLabel} — Semblanza: vacío";
            $emptyFields[] = "unab_docente_{$i}_semblanza";
            $docenteOk = false;
        } elseif (!filter_var($semblanzaDocente, FILTER_VALIDATE_URL)) {
            $warnings[] = "{$docenteLabel} — Semblanza: URL no válida";
            $emptyFields[] = "unab_docente_{$i}_semblanza";
            $docenteOk = false;
        }

        if ($docenteOk) {
            $docentesValidos[] = [
                'nombre'    => $nombreDocente,
                'foto'      => $fotoDocente,
                'semblanza' => $semblanzaDocente,
            ];
        }
    }

    // Generar HTML: primero todos los nav-links, luego todos los paneles
    if (!empty($docentesValidos)) {
        $navTemplate     = cargarPlantilla($alliance, $lms, 'inicio', '06-docente-nav-v1.html');
        $contentTemplate = cargarPlantilla($alliance, $lms, 'inicio', '06-docente-content-v1.html');

        if ($navTemplate !== null && $contentTemplate !== null) {
            $navParts     = [];
            $contentParts = [];
            $counter      = 1;

            foreach ($docentesValidos as $docente) {
                $id = 'teacher-' . str_pad($counter, 2, '0', STR_PAD_LEFT);

                $navParts[] = renderizarPlantilla($navTemplate, [
                    'Id'     => $id,
                    'Nombre' => sanitizarTexto($docente['nombre']),
                    'UrlFoto' => htmlspecialchars($docente['foto'], ENT_QUOTES, 'UTF-8'),
                ]);

                $contentParts[] = renderizarPlantilla($contentTemplate, [
                    'Id'        => $id,
                    'Semblanza' => htmlspecialchars($docente['semblanza'], ENT_QUOTES, 'UTF-8'),
                ]);

                $counter++;
            }

            $htmlParts[] = "<!-- Conozcámonos: fotos -->\n" . implode("\n", $navParts) . "\n<!-- Conozcámonos: semblanzas -->\n" . implode("\n", $contentParts);
        } else {
            $warnings[] = 'Conozcámonos: plantilla no encontrada';
        }
    }

    return [
        'html'        => implode("\n\n", $htmlParts),
        'warnings'    => $warnings,
        'emptyFields' => $emptyFields,
    ];
}

// ============================================================
// PROCESADOR: CURSO UNAB
// ============================================================

/**
 * Procesa la pestaña Curso de UNAB:
 * Banner, Generalidades (textos + URLs), Aprende (4 unidades), Glosario, Recursos
 */
function procesarCursoUnab(array $data, string $alliance, string $lms): array
{
    $warnings    = [];
    $htmlParts   = [];
    $emptyFields = [];

    // --- Banner: Nombre del tema + Código + Imagen ---
    $nombreTema   = $data['unab_curso_nombre_tema'] ?? '';
    $codigo       = $data['unab_curso_codigo'] ?? '';
    $imagenBanner = $data['unab_curso_imagen_banner'] ?? '';

    if (empty($nombreTema)) {
        $warnings[] = 'Nombre del tema: vacío';
        $emptyFields[] = 'unab_curso_nombre_tema';
    }
    if (empty($codigo)) {
        $warnings[] = 'Código del curso: vacío';
        $emptyFields[] = 'unab_curso_codigo';
    }
    if (empty($imagenBanner)) {
        $warnings[] = 'Imagen del banner: vacío';
        $emptyFields[] = 'unab_curso_imagen_banner';
    } elseif (!filter_var($imagenBanner, FILTER_VALIDATE_URL)) {
        $warnings[] = 'Imagen del banner: URL no válida';
        $emptyFields[] = 'unab_curso_imagen_banner';
    }

    if (!empty($nombreTema) && !empty($codigo) && !empty($imagenBanner) && filter_var($imagenBanner, FILTER_VALIDATE_URL)) {
        $template = cargarPlantilla($alliance, $lms, 'curso', '01-banner-v1.html');
        if ($template !== null) {
            // Sanitizar nombre y aplicar formato inline (#...# -> cursiva)
            $nombreSafe = sanitizarTexto($nombreTema);
            $nombreSafe = preg_replace('/#([^#]+)#/', '<em>$1</em>', $nombreSafe);

            $htmlParts[] = "<!-- Banner -->\n" . renderizarPlantilla($template, [
                'Nombre'  => $nombreSafe,
                'Codigo'  => sanitizarTexto($codigo),
                'Imagen'  => htmlspecialchars($imagenBanner, ENT_QUOTES, 'UTF-8'),
            ]);
        } else {
            $warnings[] = 'Banner: plantilla no encontrada';
        }
    }

    // --- Generalidades: Descripción + Resultados + Syllabus + Video descripción (plantilla combinada) ---
    $descripcion      = $data['unab_curso_descripcion'] ?? '';
    $resultados       = $data['unab_curso_resultados'] ?? '';
    $syllabus         = $data['unab_curso_syllabus'] ?? '';
    $videoDescripcion = $data['unab_curso_video_descripcion'] ?? '';
    $generalidadesOk  = true;

    if (empty($descripcion)) {
        $warnings[] = 'Descripción: vacío';
        $emptyFields[] = 'unab_curso_descripcion';
        $generalidadesOk = false;
    }
    if (empty($resultados)) {
        $warnings[] = 'Resultados de aprendizaje: vacío';
        $emptyFields[] = 'unab_curso_resultados';
        $generalidadesOk = false;
    }

    foreach ([
        'unab_curso_syllabus'          => ['value' => $syllabus,         'label' => 'Syllabus'],
        'unab_curso_video_descripcion' => ['value' => $videoDescripcion, 'label' => 'Video descripción'],
    ] as $field => $config) {
        if (empty($config['value'])) {
            $warnings[] = "{$config['label']}: vacío";
            $emptyFields[] = $field;
            $generalidadesOk = false;
        } elseif (!filter_var($config['value'], FILTER_VALIDATE_URL)) {
            $warnings[] = "{$config['label']}: URL no válida";
            $emptyFields[] = $field;
            $generalidadesOk = false;
        }
    }

    if ($generalidadesOk) {
        $template = cargarPlantilla($alliance, $lms, 'curso', '02-generalidades-v1.html');
        if ($template !== null) {
            $htmlParts[] = "<!-- Generalidades -->\n" . renderizarPlantilla($template, [
                'Descripcion'      => formatearTranscripcion($descripcion),
                'Aprendizaje'      => formatearListaItems($resultados),
                'Syllabus'         => htmlspecialchars($syllabus, ENT_QUOTES, 'UTF-8'),
                'Videodescripcion' => htmlspecialchars($videoDescripcion, ENT_QUOTES, 'UTF-8'),
            ]);
        } else {
            $warnings[] = 'Generalidades: plantilla no encontrada';
        }
    }

    // --- Video de introducción (independiente) ---
    $videoIntroduccion = $data['unab_curso_video_introduccion'] ?? '';

    if (empty($videoIntroduccion)) {
        $warnings[] = 'Video de introducción: vacío';
        $emptyFields[] = 'unab_curso_video_introduccion';
    } elseif (!filter_var($videoIntroduccion, FILTER_VALIDATE_URL)) {
        $warnings[] = 'Video de introducción: URL no válida';
        $emptyFields[] = 'unab_curso_video_introduccion';
    } else {
        $template = cargarPlantilla($alliance, $lms, 'curso', '06-video-introduccion-v1.html');
        if ($template !== null) {
            $htmlParts[] = "<!-- Video de introducción -->\n" . renderizarPlantilla($template, [
                'Videontroduccion' => htmlspecialchars($videoIntroduccion, ENT_QUOTES, 'UTF-8'),
            ]);
        } else {
            $warnings[] = 'Video de introducción: plantilla no encontrada';
        }
    }

    // --- Aprende: hasta 4 unidades activadas por checkbox ---
    $allAlliances    = getAlliances();
    $resourceTypes   = $allAlliances[$alliance]['resource_types'] ?? [];
    $tiposPorValue   = array_column($resourceTypes, null, 'value');

    $unidadesValidas = [];
    for ($i = 1; $i <= 4; $i++) {
        $activeKey = "unab_curso_unidad_{$i}_active";

        if (!isset($data[$activeKey])) {
            continue;
        }

        $tipo    = $data["unab_curso_unidad_{$i}_tipo"] ?? '';
        $recurso = $data["unab_curso_unidad_{$i}_recurso"] ?? '';

        $unidadLabel = "Unidad {$i}";
        $unidadOk    = true;

        if (empty($tipo) || !isset($tiposPorValue[$tipo])) {
            $warnings[] = "{$unidadLabel} — Tipo de recurso: no seleccionado o inválido";
            $emptyFields[] = "unab_curso_unidad_{$i}_tipo";
            $unidadOk = false;
        }

        if (empty($recurso)) {
            $warnings[] = "{$unidadLabel} — Recurso: vacío";
            $emptyFields[] = "unab_curso_unidad_{$i}_recurso";
            $unidadOk = false;
        } elseif (!filter_var($recurso, FILTER_VALIDATE_URL)) {
            $warnings[] = "{$unidadLabel} — Recurso: URL no válida";
            $emptyFields[] = "unab_curso_unidad_{$i}_recurso";
            $unidadOk = false;
        }

        if ($unidadOk) {
            $unidadesValidas[] = [
                'tipo'    => $tipo,
                'recurso' => $recurso,
                'label'   => $unidadLabel,
                'numero'  => $i,
            ];
        }
    }

    if (!empty($unidadesValidas)) {
        $template = cargarPlantilla($alliance, $lms, 'curso', '07-unidad-v1.html');
        if ($template !== null) {
            $counter = 1;
            foreach ($unidadesValidas as $unidad) {
                $tipoConfig = $tiposPorValue[$unidad['tipo']];
                $topicId    = 'topic-' . str_pad($counter, 2, '0', STR_PAD_LEFT);
                $recursoUrl = htmlspecialchars($unidad['recurso'], ENT_QUOTES, 'UTF-8');
                $suffix     = $tipoConfig['url_suffix'] ?? '';

                $tipoLabel = __('alliances.' . ($tipoConfig['label_key'] ?? '')) ?: $unidad['tipo'];
                $htmlParts[] = "<!-- {$unidad['label']}: {$tipoLabel} -->\n" . renderizarPlantilla($template, [
                    'topicid'            => $topicId,
                    'UnidadNumero'       => $unidad['numero'],
                    'DescripcionRecurso' => $tipoConfig['descripcion'] ?? '',
                    'ratio'              => $tipoConfig['ratio'] ?? '16by9',
                    'RecursoUrl'         => $recursoUrl . htmlspecialchars($suffix, ENT_QUOTES, 'UTF-8'),
                ]);

                $counter++;
            }
        } else {
            $warnings[] = 'Aprende: plantilla no encontrada';
        }
    }

    // --- Recursos: obligatorios + opcionales ---
    $obligatorios = $data['unab_curso_obligatorios'] ?? '';
    $opcionales   = $data['unab_curso_opcionales'] ?? '';

    if (empty($obligatorios) && empty($opcionales)) {
        $warnings[] = 'Recursos: ambos campos vacíos';
        $emptyFields[] = 'unab_curso_obligatorios';
        $emptyFields[] = 'unab_curso_opcionales';
    } else {
        $resultObligatorios = !empty($obligatorios)
            ? parsearRecursosUnab($obligatorios)
            : ['items' => [], 'errors' => []];
        $resultOpcionales = !empty($opcionales)
            ? parsearRecursosUnab($opcionales)
            : ['items' => [], 'errors' => []];

        foreach ($resultObligatorios['errors'] as $err) {
            $warnings[] = "Recursos obligatorios: {$err}";
            if (!in_array('unab_curso_obligatorios', $emptyFields, true)) {
                $emptyFields[] = 'unab_curso_obligatorios';
            }
        }
        foreach ($resultOpcionales['errors'] as $err) {
            $warnings[] = "Recursos opcionales: {$err}";
            if (!in_array('unab_curso_opcionales', $emptyFields, true)) {
                $emptyFields[] = 'unab_curso_opcionales';
            }
        }

        $template = cargarPlantilla($alliance, $lms, 'curso', '09-recursos-v1.html');
        if ($template !== null) {
            $recursosParts = [];

            if (!empty($resultObligatorios['items'])) {
                $recursosParts[] = "<!-- Recursos obligatorios -->";
                foreach ($resultObligatorios['items'] as $item) {
                    $recursosParts[] = renderizarPlantilla($template, [
                        'Urlrecurso' => $item['url'],
                        'Cita'       => $item['cita'],
                    ]);
                }
            }

            if (!empty($resultOpcionales['items'])) {
                $recursosParts[] = "<!-- Recursos opcionales -->";
                foreach ($resultOpcionales['items'] as $item) {
                    $recursosParts[] = renderizarPlantilla($template, [
                        'Urlrecurso' => $item['url'],
                        'Cita'       => $item['cita'],
                    ]);
                }
            }

            if (!empty($recursosParts)) {
                $htmlParts[] = implode("\n", $recursosParts);
            }
        } else {
            $warnings[] = 'Recursos: plantilla no encontrada';
        }
    }

    // --- Glosario: destacados + términos ---
    $destacados = $data['unab_curso_destacados'] ?? '';
    $terminos   = $data['unab_curso_terminos'] ?? '';

    if (empty($terminos)) {
        $warnings[] = 'Glosario — Términos: vacío';
        $emptyFields[] = 'unab_curso_terminos';
    } else {
        $resultGlosario = parsearGlosario($terminos, $destacados);

        foreach ($resultGlosario['errors'] as $err) {
            $warnings[] = "Glosario: {$err}";
        }

        if (!empty($resultGlosario['pares'])) {
            $template = cargarPlantilla($alliance, $lms, 'curso', '08-glosario-v1.html');
            if ($template !== null) {
                $glosarioParts = [];
                $counter = 1;
                foreach ($resultGlosario['pares'] as $par) {
                    $terminoId = 'termino-' . str_pad($counter, 2, '0', STR_PAD_LEFT);
                    $glosarioParts[] = renderizarPlantilla($template, [
                        'terminoId'  => $terminoId,
                        'Termino'    => $par['termino'],
                        'Definicion' => $par['definicion'],
                    ]);
                    $counter++;
                }
                $htmlParts[] = "<!-- Glosario -->\n" . implode("\n", $glosarioParts);
            } else {
                $warnings[] = 'Glosario: plantilla no encontrada';
            }
        }
    }

    return [
        'html'        => implode("\n\n", $htmlParts),
        'warnings'    => $warnings,
        'emptyFields' => $emptyFields,
    ];
}

// ============================================================
// PROCESADOR: UNIDAD
// ============================================================

/**
 * Procesa la pestaña Unidad: Título, Audio, Temario, Aprendizaje, Glosario, Recursos, Multimedia, Takeaway
 */
function procesarUnidad(array $data, string $alliance, string $lms): array
{
    $warnings    = [];
    $htmlParts   = [];
    $emptyFields = [];

    // --- Título: Unidad (select) + Nombre (texto) ---
    $unidad = $data['unidad'] ?? '';
    $nombre = $data['nombre'] ?? '';

    if (empty($unidad)) {
        $warnings[] = 'Unidad: vacío';
        $emptyFields[] = 'unidad';
    }
    if (empty($nombre)) {
        $warnings[] = 'Nombre: vacío';
        $emptyFields[] = 'nombre';
    }

    if (!empty($unidad) && !empty($nombre)) {
        $template = cargarPlantilla($alliance, $lms, 'unidad', '01-titulo-v1.html');
        if ($template !== null) {
            $safeUnidad = htmlspecialchars("Unidad {$unidad}", ENT_QUOTES, 'UTF-8');
            $safeNombre = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
            $safeNombre = preg_replace('/#([^#]+)#/', '<em>$1</em>', $safeNombre);
            $htmlParts[] = "<!-- Título -->\n" . renderizarPlantilla($template, [
                'Unidad' => $safeUnidad,
                'Nombre' => $safeNombre,
            ]);
        } else {
            $warnings[] = 'Título: plantilla no encontrada';
        }
    }

    // --- Audio: URL + Transcripción ---
    $audio         = $data['audio'] ?? '';
    $transcripcion = $data['transcripcion'] ?? '';

    if (empty($audio)) {
        $warnings[] = 'Audio: vacío';
        $emptyFields[] = 'audio';
    } elseif (!filter_var($audio, FILTER_VALIDATE_URL)) {
        $warnings[] = 'Audio: URL no válida';
        $emptyFields[] = 'audio';
    }

    if (empty($transcripcion)) {
        $warnings[] = 'Transcripción: vacío';
        $emptyFields[] = 'transcripcion';
    }

    // Generar bloque Audio si la URL es válida (transcripción puede estar vacía, se advierte arriba)
    if (!empty($audio) && filter_var($audio, FILTER_VALIDATE_URL)) {
        $template = cargarPlantilla($alliance, $lms, 'unidad', '02-audio-v1.html');
        if ($template !== null) {
            $safeAudio = htmlspecialchars($audio, ENT_QUOTES, 'UTF-8');
            $htmlTranscripcion = !empty($transcripcion)
                ? formatearTranscripcion($transcripcion)
                : '';
            $htmlParts[] = "<!-- Audio -->\n" . renderizarPlantilla($template, [
                'Audio'         => $safeAudio,
                'Transcripcion' => $htmlTranscripcion,
            ]);
        } else {
            $warnings[] = 'Audio: plantilla no encontrada';
        }
    }

    // --- Temario: URL de imagen ---
    $imagen = $data['imagen'] ?? '';

    if (empty($imagen)) {
        $warnings[] = 'Temario: vacío';
        $emptyFields[] = 'imagen';
    } else {
        // Eliminar espacios en blanco adicionales
        $imagen = preg_replace('/\s+/', '', $imagen);

        if (!filter_var($imagen, FILTER_VALIDATE_URL)) {
            $warnings[] = 'Temario: URL no válida';
            $emptyFields[] = 'imagen';
        } else {
            $template = cargarPlantilla($alliance, $lms, 'unidad', '03-temario-v1.html');
            if ($template !== null) {
                $safeImagen = htmlspecialchars($imagen, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<!-- Temario -->\n" . renderizarPlantilla($template, ['Temario' => $safeImagen]);
            } else {
                $warnings[] = 'Temario: plantilla no encontrada';
            }
        }
    }

    // --- Resultado de aprendizaje: lista <li> por línea ---
    $aprendizaje = $data['aprendizaje'] ?? '';

    if (empty($aprendizaje)) {
        $warnings[] = 'Aprendizaje: vacío';
        $emptyFields[] = 'aprendizaje';
    } else {
        $template = cargarPlantilla($alliance, $lms, 'unidad', '04-aprendizaje-v1.html');
        if ($template !== null) {
            $htmlAprendizaje = formatearListaItems($aprendizaje);
            $htmlParts[] = "<!-- Resultado de aprendizaje -->\n" . renderizarPlantilla($template, ['aprendizaje' => $htmlAprendizaje]);
        } else {
            $warnings[] = 'Aprendizaje: plantilla no encontrada';
        }
    }

    // --- Glosario: pares Término:Definición con destacados ---
    $destacadosGlosario = $data['destacados_glosario'] ?? '';
    $glosario           = $data['glosario'] ?? '';

    if (empty($glosario)) {
        $warnings[] = 'Glosario: vacío';
        $emptyFields[] = 'glosario';
    } else {
        $resultGlosario = formatearGlosario($glosario, $destacadosGlosario);

        if (!empty($resultGlosario['errors'])) {
            foreach ($resultGlosario['errors'] as $err) {
                $warnings[] = "Glosario: {$err}";
            }
            $emptyFields[] = 'glosario';
        }

        if (!empty($resultGlosario['html'])) {
            $template = cargarPlantilla($alliance, $lms, 'unidad', '05-glosario-v1.html');
            if ($template !== null) {
                // Reemplazar el bloque REPEAT con los <details> generados
                $html = preg_replace(
                    '/<!--\s*REPEAT.*?-->\s*.*?\s*<!--\s*END REPEAT\s*-->/s',
                    $resultGlosario['html'],
                    $template
                );
                $htmlParts[] = "<!-- Glosario -->\n" . $html;
            } else {
                $warnings[] = 'Glosario: plantilla no encontrada';
            }
        }
    }

    // --- Recursos: Académicos + Conoce + ---
    $recursosAcademicos = $data['recursos_academicos'] ?? '';
    $conoceMas          = $data['conoce_mas'] ?? '';

    if (empty($recursosAcademicos) && empty($conoceMas)) {
        $warnings[] = 'Recursos: ambos campos vacíos';
        $emptyFields[] = 'recursos_academicos';
        $emptyFields[] = 'conoce_mas';
    } else {
        $resultAcademicos = !empty($recursosAcademicos)
            ? formatearRecursos($recursosAcademicos)
            : ['html' => '', 'errors' => []];
        $resultConoce = !empty($conoceMas)
            ? formatearRecursos($conoceMas)
            : ['html' => '', 'errors' => []];

        foreach ($resultAcademicos['errors'] as $err) {
            $warnings[] = "Recursos Académicos: {$err}";
            if (!in_array('recursos_academicos', $emptyFields, true)) {
                $emptyFields[] = 'recursos_academicos';
            }
        }
        foreach ($resultConoce['errors'] as $err) {
            $warnings[] = "Conoce +: {$err}";
            if (!in_array('conoce_mas', $emptyFields, true)) {
                $emptyFields[] = 'conoce_mas';
            }
        }

        // Combinar ambos bloques con comentarios identificadores
        $recursosParts = [];
        if (!empty($resultAcademicos['html'])) {
            $recursosParts[] = "    <!-- Recursos Académicos -->\n" . $resultAcademicos['html'];
        }
        if (!empty($resultConoce['html'])) {
            $recursosParts[] = "    <!-- Conoce + -->\n" . $resultConoce['html'];
        }

        if (!empty($recursosParts)) {
            $template = cargarPlantilla($alliance, $lms, 'unidad', '06-recursos-v1.html');
            if ($template !== null) {
                $html = preg_replace(
                    '/<!--\s*REPEAT.*?-->\s*.*?\s*<!--\s*END REPEAT\s*-->/s',
                    implode("\n\n", $recursosParts),
                    $template
                );
                $htmlParts[] = "<!-- Recursos -->\n" . $html;
            } else {
                $warnings[] = 'Recursos: plantilla no encontrada';
            }
        }
    }

    // --- Multimedia: texto libre ---
    $multimedia = $data['multimedia'] ?? '';

    if (empty($multimedia)) {
        $warnings[] = 'Multimedia: vacío';
        $emptyFields[] = 'multimedia';
    } else {
        $template = cargarPlantilla($alliance, $lms, 'unidad', '07-multimedia-v1.html');
        if ($template !== null) {
            $safeMultimedia = htmlspecialchars($multimedia, ENT_QUOTES, 'UTF-8');
            $safeMultimedia = preg_replace('/#([^#]+)#/', '<em>$1</em>', $safeMultimedia);
            $htmlParts[] = "<!-- Multimedia -->\n" . renderizarPlantilla($template, ['Multimedia' => $safeMultimedia]);
        } else {
            $warnings[] = 'Multimedia: plantilla no encontrada';
        }
    }

    // --- Takeaway: URL válida ---
    $takeaway = $data['takeaway'] ?? '';

    if (empty($takeaway)) {
        $warnings[] = 'Takeaway: vacío';
        $emptyFields[] = 'takeaway';
    } else {
        $takeaway = preg_replace('/\s+/', '', $takeaway);

        if (!filter_var($takeaway, FILTER_VALIDATE_URL)) {
            $warnings[] = 'Takeaway: URL no válida';
            $emptyFields[] = 'takeaway';
        } else {
            $template = cargarPlantilla($alliance, $lms, 'unidad', '08-takeaway-v1.html');
            if ($template !== null) {
                $safeTakeaway = htmlspecialchars($takeaway, ENT_QUOTES, 'UTF-8');
                $htmlParts[] = "<!-- Takeaway -->\n" . renderizarPlantilla($template, ['Takeaway' => $safeTakeaway]);
            } else {
                $warnings[] = 'Takeaway: plantilla no encontrada';
            }
        }
    }

    return [
        'html'        => implode("\n\n", $htmlParts),
        'warnings'    => $warnings,
        'emptyFields' => $emptyFields,
    ];
}

// ============================================================
// FUNCIONES DE UTILIDAD
// ============================================================

/**
 * Carga un archivo de plantilla individual
 */
function cargarPlantilla(string $alliance, string $lms, string $section, string $filename): ?string
{
    // Validar nombre de archivo (prevenir path traversal)
    if (!preg_match('/^[a-z0-9-]+\.html$/', $filename)) {
        return null;
    }

    $path = TEMPLATES_PATH . "/{$alliance}/{$lms}/{$section}/{$filename}";

    if (!file_exists($path)) {
        return null;
    }

    return file_get_contents($path);
}

/**
 * Sustituye variables {{ variable }} en la plantilla con los datos recibidos
 */
function renderizarPlantilla(string $template, array $data): string
{
    return preg_replace_callback(
        '/\{\{\s*(\w+)\s*\}\}/',
        function (array $matches) use ($data): string {
            return $data[$matches[1]] ?? $matches[0];
        },
        $template
    );
}

/**
 * Sanitiza un campo de actividad:
 * 1. Elimina HTML, 2. Escapa entidades, 3. Colapsa espacios, 4. Convierte #...# a <em>
 */
function sanitizarActividad(string $value): string
{
    $value = strip_tags($value);
    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $value = preg_replace('/\s+/', ' ', trim($value));
    $value = preg_replace('/#([^#]+)#/', '<em>$1</em>', $value);
    return $value;
}

/**
 * Sanitiza un campo de texto simple (ponderaciones, etc.):
 * 1. Elimina HTML, 2. Escapa entidades, 3. Colapsa espacios
 */
function sanitizarTexto(string $value): string
{
    $value = strip_tags($value);
    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $value = preg_replace('/\s+/', ' ', trim($value));
    return $value;
}

/**
 * Formatea el campo de transcripción:
 * 1. Cada salto de línea separa un párrafo (<p>)
 * 2. #...# → <em> (cursiva)
 * 3. *...* → <strong> (negrilla)
 */
function formatearTranscripcion(string $value): string
{
    // Separar por saltos de línea (normalizar \r\n y \r a \n)
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $lines = explode("\n", $value);

    $paragraphs = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        // Sanitizar: strip_tags + htmlspecialchars
        $line = strip_tags($line);
        $line = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');

        // Convertir #...# → <em> (cursiva)
        $line = preg_replace('/#([^#]+)#/', '<em>$1</em>', $line);

        // Convertir *...* → <strong> (negrilla)
        $line = preg_replace('/\*([^*]+)\*/', '<strong>$1</strong>', $line);

        $paragraphs[] = '<p>' . $line . '</p>';
    }

    return implode("\n", $paragraphs);
}

/**
 * Formatea un campo multilínea como ítems de lista (<li>):
 * 1. Cada salto de línea es un <li>
 * 2. #...# → <em> (cursiva)
 * 3. *...* → <strong> (negrilla)
 */
function formatearListaItems(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $lines = explode("\n", $value);

    $items = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $line = strip_tags($line);
        $line = htmlspecialchars($line, ENT_QUOTES, 'UTF-8');
        $line = preg_replace('/#([^#]+)#/', '<em>$1</em>', $line);
        $line = preg_replace('/\*([^*]+)\*/', '<strong>$1</strong>', $line);

        $items[] = '<li>' . $line . '</li>';
    }

    return implode("\n", $items);
}

/**
 * Procesa el glosario: pares Término:Definición o Término&Definición
 * 1. Parsea cada línea buscando separador : o &
 * 2. Valida que cada línea tenga término y definición
 * 3. Ordena alfabéticamente por término
 * 4. Aplica destacados (cursiva case-sensitive) desde el campo destacados_glosario
 * 5. Aplica *...* → <strong> (negrilla)
 * 6. Genera <details> con contador numItem
 *
 * @return array{html: string, errors: string[]}
 */
/**
 * Parsea y valida pares Término:Definición o Término&Definición.
 * 1. Parsea cada línea buscando separador : o &
 * 2. Ordena alfabéticamente por término (case-insensitive)
 * 3. Sanitiza, asegura mayúscula inicial y punto final en definición
 * 4. Aplica #...# → <em>, *...* → <strong>, y destacados → <em>
 *
 * @return array{pares: array, errors: string[]}
 */
function parsearGlosario(string $glosario, string $destacados): array
{
    $errors = [];

    // --- Parsear palabras/frases destacadas (separadas por coma) ---
    $palabrasDestacadas = [];
    if (!empty($destacados)) {
        foreach (explode(',', $destacados) as $palabra) {
            $palabra = trim($palabra);
            if ($palabra !== '' && !in_array($palabra, $palabrasDestacadas)) {
                $palabrasDestacadas[] = $palabra;
            }
        }
    }

    // --- Parsear pares Término:Definición o Término&Definición ---
    $glosario = str_replace(["\r\n", "\r"], "\n", $glosario);
    $lines    = explode("\n", $glosario);
    $pares    = [];

    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        // Buscar separador: primero & luego :
        $separatorPos = false;

        $posAmpersand = strpos($line, '&');
        if ($posAmpersand !== false) {
            $separatorPos = $posAmpersand;
        }

        if ($separatorPos === false) {
            $posColon = strpos($line, ':');
            if ($posColon !== false) {
                $separatorPos = $posColon;
            }
        }

        if ($separatorPos === false) {
            $errors[] = "Línea " . ($lineNum + 1) . ": sin separador (: o &)";
            continue;
        }

        $termino    = trim(substr($line, 0, $separatorPos));
        $definicion = trim(substr($line, $separatorPos + 1));

        if ($termino === '') {
            $errors[] = "Línea " . ($lineNum + 1) . ": término vacío";
            continue;
        }

        if ($definicion === '') {
            $errors[] = "Línea " . ($lineNum + 1) . ": definición vacía para \"{$termino}\"";
            continue;
        }

        $pares[] = ['termino' => $termino, 'definicion' => $definicion];
    }

    if (empty($pares)) {
        return ['pares' => [], 'errors' => $errors ?: ['No se encontraron pares válidos']];
    }

    // --- Ordenar alfabéticamente por término (soporte UTF-8/acentos) ---
    $collator = class_exists('Collator') ? new Collator('es') : null;
    usort($pares, function ($a, $b) use ($collator) {
        if ($collator) {
            return $collator->compare($a['termino'], $b['termino']);
        }
        return strcasecmp($a['termino'], $b['termino']);
    });

    // --- Sanitizar y formatear cada par ---
    foreach ($pares as &$par) {
        // Sanitizar
        $termino    = strip_tags($par['termino']);
        $termino    = htmlspecialchars($termino, ENT_QUOTES, 'UTF-8');
        $termino    = preg_replace('/\s+/', ' ', trim($termino));
        $definicion = strip_tags($par['definicion']);
        $definicion = htmlspecialchars($definicion, ENT_QUOTES, 'UTF-8');
        $definicion = preg_replace('/\s+/', ' ', trim($definicion));

        // Mayúscula inicial (respeta mayúsculas sostenidas)
        $termino    = mb_strtoupper(mb_substr($termino, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($termino, 1, null, 'UTF-8');
        $definicion = mb_strtoupper(mb_substr($definicion, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($definicion, 1, null, 'UTF-8');

        // Punto final en definición si no lo tiene
        if (!str_ends_with($definicion, '.')) {
            $definicion .= '.';
        }

        // Aplicar #...# → <em> (cursiva)
        $termino    = preg_replace('/#([^#]+)#/', '<em>$1</em>', $termino);
        $definicion = preg_replace('/#([^#]+)#/', '<em>$1</em>', $definicion);

        // Aplicar *...* → <strong> (negrilla)
        $termino    = preg_replace('/\*([^*]+)\*/', '<strong>$1</strong>', $termino);
        $definicion = preg_replace('/\*([^*]+)\*/', '<strong>$1</strong>', $definicion);

        // Aplicar destacados como <em> (case-sensitive, evita doble anidamiento)
        foreach ($palabrasDestacadas as $palabra) {
            $escaped = htmlspecialchars($palabra, ENT_QUOTES, 'UTF-8');
            $quoted  = preg_quote($escaped, '/');
            // Solo aplica si no está ya dentro de <em>...</em>
            $termino    = preg_replace("/(?<!<em>)({$quoted})(?!<\/em>)/", '<em>$1</em>', $termino);
            $definicion = preg_replace("/(?<!<em>)({$quoted})(?!<\/em>)/", '<em>$1</em>', $definicion);
        }

        $par['termino']    = $termino;
        $par['definicion'] = $definicion;
    }
    unset($par);

    return ['pares' => $pares, 'errors' => $errors];
}

/**
 * Wrapper para UNIS: retorna HTML con <details> usando parsearGlosario().
 */
function formatearGlosario(string $glosario, string $destacados): array
{
    $result = parsearGlosario($glosario, $destacados);

    if (empty($result['pares'])) {
        return ['html' => '', 'errors' => $result['errors']];
    }

    $detailsParts = [];
    foreach ($result['pares'] as $index => $par) {
        $numItem = $index + 1;
        $detailsParts[] = "        <details>\n"
                        . "          <summary>{$par['termino']}</summary>\n"
                        . "          <p>{$par['definicion']}</p>\n"
                        . "        </details> <!-- Fin término {$numItem} -->";
    }

    return [
        'html'   => implode("\n\n", $detailsParts),
        'errors' => $result['errors'],
    ];
}

/**
 * Procesa un campo de recursos (Académicos o Conoce +):
 * 1. Agrupa por párrafos (separados por líneas vacías)
 * 2. Dentro de cada párrafo une las líneas (la URL puede estar en línea aparte)
 * 3. Extrae el tipo [web|pdf|audio|video] del inicio (case-insensitive)
 * 4. La URL se extrae del final del texto (último token https?://...)
 * 5. El texto previo a la URL es la cita (recurso)
 * 6. Valida URL con filter_var
 * 7. Ordena alfabéticamente
 * 8. Aplica #...# → <em> y *...* → <strong>
 *
 * @return array{html: string, errors: string[]}
 */
function formatearRecursos(string $recursos): array
{
    $errors = [];
    $tiposPermitidos = ['web', 'pdf', 'audio', 'video'];

    $recursos = str_replace(["\r\n", "\r"], "\n", $recursos);

    // Agrupar por párrafos (separados por líneas vacías)
    // Si no hay líneas vacías, cada línea es un recurso
    $bloques = preg_split('/\n\s*\n/', trim($recursos));
    $items   = [];

    foreach ($bloques as $blockNum => $bloque) {
        // Unir líneas del bloque en una sola cadena (separadas por espacio)
        $lineas = explode("\n", $bloque);
        $lineas = array_map('trim', $lineas);
        $lineas = array_filter($lineas, fn($l) => $l !== '');
        $texto  = implode(' ', $lineas);

        if ($texto === '') {
            continue;
        }

        // Extraer el tipo [web], [pdf], [audio], [video] del inicio
        $tipo = '';
        if (preg_match('/^\[(\w+)\]\s*/', $texto, $tipoMatch)) {
            $tipoCandidate = strtolower(trim($tipoMatch[1]));
            if (in_array($tipoCandidate, $tiposPermitidos, true)) {
                $tipo  = $tipoCandidate;
                $texto = trim(substr($texto, strlen($tipoMatch[0])));
            } else {
                $errors[] = "Recurso " . ($blockNum + 1) . ": tipo [{$tipoMatch[1]}] no válido. Use: [web], [pdf], [audio] o [video]";
                continue;
            }
        } else {
            $errors[] = "Recurso " . ($blockNum + 1) . ": falta el tipo al inicio. Use: [web], [pdf], [audio] o [video]";
            continue;
        }

        // Extraer la URL del final del texto
        if (preg_match('/(https?:\/\/\S+)\s*$/i', $texto, $matches)) {
            $url     = trim($matches[1]);
            $recurso = trim(substr($texto, 0, strpos($texto, $matches[1])));
        } else {
            $errors[] = "Recurso " . ($blockNum + 1) . ": no se encontró una URL válida al final";
            continue;
        }

        // Validar formato de URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = "Recurso " . ($blockNum + 1) . ": URL no válida";
            continue;
        }

        if ($recurso === '') {
            $errors[] = "Recurso " . ($blockNum + 1) . ": texto del recurso vacío";
            continue;
        }

        $items[] = ['recurso' => $recurso, 'url' => $url, 'tipo' => $tipo];
    }

    if (empty($items)) {
        return ['html' => '', 'errors' => $errors ?: ['No se encontraron recursos válidos']];
    }

    // Ordenar alfabéticamente por texto del recurso
    usort($items, function ($a, $b) {
        return strcasecmp($a['recurso'], $b['recurso']);
    });

    // Generar <a> por cada recurso
    $linkParts = [];
    foreach ($items as $item) {
        // Sanitizar
        $recurso = strip_tags($item['recurso']);
        $recurso = htmlspecialchars($recurso, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8');
        $tipo    = $item['tipo'];

        // Aplicar #...# → <em> y *...* → <strong>
        $recurso = preg_replace('/#([^#]+)#/', '<em>$1</em>', $recurso);
        $recurso = preg_replace('/\*([^*]+)\*/', '<strong>$1</strong>', $recurso);

        $linkParts[] = "        <a class=\"assetsItems {$tipo}\" href=\"{$safeUrl}\" target=\"_blank\">{$recurso}</a>";
    }

    return [
        'html'   => implode("\n", $linkParts),
        'errors' => $errors,
    ];
}

/**
 * Parsea recursos para UNAB (sin tipo [web|pdf|...]).
 * 1. Agrupa por párrafos (separados por líneas vacías)
 * 2. Extrae la URL del final del texto
 * 3. El texto previo a la URL es la cita
 * 4. Ordena alfabéticamente por cita
 * 5. Aplica #...# → <span style="font-weight: normal;">
 * 6. Respeta mayúsculas del usuario
 *
 * @return array{items: array, errors: string[]}
 */
function parsearRecursosUnab(string $recursos): array
{
    $errors = [];

    $recursos = str_replace(["\r\n", "\r"], "\n", $recursos);
    $bloques  = preg_split('/\n\s*\n/', trim($recursos));
    $items    = [];

    foreach ($bloques as $blockNum => $bloque) {
        $lineas = explode("\n", $bloque);
        $lineas = array_map('trim', $lineas);
        $lineas = array_filter($lineas, fn($l) => $l !== '');
        $texto  = implode(' ', $lineas);

        if ($texto === '') {
            continue;
        }

        // Extraer la URL (puede estar al inicio, medio o final del texto)
        if (preg_match('/(https?:\/\/\S+)/i', $texto, $matches)) {
            $url  = trim($matches[1]);
            $cita = trim(str_replace($matches[1], '', $texto));
        } else {
            $errors[] = "Recurso " . ($blockNum + 1) . ": no se encontró una URL válida";
            continue;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $errors[] = "Recurso " . ($blockNum + 1) . ": URL no válida";
            continue;
        }

        if ($cita === '') {
            $errors[] = "Recurso " . ($blockNum + 1) . ": texto de la cita vacío";
            continue;
        }

        $items[] = ['cita' => $cita, 'url' => $url];
    }

    if (empty($items)) {
        return ['items' => [], 'errors' => $errors ?: ['No se encontraron recursos válidos']];
    }

    // Ordenar alfabéticamente por cita (soporte UTF-8/acentos)
    $collator = class_exists('Collator') ? new Collator('es') : null;
    usort($items, function ($a, $b) use ($collator) {
        if ($collator) {
            return $collator->compare($a['cita'], $b['cita']);
        }
        return strcasecmp($a['cita'], $b['cita']);
    });

    // Sanitizar y formatear
    foreach ($items as &$item) {
        $cita = strip_tags($item['cita']);
        $cita = htmlspecialchars($cita, ENT_QUOTES, 'UTF-8');
        $cita = preg_replace('/\s+/', ' ', trim($cita));

        // #...# → <span style="font-weight: normal;"> (texto sin negrilla)
        $cita = preg_replace('/#([^#]+)#/', '<span style="font-weight: normal;">$1</span>', $cita);

        $item['cita'] = $cita;
        $item['url']  = htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8');
    }
    unset($item);

    return ['items' => $items, 'errors' => $errors];
}

// ============================================================
// CHECK URL
// ============================================================

function checkUrl(): void
{
    $raw = trim($_POST['url'] ?? '');

    if (empty($raw)) {
        echo json_encode(['reachable' => false]);
        exit;
    }

    $parsed = parse_url($raw);
    $scheme = strtolower($parsed['scheme'] ?? '');
    $host   = strtolower($parsed['host']   ?? '');

    // Solo http/https
    if (!in_array($scheme, ['http', 'https'], true) || empty($host)) {
        echo json_encode(['reachable' => false]);
        exit;
    }

    // Protección SSRF: bloquear IPs privadas y loopback
    $ip = filter_var($host, FILTER_VALIDATE_IP)
        ? $host
        : gethostbyname($host);

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        echo json_encode(['reachable' => false]);
        exit;
    }

    $ch = curl_init($raw);
    curl_setopt_array($ch, [
        CURLOPT_NOBODY         => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; NexusBot/1.0)',
    ]);
    curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // 2xx y 3xx = alcanzable
    $reachable = $code >= 200 && $code < 400;
    echo json_encode(['reachable' => $reachable]);
    exit;
}


