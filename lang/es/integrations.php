<?php
/**
 * Nexus 2.0 — Traducciones Español (Integraciones)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'integrations' => [
        'page_title'    => 'Integraciones',
        'page_subtitle' => 'Configura las claves de acceso a servicios externos.',

        'ilp_description'         => 'Servicio compartido para optimizar PDF e imágenes vía API en la nube.',
        'status_configured'       => 'Configurada',
        'status_not_configured'   => 'No configurada',
        'status_saved'            => 'Guardada',

        'security_note' => 'Las claves se almacenan encriptadas con AES-256. Nunca se muestran en texto plano una vez guardadas.',

        'section_account' => 'Datos de la cuenta',
        'section_keys'    => 'Claves API',

        'field_email'             => 'Correo de la cuenta',
        'field_password'          => 'Contraseña de la cuenta',
        'field_project'           => 'Nombre del proyecto',
        'field_project_placeholder' => 'Ej: Nexus',
        'field_public_key'        => 'Public Key',
        'field_secret_key'        => 'Secret Key',

        'placeholder_keep_current' => 'Dejar vacío para conservar la actual',
        'password_current_help'    => 'Hay una contraseña guardada. Dejar vacío para mantenerla.',
        'public_key_help'          => 'Clave publica del proyecto en developer.ilovepdf.com.',
        'secret_key_help'          => 'Clave secreta del proyecto. Se encripta al guardar.',

        'show_password' => 'Mostrar contraseña',
        'show_value'    => 'Mostrar valor',
        'hide_value'    => 'Ocultar',

        'btn_save'  => 'Guardar cambios',
        'btn_test'  => 'Probar conexión',
        'testing'   => 'Probando conexión...',
        'plan_info' => 'Información del plan',
        'test_ok'   => 'Conexión verificada correctamente.',
        'test_fail' => 'No se pudo conectar. Verifica la clave publica.',

        'err_email'   => 'Correo no valido.',
        'err_generic' => 'No se pudo guardar la configuración.',
        'saved'       => 'Configuración guardada.',

        'guide_title'     => 'Como obtener las claves de iLovePDF / iLoveIMG',
        'guide_intro'     => 'iLovePDF e iLoveIMG comparten la misma plataforma y claves API. El registro toma menos de 5 minutos.',
        'guide_step1'     => 'Ve a la consola para desarrolladores en',
        'guide_step2'     => 'Crea una cuenta gratuita o inicia sesión.',
        'guide_step3'     => 'En el panel principal, haz clic en "+ New project" y asignale un nombre.',
        'guide_step4'     => 'Abre el proyecto creado. Encontraras la Public Key y la Secret Key.',
        'guide_step5'     => 'Copia ambas claves y pegalas en el formulario de arriba. Haz clic en "Probar conexión" para confirmar.',
        'guide_free_plan' => 'Plan gratuito: 200 operaciones al mes (PDFs e imágenes) con archivos de hasta 100 MB.',

        // Gmail
        'gmail_description'      => 'Convierte correos etiquetados en Gmail en tareas de Nexus automáticamente vía IMAP.',
        'gmail_security_note'    => 'La contrasena de aplicacion se almacena encriptada con AES-256. Usar una contrasena de app de Google, no la contrasena principal de Gmail.',
        'gmail_field_email'      => 'Correo Gmail',
        'gmail_field_label'      => 'Etiqueta de Gmail',
        'gmail_field_app_password' => 'Contrasena de aplicacion',
        'gmail_label_help'       => 'Nombre exacto de la etiqueta en Gmail. Los correos con esa etiqueta se convierten en tareas.',
        'gmail_last_sync'        => 'Ultima sincronizacion',
        'gmail_btn_sync'         => 'Sincronizar ahora',
        'gmail_syncing'          => 'Sincronizando...',

        'gmail_guide_title'  => 'Como configurar la integracion con Gmail',
        'gmail_guide_intro'  => 'La integracion usa IMAP con una Contrasena de aplicacion de Google. La sincronizacion ocurre al hacer clic en "Sincronizar ahora".',
        'gmail_guide_step1'  => 'En Gmail, crea una etiqueta nueva (ej: "Nexus") desde Configuracion > Etiquetas.',
        'gmail_guide_step2'  => 'Ve a tu Cuenta de Google > Seguridad > Verificacion en dos pasos (debe estar activa).',
        'gmail_guide_step3'  => 'Al final de esa misma pagina encontraras "Contrasenas de aplicaciones". Crea una para "Nexus".',
        'gmail_guide_step4'  => 'Asegurate de que el acceso IMAP este habilitado en Gmail: Configuracion > Ver toda la configuracion > Reenvio e IMAP.',
        'gmail_guide_step5'  => 'Pega el correo, la contrasena de app (16 caracteres) y el nombre exacto de la etiqueta en el formulario. Guarda y prueba la conexion.',
        'gmail_guide_note'   => 'Cuando quieras crear una tarea desde un correo, solo aplicale la etiqueta configurada en Gmail y haz clic en "Sincronizar ahora" en Nexus.',

        // SMTP
        'smtp_title'          => 'Correo saliente (SMTP)',
        'smtp_description'    => 'Configura el servidor de correo para enviar notificaciones y recuperación de contraseñas.',
        'smtp_security_note'  => 'La contraseña se almacena encriptada con AES-256. Se recomienda usar una contraseña de aplicación, no la principal.',
        'smtp_section_server' => 'Servidor',
        'smtp_section_auth'   => 'Autenticación',
        'smtp_section_sender' => 'Remitente',
        'smtp_field_host'     => 'Servidor SMTP',
        'smtp_field_port'     => 'Puerto',
        'smtp_field_secure'   => 'Cifrado',
        'smtp_field_user'     => 'Usuario / correo',
        'smtp_field_pass'     => 'Contraseña',
        'smtp_field_from_email' => 'Dirección del remitente',
        'smtp_field_from_name'  => 'Nombre del remitente',
    ],
];
