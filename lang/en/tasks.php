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
        'btn_delete'          => 'Delete',

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
        'err_delete'         => 'Could not delete the task.',
        'task_deleted'       => 'Task deleted.',
        'delete_title'       => 'Delete task',
        'delete_message'     => 'Task "{title}" and all its time entries will be deleted. This action cannot be undone.',

        // Placeholder for upcoming sub-phases
        // List (sub-phase 4.2)
        'list_title'   => 'Task list',
        'tabs_label'   => 'List views',
        'tab_active'   => 'Active tasks',
        'tab_scheduled'=> 'Upcoming tasks',
        'tab_yesterday'=> 'Yesterday\'s tasks',
        'tab_history'  => 'History',

        'col_alliance'   => 'Alliance',
        'col_task'       => 'Task',
        'col_status'     => 'Status',
        'col_tags'       => 'Tags',
        'col_total_time' => 'Time',
        'entry_count_hint' => 'Number of entries',
        'is_overdue'       => 'Overdue',

        'filters_label'            => 'List filters',
        'filter_search_placeholder'=> 'Search by title or alliance...',
        'filter_search_label'      => 'Search task',
        'filter_date_from'         => 'From',
        'filter_date_to'           => 'To',
        'filter_alliance'          => 'Filter by alliance',
        'filter_all_alliances'     => 'All alliances',
        'filter_priority'          => 'Filter by priority',
        'filter_all_priorities'    => 'All priorities',
        'filter_tag'               => 'Filter by tag',
        'filter_all_tags'          => 'All tags',
        'filter_clear'             => 'Clear',

        'empty_active_title'     => 'No active tasks',
        'empty_active_desc'      => 'Active or paused tasks will appear here. Start a timer to begin.',
        'empty_scheduled_title'  => 'No upcoming tasks',
        'empty_scheduled_desc'   => 'Pending tasks with no logged time will appear here, ordered by priority and due date.',
        'empty_history_title'    => 'No history in this range',
        'empty_history_desc'     => 'Adjust the date range or start logging time to see history here.',
        'empty_yesterday_title'  => 'No activity yesterday',
        'empty_yesterday_desc'   => 'Tasks you worked on yesterday will appear here, with total time and session count.',

        'btn_resume'   => 'Resume',
        'is_running'   => 'Running',
        'total_time'   => 'Total time',
        'err_already_running' => 'A timer is already running. Pause or complete it first.',

        // Placeholder for upcoming sub-phases
        'upcoming_placeholder_title' => 'Kanban and reports',
        'upcoming_placeholder_desc'  => 'Upcoming sub-phases bring: priority kanban, reports (PDF/Excel/CSV) and due-dates dashboard.',
    ],
];
