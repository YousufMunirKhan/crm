<template>
    <div class="form-card mb-6">
        <div class="form-section-head">
            <h3 class="form-section-title">Add Items (Required to Close Deal)</h3>
        </div>

        <form @submit.prevent="handleSubmit" class="p-4 sm:p-6 space-y-4">
            <div v-for="(item, index) in items" :key="index" class="border border-slate-200 rounded-card p-4 space-y-3">
                <div class="flex justify-between items-center mb-2">
                    <h4 class="font-medium text-slate-900">Item {{ index + 1 }}</h4>
                    <BaseButton
                        v-if="items.length > 1"
                        variant="ghost"
                        size="sm"
                        class="text-danger-700"
                        @click="removeItem(index)"
                    >
                        <template #icon>
                            <TrashIcon class="icon-sm" aria-hidden="true" />
                        </template>
                        Remove
                    </BaseButton>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label class="form-label mb-0" :for="`leaditemsform-product-${index}`">Product <span class="form-required">*</span></label>
                            <router-link
                                to="/products"
                                target="_blank"
                                class="link text-xs inline-flex items-center gap-1"
                            >
                                <PlusIcon class="icon-sm" aria-hidden="true" />
                                Add New Product
                            </router-link>
                        </div>
                        <select
                            :id="`leaditemsform-product-${index}`"
                            v-model="item.product_id"
                            required
                            class="form-select"
                        >
                            <option value="">Select product...</option>
                            <option v-for="product in products" :key="product.id" :value="product.id">
                                {{ product.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" :for="`leaditemsform-quantity-${index}`">Quantity <span class="form-required">*</span></label>
                        <input
                            :id="`leaditemsform-quantity-${index}`"
                            v-model.number="item.quantity"
                            type="number"
                            min="1"
                            required
                            class="form-input"
                        />
                    </div>

                    <div>
                        <label class="form-label" :for="`leaditemsform-unit-price-${index}`">Unit Price (£) <span class="form-required">*</span></label>
                        <input :id="`leaditemsform-unit-price-${index}`"
                            v-model.number="item.unit_price"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            class="form-input"
                        />
                    </div>

                    <div>
                        <span class="form-label">Total</span>
                        <p class="text-sm font-semibold text-slate-900 tabular-nums">£{{ ((item.quantity || 0) * (item.unit_price || 0)).toFixed(2) }}</p>
                    </div>
                </div>

                <div>
                    <label class="form-label" :for="`leaditemsform-notes-${index}`">Notes</label>
                    <textarea :id="`leaditemsform-notes-${index}`"
                        v-model="item.notes"
                        rows="2"
                        class="form-textarea"
                        placeholder="Additional notes..."
                    />
                </div>
            </div>

            <BaseButton variant="outline" class="w-full border-dashed" @click="addItem">
                <template #icon>
                    <PlusIcon class="icon-sm" aria-hidden="true" />
                </template>
                Add Another Item
            </BaseButton>

            <div v-if="error" class="callout callout-danger" role="alert">
                {{ error }}
            </div>

            <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-slate-200">
                <BaseButton variant="outline" block-mobile @click="$emit('cancel')">
                    Cancel
                </BaseButton>
                <BaseButton variant="primary" type="submit" block-mobile :loading="loading">
                    {{ loading ? 'Saving...' : 'Save Items' }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { BaseButton } from '@/components/base';

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
