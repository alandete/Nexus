<?php
/**
 * Nexus 2.0 — Traducciones Español (Reportes — Sub-fase 4.5)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

return [
    'reports' => [
        'page_title'    => 'Reportes de actividades',
        'page_subtitle' => 'Genera un resumen mensual de tus tareas y exporta en CSV, Excel o PDF.',

        'filters_label' => 'Filtros del reporte',
        'field_type'     => 'Tipo de reporte',
        'field_range'    => 'Rango de fechas',
        'field_user'     => 'Usuario',
        'field_alliance' => 'Alianza',
        'field_tags'     => 'Etiqueta',
        'all_alliances'  => 'Todas las alianzas',
        'all_tags'       => 'Todas las etiquetas',
        'clear_filters'  => 'Limpiar filtros',
        'bar_chart_label'=> 'Distribución de tiempo por período',

        'type_summary'  => 'Resumido',
        'type_detailed' => 'Detallado',

        'range_weekly'  => 'Última semana',
        'range_monthly' => 'Mes anterior',
        'range_custom'  => 'Personalizado',
        'range_from'    => 'Desde',
        'range_to'      => 'Hasta',

        // Presets del date picker (solo los que no duplican los botones de rango)
        'preset_lastweek' => 'La semana pasada',
        'preset_last15'   => 'Últimos 15 días',
        'preset_last30'   => 'Últimos 30 días',
        'preset_thisyear' => 'Este año',

        'export_as'        => 'Exportar:',
        'export_csv_done'  => 'Reporte CSV descargado.',
        'export_xlsx_done' => 'Reporte Excel descargado.',

        // Vista
        'meta_user'   => 'Usuario',
        'meta_period' => 'Periodo',
        'meta_total'  => 'Tiempo total',
        'meta_tasks'  => 'Tareas',
        'generated_at'=> 'Generado',

        'section_alliances' => 'Distribución por alianza',
        'section_tasks'     => 'Tareas por alianza',
        'section_tags'      => 'Total por etiqueta',

        'chart_label' => 'Gráfico de distribución por alianza',

        'col_alliance' => 'Alianza',
        'col_task'     => 'Tarea',
        'col_sessions' => 'Sesiones',
        'col_tasks'    => 'Tareas',
        'col_tag'      => 'Etiqueta',
        'col_time'     => 'Tiempo',

        'no_tasks' => 'Sin tareas en el periodo.',
        'no_tags'  => 'Sin etiquetas en el periodo.',

        'err_generate' => 'No se pudo generar el reporte.',
        'err_xlsx_lib' => 'La librería de Excel no se cargó. Revisa tu conexión.',
    ],
];
