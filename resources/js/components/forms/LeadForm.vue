<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-2xl w-full mx-4 my-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-slate-900">
                    {{ lead ? 'Edit Lead' : 'Create New Lead' }}
                </h3>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Customer *</label>
                    <select
                        v-model="form.customer_id"
                        required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    >
                        <option value="">Select Customer</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }} - {{ customer.phone }}
                        </option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Stage *</label>
                        <select
                            v-model="form.stage"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="follow_up">Follow Up</option>
                            <option value="lead">Lead</option>
                            <option value="hot_lead">Hot Lead</option>
                            <option value="quotation">Quotation</option>
                            <option value="won">Won</option>
                            <option value="lost">Lost</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Source</label>
                        <select
                            v-model="form.source"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="">Select Source</option>
                            <option value="call_center">Call Center</option>
                            <option value="ground_field">Ground Field</option>
                            <option value="website">Website</option>
                            <option value="meta">Meta</option>
                            <option value="tiktok">TikTok</option>
                            <option value="google_ads">Google Ads</option>
                            <option value="organic_lead">Organic Lead</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Assigned To</label>
                        <select
                            v-model="form.assigned_to"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="">Unassigned</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Pipeline Value (£)</label>
                        <input
                            v-model="form.pipeline_value"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <div v-if="form.stage === 'lost'" class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Lost Reason</label>
                        <input
                            v-model="form.lost_reason"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Next Follow-up</label>
                        <input
                            v-model="form.next_follow_up_at"
                            type="datetime-local"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                </div>

                <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded">
                    {{ error }}
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 text-sm border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50"
                        :disabled="loading"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800"
                        :disabled="loading"
                    >
                        {{ loading ? 'Saving...' : (lead ? 'Update' : 'Create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    lead: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const form = reactive({
    customer_id: '',
    stage: 'follow_up',
    source: '',
    assigned_to: '',
    pipeline_value: 0,
    lost_reason: '',
    next_follow_up_at: '',
});

const customers = ref([]);
const users = ref([]);
const loading = ref(false);
const error = ref(null);

onMounted(async () => {
    await Promise.all([loadCustomers(), loadUsers()]);

    if (props.lead) {
        Object.assign(form, {
            customer_id: props.lead.customer_id || '',
            stage: props.lead.stage || 'follow_up',
            source: props.lead.source || '',
            assigned_to: props.lead.assigned_to || '',
            pipeline_value: props.lead.pipeline_value || 0,
            lost_reason: props.lead.lost_reason || '',
            next_follow_up_at: props.lead.next_follow_up_at ? new Date(props.lead.next_follow_up_at).toISOString().slice(0, 16) : '',
        });
    }
});

const loadCustomers = async () => {
    try {
        const { data } = await axios.get('/api/customers', { params: { per_page: 100 } });
        customers.value = data.data || [];
    } catch (err) {
        console.error('Failed to load customers:', err);
    }
};

const loadUsers = async () => {
    try {
        const { data } = await axios.get('/api/users');
        users.value = data || [];
    } catch (err) {
        console.error('Failed to load users:', err);
    }
};

const handleSubmit = async () => {
    loading.value = true;
    error.value = null;

    try {
        const data = { ...form };
        if (!data.pipeline_value) data.pipeline_value = 0;
        if (!data.next_follow_up_at) delete data.next_follow_up_at;

        if (props.lead) {
            await axios.put(`/api/leads/${props.lead.id}`, data);
        } else {
            await axios.post('/api/leads', data);
        }

        emit('saved');
        emit('close');
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to save lead';
    } finally {
        loading.value = false;
    }
};
</script>

