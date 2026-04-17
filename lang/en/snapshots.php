<?php
/**
 * Nexus 2.0 — English Translations (Snapshots / Backups)
 */
defined('APP_ACCESS') or die('Direct access not allowed');

return [
    'snapshots' => [
        'page_title'    => 'Backups',
        'page_subtitle' => 'System snapshots to protect your data and configuration.',

        'btn_create'         => 'New backup',
        'btn_create_confirm' => 'Create backup',
        'btn_download'       => 'Download',
        'btn_restore'        => 'Restore',
        'btn_restore_confirm'=> 'Restore',
        'btn_delete'         => 'Delete',
        'btn_favorite'       => 'Mark as favorite',
        'btn_unfavorite'     => 'Remove favorite',

        'stat_total'     => 'Backups',
        'stat_size'      => 'Space used',
        'stat_last'      => 'Last backup',
        'stat_favorites' => 'Favorites',

        'rotation_data'  => 'Data',
        'rotation_full'  => 'Full',
        'rotation_slots' => 'slots used',
        'rotation_hint'  => 'Favorite backups do not count toward automatic rotation.',

        'search_placeholder' => 'Search by name or note...',
        'filter_type'        => 'Filter by type',
        'filter_all'         => 'All',
        'filter_data'        => 'Data',
        'filter_full'        => 'Full',
        'filter_favorites'   => 'Favorites',

        'empty_title'          => 'No backups',
        'empty_desc'           => 'No backups saved yet. Create the first one to protect your information.',
        'empty_filtered_title' => 'No results',
        'empty_filtered_desc'  => 'Adjust filters to see more backups.',

        'type_data'       => 'Data',
        'type_data_desc'  => 'JSON files + templates + database. Lightweight, restorable from the UI.',
        'type_full'       => 'Full',
        'type_full_desc'  => 'Entire project: code, configuration and data. Download only.',
        'full_hint'       => 'Full backups can only be downloaded. Restoration is manual.',

        'protected'         => 'Protected',
        'protected_tooltip' => 'Not deleted by automatic rotation.',
        'delete_locked'     => 'Unfavorite to delete',

        'form_create_title'      => 'Create backup',
        'field_type'             => 'Backup type',
        'field_note'             => 'Note (optional)',
        'field_note_placeholder' => 'E.g.: before change X',
        'field_note_help'        => 'Short text to identify this backup later.',
        'field_cleanup'          => 'Clean temporary files before creating',
        'field_cleanup_help'     => 'Removes temporary processing files (/temp/) before creating the backup.',
        'create_hint_data'       => 'JSON files, templates and database will be saved. This backup can be restored from here.',
        'create_hint_full'       => 'Entire project will be packaged. Useful for full migration. Cannot be restored from the UI, only downloaded.',
        'creating'               => 'Creating...',

        'restore_title'   => 'Restore backup',
        'restore_message' => 'Current data will be overwritten with the content of this backup. This cannot be undone.

File: {file}',

        'delete_title'   => 'Delete backup',
        'delete_message' => 'Backup "{file}" will be permanently deleted.',

        'created'      => 'Backup created successfully.',
        'restored'     => 'Backup restored successfully.',
        'deleted'      => 'Backup deleted.',
        'favorited'    => 'Marked as favorite.',
        'unfavorited'  => 'Favorite removed.',
        'err_create'   => 'Could not create backup.',
        'err_restore'  => 'Could not restore.',
        'err_delete'   => 'Could not delete.',
        'err_favorite' => 'Could not update favorite.',
    ],
];
