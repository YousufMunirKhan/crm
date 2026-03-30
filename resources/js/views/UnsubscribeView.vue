<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-100 p-4">
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-6 sm:p-8">
            <div class="text-center">
                <div v-if="loading" class="py-8">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-slate-900 mx-auto"></div>
                    <p class="mt-4 text-slate-600">Processing...</p>
                </div>
                <div v-else-if="success" class="py-4">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h1 class="text-xl font-bold text-slate-900">You're unsubscribed</h1>
                    <p class="mt-2 text-slate-600 text-sm">You will no longer receive marketing emails from us.</p>
                </div>
                <div v-else-if="error" class="py-4">
                    <p class="text-red-600">{{ error }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const loading = ref(true);
const success = ref(false);
const error = ref('');

async function unsubscribe() {
    const email = route.query.email;
    if (!email) {
        error.value = 'Invalid unsubscribe link. No email address found.';
        loading.value = false;
        return;
    }
    try {
        await axios.post('/api/unsubscribe', { email });
        success.value = true;
    } catch (e) {
        error.value = e.response?.data?.message || 'Something went wrong. Please try again.';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    unsubscribe();
});
</script>
