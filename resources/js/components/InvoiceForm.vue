<template>
    <BaseModal
        :model-value="true"
        :title="invoice ? 'Edit Invoice' : 'Create New Invoice'"
        size="lg"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <form id="invoice-form" class="space-y-4" @submit.prevent="handleSubmit">
            <div class="form-grid-2">
                <div>
                    <label class="form-label" for="invoiceform-customer">Customer <span class="form-required" aria-hidden="true">*</span></label>
                    <select id="invoiceform-customer"
                        v-model="form.customer_id"
                        required
                        class="form-select"
                    >
                        <option value="">Select Customer</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }} - {{ customer.phone }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="invoiceform-invoice-date">Invoice Date</label>
                    <input id="invoiceform-invoice-date"
                        v-model="form.invoice_date"
                        type="date"
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label" for="invoiceform-due-date">Due Date</label>
                    <input id="invoiceform-due-date"
                        v-model="form.due_date"
                        type="date"
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label" for="invoiceform-vat-rate">VAT Rate (%)</label>
                    <input id="invoiceform-vat-rate"
                        v-model.number="form.vat_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        class="form-input"
                    />
                </div>
            </div>

            <div>
                <div class="mb-2 flex items-center justify-between gap-3">
                    <span class="form-label mb-0">Invoice Items <span class="form-required" aria-hidden="true">*</span></span>
                    <BaseButton variant="ghost" size="sm" @click="addItem">
                        <template #icon>
                            <PlusIcon class="icon-sm" aria-hidden="true" />
                        </template>
                        Add Item
                    </BaseButton>
                </div>

                <div class="space-y-2">
                    <div
                        v-for="(item, index) in form.items"
                        :key="index"
                        class="grid grid-cols-12 gap-2 items-end"
                    >
                        <div class="col-span-5">
                            <label class="sr-only" :for="`invoiceform-item-${index}-description`">Item {{ index + 1 }} description</label>
                            <input :id="`invoiceform-item-${index}-description`"
                                v-model="item.description"
                                type="text"
                                placeholder="Description"
                                required
                                class="form-input text-sm"
                            />
                        </div>
                        <div class="col-span-2">
                            <label class="sr-only" :for="`invoiceform-item-${index}-quantity`">Item {{ index + 1 }} quantity</label>
                            <input :id="`invoiceform-item-${index}-quantity`"
                                v-model.number="item.quantity"
                                type="number"
                                min="1"
                                placeholder="Qty"
                                required
                                class="form-input text-sm"
                            />
                        </div>
                        <div class="col-span-2">
                            <label class="sr-only" :for="`invoiceform-item-${index}-unit-price`">Item {{ index + 1 }} unit price</label>
                            <input :id="`invoiceform-item-${index}-unit-price`"
                                v-model.number="item.unit_price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Unit Price"
                                required
                                class="form-input text-sm"
                            />
                        </div>
                        <div class="col-span-2 text-sm font-medium text-slate-700">
                            £{{ ((item.quantity || 0) * (item.unit_price || 0)).toFixed(2) }}
                        </div>
                        <div class="col-span-1">
                            <BaseButton
                                variant="ghost"
                                size="icon"
                                :label="`Remove item ${index + 1}`"
                                @click="removeItem(index)"
                            >
                                <TrashIcon class="icon-sm text-danger-700" aria-hidden="true" />
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-card bg-slate-50 p-4">
                <div class="flex justify-between text-sm mb-1">
                    <span>Subtotal:</span>
                    <span class="font-medium">£{{ subtotal.toFixed(2) }}</span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                    <span>VAT ({{ form.vat_rate || 20 }}%):</span>
                    <span class="font-medium">£{{ vatAmount.toFixed(2) }}</span>
                </div>
                <div class="flex justify-between text-lg font-semibold pt-2 border-t border-slate-200">
                    <span>Total:</span>
                    <span>£{{ total.toFixed(2) }}</span>
                </div>
            </div>

            <p v-if="error" class="callout callout-danger" role="alert">
                {{ error }}
            </p>
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="$emit('close')">Cancel</BaseButton>
            <BaseButton
                variant="primary"
                type="submit"
                form="invoice-form"
                block-mobile
                :loading="loading"
                :disabled="form.items.length === 0"
            >
                {{ loading ? 'Saving...' : (invoice ? 'Update' : 'Create') }}
            </BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { BaseButton, BaseModal } from '@/components/base';

const props = defineProps({
    invoice: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const form = ref({
    customer_id: '',
    invoice_date: new Date().toISOString().split('T')[0],
    due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
    vat_rate: 20,
    status: 'draft',
    items: [{ description: '', quantity: 1, unit_price: 0 }],
});

const customers = ref([]);
const loading = ref(false);
const error = ref(null);

const subtotal = computed(() => {
    return form.value.items.reduce((sum, item) => {
        return sum + (item.quantity || 0) * (item.unit_price || 0);
    }, 0);
});

const vatAmount = computed(() => {
    return subtotal.value * (form.value.vat_rate || 20) / 100;
});

const total = computed(() => {
    return subtotal.value + vatAmount.value;
});

onMounted(async () => {
    try {
        const { data } = await axios.get('/api/customers', { params: { per_page: 100 } });
        customers.value = data.data || [];
    } catch (err) {
        console.error('Failed to load customers:', err);
    }

    if (props.invoice) {
        form.value = {
            customer_id: props.invoice.customer_id,
            invoice_date: props.invoice.invoice_date,
            due_date: props.invoice.due_date,
            vat_rate: parseFloat(props.invoice.vat_rate),
            status: props.invoice.status,
            items: props.invoice.items?.map(item => ({
                description: item.description,
                quantity: item.quantity,
                unit_price: parseFloat(item.unit_price),
            })) || [],
        };
    }
});

const addItem = () => {
    form.value.items.push({ description: '', quantity: 1, unit_price: 0 });
};

const removeItem = (index) => {
    if (form.value.items.length > 1) {
        form.value.items.splice(index, 1);
    }
};

const handleSubmit = async () => {
    loading.value = true;
    error.value = null;

    try {
        if (props.invoice) {
            await axios.put(`/api/invoices/${props.invoice.id}`, form.value);
        } else {
            await axios.post('/api/invoices', form.value);
        }
        emit('saved');
        emit('close');
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to save invoice';
    } finally {
        loading.value = false;
    }
};
</script>

