<template>
    <component
        :is="tag"
        :class="classes"
        v-bind="extraBindings"
        :aria-busy="loading ? 'true' : undefined"
        @click="onClick"
    >
        <span v-if="loading" class="spinner" aria-hidden="true" />
        <slot v-else name="icon" />
        <slot />
    </component>
</template>

<script setup>
import { computed } from 'vue';

defineOptions({ inheritAttrs: true });

const props = defineProps({
    variant: { type: String, default: 'outline' }, // primary|soft|outline|ghost|ghost-danger|danger|success
    size: { type: String, default: 'md' }, // sm|md|lg|icon
    type: { type: String, default: 'button' },
    to: { type: [String, Object], default: null },
    href: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    /** Shows .spinner, sets aria-busy and blocks the click. */
    loading: { type: Boolean, default: false },
    blockMobile: { type: Boolean, default: false },
    /** REQUIRED when size === 'icon'; becomes aria-label. */
    label: { type: String, default: '' },
});

const emit = defineEmits(['click']);

if (import.meta.env.DEV && props.size === 'icon' && !props.label) {
    console.warn('[BaseButton] size="icon" requires a `label` prop for aria-label.');
}

const tag = computed(() => {
    if (props.to) return 'router-link';
    if (props.href) return 'a';
    return 'button';
});

const isInert = computed(() => props.disabled || props.loading);

const SIZES = { sm: 'btn-sm', md: 'btn-md', lg: 'btn-lg', icon: 'btn-icon' };
const VARIANTS = {
    primary: 'btn-primary',
    soft: 'btn-soft',
    outline: 'btn-outline',
    ghost: 'btn-ghost',
    'ghost-danger': 'btn-ghost-danger',
    danger: 'btn-danger',
    success: 'btn-success',
};

const classes = computed(() => [
    'btn',
    SIZES[props.size] || SIZES.md,
    VARIANTS[props.variant] || VARIANTS.outline,
    props.blockMobile ? 'btn-block-mobile' : '',
    // router-link/anchor cannot use :disabled, so mimic it visually + block clicks.
    tag.value !== 'button' && isInert.value ? 'opacity-50 pointer-events-none' : '',
]);

const extraBindings = computed(() => {
    const bindings = {};
    if (props.label) bindings['aria-label'] = props.label;

    if (tag.value === 'button') {
        bindings.type = props.type;
        bindings.disabled = isInert.value;
    } else if (tag.value === 'router-link') {
        bindings.to = props.to;
        if (isInert.value) bindings['aria-disabled'] = 'true';
    } else {
        bindings.href = props.href;
        if (isInert.value) bindings['aria-disabled'] = 'true';
    }

    return bindings;
});

function onClick(event) {
    if (isInert.value) {
        event.preventDefault();
        event.stopPropagation();
        return;
    }
    emit('click', event);
}
</script>
