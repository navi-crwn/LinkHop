// x-modal="isOpen": dialog semantics for a modal panel.
// - role="dialog", aria-modal and aria-labelledby (first heading inside)
// - moves focus into the dialog when it opens and back to the opener when it closes
// - keeps Tab inside the dialog
// - Escape closes it: runs data-modal-close="..." if given, otherwise sets `isOpen = false`
// Works for dialogs shown with x-show (the expression toggles) and with x-if
// (the element is created and removed; the expression is then always truthy).
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), iframe, [contenteditable="true"], [tabindex]:not([tabindex="-1"])';
let uid = 0;

const focusables = (el) => Array.from(el.querySelectorAll(FOCUSABLE))
    .filter((node) => node.offsetParent !== null || node === document.activeElement);

export default function (Alpine) {
    Alpine.directive('modal', (el, { expression }, { evaluate, evaluateLater, effect, cleanup }) => {
        el.setAttribute('role', el.getAttribute('role') || 'dialog');
        el.setAttribute('aria-modal', 'true');
        if (!el.hasAttribute('tabindex')) el.setAttribute('tabindex', '-1');
        if (!el.hasAttribute('aria-labelledby') && !el.hasAttribute('aria-label')) {
            const heading = el.querySelector('h1, h2, h3, h4, .modal-title');
            if (heading) {
                heading.id ||= `modal-title-${++uid}`;
                el.setAttribute('aria-labelledby', heading.id);
            }
        }

        const close = evaluateLater(el.dataset.modalClose || `${expression} = false`);
        let opener = null;
        let open = false;

        const focusInside = () => {
            // Wait for x-show / x-if to make the dialog visible.
            setTimeout(() => {
                if (!el.isConnected || el.contains(document.activeElement)) return;
                const auto = el.querySelector('[autofocus]');
                (auto || focusables(el)[0] || el).focus({ preventScroll: true });
            }, 30);
        };
        const restoreFocus = () => {
            const target = opener;
            opener = null;
            if (target && target.isConnected && typeof target.focus === 'function') {
                setTimeout(() => target.focus({ preventScroll: true }), 0);
            }
        };

        effect(() => {
            const now = !!evaluate(expression);
            if (now && !open) {
                opener = document.activeElement;
                focusInside();
            } else if (!now && open) {
                restoreFocus();
            }
            open = now;
        });

        const onKeydown = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                close();
                return;
            }
            if (event.key !== 'Tab') return;
            const items = focusables(el);
            if (items.length === 0) {
                event.preventDefault();
                return;
            }
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && (document.activeElement === first || document.activeElement === el)) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        };
        el.addEventListener('keydown', onKeydown);
        cleanup(() => {
            el.removeEventListener('keydown', onKeydown);
            if (open) restoreFocus();
        });
    });
}
