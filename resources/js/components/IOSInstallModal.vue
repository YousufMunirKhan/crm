<template>
    <Teleport to="body">
        <Transition name="modal">
            <div
                v-if="visible"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm p-4"
                @click.self="$emit('close')"
            >
                <div class="bg-white rounded-3xl w-full max-w-md overflow-hidden shadow-2xl animate-slide-up">
                    <!-- Header -->
                    <div class="relative p-6 pb-4 text-center bg-gradient-to-b from-slate-50 to-white">
                        <!-- Close button -->
                        <button
                            @click="$emit('close')"
                            class="absolute top-4 right-4 p-2 text-slate-400 hover:text-slate-600 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>

                        <!-- App Icon -->
                        <div class="w-20 h-20 bg-slate-900 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-xl overflow-hidden">
                            <img
                                v-if="branding.faviconUrl"
                                :src="faviconDisplayUrl"
                                alt=""
                                class="w-full h-full object-cover"
                            >
                            <span v-else class="text-blue-500 font-bold text-2xl">S&S</span>
                        </div>

                        <h2 class="text-xl font-bold text-slate-900">Install CRM App</h2>
                        <p class="text-slate-500 text-sm mt-1">Add to your home screen for the best experience</p>
                    </div>

                    <!-- Instructions -->
                    <div class="p-6 pt-2 space-y-4">
                        <!-- Step 1 -->
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                <span class="text-blue-600 font-bold">1</span>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-slate-900">Tap the Share button</p>
                                <div class="flex items-center gap-2 mt-2">
                                    <div class="p-2 bg-slate-100 rounded-lg">
                                        <!-- iOS Share Icon -->
                                        <svg class="w-6 h-6 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 16V4m0 0l4 4m-4-4l-4 4" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M3 15v4a2 2 0 002 2h14a2 2 0 002-2v-4" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                    <span class="text-sm text-slate-500">at the bottom of Safari</span>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2 -->
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                <span class="text-blue-600 font-bold">2</span>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-slate-900">Scroll down and tap</p>
                                <div class="flex items-center gap-2 mt-2">
                                    <div class="p-2 bg-slate-100 rounded-lg flex items-center gap-2">
                                        <!-- Add to Home Screen Icon -->
                                        <svg class="w-6 h-6 text-slate-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="3" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="M12 8v8m-4-4h8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                        <span class="text-sm font-medium text-slate-700">Add to Home Screen</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3 -->
                        <div class="flex items-start gap-4">
                            <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-slate-900">Tap "Add" to confirm</p>
                                <p class="text-sm text-slate-500 mt-1">The app icon will appear on your home screen</p>
                            </div>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="p-4 border-t border-slate-100 bg-slate-50">
                        <button
                            @click="$emit('close')"
                            class="w-full py-3 bg-slate-900 text-white font-medium rounded-xl hover:bg-slate-800 transition-colors"
                        >
                            Got it!
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue';
import { useBrandingStore } from '@/stores/branding';
import { absolutePublicUrl } from '@/utils/branding';

defineProps({
    visible: {
        type: Boolean,
        default: false
    }
});

defineEmits(['close']);

const branding = useBrandingStore();
const faviconDisplayUrl = computed(() => absolutePublicUrl(branding.faviconUrl));
</script>

<style scoped>
.modal-enter-active {
    transition: all 0.3s ease-out;
}

.modal-leave-active {
    transition: all 0.2s ease-in;
}

.modal-enter-from {
    opacity: 0;
}

.modal-leave-to {
    opacity: 0;
}

.modal-enter-from .animate-slide-up,
.modal-leave-to .animate-slide-up {
    transform: translateY(100%);
}

.animate-slide-up {
    animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes slideUp {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}
</style>

