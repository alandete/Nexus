/**
 * Nexus 2.0 — Snapshots (Copias de seguridad)
 * Listar, crear, restaurar, descargar, favoritos, eliminar
 */

(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const T = window.__T__ || {};
    const t = (key, fallback) => T[key] || fallback;

    const canCreate  = window.__SNAPSHOTS_CAN_CREATE__ || false;
    const canRestore = window.__SNAPSHOTS_CAN_RESTORE__ || false;

    /** ========================================================
     * Helpers
     * ======================================================== */

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    async function postAction(data) {
        const fd = new FormData();
        Object.keys(data).forEach(k => fd.append(k, data[k] ?? ''));
        const res = await fetch('includes/backup_actions.php', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken },
            body: fd,
        });
        return res.json();
    }

    /** ========================================================
     * Filtros
     * ======================================================== */

    function applyFilters() {
        const search = document.getElementById('snapshotSearch');
        const activeChip = document.querySelector('.chip-active');
        const list = document.getElementById('snapshotsList');
        const empty = document.getElementById('snapshotsEmptyFiltered');
        if (!list) return;

        const query = (search?.value || '').trim().toLowerCase();
        const filter = activeChip?.dataset.filter || 'all';

        const cards = list.querySelectorAll('.snapshot-card');
        let visible = 0;

        cards.forEach(card => {
            const cardSearch = card.dataset.search || '';
            const cardType   = card.dataset.type || '';
            const cardFav    = card.dataset.favorite === '1';

            const matchSearch = !query || cardSearch.includes(query);
            let matchFilter = true;
            if (filter === 'data') matchFilter = cardType === 'data';
            else if (filter === 'full') matchFilter = cardType === 'full';
            else if (filter === 'favorites') matchFilter = cardFav;

            const show = matchSearch && matchFilter;
            card.classList.toggle('d-none', !show);
            if (show) visible++;
        });

        if (empty) empty.classList.toggle('d-none', visible > 0 || cards.length === 0);
        list.classList.toggle('d-none', visible === 0 && cards.length > 0);
    }

    document.querySelectorAll('.chip[data-filter]').forEach(chip => {
        chip.addEventListener('click', () => {
            document.querySelectorAll('.chip[data-filter]').forEach(c => {
                c.classList.remove('chip-active');
                c.setAttribute('aria-selected', 'false');
            });
            chip.classList.add('chip-active');
            chip.setAttribute('aria-selected', 'true');
            applyFilters();
        });
    });

    const searchInput = document.getElementById('snapshotSearch');
    if (searchInput) searchInput.addEventListener('input', applyFilters);

    /** ========================================================
     * Crear backup (Slide Panel)
     * ======================================================== */

    function openCreateForm() {
        const html = `
            <form id="snapshotForm" novalidate>
                <div class="form-group">
                    <label class="form-label">${t('snapshots.field_type', 'Tipo de copia')}</label>
                    <div class="snapshot-type-options">
                        <label class="snapshot-type-option">
                            <input type="radio" name="type" value="data" checked>
                            <div class="snapshot-type-option-body">
                                <i class="bi bi-database snapshot-type-icon" aria-hidden="true"></i>
                                <div class="snapshot-type-option-info">
                                    <strong>${t('snapshots.type_data', 'Datos')}</strong>
                                    <span>${t('snapshots.type_data_desc', 'Archivos JSON + plantillas + base de datos. Ligero, restaurable desde la interfaz.')}</span>
                                </div>
                            </div>
                        </label>
                        <label class="snapshot-type-option">
                            <input type="radio" name="type" value="full">
                            <div class="snapshot-type-option-body">
                                <i class="bi bi-hdd snapshot-type-icon" aria-hidden="true"></i>
                                <div class="snapshot-type-option-info">
                                    <strong>${t('snapshots.type_full', 'Completa')}</strong>
                                    <span>${t('snapshots.type_full_desc', 'Todo el proyecto: codigo, configuracion y datos. Solo para descarga manual.')}</span>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="fSnapshotNote" class="form-label">${t('snapshots.field_note', 'Nota (opcional)')}</label>
                    <input type="text" id="fSnapshotNote" name="notes" class="form-control"
                           placeholder="${t('snapshots.field_note_placeholder', 'Ej: antes del cambio X')}"
                           maxlength="200">
                    <p class="form-helper">${t('snapshots.field_note_help', 'Texto breve para identificar esta copia despues.')}</p>
                </div>

                <div class="form-group">
                    <label class="toggle-field">
                        <input type="checkbox" id="fCleanup" name="cleanup" value="1">
                        <span class="toggle-field-label">
                            <strong>${t('snapshots.field_cleanup', 'Limpiar archivos temporales antes de crear')}</strong>
                            <span class="form-helper">${t('snapshots.field_cleanup_help', 'Elimina la carpeta /temp/ y vacia el registro de actividad.')}</span>
                        </span>
                    </label>
                </div>

                <div class="alert alert-info" id="snapshotCreateHint">
                    <i class="bi bi-info-circle alert-icon" aria-hidden="true"></i>
                    <span class="alert-content" id="snapshotCreateHintText">
                        ${t('snapshots.create_hint_data', 'Se guardaran los JSON, plantillas y la base de datos. Esta copia se puede restaurar desde aqui mismo.')}
                    </span>
                </div>

                <div class="alert alert-danger d-none" id="snapshotFormError" role="alert">
                    <i class="bi bi-exclamation-triangle-fill alert-icon" aria-hidden="true"></i>
                    <span class="alert-content" id="snapshotFormErrorText"></span>
                </div>
            </form>
        `;

        SlidePanel.open(t('snapshots.form_create_title', 'Crear copia de seguridad'), html);

        SlidePanel.setFooter(`
            <button type="button" class="btn btn-subtle" id="snapshotCancelBtn">
                ${t('common.cancel', 'Cancelar')}
            </button>
            <button type="button" class="btn btn-primary" id="snapshotSubmitBtn">
                <i class="bi bi-cloud-arrow-down" aria-hidden="true"></i>
                <span class="btn-text">${t('snapshots.btn_create_confirm', 'Crear copia')}</span>
            </button>
        `);

        document.getElementById('snapshotCancelBtn').addEventListener('click', () => SlidePanel.close());
        document.getElementById('snapshotSubmitBtn').addEventListener('click', handleCreate);

        // Actualizar hint segun tipo seleccionado
        document.querySelectorAll('input[name="type"]').forEach(radio => {
            radio.addEventListener('change', updateHint);
        });
    }

    function updateHint() {
        const type = document.querySelector('input[name="type"]:checked')?.value;
        const hint = document.getElementById('snapshotCreateHintText');
        if (!hint) return;
        if (type === 'full') {
            hint.textContent = t('snapshots.create_hint_full', 'Se empaquetara todo el proyecto. Util para migracion completa. No podras restaurarla desde la interfaz, solo descargarla.');
        } else {
            hint.textContent = t('snapshots.create_hint_data', 'Se guardaran los JSON, plantillas y la base de datos. Esta copia se puede restaurar desde aqui mismo.');
        }
    }

    async function handleCreate() {
        const form = document.getElementById('snapshotForm');
        const submitBtn = document.getElementById('snapshotSubmitBtn');
        if (!form || !submitBtn) return;

        const type = form.type.value;
        const notes = form.notes.value.trim();
        const cleanup = form.cleanup.checked;

        submitBtn.disabled = true;
        const btnText = submitBtn.querySelector('.btn-text');
        const originalText = btnText?.textContent;
        if (btnText) btnText.textContent = t('snapshots.creating', 'Creando...');

        try {
            // Cleanup previo si aplica
            if (cleanup) {
                await fetch('includes/cleanup_actions.php', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: new URLSearchParams({ action: 'clean' }),
                }).catch(() => {});
            }

            const result = await postAction({ action: 'create', type: type, notes: notes });
            if (result.success) {
                Toast.success(result.message || t('snapshots.created', 'Copia creada correctamente.'));
                SlidePanel.close();
                setTimeout(() => window.location.reload(), 600);
            } else {
                const err = document.getElementById('snapshotFormError');
                document.getElementById('snapshotFormErrorText').textContent = result.message || t('snapshots.err_create', 'No se pudo crear la copia.');
                err.classList.remove('d-none');
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        } finally {
            submitBtn.disabled = false;
            if (btnText) btnText.textContent = originalText;
        }
    }

    /** ========================================================
     * Restaurar
     * ======================================================== */

    async function restoreSnapshot(filename) {
        const confirmed = await ConfirmModal.show({
            title:     t('snapshots.restore_title', 'Restaurar copia'),
            message:   t('snapshots.restore_message', 'Se sobrescribiran los datos actuales con el contenido de esta copia. Esta accion no se puede deshacer.\n\nArchivo: {file}').replace('{file}', filename),
            acceptText: t('snapshots.btn_restore_confirm', 'Restaurar'),
            acceptVariant: 'warning',
            icon: 'bi-arrow-counterclockwise',
            variant: 'warning',
        });
        if (!confirmed) return;

        try {
            const result = await postAction({ action: 'restore', filename: filename });
            if (result.success) {
                Toast.success(result.message || t('snapshots.restored', 'Copia restaurada.'));
                setTimeout(() => window.location.reload(), 1000);
            } else {
                Toast.error(result.message || t('snapshots.err_restore', 'No se pudo restaurar.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        }
    }

    /** ========================================================
     * Favorito
     * ======================================================== */

    async function toggleFavorite(filename, btn) {
        try {
            const result = await postAction({ action: 'favorite', filename: filename });
            if (result.success) {
                const card = btn.closest('.snapshot-card');
                const isFav = !!result.favorited;
                const icon = btn.querySelector('i');

                btn.classList.toggle('is-active', isFav);
                btn.setAttribute('aria-pressed', isFav ? 'true' : 'false');
                if (icon) icon.className = isFav ? 'bi bi-star-fill' : 'bi bi-star';
                btn.setAttribute('data-tooltip', isFav
                    ? t('snapshots.btn_unfavorite', 'Quitar favorito')
                    : t('snapshots.btn_favorite', 'Marcar favorito'));

                if (card) {
                    card.classList.toggle('is-favorite', isFav);
                    card.dataset.favorite = isFav ? '1' : '0';

                    // Lozenge "Protegida"
                    const header = card.querySelector('.snapshot-header');
                    let protectedBadge = header?.querySelector('.lozenge-warning[data-protected]');
                    if (isFav && !protectedBadge && header) {
                        protectedBadge = document.createElement('span');
                        protectedBadge.className = 'lozenge lozenge-warning';
                        protectedBadge.dataset.protected = '1';
                        protectedBadge.setAttribute('data-tooltip', t('snapshots.protected_tooltip', 'No se elimina automaticamente.'));
                        protectedBadge.innerHTML = '<i class="bi bi-shield-fill" aria-hidden="true"></i> ' + escapeHtml(t('snapshots.protected', 'Protegida'));
                        // Insertar despues del primer lozenge (tipo)
                        const firstLozenge = header.querySelector('.lozenge');
                        if (firstLozenge && firstLozenge.nextSibling) {
                            header.insertBefore(protectedBadge, firstLozenge.nextSibling);
                        } else {
                            header.appendChild(protectedBadge);
                        }
                    } else if (!isFav && protectedBadge) {
                        protectedBadge.remove();
                    }

                    const deleteBtn = card.querySelector('.btn-delete');
                    if (deleteBtn) {
                        deleteBtn.disabled = isFav;
                        deleteBtn.setAttribute('data-tooltip', isFav
                            ? t('snapshots.delete_locked', 'Quita el favorito para eliminar')
                            : t('snapshots.btn_delete', 'Eliminar'));
                    }
                }

                Toast.success(isFav
                    ? t('snapshots.favorited', 'Marcado como favorito.')
                    : t('snapshots.unfavorited', 'Favorito removido.'));
            } else {
                Toast.error(result.message || t('snapshots.err_favorite', 'No se pudo actualizar.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        }
    }

    /** ========================================================
     * Eliminar
     * ======================================================== */

    async function deleteSnapshot(filename) {
        const confirmed = await ConfirmModal.show({
            title:     t('snapshots.delete_title', 'Eliminar copia'),
            message:   t('snapshots.delete_message', 'La copia "{file}" sera eliminada permanentemente.').replace('{file}', filename),
            acceptText: t('common.delete', 'Eliminar'),
            acceptVariant: 'danger',
            icon: 'bi-trash',
            variant: 'danger',
        });
        if (!confirmed) return;

        try {
            const result = await postAction({ action: 'delete', filename: filename });
            if (result.success) {
                Toast.success(result.message || t('snapshots.deleted', 'Copia eliminada.'));
                setTimeout(() => window.location.reload(), 400);
            } else {
                Toast.error(result.message || t('snapshots.err_delete', 'No se pudo eliminar.'));
            }
        } catch (err) {
            Toast.error(t('common.err_network', 'Error de red.'));
        }
    }

    /** ========================================================
     * Bindings
     * ======================================================== */

    document.addEventListener('DOMContentLoaded', () => {
        if (canCreate) {
            const btn1 = document.getElementById('btnCreateSnapshot');
            const btn2 = document.getElementById('btnCreateSnapshotEmpty');
            if (btn1) btn1.addEventListener('click', openCreateForm);
            if (btn2) btn2.addEventListener('click', openCreateForm);
        }

        document.addEventListener('click', (e) => {
            const favBtn = e.target.closest('.btn-favorite');
            const restoreBtn = e.target.closest('.btn-restore');
            const deleteBtn = e.target.closest('.btn-delete');

            if (favBtn) {
                e.preventDefault();
                toggleFavorite(favBtn.dataset.filename, favBtn);
            } else if (restoreBtn && canRestore) {
                e.preventDefault();
                restoreSnapshot(restoreBtn.dataset.filename);
            } else if (deleteBtn && !deleteBtn.disabled && canCreate) {
                e.preventDefault();
                deleteSnapshot(deleteBtn.dataset.filename);
            }
        });
    });

})();
