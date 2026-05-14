<?php
/**
 * Nexus 2.0 — Ajustes > Mis claves API
 * Cada usuario gestiona sus propias claves iLovePDF / iLoveIMG.
 * Accessible para todos los roles.
 */
defined('APP_ACCESS') or die('Acceso directo no permitido');

$currentUser = getCurrentUser();

if (!isLoggedIn()) {
    http_response_code(403);
    include 'pages/403.php';
    return;
}

$username = $currentUser['username'] ?? '';
$safe     = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $username);
$userFile = DATA_PATH . '/user_api_' . $safe . '.json';

$raw       = file_exists($userFile) ? (json_decode(file_get_contents($userFile), true) ?? []) : [];
$hasPublic = !empty($raw['ilp_public_key']);
$hasSecret = !empty($raw['ilp_secret_key']);
$configured = $hasPublic && $hasSecret;
?>

<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= url('settings') ?>" class="breadcrumb-link"><?= __('menu.settings') ?></a>
    <i class="bi bi-chevron-right breadcrumb-separator" aria-hidden="true"></i>
    <span class="breadcrumb-current" aria-current="page"><?= __('my_integrations.page_title') ?></span>
</nav>

<div class="page-header">
    <h1 class="page-title"><?= __('my_integrations.page_title') ?></h1>
    <p class="page-description"><?= __('my_integrations.page_subtitle') ?></p>
</div>

<!-- Panel iLovePDF -->
<div class="card integration-card">
    <header class="integration-header">
        <div class="integration-header-icon">
            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
        </div>
        <div class="integration-header-info">
            <h2 class="integration-title">iLovePDF / iLoveIMG</h2>
            <p class="integration-description"><?= __('integrations.ilp_description') ?></p>
        </div>
        <div class="integration-header-status">
            <?php if ($configured): ?>
            <span class="lozenge lozenge-success">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <?= __('integrations.status_configured') ?>
            </span>
            <?php else: ?>
            <span class="lozenge lozenge-default">
                <i class="bi bi-dash-circle" aria-hidden="true"></i>
                <?= __('integrations.status_not_configured') ?>
            </span>
            <?php endif; ?>
        </div>
    </header>

    <div class="integration-body">
        <div class="alert alert-info">
            <i class="bi bi-person-lock alert-icon" aria-hidden="true"></i>
            <span class="alert-content"><?= __('my_integrations.personal_note') ?></span>
        </div>

        <h3 class="integration-section-title"><?= __('integrations.section_keys') ?></h3>

        <div class="form-grid-2">
            <div class="form-group">
                <label for="fMyIlpPublic" class="form-label">
                    <?= __('integrations.field_public_key') ?>
                    <?php if ($hasPublic): ?>
                    <span class="lozenge lozenge-success"><?= __('integrations.status_saved') ?></span>
                    <?php endif; ?>
                </label>
                <div class="password-field">
                    <input type="password" id="fMyIlpPublic" class="form-control" autocomplete="off"
                           placeholder="<?= $hasPublic ? __('integrations.placeholder_keep_current') : 'project_public_...' ?>">
                    <button type="button" class="password-toggle" id="toggleMyPublic"
                            aria-label="<?= __('integrations.show_value') ?>"
                            data-tooltip="<?= __('integrations.show_value') ?>">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <p class="form-helper"><?= __('integrations.public_key_help') ?></p>
            </div>

            <div class="form-group">
                <label for="fMyIlpSecret" class="form-label">
                    <?= __('integrations.field_secret_key') ?>
                    <?php if ($hasSecret): ?>
                    <span class="lozenge lozenge-success"><?= __('integrations.status_saved') ?></span>
                    <?php endif; ?>
                </label>
                <div class="password-field">
                    <input type="password" id="fMyIlpSecret" class="form-control" autocomplete="off"
                           placeholder="<?= $hasSecret ? __('integrations.placeholder_keep_current') : 'secret_key_...' ?>">
                    <button type="button" class="password-toggle" id="toggleMySecret"
                            aria-label="<?= __('integrations.show_value') ?>"
                            data-tooltip="<?= __('integrations.show_value') ?>">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <p class="form-helper"><?= __('integrations.secret_key_help') ?></p>
            </div>
        </div>

        <div class="integration-test-result d-none" id="myApiResult" role="status" aria-live="polite"></div>

        <div class="integration-actions">
            <?php if ($configured): ?>
            <button type="button" class="btn btn-default" id="myApiClearBtn">
                <i class="bi bi-trash" aria-hidden="true"></i>
                <span class="btn-text"><?= __('my_integrations.btn_clear') ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="btn btn-default" id="myApiTestBtn" <?= !$configured ? 'disabled' : '' ?>>
                <i class="bi bi-wifi" aria-hidden="true"></i>
                <span class="btn-text"><?= __('integrations.btn_test') ?></span>
            </button>
            <button type="button" class="btn btn-primary" id="myApiSaveBtn">
                <i class="bi bi-check2" aria-hidden="true"></i>
                <span class="btn-text"><?= __('integrations.btn_save') ?></span>
            </button>
        </div>
    </div>

    <details class="integration-guide">
        <summary class="integration-guide-summary">
            <i class="bi bi-question-circle" aria-hidden="true"></i>
            <?= __('integrations.guide_title') ?>
        </summary>
        <div class="integration-guide-body">
            <p><?= __('integrations.guide_intro') ?></p>
            <ol class="integration-guide-steps">
                <li><?= __('integrations.guide_step1') ?>
                    <a href="https://developer.ilovepdf.com" target="_blank" rel="noopener noreferrer">developer.ilovepdf.com <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
                </li>
                <li><?= __('integrations.guide_step2') ?></li>
                <li><?= __('integrations.guide_step3') ?></li>
                <li><?= __('integrations.guide_step4') ?></li>
                <li><?= __('integrations.guide_step5') ?></li>
            </ol>
            <div class="alert alert-success">
                <i class="bi bi-gift alert-icon" aria-hidden="true"></i>
                <span class="alert-content"><?= __('integrations.guide_free_plan') ?></span>
            </div>
        </div>
    </details>
</div>

<script>
(function () {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const username = <?= json_encode($username) ?>;

    async function apiPost(data) {
        const fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(data).forEach(k => fd.append(k, data[k] ?? ''));
        const res = await fetch('includes/user_api_actions.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
            body: fd,
        });
        return res.json();
    }

    function showResult(msg, ok) {
        const el = document.getElementById('myApiResult');
        if (!el) return;
        el.className = 'integration-test-result ' + (ok ? 'alert alert-success' : 'alert alert-danger');
        el.innerHTML = `<i class="bi ${ok ? 'bi-check-circle' : 'bi-exclamation-triangle-fill'} alert-icon"></i><span class="alert-content">${msg}</span>`;
        el.classList.remove('d-none');
    }

    function setBusy(btn, busy) {
        if (!btn) return;
        btn.disabled = busy;
        const icon = btn.querySelector('i');
        if (icon) icon.className = busy ? 'spinner' : btn.dataset.icon;
    }

    // Toggle show/hide
    ['toggleMyPublic', 'toggleMySecret'].forEach(id => {
        const btn   = document.getElementById(id);
        const input = document.getElementById(id === 'toggleMyPublic' ? 'fMyIlpPublic' : 'fMyIlpSecret');
        if (!btn || !input) return;
        btn.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.querySelector('i').className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });

    // Guardar
    const saveBtn = document.getElementById('myApiSaveBtn');
    if (saveBtn) {
        saveBtn.dataset.icon = 'bi bi-check2';
        saveBtn.addEventListener('click', async () => {
            const pub = document.getElementById('fMyIlpPublic')?.value.trim() || '';
            const sec = document.getElementById('fMyIlpSecret')?.value.trim() || '';
            if (!pub && !sec) { showResult('<?= __('my_integrations.err_empty') ?>', false); return; }
            setBusy(saveBtn, true);
            try {
                const res = await apiPost({ action: 'save', username, ilp_public_key: pub, ilp_secret_key: sec });
                showResult(res.message, res.success);
                if (res.success) {
                    const testBtn = document.getElementById('myApiTestBtn');
                    if (testBtn) testBtn.disabled = false;
                }
            } catch { showResult('<?= __('common.err_network') ?>', false); }
            setBusy(saveBtn, false);
        });
    }

    // Probar
    const testBtn = document.getElementById('myApiTestBtn');
    if (testBtn) {
        testBtn.dataset.icon = 'bi bi-wifi';
        testBtn.addEventListener('click', async () => {
            setBusy(testBtn, true);
            try {
                const res = await apiPost({ action: 'test', username });
                showResult(res.message, res.success);
            } catch { showResult('<?= __('common.err_network') ?>', false); }
            setBusy(testBtn, false);
        });
    }

    // Borrar
    const clearBtn = document.getElementById('myApiClearBtn');
    if (clearBtn) {
        clearBtn.dataset.icon = 'bi bi-trash';
        clearBtn.addEventListener('click', async () => {
            setBusy(clearBtn, true);
            try {
                const res = await apiPost({ action: 'clear', username });
                showResult(res.message, res.success);
                if (res.success) setTimeout(() => location.reload(), 1000);
            } catch { showResult('<?= __('common.err_network') ?>', false); }
            setBusy(clearBtn, false);
        });
    }
})();
</script>
