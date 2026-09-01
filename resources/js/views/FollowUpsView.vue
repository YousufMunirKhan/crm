<template>
    <ListingPageShell
        title="Follow-ups"
        subtitle="Scheduled next actions by date range — expand rows on desktop for full notes; export for calling lists."
        :badge="followUpsBadge"
    >
        <template #actions>
            <BaseButton variant="outline" block-mobile :disabled="!followUps.length" @click="exportCsv">
                <template #icon><ArrowDownTrayIcon class="icon" aria-hidden="true" /></template>
                Export CSV
            </BaseButton>
        </template>

        <template #filters>
            <div class="listing-filters-row">
                <div>
                    <label class="form-label" for="followupsview-from">From</label>
                    <input id="followupsview-from" v-model="fromDate" type="date" class="form-input w-full sm:w-40" />
                </div>
                <div>
                    <label class="form-label" for="followupsview-to">To</label>
                    <input id="followupsview-to" v-model="toDate" type="date" class="form-input w-full sm:w-40" />
                </div>
                <BaseButton
                    :variant="mode === 'due' ? 'soft' : 'outline'"
                    @click="setDueNow"
                >Due now</BaseButton>
                <BaseButton
                    :variant="mode === 'overdue' ? 'soft-danger' : 'outline'"
                    @click="setOverdue"
                >Overdue only</BaseButton>
                <BaseButton :variant="mode === 'today' ? 'soft' : 'outline'" @click="setToday">Today</BaseButton>
                <BaseButton :variant="mode === 'week' ? 'soft' : 'outline'" @click="setThisWeek">Next 7 days</BaseButton>
                <BaseButton variant="primary" @click="loadFollowUps">
                    <template #icon><FunnelIcon class="icon" aria-hidden="true" /></template>
                    Filter
                </BaseButton>
            </div>
        </template>

        <div v-if="loading" class="px-5 py-14 text-center text-slate-500 text-sm" aria-busy="true">
            <span class="spinner" role="status" aria-label="Loading" />
            <span class="ml-2 align-middle">Loading…</span>
        </div>

        <EmptyState
            v-else-if="!followUps.length"
            heading="No follow-ups for this period"
            description="Try widening the date range, or pick “This week” to see what is coming up."
        >
            <template #icon><CalendarDaysIcon class="icon" aria-hidden="true" /></template>
        </EmptyState>

        <!-- Desktop / tablet table -->
        <div v-else class="hidden sm:block overflow-hidden">
            <div class="table-wrap">
                <table class="table min-w-[900px]">
                    <caption class="sr-only">Scheduled follow-ups for the selected date range</caption>
                    <thead class="table-thead">
                        <tr>
                            <th scope="col" class="table-th">Date</th>
                            <th scope="col" class="table-th">Time</th>
                            <th scope="col" class="table-th">Customer</th>
                            <th scope="col" class="table-th">Products</th>
                            <th scope="col" class="table-th">Stage</th>
                            <th scope="col" class="table-th">Assignee</th>
                            <th scope="col" class="table-th">Latest note</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="fu in followUps" :key="fu.id">
                            <tr
                                class="table-row cursor-pointer"
                                @click="toggleExpanded(fu.id)"
                            >
                                <td class="table-td-strong">
                                    {{ formatDate(fu.next_follow_up_date) }}
                                </td>
                                <td class="table-td text-slate-600">
                                    {{ fu.next_follow_up_time || '—' }}
                                </td>
                                <td class="table-td">
                                    <router-link
                                        v-if="fu.customer_id"
                                        :to="`/customers/${fu.customer_id}`"
                                        class="block hover:text-primary-800"
                                    >
                                        <CustomerName :customer="fu.customer" name-class="link font-medium" />
                                    </router-link>
                                    <CustomerName v-else :customer="fu.customer" />
                                </td>
                                <td class="table-td">
                                    {{ fu.products || '—' }}
                                </td>
                                <td class="table-td">
                                    <BaseBadge :tone="stageTone(fu.stage)">{{ formatStage(fu.stage) }}</BaseBadge>
                                </td>
                                <td class="table-td">
                                    {{ fu.assignee?.name || '—' }}
                                </td>
                                <td class="table-td max-w-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="block truncate flex-1" :title="fu.latest_note || ''">
                                            {{ fu.latest_note || '—' }}
                                        </span>
                                        <BaseButton
                                            v-if="fu.latest_note"
                                            variant="ghost"
                                            size="sm"
                                            class="whitespace-nowrap"
                                            @click.stop="toggleExpanded(fu.id)"
                                        >
                                            {{ expandedId === fu.id ? 'Hide note' : 'View note' }}
                                        </BaseButton>
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
            <div v-for="fu in followUps" :key="fu.id" class="table-card">
                <div class="flex justify-between items-start gap-2">
                    <div>
                        <div class="text-eyebrow text-slate-500 uppercase">Date &amp; time</div>
                        <div class="text-sm font-medium text-slate-900">
                            {{ formatDate(fu.next_follow_up_date) }} · {{ fu.next_follow_up_time || '—' }}
                        </div>
                    </div>
                    <BaseBadge :tone="stageTone(fu.stage)">{{ formatStage(fu.stage) }}</BaseBadge>
                </div>
                <div>
                    <div class="text-eyebrow text-slate-500 uppercase">Customer</div>
                    <router-link
                        v-if="fu.customer_id"
                        :to="`/customers/${fu.customer_id}`"
                        class="block hover:text-primary-800"
                    >
                        <CustomerName :customer="fu.customer" name-class="link text-sm font-medium" />
                    </router-link>
                    <CustomerName v-else :customer="fu.customer" name-class="text-sm font-medium text-slate-900" />
                </div>
                <div v-if="fu.products">
                    <div class="text-eyebrow text-slate-500 uppercase">Products</div>
                    <div class="text-sm text-slate-700">
                        {{ fu.products }}
                    </div>
                </div>
                <div>
                    <div class="text-eyebrow text-slate-500 uppercase">Assignee</div>
                    <div class="text-sm text-slate-700">
                        {{ fu.assignee?.name || '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-eyebrow text-slate-500 uppercase mb-1">Latest note</div>
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
import { ArrowDownTrayIcon, CalendarDaysIcon, FunnelIcon } from '@heroicons/vue/24/outline';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';
import { formatLeadStage } from '@/utils/displayFormat';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseBadge, BaseButton, EmptyState } from '@/components/base';
import CustomerName from '@/components/CustomerName.vue';

const loading = ref(true);
const followUps = ref([]);
const fromDate = ref('');
const toDate = ref('');
const expandedId = ref(null);

const todayStr = computed(() => {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
});

/** Which quick range is active; drives both the request and the button state. */
const mode = ref('due');

/** Anything whose date has passed, so the count can be said out loud. */
const overdueCount = computed(() => followUps.value.filter(isOverdue).length);

function isOverdue(row) {
    const due = row.next_follow_up_at || row.next_follow_up_date;

    return due ? new Date(due) < new Date(new Date().toDateString()) : false;
}

const followUpsBadge = computed(() => {
    if (loading.value || !followUps.value.length) return null;

    return overdueCount.value
        ? `${overdueCount.value} overdue of ${followUps.value.length}`
        : `${followUps.value.length} due`;
});

/**
 * Everything outstanding: overdue first, then the week ahead. This is the
 * default, because a follow-up that was due last Tuesday is more urgent than
 * one due tomorrow - and the previous default, today..+7, hid it completely.
 */
function setDueNow() {
    mode.value = 'due';
    fromDate.value = '';
    toDate.value = '';
    loadFollowUps();
}

function setOverdue() {
    mode.value = 'overdue';
    fromDate.value = '';
    toDate.value = '';
    loadFollowUps();
}

function setToday() {
    mode.value = 'today';
    fromDate.value = todayStr.value;
    toDate.value = todayStr.value;
    loadFollowUps();
}

function setThisWeek() {
    mode.value = 'week';
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

const formatStage = formatLeadStage;

/** Lead stage -> BaseBadge tone. Mirrors the previous colour intent exactly. */
function stageTone(stage) {
    const map = {
        follow_up: 'primary',
        lead: 'success',
        hot_lead: 'warning',
        quotation: 'primary',
        won: 'success',
        lost: 'danger',
    };
    return map[stage] || 'neutral';
}

async function loadFollowUps() {
    loading.value = true;
    try {
        const params = {};

        if (mode.value === 'overdue') {
            params.overdue = 1;
        } else if (mode.value === 'due') {
            const horizon = new Date();
            horizon.setDate(horizon.getDate() + 7);
            params.due_by = horizon.toISOString().slice(0, 10);
        } else if (fromDate.value && toDate.value && fromDate.value !== toDate.value) {
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
        { key: 'customer.business_name', label: 'Company' },
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
    setDueNow();
});
</script>

