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
                <div class="w-full sm:flex-1 sm:min-w-64">
                    <label class="form-label" for="ticketsview-search">Search</label>
                    <input
                        id="ticketsview-search"
                        v-model="searchQuery"
                        type="search"
                        class="form-input-search w-full"
                        placeholder="Subject, customer name, company, phone or ticket #"
                        @input="onSearchInput"
                    />
                </div>
                <div class="w-full sm:w-56">
                    <label class="form-label" for="ticketsview-status">Status</label>
                    <select id="ticketsview-status" v-model="statusFilter" class="form-select" @change="onStatusFilterChange">
                        <option value="">All status</option>
                        <option value="open">Open</option>
                        <option value="in_progress">Working</option>
                        <!-- 108 tickets sit here. Nothing in the product closes
                             them off, and the filter did not offer the state,
                             so they were unreachable except through "all". -->
                        <option value="resolved">Resolved</option>
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

        <!--
            What is outstanding, before the list. The header only ever said
            "116 Total", which counts the resolved pile and the closed ones
            along with the 28 that are actually open.
        -->
        <div v-if="summary" class="flex flex-wrap gap-2 px-3 pb-3 sm:px-5">
            <button
                v-for="chip in queueChips"
                :key="chip.key"
                type="button"
                class="inline-flex min-h-[44px] items-center gap-2 rounded-control border px-3 text-sm
                       touch-manipulation transition-colors focus-visible:outline-none
                       focus-visible:ring-2 focus-visible:ring-primary-500/40"
                :class="[
                    chip.value > 0 && chip.urgent
                        ? 'border-danger-200 bg-danger-50 text-danger-800 hover:bg-danger-100'
                        : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                    statusFilter === chip.status ? 'ring-2 ring-primary-500/40' : '',
                ]"
                @click="applyChip(chip)"
            >
                <span class="font-semibold tabular-nums">{{ chip.value }}</span>
                {{ chip.label }}
            </button>
        </div>

        <div v-if="tickets.length" class="hidden md:block table-wrap">
            <table class="table">
                <caption class="sr-only">Support tickets matching the current filters</caption>
                <thead class="table-thead">
                    <tr>
                        <th scope="col" class="table-th">Ticket</th>
                        <th scope="col" class="table-th w-32">Status</th>
                        <th scope="col" class="table-th w-28">Priority</th>
                        <th scope="col" class="table-th w-36">Age</th>
                        <th scope="col" class="table-th w-40">Assigned</th>
                        <th scope="col" class="table-th w-24">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="ticket in tickets" :key="ticket.id" class="table-row">
                        <!-- The subject is what tells one ticket from another,
                             so it gets the room. The reference, the customer
                             where there is one, and any replies or files sit
                             underneath it rather than taking columns of their
                             own that are empty on almost every row. -->
                        <td class="table-td">
                            <router-link :to="`/tickets/${ticket.id}`" class="font-medium text-slate-800 hover:text-primary-700">
                                {{ ticket.subject }}
                            </router-link>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                                <span class="tabular-nums">{{ ticket.ticket_number }}</span>
                                <template v-if="ticket.customer">
                                    <span class="text-slate-300">·</span>
                                    <CustomerName :customer="ticket.customer" name-class="text-slate-600" />
                                </template>
                                <template v-if="ticket.messages_count">
                                    <span class="text-slate-300">·</span>
                                    <span>{{ ticket.messages_count }} {{ ticket.messages_count === 1 ? 'reply' : 'replies' }}</span>
                                </template>
                                <template v-if="ticket.attachments_count">
                                    <span class="text-slate-300">·</span>
                                    <span>{{ ticket.attachments_count }} {{ ticket.attachments_count === 1 ? 'file' : 'files' }}</span>
                                </template>
                            </div>
                        </td>
                        <td class="table-td">
                            <BaseBadge :tone="getStatusTone(ticket.status)">{{ getStatusLabel(ticket.status) }}</BaseBadge>
                        </td>
                        <td class="table-td">
                            <BaseBadge :tone="getPriorityTone(ticket.priority)">{{ ticket.priority }}</BaseBadge>
                        </td>
                        <td class="table-td whitespace-nowrap">
                            <span :class="ageTone(ticket)">{{ ageLabel(ticket) }}</span>
                        </td>
                        <td class="table-td">
                            <span :class="isUnassigned(ticket) ? 'font-medium text-danger-700' : ''">
                                {{ formatAssignees(ticket) }}
                            </span>
                        </td>
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
                        {{ ticket.subject }}
                    </router-link>
                    <BaseBadge :tone="getStatusTone(ticket.status)" class="shrink-0">
                        {{ getStatusLabel(ticket.status) }}
                    </BaseBadge>
                </div>
                <div class="flex flex-wrap items-center gap-x-2 text-xs text-slate-500">
                    <span class="tabular-nums">{{ ticket.ticket_number }}</span>
                    <template v-if="ticket.customer">
                        <span class="text-slate-300">·</span>
                        <CustomerName :customer="ticket.customer" name-class="text-slate-600" />
                    </template>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <BaseBadge :tone="getPriorityTone(ticket.priority)">{{ ticket.priority }}</BaseBadge>
                    <span :class="ageTone(ticket)">{{ ageLabel(ticket) }}</span>
                    <span :class="isUnassigned(ticket) ? 'font-medium text-danger-700' : 'text-slate-600'">
                        {{ formatAssignees(ticket) }}
                    </span>
                </div>
                <div class="flex flex-wrap gap-3 pt-1">
                    <router-link :to="`/tickets/${ticket.id}`" class="link text-sm">View</router-link>
                    <router-link :to="`/tickets/${ticket.id}/edit`" class="link text-sm">Edit</router-link>
                </div>
            </div>
        </div>

        <div v-if="loading" class="px-5 py-12 text-center text-slate-500 text-sm" aria-busy="true">
            <span class="spinner" role="status" aria-label="Loading" />
            <span class="ml-2 align-middle">Loading tickets…</span>
        </div>

        <EmptyState
            v-else-if="!tickets.length"
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
import CustomerName from '@/components/CustomerName.vue';

// Distinguishes "still fetching" from "nothing to show".
const loading = ref(true);

const toast = useToastStore();
const auth = useAuthStore();
const route = useRoute();

/**
 * The state of the queue, from the server, independent of the status filter.
 *
 * The badge in the header said "116 Total", which counts 108 resolved and the
 * closed ones alongside the handful that are genuinely open - so the one number
 * on the page answered a question nobody asks.
 */
const summary = ref(null);

const queueChips = computed(() => {
    if (! summary.value) return [];

    const s = summary.value;

    return [
        { key: 'open', label: 'open', value: s.open, status: 'open', urgent: false },
        { key: 'unassigned', label: 'with nobody on them', value: s.unassigned, status: 'open', urgent: true },
        { key: 'week', label: 'open over a week', value: s.over_a_week, status: 'open', urgent: true },
        { key: 'resolved', label: 'resolved, never closed', value: s.resolved_not_closed, status: 'resolved', urgent: false },
    ].filter((c) => c.value > 0);
});

function applyChip(chip) {
    statusFilter.value = statusFilter.value === chip.status ? '' : chip.status;
    onStatusFilterChange();
}

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
const searchQuery = ref('');

/** Debounced so the list follows typing without a request per keystroke. */
let searchTimeout = null;
const onSearchInput = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        pagination.value.current_page = 1;
        loadTickets();
    }, 350);
};

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
    // 34 of 150 tickets have nobody on them. "—" hid that; saying it does not.
    return ticket.assignee?.name || 'Nobody';
};

function isUnassigned(ticket) {
    return formatAssignees(ticket) === 'Nobody';
}

/**
 * How long this has been going on.
 *
 * The list showed Created and Resolved as two timestamp columns. On half the
 * resolved tickets those are the same minute - they are written up after the
 * fact - so as a measure of how long anything took, the pair said nothing. What
 * does matter is how long the open ones have been open.
 */
function ageLabel(ticket) {
    const done = ['resolved', 'closed'].includes(ticket.status);
    const from = new Date(ticket.created_at);
    const to = done && ticket.resolved_at ? new Date(ticket.resolved_at) : new Date();
    const days = Math.floor((to - from) / 86400000);

    if (done) {
        return days <= 0 ? 'Same day' : `Took ${days}d`;
    }

    if (days <= 0) return 'Today';

    return `${days} ${days === 1 ? 'day' : 'days'} open`;
}

/** Red once an open ticket is past a week, amber over a day. */
function ageTone(ticket) {
    if (['resolved', 'closed'].includes(ticket.status)) return 'text-slate-500';

    const days = Math.floor((Date.now() - new Date(ticket.created_at)) / 86400000);

    if (days > 7) return 'font-medium text-danger-700';
    if (days > 1) return 'text-warning-800';

    return 'text-slate-600';
}

const getStatusLabel = (status) => formatTicketStatus(status);

const loadTickets = async () => {
    loading.value = true;
    try {
        const params = {
            per_page: 15,
            page: pagination.value.current_page,
        };
        if (searchQuery.value.trim()) {
            params.search = searchQuery.value.trim();
        }
        if (statusFilter.value) {
            params.status = statusFilter.value;
        }
        if (dateFrom.value) params.from = dateFrom.value;
        if (dateTo.value) params.to = dateTo.value;
        if (assignedToFilter.value) params.assigned_to = assignedToFilter.value;

        const { data } = await axios.get('/api/tickets', { params });
        tickets.value = data.data || data;
        // Independent of the status filter, so the chips keep saying what is
        // outstanding even while you are looking at one slice of it.
        summary.value = data.summary || null;
        pagination.value = {
            current_page: data.current_page || 1,
            last_page: data.last_page || 1,
            per_page: data.per_page || 15,
            total: data.total ?? (Array.isArray(data.data) ? data.data.length : 0),
        };
    } catch (error) {
        console.error('Failed to load tickets:', error);
        toast.error('Failed to load tickets');
    } finally {
        loading.value = false;
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
