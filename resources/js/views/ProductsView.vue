<template>
    <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Products</h1>
                <button
                    @click="showForm = true; editingProduct = null"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800"
                >
                    + Add Product
                </button>
            </div>

            <!-- Product Form Modal -->
            <div
                v-if="showForm"
                class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                @click.self="showForm = false"
            >
                <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <h2 class="text-xl font-semibold text-slate-900 mb-4">
                        {{ editingProduct ? 'Edit Product' : 'Add New Product' }}
                    </h2>

                    <form @submit.prevent="handleSubmit" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Product Name *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Category</label>
                            <input
                                v-model="form.category"
                                type="text"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                                placeholder="e.g., Terminal, EPOS, Software"
                            />
                        </div>

                        <div class="flex items-center">
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                id="is_active"
                                class="mr-2"
                            />
                            <label for="is_active" class="text-sm text-slate-700">Active</label>
                        </div>

                        <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded">
                            {{ error }}
                        </div>

                        <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                            <button
                                type="button"
                                @click="showForm = false; editingProduct = null; resetForm()"
                                class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="loading"
                                class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                            >
                                {{ loading ? 'Saving...' : (editingProduct ? 'Update' : 'Create') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Products List -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <div class="p-4 border-b border-slate-200">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search products..."
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>

                <div v-if="loading" class="p-8 text-center text-slate-500">
                    Loading products...
                </div>

                <div v-else-if="filteredProducts.length === 0" class="p-8 text-center text-slate-500">
                    No products found
                </div>

                <div v-else class="overflow-x-auto -mx-4 sm:mx-0">
                    <table class="w-full min-w-[560px]">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                Name
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                Category
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                Description
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">
                                Status
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-slate-200">
                        <tr v-for="product in filteredProducts" :key="product.id">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-900">{{ product.name }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-slate-600">{{ product.category || '-' }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm text-slate-600">{{ product.description || '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span
                                    :class="product.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
                                    class="px-2 py-1 text-xs font-medium rounded-full"
                                >
                                    {{ product.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button
                                    @click="editProduct(product)"
                                    class="text-blue-600 hover:text-blue-900 mr-4"
                                >
                                    Edit
                                </button>
                                <button
                                    @click="deleteProduct(product.id)"
                                    class="text-red-600 hover:text-red-900"
                                >
                                    Delete
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                </div>
            </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();
const products = ref([]);
const loading = ref(false);
const showForm = ref(false);
const editingProduct = ref(null);
const searchQuery = ref('');
const error = ref(null);

const form = ref({
    name: '',
    description: '',
    category: '',
    is_active: true,
});

const filteredProducts = computed(() => {
    if (!searchQuery.value) {
        return products.value;
    }
    const query = searchQuery.value.toLowerCase();
    return products.value.filter(product =>
        product.name.toLowerCase().includes(query) ||
        (product.description && product.description.toLowerCase().includes(query)) ||
        (product.category && product.category.toLowerCase().includes(query))
    );
});

const loadProducts = async () => {
    loading.value = true;
    try {
        const response = await axios.get('/api/products');
        products.value = response.data;
    } catch (err) {
        console.error('Error loading products:', err);
        error.value = 'Failed to load products';
    } finally {
        loading.value = false;
    }
};

const handleSubmit = async () => {
    loading.value = true;
    error.value = null;

    try {
        if (editingProduct.value) {
            await axios.put(`/api/products/${editingProduct.value.id}`, form.value);
        } else {
            await axios.post('/api/products', form.value);
        }
        await loadProducts();
        showForm.value = false;
        editingProduct.value = null;
        resetForm();
    } catch (err) {
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            error.value = Object.values(errors).flat().join(', ');
        } else if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else {
            error.value = 'Failed to save product. Please try again.';
        }
        console.error('Error:', err);
    } finally {
        loading.value = false;
    }
};

const editProduct = (product) => {
    editingProduct.value = product;
    form.value = {
        name: product.name,
        description: product.description || '',
        category: product.category || '',
        is_active: product.is_active,
    };
    showForm.value = true;
};

const deleteProduct = async (id) => {
    if (!confirm('Are you sure you want to delete this product?')) {
        return;
    }

    try {
        await axios.delete(`/api/products/${id}`);
        await loadProducts();
    } catch (err) {
        toast.error('Failed to delete product. It may be in use.');
        console.error('Error:', err);
    }
};

const resetForm = () => {
    form.value = {
        name: '',
        description: '',
        category: '',
        is_active: true,
    };
    error.value = null;
};

onMounted(() => {
    loadProducts();
});
</script>

