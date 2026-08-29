<template>
    <ListingPageShell
        title="Change password"
        subtitle="Update your login password. You will stay signed in on this device."
    >
        <div class="max-w-md mx-auto w-full space-y-5">
            <form
                id="change-password-form"
                class="card card-body space-y-4"
                novalidate
                @submit.prevent="submit"
            >
                <div
                    v-if="errorMessage"
                    ref="errorSummary"
                    class="callout callout-danger"
                    role="alert"
                    tabindex="-1"
                >
                    {{ errorMessage }}
                </div>
                <div>
                    <label class="form-label" for="cp-current">Current password</label>
                    <input
                        id="cp-current"
                        v-model="form.current_password"
                        type="password"
                        autocomplete="current-password"
                        class="form-input"
                        required
                    />
                </div>
                <div>
                    <label class="form-label" for="cp-new">New password</label>
                    <input
                        id="cp-new"
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        class="form-input"
                        required
                        minlength="8"
                        aria-describedby="cp-new-hint"
                    />
                    <p id="cp-new-hint" class="form-hint">At least 8 characters. Use a strong password.</p>
                </div>
                <div>
                    <label class="form-label" for="cp-confirm">Confirm new password</label>
                    <input
                        id="cp-confirm"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="form-input"
                        required
                        minlength="8"
                    />
                </div>
                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
                    <BaseButton variant="outline" to="/" block-mobile>Cancel</BaseButton>
                    <BaseButton
                        variant="primary"
                        type="submit"
                        block-mobile
                        :loading="submitting"
                    >
                        {{ submitting ? 'Saving…' : 'Update password' }}
                    </BaseButton>
                </div>
            </form>
            <p class="text-xs text-slate-500 text-center px-2">
                Forgot your current password?
                <router-link to="/login" class="link">Sign out</router-link>
                and use “Forgot password?” on the login page.
            </p>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { nextTick, ref } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseButton } from '@/components/base';

const toast = useToastStore();
const submitting = ref(false);
const errorMessage = ref('');
const errorSummary = ref(null);
const form = ref({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const showError = async (message) => {
    errorMessage.value = message;
    await nextTick();
    errorSummary.value?.focus();
};

const submit = async () => {
    errorMessage.value = '';
    // The form is `novalidate`, so the native required/minlength gates are mirrored here.
    if (!form.value.current_password || !form.value.password || !form.value.password_confirmation) {
        await showError('Please fill in every password field.');
        return;
    }
    if (form.value.password.length < 8 || form.value.password_confirmation.length < 8) {
        await showError('Your new password must be at least 8 characters.');
        return;
    }
    if (form.value.password !== form.value.password_confirmation) {
        await showError('New password and confirmation do not match.');
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
            await showError(Object.values(d.errors).flat().join(' '));
        } else {
            await showError(d?.message || 'Could not update password. Try again.');
        }
    } finally {
        submitting.value = false;
    }
};
</script>
