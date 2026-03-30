<template>
    <!-- Install Button - Fixed at bottom on mobile -->
    <Transition name="slide-up">
        <div
            v-if="pwa.shouldShowInstallButton && !dismissed"
            class="fixed bottom-0 left-0 right-0 z-50 p-4 md:hidden"
        >
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 rounded-2xl shadow-2xl p-4 border border-slate-700">
                <div class="flex items-center gap-4">
                    <!-- App Icon -->
                    <div class="w-14 h-14 bg-blue-600 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg">
                        <span class="text-white font-bold text-lg">S&S</span>
                    </div>

                    <!-- Text -->
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-white text-sm">Install CRM App</h3>
                        <p class="text-slate-400 text-xs mt-0.5 truncate">
                            Add to home screen for quick access
                        </p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button
                            @click="dismiss"
                            class="p-2 text-slate-500 hover:text-slate-300 transition-colors"
                            aria-label="Dismiss"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                        <button
                            @click="handleInstall"
                            :disabled="installing"
                            class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-500 transition-all disabled:opacity-50 shadow-lg shadow-blue-500/25"
                        >
                            <span v-if="installing" class="flex items-center gap-2">
                                <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Installing...
                            </span>
                            <span v-else>Install</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Transition>

    <!-- Desktop Install Button (optional) -->
    <button
        v-if="pwa.shouldShowInstallButton && !dismissed && showDesktop"
        @click="handleInstall"
        class="hidden md:flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-500 transition-all"
    >
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
        </svg>
        Install App
    </button>

    <!-- iOS Instructions Modal -->
    <IOSInstallModal
        :visible="pwa.showIOSModal"
        @close="pwa.closeIOSModal"
    />
</template>

<script setup>
import { ref } from 'vue';
import { usePwaStore } from '@/stores/pwa';
import { useToastStore } from '@/stores/toast';
import IOSInstallModal from './IOSInstallModal.vue';

defineProps({
    showDesktop: {
        type: Boolean,
        default: false
    }
});

const pwa = usePwaStore();
const toast = useToastStore();
const installing = ref(false);
const dismissed = ref(false);

const handleInstall = async () => {
    installing.value = true;
    
    try {
        const result = await pwa.promptInstall();
        
        if (result.outcome === 'accepted') {
            toast.success('App installed successfully!', 'PWA');
        } else if (result.outcome === 'ios-modal') {
            // iOS modal is shown automatically
        } else if (result.outcome === 'dismissed') {
            // User dismissed the prompt
        }
    } catch (error) {
        console.error('[PWA] Install error:', error);
        toast.error('Failed to install app. Please try again.');
    } finally {
        installing.value = false;
    }
};

const dismiss = () => {
    dismissed.value = true;
    // Store dismissal in sessionStorage so it persists for this session
    sessionStorage.setItem('pwa-install-dismissed', 'true');
};

// Check if previously dismissed this session
if (sessionStorage.getItem('pwa-install-dismissed') === 'true') {
    dismissed.value = true;
}
</script>

<style scoped>
.slide-up-enter-active {
    transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
}

.slide-up-leave-active {
    transition: all 0.3s ease-in;
}

.slide-up-enter-from {
    transform: translateY(100%);
    opacity: 0;
}

.slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}
</style>

