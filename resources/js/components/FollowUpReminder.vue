<template>
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-slate-900">Follow-up Reminders</h3>
            <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium">
                {{ todayFollowUps.length }} Today
            </span>
        </div>

        <div v-if="loading" class="text-center py-8 text-slate-500">
            Loading...
        </div>

        <div v-else-if="todayFollowUps.length === 0" class="text-center py-8 text-slate-500">
            No follow-ups scheduled for today
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="followUp in todayFollowUps"
                :key="followUp.id"
                class="border border-slate-200 rounded-lg p-4 hover:border-blue-300 transition-colors"
            >
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <div class="font-medium text-slate-900">{{ followUp.customer?.name }}</div>
                        <div class="text-sm text-slate-600 mt-1">
                            {{ followUp.product?.name || (followUp.items && followUp.items.length > 0 ? followUp.items.map(i => i.product?.name).join(', ') : '-') }}
                        </div>
                        <div class="text-xs text-slate-500 mt-1">
                            {{ formatDateTime(followUp.next_follow_up_at) }}
                        </div>
                    </div>
                    <router-link
                        :to="`/customers/${followUp.customer_id}`"
                        class="px-3 py-1 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 ml-3"
                    >
                        View
                    </router-link>
                </div>

                <div v-if="!followUp.completed" class="mt-3 pt-3 border-t border-slate-200 flex gap-2">
                    <button
                        @click="openActivityModal(followUp)"
                        class="flex-1 px-4 py-2 border border-purple-500 text-purple-600 rounded-lg hover:bg-purple-50 transition-colors"
                    >
                        Log Activity
                    </button>
                    <button
                        @click="showCompleteModal(followUp)"
                        class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                    >
                        Mark as Done
                    </button>
                </div>
            </div>
        </div>

        <!-- Complete Follow-up Modal -->
        <div
            v-if="showModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
            @click.self="closeModal"
        >
            <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-slate-200">
                    <h3 class="text-xl font-semibold text-slate-900">Complete Follow-up</h3>
                </div>

                <form @submit.prevent="completeFollowUp" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Remarks / Notes *
                        </label>
                        <textarea
                            v-model="form.remarks"
                            rows="4"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter your remarks about the follow-up..."
                        ></textarea>
                    </div>

                    <div>
                        <label class="flex items-center space-x-2">
                            <input
                                v-model="form.saleHappened"
                                type="checkbox"
                                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            <span class="text-sm text-slate-700">Sale happened</span>
                        </label>
                    </div>

                    <div v-if="form.saleHappened">
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            New Stage
                        </label>
                        <select
                            v-model="form.newStage"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="lead">Lead</option>
                            <option value="hot_lead">Hot Lead</option>
                            <option value="quotation">Quotation</option>
                            <option value="won">Won</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Next Follow-up Date (Optional)
                        </label>
                        <input
                            v-model="form.nextFollowUpAt"
                            type="datetime-local"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div class="flex justify-end space-x-3 pt-4">
                        <button
                            type="button"
                            @click="closeModal"
                            :disabled="completingFollowUp"
                            class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="completingFollowUp"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                        >
                            {{ completingFollowUp ? 'Saving...' : 'Complete Follow-up' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Log Activity Modal -->
        <LogActivityModal
            v-if="showActivityModal && activityLead"
            :lead="activityLead"
            @close="closeActivityModal"
            @saved="handleActivitySaved"
        />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
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

