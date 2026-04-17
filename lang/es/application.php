<?php
/**
 * Nexus 2.0 — Traducciones Español (Aplicacion)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'application' => [
        'page_title'    => 'Aplicacion',
        'page_subtitle' => 'Identidad, colores de marca, zona horaria, modo mantenimiento y datos de la empresa.',

        // Secciones
        'sec_identity'       => 'Identidad',
        'sec_identity_desc'  => 'Nombre, eslogan, descripcion, logo, favicon y colores de marca.',
        'sec_company'        => 'Empresa',
        'sec_company_desc'   => 'Datos de contacto de la organizacion.',
        'sec_operation'      => 'Operacion',
        'sec_operation_desc' => 'Zona horaria, idioma por defecto y modo mantenimiento.',
        'sec_privacy'        => 'Privacidad',
        'sec_privacy_desc'   => 'Visibilidad del sitio ante buscadores y traductores.',

        // Identidad
        'field_app_name'       => 'Nombre de la aplicacion',
        'field_tagline'        => 'Eslogan',
        'field_tagline_help'   => 'Frase corta visible en el login y metadatos.',
        'field_description'    => 'Descripcion',
        'field_assets'         => 'Logo y favicon',
        'field_logo'           => 'Logo',
        'field_favicon'        => 'Favicon',
        'field_colors'         => 'Colores de marca',
        'colors_help'          => 'El color primario se usa en navegacion, marca y acciones clave. El acento se reserva para elementos puntuales.',
        'logo_help'            => 'JPG, PNG o WebP. Max 2 MB. Recomendado 120x40 px.',
        'favicon_help'         => 'PNG, SVG o ICO. Max 512 KB. Recomendado cuadrado.',
        'btn_upload'           => 'Subir',
        'btn_remove'           => 'Quitar',

        // Colores
        'field_brand_color'    => 'Color primario',
        'brand_color_help'     => 'Se usa en navegacion activa, marca, foco y acentos clave.',
        'field_accent_color'   => 'Color de acento',
        'accent_color_help'    => 'Color secundario para resaltar elementos puntuales.',
        'color_preview'        => 'Vista previa',
        'preview_button'       => 'Accion principal',
        'preview_lozenge'      => 'Activo',

        // Empresa
        'field_company_name'    => 'Nombre de la empresa',
        'field_company_address' => 'Direccion',
        'field_contact_email'   => 'Correo de contacto',
        'field_contact_phone'   => 'Telefono',
        'field_website'         => 'Sitio web',

        // Operacion
        'field_timezone'         => 'Zona horaria',
        'field_timezone_help'    => 'Afecta fechas y horas mostradas en toda la aplicacion.',
        'field_default_lang'     => 'Idioma por defecto',
        'field_default_lang_help'=> 'Idioma inicial para usuarios sin sesion.',

        // Modo mantenimiento
        'field_maintenance'               => 'Modo mantenimiento',
        'field_maintenance_help'          => 'Bloquea el acceso a usuarios no administradores.',
        'field_maintenance_msg'           => 'Mensaje visible',
        'field_maintenance_msg_placeholder' => 'Estaremos de vuelta pronto...',
        'field_maintenance_ips'           => 'IPs permitidas',
        'field_maintenance_ips_help'      => 'Una IP por linea. Estas IPs podran acceder aunque el modo este activo.',
        'maintenance_warning'             => 'Al activar el modo mantenimiento, solo los administradores e IPs permitidas podran acceder al sistema.',
        'maintenance_default_msg'         => 'Estamos realizando mejoras al sistema. Volveremos pronto.',

        // Privacidad
        'field_privacy'      => 'Modo privado',
        'field_privacy_help' => 'Agrega meta tags noindex/nofollow para buscadores y desactiva traductores.',

        // Pagina de mantenimiento
        'maintenance_title'        => 'En mantenimiento',
        'maintenance_admin_access' => 'Acceso de administrador',

        // Acciones
        'btn_save'        => 'Guardar cambios',

        // Errores y exito
        'err_app_name'     => 'El nombre de la aplicacion es obligatorio.',
        'err_brand_color'  => 'Color primario invalido. Debe ser hexadecimal (#RRGGBB).',
        'err_accent_color' => 'Color de acento invalido. Debe ser hexadecimal (#RRGGBB).',
        'err_email'        => 'Correo no valido.',
        'err_website'      => 'URL no valida. Debe empezar con http:// o https://',
        'err_timezone'     => 'Zona horaria no valida.',
        'err_generic'      => 'No se pudo guardar la configuracion.',
        'err_save'         => 'Error al guardar. Revisa los campos.',
        'success_save'     => 'Configuracion guardada.',
        'saved'            => 'Cambios guardados.',
    ],
];
