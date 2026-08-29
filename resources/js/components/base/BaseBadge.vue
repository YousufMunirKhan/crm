<template>
    <span :class="['badge', toneClass]">
        <span v-if="dot" class="badge-dot" aria-hidden="true" />
        <slot>{{ fallbackLabel }}</slot>
    </span>
</template>

<script>
/**
 * The single source of truth for domain status -> visual tone.
 * Views must not re-derive this map.
 */
export const STATUS_TONE = {
    active: 'success', won: 'success', paid: 'success', completed: 'success',
    approved: 'success', resolved: 'success', delivered: 'success', sent: 'success',
    pending: 'warning', draft: 'warning', scheduled: 'warning', partial: 'warning',
    in_progress: 'warning', overdue: 'danger', failed: 'danger', lost: 'danger',
    cancelled: 'danger', inactive: 'danger', rejected: 'danger', unpaid: 'danger',
    open: 'primary', new: 'primary', contacted: 'primary', qualified: 'primary',
};
</script>

<script setup>
import { computed } from 'vue';

const TONE_CLASS = {
    neutral: 'badge-neutral',
    primary: 'badge-primary',
    success: 'badge-success',
    warning: 'badge-warning',
    danger: 'badge-danger',
};

const props = defineProps({
    tone: { type: String, default: 'neutral' },
    /** Raw domain status. When set, the tone is derived from STATUS_TONE. */
    status: { type: String, default: '' },
    dot: { type: Boolean, default: false },
});

const resolvedTone = computed(() => {
    if (props.status) {
        // Unknown statuses fall back to neutral - never throw.
        return STATUS_TONE[String(props.status).toLowerCase().replace(/[\s-]+/g, '_')] || 'neutral';
    }
    return props.tone;
});

const toneClass = computed(() => TONE_CLASS[resolvedTone.value] || TONE_CLASS.neutral);

const fallbackLabel = computed(() => {
    if (!props.status) return '';
    return String(props.status)
        .replace(/[_-]+/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
});
</script>
