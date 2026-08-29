<template>
    <div class="w-full min-w-0 max-w-2xl mx-auto px-3 sm:px-4 py-10 sm:py-16">
        <div class="form-card text-center px-6 py-12 sm:py-16">
            <p class="text-5xl sm:text-6xl font-bold text-primary-600 tracking-tight">404</p>
            <h1 class="mt-4 text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">
                We couldn't find that page
            </h1>
            <p class="mt-2 text-sm text-slate-500 leading-relaxed max-w-md mx-auto">
                The link may be out of date, or the page may have moved. The address we tried was
                <code class="px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 text-xs break-all">{{ attemptedPath }}</code>
            </p>
            <div class="mt-8 flex flex-col-reverse sm:flex-row items-center justify-center gap-3">
                <BaseButton variant="outline" block-mobile @click="goBack">
                    <template #icon><ArrowLeftIcon class="icon" aria-hidden="true" /></template>
                    Go back
                </BaseButton>
                <BaseButton variant="primary" :to="homePath" block-mobile>
                    <template #icon><HomeIcon class="icon" aria-hidden="true" /></template>
                    Back to dashboard
                </BaseButton>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeftIcon, HomeIcon } from '@heroicons/vue/24/outline';
import { useAuthStore } from '@/stores/auth';
import { BaseButton } from '@/components/base';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();

const attemptedPath = computed(() => route.fullPath);

const homePath = computed(() => {
    const role = auth.user?.role?.name;
    return role === 'Sales' || role === 'CallAgent' ? '/dashboard/sales' : '/';
});

function goBack() {
    if (window.history.length > 1) {
        router.back();
        return;
    }
    router.push(homePath.value);
}
</script>
