<?php
/**
 * S4Learning - Traducciones Español (Reportes)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'reports' => [
        'submenu_link'    => 'Registro de Actividad',
        'page_title'      => 'Registro de Actividad',
        'description'     => 'Historial de acciones realizadas en el sistema.',

        // Filtros
        'filter_date_from'  => 'Desde',
        'filter_date_to'    => 'Hasta',
        'filter_user'       => 'Usuario',
        'filter_module'     => 'Módulo',
        'filter_action'     => 'Acción',
        'filter_all'        => 'Todos',
        'btn_filter'        => 'Filtrar',
        'btn_clear_filters' => 'Limpiar filtros',
        'btn_clear_log'     => 'Vaciar registro',
        'confirm_clear'     => '¿Eliminar todas las entradas del registro de actividad? Esta acción no se puede deshacer.',
        'success_clear'     => 'Registro de actividad vaciado correctamente.',

        // Tabla
        'col_date'    => 'Fecha',
        'col_user'    => 'Usuario',
        'col_module'  => 'Módulo',
        'col_action'  => 'Acción',
        'col_detail'  => 'Detalle',
        'col_ip'      => 'IP',
        'empty_state' => 'No hay registros de actividad.',
        'no_results'  => 'No se encontraron resultados con los filtros aplicados.',

        // Paginación
        'showing'   => 'Mostrando {from}-{to} de {total}',
        'page_prev' => 'Anterior',
        'page_next' => 'Siguiente',

        // Etiquetas de módulos
        'module_auth'             => 'Autenticación',
        'module_users'            => 'Usuarios',
        'module_manage_alliances' => 'Gestionar Alianzas',
        'module_alliances'        => 'Alianzas',
        'module_backup'           => 'Copias de Seguridad',
        'module_projectinfo'      => 'Info del Proyecto',
        'module_reports'          => 'Reportes',

        // Etiquetas de acciones
        'action_login'   => 'Inicio de sesión',
        'action_logout'  => 'Cierre de sesión',
        'action_create'  => 'Crear',
        'action_update'  => 'Actualizar',
        'action_delete'  => 'Eliminar',
        'action_restore' => 'Restaurar',
        'action_process' => 'Procesar',
        'action_clear'   => 'Vaciar registro',

        // Errores
        'error_request' => 'Error al consultar el registro',
    ],
];
