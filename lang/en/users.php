<?php
/**
 * Nexus 2.0 — English Translations (Users)
 */
defined('APP_ACCESS') or die('Direct access not allowed');

return [
    'users' => [
        'page_title'           => 'Users',
        'page_subtitle'        => 'Manage accounts, roles and system permissions.',
        'btn_create'           => 'New user',

        'stat_total'           => 'Total',
        'stat_active'          => 'Active',
        'stat_admins'          => 'Admins',

        'search_placeholder'   => 'Search by name, username or email...',
        'filter_role'          => 'Filter by role',
        'filter_all_roles'     => 'All roles',
        'filter_status'        => 'Filter by status',
        'filter_all_status'    => 'All statuses',
        'filter_active'        => 'Active',
        'filter_inactive'      => 'Inactive',

        'col_user'             => 'User',
        'col_role'             => 'Role',
        'col_lang'             => 'Language',
        'col_last_login'       => 'Last login',
        'col_status'           => 'Status',
        'col_actions'          => 'Actions',
        'status_active'        => 'Active',
        'status_inactive'      => 'Inactive',
        'never_logged'         => 'Never',
        'you'                  => 'You',

        'btn_edit'             => 'Edit',
        'btn_delete'           => 'Delete',

        'empty_filtered_title' => 'No results',
        'empty_filtered_desc'  => 'Adjust filters or search terms.',

        'form_create_title'    => 'New user',
        'form_edit_title'      => 'Edit user',
        'field_username'       => 'Username',
        'field_username_help'  => '3-20 characters: letters, numbers and underscore.',
        'field_name'           => 'Full name',
        'field_email'          => 'Email',
        'field_password'       => 'Password',
        'field_password_help'  => 'Minimum 6 characters.',
        'field_password_help_edit' => 'Leave empty to keep current password.',
        'field_role'           => 'Role',
        'field_role_self'      => 'You cannot change your own role.',
        'field_lang'           => 'Preferred language',
        'field_active'         => 'Active user',
        'field_active_help'    => 'Inactive users cannot log in.',
        'field_schedule'       => 'Work schedule',
        'field_schedule_help'  => 'Enable working days and set morning and afternoon time blocks.',
        'day_monday'           => 'Monday',
        'day_tuesday'          => 'Tuesday',
        'day_wednesday'        => 'Wednesday',
        'day_thursday'         => 'Thursday',
        'day_friday'           => 'Friday',
        'day_saturday'         => 'Saturday',
        'day_sunday'           => 'Sunday',
        'field_photo'          => 'Profile photo',
        'field_photo_help'     => 'JPG, PNG or WebP. Max 2 MB.',
        'field_photo_change'   => 'Change photo',
        'field_photo_remove'   => 'Remove photo',

        'err_username'         => 'Invalid username. Use 3-20 letters, numbers or underscore.',
        'err_name'             => 'Name is required.',
        'err_email'            => 'Invalid email.',
        'err_password'         => 'Password must be at least 6 characters.',
        'err_generic'          => 'Could not save. Please check the data.',
        'err_network'          => 'Network error. Please try again.',
        'err_delete'           => 'Could not delete user.',
        'success'              => 'Changes saved.',
        'deleted'              => 'User deleted.',

        'delete_title'         => 'Delete user',
        'delete_message'       => '{name} will be permanently deleted. This action cannot be undone.',
    ],
];
