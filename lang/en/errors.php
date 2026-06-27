<?php
/**
 * Nexus 2.0 — English Translations: Error Log
 */
defined('APP_ACCESS') or die('Direct access not allowed');

return [
    'errors' => [
        'page_title'    => 'Error Log',
        'page_subtitle' => 'Errors captured by the application at runtime.',

        'col_timestamp' => 'Date',
        'col_level'     => 'Level',
        'col_message'   => 'Message',
        'col_origin'    => 'Origin',

        'level_fatal'     => 'Fatal',
        'level_exception' => 'Exception',
        'level_error'     => 'Error',
        'level_warning'   => 'Warning',

        'filter_date_from'  => 'From',
        'filter_date_to'    => 'To',
        'filter_level'      => 'Level',
        'filter_all_levels' => 'All levels',
        'btn_clear_filters' => 'Clear filters',

        'btn_clear'      => 'Clear log',
        'btn_clear_help' => 'Delete all error records',

        'clear_title'   => 'Clear error log',
        'clear_message' => 'ALL error records will be deleted. This action cannot be undone.',
        'clear_success' => 'Error log cleared.',
        'clear_error'   => 'Could not clear the log.',

        'empty_title' => 'No errors recorded',
        'empty_desc'  => 'No errors have been captured yet.',

        'loading'          => 'Loading errors...',
        'pagination_label' => 'Error log pagination',
        'pagination_prev'  => 'Previous',
        'pagination_next'  => 'Next',
        'showing_of'       => 'Page {page} of {pages} — {total} records',

        'results_one'      => '1 record',
        'results_many'     => '{n} records',
        'results_filtered' => 'Filtered',

        'err_generic'  => 'Could not load the log.',
        'detail_file'  => 'File',
        'detail_url'   => 'URL',
        'detail_user'  => 'User',
        'detail_ip'    => 'IP',
        'detail_trace' => 'Trace',
        'no_trace'     => '(no trace)',
    ],
];
