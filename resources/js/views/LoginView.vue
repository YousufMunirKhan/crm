<template>
    <div
        class="login-page-bg min-h-screen flex items-center justify-center p-4 sm:p-6"
    >
        <div
            class="w-full max-w-md relative z-10 rounded-2xl bg-white shadow-2xl shadow-black/20 border border-white/20"
        >
            <div class="p-8 sm:p-10">
                <div class="text-center mb-8">
                    <div v-if="logoUrl" class="mb-3 flex justify-center">
                        <img :src="logoUrl" alt="Company Logo" class="max-h-16 sm:max-h-20 object-contain">
                    </div>
                    <div v-else-if="faviconUrl" class="mb-3 flex justify-center">
                        <img
                            :src="faviconUrl"
                            alt=""
                            class="w-16 h-16 sm:w-20 sm:h-20 rounded-2xl object-cover shadow-md border border-slate-200/80"
                        >
                    </div>
                    <p
                        v-else
                        class="text-lg sm:text-xl font-bold tracking-tight text-slate-900 mb-3"
                    >
                        SWITCH & SAVE
                    </p>
                    <p class="text-sm sm:text-base text-slate-800 font-medium leading-snug">
                        Smart Solutions for Smart Businesses
                    </p>
                </div>

                <form @submit.prevent="handleLogin" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-800 mb-1.5">Email Address</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            autocomplete="email"
                            class="login-input w-full px-4 py-2.5 border border-slate-200 rounded-xl bg-slate-50 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#7C3AED]/30 focus:border-[#7C3AED]"
                            placeholder="Enter your email address"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-800 mb-1.5">Password</label>
                        <div class="relative">
                            <input
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                autocomplete="current-password"
                                class="login-input w-full px-4 py-2.5 pr-11 border border-slate-200 rounded-xl bg-slate-50 text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-[#7C3AED]/30 focus:border-[#7C3AED]"
                                placeholder="Enter your password"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-700 focus:outline-none p-0.5"
                                tabindex="-1"
                                aria-label="Toggle password visibility"
                            >
                                <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                </svg>
                                <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-3 pt-0.5">
                        <div class="flex items-center min-w-0">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                id="remember"
                                class="w-4 h-4 rounded border-slate-300 text-[#7C3AED] focus:ring-[#7C3AED]"
                            />
                            <label for="remember" class="ml-2 text-sm text-slate-700">Remember me</label>
                        </div>
                        <button
                            type="button"
                            @click="showForgotPasswordModal = true"
                            class="text-sm font-medium text-[#2563EB] hover:text-[#1d4ed8] focus:outline-none shrink-0"
                        >
                            Forgot password?
                        </button>
                    </div>

                    <div v-if="error" class="text-sm text-red-700 bg-red-50 border border-red-100 p-3 rounded-xl">
                        {{ error }}
                    </div>

                    <button
                        type="submit"
                        :disabled="loading"
                        class="login-btn-primary w-full py-3 rounded-xl text-white font-semibold text-base shadow-lg shadow-violet-600/30 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ loading ? 'Signing in...' : 'Sign In to Dashboard' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Forgot Password Modal -->
        <div
            v-if="showForgotPasswordModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="showForgotPasswordModal = false"
        >
            <div class="bg-white rounded-2xl shadow-2xl p-6 sm:p-8 w-full max-w-md border border-slate-100">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-teal-600">
                        Forgot Password
                    </h2>
                    <button
                        @click="showForgotPasswordModal = false; forgotPasswordEmail = ''; forgotPasswordMessage = ''; forgotPasswordSuccess = false;"
                        class="text-slate-400 hover:text-slate-600 focus:outline-none text-lg leading-none p-1"
                    >
                        ✕
                    </button>
                </div>
                <p class="text-sm text-slate-600 mb-4">
                    Enter your email address and we'll send you a link to reset your password.
                </p>
                <form @submit.prevent="handleForgotPassword" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-800 mb-1.5">Email Address</label>
                        <input
                            v-model="forgotPasswordEmail"
                            type="email"
                            required
                            class="w-full px-4 py-2.5 border border-slate-200 rounded-xl bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[#7C3AED]/30 focus:border-[#7C3AED]"
                            placeholder="Enter your email address"
                        />
                    </div>
                    <div v-if="forgotPasswordMessage" class="text-sm p-3 rounded-xl" :class="forgotPasswordSuccess ? 'text-green-800 bg-green-50 border border-green-100' : 'text-red-700 bg-red-50 border border-red-100'">
                        {{ forgotPasswordMessage }}
                    </div>
                    <div class="flex gap-3">
                        <button
                            type="button"
                            @click="showForgotPasswordModal = false; forgotPasswordEmail = ''; forgotPasswordMessage = ''; forgotPasswordSuccess = false;"
                            class="flex-1 px-4 py-2.5 border border-slate-200 text-slate-700 rounded-xl hover:bg-slate-50 transition font-medium"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="forgotPasswordLoading"
                            class="login-btn-primary flex-1 px-4 py-2.5 text-white rounded-xl font-semibold shadow-lg shadow-violet-600/25 transition disabled:opacity-50"
                        >
                            {{ forgotPasswordLoading ? 'Sending...' : 'Send Reset Link' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import axios from 'axios';

const auth = useAuthStore();

const form = ref({
    email: '',
    password: '',
    remember: false,
});

const loading = ref(false);
const error = ref(null);
const logoUrl = ref('');
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
/* Full-page horizontal gradient: blue → teal (matches app sidebar palette) */
.login-page-bg {
    background: linear-gradient(to right, #2563eb, #0d9488);
}

/* Primary actions: violet (dashboard CTA style) */
.login-btn-primary {
    background-color: #7c3aed;
}

.login-btn-primary:hover:not(:disabled) {
    background-color: #6d28d9;
}

.login-btn-primary:active:not(:disabled) {
    background-color: #5b21b6;
}
</style>
