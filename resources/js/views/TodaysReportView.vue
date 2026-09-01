<template>
    <ListingPageShell
        title="Today's report"
        subtitle="Team summary for the selected day: CRM activities, entries from Today's Activity (manual logs), appointments, prospects, leads, won sales, tickets, and attendance. Export to CSV anytime."
        :badge="reportBadge"
    >
        <template #actions>
            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                <BaseButton
                    variant="outline"
                    block-mobile
                    :disabled="!report"
                    :loading="exporting"
                    @click="exportCsv"
                >
                    <template #icon><ArrowDownTrayIcon class="icon" aria-hidden="true" /></template>
                    {{ exporting ? 'Exporting…' : 'Export CSV' }}
                </BaseButton>
                <BaseButton
                    v-if="canGenerateReport"
                    variant="primary"
                    block-mobile
                    :loading="generatingReport"
                    @click="generateReport"
                >
                    <template #icon><BoltIcon class="icon" aria-hidden="true" /></template>
                    {{ generatingReport ? 'Generating…' : 'Generate report (GPT)' }}
                </BaseButton>
            </div>
        </template>

        <template #filters>
            <div class="flex flex-col sm:flex-row flex-wrap gap-3 sm:items-end">
                <div>
                    <label class="form-label" for="todaysreportview-date">Date</label>
                    <input id="todaysreportview-date" v-model="selectedDate" type="date" class="form-input w-full sm:w-44" @change="loadReport" />
                </div>
                <BaseButton v-if="selectedDate !== todayStr" variant="outline" @click="goToToday">Today</BaseButton>
            </div>
        </template>

        <!-- GPT-generated report -->
        <div v-if="generatedReport" class="space-y-4">
            <div class="card card-body">
                <h2 class="card-title mb-2">{{ generatedReport.date_label }} – Summary</h2>
                <div class="prose prose-slate max-w-none text-sm whitespace-pre-wrap text-slate-700">{{ generatedReport.generated_report }}</div>
            </div>
            <details class="card overflow-hidden">
                <summary class="px-4 py-3 cursor-pointer text-sm font-medium text-slate-700 hover:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40">Show what was sent to GPT (prompt + data)</summary>
                <div class="px-4 py-3 border-t border-slate-200 space-y-3">
                    <div>
                        <div class="text-eyebrow text-slate-500 uppercase mb-1">System prompt</div>
                        <pre class="text-xs bg-slate-50 p-3 rounded-control whitespace-pre-wrap text-slate-700">{{ generatedReport.system_prompt }}</pre>
                    </div>
                    <div>
                        <div class="text-eyebrow text-slate-500 uppercase mb-1">User message (data sent to GPT)</div>
                        <pre class="text-xs bg-slate-50 p-3 rounded-control whitespace-pre-wrap text-slate-700 max-h-96 overflow-y-auto">{{ generatedReport.raw_data_sent_to_gpt }}</pre>
                    </div>
                </div>
            </details>
        </div>

        <div v-if="loading" class="text-center py-16 text-slate-500" aria-busy="true">
            <span class="spinner" role="status" aria-label="Loading" />
            <span class="ml-2 align-middle">Loading report…</span>
        </div>
        <div v-else-if="report" class="space-y-6 min-w-0">
            <!--
                What this replaced: thirteen people down, thirteen metrics
                across, and about 160 of the 169 cells reading "0". Headed
                "T✓", "T+", "Appt" and "Logs", scrolling sideways on anything
                narrower than 920px. You could not tell at a glance whether
                anybody had done anything, which is the only question the page
                exists to answer.
            -->
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-card border border-slate-200 bg-white px-4 py-3">
                <div>
                    <p class="text-sm font-semibold text-slate-900">{{ report.date_label }}</p>
                    <p class="text-xs text-slate-500">
                        {{ activePeople.length }} of {{ report.report.length }}
                        {{ report.report.length === 1 ? 'person' : 'people' }} recorded something
                    </p>
                </div>

                <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-600">
                    <span v-for="total in dayTotals" :key="total.label" class="tabular-nums">
                        <strong class="text-base font-bold" :class="total.value ? 'text-slate-900' : 'text-slate-300'">
                            {{ total.value }}
                        </strong>
                        {{ total.label }}
                    </span>
                </div>
            </div>

            <!-- The people who did something, and what they did. -->
            <div v-if="activePeople.length" class="grid grid-cols-1 gap-3 lg:grid-cols-2">
                <div
                    v-for="user in activePeople"
                    :key="`active-${user.user_id}`"
                    class="rounded-card border border-slate-200 bg-white p-4"
                >
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <div>
                            <span class="text-sm font-semibold text-slate-900">{{ user.user_name }}</span>
                            <span class="ml-2 text-xs text-slate-500">{{ user.role }}</span>
                        </div>
                        <span class="text-xs tabular-nums text-slate-500">{{ attendanceLabel(user) }}</span>
                    </div>

                    <div class="mt-2.5 flex flex-wrap gap-1.5">
                        <span
                            v-for="item in whatTheyDid(user)"
                            :key="item.label"
                            class="rounded-control px-2 py-1 text-xs font-medium"
                            :class="item.tone"
                        >
                            {{ item.value }} {{ item.label }}
                        </span>
                    </div>
                </div>
            </div>

            <p v-else class="callout callout-warning">
                Nobody recorded anything on this day. That is either a quiet day or a day nobody
                wrote down - and with three calls logged in the company's whole history, it is
                worth knowing which.
            </p>

            <!-- Everyone else, folded away rather than printed as a grid of zeroes. -->
            <details v-if="quietPeople.length" class="rounded-card border border-slate-200 bg-white">
                <summary class="cursor-pointer px-4 py-3 text-sm text-slate-600 touch-manipulation">
                    {{ quietPeople.length }} {{ quietPeople.length === 1 ? 'person' : 'people' }}
                    recorded nothing on this day
                </summary>
                <ul class="flex flex-wrap gap-x-4 gap-y-1.5 border-t border-slate-100 px-4 py-3 text-sm text-slate-600">
                    <li v-for="user in quietPeople" :key="`quiet-${user.user_id}`">
                        {{ user.user_name }}
                        <span class="text-xs text-slate-400">{{ attendanceLabel(user) }}</span>
                    </li>
                </ul>
            </details>
                <!-- Per-user detail: timeline + lists -->
                <div class="space-y-3">
                    <h2 class="text-base font-semibold text-slate-800 pt-2">Details by person</h2>
                    <!-- Only people with something in their day. Eleven empty
                         timelines are eleven cards to scroll past. -->
                    <div
                        v-for="user in activePeople"
                        :key="'d-' + user.user_id"
                        class="card overflow-hidden min-w-0"
                    >
                        <div class="px-3 sm:px-4 py-3 bg-slate-50 border-b border-slate-200 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <span class="font-semibold text-slate-900">{{ user.user_name }}</span>
                                <span class="text-slate-500 mx-1.5 hidden sm:inline" aria-hidden="true">·</span>
                                <span class="text-sm text-slate-500">{{ user.role }}</span>
                            </div>
                            <div class="text-xs text-slate-500 lg:hidden">
                                <span v-if="user.attendance">In {{ user.attendance.check_in || '—' }} · Out {{ user.attendance.check_out || '—' }}</span>
                                <span v-else>No attendance</span>
                            </div>
                        </div>

                        <div class="p-3 sm:p-4 space-y-3 min-w-0">
                            <details open class="group rounded-control border border-slate-200 overflow-hidden min-w-0">
                                <summary
                                    class="px-3 py-2.5 cursor-pointer text-sm font-medium text-slate-800 bg-slate-50 hover:bg-slate-100 flex flex-wrap items-center justify-between gap-2 list-none [&::-webkit-details-marker]:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                >
                                    <span>Day timeline ({{ user.timeline?.length || 0 }})</span>
                                    <span class="text-xs text-slate-500">{{ user.timeline?.length ? 'Tap to collapse' : '' }}</span>
                                </summary>
                                <div v-if="!user.timeline?.length" class="p-4 text-sm text-slate-500 italic border-t border-slate-100">No recorded events for this day.</div>
                                <ul v-else class="divide-y divide-slate-100 border-t border-slate-100 sm:hidden">
                                    <li
                                        v-for="(ev, idx) in user.timeline"
                                        :key="`m-${idx}`"
                                        class="px-3 py-2.5"
                                    >
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-xs tabular-nums text-slate-500">{{ formatTime(ev.at) }}</span>
                                            <BaseBadge :tone="categoryStyle(ev.category).tone">
                                                {{ categoryStyle(ev.category).short }}
                                            </BaseBadge>
                                        </div>
                                        <p class="mt-1 text-sm font-medium text-slate-800">{{ ev.title }}</p>
                                        <p v-if="ev.detail" class="mt-0.5 break-words text-xs text-slate-600">{{ ev.detail }}</p>
                                    </li>
                                </ul>

                                <div v-if="user.timeline?.length" class="table-wrap hidden border-t border-slate-100 sm:block">
                                    <table class="table text-sm">
                                        <caption class="sr-only">Timeline of recorded events for {{ user.user_name }}</caption>
                                        <thead>
                                            <tr class="bg-slate-50 text-slate-600 text-xs uppercase tracking-wide border-b border-slate-200">
                                                <th scope="col" class="text-left font-semibold px-3 py-2 w-24 whitespace-nowrap">Time</th>
                                                <th scope="col" class="text-left font-semibold px-2 py-2 w-20 whitespace-nowrap">Type</th>
                                                <th scope="col" class="text-left font-semibold px-2 py-2">Event</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(ev, idx) in user.timeline"
                                                :key="idx"
                                                class="border-b border-slate-100 last:border-0 align-top"
                                                :class="idx % 2 === 1 ? 'bg-slate-50/60' : 'bg-white'"
                                            >
                                                <td class="px-3 py-2 text-xs text-slate-500 tabular-nums whitespace-nowrap">{{ formatTime(ev.at) }}</td>
                                                <td class="px-2 py-2">
                                                    <BaseBadge :tone="categoryStyle(ev.category).tone">
                                                        {{ categoryStyle(ev.category).short }}
                                                    </BaseBadge>
                                                </td>
                                                <td class="px-2 py-2 min-w-0">
                                                    <div class="font-medium text-slate-800">{{ ev.title }}</div>
                                                    <p v-if="ev.detail" class="text-slate-600 text-xs mt-1 break-words">{{ ev.detail }}</p>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </details>

                            <details v-if="user.activities?.length" class="rounded-control border border-slate-200 overflow-hidden">
                                <summary class="px-3 py-2.5 cursor-pointer text-sm font-medium text-slate-800 bg-slate-50 hover:bg-slate-100 list-none [&::-webkit-details-marker]:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40">Daily logs ({{ user.activities.length }})</summary>
                                <div class="divide-y divide-slate-100 border-t border-slate-100">
                                    <div v-for="act in user.activities" :key="act.id" class="px-3 py-2.5 text-sm">
                                        <p class="text-slate-800 whitespace-pre-wrap break-words">{{ act.description }}</p>
                                        <p class="text-xs text-slate-500 mt-1">{{ formatTime(act.created_at) }}</p>
                                    </div>
                                </div>
                            </details>

                            <details v-if="user.won_sales_list?.length" class="rounded-control border border-success-200 overflow-hidden">
                                <summary class="px-3 py-2.5 cursor-pointer text-sm font-medium text-success-900 bg-success-50/90 hover:bg-success-50 list-none [&::-webkit-details-marker]:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40">Won sales ({{ user.won_sales_list.length }})</summary>
                                <ul class="divide-y divide-success-100 border-t border-success-100 text-sm">
                                    <li v-for="w in user.won_sales_list" :key="w.id" class="px-3 py-2 flex flex-wrap gap-x-2 gap-y-1 text-slate-700">
                                        <span class="font-medium">{{ w.product_name || 'Product' }}</span>
                                        <span v-if="w.customer_name" class="text-slate-500">{{ withCompany(w) }}</span>
                                        <span v-if="w.quantity" class="font-medium text-success-700">
                                            {{ w.quantity }} {{ w.quantity === 1 ? 'unit' : 'units' }}
                                        </span>
                                    </li>
                                </ul>
                            </details>

                            <details v-if="user.tickets_resolved?.length" class="rounded-control border border-primary-200 overflow-hidden">
                                <summary class="px-3 py-2.5 cursor-pointer text-sm font-medium text-primary-900 bg-primary-50/90 list-none [&::-webkit-details-marker]:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40">Tickets resolved ({{ user.tickets_resolved.length }})</summary>
                                <ul class="divide-y divide-primary-100 border-t border-primary-100 text-sm">
                                    <li v-for="t in user.tickets_resolved" :key="t.id" class="px-3 py-2 break-words">
                                        #{{ t.ticket_number }} {{ t.subject }} <span v-if="t.customer_name" class="text-slate-500">— {{ withCompany(t) }}</span>
                                    </li>
                                </ul>
                            </details>

                            <details v-if="user.tickets_created?.length" class="rounded-control border border-primary-200 overflow-hidden">
                                <summary class="px-3 py-2.5 cursor-pointer text-sm font-medium text-primary-900 bg-primary-50/90 list-none [&::-webkit-details-marker]:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40">Tickets created ({{ user.tickets_created.length }})</summary>
                                <ul class="divide-y divide-primary-100 border-t border-primary-100 text-sm">
                                    <li v-for="t in user.tickets_created" :key="t.id" class="px-3 py-2 break-words">#{{ t.ticket_number }} {{ t.subject }}</li>
                                </ul>
                            </details>

                            <details v-if="user.prospects_added_list?.length" class="rounded-control border border-warning-200 overflow-hidden">
                                <summary class="px-3 py-2.5 cursor-pointer text-sm font-medium text-warning-900 bg-warning-50/90 list-none [&::-webkit-details-marker]:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40">Prospects added ({{ user.prospects_added_list.length }})</summary>
                                <ul class="divide-y divide-warning-100 border-t border-warning-100 text-sm">
                                    <li v-for="p in user.prospects_added_list" :key="p.id" class="px-3 py-2 break-words">{{ p.name }}</li>
                                </ul>
                            </details>

                            <details v-if="user.leads_added_list?.length" class="rounded-control border border-primary-200 overflow-hidden">
                                <summary class="px-3 py-2.5 cursor-pointer text-sm font-medium text-primary-900 bg-primary-50/90 list-none [&::-webkit-details-marker]:hidden focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40">Leads added ({{ user.leads_added_list.length }})</summary>
                                <ul class="divide-y divide-primary-100 border-t border-primary-100 text-sm">
                                    <li v-for="l in user.leads_added_list" :key="l.id" class="px-3 py-2 break-words">{{ withCompany(l) || 'Lead' }} — {{ l.stage }}</li>
                                </ul>
                            </details>
                        </div>
                    </div>
                </div>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { ArrowDownTrayIcon, BoltIcon } from '@heroicons/vue/24/outline';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseBadge, BaseButton } from '@/components/base';
import { customerLabel } from '@/utils/customerLabel';

/** Contact and company on one line; these digests are dense. */
const withCompany = (row) => customerLabel(row, '');

const toast = useToastStore();
const auth = useAuthStore();
const report = ref(null);
const loading = ref(false);
const exporting = ref(false);
const selectedDate = ref('');
const generatingReport = ref(false);
const generatedReport = ref(null);

const allowedReportEmails = ['yousufmunir59@gmail.com', 'owaishameed301@gmail.com'];
const canGenerateReport = computed(() => {
    const email = auth.user?.email;
    return email && allowedReportEmails.includes(email);
});

const todayStr = computed(() => new Date().toISOString().slice(0, 10));

const reportBadge = computed(() => {
    if (!report.value?.report?.length) return null;
    const n = report.value.report.length;
    return `${n} active ${n === 1 ? 'user' : 'users'}`;
});

/** Resolve count from user.counts with optional legacy key on user root */
const countVal = (user, countsKey, legacyKey = null) => {
    const c = user.counts || {};
    if (c[countsKey] != null && c[countsKey] !== '') return c[countsKey];
    if (legacyKey && user[legacyKey] != null) return user[legacyKey];
    return 0;
};

/**
 * What each metric is called when a person reads it.
 *
 * The table these replace used "T✓", "T+", "Appt" and "Logs" as column heads,
 * with the meaning hidden in a title attribute that never appears on a phone.
 */
const METRICS = [
    { key: 'won_sales', legacy: 'won_sales_count', one: 'sale won', many: 'sales won', tone: 'bg-success-50 text-success-800' },
    { key: 'crm_activities', one: 'CRM activity', many: 'CRM activities', tone: 'bg-primary-50 text-primary-800' },
    { key: 'appointments_logged', one: 'appointment', many: 'appointments', tone: 'bg-primary-50 text-primary-800' },
    { key: 'prospects_added', one: 'prospect added', many: 'prospects added', tone: 'bg-slate-100 text-slate-700' },
    { key: 'customers_added', one: 'customer added', many: 'customers added', tone: 'bg-slate-100 text-slate-700' },
    { key: 'leads_added_by_user', legacy: 'leads_added_by_user_count', one: 'lead added', many: 'leads added', tone: 'bg-slate-100 text-slate-700' },
    { key: 'leads_assigned_new_today', legacy: 'leads_created_count', one: 'lead assigned', many: 'leads assigned', tone: 'bg-slate-100 text-slate-700' },
    { key: 'tickets_resolved', one: 'ticket resolved', many: 'tickets resolved', tone: 'bg-success-50 text-success-800' },
    { key: 'tickets_created', one: 'ticket raised', many: 'tickets raised', tone: 'bg-warning-50 text-warning-900' },
    { key: 'follow_ups_due', legacy: 'follow_ups_count', one: 'follow-up due', many: 'follow-ups due', tone: 'bg-slate-100 text-slate-700' },
    { key: 'daily_logs', legacy: 'activities_count', one: 'note', many: 'notes', tone: 'bg-slate-100 text-slate-700' },
];

/** Only the metrics this person has a number for - never a row of zeroes. */
function whatTheyDid(user) {
    return METRICS
        .map((m) => {
            const value = Number(countVal(user, m.key, m.legacy) || 0);

            // "1 tickets resolved" reads as a bug in the page, not a number.
            return { ...m, value, label: value === 1 ? m.one : m.many };
        })
        .filter((m) => m.value > 0);
}

/** Somebody who put something in the record on this day. */
const activePeople = computed(() =>
    (report.value?.report ?? []).filter((u) => whatTheyDid(u).length > 0));

const quietPeople = computed(() =>
    (report.value?.report ?? []).filter((u) => whatTheyDid(u).length === 0));

/**
 * The day in a handful of figures.
 *
 * Deliberately short: the point is whether the day happened, not a full
 * accounting of it. The detail is a card away.
 */
const dayTotals = computed(() => {
    const rows = report.value?.report ?? [];
    const sum = (key, legacy = null) => rows.reduce((t, u) => t + Number(countVal(u, key, legacy) || 0), 0);

    return [
        { label: 'won', value: sum('won_sales', 'won_sales_count') },
        { label: 'appointments', value: sum('appointments_logged') },
        { label: 'CRM activities', value: sum('crm_activities') },
        { label: 'tickets resolved', value: sum('tickets_resolved') },
        { label: 'clocked in', value: rows.filter((u) => u.attendance?.check_in).length },
    ];
});

function attendanceLabel(user) {
    const inAt = user.attendance?.check_in;

    if (! inAt) return 'not clocked in';

    const outAt = user.attendance?.check_out;
    const hours = user.attendance?.work_hours;

    return outAt
        ? `${inAt} - ${outAt}${hours ? ` (${hours}h)` : ''}`
        : `in at ${inAt}`;
}

/** Timeline category -> short label + BaseBadge tone (same colour intent as before). */
const categoryStyle = (cat) => {
    const map = {
        daily_log: { short: 'Entered', tone: 'neutral' },
        crm_activity: { short: 'CRM', tone: 'primary' },
        ticket_resolved: { short: 'Ticket', tone: 'primary' },
        ticket_created: { short: 'Ticket+', tone: 'primary' },
        prospect_added: { short: 'Prospect', tone: 'warning' },
        customer_added: { short: 'Customer', tone: 'primary' },
        lead_added: { short: 'Lead', tone: 'primary' },
        sale_won: { short: 'Sale', tone: 'success' },
    };
    return map[cat] || { short: (cat || '?').slice(0, 6), tone: 'neutral' };
};

const formatTime = (d) => {
    if (!d) return '—';
    return new Date(d).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};

const loadReport = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/daily-activities/todays-report', {
            params: { date: selectedDate.value },
        });
        report.value = data;
    } catch (e) {
        toast.error('Failed to load report');
        report.value = null;
    } finally {
        loading.value = false;
    }
};

const goToToday = () => {
    selectedDate.value = todayStr.value;
    loadReport();
};

const exportCsv = async () => {
    exporting.value = true;
    try {
        const response = await axios.get('/api/daily-activities/todays-report/export', {
            params: { date: selectedDate.value },
            responseType: 'blob',
        });
        const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8;' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `todays-report-${selectedDate.value}.csv`;
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
        toast.success('Download started');
    } catch (e) {
        toast.error('Export failed');
    } finally {
        exporting.value = false;
    }
};

const generateReport = async () => {
    generatingReport.value = true;
    generatedReport.value = null;
    try {
        const { data } = await axios.post('/api/daily-activities/generate-report', null, {
            params: { date: selectedDate.value },
        });
        generatedReport.value = data;
    } catch (e) {
        const msg = e.response?.data?.message || e.message || 'Failed to generate report';
        toast.error(msg);
    } finally {
        generatingReport.value = false;
    }
};

onMounted(() => {
    selectedDate.value = todayStr.value;
    loadReport();
});
</script>
