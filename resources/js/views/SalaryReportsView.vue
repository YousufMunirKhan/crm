<template>
    <ListingPageShell
        title="Salary reports"
        subtitle="Aggregate payslips by month and employee — export matches the current filter set."
        :badge="salaryReportsBadge"
    >
        <template #actions>
            <button
                type="button"
                :disabled="loading"
                class="listing-btn-primary w-full sm:w-auto disabled:opacity-50"
                @click="exportReport"
            >
                Export CSV
            </button>
        </template>

        <template #filters>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="listing-label">From month</label>
                    <input v-model="filters.from_month" type="month" class="listing-input" />
                </div>
                <div>
                    <label class="listing-label">To month</label>
                    <input v-model="filters.to_month" type="month" class="listing-input" />
                </div>
                <div>
                    <label class="listing-label">Employee</label>
                    <select v-model="filters.user_id" class="listing-input">
                        <option value="">All employees</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }} ({{ user.role?.name || 'N/A' }})
                        </option>
                    </select>
                </div>
                <div>
                    <label class="listing-label">Currency</label>
                    <select v-model="filters.currency" class="listing-input">
                        <option value="">All currencies</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="PKR">PKR (Rs)</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" :disabled="loading" class="listing-btn-primary disabled:opacity-50" @click="loadReport">
                    {{ loading ? 'Loading…' : 'Generate report' }}
                </button>
                <button type="button" class="listing-btn-outline" @click="resetFilters">Reset</button>
            </div>
        </template>

        <div v-if="loading" class="px-5 py-14 flex justify-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900"></div>
        </div>

        <div v-if="report && !loading" class="grid grid-cols-1 md:grid-cols-4 gap-4 px-3 sm:px-5">
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="text-sm text-slate-600 mb-1">Total Salaries</div>
                <div class="text-2xl font-bold text-slate-900">{{ report.summary.total_salaries }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="text-sm text-slate-600 mb-1">Total Employees</div>
                <div class="text-2xl font-bold text-slate-900">{{ report.summary.total_employees }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="text-sm text-slate-600 mb-1">Total GBP</div>
                <div class="text-2xl font-bold text-green-600">£{{ formatNumber(report.summary.total_gbp) }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="text-sm text-slate-600 mb-1">Total PKR</div>
                <div class="text-2xl font-bold text-blue-600">Rs{{ formatNumber(report.summary.total_pkr) }}</div>
            </div>
        </div>

        <div v-if="report && !loading" class="mx-3 sm:mx-5 mb-4 rounded-xl border border-slate-200 bg-slate-50/30 p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-slate-900 mb-4">Monthly Breakdown</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-200">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Month</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-slate-700">Count</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-slate-700">Total GBP</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-slate-700">Total PKR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(data, month) in report.monthly"
                            :key="month"
                            class="border-b border-slate-100 hover:bg-slate-50"
                        >
                            <td class="py-3 px-4 text-sm text-slate-900">
                                {{ formatMonth(month) }}
                            </td>
                            <td class="py-3 px-4 text-sm text-slate-600 text-right">{{ data.count }}</td>
                            <td class="py-3 px-4 text-sm text-green-600 text-right font-semibold">
                                £{{ formatNumber(data.total_gbp) }}
                            </td>
                            <td class="py-3 px-4 text-sm text-blue-600 text-right font-semibold">
                                Rs{{ formatNumber(data.total_pkr) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- By Employee -->
        <div v-if="report && !loading" class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-slate-900 mb-4">By Employee</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-200">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Employee Name</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Designation</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-slate-700">Total Salaries</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-slate-700">Total GBP</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-slate-700">Total PKR</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="employee in report.by_employee"
                            :key="employee.user_id"
                            class="border-b border-slate-100 hover:bg-slate-50"
                        >
                            <td class="py-3 px-4 text-sm text-slate-900 font-medium">
                                {{ employee.user_name }}
                            </td>
                            <td class="py-3 px-4 text-sm text-slate-600">{{ employee.user_role }}</td>
                            <td class="py-3 px-4 text-sm text-slate-600 text-right">{{ employee.total_salaries }}</td>
                            <td class="py-3 px-4 text-sm text-green-600 text-right font-semibold">
                                £{{ formatNumber(employee.total_gbp) }}
                            </td>
                            <td class="py-3 px-4 text-sm text-blue-600 text-right font-semibold">
                                Rs{{ formatNumber(employee.total_pkr) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="report && !loading" class="mx-3 sm:mx-5 mb-4 rounded-xl border border-slate-200 bg-slate-50/30 p-5 sm:p-6">
            <h2 class="text-lg font-semibold text-slate-900 mb-4">Detailed Salary List</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-slate-200">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Payslip #</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Employee</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Designation</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-slate-700">Month</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-slate-700">Base Salary</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-slate-700">Allowances</th>
                            <th class="text-right py-3 px-4 text-sm font-semibold text-slate-700">Net Salary</th>
                            <th class="text-center py-3 px-4 text-sm font-semibold text-slate-700">Currency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="salary in report.salaries"
                            :key="salary.id"
                            class="border-b border-slate-100 hover:bg-slate-50"
                        >
                            <td class="py-3 px-4 text-sm text-slate-600">
                                PS-{{ String(salary.id).padStart(4, '0') }}
                            </td>
                            <td class="py-3 px-4 text-sm text-slate-900 font-medium">{{ salary.user_name }}</td>
                            <td class="py-3 px-4 text-sm text-slate-600">{{ salary.user_role }}</td>
                            <td class="py-3 px-4 text-sm text-slate-600">{{ formatMonth(salary.month) }}</td>
                            <td class="py-3 px-4 text-sm text-slate-600 text-right">
                                {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.base_salary) }}
                            </td>
                            <td class="py-3 px-4 text-sm text-slate-600 text-right">
                                {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.allowances || 0) }}
                            </td>
                            <td class="py-3 px-4 text-sm font-semibold text-right"
                                :class="salary.currency === 'GBP' ? 'text-green-600' : 'text-blue-600'"
                            >
                                {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.net_salary) }}
                            </td>
                            <td class="py-3 px-4 text-sm text-slate-600 text-center">{{ salary.currency }}</td>
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
import { useToastStore } from '@/stores/toast';
import ListingPageShell from '@/components/ListingPageShell.vue';

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

