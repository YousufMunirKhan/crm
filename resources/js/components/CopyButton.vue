<template>
    <button
        type="button"
        class="inline-flex shrink-0 items-center justify-center rounded-control text-slate-500
               hover:bg-slate-100 hover:text-slate-800 transition-colors touch-manipulation
               focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
        :class="sizeClass"
        :aria-label="`Copy ${label}`"
        :title="copied ? 'Copied' : `Copy ${label}`"
        @click.stop.prevent="copy"
    >
        <CheckIcon v-if="copied" class="icon-sm text-success-700" aria-hidden="true" />
        <ClipboardIcon v-else class="icon-sm" aria-hidden="true" />
    </button>
</template>

<script setup>
import { ref } from 'vue';
import { CheckIcon, ClipboardIcon } from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';

/**
 * Copies a value to the clipboard.
 *
 * Phone numbers on a phone are a tap-to-call link, which is right most of the
 * time and useless when what you actually want is to paste the number into
 * WhatsApp or save it to contacts. Long-pressing a link to reach "copy" is not
 * something people find. This is the second, explicit way.
 */
const props = defineProps({
    value: { type: [String, Number], default: '' },
    /** Used in the accessible name and the toast: "Copy phone number". */
    label: { type: String, default: 'value' },
    /** Touch targets need 44px; dense desktop tables do not. */
    size: { type: String, default: 'touch' },
});

const toast = useToastStore();
const copied = ref(false);

const sizeClass = props.size === 'touch'
    ? 'h-11 w-11 sm:h-8 sm:w-8'
    : 'h-8 w-8';

/**
 * The old-fashioned way, and still the one that works in the most places.
 *
 * iOS Safari ignores select() on a textarea unless it is contentEditable and
 * not readonly, and it will not copy from an element with display:none or
 * zero size - hence the visible-but-invisible positioning.
 */
function copyByExecCommand(text) {
    const area = document.createElement('textarea');

    area.value = text;
    area.contentEditable = 'true';
    area.style.position = 'fixed';
    area.style.top = '0';
    area.style.left = '0';
    area.style.width = '1px';
    area.style.height = '1px';
    area.style.padding = '0';
    area.style.border = 'none';
    area.style.outline = 'none';
    area.style.boxShadow = 'none';
    area.style.background = 'transparent';
    area.style.opacity = '0';

    document.body.appendChild(area);

    const selection = document.getSelection();
    const previous = selection.rangeCount > 0 ? selection.getRangeAt(0) : null;

    area.focus();
    area.select();
    area.setSelectionRange(0, text.length);

    let ok = false;

    try {
        ok = document.execCommand('copy');
    } catch {
        ok = false;
    }

    area.remove();

    // Put the user's own selection back where it was.
    if (previous) {
        selection.removeAllRanges();
        selection.addRange(previous);
    }

    return ok;
}

async function copy() {
    const text = String(props.value ?? '').trim();

    if (text === '') return;

    let ok = false;

    // The async API is blocked in more places than it looks: a page served over
    // plain http, an embedded frame without clipboard-write permission, a
    // browser that refuses while the document is not focused. When it refuses
    // it rejects, and the older command still works - so this is a fallback
    // rather than a branch, which is what it wrongly was before.
    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            ok = true;
        } catch {
            ok = false;
        }
    }

    if (! ok) {
        ok = copyByExecCommand(text);
    }

    if (! ok) {
        // Nothing worked, so hand over something the person can actually use
        // rather than telling them to long-press and hope.
        window.prompt(`Copy the ${props.label}:`, text);

        return;
    }

    copied.value = true;
    toast.success(`${props.label.charAt(0).toUpperCase()}${props.label.slice(1)} copied`);
    setTimeout(() => { copied.value = false; }, 1600);
}
</script>
