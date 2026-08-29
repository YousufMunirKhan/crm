<template>
    <ListingPageShell
        title="Target vs achievement"
        subtitle="Monthly targets and performance by employee — summary reflects appointment totals for the selected month."
        :badge="targetAchievementBadge"
    >
        <template #filters>
            <div class="flex flex-col sm:flex-row sm:flex-wrap gap-3 sm:items-end">
                <div>
                    <label class="form-label" for="reporttargetachievementview-month">Month</label>
                    <div class="flex items-center gap-2">
                        <BaseButton
                            variant="outline"
                            size="icon"
                            type="button"
                            label="Previous month"
                            title="Previous month"
                            @click="shiftMonth(-1)"
                        >
                            <template #icon>
                                <ChevronLeftIcon class="icon" aria-hidden="true" />
                            </template>
                        </BaseButton>
                        <select id="reporttargetachievementview-month" v-model="selectedMonth" class="form-select flex-1 min-w-[10rem]" @change="loadData">
                            <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                        </select>
                        <BaseButton
                            variant="outline"
                            size="icon"
                            type="button"
                            label="Next month"
                            title="Next month"
                            :disabled="selectedMonth === monthOptions[0]?.value"
                            @click="shiftMonth(1)"
                        >
                            <template #icon>
                                <ChevronRightIcon class="icon" aria-hidden="true" />
                            </template>
                        </BaseButton>
                    </div>
                </div>
            </div>
        </template>

        <div class="px-3 sm:px-5 pt-4 pb-4 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                <div class="w-16 h-16 rounded-full grid place-items-center shrink-0" :style="ringStyle(overallPercent)" aria-hidden="true">
                    <div class="w-12 h-12 bg-white rounded-full grid place-items-center text-lg font-bold text-slate-900 tabular-nums">
                        {{ overallPercent }}%
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 flex-1 min-w-0">
                    <StatCard label="Total target" :value="totalTarget" caption="Appointments targeted this month" />
                    <StatCard label="Total achieved" :value="totalAchieved" caption="Appointments recorded this month" tone="success" />
                    <StatCard label="Overall" :value="`${overallPercent}%`" caption="Achieved against target" tone="primary" />
                </div>
            </div>
            <div
                class="h-2.5 bg-slate-100 rounded-full overflow-hidden"
                role="progressbar"
                aria-label="Overall appointment progress"
                :aria-valuenow="overallPercent"
                aria-valuemin="0"
                aria-valuemax="100"
            >
                <div
                    class="h-2.5 rounded-full bg-gradient-to-r from-primary-600 via-success-500 to-success-500"
                    :style="{ width: `${overallPercent}%` }"
                />
            </div>
        </div>

        <div v-if="loading" class="px-3 sm:px-5 py-8 space-y-3" aria-busy="true">
            <p class="sr-only">Loading target achievement…</p>
            <div class="skeleton-text w-1/3"></div>
            <div class="skeleton-text w-2/3"></div>
            <div class="skeleton-text w-1/2"></div>
            <div class="skeleton-text w-3/4"></div>
        </div>

        <EmptyState
            v-else-if="data.length === 0"
            heading="No employees with targets"
            description="No employees with targets for this month. Set targets to start tracking achievement."
        >
            <template #icon>
                <ViewfinderCircleIcon class="icon" aria-hidden="true" />
            </template>
            <template #action>
                <BaseButton variant="primary" to="/employees/goals">Set targets</BaseButton>
            </template>
        </EmptyState>

        <div v-else class="space-y-6 px-3 pb-4 sm:px-5">
            <!-- Appointments board -->
            <BaseCard title="Appointments" subtitle="Ranked by appointment achievement." :padded="false">
                <div class="table-wrap">
                    <table class="table min-w-[720px]">
                        <caption class="sr-only">Employees ranked by appointment achievement, with achieved and target appointments and progress</caption>
                        <thead class="table-thead">
                            <tr>
                                <th scope="col" class="table-th">Rank</th>
                                <th scope="col" class="table-th">Employee</th>
                                <th scope="col" class="table-th">Appointments</th>
                                <th scope="col" class="table-th">Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, idx) in appointmentsRows" :key="row.employee_id" class="table-row">
                                <td class="table-td">
                                    <span :class="rankClass(idx + 1)" class="inline-flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold tabular-nums">
                                        {{ idx + 1 }}
                                    </span>
                                </td>
                                <td class="table-td">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-slate-200 text-slate-700 grid place-items-center text-xs font-bold" aria-hidden="true">
                                            {{ initials(row.employee_name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-slate-900 truncate">{{ row.employee_name }}</div>
                                            <div v-if="idx === 0" class="inline-flex items-center gap-2 mt-1">
                                                <BaseBadge tone="warning">Top Performer</BaseBadge>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="table-td font-medium tabular-nums">
                                    {{ row.achieved_appointments }} / {{ row.target_appointments || 0 }}
                                </td>
                                <td class="table-td">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden"
                                            role="progressbar"
                                            :aria-label="`${row.employee_name} appointment progress`"
                                            :aria-valuenow="clampPct(row.appointment_progress)"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                        >
                                            <div class="h-2 rounded-full bg-gradient-to-r from-primary-600 to-success-500" :style="{ width: `${clampPct(row.appointment_progress)}%` }" />
                                        </div>
                                        <div class="text-sm font-semibold text-slate-700 w-14 text-right tabular-nums">{{ clampPct(row.appointment_progress) }}%</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </BaseCard>

            <!-- Sales + Revenue board -->
            <BaseCard title="Sales &amp; Revenue" subtitle="Ranked by overall achievement." :padded="false">
                <div class="table-wrap">
                    <table class="table min-w-[860px]">
                        <caption class="sr-only">Employees ranked by overall achievement, with sales, revenue and overall progress</caption>
                        <thead class="table-thead">
                            <tr>
                                <th scope="col" class="table-th">Rank</th>
                                <th scope="col" class="table-th">Employee</th>
                                <th scope="col" class="table-th">Sales</th>
                                <th scope="col" class="table-th">Revenue</th>
                                <th scope="col" class="table-th-num">Overall</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in data" :key="row.employee_id" class="table-row">
                                <td class="table-td">
                                    <span :class="rankClass(row.rank)" class="inline-flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold tabular-nums">
                                        {{ row.rank }}
                                    </span>
                                </td>
                                <td class="table-td-strong">{{ row.employee_name }}</td>
                                <td class="table-td">
                                    <div class="text-sm font-medium text-slate-700 tabular-nums">{{ row.achieved_sales }} / {{ row.target_sales || 0 }}</div>
                                    <div class="flex items-center gap-2 mt-2">
                                        <div
                                            class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden"
                                            role="progressbar"
                                            :aria-label="`${row.employee_name} sales progress`"
                                            :aria-valuenow="clampPct(row.sales_progress)"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                        >
                                            <div class="h-2 rounded-full bg-gradient-to-r from-primary-600 to-warning-400" :style="{ width: `${clampPct(row.sales_progress)}%` }" />
                                        </div>
                                        <div class="text-xs font-semibold text-slate-600 w-12 text-right tabular-nums">{{ clampPct(row.sales_progress) }}%</div>
                                    </div>
                                </td>
                                <td class="table-td">
                                    <div class="text-sm font-medium text-slate-700 tabular-nums">£{{ formatNumber(row.achieved_revenue) }} / £{{ formatNumber(row.target_revenue) }}</div>
                                    <div class="flex items-center gap-2 mt-2">
                                        <div
                                            class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden"
                                            role="progressbar"
                                            :aria-label="`${row.employee_name} revenue progress`"
                                            :aria-valuenow="clampPct(row.revenue_progress)"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                        >
                                            <div class="h-2 rounded-full bg-gradient-to-r from-success-500 to-success-600" :style="{ width: `${clampPct(row.revenue_progress)}%` }" />
                                        </div>
                                        <div class="text-xs font-semibold text-slate-600 w-12 text-right tabular-nums">{{ clampPct(row.revenue_progress) }}%</div>
                                    </div>
                                </td>
                                <td class="table-td-num">
                                    <span class="text-sm font-bold" :class="overallClass(row.overall_progress)">{{ clampPct(row.overall_progress) }}%</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </BaseCard>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseBadge, BaseButton, BaseCard, EmptyState, StatCard } from '@/components/base';
import { ChevronLeftIcon, ChevronRightIcon, ViewfinderCircleIcon } from '@heroicons/vue/24/outline';

const formatMonth = (date) => {
    const d = date instanceof Date ? date : new Date(date);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    return `${y}-${m}`;
};

const selectedMonth = ref(formatMonth(new Date()));
const loading = ref(false);
const data = ref([]);

const targetAchievementBadge = computed(() => {
    if (loading.value || !data.value.length) return null;
    const n = data.value.length;
    return `${n} ${n === 1 ? 'employee' : 'employees'}`;
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

const clampPct = (n) => {
    const x = Number(n ?? 0);
    if (!Number.isFinite(x)) return 0;
    return Math.max(0, Math.min(100, Math.round(x)));
};

const totalTarget = computed(() => data.value.reduce((sum, r) => sum + (Number(r.target_appointments) || 0), 0));
const totalAchieved = computed(() => data.value.reduce((sum, r) => sum + (Number(r.achieved_appointments) || 0), 0));
const overallPercent = computed(() => {
    const t = totalTarget.value;
    const a = totalAchieved.value;
    if (t <= 0) return 0;
    return clampPct((a / t) * 100);
});

const appointmentsRows = computed(() => {
    return [...data.value].sort((a, b) => (Number(b.appointment_progress) || 0) - (Number(a.appointment_progress) || 0));
});

function initials(name) {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    const a = (parts[0] || '').slice(0, 1).toUpperCase();
    const b = (parts[1] || '').slice(0, 1).toUpperCase();
    return (a + b) || '—';
}

function rankClass(rank) {
    if (rank === 1) return 'bg-warning-100 text-warning-800';
    if (rank === 2) return 'bg-slate-200 text-slate-700';
    if (rank === 3) return 'bg-warning-100/70 text-warning-800';
    return 'bg-slate-100 text-slate-700';
}

function overallClass(pct) {
    const p = Number(pct ?? 0);
    if (p >= 100) return 'text-success-700';
    if (p >= 50) return 'text-warning-800';
    return 'text-slate-700';
}

function ringStyle(pct) {
    const p = clampPct(pct);
    return {
        background: `conic-gradient(var(--color-primary-600) ${p * 3.6}deg, var(--color-slate-200) 0deg)`,
    };
}

function shiftMonth(delta) {
    const [y, m] = String(selectedMonth.value).split('-').map(Number);
    if (!y || !m) return;
    const d = new Date(y, m - 1, 1);
    d.setMonth(d.getMonth() + delta);
    selectedMonth.value = formatMonth(d);
    loadData();
}

const loadData = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/reporting/target-vs-achievement', {
            params: { month: selectedMonth.value },
        });
        data.value = res.data.data || [];
    } catch (e) {
        console.error('Failed to load data:', e);
        data.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(loadData);
</script>
