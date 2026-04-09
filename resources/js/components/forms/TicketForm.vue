<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-2xl w-full mx-4 my-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-slate-900">
                    {{ ticket ? 'Edit Ticket' : 'Create New Ticket' }}
                </h3>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Customer</label>
                    <select
                        v-model="form.customer_id"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    >
                        <option value="">Select Customer (Optional)</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }} - {{ customer.phone }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Subject *</label>
                    <input
                        v-model="form.subject"
                        type="text"
                        required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea
                        ref="descriptionTextareaRef"
                        v-model="form.description"
                        rows="8"
                        class="w-full min-h-[13rem] max-h-[min(70vh,36rem)] px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 resize-y overflow-x-hidden"
                    />
                    <p class="text-xs text-slate-500 mt-1">Drag the corner to resize. Expands as you type or paste.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Priority</label>
                        <select
                            v-model="form.priority"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
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

                    <div v-if="ticket" class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select
                            v-model="form.status"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="on_hold">On Hold</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
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
                        {{ loading ? 'Saving...' : (ticket ? 'Update' : 'Create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useAutosizeTextarea } from '@/composables/useAutosizeTextarea';
import axios from 'axios';

const props = defineProps({
    ticket: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const form = reactive({
    customer_id: '',
    subject: '',
    description: '',
    priority: 'medium',
    assigned_to: '',
    status: 'open',
});

const customers = ref([]);
const users = ref([]);
const loading = ref(false);
const error = ref(null);

const { textareaRef: descriptionTextareaRef } = useAutosizeTextarea(() => form.description);

onMounted(async () => {
    await Promise.all([loadCustomers(), loadUsers()]);

    if (props.ticket) {
        Object.assign(form, {
            customer_id: props.ticket.customer_id || '',
            subject: props.ticket.subject || '',
            description: props.ticket.description || '',
            priority: props.ticket.priority || 'medium',
            assigned_to: props.ticket.assigned_to || '',
            status: props.ticket.status || 'open',
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
        if (!data.customer_id) delete data.customer_id;

        if (props.ticket) {
            await axios.put(`/api/tickets/${props.ticket.id}`, data);
        } else {
            await axios.post('/api/tickets', data);
        }

        emit('saved');
        emit('close');
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to save ticket';
    } finally {
        loading.value = false;
    }
};
</script>

