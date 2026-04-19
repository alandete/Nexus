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

<!-- Placeholder sub-fases siguientes -->
<div class="card">
    <div class="empty-state p-300">
        <div class="empty-state-icon"><i class="bi bi-list-task" aria-hidden="true"></i></div>
        <h3 class="empty-state-title"><?= __('tasks.upcoming_placeholder_title') ?></h3>
        <p class="empty-state-description"><?= __('tasks.upcoming_placeholder_desc') ?></p>
    </div>
</div>

<script>
window.__TASKS_ALLIANCES__ = <?= json_encode(array_values(array_map(fn($a) => [
    'id'    => $a['id'] ?? 0,
    'name'  => $a['name'] ?? '',
    'color' => $a['color'] ?? null,
], $activeAlliances)), JSON_UNESCAPED_UNICODE) ?>;
window.__TASKS_TAGS__ = <?= json_encode($allTags, JSON_UNESCAPED_UNICODE) ?>;
</script>
