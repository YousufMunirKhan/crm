<template>
    <BaseModal
        :model-value="true"
        :title="lead ? 'Edit Lead' : 'Create New Lead'"
        size="md"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <form id="lead-form" class="space-y-4" @submit.prevent="handleSubmit">
            <div>
                <label class="form-label" for="leadform-customer">Customer <span class="form-required" aria-hidden="true">*</span></label>
                <select id="leadform-customer"
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

            <div role="group" aria-labelledby="leadform-products-label">
                <div class="mb-1 flex items-center justify-between gap-3">
                    <span id="leadform-products-label" class="form-label mb-0">Product(s) <span class="form-required" aria-hidden="true">*</span></span>
                    <router-link
                        to="/products"
                        target="_blank"
                        class="link text-xs"
                    >
                        + Add New Product
                    </router-link>
                </div>
                <div class="max-h-48 overflow-y-auto rounded-control border border-slate-300 bg-white p-3">
                    <div v-if="!products || products.length === 0" class="py-4 text-center text-sm text-slate-500">
                        Loading products...
                    </div>
                    <div v-else class="space-y-2">
                        <label
                            v-for="product in products"
                            :key="product.id"
                            class="flex w-full cursor-pointer items-center gap-3 rounded-control p-2 transition-colors hover:bg-slate-50"
                            :class="{ 'bg-primary-50 border border-primary-200': form.product_ids.includes(Number(product.id)) }"
                            :for="`leadform-product-${product.id}`"
                        >
                            <input
                                :id="`leadform-product-${product.id}`"
                                type="checkbox"
                                :value="Number(product.id)"
                                v-model="form.product_ids"
                                class="form-checkbox"
                            />
                            <span class="text-sm text-slate-700">{{ product.name }}</span>
                        </label>
                    </div>
                </div>
                <p class="form-hint">
                    Click to select multiple products for this lead.
                </p>
                <div v-if="form.product_ids.length > 0" class="mt-2 rounded-control border border-primary-200 bg-primary-50 p-2">
                    <p class="mb-1 flex items-center gap-1.5 text-sm font-medium text-primary-800">
                        <CheckIcon class="icon-sm" aria-hidden="true" />
                        {{ form.product_ids.length }} product(s) selected:
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="productId in form.product_ids"
                            :key="productId"
                            class="chip"
                        >
                            {{ products.find(p => p.id == productId)?.name || 'Loading...' }}
                            <button
                                type="button"
                                @click.prevent="removeProductId(productId)"
                                class="chip-remove"
                                :aria-label="`Remove ${products.find(p => p.id == productId)?.name || 'product'}`"
                            >
                                <XMarkIcon class="icon-sm" aria-hidden="true" />
                            </button>
                        </span>
                    </div>
                </div>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="form-label" for="leadform-stage">Stage <span class="form-required" aria-hidden="true">*</span></label>
                    <select id="leadform-stage"
                        v-model="form.stage"
                        required
                        class="form-select"
                    >
                        <option value="follow_up">Follow Up</option>
                        <option value="lead">Lead</option>
                        <option value="hot_lead">Hot Lead</option>
                        <option value="quotation">Quotation</option>
                        <option value="won">Won</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="leadform-source">Source</label>
                    <select id="leadform-source"
                        v-model="form.source"
                        class="form-select"
                    >
                        <option value="">Select Source</option>
                        <option value="call_center">Call Center</option>
                        <option value="ground_field">Ground Field</option>
                        <option value="website">Website</option>
                        <option value="meta">Meta</option>
                        <option value="tiktok">TikTok</option>
                        <option value="google_ads">Google Ads</option>
                        <option value="organic_lead">Organic Lead</option>
                    </select>
                </div>

                <!-- Assign To - Sales agents can reassign when editing, auto-assign when creating -->
                <div v-if="canReassign">
                    <label class="form-label" for="leadform-assigned-to">
                        {{ isSalesAgent && lead ? 'Reassign To' : 'Assign To' }}
                    </label>
                    <select id="leadform-assigned-to"
                        v-model="form.assigned_to"
                        class="form-select"
                        :aria-describedby="isSalesAgent && lead ? 'leadform-assigned-to-hint' : undefined"
                    >
                        <option v-if="!isSalesAgent" value="">Unassigned</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }} {{ user.id === auth.user?.id ? '(You)' : '' }}
                        </option>
                    </select>
                    <p v-if="isSalesAgent && lead" id="leadform-assigned-to-hint" class="form-hint">
                        You can reassign this lead to another team member
                    </p>
                </div>
                <div v-else>
                    <span class="form-label">Assigned To</span>
                    <p class="rounded-control border border-slate-200 bg-slate-100 px-3 py-2 text-slate-700">
                        {{ auth.user?.name }} (You)
                    </p>
                    <p class="form-hint">
                        Lead will be assigned to you automatically
                    </p>
                </div>

                <div>
                    <label class="form-label" for="leadform-pipeline-value">Pipeline Value (£)</label>
                    <input id="leadform-pipeline-value"
                        v-model.number="form.pipeline_value"
                        type="number"
                        step="0.01"
                        min="0"
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label" for="leadform-next-follow-up">Next Follow-up</label>
                    <input id="leadform-next-follow-up"
                        v-model="form.next_follow_up_at"
                        type="datetime-local"
                        class="form-input"
                    />
                </div>
            </div>

            <div v-if="form.stage === 'lost'">
                <label class="form-label" for="leadform-lost-reason">Lost Reason <span class="form-required" aria-hidden="true">*</span></label>
                <textarea id="leadform-lost-reason"
                    v-model="form.lost_reason"
                    rows="2"
                    required
                    class="form-textarea"
                    placeholder="Please provide a reason why this lead was lost..."
                />
            </div>

            <!-- Items Section for Won Stage -->
            <div v-if="form.stage === 'won'" class="rounded-card border-2 border-success-200 bg-success-50/30 p-4">
                <div class="mb-3 flex items-start justify-between gap-3">
                    <div>
                        <h3 class="mb-1 text-sm font-semibold text-success-800">Items Required to Close Deal</h3>
                        <p class="text-xs text-success-700">You must add at least one item before closing this deal.</p>
                    </div>
                    <BaseButton variant="success" size="sm" @click="showItemsForm = !showItemsForm">
                        {{ showItemsForm ? 'Hide Items' : (leadItems.length > 0 ? `Edit Items (${leadItems.length})` : 'Add Items') }}
                    </BaseButton>
                </div>

                <!-- Show existing items if any -->
                <div v-if="leadItems.length > 0 && !showItemsForm" class="mt-3 space-y-2">
                    <div
                        v-for="item in leadItems"
                        :key="item.id"
                        class="rounded-control border border-success-200 bg-white p-2"
                    >
                        <div class="text-sm font-medium text-slate-900">{{ item.product?.name }}</div>
                        <div class="text-xs text-slate-600">
                            Qty: {{ item.quantity }} × £{{ parseFloat(item.unit_price || 0).toFixed(2) }} = £{{ parseFloat(item.total_price || 0).toFixed(2) }}
                        </div>
                    </div>
                </div>

                <!-- Items Form -->
                <div v-if="showItemsForm" class="mt-4 space-y-4">
                    <div v-for="(item, index) in items" :key="index" class="space-y-3 rounded-card border border-slate-200 bg-white p-4">
                        <div class="mb-2 flex items-center justify-between gap-3">
                            <h4 class="font-medium text-slate-900">Item {{ index + 1 }}</h4>
                            <BaseButton
                                v-if="items.length > 1"
                                variant="ghost"
                                size="sm"
                                @click="removeItem(index)"
                            >
                                <template #icon>
                                    <TrashIcon class="icon-sm text-danger-700" aria-hidden="true" />
                                </template>
                                Remove
                            </BaseButton>
                        </div>

                        <div class="form-grid-2">
                            <div>
                                <div class="mb-1 flex items-center justify-between gap-3">
                                    <label class="form-label mb-0" :for="`leadform-item-${index}-product`">Product <span class="form-required" aria-hidden="true">*</span></label>
                                    <router-link
                                        to="/products"
                                        target="_blank"
                                        class="link text-xs"
                                    >
                                        + Add New
                                    </router-link>
                                </div>
                                <select
                                    :id="`leadform-item-${index}-product`"
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
                                <label class="form-label" :for="`leadform-item-${index}-quantity`">Quantity <span class="form-required" aria-hidden="true">*</span></label>
                                <input
                                    :id="`leadform-item-${index}-quantity`"
                                    v-model.number="item.quantity"
                                    type="number"
                                    min="1"
                                    required
                                    @input="item.quantity = parseInt(item.quantity) || 1"
                                    class="form-input"
                                />
                            </div>

                            <div>
                                <label class="form-label" :for="`leadform-unit-price-${index}`">Unit Price (£) <span class="form-required" aria-hidden="true">*</span></label>
                                <input :id="`leadform-unit-price-${index}`"
                                    v-model.number="item.unit_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    @input="item.unit_price = parseFloat(item.unit_price) || 0"
                                    class="form-input"
                                />
                            </div>

                            <div>
                                <span class="form-label">
                                    Total: £{{ calculateItemTotal(item).toFixed(2) }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="form-label" :for="`leadform-notes-${index}`">Notes</label>
                            <textarea :id="`leadform-notes-${index}`"
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
                </div>

                <p v-if="leadItems.length === 0 && !showItemsForm" class="callout callout-warning mt-3 flex items-start gap-2">
                    <ExclamationTriangleIcon class="icon shrink-0 text-warning-800" aria-hidden="true" />
                    <span><strong>No items added yet.</strong> Click "Add Items" above to add products before closing this deal.</span>
                </p>
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
                form="lead-form"
                block-mobile
                :loading="loading"
            >
                {{ loading ? 'Saving...' : (lead ? 'Update' : 'Create') }}
            </BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { CheckIcon, ExclamationTriangleIcon, PlusIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { BaseButton, BaseModal } from '@/components/base';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

const props = defineProps({
    lead: {
        type: Object,
        default: null,
    },
    customerId: {
        type: [Number, String],
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

// Check if current user is a sales agent
const isSalesAgent = computed(() => {
    const role = auth.user?.role?.name;
    return role === 'Sales' || role === 'CallAgent';
});

// Sales agents can reassign when editing their own leads, but not when creating new leads
const canReassign = computed(() => {
    // Admin/Manager can always reassign
    if (!isSalesAgent.value) return true;
    // Sales agents can reassign when editing an existing lead
    return !!props.lead;
});

const form = ref({
    customer_id: '',
    product_ids: [], // Changed to array for multi-select
    stage: 'follow_up',
    source: '',
    assigned_to: null,
    pipeline_value: 0,
    lost_reason: '',
    next_follow_up_at: null,
});

const customers = ref([]);
const users = ref([]);
const products = ref([]);
const loading = ref(false);
const error = ref(null);
const showItemsForm = ref(false);
const items = ref([{ product_id: null, quantity: 1, unit_price: 0, notes: '' }]);
const leadItems = ref([]);

const loadProducts = async () => {
    try {
        const response = await axios.get('/api/products');
        products.value = response.data;
    } catch (err) {
        console.error('Error loading products:', err);
    }
};

onMounted(async () => {
    try {
        const [customersRes, usersRes] = await Promise.all([
            axios.get('/api/customers', { params: { per_page: 100 } }),
            axios.get('/api/users'),
        ]);
        customers.value = customersRes.data.data || customersRes.data || [];
        users.value = usersRes.data || [];
        await loadProducts();
    } catch (err) {
        console.error('Failed to load data:', err);
        error.value = 'Failed to load customers or users. Please refresh the page.';
    }

    if (props.lead) {
        // If lead doesn't have full data (e.g., from pipeline), fetch it
        if (!props.lead.product && props.lead.id) {
            try {
                const response = await axios.get(`/api/leads/${props.lead.id}`);
                const fullLead = response.data;
                
                // Get product_ids from items if available, otherwise from product_id
                let productIds = [];
                if (fullLead.items && fullLead.items.length > 0) {
                    productIds = fullLead.items.map(item => Number(item.product_id));
                } else if (fullLead.product_id) {
                    productIds = [Number(fullLead.product_id)];
                }
                
                form.value = {
                    customer_id: fullLead.customer_id,
                    product_ids: productIds,
                    stage: fullLead.stage,
                    source: fullLead.source || '',
                    assigned_to: fullLead.assigned_to,
                    pipeline_value: fullLead.pipeline_value || 0,
                    lost_reason: fullLead.lost_reason || '',
                    next_follow_up_at: fullLead.next_follow_up_at ? new Date(fullLead.next_follow_up_at).toISOString().slice(0, 16) : null,
                };
                
                // Load existing items if lead has items
                if (fullLead.items && fullLead.items.length > 0) {
                    leadItems.value = fullLead.items;
                    items.value = fullLead.items.map(item => ({
                        product_id: item.product_id,
                        quantity: item.quantity,
                        unit_price: parseFloat(item.unit_price) || 0,
                        notes: item.notes || '',
                    }));
                    showItemsForm.value = false;
                } else if (fullLead.stage === 'won') {
                    showItemsForm.value = true;
                    items.value = [{ product_id: null, quantity: 1, unit_price: 0, notes: '' }];
                }
                return; // Exit early since we fetched full data
            } catch (err) {
                console.error('Failed to fetch full lead data:', err);
                // Fall through to use props.lead data
            }
        }
        
        // Get product_ids from items if available, otherwise from product_id
        let productIds = [];
        if (props.lead.items && props.lead.items.length > 0) {
            productIds = props.lead.items.map(item => Number(item.product_id));
        } else if (props.lead.product_id) {
            productIds = [Number(props.lead.product_id)];
        }
        
        // Use props.lead data directly if it has all needed fields
        form.value = {
            customer_id: props.lead.customer_id,
            product_ids: productIds,
            stage: props.lead.stage,
            source: props.lead.source || '',
            assigned_to: props.lead.assigned_to,
            pipeline_value: props.lead.pipeline_value || 0,
            lost_reason: props.lead.lost_reason || '',
            next_follow_up_at: props.lead.next_follow_up_at ? new Date(props.lead.next_follow_up_at).toISOString().slice(0, 16) : null,
        };
        
        // Load existing items if lead has items
        if (props.lead.items && props.lead.items.length > 0) {
            leadItems.value = props.lead.items;
            items.value = props.lead.items.map(item => ({
                product_id: item.product_id,
                quantity: item.quantity,
                unit_price: parseFloat(item.unit_price) || 0,
                notes: item.notes || '',
            }));
            showItemsForm.value = false;
        } else if (props.lead.stage === 'won') {
            showItemsForm.value = true;
            items.value = [{ product_id: null, quantity: 1, unit_price: 0, notes: '' }];
        }
    } else if (props.customerId) {
        form.value.customer_id = props.customerId;
    }

    // Auto-assign to current user if they're a sales agent (for new leads only)
    if (!props.lead && isSalesAgent.value && auth.user?.id) {
        form.value.assigned_to = auth.user.id;
    }
    
    // Show items form if stage is "won"
    if (form.value.stage === 'won') {
        showItemsForm.value = true;
    }
});

// Watch for stage changes to show items form when "won" is selected
watch(() => form.value.stage, (newStage) => {
    if (newStage === 'won') {
        showItemsForm.value = true;
        // If no items exist yet, ensure at least one item form is shown
        if (items.value.length === 0) {
            items.value = [{ product_id: null, quantity: 1, unit_price: 0, notes: '' }];
        }
    } else {
        showItemsForm.value = false;
    }
});

const calculateItemTotal = (item) => {
    const quantity = parseFloat(item.quantity) || 0;
    const unitPrice = parseFloat(item.unit_price) || 0;
    return quantity * unitPrice;
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
    if (items.value.length === 0) {
        items.value = [{ product_id: null, quantity: 1, unit_price: 0, notes: '' }];
    }
};

const removeProductId = (productId) => {
    const index = form.value.product_ids.indexOf(productId);
    if (index > -1) {
        form.value.product_ids.splice(index, 1);
    }
};

const handleSubmit = async () => {
    loading.value = true;
    error.value = null;

    try {
        // Validate product selection
        if (!form.value.product_ids || form.value.product_ids.length === 0) {
            error.value = 'Please select at least one product.';
            loading.value = false;
            return;
        }

        // If stage is "won", validate and save items first
        if (form.value.stage === 'won') {
            // Validate items
            const validItems = items.value.filter(item => item.product_id && item.quantity > 0 && item.unit_price >= 0);
            if (validItems.length === 0) {
                error.value = 'Please add at least one item with product, quantity, and unit price before closing the deal.';
                loading.value = false;
                return;
            }

            // If editing a lead, save items first
            if (props.lead) {
                try {
                    await axios.post(`/api/leads/${props.lead.id}/items`, {
                        items: validItems,
                    });
                } catch (itemErr) {
                    if (itemErr.response?.status === 422) {
                        // Items might already exist, try to update by deleting and re-adding
                        // For now, just show the error
                        error.value = itemErr.response?.data?.message || 'Failed to save items. Please try again.';
                        loading.value = false;
                        return;
                    }
                    throw itemErr;
                }
            }
        }

        if (props.lead) {
            // For editing, only update the first product (or we could allow changing product_id)
            const payload = { ...form.value };
            payload.product_id = form.value.product_ids[0]; // Use first selected product for edit
            delete payload.product_ids; // Remove array, use single product_id for update
            
            // For sales agents editing: allow reassignment; for creating: auto-assign
            if (isSalesAgent.value && auth.user?.id && !props.lead) {
                // Creating new lead - auto-assign to self
                payload.assigned_to = auth.user.id;
            } else if (!payload.assigned_to) {
                payload.assigned_to = null;
            }
            if (!payload.source) {
                delete payload.source;
            }
            if (!payload.lost_reason && payload.stage !== 'lost') {
                delete payload.lost_reason;
            }
            if (!payload.next_follow_up_at) {
                delete payload.next_follow_up_at;
            }

            await axios.put(`/api/leads/${props.lead.id}`, payload);
        } else {
            // For creating new leads - create ONE lead with multiple products
            const payload = { ...form.value };
            
            // For sales agents, always assign to themselves
            if (isSalesAgent.value && auth.user?.id) {
                payload.assigned_to = auth.user.id;
            } else if (!payload.assigned_to) {
                payload.assigned_to = null;
            }
            if (!payload.source) {
                delete payload.source;
            }
            if (!payload.lost_reason) {
                delete payload.lost_reason;
            }
            if (!payload.next_follow_up_at) {
                delete payload.next_follow_up_at;
            }

            // Create a single lead with all products
            const response = await axios.post('/api/leads', payload);
            const lead = response.data;
            
            // If stage is "won", add items with pricing
            if (form.value.stage === 'won') {
                const validItems = items.value.filter(item => item.product_id && item.quantity > 0 && item.unit_price >= 0);
                if (validItems.length > 0) {
                    await axios.post(`/api/leads/${lead.id}/items`, {
                        items: validItems,
                    });
                }
            }
        }
        
        // Reset form if creating new
        if (!props.lead) {
            form.value = {
                customer_id: props.customerId || '',
                product_ids: [],
                stage: 'follow_up',
                source: '',
                assigned_to: null,
                pipeline_value: 0,
                lost_reason: '',
                next_follow_up_at: null,
            };
            items.value = [{ product_id: null, quantity: 1, unit_price: 0, notes: '' }];
            showItemsForm.value = false;
        }
        
        emit('saved');
        emit('close');
    } catch (err) {
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            error.value = Object.values(errors).flat().join(', ');
        } else if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else {
            error.value = 'Failed to save lead. Please check your connection and try again.';
        }
        console.error('Lead save error:', err);
    } finally {
        loading.value = false;
    }
};
</script>
