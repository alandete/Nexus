<?php
/**
 * Nexus 2.0 — Documentacion
 * Layout: TOC lateral + contenido con scroll-spy
 * Accesibilidad: landmarks, headings hierarchy, keyboard navigation
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

// Sections structure for TOC generation
$docSections = [
    'overview' => [
        'icon' => 'bi-house',
        'title' => __('docs.nav_overview'),
        'subs' => [
            'overview-features'     => __('docs.overview_features'),
            'overview-requirements' => __('docs.overview_requirements'),
            'overview-structure'    => __('docs.overview_structure'),
            'overview-roles'        => __('docs.overview_roles'),
            'overview-security'     => __('docs.overview_security'),
        ],
    ],
    'alliances' => [
        'icon' => 'bi-building',
        'title' => __('docs.nav_alliances'),
        'subs' => [
            'alliances-workflow'  => __('docs.alliances_workflow'),
            'alliances-templates' => __('docs.alliances_templates'),
            'alliances-glossary'  => __('docs.alliances_glossary'),
            'alliances-resources' => __('docs.alliances_resources'),
        ],
    ],
    'utilities' => [
        'icon' => 'bi-tools',
        'title' => __('docs.nav_utilities'),
        'subs' => [
            'utilities-questions' => __('docs.utilities_questions'),
            'utilities-pdf'       => __('docs.utilities_pdf'),
            'utilities-images'    => __('docs.utilities_images'),
        ],
    ],
    'tasks' => [
        'icon' => 'bi-stopwatch',
        'title' => __('docs.nav_tasks'),
        'subs' => [
            'tasks-tracker'  => __('docs.tasks_tracker'),
            'tasks-statuses' => __('docs.tasks_statuses'),
            'tasks-filters'  => __('docs.tasks_filters'),
            'tasks-reports'  => __('docs.tasks_reports'),
            'tasks-manage'   => __('docs.tasks_manage'),
        ],
    ],
    'settings' => [
        'icon' => 'bi-gear',
        'title' => __('docs.nav_settings'),
        'subs' => [
            'settings-users'     => __('docs.settings_users'),
            'settings-alliances' => __('docs.settings_alliances'),
            'settings-project'   => __('docs.settings_project'),
            'settings-apis'      => __('docs.settings_apis'),
            'settings-backups'   => __('docs.settings_backups'),
            'settings-activity'  => __('docs.settings_activity'),
        ],
    ],
];
?>

<div class="docs-layout">

    <!-- TOC -->
    <nav class="docs-toc" id="docsToc" aria-label="<?= __('docs.toc_label') ?>">
        <div class="docs-toc-header">
            <h2 class="docs-toc-title"><?= __('menu.docs') ?></h2>
        </div>

        <!-- Search -->
        <div class="docs-search">
            <input type="search" class="form-control" id="docsSearch"
                   placeholder="<?= __('docs.search_placeholder') ?>"
                   aria-label="<?= __('docs.search_placeholder') ?>">
        </div>

        <ul class="docs-toc-list">
            <?php foreach ($docSections as $sectionId => $section): ?>
            <li class="docs-toc-group" aria-expanded="false">
                <a href="#<?= $sectionId ?>" class="docs-toc-link docs-toc-parent" data-section="<?= $sectionId ?>">
                    <i class="bi <?= $section['icon'] ?> docs-toc-icon" aria-hidden="true"></i>
                    <span><?= $section['title'] ?></span>
                    <i class="bi bi-chevron-down docs-toc-chevron" aria-hidden="true"></i>
                </a>
                <?php if (!empty($section['subs'])): ?>
                <ul class="docs-toc-sublist">
                    <?php foreach ($section['subs'] as $subId => $subTitle): ?>
                    <li>
                        <a href="#<?= $subId ?>" class="docs-toc-link docs-toc-child" data-section="<?= $subId ?>">
                            <?= $subTitle ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Content -->
    <div class="docs-content" id="docsContent">

        <!-- ═══════════════ OVERVIEW ═══════════════ -->
        <section id="overview" class="docs-section">
            <h2 class="docs-section-title"><?= __('docs.nav_overview') ?></h2>
            <p class="docs-section-desc"><?= __('docs.overview_desc') ?></p>

            <article id="overview-features" class="docs-article">
                <h3><?= __('docs.overview_features') ?></h3>
                <div class="docs-feature-grid">
                    <div class="docs-feature">
                        <i class="bi bi-building" aria-hidden="true"></i>
                        <div>
                            <strong><?= __('docs.feature_alliances') ?></strong>
                            <p><?= __('docs.feature_alliances_desc') ?></p>
                        </div>
                    </div>
                    <div class="docs-feature">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                        <div>
                            <strong><?= __('docs.feature_questions') ?></strong>
                            <p><?= __('docs.feature_questions_desc') ?></p>
                        </div>
                    </div>
                    <div class="docs-feature">
                        <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                        <div>
                            <strong><?= __('docs.feature_optimization') ?></strong>
                            <p><?= __('docs.feature_optimization_desc') ?></p>
                        </div>
                    </div>
                    <div class="docs-feature">
                        <i class="bi bi-stopwatch" aria-hidden="true"></i>
                        <div>
                            <strong><?= __('docs.feature_tasks') ?></strong>
                            <p><?= __('docs.feature_tasks_desc') ?></p>
                        </div>
                    </div>
                    <div class="docs-feature">
                        <i class="bi bi-translate" aria-hidden="true"></i>
                        <div>
                            <strong><?= __('docs.feature_i18n') ?></strong>
                            <p><?= __('docs.feature_i18n_desc') ?></p>
                        </div>
                    </div>
                    <div class="docs-feature">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <div>
                            <strong><?= __('docs.feature_security') ?></strong>
                            <p><?= __('docs.feature_security_desc') ?></p>
                        </div>
                    </div>
                </div>
            </article>

            <article id="overview-requirements" class="docs-article">
                <h3><?= __('docs.overview_requirements') ?></h3>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_component') ?></th><th><?= __('docs.col_required') ?></th><th><?= __('docs.col_notes') ?></th></tr></thead>
                    <tbody>
                        <tr><td>PHP</td><td>8.3+</td><td>pdo_mysql, intl, mbstring, openssl, gd</td></tr>
                        <tr><td>MySQL</td><td>8.0+</td><td><?= __('docs.req_mysql_note') ?></td></tr>
                        <tr><td>Apache</td><td>mod_rewrite</td><td><?= __('docs.req_apache_note') ?></td></tr>
                        <tr><td>ZipArchive</td><td><?= __('docs.req_required') ?></td><td><?= __('docs.req_zip_note') ?></td></tr>
                        <tr><td>cURL</td><td><?= __('docs.req_required') ?></td><td><?= __('docs.req_curl_note') ?></td></tr>
                        <tr><td>Ghostscript</td><td><?= __('docs.req_optional') ?></td><td><?= __('docs.req_gs_note') ?></td></tr>
                        <tr><td>ImageMagick</td><td><?= __('docs.req_optional') ?></td><td><?= __('docs.req_im_note') ?></td></tr>
                    </tbody>
                </table>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle alert-icon" aria-hidden="true"></i>
                    <span class="alert-content"><?= __('docs.req_setup_hint') ?></span>
                </div>
            </article>

            <article id="overview-structure" class="docs-article">
                <h3><?= __('docs.overview_structure') ?></h3>
                <div class="docs-code-block">
                    <pre><code>Nexus/
├── index.php              # <?= __('docs.struct_index') ?>

├── config/                # <?= __('docs.struct_config') ?>

├── includes/              # <?= __('docs.struct_includes') ?>

├── pages/                 # <?= __('docs.struct_pages') ?>

├── assets/css/            # <?= __('docs.struct_css') ?>

├── assets/js/             # <?= __('docs.struct_js') ?>

├── lang/{es,en}/          # <?= __('docs.struct_lang') ?>

├── templates/             # <?= __('docs.struct_templates') ?>

├── data/                  # <?= __('docs.struct_data') ?>

└── docs/design/           # <?= __('docs.struct_docs') ?></code></pre>
                </div>
            </article>

            <article id="overview-roles" class="docs-article">
                <h3><?= __('docs.overview_roles') ?></h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th><?= __('docs.col_role') ?></th>
                            <th><?= __('docs.nav_alliances') ?></th>
                            <th><?= __('docs.nav_utilities') ?></th>
                            <th><?= __('docs.nav_settings') ?></th>
                            <th><?= __('docs.settings_users') ?></th>
                            <th><?= __('docs.settings_backups') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="lozenge lozenge-bold">Admin</span></td>
                            <td><?= __('docs.perm_full') ?></td>
                            <td><?= __('docs.perm_full') ?></td>
                            <td><?= __('docs.perm_full') ?></td>
                            <td><?= __('docs.perm_full') ?></td>
                            <td><?= __('docs.perm_full') ?></td>
                        </tr>
                        <tr>
                            <td><span class="lozenge lozenge-info">Editor</span></td>
                            <td><?= __('docs.perm_read_write') ?></td>
                            <td><?= __('docs.perm_read_write') ?></td>
                            <td><?= __('docs.perm_read') ?></td>
                            <td><?= __('docs.perm_read') ?></td>
                            <td>—</td>
                        </tr>
                        <tr>
                            <td><span class="lozenge lozenge-default">Viewer</span></td>
                            <td><?= __('docs.perm_read') ?></td>
                            <td><?= __('docs.perm_read') ?></td>
                            <td><?= __('docs.perm_read') ?></td>
                            <td>—</td>
                            <td>—</td>
                        </tr>
                    </tbody>
                </table>
            </article>

            <article id="overview-security" class="docs-article">
                <h3><?= __('docs.overview_security') ?></h3>
                <ul class="docs-list">
                    <li><strong>CSRF</strong> — <?= __('docs.sec_csrf') ?></li>
                    <li><strong><?= __('docs.sec_sessions_label') ?></strong> — HttpOnly, SameSite=Strict</li>
                    <li><strong><?= __('docs.sec_passwords_label') ?></strong> — bcrypt (password_hash)</li>
                    <li><strong><?= __('docs.sec_api_label') ?></strong> — AES-256-CBC</li>
                    <li><strong><?= __('docs.sec_password_recovery_label') ?></strong> — <?= __('docs.sec_password_recovery') ?></li>
                    <li><strong>Headers</strong> — nosniff, SAMEORIGIN, referrer-policy, permissions-policy</li>
                </ul>
            </article>
        </section>

        <!-- ═══════════════ ALLIANCES ═══════════════ -->
        <section id="alliances" class="docs-section">
            <h2 class="docs-section-title"><?= __('docs.nav_alliances') ?></h2>
            <p class="docs-section-desc"><?= __('docs.alliances_desc') ?></p>

            <article id="alliances-workflow" class="docs-article">
                <h3><?= __('docs.alliances_workflow') ?></h3>
                <ol class="docs-list docs-steps">
                    <li><?= __('docs.alliances_step1') ?></li>
                    <li><?= __('docs.alliances_step2') ?></li>
                    <li><?= __('docs.alliances_step3') ?></li>
                    <li><?= __('docs.alliances_step4') ?></li>
                    <li><?= __('docs.alliances_step5') ?></li>
                </ol>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_alliance') ?></th><th><?= __('docs.col_tabs') ?></th><th>LMS</th></tr></thead>
                    <tbody>
                        <tr><td>UNIS</td><td>Inicio, Unidad</td><td>Moodle, Canvas</td></tr>
                        <tr><td>UNAB</td><td>Inicio, Curso</td><td>Moodle</td></tr>
                    </tbody>
                </table>
            </article>

            <article id="alliances-templates" class="docs-article">
                <h3><?= __('docs.alliances_templates') ?></h3>
                <p><?= __('docs.alliances_templates_desc') ?></p>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_element') ?></th><th><?= __('docs.col_description') ?></th></tr></thead>
                    <tbody>
                        <tr><td><code>{{ Variable }}</code></td><td><?= __('docs.tpl_placeholder') ?></td></tr>
                        <tr><td><code>&lt;!-- REPEAT --&gt;</code></td><td><?= __('docs.tpl_repeat') ?></td></tr>
                        <tr><td><code>-v1.html</code></td><td><?= __('docs.tpl_versioning') ?></td></tr>
                    </tbody>
                </table>
                <p class="text-sm text-subtle"><?= __('docs.tpl_path_example') ?></p>
            </article>

            <article id="alliances-glossary" class="docs-article">
                <h3><?= __('docs.alliances_glossary') ?></h3>
                <p><?= __('docs.alliances_glossary_desc') ?></p>
                <div class="docs-code-block">
                    <pre><code>Termino:Definicion del termino
Termino&amp;Definicion con separador alternativo</code></pre>
                </div>
                <ul class="docs-list">
                    <li><?= __('docs.glossary_sort') ?></li>
                    <li><?= __('docs.glossary_format') ?></li>
                    <li><?= __('docs.glossary_highlight') ?></li>
                </ul>
            </article>

            <article id="alliances-resources" class="docs-article">
                <h3><?= __('docs.alliances_resources') ?></h3>
                <p><?= __('docs.alliances_resources_desc') ?></p>
                <div class="docs-code-block">
                    <pre><code>Apellido, N. (2023). Titulo del libro. Editorial. https://url-del-recurso.com</code></pre>
                </div>
            </article>
        </section>

        <!-- ═══════════════ UTILITIES ═══════════════ -->
        <section id="utilities" class="docs-section">
            <h2 class="docs-section-title"><?= __('docs.nav_utilities') ?></h2>
            <p class="docs-section-desc"><?= __('docs.utilities_desc') ?></p>

            <article id="utilities-questions" class="docs-article">
                <h3><?= __('docs.utilities_questions') ?></h3>
                <p><?= __('docs.utilities_questions_desc') ?></p>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_type') ?></th><th><?= __('docs.col_marker') ?></th><th><?= __('docs.col_description') ?></th></tr></thead>
                    <tbody>
                        <tr><td><?= __('docs.q_multiple') ?></td><td><code>OM</code></td><td><?= __('docs.q_multiple_desc') ?></td></tr>
                        <tr><td><?= __('docs.q_truefalse') ?></td><td><code>FV</code></td><td><?= __('docs.q_truefalse_desc') ?></td></tr>
                        <tr><td><?= __('docs.q_matching') ?></td><td><code>EM</code></td><td><?= __('docs.q_matching_desc') ?></td></tr>
                    </tbody>
                </table>
                <table class="table table-compact mt-200">
                    <thead><tr><th><?= __('docs.col_format') ?></th><th><?= __('docs.col_platform') ?></th><th><?= __('docs.col_output') ?></th></tr></thead>
                    <tbody>
                        <tr><td>GIFT</td><td>Moodle</td><td><code>.txt</code></td></tr>
                        <tr><td>QTI 1.2</td><td>Canvas</td><td><code>.zip</code></td></tr>
                    </tbody>
                </table>
                <div class="docs-code-block mt-200">
                    <div class="docs-code-label"><?= __('docs.q_example_word') ?></div>
                    <pre><code>OM Pregunta 01
Cual es la capital de Francia?
a. Madrid
b. Londres
c. Paris
d. Berlin
Retro correcta: Paris es la capital de Francia.
Retro incorrecta: La respuesta correcta es Paris.</code></pre>
                </div>
            </article>

            <article id="utilities-pdf" class="docs-article">
                <h3><?= __('docs.utilities_pdf') ?></h3>
                <p><?= __('docs.utilities_pdf_desc') ?></p>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_method') ?></th><th><?= __('docs.col_required') ?></th><th><?= __('docs.col_description') ?></th></tr></thead>
                    <tbody>
                        <tr><td>Ghostscript</td><td><?= __('docs.pdf_gs_req') ?></td><td><?= __('docs.pdf_gs_desc') ?></td></tr>
                        <tr><td>API (iLovePDF)</td><td><?= __('docs.pdf_api_req') ?></td><td><?= __('docs.pdf_api_desc') ?></td></tr>
                        <tr><td>PHP</td><td><?= __('docs.pdf_php_req') ?></td><td><?= __('docs.pdf_php_desc') ?></td></tr>
                    </tbody>
                </table>
            </article>

            <article id="utilities-images" class="docs-article">
                <h3><?= __('docs.utilities_images') ?></h3>
                <p><?= __('docs.utilities_images_desc') ?></p>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_operation') ?></th><th><?= __('docs.col_description') ?></th><th><?= __('docs.col_formats') ?></th></tr></thead>
                    <tbody>
                        <tr><td><?= __('docs.img_compress') ?></td><td><?= __('docs.img_compress_desc') ?></td><td>JPEG, PNG, WebP, GIF</td></tr>
                        <tr><td><?= __('docs.img_resize') ?></td><td><?= __('docs.img_resize_desc') ?></td><td>JPEG, PNG, WebP, GIF</td></tr>
                        <tr><td><?= __('docs.img_convert') ?></td><td><?= __('docs.img_convert_desc') ?></td><td>JPEG, PNG, WebP</td></tr>
                    </tbody>
                </table>
            </article>
        </section>

        <!-- ═══════════════ TASKS ═══════════════ -->
        <section id="tasks" class="docs-section">
            <h2 class="docs-section-title"><?= __('docs.nav_tasks') ?></h2>
            <p class="docs-section-desc"><?= __('docs.tasks_desc') ?></p>

            <article id="tasks-tracker" class="docs-article">
                <h3><?= __('docs.tasks_tracker') ?></h3>
                <p><?= __('docs.tasks_tracker_desc') ?></p>
                <ol class="docs-list docs-steps">
                    <li><?= __('docs.tasks_tracker_step1') ?></li>
                    <li><?= __('docs.tasks_tracker_step2') ?></li>
                    <li><?= __('docs.tasks_tracker_step3') ?></li>
                    <li><?= __('docs.tasks_tracker_step4') ?></li>
                </ol>
                <table class="table table-compact mt-200">
                    <thead><tr><th><?= __('docs.col_section') ?></th><th><?= __('docs.col_description') ?></th></tr></thead>
                    <tbody>
                        <tr><td><?= __('docs.tasks_sec_active') ?></td><td><?= __('docs.tasks_sec_active_desc') ?></td></tr>
                        <tr><td><?= __('docs.tasks_sec_scheduled') ?></td><td><?= __('docs.tasks_sec_scheduled_desc') ?></td></tr>
                        <tr><td><?= __('docs.tasks_sec_today') ?></td><td><?= __('docs.tasks_sec_today_desc') ?></td></tr>
                        <tr><td><?= __('docs.tasks_sec_yesterday') ?></td><td><?= __('docs.tasks_sec_yesterday_desc') ?></td></tr>
                        <tr><td><?= __('docs.tasks_sec_history') ?></td><td><?= __('docs.tasks_sec_history_desc') ?></td></tr>
                    </tbody>
                </table>
            </article>

            <article id="tasks-statuses" class="docs-article">
                <h3><?= __('docs.tasks_statuses') ?></h3>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_status') ?></th><th><?= __('docs.col_description') ?></th><th><?= __('docs.col_notes') ?></th></tr></thead>
                    <tbody>
                        <tr><td><span class="lozenge lozenge-default"><?= __('docs.status_pending') ?></span></td><td><?= __('docs.status_pending_desc') ?></td><td><?= __('docs.status_pending_note') ?></td></tr>
                        <tr><td><span class="lozenge lozenge-info"><?= __('docs.status_in_progress') ?></span></td><td><?= __('docs.status_in_progress_desc') ?></td><td><?= __('docs.status_in_progress_note') ?></td></tr>
                        <tr><td><span class="lozenge lozenge-warning"><?= __('docs.status_paused') ?></span></td><td><?= __('docs.status_paused_desc') ?></td><td><?= __('docs.status_paused_note') ?></td></tr>
                        <tr><td><span class="lozenge lozenge-success"><?= __('docs.status_completed') ?></span></td><td><?= __('docs.status_completed_desc') ?></td><td><?= __('docs.status_completed_note') ?></td></tr>
                        <tr><td><span class="lozenge lozenge-danger"><?= __('docs.status_cancelled') ?></span></td><td><?= __('docs.status_cancelled_desc') ?></td><td><?= __('docs.status_cancelled_note') ?></td></tr>
                    </tbody>
                </table>
            </article>

            <article id="tasks-filters" class="docs-article">
                <h3><?= __('docs.tasks_filters') ?></h3>
                <p><?= __('docs.tasks_filters_desc') ?></p>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_filter') ?></th><th><?= __('docs.col_scope') ?></th><th><?= __('docs.col_description') ?></th></tr></thead>
                    <tbody>
                        <tr><td><?= __('docs.filter_date') ?></td><td><?= __('docs.filter_server') ?></td><td><?= __('docs.filter_date_desc') ?></td></tr>
                        <tr><td><?= __('docs.filter_alliance') ?></td><td><?= __('docs.filter_server') ?></td><td><?= __('docs.filter_alliance_desc') ?></td></tr>
                        <tr><td><?= __('docs.filter_priority') ?></td><td><?= __('docs.filter_client') ?></td><td><?= __('docs.filter_priority_desc') ?></td></tr>
                        <tr><td><?= __('docs.filter_tags') ?></td><td><?= __('docs.filter_client') ?></td><td><?= __('docs.filter_tags_desc') ?></td></tr>
                        <tr><td><?= __('docs.filter_search') ?></td><td><?= __('docs.filter_client') ?></td><td><?= __('docs.filter_search_desc') ?></td></tr>
                    </tbody>
                </table>
                <div class="alert alert-info mt-200">
                    <i class="bi bi-info-circle alert-icon" aria-hidden="true"></i>
                    <span class="alert-content"><?= __('docs.tasks_filters_hint') ?></span>
                </div>
            </article>

            <article id="tasks-reports" class="docs-article">
                <h3><?= __('docs.tasks_reports') ?></h3>
                <p><?= __('docs.tasks_reports_desc') ?></p>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_element') ?></th><th><?= __('docs.col_description') ?></th></tr></thead>
                    <tbody>
                        <tr><td><?= __('docs.report_chart') ?></td><td><?= __('docs.report_chart_desc') ?></td></tr>
                        <tr><td><?= __('docs.report_cards') ?></td><td><?= __('docs.report_cards_desc') ?></td></tr>
                        <tr><td><?= __('docs.report_export_csv') ?></td><td><?= __('docs.report_export_csv_desc') ?></td></tr>
                        <tr><td><?= __('docs.report_export_excel') ?></td><td><?= __('docs.report_export_excel_desc') ?></td></tr>
                        <tr><td><?= __('docs.report_export_pdf') ?></td><td><?= __('docs.report_export_pdf_desc') ?></td></tr>
                    </tbody>
                </table>
            </article>

            <article id="tasks-manage" class="docs-article">
                <h3><?= __('docs.tasks_manage') ?></h3>
                <p><?= __('docs.tasks_manage_desc') ?></p>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_tab') ?></th><th><?= __('docs.col_description') ?></th></tr></thead>
                    <tbody>
                        <tr>
                            <td><?= __('docs.manage_tab_tags') ?></td>
                            <td><?= __('docs.manage_tab_tags_desc') ?></td>
                        </tr>
                        <tr>
                            <td><?= __('docs.manage_tab_io') ?></td>
                            <td><?= __('docs.manage_tab_io_desc') ?></td>
                        </tr>
                        <tr>
                            <td><?= __('docs.manage_tab_cleanup') ?></td>
                            <td><?= __('docs.manage_tab_cleanup_desc') ?></td>
                        </tr>
                    </tbody>
                </table>
                <div class="alert alert-warning mt-200">
                    <i class="bi bi-exclamation-triangle alert-icon" aria-hidden="true"></i>
                    <span class="alert-content"><?= __('docs.manage_cleanup_warning') ?></span>
                </div>
            </article>
        </section>

        <!-- ═══════════════ SETTINGS ═══════════════ -->
        <section id="settings" class="docs-section">
            <h2 class="docs-section-title"><?= __('docs.nav_settings') ?></h2>
            <p class="docs-section-desc"><?= __('docs.settings_desc') ?></p>

            <article id="settings-users" class="docs-article">
                <h3><?= __('docs.settings_users') ?></h3>
                <p><?= __('docs.settings_users_desc') ?></p>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_field') ?></th><th><?= __('docs.col_description') ?></th><th><?= __('docs.col_notes') ?></th></tr></thead>
                    <tbody>
                        <tr><td><?= __('docs.field_username') ?></td><td><?= __('docs.field_username_desc') ?></td><td><?= __('docs.field_username_note') ?></td></tr>
                        <tr><td><?= __('docs.field_name') ?></td><td><?= __('docs.field_name_desc') ?></td><td><?= __('docs.field_name_note') ?></td></tr>
                        <tr><td><?= __('docs.field_email') ?></td><td><?= __('docs.field_email_desc') ?></td><td><?= __('docs.field_email_note') ?></td></tr>
                        <tr><td><?= __('docs.field_password') ?></td><td><?= __('docs.field_password_desc') ?></td><td><?= __('docs.field_password_note') ?></td></tr>
                        <tr><td><?= __('docs.field_role') ?></td><td><?= __('docs.field_role_desc') ?></td><td>Admin, Editor, Viewer</td></tr>
                    </tbody>
                </table>
                <h4 class="mt-300"><?= __('docs.users_recovery_title') ?></h4>
                <p><?= __('docs.users_recovery_desc') ?></p>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_method') ?></th><th><?= __('docs.col_description') ?></th></tr></thead>
                    <tbody>
                        <tr><td><?= __('docs.recovery_email') ?></td><td><?= __('docs.recovery_email_desc') ?></td></tr>
                        <tr><td><?= __('docs.recovery_admin_link') ?></td><td><?= __('docs.recovery_admin_link_desc') ?></td></tr>
                    </tbody>
                </table>
            </article>

            <article id="settings-alliances" class="docs-article">
                <h3><?= __('docs.settings_alliances') ?></h3>
                <p><?= __('docs.settings_alliances_desc') ?></p>
            </article>

            <article id="settings-project" class="docs-article">
                <h3><?= __('docs.settings_project') ?></h3>
                <p><?= __('docs.settings_project_desc') ?></p>
            </article>

            <article id="settings-apis" class="docs-article">
                <h3><?= __('docs.settings_apis') ?></h3>
                <p><?= __('docs.settings_apis_desc') ?></p>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_integration') ?></th><th><?= __('docs.col_purpose') ?></th><th><?= __('docs.col_required') ?></th></tr></thead>
                    <tbody>
                        <tr><td>iLovePDF / iLoveIMG</td><td><?= __('docs.api_ilovepdf_purpose') ?></td><td><?= __('docs.api_ilovepdf_req') ?></td></tr>
                        <tr><td>Gmail IMAP</td><td><?= __('docs.api_gmail_purpose') ?></td><td><?= __('docs.api_gmail_req') ?></td></tr>
                        <tr><td>SMTP</td><td><?= __('docs.api_smtp_purpose') ?></td><td><?= __('docs.api_smtp_req') ?></td></tr>
                    </tbody>
                </table>
                <div class="alert alert-info mt-200">
                    <i class="bi bi-info-circle alert-icon" aria-hidden="true"></i>
                    <span class="alert-content"><?= __('docs.api_smtp_note') ?></span>
                </div>
                <div class="alert alert-warning mt-200">
                    <i class="bi bi-exclamation-triangle alert-icon" aria-hidden="true"></i>
                    <span class="alert-content"><?= __('docs.settings_apis_warning') ?></span>
                </div>
            </article>

            <article id="settings-backups" class="docs-article">
                <h3><?= __('docs.settings_backups') ?></h3>
                <p><?= __('docs.settings_backups_desc') ?></p>
                <table class="table table-compact">
                    <thead><tr><th><?= __('docs.col_type') ?></th><th><?= __('docs.col_content') ?></th></tr></thead>
                    <tbody>
                        <tr><td><?= __('docs.backup_data') ?></td><td><?= __('docs.backup_data_desc') ?></td></tr>
                        <tr><td><?= __('docs.backup_full') ?></td><td><?= __('docs.backup_full_desc') ?></td></tr>
                    </tbody>
                </table>
            </article>

            <article id="settings-activity" class="docs-article">
                <h3><?= __('docs.settings_activity') ?></h3>
                <p><?= __('docs.settings_activity_desc') ?></p>
            </article>
        </section>

    </div>
</div>
