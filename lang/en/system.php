<?php
/**
 * Nexus 2.0 — English Translations (System)
 */
defined('APP_ACCESS') or die('Direct access not allowed');

return [
    'system' => [
        'page_title'    => 'System',
        'page_subtitle' => 'Diagnostics, dependencies, PHP environment and database connection.',

        'sec_diagnostics'       => 'Diagnostics',
        'sec_dependencies'      => 'Dependencies',
        'sec_dependencies_desc' => 'External components needed to optimize files and connect to the database.',
        'sec_php'               => 'PHP Environment',
        'sec_php_desc'          => 'Version, memory limits, available extensions.',
        'sec_database'          => 'Database',
        'sec_database_desc'     => 'Information about the configured connection.',
        'sec_permissions'       => 'Directory permissions',
        'sec_permissions_desc'  => 'Directories that must have write permission.',

        'btn_run'        => 'Run diagnostics',
        'running'        => 'Running...',
        'run_success'    => 'Diagnostics completed.',
        'err_run'        => 'Could not run diagnostics.',
        'last_run'       => 'Last run',
        'never_run'      => 'Never run',
        'just_now'       => 'just now',
        'status_ok'      => 'Passed',
        'status_warning' => 'Warnings',
        'status_error'   => 'Errors',
        'all_ok'         => 'All checks passed successfully.',

        'deps_installed'      => 'Installed',
        'deps_missing'        => 'Not found',
        'deps_connected'      => 'Connected',
        'deps_no_connection'  => 'No connection',
        'deps_use_pdf'        => 'Required by the PDF optimizer',
        'deps_use_img'        => 'Required by the image optimizer',
        'deps_use_db'         => 'Required for tasks, users and activity',
        'deps_install_hint'   => 'To install missing components, run in the terminal:',
        'btn_check_deps'      => 'Verify',
        'checking_deps'       => 'Checking...',
        'deps_checked'        => 'Verification complete.',
        'err_check_deps'      => 'Could not verify dependencies.',

        'php_version'    => 'PHP version',
        'memory_limit'   => 'Memory',
        'upload_max'     => 'Max upload',
        'post_max'       => 'Max POST',
        'max_execution'  => 'Max execution',
        'timezone'       => 'Timezone',
        'extensions'     => 'Required extensions',

        'db_connected'     => 'Connected',
        'db_disconnected'  => 'No connection',
        'db_host'          => 'Host',
        'db_name'          => 'Database',
        'db_user'          => 'Username',
        'db_version'       => 'Version',
        'db_no_config'     => 'No config/database.php file found. Copy config/database.example.php and configure it.',

        'perm_writable'  => 'Writable',
        'perm_readonly'  => 'Read only',
    ],
];
