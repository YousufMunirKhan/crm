<template>
    <ListingPageShell
        title="Change password"
        subtitle="Update your login password. You will stay signed in on this device."
    >
        <div class="max-w-md mx-auto w-full space-y-5">
            <form class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 sm:p-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="listing-label" for="cp-current">Current password</label>
                    <input
                        id="cp-current"
                        v-model="form.current_password"
                        type="password"
                        autocomplete="current-password"
                        class="listing-input w-full"
                        required
                    />
                </div>
                <div>
                    <label class="listing-label" for="cp-new">New password</label>
                    <input
                        id="cp-new"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        class="listing-input w-full"
                        required
                        minlength="8"
                    />
                    <p class="text-xs text-slate-500 mt-1">At least 8 characters. Use a strong password.</p>
                </div>
                <div>
                    <label class="listing-label" for="cp-confirm">Confirm new password</label>
                    <input
                        id="cp-confirm"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="listing-input w-full"
                        required
                        minlength="8"
                    />
                </div>
                <p v-if="errorMessage" class="text-sm text-red-700 bg-red-50 border border-red-100 rounded-lg px-3 py-2">{{ errorMessage }}</p>
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
                    <router-link to="/" class="listing-btn-outline w-full sm:w-auto text-center">Cancel</router-link>
                    <button type="submit" class="listing-btn-primary w-full sm:w-auto disabled:opacity-50" :disabled="submitting">
                        {{ submitting ? 'Saving…' : 'Update password' }}
                    </button>
                </div>
            </form>
            <p class="text-xs text-slate-500 text-center px-2">
                Forgot your current password?
                <router-link to="/login" class="text-violet-700 font-medium hover:underline">Sign out</router-link>
                and use “Forgot password?” on the login page.
            </p>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import ListingPageShell from '@/components/ListingPageShell.vue';

const toast = useToastStore();
const submitting = ref(false);
const errorMessage = ref('');
const form = ref({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = async () => {
    errorMessage.value = '';
    if (form.value.password !== form.value.password_confirmation) {
        errorMessage.value = 'New password and confirmation do not match.';
        return;
    }
    submitting.value = true;
    try {
        await axios.post('/api/profile/password', {
            current_password: form.value.current_password,
            password: form.value.password,
            password_confirmation: form.value.password_confirmation,
        });
        toast.success('Password updated successfully.');
        form.value = { current_password: '', password: '', password_confirmation: '' };
    } catch (e) {
        const d = e.response?.data;
        if (d?.errors) {
            errorMessage.value = Object.values(d.errors).flat().join(' ');
        } else {
            errorMessage.value = d?.message || 'Could not update password. Try again.';
        }
    } finally {
        submitting.value = false;
    }
};
</script>
