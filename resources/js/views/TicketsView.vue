<template>
    <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Tickets</h1>
            <div class="flex flex-wrap gap-2 sm:gap-3">
                <select
                    v-model="statusFilter"
                    @change="loadTickets"
                    class="px-3 sm:px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 text-sm w-full sm:w-auto"
                >
                    <option value="">All Status</option>
                    <option value="open">Open</option>
                    <option value="in_progress">Working</option>
                    <option value="closed">Closed</option>
                </select>
                <button
                    @click="openCreateForm"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 text-sm whitespace-nowrap"
                >
                    + Create Ticket
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden overflow-x-auto">
            <table class="w-full min-w-[1040px]">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Ticket #</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Subject</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Customer</th>
                        <th v-if="isStaffAdmin" class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Created</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Priority</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Status</th>
                        <th v-if="isStaffAdmin" class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Resolved</th>
                        <th v-if="isStaffAdmin" class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Comments</th>
                        <th v-if="isStaffAdmin" class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Files</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Assigned</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr
                        v-for="ticket in tickets"
                        :key="ticket.id"
                        class="hover:bg-slate-50"
                    >
                        <td class="px-6 py-4 text-sm font-medium text-slate-900">
                            <router-link :to="`/tickets/${ticket.id}`" class="text-blue-600 hover:underline">
                                {{ ticket.ticket_number }}
                            </router-link>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-900">
                            <router-link :to="`/tickets/${ticket.id}`" class="hover:text-blue-600 hover:underline">
                                {{ ticket.subject }}
                            </router-link>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">{{ ticket.customer?.name || '-' }}</td>
                        <td v-if="isStaffAdmin" class="px-6 py-4 text-sm text-slate-600 whitespace-nowrap">{{ formatDateTime(ticket.created_at) }}</td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-2 py-1 rounded text-xs" :class="getPriorityClass(ticket.priority)">
                                {{ ticket.priority }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-2 py-1 rounded text-xs" :class="getStatusClass(ticket.status)">
                                {{ getStatusLabel(ticket.status) }}
                            </span>
                        </td>
                        <td v-if="isStaffAdmin" class="px-6 py-4 text-sm text-slate-600 whitespace-nowrap">
                            {{ ticket.resolved_at ? formatDateTime(ticket.resolved_at) : '—' }}
                        </td>
                        <td v-if="isStaffAdmin" class="px-6 py-4 text-sm text-slate-600 text-center">{{ ticket.messages_count ?? '—' }}</td>
                        <td v-if="isStaffAdmin" class="px-6 py-4 text-sm text-slate-600 text-center">{{ ticket.attachments_count ?? '—' }}</td>
                        <td class="px-6 py-4 text-sm text-slate-600">{{ ticket.assignee?.name || '-' }}</td>
                        <td class="px-6 py-4 text-sm">
                            <div class="flex gap-2">
                                <router-link
                                    :to="`/tickets/${ticket.id}`"
                                    class="text-blue-600 hover:underline"
                                >
                                    View
                                </router-link>
                                <button
                                    @click="openEditForm(ticket)"
                                    class="text-green-600 hover:underline"
                                >
                                    Edit
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            v-if="pagination.last_page > 1"
            class="flex items-center justify-between gap-3 pt-4"
        >
            <div class="text-sm text-slate-600">
                Page {{ pagination.current_page }} of {{ pagination.last_page }}
                <span v-if="pagination.total">· {{ pagination.total }} tickets</span>
            </div>
            <div class="flex items-center gap-2">
                <button
                    class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 text-slate-700 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50"
                    :disabled="pagination.current_page <= 1"
                    @click="goToPage(pagination.current_page - 1)"
                    type="button"
                >
                    Previous
                </button>
                <button
                    class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 text-slate-700 disabled:opacity-40 disabled:cursor-not-allowed hover:bg-slate-50"
                    :disabled="pagination.current_page >= pagination.last_page"
                    @click="goToPage(pagination.current_page + 1)"
                    type="button"
                >
                    Next
                </button>
            </div>
        </div>

        <!-- Ticket Form Modal -->
        <TicketForm
            v-if="showForm"
            :ticket="selectedTicket"
            @close="closeForm"
            @saved="handleSaved"
        />

    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import TicketForm from '@/components/TicketForm.vue';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';

const toast = useToastStore();
const auth = useAuthStore();

const isStaffAdmin = computed(() => {
    const n = auth.user?.role?.name;
    return ['Admin', 'Manager', 'System Admin'].includes(n);
});

function formatDateTime(value) {
    if (!value) return '—';
    const d = new Date(value);
    return d.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}
const tickets = ref([]);
const statusFilter = ref('');
const showForm = ref(false);
const selectedTicket = ref(null);
const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
});

const getPriorityClass = (priority) => {
    const classes = {
        urgent: 'bg-red-100 text-red-800',
        high: 'bg-orange-100 text-orange-800',
        medium: 'bg-yellow-100 text-yellow-800',
        low: 'bg-blue-100 text-blue-800',
    };
    return classes[priority] || 'bg-slate-100 text-slate-800';
};

const getStatusClass = (status) => {
    const classes = {
        open: 'bg-yellow-100 text-yellow-800',
        in_progress: 'bg-blue-100 text-blue-800',
        on_hold: 'bg-amber-100 text-amber-800',
        resolved: 'bg-green-100 text-green-800',
        closed: 'bg-slate-100 text-slate-800',
    };
    return classes[status] || 'bg-slate-100 text-slate-800';
};

const getStatusLabel = (status) => {
    const labels = {
        open: 'Open',
        in_progress: 'Working',
        on_hold: 'On Hold',
        resolved: 'Resolved',
        closed: 'Closed',
    };
    return labels[status] || status || '—';
};

const loadTickets = async () => {
    try {
        const params = {
            per_page: 15,
            page: pagination.value.current_page,
        };
        if (statusFilter.value) {
            params.status = statusFilter.value;
        }

        const { data } = await axios.get('/api/tickets', { params });
        tickets.value = data.data || data;
        pagination.value = {
            current_page: data.current_page || 1,
            last_page: data.last_page || 1,
            total: data.total ?? (Array.isArray(data.data) ? data.data.length : 0),
        };
    } catch (error) {
        console.error('Failed to load tickets:', error);
        toast.error('Failed to load tickets');
    }
};

const goToPage = (page) => {
    if (page < 1 || page > pagination.value.last_page) return;
    pagination.value.current_page = page;
    loadTickets();
};

const openCreateForm = () => {
    selectedTicket.value = null;
    showForm.value = true;
};

const openEditForm = (ticket) => {
    selectedTicket.value = ticket;
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    selectedTicket.value = null;
};

const handleSaved = () => {
    loadTickets();
    closeForm();
};

onMounted(async () => {
    if (!auth.initialized) await auth.bootstrap();
    loadTickets();
});
</script>
