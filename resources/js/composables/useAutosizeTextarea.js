import { ref, watch, onMounted, nextTick } from 'vue';

/**
 * Grows a textarea with its content while respecting min-height, optional CSS max-height,
 * and manual resize (height is not shrunk below the current box while the user has expanded it).
 *
 * @param {() => string|undefined|null} textSource - reactive getter, e.g. () => form.value.description
 * @param {{ minHeightPx?: number }} [options]
 */
export function useAutosizeTextarea(textSource, options = {}) {
    const minHeightPx = options.minHeightPx ?? 208;
    const textareaRef = ref(null);

    function syncHeight() {
        const el = textareaRef.value;
        if (!el || !(el instanceof HTMLTextAreaElement)) return;

        const prev = el.offsetHeight;
        el.style.overflowY = 'hidden';
        el.style.height = 'auto';
        const content = el.scrollHeight;
        let next = Math.max(minHeightPx, prev, content);

        const maxStr = getComputedStyle(el).maxHeight;
        const maxPx = parseFloat(maxStr);
        if (Number.isFinite(maxPx) && maxPx > 0 && next > maxPx) {
            next = maxPx;
            el.style.overflowY = 'auto';
        } else {
            el.style.overflowY = 'hidden';
        }

        el.style.height = `${next}px`;
    }

    onMounted(() => nextTick(syncHeight));
    watch(textSource, () => nextTick(syncHeight), { flush: 'post' });

    return { textareaRef, syncHeight };
}
