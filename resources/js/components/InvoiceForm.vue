<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-slate-200">
                <h2 class="text-xl font-semibold text-slate-900">
                    {{ invoice ? 'Edit Invoice' : 'Create New Invoice' }}
                </h2>
            </div>

            <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Customer *</label>
                        <select
                            v-model="form.customer_id"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="">Select Customer</option>
                            <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                {{ customer.name }} - {{ customer.phone }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Invoice Date</label>
                        <input
                            v-model="form.invoice_date"
                            type="date"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Due Date</label>
                        <input
                            v-model="form.due_date"
                            type="date"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">VAT Rate (%)</label>
                        <input
                            v-model.number="form.vat_rate"
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                </div>

                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-medium text-slate-700">Invoice Items *</label>
                        <button
                            type="button"
                            @click="addItem"
                            class="text-sm text-blue-600 hover:underline"
                        >
                            + Add Item
                        </button>
                    </div>

                    <div class="space-y-2">
                        <div
                            v-for="(item, index) in form.items"
                            :key="index"
                            class="grid grid-cols-12 gap-2 items-end"
                        >
                            <div class="col-span-5">
                                <input
                                    v-model="item.description"
                                    type="text"
                                    placeholder="Description"
                                    required
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                                />
                            </div>
                            <div class="col-span-2">
                                <input
                                    v-model.number="item.quantity"
                                    type="number"
                                    min="1"
                                    placeholder="Qty"
                                    required
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                                />
                            </div>
                            <div class="col-span-2">
                                <input
                                    v-model.number="item.unit_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Unit Price"
                                    required
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                                />
                            </div>
                            <div class="col-span-2 text-sm font-medium text-slate-700">
                                £{{ ((item.quantity || 0) * (item.unit_price || 0)).toFixed(2) }}
                            </div>
                            <div class="col-span-1">
                                <button
                                    type="button"
                                    @click="removeItem(index)"
                                    class="text-red-600 hover:text-red-800"
                                >
                                    ×
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-50 p-4 rounded-lg">
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

                <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded">
                    {{ error }}
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="loading || form.items.length === 0"
                        class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                    >
                        {{ loading ? 'Saving...' : (invoice ? 'Update' : 'Create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

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

