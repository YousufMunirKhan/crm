<template>
    <div class="w-full min-w-0 max-w-7xl mx-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between min-w-0">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Monthly Expense Report</h1>
                <p class="text-sm text-slate-600 mt-1">Detailed breakdown of expenses by currency and category</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 sm:items-end w-full lg:w-auto min-w-0">
                <div class="w-full sm:w-auto min-w-0">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Select Month</label>
                    <input
                        v-model="reportMonth"
                        type="month"
                        @change="loadMonthlyReport"
                        class="w-full sm:w-auto min-w-0 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>
                <button
                    v-if="monthlyReport"
                    @click="exportMonthlyReport"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center justify-center gap-2 touch-manipulation w-full sm:w-auto shrink-0"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export CSV
                </button>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex items-center justify-center py-12">
            <div class="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full"></div>
        </div>

        <!-- Report Content -->
        <div v-else-if="monthlyReport" class="space-y-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="text-blue-100 text-sm font-medium mb-2">Total Expenses</div>
                    <div class="text-3xl font-bold">{{ monthlyReport.total_count }}</div>
                    <div class="text-blue-200 text-xs mt-2">Expenses this month</div>
                </div>
                <div v-if="monthlyReport.by_currency?.GBP" class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="text-green-100 text-sm font-medium mb-2">Total (GBP)</div>
                    <div class="text-3xl font-bold">£{{ formatNumber(monthlyReport.by_currency.GBP.total) }}</div>
                    <div class="text-green-200 text-xs mt-2">{{ monthlyReport.by_currency.GBP.count }} expenses</div>
                </div>
                <div v-if="monthlyReport.by_currency?.PKR" class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
                    <div class="text-purple-100 text-sm font-medium mb-2">Total (PKR)</div>
                    <div class="text-3xl font-bold">₨{{ formatNumber(monthlyReport.by_currency.PKR.total) }}</div>
                    <div class="text-purple-200 text-xs mt-2">{{ monthlyReport.by_currency.PKR.count }} expenses</div>
                </div>
            </div>

            <!-- Currency Breakdown -->
            <div v-if="monthlyReport.by_currency && Object.keys(monthlyReport.by_currency).length > 0" class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-semibold text-slate-900 mb-4">Breakdown by Currency</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="(data, currency) in monthlyReport.by_currency"
                        :key="currency"
                        class="p-4 border-2 rounded-lg"
                        :class="currency === 'GBP' ? 'border-green-200 bg-green-50' : 'border-purple-200 bg-purple-50'"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <div class="font-semibold text-slate-900">{{ currency }}</div>
                            <div class="text-2xl font-bold" :class="currency === 'GBP' ? 'text-green-700' : 'text-purple-700'">
                                {{ currency === 'PKR' ? '₨' : '£' }}{{ formatNumber(data.total) }}
                            </div>
                        </div>
                        <div class="text-sm text-slate-600">{{ data.count }} expenses</div>
                        <div class="mt-3 pt-3 border-t border-slate-200">
                            <div class="text-xs text-slate-500">Average per expense:</div>
                            <div class="text-sm font-medium text-slate-700">
                                {{ currency === 'PKR' ? '₨' : '£' }}{{ formatNumber(data.total / (data.count || 1)) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Breakdown -->
            <div v-if="monthlyReport.by_category && Object.keys(monthlyReport.by_category).length > 0" class="bg-white rounded-xl shadow-sm p-6">
                <h2 class="text-xl font-semibold text-slate-900 mb-4">Breakdown by Category</h2>
                <div class="space-y-4">
                    <div
                        v-for="(categoryData, category) in monthlyReport.by_category"
                        :key="category"
                        class="border border-slate-200 rounded-lg p-4 hover:shadow-md transition-shadow"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-lg font-semibold text-slate-900">{{ category || 'Uncategorized' }}</h3>
                            <div class="text-sm text-slate-500">
                                {{ Object.values(categoryData).reduce((sum, d) => sum + d.count, 0) }} expenses
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div
                                v-for="(data, currency) in categoryData"
                                :key="currency"
                                class="p-3 bg-slate-50 rounded-lg"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-slate-700">{{ currency }}</div>
                                        <div class="text-xs text-slate-500 mt-1">{{ data.count }} expenses</div>
                                    </div>
                                    <div class="text-lg font-bold text-slate-900">
                                        {{ currency === 'PKR' ? '₨' : '£' }}{{ formatNumber(data.total) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- All Expenses Table -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-slate-900">All Expenses</h2>
                    <div class="text-sm text-slate-600">
                        Total: {{ monthlyReport.expenses.length }} expenses
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase">Reason</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase">Category</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase">Currency</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-slate-700 uppercase">Added By</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr
                                v-for="expense in monthlyReport.expenses"
                                :key="expense.id"
                                class="hover:bg-slate-50"
                            >
                                <td class="px-4 py-3 text-sm text-slate-900">{{ formatDate(expense.date) }}</td>
                                <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ expense.reason }}</td>
                                <td class="px-4 py-3">
                                    <span v-if="expense.category" class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">
                                        {{ expense.category }}
                                    </span>
                                    <span v-else class="text-xs text-slate-400">-</span>
                                </td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-900">
                                    {{ expense.currency === 'PKR' ? '₨' : '£' }}{{ formatNumber(expense.amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-1 rounded text-xs font-medium"
                                        :class="expense.currency === 'PKR' ? 'bg-purple-100 text-purple-700' : 'bg-green-100 text-green-700'"
                                    >
                                        {{ expense.currency }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600 max-w-xs truncate">
                                    {{ expense.description || '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">{{ expense.creator?.name || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-if="monthlyReport.expenses.length === 0" class="text-center py-8 text-slate-400 text-sm">
                    No expenses found for this month
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-else class="bg-white rounded-xl shadow-sm p-12 text-center">
            <svg class="w-16 h-16 text-slate-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <h3 class="text-lg font-semibold text-slate-900 mb-2">No Report Data</h3>
            <p class="text-sm text-slate-600">Select a month to view the expense report</p>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const loading = ref(false);
const monthlyReport = ref(null);
const reportMonth = ref(route.query.month || new Date().toISOString().slice(0, 7));

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num || 0);
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const loadMonthlyReport = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/hr/expenses/report/monthly', {
            params: { month: reportMonth.value },
        });
        monthlyReport.value = data;
        
        // Update URL without reloading
        router.replace({ query: { month: reportMonth.value } });
    } catch (error) {
        console.error('Failed to load monthly report:', error);
        toast.error('Failed to load monthly report. Please try again.');
    } finally {
        loading.value = false;
    }
};

const exportMonthlyReport = () => {
    if (!monthlyReport.value || !monthlyReport.value.expenses || monthlyReport.value.expenses.length === 0) {
        toast.error('No expenses to export');
        return;
    }

    try {
        const filename = `expenses_monthly_report_${reportMonth.value.replace('-', '_')}.csv`;
        
        const columns = [
            { key: 'date', label: 'Date' },
            { key: 'reason', label: 'Reason' },
            { key: 'category', label: 'Category' },
            { key: 'amount', label: 'Amount' },
            { key: 'currency', label: 'Currency' },
            { key: 'description', label: 'Description' },
            { key: 'creator.name', label: 'Added By' },
        ];
        
        exportCSV(monthlyReport.value.expenses, columns, filename);
        toast.success('Monthly report exported successfully!');
    } catch (error) {
        console.error('Failed to export monthly report:', error);
        toast.error('Failed to export monthly report. Please try again.');
    }
};

onMounted(() => {
    if (reportMonth.value) {
        loadMonthlyReport();
    }
});
</script>

