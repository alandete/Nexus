<?php
/**
 * S4Learning - English Translations (Alliances)
 */
defined('APP_ACCESS') or die('Direct access not allowed');

return [
    'alliances' => [
        'page_title'             => 'Educational Alliances',
        'lms_label'              => 'LMS:',
        'btn_clean'              => 'Clear',
        'btn_process'            => 'Process',
        'btn_processing'         => 'Processing...',
        'lms_hint'               => 'Select a platform to view the course fields.',
        'lms_unavailable_notice' => 'Content for the selected LMS is not yet available for this alliance. Select <strong>Moodle</strong> to continue working.',

        // Alliance submenu
        'unis'                          => 'UNIS',
        'unab'                          => 'UNAB',
        'panamericana'                  => 'Panamericana',
        'central'                       => 'Central',
        'formacion_docente'             => 'Teacher Training',
        'coming_soon_unab'              => 'UNAB content coming soon...',
        'coming_soon_panamericana'      => 'Panamericana content coming soon...',
        'coming_soon_central'           => 'Central content coming soon...',
        'coming_soon_formacion_docente' => 'Teacher Training content coming soon...',

        // Internal tabs
        'tab_inicio'  => 'Overview',
        'tab_unidad'  => 'Unit',
        'tab_curso'   => 'Course',

        // Overview — General Information (UNIS)
        'generalidades'      => 'General Information',
        'generalidades_desc' => 'Enter the corresponding URL in each field. The field will show a <strong class="text-success">green</strong> border if the URL is valid or <strong class="text-danger">red</strong> if it is not.',

        // Overview — Evaluation (UNIS)
        'evaluacion'      => 'Evaluation',
        'evaluacion_desc' => 'Enter the activity name and weighting for each unit according to the course evaluation document. In activity names, use #...# for italics.',
        'col_unit'        => 'Unit',
        'col_activity'    => 'Activity',
        'col_weight'      => 'Weight',

        // Fields — UNIS Overview
        'field_docente'     => 'Instructor',
        'field_silabo'      => 'Syllabus',
        'field_ruta'        => 'Path',
        'field_activity'    => 'Activity',
        'field_weight_pct'  => 'Weight %',
        'field_url_help'    => 'Resource URL',

        // Unit — Title (UNIS)
        'titulo'            => 'Title',
        'field_unidad_num'  => 'Unit',
        'field_nombre'      => 'Name',
        'field_nombre_help' => 'Full unit name. Use #text# for italics.',

        // Unit — Audio (UNIS)
        'audio'                    => 'Audio',
        'field_audio'              => 'Audio',
        'field_audio_help'         => 'Audio resource URL',
        'field_transcripcion'      => 'Transcript',
        'field_transcripcion_help' => 'Each line break creates a paragraph. Use #...# for italics and *...* for bold.',

        // Unit — Syllabus (UNIS)
        'temario'      => 'Syllabus',
        'field_imagen' => 'Image',

        // Unit — Learning Outcome (UNIS)
        'aprendizaje'            => 'Learning Outcome',
        'field_aprendizaje'      => 'Outcome',
        'field_aprendizaje_help' => 'One outcome per line. Use #...# for italics and *...* for bold.',

        // Unit — Glossary (UNIS)
        'glosario'              => 'Glossary',
        'field_destacados'      => 'Highlights',
        'field_destacados_help' => 'Words or phrases in italics, separated by commas. Case-sensitive (fast ≠ Fast).',
        'field_glosario'        => 'Glossary',
        'field_glosario_help'   => 'One pair per line: Term:Definition or Term&Definition. Use *...* for bold. Highlights are auto-applied as italics.',

        // Unit — Resources (UNIS)
        'recursos'                    => 'Resources',
        'field_recursos_academicos'   => 'Academic Resources',
        'field_conoce_mas'            => 'Learn More',
        'field_recursos_help'         => 'Start with [web], [pdf], [audio] or [video], then the citation and URL at the end. Separate resources with a blank line. Use #...# for italics and *...* for bold.',

        // Unit — Multimedia & Takeaway (UNIS)
        'multimedia_takeaway'   => 'Multimedia & Takeaway',
        'field_multimedia'      => 'Multimedia',
        'field_multimedia_help' => 'Multimedia resource name. Use #text# for italics.',
        'field_takeaway'        => 'Takeaway',

        // Format help (shared)
        'field_format_help'              => 'Use #...# for italics and *...* for bold.',
        'field_glosario_destacados_help' => 'Words or phrases separated by comma that will be italicized within the terms.',
        'field_glosario_terminos_help'   => 'One pair per line as Term:Definition or Term&Definition. Use *...* for bold.',

        // Overview — Banner (UNAB)
        'unab_banner'               => 'Banner',
        'unab_banner_desc'          => 'General course information displayed in the banner.',
        'field_unab_banner'         => 'Banner URL',
        'field_unab_nombre_curso'       => 'Diploma name',
        'field_unab_nombre_curso_help'  => 'Do not include "Diplomado en", the template already adds it.',
        'field_unab_facultad'       => 'Faculty',

        // Overview — General Information (UNAB)
        'unab_generalidades'        => 'General Information',
        'unab_generalidades_desc'   => 'Enter the corresponding URL in each field. The field will show a <strong class="text-success">green</strong> border if the URL is valid or <strong class="text-danger">red</strong> if it is not.',
        'field_unab_video'          => 'Welcome video URL',
        'field_unab_generalidades'  => 'General information URL',
        'field_unab_ruta'           => 'Path URL',
        'field_unab_evaluacion'     => 'Evaluation URL',

        // Overview — Meet Us (UNAB)
        'unab_conozcamonos'             => 'Meet Us',
        'unab_conozcamonos_desc'        => 'Toggle each instructor switch to enable their fields.',
        'field_unab_docente_nombre'     => 'Name',
        'field_unab_docente_foto'       => 'Photo URL',
        'field_unab_docente_semblanza'  => 'Bio URL',
        'unab_docente_n'                => 'Instructor',

        // UNAB — Course tab
        'unab_curso_banner'                 => 'Banner',
        'unab_curso_banner_desc'            => 'Main image and course data.',
        'field_unab_curso_nombre_tema'      => 'Topic name',
        'field_unab_curso_codigo'           => 'Course code',
        'field_unab_curso_imagen_banner'    => 'Banner image URL',
        'unab_curso_generalidades'          => 'General Information',
        'unab_curso_generalidades_desc'     => 'Description, learning outcomes and audiovisual resources.',
        'field_unab_curso_descripcion'      => 'Description',
        'field_unab_curso_descripcion_help' => 'Each paragraph becomes a separate block.',
        'field_unab_curso_resultados'       => 'Learning outcomes',
        'field_unab_curso_resultados_help'  => 'Each line becomes a list item.',
        'field_unab_curso_syllabus'         => 'Syllabus URL',
        'field_unab_curso_video_descripcion'  => 'Podcast URL',
        'field_unab_curso_video_introduccion' => 'Introduction video URL',
        'unab_aprende'                      => 'Learn',
        'unab_aprende_desc'                 => 'Interactive resources by unit.',
        'unab_unidad_n'                     => 'Unit',
        'field_unab_curso_tipo_recurso'     => 'Resource type',
        'field_unab_curso_recurso'          => 'Resource URL',
        'option_menu_interactivo'           => 'Interactive menu',
        'option_pagina_interactiva'         => 'Interactive page',
        'option_articulo'                   => 'Article',
        'unab_glosario'                     => 'Glossary',
        'unab_glosario_desc'                => 'Highlighted terms and definitions.',
        'field_unab_curso_destacados'       => 'Highlights',
        'field_unab_curso_destacados_help'  => 'Comma-separated words. They will be highlighted in the glossary.',
        'field_unab_curso_terminos'         => 'Terms',
        'field_unab_glosario_help'          => 'One entry per line. Use & or : to separate term and definition.',
        'unab_recursos'                     => 'Resources',
        'unab_recursos_desc'                => 'Required and optional academic reference resources.',
        'field_unab_curso_obligatorios'     => 'Required',
        'field_unab_curso_opcionales'       => 'Optional',
        'field_unab_recursos_help'          => 'Write the citation and the URL at the end. Separate resources with a blank line. Use #...# for non-bold text.',

        // JavaScript messages
        'js' => [
            'select_lms'        => 'Select a platform (Moodle or Canvas) before processing',
            'no_active_section' => 'No active section to process',
            'no_section_name'   => 'Could not determine the active section',
            'no_data'           => 'No data to process',
            'success_generated' => 'Content generated for {lms} successfully',
            'error_process'     => 'Error while processing',
            'error_connection'  => 'Connection error while processing',
            'confirm_clean'     => 'Are you sure you want to clear all fields?',
            'success_clean'     => 'Form cleared successfully',
            'panel_title_inicio' => 'Result — Overview',
            'panel_title_unidad' => 'Result — Unit',
            'panel_title_curso'  => 'Result — Course',
            'btn_copy'           => 'Copy content',
            'btn_copied'         => 'Copied!',
            'warnings_title'     => 'Warnings',
            'empty_field'        => 'empty',
            'invalid_url'        => 'Invalid URL',
            'eval_incomplete'    => 'Evaluation incomplete. Empty fields',
        ],
    ],
];
