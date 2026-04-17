<?php

/**
 * S4Learning - Traducciones Español (Común)
 * Elementos globales compartidos entre todas las páginas.
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'site_title' => 'Sistema de Gestión Educativa',
    'version' => 'Versión',
    'copyright' => 'Todos los derechos reservados',

    // Menú
    'menu' => [
        'home' => 'Inicio',
        'tasks' => 'Tareas',
        'alliances' => 'Alianzas',
        'utilities' => 'Utilidades',
        'settings' => 'Ajustes',
        'main_nav' => 'Navegacion principal',
        'questions' => 'Preguntas',
        'pdf_optimizer' => 'Optimizar PDF',
        'image_optimizer' => 'Optimizar imagenes',
        'users' => 'Usuarios',
        'manage_alliances' => 'Gestionar alianzas',
        'project' => 'Proyecto',
        'integrations' => 'Integraciones',
        'backups' => 'Copias de seguridad',
        'system' => 'Sistema',
        'activity' => 'Actividad',
        'docs' => 'Documentacion',
    ],

    // Header
    'header' => [
        'logout_title'  => 'Cerrar Sesión',
        'logout_aria'   => 'Cerrar sesión',
        'toggle_menu'   => 'Abrir menú de navegación',
        'user_fallback' => 'Usuario',
        'change_lang'   => 'Cambiar idioma',
    ],

    // Accesibilidad
    'a11y' => [
        'skip_to_content' => 'Saltar al contenido principal',
        'close_panel'     => 'Cerrar panel',
        'scroll_to_top'   => 'Volver arriba',
        'help'            => 'Ayuda',
        'loading'         => 'Cargando contenido',
    ],

    // Login
    'login' => [
        'page_title'       => 'Iniciar Sesión',
        'tagline'          => 'Del contenido al aula, sin complicaciones',
        'description'      => 'Todo lo que el equipo de migración necesita para organizar, transformar y optimizar recursos educativos listos para las plataformas de aprendizaje.',
        'version_prefix'   => 'Versión',
        'form_title'       => 'Iniciar Sesión',
        'form_subtitle'    => 'Ingrese sus credenciales para acceder al sistema',
        'field_username'   => 'Usuario',
        'field_password'   => 'Contraseña',
        'placeholder_user' => 'Ingrese su usuario',
        'placeholder_pass' => 'Ingrese su contraseña',
        'btn_submit'       => 'Ingresar',
        'error_empty'      => 'Por favor ingrese usuario y contraseña',
        'demo_title'       => 'Usuarios de demostración:',
    ],

    // Dashboard
    'dashboard' => [
        'welcome'            => 'Hola, {user_name}',
        'date_format'        => 'd \d\e F, Y',
        'greeting_morning'   => 'Buenos dias',
        'greeting_afternoon' => 'Buenas tardes',
        'greeting_evening'   => 'Buenas noches',
        // Timer activo
        'active_timer'       => 'Cronometro activo',
        'no_active_timer'    => 'Sin cronometro activo',
        'timer_go_tasks'     => 'Ir a tareas',
        'timer_pause'        => 'Pausar',
        'timer_stop'         => 'Detener',
        // Stats
        'stats_pending'      => 'Pendientes',
        'stats_in_progress'  => 'En progreso',
        'stats_today_time'   => 'Tiempo hoy',
        'stats_overdue'      => 'Vencidas',
        // Proximas tareas
        'upcoming_tasks'     => 'Proximas tareas',
        'no_upcoming'        => 'No hay tareas programadas',
        'due_today'          => 'Vence hoy',
        'due_tomorrow'       => 'Vence manana',
        'due_days'           => 'Vence en {days} dias',
        'overdue'            => 'Vencida',
        'view_all_tasks'     => 'Ver todas las tareas',
        // Accesos rapidos
        'quick_access'       => 'Accesos rapidos',
        'quick_new_task'     => 'Nueva tarea',
        'quick_questions'    => 'Convertir preguntas',
        'quick_pdf'          => 'Optimizar PDF',
        'quick_alliances'    => 'Alianzas',
        // Actividad reciente
        'recent_activity'    => 'Actividad reciente',
        'no_activity'        => 'Sin actividad reciente',
        'view_all_activity'  => 'Ver todo',
        // Progreso
        'project_progress'   => 'Progreso del proyecto',
        'stages_completed'   => '{count} de {total} etapas',
        'status_completed'   => 'Completado',
        'status_in_progress' => 'En progreso',
        'status_pending'     => 'Pendiente',
    ],

    // Estados comunes
    'common' => [
        'save'   => 'Guardar',
        'cancel' => 'Cancelar',
        'edit'   => 'Editar',
        'delete' => 'Eliminar',
        'view'   => 'Ver',
        'back'   => 'Volver',
        'search' => 'Buscar',
        'filter' => 'Filtrar',
        'export' => 'Exportar',
        'import' => 'Importar',
    ],

    // Ajustes
    'settings' => [
        'page_title'        => 'Ajustes',
        'submenu_users'     => 'Usuarios',
        'submenu_alliances' => 'Alianzas',
        'submenu_backups'   => 'Copias de seguridad',
        'submenu_system'    => 'Sistema',
        'sys_info_title'    => 'Información del Sistema',
        'label_version'     => 'Versión',
        'label_php'         => 'PHP',
        'label_bootstrap'   => 'Bootstrap',
        'label_design'      => 'Diseño',
        'val_design'        => 'Mobile-first, Minimalista',
        'tech_title'        => 'Características Técnicas',
        'tech_desc'         => 'PHP 8+ Compatible • Sin estilos CSS inline • Sin scripts embebidos • Variables CSS reutilizables • Sistema de traducciones',
        'card_active'       => 'Alianzas activas',
        'card_inactive'     => 'Alianzas inactivas',
        'card_users'        => 'Usuarios registrados',
        'card_tech'         => 'Información técnica',
        'submenu_docs'      => 'Documentación',
        'submenu_apis'      => 'Integraciones',
        'apis_title'        => 'Configuracion de APIs externas',
        'apis_desc'         => 'Administra las claves de acceso a servicios externos. Las claves se guardan encriptadas y solo son visibles para el administrador.',
        'apis_ilp_desc'     => 'Plataforma compartida de iLovePDF e iLoveIMG. Las mismas claves habilitan el metodo API en Optimizar PDF y en Optimizar imágenes.',
        'apis_ilp_email'    => 'Correo de la cuenta',
        'apis_ilp_email_placeholder' => 'correo@ejemplo.com',
        'apis_ilp_password' => 'Contraseña de la cuenta',
        'apis_ilp_project'  => 'Nombre del proyecto',
        'apis_ilp_project_placeholder' => 'Ej: S4Learning',
        'apis_ilp_public'   => 'Public Key',
        'apis_ilp_secret'   => 'Secret Key',
        'apis_ilp_public_help' => 'Clave publica de tu proyecto en developer.ilovepdf.com',
        'apis_ilp_secret_help' => 'Clave secreta de tu proyecto en developer.ilovepdf.com',
        'apis_key_placeholder' => 'Dejar en blanco para conservar el valor actual',
        'apis_btn_save'     => 'Guardar claves',
        'apis_btn_test'     => 'Probar conexion',
        'apis_plan_title'   => 'Información del plan',
        'apis_plan_link'    => 'Ver cuota y uso en el panel de desarrolladores',
        'apis_status_configured'   => 'Configurada',
        'apis_status_unconfigured' => 'No configurada',
        'apis_status_incomplete'   => 'Configuracion incompleta',
        'apis_security_note' => 'Las claves se almacenan encriptadas con AES-256. Nunca se muestran en texto plano una vez guardadas.',
        // Dependencias del servidor
        'deps_title'          => 'Dependencias del servidor',
        'deps_desc'           => 'Estado de los componentes externos requeridos por el optimizador de PDF e imagenes.',
        'deps_col_component'  => 'Componente',
        'deps_col_status'     => 'Estado',
        'deps_col_version'    => 'Version',
        'deps_col_update'     => 'Actualizacion',
        'deps_installed'      => 'Instalado',
        'deps_missing'        => 'No encontrado',
        'deps_use_pdf'        => 'Requerido por el optimizador de PDF',
        'deps_use_img'        => 'Requerido por el optimizador de imagenes',
        'deps_use_db'         => 'Requerido para time tracker, usuarios y actividad',
        'deps_update_unknown' => 'Ejecuta el script para verificar',
        'deps_update_available' => 'disponible',
        'deps_update_ok'      => 'Al dia',
        'deps_last_check'     => 'Ultima verificacion',
        'deps_script_title'   => 'Instalador automatico',
        'deps_script_desc'    => 'Ejecuta el siguiente comando en la terminal desde la raiz del proyecto para detectar, descargar y configurar las dependencias:',

        // Diagnóstico
        'diag_title'          => 'Diagnóstico del sistema',
        'diag_desc'           => 'Verifica el estado del entorno, base de datos, datos y seguridad.',
        'diag_btn'            => 'Ejecutar',
        'diag_last_run'       => 'Última ejecución',
        'diag_errors'         => 'errores',
        'diag_warnings'       => 'advertencias',
        'diag_running'        => 'Ejecutando diagnóstico...',

        // Info BD
        'db_info_title'       => 'Conexión a base de datos',
        'db_host'             => 'Servidor',
        'db_name'             => 'Base de datos',
        'db_user'             => 'Usuario',

        // Privacidad
        'privacy_title'       => 'Modo privado',
        'privacy_desc'        => 'Oculta el sitio de buscadores, rastreadores y servicios de traducción.',
        'privacy_noindex'     => 'No indexar páginas en buscadores',
        'privacy_nofollow'    => 'No seguir enlaces externos',
        'privacy_noimageindex' => 'No indexar imágenes',
        'privacy_notranslate' => 'No ofrecer traducción automática',
        // Guia iLovePDF / iLoveIMG (para el admin en Ajustes)
        'apis_guide_ilp_title'  => '¿Como obtener las claves de iLovePDF / iLoveIMG?',
        'apis_guide_ilp_intro'  => 'iLovePDF e iLoveIMG comparten la misma plataforma y las mismas claves API. El plan gratuito incluye 200 operaciones al mes para PDFs e imágenes. El registro toma menos de 5 minutos.',
        'apis_guide_ilp_step1'  => 'Ve a la consola para desarrolladores:',
        'apis_guide_ilp_step2'  => 'Crea una cuenta gratuita con tu correo o inicia sesion si ya tienes una.',
        'apis_guide_ilp_step3'  => 'En el panel principal, haz clic en "+ New project" y asignale un nombre (ej. "NexusApp").',
        'apis_guide_ilp_step4'  => 'Abre el proyecto creado. Encontraras la "Public key" y la "Secret key".',
        'apis_guide_ilp_step5'  => 'Copia ambas claves y pegaLas en el formulario de arriba.',
        'apis_guide_ilp_step6'  => 'Haz clic en "Guardar claves" y luego en "Probar conexion" para confirmar que todo funciona.',
        'apis_guide_ilp_free_plan' => 'Plan gratuito: 200 operaciones/mes (PDFs e imágenes) · Archivos de hasta 100 MB · Sin marca de agua en el resultado.',
    ],
];
