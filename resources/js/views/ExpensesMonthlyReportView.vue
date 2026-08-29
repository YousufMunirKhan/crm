<template>
    <ListingPageShell
        title="Monthly Expense Report"
        subtitle="Detailed breakdown of expenses by currency and category"
        :badge="monthlyReport ? `${monthlyReport.total_count} expenses` : null"
    >
        <template #actions>
            <BaseButton
                v-if="monthlyReport"
                variant="primary"
                type="button"
                block-mobile
                @click="exportMonthlyReport"
            >
                <template #icon>
                    <ArrowDownTrayIcon class="icon" aria-hidden="true" />
                </template>
                Export CSV
            </BaseButton>
        </template>

        <template #filters>
            <div class="w-full sm:w-auto min-w-0">
                <label class="form-label" for="expensesmonthlyreportview-select-month">Select Month</label>
                <input id="expensesmonthlyreportview-select-month"
                    v-model="reportMonth"
                    type="month"
                    @change="loadMonthlyReport"
                    class="form-input w-full sm:w-56 min-w-0"
                />
            </div>
        </template>

        <!-- Loading State -->
        <div v-if="loading" class="px-4 sm:px-6 py-8 space-y-3" aria-busy="true">
            <p class="sr-only">Loading monthly expense report…</p>
            <div class="skeleton-text w-1/3"></div>
            <div class="skeleton-text w-2/3"></div>
            <div class="skeleton-text w-1/2"></div>
            <div class="skeleton-text w-3/4"></div>
        </div>

        <!-- Report Content -->
        <div v-else-if="monthlyReport" class="px-4 sm:px-6 py-5 space-y-6">
            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <StatCard
                    label="Total Expenses"
                    :value="monthlyReport.total_count"
                    caption="Expenses this month"
                    tone="primary"
                >
                    <template #icon>
                        <DocumentTextIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard
                    v-if="monthlyReport.by_currency?.GBP"
                    label="Total (GBP)"
                    :value="`£${formatNumber(monthlyReport.by_currency.GBP.total)}`"
                    :caption="`${monthlyReport.by_currency.GBP.count} expenses`"
                    tone="success"
                >
                    <template #icon>
                        <BanknotesIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard
                    v-if="monthlyReport.by_currency?.PKR"
                    label="Total (PKR)"
                    :value="`₨${formatNumber(monthlyReport.by_currency.PKR.total)}`"
                    :caption="`${monthlyReport.by_currency.PKR.count} expenses`"
                    tone="primary"
                >
                    <template #icon>
                        <BanknotesIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
            </div>

            <!-- Currency Breakdown -->
            <BaseCard
                v-if="monthlyReport.by_currency && Object.keys(monthlyReport.by_currency).length > 0"
                title="Breakdown by Currency"
            >
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div
                        v-for="(data, currency) in monthlyReport.by_currency"
                        :key="currency"
                        class="p-4 border-2 rounded-card"
                        :class="currency === 'GBP' ? 'border-success-200 bg-success-50' : 'border-primary-200 bg-primary-50'"
                    >
                        <div class="flex items-center justify-between mb-2">
                            <div class="font-semibold text-slate-900">{{ currency }}</div>
                            <div class="text-2xl font-bold tabular-nums" :class="currency === 'GBP' ? 'text-success-800' : 'text-primary-800'">
                                {{ currency === 'PKR' ? '₨' : '£' }}{{ formatNumber(data.total) }}
                            </div>
                        </div>
                        <div class="text-sm text-slate-600 tabular-nums">{{ data.count }} expenses</div>
                        <div class="mt-3 pt-3 border-t border-slate-200">
                            <div class="text-xs text-slate-500">Average per expense:</div>
                            <div class="text-sm font-medium text-slate-700 tabular-nums">
                                {{ currency === 'PKR' ? '₨' : '£' }}{{ formatNumber(data.total / (data.count || 1)) }}
                            </div>
                        </div>
                    </div>
                </div>
            </BaseCard>

            <!-- Category Breakdown -->
            <BaseCard
                v-if="monthlyReport.by_category && Object.keys(monthlyReport.by_category).length > 0"
                title="Breakdown by Category"
            >
                <div class="space-y-4">
                    <div
                        v-for="(categoryData, category) in monthlyReport.by_category"
                        :key="category"
                        class="border border-slate-200 rounded-card p-4 hover:shadow-card-hover transition-shadow"
                    >
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-lg font-semibold text-slate-900">{{ category || 'Uncategorized' }}</h3>
                            <div class="text-sm text-slate-500 tabular-nums">
                                {{ Object.values(categoryData).reduce((sum, d) => sum + d.count, 0) }} expenses
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div
                                v-for="(data, currency) in categoryData"
                                :key="currency"
                                class="p-3 bg-slate-50 rounded-card"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <div class="text-sm font-medium text-slate-700">{{ currency }}</div>
                                        <div class="text-xs text-slate-500 mt-1 tabular-nums">{{ data.count }} expenses</div>
                                    </div>
                                    <div class="text-lg font-bold text-slate-900 tabular-nums">
                                        {{ currency === 'PKR' ? '₨' : '£' }}{{ formatNumber(data.total) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </BaseCard>

            <!-- All Expenses Table -->
            <BaseCard title="All Expenses" :padded="false">
                <template #actions>
                    <span class="text-sm text-slate-600 tabular-nums">Total: {{ monthlyReport.expenses.length }} expenses</span>
                </template>

                <div v-if="monthlyReport.expenses.length" class="table-wrap">
                    <table class="table">
                        <caption class="sr-only">All expenses for the selected month, with date, reason, category, amount, currency, description and who added them</caption>
                        <thead class="table-thead">
                            <tr>
                                <th scope="col" class="table-th">Date</th>
                                <th scope="col" class="table-th">Reason</th>
                                <th scope="col" class="table-th">Category</th>
                                <th scope="col" class="table-th-num">Amount</th>
                                <th scope="col" class="table-th">Currency</th>
                                <th scope="col" class="table-th">Description</th>
                                <th scope="col" class="table-th">Added By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="expense in monthlyReport.expenses"
                                :key="expense.id"
                                class="table-row"
                            >
                                <td class="table-td">{{ formatDate(expense.date) }}</td>
                                <td class="table-td-strong">{{ expense.reason }}</td>
                                <td class="table-td">
                                    <BaseBadge v-if="expense.category" tone="primary">{{ expense.category }}</BaseBadge>
                                    <span v-else class="text-xs text-slate-500">-</span>
                                </td>
                                <td class="table-td-num font-semibold text-slate-900">
                                    {{ expense.currency === 'PKR' ? '₨' : '£' }}{{ formatNumber(expense.amount) }}
                                </td>
                                <td class="table-td">
                                    <BaseBadge :tone="expense.currency === 'PKR' ? 'primary' : 'success'">{{ expense.currency }}</BaseBadge>
                                </td>
                                <td class="table-td max-w-xs truncate">
                                    {{ expense.description || '-' }}
                                </td>
                                <td class="table-td">{{ expense.creator?.name || '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <EmptyState
                    v-else
                    heading="No expenses found"
                    description="No expenses found for this month."
                >
                    <template #icon>
                        <DocumentTextIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>
            </BaseCard>
        </div>

        <!-- Empty State -->
        <EmptyState
            v-else
            heading="No Report Data"
            description="Select a month to view the expense report"
        >
            <template #icon>
                <DocumentTextIcon class="icon" aria-hidden="true" />
            </template>
        </EmptyState>
    </ListingPageShell>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseBadge, BaseButton, BaseCard, EmptyState, StatCard } from '@/components/base';
import { ArrowDownTrayIcon, BanknotesIcon, DocumentTextIcon } from '@heroicons/vue/24/outline';

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
