<?php
/**
 * Nexus 2.0 — Traducciones Espanol (Administrar tareas)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'manage_tasks' => [
        'page_title'    => 'Administrar tareas',
        'page_subtitle' => 'Gestiona las etiquetas, importa o exporta tareas y realiza mantenimiento de los datos.',

        // ─── Etiquetas ───
        'tabs_label'              => 'Secciones de administracion',
        'tags_title'              => 'Etiquetas',
        'tags_desc'               => 'Organiza las tareas con etiquetas personalizadas. Una tarea puede tener varias etiquetas.',
        'new_tag_placeholder'     => 'Nueva etiqueta...',

        'stat_total'  => 'Total',
        'stat_in_use' => 'En uso',
        'stat_unused' => 'Sin uso',

        'col_name'  => 'Nombre',
        'col_color' => 'Color',
        'col_usage' => 'Uso',

        'task_singular' => 'tarea',
        'task_plural'   => 'tareas',
        'not_in_use'    => 'Sin uso',

        'empty_tags_title' => 'Aun no hay etiquetas',
        'empty_tags_desc'  => 'Crea la primera etiqueta para empezar a categorizar tus tareas.',
        'no_results_title' => 'Sin coincidencias',
        'no_results_desc'  => 'Ninguna etiqueta coincide con la busqueda.',

        'create_tag_title' => 'Nueva etiqueta',
        'edit_tag_title'   => 'Editar etiqueta',
        'field_name'       => 'Nombre',
        'field_color'      => 'Color',
        'field_color_hex'  => 'Codigo hex',
        'color_help'       => 'Color identificador de la etiqueta. Debe ser un hex valido (#RRGGBB).',

        'tag_created'  => 'Etiqueta creada.',
        'tag_updated'  => 'Etiqueta actualizada.',
        'tag_deleted'  => 'Etiqueta eliminada.',

        'err_name_required' => 'El nombre es obligatorio.',
        'err_save_tag'      => 'No se pudo guardar la etiqueta.',
        'err_delete_tag'    => 'No se pudo eliminar la etiqueta.',

        'delete_tag_title'     => 'Eliminar etiqueta',
        'delete_tag_message'   => 'La etiqueta "{name}" se eliminara.',
        'delete_tag_warn_usage'=> 'Esta asignada a {n} {label} — se quitara de todas ellas.',
        'delete_tag_warn_undo' => 'Esta accion no se puede deshacer.',

        // ─── Importar / Exportar (placeholder) ───
        'io_title' => 'Importar y exportar',
        'io_desc'  => 'Transfiere tareas entre entornos, importa desde hojas de calculo o genera copias en JSON/CSV/Excel.',
        'io_placeholder_title' => 'Proximamente',
        'io_placeholder_desc'  => 'La importacion y exportacion de tareas se habilitara en una sub-fase proxima.',

        // ─── Limpieza (placeholder) ───
        'cleanup_title' => 'Limpieza de datos',
        'cleanup_desc'  => 'Elimina tareas antiguas o completadas en bloque para mantener la base de datos ligera.',
        'cleanup_placeholder_title' => 'Proximamente',
        'cleanup_placeholder_desc'  => 'Las operaciones de limpieza en bloque se habilitaran en una sub-fase proxima.',
    ],
];
