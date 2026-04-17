<?php
/**
 * Nexus 2.0 — Traducciones Español (Gestion de Alianzas)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'manage_alliances' => [
        'page_title'         => 'Alianzas',
        'page_subtitle'      => 'Gestiona las instituciones aliadas y sus archivos de apoyo.',
        'btn_create'         => 'Nueva alianza',
        'btn_edit'           => 'Editar',
        'btn_delete'         => 'Eliminar',

        'stat_total'         => 'Total',
        'stat_active'        => 'Activas',
        'stat_billable'      => 'Facturables',

        'search_placeholder' => 'Buscar por nombre o país...',
        'filter_status'      => 'Filtrar por estado',
        'filter_all_status'  => 'Todas',
        'status_active'      => 'Activa',
        'status_inactive'    => 'Inactiva',

        'view_toggle'        => 'Vista',
        'view_cards'         => 'Vista de tarjetas',
        'view_table'         => 'Vista de lista',

        'responsibles'       => 'Responsables',
        'file'               => 'archivo',
        'files'              => 'archivos',
        'billable_short'     => 'Facturable',

        'empty_title'        => 'Sin alianzas',
        'empty_desc'         => 'Crea tu primera alianza para empezar.',
        'empty_filtered_title' => 'Sin resultados',
        'empty_filtered_desc'  => 'Ajusta los filtros o la búsqueda.',

        'field_manager'      => 'Gerente',
        'field_coordinator'  => 'Coordinador',
        'field_migrator'     => 'Migrador',
    ],

    'alliances' => [
        'form_create'        => 'Nueva alianza',
        'form_edit'          => 'Editar alianza',

        'field_name'         => 'Nombre corto',
        'field_name_help'    => 'Identificador corto, visible en listados.',
        'field_fullname'     => 'Nombre completo',
        'field_country'      => 'País',
        'field_color'        => 'Color institucional',
        'field_website'      => 'Sitio web',
        'field_lms_url'      => 'URL del LMS',
        'field_manager'      => 'Gerente responsable',
        'field_coordinator'  => 'Coordinador',
        'field_migrator'     => 'Migrador',
        'field_active'       => 'Alianza activa',
        'field_billable'     => 'Facturable',
        'field_billable_help' => 'Las alianzas no facturables solo aparecen en Tareas para tareas internas.',

        'country_none'       => 'Sin especificar',
        'status'             => 'Estado',
        'responsibles'       => 'Responsables',

        'resp_user'          => 'Usuario del sistema',
        'resp_external'      => 'Externo',
        'resp_select_user'   => 'Seleccionar usuario...',
        'resp_name_placeholder'  => 'Nombre',
        'resp_email_placeholder' => 'Correo electrónico',

        'files'              => 'Archivos de apoyo',
        'files_help'         => 'PDF, Excel, Word o TXT. Máximo 5 archivos, 5 MB cada uno.',
        'upload'             => 'Subir',
        'file_uploaded'      => 'Archivo cargado.',
        'file_deleted'       => 'Archivo eliminado.',

        'err_name'           => 'El nombre es obligatorio.',
        'err_fullname'       => 'El nombre completo es obligatorio.',
        'err_generic'        => 'No se pudo guardar la alianza.',
        'err_upload'         => 'No se pudo cargar el archivo.',
        'err_delete'         => 'No se pudo eliminar la alianza.',
        'err_delete_file'    => 'No se pudo eliminar el archivo.',
        'saved'              => 'Alianza guardada.',
        'deleted'            => 'Alianza eliminada.',

        'delete_title'       => 'Eliminar alianza',
        'delete_message'     => 'La alianza "{name}" será eliminada permanentemente junto con sus archivos asociados.',
        'delete_file_title'  => 'Eliminar archivo',
        'delete_file_message'=> 'El archivo será eliminado permanentemente.',
    ],
];
