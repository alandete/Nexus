<?php
/**
 * Nexus 2.0 — English Translations (Tasks)
 */
defined('APP_ACCESS') or die('Direct access not allowed');

return [
    'tasks' => [
        'page_title'    => 'Tasks',
        'page_subtitle' => 'Track your time, organize tasks and stay on top of deliverables.',

        // Tracker (sub-phase 4.1)
        'tracker_title'       => 'Timer',
        'tracker_empty_title' => 'No active timer',
        'tracker_empty_desc'  => 'Start the timer when you begin working. You can pause or complete the task when done.',
        'elapsed_time'        => 'Elapsed time',

        // Inline input (friction-free)
        'input_placeholder'   => 'What are you working on? Type and press Enter...',
        'input_label'         => 'Task name',
        'autocomplete_label'  => 'Suggestions of existing tasks',

        // Buttons
        'btn_start_timer'     => 'Start timer',
        'btn_start'           => 'Start',
        'btn_pause'           => 'Pause',
        'btn_stop'            => 'Complete',
        'btn_edit'            => 'Edit',
        'btn_discard'         => 'Discard',
        'btn_discard_confirm' => 'Discard timer',
        'btn_add_tag'         => 'Add',
        'btn_complete_data'   => 'Complete info',

        // Fields
        'field_task'         => 'Task',
        'field_description'  => 'Description',
        'field_alliance'     => 'Alliance',
        'field_tags'         => 'Tags',
        'field_priority'     => 'Priority',
        'field_due_date'     => 'Due date',

        // Priorities
        'priority_low'    => 'Low',
        'priority_medium' => 'Medium',
        'priority_high'   => 'High',
        'priority_urgent' => 'Urgent',

        // Statuses
        'status_pending'     => 'Pending',
        'status_in_progress' => 'In progress',
        'status_paused'      => 'Paused',
        'status_completed'   => 'Completed',

        // Form
        'start_title'          => 'Start timer',
        'edit_task_title'      => 'Edit task',
        'complete_data_title'  => 'Complete the information',
        'task_placeholder'     => 'Describe what you are working on...',
        'task_help'            => 'If a task with that name exists, it will be resumed. Otherwise a new one is created.',
        'description_placeholder' => 'Add details, notes or extra context...',
        'alliance_placeholder' => 'Select alliance...',
        'tag_new_placeholder'  => 'New tag...',
        'no_tags_yet'          => 'No tags yet. Create the first one:',
        'due_date_help'        => 'Optional. If set, the task will appear in the due-dates dashboard.',
        'start_hint'           => 'The timer starts when you confirm. You can edit, pause or complete from the main buttons.',
        'incomplete_msg'       => 'Add {fields} to be able to pause or complete.',
        'force_complete_msg'   => 'Fill in alliance and tags before pausing or completing the task.',

        // Empty states
        'no_alliance' => 'No alliance',
        'no_tags'     => 'No tags',

        // Confirmation actions
        'stop_title'      => 'Complete task',
        'stop_message'    => 'The recorded time will be saved and the task marked as completed.',
        'discard_title'   => 'Discard timer',
        'discard_message' => 'The recorded time will be deleted without saving. This action cannot be undone.',

        // Messages
        'timer_started'   => 'Timer started.',
        'timer_paused'    => 'Timer paused ({duration})',
        'timer_stopped'   => 'Task completed ({duration})',
        'timer_discarded' => 'Timer discarded.',
        'task_updated'    => 'Changes saved.',
        'starting'        => 'Starting...',

        // Validation
        'err_title'          => 'Task name is required.',
        'err_title_required' => 'Enter a task name before starting.',
        'err_alliance'       => 'Select an alliance.',
        'err_tags'           => 'Select at least one tag.',
        'err_start'          => 'Could not start the timer.',
        'err_pause'          => 'Could not pause.',
        'err_stop'           => 'Could not complete the task.',
        'err_discard'        => 'Could not discard.',
        'err_update'         => 'Could not save changes.',
        'err_create_tag'     => 'Could not create the tag.',

        // Placeholder for upcoming sub-phases
        'upcoming_placeholder_title' => 'Upcoming tasks, list and kanban',
        'upcoming_placeholder_desc'  => 'This area will be built in upcoming sub-phases: list with filters, priority kanban, reports and due dates dashboard.',
    ],
];
