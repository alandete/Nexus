<?php
/**
 * Nexus 2.0 — Traducciones Espanol (Tareas)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'tasks' => [
        'page_title'    => 'Tareas',
        'page_subtitle' => 'Registra tu tiempo, organiza tareas y mantente al dia con tus entregas.',

        // Tracker (sub-fase 4.1)
        'tracker_title'       => 'Cronometro',
        'tracker_empty_title' => 'Sin cronometro activo',
        'tracker_empty_desc'  => 'Inicia el cronometro cuando empieces a trabajar. Podras pausarlo o completar la tarea cuando termines.',
        'elapsed_time'        => 'Tiempo transcurrido',

        // Input inline (friccion-cero)
        'input_placeholder'   => 'En que vas a trabajar? Escribe y presiona Enter...',
        'input_label'         => 'Nombre de la tarea',
        'autocomplete_label'  => 'Sugerencias de tareas existentes',

        // Botones
        'btn_start_timer'     => 'Iniciar cronometro',
        'btn_start'           => 'Iniciar',
        'btn_pause'           => 'Pausar',
        'btn_stop'            => 'Completar',
        'btn_edit'            => 'Editar',
        'btn_discard'         => 'Descartar',
        'btn_discard_confirm' => 'Descartar cronometro',
        'btn_add_tag'         => 'Agregar',
        'btn_complete_data'   => 'Completar informacion',
        'btn_delete'          => 'Eliminar',
        'btn_new_task'        => 'Nueva tarea',
        'btn_create'          => 'Crear tarea',
        'create_task_title'   => 'Nueva tarea programada',
        'task_created'        => 'Tarea creada.',
        'err_create'          => 'No se pudo crear la tarea.',
        'view_detail'         => 'Ver detalle',
        'no_description'      => 'Sin descripcion',
        'no_due_date'         => 'Sin fecha',
        'no_sessions_yet'     => 'Sin sesiones',
        'no_entries_yet'      => 'Aun no hay registros cerrados.',
        'entries_title'       => 'Registros',
        'entry_start'         => 'Inicio',
        'entry_end'           => 'Fin',
        'field_vencimiento'   => 'Vencimiento',
        'field_since'         => 'Desde',
        'entry_updated'       => 'Registro actualizado.',
        'entry_deleted'       => 'Registro eliminado.',
        'entry_err_required'  => 'Completa hora de inicio y fin.',
        'entry_err_order'     => 'La hora de fin debe ser posterior al inicio.',
        'entry_err_overlap'   => 'Se solapa con otro registro del dia ({task}, {start}-{end})',
        'entry_err_update'    => 'No se pudo actualizar el registro.',
        'entry_err_delete'    => 'No se pudo eliminar el registro.',
        'entry_delete_title'   => 'Eliminar registro',
        'entry_delete_message' => 'Este registro de tiempo se eliminara y el total de la tarea se recalculara. Esta accion no se puede deshacer.',
        'tags_select_placeholder' => 'Seleccionar etiquetas...',
        'field_total_time'        => 'Tiempo acumulado',
        'field_status'            => 'Estado',
        'status_delivered_ontime' => 'Entregada a tiempo',
        'status_delivered_late'   => '{n} dias de retraso',
        'status_on_schedule'      => 'En plazo',
        'status_overdue_days'     => 'Vencida hace {n} dias',

        // Campos
        'field_task'         => 'Tarea',
        'field_description'  => 'Descripcion',
        'field_alliance'     => 'Alianza',
        'field_tags'         => 'Etiquetas',
        'field_priority'     => 'Prioridad',
        'field_due_date'     => 'Fecha de vencimiento',

        // Prioridades
        'priority_low'    => 'Baja',
        'priority_medium' => 'Media',
        'priority_high'   => 'Alta',
        'priority_urgent' => 'Urgente',

        // Estados
        'status_pending'     => 'Pendiente',
        'status_in_progress' => 'En progreso',
        'status_paused'      => 'Pausada',
        'status_completed'   => 'Completada',

        // Formulario
        'start_title'          => 'Iniciar cronometro',
        'edit_task_title'      => 'Editar tarea',
        'complete_data_title'  => 'Completa la informacion',
        'task_placeholder'     => 'Describe lo que vas a trabajar...',
        'task_help'            => 'Si ya existe una tarea con ese nombre, se reanuda. Si no, se crea una nueva.',
        'description_placeholder' => 'Agrega detalles, notas o contexto adicional...',
        'alliance_placeholder' => 'Seleccionar alianza...',
        'tag_new_placeholder'  => 'Nueva etiqueta...',
        'no_tags_yet'          => 'Aun no hay etiquetas. Crea la primera:',
        'due_date_help'        => 'Opcional. Si se define, la tarea aparecera en el dashboard de vencimientos.',
        'start_hint'           => 'El cronometro empieza al confirmar. Puedes editar, pausar o completar desde los botones principales.',
        'incomplete_msg'       => 'Agrega {fields} para poder pausar o completar.',
        'force_complete_msg'   => 'Completa la alianza y etiquetas antes de pausar o completar la tarea.',

        // Estados sin valor
        'no_alliance'   => 'Sin alianza',
        'no_tags'       => 'Sin etiquetas',
        'tag_singular'  => 'etiqueta',
        'tag_plural'    => 'etiquetas',

        // Acciones de confirmacion
        'stop_title'      => 'Completar tarea',
        'stop_message'    => 'Se guardara el tiempo registrado y la tarea se marcara como completada.',
        'discard_title'   => 'Descartar cronometro',
        'discard_message' => 'Se eliminara el tiempo registrado sin guardar. Esta accion no se puede deshacer.',

        // Mensajes
        'timer_started'   => 'Cronometro iniciado.',
        'timer_paused'    => 'Cronometro pausado ({duration})',
        'timer_stopped'   => 'Tarea completada ({duration})',
        'timer_discarded' => 'Cronometro descartado.',
        'task_updated'    => 'Cambios guardados.',
        'starting'        => 'Iniciando...',

        // Validacion
        'err_title'          => 'El nombre de la tarea es obligatorio.',
        'err_title_required' => 'Escribe un nombre para la tarea antes de iniciar.',
        'err_alliance'       => 'Selecciona una alianza.',
        'err_tags'           => 'Selecciona al menos una etiqueta.',
        'err_start'          => 'No se pudo iniciar el cronometro.',
        'err_pause'          => 'No se pudo pausar.',
        'err_stop'           => 'No se pudo completar la tarea.',
        'err_discard'        => 'No se pudo descartar.',
        'err_update'         => 'No se pudieron guardar los cambios.',
        'err_create_tag'     => 'No se pudo crear la etiqueta.',
        'err_delete'         => 'No se pudo eliminar la tarea.',
        'task_deleted'       => 'Tarea eliminada.',
        'delete_title'       => 'Eliminar tarea',
        'delete_message'     => 'Se eliminara la tarea "{title}" y todas sus entradas de tiempo. Esta accion no se puede deshacer.',

        // Listado (sub-fase 4.2)
        'list_title'   => 'Listado de tareas',
        'tabs_label'   => 'Vistas del listado',
        'tab_active'   => 'Tareas activas',
        'tab_scheduled'=> 'Proximas tareas',
        'tab_today'    => 'Tareas de hoy',
        'tab_yesterday'=> 'Tareas de ayer',
        'tab_history'  => 'Historial',

        'col_alliance'   => 'Alianza',
        'col_task'       => 'Tarea',
        'col_status'     => 'Estado',
        'col_tags'       => 'Etiquetas',
        'col_total_time' => 'Tiempo',
        'col_start_time' => 'Hora',
        'entry_count_hint' => 'Numero de registros',
        'is_overdue'       => 'Vencida',

        'filters_label'            => 'Filtros del listado',
        'filter_search_placeholder'=> 'Buscar por titulo o alianza...',
        'filter_search_label'      => 'Buscar tarea',
        'filter_date_from'         => 'Desde',
        'filter_date_to'           => 'Hasta',
        'filter_alliance'          => 'Filtrar por alianza',
        'filter_all_alliances'     => 'Todas las alianzas',
        'filter_priority'          => 'Filtrar por prioridad',
        'filter_all_priorities'    => 'Todas las prioridades',
        'filter_tag'               => 'Filtrar por etiqueta',
        'filter_all_tags'          => 'Todas las etiquetas',
        'filter_clear'             => 'Limpiar',

        'empty_active_title'     => 'No hay tareas activas',
        'empty_active_desc'      => 'Aqui apareceran las tareas en progreso o pausadas. Inicia un cronometro para comenzar.',
        'empty_scheduled_title'  => 'No hay tareas proximas',
        'empty_scheduled_desc'   => 'Aqui apareceran las tareas pendientes sin tiempo registrado, ordenadas por prioridad y fecha de vencimiento.',
        'empty_history_title'    => 'Sin historial en este rango',
        'empty_history_desc'     => 'Ajusta las fechas o empieza a registrar tiempo para ver el historial aqui.',
        'history_page_info'      => 'Pagina {page} de {pages}',
        'pagination_prev'        => 'Anterior',
        'pagination_next'        => 'Siguiente',
        'empty_today_title'      => 'Sin actividad hoy aun',
        'empty_today_desc'       => 'Las tareas que completes o pauses hoy apareceran aqui.',
        'empty_yesterday_title'  => 'No hubo actividad ayer',
        'empty_yesterday_desc'   => 'Aqui apareceran las tareas en las que trabajaste ayer, con total de tiempo y numero de sesiones.',

        'btn_resume'   => 'Reanudar',
        'is_running'   => 'Corriendo',
        'total_time'   => 'Tiempo acumulado',
        'err_already_running' => 'Hay un cronometro corriendo. Pausalo o completalo primero.',

        // Placeholder para sub-fases siguientes
        'upcoming_placeholder_title' => 'Kanban y reportes',
        'upcoming_placeholder_desc'  => 'Las siguientes sub-fases traen: kanban de prioridades, reportes (PDF/Excel/CSV) y dashboard de vencimientos.',
    ],
];
