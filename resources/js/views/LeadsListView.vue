<template>
    <div class="w-full min-w-0 max-w-[1600px] mx-auto px-3 sm:px-4 md:px-6 py-4 sm:py-6 space-y-5">
        <!-- Page header -->
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
            <div class="min-w-0 space-y-1">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900">Leads</h1>
                <p class="text-sm sm:text-base text-slate-600 max-w-2xl leading-relaxed">
                    Set filters first, then review totals and the table. Open the pipeline board or export when you need a wider view.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <router-link
                    to="/leads/pipeline"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold bg-slate-900 text-white hover:bg-slate-800 shadow-sm border border-slate-800/20 touch-manipulation"
                >
                    <span class="text-base leading-none" aria-hidden="true">▣</span>
                    Lead Pipeline
                </router-link>
                <button
                    type="button"
                    class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 touch-manipulation disabled:opacity-50"
                    :disabled="exporting || !stats?.total"
                    @click="exportAllCsv"
                >
                    {{ exporting ? 'Exporting…' : 'Export CSV (all)' }}
                </button>
                <button
                    type="button"
                    class="inline-flex items-center justify-center px-4 py-2.5 rounded-xl text-sm font-semibold border border-violet-200 bg-violet-50 text-violet-900 hover:bg-violet-100 touch-manipulation disabled:opacity-50"
                    :disabled="!leads.length"
                    @click="exportPageCsv"
                >
                    Export page
                </button>
            </div>
        </div>

        <!-- Filters first, then stats + table -->
        <div class="rounded-2xl border border-slate-200/90 bg-gradient-to-b from-slate-50/95 to-slate-50/70 shadow-sm p-4 sm:p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wide">Filters</h2>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-white border border-slate-200 hover:bg-slate-100" @click="presetRange('today')">Today</button>
                    <button type="button" class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-white border border-slate-200 hover:bg-slate-100" @click="presetRange('2d')">Last 2 days</button>
                    <button type="button" class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-white border border-slate-200 hover:bg-slate-100" @click="presetRange('5d')">Last 5 days</button>
                    <button type="button" class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-white border border-slate-200 hover:bg-slate-100" @click="presetRange('last_week')">Last week</button>
                    <button type="button" class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-white border border-slate-200 hover:bg-slate-100" @click="presetRange('week')">This week</button>
                    <button type="button" class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-white border border-slate-200 hover:bg-slate-100" @click="presetRange('month')">This month</button>
                    <button type="button" class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-white border border-slate-200 hover:bg-slate-100" @click="presetRange('30d')">Last 30 days</button>
                    <button type="button" class="text-xs font-semibold px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100" @click="clearDates">Clear dates</button>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 lg:gap-4 items-end">
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Created from</label>
                    <input v-model="filters.from" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500" />
                </div>
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Created to</label>
                    <input v-model="filters.to" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white shadow-sm focus:ring-2 focus:ring-sky-500 focus:border-sky-500" />
                </div>
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Stage</label>
                    <select v-model="filters.stage" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white shadow-sm focus:ring-2 focus:ring-sky-500">
                        <option value="">All stages</option>
                        <option value="follow_up">Follow-up</option>
                        <option value="lead">Lead</option>
                        <option value="hot_lead">Hot lead</option>
                        <option value="quotation">Quotation</option>
                        <option value="won">Won</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Source</label>
                    <input
                        v-model="filters.source"
                        type="text"
                        placeholder="Exact match"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white shadow-sm focus:ring-2 focus:ring-sky-500"
                    />
                </div>
                <div v-if="isAdmin" class="sm:col-span-1 lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Assignee</label>
                    <select v-model="filters.assigned_to" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm bg-white shadow-sm focus:ring-2 focus:ring-sky-500">
                        <option value="">All owners</option>
                        <option value="unassigned">Unassigned only</option>
                        <option v-for="emp in employees" :key="emp.id" :value="String(emp.id)">{{ emp.name }}</option>
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-2 flex flex-col gap-2">
                    <label v-if="isAdmin" class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer select-none">
                        <input v-model="filters.assigned_by_me" type="checkbox" class="rounded border-slate-300 text-sky-600 focus:ring-sky-500" />
                        <span>Leads I assigned</span>
                    </label>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="flex-1 min-w-[7rem] px-4 py-2.5 rounded-xl text-sm font-semibold bg-sky-600 text-white hover:bg-sky-700 shadow-sm touch-manipulation" @click="applyFilters">
                            Apply filters
                        </button>
                        <button type="button" class="px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 touch-manipulation" @click="resetFilters">
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI strip -->
        <div class="rounded-2xl border border-slate-200/90 bg-white shadow-sm shadow-slate-900/[0.04] p-4 sm:p-5">
            <div v-if="statsLoading && !stats" class="space-y-3">
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 animate-pulse">
                    <div v-for="n in 3" :key="n" class="h-24 rounded-xl bg-slate-100"></div>
                </div>
                <div class="h-20 rounded-xl bg-slate-100 animate-pulse"></div>
            </div>
            <template v-else-if="stats">
                <div class="grid grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <div class="rounded-xl border border-slate-100 bg-gradient-to-br from-slate-50 to-white p-4 ring-1 ring-slate-100/80">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total leads</div>
                        <div class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ stats.total }}</div>
                        <div class="text-xs text-slate-500 mt-1">Matching filters</div>
                    </div>
                    <div class="rounded-xl border border-emerald-100 bg-gradient-to-br from-emerald-50/90 to-white p-4 ring-1 ring-emerald-100/60">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-emerald-800/80">Total products won</div>
                        <div class="mt-1 text-2xl font-bold tabular-nums text-emerald-950">{{ stats.won_product_units ?? 0 }}</div>
                        <div class="text-xs text-emerald-800/70 mt-1">
                            Units on won line items
                            <span v-if="(stats.won_product_lines || 0) > 0" class="tabular-nums"> · {{ stats.won_product_lines }} line(s)</span>
                        </div>
                    </div>
                    <div class="rounded-xl border border-amber-100 bg-gradient-to-br from-amber-50/80 to-white p-4 ring-1 ring-amber-100/60 col-span-2 lg:col-span-1">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-900/80">Unassigned</div>
                        <div class="mt-1 text-2xl font-bold tabular-nums text-amber-950">{{ stats.unassigned_count }}</div>
                        <div class="text-xs text-amber-900/70 mt-1">No owner set</div>
                    </div>
                </div>
                <div class="mt-4 rounded-xl border border-violet-100 bg-gradient-to-br from-violet-50/80 to-white p-4 ring-1 ring-violet-100/60">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-violet-900/80 mb-2">By stage</div>
                    <div class="flex flex-wrap gap-1.5">
                        <span
                            v-for="(count, st) in stats.by_stage"
                            :key="st"
                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium border"
                            :class="stageClass(st)"
                        >
                            {{ shortStage(st) }}
                            <span class="tabular-nums font-bold">{{ count }}</span>
                        </span>
                    </div>
                </div>

                <div v-if="stats.by_assignee && stats.by_assignee.length" class="mt-5 pt-4 border-t border-slate-100">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 mb-2">Leads by owner (click to filter)</div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="row in stats.by_assignee"
                            :key="row.id"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition-colors touch-manipulation"
                            :class="String(filters.assigned_to) === String(row.id)
                                ? 'border-sky-500 bg-sky-50 text-sky-900 ring-1 ring-sky-200'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'"
                            @click="filterByAssignee(row.id)"
                        >
                            <span class="font-medium truncate max-w-[10rem]">{{ row.name }}</span>
                            <span class="tabular-nums text-xs font-bold text-slate-600 bg-slate-100 rounded-full px-2 py-0.5">{{ row.count }}</span>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Table card -->
        <div class="rounded-2xl bg-white shadow-md shadow-slate-900/[0.06] border border-slate-200/90 overflow-hidden">
            <div class="px-4 sm:px-6 py-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
                <div class="text-sm text-slate-600">
                    <span v-if="pagination" class="font-semibold text-slate-900 tabular-nums">{{ pagination.total }}</span>
                    <span v-if="pagination"> leads</span>
                    <span v-else>—</span>
                    <span v-if="stageLabel" class="text-slate-500"> · {{ stageLabel }}</span>
                </div>
            </div>

            <div v-if="loading" class="px-5 py-16 text-center text-slate-500 text-sm">Loading leads…</div>
            <div v-else-if="!leads.length" class="px-5 py-16 text-center text-slate-500 text-sm">
                No leads match these filters. Try widening the date range or clearing the assignee.
            </div>
            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[1320px]">
                    <thead class="bg-slate-50/90 border-b border-slate-200">
                        <tr>
                            <th class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600 px-4 py-3 w-24">Lead</th>
                            <th class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600 px-4 py-3">Customer</th>
                            <th class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600 px-4 py-3 min-w-[10rem]">Email</th>
                            <th class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600 px-4 py-3 w-28">Created</th>
                            <th class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600 px-4 py-3">Created by</th>
                            <th class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600 px-4 py-3">Next activity</th>
                            <th class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600 px-4 py-3">Products</th>
                            <th class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600 px-4 py-3">Stage</th>
                            <th class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600 px-4 py-3">Assignee</th>
                            <th class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600 px-4 py-3">Source</th>
                            <th class="text-right text-xs font-semibold uppercase tracking-wide text-slate-600 px-4 py-3">Value (£)</th>
                            <th class="text-left text-xs font-semibold uppercase tracking-wide text-slate-600 px-4 py-3 w-36">Follow-up</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="lead in leads" :key="lead.id" class="hover:bg-slate-50/80 transition-colors">
                            <td class="px-4 py-3 font-mono text-sm">
                                <router-link :to="`/leads/${lead.id}`" class="font-semibold text-sky-700 hover:text-sky-900 hover:underline">
                                    #{{ lead.id }}
                                </router-link>
                            </td>
                            <td class="px-4 py-3">
                                <router-link :to="`/leads/${lead.id}`" class="font-semibold text-slate-900 hover:text-sky-800 block truncate max-w-[14rem]" :title="lead.customer?.name || ''">
                                    {{ lead.customer?.name || '—' }}
                                </router-link>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <a
                                    v-if="lead.customer?.email"
                                    :href="`mailto:${lead.customer.email}`"
                                    class="text-sky-700 hover:text-sky-900 hover:underline truncate max-w-[14rem] inline-block align-bottom"
                                    :title="lead.customer.email"
                                    @click.stop
                                >{{ lead.customer.email }}</a>
                                <span v-else class="text-slate-400">—</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 text-sm whitespace-nowrap">{{ formatDate(lead.created_at) }}</td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ lead.creator?.name || '—' }}</td>
                            <td class="px-4 py-3 max-w-[14rem]">
                                <span v-if="lead.next_activity_summary" class="text-sm text-slate-800 line-clamp-2" :title="lead.next_activity_summary">{{ lead.next_activity_summary }}</span>
                                <span v-else class="text-xs font-medium text-amber-800 bg-amber-50 border border-amber-200/80 rounded px-2 py-0.5 inline-block">No activity</span>
                            </td>
                            <td class="px-4 py-3 max-w-xs">
                                <span class="block truncate text-sm text-slate-700" :title="productNames(lead)">{{ productNames(lead) || '—' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="stageClass(lead.stage)">
                                    {{ formatStage(lead.stage) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">{{ lead.assignee?.name || '—' }}</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ lead.source || '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-slate-900 tabular-nums">£{{ formatNumber(getLeadValue(lead)) }}</td>
                            <td class="px-4 py-3 text-slate-600 text-sm whitespace-nowrap">{{ formatDateTime(lead.next_follow_up_at) || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer v-if="pagination && leads.length" class="border-t border-slate-100 bg-white px-2">
                <Pagination
                    :pagination="pagination"
                    embedded
                    result-label="leads"
                    singular-label="lead"
                    @page-change="loadLeads"
                />
            </footer>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';
import { formatLeadStage } from '@/utils/displayFormat';
import { useAuthStore } from '@/stores/auth';
import Pagination from '@/components/Pagination.vue';

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
    stage: '',
    from: '',
    to: '',
    assigned_to: '',
    source: '',
    assigned_by_me: false,
});

const isAdmin = computed(() => {
    const role = auth.user?.role?.name;
    return role === 'Admin' || role === 'Manager' || role === 'System Admin';
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
    if (filters.value.stage) p.stage = filters.value.stage;
    if (filters.value.from) p.from = filters.value.from;
    if (filters.value.to) p.to = filters.value.to;
    if (filters.value.source?.trim()) p.source = filters.value.source.trim();
    if (filters.value.assigned_to) p.assigned_to = filters.value.assigned_to;
    if (isAdmin.value && filters.value.assigned_by_me) p.assigned_by_me = 1;
    return p;
}

function normalizeLeadListQuery(q) {
    const keys = Object.keys(q || {}).filter((k) => ['stage', 'from', 'to', 'source', 'assigned_to', 'assigned_by_me'].includes(k)).sort();
    const o = {};
    keys.forEach((k) => {
        const v = q[k];
        if (v !== undefined && v !== null && String(v) !== '') o[k] = String(v);
    });
    return o;
}

function syncUrlQuery() {
    const q = {};
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

function stageClass(stage) {
    const map = {
        follow_up: 'bg-blue-100 text-blue-800 border-blue-200/80',
        lead: 'bg-yellow-100 text-yellow-800 border-yellow-200/80',
        hot_lead: 'bg-orange-100 text-orange-800 border-orange-200/80',
        quotation: 'bg-purple-100 text-purple-800 border-purple-200/80',
        won: 'bg-green-100 text-green-800 border-green-200/80',
        lost: 'bg-red-100 text-red-800 border-red-200/80',
    };
    return map[stage] || 'bg-slate-100 text-slate-700 border-slate-200';
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