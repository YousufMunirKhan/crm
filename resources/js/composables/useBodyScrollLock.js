/**
 * The single owner of `document.body.style.overflow`.
 *
 * Two things used to write it independently - the dialog focus trap and the
 * mobile navigation drawer - so whichever released last won. Closing a drawer
 * while a dialog was open handed scrolling back to the page behind the dialog;
 * closing them in the other order left the page locked with nothing on screen
 * to explain why, which reads as the app having frozen.
 *
 * Locks are counted rather than set, so overlapping holders behave: scrolling
 * comes back when the last holder lets go, and only then.
 */
const holders = new Set();
let originalOverflow = null;

function apply() {
    if (typeof document === 'undefined') return;

    if (holders.size > 0) {
        if (originalOverflow === null) {
            originalOverflow = document.body.style.overflow;
        }
        document.body.style.overflow = 'hidden';

        return;
    }

    if (originalOverflow !== null) {
        document.body.style.overflow = originalOverflow;
        originalOverflow = null;
    }
}

/**
 * @param {object} owner  Any stable object identifying the holder.
 */
export function lockBodyScroll(owner) {
    holders.add(owner);
    apply();
}

export function unlockBodyScroll(owner) {
    holders.delete(owner);
    apply();
}

/**
 * Last resort for a lock whose owner vanished - a component destroyed mid
 * transition, a dialog removed by a route change. Called on navigation so a
 * leaked lock cannot outlive the screen that took it.
 */
export function releaseAllBodyScrollLocks() {
    if (holders.size === 0) return;

    holders.clear();
    apply();
}

export function bodyScrollLockCount() {
    return holders.size;
}
