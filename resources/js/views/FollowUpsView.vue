<template>
    <ListingPageShell
        title="Follow-ups"
        subtitle="Scheduled next actions by date range — expand rows on desktop for full notes; export for calling lists."
        :badge="followUpsBadge"
    >
        <template #actions>
            <button type="button" class="listing-btn-outline w-full sm:w-auto" :disabled="!followUps.length" @click="exportCsv">
                Export CSV
            </button>
        </template>

        <template #filters>
            <div class="listing-filters-row">
                <div>
                    <label class="listing-label">From</label>
                    <input v-model="fromDate" type="date" class="listing-input w-full sm:w-40" />
                </div>
                <div>
                    <label class="listing-label">To</label>
                    <input v-model="toDate" type="date" class="listing-input w-full sm:w-40" />
                </div>
                <button type="button" class="listing-btn-outline" @click="setToday">Today</button>
                <button type="button" class="listing-btn-outline" @click="setThisWeek">This week</button>
                <button type="button" class="listing-btn-primary" @click="loadFollowUps">Filter</button>
            </div>
        </template>

        <div v-if="loading" class="px-5 py-14 text-center text-slate-500 text-sm">Loading…</div>

        <div v-else-if="!followUps.length" class="px-5 py-12 text-center text-slate-500 text-sm">
            No follow-ups for this period.
        </div>

        <!-- Desktop / tablet table -->
        <div v-else class="hidden sm:block overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px]">
                    <thead class="listing-thead">
                        <tr>
                            <th class="listing-th">Date</th>
                            <th class="listing-th">Time</th>
                            <th class="listing-th">Customer</th>
                            <th class="listing-th">Products</th>
                            <th class="listing-th">Stage</th>
                            <th class="listing-th">Assignee</th>
                            <th class="listing-th">Latest note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="fu in followUps" :key="fu.id">
                            <tr
                                class="listing-row cursor-pointer"
                                @click="toggleExpanded(fu.id)"
                            >
                                <td class="listing-td-strong">
                                    {{ formatDate(fu.next_follow_up_date) }}
                                </td>
                                <td class="listing-td text-slate-600">
                                    {{ fu.next_follow_up_time || '—' }}
                                </td>
                                <td class="listing-td">
                                    <router-link
                                        v-if="fu.customer_id"
                                        :to="`/customers/${fu.customer_id}`"
                                        class="text-slate-900 font-medium text-blue-600 hover:text-blue-800 hover:underline"
                                    >
                                        {{ fu.customer?.name || '—' }}
                                    </router-link>
                                    <span v-else class="text-slate-900 font-medium">
                                        {{ fu.customer?.name || '—' }}
                                    </span>
                                </td>
                                <td class="listing-td">
                                    {{ fu.products || '—' }}
                                </td>
                                <td class="listing-td">
                                <span
                                    class="inline-flex px-2 py-1 rounded-full font-medium whitespace-nowrap"
                                    :class="stageClass(fu.stage)"
                                >
                                    {{ formatStage(fu.stage) }}
                                </span>
                                </td>
                                <td class="listing-td">
                                    {{ fu.assignee?.name || '—' }}
                                </td>
                                <td class="listing-td max-w-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="block truncate flex-1" :title="fu.latest_note || ''">
                                            {{ fu.latest_note || '—' }}
                                        </span>
                                        <button
                                            v-if="fu.latest_note"
                                            type="button"
                                            class="text-xs text-blue-600 hover:text-blue-800 whitespace-nowrap"
                                            @click.stop="toggleExpanded(fu.id)"
                                        >
                                            {{ expandedId === fu.id ? 'Hide note' : 'View note' }}
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-if="expandedId === fu.id"
                                :key="`expanded-${fu.id}`"
                                class="bg-slate-50"
                            >
                                <td colspan="7" class="px-4 sm:px-6 py-4 text-sm text-slate-700">
                                    <div class="font-semibold text-slate-900 mb-1">
                                        Note for {{ fu.customer?.name || 'customer' }} (Lead #{{ fu.id }})
                                    </div>
                                    <div class="whitespace-pre-wrap">
                                        {{ fu.latest_note }}
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Mobile cards -->
        <div v-if="followUps.length" class="sm:hidden space-y-3 px-3 pb-3">
            <div
                v-for="fu in followUps"
                :key="fu.id"
                class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-2"
            >
                <div class="flex justify-between items-start gap-2">
                    <div>
                        <div class="text-xs text-slate-500 uppercase tracking-wide">Date & time</div>
                        <div class="text-sm font-medium text-slate-900">
                            {{ formatDate(fu.next_follow_up_date) }} · {{ fu.next_follow_up_time || '—' }}
                        </div>
                    </div>
                    <span
                        class="inline-flex px-2 py-1 rounded-full text-[11px] font-medium"
                        :class="stageClass(fu.stage)"
                    >
                        {{ formatStage(fu.stage) }}
                    </span>
                </div>
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Customer</div>
                    <router-link
                        v-if="fu.customer_id"
                        :to="`/customers/${fu.customer_id}`"
                        class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline"
                    >
                        {{ fu.customer?.name || '—' }}
                    </router-link>
                    <span v-else class="text-sm font-medium text-slate-900">
                        {{ fu.customer?.name || '—' }}
                    </span>
                </div>
                <div v-if="fu.products">
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Products</div>
                    <div class="text-sm text-slate-700">
                        {{ fu.products }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Assignee</div>
                    <div class="text-sm text-slate-700">
                        {{ fu.assignee?.name || '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide mb-1">Latest note</div>
                    <div class="text-sm text-slate-700 whitespace-pre-wrap">
                        {{ fu.latest_note || '—' }}
                    </div>
                </div>
            </div>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';
import ListingPageShell from '@/components/ListingPageShell.vue';

const loading = ref(true);
const followUps = ref([]);
const fromDate = ref('');
const toDate = ref('');
const expandedId = ref(null);

const todayStr = computed(() => {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
});

const followUpsBadge = computed(() =>
    !loading.value && followUps.value.length ? `${followUps.value.length} Total` : null,
);

function setToday() {
    fromDate.value = todayStr.value;
    toDate.value = todayStr.value;
    loadFollowUps();
}

function setThisWeek() {
    const today = new Date();
    const end = new Date();
    end.setDate(today.getDate() + 7);
    fromDate.value = today.toISOString().slice(0, 10);
    toDate.value = end.toISOString().slice(0, 10);
    loadFollowUps();
}

function formatDate(ymd) {
    if (!ymd) return '—';
    const [y, m, d] = ymd.split('-');
    return `${d}/${m}/${y}`;
}

function formatStage(stage) {
    if (!stage) return '—';
    if (stage === 'follow_up') return 'Follow-up';
    return stage.replace('_', ' ').replace(/\b\w/g, (l) => l.toUpperCase());
}

function stageClass(stage) {
    const map = {
        follow_up: 'bg-blue-100 text-blue-800',
        lead: 'bg-green-100 text-green-800',
        hot_lead: 'bg-orange-100 text-orange-800',
        quotation: 'bg-purple-100 text-purple-800',
        won: 'bg-emerald-100 text-emerald-800',
        lost: 'bg-red-100 text-red-800',
    };
    return map[stage] || 'bg-slate-100 text-slate-700';
}

async function loadFollowUps() {
    loading.value = true;
    try {
        const params = {};
        if (fromDate.value && toDate.value && fromDate.value !== toDate.value) {
            params.from = fromDate.value;
            params.to = toDate.value;
        } else if (fromDate.value) {
            params.date = fromDate.value;
        } else {
            params.date = todayStr.value;
        }
        const res = await axios.get('/api/followups', { params });
        followUps.value = res.data ?? [];
    } catch (e) {
        console.error('Failed to load follow-ups', e);
        followUps.value = [];
    } finally {
        loading.value = false;
    }
}

function exportCsv() {
    if (!followUps.value.length) return;
    const columns = [
        { key: 'next_follow_up_date', label: 'Date' },
        { key: 'next_follow_up_time', label: 'Time' },
        { key: 'customer.name', label: 'Customer' },
        { key: 'products', label: 'Products' },
        { key: 'stage', label: 'Stage' },
        { key: 'assignee.name', label: 'Assignee' },
        { key: 'latest_note', label: 'Latest note' },
    ];
    exportCSV(followUps.value, columns, `followups_${todayStr.value}.csv`);
}

function toggleExpanded(id) {
    expandedId.value = expandedId.value === id ? null : id;
}

onMounted(() => {
    setThisWeek();
});
</script>

