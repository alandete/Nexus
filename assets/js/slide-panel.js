/**
 * Nexus 2.0 — Slide Panel (Componente Reutilizable)
 * Panel lateral deslizante con focus trap y accesibilidad
 */

const SlidePanel = {
    panel: null,
    overlay: null,
    closeBtn: null,
    titleElement: null,
    bodyElement: null,
    footerElement: null,
    previousFocus: null,

    init() {
        this.panel = document.getElementById('slidePanel');
        this.overlay = document.getElementById('slidePanelOverlay');
        this.closeBtn = document.getElementById('slidePanelClose');
        this.titleElement = document.getElementById('slidePanelTitle');
        this.bodyElement = document.getElementById('slidePanelBody');
        this.footerElement = document.getElementById('slidePanelFooter');

        if (!this.panel || !this.overlay) return;

        this.closeBtn.addEventListener('click', () => this.close());
        this.overlay.addEventListener('click', () => this.close());

        document.addEventListener('keydown', (e) => {
            if (!this.isOpen()) return;
            if (e.key === 'Escape') {
                this.close();
            } else if (e.key === 'Tab') {
                this.handleTabKey(e);
            }
        });
    },

    open(title, content) {
        this.previousFocus = document.activeElement;
        this.titleElement.textContent = title;

        if (typeof content === 'string') {
            this.bodyElement.innerHTML = content;
        } else if (content instanceof HTMLElement) {
            this.bodyElement.innerHTML = '';
            this.bodyElement.appendChild(content);
        }

        requestAnimationFrame(() => {
            this.overlay.classList.add('active');
            this.panel.classList.add('active');
            this.panel.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';

            const firstFocusable = this.panel.querySelector(
                'input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), [tabindex]:not([tabindex="-1"])'
            );
            if (firstFocusable) {
                firstFocusable.focus();
            } else {
                this.closeBtn.focus();
            }
        });
    },

    close() {
        this.overlay.classList.remove('active');
        this.panel.classList.remove('active');
        this.panel.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        setTimeout(() => {
            this.bodyElement.innerHTML = '';
            if (this.footerElement) {
                this.footerElement.innerHTML = '';
                this.footerElement.classList.remove('active');
            }
        }, 350);

        if (this.previousFocus && this.previousFocus.focus) {
            this.previousFocus.focus();
        }
    },

    handleTabKey(e) {
        const sel = 'button:not([disabled]), [href], input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        const focusable = Array.from(this.panel.querySelectorAll(sel)).filter(el => el.offsetParent !== null);

        if (focusable.length === 0) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (e.shiftKey) {
            if (document.activeElement === first) {
                last.focus();
                e.preventDefault();
            }
        } else {
            if (document.activeElement === last) {
                first.focus();
                e.preventDefault();
            }
        }
    },

    isOpen() {
        return this.panel && this.panel.classList.contains('active');
    },

    setTitle(title) {
        this.titleElement.textContent = title;
    },

    setContent(content) {
        if (typeof content === 'string') {
            this.bodyElement.innerHTML = content;
        } else if (content instanceof HTMLElement) {
            this.bodyElement.innerHTML = '';
            this.bodyElement.appendChild(content);
        }
    },

    setFooter(content) {
        if (!this.footerElement) return;
        if (typeof content === 'string') {
            this.footerElement.innerHTML = content;
        } else if (content instanceof HTMLElement) {
            this.footerElement.innerHTML = '';
            this.footerElement.appendChild(content);
        }
        this.footerElement.classList.add('active');
    }
};

document.addEventListener('DOMContentLoaded', () => {
    SlidePanel.init();
});
