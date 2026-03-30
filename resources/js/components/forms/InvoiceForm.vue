<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-3xl w-full mx-4 my-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-slate-900">
                    {{ invoice ? 'Edit Invoice' : 'Create New Invoice' }}
                </h3>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                        <label class="block text-sm font-medium text-slate-700 mb-1">Invoice Date *</label>
                        <input
                            v-model="form.invoice_date"
                            type="date"
                            required
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
                            v-model="form.vat_rate"
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
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                                />
                            </div>
                            <div class="col-span-2">
                                <input
                                    v-model.number="item.quantity"
                                    type="number"
                                    min="1"
                                    placeholder="Qty"
                                    required
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                                />
                            </div>
                            <div class="col-span-2">
                                <input
                                    v-model.number="item.unit_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Price"
                                    required
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                                />
                            </div>
                            <div class="col-span-2 text-sm text-slate-600">
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

                <div class="border-t pt-4">
                    <div class="flex justify-end">
                        <div class="text-right space-y-1">
                            <div class="text-sm text-slate-600">
                                Subtotal: £{{ subtotal.toFixed(2) }}
                            </div>
                            <div class="text-sm text-slate-600">
                                VAT ({{ form.vat_rate || 20 }}%): £{{ vatAmount.toFixed(2) }}
                            </div>
                            <div class="text-lg font-semibold text-slate-900">
                                Total: £{{ total.toFixed(2) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded">
                    {{ error }}
                </div>

                <div class="flex justify-end gap-3 pt-4">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 text-sm border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50"
                        :disabled="loading"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800"
                        :disabled="loading || form.items.length === 0"
                    >
                        {{ loading ? 'Saving...' : (invoice ? 'Update' : 'Create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    invoice: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const form = reactive({
    customer_id: '',
    invoice_date: new Date().toISOString().split('T')[0],
    due_date: '',
    vat_rate: 20,
    items: [{ description: '', quantity: 1, unit_price: 0 }],
});

const customers = ref([]);
const loading = ref(false);
const error = ref(null);

const subtotal = computed(() => {
    return form.items.reduce((sum, item) => {
        return sum + ((item.quantity || 0) * (item.unit_price || 0));
    }, 0);
});

const vatAmount = computed(() => {
    return subtotal.value * ((form.vat_rate || 20) / 100);
});

const total = computed(() => {
    return subtotal.value + vatAmount.value;
});

onMounted(async () => {
    await loadCustomers();

    if (props.invoice) {
        Object.assign(form, {
            customer_id: props.invoice.customer_id || '',
            invoice_date: props.invoice.invoice_date || new Date().toISOString().split('T')[0],
            due_date: props.invoice.due_date || '',
            vat_rate: props.invoice.vat_rate || 20,
            items: props.invoice.items?.map(item => ({
                description: item.description,
                quantity: item.quantity,
                unit_price: parseFloat(item.unit_price),
            })) || [{ description: '', quantity: 1, unit_price: 0 }],
        });
    } else {
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 30);
        form.due_date = dueDate.toISOString().split('T')[0];
    }
});

const loadCustomers = async () => {
    try {
        const { data } = await axios.get('/api/customers', { params: { per_page: 100 } });
        customers.value = data.data || [];
    } catch (err) {
        console.error('Failed to load customers:', err);
    }
};

const addItem = () => {
    form.items.push({ description: '', quantity: 1, unit_price: 0 });
};

const removeItem = (index) => {
    if (form.items.length > 1) {
        form.items.splice(index, 1);
    }
};

const handleSubmit = async () => {
    if (form.items.length === 0 || form.items.some(item => !item.description)) {
        error.value = 'Please add at least one item with description';
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const data = {
            customer_id: form.customer_id,
            invoice_date: form.invoice_date,
            due_date: form.due_date || null,
            vat_rate: form.vat_rate || 20,
            items: form.items.map(item => ({
                description: item.description,
                quantity: parseInt(item.quantity),
                unit_price: parseFloat(item.unit_price),
            })),
        };

        if (props.invoice) {
            await axios.put(`/api/invoices/${props.invoice.id}`, data);
        } else {
            await axios.post('/api/invoices', data);
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

