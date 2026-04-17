<?php
/**
 * Nexus 2.0 — Traducciones Español (Registro de actividad)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'activity' => [
        'page_title'    => 'Registro de actividad',
        'page_subtitle' => 'Historial completo de acciones realizadas en el sistema.',

        // Acciones principales
        'btn_clear'         => 'Vaciar registro',
        'btn_clear_help'    => 'Eliminar todos los registros',
        'btn_clear_filters' => 'Limpiar filtros',

        // Filtros
        'filter_date_from'    => 'Desde',
        'filter_date_to'      => 'Hasta',
        'filter_user'         => 'Usuario',
        'filter_module'       => 'Modulo',
        'filter_action'       => 'Accion',
        'filter_all_users'    => 'Todos los usuarios',
        'filter_all_modules'  => 'Todos los modulos',
        'filter_all_actions'  => 'Todas las acciones',

        // Columnas
        'col_timestamp' => 'Fecha y hora',
        'col_user'      => 'Usuario',
        'col_module'    => 'Modulo',
        'col_action'    => 'Accion',
        'col_detail'    => 'Detalle',
        'col_ip'        => 'IP',

        // Estados
        'empty_title'      => 'Sin registros',
        'empty_desc'       => 'Aun no hay actividad registrada o los filtros no tienen coincidencias.',
        'loading'          => 'Cargando registros...',
        'results_one'      => '1 registro',
        'results_many'     => '{n} registros',
        'results_filtered' => 'Filtros aplicados',

        // Paginacion
        'pagination_label'  => 'Paginacion del registro',
        'pagination_prev'   => 'Anterior',
        'pagination_next'   => 'Siguiente',
        'showing_of'        => 'Pagina {page} de {pages} — {total} registros',

        // Limpiar log
        'clear_title'   => 'Vaciar registro de actividad',
        'clear_message' => 'Se eliminaran TODOS los registros de actividad. Esta accion no se puede deshacer.',
        'clear_success' => 'Registro limpiado correctamente.',
        'clear_error'   => 'No se pudo limpiar el registro.',

        // Errores
        'err_generic' => 'No se pudo cargar el registro.',

        // Modulos (etiquetas legibles)
        'mod_auth'             => 'Autenticacion',
        'mod_users'            => 'Usuarios',
        'mod_manage_alliances' => 'Gestion de alianzas',
        'mod_alliances'        => 'Alianzas',
        'mod_backup'           => 'Copias de seguridad',
        'mod_settings'         => 'Ajustes',
        'mod_application'      => 'Aplicacion',
        'mod_utilities'        => 'Utilidades',
        'mod_cleanup'          => 'Limpieza',
        'mod_reports'          => 'Registro',

        // Acciones (etiquetas legibles)
        'act_login'       => 'Inicio de sesion',
        'act_logout'      => 'Cierre de sesion',
        'act_create'      => 'Crear',
        'act_update'      => 'Actualizar',
        'act_delete'      => 'Eliminar',
        'act_restore'     => 'Restaurar',
        'act_process'     => 'Procesar',
        'act_clean'       => 'Limpiar',
        'act_clear'       => 'Vaciar',
        'act_diagnostics' => 'Diagnostico',
    ],
];
