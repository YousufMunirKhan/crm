<template>
    <!-- Install Button - Fixed at bottom on mobile -->
    <Transition name="slide-up">
        <div
            v-if="pwa.shouldShowInstallButton && !dismissed"
            class="fixed bottom-0 left-0 right-0 z-modal p-4 md:hidden"
        >
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 rounded-2xl shadow-2xl p-4 border border-slate-700">
                <div class="flex items-center gap-4">
                    <!-- App Icon -->
                    <div class="w-14 h-14 bg-primary-600 rounded-xl flex items-center justify-center flex-shrink-0 shadow-lg overflow-hidden">
                        <img
                            v-if="branding.faviconUrl"
                            :src="faviconDisplayUrl"
                            alt=""
                            class="w-full h-full object-cover"
                        >
                        <span v-else class="text-white font-bold text-lg">S&S</span>
                    </div>

                    <!-- Text -->
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-white text-sm">Install CRM App</h3>
                        <p class="text-slate-500 text-xs mt-0.5 truncate">
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
<XMarkIcon class="icon" aria-hidden="true" />
                        </button>
                        <button
                            @click="handleInstall"
                            :disabled="installing"
                            class="px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-control hover:bg-primary-700 transition-colors disabled:opacity-50 shadow-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                        >
                            <span v-if="installing" class="flex items-center gap-2">
                                <span class="spinner" role="status" aria-label="Installing" />
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
        class="hidden md:flex items-center gap-2 px-4 py-2 bg-primary-600 text-white text-sm font-medium rounded-control hover:bg-primary-700 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
    >
<ArrowDownTrayIcon class="icon-sm" aria-hidden="true" />
        Install App
    </button>

    <!-- iOS Instructions Modal -->
    <IOSInstallModal
        :visible="pwa.showIOSModal"
        @close="pwa.closeIOSModal"
    />
</template>

<script setup>
import { ArrowDownTrayIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { ref, computed } from 'vue';
import { usePwaStore } from '@/stores/pwa';
import { useToastStore } from '@/stores/toast';
import { useBrandingStore } from '@/stores/branding';
import { absolutePublicUrl } from '@/utils/branding';
import IOSInstallModal from './IOSInstallModal.vue';

defineProps({
    showDesktop: {
        type: Boolean,
        default: false
    }
});

const pwa = usePwaStore();
const toast = useToastStore();
const branding = useBrandingStore();

const faviconDisplayUrl = computed(() => absolutePublicUrl(branding.faviconUrl));
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

