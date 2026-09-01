<template>
    <div class="page overflow-x-hidden box-border">
        <!--
            A greeting, not a second page title - the top bar already says
            "Dashboard". Demoted to h2 so the page keeps exactly one h1.
        -->
        <div class="min-w-0">
            <h2 class="text-page-title text-slate-900 break-words">
                Welcome back, {{ welcomeName }}
            </h2>
            <p class="page-lead mt-1">
                Here’s what’s happening with your pipeline today.
            </p>
        </div>

        <!--
            Above the date range on purpose. Everything below this point answers
            "what happened in a window", which on a quiet week is a screen of
            zeroes; this answers "what is rotting right now", which is the
            question an owner actually opens the app with.
        -->
        <NeedsAttention v-if="canUseOrgDashboardFilters" />

        <p v-if="isSelfDashboardScope" class="callout callout-info">
            You’re viewing <strong>your</strong> opportunities, customers, and activity. Admins and managers see the full organization on this screen.
        </p>

        <!-- Date range (+ org user filter): compact on mobile, full row on desktop -->
        <div class="card overflow-hidden">
            <button
                type="button"
                aria-controls="dashboard-filters-panel"
                :aria-expanded="filtersOpen ? 'true' : 'false'"
                class="md:hidden w-full min-h-12 flex items-center justify-between px-4 py-3 text-left text-slate-700 hover:bg-slate-50 transition-colors touch-manipulation active:bg-slate-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                @click="filtersOpen = !filtersOpen"
            >
                <span class="flex items-center gap-2">
                    <FunnelIcon class="icon text-slate-500" aria-hidden="true" />
                    <span class="font-medium text-sm">Date range</span>
                    <span v-if="activeFilterCount" class="badge-count">{{ activeFilterCount }}</span>
                </span>
                <ChevronDownIcon
                    class="icon text-slate-500 transition-transform"
                    :class="filtersOpen ? 'rotate-180' : ''"
                    aria-hidden="true"
                />
            </button>
            <div id="dashboard-filters-panel" :class="['md:block', filtersOpen ? 'block' : 'hidden']">
                <div class="p-4 pt-0 md:pt-4 border-t md:border-t-0 border-slate-200">
                    <p id="dashboard-quick-range-label" class="text-xs font-medium text-slate-500 mb-2">Quick range</p>
                    <div class="flex flex-wrap gap-2 mb-4" role="group" aria-labelledby="dashboard-quick-range-label">
                        <BaseButton
                            v-for="preset in DATE_PRESETS"
                            :key="preset.key"
                            :variant="dateRangePreset === preset.key ? 'primary' : 'outline'"
                            :aria-pressed="dateRangePreset === preset.key ? 'true' : 'false'"
                            @click="selectDatePreset(preset.key)"
                        >
                            {{ preset.label }}
                        </BaseButton>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 lg:gap-4 lg:items-end">
                        <div class="sm:col-span-1 lg:col-span-3">
                            <label class="form-label" for="dashboardview-from">From</label>
                            <input id="dashboardview-from"
                                v-model="filters.from"
                                type="date"
                                class="form-input"
                                @change="onDashboardDateManualChange"
                            />
                        </div>
                        <div class="sm:col-span-1 lg:col-span-3">
                            <label class="form-label" for="dashboardview-to">To</label>
                            <input id="dashboardview-to"
                                v-model="filters.to"
                                type="date"
                                class="form-input"
                                @change="onDashboardDateManualChange"
                            />
                        </div>
                        <div v-if="canUseOrgDashboardFilters" class="sm:col-span-1 lg:col-span-3">
                            <label class="form-label" for="dashboardview-user">User</label>
                            <select id="dashboardview-user"
                                v-model="filters.employee_id"
                                class="form-select"
                            >
                                <option value="">All users</option>
                                <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                                    {{ emp.name }}
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2 sm:col-span-2" :class="canUseOrgDashboardFilters ? 'lg:col-span-3' : 'lg:col-span-6'">
                            <BaseButton
                                variant="primary"
                                block-mobile
                                class="flex-1 order-2 sm:order-1"
                                :loading="loading"
                                @click="loadDashboard"
                            >
                                {{ loading ? 'Applying...' : 'Apply' }}
                            </BaseButton>
                            <BaseButton
                                variant="outline"
                                block-mobile
                                class="order-1 sm:order-2"
                                @click="resetFilters"
                            >
                                Reset
                            </BaseButton>
                        </div>
                    </div>
                    <div v-if="filters.from || filters.to || filters.employee_id" class="mt-3 pt-3 border-t border-slate-200 flex flex-wrap items-center gap-2">
                        <span class="text-xs text-slate-500">Active:</span>
                        <span v-if="filters.from" class="chip">From {{ formatFilterDate(filters.from) }}</span>
                        <span v-if="filters.to" class="chip">To {{ formatFilterDate(filters.to) }}</span>
                        <span v-if="filters.employee_id" class="chip">
                            {{ employees.find(e => e.id == filters.employee_id)?.name }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main KPIs: total leads, won products, active, win rate -->
        <div class="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <StatCard
                label="Total leads"
                :value="dashboardData.total_leads_all ?? 0"
                :caption="`All stages · ${filterPeriodLabel}`"
                tone="neutral"
                :to="totalLeadsRoute"
            >
                <template #icon>
                    <DocumentTextIcon class="icon" aria-hidden="true" />
                </template>
            </StatCard>

            <StatCard
                label="Won products"
                :value="dashboardData.won_product_units ?? 0"
                :caption="wonProductsCaption"
                tone="success"
                :to="wonLeadsRoute"
            >
                <template #icon>
                    <CheckCircleIcon class="icon" aria-hidden="true" />
                </template>
            </StatCard>

            <StatCard
                label="Active opportunities"
                :value="dashboardData.total_leads || 0"
                :caption="`Open stages · ${filterPeriodLabel}`"
                tone="primary"
                to="/leads/pipeline"
            >
                <template #icon>
                    <UsersIcon class="icon" aria-hidden="true" />
                </template>
            </StatCard>

            <StatCard
                label="Win rate"
                :value="`${dashboardData.conversion_rate || 0}%`"
                caption="Won / total"
                tone="warning"
            >
                <template #icon>
                    <ArrowTrendingUpIcon class="icon" aria-hidden="true" />
                </template>
            </StatCard>
        </div>

        <!-- Tickets by status (same date range and assignee as filters) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
            <StatCard
                label="Open tickets"
                :value="ticketStatusCounts.open"
                :caption="filterPeriodLabel"
                tone="warning"
                :to="{ path: '/tickets', query: ticketsListingQuery('open') }"
            >
                <template #icon>
                    <TicketIcon class="icon" aria-hidden="true" />
                </template>
            </StatCard>
            <StatCard
                label="Working"
                :value="ticketStatusCounts.in_progress"
                :caption="`In progress · ${filterPeriodLabel}`"
                tone="primary"
                :to="{ path: '/tickets', query: ticketsListingQuery('in_progress') }"
            >
                <template #icon>
                    <ClockIcon class="icon" aria-hidden="true" />
                </template>
            </StatCard>
            <StatCard
                label="Closed"
                :value="ticketStatusCounts.closed"
                :caption="filterPeriodLabel"
                tone="neutral"
                :to="{ path: '/tickets', query: ticketsListingQuery('closed') }"
            >
                <template #icon>
                    <CheckIcon class="icon" aria-hidden="true" />
                </template>
            </StatCard>
        </div>

        <!-- Performer of the month + their targets (always shown; calendar month from reporting API) -->
        <!--
            Plain card surface. This was an amber-to-green gradient with a
            warning-toned border, which used the "needs attention" ramp to mean
            "celebrate" and made the dashboard look unrelated to every other
            screen. The content carries the meaning instead.
        -->
        <section class="card min-w-0 p-4 sm:p-5 md:p-6">
            <template v-if="monthlyTopPerformer && performerHasActivity">
                <div class="flex min-w-0 flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex min-w-0 items-start gap-3 sm:items-center sm:gap-4">
                        <span class="stat-icon bg-primary-50 text-primary-700" aria-hidden="true">
                            <TrophyIcon class="icon" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h2 class="stat-label mb-1">
                                {{ isSelfDashboardScope ? 'Your performance this month' : 'Top performer of the month' }}
                            </h2>
                            <div class="text-lg font-bold text-slate-900 break-words sm:text-xl">{{ monthlyTopPerformer.name }}</div>
                            <div class="text-sm text-slate-600">
                                {{ monthlyTopPerformer.leads_count || 0 }} leads •
                                {{ monthlyTopPerformer.won_products || monthlyTopPerformer.won_count || 0 }} won
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 text-center w-full md:max-w-md md:ml-auto">
                        <div class="px-3 py-2 bg-white rounded-control border border-slate-200">
                            <div class="text-xs text-slate-500">Sales won</div>
                            <div class="text-sm font-bold text-slate-900 tabular-nums">{{ monthlyTopPerformer.won_products || monthlyTopPerformer.won_count || 0 }}</div>
                        </div>
                        <div class="px-3 py-2 bg-white rounded-control border border-slate-200">
                            <div class="text-xs text-slate-500">Leads worked</div>
                            <div class="text-sm font-bold text-slate-900 tabular-nums">{{ monthlyTopPerformer.leads_count || 0 }}</div>
                        </div>
                    </div>
                </div>
                <div class="mt-5 pt-5 border-t border-warning-200">
                    <h3 class="subsection-title">Their targets (this month)</h3>
                    <div v-if="performerMonthTarget" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="rounded-card bg-white/80 border border-slate-200 p-3">
                            <div class="text-xs text-slate-500">Appointments</div>
                            <div class="text-sm font-semibold text-slate-900 tabular-nums">
                                {{ performerMonthTarget.achieved_appointments }} / {{ performerMonthTarget.target_appointments || 0 }}
                            </div>
                            <div class="mt-2 h-1.5 bg-slate-200 rounded-full overflow-hidden" aria-hidden="true">
                                <div
                                    class="h-full rounded-full bg-success-600"
                                    :style="{ width: `${performerMonthTarget.appointment_progress}%` }"
                                />
                            </div>
                        </div>
                        <div class="rounded-card bg-white/80 border border-slate-200 p-3">
                            <div class="text-xs text-slate-500">Sales</div>
                            <div class="text-sm font-semibold text-slate-900 tabular-nums">
                                {{ performerMonthTarget.achieved_sales }} / {{ performerMonthTarget.target_sales || 0 }}
                            </div>
                        </div>
                        <div class="rounded-card bg-white/80 border border-slate-200 p-3">
                            <div class="text-xs text-slate-500">Appointments held</div>
                            <div class="text-sm font-semibold text-slate-900 tabular-nums">
                                {{ performerMonthTarget.achieved_appointments || 0 }}
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-slate-600 rounded-card bg-white/60 border border-slate-200 p-4">
                        <p class="font-medium text-slate-800">No formal targets set</p>
                        <p class="text-xs text-slate-500 mt-1">
                            Activity this month: {{ monthlyTopPerformer.appointments_count || 0 }} appointments logged.
                        </p>
                    </div>
                </div>
            </template>
            <EmptyState
                v-else
                :heading="isSelfDashboardScope ? 'Nothing recorded yet this month' : 'No results logged yet this month'"
                :description="
                    isSelfDashboardScope
                        ? 'Your leads, appointments and won sales for the month will appear here once you start logging them.'
                        : 'Once the team logs leads, appointments or won sales this month, the leader will appear here.'
                "
            >
                <template #icon>
                    <TrophyIcon class="icon" aria-hidden="true" />
                </template>
            </EmptyState>
        </section>

        <!-- Attendance Clock for non-admin -->
        <AttendanceClock />

        <!-- Attendance: hours per day (only users who checked in) -->
        <BaseCard
            title="Attendance by day"
            subtitle="Check-in / check-out times and hours worked. Only staff who recorded attendance in the range appear. Hover a bar for times."
        >
            <template #actions>
                <BaseButton
                    :variant="attendancePreset === '3d' ? 'primary' : 'outline'"
                    :aria-pressed="attendancePreset === '3d' ? 'true' : 'false'"
                    @click="attendancePreset = '3d'"
                >
                    Last 3 days
                </BaseButton>
                <BaseButton
                    :variant="attendancePreset === '7d' ? 'primary' : 'outline'"
                    :aria-pressed="attendancePreset === '7d' ? 'true' : 'false'"
                    @click="attendancePreset = '7d'"
                >
                    Last 7 days
                </BaseButton>
                <BaseButton
                    :variant="attendancePreset === 'month' ? 'primary' : 'outline'"
                    :aria-pressed="attendancePreset === 'month' ? 'true' : 'false'"
                    @click="attendancePreset = 'month'"
                >
                    This month
                </BaseButton>
            </template>

            <div :aria-busy="attendanceChartLoading ? 'true' : 'false'">
                <div
                    v-if="attendanceChartLoading"
                    class="flex items-center justify-center min-h-[200px] text-slate-500 text-sm"
                    role="status"
                    aria-live="polite"
                >
                    Loading chart…
                </div>
                <div v-else class="overflow-x-auto min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                    <!-- The canvas below is invisible to a screen reader; this states the same figures in words. -->
                    <p class="sr-only">{{ attendanceChartSummary }}</p>
                    <div :class="attendancePreset === 'month' ? 'min-w-[min(100%,720px)] sm:min-w-[860px]' : ''">
                        <AttendanceWorkHoursChart :payload="attendanceChartPayload" />
                    </div>
                </div>
            </div>
        </BaseCard>

        <!-- Today's Follow-ups & appointments -->
        <div class="space-y-6">
            <!-- Today's Follow-ups -->
            <BaseCard title="Today's Follow-ups">
                <template #actions>
                    <BaseBadge tone="primary">{{ todayFollowUps.length }} due today</BaseBadge>
                </template>

                <EmptyState v-if="todayFollowUps.length === 0" heading="No follow-ups scheduled for today">
                    <template #icon>
                        <CalendarDaysIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>
                <div v-else class="space-y-3 max-h-80 overflow-y-auto">
                    <div
                        v-for="followUp in todayFollowUps"
                        :key="followUp.id"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-3 bg-slate-50 rounded-card hover:bg-slate-100 transition-colors group"
                    >
                        <div class="flex-1 min-w-0">
                            <router-link
                                v-if="followUp.customer_id"
                                :to="`/customers/${followUp.customer_id}`"
                                class="block hover:text-primary-800"
                            >
                                <CustomerName :customer="followUp.customer" fallback="Customer" name-class="link break-words" />
                            </router-link>
                            <CustomerName v-else :customer="followUp.customer" fallback="Customer" />
                            <div class="text-xs text-slate-500">
                                {{ followUp.assignee?.name || 'Unassigned' }} •
                                {{ formatTime(followUp.next_follow_up_at) }}
                            </div>
                        </div>
                        <div class="flex w-full shrink-0 flex-wrap gap-2 sm:w-auto sm:justify-end">
                            <BaseButton
                                variant="success"
                                class="flex-1 sm:flex-none sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100 transition-opacity"
                                @click="openActivityModal(followUp)"
                            >
                                Log
                            </BaseButton>
                            <BaseButton
                                v-if="followUp.customer_id"
                                variant="primary"
                                class="flex-1 sm:flex-none"
                                :to="`/customers/${followUp.customer_id}`"
                            >
                                View
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </BaseCard>

            <!-- Today's Appointments -->
            <BaseCard title="Today's Appointments">
                <template #actions>
                    <BaseBadge tone="warning">{{ todayAppointments.length }} today</BaseBadge>
                </template>

                <EmptyState
                    v-if="todayAppointments.length === 0"
                    heading="No appointments for today"
                    description="Appointments are always shown for the current date. Add one from a customer or lead (Appointment tab)."
                >
                    <template #icon>
                        <CalendarDaysIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>
                <div v-else class="space-y-3 max-h-80 overflow-y-auto">
                    <div
                        v-for="apt in todayAppointments"
                        :key="apt.id"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-3 bg-warning-50 rounded-card hover:bg-warning-100 transition-colors group"
                    >
                        <div class="flex-1 min-w-0">
                            <router-link
                                v-if="apt.customer_id"
                                :to="`/customers/${apt.customer_id}`"
                                class="block hover:text-primary-800"
                            >
                                <CustomerName :customer="apt.customer" fallback="Customer" name-class="link break-words" />
                            </router-link>
                            <CustomerName v-else :customer="apt.customer" fallback="Customer" />
                            <div class="text-sm text-slate-600 mt-0.5">{{ apt.description || 'Appointment' }}</div>
                            <div class="text-xs text-slate-500 mt-1 tabular-nums">{{ apt.appointment_time || '10:00' }}</div>
                        </div>
                        <div class="flex w-full shrink-0 flex-wrap gap-2 sm:w-auto sm:justify-end">
                            <BaseButton
                                v-if="apt.lead_id"
                                variant="success"
                                class="flex-1 sm:flex-none sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100 transition-opacity"
                                @click="openCompleteForAppointment(apt)"
                            >
                                Complete
                            </BaseButton>
                            <BaseButton
                                v-if="apt.customer_id"
                                variant="primary"
                                class="flex-1 sm:flex-none"
                                :to="`/customers/${apt.customer_id}`"
                            >
                                View
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </BaseCard>
        </div>

        <!-- Log Activity Modal -->
        <LogActivityModal
            v-if="showActivityModal && activityLead"
            :lead="activityLead"
            @close="closeActivityModal"
            @saved="handleActivitySaved"
        />

        <!-- Complete Follow-up / Appointment Modal -->
        <BaseModal
            v-model="showCompleteModal"
            title="Complete Follow-up / Appointment"
            size="md"
            :close-on-backdrop="false"
            @close="closeCompleteModal"
        >
            <!-- No `novalidate`: the required Remarks field must still block submit, as before. -->
            <form id="dashboard-complete-followup-form" class="space-y-4" @submit.prevent="completeFollowUp">
                <div>
                    <label class="form-label" for="dashboardview-remarks-notes">
                        Remarks / Notes <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <textarea id="dashboardview-remarks-notes"
                        v-model="completeForm.remarks"
                        rows="4"
                        required
                        class="form-textarea"
                        placeholder="Enter your remarks..."
                    />
                </div>
                <div>
                    <label class="form-choice">
                        <input v-model="completeForm.saleHappened" type="checkbox" class="form-checkbox" />
                        <span>Sale won (counts as sale; prospect becomes customer)</span>
                    </label>
                </div>
                <div v-if="completeForm.saleHappened">
                    <label class="form-label" for="dashboardview-new-stage">New Stage</label>
                    <select id="dashboardview-new-stage" v-model="completeForm.newStage" class="form-select">
                        <option value="lead">Lead</option>
                        <option value="hot_lead">Hot Lead</option>
                        <option value="quotation">Quotation</option>
                        <option value="won">Won</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="dashboardview-next-follow-up-date-optional">Next Follow-up Date (Optional)</label>
                    <input id="dashboardview-next-follow-up-date-optional"
                        v-model="completeForm.nextFollowUpAt"
                        type="datetime-local"
                        class="form-input"
                    />
                </div>
            </form>

            <template #actions>
                <BaseButton variant="outline" block-mobile :disabled="completingFollowUp" @click="closeCompleteModal">
                    Cancel
                </BaseButton>
                <BaseButton
                    variant="success"
                    type="submit"
                    form="dashboard-complete-followup-form"
                    block-mobile
                    :loading="completingFollowUp"
                >
                    {{ completingFollowUp ? 'Saving...' : 'Complete' }}
                </BaseButton>
            </template>
        </BaseModal>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import axios from 'axios';
import {
    ArrowTrendingUpIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    CheckIcon,
    ChevronDownIcon,
    ClockIcon,
    DocumentTextIcon,
    FunnelIcon,
    TicketIcon,
    TrophyIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import {
    BaseBadge,
    BaseButton,
    BaseCard,
    BaseModal,
    EmptyState,
    StatCard,
} from '@/components/base';
import AttendanceClock from '@/components/AttendanceClock.vue';
import NeedsAttention from '@/components/NeedsAttention.vue';
import AttendanceWorkHoursChart from '@/components/AttendanceWorkHoursChart.vue';
import LogActivityModal from '@/components/LogActivityModal.vue';
import CustomerName from '@/components/CustomerName.vue';

const auth = useAuthStore();
const toast = useToastStore();
const user = computed(() => auth.user);
const isAdmin = computed(() => {
    const role = auth.user?.role?.name;
    return role === 'Admin' || role === 'System Admin';
});
const isManager = computed(() => auth.user?.role?.name === 'Manager');
/** Admin, System Admin, and Manager can filter the org dashboard; everyone else is self-scoped on the API. */
const canUseOrgDashboardFilters = computed(() => isAdmin.value || isManager.value);

const dashboardMeta = ref({});

const welcomeName = computed(() => {
    const n = user.value?.name?.trim();
    if (!n) return 'there';
    return n.split(/\s+/)[0];
});

const isSelfDashboardScope = computed(() => dashboardMeta.value.viewer_scope === 'self');

const loading = ref(false);
const dashboardData = ref({});

const attendancePreset = ref('3d');
const attendanceChartPayload = ref(null);
const attendanceChartLoading = ref(false);

const todayFollowUps = ref([]);
const todayAppointments = ref([]);
const monthlyTopPerformer = ref(null);
const ticketStatusCounts = ref({ open: 0, in_progress: 0, closed: 0 });
const showActivityModal = ref(false);
const activityLead = ref(null);
const showCompleteModal = ref(false);
const completingFollowUp = ref(false);
const selectedFollowUp = ref(null);
const completeForm = ref({
    remarks: '',
    saleHappened: false,
    newStage: 'won',
    nextFollowUpAt: '',
});
const employees = ref([]);
const employeeTargets = ref([]);

/**
 * A performer record exists as soon as there is an active sales user, even
 * when nothing has happened yet. Announcing a "Top performer of the month"
 * with 0 leads, 0 won and £0 celebrates nothing and reads as broken, so the
 * section only claims a winner once there is something to have won.
 */
const performerHasActivity = computed(() => {
    const p = monthlyTopPerformer.value;
    if (!p) return false;
    return [
        p.leads_count,
        p.won_products ?? p.won_count,
        p.appointments_count,
    ].some((v) => Number(v) > 0);
});

const performerMonthTarget = computed(() => {
    const p = monthlyTopPerformer.value;
    if (!p) return null;
    const id = Number(p.id);
    return employeeTargets.value.find((t) => Number(t.user_id) === id) || null;
});

// Date range defaults to last 7 days on mount; from/to are always sent to /api/dashboard.
const filters = ref({
    from: '',
    to: '',
    employee_id: ''
});
const filtersOpen = ref(false);
/** Tracks which quick preset is selected, or 'custom' after manual date edits. */
const dateRangePreset = ref('7d');

const DATE_PRESETS = [
    { key: 'today', label: 'Today' },
    { key: '2d', label: 'Last 2 days' },
    { key: '7d', label: 'Last 7 days' },
    { key: 'last_week', label: 'Last week' },
    { key: 'week', label: 'This week' },
    { key: 'month', label: 'This month' },
    { key: '30d', label: 'Last 30 days' },
];

const activeFilterCount = computed(() => {
    let n = 0;
    if (dateRangePreset.value !== '7d') n++;
    if (canUseOrgDashboardFilters.value && filters.value.employee_id) n++;
    return n;
});

const formatFilterDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

const filterPeriodLabel = computed(() => {
    const parts = [];
    if (filters.value.from && filters.value.to) {
        const from = new Date(filters.value.from + 'T12:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
        const to = new Date(filters.value.to + 'T12:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
        parts.push(`${from} - ${to}`);
    } else if (filters.value.from) {
        parts.push(`From ${new Date(filters.value.from + 'T12:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}`);
    } else if (filters.value.to) {
        parts.push(`Until ${new Date(filters.value.to + 'T12:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}`);
    }
    if (canUseOrgDashboardFilters.value && filters.value.employee_id) {
        const emp = employees.value.find((e) => e.id == filters.value.employee_id);
        if (emp) parts.push(emp.name);
    }
    return parts.length > 0 ? parts.join(' • ') : 'This month';
});

/** "Units on won line items" plus the line count when the API reported one. */
const wonProductsCaption = computed(() => {
    const lines = dashboardData.value.won_product_lines || 0;
    return lines > 0
        ? `Units on won line items · ${lines} line(s)`
        : 'Units on won line items';
});

/**
 * The attendance chart is a <canvas>, which exposes nothing to assistive tech.
 * This sentence restates the same figures the chart is drawing - no extra requests.
 */
const attendanceChartSummary = computed(() => {
    const payload = attendanceChartPayload.value;
    const users = payload?.users || [];
    if (!users.length) {
        return 'Attendance by day: no one recorded attendance in this period, so the chart is empty.';
    }
    const dayCount = (payload.label_display || []).length;
    const perUser = users.map((u) => {
        const hours = (u.hours || [])
            .filter((h) => h !== null && h !== undefined && h !== '')
            .map((h) => num(h));
        const total = Math.round(hours.reduce((sum, h) => sum + h, 0) * 10) / 10;
        return `${u.name}: ${total} hours across ${hours.length} ${hours.length === 1 ? 'day' : 'days'}`;
    });
    return `Bar chart of hours worked per person over ${dayCount} ${dayCount === 1 ? 'day' : 'days'}. ${perUser.join('. ')}.`;
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num || 0);
};

const formatTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};

const loadEmployees = async () => {
    try {
        const response = await axios.get('/api/users');
        employees.value = response.data || [];
    } catch (error) {
        console.error('Failed to load employees:', error);
    }
};

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

/** Sets filters.from / filters.to only (no request). Same calendar rules as the leads list. */
function applyDatePresetRanges(kind) {
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
    } else if (kind === '7d') {
        const end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const start = new Date(end);
        start.setDate(start.getDate() - 6);
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
}

function onDashboardDateManualChange() {
    dateRangePreset.value = 'custom';
}

function selectDatePreset(kind) {
    applyDatePresetRanges(kind);
    dateRangePreset.value = kind;
    loadDashboard();
}

const resetFilters = () => {
    filters.value.employee_id = '';
    applyDatePresetRanges('7d');
    dateRangePreset.value = '7d';
    loadDashboard();
};

/** Safe numeric parse for API values (strings, decimals, commas). */
function num(v) {
    if (v === null || v === undefined || v === '') return 0;
    if (typeof v === 'number') return Number.isFinite(v) ? v : 0;
    const n = parseFloat(String(v).replace(/,/g, ''));
    return Number.isFinite(n) ? n : 0;
}

/** Reporting /agents may be a bare array or wrapped in { data: [...] }. */
function normalizeAgentsList(payload) {
    if (Array.isArray(payload)) return payload;
    if (payload && Array.isArray(payload.data)) return payload.data;
    return [];
}

const loadAttendanceChart = async () => {
    attendanceChartLoading.value = true;
    try {
        const { data } = await axios.get('/api/hr/attendance/chart-summary', {
            params: { preset: attendancePreset.value },
        });
        attendanceChartPayload.value = data;
    } catch (e) {
        console.error('Failed to load attendance chart:', e);
        attendanceChartPayload.value = null;
    } finally {
        attendanceChartLoading.value = false;
    }
};

watch(attendancePreset, () => {
    loadAttendanceChart();
});

const loadDashboard = async () => {
    loading.value = true;
    try {
        // Build query params for main dashboard APIs (can be date-filtered)
        const params = {};
        if (filters.value.from) params.from = filters.value.from;
        if (filters.value.to) params.to = filters.value.to;
        if (filters.value.employee_id) params.agent_id = filters.value.employee_id;

        // Targets/achievement widgets should always use the current month (local), not the date filter
        const now = new Date();
        const currentMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        const agentTargetParams = { month: currentMonth };
        if (filters.value.employee_id) agentTargetParams.agent_id = filters.value.employee_id;

        const ticketParams = {};
        if (filters.value.from) ticketParams.from = filters.value.from;
        if (filters.value.to) ticketParams.to = filters.value.to;
        if (filters.value.employee_id) ticketParams.assigned_to = filters.value.employee_id;

        const [dashboardRes, ticketsOpenRes, ticketsWorkingRes, ticketsClosedRes, agentsRes, targetsRes] = await Promise.all([
            axios.get('/api/dashboard', { params }),
            axios.get('/api/tickets', { params: { ...ticketParams, status: 'open', per_page: 1 } }),
            axios.get('/api/tickets', { params: { ...ticketParams, status: 'in_progress', per_page: 1 } }),
            axios.get('/api/tickets', { params: { ...ticketParams, status: 'closed', per_page: 1 } }),
            axios.get('/api/reporting/agents', { params: agentTargetParams }),
            axios.get('/api/hr/employee-targets', { params: { month: currentMonth } }),
        ]);

        const data = dashboardRes.data;

        dashboardMeta.value = data.meta || {};

        // Backend always returns stats for the requested from/to (defaults to month if omitted).
        const statsSource = data.stats?.filtered ?? data.stats?.monthly;
        const totalOpportunities =
            (statsSource && typeof statsSource.total_opportunities === 'number')
                ? statsSource.total_opportunities
                // Fallback for older API responses: approximate using active leads + won
                : ((statsSource?.leads || 0) + (statsSource?.won || 0));

        // Main dashboard data
        dashboardData.value = {
            total_leads: statsSource?.leads || 0,
            total_leads_all: totalOpportunities,
            won_product_units: statsSource?.won_product_units ?? 0,
            won_product_lines: statsSource?.won_product_lines ?? 0,
            conversion_rate: totalOpportunities > 0
                ? Math.min(100, Math.round(((statsSource?.won || 0) / totalOpportunities) * 100))
                : 0,
            // Also store monthly for comparison
            monthly: data.stats?.monthly || {},
            yearly: data.stats?.yearly || {},
            daily: data.stats?.daily || {},
        };

        // Today's follow-ups
        todayFollowUps.value = data.today_follow_ups || [];
        todayAppointments.value = data.today_appointments || [];

        const agents = normalizeAgentsList(agentsRes.data);

        // One ordering, used for the spotlight and its fallback: most won lines,
        // then the size of the book they worked to get them.
        const byWonThenLeads = (a, b) => {
            const aWon = Math.max(num(a.won_products), num(a.won_count), num(a.won_leads));
            const bWon = Math.max(num(b.won_products), num(b.won_count), num(b.won_leads));

            if (bWon !== aWon) return bWon - aWon;

            return num(b.leads_count) - num(a.leads_count);
        };

        const performanceCandidates = agents
            .filter((a) =>
                num(a.leads_count) > 0 ||
                num(a.won_count) > 0 ||
                num(a.won_products) > 0 ||
                num(a.won_leads) > 0
            )
            // Revenue used to sit in here as a tiebreak. It is £0 for everybody,
            // so it never broke a tie - it only made the order look considered.
            .sort(byWonThenLeads);
        let spotlight = performanceCandidates[0] || null;
        if (!spotlight && agents.length === 1) {
            spotlight = agents[0];
        }
        if (!spotlight && agents.length > 0) {
            spotlight = [...agents].sort(byWonThenLeads)[0];
        }
        monthlyTopPerformer.value = spotlight;

        const openTotal = ticketsOpenRes.data?.total ?? ticketsOpenRes.data?.data?.length ?? 0;
        const workingTotal = ticketsWorkingRes.data?.total ?? ticketsWorkingRes.data?.data?.length ?? 0;
        const closedTotal = ticketsClosedRes.data?.total ?? ticketsClosedRes.data?.data?.length ?? 0;
        ticketStatusCounts.value = {
            open: openTotal,
            in_progress: workingTotal,
            closed: closedTotal,
        };

        // Employee targets / achievement board (current month)
        // Only include: (1) users with at least one non-zero target for this month, or
        // (2) users with real activity (appointments / wins). Do not list every sales agent.
        const targetsRaw = targetsRes.data?.data || [];
        const targetsByUser = {};

        for (const t of targetsRaw) {
            const lines = t.lines || [];
            const tsFromLines = lines.length
                ? lines.reduce((s, l) => s + num(l.target_quantity), 0)
                : 0;
            const tp = num(t.target_appointments);
            const ts = lines.length ? tsFromLines : num(t.target_sales);
            // A revenue target is deliberately never achieved against, so a row
            // that has only that would sit at 0% forever and read as failure.
            if (tp === 0 && ts === 0) {
                continue;
            }
            const achievedFromLines = lines.length
                ? lines.reduce((s, l) => s + num(l.achieved_quantity), 0)
                : 0;
            targetsByUser[t.user_id] = {
                user_id: t.user_id,
                user: t.user,
                lines,
                target_appointments: tp,
                target_sales: ts,
                achieved_appointments: 0,
                achieved_sales: achievedFromLines,
            };
        }

        const agentHasMeasurableActivity = (ag) =>
            num(ag.appointments_count) > 0 ||
            num(ag.won_products) > 0 ||
            num(ag.won_count) > 0 ||
            num(ag.won_leads) > 0;

        for (const ag of agents) {
            const id = ag.id;
            const existing = targetsByUser[id];
            if (!existing && !agentHasMeasurableActivity(ag)) {
                continue;
            }
            const row =
                existing || {
                    user_id: id,
                    user: { id: ag.id, name: ag.name },
                    lines: [],
                    target_appointments: 0,
                    target_sales: 0,
                    achieved_appointments: 0,
                    achieved_sales: 0,
                };
            row.achieved_appointments = num(ag.appointments_count);
            if (row.lines?.length) {
                row.achieved_sales = row.lines.reduce((s, l) => s + num(l.achieved_quantity), 0);
            } else {
                row.achieved_sales = num(ag.won_products) || num(ag.won_count);
            }
            targetsByUser[id] = row;
        }

        employeeTargets.value = Object.values(targetsByUser)
            .map((t) => {
                const denom = num(t.target_appointments);
                const achievedAppt = num(t.achieved_appointments);
                const progress = denom > 0 ? Math.min(100, Math.round((achievedAppt / denom) * 100)) : 0;
                return {
                    ...t,
                    appointment_progress: progress,
                };
            })
            .filter((t) => {
                const hasTargets =
                    num(t.target_appointments) > 0 ||
                    num(t.target_sales) > 0;
                const hasAchievement =
                    num(t.achieved_appointments) > 0 ||
                    num(t.achieved_sales) > 0;
                return hasTargets || hasAchievement;
            })
            .sort((a, b) => num(b.achieved_appointments) - num(a.achieved_appointments));

    } catch (error) {
        console.error('Failed to load dashboard:', error);
    } finally {
        loading.value = false;
    }
};

const openCompleteForAppointment = (apt) => {
    if (!apt?.lead_id) return;
    selectedFollowUp.value = { id: apt.lead_id };
    completeForm.value = { remarks: '', saleHappened: false, newStage: 'won', nextFollowUpAt: '' };
    showCompleteModal.value = true;
};

const closeCompleteModal = () => {
    showCompleteModal.value = false;
    selectedFollowUp.value = null;
};

const completeFollowUp = async () => {
    if (!selectedFollowUp.value?.id || completingFollowUp.value) return;
    completingFollowUp.value = true;
    try {
        const payload = {
            remarks: completeForm.value.remarks,
            sale_happened: completeForm.value.saleHappened,
            new_stage: completeForm.value.saleHappened ? completeForm.value.newStage : null,
        };
        if (completeForm.value.nextFollowUpAt) payload.next_follow_up_at = completeForm.value.nextFollowUpAt;
        const saleWon = completeForm.value.saleHappened && completeForm.value.newStage === 'won';
        await axios.post(`/api/leads/${selectedFollowUp.value.id}/complete-followup`, payload);
        closeCompleteModal();
        loadDashboard();
        toast.success(saleWon ? 'Appointment completed. Sale counted; prospect is now a customer.' : 'Follow-up / appointment completed.');
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to complete');
    } finally {
        completingFollowUp.value = false;
    }
};

const openActivityModal = (lead) => {
    activityLead.value = lead;
    showActivityModal.value = true;
};

const closeActivityModal = () => {
    showActivityModal.value = false;
    activityLead.value = null;
};

const handleActivitySaved = () => {
    loadDashboard();
    closeActivityModal();
};

function ticketsListingQuery(status) {
    const q = { status };
    if (filters.value.from) q.from = filters.value.from;
    if (filters.value.to) q.to = filters.value.to;
    if (filters.value.employee_id) q.assigned_to = String(filters.value.employee_id);
    return q;
}

// Summary tiles link into the detailed lists, so they are real links (keyboard + middle-click).
const totalLeadsRoute = computed(() => {
    // All leads in the selected period (any stage)
    const query = {};
    if (filters.value.from) query.from = filters.value.from;
    if (filters.value.to) query.to = filters.value.to;
    if (filters.value.employee_id) query.assigned_to = filters.value.employee_id;
    return { path: '/leads', query };
});

const wonLeadsRoute = computed(() => {
    // For stage-specific views we show all leads in that stage,
    // but still allow narrowing by employee if selected.
    const query = { stage: 'won' };
    if (filters.value.employee_id) query.assigned_to = filters.value.employee_id;
    return { path: '/leads', query };
});

onMounted(async () => {
    if (!auth.initialized) {
        await auth.bootstrap();
    }
    applyDatePresetRanges('7d');
    dateRangePreset.value = '7d';
    if (canUseOrgDashboardFilters.value) {
        loadEmployees();
    }
    await loadDashboard();
    loadAttendanceChart();
});
</script>
