<?php
/**
 * Nexus 2.0 — English Translations (Activity Log)
 */
defined('APP_ACCESS') or die('Direct access not allowed');

return [
    'activity' => [
        'page_title'    => 'Activity log',
        'page_subtitle' => 'Complete history of actions performed in the system.',

        'btn_clear'         => 'Clear log',
        'btn_clear_help'    => 'Delete all records',
        'btn_clear_filters' => 'Clear filters',

        'filter_date_from'    => 'From',
        'filter_date_to'      => 'To',
        'filter_user'         => 'User',
        'filter_module'       => 'Module',
        'filter_action'       => 'Action',
        'filter_all_users'    => 'All users',
        'filter_all_modules'  => 'All modules',
        'filter_all_actions'  => 'All actions',

        'col_timestamp' => 'Date & time',
        'col_user'      => 'User',
        'col_module'    => 'Module',
        'col_action'    => 'Action',
        'col_detail'    => 'Detail',
        'col_ip'        => 'IP',

        'empty_title' => 'No records',
        'empty_desc'  => 'No activity has been logged yet or no records match the current filters.',
        'loading'     => 'Loading records...',
        'results_one'      => '1 record',
        'results_many'     => '{n} records',
        'results_filtered' => 'Filters applied',

        'pagination_label' => 'Log pagination',
        'pagination_prev'  => 'Previous',
        'pagination_next'  => 'Next',
        'showing_of'       => 'Page {page} of {pages} — {total} records',

        'clear_title'   => 'Clear activity log',
        'clear_message' => 'ALL activity records will be deleted. This action cannot be undone.',
        'clear_success' => 'Log cleared successfully.',
        'clear_error'   => 'Could not clear the log.',

        'err_generic' => 'Could not load the log.',

        'mod_auth'             => 'Authentication',
        'mod_users'            => 'Users',
        'mod_manage_alliances' => 'Alliance management',
        'mod_alliances'        => 'Alliances',
        'mod_backup'           => 'Backups',
        'mod_settings'         => 'Settings',
        'mod_application'      => 'Application',
        'mod_utilities'        => 'Utilities',
        'mod_cleanup'          => 'Cleanup',
        'mod_reports'          => 'Log',

        'act_login'       => 'Login',
        'act_logout'      => 'Logout',
        'act_create'      => 'Create',
        'act_update'      => 'Update',
        'act_delete'      => 'Delete',
        'act_restore'     => 'Restore',
        'act_process'     => 'Process',
        'act_clean'       => 'Clean',
        'act_clear'       => 'Clear',
        'act_diagnostics' => 'Diagnostics',
    ],
];
