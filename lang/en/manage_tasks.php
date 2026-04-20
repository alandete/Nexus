<?php
/**
 * Nexus 2.0 — English Translations (Manage tasks)
 */
defined('APP_ACCESS') or die('Direct access not allowed');

return [
    'manage_tasks' => [
        'page_title'    => 'Manage tasks',
        'page_subtitle' => 'Manage tags, import or export tasks and perform data maintenance.',

        // ─── Tags ───
        'tabs_label'              => 'Management sections',
        'tags_title'              => 'Tags',
        'tags_desc'               => 'Organize tasks with custom tags. A task can have multiple tags.',
        'new_tag_placeholder'     => 'New tag...',

        'stat_total'  => 'Total',
        'stat_in_use' => 'In use',
        'stat_unused' => 'Unused',

        'col_name'  => 'Name',
        'col_color' => 'Color',
        'col_usage' => 'Usage',

        'task_singular' => 'task',
        'task_plural'   => 'tasks',
        'not_in_use'    => 'Unused',

        'empty_tags_title' => 'No tags yet',
        'empty_tags_desc'  => 'Create the first tag to start categorizing your tasks.',
        'no_results_title' => 'No matches',
        'no_results_desc'  => 'No tag matches the search.',

        'create_tag_title' => 'New tag',
        'edit_tag_title'   => 'Edit tag',
        'field_name'       => 'Name',
        'field_color'      => 'Color',
        'field_color_hex'  => 'Hex code',
        'color_help'       => 'Identifier color for the tag. Must be a valid hex (#RRGGBB).',

        'tag_created'  => 'Tag created.',
        'tag_updated'  => 'Tag updated.',
        'tag_deleted'  => 'Tag deleted.',

        'err_name_required' => 'Name is required.',
        'err_save_tag'      => 'Could not save the tag.',
        'err_delete_tag'    => 'Could not delete the tag.',

        'delete_tag_title'     => 'Delete tag',
        'delete_tag_message'   => 'Tag "{name}" will be deleted.',
        'delete_tag_warn_usage'=> 'It is assigned to {n} {label} — it will be removed from all of them.',
        'delete_tag_warn_undo' => 'This action cannot be undone.',

        // ─── Import / Export (placeholder) ───
        'io_title' => 'Import and export',
        'io_desc'  => 'Transfer tasks between environments, import from spreadsheets or export as JSON/CSV/Excel.',
        'io_placeholder_title' => 'Coming soon',
        'io_placeholder_desc'  => 'Task import and export will be enabled in an upcoming sub-phase.',

        // ─── Cleanup (placeholder) ───
        'cleanup_title' => 'Data cleanup',
        'cleanup_desc'  => 'Bulk delete old or completed tasks to keep the database lightweight.',
        'cleanup_placeholder_title' => 'Coming soon',
        'cleanup_placeholder_desc'  => 'Bulk cleanup operations will be enabled in an upcoming sub-phase.',
    ],
];
