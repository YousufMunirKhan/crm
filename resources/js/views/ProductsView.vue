<template>
    <ListingPageShell
        title="Products"
        subtitle="Manage catalog items used on quotes and invoices — keep names and categories consistent."
        :badge="productsBadge"
    >
        <template #actions>
            <button type="button" class="listing-btn-accent w-full sm:w-auto touch-manipulation" @click="showForm = true; editingProduct = null">
                + Add product
            </button>
        </template>

        <template #filters>
            <div class="listing-filters-row">
                <div class="flex-1 min-w-0 w-full sm:min-w-[12rem] sm:max-w-md">
                    <label class="listing-label">Search</label>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Product name, category, or description..."
                        class="listing-input"
                    />
                </div>
                <button type="button" class="listing-btn-primary w-full sm:w-auto shrink-0 touch-manipulation" @click="productListPage = 1">
                    Filter
                </button>
            </div>
        </template>

        <div v-if="loading" class="px-5 py-12 text-center text-slate-500 text-sm">
            Loading products...
        </div>
        <div v-else-if="filteredProducts.length === 0" class="px-5 py-12 text-center text-slate-500 text-sm">
            No products found
        </div>
        <div v-else class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[560px]">
                <thead class="listing-thead">
                    <tr>
                        <th class="listing-th">Name</th>
                        <th class="listing-th">Category</th>
                        <th class="listing-th">Description</th>
                        <th class="listing-th">Status</th>
                        <th class="listing-th text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="product in pagedProducts" :key="product.id" class="listing-row">
                        <td class="listing-td-strong">{{ product.name }}</td>
                        <td class="listing-td">{{ product.category || '—' }}</td>
                        <td class="listing-td max-w-xs truncate" :title="product.description || ''">
                            {{ product.description || '—' }}
                        </td>
                        <td class="listing-td">
                            <span :class="product.is_active ? 'listing-badge-active' : 'listing-badge-inactive'">
                                {{ product.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="listing-td text-right">
                            <button type="button" class="listing-link-edit mr-3" @click="editProduct(product)">
                                Edit
                            </button>
                            <button type="button" class="listing-link-delete" @click="deleteProduct(product.id)">
                                Delete
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="!loading && filteredProducts.length" class="md:hidden space-y-3 px-3 pb-3">
            <div
                v-for="product in pagedProducts"
                :key="`mobile-${product.id}`"
                class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-2"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="text-sm font-semibold text-slate-900">{{ product.name }}</div>
                    <span :class="product.is_active ? 'listing-badge-active' : 'listing-badge-inactive'">
                        {{ product.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="text-sm text-slate-600">Category: {{ product.category || '—' }}</div>
                <div class="text-sm text-slate-600 break-words">Description: {{ product.description || '—' }}</div>
                <div class="flex flex-wrap gap-3 pt-1">
                    <button type="button" class="listing-link-edit" @click="editProduct(product)">Edit</button>
                    <button type="button" class="listing-link-delete" @click="deleteProduct(product.id)">Delete</button>
                </div>
            </div>
        </div>

        <template #pagination>
            <Pagination
                v-if="filteredProducts.length > 0"
                :pagination="productPagination"
                embedded
                result-label="products"
                singular-label="product"
                @page-change="onProductPageChange"
            />
        </template>
    </ListingPageShell>

    <!-- Product Form Modal -->
    <div
        v-if="showForm"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4"
        @click.self="showForm = false"
    >
        <div class="form-card w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-xl shadow-slate-900/10">
            <div class="form-section-head-mint">
                <h2 class="form-section-title-mint text-xl">
                    {{ editingProduct ? 'Edit Product' : 'Create New Product' }}
                </h2>
                <p class="form-section-desc-mint">
                    {{ editingProduct ? 'Update catalog details below — changes apply wherever this product is used.' : 'Products created here will be synced to POS.' }}
                </p>
            </div>

            <form @submit.prevent="handleSubmit" class="form-body space-y-4">
                <div>
                    <label class="form-label">Product Name *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="form-input resize-none"
                    />
                </div>

                <div>
                    <label class="form-label">Category</label>
                    <input
                        v-model="form.category"
                        type="text"
                        class="form-input"
                        placeholder="e.g., Terminal, EPOS, Software"
                    />
                </div>

                <div class="flex items-center gap-2">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        id="is_active"
                        class="form-checkbox"
                    />
                    <label for="is_active" class="text-sm font-medium text-slate-700">Active</label>
                </div>

                <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded-xl border border-red-100">
                    {{ error }}
                </div>

                <div class="form-actions !px-0 !py-0 !bg-transparent !border-0 pt-2">
                    <button
                        type="button"
                        @click="showForm = false; editingProduct = null; resetForm()"
                        class="form-btn-cancel"
                    >
                        Cancel
                    </button>
                    <button type="submit" :disabled="loading" class="form-btn-submit">
                        {{ loading ? 'Saving...' : (editingProduct ? 'Update product' : 'Create product') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import ListingPageShell from '@/components/ListingPageShell.vue';
import Pagination from '@/components/Pagination.vue';

const toast = useToastStore();
const products = ref([]);
const loading = ref(false);
const showForm = ref(false);
const editingProduct = ref(null);
const searchQuery = ref('');
const error = ref(null);

const productsPerPage = 10;
const productListPage = ref(1);

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
    return products.value.filter(
        (product) =>
            product.name.toLowerCase().includes(query) ||
            (product.description && product.description.toLowerCase().includes(query)) ||
            (product.category && product.category.toLowerCase().includes(query)),
    );
});

const productPagination = computed(() => {
    const total = filteredProducts.value.length;
    const last_page = Math.max(1, Math.ceil(total / productsPerPage));
    let current = productListPage.value;
    if (current > last_page) current = last_page;
    if (current < 1) current = 1;
    return {
        current_page: current,
        last_page,
        per_page: productsPerPage,
        total,
    };
});

const pagedProducts = computed(() => {
    const { current_page, per_page } = productPagination.value;
    const start = (current_page - 1) * per_page;
    return filteredProducts.value.slice(start, start + per_page);
});

const productsBadge = computed(() =>
    filteredProducts.value.length ? `${filteredProducts.value.length} Total` : null,
);

watch(searchQuery, () => {
    productListPage.value = 1;
});

watch(filteredProducts, () => {
    const last = Math.max(1, Math.ceil(filteredProducts.value.length / productsPerPage));
    if (productListPage.value > last) {
        productListPage.value = last;
    }
});

function onProductPageChange(page) {
    productListPage.value = page;
}

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
