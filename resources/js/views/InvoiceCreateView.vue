<template>
    <div class="min-h-screen bg-slate-50 w-full min-w-0 overflow-x-hidden">
        <div class="max-w-3xl mx-auto px-3 sm:px-6 py-6 lg:py-8 w-full min-w-0">
            <!-- Back + Title -->
            <div class="mb-6">
                <router-link
                    to="/invoices"
                    class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 text-sm mb-4"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Invoices
                </router-link>
                <h1 class="text-2xl font-bold text-slate-900">{{ isEditMode ? 'Edit Invoice' : 'Create Invoice' }}</h1>
            </div>

            <div v-if="loadingInvoice" class="flex items-center justify-center py-12 text-slate-500">
                Loading invoice...
            </div>
            <form v-else @submit.prevent="handleSubmit" class="space-y-6">
                <!-- Customer: Choose existing or Add new (overflow-visible so dropdown is not clipped) -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-visible">
                    <div class="px-4 sm:px-6 py-4 border-b border-slate-200 bg-slate-50 rounded-t-xl">
                        <h2 class="text-base font-semibold text-slate-900">Customer</h2>
                    </div>
                    <div class="p-4 sm:p-6 space-y-4 overflow-visible">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="customerMode"
                                    type="radio"
                                    value="existing"
                                    class="rounded-full border-slate-300 text-slate-900 focus:ring-slate-500"
                                />
                                <span class="text-sm font-medium text-slate-700">Choose existing customer</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="customerMode"
                                    type="radio"
                                    value="new"
                                    class="rounded-full border-slate-300 text-slate-900 focus:ring-slate-500"
                                />
                                <span class="text-sm font-medium text-slate-700">Add new customer</span>
                            </label>
                        </div>

                        <!-- Searchable customer select - dropdown in Teleport so it is never clipped -->
                        <div v-if="customerMode === 'existing'" class="space-y-1">
                            <label class="block text-sm font-medium text-slate-700">Search & select customer *</label>
                            <div class="relative" ref="customerSelectRef">
                                <input
                                    v-model="customerSearch"
                                    type="text"
                                    placeholder="Type name, phone or email..."
                                    class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                    @focus="showCustomerDropdown = true; updateDropdownPosition()"
                                    @input="debounceCustomerSearch"
                                />
                                <Teleport to="body">
                                    <div
                                        v-if="showCustomerDropdown && customerSelectRef"
                                        ref="dropdownPanelRef"
                                        class="fixed z-[100] bg-white border border-slate-200 rounded-lg shadow-xl max-h-56 overflow-y-auto min-w-[200px]"
                                        :style="dropdownStyle"
                                    >
                                        <div
                                            v-if="customerSearchLoading"
                                            class="px-3 py-4 text-center text-sm text-slate-500"
                                        >
                                            Searching...
                                        </div>
                                        <div
                                            v-else-if="!customerSearch.trim()"
                                            class="px-3 py-4 text-center text-sm text-slate-500"
                                        >
                                            Type to search by name, phone or email
                                        </div>
                                        <div
                                            v-else-if="customerOptions.length === 0"
                                            class="px-3 py-4 text-center text-sm text-slate-500"
                                        >
                                            No customers found. Try another search or add a new customer.
                                        </div>
                                        <button
                                            v-for="c in customerOptions"
                                            :key="c.id"
                                            type="button"
                                            class="w-full text-left px-3 py-2.5 text-sm hover:bg-slate-50 border-b border-slate-100 last:border-0 break-words"
                                            @click="selectCustomer(c)"
                                        >
                                            <span class="font-medium text-slate-900">{{ c.name }}</span>
                                            <span v-if="c.phone" class="text-slate-500"> · {{ c.phone }}</span>
                                            <span v-if="c.email" class="text-slate-500"> · {{ c.email }}</span>
                                        </button>
                                    </div>
                                </Teleport>
                            </div>
                            <p v-if="selectedCustomer" class="text-sm text-slate-600 mt-1">
                                Selected: <strong>{{ selectedCustomer.name }}</strong>
                                <button
                                    type="button"
                                    class="ml-2 text-red-600 hover:underline text-sm"
                                    @click="clearSelectedCustomer"
                                >
                                    Clear
                                </button>
                            </p>
                            <!-- Selected customer details: address, phone, email, VAT -->
                            <div v-if="selectedCustomer" class="mt-3 p-3 bg-slate-50 rounded-lg border border-slate-100 text-sm space-y-1">
                                <div v-if="selectedCustomer.phone"><span class="text-slate-500">Phone:</span> {{ selectedCustomer.phone }}</div>
                                <div v-if="selectedCustomer.email"><span class="text-slate-500">Email:</span> {{ selectedCustomer.email }}</div>
                                <div v-if="selectedCustomer.address"><span class="text-slate-500">Address:</span> {{ selectedCustomer.address }}</div>
                                <div v-if="selectedCustomer.vat_number"><span class="text-slate-500">VAT:</span> {{ selectedCustomer.vat_number }}</div>
                            </div>
                        </div>

                        <!-- New customer fields: name, phone, email, address, VAT -->
                        <div v-if="customerMode === 'new'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Name *</label>
                                <input
                                    v-model="newCustomer.name"
                                    type="text"
                                    required
                                    placeholder="Customer name"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Phone *</label>
                                <input
                                    v-model="newCustomer.phone"
                                    type="text"
                                    required
                                    placeholder="Phone"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                                <input
                                    v-model="newCustomer.email"
                                    type="email"
                                    placeholder="Email (optional)"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                                <input
                                    v-model="newCustomer.address"
                                    type="text"
                                    placeholder="Address (optional)"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">VAT number</label>
                                <input
                                    v-model="newCustomer.vat_number"
                                    type="text"
                                    placeholder="VAT number (optional)"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoice details -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-4 sm:px-6 py-4 border-b border-slate-200 bg-slate-50">
                        <h2 class="text-base font-semibold text-slate-900">Invoice details</h2>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Invoice date *</label>
                                <input
                                    v-model="form.invoice_date"
                                    type="date"
                                    required
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Due date</label>
                                <input
                                    v-model="form.due_date"
                                    type="date"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">VAT rate (%)</label>
                                <input
                                    v-model.number="form.vat_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line items -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                    <div class="px-4 sm:px-6 py-4 border-b border-slate-200 bg-slate-50 flex flex-wrap justify-between items-center gap-2">
                        <h2 class="text-base font-semibold text-slate-900">Line items *</h2>
                        <button
                            type="button"
                            @click="addItem"
                            class="text-sm text-blue-600 hover:underline font-medium"
                        >
                            + Add item
                        </button>
                    </div>
                    <div class="p-4 sm:p-6 overflow-x-auto">
                        <div class="space-y-3 min-w-[280px]">
                            <div
                                v-for="(item, index) in form.items"
                                :key="index"
                                class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-end"
                            >
                                <div class="sm:col-span-5 relative">
                                    <input
                                        v-model="item.description"
                                        type="text"
                                        placeholder="Search product or type new name..."
                                        required
                                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                                        :ref="el => setProductInputRef(index, el)"
                                        @focus="openProductDropdown(index)"
                                        @input="debounceProductSearch(index)"
                                    />
                                    <Teleport to="body">
                                        <div
                                            v-if="showProductDropdown && activeProductRow === index && productInputRefs[index]"
                                            ref="productDropdownRef"
                                            class="fixed z-[100] bg-white border border-slate-200 rounded-lg shadow-xl max-h-52 overflow-y-auto min-w-[200px]"
                                            :style="productDropdownStyle(index)"
                                        >
                                            <div v-if="productSearchLoading" class="px-3 py-3 text-center text-sm text-slate-500">
                                                Searching...
                                            </div>
                                            <div v-else-if="!item.description?.trim()" class="px-3 py-3 text-center text-sm text-slate-500">
                                                Type to search products or add a new one
                                            </div>
                                            <template v-else>
                                                <button
                                                    v-for="p in productOptions"
                                                    :key="p.id"
                                                    type="button"
                                                    class="w-full text-left px-3 py-2.5 text-sm hover:bg-slate-50 border-b border-slate-100 last:border-0 break-words"
                                                    @click="selectProduct(index, p)"
                                                >
                                                    <span class="font-medium text-slate-900">{{ p.name }}</span>
                                                    <span v-if="p.category" class="text-slate-500 text-xs"> · {{ p.category }}</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="w-full text-left px-3 py-2.5 text-sm hover:bg-slate-50 border-t border-slate-200 text-blue-600 font-medium"
                                                    @click="addNewProduct(index)"
                                                >
                                                    + Add "{{ item.description.trim() }}" as new product
                                                </button>
                                            </template>
                                        </div>
                                    </Teleport>
                                </div>
                                <div class="sm:col-span-2">
                                    <input
                                        v-model.number="item.quantity"
                                        type="number"
                                        min="1"
                                        placeholder="Qty"
                                        required
                                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                                    />
                                </div>
                                <div class="sm:col-span-2">
                                    <input
                                        v-model.number="item.unit_price"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        placeholder="Unit price"
                                        required
                                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                                    />
                                </div>
                                <div class="sm:col-span-2 text-sm font-medium text-slate-700">
                                    £{{ ((item.quantity || 0) * (item.unit_price || 0)).toFixed(2) }}
                                </div>
                                <div class="sm:col-span-1">
                                    <button
                                        type="button"
                                        @click="removeItem(index)"
                                        class="text-red-600 hover:text-red-800 p-2"
                                        :disabled="form.items.length === 1"
                                    >
                                        ×
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-slate-200 flex flex-wrap justify-between gap-2 text-sm">
                            <span>Subtotal: <strong>£{{ subtotal.toFixed(2) }}</strong></span>
                            <span>VAT ({{ form.vat_rate || 20 }}%): <strong>£{{ vatAmount.toFixed(2) }}</strong></span>
                            <span class="text-base font-semibold">Total: £{{ total.toFixed(2) }}</span>
                        </div>
                    </div>
                </div>

                <p v-if="submitError" class="text-sm text-red-600 bg-red-50 p-3 rounded-lg">{{ submitError }}</p>

                <div class="flex flex-col-reverse sm:flex-row justify-end gap-3">
                    <router-link
                        to="/invoices"
                        class="inline-flex justify-center px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50"
                    >
                        Cancel
                    </router-link>
                    <button
                        type="submit"
                        :disabled="loading || !canSubmit"
                        class="inline-flex justify-center px-4 py-2.5 bg-slate-900 text-white rounded-lg text-sm font-medium hover:bg-slate-800 disabled:opacity-50"
                    >
                        {{ loading ? (isEditMode ? 'Updating...' : 'Creating...') : (isEditMode ? 'Update invoice' : 'Create invoice') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const router = useRouter();
const route = useRoute();
const toast = useToastStore();

const isEditMode = computed(() => !!route.params.id);
const invoiceId = computed(() => route.params.id || null);

const customerMode = ref('existing');
const customerSearch = ref('');
const customerOptions = ref([]);
const customerSearchLoading = ref(false);
const showCustomerDropdown = ref(false);
const selectedCustomer = ref(null);
const customerSelectRef = ref(null);
const dropdownPanelRef = ref(null);
const dropdownRect = ref(null);
const newCustomer = ref({ name: '', phone: '', email: '', address: '', vat_number: '' });
const form = ref({
    invoice_date: new Date().toISOString().split('T')[0],
    due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    vat_rate: 20,
    status: 'draft',
    items: [{ description: '', quantity: 1, unit_price: 0 }],
});
const loading = ref(false);
const submitError = ref(null);
const loadingInvoice = ref(false);
const activeProductRow = ref(null);
const productOptions = ref([]);
const productSearchLoading = ref(false);
const showProductDropdown = ref(false);
const productInputRefs = ref({});
const productDropdownRef = ref(null);
let searchTimeout = null;
let productSearchTimeout = null;

const subtotal = computed(() => {
    return form.value.items.reduce((sum, item) => sum + (item.quantity || 0) * (item.unit_price || 0), 0);
});
const vatAmount = computed(() => {
    return subtotal.value * (form.value.vat_rate || 20) / 100;
});
const total = computed(() => subtotal.value + vatAmount.value);

const canSubmit = computed(() => {
    if (form.value.items.length === 0) return false;
    if (form.value.items.some(i => !i.description || !i.quantity || !i.unit_price)) return false;
    if (customerMode.value === 'existing') return !!selectedCustomer.value;
    if (customerMode.value === 'new') return !!(newCustomer.value.name?.trim() && newCustomer.value.phone?.trim());
    return false;
});

const dropdownStyle = computed(() => {
    if (!dropdownRect.value) return {};
    return {
        top: `${dropdownRect.value.bottom + 4}px`,
        left: `${dropdownRect.value.left}px`,
        width: `${Math.max(dropdownRect.value.width, 280)}px`,
    };
});

function updateDropdownPosition() {
    if (customerSelectRef.value) {
        dropdownRect.value = customerSelectRef.value.getBoundingClientRect();
    }
}

function debounceCustomerSearch() {
    clearTimeout(searchTimeout);
    const q = customerSearch.value.trim();
    if (!q) {
        customerOptions.value = [];
        showCustomerDropdown.value = true;
        updateDropdownPosition();
        return;
    }
    searchTimeout = setTimeout(async () => {
        customerSearchLoading.value = true;
        updateDropdownPosition();
        try {
            const { data } = await axios.get('/api/customers', { params: { search: q, per_page: 20 } });
            customerOptions.value = Array.isArray(data) ? data : (data.data || []);
        } catch (_) {
            customerOptions.value = [];
        } finally {
            customerSearchLoading.value = false;
        }
    }, 300);
}

function selectCustomer(c) {
    selectedCustomer.value = c;
    customerSearch.value = c.name;
    showCustomerDropdown.value = false;
}

function clearSelectedCustomer() {
    selectedCustomer.value = null;
    customerSearch.value = '';
}

function addItem() {
    form.value.items.push({ description: '', quantity: 1, unit_price: 0 });
}

function removeItem(index) {
    if (form.value.items.length > 1) form.value.items.splice(index, 1);
}

async function loadInvoiceForEdit() {
    if (!invoiceId.value) return;
    loadingInvoice.value = true;
    try {
        const { data } = await axios.get(`/api/invoices/${invoiceId.value}`);
        customerMode.value = 'existing';
        selectedCustomer.value = data.customer;
        customerSearch.value = data.customer?.name || '';
        form.value = {
            invoice_date: data.invoice_date ? new Date(data.invoice_date).toISOString().split('T')[0] : form.value.invoice_date,
            due_date: data.due_date ? new Date(data.due_date).toISOString().split('T')[0] : form.value.due_date,
            vat_rate: data.vat_rate ?? 20,
            status: data.status || 'draft',
            items: (data.items || []).length ? data.items.map(i => ({
                description: i.description,
                quantity: i.quantity,
                unit_price: parseFloat(i.unit_price),
            })) : [{ description: '', quantity: 1, unit_price: 0 }],
        };
    } catch (err) {
        submitError.value = err.response?.data?.message || 'Failed to load invoice';
    } finally {
        loadingInvoice.value = false;
    }
}

async function handleSubmit() {
    if (!canSubmit.value) return;
    loading.value = true;
    submitError.value = null;
    try {
        const payload = {
            invoice_date: form.value.invoice_date,
            due_date: form.value.due_date || null,
            vat_rate: form.value.vat_rate,
            status: form.value.status,
            items: form.value.items.map(i => ({
                description: i.description,
                quantity: Number(i.quantity),
                unit_price: Number(i.unit_price),
            })),
        };
        if (customerMode.value === 'existing') {
            payload.customer_id = selectedCustomer.value.id;
        } else {
            payload.customer = {
                name: newCustomer.value.name.trim(),
                phone: newCustomer.value.phone.trim(),
                email: newCustomer.value.email?.trim() || null,
                address: newCustomer.value.address?.trim() || null,
                vat_number: newCustomer.value.vat_number?.trim() || null,
            };
        }
        if (isEditMode.value) {
            await axios.put(`/api/invoices/${invoiceId.value}`, payload);
            toast.success('Invoice updated');
        } else {
            await axios.post('/api/invoices', payload);
            toast.success('Invoice created');
        }
        router.push('/invoices');
    } catch (err) {
        submitError.value = err.response?.data?.message || (isEditMode.value ? 'Failed to update invoice' : 'Failed to create invoice');
    } finally {
        loading.value = false;
    }
}

function onDocumentClick(e) {
    if (!customerSelectRef.value?.contains(e.target) && !dropdownPanelRef.value?.contains(e.target)) {
        showCustomerDropdown.value = false;
    }
    if (activeProductRow.value !== null && !productInputRefs.value[activeProductRow.value]?.contains(e.target) && !productDropdownRef.value?.contains(e.target)) {
        showProductDropdown.value = false;
        activeProductRow.value = null;
    }
}

function setProductInputRef(index, el) {
    if (el) productInputRefs.value[index] = el;
}

function productDropdownStyle(index) {
    const el = productInputRefs.value[index];
    if (!el) return {};
    const rect = el.getBoundingClientRect();
    return {
        top: `${rect.bottom + 4}px`,
        left: `${rect.left}px`,
        width: `${Math.max(rect.width, 260)}px`,
    };
}

function openProductDropdown(index) {
    activeProductRow.value = index;
    showProductDropdown.value = true;
    const q = form.value.items[index]?.description?.trim();
    if (q) debounceProductSearch(index);
    else productOptions.value = [];
}

function debounceProductSearch(index) {
    clearTimeout(productSearchTimeout);
    const q = form.value.items[index]?.description?.trim() || '';
    if (!q) {
        productOptions.value = [];
        return;
    }
    productSearchTimeout = setTimeout(async () => {
        productSearchLoading.value = true;
        try {
            const { data } = await axios.get('/api/products', { params: { search: q } });
            productOptions.value = Array.isArray(data) ? data : (data.data || []);
        } catch (_) {
            productOptions.value = [];
        } finally {
            productSearchLoading.value = false;
        }
    }, 280);
}

function selectProduct(index, product) {
    form.value.items[index].description = product.name;
    showProductDropdown.value = false;
    activeProductRow.value = null;
}

async function addNewProduct(index) {
    const name = form.value.items[index]?.description?.trim();
    if (!name) return;
    try {
        const { data } = await axios.post('/api/products', { name, category: '' });
        form.value.items[index].description = data.name;
        toast.success('Product added');
    } catch (err) {
        toast.error(err.response?.data?.message || 'Failed to add product');
    }
    showProductDropdown.value = false;
    activeProductRow.value = null;
}

watch(invoiceId, (id) => {
    if (id) loadInvoiceForEdit();
}, { immediate: true });

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>
