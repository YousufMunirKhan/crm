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

async function copy() {
    const text = String(props.value ?? '').trim();

    if (text === '') return;

    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
        } else {
            // navigator.clipboard is unavailable outside a secure context, and
            // the CRM is reached over plain http on the office network.
            const area = document.createElement('textarea');
            area.value = text;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }

        copied.value = true;
        toast.success(`${props.label.charAt(0).toUpperCase()}${props.label.slice(1)} copied`);
        setTimeout(() => { copied.value = false; }, 1600);
    } catch {
        toast.error('Could not copy. Long-press the text to copy it instead.');
    }
}
</script>
