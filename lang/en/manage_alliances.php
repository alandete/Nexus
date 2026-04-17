<?php
/**
 * Nexus 2.0 — English Translations (Manage Alliances)
 */
defined('APP_ACCESS') or die('Direct access not allowed');

return [
    'manage_alliances' => [
        'page_title'         => 'Alliances',
        'page_subtitle'      => 'Manage partner institutions and their support files.',
        'btn_create'         => 'New alliance',
        'btn_edit'           => 'Edit',
        'btn_delete'         => 'Delete',

        'stat_total'         => 'Total',
        'stat_active'        => 'Active',
        'stat_billable'      => 'Billable',

        'search_placeholder' => 'Search by name or country...',
        'filter_status'      => 'Filter by status',
        'filter_all_status'  => 'All',
        'status_active'      => 'Active',
        'status_inactive'    => 'Inactive',

        'view_toggle'        => 'View',
        'view_cards'         => 'Card view',
        'view_table'         => 'List view',

        'responsibles'       => 'Responsibles',
        'file'               => 'file',
        'files'              => 'files',
        'billable_short'     => 'Billable',

        'empty_title'        => 'No alliances',
        'empty_desc'         => 'Create your first alliance to get started.',
        'empty_filtered_title' => 'No results',
        'empty_filtered_desc'  => 'Adjust filters or search terms.',

        'field_manager'      => 'Manager',
        'field_coordinator'  => 'Coordinator',
        'field_migrator'     => 'Migrator',
    ],

    'alliances' => [
        'form_create'        => 'New alliance',
        'form_edit'          => 'Edit alliance',

        'field_name'         => 'Short name',
        'field_name_help'    => 'Short identifier, visible in listings.',
        'field_fullname'     => 'Full name',
        'field_country'      => 'Country',
        'field_color'        => 'Institutional color',
        'field_website'      => 'Website',
        'field_lms_url'      => 'LMS URL',
        'field_manager'      => 'Responsible manager',
        'field_coordinator'  => 'Coordinator',
        'field_migrator'     => 'Migrator',
        'field_active'       => 'Active alliance',
        'field_billable'     => 'Billable',
        'field_billable_help' => 'Non-billable alliances only appear in Tasks for internal work.',

        'country_none'       => 'Unspecified',
        'status'             => 'Status',
        'responsibles'       => 'Responsibles',

        'resp_user'          => 'System user',
        'resp_external'      => 'External',
        'resp_select_user'   => 'Select user...',
        'resp_name_placeholder'  => 'Name',
        'resp_email_placeholder' => 'Email',

        'files'              => 'Support files',
        'files_help'         => 'PDF, Excel, Word or TXT. Max 5 files, 5 MB each.',
        'upload'             => 'Upload',
        'file_uploaded'      => 'File uploaded.',
        'file_deleted'       => 'File deleted.',

        'err_name'           => 'Name is required.',
        'err_fullname'       => 'Full name is required.',
        'err_generic'        => 'Could not save alliance.',
        'err_upload'         => 'Could not upload file.',
        'err_delete'         => 'Could not delete alliance.',
        'err_delete_file'    => 'Could not delete file.',
        'saved'              => 'Alliance saved.',
        'deleted'            => 'Alliance deleted.',

        'delete_title'       => 'Delete alliance',
        'delete_message'     => 'Alliance "{name}" will be permanently deleted along with its associated files.',
        'delete_file_title'  => 'Delete file',
        'delete_file_message'=> 'The file will be permanently deleted.',
    ],
];
