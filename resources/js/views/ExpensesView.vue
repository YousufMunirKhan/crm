<template>
    <div class="max-w-7xl mx-auto p-6 space-y-6">
        <div class="flex justify-between items-center">
            <h1 class="text-2xl font-bold text-slate-900">Expense Management</h1>
            <div class="flex gap-2">
                <button
                    @click="downloadTemplate"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm"
                >
                    📥 Download Template
                </button>
                <button
                    @click="triggerFileInput"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm"
                >
                    📤 Import Expenses
                </button>
                <input
                    ref="fileInput"
                    type="file"
                    accept=".csv"
                    @change="handleFileSelect"
                    class="hidden"
                />
                <button
                    @click="openExpenseForm"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800"
                >
                    + Add Expense
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-600">From:</label>
                    <input
                        v-model="filters.from_date"
                        type="date"
                        class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-600">To:</label>
                    <input
                        v-model="filters.to_date"
                        type="date"
                        class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-600">Category:</label>
                    <select
                        v-model="filters.category"
                        class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                    >
                        <option value="">All Categories</option>
                        <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-600">Month:</label>
                    <input
                        v-model="filters.month"
                        type="month"
                        class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <label class="text-sm text-slate-600">Status:</label>
                    <select
                        v-model="filters.status"
                        class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                    >
                        <option value="">All</option>
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>
                <button
                    @click="loadExpenses(1)"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm hover:bg-slate-800"
                >
                    Apply Filters
                </button>
                <button
                    @click="resetFilters"
                    class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg text-sm hover:bg-slate-50"
                >
                    Reset
                </button>
                <router-link
                    :to="{ name: 'expenses-monthly-report', query: { month: filters.month || new Date().toISOString().slice(0, 7) } }"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700"
                >
                    Monthly Report
                </router-link>
            </div>
        </div>

        <!-- Expenses List -->
        <div class="bg-white rounded-xl shadow-sm p-6">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
                <div class="flex items-center gap-3">
                    <h3 class="text-lg font-semibold text-slate-900">Expenses</h3>
                    <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            class="rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                            :checked="allOnPageSelected"
                            @change="toggleSelectAllOnPage"
                        />
                        Select page
                    </label>
                    <span v-if="selectedExpenseIds.length" class="text-sm text-slate-600">
                        {{ selectedExpenseIds.length }} selected
                    </span>
                    <button
                        v-if="selectedOpenExpenseIds.length"
                        type="button"
                        @click="bulkCloseSelected"
                        class="px-3 py-1.5 text-sm bg-amber-600 text-white rounded-lg hover:bg-amber-700"
                    >
                        Close selected ({{ selectedOpenExpenseIds.length }})
                    </button>
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
                    <button
                        @click="exportExpenses"
                        class="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700 flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Export CSV
                    </button>
                </div>
            </div>
            <div v-if="expenses.length === 0" class="text-center py-8 text-slate-400 text-sm">
                No expenses found
            </div>
            <div v-else class="space-y-3">
                <div
                    v-for="expense in expenses"
                    :key="expense.id"
                    class="flex items-center justify-between gap-3 p-4 bg-slate-50 rounded-lg hover:bg-slate-100 cursor-pointer"
                    @click="openExpenseForm(expense)"
                >
                    <div class="flex items-start gap-3 shrink-0" @click.stop>
                        <input
                            type="checkbox"
                            class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                            :checked="selectedExpenseIds.includes(expense.id)"
                            @change="toggleExpenseSelected(expense.id)"
                        />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium text-slate-900">{{ expense.reason }}</span>
                            <span
                                class="text-xs font-medium px-2 py-0.5 rounded-full"
                                :class="(expense.status || 'open') === 'closed' ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-800'"
                            >
                                {{ (expense.status || 'open') === 'closed' ? 'Closed' : 'Open' }}
                            </span>
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
                            <span v-if="expense.category" class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">
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
            <div v-if="pagination" class="mt-4">
                <Pagination
                    v-if="pagination.last_page > 1"
                    :pagination="pagination"
                    @page-change="loadExpenses"
                />
                <div v-else-if="pagination.total > 0" class="text-center py-4 text-sm text-slate-600 border-t border-slate-200 bg-white rounded-b-xl">
                    <p class="font-medium">
                        Showing {{ expenses.length }} of {{ pagination.total }} expense{{ pagination.total !== 1 ? 's' : '' }}
                    </p>
                    <p v-if="pagination.total <= pagination.per_page" class="text-xs text-slate-500 mt-1">
                        All expenses are displayed on this page
                    </p>
                </div>
            </div>
        </div>

        <!-- Expense Form Modal -->
        <div
            v-if="showExpenseForm"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="closeExpenseForm"
        >
            <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <h2 class="text-xl font-semibold text-slate-900 mb-4">
                    {{ editingExpense && editingExpense.id ? 'Edit Expense' : 'Add Expense' }}
                </h2>
                <form @submit.prevent="saveExpense" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Date *</label>
                        <input
                            v-model="expenseForm.date"
                            type="date"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Amount *</label>
                            <input
                                v-model.number="expenseForm.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Currency *</label>
                            <select
                                v-model="expenseForm.currency"
                                required
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            >
                                <option value="GBP">GBP (£)</option>
                                <option value="PKR">PKR (₨)</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Reason *</label>
                        <input
                            v-model="expenseForm.reason"
                            type="text"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            placeholder="e.g., Office Supplies, Travel, etc."
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Category *</label>
                        <select
                            v-model="expenseForm.category"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="">Select Category...</option>
                            <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                        <textarea
                            v-model="expenseForm.description"
                            rows="3"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            placeholder="Additional details..."
                        ></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select
                            v-model="expenseForm.status"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="open">Open (pending reimbursement / outstanding)</option>
                            <option value="closed">Closed (received / settled)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Documents</label>
                        <p class="text-xs text-slate-500 mb-2">Upload receipts, invoices, or other files (PDF, images, Office, CSV — max 20 MB each).</p>
                        <input
                            ref="attachmentInputRef"
                            type="file"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"
                            class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200"
                            @change="onAttachmentFilesSelected"
                        />
                        <ul v-if="pendingAttachmentFiles.length" class="mt-2 text-xs text-slate-600 space-y-1">
                            <li v-for="(f, i) in pendingAttachmentFiles" :key="i" class="flex justify-between gap-2">
                                <span class="truncate">{{ f.name }}</span>
                                <button type="button" class="text-red-600 shrink-0" @click="removePendingFile(i)">Remove</button>
                            </li>
                        </ul>
                        <div v-if="editingExpense?.attachments?.length" class="mt-3 pt-3 border-t border-slate-200">
                            <p class="text-xs font-medium text-slate-700 mb-2">Attached files</p>
                            <ul class="space-y-2">
                                <li
                                    v-for="att in editingExpense.attachments"
                                    :key="att.id"
                                    class="flex items-center justify-between gap-2 text-sm"
                                >
                                    <a
                                        :href="att.url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-blue-600 hover:underline truncate"
                                        @click.stop
                                    >
                                        {{ att.original_name }}
                                    </a>
                                    <button
                                        type="button"
                                        class="text-xs text-red-600 shrink-0"
                                        @click.stop="deleteAttachment(att)"
                                    >
                                        Delete
                                    </button>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div v-if="expenseError" class="text-sm text-red-600 bg-red-50 p-3 rounded">
                        {{ expenseError }}
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <button
                            type="button"
                            @click="closeExpenseForm"
                            class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="savingExpense"
                            class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                        >
                            {{ savingExpense ? 'Saving...' : (editingExpense ? 'Update' : 'Create') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Import Results Modal -->
        <div
            v-if="showImportModal"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
            @click.self="closeImportModal"
        >
            <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-slate-200">
                    <h2 class="text-xl font-bold text-slate-900">Import Results</h2>
                </div>
                <div class="p-6">
                    <div v-if="importing" class="text-center py-8">
                        <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900 mb-4"></div>
                        <p class="text-slate-600">Importing expenses...</p>
                    </div>
                    <div v-else-if="importResult">
                        <div class="mb-4 p-4 rounded-lg" :class="importResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
                            <p class="font-semibold" :class="importResult.success ? 'text-green-800' : 'text-red-800'">
                                {{ importResult.message || (importResult.success ? 'Import completed successfully!' : 'Import failed') }}
                            </p>
                            <div class="mt-2 text-sm" :class="importResult.success ? 'text-green-700' : 'text-red-700'">
                                <p>Imported: {{ importResult.imported || 0 }} expense(s)</p>
                                <p v-if="importResult.skipped > 0">Skipped: {{ importResult.skipped }} row(s)</p>
                            </div>
                        </div>
                        <div v-if="importResult.errors && importResult.errors.length > 0" class="mt-4">
                            <h3 class="font-semibold text-slate-900 mb-2">Errors:</h3>
                            <div class="max-h-64 overflow-y-auto overflow-x-auto border border-slate-200 rounded-lg">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Row</th>
                                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Error</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-slate-200">
                                        <tr v-for="(error, idx) in importResult.errors" :key="idx">
                                            <td class="px-4 py-2 text-sm text-slate-900">{{ error.row }}</td>
                                            <td class="px-4 py-2 text-sm text-red-600">{{ error.error }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-6 border-t border-slate-200 flex justify-end gap-2">
                    <button
                        @click="closeImportModal"
                        class="px-4 py-2 bg-slate-200 text-slate-700 rounded-lg hover:bg-slate-300"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import Pagination from '@/components/Pagination.vue';
import { useToastStore } from '@/stores/toast';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';

const toast = useToastStore();

const expenses = ref([]);
const pagination = ref(null);
const showExpenseForm = ref(false);
const editingExpense = ref(null);
const savingExpense = ref(false);
const expenseError = ref(null);
const fileInput = ref(null);
const showImportModal = ref(false);
const importing = ref(false);
const importResult = ref(null);
const selectedExpenseIds = ref([]);
const pendingAttachmentFiles = ref([]);
const attachmentInputRef = ref(null);

const filters = ref({
    from_date: '',
    to_date: '',
    category: '',
    month: '',
    status: '',
});

const expenseForm = ref({
    date: new Date().toISOString().split('T')[0],
    amount: 0,
    currency: 'GBP',
    reason: '',
    description: '',
    category: '',
    status: 'open',
});

const categories = ref([
    'Office',
    'Travel',
    'Marketing',
    'Utilities',
    'Equipment',
    'Software',
    'Training',
    'Other',
]);

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
        
        // Debug log
        console.log('Pagination set:', pagination.value, 'Expenses count:', expenses.value.length);
    } catch (error) {
        console.error('Failed to load expenses:', error);
        toast.error('Failed to load expenses. Please try again.');
    }
};

const openExpenseForm = (expense = null) => {
    pendingAttachmentFiles.value = [];
    if (attachmentInputRef.value) attachmentInputRef.value.value = '';

    editingExpense.value = expense ? { ...expense, attachments: expense.attachments ? [...expense.attachments] : [] } : null;

    if (expense && expense.id) {
        expenseForm.value = {
            date: expense.date,
            amount: parseFloat(expense.amount),
            currency: expense.currency || 'GBP',
            reason: expense.reason,
            description: expense.description || '',
            category: expense.category || '',
            status: expense.status || 'open',
        };
    } else {
        editingExpense.value = null;
        expenseForm.value = {
            date: new Date().toISOString().split('T')[0],
            amount: 0,
            currency: 'GBP',
            reason: '',
            description: '',
            category: '',
            status: 'open',
        };
    }
    expenseError.value = null;
    showExpenseForm.value = true;
};

const closeExpenseForm = () => {
    showExpenseForm.value = false;
    editingExpense.value = null;
    pendingAttachmentFiles.value = [];
    if (attachmentInputRef.value) attachmentInputRef.value.value = '';
    expenseForm.value = {
        date: new Date().toISOString().split('T')[0],
        amount: 0,
        currency: 'GBP',
        reason: '',
        description: '',
        category: '',
        status: 'open',
    };
    expenseError.value = null;
};

const onAttachmentFilesSelected = (event) => {
    const picked = Array.from(event.target.files || []);
    if (picked.length) {
        pendingAttachmentFiles.value = [...pendingAttachmentFiles.value, ...picked];
    }
    event.target.value = '';
};

const removePendingFile = (index) => {
    pendingAttachmentFiles.value.splice(index, 1);
};

const deleteAttachment = async (att) => {
    if (!editingExpense.value?.id) return;
    try {
        await axios.delete(`/api/hr/expenses/${editingExpense.value.id}/attachments/${att.id}`);
        const next = (editingExpense.value.attachments || []).filter((a) => a.id !== att.id);
        editingExpense.value = { ...editingExpense.value, attachments: next };
        const row = expenses.value.find((e) => e.id === editingExpense.value.id);
        if (row) row.attachments = [...next];
        toast.success('File removed');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Could not remove file');
    }
};

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

const saveExpense = async () => {
    savingExpense.value = true;
    expenseError.value = null;

    try {
        const payload = { ...expenseForm.value };

        if (editingExpense.value && editingExpense.value.id) {
            await axios.put(`/api/hr/expenses/${editingExpense.value.id}`, payload);
            if (pendingAttachmentFiles.value.length > 0) {
                const fd = new FormData();
                pendingAttachmentFiles.value.forEach((f) => fd.append('attachments[]', f));
                await axios.post(`/api/hr/expenses/${editingExpense.value.id}/attachments`, fd, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
            }
        } else if (pendingAttachmentFiles.value.length > 0) {
            const fd = new FormData();
            Object.entries(payload).forEach(([key, val]) => {
                if (val === null || val === undefined) return;
                fd.append(key, typeof val === 'number' ? String(val) : val);
            });
            pendingAttachmentFiles.value.forEach((f) => fd.append('attachments[]', f));
            await axios.post('/api/hr/expenses', fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
        } else {
            await axios.post('/api/hr/expenses', payload);
        }
        closeExpenseForm();
        await loadExpenses(pagination.value?.current_page || 1);
        toast.success('Expense saved successfully!');
    } catch (error) {
        if (error.response?.data?.errors) {
            expenseError.value = Object.values(error.response.data.errors).flat().join(', ');
        } else if (error.response?.data?.message) {
            expenseError.value = error.response.data.message;
        } else {
            expenseError.value = 'Failed to save expense. Please try again.';
        }
        console.error('Failed to save expense:', error);
    } finally {
        savingExpense.value = false;
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

