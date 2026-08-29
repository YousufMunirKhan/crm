<template>
    <div class="max-w-7xl mx-auto w-full min-w-0 p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
        <div class="min-w-0">
            <h1 class="text-page-title text-slate-900 break-words">My dashboard</h1>
            <p class="text-xs text-slate-500 mt-1.5 sm:text-sm leading-relaxed">
                Your follow-ups, appointments and pipeline.
            </p>
        </div>

        <!-- Attendance & Stats Row (no Revenue) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
            <div class="min-w-0">
                <AttendanceClock />
            </div>
            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 min-w-0">
                <StatCard
                    label="Today's Active Leads"
                    :value="stats.daily?.leads || 0"
                    :caption="`Won: ${stats.daily?.won || 0}`"
                    tone="primary"
                >
                    <template #icon>
                        <CalendarDaysIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard
                    label="Monthly Active Leads"
                    :value="stats.monthly?.leads || 0"
                    :caption="`Won: ${stats.monthly?.won || 0}`"
                    tone="primary"
                >
                    <template #icon>
                        <ChartBarIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard
                    label="Yearly Active Leads"
                    :value="stats.yearly?.leads || 0"
                    :caption="`Won: ${stats.yearly?.won || 0}`"
                    tone="primary"
                >
                    <template #icon>
                        <ArrowTrendingUpIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
            </div>
        </div>

        <!-- My Targets (if admin has set them for this month) -->
        <section
            v-if="myTarget"
            class="card bg-gradient-to-r from-primary-50 via-white to-success-50 border-primary-200 px-4 py-4 md:px-6 md:py-5 flex flex-col gap-4"
        >
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 shrink-0">
                        <ViewfinderCircleIcon class="icon" aria-hidden="true" />
                    </span>
                    <div>
                        <h2 class="text-sm md:text-base font-semibold text-slate-900">My Targets (this month)</h2>
                        <p class="text-[11px] md:text-xs text-slate-500">
                            Set by your admin. Updated automatically from your appointments and won sales.
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 sm:gap-3">
                    <span class="chip tabular-nums">
                        <span class="inline-block w-2 h-2 rounded-full bg-success-600" aria-hidden="true"></span>
                        {{ myTarget.achieved_appointments }} / {{ myTarget.target_appointments || 0 }} appointments
                    </span>
                    <span class="chip tabular-nums">
                        <span class="inline-block w-2 h-2 rounded-full bg-primary-600" aria-hidden="true"></span>
                        {{ myTarget.achieved_sales }} / {{ myTarget.target_sales || 0 }} sales
                    </span>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between items-center text-xs text-slate-500">
                    <span>Overall progress</span>
                    <span class="font-medium text-slate-700 tabular-nums">{{ myTarget.overall_progress }}%</span>
                </div>
                <div class="w-full bg-slate-200/80 rounded-full h-2.5 overflow-hidden" aria-hidden="true">
                    <div
                        class="h-2.5 rounded-full bg-primary-600 transition-all duration-500"
                        :style="{ width: `${Math.min(100, myTarget.overall_progress || 0)}%` }"
                    ></div>
                </div>
                <p v-if="myTarget.target_revenue > 0" class="text-[11px] text-slate-500 tabular-nums">
                    Revenue: {{ formatNumber(myTarget.achieved_revenue) }} / {{ formatNumber(myTarget.target_revenue) }}
                    ({{ myTarget.revenue_progress }}%)
                </p>
            </div>
        </section>

        <!-- Monthly #1 Spotlight (all users) -->
        <section
            v-if="monthlyTopPerformer"
            class="card bg-gradient-to-r from-warning-50 via-white to-success-50 border-warning-200 px-4 py-4 md:px-6 md:py-5 min-w-0"
        >
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 min-w-0">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-warning-500 text-white flex items-center justify-center font-bold shrink-0" aria-hidden="true">1</span>
                    <div>
                        <h2 class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-warning-100 text-warning-800 text-xs font-semibold mb-1">
                            <TrophyIcon class="icon-sm" aria-hidden="true" />
                            Top Performer of the Month
                        </h2>
                        <div class="text-lg font-bold text-slate-900">{{ monthlyTopPerformer.name }}</div>
                        <div class="text-xs text-slate-600 tabular-nums">
                            {{ monthlyTopPerformer.leads_count || 0 }} leads •
                            {{ monthlyTopPerformer.won_products || monthlyTopPerformer.won_count || 0 }} won
                        </div>
                    </div>
                </div>
                <div class="text-left sm:text-right text-sm w-full sm:w-auto shrink-0">
                    <div class="font-bold text-success-700 tabular-nums break-words">£{{ formatNumber(monthlyTopPerformer.revenue || 0) }}</div>
                    <div class="text-xs text-slate-500 tabular-nums">{{ monthlyTopPerformer.conversion_rate || 0 }}% conv.</div>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <div class="flex flex-col sm:flex-row flex-wrap gap-2 sm:gap-3">
            <BaseButton variant="primary" block-mobile :to="{ path: '/customers', query: { type: 'prospect' } }">
                <template #icon>
                    <UserPlusIcon class="icon" aria-hidden="true" />
                </template>
                Add Customer
            </BaseButton>
            <BaseButton variant="success" block-mobile to="/leads/pipeline">
                <template #icon>
                    <PlusCircleIcon class="icon" aria-hidden="true" />
                </template>
                Add Lead
            </BaseButton>
        </div>

        <!-- Follow-ups & Appointments Section -->
        <BaseCard>
            <template #header>
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 min-w-0 w-full">
                    <label class="text-sm font-medium text-slate-700 shrink-0" for="salesagentdashboard-view-date">View date:</label>
                    <input id="salesagentdashboard-view-date"
                        v-model="selectedDate"
                        type="date"
                        class="form-input w-full sm:w-auto min-w-0 max-w-full"
                        @change="onDateChange"
                    />
                    <BaseButton
                        v-if="selectedDate !== todayStr"
                        variant="ghost"
                        class="shrink-0"
                        @click="resetDate"
                    >
                        Today
                    </BaseButton>
                </div>
            </template>
            <template #actions>
                <div class="tab-list" role="group" aria-label="Follow-up range">
                    <button
                        type="button"
                        :class="['tab', activeTab === 'today' ? 'tab-active' : '']"
                        :aria-pressed="activeTab === 'today' ? 'true' : 'false'"
                        @click="activeTab = 'today'"
                    >
                        Today
                    </button>
                    <button
                        type="button"
                        :class="['tab', activeTab === 'next7' ? 'tab-active' : '']"
                        :aria-pressed="activeTab === 'next7' ? 'true' : 'false'"
                        @click="activeTab = 'next7'"
                    >
                        Next 7 Days
                    </button>
                </div>
            </template>

            <!-- Today / Selected Date Follow-ups -->
            <div v-show="activeTab === 'today'" class="space-y-6">
                <section>
                    <h2 class="text-base font-semibold text-slate-900 mb-3 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-control bg-primary-100 text-primary-700 flex items-center justify-center shrink-0">
                            <CalendarDaysIcon class="icon" aria-hidden="true" />
                        </span>
                        {{ selectedDate === todayStr ? "Today's Follow-ups" : `Follow-ups for ${formatSelectedDateLabel(selectedDate)}` }}
                    </h2>
                    <p v-if="loading" class="text-center py-8 text-slate-500" role="status" aria-live="polite">Loading...</p>
                    <EmptyState
                        v-else-if="displayFollowUpsToday.length === 0"
                        :heading="selectedDate === todayStr ? 'No follow-ups scheduled for today' : 'No follow-ups for this date'"
                    >
                        <template #icon>
                            <CalendarDaysIcon class="icon" aria-hidden="true" />
                        </template>
                    </EmptyState>
                    <div v-else class="space-y-3">
                        <div
                            v-for="fu in displayFollowUpsToday"
                            :key="fu.id"
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-4 bg-slate-50 rounded-card hover:bg-slate-100 transition-colors group min-w-0"
                        >
                            <div class="flex-1 min-w-0">
                                <router-link
                                    v-if="fu.customer_id"
                                    :to="`/customers/${fu.customer_id}`"
                                    class="link break-words"
                                >
                                    {{ fu.customer?.name || 'Customer' }}
                                </router-link>
                                <div v-else class="font-medium text-slate-900">
                                    {{ fu.customer?.name || 'Customer' }}
                                </div>
                                <div class="text-sm text-slate-600 mt-0.5">
                                    {{ fu.items?.length ? fu.items.map(i => i.product?.name).filter(Boolean).join(', ') : '-' }}
                                </div>
                                <div class="text-xs text-slate-500 mt-1 tabular-nums">{{ formatDateTime(fu.next_follow_up_at) }}</div>
                            </div>
                            <div class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                                <BaseButton variant="outline" @click="openActivityModal(fu)">Log Activity</BaseButton>
                                <BaseButton variant="success" @click="openCompleteModal(fu)">Mark as Done</BaseButton>
                                <BaseButton
                                    v-if="fu.customer_id"
                                    variant="primary"
                                    :to="`/customers/${fu.customer_id}`"
                                >
                                    View
                                </BaseButton>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Today's Appointments -->
                <section>
                    <h2 class="text-base font-semibold text-slate-900 mb-3 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-control bg-warning-100 text-warning-800 flex items-center justify-center shrink-0">
                            <ClockIcon class="icon" aria-hidden="true" />
                        </span>
                        Today's Appointments
                    </h2>
                    <EmptyState v-if="todayAppointments.length === 0" heading="No appointments scheduled for today">
                        <template #icon>
                            <ClockIcon class="icon" aria-hidden="true" />
                        </template>
                    </EmptyState>
                    <div v-else class="space-y-3">
                        <div
                            v-for="apt in todayAppointments"
                            :key="apt.id"
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-4 bg-warning-50 rounded-card hover:bg-warning-100 transition-colors min-w-0"
                        >
                            <div class="flex-1 min-w-0">
                                <router-link
                                    v-if="apt.customer_id"
                                    :to="`/customers/${apt.customer_id}`"
                                    class="link break-words"
                                >
                                    {{ apt.customer?.name || 'Customer' }}
                                </router-link>
                                <div v-else class="font-medium text-slate-900">
                                    {{ apt.customer?.name || 'Customer' }}
                                </div>
                                <div class="text-sm text-slate-600 mt-0.5">{{ apt.description || 'Appointment' }}</div>
                                <div class="text-xs text-slate-500 mt-1 tabular-nums">{{ apt.appointment_time || '10:00' }}</div>
                            </div>
                            <div class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                                <BaseButton
                                    v-if="apt.lead_id"
                                    variant="success"
                                    @click="openCompleteModal({ id: apt.lead_id })"
                                >
                                    Complete
                                </BaseButton>
                                <BaseButton
                                    v-if="apt.customer_id"
                                    variant="primary"
                                    :to="`/customers/${apt.customer_id}`"
                                >
                                    View
                                </BaseButton>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Next 7 Days Follow-ups -->
            <div v-show="activeTab === 'next7'" class="space-y-4">
                <h2 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-control bg-success-100 text-success-800 flex items-center justify-center shrink-0">
                        <ListBulletIcon class="icon" aria-hidden="true" />
                    </span>
                    Follow-ups (Next 7 Days)
                </h2>
                <EmptyState v-if="next7DaysFollowUps.length === 0" heading="No follow-ups scheduled in the next 7 days">
                    <template #icon>
                        <ListBulletIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>
                <div v-else class="space-y-3 max-h-96 overflow-y-auto">
                    <div
                        v-for="fu in next7DaysFollowUps"
                        :key="fu.id"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-4 bg-slate-50 rounded-card hover:bg-slate-100 transition-colors group min-w-0"
                    >
                        <div class="flex-1 min-w-0">
                            <router-link
                                v-if="fu.customer_id"
                                :to="`/customers/${fu.customer_id}`"
                                class="link break-words"
                            >
                                {{ fu.customer?.name || 'Customer' }}
                            </router-link>
                            <div v-else class="font-medium text-slate-900">
                                {{ fu.customer?.name || 'Customer' }}
                            </div>
                            <div class="text-sm text-slate-600 mt-0.5">
                                {{ fu.items?.length ? fu.items.map(i => i.product?.name).filter(Boolean).join(', ') : '-' }}
                            </div>
                            <div class="text-xs text-slate-500 mt-1 flex items-center gap-2 tabular-nums">
                                <span class="font-medium">{{ formatDateOnly(fu.next_follow_up_at) }}</span>
                                {{ formatTimeOnly(fu.next_follow_up_at) }}
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                            <BaseButton
                                variant="outline"
                                class="sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100 transition-opacity"
                                @click="openActivityModal(fu)"
                            >
                                Log
                            </BaseButton>
                            <BaseButton
                                v-if="fu.customer_id"
                                variant="primary"
                                :to="`/customers/${fu.customer_id}`"
                            >
                                View
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </div>
        </BaseCard>

        <!-- Recent Leads & Assigned Customers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <BaseCard title="Recent Leads">
                <EmptyState v-if="recentLeads.length === 0" heading="No recent leads">
                    <template #icon>
                        <DocumentTextIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>
                <div v-else class="space-y-3">
                    <div
                        v-for="lead in recentLeads"
                        :key="lead.id"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-3 bg-slate-50 rounded-card group min-w-0"
                    >
                        <div class="flex-1 min-w-0">
                            <router-link
                                v-if="lead.customer_id"
                                :to="`/customers/${lead.customer_id}`"
                                class="link break-words"
                            >
                                {{ lead.customer?.name || 'Customer' }}
                            </router-link>
                            <div v-else class="font-medium text-slate-900">
                                {{ lead.customer?.name || 'Customer' }}
                            </div>
                            <div class="text-xs text-slate-500">{{ formatLeadStage(lead.stage) }} • {{ lead.items?.length ? lead.items.map(i => i.product?.name).filter(Boolean).join(', ') : '-' }}</div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:gap-3 shrink-0 sm:justify-end">
                            <div class="text-sm font-medium text-slate-700 tabular-nums">£{{ formatNumber(getLeadValue(lead)) }}</div>
                            <BaseButton
                                variant="success"
                                class="sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100 transition-opacity"
                                @click="openActivityModal(lead)"
                            >
                                Log
                            </BaseButton>
                            <BaseButton
                                v-if="lead.customer_id"
                                variant="primary"
                                class="sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100 transition-opacity"
                                :to="`/customers/${lead.customer_id}`"
                            >
                                View
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </BaseCard>

            <BaseCard title="Assigned Customers">
                <EmptyState v-if="assignedCustomers.length === 0" heading="No assigned customers">
                    <template #icon>
                        <UsersIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>
                <div v-else class="space-y-3">
                    <div
                        v-for="customer in assignedCustomers"
                        :key="customer.id"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-3 bg-slate-50 rounded-card hover:bg-slate-100 transition-colors min-w-0"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-slate-900 break-words">{{ customer.name }}</div>
                            <div class="text-xs text-slate-500">{{ customer.phone }}</div>
                        </div>
                        <BaseButton
                            variant="primary"
                            block-mobile
                            class="shrink-0"
                            :to="`/customers/${customer.id}`"
                        >
                            View
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>
        </div>

        <!-- Pipeline Summary -->
        <section class="min-w-0 space-y-3 sm:space-y-4">
            <h2 class="text-base font-semibold text-slate-900">Pipeline Summary</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
                <StatCard label="Follow Up" :value="pipeline.follow_up || 0" tone="neutral">
                    <template #icon>
                        <ArrowPathIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard label="Lead" :value="pipeline.lead || 0" tone="neutral">
                    <template #icon>
                        <UserPlusIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard label="Hot Lead" :value="pipeline.hot_lead || 0" tone="warning">
                    <template #icon>
                        <BoltIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard label="Quotation" :value="pipeline.quotation || 0" tone="primary">
                    <template #icon>
                        <DocumentTextIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard label="Won" :value="pipeline.won || 0" tone="success">
                    <template #icon>
                        <CheckCircleIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard label="Lost" :value="pipeline.lost || 0" tone="danger">
                    <template #icon>
                        <XCircleIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
            </div>
        </section>

        <!-- Complete Follow-up Modal -->
        <BaseModal
            v-model="showCompleteModal"
            title="Complete Follow-up"
            size="md"
            :close-on-backdrop="false"
            @close="closeCompleteModal"
        >
            <!-- No `novalidate`: the required Remarks field must still block submit, as before. -->
            <form id="sales-agent-complete-followup-form" class="space-y-4" @submit.prevent="completeFollowUp">
                <div>
                    <label class="form-label" for="salesagentdashboard-remarks-notes">
                        Remarks / Notes <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <textarea id="salesagentdashboard-remarks-notes"
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
                        <span>Sale happened</span>
                    </label>
                </div>
                <div v-if="completeForm.saleHappened">
                    <label class="form-label" for="salesagentdashboard-new-stage">New Stage</label>
                    <select id="salesagentdashboard-new-stage" v-model="completeForm.newStage" class="form-select">
                        <option value="lead">Lead</option>
                        <option value="hot_lead">Hot Lead</option>
                        <option value="quotation">Quotation</option>
                        <option value="won">Won</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="salesagentdashboard-next-follow-up-date-optional">Next Follow-up Date (Optional)</label>
                    <input id="salesagentdashboard-next-follow-up-date-optional"
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
                    form="sales-agent-complete-followup-form"
                    block-mobile
                    :loading="completingFollowUp"
                >
                    {{ completingFollowUp ? 'Saving...' : 'Complete Follow-up' }}
                </BaseButton>
            </template>
        </BaseModal>

        <LogActivityModal
            v-if="showActivityModal && activityLead"
            :lead="activityLead"
            @close="closeActivityModal"
            @saved="handleActivitySaved"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import {
    ArrowPathIcon,
    ArrowTrendingUpIcon,
    BoltIcon,
    CalendarDaysIcon,
    ChartBarIcon,
    CheckCircleIcon,
    ClockIcon,
    DocumentTextIcon,
    ListBulletIcon,
    PlusCircleIcon,
    TrophyIcon,
    UserPlusIcon,
    UsersIcon,
    ViewfinderCircleIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';
import {
    BaseButton,
    BaseCard,
    BaseModal,
    EmptyState,
    StatCard,
} from '@/components/base';
import AttendanceClock from '@/components/AttendanceClock.vue';
import LogActivityModal from '@/components/LogActivityModal.vue';
import { formatLeadStage } from '@/utils/displayFormat';

const toast = useToastStore();
const auth = useAuthStore();
const stats = ref({});
const recentLeads = ref([]);
const assignedCustomers = ref([]);
const pipeline = ref({});
const todayFollowUps = ref([]);
const next7DaysFollowUps = ref([]);
const todayAppointments = ref([]);
const employeeTargets = ref([]);
const monthlyTopPerformer = ref(null);
const myTarget = computed(() => {
    const userId = auth.user?.id;
    if (!userId) return null;
    return employeeTargets.value.find((t) => t.user_id === userId) || null;
});
const followUpsByDate = ref([]);
const loading = ref(false);
const showActivityModal = ref(false);
const showCompleteModal = ref(false);
const completingFollowUp = ref(false);
const activityLead = ref(null);
const selectedFollowUp = ref(null);
const activeTab = ref('today');
const selectedDate = ref('');
const completeForm = ref({
    remarks: '',
    saleHappened: false,
    newStage: 'lead',
    nextFollowUpAt: '',
});

const todayStr = computed(() => {
    const d = new Date();
    return d.toISOString().split('T')[0];
});

const displayFollowUpsToday = computed(() => {
    if (selectedDate.value && selectedDate.value !== todayStr.value) {
        return followUpsByDate.value;
    }
    return todayFollowUps.value;
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num || 0);
};

const num = (v) => {
    if (v === null || v === undefined || v === '') return 0;
    if (typeof v === 'number') return Number.isFinite(v) ? v : 0;
    const n = parseFloat(String(v).replace(/,/g, ''));
    return Number.isFinite(n) ? n : 0;
};

const normalizeAgentsList = (payload) => {
    if (Array.isArray(payload)) return payload;
    if (payload && Array.isArray(payload.data)) return payload.data;
    return [];
};

const formatDateTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDateOnly = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
};

const formatTimeOnly = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};

const formatSelectedDateLabel = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr + 'T12:00:00').toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });
};

const getLeadValue = (lead) => {
    if (lead.stage === 'won' && lead.items?.length > 0) {
        const itemsTotal = lead.items.reduce((sum, item) => sum + (parseFloat(item.total_price) || 0), 0);
        return itemsTotal > 0 ? itemsTotal : (lead.pipeline_value || 0);
    }
    return lead.pipeline_value || 0;
};

const loadDashboard = async (dateParam = null) => {
    loading.value = true;
    try {
        const params = dateParam ? { date: dateParam } : {};

        // Targets/achievements for sales agents should always be based on a calendar month.
        // Use the month of the selected date if provided, otherwise the current month.
        const baseDate = dateParam ? new Date(dateParam) : new Date();
        const monthStr = `${baseDate.getFullYear()}-${String(baseDate.getMonth() + 1).padStart(2, '0')}`;

        const [response, agentsRes, targetsRes] = await Promise.all([
            axios.get('/api/dashboard/sales-agent', { params }),
            axios.get('/api/reporting/agents', { params: { month: monthStr } }),
            axios.get('/api/hr/employee-targets', { params: { month: monthStr } }),
        ]);
        stats.value = response.data.stats || {};
        recentLeads.value = response.data.recent_leads || [];
        assignedCustomers.value = response.data.assigned_customers || [];
        pipeline.value = response.data.pipeline || {};
        todayFollowUps.value = response.data.today_follow_ups || [];
        next7DaysFollowUps.value = response.data.next_7_days_follow_ups || [];
        todayAppointments.value = response.data.today_appointments || [];
        followUpsByDate.value = response.data.follow_ups_by_date || [];

        // Build employee target stats for the logged-in user and others (for future use)
        const targetsRaw = targetsRes.data?.data || [];
        const agents = normalizeAgentsList(agentsRes.data);
        const byUser = {};

        const performanceCandidates = agents
            .filter((a) =>
                num(a.leads_count) > 0 ||
                num(a.won_count) > 0 ||
                num(a.won_products) > 0 ||
                num(a.won_leads) > 0 ||
                num(a.revenue) > 0
            )
            .sort((a, b) => {
                const aWon = Math.max(num(a.won_products), num(a.won_count), num(a.won_leads));
                const bWon = Math.max(num(b.won_products), num(b.won_count), num(b.won_leads));
                if (bWon !== aWon) return bWon - aWon;
                if (num(b.revenue) !== num(a.revenue)) return num(b.revenue) - num(a.revenue);
                return num(b.leads_count) - num(a.leads_count);
            });
        monthlyTopPerformer.value = performanceCandidates[0] || null;

        for (const t of targetsRaw) {
            const lines = t.lines || [];
            const achievedFromLines = lines.length
                ? lines.reduce((s, l) => s + Number(l.achieved_quantity || 0), 0)
                : 0;
            const targetSales = lines.length
                ? lines.reduce((s, l) => s + Number(l.target_quantity || 0), 0)
                : t.target_sales || 0;
            byUser[t.user_id] = {
                user_id: t.user_id,
                user: t.user,
                lines,
                target_appointments: t.target_appointments || 0,
                target_sales: targetSales,
                target_revenue: t.target_revenue || 0,
                achieved_appointments: 0,
                achieved_sales: achievedFromLines,
                achieved_revenue: 0,
            };
        }

        for (const ag of agents) {
            const existing =
                byUser[ag.id] ||
                {
                    user_id: ag.id,
                    user: { id: ag.id, name: ag.name },
                    lines: [],
                    target_appointments: 0,
                    target_sales: 0,
                    target_revenue: 0,
                    achieved_appointments: 0,
                    achieved_sales: 0,
                    achieved_revenue: 0,
                };
            existing.achieved_appointments = ag.appointments_count || 0;
            if (existing.lines?.length) {
                existing.achieved_sales = existing.lines.reduce(
                    (s, l) => s + Number(l.achieved_quantity || 0),
                    0
                );
            } else {
                existing.achieved_sales = ag.won_products || ag.won_count || 0;
            }
            existing.achieved_revenue = ag.revenue || 0;
            byUser[ag.id] = existing;
        }

        employeeTargets.value = Object.values(byUser).map((t) => {
            const apptDenom = num(t.target_appointments);
            const apptAch = num(t.achieved_appointments);
            const appointment_progress =
                apptDenom > 0
                    ? Math.min(100, Math.round((apptAch / apptDenom) * 100))
                    : apptAch > 0
                      ? 100
                      : 0;
            const salesDenom = num(t.target_sales);
            const salesAch = num(t.achieved_sales);
            const sales_progress =
                salesDenom > 0
                    ? Math.min(100, Math.round((salesAch / salesDenom) * 100))
                    : salesAch > 0
                      ? 100
                      : 0;
            const revDenom = num(t.target_revenue);
            const revAch = num(t.achieved_revenue);
            const revenue_progress =
                revDenom > 0
                    ? Math.min(100, Math.round((revAch / revDenom) * 100))
                    : revAch > 0
                      ? 100
                      : 0;
            const parts = [];
            if (apptDenom > 0) parts.push(appointment_progress);
            if (salesDenom > 0) parts.push(sales_progress);
            if (revDenom > 0) parts.push(revenue_progress);
            const overall_progress = parts.length
                ? Math.round(parts.reduce((a, b) => a + b, 0) / parts.length)
                : 0;
            return {
                ...t,
                appointment_progress,
                sales_progress,
                revenue_progress,
                overall_progress,
            };
        });
    } catch (error) {
        console.error('Failed to load dashboard:', error);
    } finally {
        loading.value = false;
    }
};

const onDateChange = () => {
    if (selectedDate.value === todayStr.value) {
        followUpsByDate.value = todayFollowUps.value;
        return;
    }
    loadDashboard(selectedDate.value);
};

const resetDate = () => {
    selectedDate.value = todayStr.value;
    followUpsByDate.value = [];
    loadDashboard();
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
    loadDashboard(selectedDate.value && selectedDate.value !== todayStr.value ? selectedDate.value : undefined);
    closeActivityModal();
};

const openCompleteModal = (fu) => {
    selectedFollowUp.value = fu;
    completeForm.value = { remarks: '', saleHappened: false, newStage: 'lead', nextFollowUpAt: '' };
    showCompleteModal.value = true;
};

const closeCompleteModal = () => {
    showCompleteModal.value = false;
    selectedFollowUp.value = null;
};

const completeFollowUp = async () => {
    if (!selectedFollowUp.value || completingFollowUp.value) return;
    completingFollowUp.value = true;
    try {
        const payload = {
            remarks: completeForm.value.remarks,
            sale_happened: completeForm.value.saleHappened,
            new_stage: completeForm.value.saleHappened ? completeForm.value.newStage : null,
        };
        if (completeForm.value.nextFollowUpAt) {
            payload.next_follow_up_at = completeForm.value.nextFollowUpAt;
        }
        await axios.post(`/api/leads/${selectedFollowUp.value.id}/complete-followup`, payload);
        closeCompleteModal();
        loadDashboard(selectedDate.value && selectedDate.value !== todayStr.value ? selectedDate.value : undefined);
        toast.success('Follow-up completed');
    } catch (e) {
        toast.error('Failed to complete follow-up');
    } finally {
        completingFollowUp.value = false;
    }
};

onMounted(() => {
    selectedDate.value = todayStr.value;
    loadDashboard();
});
</script>
