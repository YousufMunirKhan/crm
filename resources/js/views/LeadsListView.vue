<template>
    <ListingPageShell
        title="Leads"
        subtitle="Every lead that matches the filters below, newest first. Set the filters, then review the totals and the table."
        :badge="leadsBadge"
    >
        <template #actions>
            <BaseButton variant="primary" to="/leads/pipeline" block-mobile>
                <template #icon><Squares2X2Icon class="icon-sm" aria-hidden="true" /></template>
                Lead Pipeline
            </BaseButton>
            <BaseButton
                variant="outline"
                block-mobile
                :loading="exporting"
                :disabled="!stats?.total"
                @click="exportAllCsv"
            >
                <template #icon><ArrowDownTrayIcon class="icon-sm" aria-hidden="true" /></template>
                {{ exporting ? 'Exporting…' : 'Export CSV (all)' }}
            </BaseButton>
            <BaseButton variant="outline" block-mobile :disabled="!leads.length" @click="exportPageCsv">
                <template #icon><ArrowDownTrayIcon class="icon-sm" aria-hidden="true" /></template>
                Export page
            </BaseButton>
        </template>

        <template #filters>
            <div class="listing-filters-row">
                <div class="w-full">
                    <label class="listing-label" for="leadslistview-search">Search</label>
                    <input
                        id="leadslistview-search"
                        v-model="filters.search"
                        type="search"
                        class="form-input-search w-full"
                        placeholder="Customer name, company, phone, email, product or #lead number"
                        @input="onSearchInput"
                    />
                </div>

                <div class="w-full flex flex-wrap items-center gap-2">
                    <span class="text-eyebrow uppercase text-slate-500 mr-1">Quick range</span>
                    <BaseButton variant="outline" @click="presetRange('today')">Today</BaseButton>
                    <BaseButton variant="outline" @click="presetRange('2d')">Last 2 days</BaseButton>
                    <BaseButton variant="outline" @click="presetRange('5d')">Last 5 days</BaseButton>
                    <BaseButton variant="outline" @click="presetRange('last_week')">Last week</BaseButton>
                    <BaseButton variant="outline" @click="presetRange('week')">This week</BaseButton>
                    <BaseButton variant="outline" @click="presetRange('month')">This month</BaseButton>
                    <BaseButton variant="outline" @click="presetRange('30d')">Last 30 days</BaseButton>
                    <BaseButton variant="ghost" @click="clearDates">Clear dates</BaseButton>
                </div>

                <div class="w-full sm:w-auto sm:min-w-[10rem]">
                    <label class="listing-label" for="leadslistview-created-from">Created from</label>
                    <input
                        id="leadslistview-created-from"
                        v-model="filters.from"
                        type="date"
                        class="form-input"
                    />
                </div>
                <div class="w-full sm:w-auto sm:min-w-[10rem]">
                    <label class="listing-label" for="leadslistview-created-to">Created to</label>
                    <input
                        id="leadslistview-created-to"
                        v-model="filters.to"
                        type="date"
                        class="form-input"
                    />
                </div>
                <div class="w-full sm:w-auto sm:min-w-[10rem]">
                    <label class="listing-label" for="leadslistview-stage">Stage</label>
                    <select id="leadslistview-stage" v-model="filters.stage" class="form-select">
                        <option value="">All stages</option>
                        <option value="follow_up">Follow-up</option>
                        <option value="lead">Lead</option>
                        <option value="hot_lead">Hot lead</option>
                        <option value="quotation">Quotation</option>
                        <option value="won">Won</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto sm:min-w-[10rem]">
                    <label class="listing-label" for="leadslistview-source">Source</label>
                    <input
                        id="leadslistview-source"
                        v-model="filters.source"
                        type="text"
                        placeholder="Exact match"
                        class="form-input"
                    />
                </div>
                <div v-if="isAdmin" class="w-full sm:w-auto sm:min-w-[11rem]">
                    <label class="listing-label" for="leadslistview-assignee">Assignee</label>
                    <select id="leadslistview-assignee" v-model="filters.assigned_to" class="form-select">
                        <option value="">All owners</option>
                        <option value="unassigned">Unassigned only</option>
                        <option v-for="emp in employees" :key="emp.id" :value="String(emp.id)">{{ emp.name }}</option>
                    </select>
                </div>

                <div class="w-full sm:w-auto flex flex-col gap-2">
                    <label
                        v-if="isAdmin"
                        class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer select-none"
                    >
                        <input v-model="filters.assigned_by_me" type="checkbox" class="form-checkbox" />
                        <span>Leads I assigned</span>
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <BaseButton variant="soft" block-mobile @click="applyFilters">
                            <template #icon><FunnelIcon class="icon-sm" aria-hidden="true" /></template>
                            Apply filters
                        </BaseButton>
                        <BaseButton variant="outline" block-mobile @click="resetFilters">
                            <template #icon><ArrowPathIcon class="icon-sm" aria-hidden="true" /></template>
                            Reset
                        </BaseButton>
                    </div>
                </div>
            </div>
        </template>

        <template #toolbar>
            <div class="space-y-4">
                <div
                    v-if="statsLoading && !stats"
                    class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4"
                    aria-busy="true"
                >
                    <div v-for="n in 3" :key="n" class="card p-4 space-y-2">
                        <span class="skeleton-text block w-1/2" />
                        <span class="skeleton-text block w-2/3" />
                        <span class="skeleton-text block w-1/3" />
                    </div>
                </div>

                <template v-else-if="stats">
                    <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                        <StatCard
                            label="Total leads"
                            :value="stats.total"
                            caption="Matching filters"
                            tone="primary"
                        >
                            <template #icon><UsersIcon class="icon" aria-hidden="true" /></template>
                        </StatCard>
                        <StatCard
                            label="Total products won"
                            :value="stats.won_product_units ?? 0"
                            :caption="wonUnitsCaption"
                            tone="success"
                        >
                            <template #icon><CubeIcon class="icon" aria-hidden="true" /></template>
                        </StatCard>
                        <StatCard
                            label="Unassigned"
                            :value="stats.unassigned_count"
                            caption="No owner set"
                            tone="warning"
                            class="col-span-2 lg:col-span-1"
                        >
                            <template #icon><UserPlusIcon class="icon" aria-hidden="true" /></template>
                        </StatCard>
                    </div>

                    <div class="card p-4">
                        <p class="text-eyebrow uppercase text-slate-500 mb-2">By stage</p>
                        <div class="flex flex-wrap gap-1.5">
                            <BaseBadge v-for="(count, st) in stats.by_stage" :key="st" :tone="stageTone(st)">
                                {{ shortStage(st) }}
                                <span class="tabular-nums font-bold ml-1">{{ count }}</span>
                            </BaseBadge>
                        </div>
                    </div>

                    <div v-if="stats.by_assignee && stats.by_assignee.length" class="pt-1">
                        <p class="text-eyebrow uppercase text-slate-500 mb-2">Leads by owner (click to filter)</p>
                        <div class="flex flex-wrap gap-2">
                            <BaseButton
                                v-for="row in stats.by_assignee"
                                :key="row.id"
                                :variant="String(filters.assigned_to) === String(row.id) ? 'soft' : 'outline'"
                                @click="filterByAssignee(row.id)"
                            >
                                <span class="truncate max-w-[10rem]">{{ row.name }}</span>
                                <span
                                    class="tabular-nums text-xs font-bold text-slate-700 bg-slate-100 rounded-full px-2 py-0.5 ml-2"
                                >{{ row.count }}</span>
                            </BaseButton>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <div class="px-4 sm:px-6 py-3 border-b border-slate-100 text-sm text-slate-600">
            <span v-if="pagination" class="font-semibold text-slate-900 tabular-nums">{{ pagination.total }}</span>
            <span v-if="pagination"> leads</span>
            <span v-else>—</span>
            <span v-if="stageLabel" class="text-slate-500"> · {{ stageLabel }}</span>
        </div>

        <div v-if="loading" class="p-4 sm:p-6 space-y-3" aria-busy="true">
            <span v-for="n in 6" :key="n" class="skeleton-text block w-full" />
        </div>

        <EmptyState
            v-else-if="!leads.length"
            heading="No leads match these filters"
            description="Try a shorter search term, widening the date range, or resetting the filters above."
        >
            <template #icon><FunnelIcon class="icon" aria-hidden="true" /></template>
            <template #action>
                <BaseButton variant="outline" @click="resetFilters">Reset filters</BaseButton>
            </template>
        </EmptyState>

        <div v-else class="hidden lg:block table-wrap">
            <table class="table" style="min-width: 1320px">
                <caption class="sr-only">Leads matching the current filters</caption>
                <thead class="table-thead">
                    <tr>
                        <th scope="col" class="table-th w-24">Lead</th>
                        <th scope="col" class="table-th">Customer</th>
                        <th scope="col" class="table-th min-w-[10rem]">Email</th>
                        <th scope="col" class="table-th w-28">Created</th>
                        <th scope="col" class="table-th">Created by</th>
                        <th scope="col" class="table-th">Next activity</th>
                        <th scope="col" class="table-th">Products</th>
                        <th scope="col" class="table-th">Stage</th>
                        <th scope="col" class="table-th">Assignee</th>
                        <th scope="col" class="table-th">Source</th>
                        <th scope="col" class="table-th-num">Value (£)</th>
                        <th scope="col" class="table-th w-36">Follow-up</th>
                        <th scope="col" class="table-th w-56">Log</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="lead in leads" :key="lead.id" class="table-row">
                        <td class="table-td font-mono">
                            <router-link :to="`/leads/${lead.id}`" class="link font-semibold">
                                #{{ lead.id }}
                            </router-link>
                        </td>
                        <td class="table-td">
                            <router-link
                                :to="`/leads/${lead.id}`"
                                class="block max-w-[16rem] hover:text-primary-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 rounded-control"
                            >
                                <CustomerName :customer="lead.customer" name-class="font-semibold text-slate-900" />
                            </router-link>
                        </td>
                        <td class="table-td">
                            <a
                                v-if="lead.customer?.email"
                                :href="`mailto:${lead.customer.email}`"
                                class="link truncate max-w-[14rem] inline-block align-bottom"
                                :title="lead.customer.email"
                                @click.stop
                            >{{ lead.customer.email }}</a>
                            <span v-else class="text-slate-500">—</span>
                        </td>
                        <td class="table-td whitespace-nowrap">{{ formatDate(lead.created_at) }}</td>
                        <td class="table-td">{{ lead.creator?.name || '—' }}</td>
                        <td class="table-td max-w-[14rem]">
                            <span
                                v-if="lead.next_activity_summary"
                                class="line-clamp-2"
                                :title="lead.next_activity_summary"
                            >{{ lead.next_activity_summary }}</span>
                            <BaseBadge v-else tone="warning">No activity</BaseBadge>
                        </td>
                        <td class="table-td max-w-xs">
                            <span class="block truncate" :title="productNames(lead)">{{ productNames(lead) || '—' }}</span>
                        </td>
                        <td class="table-td">
                            <BaseBadge :tone="stageTone(lead.stage)">{{ formatStage(lead.stage) }}</BaseBadge>
                        </td>
                        <td class="table-td">{{ lead.assignee?.name || '—' }}</td>
                        <td class="table-td">{{ lead.source || '—' }}</td>
                        <td class="table-td-num table-td-strong">£{{ formatNumber(getLeadValue(lead)) }}</td>
                        <td class="table-td whitespace-nowrap">{{ formatDateTime(lead.next_follow_up_at) || '—' }}</td>
                        <td class="table-td">
                            <QuickLogActivity :lead-id="lead.id" compact @logged="onQuickLogged" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!--
            Small screens get a card instead. Twelve columns cannot be read
            sideways, so this keeps the four things a rep acts on - who it is,
            what stage, what is next, and how to reach them - and drops the
            rest, which are available on the lead itself.
        -->
        <div v-if="leads.length" class="lg:hidden space-y-3 px-3 pb-4 min-w-0">
            <router-link
                v-for="lead in leads"
                :key="`m-${lead.id}`"
                :to="`/leads/${lead.id}`"
                class="table-card block space-y-3 min-w-0 hover:border-primary-300"
            >
                <div class="flex items-start justify-between gap-3 min-w-0">
                    <div class="min-w-0 flex-1">
                        <CustomerName :customer="lead.customer" name-class="font-semibold text-slate-900" />
                        <span class="mt-1 block text-xs text-slate-500 font-mono">#{{ lead.id }}</span>
                    </div>
                    <BaseBadge :tone="stageTone(lead.stage)" class="shrink-0">
                        {{ formatLeadStage(lead.stage) }}
                    </BaseBadge>
                </div>

                <div class="text-sm text-slate-700 break-words">
                    {{ productNames(lead) || 'No products yet' }}
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                    <span class="text-slate-600">{{ lead.next_activity_summary || 'No activity' }}</span>
                    <span v-if="lead.next_follow_up_at" class="text-slate-600">
                        Follow up {{ formatDate(lead.next_follow_up_at) }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 text-xs text-slate-500">
                    <span>{{ lead.assignee?.name || 'Unassigned' }}</span>
                    <span>{{ formatDate(lead.created_at) }}</span>
                </div>

                <!--
                    Tap to call, and a separate button to copy. Tapping a tel:
                    link dials, which is wrong when what you wanted was to paste
                    the number into WhatsApp - and long-pressing to find "copy"
                    is not something people discover.
                -->
                <div v-if="lead.customer?.email || lead.customer?.phone" class="space-y-2 pt-1">
                    <div v-if="lead.customer?.phone" class="flex items-center gap-1 min-w-0">
                        <a
                            :href="`tel:${lead.customer.phone}`"
                            class="btn btn-sm btn-soft min-h-[44px] flex-1 justify-center"
                            @click.stop
                        >{{ lead.customer.phone }}</a>
                        <CopyButton :value="lead.customer.phone" label="phone number" />
                    </div>
                    <div v-if="lead.customer?.email" class="flex items-center gap-1 min-w-0">
                        <a
                            :href="`mailto:${lead.customer.email}`"
                            class="btn btn-sm btn-soft min-h-[44px] flex-1 min-w-0 flex-nowrap justify-center"
                            @click.stop
                        ><span class="truncate">{{ lead.customer.email }}</span></a>
                        <CopyButton :value="lead.customer.email" label="email address" />
                    </div>
                </div>

                <!-- Calling and recording the call belong next to each other. -->
                <div class="border-t border-slate-100 pt-3">
                    <QuickLogActivity :lead-id="lead.id" @logged="onQuickLogged" />
                </div>
            </router-link>
        </div>

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
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import {
    ArrowDownTrayIcon,
    ArrowPathIcon,
    CubeIcon,
    FunnelIcon,
    Squares2X2Icon,
    UserPlusIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';
import { formatLeadStage } from '@/utils/displayFormat';
import { useAuthStore } from '@/stores/auth';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import CustomerName from '@/components/CustomerName.vue';
import QuickLogActivity from '@/components/QuickLogActivity.vue';
import { BaseBadge, BaseButton, EmptyState, StatCard } from '@/components/base';
import CopyButton from '@/components/CopyButton.vue';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const leads = ref([]);
const pagination = ref(null);
const loading = ref(true);
const employees = ref([]);
const stats = ref(null);
const statsLoading = ref(false);
const exporting = ref(false);
/** Skip query watcher when we just synced the URL from filters (avoids double fetch). */
const syncingQueryFromFilters = ref(false);

const filters = ref({
    search: '',
    stage: '',
    from: '',
    to: '',
    assigned_to: '',
    source: '',
    assigned_by_me: false,
});

/**
 * Typing is debounced; the other filters apply on their own button because they
 * are set deliberately, but a search box that only responds to a button press
 * feels broken.
 */
let searchTimeout = null;
function onSearchInput() {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadLeads(1), 350);
}

const isAdmin = computed(() => {
    const role = auth.user?.role?.name;
    return role === 'Admin' || role === 'Manager' || role === 'System Admin';
});

const leadsBadge = computed(() =>
    pagination.value?.total != null ? `${pagination.value.total} Total` : null,
);

const wonUnitsCaption = computed(() => {
    const lines = stats.value?.won_product_lines || 0;
    return lines > 0 ? `Units on won line items · ${lines} line(s)` : 'Units on won line items';
});

const stageLabel = computed(() => {
    if (!filters.value.stage) return '';
    const map = {
        follow_up: 'Follow-up',
        lead: 'Lead',
        hot_lead: 'Hot lead',
        quotation: 'Quotation',
        won: 'Won',
        lost: 'Lost',
    };
    return map[filters.value.stage] || filters.value.stage;
});

function formatLocalYmd(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function startOfWeekMonday(d) {
    const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const day = x.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    x.setDate(x.getDate() + diff);
    return x;
}

function presetRange(kind) {
    const now = new Date();
    if (kind === 'today') {
        const t = formatLocalYmd(now);
        filters.value.from = t;
        filters.value.to = t;
    } else if (kind === '2d') {
        const end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const start = new Date(end);
        start.setDate(start.getDate() - 1);
        filters.value.from = formatLocalYmd(start);
        filters.value.to = formatLocalYmd(end);
    } else if (kind === '5d') {
        const end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const start = new Date(end);
        start.setDate(start.getDate() - 4);
        filters.value.from = formatLocalYmd(start);
        filters.value.to = formatLocalYmd(end);
    } else if (kind === 'last_week') {
        const thisMonday = startOfWeekMonday(now);
        const lastMonday = new Date(thisMonday);
        lastMonday.setDate(lastMonday.getDate() - 7);
        const lastSunday = new Date(lastMonday);
        lastSunday.setDate(lastMonday.getDate() + 6);
        filters.value.from = formatLocalYmd(lastMonday);
        filters.value.to = formatLocalYmd(lastSunday);
    } else if (kind === 'month') {
        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        filters.value.from = formatLocalYmd(start);
        filters.value.to = formatLocalYmd(end);
    } else if (kind === '30d') {
        const end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const start = new Date(end);
        start.setDate(start.getDate() - 29);
        filters.value.from = formatLocalYmd(start);
        filters.value.to = formatLocalYmd(end);
    } else if (kind === 'week') {
        const start = startOfWeekMonday(now);
        const end = new Date(start);
        end.setDate(start.getDate() + 6);
        filters.value.from = formatLocalYmd(start);
        filters.value.to = formatLocalYmd(end);
    }
    applyFilters();
}

function clearDates() {
    filters.value.from = '';
    filters.value.to = '';
    applyFilters();
}

function filterByAssignee(id) {
    if (!isAdmin.value) return;
    filters.value.assigned_to = String(filters.value.assigned_to) === String(id) ? '' : String(id);
    applyFilters();
}

function filterParamsForApi() {
    const p = {
        page: undefined,
        per_page: 25,
    };
    if (filters.value.search?.trim()) p.search = filters.value.search.trim();
    if (filters.value.stage) p.stage = filters.value.stage;
    if (filters.value.from) p.from = filters.value.from;
    if (filters.value.to) p.to = filters.value.to;
    if (filters.value.source?.trim()) p.source = filters.value.source.trim();
    if (filters.value.assigned_to) p.assigned_to = filters.value.assigned_to;
    if (isAdmin.value && filters.value.assigned_by_me) p.assigned_by_me = 1;
    return p;
}

function normalizeLeadListQuery(q) {
    const keys = Object.keys(q || {}).filter((k) => ['search', 'stage', 'from', 'to', 'source', 'assigned_to', 'assigned_by_me'].includes(k)).sort();
    const o = {};
    keys.forEach((k) => {
        const v = q[k];
        if (v !== undefined && v !== null && String(v) !== '') o[k] = String(v);
    });
    return o;
}

function syncUrlQuery() {
    const q = {};
    if (filters.value.search?.trim()) q.search = filters.value.search.trim();
    if (filters.value.stage) q.stage = filters.value.stage;
    if (filters.value.from) q.from = filters.value.from;
    if (filters.value.to) q.to = filters.value.to;
    if (filters.value.source?.trim()) q.source = filters.value.source.trim();
    if (filters.value.assigned_to) q.assigned_to = filters.value.assigned_to;
    if (isAdmin.value && filters.value.assigned_by_me) q.assigned_by_me = '1';
    const next = normalizeLeadListQuery(q);
    const cur = normalizeLeadListQuery(route.query);
    if (JSON.stringify(next) === JSON.stringify(cur)) return;
    syncingQueryFromFilters.value = true;
    router.replace({ path: '/leads', query: q }).finally(() => {
        nextTick(() => {
            syncingQueryFromFilters.value = false;
        });
    });
}

/**
 * A quick log changes the lead's "last touched" date, which is what the stale
 * filters read - so the row is refreshed rather than left showing a figure the
 * click has just made wrong.
 */
function onQuickLogged({ leadId }) {
    const lead = leads.value.find((l) => l.id === leadId);

    if (lead) {
        lead.updated_at = new Date().toISOString();
        lead.next_activity_summary = 'Just logged';
    }
}

async function loadLeads(page = 1) {
    loading.value = true;
    statsLoading.value = true;
    try {
        const listParams = { ...filterParamsForApi(), page };
        const statsParams = { ...filterParamsForApi() };
        delete statsParams.page;
        delete statsParams.per_page;

        const [listRes, statsRes] = await Promise.all([
            axios.get('/api/leads', { params: listParams }),
            axios.get('/api/leads/stats', { params: statsParams }),
        ]);

        const data = listRes.data || {};
        leads.value = data.data || [];
        pagination.value = {
            current_page: data.current_page || 1,
            last_page: data.last_page || 1,
            per_page: data.per_page || 25,
            total: data.total ?? leads.value.length,
        };
        stats.value = statsRes.data;
        syncUrlQuery();
    } catch (e) {
        console.error('Failed to load leads', e);
        leads.value = [];
        pagination.value = null;
        stats.value = null;
    } finally {
        loading.value = false;
        statsLoading.value = false;
    }
}

function applyFilters() {
    loadLeads(1);
}

function resetFilters() {
    filters.value = {
        search: '',
        stage: '',
        from: '',
        to: '',
        assigned_to: '',
        source: '',
        assigned_by_me: false,
    };
    loadLeads(1);
}

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

const formatStage = formatLeadStage;

function shortStage(stage) {
    const map = {
        follow_up: 'F/U',
        lead: 'Lead',
        hot_lead: 'Hot',
        quotation: 'Quote',
        won: 'Won',
        lost: 'Lost',
    };
    return map[stage] || formatLeadStage(stage);
}

function stageTone(stage) {
    const map = {
        follow_up: 'primary',
        lead: 'warning',
        hot_lead: 'warning',
        quotation: 'primary',
        won: 'success',
        lost: 'danger',
    };
    return map[stage] || 'neutral';
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

function exportPageCsv() {
    if (!leads.value.length) return;
    const columns = [
        { key: 'id', label: 'Lead #' },
        { key: 'created_at', label: 'Created' },
        { key: 'creator.name', label: 'Created by' },
        { key: 'customer.name', label: 'Customer' },
        { key: 'customer.business_name', label: 'Company' },
        { key: 'customer.email', label: 'Customer email' },
        { key: 'next_activity_summary', label: 'Next activity' },
        { key: 'stage', label: 'Stage' },
        { key: 'products', label: 'Products' },
        { key: 'assignee.name', label: 'Assignee' },
        { key: 'source', label: 'Source' },
        { key: 'pipeline_value', label: 'Pipeline Value' },
        { key: 'next_follow_up_at', label: 'Next Follow-up' },
    ];
    const data = leads.value.map((lead) => ({
        ...lead,
        products: productNames(lead),
        next_activity_summary: lead.next_activity_summary || '',
    }));
    exportCSV(data, columns, `leads_page_${pagination.value?.current_page || 1}.csv`);
}

async function exportAllCsv() {
    exporting.value = true;
    try {
        const params = new URLSearchParams();
        const fp = filterParamsForApi();
        Object.entries(fp).forEach(([k, v]) => {
            if (v !== undefined && v !== null && v !== '') {
                params.set(k, String(v));
            }
        });
        const res = await axios.get(`/api/leads/export?${params.toString()}`, {
            responseType: 'blob',
        });
        const blob = new Blob([res.data], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `leads_export_${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        console.error('Export failed', e);
    } finally {
        exporting.value = false;
    }
}

onMounted(async () => {
    filters.value.search = route.query.search || '';
    filters.value.stage = route.query.stage || '';
    filters.value.from = route.query.from || '';
    filters.value.to = route.query.to || '';
    filters.value.assigned_to = route.query.assigned_to || '';
    filters.value.source = route.query.source || '';
    filters.value.assigned_by_me = route.query.assigned_by_me === '1' || route.query.assigned_by_me === 'true';

    await loadEmployees();
    await loadLeads(1);
});

watch(
    () => route.query,
    (q) => {
        if (route.name !== 'leads-list' || syncingQueryFromFilters.value) return;
        filters.value.search = q.search || '';
        filters.value.stage = q.stage || '';
        filters.value.from = q.from || '';
        filters.value.to = q.to || '';
        filters.value.assigned_to = q.assigned_to || '';
        filters.value.source = q.source || '';
        filters.value.assigned_by_me = q.assigned_by_me === '1' || q.assigned_by_me === 'true';
        loadLeads(1);
    },
);
</script>
