<template>
    <div v-if="auth.initialized">
        <AppLayout v-if="showLayout" />
        <router-view v-else />
    </div>
    <div v-else class="flex items-center justify-center min-h-screen bg-slate-100">
        <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-slate-900 mx-auto"></div>
            <p class="mt-4 text-slate-600">Loading...</p>
        </div>
    </div>
    
    <!-- Global Toast Notifications -->
    <GlobalToast />
    
    <!-- PWA Install Button -->
    <PwaInstallButton />
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { usePwaStore } from '@/stores/pwa';
import AppLayout from '@/layouts/AppLayout.vue';
import GlobalToast from '@/components/GlobalToast.vue';
import PwaInstallButton from '@/components/PwaInstallButton.vue';

const route = useRoute();
const auth = useAuthStore();
const pwa = usePwaStore();

const showLayout = computed(() => {
    // Show layout only if authenticated AND not on login page
    return auth.isAuthenticated && route.name !== 'login';
});

onMounted(async () => {
    // Initialize PWA
    pwa.initialize();
    pwa.loadSettings();
    
    if (!auth.initialized) {
        await auth.bootstrap();
    }
});
</script>


