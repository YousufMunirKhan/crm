<template>
    <div class="w-full min-w-0 max-w-7xl mx-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Leads</h1>
            </div>
            <div class="flex flex-wrap items-stretch sm:items-center gap-3 w-full sm:w-auto min-w-0">
                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 w-full sm:w-auto min-w-0">
                    <label class="text-sm font-medium text-slate-700 shrink-0">From</label>
                    <input
                        v-model="filters.from"
                        type="date"
                        class="w-full sm:w-auto min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 w-full sm:w-auto min-w-0">
                    <label class="text-sm font-medium text-slate-700 shrink-0">To</label>
                    <input
                        v-model="filters.to"
                        type="date"
                        class="w-full sm:w-auto min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                </div>
                <div class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 w-full sm:w-auto min-w-0">
                    <label class="text-sm font-medium text-slate-700 shrink-0">Stage</label>
                    <select
                        v-model="filters.stage"
                        class="w-full sm:w-auto min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All</option>
                        <option value="follow_up">Follow-up</option>
                        <option value="lead">Lead</option>
                        <option value="hot_lead">Hot Lead</option>
                        <option value="won">Won</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <div v-if="isAdmin" class="flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 w-full sm:w-auto min-w-0">
                    <label class="text-sm font-medium text-slate-700 shrink-0">Employee</label>
                    <select
                        v-model="filters.assigned_to"
                        class="w-full sm:w-auto min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All</option>
                        <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                            {{ emp.name }}
                        </option>
                    </select>
                </div>
                <button
                    @click="loadLeads(1)"
                    class="px-3 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 touch-manipulation w-full sm:w-auto"
                >
                    Apply
                </button>
                <button
                    @click="exportCsv"
                    :disabled="!leads.length"
                    class="px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 disabled:opacity-50 touch-manipulation w-full sm:w-auto"
                >
                    Export CSV
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>

        <div v-else-if="!leads.length" class="bg-white rounded-xl shadow-sm p-8 text-center text-slate-500">
            No leads found for this period.
        </div>

        <div v-else class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden min-w-0">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px]">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                Created
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                Customer
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                Products
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                Stage
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                Assignee
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                Source
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                Value (£)
                            </th>
                            <th class="px-4 sm:px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                Next Follow-up
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="lead in leads"
                            :key="lead.id"
                            class="hover:bg-slate-50 transition-colors"
                        >
                            <td class="px-4 sm:px-6 py-3 text-sm text-slate-900">
                                {{ formatDate(lead.created_at) }}
                            </td>
                            <td class="px-4 sm:px-6 py-3 text-sm">
                                <router-link
                                    :to="`/customers/${lead.customer_id}`"
                                    class="text-slate-900 font-medium hover:text-blue-600"
                                >
                                    {{ lead.customer?.name || '—' }}
                                </router-link>
                            </td>
                            <td class="px-4 sm:px-6 py-3 text-sm text-slate-600 max-w-xs">
                                <span class="block truncate" :title="productNames(lead)">
                                    {{ productNames(lead) || '—' }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-3 text-xs">
                                <span
                                    class="inline-flex px-2 py-1 rounded-full font-medium whitespace-nowrap"
                                    :class="stageClass(lead.stage)"
                                >
                                    {{ formatStage(lead.stage) }}
                                </span>
                            </td>
                            <td class="px-4 sm:px-6 py-3 text-sm text-slate-600">
                                {{ lead.assignee?.name || '—' }}
                            </td>
                            <td class="px-4 sm:px-6 py-3 text-sm text-slate-600">
                                {{ lead.source || '—' }}
                            </td>
                            <td class="px-4 sm:px-6 py-3 text-sm text-right text-slate-900">
                                £{{ formatNumber(getLeadValue(lead)) }}
                            </td>
                            <td class="px-4 sm:px-6 py-3 text-sm text-slate-600">
                                {{ formatDateTime(lead.next_follow_up_at) || '—' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Simple pagination -->
            <div
                v-if="pagination && (pagination.last_page > 1)"
                class="px-4 sm:px-6 py-3 border-t border-slate-200 bg-slate-50 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-sm text-slate-600"
            >
                <div>
                    Showing
                    {{ (pagination.current_page - 1) * pagination.per_page + 1 }}
                    –
                    {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}
                    of {{ pagination.total }} leads
                </div>
                <div class="flex items-center gap-2">
                    <button
                        @click="loadLeads(1)"
                        :disabled="pagination.current_page === 1"
                        class="px-2 py-1 border border-slate-300 rounded-lg hover:bg-white disabled:opacity-50"
                    >
                        « First
                    </button>
                    <button
                        @click="loadLeads(pagination.current_page - 1)"
                        :disabled="pagination.current_page === 1"
                        class="px-2 py-1 border border-slate-300 rounded-lg hover:bg-white disabled:opacity-50"
                    >
                        ‹ Prev
                    </button>
                    <button
                        @click="loadLeads(pagination.current_page + 1)"
                        :disabled="pagination.current_page === pagination.last_page"
                        class="px-2 py-1 border border-slate-300 rounded-lg hover:bg-white disabled:opacity-50"
                    >
                        Next ›
                    </button>
                    <button
                        @click="loadLeads(pagination.last_page)"
                        :disabled="pagination.current_page === pagination.last_page"
                        class="px-2 py-1 border border-slate-300 rounded-lg hover:bg-white disabled:opacity-50"
                    >
                        Last »
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();

const leads = ref([]);
const pagination = ref(null);
const loading = ref(true);
const employees = ref([]);

const filters = ref({
    stage: '',
    from: '',
    to: '',
    assigned_to: '',
});

const isAdmin = computed(() => {
    const role = auth.user?.role?.name;
    return role === 'Admin' || role === 'Manager' || role === 'System Admin';
});

const stageLabel = computed(() => {
    if (!filters.value.stage) return '';
    const map = {
        follow_up: 'Follow-up leads',
        lead: 'Leads',
        hot_lead: 'Hot leads',
        won: 'Won deals',
        lost: 'Lost deals',
    };
    return map[filters.value.stage] || filters.value.stage;
});

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatDateTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function formatStage(stage) {
    if (!stage) return '—';
    if (stage === 'follow_up') return 'Follow-up';
    return stage.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());
}

function stageClass(stage) {
    const map = {
        follow_up: 'bg-blue-100 text-blue-800',
        lead: 'bg-yellow-100 text-yellow-800',
        hot_lead: 'bg-orange-100 text-orange-800',
        won: 'bg-green-100 text-green-800',
        lost: 'bg-red-100 text-red-800',
    };
    return map[stage] || 'bg-slate-100 text-slate-700';
}

function productNames(lead) {
    if (lead.items && lead.items.length) {
        return lead.items
            .map((i) => i.product?.name)
            .filter(Boolean)
            .join(', ');
    }
    return lead.product?.name || '';
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-GB', { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(num || 0);
}

function getLeadValue(lead) {
    if (lead.stage === 'won' && lead.items && lead.items.length > 0) {
        const itemsTotal = lead.items.reduce((sum, item) => sum + (parseFloat(item.total_price) || 0), 0);
        return itemsTotal > 0 ? itemsTotal : (lead.pipeline_value || 0);
    }
    return lead.total_value || lead.pipeline_value || 0;
}

async function loadEmployees() {
    if (!isAdmin.value) return;
    try {
        const res = await axios.get('/api/users');
        employees.value = Array.isArray(res.data) ? res.data : res.data?.data || [];
    } catch (e) {
        console.error('Failed to load employees', e);
    }
}

async function loadLeads(page = 1) {
    loading.value = true;
    try {
        const params = {
            page,
            per_page: 25,
        };
        if (filters.value.stage) params.stage = filters.value.stage;
        if (filters.value.from) params.from = filters.value.from;
        if (filters.value.to) params.to = filters.value.to;
        if (filters.value.assigned_to) params.assigned_to = filters.value.assigned_to;

        const res = await axios.get('/api/leads', { params });
        const data = res.data || {};
        leads.value = data.data || [];
        pagination.value = {
            current_page: data.current_page || 1,
            last_page: data.last_page || 1,
            per_page: data.per_page || 25,
            total: data.total || leads.value.length,
        };
    } catch (e) {
        console.error('Failed to load leads', e);
        leads.value = [];
        pagination.value = null;
    } finally {
        loading.value = false;
    }
}

function exportCsv() {
    if (!leads.value.length) return;
    const columns = [
        { key: 'created_at', label: 'Created' },
        { key: 'customer.name', label: 'Customer' },
        { key: 'stage', label: 'Stage' },
        { key: 'products', label: 'Products' },
        { key: 'assignee.name', label: 'Assignee' },
        { key: 'source', label: 'Source' },
        { key: 'pipeline_value', label: 'Pipeline Value' },
        { key: 'next_follow_up_at', label: 'Next Follow-up' },
    ];

    // Map leads into flat structure for export (products & formatted value)
    const data = leads.value.map((lead) => ({
        ...lead,
        products: productNames(lead),
    }));

    exportCSV(data, columns, 'leads_export.csv');
}

onMounted(async () => {
    // Initialise filters from query params
    filters.value.stage = route.query.stage || '';
    filters.value.from = route.query.from || '';
    filters.value.to = route.query.to || '';
    filters.value.assigned_to = route.query.assigned_to || '';

    await loadEmployees();
    await loadLeads(1);
});
</script>

