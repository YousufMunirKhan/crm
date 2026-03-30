<template>
    <Teleport to="body">
        <Transition name="slide-fade">
            <div
                v-if="visible"
                class="fixed top-4 right-4 z-50 max-w-sm w-full"
            >
                <div
                    class="rounded-xl shadow-2xl p-4 flex items-start gap-3 border"
                    :class="typeClasses"
                >
                    <!-- Icon -->
                    <div class="flex-shrink-0 w-6 h-6">
                        <svg v-if="type === 'success'" class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <svg v-else-if="type === 'error'" class="w-6 h-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <svg v-else-if="type === 'warning'" class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <svg v-else class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <p v-if="title" class="font-semibold text-sm" :class="titleClass">{{ title }}</p>
                        <p class="text-sm" :class="messageClass">{{ message }}</p>
                    </div>

                    <!-- Close Button -->
                    <button
                        @click="close"
                        class="flex-shrink-0 p-1 rounded-lg hover:bg-black/10 transition-colors"
                    >
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    type: {
        type: String,
        default: 'info', // success, error, warning, info
        validator: (value) => ['success', 'error', 'warning', 'info'].includes(value)
    },
    title: {
        type: String,
        default: ''
    },
    message: {
        type: String,
        required: true
    }
});

const emit = defineEmits(['close']);

const close = () => {
    emit('close');
};

const typeClasses = computed(() => {
    switch (props.type) {
        case 'success':
            return 'bg-green-50 border-green-200';
        case 'error':
            return 'bg-red-50 border-red-200';
        case 'warning':
            return 'bg-amber-50 border-amber-200';
        default:
            return 'bg-blue-50 border-blue-200';
    }
});

const titleClass = computed(() => {
    switch (props.type) {
        case 'success':
            return 'text-green-800';
        case 'error':
            return 'text-red-800';
        case 'warning':
            return 'text-amber-800';
        default:
            return 'text-blue-800';
    }
});

const messageClass = computed(() => {
    switch (props.type) {
        case 'success':
            return 'text-green-700';
        case 'error':
            return 'text-red-700';
        case 'warning':
            return 'text-amber-700';
        default:
            return 'text-blue-700';
    }
});
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

