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
        'no_alliance' => 'Sin alianza',
        'no_tags'     => 'Sin etiquetas',

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

        // Listado (sub-fase 4.2)
        'list_title'   => 'Listado de tareas',
        'tabs_label'   => 'Vistas del listado',
        'tab_active'   => 'Activas',
        'tab_scheduled'=> 'Proximas',
        'tab_history'  => 'Historial',

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

        'btn_resume'   => 'Reanudar',
        'is_running'   => 'Corriendo',
        'total_time'   => 'Tiempo acumulado',
        'err_already_running' => 'Hay un cronometro corriendo. Pausalo o completalo primero.',

        // Placeholder para sub-fases siguientes
        'upcoming_placeholder_title' => 'Kanban y reportes',
        'upcoming_placeholder_desc'  => 'Las siguientes sub-fases traen: kanban de prioridades, reportes (PDF/Excel/CSV) y dashboard de vencimientos.',
    ],
];
