<?php
/**
 * Nexus 2.0 — Traducciones Español (Integraciones)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'integrations' => [
        'page_title'    => 'Integraciones',
        'page_subtitle' => 'Configura las claves de acceso a servicios externos.',

        'ilp_description'         => 'Servicio compartido para optimizar PDF e imagenes via API en la nube.',
        'status_configured'       => 'Configurada',
        'status_not_configured'   => 'No configurada',
        'status_saved'            => 'Guardada',

        'security_note' => 'Las claves se almacenan encriptadas con AES-256. Nunca se muestran en texto plano una vez guardadas.',

        'section_account' => 'Datos de la cuenta',
        'section_keys'    => 'Claves API',

        'field_email'             => 'Correo de la cuenta',
        'field_password'          => 'Contrasena de la cuenta',
        'field_project'           => 'Nombre del proyecto',
        'field_project_placeholder' => 'Ej: Nexus',
        'field_public_key'        => 'Public Key',
        'field_secret_key'        => 'Secret Key',

        'placeholder_keep_current' => 'Dejar vacio para conservar la actual',
        'password_current_help'    => 'Hay una contrasena guardada. Dejar vacio para mantenerla.',
        'public_key_help'          => 'Clave publica del proyecto en developer.ilovepdf.com.',
        'secret_key_help'          => 'Clave secreta del proyecto. Se encripta al guardar.',

        'show_password' => 'Mostrar contrasena',
        'show_value'    => 'Mostrar valor',
        'hide_value'    => 'Ocultar',

        'btn_save'  => 'Guardar cambios',
        'btn_test'  => 'Probar conexion',
        'testing'   => 'Probando conexion...',
        'plan_info' => 'Informacion del plan',
        'test_ok'   => 'Conexion verificada correctamente.',
        'test_fail' => 'No se pudo conectar. Verifica la clave publica.',

        'err_email'   => 'Correo no valido.',
        'err_generic' => 'No se pudo guardar la configuracion.',
        'saved'       => 'Configuracion guardada.',

        'guide_title'     => 'Como obtener las claves de iLovePDF / iLoveIMG',
        'guide_intro'     => 'iLovePDF e iLoveIMG comparten la misma plataforma y claves API. El registro toma menos de 5 minutos.',
        'guide_step1'     => 'Ve a la consola para desarrolladores en',
        'guide_step2'     => 'Crea una cuenta gratuita o inicia sesion.',
        'guide_step3'     => 'En el panel principal, haz clic en "+ New project" y asignale un nombre.',
        'guide_step4'     => 'Abre el proyecto creado. Encontraras la Public Key y la Secret Key.',
        'guide_step5'     => 'Copia ambas claves y pegalas en el formulario de arriba. Haz clic en "Probar conexion" para confirmar.',
        'guide_free_plan' => 'Plan gratuito: 200 operaciones al mes (PDFs e imagenes) con archivos de hasta 100 MB.',
    ],
];
