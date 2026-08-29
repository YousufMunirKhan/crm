<template>
    <ListingPageShell
        title="Invoices"
        subtitle="Create, filter, and send invoices — totals reflect the current filter set."
        :badge="invoicesBadge"
    >
        <template #actions>
            <BaseButton variant="primary" to="/invoices/create" block-mobile>
                <template #icon><PlusIcon class="icon" aria-hidden="true" /></template>
                Create invoice
            </BaseButton>
        </template>

        <template #filters>
            <div class="space-y-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-sm font-semibold text-slate-700">Filters</h2>
                    <BaseButton
                        variant="ghost"
                        size="sm"
                        class="self-start sm:self-auto"
                        :aria-expanded="showFilters ? 'true' : 'false'"
                        @click="showFilters = !showFilters"
                    >
                        <template #icon>
                            <ChevronDownIcon
                                class="icon-sm transition-transform"
                                :class="{ 'rotate-180': showFilters }"
                                aria-hidden="true"
                            />
                        </template>
                        {{ showFilters ? 'Hide' : 'Show' }} filters
                    </BaseButton>
                </div>

                <div v-show="showFilters" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="listing-label" for="invoicesview-search">Search</label>
                        <input id="invoicesview-search"
                            v-model="filters.search"
                            type="text"
                            placeholder="Invoice # or customer..."
                            class="listing-input"
                            @input="debounceSearch"
                        />
                    </div>
                    <div>
                        <label class="listing-label" for="invoicesview-status">Status</label>
                        <select id="invoicesview-status" v-model="filters.status" class="listing-input" @change="applyFilters">
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="sent">Sent</option>
                            <option value="partially_paid">Partially Paid</option>
                            <option value="paid">Paid</option>
                            <option value="overdue">Overdue</option>
                        </select>
                    </div>
                    <div v-if="isAdmin">
                        <label class="listing-label" for="invoicesview-created-by">Created by</label>
                        <select id="invoicesview-created-by" v-model="filters.created_by" class="listing-input" @change="applyFilters">
                            <option value="">All users</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="listing-label" for="invoicesview-customer">Customer</label>
                        <select id="invoicesview-customer" v-model="filters.customer_id" class="listing-input" @change="applyFilters">
                            <option value="">All customers</option>
                            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                {{ customer.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="listing-label" for="invoicesview-from-date">From date</label>
                        <input id="invoicesview-from-date" v-model="filters.from_date" type="date" class="listing-input" @change="applyFilters" />
                    </div>
                    <div>
                        <label class="listing-label" for="invoicesview-to-date">To date</label>
                        <input id="invoicesview-to-date" v-model="filters.to_date" type="date" class="listing-input" @change="applyFilters" />
                    </div>
                    <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-4">
                        <BaseButton variant="soft" @click="applyFilters">
                            <template #icon><FunnelIcon class="icon" aria-hidden="true" /></template>
                            Apply
                        </BaseButton>
                        <BaseButton variant="outline" @click="clearFilters">Clear</BaseButton>
                    </div>
                </div>

                <div v-if="hasActiveFilters" class="flex flex-wrap gap-2">
                    <span v-for="(value, key) in activeFilterTags" :key="key" class="chip">
                        {{ key }}: {{ value }}
                        <button
                            type="button"
                            class="chip-remove"
                            :aria-label="`Remove ${key} filter`"
                            @click="removeFilter(key)"
                        >
                            <XMarkIcon class="icon-sm" aria-hidden="true" />
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
                    <div class="text-xl font-bold text-slate-900 mt-1 tabular-nums break-words">{{ formatMoney(summary.total_amount || 0) }}</div>
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
                        <th class="listing-th">Paid</th>
                        <th class="listing-th">Due</th>
                        <th class="listing-th">Status</th>
                        <th class="listing-th">Created By</th>
                        <th class="listing-th">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="9" class="listing-td text-center text-slate-500 py-10">Loading invoices...</td>
                    </tr>
                    <tr v-else-if="invoices.length === 0">
                        <td colspan="9" class="listing-td text-center text-slate-500 py-10">No invoices found</td>
                    </tr>
                    <tr v-for="invoice in invoices" :key="invoice.id" class="listing-row">
                        <td class="listing-td-strong">{{ invoice.invoice_number }}</td>
                        <td class="listing-td"><CustomerName :customer="invoice.customer" /></td>
                        <td class="listing-td text-slate-600">{{ formatDate(invoice.invoice_date) }}</td>
                        <td class="listing-td font-semibold text-slate-900">{{ formatMoney(invoice.total) }}</td>
                        <td class="listing-td font-semibold text-success-700">{{ formatMoney(invoice.amount_paid) }}</td>
                        <td class="listing-td font-semibold" :class="outstanding(invoice) > 0 ? 'text-danger-700' : 'text-slate-700'">
                            {{ formatMoney(outstanding(invoice)) }}
                        </td>
                        <td class="listing-td">
                            <BaseBadge :status="badgeStatus(invoice.status)">{{ formatStatus(invoice.status) }}</BaseBadge>
                        </td>
                        <td class="listing-td text-slate-600">{{ invoice.creator?.name || '—' }}</td>
                        <td class="listing-td">
                            <div class="flex flex-wrap gap-x-3 gap-y-1">
                                <button type="button" class="listing-link-edit rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40" @click="openSendEmail(invoice)">
                                    Send email
                                </button>
                                <button type="button" class="text-success-700 hover:text-success-800 font-medium text-sm rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 disabled:opacity-40" :disabled="outstanding(invoice) <= 0" @click="openPaymentModal(invoice)">
                                    Record payment
                                </button>
                                <router-link :to="`/invoices/${invoice.id}/edit`" class="listing-link-edit">Edit</router-link>
                                <button type="button" class="listing-link-edit rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40" @click="generatePDF(invoice.id)">PDF</button>
                                <button type="button" class="listing-link-delete rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40" @click="openDeleteConfirm(invoice)">Delete</button>
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
                    <BaseBadge :status="badgeStatus(invoice.status)">{{ formatStatus(invoice.status) }}</BaseBadge>
                </div>
                <div class="text-sm text-slate-600 flex gap-1 items-baseline">
                    <span class="shrink-0">Customer:</span>
                    <CustomerName :customer="invoice.customer" name-class="text-slate-900 font-medium" />
                </div>
                <div class="text-sm text-slate-600">Date: {{ formatDate(invoice.invoice_date) }}</div>
                <div class="text-sm font-semibold text-slate-900">Total: {{ formatMoney(invoice.total) }}</div>
                <div class="text-sm text-slate-600">Created By: {{ invoice.creator?.name || '—' }}</div>
                <div class="grid grid-cols-2 gap-2 text-sm">
                    <div>
                        <div class="text-xs text-slate-500">Paid</div>
                        <div class="font-semibold text-success-700">{{ formatMoney(invoice.amount_paid) }}</div>
                    </div>
                    <div>
                        <div class="text-xs text-slate-500">Due</div>
                        <div class="font-semibold" :class="outstanding(invoice) > 0 ? 'text-danger-700' : 'text-slate-700'">{{ formatMoney(outstanding(invoice)) }}</div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-3 pt-1">
                    <button type="button" class="listing-link-edit rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40" @click="openSendEmail(invoice)">Send email</button>
                    <button type="button" class="text-success-700 hover:text-success-800 font-medium text-sm rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 disabled:opacity-40" :disabled="outstanding(invoice) <= 0" @click="openPaymentModal(invoice)">Record payment</button>
                    <router-link :to="`/invoices/${invoice.id}/edit`" class="listing-link-edit">Edit</router-link>
                    <button type="button" class="listing-link-edit rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40" @click="generatePDF(invoice.id)">PDF</button>
                    <button type="button" class="listing-link-delete rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40" @click="openDeleteConfirm(invoice)">Delete</button>
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

    <BaseModal
        v-model="showPaymentModal"
        title="Record payment"
        :description="paymentModalDescription"
        size="md"
        :dismissible="!savingPayment"
        :close-on-backdrop="false"
        @close="closePaymentModal"
    >
        <form id="record-payment-form" class="space-y-4" novalidate @submit.prevent="savePayment">
            <div class="form-grid-2">
                <div>
                    <label class="form-label" for="invoicesview-payment-date">Payment date</label>
                    <input id="invoicesview-payment-date" v-model="paymentForm.payment_date" type="date" class="form-input" />
                </div>
                <div>
                    <label class="form-label" for="invoicesview-amount">Amount</label>
                    <input id="invoicesview-amount" v-model.number="paymentForm.amount" type="number" min="0.01" step="0.01" class="form-input" />
                </div>
            </div>
            <div class="form-grid-2">
                <div>
                    <label class="form-label" for="invoicesview-method">Method</label>
                    <input id="invoicesview-method" v-model="paymentForm.method" type="text" class="form-input" placeholder="Bank transfer, cash..." />
                </div>
                <div>
                    <label class="form-label" for="invoicesview-reference">Reference</label>
                    <input id="invoicesview-reference" v-model="paymentForm.reference" type="text" class="form-input" placeholder="Optional reference" />
                </div>
            </div>
            <div>
                <label class="form-label" for="invoicesview-notes">Notes</label>
                <textarea id="invoicesview-notes" v-model="paymentForm.notes" class="form-textarea" placeholder="Optional internal note"></textarea>
            </div>
            <p v-if="paymentError" class="callout callout-danger" role="alert">{{ paymentError }}</p>
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile :disabled="savingPayment" @click="closePaymentModal">Cancel</BaseButton>
            <BaseButton variant="soft" type="submit" form="record-payment-form" block-mobile :loading="savingPayment">
                {{ savingPayment ? 'Saving...' : 'Save payment' }}
            </BaseButton>
        </template>
    </BaseModal>

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
import { BaseBadge, BaseButton, BaseModal } from '@/components/base';
import { ChevronDownIcon, FunnelIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';
import { formatInvoiceStatus } from '@/utils/displayFormat';
import CustomerName from '@/components/CustomerName.vue';
import { formatMoney } from '@/utils/money';

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
const showPaymentModal = ref(false);
const paymentInvoice = ref(null);
const savingPayment = ref(false);
const paymentError = ref('');
const paymentForm = ref({
    payment_date: '',
    amount: '',
    method: '',
    reference: '',
    notes: '',
});
let searchTimeout = null;

const invoicesBadge = computed(() =>
    pagination.value?.total != null ? `${pagination.value.total} Total` : null,
);

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(num || 0));
};

const todayYmd = () => new Date().toISOString().split('T')[0];

const outstanding = (invoice) => {
    if (!invoice) return 0;
    return Math.max(0, Number(invoice.total || 0) - Number(invoice.amount_paid || 0));
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

/**
 * Normalises an invoice status onto a key BaseBadge's STATUS_TONE knows,
 * so the tone map stays the single source of truth.
 */
const badgeStatus = (status) => (status === 'partially_paid' ? 'partial' : status || 'draft');

const paymentModalDescription = computed(() =>
    paymentInvoice.value
        ? `${paymentInvoice.value.invoice_number} - outstanding ${formatMoney(outstanding(paymentInvoice.value))}`
        : '',
);

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
                total_paid: invoices.value.reduce((sum, inv) => sum + parseFloat(inv.amount_paid || 0), 0),
                total_outstanding: invoices.value.reduce((sum, inv) => sum + outstanding(inv), 0),
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

const openPaymentModal = (invoice) => {
    paymentInvoice.value = invoice;
    paymentError.value = '';
    paymentForm.value = {
        payment_date: todayYmd(),
        amount: outstanding(invoice).toFixed(2),
        method: '',
        reference: '',
        notes: '',
    };
    showPaymentModal.value = true;
};

const closePaymentModal = () => {
    if (savingPayment.value) return;
    showPaymentModal.value = false;
    paymentInvoice.value = null;
    paymentError.value = '';
};

const savePayment = async () => {
    if (!paymentInvoice.value || savingPayment.value) return;
    paymentError.value = '';
    const amount = Number(paymentForm.value.amount || 0);
    if (!paymentForm.value.payment_date || amount <= 0) {
        paymentError.value = 'Enter a valid payment date and amount.';
        return;
    }
    if (amount > outstanding(paymentInvoice.value) + 0.01) {
        paymentError.value = 'Payment amount cannot be greater than the outstanding balance.';
        return;
    }

    savingPayment.value = true;
    try {
        await axios.post(`/api/invoices/${paymentInvoice.value.id}/payments`, {
            payment_date: paymentForm.value.payment_date,
            amount,
            method: paymentForm.value.method || null,
            reference: paymentForm.value.reference || null,
            notes: paymentForm.value.notes || null,
        });
        toast.success('Payment recorded');
        closePaymentModal();
        await loadInvoices(pagination.value?.current_page || 1);
    } catch (error) {
        paymentError.value = error.response?.data?.message || 'Failed to record payment.';
    } finally {
        savingPayment.value = false;
    }
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
