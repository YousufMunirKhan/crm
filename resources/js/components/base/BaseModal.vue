<template>
    <Teleport to="body">
        <div
            v-if="modelValue"
            class="modal-backdrop flex items-center justify-center p-4"
            @click.self="close('backdrop')"
        >
            <div
                ref="panel"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="title ? headingId : undefined"
                tabindex="-1"
                :class="['form-card flex w-full max-h-[90vh] flex-col shadow-xl shadow-slate-900/10 focus-visible:outline-none', sizeClass]"
            >
                <header v-if="title || $slots.header" class="form-section-head flex shrink-0 items-start justify-between gap-4">
                    <div class="min-w-0">
                        <slot name="header">
                            <h2 :id="headingId" class="form-section-title">{{ title }}</h2>
                            <p v-if="description" class="form-section-desc">{{ description }}</p>
                        </slot>
                    </div>
                    <button
                        v-if="dismissible"
                        type="button"
                        class="shrink-0 rounded-control p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                        aria-label="Close dialog"
                        @click="close('button')"
                    >
                        <XMarkIcon class="icon" aria-hidden="true" />
                    </button>
                </header>

                <div class="form-body min-h-0 flex-1 overflow-y-auto">
                    <slot />
                </div>

                <footer v-if="$slots.actions" class="form-actions shrink-0">
                    <slot name="actions" />
                </footer>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, ref, useId } from 'vue';
import { XMarkIcon } from '@heroicons/vue/24/outline';
import { useFocusTrap } from '@/composables/useFocusTrap';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    size: { type: String, default: 'md' }, // sm | md | lg | xl
    dismissible: { type: Boolean, default: true },
    /** Set false where losing work would be costly, e.g. a long form. */
    closeOnBackdrop: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'close']);

const panel = ref(null);
const headingId = `modal-heading-${useId()}`;

const sizeClass = computed(
    () => ({ sm: 'max-w-sm', md: 'max-w-2xl', lg: 'max-w-4xl', xl: 'max-w-6xl' })[props.size] || 'max-w-2xl',
);

function close(source) {
    if (source === 'backdrop' && !props.closeOnBackdrop) return;
    if (!props.dismissible) return;
    emit('update:modelValue', false);
    emit('close');
}

// Escape, tab-cycling, scroll lock and focus restoration.
useFocusTrap(
    panel,
    computed(() => props.modelValue),
    () => close('escape'),
);
</script>
