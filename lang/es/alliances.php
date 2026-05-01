<?php

/**
 * S4Learning - Traducciones Español (Alianzas)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'alliances' => [
        'page_title'             => 'Alianzas corporativas',
        'lms_label'              => 'LMS:',
        'btn_clean'              => 'Limpiar',
        'btn_process'            => 'Procesar',
        'btn_processing'         => 'Procesando...',
        'lms_hint'               => 'Selecciona una plataforma para ver la plantilla del curso.',
        'lms_unavailable_notice' => 'El contenido para el LMS seleccionado aún no está disponible en esta alianza. Selecciona <strong>Moodle</strong> para continuar trabajando.',

        // Submenu de alianzas
        'unis'                          => 'UNIS',
        'unab'                          => 'UNAB',
        'panamericana'                  => 'Panamericana',
        'central'                       => 'Central',
        'formacion_docente'             => 'Formación Docente',
        'coming_soon_unab'              => 'Contenido de UNAB próximamente...',
        'coming_soon_panamericana'      => 'Contenido de Panamericana próximamente...',
        'coming_soon_central'           => 'Contenido de Central próximamente...',
        'coming_soon_formacion_docente' => 'Contenido de Formación Docente próximamente...',

        // Placeholder alianzas sin UI lista
        'placeholder_badge'   => 'En desarrollo',
        'placeholder_links'   => 'Accesos directos',
        'placeholder_website' => 'Sitio web institucional',
        'placeholder_lms'     => 'Plataforma LMS',
        'placeholder_team'    => 'Equipo de trabajo',
        'role_manager'        => 'Líder',
        'role_coordinator'    => 'Coordinador',
        'role_migrator'       => 'Migrador',
        'inactive_notice'     => 'Esta alianza no está activa actualmente.',

        // Pestañas internas
        'tab_inicio'  => 'Inicio',
        'tab_unidad'  => 'Unidad',
        'tab_curso'   => 'Curso',

        // Inicio — Generalidades (UNIS)
        'generalidades'      => 'Generalidades',
        'generalidades_desc' => 'Indica en cada campo la URL que le corresponda. El campo se mostrará con borde <strong class="text-success">verde</strong> si la URL es válida o <strong class="text-danger">rojo</strong> si no lo es.',

        // Inicio — Evaluación (UNIS)
        'evaluacion'      => 'Evaluación',
        'evaluacion_desc' => 'Indique el nombre de la actividad y su ponderación para cada unidad siguiendo el documento de evaluación que corresponda en el curso. En los nombres de las actividades use #...# para cursiva.',
        'col_unit'        => 'Unidad',
        'col_activity'    => 'Actividad',
        'col_weight'      => 'Ponderación',

        // Campos — UNIS Inicio
        'field_docente'     => 'Docente',
        'field_silabo'      => 'Sílabo',
        'field_ruta'        => 'Ruta',
        'field_activity'    => 'Actividad',
        'field_weight_pct'  => 'Ponderación %',
        'field_url_help'    => 'URL del recurso',

        // Unidad — Título (UNIS)
        'titulo'            => 'Título',
        'field_unidad_num'  => 'Unidad',
        'field_nombre'      => 'Nombre',
        'field_nombre_help' => 'Nombre completo de la unidad. Use #texto# para cursiva.',

        // Unidad — Audio (UNIS)
        'audio'                    => 'Audio',
        'field_audio'              => 'Audio',
        'field_audio_help'         => 'URL del recurso de audio',
        'field_transcripcion'      => 'Transcripción',
        'field_transcripcion_help' => 'Cada salto de línea será un párrafo. Use #...# para cursiva y *...* para negrilla.',

        // Unidad — Temario (UNIS)
        'temario'      => 'Temario',
        'field_imagen' => 'Imagen',

        // Unidad — Resultado de aprendizaje (UNIS)
        'aprendizaje'             => 'Resultado de aprendizaje',
        'field_aprendizaje'       => 'Aprendizaje',
        'field_aprendizaje_help'  => 'Un resultado por línea. Use #...# para cursiva y *...* para negrilla.',

        // Unidad — Glosario (UNIS)
        'glosario'              => 'Glosario',
        'field_destacados'      => 'Destacados',
        'field_destacados_help' => 'Palabras o frases en cursiva, separadas por coma. Se respetan mayúsculas (fast ≠ Fast).',
        'field_glosario'        => 'Glosario',
        'field_glosario_help'   => 'Un par por línea: Término:Definición o Término&Definición. Use *...* para negrilla. Los destacados se aplican automáticamente como cursiva.',

        // Unidad — Recursos (UNIS)
        'recursos'                    => 'Recursos',
        'field_recursos_academicos'   => 'Recursos Académicos',
        'field_conoce_mas'            => 'Conoce +',
        'field_recursos_help'         => 'Inicie con [web], [pdf], [audio] o [video], luego la cita y la URL al final. Separe recursos con una línea vacía. Use #...# para cursiva y *...* para negrilla.',

        // Unidad — Multimedia y Takeaway (UNIS)
        'multimedia_takeaway'   => 'Multimedia y Takeaway',
        'field_multimedia'      => 'Multimedia',
        'field_multimedia_help' => 'Nombre del recurso multimedia. Use #texto# para cursiva.',
        'field_takeaway'        => 'Takeaway',

        // Ayuda de formato (compartida)
        'field_format_help'              => 'Use #...# para cursiva y *...* para negrilla.',
        'field_glosario_destacados_help' => 'Palabras o frases separadas por coma que se resaltarán en cursiva dentro de los términos.',
        'field_glosario_terminos_help'   => 'Un par por línea con formato Término:Definición o Término&amp;Definición. Use *...* para negrilla.',

        // Inicio — Banner (UNAB)
        'unab_banner'               => 'Banner',
        'unab_banner_desc'          => 'Datos generales del curso que se muestran en el banner.',
        'field_unab_banner'         => 'Url banner',
        'field_unab_nombre_curso'       => 'Nombre del diplomado',
        'field_unab_nombre_curso_help'  => 'No incluya "Diplomado en", la plantilla ya lo agrega.',
        'field_unab_facultad'       => 'Facultad',

        // Inicio — Generalidades (UNAB)
        'unab_generalidades'        => 'Generalidades',
        'unab_generalidades_desc'   => 'Indica en cada campo la URL que le corresponda. El campo se mostrará con borde <strong class="text-success">verde</strong> si la URL es válida o <strong class="text-danger">rojo</strong> si no lo es.',
        'field_unab_video'          => 'Url video de bienvenida',
        'field_unab_generalidades'  => 'Url generalidades',
        'field_unab_ruta'           => 'Url ruta',
        'field_unab_evaluacion'     => 'Url evaluación',

        // Inicio — Conozcámonos (UNAB)
        'unab_conozcamonos'             => 'Conozcámonos',
        'unab_conozcamonos_desc'        => 'Active cada docente con el interruptor para habilitar sus campos.',
        'field_unab_docente_nombre'     => 'Nombre',
        'field_unab_docente_foto'       => 'Url foto',
        'field_unab_docente_semblanza'  => 'Url semblanza',
        'unab_docente_n'                => 'Docente',

        // UNAB — Pestaña Curso
        'unab_curso_banner'                 => 'Banner',
        'unab_curso_banner_desc'            => 'Imagen principal y datos del curso.',
        'field_unab_curso_nombre_tema'      => 'Nombre del tema',
        'field_unab_curso_codigo'           => 'Código del curso',
        'field_unab_curso_imagen_banner'    => 'Url imagen del banner',
        'unab_curso_generalidades'          => 'Generalidades',
        'unab_curso_generalidades_desc'     => 'Descripción, resultados de aprendizaje y recursos audiovisuales del curso.',
        'field_unab_curso_descripcion'      => 'Descripción',
        'field_unab_curso_descripcion_help' => 'Cada párrafo de texto se convierte en un bloque separado.',
        'field_unab_curso_resultados'       => 'Resultados de aprendizaje',
        'field_unab_curso_resultados_help'  => 'Cada línea se convierte en un ítem de lista.',
        'field_unab_curso_syllabus'         => 'Url del Syllabus',
        'field_unab_curso_video_descripcion'  => 'Url del pódcast',
        'field_unab_curso_video_introduccion' => 'Url del video de introducción',
        'unab_aprende'                      => 'Aprende',
        'unab_aprende_desc'                 => 'Recursos interactivos por unidad.',
        'unab_unidad_n'                     => 'Unidad',
        'field_unab_curso_tipo_recurso'     => 'Tipo de recurso',
        'field_unab_curso_recurso'          => 'Url del recurso',
        'option_menu_interactivo'           => 'Menú interactivo',
        'option_pagina_interactiva'         => 'Página interactiva',
        'option_articulo'                   => 'Artículo',
        'unab_glosario'                     => 'Glosario',
        'unab_glosario_desc'                => 'Términos y definiciones destacadas del curso.',
        'field_unab_curso_destacados'       => 'Destacados',
        'field_unab_curso_destacados_help'  => 'Palabras separadas por coma. Se resaltarán en glosario.',
        'field_unab_curso_terminos'         => 'Términos',
        'field_unab_glosario_help'          => 'Una entrada por línea. Use & o : para separar término y definición.',
        'unab_recursos'                     => 'Recursos',
        'unab_recursos_desc'                => 'Recursos académicos de consulta obligatoria y opcional.',
        'field_unab_curso_obligatorios'     => 'Obligatorios',
        'field_unab_curso_opcionales'       => 'Opcionales',
        'field_unab_recursos_help'          => 'Escriba la cita y la URL al final. Separe recursos con una línea vacía. Use #...# para texto sin negrilla.',

        // Mensajes JavaScript
        'js' => [
            'select_lms'        => 'Seleccione una plataforma (Moodle o Canvas) antes de procesar',
            'no_active_section' => 'No hay sección activa para procesar',
            'no_section_name'   => 'No se pudo determinar la sección activa',
            'no_data'           => 'No hay datos para procesar',
            'success_generated' => 'Contenido generado para {lms} exitosamente',
            'error_process'     => 'Error al procesar',
            'error_connection'  => 'Error de conexión al procesar',
            'confirm_clean'     => '¿Está seguro que desea limpiar todos los campos?',
            'success_clean'     => 'Formulario limpiado correctamente',
            'panel_title_inicio' => 'Resultado — Inicio',
            'panel_title_unidad' => 'Resultado — Unidad',
            'panel_title_curso'  => 'Resultado — Curso',
            'btn_copy'           => 'Copiar contenido',
            'btn_copied'         => '¡Copiado!',
            'warnings_title'     => 'Advertencias',
            'empty_field'        => 'vacío',
            'invalid_url'        => 'URL no válida',
            'eval_incomplete'    => 'Evaluación incompleta. Campos vacíos',
        ],
    ],
];
