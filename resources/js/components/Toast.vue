<template>
    <Teleport to="body">
        <Transition name="slide-fade">
            <div
                v-if="visible"
                class="fixed left-3 right-3 top-[calc(env(safe-area-inset-top)+0.75rem)] z-toast w-auto sm:left-auto sm:right-4 sm:top-4 sm:w-full sm:max-w-sm"
                :role="type === 'error' ? 'alert' : 'status'"
                :aria-live="type === 'error' ? 'assertive' : 'polite'"
                aria-atomic="true"
            >
                <div :class="['callout', calloutClass, 'shadow-overlay flex items-start gap-3']">
                    <component :is="icon" class="w-6 h-6 shrink-0" aria-hidden="true" />

                    <div class="flex-1 min-w-0">
                        <p v-if="title" class="break-words text-sm font-semibold">{{ title }}</p>
                        <p class="break-words text-sm">{{ message }}</p>
                    </div>

                    <button
                        type="button"
                        aria-label="Dismiss notification"
                        class="shrink-0 p-1 rounded-control hover:bg-slate-900/10 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                        @click="close"
                    >
                        <XMarkIcon class="icon-sm" aria-hidden="true" />
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue';
import {
    CheckCircleIcon,
    ExclamationTriangleIcon,
    InformationCircleIcon,
    XCircleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    type: {
        type: String,
        default: 'info', // success, error, warning, info
        validator: (value) => ['success', 'error', 'warning', 'info'].includes(value),
    },
    title: {
        type: String,
        default: '',
    },
    message: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['close']);

const close = () => {
    emit('close');
};

const CALLOUT = {
    success: 'callout-success',
    error: 'callout-danger',
    warning: 'callout-warning',
    info: 'callout-info',
};

const ICON = {
    success: CheckCircleIcon,
    error: XCircleIcon,
    warning: ExclamationTriangleIcon,
    info: InformationCircleIcon,
};

const calloutClass = computed(() => CALLOUT[props.type] || CALLOUT.info);
const icon = computed(() => ICON[props.type] || ICON.info);
</script>

<style scoped>
.slide-fade-enter-active {
    transition: all 0.3s ease-out;
}

.slide-fade-leave-active {
    transition: all 0.2s ease-in;
}

.slide-fade-enter-from {
    transform: translateX(100%);
    opacity: 0;
}

.slide-fade-leave-to {
    transform: translateX(100%);
    opacity: 0;
}
</style>
