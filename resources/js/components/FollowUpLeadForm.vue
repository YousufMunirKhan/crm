<template>
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden mb-6">
        <div class="px-4 sm:px-6 py-4 border-b border-slate-200 bg-slate-50">
            <h3 class="text-lg sm:text-xl font-semibold text-slate-900">Add Follow-up or Create Lead</h3>
            <p class="text-sm text-slate-500 mt-1">Schedule a follow-up, book an appointment, or create a new lead</p>
        </div>

        <form @submit.prevent="handleSubmit" class="p-4 sm:p-6 space-y-6">
            <!-- Type Selection - Card style -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-3">Select Type</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 lg:gap-5">
                    <label
                        class="relative flex flex-col items-center p-4 rounded-xl border-2 cursor-pointer transition-all"
                        :class="form.type === 'follow_up' ? 'border-blue-500 bg-blue-50 shadow-sm' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                    >
                        <input v-model="form.type" type="radio" value="follow_up" class="sr-only" />
                        <span class="text-2xl mb-2">🔄</span>
                        <span class="font-medium text-slate-900 text-center">Follow-up</span>
                        <span class="text-xs text-slate-500 mt-1 text-center">Remind to follow up</span>
                    </label>
                    <label
                        class="relative flex flex-col items-center p-4 rounded-xl border-2 cursor-pointer transition-all"
                        :class="form.type === 'appointment' ? 'border-blue-500 bg-blue-50 shadow-sm' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                    >
                        <input v-model="form.type" type="radio" value="appointment" class="sr-only" />
                        <span class="text-2xl mb-2">📅</span>
                        <span class="font-medium text-slate-900 text-center">Appointment</span>
                        <span class="text-xs text-slate-500 mt-1 text-center">Schedule a meeting</span>
                    </label>
                    <label
                        class="relative flex flex-col items-center p-4 rounded-xl border-2 cursor-pointer transition-all"
                        :class="form.type === 'lead' ? 'border-blue-500 bg-blue-50 shadow-sm' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                    >
                        <input v-model="form.type" type="radio" value="lead" class="sr-only" />
                        <span class="text-2xl mb-2">➕</span>
                        <span class="font-medium text-slate-900 text-center">Create Lead</span>
                        <span class="text-xs text-slate-500 mt-1 text-center">New sales opportunity</span>
                    </label>
                </div>
            </div>

            <!-- Appointment Fields -->
            <div v-if="form.type === 'appointment'" class="space-y-4">
                <div class="p-4 sm:p-5 bg-blue-50/80 rounded-xl border border-blue-200">
                    <h4 class="font-semibold text-blue-900 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 mb-4">
                        <span>📅 Schedule Appointment</span>
                        <span class="text-xs font-normal text-blue-700">(Email sent to customer, admin & assignee)</span>
                    </h4>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Assign to (who will attend) *</label>
                        <select
                            v-model="form.assigned_user_id"
                            required
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">Select team member...</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role?.name || '—' }})</option>
                        </select>
                        <p class="text-xs text-blue-700 mt-1">The assigned person will receive an email with the appointment time and notes.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Appointment Date *</label>
                            <input
                                v-model="form.appointment_date"
                                type="date"
                                required
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Appointment Time *</label>
                            <input
                                v-model="form.appointment_time"
                                type="time"
                                required
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                    </div>
                    
                    <p class="text-xs text-blue-700 mt-3 flex items-start gap-1">
                        <span>ℹ️</span>
                        <span>Confirmation emails will be sent to the customer and admin notification email (Settings).</span>
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes (Optional)</label>
                    <textarea
                        v-model="form.comment"
                        rows="3"
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 resize-none"
                        placeholder="Additional notes for the appointment..."
                    />
                </div>

                <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 sm:p-4">
                    <p class="text-sm text-slate-700">
                        <strong>Note:</strong> An appointment will be created for this customer. If no lead exists, one will be created automatically.
                    </p>
                </div>
            </div>

            <!-- Comment (shown for follow-up and lead types) -->
            <div v-if="form.type !== 'appointment'">
                <label class="block text-sm font-medium text-slate-700 mb-1">Comment/Notes *</label>
                <textarea
                    v-model="form.comment"
                    rows="4"
                    required
                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 resize-none text-base"
                    placeholder="Add your comment or notes..."
                />
            </div>

            <!-- Follow-up Fields -->
            <div v-if="form.type === 'follow_up'" class="space-y-4">
                <!-- Product Selection (Required for follow-ups too) - Multiple Selection -->
                <div>
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 mb-2">
                        <label class="text-sm font-medium text-slate-700">Product(s) *</label>
                        <router-link
                            to="/products"
                            target="_blank"
                            class="text-xs text-blue-600 hover:text-blue-800 underline"
                        >
                            + Add New Product
                        </router-link>
                    </div>
                    <div class="border border-slate-200 rounded-xl p-3 sm:p-4 bg-slate-50/50 max-h-52 overflow-y-auto">
                        <div v-if="!products || products.length === 0" class="text-sm text-slate-500 text-center py-6">
                            Loading products...
                        </div>
                        <div v-else class="space-y-1">
                            <label
                                v-for="product in products"
                                :key="product.id"
                                class="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors min-h-[44px] touch-manipulation"
                                :class="{ 'bg-blue-50 border border-blue-200': form.product_ids.includes(Number(product.id)), 'hover:bg-slate-50': !form.product_ids.includes(Number(product.id)) }"
                            >
                                <input
                                    type="checkbox"
                                    :value="Number(product.id)"
                                    v-model="form.product_ids"
                                    @change="loadSuggestedProducts"
                                    class="w-5 h-5 text-blue-600 border-slate-300 rounded focus:ring-blue-500 flex-shrink-0"
                                />
                                <span class="text-sm text-slate-700">{{ product.name }}</span>
                            </label>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">
                        Click to select multiple products for this lead.
                    </p>
                    <div v-if="form.product_ids.length > 0" class="mt-3 p-3 sm:p-4 bg-blue-50 border border-blue-200 rounded-xl">
                        <p class="text-sm font-medium text-blue-900 mb-1">
                            ✓ {{ form.product_ids.length }} product(s) selected:
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="productId in form.product_ids"
                                :key="productId"
                                class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-medium flex items-center gap-1"
                            >
                                {{ products.find(p => p.id == productId)?.name || 'Loading...' }}
                                <button
                                    type="button"
                                    @click.prevent="removeProductId(productId)"
                                    class="text-blue-600 hover:text-blue-800 ml-1"
                                >
                                    ×
                                </button>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Suggested Products -->
                <div v-if="suggestedProducts.length > 0" class="bg-purple-50 border border-purple-200 rounded-xl p-3 sm:p-4">
                    <p class="text-sm font-medium text-purple-900 mb-2">Suggested Related Products:</p>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="product in suggestedProducts"
                            :key="product.id"
                            class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm"
                        >
                            {{ product.name }}
                        </span>
                    </div>
                </div>

                <!-- Lead Selection (optional): choose a lead = follow up that lead; leave empty = first call / simple follow-up -->
                <div v-if="customerLeads.length > 0">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Select Lead (optional)</label>
                    <select
                        v-model="form.lead_id"
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 text-base"
                    >
                        <option value="">First call / no specific lead — create new follow-up</option>
                        <option v-for="lead in customerLeads" :key="lead.id" :value="lead.id">
                            Lead #{{ lead.id }} — {{ formatLeadStage(lead.stage) }} ({{ formatDate(lead.created_at) }})
                        </option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Leave as "First call" for a simple follow-up (e.g. first contact). Choose a lead to add this follow-up to that lead.</p>
                </div>
                <div v-else class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <p class="text-sm text-blue-800">
                        <strong>Note:</strong> No lead exists yet. This follow-up will create a new lead with stage "follow_up" (first call).
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Follow-up Date & Time *</label>
                    <input
                        v-model="form.follow_up_at"
                        type="datetime-local"
                        required
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 text-base"
                    />
                </div>
            </div>

            <!-- Lead Fields -->
            <div v-if="form.type === 'lead'" class="space-y-4">
                <!-- Product Selection (Required for leads) - Multiple Selection -->
                <div>
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 mb-2">
                        <label class="text-sm font-medium text-slate-700">Product(s) *</label>
                        <router-link
                            to="/products"
                            target="_blank"
                            class="text-xs text-blue-600 hover:text-blue-800 underline"
                        >
                            + Add New Product
                        </router-link>
                    </div>
                    <div class="border border-slate-200 rounded-xl p-3 sm:p-4 bg-slate-50/50 max-h-52 overflow-y-auto">
                        <div v-if="!products || products.length === 0" class="text-sm text-slate-500 text-center py-4">
                            Loading products...
                        </div>
                        <div v-else class="space-y-1">
                            <label
                                v-for="product in products"
                                :key="product.id"
                                class="flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors min-h-[44px] touch-manipulation"
                                :class="{ 'bg-blue-50 border border-blue-200': form.product_ids.includes(Number(product.id)), 'hover:bg-slate-50': !form.product_ids.includes(Number(product.id)) }"
                            >
                                <input
                                    type="checkbox"
                                    :value="Number(product.id)"
                                    v-model="form.product_ids"
                                    @change="loadSuggestedProducts"
                                    class="w-5 h-5 text-blue-600 border-slate-300 rounded focus:ring-blue-500 flex-shrink-0"
                                />
                                <span class="text-sm text-slate-700">{{ product.name }}</span>
                            </label>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500 mt-2">
                        Click to select multiple products.
                    </p>
                    <div v-if="form.product_ids.length > 0" class="mt-3 p-3 sm:p-4 bg-blue-50 border border-blue-200 rounded-xl">
                        <p class="text-sm font-medium text-blue-900 mb-1">
                            ✓ {{ form.product_ids.length }} product(s) selected:
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="productId in form.product_ids"
                                :key="productId"
                                class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs font-medium flex items-center gap-1"
                            >
                                {{ products.find(p => p.id == productId)?.name || 'Loading...' }}
                                <button
                                    type="button"
                                    @click.prevent="removeProductId(productId)"
                                    class="text-blue-600 hover:text-blue-800 ml-1"
                                >
                                    ×
                                </button>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Suggested Products -->
                <div v-if="suggestedProducts.length > 0" class="bg-purple-50 border border-purple-200 rounded-xl p-3 sm:p-4">
                    <p class="text-sm font-medium text-purple-900 mb-2">Suggested Related Products:</p>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="product in suggestedProducts"
                            :key="product.id"
                            class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm"
                        >
                            {{ product.name }}
                        </span>
                    </div>
                </div>

                <!-- Convertible Follow-ups Selection -->
                <div v-if="convertibleFollowUps.length > 0">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Convert from Follow-up (Optional)</label>
                    <select
                        v-model="form.converted_from_activity_id"
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 text-base"
                    >
                        <option value="">None - Create new lead</option>
                        <option v-for="followUp in convertibleFollowUps" :key="followUp.id" :value="followUp.id">
                            Follow-up: {{ followUp.description }} ({{ formatDate(followUp.remind_at) }})
                        </option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Select a follow-up to convert into this lead. This will track the conversion.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Stage *</label>
                        <select
                            v-model="form.stage"
                            required
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 text-base"
                        >
                            <option value="follow_up">Follow Up</option>
                            <option value="lead">Lead</option>
                            <option value="hot_lead">Hot Lead</option>
                            <option value="quotation">Quotation</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Expected Closing Date *</label>
                        <input
                            v-model="form.expected_closing_date"
                            type="date"
                            required
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 text-base"
                        />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Source</label>
                        <select
                            v-model="form.source"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 text-base"
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

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Pipeline Value (£)</label>
                        <input
                            v-model.number="form.pipeline_value"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 text-base"
                        />
                    </div>
                </div>
            </div>

            <div v-if="error" class="text-sm text-red-600 bg-red-50 p-4 rounded-xl border border-red-200">
                {{ error }}
            </div>

            <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-6 border-t border-slate-200">
                <button
                    type="button"
                    @click="$emit('cancel')"
                    class="w-full sm:w-auto px-4 py-2.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full sm:w-auto px-6 py-2.5 text-sm font-medium bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                >
                    {{ loading ? 'Saving...' : (form.type === 'follow_up' ? 'Save Follow-up' : (form.type === 'appointment' ? 'Schedule Appointment' : 'Create Lead')) }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { formatLeadStage } from '@/utils/displayFormat';

const props = defineProps({
    customerId: {
        type: [Number, String],
        required: true,
    },
    existingLead: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['saved', 'cancel']);

const form = ref({
    type: 'follow_up',
    comment: '',
    follow_up_at: null,
    expected_closing_date: null,
    stage: 'follow_up',
    source: '',
    pipeline_value: 0,
    product_id: null, // Single product (for backward compatibility)
    product_ids: [], // Multiple products selection
    lead_id: null, // For selecting which lead to add follow-up to
    converted_from_activity_id: null, // For tracking conversion
    // Appointment fields
    assigned_user_id: '',
    appointment_date: '',
    appointment_time: '10:00',
});

const loading = ref(false);
const error = ref(null);
const customerLeads = ref([]);
const convertibleFollowUps = ref([]);
const products = ref([]);
const suggestedProducts = ref([]);
const users = ref([]);

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const loadCustomerLeads = async () => {
    try {
        const response = await axios.get(`/api/customers/${props.customerId}/leads`);
        customerLeads.value = response.data;
    } catch (err) {
        console.error('Error loading customer leads:', err);
    }
};

const loadConvertibleFollowUps = async () => {
    try {
        const response = await axios.get(`/api/customers/${props.customerId}/convertible-followups`);
        convertibleFollowUps.value = response.data;
    } catch (err) {
        console.error('Error loading convertible follow-ups:', err);
    }
};

const loadProducts = async () => {
    try {
        const response = await axios.get('/api/products');
        products.value = response.data;
    } catch (err) {
        console.error('Error loading products:', err);
    }
};

const loadSuggestedProducts = async () => {
    // Load suggestions for the first selected product
    const firstProductId = form.value.product_ids && form.value.product_ids.length > 0 
        ? form.value.product_ids[0] 
        : form.value.product_id;
    
    if (!firstProductId) {
        suggestedProducts.value = [];
        return;
    }
    try {
        const response = await axios.get(`/api/products/${firstProductId}/suggested`);
        suggestedProducts.value = response.data;
    } catch (err) {
        console.error('Error loading suggested products:', err);
        suggestedProducts.value = [];
    }
};

const loadUsers = async () => {
    try {
        const res = await axios.get('/api/users');
        users.value = Array.isArray(res.data) ? res.data : (res.data?.data ?? []);
    } catch (e) {
        console.error('Failed to load users for appointment assignee', e);
    }
};

onMounted(async () => {
    // Set default follow-up time to 1 hour from now
    const now = new Date();
    now.setHours(now.getHours() + 1);
    form.value.follow_up_at = now.toISOString().slice(0, 16);
    
    // Set default appointment date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    form.value.appointment_date = tomorrow.toISOString().slice(0, 10);
    
    // Load customer leads, convertible follow-ups, products, and users
    await loadCustomerLeads();
    await loadConvertibleFollowUps();
    await loadProducts();
    await loadUsers();
    
    // Reload products when window regains focus (in case product was added in another tab)
    window.addEventListener('focus', loadProducts);
});

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
        // Handle appointment type separately
        if (form.value.type === 'appointment') {
            // Validate appointment fields
            if (!form.value.assigned_user_id) {
                error.value = 'Please select who will attend this appointment.';
                loading.value = false;
                return;
            }
            if (!form.value.appointment_date || !form.value.appointment_time) {
                error.value = 'Please select appointment date and time.';
                loading.value = false;
                return;
            }

            // Find or create a lead for this customer
            let leadId = null;
            
            // Try to find an existing active lead
            if (customerLeads.value.length > 0) {
                // Use the most recent lead
                leadId = customerLeads.value[0].id;
            }
            
            // If no lead exists, create one automatically
            if (!leadId) {
                const leadResponse = await axios.post('/api/leads', {
                    customer_id: props.customerId,
                    stage: 'follow_up',
                    source: 'appointment',
                    product_ids: products.value.length > 0 ? [products.value[0].id] : [],
                    comment: `Appointment scheduled for ${form.value.appointment_date} at ${form.value.appointment_time}`,
                });
                leadId = leadResponse.data.id;
            }

            // Create appointment activity
            const activityPayload = {
                type: 'appointment',
                description: form.value.comment || `Appointment scheduled for ${form.value.appointment_date} at ${form.value.appointment_time}`,
                meta: {
                    appointment_date: form.value.appointment_date,
                    appointment_time: form.value.appointment_time,
                },
            };
            if (form.value.assigned_user_id) {
                activityPayload.assigned_user_id = form.value.assigned_user_id;
            }
            await axios.post(`/api/leads/${leadId}/activity`, activityPayload);

            // Reset form
            resetForm();
            emit('saved');
            return;
        }

        // Handle follow-up and lead types
        const selectedProductIds = form.value.product_ids && form.value.product_ids.length > 0 
            ? form.value.product_ids 
            : (form.value.product_id ? [form.value.product_id] : []);

        if (selectedProductIds.length === 0) {
            error.value = 'Please select at least one product.';
            loading.value = false;
            return;
        }

        const payload = {
            customer_id: props.customerId,
            type: form.value.type,
            comment: form.value.comment.trim(),
            product_ids: selectedProductIds,
        };

        if (form.value.type === 'follow_up') {
            payload.follow_up_at = form.value.follow_up_at;
            if (form.value.lead_id) {
                payload.lead_id = form.value.lead_id;
            }
        } else {
            payload.expected_closing_date = form.value.expected_closing_date;
            payload.stage = form.value.stage;
            if (form.value.source) {
                payload.source = form.value.source;
            }
            if (form.value.pipeline_value) {
                payload.pipeline_value = form.value.pipeline_value;
            }
            if (form.value.converted_from_activity_id) {
                payload.converted_from_activity_id = form.value.converted_from_activity_id;
            }
        }

        await axios.post('/api/leads/followup-or-lead', payload);
        
        // Reload data after successful submission
        await loadCustomerLeads();
        await loadConvertibleFollowUps();
        
        resetForm();
        emit('saved');
    } catch (err) {
        if (err.response?.data?.errors) {
            const errors = err.response.data.errors;
            error.value = Object.values(errors).flat().join(', ');
        } else if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else {
            error.value = 'Failed to save. Please try again.';
        }
        console.error('Error:', err);
    } finally {
        loading.value = false;
    }
};

const resetForm = () => {
    form.value = {
        type: 'follow_up',
        comment: '',
        follow_up_at: new Date(Date.now() + 60 * 60 * 1000).toISOString().slice(0, 16),
        expected_closing_date: null,
        stage: 'follow_up',
        source: '',
        pipeline_value: 0,
        product_id: null,
        product_ids: [],
        lead_id: null,
        converted_from_activity_id: null,
        assigned_user_id: '',
        appointment_date: '',
        appointment_time: '10:00',
    };
    suggestedProducts.value = [];
};
</script>

