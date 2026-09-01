<template>
    <ListingPageShell
        title="Expense management"
        subtitle="What the company has spent, and what is still open."
        :badge="expensesBadge"
    >
        <template #actions>
            <div class="flex flex-wrap gap-2 w-full sm:w-auto justify-stretch sm:justify-end">
                <BaseButton variant="outline" class="flex-1 min-w-[10rem] sm:flex-initial" @click="downloadTemplate">
                    <template #icon><ArrowDownTrayIcon class="icon-sm" aria-hidden="true" /></template>
                    Download template
                </BaseButton>
                <BaseButton variant="outline" class="flex-1 min-w-[10rem] sm:flex-initial" @click="triggerFileInput">
                    <template #icon><ArrowUpTrayIcon class="icon-sm" aria-hidden="true" /></template>
                    Import CSV
                </BaseButton>
                <input ref="fileInput" type="file" accept=".csv" class="hidden" aria-hidden="true" tabindex="-1" @change="handleFileSelect" />
                <BaseButton
                    variant="primary"
                    :to="{ name: 'expense-create' }"
                    class="flex-1 min-w-[10rem] sm:flex-initial"
                >
                    <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                    Add expense
                </BaseButton>
            </div>
        </template>

        <template #filters>
            <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end">
                <div>
                    <label class="listing-label" for="expensesview-month">Month</label>
                    <input id="expensesview-month" v-model="filters.month" type="month" class="form-input w-full sm:w-44" />
                </div>
                <div>
                    <label class="listing-label" for="expensesview-category">Category</label>
                    <select id="expensesview-category" v-model="filters.category" class="form-select w-full sm:w-44">
                        <option value="">All categories</option>
                        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                    </select>
                </div>
                <div>
                    <label class="listing-label" for="expensesview-status">Status</label>
                    <select id="expensesview-status" v-model="filters.status" class="form-select w-full sm:w-36">
                        <option value="">All</option>
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <BaseButton variant="soft" @click="loadExpenses(1)">Apply</BaseButton>
                <BaseButton variant="outline" @click="resetFilters">Reset</BaseButton>
                <BaseButton
                    variant="outline"
                    :to="{ name: 'expenses-monthly-report', query: { month: filters.month || new Date().toISOString().slice(0, 7) } }"
                >
                    Monthly report
                </BaseButton>

                <!-- A month covers almost every question anybody asks of this
                     page, so the two date fields stay out of the way until
                     somebody wants a range that is not a month. -->
                <details class="w-full">
                    <summary class="cursor-pointer text-xs text-slate-600 touch-manipulation">Custom date range</summary>
                    <div class="mt-2 flex flex-col gap-3 sm:flex-row sm:items-end">
                        <div>
                            <label class="listing-label" for="expensesview-from">From</label>
                            <input id="expensesview-from" v-model="filters.from_date" type="date" class="form-input w-full sm:w-40" />
                        </div>
                        <div>
                            <label class="listing-label" for="expensesview-to">To</label>
                            <input id="expensesview-to" v-model="filters.to_date" type="date" class="form-input w-full sm:w-40" />
                        </div>
                    </div>
                </details>
            </div>
        </template>

        <!--
            What an owner opens this page to find out, before any list.

            There was none of this: the page opened straight onto ten rows, and
            the only figure was "Total (PKR)" summed from those ten rows with
            nothing to say so. On live data it read Rs118,600 against a real
            total of Rs639,896 - under a fifth of the spend, labelled as the
            spend.
        -->
        <div v-if="summary" class="grid grid-cols-2 gap-3 px-4 pt-4 sm:px-5 lg:grid-cols-4">
            <div
                v-for="card in summaryCards"
                :key="card.label"
                class="rounded-card border p-3 sm:p-4"
                :class="card.tone ?? 'border-slate-200 bg-white'"
            >
                <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                <p class="mt-1 text-xl font-bold tabular-nums text-slate-900 sm:text-2xl">{{ card.value }}</p>
                <p class="mt-1 text-[11px] leading-snug text-slate-600">{{ card.help }}</p>
            </div>
        </div>

        <!-- Where the money goes, biggest first. -->
        <div v-if="summary?.by_category?.length" class="px-4 pt-3 sm:px-5">
            <div class="rounded-card border border-slate-200 bg-white px-4 py-3">
                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">
                    By category, across everything matching your filter
                </p>
                <ul class="space-y-1.5">
                    <li
                        v-for="row in summary.by_category"
                        :key="`${row.category}-${row.currency}`"
                        class="flex items-center gap-3"
                    >
                        <span class="w-28 shrink-0 truncate text-sm text-slate-700">{{ row.category }}</span>
                        <span class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                            <span class="block h-full rounded-full bg-primary-500" :style="{ width: `${categoryShare(row)}%` }" />
                        </span>
                        <span class="w-32 shrink-0 text-right text-sm tabular-nums text-slate-900">
                            {{ money(row.total, row.currency) }}
                        </span>
                        <span class="w-16 shrink-0 text-right text-xs tabular-nums text-slate-500">
                            {{ row.count }}
                        </span>
                    </li>
                </ul>
            </div>
        </div>

        <div class="px-4 sm:px-5 pt-4">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                <div class="flex items-center gap-3">
                    <h3 class="card-title">Expenses</h3>
                    <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            class="form-checkbox"
                            :checked="allOnPageSelected"
                            @change="toggleSelectAllOnPage"
                        />
                        Select page
                    </label>
                    <span v-if="selectedExpenseIds.length" class="text-sm text-slate-600">
                        {{ selectedExpenseIds.length }} selected
                    </span>
                    <BaseButton
                        v-if="selectedOpenExpenseIds.length"
                        variant="soft"
                        size="sm"
                        @click="bulkCloseSelected"
                    >
                        Close selected ({{ selectedOpenExpenseIds.length }})
                    </BaseButton>
                </div>
                <div class="flex items-center gap-4">
                    <!-- Named for what it covers. It used to sum the ten rows
                         on screen and call itself the total. -->
                    <div v-if="summary?.by_currency?.length" class="text-sm text-slate-600">
                        <span v-for="(cur, i) in summary.by_currency" :key="cur.currency">
                            <span v-if="i" class="mx-1 text-slate-300">·</span>
                            All {{ cur.count }} matching:
                            <span class="font-bold text-slate-900">{{ money(cur.total, cur.currency) }}</span>
                        </span>
                    </div>
                    <BaseButton variant="outline" size="sm" @click="exportExpenses">
                        <template #icon><ArrowDownTrayIcon class="icon-sm" aria-hidden="true" /></template>
                        Export CSV
                    </BaseButton>
                </div>
            </div>
            <div v-if="loading" class="px-5 py-12 text-center text-slate-500 text-sm" aria-busy="true">
                <span class="spinner" role="status" aria-label="Loading" />
                <span class="ml-2 align-middle">Loading expenses…</span>
            </div>
            <EmptyState
                v-else-if="expenses.length === 0"
                heading="No expenses found"
                description="Adjust the filters above, or add your first expense."
            >
                <template #icon><CurrencyPoundIcon class="icon" aria-hidden="true" /></template>
            </EmptyState>
            <div v-else class="space-y-3">
                <div
                    v-for="expense in expenses"
                    :key="expense.id"
                    class="flex items-center justify-between gap-3 p-4 rounded-card border border-slate-200 bg-slate-50/40 hover:border-slate-300 cursor-pointer"
                    @click="goToEdit(expense.id)"
                >
                    <div class="flex items-start gap-3 shrink-0" @click.stop>
                        <input
                            type="checkbox"
                            class="mt-1 form-checkbox"
                            :aria-label="`Select expense ${expense.reason}`"
                            :checked="selectedExpenseIds.includes(expense.id)"
                            @change="toggleExpenseSelected(expense.id)"
                        />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-slate-900">{{ expense.reason }}</span>
                            <BaseBadge :tone="(expense.status || 'open') === 'closed' ? 'neutral' : 'warning'">
                                {{ (expense.status || 'open') === 'closed' ? 'Closed' : 'Open' }}
                            </BaseBadge>
                            <span
                                v-if="expense.attachments && expense.attachments.length"
                                class="text-xs text-slate-500"
                                title="Attached files"
                            >
                                {{ expense.attachments.length }} file{{ expense.attachments.length !== 1 ? 's' : '' }}
                            </span>
                        </div>
                        <div class="text-xs text-slate-500 mt-1">
                            {{ formatDate(expense.date) }}
                            <span v-if="expense.category" class="ml-2 px-2 py-0.5 bg-primary-100 text-primary-800 rounded text-xs">
                                {{ expense.category }}
                            </span>
                        </div>
                        <div v-if="expense.description && expense.description !== expense.reason"
                             class="mt-1 text-sm text-slate-600">
                            {{ expense.description }}
                        </div>
                        <!-- Only worth saying when more than one person books
                             expenses. All 43 of these were entered by the same
                             person, so it was 43 identical lines of noise. -->
                        <div v-if="manyCreators" class="mt-1 text-xs text-slate-500">
                            Added by {{ expense.creator?.name }}
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-lg font-bold text-slate-900">
                            {{ expense.currency === 'PKR' ? '₨' : '£' }}{{ formatNumber(expense.amount) }}
                        </div>
                        <div class="text-xs text-slate-500">{{ expense.currency }}</div>
                    </div>
                </div>
            </div>
        </div>

        <template #pagination>
            <Pagination
                v-if="pagination"
                :pagination="pagination"
                embedded
                result-label="expenses"
                singular-label="expense"
                @page-change="loadExpenses"
            />
        </template>
    </ListingPageShell>

        <!-- Import Results -->
        <BaseModal v-model="showImportModal" title="Import Results" size="md" @close="closeImportModal">
            <div v-if="importing" class="text-center py-8">
                <span class="spinner w-8 h-8 text-slate-900 mb-4" role="status" aria-label="Importing" />
                <p class="text-slate-600">Importing expenses...</p>
            </div>
            <div v-else-if="importResult">
                <div :class="['callout', importResult.success ? 'callout-success' : 'callout-danger']" role="status">
                    <p class="font-semibold">
                        {{ importResult.message || (importResult.success ? 'Import completed successfully!' : 'Import failed') }}
                    </p>
                    <div class="mt-2 text-sm">
                        <p>Imported: {{ importResult.imported || 0 }} expense(s)</p>
                        <p v-if="importResult.skipped > 0">Skipped: {{ importResult.skipped }} row(s)</p>
                    </div>
                </div>
                <div v-if="importResult.errors && importResult.errors.length > 0" class="mt-4">
                    <h3 class="subsection-title">Errors:</h3>
                    <div class="max-h-64 overflow-y-auto table-wrap border border-slate-200 rounded-card">
                        <table class="table">
                            <caption class="sr-only">Rows that could not be imported</caption>
                            <thead class="table-thead">
                                <tr>
                                    <th scope="col" class="table-th">Row</th>
                                    <th scope="col" class="table-th">Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(error, idx) in importResult.errors" :key="idx">
                                    <td class="table-td">{{ error.row }}</td>
                                    <td class="table-td text-danger-700">{{ error.error }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <template #actions>
                <BaseButton variant="outline" block-mobile @click="closeImportModal">Close</BaseButton>
            </template>
        </BaseModal>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import {
    ArrowDownTrayIcon,
    ArrowUpTrayIcon,
    CurrencyPoundIcon,
    PlusIcon,
} from '@heroicons/vue/24/outline';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseBadge, BaseButton, BaseModal, EmptyState } from '@/components/base';
import { useToastStore } from '@/stores/toast';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';

// Distinguishes "still fetching" from "nothing to show".
const loading = ref(true);

const toast = useToastStore();
const router = useRouter();

const expenses = ref([]);

/**
 * Totals for everything the filter matches, worked out by the server.
 *
 * The page used to add up the ten rows it was showing and print the result as
 * "Total (PKR)". On live data that read Rs118,600 against a real total of
 * Rs639,896 - under a fifth of the spend, with nothing on screen to say it was
 * only page one.
 */
const summary = ref(null);
const pagination = ref(null);
const fileInput = ref(null);
const showImportModal = ref(false);
const importing = ref(false);
const importResult = ref(null);
const selectedExpenseIds = ref([]);

const filters = ref({
    from_date: '',
    to_date: '',
    category: '',
    month: '',
    status: '',
});

const categories = [
    'Office',
    'Travel',
    'Marketing',
    'Utilities',
    'Equipment',
    'Software',
    'Training',
    'Other',
];

const expensesBadge = computed(() => {
    const t = pagination.value?.total;
    if (t == null || t === 0) return null;
    return `${t} ${t === 1 ? 'expense' : 'expenses'}`;
});

const selectedOpenExpenseIds = computed(() =>
    selectedExpenseIds.value.filter((id) => {
        const e = expenses.value.find((x) => x.id === id);
        return e && (e.status === 'open' || !e.status);
    }),
);

const allOnPageSelected = computed(() => {
    if (!expenses.value.length) return false;
    return expenses.value.every((e) => selectedExpenseIds.value.includes(e.id));
});

/** Money, in the currency it was actually booked in. */
function money(amount, currency) {
    const symbol = currency === 'PKR' ? '₨' : '£';

    return symbol + formatNumber(amount);
}

/**
 * The four figures worth having before the list.
 *
 * Currencies are never added together: office costs are booked in rupees and
 * everything else in pounds, and there is no exchange rate anywhere in this
 * product that could combine them honestly.
 */
const summaryCards = computed(() => {
    if (! summary.value) return [];

    const line = (rows) => (rows?.length
        ? rows.map((r) => money(r.total, r.currency)).join(' · ')
        : '—');

    const open = summary.value.by_currency?.filter((c) => c.open_count > 0) ?? [];
    const openCount = open.reduce((n, c) => n + c.open_count, 0);

    return [
        {
            label: 'This month',
            value: line(summary.value.this_month),
            help: countOf(summary.value.this_month) + ' recorded so far',
        },
        {
            label: 'Last month',
            value: line(summary.value.last_month),
            help: countOf(summary.value.last_month) + ' in total',
        },
        {
            label: 'Still open',
            value: open.length ? open.map((c) => money(c.open_total, c.currency)).join(' · ') : '—',
            help: openCount ? `${openCount} not closed off yet` : 'everything is closed off',
            tone: openCount ? 'border-warning-200 bg-warning-50/60' : 'border-success-200 bg-success-50/50',
        },
        {
            label: 'Matching your filter',
            value: line(summary.value.by_currency),
            help: countOf(summary.value.by_currency) + ' in the current view',
        },
    ];
});

function countOf(rows) {
    const n = (rows ?? []).reduce((t, r) => t + Number(r.count || 0), 0);

    return `${n} ${n === 1 ? 'expense' : 'expenses'}`;
}

/** Bar width for the category breakdown, relative to the biggest line. */
function categoryShare(row) {
    const rows = (summary.value?.by_category ?? []).filter((r) => r.currency === row.currency);
    const top = Math.max(...rows.map((r) => Number(r.total || 0)), 0);

    return top > 0 ? Math.max(2, Math.round((Number(row.total || 0) / top) * 100)) : 0;
}

/** "Added by" only earns its line when more than one person books expenses. */
const manyCreators = computed(() =>
    new Set(expenses.value.map((e) => e.creator?.id).filter(Boolean)).size > 1);

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num || 0);
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const loadExpenses = async (page = 1) => {
    loading.value = true;
    try {
        const params = { per_page: 10, page };
        if (filters.value.from_date) params.from_date = filters.value.from_date;
        if (filters.value.to_date) params.to_date = filters.value.to_date;
        if (filters.value.category) params.category = filters.value.category;
        if (filters.value.month) params.month = filters.value.month;
        if (filters.value.status) params.status = filters.value.status;

        const { data } = await axios.get('/api/hr/expenses', { params });
        expenses.value = data.data || [];
        summary.value = data.summary || null;
        
        // Ensure pagination is set correctly - Laravel paginate() returns these fields directly
        // Check if data has pagination properties (Laravel paginator response)
        if (data.current_page !== undefined) {
            pagination.value = {
                current_page: data.current_page,
                last_page: data.last_page,
                per_page: data.per_page,
                total: data.total,
            };
        } else {
            // Fallback: if no pagination data, create it from the data array
            pagination.value = {
                current_page: 1,
                last_page: 1,
                per_page: 10,
                total: expenses.value.length,
            };
        }
        
    } catch (error) {
        console.error('Failed to load expenses:', error);
        toast.error('Failed to load expenses. Please try again.');
    } finally {
        loading.value = false;
    }
};

function goToEdit(id) {
    router.push({ name: 'expense-edit', params: { id: String(id) } });
}

const toggleExpenseSelected = (id) => {
    const idx = selectedExpenseIds.value.indexOf(id);
    if (idx === -1) selectedExpenseIds.value.push(id);
    else selectedExpenseIds.value.splice(idx, 1);
};

const toggleSelectAllOnPage = () => {
    const pageIds = expenses.value.map((e) => e.id);
    if (allOnPageSelected.value) {
        selectedExpenseIds.value = selectedExpenseIds.value.filter((id) => !pageIds.includes(id));
    } else {
        selectedExpenseIds.value = [...new Set([...selectedExpenseIds.value, ...pageIds])];
    }
};

const bulkCloseSelected = async () => {
    const ids = selectedOpenExpenseIds.value;
    if (!ids.length) {
        toast.error('Select at least one open expense to close');
        return;
    }
    try {
        const { data } = await axios.post('/api/hr/expenses/bulk-close', { ids });
        toast.success(data.message || 'Expenses closed');
        selectedExpenseIds.value = [];
        await loadExpenses(pagination.value?.current_page || 1);
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to close expenses');
    }
};

const resetFilters = () => {
    filters.value = {
        from_date: '',
        to_date: '',
        category: '',
        month: '',
        status: '',
    };
    loadExpenses(1);
};

const triggerFileInput = () => {
    fileInput.value?.click();
};

const handleFileSelect = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    // Validate file type
    if (!file.name.endsWith('.csv')) {
        toast.error('Please select a CSV file');
        return;
    }

    showImportModal.value = true;
    importing.value = true;
    importResult.value = null;

    const formData = new FormData();
    formData.append('file', file);

    try {
        const { data } = await axios.post('/api/hr/expenses/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        importResult.value = data;
        importing.value = false;

        if (data.success) {
            toast.success(data.message || `Successfully imported ${data.imported} expense(s)`);
            // Reload expenses list
            loadExpenses(pagination.value?.current_page || 1);
        } else {
            toast.error(data.message || 'Import failed');
        }
    } catch (error) {
        importing.value = false;
        const errorMessage = error.response?.data?.message || error.message || 'Failed to import expenses';
        importResult.value = {
            success: false,
            message: errorMessage,
            imported: 0,
            skipped: 0,
            errors: [],
        };
        toast.error(errorMessage);
    } finally {
        // Reset file input
        if (fileInput.value) {
            fileInput.value.value = '';
        }
    }
};

const downloadTemplate = () => {
    // Simple direct download - no authentication needed
    window.location.href = '/api/hr/expenses/template/download';
};

const closeImportModal = () => {
    showImportModal.value = false;
    importResult.value = null;
    importing.value = false;
};

const exportExpenses = async () => {
    try {
        const params = { per_page: 10000 };
        if (filters.value.from_date) params.from_date = filters.value.from_date;
        if (filters.value.to_date) params.to_date = filters.value.to_date;
        if (filters.value.category) params.category = filters.value.category;
        if (filters.value.month) params.month = filters.value.month;
        if (filters.value.status) params.status = filters.value.status;

        const { data } = await axios.get('/api/hr/expenses', { params });
        const allExpenses = data.data || [];
        
        if (allExpenses.length === 0) {
            toast.error('No expenses to export');
            return;
        }

        const columns = [
            { key: 'date', label: 'Date' },
            { key: 'reason', label: 'Reason' },
            { key: 'category', label: 'Category' },
            { key: 'status', label: 'Status' },
            { key: 'amount', label: 'Amount' },
            { key: 'currency', label: 'Currency' },
            { key: 'description', label: 'Description' },
            { key: 'creator.name', label: 'Added By' },
        ];
        
        const filename = `expenses_export_${new Date().toISOString().split('T')[0]}.csv`;
        exportCSV(allExpenses, columns, filename);
        toast.success('Expenses exported successfully!');
    } catch (error) {
        console.error('Failed to export expenses:', error);
        toast.error('Failed to export expenses. Please try again.');
    }
};

onMounted(() => {
    loadExpenses();
});
</script>

