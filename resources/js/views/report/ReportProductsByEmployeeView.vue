<template>
    <div class="w-full min-w-0 max-w-7xl mx-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h1 class="text-2xl font-bold text-slate-900">Products Sold by Employee</h1>
            <div class="flex flex-wrap items-center gap-3">
                <select
                    v-model="selectedEmployeeId"
                    @change="loadData"
                    class="w-full sm:w-auto min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm sm:min-w-[200px]"
                >
                    <option value="">Select Employee</option>
                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                        {{ emp.name }}
                    </option>
                </select>
                <select
                    v-model="selectedMonth"
                    @change="loadData"
                    class="w-full sm:w-auto min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm"
                >
                    <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
                <button
                    @click="loadData"
                    :disabled="loading"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm hover:bg-slate-800 disabled:opacity-50 touch-manipulation w-full sm:w-auto text-center"
                >
                    {{ loading ? 'Loading...' : 'Apply' }}
                </button>
            </div>
        </div>

        <div v-if="!selectedEmployeeId" class="bg-slate-50 rounded-xl p-8 text-center text-slate-500">
            Select an employee from the dropdown to view products they sold this month.
        </div>

        <template v-else>
            <div v-if="loading" class="flex justify-center py-12">
                <svg class="animate-spin h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>

            <template v-else>
                <div v-if="report.employee_name" class="mb-4 text-sm text-slate-600">
                    <strong>{{ report.employee_name }}</strong> — {{ report.period?.from }} to {{ report.period?.to }}
                    <span v-if="report.total_revenue !== undefined" class="ml-2 font-semibold text-slate-900">
                        Total: £{{ formatNumber(report.total_revenue) }}
                    </span>
                </div>

                <div v-if="report.products?.length === 0" class="bg-white rounded-xl shadow-sm p-12 text-center text-slate-500">
                    No products sold by this employee in the selected period.
                </div>

                <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden min-w-0">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[600px]">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-700">Product</th>
                                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-700">Customer</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-700">Qty</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-700">Unit Price</th>
                                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-700">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                <tr v-for="(p, i) in report.products" :key="i" class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ p.product_name }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <router-link
                                            v-if="p.customer_id"
                                            :to="`/customers/${p.customer_id}`"
                                            class="text-blue-600 hover:text-blue-800 hover:underline"
                                        >
                                            {{ p.customer_name }}
                                        </router-link>
                                        <span v-else class="text-slate-600">
                                            {{ p.customer_name }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-right text-slate-700">{{ p.quantity }}</td>
                                    <td class="px-4 py-3 text-sm text-right text-slate-700">£{{ formatNumber(p.unit_price) }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium text-slate-900">£{{ formatNumber(p.total_price) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </template>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const selectedEmployeeId = ref('');

// Helpers to avoid UTC shifting issues
const formatMonth = (date) => {
    const d = date instanceof Date ? date : new Date(date);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    return `${y}-${m}`;
};

const selectedMonth = ref(formatMonth(new Date()));
const loading = ref(false);
const employees = ref([]);
const report = ref({
    employee_name: null,
    period: { from: null, to: null },
    products: [],
    total_revenue: 0,
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

const loadEmployees = async () => {
    try {
        const res = await axios.get('/api/users', { params: { for_sales_report: 1 } });
        employees.value = res.data.data || res.data || [];
    } catch (e) {
        console.error('Failed to load employees:', e);
        employees.value = [];
    }
};

const loadData = async () => {
    if (!selectedEmployeeId.value) return;
    loading.value = true;
    try {
        const [y, m] = selectedMonth.value.split('-').map(Number);
        // First day of the selected month (local)
        const from = `${y}-${String(m).padStart(2, '0')}-01`;
        // Last day of the selected month (local) – use year/month/day parts, not ISO
        const endDate = new Date(y, m, 0); // day 0 of next month = last day of current
        const to = `${endDate.getFullYear()}-${String(endDate.getMonth() + 1).padStart(2, '0')}-${String(
            endDate.getDate()
        ).padStart(2, '0')}`;
        const res = await axios.get('/api/reporting/products-sold-by-employee', {
            params: { agent_id: selectedEmployeeId.value, from, to },
        });
        report.value = res.data || {};
    } catch (e) {
        console.error('Failed to load report:', e);
        report.value = { products: [], total_revenue: 0 };
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    loadEmployees();
});
</script>
