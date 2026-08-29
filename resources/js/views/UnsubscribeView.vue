<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-100 p-4">
        <div class="w-full max-w-md bg-white rounded-card shadow-lg p-6 sm:p-8">
            <div class="text-center">
                <!-- Checking the link -->
                <div v-if="loading" class="py-8">
                    <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-primary-600 mx-auto"></div>
                    <p class="mt-4 text-slate-600">Checking your link…</p>
                </div>

                <!-- Already done -->
                <div v-else-if="success" class="py-4">
                    <div class="w-16 h-16 bg-success-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <CheckIcon class="w-8 h-8 text-success-700" aria-hidden="true" />
                    </div>
                    <h1 class="text-xl font-bold text-slate-900">You're unsubscribed</h1>
                    <p class="mt-2 text-slate-600 text-sm">
                        You will no longer receive marketing {{ channelLabel }} from us.
                    </p>
                </div>

                <!-- Something wrong with the link -->
                <div v-else-if="error" class="py-4">
                    <div class="w-16 h-16 bg-danger-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <ExclamationTriangleIcon class="w-8 h-8 text-danger-700" aria-hidden="true" />
                    </div>
                    <h1 class="text-lg font-bold text-slate-900">We couldn't complete that</h1>
                    <p class="mt-2 text-slate-600 text-sm">{{ error }}</p>
                </div>

                <!--
                    Confirmation step. This page previously unsubscribed on page
                    load, so corporate mail gateways that pre-fetch links were
                    silently opting recipients out without them ever clicking.
                -->
                <div v-else class="py-4">
                    <h1 class="text-xl font-bold text-slate-900">Unsubscribe</h1>
                    <p class="mt-2 text-slate-600 text-sm">
                        Confirm you'd like to stop receiving marketing {{ channelLabel }}
                        <span v-if="identifier" class="font-medium text-slate-800 break-all">at {{ identifier }}</span>.
                    </p>
                    <button
                        type="button"
                        class="btn btn-md btn-primary btn-block-mobile w-full mt-6"
                        :disabled="submitting"
                        @click="confirmUnsubscribe"
                    >
                        {{ submitting ? 'Unsubscribing…' : 'Yes, unsubscribe me' }}
                    </button>
                    <p class="mt-3 text-xs text-slate-500">
                        You can ask us to resubscribe at any time by replying to one of our emails.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { CheckIcon, ExclamationTriangleIcon } from '@heroicons/vue/24/outline';
import { computed, ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const loading = ref(true);
const submitting = ref(false);
const success = ref(false);
const error = ref('');

const channel = computed(() => {
    const c = route.query.channel;
    return ['email', 'sms', 'whatsapp'].includes(c) ? c : 'email';
});

const identifier = computed(() =>
    channel.value === 'email' ? route.query.email : route.query.phone,
);

const channelLabel = computed(
    () => ({ email: 'emails', sms: 'text messages', whatsapp: 'WhatsApp messages' })[channel.value],
);

/** Validates the link only. Nothing is changed until the user clicks. */
async function checkLink() {
    if (!identifier.value) {
        error.value = 'This unsubscribe link is incomplete. Please use the link from your most recent message.';
        loading.value = false;
        return;
    }

    try {
        const { data } = await axios.get('/api/unsubscribe/check', {
            params: {
                channel: channel.value,
                token: route.query.token,
                ...(channel.value === 'email'
                    ? { email: identifier.value }
                    : { phone: identifier.value }),
            },
        });

        if (data.unsubscribed) {
            success.value = true;
        } else if (!data.valid) {
            error.value = 'This unsubscribe link is not valid or has expired. Please use the link from your most recent message.';
        }
    } catch {
        error.value = 'We could not check that link. Please try again shortly.';
    } finally {
        loading.value = false;
    }
}

async function confirmUnsubscribe() {
    submitting.value = true;
    try {
        await axios.post('/api/unsubscribe', {
            channel: channel.value,
            token: route.query.token,
            ...(channel.value === 'email'
                ? { email: identifier.value }
                : { phone: identifier.value }),
        });
        success.value = true;
    } catch (e) {
        error.value = e.response?.data?.message || 'Something went wrong. Please try again.';
    } finally {
        submitting.value = false;
    }
}

onMounted(checkLink);
</script>
