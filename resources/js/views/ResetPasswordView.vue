<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-100 p-4">
        <div class="w-full max-w-md bg-white rounded-card shadow-lg p-6 sm:p-8">
            <div v-if="done" class="text-center py-4">
                <div class="w-16 h-16 bg-success-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <CheckIcon class="w-8 h-8 text-success-700" aria-hidden="true" />
                </div>
                <h1 class="text-xl font-bold text-slate-900">Password updated</h1>
                <p class="mt-2 text-sm text-slate-600">You can now sign in with your new password.</p>
                <BaseButton variant="primary" to="/login" class="w-full mt-6">Go to sign in</BaseButton>
            </div>

            <div v-else-if="!hasToken" class="text-center py-4">
                <h1 class="text-lg font-bold text-slate-900">This link isn't valid</h1>
                <p class="mt-2 text-sm text-slate-600">
                    The reset link is incomplete or has expired. Request a new one from the sign-in page.
                </p>
                <router-link to="/login" class="btn btn-md btn-primary btn-block-mobile w-full mt-6">Back to sign in</router-link>
            </div>

            <form v-else @submit.prevent="submit">
                <h1 class="text-xl font-bold text-slate-900">Choose a new password</h1>
                <p class="mt-1 text-sm text-slate-500">Resetting the password for {{ email }}.</p>

                <div class="mt-6 space-y-4">
                    <div>
                        <label for="reset-password" class="form-label">New password</label>
                        <input
                            id="reset-password"
                            v-model="password"
                            type="password"
                            class="form-input"
                            autocomplete="new-password"
                            required
                        />
                    </div>
                    <div>
                        <label for="reset-password-confirm" class="form-label">Confirm new password</label>
                        <input
                            id="reset-password-confirm"
                            v-model="passwordConfirmation"
                            type="password"
                            class="form-input"
                            autocomplete="new-password"
                            required
                        />
                    </div>
                </div>

                <p v-if="error" class="mt-4 text-sm text-danger-700 bg-danger-50 border border-danger-100 rounded-control p-3">
                    {{ error }}
                </p>

                <button type="submit" class="btn btn-lg btn-primary btn-block-mobile w-full mt-6" :disabled="loading">
                    {{ loading ? 'Updating…' : 'Update password' }}
                </button>

                <router-link to="/login" class="block text-center text-sm text-primary-600 hover:text-primary-800 mt-4">
                    Back to sign in
                </router-link>
            </form>
        </div>
    </div>
</template>

<script setup>
import { CheckIcon } from '@heroicons/vue/24/outline';
import { BaseButton } from '@/components/base';
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();

const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);
const done = ref(false);
const error = ref('');

const token = computed(() => route.query.token || '');
const email = computed(() => route.query.email || '');
const hasToken = computed(() => !!token.value && !!email.value);

async function submit() {
    error.value = '';

    if (password.value !== passwordConfirmation.value) {
        error.value = 'Those passwords do not match.';
        return;
    }

    loading.value = true;
    try {
        await axios.post('/api/auth/reset-password', {
            token: token.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        });
        done.value = true;
    } catch (e) {
        const data = e.response?.data;
        error.value =
            data?.errors?.password?.[0] ||
            data?.message ||
            'We could not reset your password. The link may have expired.';
    } finally {
        loading.value = false;
    }
}
</script>
