<template>
    <ListingPageShell
        title="Leads report"
        :subtitle="leadsListSubtitle"
        :badge="leadsListBadge"
    >
        <template #actions>
            <button type="button" class="listing-btn-outline w-full sm:w-auto" :disabled="!leads.length" @click="exportCsv">
                Export CSV
            </button>
        </template>

        <template #filters>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 lg:gap-4 items-end">
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="listing-label">From</label>
                    <input v-model="filters.from" type="date" class="listing-input" />
                </div>
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="listing-label">To</label>
                    <input v-model="filters.to" type="date" class="listing-input" />
                </div>
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="listing-label">Stage</label>
                    <select v-model="filters.stage" class="listing-input">
                        <option value="">All stages</option>
                        <option value="follow_up">Follow-up</option>
                        <option value="lead">Lead</option>
                        <option value="hot_lead">Hot Lead</option>
                        <option value="won">Won</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <div v-if="isAdmin" class="sm:col-span-1 lg:col-span-3">
                    <label class="listing-label">Employee</label>
                    <select v-model="filters.assigned_to" class="listing-input">
                        <option value="">All employees</option>
                        <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                            {{ emp.name }}
                        </option>
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-3 flex gap-2">
                    <button type="button" class="listing-btn-primary w-full sm:w-auto" @click="loadLeads(1)">Filter</button>
                </div>
            </div>
        </template>

        <div v-if="loading" class="px-5 py-14 text-center text-slate-500 text-sm">Loading…</div>

        <div v-else-if="!leads.length" class="px-5 py-12 text-center text-slate-500 text-sm">
            No leads found for this period.
        </div>

        <template v-else>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px]">
                    <thead class="listing-thead">
                        <tr>
                            <th class="listing-th">Created</th>
                            <th class="listing-th">Customer</th>
                            <th class="listing-th">Products</th>
                            <th class="listing-th">Stage</th>
                            <th class="listing-th">Assignee</th>
                            <th class="listing-th">Source</th>
                            <th class="listing-th text-right">Value (£)</th>
                            <th class="listing-th">Next follow-up</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="lead in leads" :key="lead.id" class="listing-row">
                            <td class="listing-td text-slate-800">{{ formatDate(lead.created_at) }}</td>
                            <td class="listing-td">
                                <router-link :to="`/customers/${lead.customer_id}`" class="listing-link-edit font-semibold">
                                    {{ lead.customer?.name || '—' }}
                                </router-link>
                            </td>
                            <td class="listing-td max-w-xs">
                                <span class="block truncate" :title="productNames(lead)">{{ productNames(lead) || '—' }}</span>
                            </td>
                            <td class="listing-td">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="stageClass(lead.stage)">
                                    {{ formatStage(lead.stage) }}
                                </span>
                            </td>
                            <td class="listing-td">{{ lead.assignee?.name || '—' }}</td>
                            <td class="listing-td">{{ lead.source || '—' }}</td>
                            <td class="listing-td text-right font-semibold text-slate-800">£{{ formatNumber(getLeadValue(lead)) }}</td>
                            <td class="listing-td text-slate-600">{{ formatDateTime(lead.next_follow_up_at) || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <template #pagination>
            <Pagination
                v-if="pagination && leads.length"
                :pagination="pagination"
                embedded
                result-label="leads"
                singular-label="lead"
                @page-change="loadLeads"
            />
        </template>
    </ListingPageShell>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';
import { useAuthStore } from '@/stores/auth';
import ListingPageShell from '@/components/ListingPageShell.vue';
import Pagination from '@/components/Pagination.vue';

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

const leadsListSubtitle = computed(() => {
    const base = 'Slice the pipeline by date range, stage, and (for admins) assignee — export matches to CSV.';
    return stageLabel.value ? `${base} View: ${stageLabel.value}.` : base;
});

const leadsListBadge = computed(() =>
    pagination.value?.total != null ? `${pagination.value.total} Total` : null,
);

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

