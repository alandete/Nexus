<?php
/**
 * S4Learning - English Translations (Reports)
 */
defined('APP_ACCESS') or die('Direct access not allowed');

return [
    'reports' => [
        'submenu_link'    => 'Activity Log',
        'page_title'      => 'Activity Log',
        'description'     => 'History of actions performed in the system.',

        // Filters
        'filter_date_from'  => 'From',
        'filter_date_to'    => 'To',
        'filter_user'       => 'User',
        'filter_module'     => 'Module',
        'filter_action'     => 'Action',
        'filter_all'        => 'All',
        'btn_filter'        => 'Filter',
        'btn_clear_filters' => 'Clear filters',
        'btn_clear_log'     => 'Clear log',
        'confirm_clear'     => 'Delete all entries from the activity log? This action cannot be undone.',
        'success_clear'     => 'Activity log cleared successfully.',

        // Table
        'col_date'    => 'Date',
        'col_user'    => 'User',
        'col_module'  => 'Module',
        'col_action'  => 'Action',
        'col_detail'  => 'Detail',
        'col_ip'      => 'IP',
        'empty_state' => 'No activity records.',
        'no_results'  => 'No results found with the applied filters.',

        // Pagination
        'showing'   => 'Showing {from}-{to} of {total}',
        'page_prev' => 'Previous',
        'page_next' => 'Next',

        // Module labels
        'module_auth'             => 'Authentication',
        'module_users'            => 'Users',
        'module_manage_alliances' => 'Manage Alliances',
        'module_alliances'        => 'Alliances',
        'module_backup'           => 'Backups',
        'module_projectinfo'      => 'Project Info',
        'module_reports'          => 'Reports',

        // Action labels
        'action_login'   => 'Login',
        'action_logout'  => 'Logout',
        'action_create'  => 'Create',
        'action_update'  => 'Update',
        'action_delete'  => 'Delete',
        'action_restore' => 'Restore',
        'action_process' => 'Process',
        'action_clear'   => 'Clear log',

        // Errors
        'error_request' => 'Error querying the log',
    ],
];
