<template>
    <BaseModal
        :model-value="true"
        title="Schedule Follow-up"
        size="sm"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <form id="schedule-follow-up-form" class="space-y-4" @submit.prevent="handleSubmit">
            <p v-if="lead" class="text-sm text-slate-600">{{ lead.customer?.name }} — Lead #{{ lead.id }}</p>

            <div>
                <label class="form-label" for="schedulefollowupmodal-date-time">
                    Date &amp; time <span class="form-required" aria-hidden="true">*</span>
                </label>
                <input
                    id="schedulefollowupmodal-date-time"
                    v-model="form.next_follow_up_at"
                    type="datetime-local"
                    required
                    class="form-input"
                />
            </div>

            <div>
                <label class="form-label" for="schedulefollowupmodal-note-optional">Note (optional)</label>
                <textarea
                    id="schedulefollowupmodal-note-optional"
                    v-model="form.comment"
                    rows="2"
                    class="form-textarea"
                    placeholder="e.g. Call back about quote"
                />
            </div>

            <p v-if="error" class="callout callout-danger" role="alert">{{ error }}</p>
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="$emit('close')">Cancel</BaseButton>
            <BaseButton
                variant="primary"
                type="submit"
                form="schedule-follow-up-form"
                block-mobile
                :loading="loading"
            >
                Schedule
            </BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import { BaseButton, BaseModal } from '@/components/base';

const props = defineProps({ lead: { type: Object, default: null } });
const emit = defineEmits(['saved', 'close']);
const toast = useToastStore();

const form = ref({ next_follow_up_at: '', comment: '' });
const loading = ref(false);
const error = ref('');

function setDefaultTime() {
    const d = new Date();
    d.setHours(d.getHours() + 1, 0, 0, 0);
    form.value.next_follow_up_at = d.toISOString().slice(0, 16);
}

watch(() => props.lead, () => {
    error.value = '';
    setDefaultTime();
    form.value.comment = '';
}, { immediate: true });

async function handleSubmit() {
    if (!props.lead?.id) return;
    loading.value = true;
    error.value = '';
    try {
        await axios.post(`/api/leads/${props.lead.id}/followup`, {
            next_follow_up_at: form.value.next_follow_up_at,
            comment: form.value.comment || undefined,
        });
        toast.success('Follow-up scheduled.');
        emit('saved');
        emit('close');
    } catch (err) {
        const msg = err.response?.data?.message || err.response?.data?.errors?.next_follow_up_at?.[0] || 'Failed to schedule follow-up.';
        error.value = msg;
        toast.error(msg);
    } finally {
        loading.value = false;
    }
}
</script>
