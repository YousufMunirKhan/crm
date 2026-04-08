<template>
    <ListingPageShell
        title="Employee performance"
        subtitle="Last week’s sales, the full selected month, and monthly target achievement — same rules as Set targets (won line items, lead in period)."
        :badge="pageBadge"
    >
        <template #filters>
            <div class="flex flex-col sm:flex-row flex-wrap gap-3 sm:items-end">
                <div class="w-full sm:w-56">
                    <label class="listing-label">Employee</label>
                    <select v-model="selectedEmployeeId" class="listing-input w-full" @change="loadData">
                        <option value="">Select employee</option>
                        <option v-for="emp in employees" :key="emp.id" :value="String(emp.id)">
                            {{ emp.name }}
                        </option>
                    </select>
                </div>
                <div class="w-full sm:w-48">
                    <label class="listing-label">Month (targets + month sales)</label>
                    <select v-model="selectedMonth" class="listing-input w-full" @change="loadData">
                        <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                </div>
                <button type="button" class="listing-btn-primary" :disabled="loading || !selectedEmployeeId" @click="loadData">
                    {{ loading ? 'Loading…' : 'Refresh' }}
                </button>
            </div>
        </template>

        <div v-if="!selectedEmployeeId" class="mx-3 sm:mx-5 mb-4 rounded-xl border border-slate-200 bg-slate-50/50 p-8 text-center text-slate-500 text-sm">
            Choose an employee to see last week, that month’s sales, and targets.
        </div>

        <div v-else-if="loading" class="flex justify-center py-16">
            <svg class="animate-spin h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                ></path>
            </svg>
        </div>

        <div v-else class="space-y-8 px-3 sm:px-5 pb-6">
            <section v-if="self" class="space-y-3">
                <h2 class="text-base font-semibold text-slate-900">Target achievement — {{ monthLabel }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                        <div class="text-sm text-slate-500 mb-1">Appointments</div>
                        <div class="text-xl font-bold text-slate-900">
                            {{ self.achieved_appointments }} <span class="text-slate-500 font-normal">/ {{ self.target_appointments }}</span>
                        </div>
                        <div class="mt-2 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div
                                class="h-2 bg-emerald-500 rounded-full"
                                :style="{ width: `${Math.min(self.appointment_progress || 0, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                        <div class="text-sm text-slate-500 mb-1">Sales (won line items)</div>
                        <div class="text-xl font-bold text-slate-900">
                            {{ self.achieved_sales }} <span class="text-slate-500 font-normal">/ {{ self.target_sales }}</span>
                        </div>
                        <div class="mt-2 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div
                                class="h-2 bg-blue-500 rounded-full"
                                :style="{ width: `${Math.min(self.sales_progress || 0, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                        <div class="text-sm text-slate-500 mb-1">Revenue</div>
                        <div class="text-xl font-bold text-slate-900">
                            £{{ formatNumber(self.achieved_revenue) }}
                            <span class="text-slate-500 font-normal">/ £{{ formatNumber(self.target_revenue) }}</span>
                        </div>
                        <div class="mt-2 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div
                                class="h-2 bg-indigo-500 rounded-full"
                                :style="{ width: `${Math.min(self.revenue_progress || 0, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
                <div
                    v-if="self.sales_target_lines?.length"
                    class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm"
                >
                    <div class="px-4 py-2 border-b border-slate-100 text-sm font-medium text-slate-800">Product & category targets</div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs text-slate-600">
                                <tr>
                                    <th class="px-4 py-2">Line</th>
                                    <th class="px-4 py-2">Achieved</th>
                                    <th class="px-4 py-2">Target</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="(ln, idx) in self.sales_target_lines" :key="idx">
                                    <td class="px-4 py-2">{{ ln.label }}</td>
                                    <td class="px-4 py-2 font-medium">{{ ln.achieved_quantity }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ ln.target_quantity }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
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
