import { nextTick, onBeforeUnmount, watch } from 'vue';
import { lockBodyScroll, unlockBodyScroll } from './useBodyScrollLock';

const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(',');

/**
 * Stack of currently-open traps. Dialogs nest now (a ConfirmDialog opened from
 * inside a BaseModal), and without this only the topmost one may answer Escape
 * and Tab - otherwise one Escape closes the whole pile.
 */
const stack = [];

/**
 * Traps focus inside a dialog while it is open, restores it on close, and
 * locks background scroll.
 *
 * None of the app's 36 hand-rolled modals did any of this: Tab walked out into
 * the page behind, Escape did nothing, and the body kept scrolling underneath.
 *
 * @param {import('vue').Ref<HTMLElement|null>} containerRef
 * @param {import('vue').Ref<boolean>} isOpen
 * @param {() => void} onClose
 */
export function useFocusTrap(containerRef, isOpen, onClose) {
    let previouslyFocused = null;
    const handle = {};

    function isTopmost() {
        return stack[stack.length - 1] === handle;
    }

    function focusableElements() {
        if (!containerRef.value) return [];
        return Array.from(containerRef.value.querySelectorAll(FOCUSABLE)).filter(
            (el) => el.offsetParent !== null || el === document.activeElement,
        );
    }

    function onKeydown(event) {
        // Only the dialog on top of the stack reacts.
        if (!isTopmost()) return;

        if (event.key === 'Escape') {
            event.stopPropagation();
            onClose?.();
            return;
        }

        if (event.key !== 'Tab') return;

        const elements = focusableElements();
        if (elements.length === 0) {
            event.preventDefault();
            return;
        }

        const first = elements[0];
        const last = elements[elements.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function activate() {
        if (stack.includes(handle)) return;

        previouslyFocused = document.activeElement;

        // Counted centrally, so a drawer closing elsewhere cannot hand the
        // page back its scrollbar while this dialog is still open.
        lockBodyScroll(handle);
        stack.push(handle);

        document.addEventListener('keydown', onKeydown, true);

        nextTick(() => {
            const elements = focusableElements();
            (elements[0] || containerRef.value)?.focus?.();
        });
    }

    function deactivate() {
        document.removeEventListener('keydown', onKeydown, true);

        const index = stack.indexOf(handle);
        if (index === -1) return;
        stack.splice(index, 1);

        unlockBodyScroll(handle);

        previouslyFocused?.focus?.();
        previouslyFocused = null;
    }

    watch(
        isOpen,
        (open) => (open ? activate() : deactivate()),
        { immediate: true },
    );

    onBeforeUnmount(deactivate);
}
