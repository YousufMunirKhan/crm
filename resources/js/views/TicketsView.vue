<template>
    <ListingPageShell
        title="Tickets"
        subtitle="Track support requests, assignments, and resolution — filter by status to focus the queue."
        :badge="ticketsBadge"
    >
        <template #actions>
            <router-link
                to="/tickets/create"
                class="listing-btn-accent w-full sm:w-auto touch-manipulation text-center"
            >
                + Create Ticket
            </router-link>
        </template>

        <template #filters>
            <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:items-end">
                <div class="w-full sm:w-56">
                    <label class="listing-label">Status</label>
                    <select v-model="statusFilter" class="listing-input" @change="onStatusFilterChange">
                        <option value="">All status</option>
                        <option value="open">Open</option>
                        <option value="in_progress">Working</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="w-full sm:w-44">
                    <label class="listing-label">From</label>
                    <input v-model="dateFrom" type="date" class="listing-input" />
                </div>
                <div class="w-full sm:w-44">
                    <label class="listing-label">To</label>
                    <input v-model="dateTo" type="date" class="listing-input" />
                </div>
                <button type="button" class="listing-btn-primary" @click="loadTickets">
                    Filter
                </button>
            </div>
        </template>

        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[1040px]">
                <thead class="listing-thead">
                    <tr>
                        <th class="listing-th">Ticket #</th>
                        <th class="listing-th">Subject</th>
                        <th class="listing-th">Customer</th>
                        <th v-if="isStaffAdmin" class="listing-th">Created</th>
                        <th class="listing-th">Priority</th>
                        <th class="listing-th">Status</th>
                        <th v-if="isStaffAdmin" class="listing-th">Resolved</th>
                        <th v-if="isStaffAdmin" class="listing-th text-center">Comments</th>
                        <th v-if="isStaffAdmin" class="listing-th text-center">Files</th>
                        <th class="listing-th">Assigned</th>
                        <th class="listing-th">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="ticket in tickets" :key="ticket.id" class="listing-row">
                        <td class="listing-td-strong">
                            <router-link :to="`/tickets/${ticket.id}`" class="listing-link-edit">
                                {{ ticket.ticket_number }}
                            </router-link>
                        </td>
                        <td class="listing-td">
                            <router-link :to="`/tickets/${ticket.id}`" class="text-slate-800 hover:text-blue-600 font-medium">
                                {{ ticket.subject }}
                            </router-link>
                        </td>
                        <td class="listing-td">{{ ticket.customer?.name || '—' }}</td>
                        <td v-if="isStaffAdmin" class="listing-td whitespace-nowrap text-slate-600">{{ formatDateTime(ticket.created_at) }}</td>
                        <td class="listing-td">
                            <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium" :class="getPriorityClass(ticket.priority)">
                                {{ ticket.priority }}
                            </span>
                        </td>
                        <td class="listing-td">
                            <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium" :class="getStatusClass(ticket.status)">
                                {{ getStatusLabel(ticket.status) }}
                            </span>
                        </td>
                        <td v-if="isStaffAdmin" class="listing-td whitespace-nowrap text-slate-600">
                            {{ ticket.resolved_at ? formatDateTime(ticket.resolved_at) : '—' }}
                        </td>
                        <td v-if="isStaffAdmin" class="listing-td text-center text-slate-600">{{ ticket.messages_count ?? '—' }}</td>
                        <td v-if="isStaffAdmin" class="listing-td text-center text-slate-600">{{ ticket.attachments_count ?? '—' }}</td>
                        <td class="listing-td">{{ formatAssignees(ticket) }}</td>
                        <td class="listing-td">
                            <div class="flex flex-wrap gap-x-3 gap-y-1">
                                <router-link :to="`/tickets/${ticket.id}`" class="listing-link-edit">View</router-link>
                                <router-link :to="`/tickets/${ticket.id}/edit`" class="listing-link-edit">Edit</router-link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="md:hidden space-y-3 px-3 pb-3">
            <div
                v-for="ticket in tickets"
                :key="`mobile-${ticket.id}`"
                class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-2"
            >
                <div class="flex items-start justify-between gap-2">
                    <router-link :to="`/tickets/${ticket.id}`" class="text-sm font-semibold text-blue-600 hover:text-blue-800">
                        #{{ ticket.ticket_number }}
                    </router-link>
                    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium" :class="getStatusClass(ticket.status)">
                        {{ getStatusLabel(ticket.status) }}
                    </span>
                </div>
                <div class="text-sm font-medium text-slate-900">
                    {{ ticket.subject }}
                </div>
                <div class="text-sm text-slate-600">
                    Customer: {{ ticket.customer?.name || '—' }}
                </div>
                <div class="text-sm text-slate-600">
                    Priority:
                    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium ml-1" :class="getPriorityClass(ticket.priority)">
                        {{ ticket.priority }}
                    </span>
                </div>
                <div class="text-sm text-slate-600">
                    Assigned: {{ formatAssignees(ticket) }}
                </div>
                <div class="flex flex-wrap gap-3 pt-1">
                    <router-link :to="`/tickets/${ticket.id}`" class="listing-link-edit">View</router-link>
                    <router-link :to="`/tickets/${ticket.id}/edit`" class="listing-link-edit">Edit</router-link>
                </div>
            </div>
        </div>

        <template #pagination>
            <Pagination
                :pagination="pagination"
                embedded
                result-label="tickets"
                singular-label="ticket"
                @page-change="goToPage"
            />
        </template>
    </ListingPageShell>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';
import { formatTicketStatus } from '@/utils/displayFormat';

const toast = useToastStore();
const auth = useAuthStore();
const route = useRoute();

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
const dateFrom = ref('');
const dateTo = ref('');
/** Set from dashboard deep link (`assigned_to`); only sent when present. */
const assignedToFilter = ref('');
const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
});

const ticketsBadge = computed(() =>
    pagination.value?.total != null ? `${pagination.value.total} Total` : null,
);

const onStatusFilterChange = () => {
    pagination.value.current_page = 1;
    loadTickets();
};

const TICKET_STATUS_QUERY = ['open', 'in_progress', 'on_hold', 'resolved', 'closed'];

function syncFiltersFromRoute() {
    const q = route.query;
    const st = q.status;
    if (st != null && String(st) !== '') {
        const s = String(st);
        statusFilter.value = TICKET_STATUS_QUERY.includes(s) ? s : '';
    } else {
        statusFilter.value = '';
    }
    dateFrom.value = q.from != null && q.from !== '' ? String(q.from) : '';
    dateTo.value = q.to != null && q.to !== '' ? String(q.to) : '';
    assignedToFilter.value = q.assigned_to != null && q.assigned_to !== '' ? String(q.assigned_to) : '';
}

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

const formatAssignees = (ticket) => {
    const list = ticket.assignees;
    if (Array.isArray(list) && list.length) {
        return list.map((a) => a.name).filter(Boolean).join(', ');
    }
    return ticket.assignee?.name || '—';
};

const getStatusLabel = (status) => formatTicketStatus(status);

const loadTickets = async () => {
    try {
        const params = {
            per_page: 15,
            page: pagination.value.current_page,
        };
        if (statusFilter.value) {
            params.status = statusFilter.value;
        }
        if (dateFrom.value) params.from = dateFrom.value;
        if (dateTo.value) params.to = dateTo.value;
        if (assignedToFilter.value) params.assigned_to = assignedToFilter.value;

        const { data } = await axios.get('/api/tickets', { params });
        tickets.value = data.data || data;
        pagination.value = {
            current_page: data.current_page || 1,
            last_page: data.last_page || 1,
            per_page: data.per_page || 15,
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

watch(
    () => route.query,
    () => {
        syncFiltersFromRoute();
        pagination.value.current_page = 1;
        loadTickets();
    },
    { deep: true },
);

onMounted(async () => {
    if (!auth.initialized) await auth.bootstrap();
    syncFiltersFromRoute();
    loadTickets();
});
</script>
