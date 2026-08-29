<template>
    <ListingPageShell
        title="Products sold by employee"
        subtitle="Per-employee product lines and revenue for a selected month."
        :badge="productsReportBadge"
    >
        <template #filters>
            <div class="flex flex-col lg:flex-row lg:flex-wrap gap-3 lg:items-end">
                <div class="w-full lg:w-auto lg:min-w-[12rem]">
                    <label class="form-label" for="reportproductsbyemployeeview-employee">Employee</label>
                    <select id="reportproductsbyemployeeview-employee" v-model="selectedEmployeeId" class="form-select" @change="loadData">
                        <option value="">Select employee</option>
                        <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                            {{ emp.name }}
                        </option>
                    </select>
                </div>
                <div class="w-full sm:w-48">
                    <label class="form-label" for="reportproductsbyemployeeview-month">Month</label>
                    <select id="reportproductsbyemployeeview-month" v-model="selectedMonth" class="form-select" @change="loadData">
                        <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                    </select>
                </div>
                <BaseButton
                    variant="primary"
                    type="button"
                    :disabled="loading"
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
            description="Select an employee from the dropdown to view products they sold this month."
        >
            <template #icon>
                <UsersIcon class="icon" aria-hidden="true" />
            </template>
        </EmptyState>

        <template v-else>
            <div v-if="loading" class="px-3 sm:px-5 py-8 space-y-3" aria-busy="true">
                <p class="sr-only">Loading products sold…</p>
                <div class="skeleton-text w-1/3"></div>
                <div class="skeleton-text w-2/3"></div>
                <div class="skeleton-text w-1/2"></div>
                <div class="skeleton-text w-3/4"></div>
            </div>

            <template v-else>
                <div v-if="report.employee_name" class="px-3 sm:px-5 py-4 text-sm text-slate-600">
                    <strong>{{ report.employee_name }}</strong> — {{ report.period?.from }} to {{ report.period?.to }}
                    <span v-if="report.total_revenue !== undefined" class="ml-2 font-semibold text-slate-900 tabular-nums">
                        Total: £{{ formatNumber(report.total_revenue) }}
                    </span>
                </div>

                <EmptyState
                    v-if="report.products?.length === 0"
                    heading="No products sold"
                    description="No products sold by this employee in the selected period."
                >
                    <template #icon>
                        <CubeIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>

                <div v-else class="table-wrap min-w-0">
                    <table class="table min-w-[600px]">
                        <caption class="sr-only">Products sold by the selected employee, with quantity, unit price and total</caption>
                        <thead class="table-thead">
                            <tr>
                                <th scope="col" class="table-th">Product</th>
                                <th scope="col" class="table-th">Customer</th>
                                <th scope="col" class="table-th-num">Qty</th>
                                <th scope="col" class="table-th-num">Unit price</th>
                                <th scope="col" class="table-th-num">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(p, i) in report.products" :key="i" class="table-row">
                                <td class="table-td-strong">{{ p.product_name }}</td>
                                <td class="table-td">
                                    <router-link
                                        v-if="p.customer_id"
                                        :to="`/customers/${p.customer_id}`"
                                        class="link"
                                    >
                                        {{ withCompany(p) }}
                                    </router-link>
                                    <span v-else class="text-slate-600">{{ withCompany(p) }}</span>
                                </td>
                                <td class="table-td-num">{{ p.quantity }}</td>
                                <td class="table-td-num">£{{ formatNumber(p.unit_price) }}</td>
                                <td class="table-td-num font-semibold text-slate-900">£{{ formatNumber(p.total_price) }}</td>
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
import { BaseButton, EmptyState } from '@/components/base';
import { ArrowPathIcon, CubeIcon, UsersIcon } from '@heroicons/vue/24/outline';
import { customerLabel } from '@/utils/customerLabel';

/** Contact and company on one line; these digests are dense. */
const withCompany = (row) => customerLabel(row, '');

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
