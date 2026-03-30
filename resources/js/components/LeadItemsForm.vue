<template>
    <div class="bg-white rounded-xl shadow-sm p-6 mb-6">
        <h3 class="text-lg font-semibold text-slate-900 mb-4">Add Items (Required to Close Deal)</h3>

        <form @submit.prevent="handleSubmit" class="space-y-4">
            <div v-for="(item, index) in items" :key="index" class="border border-slate-200 rounded-lg p-4 space-y-3">
                <div class="flex justify-between items-center mb-2">
                    <h4 class="font-medium text-slate-900">Item {{ index + 1 }}</h4>
                    <button
                        v-if="items.length > 1"
                        type="button"
                        @click="removeItem(index)"
                        class="text-red-600 hover:text-red-800 text-sm"
                    >
                        Remove
                    </button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-sm font-medium text-slate-700">Product *</label>
                            <router-link
                                to="/products"
                                target="_blank"
                                class="text-xs text-blue-600 hover:text-blue-800 underline"
                            >
                                + Add New Product
                            </router-link>
                        </div>
                        <select
                            v-model="item.product_id"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="">Select product...</option>
                            <option v-for="product in products" :key="product.id" :value="product.id">
                                {{ product.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Quantity *</label>
                        <input
                            v-model.number="item.quantity"
                            type="number"
                            min="1"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Unit Price (£) *</label>
                        <input
                            v-model.number="item.unit_price"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Total: £{{ ((item.quantity || 0) * (item.unit_price || 0)).toFixed(2) }}</label>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                    <textarea
                        v-model="item.notes"
                        rows="2"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        placeholder="Additional notes..."
                    />
                </div>
            </div>

            <button
                type="button"
                @click="addItem"
                class="w-full py-2 border-2 border-dashed border-slate-300 rounded-lg text-slate-600 hover:border-slate-400 hover:text-slate-700"
            >
                + Add Another Item
            </button>

            <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded">
                {{ error }}
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                <button
                    type="button"
                    @click="$emit('cancel')"
                    class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="loading"
                    class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                >
                    {{ loading ? 'Saving...' : 'Save Items' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const props = defineProps({
    leadId: {
        type: [Number, String],
        required: true,
    },
});

const emit = defineEmits(['saved', 'cancel']);

const items = ref([
    {
        product_id: null,
        quantity: 1,
        unit_price: 0,
        notes: '',
    },
]);

const products = ref([]);
const loading = ref(false);
const error = ref(null);

const loadProducts = async () => {
    try {
        const response = await axios.get('/api/products');
        products.value = response.data;
    } catch (err) {
        console.error('Error loading products:', err);
    }
};

const addItem = () => {
    items.value.push({
        product_id: null,
        quantity: 1,
        unit_price: 0,
        notes: '',
    });
};

const removeItem = (index) => {
    items.value.splice(index, 1);
};

const handleSubmit = async () => {
    loading.value = true;
    error.value = null;

    try {
        await axios.post(`/api/leads/${props.leadId}/items`, {
            items: items.value,
        });

        emit('saved');
    } catch (err) {
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            error.value = Object.values(errors).flat().join(', ');
        } else if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else {
            error.value = 'Failed to save items. Please try again.';
        }
        console.error('Error:', err);
    } finally {
        loading.value = false;
    }
};

// Reload products when window regains focus (in case product was added in another tab)
const handleFocus = () => {
    loadProducts();
};

onMounted(() => {
    loadProducts();
    window.addEventListener('focus', handleFocus);
});
</script>

