<?php
/**
 * Nexus 2.0 — Alianzas corporativas (procesador de plantillas)
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();
if (!hasPermission($currentUser, 'alliances', 'read')) {
    include BASE_PATH . '/pages/403.php'; return;
}

$canWrite = hasPermission($currentUser, 'alliances', 'write');

$allianceSlug = sanitize($_GET['alliance'] ?? 'unis');
$alliances    = getAlliances();
$allianceData = $alliances[$allianceSlug] ?? null;

$allianceSections = [
    'unis' => ['inicio', 'unidad'],
    'unab' => ['inicio', 'curso'],
];
$sections = $allianceSections[$allianceSlug] ?? [];

// Disponibilidad por LMS y sección
$sectionsLms = [];
foreach (['moodle', 'canvas'] as $lms) {
    $sectionsLms[$lms] = [];
    foreach ($sections as $sec) {
        $sectionsLms[$lms][$sec] = !empty($allianceData)
            && is_dir(TEMPLATES_PATH . "/{$allianceSlug}/{$lms}/{$sec}");
    }
}
?>

<!-- Breadcrumb -->
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= url('home') ?>" class="breadcrumb-link"><?= __('menu.home') ?></a>
    <i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>
    <span class="breadcrumb-current" aria-current="page"><?= __('alliances.page_title') ?></span>
</nav>

<div class="page-header">
    <div>
        <h1 class="page-title"><?= htmlspecialchars($allianceData['name'] ?? strtoupper($allianceSlug)) ?></h1>
        <?php if (!empty($allianceData['fullname'])): ?>
        <p class="page-description"><?= htmlspecialchars($allianceData['fullname']) ?></p>
        <?php endif; ?>
    </div>
</div>

<?php
// Alianzas con UI de procesamiento lista (control explícito)
$readyForProcessing = ['unis'];

$allianceReady = in_array($allianceSlug, $readyForProcessing)
    && !empty($allianceData)
    && !empty($allianceData['active']);

if (!$allianceReady):
    $comingSoonKey = 'alliances.coming_soon_' . $allianceSlug;
    $comingSoonMsg = __($comingSoonKey) ?: 'Contenido próximamente...';
?>
<div class="alliance-coming-soon">
    <i class="bi bi-hourglass-split alliance-coming-soon-icon" aria-hidden="true"></i>
    <p class="alliance-coming-soon-text"><?= htmlspecialchars($comingSoonMsg) ?></p>
</div>
<?php return; endif; ?>

<!-- Selector de LMS -->
<div class="alliance-lms-selector" id="allianceLmsSelector">
    <span class="alliance-lms-selector-label">Selecciona la plataforma donde vas a trabajar:</span>
    <div class="alliance-lms-btns">
        <?php
        $moodleOk = array_reduce(array_values($sectionsLms['moodle'] ?? []), fn($c, $v) => $c || $v, false);
        $canvasOk = array_reduce(array_values($sectionsLms['canvas'] ?? []), fn($c, $v) => $c || $v, false);
        ?>
        <button type="button" class="alliance-lms-btn" data-lms="moodle" aria-pressed="false">
            <i class="bi bi-mortarboard" aria-hidden="true"></i>
            Moodle
            <?php if (!$moodleOk): ?>
            <span class="lozenge lozenge-warning">Pendiente</span>
            <?php endif; ?>
        </button>
        <button type="button" class="alliance-lms-btn" data-lms="canvas" aria-pressed="false">
            <i class="bi bi-brush" aria-hidden="true"></i>
            Canvas
            <?php if (!$canvasOk): ?>
            <span class="lozenge lozenge-warning">Pendiente</span>
            <?php endif; ?>
        </button>
    </div>
</div>

<!-- Aviso LMS pendiente (se muestra si no hay plantillas para el LMS seleccionado) -->
<div class="alliance-pending-notice" id="alliancePendingNotice" hidden>
    <i class="bi bi-tools alliance-pending-icon" aria-hidden="true"></i>
    <p><?= __('alliances.lms_unavailable_notice') ?></p>
</div>

<!-- Contenido: pestañas de sección + formularios (oculto hasta seleccionar LMS) -->
<div id="allianceContent" hidden>

    <!-- Pestañas de sección -->
    <?php if (count($sections) > 1): ?>
    <div class="tabs alliance-section-tabs" id="sectionTabs" role="tablist" aria-label="Sección">
        <?php foreach ($sections as $i => $sec): ?>
        <button type="button"
                class="tab<?= $i === 0 ? ' active' : '' ?>"
                id="tabBtn-<?= $sec ?>"
                role="tab"
                aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                aria-controls="tabPanel-<?= $sec ?>"
                data-section="<?= $sec ?>">
            <?= htmlspecialchars(__('alliances.tab_' . $sec) ?: ucfirst($sec)) ?>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ══════════════════════════════════════════
         PESTAÑA: INICIO (UNIS)
         ══════════════════════════════════════════ -->
    <?php if (in_array('inicio', $sections, true)): ?>
    <div class="tab-content" id="tabPanel-inicio" role="tabpanel" aria-labelledby="tabBtn-inicio">

        <!-- Generalidades -->
        <section class="card alliance-section" id="section-generalidades">
            <div class="card-header">
                <h2 class="card-title"><?= __('alliances.generalidades') ?></h2>
                <p class="card-description"><?= __('alliances.generalidades_desc') ?></p>
            </div>
            <div class="card-body alliance-generalidades-fields">
                <div class="form-group">
                    <label class="form-label sr-only" for="field_docente"><?= __('alliances.field_docente') ?></label>
                    <input type="url" class="form-control alliance-url-field"
                           id="field_docente" name="docente"
                           placeholder="<?= htmlspecialchars(__('alliances.field_docente')) ?> — URL"
                           autocomplete="off"
                           data-section="inicio">
                </div>
                <div class="form-group">
                    <label class="form-label sr-only" for="field_silabo"><?= __('alliances.field_silabo') ?></label>
                    <input type="url" class="form-control alliance-url-field"
                           id="field_silabo" name="silabo"
                           placeholder="<?= htmlspecialchars(__('alliances.field_silabo')) ?> — URL"
                           autocomplete="off"
                           data-section="inicio">
                </div>
                <div class="form-group">
                    <label class="form-label sr-only" for="field_ruta"><?= __('alliances.field_ruta') ?></label>
                    <input type="url" class="form-control alliance-url-field"
                           id="field_ruta" name="ruta"
                           placeholder="<?= htmlspecialchars(__('alliances.field_ruta')) ?> — URL"
                           autocomplete="off"
                           data-section="inicio">
                </div>
            </div>
        </section>

        <!-- Evaluación -->
        <section class="card alliance-section" id="section-evaluacion">
            <div class="card-header">
                <h2 class="card-title"><?= __('alliances.evaluacion') ?></h2>
                <p class="card-description"><?= __('alliances.evaluacion_desc') ?></p>
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table class="table alliance-eval-table">
                        <colgroup>
                            <col class="col-eval-unit">
                            <col class="col-eval-activity">
                            <col class="col-eval-weight">
                        </colgroup>
                        <thead>
                            <tr>
                                <th><?= __('alliances.col_unit') ?></th>
                                <th><?= __('alliances.col_activity') ?></th>
                                <th><?= __('alliances.col_weight') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($u = 1; $u <= 4; $u++): ?>
                            <tr class="eval-row-first">
                                <td class="alliance-eval-unit" rowspan="2"><?= __('alliances.col_unit') ?> <?= $u ?></td>
                                <td>
                                    <input type="text" class="form-control form-control-sm alliance-eval-field"
                                           name="eval_u<?= $u ?>_act_1" id="eval_u<?= $u ?>_act_1"
                                           placeholder="<?= htmlspecialchars(__('alliances.field_activity')) ?> 1"
                                           autocomplete="off" data-section="inicio">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm alliance-eval-field"
                                           name="eval_u<?= $u ?>_pond_1" id="eval_u<?= $u ?>_pond_1"
                                           placeholder="%" autocomplete="off" data-section="inicio">
                                </td>
                            </tr>
                            <tr class="eval-row-second">
                                <td>
                                    <input type="text" class="form-control form-control-sm alliance-eval-field"
                                           name="eval_u<?= $u ?>_act_2" id="eval_u<?= $u ?>_act_2"
                                           placeholder="<?= htmlspecialchars(__('alliances.field_activity')) ?> 2"
                                           autocomplete="off" data-section="inicio">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm alliance-eval-field"
                                           name="eval_u<?= $u ?>_pond_2" id="eval_u<?= $u ?>_pond_2"
                                           placeholder="%" autocomplete="off" data-section="inicio">
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </div><!-- /tabPanel-inicio -->
    <?php endif; ?>

    <!-- ══════════════════════════════════════════
         PESTAÑA: UNIDAD (UNIS)
         ══════════════════════════════════════════ -->
    <?php if (in_array('unidad', $sections, true)): ?>
    <div class="tab-content d-none" id="tabPanel-unidad" role="tabpanel" aria-labelledby="tabBtn-unidad">

        <!-- Título -->
        <section class="card alliance-section" id="section-titulo">
            <div class="card-header">
                <h2 class="card-title"><?= __('alliances.titulo') ?></h2>
            </div>
            <div class="card-body alliance-section-fields alliance-section-fields--asymmetric">
                <div class="form-group">
                    <label class="form-label sr-only" for="field_unidad"><?= __('alliances.field_unidad_num') ?></label>
                    <select class="form-control" id="field_unidad" name="unidad" data-section="unidad">
                        <option value=""><?= htmlspecialchars(__('alliances.field_unidad_num')) ?>...</option>
                        <?php for ($i = 1; $i <= 10; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label sr-only" for="field_nombre"><?= __('alliances.field_nombre') ?></label>
                    <input type="text" class="form-control" id="field_nombre" name="nombre"
                           placeholder="<?= htmlspecialchars(__('alliances.field_nombre')) ?>"
                           autocomplete="off" data-section="unidad">
                    <p class="form-helper"><?= __('alliances.field_nombre_help') ?></p>
                </div>
            </div>
        </section>

        <!-- Audio -->
        <section class="card alliance-section" id="section-audio">
            <div class="card-header">
                <h2 class="card-title"><?= __('alliances.audio') ?></h2>
            </div>
            <div class="card-body alliance-section-fields alliance-section-fields--asymmetric">
                <div class="form-group">
                    <label class="form-label sr-only" for="field_audio"><?= __('alliances.field_audio') ?></label>
                    <input type="url" class="form-control alliance-url-field"
                           id="field_audio" name="audio"
                           placeholder="<?= htmlspecialchars(__('alliances.field_audio')) ?> — URL"
                           autocomplete="off" data-section="unidad">
                </div>
                <div class="form-group">
                    <label class="form-label sr-only" for="field_transcripcion"><?= __('alliances.field_transcripcion') ?></label>
                    <textarea class="form-control" id="field_transcripcion" name="transcripcion"
                              rows="5"
                              placeholder="<?= htmlspecialchars(__('alliances.field_transcripcion')) ?>"
                              data-section="unidad"></textarea>
                    <p class="form-helper"><?= __('alliances.field_transcripcion_help') ?></p>
                </div>
            </div>
        </section>

        <!-- Temario -->
        <section class="card alliance-section" id="section-temario">
            <div class="card-header">
                <h2 class="card-title"><?= __('alliances.temario') ?></h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label sr-only" for="field_imagen"><?= __('alliances.field_imagen') ?></label>
                    <input type="url" class="form-control alliance-url-field"
                           id="field_imagen" name="imagen"
                           placeholder="<?= htmlspecialchars(__('alliances.field_imagen')) ?> — URL"
                           autocomplete="off" data-section="unidad">
                </div>
            </div>
        </section>

        <!-- Resultado de aprendizaje -->
        <section class="card alliance-section" id="section-aprendizaje">
            <div class="card-header">
                <h2 class="card-title"><?= __('alliances.aprendizaje') ?></h2>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label sr-only" for="field_aprendizaje"><?= __('alliances.field_aprendizaje') ?></label>
                    <textarea class="form-control" id="field_aprendizaje" name="aprendizaje"
                              rows="5"
                              placeholder="<?= htmlspecialchars(__('alliances.field_aprendizaje')) ?>"
                              data-section="unidad"></textarea>
                    <p class="form-helper"><?= __('alliances.field_aprendizaje_help') ?></p>
                </div>
            </div>
        </section>

        <!-- Glosario -->
        <section class="card alliance-section" id="section-glosario">
            <div class="card-header">
                <h2 class="card-title"><?= __('alliances.glosario') ?></h2>
            </div>
            <div class="card-body alliance-section-fields--stacked">
                <div class="form-group">
                    <label class="form-label sr-only" for="field_destacados_glosario"><?= __('alliances.field_destacados') ?></label>
                    <input type="text" class="form-control"
                           id="field_destacados_glosario" name="destacados_glosario"
                           placeholder="<?= htmlspecialchars(__('alliances.field_destacados')) ?>"
                           autocomplete="off" data-section="unidad">
                    <p class="form-helper"><?= __('alliances.field_destacados_help') ?></p>
                </div>
                <div class="form-group">
                    <label class="form-label sr-only" for="field_glosario"><?= __('alliances.field_glosario') ?></label>
                    <textarea class="form-control" id="field_glosario" name="glosario"
                              rows="6"
                              placeholder="<?= htmlspecialchars(__('alliances.field_glosario')) ?>"
                              data-section="unidad"></textarea>
                    <p class="form-helper"><?= __('alliances.field_glosario_help') ?></p>
                </div>
            </div>
        </section>

        <!-- Recursos -->
        <section class="card alliance-section" id="section-recursos">
            <div class="card-header">
                <h2 class="card-title"><?= __('alliances.recursos') ?></h2>
            </div>
            <div class="card-body alliance-section-fields--stacked">
                <div class="form-group">
                    <label class="form-label sr-only" for="field_recursos_academicos"><?= __('alliances.field_recursos_academicos') ?></label>
                    <textarea class="form-control" id="field_recursos_academicos" name="recursos_academicos"
                              rows="5"
                              placeholder="<?= htmlspecialchars(__('alliances.field_recursos_academicos')) ?>"
                              data-section="unidad"></textarea>
                    <p class="form-helper"><?= __('alliances.field_recursos_help') ?></p>
                </div>
                <div class="form-group">
                    <label class="form-label sr-only" for="field_conoce_mas"><?= __('alliances.field_conoce_mas') ?></label>
                    <textarea class="form-control" id="field_conoce_mas" name="conoce_mas"
                              rows="5"
                              placeholder="<?= htmlspecialchars(__('alliances.field_conoce_mas')) ?>"
                              data-section="unidad"></textarea>
                    <p class="form-helper"><?= __('alliances.field_recursos_help') ?></p>
                </div>
            </div>
        </section>

        <!-- Multimedia y Takeaway -->
        <section class="card alliance-section" id="section-multimedia">
            <div class="card-header">
                <h2 class="card-title"><?= __('alliances.multimedia_takeaway') ?></h2>
            </div>
            <div class="card-body alliance-section-fields">
                <div class="form-group">
                    <label class="form-label sr-only" for="field_multimedia"><?= __('alliances.field_multimedia') ?></label>
                    <input type="text" class="form-control"
                           id="field_multimedia" name="multimedia"
                           placeholder="<?= htmlspecialchars(__('alliances.field_multimedia')) ?>"
                           autocomplete="off" data-section="unidad">
                    <p class="form-helper"><?= __('alliances.field_multimedia_help') ?></p>
                </div>
                <div class="form-group">
                    <label class="form-label sr-only" for="field_takeaway"><?= __('alliances.field_takeaway') ?></label>
                    <input type="url" class="form-control alliance-url-field"
                           id="field_takeaway" name="takeaway"
                           placeholder="<?= htmlspecialchars(__('alliances.field_takeaway')) ?> — URL"
                           autocomplete="off" data-section="unidad">
                </div>
            </div>
        </section>

    </div><!-- /tabPanel-unidad -->
    <?php endif; ?>

    <!-- Barra de acciones -->
    <?php if ($canWrite): ?>
    <div class="alliance-action-bar" id="allianceActionBar">
        <button type="button" class="btn btn-subtle" id="btnAllianceClean">
            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
            <?= __('alliances.btn_clean') ?>
        </button>
        <button type="button" class="btn btn-primary" id="btnAllianceProcess">
            <i class="bi bi-play-fill" aria-hidden="true"></i>
            <span id="btnAllianceProcessLabel"><?= __('alliances.btn_process') ?></span>
        </button>
    </div>
    <?php endif; ?>

</div><!-- /allianceContent -->

<!-- Slide panel: resultado -->
<div class="slide-panel slide-panel--wide" id="allianceResultPanel" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="slide-panel-header">
        <h2 class="slide-panel-title" id="allianceResultTitle">Resultado</h2>
        <button class="slide-panel-close btn-icon" type="button" aria-label="Cerrar panel">
            <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
    </div>
    <div class="slide-panel-body">
        <div id="allianceWarnings" hidden>
            <div class="alliance-warnings-list" id="allianceWarningsList"></div>
        </div>
        <div class="form-group" id="allianceResultGroup" hidden>
            <div class="alliance-result-actions">
                <button type="button" class="btn btn-subtle btn-sm" id="btnAllianceCopy">
                    <i class="bi bi-clipboard" aria-hidden="true"></i>
                    <?= __('alliances.js.btn_copy') ?>
                </button>
            </div>
            <textarea class="form-control alliance-result-textarea" id="allianceResultHtml"
                      readonly rows="20" spellcheck="false" autocomplete="off"></textarea>
        </div>
    </div>
</div>

<script>
window.__ALLIANCE_PAGE__ = {
    slug:        <?= json_encode($allianceSlug) ?>,
    sections:    <?= json_encode($sections) ?>,
    sectionsLms: <?= json_encode($sectionsLms, JSON_UNESCAPED_UNICODE) ?>,
    canWrite:    <?= $canWrite ? 'true' : 'false' ?>,
    csrf:        <?= json_encode($_SESSION['csrf_token']) ?>,
    t: {
        selectLms:        <?= json_encode(__('alliances.js.select_lms')) ?>,
        noActiveSection:  <?= json_encode(__('alliances.js.no_active_section')) ?>,
        processing:       <?= json_encode(__('alliances.btn_processing')) ?>,
        process:          <?= json_encode(__('alliances.btn_process')) ?>,
        panelTitleInicio: <?= json_encode(__('alliances.js.panel_title_inicio')) ?>,
        panelTitleUnidad: <?= json_encode(__('alliances.js.panel_title_unidad')) ?>,
        panelTitleCurso:  <?= json_encode(__('alliances.js.panel_title_curso')) ?>,
        btnCopy:          <?= json_encode(__('alliances.js.btn_copy')) ?>,
        btnCopied:        <?= json_encode(__('alliances.js.btn_copied')) ?>,
        confirmClean:     <?= json_encode(__('alliances.js.confirm_clean')) ?>,
        errorProcess:     <?= json_encode(__('alliances.js.error_process')) ?>,
        errorConnection:  <?= json_encode(__('alliances.js.error_connection')) ?>,
    }
};
</script>
