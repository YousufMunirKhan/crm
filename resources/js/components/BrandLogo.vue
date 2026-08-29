<template>
    <!--
        The brand logo, with a text fallback.

        A logo path can outlive its file - the setting is not cleared when the
        upload is deleted - and every render site previously used a bare <img>,
        so a missing file showed a broken-image icon and the alt text. That was
        the first thing a user saw on the sign-in screen.
    -->
    <img
        v-if="src && !failed"
        :src="src"
        :alt="companyName ? `${companyName} logo` : 'Company logo'"
        :class="imgClass"
        @error="failed = true"
    />
    <span v-else :class="textClass">
        {{ companyName || 'Switch &amp; Save CRM' }}
    </span>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    src: { type: String, default: '' },
    companyName: { type: String, default: '' },
    imgClass: { type: String, default: 'max-h-16 object-contain' },
    /** Shown when there is no logo, or the file is gone. */
    textClass: { type: String, default: 'font-bold text-slate-900' },
});

const failed = ref(false);

// A new src deserves a fresh attempt.
watch(() => props.src, () => { failed.value = false; });
</script>
