<?php
/**
 * Nexus 2.0 — Tareas (Sub-fase 4.1: Cronometro y tarea activa)
 * Flujo hibrido: inicio inline con friccion-cero, validacion tardia al pausar/completar.
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();
$alliances = getAlliances();
$activeAlliances = array_filter($alliances, fn($a) => !empty($a['active']));

// Precargar tags
$allTags = [];
if (isDBAvailable()) {
    try {
        $stmt = getDB()->prepare("SELECT id, name, color FROM tags ORDER BY name ASC");
        $stmt->execute();
        $allTags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* ignore */ }
}
?>

<div class="page-header">
    <h1 class="page-title"><?= __('tasks.page_title') ?></h1>
    <p class="page-description"><?= __('tasks.page_subtitle') ?></p>
</div>

<!-- ============ TRACKER INLINE ============ -->
<section class="tracker-bar" aria-labelledby="tracker-title">
    <h2 class="visually-hidden" id="tracker-title"><?= __('tasks.tracker_title') ?></h2>

    <!-- Input con autocomplete + boton play -->
    <div class="tracker-input-row" id="trackerInputRow">
        <div class="tracker-input-wrapper">
            <i class="bi bi-stopwatch tracker-input-icon" aria-hidden="true"></i>
            <input type="text" id="trackerInput" class="tracker-input"
                   placeholder="<?= __('tasks.input_placeholder') ?>"
                   autocomplete="off"
                   aria-label="<?= __('tasks.input_label') ?>"
                   aria-autocomplete="list"
                   aria-expanded="false"
                   aria-controls="trackerAutocomplete">
            <div class="tracker-autocomplete d-none" id="trackerAutocomplete" role="listbox" aria-label="<?= __('tasks.autocomplete_label') ?>"></div>
        </div>
        <button type="button" class="tracker-play-btn" id="btnStartTimer"
                aria-label="<?= __('tasks.btn_start_timer') ?>"
                data-tooltip="<?= __('tasks.btn_start_timer') ?>" data-tooltip-position="left">
            <i class="bi bi-play-fill" aria-hidden="true"></i>
        </button>
    </div>

    <!-- Estado activo (compacto) -->
    <div class="tracker-active d-none" id="trackerActive" aria-live="polite">
        <div class="tracker-active-row">
            <div class="tracker-dot" aria-hidden="true"></div>
            <span class="tracker-task-title" id="trackerTaskTitle">—</span>
            <div class="tracker-task-meta" id="trackerTaskMeta">
                <!-- Chips de etiquetas/alianza via JS -->
            </div>
            <div class="tracker-time" id="trackerTime" aria-label="<?= __('tasks.elapsed_time') ?>">00:00:00</div>
            <div class="tracker-actions">
                <button type="button" class="btn-icon" id="btnEditTimer"
                        data-tooltip="<?= __('tasks.btn_edit') ?>" data-tooltip-position="top"
                        aria-label="<?= __('tasks.btn_edit') ?>">
                    <i class="bi bi-pencil" aria-hidden="true"></i>
                </button>
                <button type="button" class="btn-icon btn-icon-warning" id="btnPauseTimer"
                        data-tooltip="<?= __('tasks.btn_pause') ?>" data-tooltip-position="top"
                        aria-label="<?= __('tasks.btn_pause') ?>">
                    <i class="bi bi-pause-fill" aria-hidden="true"></i>
                </button>
                <button type="button" class="btn-icon btn-icon-success" id="btnStopTimer"
                        data-tooltip="<?= __('tasks.btn_stop') ?>" data-tooltip-position="top"
                        aria-label="<?= __('tasks.btn_stop') ?>">
                    <i class="bi bi-check-lg" aria-hidden="true"></i>
                </button>
                <button type="button" class="btn-icon btn-icon-danger" id="btnDiscardTimer"
                        data-tooltip="<?= __('tasks.btn_discard') ?>" data-tooltip-position="top"
                        aria-label="<?= __('tasks.btn_discard') ?>">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>
        </div>

        <!-- Alerta sutil si faltan datos -->
        <div class="tracker-incomplete d-none" id="trackerIncomplete" role="status">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span id="trackerIncompleteText"></span>
            <button type="button" class="tracker-incomplete-cta" id="btnCompleteData">
                <?= __('tasks.btn_complete_data') ?>
            </button>
        </div>
    </div>
</section>

<!-- ============ LISTADO DE TAREAS (Sub-fase 4.2) ============ -->
<section class="tasks-list-section" aria-labelledby="tasks-list-title">
    <h2 class="visually-hidden" id="tasks-list-title"><?= __('tasks.list_title') ?></h2>

    <!-- Tabs -->
    <div class="tabs tasks-tabs" role="tablist" aria-label="<?= __('tasks.tabs_label') ?>">
        <button type="button" class="tab active" id="tabActive" role="tab" aria-selected="true" aria-controls="panelActive" data-tab="active">
            <?= __('tasks.tab_active') ?>
            <span class="tab-count" id="countActive">0</span>
        </button>
        <button type="button" class="tab" id="tabScheduled" role="tab" aria-selected="false" aria-controls="panelScheduled" data-tab="scheduled">
            <?= __('tasks.tab_scheduled') ?>
            <span class="tab-count" id="countScheduled">0</span>
        </button>
        <button type="button" class="tab" id="tabHistory" role="tab" aria-selected="false" aria-controls="panelHistory" data-tab="history">
            <?= __('tasks.tab_history') ?>
            <span class="tab-count" id="countHistory">0</span>
        </button>
    </div>

    <!-- Barra de filtros -->
    <div class="filter-bar tasks-filter-bar" role="group" aria-label="<?= __('tasks.filters_label') ?>">
        <div class="filter-bar-search">
            <i class="bi bi-search filter-bar-search-icon" aria-hidden="true"></i>
            <input type="search" id="filterSearch" class="form-control"
                   placeholder="<?= __('tasks.filter_search_placeholder') ?>"
                   aria-label="<?= __('tasks.filter_search_label') ?>">
        </div>

        <input type="date" id="filterDateFrom" class="form-control filter-bar-date"
               aria-label="<?= __('tasks.filter_date_from') ?>"
               title="<?= __('tasks.filter_date_from') ?>">

        <input type="date" id="filterDateTo" class="form-control filter-bar-date"
               aria-label="<?= __('tasks.filter_date_to') ?>"
               title="<?= __('tasks.filter_date_to') ?>">

        <select id="filterAlliance" class="form-control filter-bar-select" aria-label="<?= __('tasks.filter_alliance') ?>">
            <option value=""><?= __('tasks.filter_all_alliances') ?></option>
            <?php foreach ($activeAlliances as $a): ?>
            <option value="<?= (int) $a['id'] ?>"><?= htmlspecialchars($a['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <select id="filterPriority" class="form-control filter-bar-select" aria-label="<?= __('tasks.filter_priority') ?>">
            <option value=""><?= __('tasks.filter_all_priorities') ?></option>
            <option value="urgent"><?= __('tasks.priority_urgent') ?></option>
            <option value="high"><?= __('tasks.priority_high') ?></option>
            <option value="medium"><?= __('tasks.priority_medium') ?></option>
            <option value="low"><?= __('tasks.priority_low') ?></option>
        </select>

        <select id="filterTag" class="form-control filter-bar-select" aria-label="<?= __('tasks.filter_tag') ?>">
            <option value=""><?= __('tasks.filter_all_tags') ?></option>
            <?php foreach ($allTags as $tag): ?>
            <option value="<?= (int) $tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?></option>
            <?php endforeach; ?>
        </select>

        <button type="button" class="btn btn-subtle btn-sm" id="btnClearFilters"
                data-tooltip="<?= __('tasks.filter_clear') ?>" data-tooltip-position="top">
            <i class="bi bi-x-circle" aria-hidden="true"></i>
            <?= __('tasks.filter_clear') ?>
        </button>
    </div>

    <!-- Panel: activas -->
    <div class="tasks-panel" id="panelActive" role="tabpanel" aria-labelledby="tabActive">
        <div class="tasks-panel-loading d-none" id="loadingActive">
            <span class="spinner" aria-hidden="true"></span> <?= __('common.loading') ?>
        </div>
        <div class="tasks-panel-content" id="contentActive"></div>
    </div>

    <!-- Panel: proximas -->
    <div class="tasks-panel d-none" id="panelScheduled" role="tabpanel" aria-labelledby="tabScheduled" hidden>
        <div class="tasks-panel-content" id="contentScheduled"></div>
    </div>

    <!-- Panel: historial -->
    <div class="tasks-panel d-none" id="panelHistory" role="tabpanel" aria-labelledby="tabHistory" hidden>
        <div class="tasks-panel-content" id="contentHistory"></div>
    </div>
</section>

<script>
window.__TASKS_ALLIANCES__ = <?= json_encode(array_values(array_map(fn($a) => [
    'id'    => $a['id'] ?? 0,
    'name'  => $a['name'] ?? '',
    'color' => $a['color'] ?? null,
], $activeAlliances)), JSON_UNESCAPED_UNICODE) ?>;
window.__TASKS_TAGS__ = <?= json_encode($allTags, JSON_UNESCAPED_UNICODE) ?>;
</script>
