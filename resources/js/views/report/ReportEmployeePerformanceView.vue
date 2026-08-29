<template>
    <ListingPageShell
        title="Employee performance"
        subtitle="Last week’s sales, the full selected month, and monthly target achievement — same rules as Set targets (won line items, lead in period)."
        :badge="pageBadge"
    >
        <template #filters>
            <div class="flex flex-col sm:flex-row flex-wrap gap-3 sm:items-end">
                <div class="w-full sm:w-56">
                    <label class="form-label" for="reportemployeeperformanceview-employee">Employee</label>
                    <select id="reportemployeeperformanceview-employee" v-model="selectedEmployeeId" class="form-select w-full" @change="loadData">
                        <option value="">Select employee</option>
                        <option v-for="emp in employees" :key="emp.id" :value="String(emp.id)">
                            {{ emp.name }}
                        </option>
                    </select>
                </div>
                <div class="w-full sm:w-48">
                    <label class="form-label" for="reportemployeeperformanceview-month">Month (targets + month sales)</label>
                    <select id="reportemployeeperformanceview-month" v-model="selectedMonth" class="form-select w-full" @change="loadData">
                        <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                </div>
                <BaseButton
                    variant="primary"
                    type="button"
                    :disabled="loading || !selectedEmployeeId"
                    :loading="loading"
                    block-mobile
                    @click="loadData"
                >
                    <template #icon>
                        <ArrowPathIcon class="icon" aria-hidden="true" />
                    </template>
                    {{ loading ? 'Loading…' : 'Refresh' }}
                </BaseButton>
            </div>
        </template>

        <EmptyState
            v-if="!selectedEmployeeId"
            heading="No employee selected"
            description="Choose an employee to see last week, that month’s sales, and targets."
        >
            <template #icon>
                <UsersIcon class="icon" aria-hidden="true" />
            </template>
        </EmptyState>

        <div v-else-if="loading" class="px-3 sm:px-5 py-8 space-y-3" aria-busy="true">
            <p class="sr-only">Loading employee performance…</p>
            <div class="skeleton-text w-1/3"></div>
            <div class="skeleton-text w-2/3"></div>
            <div class="skeleton-text w-1/2"></div>
            <div class="skeleton-text w-3/4"></div>
        </div>

        <div v-else class="space-y-8 px-3 sm:px-5 pb-6">
            <section v-if="self" class="space-y-3">
                <h2 class="text-base font-semibold text-slate-900">Target achievement — {{ monthLabel }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <BaseCard>
                        <div class="stat-label">Appointments</div>
                        <div class="text-xl font-bold text-slate-900 tabular-nums">
                            {{ self.achieved_appointments }} <span class="text-slate-500 font-normal">/ {{ self.target_appointments }}</span>
                        </div>
                        <div
                            class="mt-2 h-2 bg-slate-100 rounded-full overflow-hidden"
                            role="progressbar"
                            aria-label="Appointment target progress"
                            :aria-valuenow="Math.min(self.appointment_progress || 0, 100)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-2 bg-success-600 rounded-full"
                                :style="{ width: `${Math.min(self.appointment_progress || 0, 100)}%` }"
                            ></div>
                        </div>
                    </BaseCard>
                    <BaseCard>
                        <div class="stat-label">Sales (won line items)</div>
                        <div class="text-xl font-bold text-slate-900 tabular-nums">
                            {{ self.achieved_sales }} <span class="text-slate-500 font-normal">/ {{ self.target_sales }}</span>
                        </div>
                        <div
                            class="mt-2 h-2 bg-slate-100 rounded-full overflow-hidden"
                            role="progressbar"
                            aria-label="Sales target progress"
                            :aria-valuenow="Math.min(self.sales_progress || 0, 100)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-2 bg-primary-600 rounded-full"
                                :style="{ width: `${Math.min(self.sales_progress || 0, 100)}%` }"
                            ></div>
                        </div>
                    </BaseCard>
                    <BaseCard>
                        <div class="stat-label">Revenue</div>
                        <div class="text-xl font-bold text-slate-900 tabular-nums">
                            £{{ formatNumber(self.achieved_revenue) }}
                            <span class="text-slate-500 font-normal">/ £{{ formatNumber(self.target_revenue) }}</span>
                        </div>
                        <div
                            class="mt-2 h-2 bg-slate-100 rounded-full overflow-hidden"
                            role="progressbar"
                            aria-label="Revenue target progress"
                            :aria-valuenow="Math.min(self.revenue_progress || 0, 100)"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-2 bg-primary-500 rounded-full"
                                :style="{ width: `${Math.min(self.revenue_progress || 0, 100)}%` }"
                            ></div>
                        </div>
                    </BaseCard>
                </div>
                <BaseCard v-if="self.sales_target_lines?.length" title="Product & category targets" :padded="false">
                    <div class="table-wrap">
                        <table class="table">
                            <caption class="sr-only">Product and category targets with achieved and target quantities</caption>
                            <thead class="table-thead">
                                <tr>
                                    <th scope="col" class="table-th">Line</th>
                                    <th scope="col" class="table-th-num">Achieved</th>
                                    <th scope="col" class="table-th-num">Target</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(ln, idx) in self.sales_target_lines" :key="idx" class="table-row">
                                    <td class="table-td">{{ ln.label }}</td>
                                    <td class="table-td-num font-medium">{{ ln.achieved_quantity }}</td>
                                    <td class="table-td-num">{{ ln.target_quantity }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </BaseCard>
                <p v-else-if="self.rank" class="text-sm text-slate-600">Leaderboard rank: <strong>#{{ self.rank }}</strong></p>
            </section>

            <section v-if="lastWeek" class="space-y-2">
                <h2 class="text-base font-semibold text-slate-900">Last week — {{ lastWeek.label }}</h2>
                <p class="text-xs text-slate-500">
                    Calendar week Mon–Sun immediately before this week. {{ lastWeek.won_line_items }} won line{{
                        lastWeek.won_line_items === 1 ? '' : 's'
                    }}, £{{ formatNumber(lastWeek.total_revenue) }} revenue.
                </p>
                <sold-lines-table :rows="lastWeek.products" />
            </section>

            <section v-if="selectedMonthBlock" class="space-y-2">
                <h2 class="text-base font-semibold text-slate-900">
                    Selected month — {{ selectedMonthBlock.period?.from }} to {{ selectedMonthBlock.period?.to }}
                </h2>
                <p class="text-xs text-slate-500">
                    Full calendar month (same window as targets above). {{ selectedMonthBlock.won_line_items }} won line{{
                        selectedMonthBlock.won_line_items === 1 ? '' : 's'
                    }}, £{{ formatNumber(selectedMonthBlock.total_revenue) }} revenue.
                </p>
                <sold-lines-table :rows="selectedMonthBlock.products" />
            </section>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';
import SoldLinesTable from '@/components/report/SoldLinesTable.vue';
import { BaseButton, BaseCard, EmptyState } from '@/components/base';
import { ArrowPathIcon, UsersIcon } from '@heroicons/vue/24/outline';

const route = useRoute();

const formatMonth = (date) => {
    const d = date instanceof Date ? date : new Date(date);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    return `${y}-${m}`;
};

const selectedEmployeeId = ref('');
const selectedMonth = ref(formatMonth(new Date()));
const loading = ref(false);
const employees = ref([]);
const lastWeek = ref(null);
const selectedMonthBlock = ref(null);
const self = ref(null);

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

const monthLabel = computed(() => {
    const o = monthOptions.find((m) => m.value === selectedMonth.value);
    return o?.label || selectedMonth.value;
});

const pageBadge = computed(() => {
    if (!selectedEmployeeId.value || loading.value) return null;
    const lw = lastWeek.value?.won_line_items ?? 0;
    const m = selectedMonthBlock.value?.won_line_items ?? 0;
    return `Last week ${lw} · Month ${m}`;
});

const formatNumber = (n) => new Intl.NumberFormat('en-GB', { maximumFractionDigits: 2 }).format(Number(n || 0));

const loadEmployees = async () => {
    try {
        const res = await axios.get('/api/users', { params: { for_sales_report: 1 } });
        employees.value = res.data.data || res.data || [];
    } catch (e) {
        console.error(e);
        employees.value = [];
    }
};

const loadData = async () => {
    if (!selectedEmployeeId.value) return;
    loading.value = true;
    try {
        const res = await axios.get('/api/reporting/employee-performance-overview', {
            params: {
                agent_id: selectedEmployeeId.value,
                month: selectedMonth.value,
            },
        });
        lastWeek.value = res.data.last_week || null;
        selectedMonthBlock.value = res.data.selected_month || null;
        self.value = res.data.targets?.self || null;
    } catch (e) {
        console.error(e);
        lastWeek.value = null;
        selectedMonthBlock.value = null;
        self.value = null;
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    await loadEmployees();
    const q = route.query.employee_id || route.query.agent_id;
    if (q) {
        selectedEmployeeId.value = String(q);
        await loadData();
    }
});

watch(
    () => route.query.employee_id || route.query.agent_id,
    async (q) => {
        if (!q || !employees.value.length) return;
        selectedEmployeeId.value = String(q);
        await loadData();
    }
);
</script>
