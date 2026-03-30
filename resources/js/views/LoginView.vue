<template>
    <div class="min-h-screen flex items-center justify-center bg-slate-100 p-4">
        <div class="w-full max-w-md bg-white rounded-xl shadow-lg p-6 sm:p-8">
            <div class="text-center mb-8">
                <!-- Logo from settings -->
                <div v-if="logoUrl" class="mb-6 flex justify-center">
                    <img :src="logoUrl" alt="Company Logo" class="max-h-20 object-contain">
                </div>
            </div>

            <form @submit.prevent="handleLogin" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input
                        v-model="form.email"
                        type="email"
                        required
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        placeholder="admin@switchsave.com"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Password</label>
                    <div class="relative">
                        <input
                            v-model="form.password"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            class="w-full px-4 py-2 pr-10 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            placeholder="Password"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 transform -translate-y-1/2 text-slate-500 hover:text-slate-700 focus:outline-none text-sm"
                            tabindex="-1"
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

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input
                            v-model="form.remember"
                            type="checkbox"
                            id="remember"
                            class="w-4 h-4 text-slate-600 border-slate-300 rounded focus:ring-slate-500"
                        />
                        <label for="remember" class="ml-2 text-sm text-slate-600">Remember me</label>
                    </div>
                    <button
                        type="button"
                        @click="showForgotPasswordModal = true"
                        class="text-sm text-blue-600 hover:text-blue-700 focus:outline-none"
                    >
                        Forgot password?
                    </button>
                </div>

                <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded">
                    {{ error }}
                </div>

                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full py-2.5 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition disabled:opacity-50"
                >
                    {{ loading ? 'Signing in...' : 'Sign In' }}
                </button>
            </form>
        </div>

        <!-- Forgot Password Modal -->
        <div
            v-if="showForgotPasswordModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="showForgotPasswordModal = false"
        >
            <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-slate-900">Forgot Password</h2>
                    <button
                        @click="showForgotPasswordModal = false; forgotPasswordEmail = ''; forgotPasswordMessage = ''; forgotPasswordSuccess = false;"
                        class="text-slate-400 hover:text-slate-600 focus:outline-none"
                    >
                        ✕
                    </button>
                </div>
                <p class="text-sm text-slate-600 mb-4">
                    Enter your email address and we'll send you a link to reset your password.
                </p>
                <form @submit.prevent="handleForgotPassword" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                        <input
                            v-model="forgotPasswordEmail"
                            type="email"
                            required
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            placeholder="Enter your email"
                        />
                    </div>
                    <div v-if="forgotPasswordMessage" class="text-sm p-3 rounded" :class="forgotPasswordSuccess ? 'text-green-700 bg-green-50' : 'text-red-600 bg-red-50'">
                        {{ forgotPasswordMessage }}
                    </div>
                    <div class="flex gap-3">
                        <button
                            type="button"
                            @click="showForgotPasswordModal = false; forgotPasswordEmail = ''; forgotPasswordMessage = ''; forgotPasswordSuccess = false;"
                            class="flex-1 px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="forgotPasswordLoading"
                            class="flex-1 px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition disabled:opacity-50"
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


