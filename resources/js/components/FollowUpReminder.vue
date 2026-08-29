<template>
    <BaseCard title="Follow-up Reminders">
        <template #actions>
            <BaseBadge tone="primary">{{ todayFollowUps.length }} Today</BaseBadge>
        </template>

        <div v-if="loading" class="py-8 text-center text-sm text-slate-500" role="status" aria-live="polite">
            Loading...
        </div>

        <EmptyState
            v-else-if="todayFollowUps.length === 0"
            heading="No follow-ups scheduled for today"
            description="New follow-ups appear here as soon as they are due."
        >
            <template #icon>
                <CalendarDaysIcon class="icon" aria-hidden="true" />
            </template>
        </EmptyState>

        <div v-else class="space-y-3">
            <div
                v-for="followUp in todayFollowUps"
                :key="followUp.id"
                class="rounded-card border border-slate-200 p-4 transition-colors hover:border-primary-300"
            >
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="font-medium text-slate-900">{{ followUp.customer?.name }}</div>
                        <div class="mt-1 text-sm text-slate-600">
                            {{ followUp.product?.name || (followUp.items && followUp.items.length > 0 ? followUp.items.map(i => i.product?.name).join(', ') : '-') }}
                        </div>
                        <div class="mt-1 flex items-center gap-1.5 text-xs text-slate-500">
                            <ClockIcon class="icon-sm" aria-hidden="true" />
                            {{ formatDateTime(followUp.next_follow_up_at) }}
                        </div>
                    </div>
                    <BaseButton variant="primary" :to="`/customers/${followUp.customer_id}`">
                        View
                    </BaseButton>
                </div>

                <div v-if="!followUp.completed" class="mt-3 flex flex-col gap-2 border-t border-slate-200 pt-3 sm:flex-row">
                    <BaseButton variant="outline" class="sm:flex-1" @click="openActivityModal(followUp)">
                        <template #icon>
                            <PencilSquareIcon class="icon" aria-hidden="true" />
                        </template>
                        Log Activity
                    </BaseButton>
                    <BaseButton variant="success" class="sm:flex-1" @click="showCompleteModal(followUp)">
                        <template #icon>
                            <CheckIcon class="icon" aria-hidden="true" />
                        </template>
                        Mark as Done
                    </BaseButton>
                </div>
            </div>
        </div>

        <!-- Complete Follow-up Modal -->
        <BaseModal
            :model-value="showModal"
            title="Complete Follow-up"
            size="md"
            :close-on-backdrop="false"
            @close="closeModal"
        >
            <form id="complete-follow-up-form" class="space-y-4" @submit.prevent="completeFollowUp">
                <BaseInput
                    v-model="form.remarks"
                    label="Remarks / Notes"
                    type="textarea"
                    rows="4"
                    required
                    placeholder="Enter your remarks about the follow-up..."
                />

                <div>
                    <label class="form-choice" for="follow-up-sale-happened">
                        <input
                            id="follow-up-sale-happened"
                            v-model="form.saleHappened"
                            type="checkbox"
                            class="form-checkbox"
                        />
                        <span>Sale happened</span>
                    </label>
                </div>

                <BaseSelect
                    v-if="form.saleHappened"
                    v-model="form.newStage"
                    label="New Stage"
                    :options="stageOptions"
                />

                <BaseInput
                    v-model="form.nextFollowUpAt"
                    label="Next Follow-up Date (Optional)"
                    type="datetime-local"
                />
            </form>

            <template #actions>
                <BaseButton
                    variant="outline"
                    block-mobile
                    :disabled="completingFollowUp"
                    @click="closeModal"
                >
                    Cancel
                </BaseButton>
                <BaseButton
                    variant="primary"
                    type="submit"
                    form="complete-follow-up-form"
                    block-mobile
                    :loading="completingFollowUp"
                >
                    {{ completingFollowUp ? 'Saving...' : 'Complete Follow-up' }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Log Activity Modal -->
        <LogActivityModal
            v-if="showActivityModal && activityLead"
            :lead="activityLead"
            @close="closeActivityModal"
            @saved="handleActivitySaved"
        />
    </BaseCard>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { CalendarDaysIcon, CheckIcon, ClockIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
import { BaseBadge, BaseButton, BaseCard, BaseInput, BaseModal, BaseSelect, EmptyState } from '@/components/base';
import { useToastStore } from '@/stores/toast';
import LogActivityModal from '@/components/LogActivityModal.vue';

const toast = useToastStore();

const todayFollowUps = ref([]);
const loading = ref(false);
const showModal = ref(false);
const completingFollowUp = ref(false);
const selectedFollowUp = ref(null);
const showActivityModal = ref(false);
const activityLead = ref(null);
const form = ref({
    remarks: '',
    saleHappened: false,
    newStage: 'lead',
    nextFollowUpAt: '',
});

const stageOptions = [
    { value: 'lead', label: 'Lead' },
    { value: 'hot_lead', label: 'Hot Lead' },
    { value: 'quotation', label: 'Quotation' },
    { value: 'won', label: 'Won' },
];

const formatDateTime = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const loadFollowUps = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/dashboard/sales-agent');
        todayFollowUps.value = (response.data.today_follow_ups || []).map(fu => ({
            ...fu,
            completed: false,
        }));
    } catch (error) {
        console.error('Failed to load follow-ups:', error);
    } finally {
        loading.value = false;
    }
};

const showCompleteModal = (followUp) => {
    selectedFollowUp.value = followUp;
    form.value = {
        remarks: '',
        saleHappened: false,
        newStage: 'lead',
        nextFollowUpAt: '',
    };
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    selectedFollowUp.value = null;
};

const completeFollowUp = async () => {
    if (!selectedFollowUp.value || completingFollowUp.value) return;
    completingFollowUp.value = true;
    try {
        const payload = {
            remarks: form.value.remarks,
            sale_happened: form.value.saleHappened,
            new_stage: form.value.saleHappened ? form.value.newStage : null,
        };

        if (form.value.nextFollowUpAt) {
            payload.next_follow_up_at = form.value.nextFollowUpAt;
        }

        await axios.post(`/api/leads/${selectedFollowUp.value.id}/complete-followup`, payload);

        // Remove from list
        todayFollowUps.value = todayFollowUps.value.filter(
            fu => fu.id !== selectedFollowUp.value.id
        );

        closeModal();

        // Reload to get updated data
        loadFollowUps();
    } catch (error) {
        console.error('Failed to complete follow-up:', error);
        toast.error('Failed to complete follow-up. Please try again.');
    } finally {
        completingFollowUp.value = false;
    }
};

const openActivityModal = (lead) => {
    activityLead.value = lead;
    showActivityModal.value = true;
};

const closeActivityModal = () => {
    showActivityModal.value = false;
    activityLead.value = null;
};

const handleActivitySaved = () => {
    loadFollowUps();
    closeActivityModal();
};

onMounted(loadFollowUps);
</script>
