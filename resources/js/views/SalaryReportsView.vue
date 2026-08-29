<template>
    <ListingPageShell
        title="Salary reports"
        subtitle="Aggregate payslips by month and employee — export matches the current filter set."
        :badge="salaryReportsBadge"
    >
        <template #actions>
            <BaseButton variant="outline" block-mobile :disabled="loading" @click="exportReport">
                <template #icon><ArrowDownTrayIcon class="icon" aria-hidden="true" /></template>
                Export CSV
            </BaseButton>
        </template>

        <template #filters>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="form-label" for="salaryreportsview-from-month">From month</label>
                    <input id="salaryreportsview-from-month" v-model="filters.from_month" type="month" class="form-input" />
                </div>
                <div>
                    <label class="form-label" for="salaryreportsview-to-month">To month</label>
                    <input id="salaryreportsview-to-month" v-model="filters.to_month" type="month" class="form-input" />
                </div>
                <div>
                    <label class="form-label" for="salaryreportsview-employee">Employee</label>
                    <!-- Native select: user_id is a number and goes straight into the request params. -->
                    <select id="salaryreportsview-employee" v-model="filters.user_id" class="form-select">
                        <option value="">All employees</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }} ({{ user.role?.name || 'N/A' }})
                        </option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="salaryreportsview-currency">Currency</label>
                    <select id="salaryreportsview-currency" v-model="filters.currency" class="form-select">
                        <option value="">All currencies</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="PKR">PKR (Rs)</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <BaseButton variant="primary" :loading="loading" @click="loadReport">
                    <template #icon><ChartBarIcon class="icon" aria-hidden="true" /></template>
                    {{ loading ? 'Loading…' : 'Generate report' }}
                </BaseButton>
                <BaseButton variant="outline" @click="resetFilters">
                    <template #icon><ArrowPathIcon class="icon" aria-hidden="true" /></template>
                    Reset
                </BaseButton>
            </div>
        </template>

        <div v-if="loading" class="px-5 py-14 flex justify-center text-slate-500" aria-busy="true">
            <span class="spinner" role="status" aria-label="Loading" />
        </div>

        <div v-if="report && !loading" class="grid grid-cols-1 md:grid-cols-4 gap-4 px-3 sm:px-5">
            <StatCard label="Total salaries" :value="report.summary.total_salaries" tone="neutral">
                <template #icon><DocumentTextIcon class="icon" aria-hidden="true" /></template>
            </StatCard>
            <StatCard label="Total employees" :value="report.summary.total_employees" tone="primary">
                <template #icon><UsersIcon class="icon" aria-hidden="true" /></template>
            </StatCard>
            <StatCard label="Total GBP" :value="`£${formatNumber(report.summary.total_gbp)}`" tone="success">
                <template #icon><CurrencyPoundIcon class="icon" aria-hidden="true" /></template>
            </StatCard>
            <StatCard label="Total PKR" :value="`Rs${formatNumber(report.summary.total_pkr)}`" tone="primary">
                <template #icon><BanknotesIcon class="icon" aria-hidden="true" /></template>
            </StatCard>
        </div>

        <div v-if="report && !loading" class="mx-3 sm:mx-5 mb-4 card card-body">
            <h2 class="card-title mb-4">Monthly breakdown</h2>
            <div class="table-wrap">
                <table class="table">
                    <caption class="sr-only">Salary totals for each month in the selected range</caption>
                    <thead class="table-thead">
                        <tr>
                            <th scope="col" class="table-th">Month</th>
                            <th scope="col" class="table-th-num">Count</th>
                            <th scope="col" class="table-th-num">Total GBP</th>
                            <th scope="col" class="table-th-num">Total PKR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(data, month) in report.monthly" :key="month" class="table-row">
                            <td class="table-td-strong">
                                {{ formatMonth(month) }}
                            </td>
                            <td class="table-td-num">{{ data.count }}</td>
                            <td class="table-td-num text-success-700 font-semibold">
                                £{{ formatNumber(data.total_gbp) }}
                            </td>
                            <td class="table-td-num text-primary-700 font-semibold">
                                Rs{{ formatNumber(data.total_pkr) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- By Employee -->
        <div v-if="report && !loading" class="mx-3 sm:mx-5 mb-4 card card-body">
            <h2 class="card-title mb-4">By employee</h2>
            <div class="table-wrap">
                <table class="table">
                    <caption class="sr-only">Salary totals for each employee in the selected range</caption>
                    <thead class="table-thead">
                        <tr>
                            <th scope="col" class="table-th">Employee name</th>
                            <th scope="col" class="table-th">Designation</th>
                            <th scope="col" class="table-th-num">Total salaries</th>
                            <th scope="col" class="table-th-num">Total GBP</th>
                            <th scope="col" class="table-th-num">Total PKR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="employee in report.by_employee" :key="employee.user_id" class="table-row">
                            <td class="table-td-strong">
                                {{ employee.user_name }}
                            </td>
                            <td class="table-td">{{ employee.user_role }}</td>
                            <td class="table-td-num">{{ employee.total_salaries }}</td>
                            <td class="table-td-num text-success-700 font-semibold">
                                £{{ formatNumber(employee.total_gbp) }}
                            </td>
                            <td class="table-td-num text-primary-700 font-semibold">
                                Rs{{ formatNumber(employee.total_pkr) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="report && !loading" class="mx-3 sm:mx-5 mb-4 card card-body">
            <h2 class="card-title mb-4">Detailed salary list</h2>
            <div class="table-wrap">
                <table class="table">
                    <caption class="sr-only">Every payslip matching the current filters</caption>
                    <thead class="table-thead">
                        <tr>
                            <th scope="col" class="table-th">Payslip #</th>
                            <th scope="col" class="table-th">Employee</th>
                            <th scope="col" class="table-th">Designation</th>
                            <th scope="col" class="table-th">Month</th>
                            <th scope="col" class="table-th-num">Base salary</th>
                            <th scope="col" class="table-th-num">Allowances</th>
                            <th scope="col" class="table-th-num">Net salary</th>
                            <th scope="col" class="table-th text-center">Currency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="salary in report.salaries" :key="salary.id" class="table-row">
                            <td class="table-td">
                                PS-{{ String(salary.id).padStart(4, '0') }}
                            </td>
                            <td class="table-td-strong">{{ salary.user_name }}</td>
                            <td class="table-td">{{ salary.user_role }}</td>
                            <td class="table-td">{{ formatMonth(salary.month) }}</td>
                            <td class="table-td-num">
                                {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.base_salary) }}
                            </td>
                            <td class="table-td-num">
                                {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.allowances || 0) }}
                            </td>
                            <td
                                class="table-td-num font-semibold"
                                :class="salary.currency === 'GBP' ? 'text-success-700' : 'text-primary-700'"
                            >
                                {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.net_salary) }}
                            </td>
                            <td class="table-td text-center">{{ salary.currency }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import {
    ArrowDownTrayIcon,
    ArrowPathIcon,
    BanknotesIcon,
    ChartBarIcon,
    CurrencyPoundIcon,
    DocumentTextIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseButton, StatCard } from '@/components/base';

const toast = useToastStore();
const loading = ref(false);
const report = ref(null);
const users = ref([]);

const filters = ref({
    from_month: '',
    to_month: '',
    user_id: '',
    currency: '',
});

const salaryReportsBadge = computed(() => {
    if (loading.value || !report.value?.summary?.total_salaries) return null;
    const n = report.value.summary.total_salaries;
    return `${n} ${n === 1 ? 'slip' : 'slips'}`;
});

const loadUsers = async () => {
    try {
        const response = await axios.get('/api/users', { params: { per_page: 1000 } });
        users.value = response.data.data || response.data;
    } catch (error) {
        console.error('Failed to load users:', error);
    }
};

const loadReport = async () => {
    loading.value = true;
    try {
        const params = {};
        if (filters.value.from_month) params.from_month = filters.value.from_month;
        if (filters.value.to_month) params.to_month = filters.value.to_month;
        if (filters.value.user_id) params.user_id = filters.value.user_id;
        if (filters.value.currency) params.currency = filters.value.currency;

        const response = await axios.get('/api/hr/salaries/report', { params });
        report.value = response.data;
    } catch (error) {
        console.error('Failed to load report:', error);
        toast.error('Failed to load salary report');
    } finally {
        loading.value = false;
    }
};

const resetFilters = () => {
    filters.value = {
        from_month: '',
        to_month: '',
        user_id: '',
        currency: '',
    };
    report.value = null;
};

const exportReport = async () => {
    try {
        const params = {};
        if (filters.value.from_month) params.from_month = filters.value.from_month;
        if (filters.value.to_month) params.to_month = filters.value.to_month;
        if (filters.value.user_id) params.user_id = filters.value.user_id;
        if (filters.value.currency) params.currency = filters.value.currency;

        const queryString = new URLSearchParams(params).toString();
        window.location.href = `/api/hr/salaries/export?${queryString}`;
    } catch (error) {
        console.error('Failed to export report:', error);
        toast.error('Failed to export report');
    }
};

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(num || 0);
};

const formatMonth = (month) => {
    if (!month) return '';
    const [year, monthNum] = month.split('-');
    const date = new Date(year, parseInt(monthNum) - 1);
    return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
};

const getCurrencySymbol = (currency) => {
    return currency === 'PKR' ? 'Rs' : '£';
};

onMounted(() => {
    loadUsers();
    // Load current month by default
    const now = new Date();
    filters.value.from_month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    filters.value.to_month = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
    loadReport();
});
</script>

