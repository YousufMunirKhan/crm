<template>
    <div class="w-full min-w-0 max-w-7xl mx-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Invoices</h1>
            <router-link
                to="/invoices/create"
                class="inline-flex justify-center px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors touch-manipulation w-full sm:w-auto text-center"
            >
                + Create Invoice
            </router-link>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 md:p-6 min-w-0">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4">
                <h2 class="text-lg font-semibold text-slate-900">Filters</h2>
                <button
                    @click="showFilters = !showFilters"
                    class="text-sm text-slate-600 hover:text-slate-900 flex items-center gap-1 touch-manipulation self-start sm:self-auto"
                >
                    <svg class="w-4 h-4" :class="{ 'rotate-180': showFilters }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                    {{ showFilters ? 'Hide' : 'Show' }} Filters
                </button>
            </div>

            <div v-show="showFilters" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                    <input
                        v-model="filters.search"
                        type="text"
                        placeholder="Invoice # or Customer..."
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @input="debounceSearch"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                    <select
                        v-model="filters.status"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @change="applyFilters"
                    >
                        <option value="">All Statuses</option>
                        <option value="draft">Draft</option>
                        <option value="sent">Sent</option>
                        <option value="partially_paid">Partially Paid</option>
                        <option value="paid">Paid</option>
                        <option value="overdue">Overdue</option>
                    </select>
                </div>
                <div v-if="isAdmin">
                    <label class="block text-xs font-medium text-slate-600 mb-1">Created By</label>
                    <select
                        v-model="filters.created_by"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @change="applyFilters"
                    >
                        <option value="">All Users</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Customer</label>
                    <select
                        v-model="filters.customer_id"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @change="applyFilters"
                    >
                        <option value="">All Customers</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">From Date</label>
                    <input
                        v-model="filters.from_date"
                        type="date"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @change="applyFilters"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">To Date</label>
                    <input
                        v-model="filters.to_date"
                        type="date"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        @change="applyFilters"
                    />
                </div>
            </div>

            <div v-if="hasActiveFilters" class="mt-4 flex flex-wrap gap-2">
                <span
                    v-for="(value, key) in activeFilterTags"
                    :key="key"
                    class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs"
                >
                    {{ key }}: {{ value }}
                    <button @click="removeFilter(key)" class="hover:text-blue-600">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>
            </div>

            <div v-if="showFilters" class="flex justify-end gap-2 mt-4">
                <button
                    @click="clearFilters"
                    class="px-4 py-2 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50"
                >
                    Clear Filters
                </button>
            </div>
        </div>

        <!-- Summary Cards -->
        <div v-if="summary" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 min-w-0">
                <div class="text-sm text-slate-600">Total Invoices</div>
                <div class="text-xl sm:text-2xl font-bold text-slate-900 mt-1 tabular-nums">{{ summary.total || 0 }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 min-w-0">
                <div class="text-sm text-slate-600">Total Amount</div>
                <div class="text-xl sm:text-2xl font-bold text-slate-900 mt-1 tabular-nums break-words">£{{ formatNumber(summary.total_amount || 0) }}</div>
            </div>
        </div>

        <!-- Invoices Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden min-w-0">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Invoice #</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Customer</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Date</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Total</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Status</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Created By</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-if="loading" class="text-center">
                            <td colspan="7" class="px-6 py-8 text-slate-500">Loading invoices...</td>
                        </tr>
                        <tr v-else-if="invoices.length === 0" class="text-center">
                            <td colspan="7" class="px-6 py-8 text-slate-500">No invoices found</td>
                        </tr>
                        <tr v-for="invoice in invoices" :key="invoice.id" class="hover:bg-slate-50">
                            <td class="px-4 md:px-6 py-4 text-sm font-medium text-slate-900">{{ invoice.invoice_number }}</td>
                            <td class="px-4 md:px-6 py-4 text-sm text-slate-900">{{ invoice.customer?.name || '-' }}</td>
                            <td class="px-4 md:px-6 py-4 text-sm text-slate-600">{{ formatDate(invoice.invoice_date) }}</td>
                            <td class="px-4 md:px-6 py-4 text-sm font-medium text-slate-900">£{{ formatNumber(invoice.total) }}</td>
                            <td class="px-4 md:px-6 py-4 text-sm">
                                <span class="px-2 py-1 rounded text-xs font-medium" :class="getStatusClass(invoice.status)">
                                    {{ formatStatus(invoice.status) }}
                                </span>
                            </td>
                            <td class="px-4 md:px-6 py-4 text-sm text-slate-600">{{ invoice.creator?.name || '-' }}</td>
                            <td class="px-4 md:px-6 py-4 text-sm">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        @click="openSendEmail(invoice)"
                                        class="text-indigo-600 hover:text-indigo-700 hover:underline text-xs"
                                    >
                                        Send email
                                    </button>
                                    <router-link
                                        :to="`/invoices/${invoice.id}/edit`"
                                        class="text-green-600 hover:text-green-700 hover:underline text-xs"
                                    >
                                        Edit
                                    </router-link>
                                    <button
                                        @click="generatePDF(invoice.id)"
                                        class="text-blue-600 hover:text-blue-700 hover:underline text-xs"
                                    >
                                        PDF
                                    </button>
                                    <button
                                        @click="openDeleteConfirm(invoice)"
                                        class="text-red-600 hover:text-red-700 hover:underline text-xs"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <Pagination
            v-if="pagination && pagination.last_page > 1"
            :pagination="pagination"
            @page-change="loadInvoices"
        />

        <!-- Send Invoice Email Modal -->
        <InvoiceSendEmailModal
            v-if="showSendEmailModal"
            :invoice="invoiceToSendEmail"
            :logo-url="publicSettings.logo_url"
            :company-name="publicSettings.company_name"
            @close="closeSendEmail"
            @sent="handleEmailSent"
        />

        <!-- Invoice Form Modal -->
        <InvoiceForm
            v-if="showForm"
            :invoice="selectedInvoice"
            @close="closeForm"
            @saved="handleSaved"
        />

        <!-- Delete Confirmation Modal -->
        <DeleteConfirm
            v-if="showDeleteConfirm"
            title="Delete Invoice"
            :message="`Are you sure you want to delete invoice ${invoiceToDelete?.invoice_number}?`"
            :loading="deleting"
            @confirm="confirmDelete"
            @cancel="closeDeleteConfirm"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import InvoiceForm from '@/components/InvoiceForm.vue';
import InvoiceSendEmailModal from '@/components/InvoiceSendEmailModal.vue';
import DeleteConfirm from '@/components/DeleteConfirm.vue';
import Pagination from '@/components/Pagination.vue';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';

const toast = useToastStore();
const auth = useAuthStore();
const isAdmin = computed(() => {
    const role = auth.user?.role?.name;
    return role === 'Admin' || role === 'System Admin' || role === 'Manager';
});
const invoices = ref([]);
const pagination = ref(null);
const summary = ref(null);
const filters = ref({
    search: '',
    status: '',
    created_by: '',
    customer_id: '',
    from_date: '',
    to_date: '',
});
const users = ref([]);
const customers = ref([]);
const showFilters = ref(false);
const showForm = ref(false);
const selectedInvoice = ref(null);
const showSendEmailModal = ref(false);
const invoiceToSendEmail = ref(null);
const publicSettings = ref({ logo_url: '', company_name: '' });
const showDeleteConfirm = ref(false);
const invoiceToDelete = ref(null);
const deleting = ref(false);
const loading = ref(false);
let searchTimeout = null;

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num || 0);
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
};

const formatStatus = (status) => {
    const statusMap = {
        draft: 'Draft',
        sent: 'Sent',
        partially_paid: 'Partially Paid',
        paid: 'Paid',
        overdue: 'Overdue',
    };
    return statusMap[status] || status;
};

const getStatusClass = (status) => {
    const classes = {
        paid: 'bg-green-100 text-green-800',
        partially_paid: 'bg-yellow-100 text-yellow-800',
        sent: 'bg-blue-100 text-blue-800',
        overdue: 'bg-red-100 text-red-800',
        draft: 'bg-slate-100 text-slate-800',
    };
    return classes[status] || 'bg-slate-100 text-slate-800';
};

const hasActiveFilters = computed(() => {
    return filters.value.search || 
           filters.value.status || 
           filters.value.created_by || 
           filters.value.customer_id || 
           filters.value.from_date || 
           filters.value.to_date;
});

const activeFilterTags = computed(() => {
    const tags = {};
    if (filters.value.search) tags['Search'] = filters.value.search;
    if (filters.value.status) tags['Status'] = formatStatus(filters.value.status);
    if (filters.value.created_by) {
        const user = users.value.find(u => u.id == filters.value.created_by);
        tags['Created By'] = user?.name || filters.value.created_by;
    }
    if (filters.value.customer_id) {
        const customer = customers.value.find(c => c.id == filters.value.customer_id);
        tags['Customer'] = customer?.name || filters.value.customer_id;
    }
    if (filters.value.from_date) tags['From'] = formatDate(filters.value.from_date);
    if (filters.value.to_date) tags['To'] = formatDate(filters.value.to_date);
    return tags;
});

const loadUsers = async () => {
    try {
        const { data } = await axios.get('/api/users', { params: { per_page: 1000 } });
        users.value = data.data || [];
    } catch (error) {
        console.error('Failed to load users:', error);
    }
};

const loadCustomers = async () => {
    try {
        const { data } = await axios.get('/api/customers', { params: { per_page: 1000 } });
        customers.value = data.data || [];
    } catch (error) {
        console.error('Failed to load customers:', error);
    }
};

const loadInvoices = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            page,
            per_page: 15,
            ...filters.value
        };
        
        // Remove empty filters
        Object.keys(params).forEach(key => {
            if (params[key] === '' || params[key] === null) {
                delete params[key];
            }
        });

        const { data } = await axios.get('/api/invoices', { params });
        invoices.value = data.data || [];
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page || 15,
            total: data.total || 0,
        };

        // Calculate summary
        if (invoices.value.length > 0) {
            summary.value = {
                total: data.total || 0,
                total_amount: invoices.value.reduce((sum, inv) => sum + parseFloat(inv.total || 0), 0),
            };
        }
    } catch (error) {
        console.error('Failed to load invoices:', error);
        toast.error('Failed to load invoices');
    } finally {
        loading.value = false;
    }
};

const debounceSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 500);
};

const applyFilters = () => {
    loadInvoices(1);
};

const clearFilters = () => {
    filters.value = {
        search: '',
        status: '',
        created_by: '',
        customer_id: '',
        from_date: '',
        to_date: '',
    };
    applyFilters();
};

const removeFilter = (key) => {
    const filterMap = {
        'Search': 'search',
        'Status': 'status',
        'Created By': 'created_by',
        'Customer': 'customer_id',
        'From': 'from_date',
        'To': 'to_date',
    };
    if (filterMap[key]) {
        filters.value[filterMap[key]] = '';
        applyFilters();
    }
};

const openSendEmail = (invoice) => {
    invoiceToSendEmail.value = invoice;
    showSendEmailModal.value = true;
};

const closeSendEmail = () => {
    showSendEmailModal.value = false;
    invoiceToSendEmail.value = null;
};

const handleEmailSent = () => {
    loadInvoices(pagination.value?.current_page || 1);
};

const openEditForm = (invoice) => {
    selectedInvoice.value = invoice;
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    selectedInvoice.value = null;
};

const handleSaved = () => {
    loadInvoices(pagination.value?.current_page || 1);
    closeForm();
};

const openDeleteConfirm = (invoice) => {
    invoiceToDelete.value = invoice;
    showDeleteConfirm.value = true;
};

const closeDeleteConfirm = () => {
    showDeleteConfirm.value = false;
    invoiceToDelete.value = null;
};

const confirmDelete = async () => {
    if (!invoiceToDelete.value) return;

    deleting.value = true;
    try {
        await axios.delete(`/api/invoices/${invoiceToDelete.value.id}`);
        closeDeleteConfirm();
        toast.success('Invoice deleted successfully');
        loadInvoices(pagination.value?.current_page || 1);
    } catch (error) {
        console.error('Failed to delete invoice:', error);
        toast.error('Failed to delete invoice. Please try again.');
    } finally {
        deleting.value = false;
    }
};

const generatePDF = async (id) => {
    try {
        const response = await axios.get(`/api/invoices/${id}/pdf`, { 
            responseType: 'blob',
            timeout: 30000
        });
        
        if (response.headers['content-type'] !== 'application/pdf' && !response.data.type.includes('pdf')) {
            const text = await response.data.text();
            throw new Error(text || 'Invalid PDF response');
        }
        
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `invoice-${id}.pdf`);
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
        toast.success('Invoice PDF downloaded successfully');
    } catch (error) {
        console.error('Failed to generate PDF:', error);
        let errorMessage = 'Failed to generate PDF. ';
        if (error.response) {
            if (error.response.status === 404) {
                errorMessage += 'Invoice not found.';
            } else if (error.response.status === 500) {
                errorMessage += 'Server error. Please check the logs.';
            } else {
                errorMessage += error.response.data?.message || `Error: ${error.response.status}`;
            }
        } else if (error.message) {
            errorMessage += error.message;
        } else {
            errorMessage += 'Please try again.';
        }
        toast.error(errorMessage);
    }
};

const loadPublicSettings = async () => {
    try {
        const { data } = await axios.get('/api/settings/public');
        publicSettings.value = {
            logo_url: data.logo_url ? (data.logo_url.startsWith('http') ? data.logo_url : (typeof window !== 'undefined' ? window.location.origin : '') + data.logo_url) : '',
            company_name: data.company_name || 'Company',
        };
    } catch (_) {
        publicSettings.value = { logo_url: '', company_name: 'Company' };
    }
};

onMounted(async () => {
    if (!auth.initialized) await auth.bootstrap();
    if (isAdmin.value) loadUsers();
    loadCustomers();
    loadPublicSettings();
    loadInvoices();
});
</script>
