<template>
    <div
        class="login-page-bg min-h-screen flex items-center justify-center p-4 sm:p-6"
    >
        <div
            class="w-full max-w-md relative z-10 rounded-2xl bg-white shadow-2xl shadow-black/20 border border-white/20"
        >
            <div class="p-8 sm:p-10">
                <div class="text-center mb-8">
                    <!--
                        One brand mark. The company name is the fallback, so a
                        missing logo file no longer shows a broken image - and
                        the name is not hardcoded to a single company.
                    -->
                    <div class="mb-3 flex justify-center">
                        <BrandLogo
                            :src="logoUrl || faviconUrl"
                            :company-name="companyName"
                            img-class="max-h-16 sm:max-h-20 object-contain"
                            text-class="text-lg sm:text-xl font-bold text-slate-900 tracking-tight text-balance"
                        />
                    </div>
                    <p class="text-sm sm:text-base text-slate-800 font-medium leading-snug">
                        Smart Solutions for Smart Businesses
                    </p>
                </div>

                <form @submit.prevent="handleLogin" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-800 mb-1.5" for="loginview-email-address">Email Address</label>
                        <input id="loginview-email-address"
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            class="login-input w-full px-4 py-2.5 border border-slate-200 rounded-xl bg-slate-50 text-slate-900 placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/30 focus-visible:border-primary-600"
                            placeholder="Enter your email address"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-800 mb-1.5" for="loginview-password">Password</label>
                        <div class="relative">
                            <input
                                id="loginview-password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                class="login-input w-full px-4 py-2.5 pr-11 border border-slate-200 rounded-xl bg-slate-50 text-slate-900 placeholder:text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-600/30 focus-visible:border-primary-600"
                                placeholder="Enter your password"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 rounded p-0.5"
                                tabindex="-1"
                                aria-label="Toggle password visibility"
                            >
                                <EyeSlashIcon v-if="showPassword" class="icon" aria-hidden="true" />
                                <EyeIcon v-else class="icon" aria-hidden="true" />
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-0.5">
                        <div class="flex items-center min-w-0">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                id="remember"
                                class="form-checkbox w-4 h-4"
                            />
                            <label for="remember" class="ml-2 text-sm text-slate-700">Remember me</label>
                        </div>
                        <button
                            type="button"
                            @click="showForgotPasswordModal = true"
                            class="link text-sm shrink-0"
                        >
                            Forgot password?
                        </button>
                    </div>

                    <div v-if="error" class="callout callout-danger" role="alert">
                        {{ error }}
                    </div>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="login-btn-primary w-full py-3 rounded-xl text-white font-semibold text-base shadow-lg shadow-primary-600/30 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ loading ? 'Signing in...' : 'Sign In to Dashboard' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Forgot Password -->
        <BaseModal
            v-model="showForgotPasswordModal"
            title="Forgot Password"
            description="Enter your email address and we'll send you a link to reset your password."
            size="sm"
            :close-on-backdrop="false"
            @close="resetForgotPassword"
        >
            <form id="forgot-password-form" class="space-y-4" novalidate @submit.prevent="handleForgotPassword">
                <div>
                    <label class="form-label" for="loginview-email-address-2">Email Address</label>
                    <input
                        id="loginview-email-address-2"
                        v-model="forgotPasswordEmail"
                        type="email"
                        required
                        class="form-input"
                        placeholder="Enter your email address"
                    />
                </div>
                <div
                    v-if="forgotPasswordMessage"
                    :class="['callout', forgotPasswordSuccess ? 'callout-success' : 'callout-danger']"
                    :role="forgotPasswordSuccess ? 'status' : 'alert'"
                >
                    {{ forgotPasswordMessage }}
                </div>
            </form>

            <template #actions>
                <BaseButton variant="outline" block-mobile @click="cancelForgotPassword">Cancel</BaseButton>
                <BaseButton
                    variant="primary"
                    type="submit"
                    form="forgot-password-form"
                    block-mobile
                    :loading="forgotPasswordLoading"
                >
                    Send Reset Link
                </BaseButton>
            </template>
        </BaseModal>
    </div>
</template>

<script setup>
import BrandLogo from '@/components/BrandLogo.vue';
import { ref, onMounted } from 'vue';
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline';
import { BaseButton, BaseModal } from '@/components/base';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';

const auth = useAuthStore();

const form = ref({
    email: '',
    password: '',
    remember: false,
});

/** Runs on Escape and the close button, mirroring what Cancel always did. */
function resetForgotPassword() {
    forgotPasswordEmail.value = '';
    forgotPasswordMessage.value = '';
    forgotPasswordSuccess.value = false;
}

function cancelForgotPassword() {
    showForgotPasswordModal.value = false;
    resetForgotPassword();
}

const loading = ref(false);
const error = ref(null);
const logoUrl = ref('');
const companyName = ref('');
const faviconUrl = ref('');
const showPassword = ref(false);
const showForgotPasswordModal = ref(false);
const forgotPasswordEmail = ref('');
const forgotPasswordLoading = ref(false);
const forgotPasswordMessage = ref('');
const forgotPasswordSuccess = ref(false);

const loadPublicSettings = async () => {
    try {
        const response = await axios.get('/api/settings/public');
        logoUrl.value = response.data.logo_url || '';
        companyName.value = response.data.company_name || '';
        faviconUrl.value = response.data.favicon_url || '';
    } catch (err) {
        console.error('Failed to load public settings:', err);
    }
};

const handleLogin = async () => {
    loading.value = true;
    error.value = null;

    try {
        await auth.login(form.value);
    } catch (err) {
        error.value = err.response?.data?.message || 'Invalid credentials';
    } finally {
        loading.value = false;
    }
};

const handleForgotPassword = async () => {
    forgotPasswordLoading.value = true;
    forgotPasswordMessage.value = '';
    forgotPasswordSuccess.value = false;

    try {
        const response = await axios.post('/api/auth/forgot-password', {
            email: forgotPasswordEmail.value,
        });
        forgotPasswordMessage.value = response.data.message || 'Password reset link sent to your email';
        forgotPasswordSuccess.value = true;

        // Close modal after 3 seconds on success
        setTimeout(() => {
            showForgotPasswordModal.value = false;
            forgotPasswordEmail.value = '';
            forgotPasswordMessage.value = '';
        }, 3000);
    } catch (err) {
        forgotPasswordMessage.value = err.response?.data?.message || 'Failed to send reset link. Please try again.';
        forgotPasswordSuccess.value = false;
    } finally {
        forgotPasswordLoading.value = false;
    }
};

onMounted(() => {
    loadPublicSettings();
});
</script>

<style scoped>
/*
 * One primary hue. This page used to gradient blue -> teal and paint its CTA
 * violet, which put three brand colours on the first screen a user ever sees.
 * Both now run off the primary ramp, matching the sidebar.
 */
.login-page-bg {
    background: linear-gradient(to right, var(--color-primary-600), var(--color-primary-800));
}

.login-btn-primary {
    background-color: var(--color-primary-600);
}

.login-btn-primary:hover:not(:disabled) {
    background-color: var(--color-primary-700);
}

.login-btn-primary:active:not(:disabled) {
    background-color: var(--color-primary-800);
}
</style>
