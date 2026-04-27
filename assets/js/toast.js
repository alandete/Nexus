/**
 * Nexus 2.0 — Toast (Flag Atlassian DS)
 * Sistema de notificaciones no intrusivas
 *
 * Uso:
 *   Toast.show('Usuario eliminado', 'success');
 *   Toast.show('Error al guardar', 'danger', { duration: 5000 });
 */

const Toast = {
    container: null,

    init() {
        this.container = document.createElement('div');
        this.container.className = 'toast-container';
        this.container.setAttribute('aria-live', 'polite');
        this.container.setAttribute('aria-atomic', 'true');
        document.body.appendChild(this.container);
    },

    show(message, type = 'info', opts = {}) {
        if (!this.container) this.init();

        const duration = opts.duration ?? 4000;
        const icons = {
            success: 'bi-check-circle-fill',
            danger:  'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info:    'bi-info-circle-fill',
        };
        const iconClass = icons[type] || icons.info;

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        toast.innerHTML = `
            <i class="bi ${iconClass} toast-icon" aria-hidden="true"></i>
            <div class="toast-content">
                <span class="toast-message"></span>
            </div>
            <button type="button" class="toast-close" aria-label="Cerrar">
                <i class="bi bi-x" aria-hidden="true"></i>
            </button>
        `;
        toast.querySelector('.toast-message').textContent = message;

        const dismiss = () => {
            toast.classList.add('toast-leaving');
            setTimeout(() => toast.remove(), 200);
        };

        toast.querySelector('.toast-close').addEventListener('click', dismiss);

        this.container.appendChild(toast);

        if (duration > 0) {
            setTimeout(dismiss, duration);
        }
    },

    success(msg, opts)  { this.show(msg, 'success', opts); },
    error(msg, opts)    { this.show(msg, 'danger', opts); },
    warning(msg, opts)  { this.show(msg, 'warning', opts); },
    info(msg, opts)     { this.show(msg, 'info', opts); },

    confirm(message, onConfirm, opts = {}) {
        if (!this.container) this.init();

        const labelConfirm = opts.labelConfirm ?? 'Confirmar';
        const labelCancel  = opts.labelCancel  ?? 'Cancelar';

        const toast = document.createElement('div');
        toast.className = 'toast toast-warning toast-confirm';
        toast.setAttribute('role', 'alertdialog');
        toast.setAttribute('aria-modal', 'false');
        toast.innerHTML = `
            <i class="bi bi-exclamation-triangle-fill toast-icon" aria-hidden="true"></i>
            <div class="toast-content">
                <span class="toast-message"></span>
                <div class="toast-confirm-actions">
                    <button type="button" class="btn btn-sm btn-danger toast-confirm-ok">${labelConfirm}</button>
                    <button type="button" class="btn btn-sm btn-secondary toast-confirm-cancel">${labelCancel}</button>
                </div>
            </div>
        `;
        toast.querySelector('.toast-message').textContent = message;

        const dismiss = () => {
            toast.classList.add('toast-leaving');
            setTimeout(() => toast.remove(), 200);
        };

        toast.querySelector('.toast-confirm-ok').addEventListener('click', () => {
            dismiss();
            onConfirm?.();
        });
        toast.querySelector('.toast-confirm-cancel').addEventListener('click', dismiss);

        this.container.appendChild(toast);
        toast.querySelector('.toast-confirm-ok').focus();
    },
};

window.Toast = Toast;
