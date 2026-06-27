<?php
/**
 * Nexus 2.0 — Traducciones Español: Registro de errores
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'errors' => [
        'page_title'    => 'Registro de errores',
        'page_subtitle' => 'Errores capturados por la aplicación en tiempo de ejecución.',

        'col_timestamp' => 'Fecha',
        'col_level'     => 'Nivel',
        'col_message'   => 'Mensaje',
        'col_origin'    => 'Origen',

        'level_fatal'     => 'Fatal',
        'level_exception' => 'Excepción',
        'level_error'     => 'Error',
        'level_warning'   => 'Advertencia',

        'filter_date_from'  => 'Desde',
        'filter_date_to'    => 'Hasta',
        'filter_level'      => 'Nivel',
        'filter_all_levels' => 'Todos los niveles',
        'btn_clear_filters' => 'Limpiar filtros',

        'btn_clear'      => 'Limpiar registro',
        'btn_clear_help' => 'Eliminar todos los registros de error',

        'clear_title'   => 'Limpiar registro de errores',
        'clear_message' => 'Se eliminarán TODOS los registros de errores. Esta acción no se puede deshacer.',
        'clear_success' => 'Registro de errores limpiado.',
        'clear_error'   => 'No se pudo limpiar el registro.',

        'empty_title' => 'Sin errores registrados',
        'empty_desc'  => 'No se han capturado errores aún.',

        'loading'          => 'Cargando errores...',
        'pagination_label' => 'Paginación del registro de errores',
        'pagination_prev'  => 'Anterior',
        'pagination_next'  => 'Siguiente',
        'showing_of'       => 'Página {page} de {pages} — {total} registros',

        'results_one'      => '1 registro',
        'results_many'     => '{n} registros',
        'results_filtered' => 'Filtrado',

        'err_generic'  => 'No se pudo cargar el registro.',
        'detail_file'  => 'Archivo',
        'detail_url'   => 'URL',
        'detail_user'  => 'Usuario',
        'detail_ip'    => 'IP',
        'detail_trace' => 'Traza',
        'no_trace'     => '(sin traza)',
    ],
];
