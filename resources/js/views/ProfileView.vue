<template>
    <ListingPageShell
        title="My profile"
        subtitle="Your account details and password. Bank details and documents live on your employee record."
    >
        <div class="form-body max-w-2xl space-y-8">
            <!-- Details -->
            <form class="space-y-4" @submit.prevent="saveProfile">
                <h2 class="form-section-title">Details</h2>

                <BaseInput
                    v-model="form.name"
                    label="Full name"
                    required
                    autocomplete="name"
                    :error="errors.name"
                />

                <BaseInput
                    v-model="form.email"
                    label="Email address"
                    type="email"
                    required
                    autocomplete="email"
                    :error="errors.email"
                    hint="Used to sign in and to receive reports."
                />

                <div>
                    <span class="form-label">Role</span>
                    <p class="text-sm text-slate-600">
                        {{ user?.role?.name || '—' }}
                        <span class="text-slate-500">— set by an administrator</span>
                    </p>
                </div>

                <div class="flex justify-end">
                    <BaseButton variant="primary" size="lg" type="submit" block-mobile :loading="savingProfile">
                        {{ savingProfile ? 'Saving…' : 'Save details' }}
                    </BaseButton>
                </div>
            </form>

            <hr class="divider" />

            <!-- Password -->
            <form class="space-y-4" @submit.prevent="savePassword">
                <h2 class="form-section-title">Change password</h2>

                <BaseInput
                    v-model="passwordForm.current_password"
                    label="Current password"
                    type="password"
                    required
                    autocomplete="current-password"
                    :error="errors.current_password"
                />

                <BaseInput
                    v-model="passwordForm.password"
                    label="New password"
                    type="password"
                    required
                    autocomplete="new-password"
                    :error="errors.password"
                />

                <BaseInput
                    v-model="passwordForm.password_confirmation"
                    label="Confirm new password"
                    type="password"
                    required
                    autocomplete="new-password"
                />

                <div class="flex justify-end">
                    <BaseButton variant="soft" size="lg" type="submit" block-mobile :loading="savingPassword">
                        {{ savingPassword ? 'Updating…' : 'Update password' }}
                    </BaseButton>
                </div>
            </form>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';
import BaseInput from '@/components/base/BaseInput.vue';
import { BaseButton } from '@/components/base';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';

const toast = useToastStore();
const auth = useAuthStore();

const user = ref(null);
const savingProfile = ref(false);
const savingPassword = ref(false);
const errors = reactive({});

const form = reactive({ name: '', email: '' });
const passwordForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: '',
});

function clearErrors() {
    Object.keys(errors).forEach((key) => delete errors[key]);
}

function applyValidationErrors(e) {
    const bag = e.response?.data?.errors;
    if (bag) {
        Object.entries(bag).forEach(([field, messages]) => {
            errors[field] = messages[0];
        });
        return true;
    }
    return false;
}

async function load() {
    try {
        const { data } = await axios.get('/api/profile');
        user.value = data;
        form.name = data.name || '';
        form.email = data.email || '';
    } catch {
        toast.error('Could not load your profile.');
    }
}

async function saveProfile() {
    clearErrors();
    savingProfile.value = true;
    try {
        const { data } = await axios.put('/api/profile', form);
        user.value = data;
        // Keep the header and user menu in step with the new name.
        if (auth.user) auth.user = { ...auth.user, name: data.name, email: data.email };
        toast.success('Profile updated');
    } catch (e) {
        if (!applyValidationErrors(e)) {
            toast.error(e.response?.data?.message || 'Could not save your profile.');
        }
    } finally {
        savingProfile.value = false;
    }
}

async function savePassword() {
    clearErrors();
    savingPassword.value = true;
    try {
        await axios.post('/api/profile/password', passwordForm);
        passwordForm.current_password = '';
        passwordForm.password = '';
        passwordForm.password_confirmation = '';
        toast.success('Password updated');
    } catch (e) {
        if (!applyValidationErrors(e)) {
            toast.error(e.response?.data?.message || 'Could not update your password.');
        }
    } finally {
        savingPassword.value = false;
    }
}

onMounted(load);
</script>
