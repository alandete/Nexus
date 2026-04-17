<?php
/**
 * Nexus 2.0 — Traducciones Español (Sistema)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'system' => [
        'page_title'    => 'Sistema',
        'page_subtitle' => 'Diagnostico, dependencias, entorno PHP y conexion a base de datos.',

        // Secciones
        'sec_diagnostics'       => 'Diagnostico',
        'sec_dependencies'      => 'Dependencias',
        'sec_dependencies_desc' => 'Componentes externos necesarios para optimizar archivos y conectarse a la base de datos.',
        'sec_php'               => 'Entorno PHP',
        'sec_php_desc'          => 'Version, limites de memoria, extensiones disponibles.',
        'sec_database'          => 'Base de datos',
        'sec_database_desc'     => 'Informacion de la conexion configurada.',
        'sec_permissions'       => 'Permisos de directorios',
        'sec_permissions_desc'  => 'Directorios que deben tener permiso de escritura.',

        // Diagnostico
        'btn_run'        => 'Ejecutar diagnostico',
        'running'        => 'Ejecutando...',
        'run_success'    => 'Diagnostico ejecutado.',
        'err_run'        => 'No se pudo ejecutar el diagnostico.',
        'last_run'       => 'Ultima ejecucion',
        'never_run'      => 'Nunca se ha ejecutado',
        'just_now'       => 'hace un momento',
        'status_ok'      => 'Correctos',
        'status_warning' => 'Advertencias',
        'status_error'   => 'Errores',
        'all_ok'         => 'Todos los chequeos pasaron correctamente.',

        // Dependencias
        'deps_installed'      => 'Instalado',
        'deps_missing'        => 'No encontrado',
        'deps_connected'      => 'Conectado',
        'deps_no_connection'  => 'Sin conexion',
        'deps_use_pdf'        => 'Requerido por el optimizador de PDF',
        'deps_use_img'        => 'Requerido por el optimizador de imagenes',
        'deps_use_db'         => 'Requerido para tareas, usuarios y actividad',
        'deps_install_hint'   => 'Para instalar componentes faltantes, ejecuta en la terminal:',

        // PHP
        'php_version'    => 'Version de PHP',
        'memory_limit'   => 'Memoria',
        'upload_max'     => 'Subida maxima',
        'post_max'       => 'POST maximo',
        'max_execution'  => 'Ejecucion maxima',
        'timezone'       => 'Zona horaria',
        'extensions'     => 'Extensiones requeridas',

        // Base de datos
        'db_connected'     => 'Conectado',
        'db_disconnected'  => 'Sin conexion',
        'db_host'          => 'Servidor',
        'db_name'          => 'Base de datos',
        'db_user'          => 'Usuario',
        'db_version'       => 'Version',
        'db_no_config'     => 'No hay archivo config/database.php. Copia config/database.example.php y configuralo.',

        // Permisos
        'perm_writable'  => 'Escribible',
        'perm_readonly'  => 'Solo lectura',
    ],
];
