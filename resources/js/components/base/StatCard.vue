<template>
    <component
        :is="to ? 'router-link' : 'div'"
        :to="to || undefined"
        :class="['stat-card', to ? 'card-interactive block' : '']"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="stat-label">{{ label }}</p>
                <p class="stat-value">{{ value }}</p>
                <p v-if="caption" class="stat-caption">{{ caption }}</p>
            </div>
            <span v-if="$slots.icon" :class="['stat-icon', toneClass]">
                <slot name="icon" />
            </span>
        </div>
    </component>
</template>

<script setup>
import { computed } from 'vue';

// -700/-800 glyph steps: -600 on a -100 tint fails contrast at these stroke widths.
const TONE_CLASS = {
    neutral: 'bg-slate-100 text-slate-700',
    primary: 'bg-primary-100 text-primary-700',
    success: 'bg-success-100 text-success-800',
    warning: 'bg-warning-100 text-warning-800',
    danger: 'bg-danger-100 text-danger-800',
};

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    caption: { type: String, default: '' },
    /** Icon tint only - the value itself stays slate-900. */
    tone: { type: String, default: 'neutral' },
    /** Makes the whole tile a real link: keyboard-reachable and middle-clickable. */
    to: { type: [String, Object], default: null },
});

const toneClass = computed(() => TONE_CLASS[props.tone] || TONE_CLASS.neutral);
</script>
