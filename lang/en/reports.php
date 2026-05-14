<?php
/**
 * Nexus 2.0 — English Translations (Reports — Sub-phase 4.5)
 */
defined('APP_ACCESS') or die('Direct access not allowed');

return [
    'reports' => [
        'page_title'    => 'Activity reports',
        'page_subtitle' => 'Generate a monthly summary of your tasks and export as CSV, Excel or PDF.',

        'filters_label' => 'Report filters',
        'field_type'    => 'Report type',
        'field_range'   => 'Date range',
        'field_user'    => 'User',

        'type_summary'  => 'Summary',
        'type_detailed' => 'Detailed',

        'range_weekly'  => 'Last week',
        'range_monthly' => 'Previous month',
        'range_custom'  => 'Custom',
        'range_from'    => 'From',
        'range_to'      => 'To',

        // Presets (only those not duplicated by top-level range buttons)
        'preset_thismonth' => 'This month',
        'preset_lastweek'  => 'Last week',
        'preset_last15'   => 'Last 15 days',
        'preset_last30'   => 'Last 30 days',
        'preset_thisyear' => 'This year',

        'export_as'        => 'Export:',
        'export_csv_done'  => 'CSV report downloaded.',
        'export_xlsx_done' => 'Excel report downloaded.',

        'meta_user'   => 'User',
        'meta_period' => 'Period',
        'meta_total'  => 'Total time',
        'meta_tasks'  => 'Tasks',
        'generated_at'=> 'Generated',

        'section_alliances' => 'Breakdown by alliance',
        'section_tasks'     => 'Tasks by alliance',
        'section_tags'      => 'Totals by tag',

        'chart_label' => 'Alliance breakdown chart',

        'col_alliance' => 'Alliance',
        'col_task'     => 'Task',
        'col_sessions' => 'Sessions',
        'col_tasks'    => 'Tasks',
        'col_tag'      => 'Tag',
        'col_time'     => 'Time',

        'no_tasks' => 'No tasks in the period.',
        'no_tags'  => 'No tags in the period.',

        'err_generate' => 'Could not generate the report.',
        'err_xlsx_lib' => 'Excel library failed to load. Check your connection.',
    ],
];
