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
        'btn_export_tags'         => 'Exportar',
        'btn_import_tags'         => 'Importar',
        'import_tags_confirm'          => '¿Importar etiquetas desde este archivo?',
        'import_tags_confirm_has'      => 'Ya hay',
        'import_tags_confirm_existing' => 'etiqueta(s) en el sistema. Las que coincidan se actualizarán y las nuevas se agregarán. ¿Continuar?',

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
        'import_desc'        => 'Sube un archivo CSV en formato Nexus o Clockify (Reports → Detailed → Export CSV). Las entradas con el mismo inicio para la misma tarea se omiten automáticamente para evitar duplicados.',
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

        // ─── Limpieza ───
        'cleanup_title' => 'Limpieza de datos',
        'cleanup_desc'  => 'Elimina tareas antiguas o completadas en bloque para mantener la base de datos ligera.',

        'cleanup_selective_title' => 'Limpieza selectiva',
        'cleanup_selective_desc'  => 'Aplica filtros para seleccionar qué tareas eliminar. Usa Vista previa para confirmar la cantidad antes de borrar.',

        'cleanup_filter_alliance'   => 'Alianza',
        'cleanup_all_alliances'     => 'Todas las alianzas',
        'cleanup_filter_status'     => 'Estado',
        'cleanup_status_in_progress' => 'En progreso',
        'cleanup_status_paused'      => 'Pausadas',
        'cleanup_status_completed'   => 'Completadas',
        'cleanup_status_cancelled'   => 'Canceladas',
        'cleanup_status_no_activity' => 'Pendientes sin actividad',
        'cleanup_filter_date'       => 'Anterior a',
        'cleanup_filter_date_hint'  => 'Filtra por fecha de la última actividad registrada.',

        'cleanup_count_tasks'   => 'tareas',
        'cleanup_count_entries' => 'entradas',

        'cleanup_btn_preview' => 'Calcular',
        'cleanup_btn_execute' => 'Eliminar selección',

        'cleanup_preview_result' => '{tasks} tareas y {entries} entradas serán eliminadas.',
        'cleanup_preview_empty'  => 'Ninguna tarea coincide con los filtros seleccionados.',

        'cleanup_confirm_title'  => 'Confirmar limpieza',
        'cleanup_confirm_undo'   => 'Esta acción no se puede deshacer.',
        'cleanup_err_no_status'  => 'Selecciona al menos un estado.',
        'cleanup_success'        => '{n} tareas eliminadas.',

        'cleanup_dupes_title'      => 'Entradas duplicadas',
        'cleanup_dupes_desc'       => 'Detecta entradas de tiempo con el mismo inicio para la misma tarea, generadas por importaciones repetidas.',
        'cleanup_dupes_btn_detect' => 'Detectar duplicados',
        'cleanup_dupes_btn_fix'    => 'Eliminar duplicados',
        'cleanup_dupes_none'       => 'No se encontraron duplicados.',
        'cleanup_dupes_found'      => '{n} entradas duplicadas encontradas.',
        'cleanup_dupes_confirm'    => '¿Eliminar {n} entradas duplicadas? Se conservará la entrada más antigua de cada grupo.',
        'cleanup_dupes_success'    => '{n} entradas duplicadas eliminadas.',

        'cleanup_nuke_title' => 'Eliminar todo',
        'cleanup_nuke_desc'  => 'Elimina absolutamente todas las tareas y entradas de tiempo de tu cuenta. Esta operacion es irreversible.',
        'cleanup_nuke_btn'   => 'Eliminar todo',

        'cleanup_nuke_confirm_title'  => '¿Eliminar todas las tareas?',
        'cleanup_nuke_confirm_msg'    => 'Se eliminarán TODAS las tareas y entradas de tiempo. No hay vuelta atrás.',
        'cleanup_nuke_confirm2_title' => '¿Estás completamente seguro?',
        'cleanup_nuke_confirm2_msg'   => 'Esta es la confirmación final. No podrás recuperar ningún dato.',
        'cleanup_nuke_confirm_btn'    => 'Sí, eliminar todo',
        'cleanup_nuke_confirm2_btn'   => 'Eliminar permanentemente',
    ],
];
