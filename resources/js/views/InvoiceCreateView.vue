<template>
    <div class="min-h-screen bg-slate-50 w-full min-w-0 overflow-x-hidden">
        <div class="max-w-3xl mx-auto px-3 sm:px-6 py-6 lg:py-8 w-full min-w-0">
            <!-- Back + Title -->
            <div class="mb-6">
                <BaseButton variant="ghost" size="sm" to="/invoices" class="-ml-2 mb-3">
                    <template #icon><ArrowLeftIcon class="icon-sm" aria-hidden="true" /></template>
                    Back to Invoices
                </BaseButton>
                <h1 class="text-page-title text-slate-900">{{ isEditMode ? 'Edit Invoice' : 'Create Invoice' }}</h1>
            </div>

            <div v-if="loadingInvoice" class="flex items-center justify-center py-12 text-slate-500">
                Loading invoice...
            </div>
            <form v-else @submit.prevent="handleSubmit" class="space-y-6">
                <!-- Customer: Choose existing or Add new (overflow-visible so dropdown is not clipped) -->
                <div class="form-card !overflow-visible rounded-t-xl">
                    <div class="form-section-head-mint">
                        <h2 class="form-section-title-mint">Customer</h2>
                        <p class="form-section-desc-mint">Choose someone already in the CRM or add their details as you issue the invoice.</p>
                    </div>
                    <div class="form-body space-y-4 overflow-visible">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="customerMode"
                                    type="radio"
                                    value="existing"
                                    class="form-radio"
                                />
                                <span class="text-sm font-medium text-slate-700">Choose existing customer</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="customerMode"
                                    type="radio"
                                    value="new"
                                    class="form-radio"
                                />
                                <span class="text-sm font-medium text-slate-700">Add new customer</span>
                            </label>
                        </div>

                        <!--
                          Combobox. The listbox is teleported because .form-card is
                          overflow-hidden - an in-flow panel would be clipped.
                        -->
                        <div v-if="customerMode === 'existing'" class="space-y-1">
                            <label class="form-label" for="invoice-customer-search">Search &amp; select customer *</label>
                            <div class="relative" ref="customerSelectRef">
                                <input
                                    id="invoice-customer-search"
                                    v-model="customerSearch"
                                    type="text"
                                    role="combobox"
                                    autocomplete="off"
                                    aria-autocomplete="list"
                                    aria-controls="invoice-customer-listbox"
                                    :aria-expanded="showCustomerDropdown"
                                    :aria-activedescendant="
                                        showCustomerDropdown && customerOptions[customerActiveIndex]
                                            ? `invoice-customer-option-${customerOptions[customerActiveIndex].id}`
                                            : undefined
                                    "
                                    placeholder="Type name, phone or email..."
                                    class="form-input"
                                    @focus="showCustomerDropdown = true; updateDropdownPosition()"
                                    @input="debounceCustomerSearch"
                                    @keydown.down.prevent="moveCustomerActive(1)"
                                    @keydown.up.prevent="moveCustomerActive(-1)"
                                    @keydown.enter.prevent="chooseActiveCustomer"
                                    @keydown.esc="showCustomerDropdown = false"
                                />
                                <Teleport to="body">
                                    <div
                                        v-if="showCustomerDropdown && customerSelectRef"
                                        id="invoice-customer-listbox"
                                        ref="dropdownPanelRef"
                                        role="listbox"
                                        aria-label="Matching customers"
                                        class="popover-panel fixed max-h-56 overflow-y-auto min-w-[200px]"
                                        :style="dropdownStyle"
                                    >
                                        <div
                                            v-if="customerSearchLoading"
                                            class="px-3 py-4 text-center text-sm text-slate-500"
                                            role="status"
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
                                            v-for="(c, i) in customerOptions"
                                            :id="`invoice-customer-option-${c.id}`"
                                            :key="c.id"
                                            type="button"
                                            role="option"
                                            :aria-selected="i === customerActiveIndex"
                                            :class="[
                                                'w-full text-left px-3 py-2.5 text-sm border-b border-slate-100 last:border-0 break-words',
                                                i === customerActiveIndex ? 'bg-primary-50' : 'hover:bg-slate-50',
                                            ]"
                                            @mouseenter="customerActiveIndex = i"
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
                                    class="ml-2 text-danger-600 hover:underline text-sm"
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
                                <label class="form-label" for="invoicecreateview-name">Name *</label>
                                <input id="invoicecreateview-name"
                                    v-model="newCustomer.name"
                                    type="text"
                                    required
                                    placeholder="Customer name"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label" for="invoicecreateview-phone">Phone *</label>
                                <input id="invoicecreateview-phone"
                                    v-model="newCustomer.phone"
                                    type="text"
                                    required
                                    placeholder="Phone"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label" for="invoicecreateview-email">Email</label>
                                <input id="invoicecreateview-email"
                                    v-model="newCustomer.email"
                                    type="email"
                                    placeholder="Email (optional)"
                                    class="form-input"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" for="invoicecreateview-address">Address</label>
                                <input id="invoicecreateview-address"
                                    v-model="newCustomer.address"
                                    type="text"
                                    placeholder="Address (optional)"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label" for="invoicecreateview-vat-number">VAT number</label>
                                <input id="invoicecreateview-vat-number"
                                    v-model="newCustomer.vat_number"
                                    type="text"
                                    placeholder="VAT number (optional)"
                                    class="form-input"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Invoice details -->
                <div class="form-card">
                    <div class="form-section-head">
                        <h2 class="form-section-title">Invoice details</h2>
                        <p class="form-section-desc">Dates and tax rate apply to this document only.</p>
                    </div>
                    <div class="form-body">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label" for="invoicecreateview-invoice-date">Invoice date *</label>
                                <input id="invoicecreateview-invoice-date"
                                    v-model="form.invoice_date"
                                    type="date"
                                    required
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label" for="invoicecreateview-due-date">Due date</label>
                                <input id="invoicecreateview-due-date"
                                    v-model="form.due_date"
                                    type="date"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label" for="invoicecreateview-vat-rate">VAT rate (%)</label>
                                <input id="invoicecreateview-vat-rate"
                                    v-model.number="form.vat_rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    class="form-input"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line items -->
                <div v-if="isEditMode" class="form-card">
                    <div class="form-section-head flex flex-wrap justify-between items-center gap-2">
                        <div>
                            <h2 class="form-section-title">Payment history</h2>
                            <p class="form-section-desc !mt-0.5">Record each client payment separately. The invoice number stays the same.</p>
                        </div>
                    </div>
                    <div class="form-body space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                            <div>
                                <div class="text-xs text-slate-500">Total</div>
                                <div class="font-semibold text-slate-900">GBP {{ formatNumber(invoiceTotalForPayments) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Paid</div>
                                <div class="font-semibold text-success-700">GBP {{ formatNumber(invoiceAmountPaid) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Outstanding</div>
                                <div class="font-semibold" :class="invoiceOutstanding > 0 ? 'text-danger-700' : 'text-slate-700'">GBP {{ formatNumber(invoiceOutstanding) }}</div>
                            </div>
                        </div>

                        <div v-if="invoicePayments.length" class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="px-3 py-2 text-left">Date</th>
                                        <th class="px-3 py-2 text-right">Amount</th>
                                        <th class="px-3 py-2 text-left">Method</th>
                                        <th class="px-3 py-2 text-left">Reference</th>
                                        <th class="px-3 py-2 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="payment in invoicePayments" :key="payment.id">
                                        <td class="px-3 py-2">{{ formatDate(payment.payment_date) }}</td>
                                        <td class="px-3 py-2 text-right font-semibold">GBP {{ formatNumber(payment.amount) }}</td>
                                        <td class="px-3 py-2">{{ payment.method || '-' }}</td>
                                        <td class="px-3 py-2">{{ payment.reference || '-' }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <button type="button" class="text-danger-600 hover:text-danger-800 font-medium" :disabled="deletingPaymentId === payment.id" @click="deletePayment(payment)">
                                                {{ deletingPaymentId === payment.id ? 'Removing...' : 'Remove' }}
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="rounded-lg border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                            No payments recorded yet.
                        </div>

                        <div v-if="invoiceOutstanding > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="form-label" for="invoicecreateview-payment-date">Payment date</label>
                                <input id="invoicecreateview-payment-date" v-model="paymentForm.payment_date" type="date" class="form-input" />
                            </div>
                            <div>
                                <label class="form-label" for="invoicecreateview-amount">Amount</label>
                                <input id="invoicecreateview-amount" v-model.number="paymentForm.amount" type="number" min="0.01" step="0.01" class="form-input" />
                            </div>
                            <div>
                                <label class="form-label" for="invoicecreateview-method">Method</label>
                                <input id="invoicecreateview-method" v-model="paymentForm.method" type="text" class="form-input" placeholder="Bank transfer, cash..." />
                            </div>
                            <div>
                                <label class="form-label" for="invoicecreateview-reference">Reference</label>
                                <input id="invoicecreateview-reference" v-model="paymentForm.reference" type="text" class="form-input" placeholder="Optional reference" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" for="invoicecreateview-notes">Notes</label>
                                <textarea id="invoicecreateview-notes" v-model="paymentForm.notes" class="form-input min-h-24 resize-y" placeholder="Optional internal note"></textarea>
                            </div>
                            <div class="sm:col-span-2 flex justify-end">
                                <button type="button" class="btn btn-lg btn-primary btn-block-mobile" :disabled="savingPayment" @click="savePayment">
                                    {{ savingPayment ? 'Saving payment...' : 'Record payment' }}
                                </button>
                            </div>
                        </div>
                        <p v-if="paymentError" class="text-sm text-danger-600 bg-danger-50 p-3 rounded-xl border border-danger-100">{{ paymentError }}</p>
                    </div>
                </div>

                <!-- Line items -->
                <div class="form-card">
                    <div class="form-section-head flex flex-wrap justify-between items-center gap-2">
                        <div>
                            <h2 class="form-section-title">Line items *</h2>
                            <p class="form-section-desc !mt-0.5">Search products or type a custom line.</p>
                        </div>
                        <button
                            type="button"
                            @click="addItem"
                            class="text-sm font-semibold text-success-700 hover:text-success-900 shrink-0"
                        >
                            + Add item
                        </button>
                    </div>
                    <div class="form-body overflow-x-auto">
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
                                        class="form-input"
                                        :ref="el => setProductInputRef(index, el)"
                                        @focus="openProductDropdown(index)"
                                        @input="debounceProductSearch(index)"
                                    />
                                    <Teleport to="body">
                                        <div
                                            v-if="showProductDropdown && activeProductRow === index && productInputRefs[index]"
                                            ref="productDropdownRef"
                                            role="listbox"
                                            aria-label="Matching products"
                                            class="popover-panel fixed max-h-52 overflow-y-auto min-w-[200px]"
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
                                                    role="option"
                                                    :aria-selected="false"
                                                    class="w-full text-left px-3 py-2.5 text-sm hover:bg-slate-50 border-b border-slate-100 last:border-0 break-words"
                                                    @click="selectProduct(index, p)"
                                                >
                                                    <span class="font-medium text-slate-900">{{ p.name }}</span>
                                                    <span v-if="p.category" class="text-slate-500 text-xs"> · {{ p.category }}</span>
                                                </button>
                                                <button
                                                    type="button"
                                                    class="w-full text-left px-3 py-2.5 text-sm hover:bg-primary-50 border-t border-slate-200 text-primary-700 font-medium"
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
                                        class="form-input"
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
                                        class="form-input"
                                    />
                                </div>
                                <div class="sm:col-span-2 text-sm font-medium text-slate-700">
                                    £{{ ((item.quantity || 0) * (item.unit_price || 0)).toFixed(2) }}
                                </div>
                                <div class="sm:col-span-1">
                                    <button
                                        type="button"
                                        @click="removeItem(index)"
                                        class="text-danger-600 hover:text-danger-800 p-2"
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

                <div v-if="!isEditMode" class="form-card">
                    <div class="form-section-head">
                        <h2 class="form-section-title">Payment received now</h2>
                        <p class="form-section-desc">Optional. Use this when the customer pays some or all money while you create the invoice.</p>
                    </div>
                    <div class="form-body space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 rounded-lg bg-slate-50 p-3 text-sm">
                            <div>
                                <div class="text-xs text-slate-500">Invoice total</div>
                                <div class="font-semibold text-slate-900">GBP {{ formatNumber(total) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Paid now</div>
                                <div class="font-semibold text-success-700">GBP {{ formatNumber(paymentForm.amount) }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500">Due after saving</div>
                                <div class="font-semibold" :class="createPaymentOutstanding > 0 ? 'text-danger-700' : 'text-slate-700'">GBP {{ formatNumber(createPaymentOutstanding) }}</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="form-label" for="invoicecreateview-payment-date-2">Payment date</label>
                                <input id="invoicecreateview-payment-date-2" v-model="paymentForm.payment_date" type="date" class="form-input" />
                            </div>
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    <label class="form-label">Amount paid</label>
                                    <button type="button" class="text-xs font-semibold text-success-700 hover:text-success-900" @click="setFullPaymentAmount">
                                        Full amount
                                    </button>
                                </div>
                                <input v-model.number="paymentForm.amount" type="number" min="0" step="0.01" class="form-input" placeholder="Leave 0 if not paid yet" />
                            </div>
                            <div>
                                <label class="form-label" for="invoicecreateview-method-2">Method</label>
                                <input id="invoicecreateview-method-2" v-model="paymentForm.method" type="text" class="form-input" placeholder="Bank transfer, cash..." />
                            </div>
                            <div>
                                <label class="form-label" for="invoicecreateview-reference-2">Reference</label>
                                <input id="invoicecreateview-reference-2" v-model="paymentForm.reference" type="text" class="form-input" placeholder="Optional reference" />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" for="invoicecreateview-notes-2">Notes</label>
                                <textarea id="invoicecreateview-notes-2" v-model="paymentForm.notes" class="form-input min-h-24 resize-y" placeholder="Optional internal note"></textarea>
                            </div>
                        </div>
                        <p v-if="initialPaymentError" class="text-sm text-danger-600 bg-danger-50 p-3 rounded-xl border border-danger-100">{{ initialPaymentError }}</p>
                    </div>
                </div>

                <p v-if="submitError" class="text-sm text-danger-600 bg-danger-50 p-3 rounded-xl border border-danger-100">{{ submitError }}</p>

                <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-2">
                    <router-link to="/invoices" class="btn btn-md btn-outline btn-block-mobile text-center">
                        Cancel
                    </router-link>
                    <button
                        type="submit"
                        :disabled="loading || !canSubmit"
                        class="btn btn-lg btn-primary btn-block-mobile"
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
import { ArrowLeftIcon } from '@heroicons/vue/24/outline';
import { BaseButton } from '@/components/base';
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
const customerActiveIndex = ref(0);
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
const invoicePayments = ref([]);
const savingPayment = ref(false);
const deletingPaymentId = ref(null);
const paymentError = ref('');
// The create-mode card and the edit-mode ledger card each need their own
// error slot; sharing one made an error in either surface in both.
const initialPaymentError = ref('');
const paymentForm = ref({
    payment_date: '',
    amount: '',
    method: '',
    reference: '',
    notes: '',
});
let searchTimeout = null;
let productSearchTimeout = null;

const subtotal = computed(() => {
    return form.value.items.reduce((sum, item) => sum + (item.quantity || 0) * (item.unit_price || 0), 0);
});
const vatAmount = computed(() => {
    return subtotal.value * (form.value.vat_rate || 20) / 100;
});
const total = computed(() => subtotal.value + vatAmount.value);
const invoiceAmountPaid = computed(() => invoicePayments.value.reduce((sum, payment) => sum + Number(payment.amount || 0), 0));
const invoiceTotalForPayments = computed(() => total.value);
const invoiceOutstanding = computed(() => Math.max(0, Number(invoiceTotalForPayments.value || 0) - Number(invoiceAmountPaid.value || 0)));
const createPaymentOutstanding = computed(() => Math.max(0, Number(total.value || 0) - Number(paymentForm.value.amount || 0)));

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

function todayYmd() {
    return new Date().toISOString().split('T')[0];
}

function formatNumber(value) {
    return new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));
}

function formatDate(date) {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

function resetPaymentForm() {
    paymentForm.value = {
        payment_date: todayYmd(),
        // v-model.number expects a number; toFixed() returns a string.
        amount: invoiceOutstanding.value > 0 ? Number(invoiceOutstanding.value.toFixed(2)) : null,
        method: '',
        reference: '',
        notes: '',
    };
}

function setFullPaymentAmount() {
    paymentForm.value.amount = total.value > 0 ? Number(total.value.toFixed(2)) : null;
}

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
            customerActiveIndex.value = 0;
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
    customerActiveIndex.value = 0;
}

/** Arrow-key navigation over the customer listbox. */
function moveCustomerActive(delta) {
    if (!showCustomerDropdown.value) {
        showCustomerDropdown.value = true;
        updateDropdownPosition();
    }
    const count = customerOptions.value.length;
    if (!count) return;
    customerActiveIndex.value = (customerActiveIndex.value + delta + count) % count;
}

function chooseActiveCustomer() {
    const option = customerOptions.value[customerActiveIndex.value];
    if (showCustomerDropdown.value && option) selectCustomer(option);
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
        invoicePayments.value = Array.isArray(data.payments) ? data.payments : [];
        resetPaymentForm();
    } catch (err) {
        submitError.value = err.response?.data?.message || 'Failed to load invoice';
    } finally {
        loadingInvoice.value = false;
    }
}

async function refreshInvoiceAfterPayment(data) {
    invoicePayments.value = Array.isArray(data.payments) ? data.payments : [];
    form.value.status = data.status || form.value.status;
    resetPaymentForm();
}

async function savePayment() {
    if (!invoiceId.value || savingPayment.value) return;
    paymentError.value = '';
    const amount = Number(paymentForm.value.amount || 0);
    if (!paymentForm.value.payment_date || amount <= 0) {
        paymentError.value = 'Enter a valid payment date and amount.';
        return;
    }
    if (amount > invoiceOutstanding.value + 0.01) {
        paymentError.value = 'Payment amount cannot be greater than the outstanding balance.';
        return;
    }

    savingPayment.value = true;
    try {
        const { data } = await axios.post(`/api/invoices/${invoiceId.value}/payments`, {
            payment_date: paymentForm.value.payment_date,
            amount,
            method: paymentForm.value.method || null,
            reference: paymentForm.value.reference || null,
            notes: paymentForm.value.notes || null,
        });
        await refreshInvoiceAfterPayment(data);
        toast.success('Payment recorded');
    } catch (err) {
        paymentError.value = err.response?.data?.message || 'Failed to record payment.';
    } finally {
        savingPayment.value = false;
    }
}

async function deletePayment(payment) {
    if (!invoiceId.value || !payment?.id || deletingPaymentId.value) return;
    deletingPaymentId.value = payment.id;
    paymentError.value = '';
    try {
        const { data } = await axios.delete(`/api/invoices/${invoiceId.value}/payments/${payment.id}`);
        await refreshInvoiceAfterPayment(data);
        toast.success('Payment removed');
    } catch (err) {
        paymentError.value = err.response?.data?.message || 'Failed to remove payment.';
    } finally {
        deletingPaymentId.value = null;
    }
}

async function handleSubmit() {
    if (!canSubmit.value) return;
    loading.value = true;
    submitError.value = null;
    initialPaymentError.value = '';
    try {
        const paymentAmount = Number(paymentForm.value.amount || 0);
        if (!isEditMode.value && paymentAmount > total.value + 0.01) {
            initialPaymentError.value = 'Payment amount cannot be greater than the invoice total.';
            loading.value = false;
            return;
        }

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
        if (!isEditMode.value && paymentAmount > 0) {
            payload.initial_payment = {
                payment_date: paymentForm.value.payment_date || todayYmd(),
                amount: paymentAmount,
                method: paymentForm.value.method || null,
                reference: paymentForm.value.reference || null,
                notes: paymentForm.value.notes || null,
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
    resetPaymentForm();
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
});
</script>
