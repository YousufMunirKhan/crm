<template>
    <div class="w-full max-w-[1920px] mx-auto flex flex-col min-h-[calc(100dvh-9rem)]">
        <div v-if="loading" class="flex flex-1 items-center justify-center py-24">
            <div class="animate-spin rounded-full h-10 w-10 border-2 border-slate-300 border-t-slate-900" />
        </div>
        <div v-else-if="loadError" class="flex flex-col items-center justify-center flex-1 gap-4 py-16 px-4 text-center">
            <p class="text-red-600 font-medium">{{ loadError }}</p>
            <RouterLink
                :to="{ name: 'templates', query: { tab: 'email' } }"
                class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50"
            >
                ← Back to templates
            </RouterLink>
        </div>
        <TemplateBuilder v-else layout="page" :template="loadedTemplate" />
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import axios from 'axios';
import TemplateBuilder from '@/components/TemplateBuilder.vue';

const route = useRoute();

const loading = ref(true);
const loadError = ref('');
const loadedTemplate = ref(null);

async function fetchTemplate(id) {
    loading.value = true;
    loadError.value = '';
    try {
        const { data } = await axios.get(`/api/email-templates/${id}`);
        loadedTemplate.value = data;
    } catch (e) {
        loadError.value = e.response?.data?.message || e.message || 'Could not load template';
        loadedTemplate.value = null;
    } finally {
        loading.value = false;
    }
}

watch(
    () => [route.name, route.params.id],
    () => {
        if (route.name === 'email-template-new') {
            loadedTemplate.value = null;
            loadError.value = '';
            loading.value = false;
            return;
        }
        if (route.name === 'email-template-edit' && route.params.id) {
            fetchTemplate(route.params.id);
        }
    },
    { immediate: true }
);
</script>
