<template>
    <ListingPageShell
        title="Invoices"
        subtitle="Create, filter, and send invoices — totals reflect the current filter set."
        :badge="invoicesBadge"
    >
        <template #actions>
            <router-link to="/invoices/create" class="listing-btn-accent w-full sm:w-auto text-center touch-manipulation">
                + Create invoice
            </router-link>
        </template>

        <template #filters>
            <div class="space-y-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Filters</h2>
                    <button
                        type="button"
                        @click="showFilters = !showFilters"
                        class="text-sm text-blue-600 hover:text-blue-800 flex items-center gap-1 touch-manipulation self-start sm:self-auto font-medium"
                    >
                        <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': showFilters }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                        {{ showFilters ? 'Hide' : 'Show' }} filters
                    </button>
                </div>

                <div v-show="showFilters" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="listing-label">Search</label>
                        <input
                            v-model="filters.search"
                            type="text"
                            placeholder="Invoice # or customer..."
                            class="listing-input"
                            @input="debounceSearch"
                        />
                    </div>
                    <div>
                        <label class="listing-label">Status</label>
                        <select v-model="filters.status" class="listing-input" @change="applyFilters">
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="sent">Sent</option>
                            <option value="partially_paid">Partially Paid</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                        </select>
                    </div>
                    <div v-if="isAdmin">
                        <label class="listing-label">Created by</label>
                        <select v-model="filters.created_by" class="listing-input" @change="applyFilters">
                            <option value="">All users</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="listing-label">Customer</label>
                        <select v-model="filters.customer_id" class="listing-input" @change="applyFilters">
                            <option value="">All customers</option>
                            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                {{ customer.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="listing-label">From date</label>
                        <input v-model="filters.from_date" type="date" class="listing-input" @change="applyFilters" />
                    </div>
                    <div>
                        <label class="listing-label">To date</label>
                        <input v-model="filters.to_date" type="date" class="listing-input" @change="applyFilters" />
                    </div>
                    <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-4">
                        <button type="button" class="listing-btn-primary" @click="applyFilters">Apply</button>
                        <button type="button" class="listing-btn-outline" @click="clearFilters">Clear</button>
                    </div>
                </div>

                <div v-if="hasActiveFilters" class="flex flex-wrap gap-2">
                    <span
                        v-for="(value, key) in activeFilterTags"
                        :key="key"
                        class="inline-flex items-center gap-1 px-2.5 py-1 bg-sky-50 text-sky-900 rounded-full text-xs font-medium ring-1 ring-sky-100"
                    >
                        {{ key }}: {{ value }}
                        <button type="button" @click="removeFilter(key)" class="hover:text-sky-700">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </span>
                </div>
            </div>
        </template>

        <template v-if="summary" #toolbar>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4 min-w-0">
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total invoices</div>
                    <div class="text-xl font-bold text-slate-900 mt-1 tabular-nums">{{ summary.total || 0 }}</div>
                </div>
                <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4 min-w-0">
                    <div class="text-xs font-medium text-slate-500 uppercase tracking-wide">Total amount</div>
                    <div class="text-xl font-bold text-slate-900 mt-1 tabular-nums break-words">£{{ formatNumber(summary.total_amount || 0) }}</div>
                </div>
            </div>
        </template>

        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[720px]">
                <thead class="listing-thead">
                    <tr>
                        <th class="listing-th">Invoice #</th>
                        <th class="listing-th">Customer</th>
                        <th class="listing-th">Date</th>
                        <th class="listing-th">Total</th>
                        <th class="listing-th">Status</th>
                        <th class="listing-th">Created By</th>
                        <th class="listing-th">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="7" class="listing-td text-center text-slate-500 py-10">Loading invoices...</td>
                    </tr>
                    <tr v-else-if="invoices.length === 0">
                        <td colspan="7" class="listing-td text-center text-slate-500 py-10">No invoices found</td>
                    </tr>
                    <tr v-for="invoice in invoices" :key="invoice.id" class="listing-row">
                        <td class="listing-td-strong">{{ invoice.invoice_number }}</td>
                        <td class="listing-td">{{ invoice.customer?.name || '—' }}</td>
                        <td class="listing-td text-slate-600">{{ formatDate(invoice.invoice_date) }}</td>
                        <td class="listing-td font-semibold text-slate-900">£{{ formatNumber(invoice.total) }}</td>
                        <td class="listing-td">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="getStatusClass(invoice.status)">
                                {{ formatStatus(invoice.status) }}
                            </span>
                        </td>
                        <td class="listing-td text-slate-600">{{ invoice.creator?.name || '—' }}</td>
                        <td class="listing-td">
                            <div class="flex flex-wrap gap-x-3 gap-y-1">
                                <button type="button" class="text-indigo-600 hover:text-indigo-800 font-medium text-sm" @click="openSendEmail(invoice)">
                                    Send email
                                </button>
                                <router-link :to="`/invoices/${invoice.id}/edit`" class="listing-link-edit">Edit</router-link>
                                <button type="button" class="listing-link-edit" @click="generatePDF(invoice.id)">PDF</button>
                                <button type="button" class="listing-link-delete" @click="openDeleteConfirm(invoice)">Delete</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="!loading && invoices.length" class="md:hidden space-y-3 px-3 pb-3">
            <div
                v-for="invoice in invoices"
                :key="`mobile-${invoice.id}`"
                class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-2"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="text-sm font-semibold text-slate-900">{{ invoice.invoice_number }}</div>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium" :class="getStatusClass(invoice.status)">
                        {{ formatStatus(invoice.status) }}
                    </span>
                </div>
                <div class="text-sm text-slate-600">Customer: {{ invoice.customer?.name || '—' }}</div>
                <div class="text-sm text-slate-600">Date: {{ formatDate(invoice.invoice_date) }}</div>
                <div class="text-sm font-semibold text-slate-900">Total: £{{ formatNumber(invoice.total) }}</div>
                <div class="text-sm text-slate-600">Created By: {{ invoice.creator?.name || '—' }}</div>
                <div class="flex flex-wrap gap-3 pt-1">
                    <button type="button" class="text-indigo-600 hover:text-indigo-800 font-medium text-sm" @click="openSendEmail(invoice)">Send email</button>
                    <router-link :to="`/invoices/${invoice.id}/edit`" class="listing-link-edit">Edit</router-link>
                    <button type="button" class="listing-link-edit" @click="generatePDF(invoice.id)">PDF</button>
                    <button type="button" class="listing-link-delete" @click="openDeleteConfirm(invoice)">Delete</button>
                </div>
            </div>
        </div>

        <template #pagination>
            <Pagination
                v-if="pagination"
                :pagination="pagination"
                embedded
                result-label="invoices"
                singular-label="invoice"
                @page-change="loadInvoices"
            />
        </template>
    </ListingPageShell>

    <InvoiceSendEmailModal
        v-if="showSendEmailModal"
        :invoice="invoiceToSendEmail"
        :logo-url="publicSettings.logo_url"
        :company-name="publicSettings.company_name"
        @close="closeSendEmail"
        @sent="handleEmailSent"
    />

    <InvoiceForm
        v-if="showForm"
        :invoice="selectedInvoice"
        @close="closeForm"
        @saved="handleSaved"
    />

    <DeleteConfirm
        v-if="showDeleteConfirm"
        title="Delete Invoice"
        :message="`Are you sure you want to delete invoice ${invoiceToDelete?.invoice_number}?`"
        :loading="deleting"
        @confirm="confirmDelete"
        @cancel="closeDeleteConfirm"
    />
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import InvoiceForm from '@/components/InvoiceForm.vue';
import InvoiceSendEmailModal from '@/components/InvoiceSendEmailModal.vue';
import DeleteConfirm from '@/components/DeleteConfirm.vue';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';
import { formatInvoiceStatus } from '@/utils/displayFormat';

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

const invoicesBadge = computed(() =>
    pagination.value?.total != null ? `${pagination.value.total} Total` : null,
);

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

const formatStatus = formatInvoiceStatus;

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
