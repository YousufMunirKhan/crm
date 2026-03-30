<template>
    <div class="w-full min-w-0 max-w-7xl mx-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between min-w-0">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Salary Reports</h1>
                <p class="text-sm text-slate-600 mt-1">View and export salary reports by month and employee</p>
            </div>
            <button
                @click="exportReport"
                :disabled="loading"
                class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50 flex items-center justify-center gap-2 touch-manipulation w-full sm:w-auto"
            >
                <span>📥</span>
                Export CSV
            </button>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 min-w-0">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">From Month</label>
                    <input
                        v-model="filters.from_month"
                        type="month"
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">To Month</label>
                    <input
                        v-model="filters.to_month"
                        type="month"
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Employee</label>
                    <select
                        v-model="filters.user_id"
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Employees</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }} ({{ user.role?.name || 'N/A' }})
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Currency</label>
                    <select
                        v-model="filters.currency"
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">All Currencies</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="PKR">PKR (Rs)</option>
                    </select>
                </div>
            </div>
            <div class="mt-4 flex gap-3">
                <button
                    @click="loadReport"
                    :disabled="loading"
                    class="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
                >
                    {{ loading ? 'Loading...' : 'Generate Report' }}
                </button>
                <button
                    @click="resetFilters"
                    class="px-6 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 transition-colors"
                >
                    Reset
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900"></div>
        </div>

        <!-- Summary Cards -->
        <div v-if="report && !loading" class="grid grid-cols-1 md:grid-cols-4 gap-4">
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

        <!-- Monthly Breakdown -->
        <div v-if="report && !loading" class="bg-white rounded-xl shadow-sm p-6">
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

        <!-- Detailed Salary List -->
        <div v-if="report && !loading" class="bg-white rounded-xl shadow-sm p-6">
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
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

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

