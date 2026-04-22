<?php
/**
 * Nexus 2.0 — Traducciones Español (Usuarios)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'users' => [
        // Header
        'page_title'           => 'Usuarios',
        'page_subtitle'        => 'Gestiona cuentas, roles y permisos del sistema.',
        'btn_create'           => 'Nuevo usuario',

        // Stats
        'stat_total'           => 'Total',
        'stat_active'          => 'Activos',
        'stat_admins'          => 'Admins',

        // Filtros
        'search_placeholder'   => 'Buscar por nombre, usuario o correo...',
        'filter_role'          => 'Filtrar por rol',
        'filter_all_roles'     => 'Todos los roles',
        'filter_status'        => 'Filtrar por estado',
        'filter_all_status'    => 'Todos los estados',
        'filter_active'        => 'Activos',
        'filter_inactive'      => 'Inactivos',

        // Tabla
        'col_user'             => 'Usuario',
        'col_role'             => 'Rol',
        'col_lang'             => 'Idioma',
        'col_last_login'       => 'Último acceso',
        'col_status'           => 'Estado',
        'col_actions'          => 'Acciones',
        'status_active'        => 'Activo',
        'status_inactive'      => 'Inactivo',
        'never_logged'         => 'Nunca',
        'you'                  => 'Tú',

        // Acciones
        'btn_edit'             => 'Editar',
        'btn_delete'           => 'Eliminar',

        // Empty states
        'empty_filtered_title' => 'Sin resultados',
        'empty_filtered_desc'  => 'Ajusta los filtros o la búsqueda.',

        // Formulario
        'form_create_title'    => 'Nuevo usuario',
        'form_edit_title'      => 'Editar usuario',
        'field_username'       => 'Usuario',
        'field_username_help'  => '3-20 caracteres: letras, números y guion bajo.',
        'field_name'           => 'Nombre completo',
        'field_email'          => 'Correo',
        'field_password'       => 'Contraseña',
        'field_password_help'  => 'Mínimo 6 caracteres.',
        'field_password_help_edit' => 'Dejar vacío para mantener la actual.',
        'field_role'           => 'Rol',
        'field_role_self'      => 'No puedes cambiar tu propio rol.',
        'field_lang'           => 'Idioma preferido',
        'field_active'         => 'Usuario activo',
        'field_active_help'    => 'Los usuarios inactivos no pueden iniciar sesión.',
        'field_photo'          => 'Foto de perfil',
        'field_photo_help'     => 'JPG, PNG o WebP. Máximo 2 MB.',
        'field_photo_change'   => 'Cambiar foto',
        'field_photo_remove'   => 'Quitar foto',

        // Validación
        'err_username'         => 'Usuario inválido. Usa 3-20 letras, números o guion bajo.',
        'err_name'             => 'El nombre es obligatorio.',
        'err_email'            => 'Correo inválido.',
        'err_password'         => 'La contraseña debe tener al menos 6 caracteres.',
        'err_generic'          => 'No se pudo guardar. Verifica los datos.',
        'err_network'          => 'Error de red. Intenta de nuevo.',
        'err_delete'           => 'No se pudo eliminar el usuario.',
        'success'              => 'Cambios guardados.',
        'deleted'              => 'Usuario eliminado.',

        // Delete dialog
        'delete_title'         => 'Eliminar usuario',
        'delete_message'       => 'Se eliminará a {name} de forma permanente. Esta acción no se puede deshacer.',
    ],
];
