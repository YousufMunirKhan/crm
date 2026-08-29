<template>
    <ListingPageShell
        title="My report"
        subtitle="Your targets vs achievements for the month — same metrics as the team leaderboard."
        :badge="myReportBadge"
    >
        <template #filters>
            <div>
                <label class="form-label" for="reportmyreportview-month">Month</label>
                <select id="reportmyreportview-month" v-model="selectedMonth" class="form-select w-full sm:w-56" @change="loadReport">
                    <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
            </div>
        </template>

        <div v-if="loading" class="px-4 sm:px-5 py-8 space-y-3" aria-busy="true">
            <p class="sr-only">Loading your report…</p>
            <div class="skeleton-text w-1/3"></div>
            <div class="skeleton-text w-2/3"></div>
            <div class="skeleton-text w-1/2"></div>
            <div class="skeleton-text w-3/4"></div>
        </div>

        <div v-else-if="self" class="px-4 sm:px-5 pb-5 space-y-4">
            <!-- Ranking badge -->
            <div
                v-if="self.rank"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-warning-50 to-warning-100 border border-warning-200"
            >
                <span class="text-2xl font-bold text-warning-800 tabular-nums">#{{ self.rank }}</span>
                <span class="text-sm text-warning-800">
                    out of {{ totalEmployeesWithTargets }} employee{{ totalEmployeesWithTargets !== 1 ? 's' : '' }} with targets
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
                <StatCard
                    v-for="card in activityCards"
                    :key="card.label"
                    :label="card.label"
                    :value="card.value"
                    :caption="card.help"
                />
            </div>

            <!-- Target vs Achievement cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <BaseCard>
                    <div class="stat-label">Appointments</div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-slate-900 tabular-nums">{{ self.achieved_appointments }}</span>
                        <span class="text-slate-500 tabular-nums">/ {{ self.target_appointments }}</span>
                    </div>
                    <div class="mt-2">
                        <div class="flex justify-between text-xs text-slate-500 mb-1">
                            <span>Progress</span>
                            <span class="tabular-nums">{{ self.appointment_progress }}%</span>
                        </div>
                        <div
                            class="w-full bg-slate-200 rounded-full h-2"
                            role="progressbar"
                            aria-label="Appointment target progress"
                            :aria-valuenow="Math.min(self.appointment_progress || 0, 100)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-2 rounded-full bg-success-600 transition-all"
                                :style="{ width: `${Math.min(self.appointment_progress || 0, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </BaseCard>
                <BaseCard>
                    <div class="stat-label">Sales (Products Won)</div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-slate-900 tabular-nums">{{ self.achieved_sales }}</span>
                        <span class="text-slate-500 tabular-nums">/ {{ self.target_sales }}</span>
                    </div>
                    <div class="mt-2">
                        <div class="flex justify-between text-xs text-slate-500 mb-1">
                            <span>Progress</span>
                            <span class="tabular-nums">{{ self.sales_progress }}%</span>
                        </div>
                        <div
                            class="w-full bg-slate-200 rounded-full h-2"
                            role="progressbar"
                            aria-label="Sales target progress"
                            :aria-valuenow="Math.min(self.sales_progress || 0, 100)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-2 rounded-full bg-primary-600 transition-all"
                                :style="{ width: `${Math.min(self.sales_progress || 0, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </BaseCard>
                <BaseCard>
                    <div class="stat-label">Revenue</div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-slate-900 tabular-nums">£{{ formatNumber(self.achieved_revenue || 0) }}</span>
                        <span class="text-slate-500 tabular-nums">/ £{{ formatNumber(self.target_revenue || 0) }}</span>
                    </div>
                    <div class="mt-2">
                        <div class="flex justify-between text-xs text-slate-500 mb-1">
                            <span>Progress</span>
                            <span class="tabular-nums">{{ self.revenue_progress }}%</span>
                        </div>
                        <div
                            class="w-full bg-slate-200 rounded-full h-2"
                            role="progressbar"
                            aria-label="Revenue target progress"
                            :aria-valuenow="Math.min(self.revenue_progress || 0, 100)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-2 rounded-full bg-primary-500 transition-all"
                                :style="{ width: `${Math.min(self.revenue_progress || 0, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </BaseCard>
            </div>

            <BaseCard
                v-if="self.sales_target_lines?.length"
                title="Sales targets by product & category"
                subtitle="Won line items in the month — category rows include every product in that category."
                :padded="false"
            >
                <div class="table-wrap">
                    <table class="table">
                        <caption class="sr-only">Sales targets by product and category, with achieved and target quantities</caption>
                        <thead class="table-thead">
                            <tr>
                                <th scope="col" class="table-th">Target</th>
                                <th scope="col" class="table-th-num">Achieved</th>
                                <th scope="col" class="table-th-num">Target qty</th>
                                <th scope="col" class="table-th-num">Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(ln, idx) in self.sales_target_lines" :key="idx" class="table-row">
                                <td class="table-td-strong">{{ ln.label }}</td>
                                <td class="table-td-num font-medium">{{ ln.achieved_quantity }}</td>
                                <td class="table-td-num">{{ ln.target_quantity }}</td>
                                <td class="table-td-num">{{ lineProgress(ln) }}%</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </BaseCard>

            <p v-if="!self.rank && (self.target_appointments || self.target_sales || self.target_revenue)" class="callout callout-info">
                No ranking yet — you may not be in the Sales/CallAgent role or targets were set recently.
            </p>
            <p v-if="!self.target_appointments && !self.target_sales && !self.target_revenue" class="callout callout-warning">
                No targets set for this month. Ask your admin to use Set targets (Employees).
            </p>
        </div>

        <EmptyState
            v-else
            heading="Unable to load your report"
            description="We could not fetch your targets for this month. Pick another month or try again shortly."
        >
            <template #icon>
                <ChartBarIcon class="icon" aria-hidden="true" />
            </template>
        </EmptyState>
    </ListingPageShell>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseCard, EmptyState, StatCard } from '@/components/base';
import { ChartBarIcon } from '@heroicons/vue/24/outline';

const formatMonth = (date) => {
    const d = date instanceof Date ? date : new Date(date);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    return `${y}-${m}`;
};

const selectedMonth = ref(formatMonth(new Date()));
const loading = ref(false);
const self = ref(null);
const totalEmployeesWithTargets = ref(0);

const myReportBadge = computed(() => {
    if (loading.value || !self.value) return null;
    if (self.value.rank != null) return `Rank #${self.value.rank}`;
    return totalEmployeesWithTargets.value ? `${totalEmployeesWithTargets.value} in leaderboard` : null;
});

const monthOptions = (() => {
    const opts = [];
    const now = new Date();
    for (let i = 0; i < 12; i++) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        opts.push({
            value: formatMonth(d),
            label: d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }),
        });
    }
    return opts;
})();

const formatNumber = (n) => new Intl.NumberFormat('en-GB').format(n);

const activityCards = computed(() => [
    {
        label: 'New leads',
        value: self.value?.new_leads || 0,
        help: 'Assigned this month',
    },
    {
        label: 'Open pipeline',
        value: `£${formatNumber(self.value?.open_pipeline || 0)}`,
        help: 'Not won or lost yet',
    },
    {
        label: 'Appointments',
        value: self.value?.achieved_appointments || 0,
        help: 'Booked or handled',
    },
    {
        label: 'Won products',
        value: self.value?.achieved_sales || 0,
        help: 'Closed product lines',
    },
    {
        label: 'Revenue',
        value: `£${formatNumber(self.value?.achieved_revenue || 0)}`,
        help: 'Won products plus invoices',
    },
]);

const lineProgress = (ln) => {
    const t = Number(ln.target_quantity || 0);
    const a = Number(ln.achieved_quantity || 0);
    if (t <= 0) return a > 0 ? 100 : 0;
    return Math.min(100, Math.round((a / t) * 100));
};

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/reporting/employee-self-report', {
            params: { month: selectedMonth.value },
        });
        self.value = res.data.self ?? null;
        totalEmployeesWithTargets.value = res.data.total_employees_with_targets ?? 0;
    } catch (e) {
        console.error('Failed to load report:', e);
        self.value = null;
    } finally {
        loading.value = false;
    }
};

onMounted(loadReport);
</script>
