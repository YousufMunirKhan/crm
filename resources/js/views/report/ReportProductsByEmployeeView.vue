<template>
    <ListingPageShell
        title="Products sold by employee"
        subtitle="Per-employee product lines and revenue for a selected month."
        :badge="productsReportBadge"
    >
        <template #filters>
            <div class="flex flex-col lg:flex-row lg:flex-wrap gap-3 lg:items-end">
                <div class="w-full lg:w-auto lg:min-w-[12rem]">
                    <label class="listing-label">Employee</label>
                    <select v-model="selectedEmployeeId" class="listing-input" @change="loadData">
                        <option value="">Select employee</option>
                        <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                            {{ emp.name }}
                        </option>
                    </select>
                </div>
                <div class="w-full sm:w-48">
                    <label class="listing-label">Month</label>
                    <select v-model="selectedMonth" class="listing-input" @change="loadData">
                        <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                </div>
                <button type="button" class="listing-btn-primary" :disabled="loading" @click="loadData">
                    {{ loading ? 'Loading…' : 'Refresh' }}
                </button>
            </div>
        </template>

        <div v-if="!selectedEmployeeId" class="mx-3 sm:mx-5 mb-4 rounded-xl border border-slate-200 bg-slate-50/50 p-8 text-center text-slate-500 text-sm">
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

                <div v-if="report.products?.length === 0" class="px-5 py-12 text-center text-slate-500 text-sm">
                    No products sold by this employee in the selected period.
                </div>

                <div v-else class="overflow-x-auto min-w-0">
                    <table class="w-full min-w-[600px]">
                        <thead class="listing-thead">
                            <tr>
                                <th class="listing-th">Product</th>
                                <th class="listing-th">Customer</th>
                                <th class="listing-th text-right">Qty</th>
                                <th class="listing-th text-right">Unit price</th>
                                <th class="listing-th text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(p, i) in report.products" :key="i" class="listing-row">
                                <td class="listing-td-strong">{{ p.product_name }}</td>
                                <td class="listing-td">
                                    <router-link
                                        v-if="p.customer_id"
                                        :to="`/customers/${p.customer_id}`"
                                        class="listing-link-edit"
                                    >
                                        {{ p.customer_name }}
                                    </router-link>
                                    <span v-else class="text-slate-600">{{ p.customer_name }}</span>
                                </td>
                                <td class="listing-td text-right">{{ p.quantity }}</td>
                                <td class="listing-td text-right">£{{ formatNumber(p.unit_price) }}</td>
                                <td class="listing-td text-right font-semibold text-slate-900">£{{ formatNumber(p.total_price) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </template>
    </ListingPageShell>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';

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

const productsReportBadge = computed(() => {
    const n = report.value.products?.length;
    if (!selectedEmployeeId.value || !n) return null;
    return `${n} ${n === 1 ? 'line' : 'lines'}`;
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
