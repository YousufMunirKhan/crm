<template>
    <ListingPageShell
        title="Products"
        subtitle="Manage catalog items used on quotes and invoices — keep names and categories consistent."
        :badge="productsBadge"
    >
        <template #actions>
            <BaseButton variant="primary" block-mobile @click="openCreate">
                <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                Add product
            </BaseButton>
        </template>

        <template #filters>
            <div class="listing-filters-row">
                <div class="flex-1 min-w-0 w-full sm:min-w-[12rem] sm:max-w-md">
                    <label class="listing-label" for="productsview-search">Search</label>
                    <div class="relative">
                        <MagnifyingGlassIcon
                            class="icon absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"
                            aria-hidden="true"
                        />
                        <input
                            id="productsview-search"
                            v-model="searchQuery"
                            type="search"
                            placeholder="Product name, category, or description..."
                            class="form-input-search"
                        />
                    </div>
                </div>
                <BaseButton variant="soft" block-mobile class="shrink-0" @click="productListPage = 1">
                    Filter
                </BaseButton>
            </div>
        </template>

        <BaseTable
            :columns="columns"
            :rows="pagedProducts"
            :loading="loading"
            min-width="560px"
            caption="Products in the catalog"
        >
            <template #cell-name="{ row }">
                <span class="font-semibold text-slate-800">{{ row.name }}</span>
            </template>
            <template #cell-category="{ row }">{{ row.category || '—' }}</template>
            <template #cell-description="{ row }">
                <span class="block max-w-xs truncate" :title="row.description || ''">
                    {{ row.description || '—' }}
                </span>
            </template>
            <template #cell-is_active="{ row }">
                <BaseBadge :tone="row.is_active ? 'success' : 'danger'">
                    {{ row.is_active ? 'Active' : 'Inactive' }}
                </BaseBadge>
            </template>

            <template #actions="{ row }">
                <BaseButton variant="ghost" size="sm" @click="editProduct(row)">Edit</BaseButton>
                <BaseButton
                    variant="ghost"
                    size="sm"
                    class="text-danger-600 hover:text-danger-800"
                    @click="askDelete(row)"
                >
                    Delete
                </BaseButton>
            </template>

            <template #empty>
                <EmptyState
                    heading="No products found"
                    description="Add your first catalog item, or clear the search above."
                >
                    <template #icon><CubeIcon class="icon" aria-hidden="true" /></template>
                    <template #action>
                        <BaseButton variant="primary" @click="openCreate">Add product</BaseButton>
                    </template>
                </EmptyState>
            </template>
        </BaseTable>

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

    <!-- Product form -->
    <BaseModal
        v-model="showForm"
        :title="editingProduct ? 'Edit Product' : 'Create New Product'"
        :description="
            editingProduct
                ? 'Update catalog details below — changes apply wherever this product is used.'
                : 'Products created here will be synced to POS.'
        "
        size="md"
        :close-on-backdrop="false"
        @close="closeForm"
    >
        <form id="product-form" class="space-y-4" novalidate @submit.prevent="handleSubmit">
            <div>
                <label class="form-label" for="productsview-product-name">Product Name *</label>
                <input id="productsview-product-name" v-model="form.name" type="text" required class="form-input" />
            </div>

            <div>
                <label class="form-label" for="productsview-description">Description</label>
                <textarea
                    id="productsview-description"
                    v-model="form.description"
                    rows="3"
                    class="form-input resize-none"
                />
            </div>

            <div class="form-grid-2">
                <div>
                    <label for="product-sku" class="form-label">SKU</label>
                    <input id="product-sku" v-model="form.sku" type="text" class="form-input" placeholder="Optional" />
                </div>
                <div>
                    <label for="product-category" class="form-label">Category</label>
                    <input
                        id="product-category"
                        v-model="form.category"
                        type="text"
                        class="form-input"
                        placeholder="e.g., Terminal, EPOS, Software"
                    />
                </div>
            </div>

            <div class="form-grid-3">
                <div>
                    <label for="product-price" class="form-label">Sale price</label>
                    <input
                        id="product-price"
                        v-model="form.unit_price"
                        type="number"
                        step="0.01"
                        min="0"
                        class="form-input"
                        placeholder="0.00"
                    />
                </div>
                <div>
                    <label for="product-cost" class="form-label">Cost price</label>
                    <input
                        id="product-cost"
                        v-model="form.cost_price"
                        type="number"
                        step="0.01"
                        min="0"
                        class="form-input"
                        placeholder="0.00"
                        aria-describedby="product-cost-hint"
                    />
                    <p id="product-cost-hint" class="form-hint">Used for margin. Not shown outside management.</p>
                </div>
                <div>
                    <label for="product-unit" class="form-label">Unit</label>
                    <input
                        id="product-unit"
                        v-model="form.unit"
                        type="text"
                        class="form-input"
                        placeholder="each, month, hour"
                    />
                </div>
            </div>

            <div v-if="margin !== null" class="text-xs text-slate-500">
                Margin: <span class="font-semibold text-slate-700">{{ formatMoney(margin) }}</span>
                <span v-if="marginPercent !== null"> ({{ marginPercent }}%)</span>
            </div>

            <div class="flex items-center gap-2">
                <input v-model="form.is_active" type="checkbox" id="is_active" class="form-checkbox" />
                <label for="is_active" class="text-sm font-medium text-slate-700">Active</label>
            </div>

            <!--
                Cross-sell links. The read path and both consuming screens
                already existed; there was no way to create a row, so the
                suggestions panel always rendered empty.
            -->
            <div v-if="editingProduct" class="pt-2 border-t border-slate-100">
                <p class="form-label">Suggested / upsell products</p>

                <ul v-if="relationships.length" class="space-y-2 mb-3">
                    <li
                        v-for="rel in relationships"
                        :key="rel.id"
                        class="flex items-center justify-between gap-2 rounded-control border border-slate-200 px-3 py-2"
                    >
                        <span class="min-w-0 text-sm text-slate-700 truncate">
                            {{ rel.name }}
                            <span class="ml-1 text-eyebrow uppercase text-primary-700">{{ rel.relationship_type }}</span>
                        </span>
                        <BaseButton
                            variant="ghost"
                            size="sm"
                            class="shrink-0 text-danger-600 hover:text-danger-800"
                            @click="removeRelationship(rel.id)"
                        >
                            Remove
                        </BaseButton>
                    </li>
                </ul>
                <p v-else class="text-sm text-slate-500 mb-3">No linked products yet.</p>

                <div class="flex flex-col sm:flex-row sm:items-end gap-2">
                    <div class="min-w-0 sm:flex-1">
                        <label class="form-label" for="product-link-target">Product to link</label>
                        <select id="product-link-target" v-model="newRelation.to_product_id" class="form-select">
                            <option :value="null">Choose a product…</option>
                            <option v-for="p in linkableProducts" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </div>
                    <div class="min-w-0 sm:w-40">
                        <label class="form-label" for="product-link-type">Relationship type</label>
                        <select id="product-link-type" v-model="newRelation.relationship_type" class="form-select">
                            <option value="suggest">Suggest</option>
                            <option value="upsell">Upsell</option>
                            <option value="cross_sell">Cross-sell</option>
                        </select>
                    </div>
                    <BaseButton variant="soft" :disabled="!newRelation.to_product_id" @click="addRelationship">
                        Link
                    </BaseButton>
                </div>
            </div>

            <div v-if="error" class="callout callout-danger" role="alert">
                {{ error }}
            </div>
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="cancelForm">Cancel</BaseButton>
            <BaseButton variant="primary" size="lg" type="submit" form="product-form" block-mobile :loading="loading">
                {{ editingProduct ? 'Update product' : 'Create product' }}
            </BaseButton>
        </template>
    </BaseModal>

    <ConfirmDialog
        v-model="confirmDeleteOpen"
        title="Delete product?"
        :message="`Are you sure you want to delete ${deleteTarget?.name ?? 'this product'}? This cannot be undone.`"
        confirm-label="Delete"
        :loading="deleting"
        @confirm="confirmDelete"
    />
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { CubeIcon, MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import ListingPageShell from '@/components/ListingPageShell.vue';
import Pagination from '@/components/Pagination.vue';
import {
    BaseBadge,
    BaseButton,
    BaseModal,
    BaseTable,
    ConfirmDialog,
    EmptyState,
} from '@/components/base';

const toast = useToastStore();
const products = ref([]);
const loading = ref(false);
const showForm = ref(false);
const editingProduct = ref(null);
const searchQuery = ref('');
const error = ref(null);

const confirmDeleteOpen = ref(false);
const deleteTarget = ref(null);
const deleting = ref(false);

const productsPerPage = 10;
const productListPage = ref(1);

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'category', label: 'Category' },
    { key: 'description', label: 'Description' },
    { key: 'is_active', label: 'Status' },
];

const form = ref({
    name: '',
    sku: '',
    description: '',
    unit_price: null,
    cost_price: null,
    unit: '',
    category: '',
    is_active: true,
});

const relationships = ref([]);
const newRelation = ref({ to_product_id: null, relationship_type: 'suggest' });

const linkableProducts = computed(() => {
    const linkedIds = new Set(relationships.value.map((r) => r.id));
    return products.value.filter(
        (p) => p.id !== editingProduct.value?.id && !linkedIds.has(p.id),
    );
});

function formatMoney(value) {
    return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(value);
}

const margin = computed(() => {
    const price = parseFloat(form.value.unit_price);
    const cost = parseFloat(form.value.cost_price);
    if (Number.isNaN(price) || Number.isNaN(cost)) return null;
    return Math.round((price - cost) * 100) / 100;
});

const marginPercent = computed(() => {
    const price = parseFloat(form.value.unit_price);
    if (Number.isNaN(price) || price <= 0 || margin.value === null) return null;
    return Math.round((margin.value / price) * 1000) / 10;
});

async function loadRelationships(productId) {
    relationships.value = [];
    if (!productId) return;
    try {
        const { data } = await axios.get(`/api/products/${productId}/relationships`);
        relationships.value = data.suggests || [];
    } catch {
        relationships.value = [];
    }
}

async function addRelationship() {
    if (!editingProduct.value || !newRelation.value.to_product_id) return;
    try {
        await axios.post(`/api/products/${editingProduct.value.id}/relationships`, newRelation.value);
        newRelation.value = { to_product_id: null, relationship_type: 'suggest' };
        await loadRelationships(editingProduct.value.id);
        toast.success('Product linked');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Could not link that product');
    }
}

async function removeRelationship(toProductId) {
    if (!editingProduct.value) return;
    try {
        await axios.delete(`/api/products/${editingProduct.value.id}/relationships/${toProductId}`);
        await loadRelationships(editingProduct.value.id);
    } catch (e) {
        toast.error('Could not remove that link');
    }
}

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

function openCreate() {
    editingProduct.value = null;
    showForm.value = true;
}

/** Runs on Escape, backdrop and the close button - same reset Cancel does. */
function closeForm() {
    editingProduct.value = null;
    resetForm();
}

function cancelForm() {
    showForm.value = false;
    closeForm();
}

const editProduct = (product) => {
    editingProduct.value = product;
    form.value = {
        name: product.name,
        sku: product.sku || '',
        description: product.description || '',
        unit_price: product.unit_price ?? null,
        cost_price: product.cost_price ?? null,
        unit: product.unit || '',
        category: product.category || '',
        is_active: product.is_active,
    };
    loadRelationships(product.id);
    showForm.value = true;
};

function askDelete(product) {
    deleteTarget.value = product;
    confirmDeleteOpen.value = true;
}

const confirmDelete = async () => {
    if (!deleteTarget.value) return;
    deleting.value = true;

    try {
        await axios.delete(`/api/products/${deleteTarget.value.id}`);
        await loadProducts();
        confirmDeleteOpen.value = false;
        deleteTarget.value = null;
    } catch (err) {
        toast.error('Failed to delete product. It may be in use.');
        console.error('Error:', err);
    } finally {
        deleting.value = false;
    }
};

const resetForm = () => {
    form.value = {
        name: '',
        sku: '',
        description: '',
        unit_price: null,
        cost_price: null,
        unit: '',
        category: '',
        is_active: true,
    };
    relationships.value = [];
    newRelation.value = { to_product_id: null, relationship_type: 'suggest' };
    error.value = null;
};

onMounted(() => {
    loadProducts();
});
</script>
