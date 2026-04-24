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
        'tabs_label'              => 'Secciones de administración',
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
        'field_color_hex'  => 'Código hex',
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
        'delete_tag_warn_undo' => 'Esta acción no se puede deshacer.',

        // ─── Importar / Exportar ───
        'io_title' => 'Importar y exportar',
        'io_desc'  => 'Transfiere entradas de tiempo entre entornos o importa desde Clockify.',

        // Exportar
        'export_title'        => 'Exportar',
        'export_desc'         => 'Descarga las entradas de tiempo en formato Nexus (para reimportar) o Clockify (para cargar en Clockify).',
        'export_range_label'  => 'Rango',
        'export_format_label' => 'Formato',
        'export_btn'          => 'Descargar',

        // Rangos (compartidos export/import)
        'range_today'     => 'Hoy',
        'range_week'      => 'Esta semana',
        'range_month'     => 'Mes actual',
        'range_last_month'=> 'Mes anterior',
        'range_custom'    => 'Personalizado',
        'range_from'      => 'Desde',
        'range_to'        => 'Hasta',

        // Importar
        'import_title'       => 'Importar',
        'import_desc'        => 'Sube un archivo CSV en formato Nexus o Clockify (Reports → Detailed → Export CSV).',
        'import_format_label'=> 'Formato del archivo',

        'import_drop_aria'          => 'Zona de carga de archivo CSV',
        'import_drop_text'          => 'Arrastra un archivo CSV aquí o haz clic para seleccionar',
        'import_drop_hint_nexus'    => 'Formato: Alianza, Tarea, Etiquetas, Fecha inicio, Hora inicio, Fecha fin, Hora fin',
        'import_drop_hint_clockify' => 'Clockify: Reports → Detailed → Export CSV',

        'import_unknown_alliances'      => 'Alianzas no reconocidas',
        'import_unknown_alliances_desc' => 'Selecciona una alianza existente o descarta las entradas de esa alianza.',
        'import_unknown_tags'           => 'Etiquetas no reconocidas',
        'import_unknown_tags_desc'      => 'Selecciona una etiqueta existente o crea una nueva.',

        'import_preview_title' => 'Vista previa',

        'import_btn'         => 'Importar entradas',
        'import_map_discard' => 'Descartar entradas',
        'import_map_create'  => 'Crear nueva',

        'import_stat_entries'   => 'entradas',
        'import_stat_tasks'     => 'tareas',
        'import_stat_alliances' => 'alianzas',
        'import_stat_tags'      => 'etiquetas',

        'import_success'       => '{n} entradas importadas.',
        'import_success_skip_dup'  => '{n} duplicadas omitidas.',
        'import_success_skip_over' => '{n} con solapamiento omitidas.',
        'import_err_parse'     => 'No se pudo leer el archivo. Verifica que sea un CSV válido.',
        'import_err_empty'     => 'El archivo no contiene entradas válidas.',

        // ─── Limpieza (placeholder) ───
        'cleanup_title' => 'Limpieza de datos',
        'cleanup_desc'  => 'Elimina tareas antiguas o completadas en bloque para mantener la base de datos ligera.',
        'cleanup_placeholder_title' => 'Próximamente',
        'cleanup_placeholder_desc'  => 'Las operaciones de limpieza en bloque se habilitaran en una sub-fase próxima.',
    ],
];
