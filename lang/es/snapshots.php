<?php
/**
 * Nexus 2.0 — Traducciones Español (Copias de seguridad)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'snapshots' => [
        'page_title'    => 'Copias de seguridad',
        'page_subtitle' => 'Respaldos del sistema para proteger tus datos y configuracion.',

        'btn_create'         => 'Crear copia',
        'btn_create_confirm' => 'Crear copia',
        'btn_download'       => 'Descargar',
        'btn_restore'        => 'Restaurar',
        'btn_restore_confirm'=> 'Restaurar',
        'btn_delete'         => 'Eliminar',
        'btn_favorite'       => 'Marcar como favorita',
        'btn_unfavorite'     => 'Quitar favorita',

        // Stats
        'stat_total'     => 'Copias',
        'stat_size'      => 'Espacio usado',
        'stat_last'      => 'Ultima copia',
        'stat_favorites' => 'Favoritas',

        // Rotacion
        'rotation_data'  => 'Datos',
        'rotation_full'  => 'Completas',
        'rotation_slots' => 'espacios usados',
        'rotation_hint'  => 'Las copias favoritas no cuentan para la rotacion automatica.',

        // Filtros
        'search_placeholder' => 'Buscar por nombre o nota...',
        'filter_type'        => 'Filtrar por tipo',
        'filter_all'         => 'Todas',
        'filter_data'        => 'Datos',
        'filter_full'        => 'Completas',
        'filter_favorites'   => 'Favoritas',

        // Estados
        'empty_title'          => 'Sin copias de seguridad',
        'empty_desc'           => 'Aun no hay copias guardadas. Crea la primera para proteger tu informacion.',
        'empty_filtered_title' => 'Sin resultados',
        'empty_filtered_desc'  => 'Ajusta los filtros para ver mas copias.',

        // Tipos
        'type_data'       => 'Datos',
        'type_data_desc'  => 'Archivos JSON + plantillas + base de datos. Ligero, restaurable desde la interfaz.',
        'type_full'       => 'Completa',
        'type_full_desc'  => 'Todo el proyecto: codigo, configuracion y datos. Solo para descarga manual.',
        'full_hint'       => 'Las copias completas solo pueden descargarse. La restauracion es manual.',

        // Protegida (favorita)
        'protected'         => 'Protegida',
        'protected_tooltip' => 'No se elimina automaticamente por rotacion.',
        'delete_locked'     => 'Quita el favorito para eliminarla',

        // Formulario de creacion
        'form_create_title'      => 'Crear copia de seguridad',
        'field_type'             => 'Tipo de copia',
        'field_note'             => 'Nota (opcional)',
        'field_note_placeholder' => 'Ej: antes del cambio X',
        'field_note_help'        => 'Texto breve para identificar esta copia despues.',
        'field_cleanup'          => 'Limpiar archivos temporales antes de crear',
        'field_cleanup_help'     => 'Elimina archivos temporales de procesamiento (/temp/) antes de crear la copia.',
        'create_hint_data'       => 'Se guardaran los JSON, plantillas y la base de datos. Esta copia se puede restaurar desde aqui mismo.',
        'create_hint_full'       => 'Se empaquetara todo el proyecto. Util para migracion completa. No podras restaurarla desde la interfaz, solo descargarla.',
        'creating'               => 'Creando...',

        // Restauracion
        'restore_title'   => 'Restaurar copia',
        'restore_message' => 'Se sobrescribiran los datos actuales con el contenido de esta copia. Esta accion no se puede deshacer.

Archivo: {file}',

        // Eliminacion
        'delete_title'   => 'Eliminar copia',
        'delete_message' => 'La copia "{file}" sera eliminada permanentemente.',

        // Mensajes de exito/error
        'created'      => 'Copia creada correctamente.',
        'restored'     => 'Copia restaurada correctamente.',
        'deleted'      => 'Copia eliminada.',
        'favorited'    => 'Marcada como favorita.',
        'unfavorited'  => 'Favorito removido.',
        'err_create'   => 'No se pudo crear la copia.',
        'err_restore'  => 'No se pudo restaurar.',
        'err_delete'   => 'No se pudo eliminar.',
        'err_favorite' => 'No se pudo actualizar el favorito.',
    ],
];
