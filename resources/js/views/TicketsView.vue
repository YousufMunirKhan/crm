<template>
    <ListingPageShell
        title="Tickets"
        subtitle="Track support requests, assignments, and resolution — filter by status to focus the queue."
        :badge="ticketsBadge"
    >
        <template #actions>
            <BaseButton variant="primary" to="/tickets/create" block-mobile>
                <template #icon><PlusIcon class="icon" aria-hidden="true" /></template>
                Create Ticket
            </BaseButton>
        </template>

        <template #filters>
            <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:items-end">
                <div class="w-full sm:w-56">
                    <label class="form-label" for="ticketsview-status">Status</label>
                    <select id="ticketsview-status" v-model="statusFilter" class="form-select" @change="onStatusFilterChange">
                        <option value="">All status</option>
                        <option value="open">Open</option>
                        <option value="in_progress">Working</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <div class="w-full sm:w-44">
                    <label class="form-label" for="ticketsview-from">From</label>
                    <input id="ticketsview-from" v-model="dateFrom" type="date" class="form-input" />
                </div>
                <div class="w-full sm:w-44">
                    <label class="form-label" for="ticketsview-to">To</label>
                    <input id="ticketsview-to" v-model="dateTo" type="date" class="form-input" />
                </div>
                <BaseButton variant="soft" @click="loadTickets">
                    <template #icon><FunnelIcon class="icon" aria-hidden="true" /></template>
                    Filter
                </BaseButton>
            </div>
        </template>

        <div v-if="tickets.length" class="hidden md:block table-wrap">
            <table class="table min-w-[1040px]">
                <caption class="sr-only">Support tickets matching the current filters</caption>
                <thead class="table-thead">
                    <tr>
                        <th scope="col" class="table-th">Ticket #</th>
                        <th scope="col" class="table-th">Subject</th>
                        <th scope="col" class="table-th">Customer</th>
                        <th v-if="isStaffAdmin" scope="col" class="table-th">Created</th>
                        <th scope="col" class="table-th">Priority</th>
                        <th scope="col" class="table-th">Status</th>
                        <th v-if="isStaffAdmin" scope="col" class="table-th">Resolved</th>
                        <th v-if="isStaffAdmin" scope="col" class="table-th text-center">Comments</th>
                        <th v-if="isStaffAdmin" scope="col" class="table-th text-center">Files</th>
                        <th scope="col" class="table-th">Assigned</th>
                        <th scope="col" class="table-th">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="ticket in tickets" :key="ticket.id" class="table-row">
                        <td class="table-td-strong">
                            <router-link :to="`/tickets/${ticket.id}`" class="link">
                                {{ ticket.ticket_number }}
                            </router-link>
                        </td>
                        <td class="table-td">
                            <router-link :to="`/tickets/${ticket.id}`" class="text-slate-800 hover:text-primary-700 font-medium">
                                {{ ticket.subject }}
                            </router-link>
                        </td>
                        <td class="table-td">{{ ticket.customer?.name || '—' }}</td>
                        <td v-if="isStaffAdmin" class="table-td whitespace-nowrap text-slate-600">{{ formatDateTime(ticket.created_at) }}</td>
                        <td class="table-td">
                            <BaseBadge :tone="getPriorityTone(ticket.priority)">{{ ticket.priority }}</BaseBadge>
                        </td>
                        <td class="table-td">
                            <BaseBadge :tone="getStatusTone(ticket.status)">{{ getStatusLabel(ticket.status) }}</BaseBadge>
                        </td>
                        <td v-if="isStaffAdmin" class="table-td whitespace-nowrap text-slate-600">
                            {{ ticket.resolved_at ? formatDateTime(ticket.resolved_at) : '—' }}
                        </td>
                        <td v-if="isStaffAdmin" class="table-td text-center text-slate-600">{{ ticket.messages_count ?? '—' }}</td>
                        <td v-if="isStaffAdmin" class="table-td text-center text-slate-600">{{ ticket.attachments_count ?? '—' }}</td>
                        <td class="table-td">{{ formatAssignees(ticket) }}</td>
                        <td class="table-td">
                            <div class="flex flex-wrap gap-x-3 gap-y-1">
                                <router-link :to="`/tickets/${ticket.id}`" class="link text-sm">View</router-link>
                                <router-link :to="`/tickets/${ticket.id}/edit`" class="link text-sm">Edit</router-link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="tickets.length" class="md:hidden space-y-3 px-3 pb-3">
            <div
                v-for="ticket in tickets"
                :key="`mobile-${ticket.id}`"
                class="table-card"
            >
                <div class="flex items-start justify-between gap-2">
                    <router-link :to="`/tickets/${ticket.id}`" class="link text-sm font-semibold">
                        #{{ ticket.ticket_number }}
                    </router-link>
                    <BaseBadge :tone="getStatusTone(ticket.status)">{{ getStatusLabel(ticket.status) }}</BaseBadge>
                </div>
                <div class="text-sm font-medium text-slate-900">
                    {{ ticket.subject }}
                </div>
                <div class="text-sm text-slate-600">
                    Customer: {{ ticket.customer?.name || '—' }}
                </div>
                <div class="text-sm text-slate-600 flex items-center gap-1.5">
                    <span>Priority:</span>
                    <BaseBadge :tone="getPriorityTone(ticket.priority)">{{ ticket.priority }}</BaseBadge>
                </div>
                <div class="text-sm text-slate-600">
                    Assigned: {{ formatAssignees(ticket) }}
                </div>
                <div class="flex flex-wrap gap-3 pt-1">
                    <router-link :to="`/tickets/${ticket.id}`" class="link text-sm">View</router-link>
                    <router-link :to="`/tickets/${ticket.id}/edit`" class="link text-sm">Edit</router-link>
                </div>
            </div>
        </div>

        <EmptyState
            v-if="!tickets.length"
            heading="No tickets to show"
            description="Nothing matches the current filters. Clear the status or date range, or create a new ticket."
        >
            <template #icon><TicketIcon class="icon" aria-hidden="true" /></template>
        </EmptyState>

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
import { FunnelIcon, PlusIcon, TicketIcon } from '@heroicons/vue/24/outline';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseBadge, BaseButton, EmptyState } from '@/components/base';
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

/** Ticket priority -> BaseBadge tone (same colour intent as the old class map). */
const getPriorityTone = (priority) => {
    const tones = {
        urgent: 'danger',
        high: 'warning',
        medium: 'warning',
        low: 'primary',
    };
    return tones[priority] || 'neutral';
};

/** Ticket status -> BaseBadge tone (same colour intent as the old class map). */
const getStatusTone = (status) => {
    const tones = {
        open: 'warning',
        in_progress: 'primary',
        on_hold: 'warning',
        resolved: 'success',
        closed: 'neutral',
    };
    return tones[status] || 'neutral';
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
