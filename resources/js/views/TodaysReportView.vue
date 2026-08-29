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
            <div>
                <div class="text-sm text-slate-600 font-medium">{{ report.date_label }}</div>
                <p class="text-xs text-slate-500 mt-1">
                    Active users across Sales, Support, Admin, Manager, and related roles. Manual lines from
                    <router-link to="/today-activity" class="link">Today's Activity</router-link>
                    appear in the timeline as “Manual activity”. On small screens, scroll the summary table sideways.
                </p>
            </div>

            <div v-if="report.report.length === 0" class="card">
                <EmptyState
                    heading="No active team members match this report"
                    description="Pick a different date, or check that team members are marked active."
                >
                    <template #icon><UsersIcon class="icon" aria-hidden="true" /></template>
                </EmptyState>
            </div>

            <template v-else>
                <!-- Summary table: all users × metrics -->
                <div class="card overflow-hidden min-w-0">
                    <div
                        class="table-wrap overscroll-x-contain scroll-smooth"
                        style="-webkit-overflow-scrolling: touch"
                        role="region"
                        aria-label="Daily summary by team member"
                    >
                        <table class="table text-sm min-w-[920px]">
                            <caption class="sr-only">Daily activity totals for each team member</caption>
                            <thead>
                                <tr class="bg-slate-100 text-slate-700 border-b border-slate-200">
                                    <th
                                        scope="col"
                                        class="sticky left-0 z-sticky bg-slate-100 px-3 py-3 font-semibold border-r border-slate-200 whitespace-nowrap shadow-[4px_0_8px_-4px_rgba(15,23,42,0.15)]"
                                    >
                                        Team member
                                    </th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap hidden sm:table-cell">Role</th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap" title="Follow-ups due today">
                                        <span class="sm:hidden">FU</span>
                                        <span class="hidden sm:inline">Follow-ups</span>
                                    </th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap" title="Prospects added">Prospects</th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap" title="New customers">Customers</th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap" title="Leads you created">
                                        <span class="md:hidden">L+you</span>
                                        <span class="hidden md:inline">Leads (you)</span>
                                    </th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap" title="New leads assigned to you">
                                        <span class="md:hidden">L→</span>
                                        <span class="hidden md:inline">Leads (asgn)</span>
                                    </th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap text-success-800 bg-success-50/80">Won</th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap" title="Tickets resolved">T✓</th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap" title="Tickets created">T+</th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap" title="CRM activities">CRM</th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap" title="Appointments logged">Appt</th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap" title="Daily logs">Logs</th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap hidden lg:table-cell">In</th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap hidden lg:table-cell">Out</th>
                                    <th scope="col" class="px-2 py-3 font-semibold text-center whitespace-nowrap hidden xl:table-cell">Hrs</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(user, i) in report.report"
                                    :key="user.user_id"
                                    class="border-b border-slate-100 last:border-0"
                                    :class="i % 2 === 1 ? 'bg-slate-50/90' : 'bg-white'"
                                >
                                    <th
                                        scope="row"
                                        :class="[
                                            'sticky left-0 z-sticky px-3 py-2.5 text-left font-medium border-r border-slate-200 align-top shadow-[4px_0_8px_-4px_rgba(15,23,42,0.12)]',
                                            i % 2 === 1 ? 'bg-slate-50' : 'bg-white',
                                        ]"
                                    >
                                        <div class="text-slate-900">{{ user.user_name }}</div>
                                        <div class="text-xs font-normal text-slate-500 sm:hidden">{{ user.role }}</div>
                                    </th>
                                    <td class="px-2 py-2.5 text-center text-slate-600 hidden sm:table-cell align-top">{{ user.role }}</td>
                                    <td class="px-2 py-2.5 text-center tabular-nums text-slate-900 align-top">{{ countVal(user, 'follow_ups_due', 'follow_ups_count') }}</td>
                                    <td class="px-2 py-2.5 text-center tabular-nums align-top">{{ countVal(user, 'prospects_added') }}</td>
                                    <td class="px-2 py-2.5 text-center tabular-nums align-top">{{ countVal(user, 'customers_added') }}</td>
                                    <td class="px-2 py-2.5 text-center tabular-nums align-top">{{ countVal(user, 'leads_added_by_user', 'leads_added_by_user_count') }}</td>
                                    <td class="px-2 py-2.5 text-center tabular-nums align-top">{{ countVal(user, 'leads_assigned_new_today', 'leads_created_count') }}</td>
                                    <td class="px-2 py-2.5 text-center tabular-nums font-medium text-success-800 bg-success-50/50 align-top">{{ countVal(user, 'won_sales', 'won_sales_count') }}</td>
                                    <td class="px-2 py-2.5 text-center tabular-nums align-top">{{ countVal(user, 'tickets_resolved') }}</td>
                                    <td class="px-2 py-2.5 text-center tabular-nums align-top">{{ countVal(user, 'tickets_created') }}</td>
                                    <td class="px-2 py-2.5 text-center tabular-nums align-top">{{ countVal(user, 'crm_activities') }}</td>
                                    <td class="px-2 py-2.5 text-center tabular-nums align-top">{{ countVal(user, 'appointments_logged') }}</td>
                                    <td class="px-2 py-2.5 text-center tabular-nums align-top">{{ countVal(user, 'daily_logs', 'activities_count') }}</td>
                                    <td class="px-2 py-2.5 text-center text-xs text-slate-600 whitespace-nowrap hidden lg:table-cell align-top">{{ user.attendance?.check_in || '—' }}</td>
                                    <td class="px-2 py-2.5 text-center text-xs text-slate-600 whitespace-nowrap hidden lg:table-cell align-top">{{ user.attendance?.check_out || '—' }}</td>
                                    <td class="px-2 py-2.5 text-center text-xs tabular-nums hidden xl:table-cell align-top">{{ user.attendance?.work_hours ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="px-3 py-2 text-[11px] text-slate-500 border-t border-slate-100 md:hidden">← Swipe horizontally to see all columns →</p>
                </div>

                <!-- Per-user detail: timeline + lists -->
                <div class="space-y-3">
                    <h2 class="text-base font-semibold text-slate-800 pt-2">Details by person</h2>
                    <div
                        v-for="user in report.report"
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
                                <div v-else class="table-wrap border-t border-slate-100">
                                    <table class="table text-sm min-w-[520px]">
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
                                        <span v-if="w.total_price != null" class="text-success-700 font-medium">£{{ w.total_price }}</span>
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
            </template>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { ArrowDownTrayIcon, BoltIcon, UsersIcon } from '@heroicons/vue/24/outline';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseBadge, BaseButton, EmptyState } from '@/components/base';
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
