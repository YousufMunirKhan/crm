<template>
    <ListingPageShell
        title="Expense management"
        subtitle="Import CSV, filter by period and category, and bulk-close open items — totals reflect the current page filter."
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
            <div class="flex flex-col xl:flex-row xl:flex-wrap gap-3 xl:items-end">
                <div>
                    <label class="listing-label" for="expensesview-from">From</label>
                    <input id="expensesview-from" v-model="filters.from_date" type="date" class="form-input w-full sm:w-40" />
                </div>
                <div>
                    <label class="listing-label" for="expensesview-to">To</label>
                    <input id="expensesview-to" v-model="filters.to_date" type="date" class="form-input w-full sm:w-40" />
                </div>
                <div>
                    <label class="listing-label" for="expensesview-category">Category</label>
                    <select id="expensesview-category" v-model="filters.category" class="form-select w-full sm:w-44">
                        <option value="">All categories</option>
                        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                    </select>
                </div>
                <div>
                    <label class="listing-label" for="expensesview-month">Month</label>
                    <input id="expensesview-month" v-model="filters.month" type="month" class="form-input w-full sm:w-44" />
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
                    class="w-full xl:w-auto"
                    :to="{ name: 'expenses-monthly-report', query: { month: filters.month || new Date().toISOString().slice(0, 7) } }"
                >
                    Monthly report
                </BaseButton>
            </div>
        </template>

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
                    <div class="flex gap-4 text-sm text-slate-600">
                        <div v-if="totalByCurrency.GBP > 0">
                            Total (GBP): <span class="font-bold text-slate-900">£{{ formatNumber(totalByCurrency.GBP) }}</span>
                        </div>
                        <div v-if="totalByCurrency.PKR > 0">
                            Total (PKR): <span class="font-bold text-slate-900">₨{{ formatNumber(totalByCurrency.PKR) }}</span>
                        </div>
                    </div>
                    <BaseButton variant="outline" size="sm" @click="exportExpenses">
                        <template #icon><ArrowDownTrayIcon class="icon-sm" aria-hidden="true" /></template>
                        Export CSV
                    </BaseButton>
                </div>
            </div>
            <EmptyState
                v-if="expenses.length === 0"
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
                        <div v-if="expense.description" class="text-sm text-slate-600 mt-1">
                            {{ expense.description }}
                        </div>
                        <div class="text-xs text-slate-500 mt-1">
                            Added by: {{ expense.creator?.name }}
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
                    <h3 class="font-semibold text-slate-900 mb-2">Errors:</h3>
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

const toast = useToastStore();
const router = useRouter();

const expenses = ref([]);
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

const totalByCurrency = computed(() => {
    const totals = { GBP: 0, PKR: 0 };
    expenses.value.forEach(exp => {
        if (exp.currency === 'GBP' || !exp.currency) {
            totals.GBP += parseFloat(exp.amount || 0);
        } else if (exp.currency === 'PKR') {
            totals.PKR += parseFloat(exp.amount || 0);
        }
    });
    return totals;
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num || 0);
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const loadExpenses = async (page = 1) => {
    try {
        const params = { per_page: 10, page };
        if (filters.value.from_date) params.from_date = filters.value.from_date;
        if (filters.value.to_date) params.to_date = filters.value.to_date;
        if (filters.value.category) params.category = filters.value.category;
        if (filters.value.month) params.month = filters.value.month;
        if (filters.value.status) params.status = filters.value.status;

        const { data } = await axios.get('/api/hr/expenses', { params });
        expenses.value = data.data || [];
        
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

