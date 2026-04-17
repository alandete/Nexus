/**
 * Nexus 2.0 — Confirm Modal
 * Dialogo de confirmacion reutilizable con Promise API
 *
 * Uso:
 *   ConfirmModal.show({
 *     title: 'Eliminar usuario',
 *     message: 'Esta accion no se puede deshacer.',
 *     acceptText: 'Eliminar',
 *     acceptVariant: 'danger',
 *     icon: 'bi-trash',
 *   }).then(confirmed => { ... });
 */

const ConfirmModal = {
    overlay: null,
    modal: null,
    iconEl: null,
    titleEl: null,
    messageEl: null,
    cancelBtn: null,
    acceptBtn: null,
    previousFocus: null,
    resolver: null,

    init() {
        this.overlay    = document.getElementById('confirmModalOverlay');
        this.modal      = document.getElementById('confirmModal');
        this.iconEl     = document.getElementById('confirmModalIcon');
        this.titleEl    = document.getElementById('confirmModalTitle');
        this.messageEl  = document.getElementById('confirmModalMessage');
        this.cancelBtn  = document.getElementById('confirmModalCancel');
        this.acceptBtn  = document.getElementById('confirmModalAccept');

        if (!this.modal) return;

        this.cancelBtn.addEventListener('click', () => this.resolve(false));
        this.overlay.addEventListener('click', () => this.resolve(false));
        this.acceptBtn.addEventListener('click', () => this.resolve(true));

        document.addEventListener('keydown', (e) => {
            if (!this.isOpen()) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                this.resolve(false);
            } else if (e.key === 'Tab') {
                this.trapFocus(e);
            }
        });
    },

    isOpen() {
        return this.modal && this.modal.classList.contains('active');
    },

    show(opts) {
        return new Promise((resolve) => {
            this.resolver = resolve;
            this.previousFocus = document.activeElement;

            this.titleEl.textContent = opts.title || 'Confirmar';
            this.messageEl.textContent = opts.message || '';

            // Icono
            const iconClass = opts.icon || 'bi-exclamation-triangle';
            this.iconEl.innerHTML = `<i class="bi ${iconClass}" aria-hidden="true"></i>`;
            this.iconEl.className = 'confirm-modal-icon';
            const variant = opts.variant || 'warning';
            this.iconEl.classList.add('confirm-modal-icon-' + variant);

            // Textos de botones
            this.acceptBtn.textContent = opts.acceptText || 'Confirmar';
            this.cancelBtn.textContent = opts.cancelText || 'Cancelar';

            // Estilo del boton de aceptar
            this.acceptBtn.className = 'btn btn-' + (opts.acceptVariant || 'primary');

            // Mostrar
            this.overlay.classList.add('active');
            this.modal.classList.add('active');
            this.modal.setAttribute('aria-hidden', 'false');
            this.overlay.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            // Focus en cancelar por defecto (accion menos destructiva)
            setTimeout(() => this.cancelBtn.focus(), 50);
        });
    },

    resolve(value) {
        this.overlay.classList.remove('active');
        this.modal.classList.remove('active');
        this.modal.setAttribute('aria-hidden', 'true');
        this.overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        if (this.previousFocus && this.previousFocus.focus) {
            this.previousFocus.focus();
        }

        if (this.resolver) {
            this.resolver(value);
            this.resolver = null;
        }
    },

    trapFocus(e) {
        const focusables = this.modal.querySelectorAll('button:not([disabled])');
        if (focusables.length === 0) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault(); last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault(); first.focus();
        }
    },
};

document.addEventListener('DOMContentLoaded', () => ConfirmModal.init());

window.ConfirmModal = ConfirmModal;
