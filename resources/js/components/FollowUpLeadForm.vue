<template>
    <div class="form-card mb-6">
        <div class="form-section-head">
            <h3 class="form-section-title">Add Follow-up or Create Lead</h3>
            <p class="form-section-desc">Schedule a follow-up, book an appointment, or create a new lead</p>
        </div>

        <form @submit.prevent="handleSubmit" class="p-4 sm:p-6 space-y-6">
            <!-- Type Selection - Card style -->
            <fieldset class="form-fieldset">
                <legend class="form-legend">Select Type</legend>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 lg:gap-5">
                    <label
                        class="relative flex flex-col items-center p-4 rounded-card border-2 cursor-pointer transition-all has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500/40"
                        :class="form.type === 'follow_up' ? 'border-primary-500 bg-primary-50 shadow-sm' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                    >
                        <input v-model="form.type" type="radio" value="follow_up" class="sr-only" />
                        <ArrowPathIcon class="w-6 h-6 text-slate-600 mb-2" aria-hidden="true" />
                        <span class="font-medium text-slate-900 text-center">Follow-up</span>
                        <span class="text-xs text-slate-500 mt-1 text-center">Remind to follow up</span>
                    </label>
                    <label
                        class="relative flex flex-col items-center p-4 rounded-card border-2 cursor-pointer transition-all has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500/40"
                        :class="form.type === 'appointment' ? 'border-primary-500 bg-primary-50 shadow-sm' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                    >
                        <input v-model="form.type" type="radio" value="appointment" class="sr-only" />
                        <CalendarDaysIcon class="w-6 h-6 text-slate-600 mb-2" aria-hidden="true" />
                        <span class="font-medium text-slate-900 text-center">Appointment</span>
                        <span class="text-xs text-slate-500 mt-1 text-center">Schedule a meeting</span>
                    </label>
                    <label
                        class="relative flex flex-col items-center p-4 rounded-card border-2 cursor-pointer transition-all has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500/40"
                        :class="form.type === 'lead' ? 'border-primary-500 bg-primary-50 shadow-sm' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                    >
                        <input v-model="form.type" type="radio" value="lead" class="sr-only" />
                        <PlusCircleIcon class="w-6 h-6 text-slate-600 mb-2" aria-hidden="true" />
                        <span class="font-medium text-slate-900 text-center">Create Lead</span>
                        <span class="text-xs text-slate-500 mt-1 text-center">New sales opportunity</span>
                    </label>
                </div>
            </fieldset>

            <!-- Appointment Fields -->
            <div v-if="form.type === 'appointment'" class="space-y-4">
                <div class="p-4 sm:p-5 bg-primary-50/80 rounded-card border border-primary-200">
                    <h4 class="font-semibold text-primary-900 flex flex-col sm:flex-row sm:items-center gap-1 sm:gap-2 mb-4">
                        <span class="flex items-center gap-2">
                            <CalendarDaysIcon class="icon" aria-hidden="true" />
                            Schedule Appointment
                        </span>
                        <span class="text-xs font-normal text-primary-800">(Email sent to customer, admin &amp; assignee)</span>
                    </h4>
                    <div class="mb-4">
                        <label class="form-label" for="followupleadform-assign-to-who-will-attend">Assign to (who will attend) <span class="form-required">*</span></label>
                        <select id="followupleadform-assign-to-who-will-attend"
                            v-model="form.assigned_user_id"
                            required
                            class="form-select"
                        >
                            <option value="">Select team member...</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role?.name || '—' }})</option>
                        </select>
                        <p class="form-hint">The assigned person will receive an email with the appointment time and notes.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="followupleadform-appointment-date">Appointment Date <span class="form-required">*</span></label>
                            <input id="followupleadform-appointment-date"
                                v-model="form.appointment_date"
                                type="date"
                                required
                                class="form-input"
                            />
                        </div>
                        <div>
                            <label class="form-label" for="followupleadform-appointment-time">Appointment Time <span class="form-required">*</span></label>
                            <input id="followupleadform-appointment-time"
                                v-model="form.appointment_time"
                                type="time"
                                required
                                class="form-input"
                            />
                        </div>
                    </div>

                    <div v-if="customerLeads.length > 0" class="mt-4">
                        <label class="form-label" for="followupleadform-sales-opportunity-for-this-appointment">Sales opportunity for this appointment</label>
                        <select id="followupleadform-sales-opportunity-for-this-appointment"
                            v-model="form.lead_id"
                            class="form-select"
                        >
                            <option value="">Create a new opportunity for this appointment</option>
                            <option v-for="lead in customerLeads" :key="lead.id" :value="lead.id">
                                Lead #{{ lead.id }} - {{ formatLeadStage(lead.stage) }} - {{ leadProductNames(lead) || 'No products selected' }}
                            </option>
                        </select>
                        <p class="form-hint">Select the exact lead if this visit is for an existing opportunity.</p>
                    </div>

                    <div v-if="form.lead_id" class="mt-4 rounded-card bg-white border border-primary-100 p-3">
                        <div class="text-xs font-semibold text-primary-800 mb-1">Products on selected lead</div>
                        <div v-if="selectedAppointmentLeadProducts.length" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="product in selectedAppointmentLeadProducts"
                                :key="product"
                                class="chip"
                            >
                                {{ product }}
                            </span>
                        </div>
                        <div v-else class="text-xs text-warning-800">
                            This lead has no products. Choose "Create a new opportunity" and select products, or add products on the lead page first.
                        </div>
                    </div>

                    <div v-if="!form.lead_id" class="mt-4">
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 mb-2">
                            <span class="form-label mb-0" id="followupleadform-appointment-products-label">Product(s) to sell <span class="form-required">*</span></span>
                            <router-link
                                to="/products"
                                target="_blank"
                                class="link text-xs inline-flex items-center gap-1"
                            >
                                <PlusIcon class="icon-sm" aria-hidden="true" />
                                Add New Product
                            </router-link>
                        </div>
                        <div
                            class="border border-primary-100 rounded-card p-3 sm:p-4 bg-white max-h-52 overflow-y-auto"
                            role="group"
                            aria-labelledby="followupleadform-appointment-products-label"
                            :aria-busy="!products || products.length === 0 ? 'true' : 'false'"
                        >
                            <div v-if="!products || products.length === 0" class="flex items-center justify-center gap-2 text-sm text-slate-500 py-4">
                                <span class="spinner" role="status" aria-label="Loading" />
                                Loading products...
                            </div>
                            <div v-else class="space-y-1">
                                <label
                                    v-for="product in products"
                                    :key="product.id"
                                    class="flex items-center gap-3 p-3 rounded-control cursor-pointer transition-colors min-h-[44px] touch-manipulation"
                                    :class="{ 'bg-primary-50 border border-primary-200': form.product_ids.includes(Number(product.id)), 'hover:bg-slate-50': !form.product_ids.includes(Number(product.id)) }"
                                >
                                    <input
                                        type="checkbox"
                                        :value="Number(product.id)"
                                        v-model="form.product_ids"
                                        @change="loadSuggestedProducts"
                                        class="form-checkbox w-5 h-5 flex-shrink-0"
                                    />
                                    <span class="text-sm text-slate-700">{{ product.name }}</span>
                                </label>
                            </div>
                        </div>
                        <p class="form-hint">
                            These products will appear in the agent's appointment brief and email.
                        </p>
                    </div>

                    <p class="callout callout-info mt-3 text-xs flex items-start gap-2">
                        <InformationCircleIcon class="icon-sm mt-0.5" aria-hidden="true" />
                        <span>Confirmation emails will be sent to the customer and admin notification email (Settings).</span>
                    </p>
                </div>

                <div>
                    <label class="form-label" for="followupleadform-notes-optional">Notes (Optional)</label>
                    <textarea id="followupleadform-notes-optional"
                        v-model="form.comment"
                        rows="3"
                        class="form-textarea resize-none"
                        placeholder="Additional notes for the appointment..."
                    />
                </div>

                <div class="callout callout-info">
                    <strong>Note:</strong> An appointment will be created for this customer. If no lead exists, one will be created automatically.
                </div>
            </div>

            <!-- Comment (shown for follow-up and lead types) -->
            <div v-if="form.type !== 'appointment'">
                <label class="form-label" for="followupleadform-comment-notes">Comment/Notes <span class="form-required">*</span></label>
                <textarea id="followupleadform-comment-notes"
                    v-model="form.comment"
                    rows="4"
                    required
                    class="form-textarea resize-none"
                    placeholder="Add your comment or notes..."
                />
            </div>

            <!-- Follow-up Fields -->
            <div v-if="form.type === 'follow_up'" class="space-y-4">
                <!-- Product Selection (Required for follow-ups too) - Multiple Selection -->
                <div>
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 mb-2">
                        <span class="form-label mb-0" id="followupleadform-followup-products-label">Product(s) <span class="form-required">*</span></span>
                        <router-link
                            to="/products"
                            target="_blank"
                            class="link text-xs inline-flex items-center gap-1"
                        >
                            <PlusIcon class="icon-sm" aria-hidden="true" />
                            Add New Product
                        </router-link>
                    </div>
                    <div
                        class="border border-slate-200 rounded-card p-3 sm:p-4 bg-slate-50/50 max-h-52 overflow-y-auto"
                        role="group"
                        aria-labelledby="followupleadform-followup-products-label"
                        :aria-busy="!products || products.length === 0 ? 'true' : 'false'"
                    >
                        <div v-if="!products || products.length === 0" class="flex items-center justify-center gap-2 text-sm text-slate-500 py-6">
                            <span class="spinner" role="status" aria-label="Loading" />
                            Loading products...
                        </div>
                        <div v-else class="space-y-1">
                            <label
                                v-for="product in products"
                                :key="product.id"
                                class="flex items-center gap-3 p-3 rounded-control cursor-pointer transition-colors min-h-[44px] touch-manipulation"
                                :class="{ 'bg-primary-50 border border-primary-200': form.product_ids.includes(Number(product.id)), 'hover:bg-slate-50': !form.product_ids.includes(Number(product.id)) }"
                            >
                                <input
                                    type="checkbox"
                                    :value="Number(product.id)"
                                    v-model="form.product_ids"
                                    @change="loadSuggestedProducts"
                                    class="form-checkbox w-5 h-5 flex-shrink-0"
                                />
                                <span class="text-sm text-slate-700">{{ product.name }}</span>
                            </label>
                        </div>
                    </div>
                    <p class="form-hint">
                        Click to select multiple products for this lead.
                    </p>
                    <div v-if="form.product_ids.length > 0" class="mt-3 p-3 sm:p-4 bg-primary-50 border border-primary-200 rounded-card">
                        <p class="text-sm font-medium text-primary-900 mb-1 flex items-center gap-1.5">
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

                <!-- Suggested Products -->
                <div v-if="suggestedProducts.length > 0" class="bg-primary-50 border border-primary-200 rounded-card p-3 sm:p-4">
                    <p class="text-sm font-medium text-primary-900 mb-2">Suggested Related Products:</p>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="product in suggestedProducts"
                            :key="product.id"
                            class="chip"
                        >
                            {{ product.name }}
                        </span>
                    </div>
                </div>

                <!-- Lead Selection (optional): choose a lead = follow up that lead; leave empty = first call / simple follow-up -->
                <div v-if="customerLeads.length > 0">
                    <label class="form-label" for="followupleadform-select-lead">Select Lead (optional)</label>
                    <select id="followupleadform-select-lead"
                        v-model="form.lead_id"
                        class="form-select"
                    >
                        <option value="">First call / no specific lead — create new follow-up</option>
                        <option v-for="lead in customerLeads" :key="lead.id" :value="lead.id">
                            Lead #{{ lead.id }} — {{ formatLeadStage(lead.stage) }} ({{ formatDate(lead.created_at) }})
                        </option>
                    </select>
                    <p class="form-hint">Leave as "First call" for a simple follow-up (e.g. first contact). Choose a lead to add this follow-up to that lead.</p>
                </div>
                <div v-else class="callout callout-info">
                    <strong>Note:</strong> No lead exists yet. This follow-up will create a new lead with stage "follow_up" (first call).
                </div>
                <div>
                    <label class="form-label" for="followupleadform-follow-up-at">Follow-up Date &amp; Time <span class="form-required">*</span></label>
                    <input id="followupleadform-follow-up-at"
                        v-model="form.follow_up_at"
                        type="datetime-local"
                        required
                        class="form-input"
                    />
                </div>
            </div>

            <!-- Lead Fields -->
            <div v-if="form.type === 'lead'" class="space-y-4">
                <!-- Product Selection (Required for leads) - Multiple Selection -->
                <div>
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-1 mb-2">
                        <span class="form-label mb-0" id="followupleadform-lead-products-label">Product(s) <span class="form-required">*</span></span>
                        <router-link
                            to="/products"
                            target="_blank"
                            class="link text-xs inline-flex items-center gap-1"
                        >
                            <PlusIcon class="icon-sm" aria-hidden="true" />
                            Add New Product
                        </router-link>
                    </div>
                    <div
                        class="border border-slate-200 rounded-card p-3 sm:p-4 bg-slate-50/50 max-h-52 overflow-y-auto"
                        role="group"
                        aria-labelledby="followupleadform-lead-products-label"
                        :aria-busy="!products || products.length === 0 ? 'true' : 'false'"
                    >
                        <div v-if="!products || products.length === 0" class="flex items-center justify-center gap-2 text-sm text-slate-500 py-4">
                            <span class="spinner" role="status" aria-label="Loading" />
                            Loading products...
                        </div>
                        <div v-else class="space-y-1">
                            <label
                                v-for="product in products"
                                :key="product.id"
                                class="flex items-center gap-3 p-3 rounded-control cursor-pointer transition-colors min-h-[44px] touch-manipulation"
                                :class="{ 'bg-primary-50 border border-primary-200': form.product_ids.includes(Number(product.id)), 'hover:bg-slate-50': !form.product_ids.includes(Number(product.id)) }"
                            >
                                <input
                                    type="checkbox"
                                    :value="Number(product.id)"
                                    v-model="form.product_ids"
                                    @change="loadSuggestedProducts"
                                    class="form-checkbox w-5 h-5 flex-shrink-0"
                                />
                                <span class="text-sm text-slate-700">{{ product.name }}</span>
                            </label>
                        </div>
                    </div>
                    <p class="form-hint">
                        Click to select multiple products.
                    </p>
                    <div v-if="form.product_ids.length > 0" class="mt-3 p-3 sm:p-4 bg-primary-50 border border-primary-200 rounded-card">
                        <p class="text-sm font-medium text-primary-900 mb-1 flex items-center gap-1.5">
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

                <!-- Suggested Products -->
                <div v-if="suggestedProducts.length > 0" class="bg-primary-50 border border-primary-200 rounded-card p-3 sm:p-4">
                    <p class="text-sm font-medium text-primary-900 mb-2">Suggested Related Products:</p>
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="product in suggestedProducts"
                            :key="product.id"
                            class="chip"
                        >
                            {{ product.name }}
                        </span>
                    </div>
                </div>

                <!-- Convertible Follow-ups Selection -->
                <div v-if="convertibleFollowUps.length > 0">
                    <label class="form-label" for="followupleadform-converted-from-activity">Convert from Follow-up (Optional)</label>
                    <select id="followupleadform-converted-from-activity"
                        v-model="form.converted_from_activity_id"
                        class="form-select"
                    >
                        <option value="">None - Create new lead</option>
                        <option v-for="followUp in convertibleFollowUps" :key="followUp.id" :value="followUp.id">
                            Follow-up: {{ followUp.description }} ({{ formatDate(followUp.remind_at) }})
                        </option>
                    </select>
                    <p class="form-hint">Select a follow-up to convert into this lead. This will track the conversion.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label" for="followupleadform-stage">Stage <span class="form-required">*</span></label>
                        <select id="followupleadform-stage"
                            v-model="form.stage"
                            required
                            class="form-select"
                        >
                            <option value="follow_up">Follow Up</option>
                            <option value="lead">Lead</option>
                            <option value="hot_lead">Hot Lead</option>
                            <option value="quotation">Quotation</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="followupleadform-expected-closing-date">Expected Closing Date <span class="form-required">*</span></label>
                        <input id="followupleadform-expected-closing-date"
                            v-model="form.expected_closing_date"
                            type="date"
                            required
                            class="form-input"
                        />
                    </div>

                    <div>
                        <label class="form-label" for="followupleadform-source">Source</label>
                        <select id="followupleadform-source"
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

                    <div>
                        <label class="form-label" for="followupleadform-pipeline-value">Pipeline Value (£)</label>
                        <input id="followupleadform-pipeline-value"
                            v-model.number="form.pipeline_value"
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-input"
                        />
                    </div>
                </div>
            </div>

            <div v-if="error" class="callout callout-danger" role="alert">
                {{ error }}
            </div>

            <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-6 border-t border-slate-200">
                <BaseButton variant="outline" block-mobile @click="$emit('cancel')">
                    Cancel
                </BaseButton>
                <BaseButton variant="primary" type="submit" block-mobile :loading="loading">
                    {{ loading ? 'Saving...' : (form.type === 'follow_up' ? 'Save Follow-up' : (form.type === 'appointment' ? 'Schedule Appointment' : 'Create Lead')) }}
                </BaseButton>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import axios from 'axios';
import {
    ArrowPathIcon,
    CalendarDaysIcon,
    CheckIcon,
    InformationCircleIcon,
    PlusCircleIcon,
    PlusIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { BaseButton } from '@/components/base';
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

const selectedAppointmentLead = computed(() => {
    if (!form.value.lead_id) return null;
    return customerLeads.value.find((lead) => String(lead.id) === String(form.value.lead_id)) || null;
});

const selectedAppointmentLeadProducts = computed(() => {
    const lead = selectedAppointmentLead.value;
    if (!lead) return [];
    return leadProductNames(lead)
        .split(', ')
        .filter(Boolean);
});

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const leadProductNames = (lead) => {
    const fromItems = (lead?.items || [])
        .map((item) => item.product?.name)
        .filter(Boolean);
    if (fromItems.length) {
        return [...new Set(fromItems)].join(', ');
    }
    return lead?.product?.name || '';
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

            // Use the chosen lead, or create a new opportunity with selected products.
            let leadId = form.value.lead_id || null;
            if (leadId && selectedAppointmentLeadProducts.value.length === 0) {
                error.value = 'The selected lead has no products. Add products to that lead or create a new opportunity with products.';
                loading.value = false;
                return;
            }

            if (!leadId) {
                const selectedProductIds = form.value.product_ids && form.value.product_ids.length > 0
                    ? form.value.product_ids
                    : (form.value.product_id ? [form.value.product_id] : []);

                if (selectedProductIds.length === 0) {
                    error.value = 'Please select what product(s) this appointment is for.';
                    loading.value = false;
                    return;
                }

                const leadResponse = await axios.post('/api/leads', {
                    customer_id: props.customerId,
                    stage: 'follow_up',
                    source: 'appointment',
                    product_ids: selectedProductIds,
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
