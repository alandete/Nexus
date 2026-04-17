<?php
/**
 * NexusApp - Traducciones Español (Tareas)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'tasks' => [
        'page_title'       => 'Tareas',
        'btn_new'          => 'Nueva tarea',
        'btn_save'         => 'Guardar',
        'btn_cancel'       => 'Cancelar',
        'btn_delete'       => 'Eliminar',
        'btn_log_time'     => 'Registrar tiempo',
        'btn_start'        => 'Iniciar',
        'btn_stop'         => 'Detener',
        'btn_pause'        => 'Pausar',
        'btn_discard'      => 'Descartar tarea',
        'btn_schedule'     => 'Programar tarea',

        // Campos
        'field_title'       => 'Título',
        'field_description' => 'Descripción',
        'field_tags'        => 'Etiquetas',
        'field_tags_help'   => 'Separadas por coma. Ej: migración, unidad 3, canvas',
        'field_alliance'    => 'Alianza',
        'field_alliance_none' => 'Sin alianza',
        'field_due_date'    => 'Fecha de vencimiento',
        'field_priority'    => 'Prioridad',
        'field_status'      => 'Estado',
        'field_notes'       => 'Notas',
        'field_hours'       => 'Horas',
        'field_minutes'     => 'Minutos',
        'field_date'        => 'Fecha',

        // Prioridades
        'priority_low'     => 'Baja',
        'priority_medium'  => 'Media',
        'priority_high'    => 'Alta',
        'priority_urgent'  => 'Urgente',

        // Estados
        'status_pending'     => 'Pendiente',
        'status_in_progress' => 'En progreso',
        'status_paused'      => 'En pausa',
        'status_completed'   => 'Completada',
        'status_cancelled'   => 'Cancelada',

        // Filtros
        'filter_all'        => 'Todas',
        'filter_active'     => 'Activas',
        'filter_clear'      => 'Limpiar filtros',
        'filter_no_results' => 'No se encontraron tareas con los filtros seleccionados',

        // Timer
        'timer_title'       => 'Cronómetro',
        'timer_running'     => 'En curso',
        'timer_idle'        => 'Detenido',
        'timer_select_task' => 'Seleccione una tarea',
        'timer_placeholder' => '¿En qué estás trabajando?',
        'tracker_placeholder' => '¿En qué estás trabajando?',
        'scheduled_title'   => 'Próximas tareas',
        'active_tasks_title' => 'Tareas activas',
        'filter_prev_month' => 'Mes anterior',
        'filter_no_results' => 'No hay registros en el período seleccionado',
        'btn_load_more'     => 'Cargar más',
        'btn_reprogram'     => 'Reprogramar',
        'btn_report'        => 'Reportes',
        'report_title'      => 'Generar reporte',
        'report_desc'       => 'Genera un informe con el tiempo invertido en las tareas registradas.',
        'report_type'       => 'Tipo de informe',
        'report_summary'    => 'Resumido',
        'report_detailed'   => 'Detallado',
        'report_weekly'     => 'Semanal',
        'report_prev_month' => 'Mes anterior',
        'report_period'     => 'Período',
        'report_alliance'   => 'Alianza',
        'report_all'        => 'Todas las alianzas',
        'report_user'       => 'Usuario',
        'report_all_users'  => 'Todos los usuarios',
        'report_export'     => 'Exportar como',
        'report_excel'      => 'Excel',
        'report_csv'        => 'CSV',
        'report_pdf'        => 'PDF',
        'report_print'      => 'Imprimir',
        'report_generate'   => 'Generar',
        'report_quick_total' => 'Total del mes en curso',

        // Tabla
        'col_task'          => 'Tarea',
        'col_alliance'      => 'Alianza',
        'col_tags'          => 'Etiquetas',
        'col_due_date'      => 'Vencimiento',
        'col_priority'      => 'Prioridad',
        'col_status'        => 'Estado',
        'col_time'          => 'Tiempo',
        'col_actions'       => 'Acciones',

        // Mensajes
        'empty_state'       => 'No hay tareas registradas',
        'confirm_delete'    => '¿Eliminar esta tarea y todo su registro de tiempo?',
        'success_create'    => 'Tarea creada',
        'success_update'    => 'Tarea actualizada',
        'success_delete'    => 'Tarea eliminada',
        'error_title'       => 'El título es obligatorio',

        // Log manual
        'panel_log_time'    => 'Registrar tiempo manualmente',
        'panel_create'      => 'Nueva tarea',
        'panel_edit'        => 'Editar tarea',

        // Resumen
        'summary_today'     => 'Hoy',
        'summary_week'      => 'Esta semana',
        'summary_total'     => 'Total',

        // Etiquetas
        'manage_tags'       => 'Etiquetas',
        'tag_name'          => 'Nombre',
        'tag_color'         => 'Color',
    ],
];
