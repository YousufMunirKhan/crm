<template>
    <div class="min-h-screen bg-slate-50 w-full min-w-0 overflow-x-hidden">
        <div class="max-w-4xl mx-auto px-3 sm:px-6 py-6 lg:py-8 w-full min-w-0">
            <!-- Header -->
            <div class="mb-6 lg:mb-8">
                <router-link
                    :to="form.type === 'prospect' ? { path: '/customers', query: { type: 'prospect' } } : { path: '/customers', query: { type: 'customer' } }"
                    class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 text-sm mb-4"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    {{ form.type === 'prospect' ? 'Back to Prospects' : 'Back to Customers' }}
                </router-link>
            </div>

            <!-- Form Card -->
            <form novalidate @submit.prevent="handleSubmit" class="form-card">
                <div class="form-section-head-mint">
                    <h1 class="form-section-title-mint text-2xl sm:text-3xl">
                        {{ isEdit ? (form.type === 'customer' ? 'Edit Customer' : 'Edit Prospect') : (form.type === 'customer' ? 'Add Customer' : 'Add Prospect') }}
                    </h1>
                    <p class="form-section-desc-mint">
                        {{ isEdit ? (form.type === 'customer' ? 'Update customer information' : 'Update prospect information') : (form.type === 'customer' ? 'Fill in the details to add a customer' : 'Fill in the details to add a prospect') }}
                    </p>
                </div>
                <div class="form-body space-y-6 lg:space-y-8">
                    <div v-if="!isEdit && !isSimpleCustomerCreate" class="mb-2">
                        <div class="flex items-center gap-2 sm:gap-3 overflow-x-auto pb-1">
                            <button
                                v-for="step in createSteps"
                                :key="step.id"
                                type="button"
                                @click="goToStep(step.id)"
                                class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border text-xs sm:text-sm whitespace-nowrap transition-colors"
                                :class="currentStep === step.id ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                            >
                                <span
                                    class="w-5 h-5 rounded-full text-[11px] flex items-center justify-center"
                                    :class="currentStep === step.id ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700'"
                                >
                                    {{ step.id }}
                                </span>
                                {{ step.title }}
                            </button>
                        </div>
                    </div>
                    <!-- Required fields -->
                    <div v-show="isEdit || isSimpleCustomerCreate || currentStep === 1">
                        <h2 class="text-base font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center">1</span>
                            Basic Information
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="form-label">Customer Name *</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Full name or primary contact"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label">Business Name</label>
                                <input
                                    v-model="form.business_name"
                                    type="text"
                                    placeholder="Company or business name"
                                    class="form-input"
                                />
                            </div>
                            <div v-if="isEdit || !isSimpleCustomerCreate">
                                <label class="form-label">Owner Name</label>
                                <input
                                    v-model="form.owner_name"
                                    type="text"
                                    placeholder="Owner or director name"
                                    class="form-input"
                                />
                            </div>
                            <div v-if="isEdit || !isSimpleCustomerCreate">
                                <label class="form-label">Contact Person 2 Name</label>
                                <input
                                    v-model="form.contact_person_2_name"
                                    type="text"
                                    placeholder="Second contact name"
                                    class="form-input"
                                />
                            </div>
                            <div v-if="isEdit || !isSimpleCustomerCreate">
                                <label class="form-label">Contact Person 2 Phone</label>
                                <input
                                    v-model="form.contact_person_2_phone"
                                    type="tel"
                                    placeholder="e.g. 07700900123"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label">Phone *</label>
                                <input
                                    v-model="form.phone"
                                    type="tel"
                                    placeholder="e.g. 07700900123"
                                    class="form-input"
                                    @blur="syncPhoneToWhatsApp"
                                />
                            </div>
                            <div v-if="isEdit || !isSimpleCustomerCreate">
                                <label class="form-label">Customer WhatsApp</label>
                                <input
                                    v-model="form.whatsapp_number"
                                    type="tel"
                                    placeholder="e.g. 447700900123"
                                    class="form-input"
                                    @blur="syncWhatsAppToPhone"
                                />
                                <p class="text-xs text-slate-500 mt-1">Phone and WhatsApp sync when one is empty; you can change either.</p>
                            </div>
                            <div v-if="isEdit || !isSimpleCustomerCreate" class="sm:col-span-2">
                                <label class="form-label">Customer Email</label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    placeholder="customer@example.com"
                                    class="form-input"
                                />
                            </div>
                            <div v-if="isEdit || !isSimpleCustomerCreate" class="sm:col-span-2">
                                <label class="form-label">Source</label>
                                <select
                                    v-model="form.source"
                                    class="form-input"
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
                            <div v-if="isSimpleCustomerCreate" class="sm:col-span-2">
                                <label class="form-label">Address</label>
                                <textarea
                                    v-model="form.address"
                                    rows="2"
                                    placeholder="Street address"
                                    class="form-input resize-none"
                                />
                            </div>
                            <div v-if="isSimpleCustomerCreate">
                                <label class="form-label">City</label>
                                <input
                                    v-model="form.city"
                                    type="text"
                                    placeholder="City"
                                    class="form-input"
                                />
                            </div>
                            <div v-if="isSimpleCustomerCreate">
                                <label class="form-label">Postcode</label>
                                <input
                                    v-model="form.postcode"
                                    type="text"
                                    placeholder="Postcode"
                                    class="form-input"
                                />
                            </div>
                            <div v-if="isSimpleCustomerCreate" class="sm:col-span-2">
                                <label class="form-label">Won Product (Optional)</label>
                                <div class="border border-slate-200 rounded-lg p-3 max-h-40 overflow-y-auto bg-white">
                                    <label v-for="p in products" :key="p.id" class="flex items-center gap-2 py-1.5 cursor-pointer">
                                        <input type="checkbox" :value="p.id" v-model="wonProductIds" class="form-checkbox" />
                                        <span class="text-sm">{{ p.name }}</span>
                                    </label>
                                    <p v-if="!products.length" class="text-sm text-slate-400 py-2">Loading products...</p>
                                </div>
                                <p class="text-xs text-slate-500 mt-1">If selected, these products will be recorded as won after customer creation.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Optional remote/license fields (multiple) -->
                    <div v-show="isEdit || (!isSimpleCustomerCreate && currentStep === 2)">
                        <h2 class="text-base font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-xs flex items-center justify-center">2</span>
                            Remote & License (Optional)
                        </h2>
                        <p class="text-sm text-slate-500 mb-4">Add one or more Remote/License entries. Use + Add to add more.</p>
                        <div
                            v-for="(rl, idx) in form.remote_licenses"
                            :key="idx"
                            class="mb-6 p-4 border border-slate-200 rounded-xl bg-slate-50/50 space-y-4"
                        >
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <span class="text-sm font-medium text-slate-700">Entry {{ idx + 1 }}</span>
                                <button
                                    v-if="form.remote_licenses.length > 1"
                                    type="button"
                                    @click="removeRemoteLicense(idx)"
                                    class="text-red-600 hover:text-red-800 text-sm"
                                >
                                    Remove
                                </button>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="sm:col-span-2 lg:col-span-1">
                                    <label class="form-label">Anydesk or Rustdesk</label>
                                    <input
                                        v-model="rl.anydesk_rustdesk"
                                        type="text"
                                        placeholder="ID or connection details"
                                        class="form-input"
                                    />
                                </div>
                                <div class="sm:col-span-2 lg:col-span-1">
                                    <label class="form-label">Passwords</label>
                                    <input
                                        v-model="rl.passwords"
                                        type="text"
                                        placeholder="Relevant passwords"
                                        class="form-input"
                                    />
                                </div>
                                <div>
                                    <label class="form-label">ePOS Type</label>
                                    <input
                                        v-model="rl.epos_type"
                                        type="text"
                                        placeholder="e.g. TouchBistro, Square"
                                        class="form-input"
                                    />
                                </div>
                                <div>
                                    <label class="form-label">Lic-days (Optional)</label>
                                    <input
                                        v-model="rl.lic_days"
                                        type="text"
                                        placeholder="e.g. 30, 90, 1 Year"
                                        class="form-input"
                                    />
                                </div>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="addRemoteLicense"
                            class="text-sm font-medium text-emerald-700 hover:text-emerald-900"
                        >
                            + Add Remote & License
                        </button>
                    </div>

                    <!-- Address -->
                    <div v-show="isEdit || (!isSimpleCustomerCreate && currentStep === 3)">
                        <h2 class="text-base font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-xs flex items-center justify-center">3</span>
                            Address & Notes
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label class="form-label">Address</label>
                                <textarea
                                    v-model="form.address"
                                    rows="2"
                                    placeholder="Street address"
                                    class="form-input resize-none"
                                />
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">City</label>
                                    <input
                                        v-model="form.city"
                                        type="text"
                                        placeholder="City"
                                        class="form-input"
                                    />
                                </div>
                                <div>
                                    <label class="form-label">Postcode</label>
                                    <input
                                        v-model="form.postcode"
                                        type="text"
                                        placeholder="Postcode"
                                        class="form-input"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="form-label">VAT Number</label>
                                <input
                                    v-model="form.vat_number"
                                    type="text"
                                    placeholder="VAT number"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label">Notes</label>
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    placeholder="Additional notes"
                                    class="form-input resize-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Also create: Follow-up / Appointment / Lead (prospect flow only) -->
                    <div v-if="!isEdit && !isSimpleCustomerCreate" v-show="currentStep === 4" ref="quickAddSectionRef" class="border-t border-slate-200 pt-6">
                        <h2 class="text-base font-semibold text-slate-900 mb-3 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-xs flex items-center justify-center">4</span>
                            Also create (optional)
                        </h2>
                        <p class="text-sm text-slate-500 mb-4">Quickly add a follow-up, appointment, or lead when creating this customer.</p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                            <label class="flex items-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all" :class="!quickAddType ? 'border-slate-300 bg-slate-100' : 'border-slate-200 hover:border-slate-300'">
                                <input v-model="quickAddType" type="radio" value="" class="sr-only" />
                                <span>—</span>
                                <span class="font-medium text-sm">None</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all" :class="quickAddType === 'follow_up' ? 'border-violet-500 bg-violet-50' : 'border-slate-200 hover:border-slate-300'">
                                <input v-model="quickAddType" type="radio" value="follow_up" class="sr-only" />
                                <span>🔄</span>
                                <span class="font-medium text-sm">Follow-up</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all" :class="quickAddType === 'appointment' ? 'border-violet-500 bg-violet-50' : 'border-slate-200 hover:border-slate-300'">
                                <input v-model="quickAddType" type="radio" value="appointment" class="sr-only" />
                                <span>📅</span>
                                <span class="font-medium text-sm">Appointment</span>
                            </label>
                            <label class="flex items-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all" :class="quickAddType === 'lead' ? 'border-violet-500 bg-violet-50' : 'border-slate-200 hover:border-slate-300'">
                                <input v-model="quickAddType" type="radio" value="lead" class="sr-only" />
                                <span>➕</span>
                                <span class="font-medium text-sm">Lead</span>
                            </label>
                        </div>
                        <div v-if="quickAddType" class="space-y-4 p-4 bg-slate-50 rounded-xl">
                            <div>
                                <label class="form-label">Product(s) *</label>
                                <div class="border border-slate-200 rounded-lg p-3 max-h-40 overflow-y-auto bg-white">
                                    <label v-for="p in products" :key="p.id" class="flex items-center gap-2 py-1.5 cursor-pointer">
                                        <input type="checkbox" :value="p.id" v-model="quickAddProductIds" class="form-checkbox" />
                                        <span class="text-sm">{{ p.name }}</span>
                                    </label>
                                </div>
                            </div>
                            <div>
                                <label class="form-label">Notes</label>
                                <textarea v-model="quickAddComment" rows="2" class="form-input resize-none" placeholder="Comment or notes..." />
                            </div>
                            <div v-if="quickAddType === 'follow_up'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Follow-up Date & Time *</label>
                                    <input v-model="quickAddFollowUpAt" type="datetime-local" class="form-input" />
                                </div>
                            </div>
                            <div v-if="quickAddType === 'appointment'" class="space-y-4">
                                <div>
                                    <label class="form-label">Assign to (who will attend) *</label>
                                    <select v-model="quickAddAssignedUserId" class="form-input">
                                        <option value="">Select team member...</option>
                                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role?.name || '—' }})</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="form-label">Date *</label>
                                        <input v-model="quickAddAppointmentDate" type="date" class="form-input" />
                                    </div>
                                    <div>
                                        <label class="form-label">Time *</label>
                                        <input v-model="quickAddAppointmentTime" type="time" class="form-input" />
                                    </div>
                                </div>
                            </div>
                            <div v-if="quickAddType === 'lead'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label">Stage</label>
                                    <select v-model="quickAddStage" class="form-input">
                                        <option value="follow_up">Follow-up</option>
                                        <option value="lead">Lead</option>
                                        <option value="hot_lead">Hot Lead</option>
                                        <option value="quotation">Quotation</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Expected Closing Date</label>
                                    <input v-model="quickAddExpectedDate" type="date" class="form-input" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error message -->
                    <div v-if="error" class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                        {{ error }}
                    </div>
                </div>

                <!-- Footer actions -->
                <div class="form-actions">
                    <router-link
                        :to="form.type === 'prospect' ? { path: '/customers', query: { type: 'prospect' } } : { path: '/customers', query: { type: 'customer' } }"
                        class="form-btn-cancel text-center"
                    >
                        Cancel
                    </router-link>
                    <button v-if="!isEdit && !isSimpleCustomerCreate && currentStep > 1" type="button" class="form-btn-cancel" @click="prevStep">Back</button>
                    <button v-if="!isEdit && !isSimpleCustomerCreate && currentStep < 4" type="button" class="form-btn-submit" @click="nextStep">Next</button>
                    <button
                        v-if="isEdit || isSimpleCustomerCreate || currentStep === 4"
                        type="submit"
                        :disabled="loading"
                        class="form-btn-submit"
                    >
                        <span v-if="loading" class="inline-flex items-center gap-2">
                            <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            Saving...
                        </span>
                        <span v-else>{{ isEdit ? 'Update Customer' : (form.type === 'customer' ? 'Create Customer' : 'Create Prospect') }}</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Sale credit (admin/manager): after customer created with won products — notification only -->
        <div v-if="showSaleCreditModal" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
                <div class="p-6 border-b border-slate-200">
                    <h3 class="text-lg font-semibold text-slate-900">Sale Credit</h3>
                    <p class="text-sm text-slate-600 mt-1">{{ saleCreditContextText }}</p>
                </div>
                <div class="p-6 space-y-3">
                    <label class="block text-sm font-medium text-slate-700">Who should this sale go on?</label>
                    <select v-model="selectedSaleCreditUserId" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500">
                        <option value="">Select user...</option>
                        <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }} ({{ u.role?.name || '—' }})</option>
                    </select>
                </div>
                <div class="px-6 pb-6 flex justify-end gap-3">
                    <button type="button" class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50" @click="finishSaleCreditSkip">Skip</button>
                    <button type="button" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50" :disabled="!selectedSaleCreditUserId" @click="finishSaleCreditConfirm">Confirm</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const auth = useAuthStore();

const isEdit = computed(() => !!route.params.id);

/** Create mode: derive type from URL immediately so customer add never flashes prospect UI first. */
function createTypeFromRoute() {
    return route.query.type === 'customer' ? 'customer' : 'prospect';
}

const form = reactive({
    type: isEdit.value ? 'prospect' : createTypeFromRoute(),
    name: '',
    business_name: '',
    owner_name: '',
    contact_person_2_name: '',
    contact_person_2_phone: '',
    phone: '',
    email: '',
    whatsapp_number: '',
    address: '',
    postcode: '',
    city: '',
    vat_number: '',
    notes: '',
    source: '',
    remote_licenses: [{ anydesk_rustdesk: '', passwords: '', epos_type: '', lic_days: null }],
});

const isSimpleCustomerCreate = computed(() => !isEdit.value && form.type === 'customer');

const isSaleCreditRole = computed(() => {
    const r = auth.user?.role?.name;
    return r === 'Admin' || r === 'System Admin' || r === 'Manager';
});

const showSaleCreditModal = ref(false);
const selectedSaleCreditUserId = ref('');
const saleCreditContextText = ref('');
const saleCreditPendingRoute = ref(null);
const saleCreditLeadId = ref(null);

function addRemoteLicense() {
    form.remote_licenses.push({ anydesk_rustdesk: '', passwords: '', epos_type: '', lic_days: null });
}

function removeRemoteLicense(idx) {
    form.remote_licenses.splice(idx, 1);
}

const loading = ref(false);
const error = ref(null);
const products = ref([]);
const users = ref([]);
const wonProductIds = ref([]);
const quickAddType = ref('');
const quickAddComment = ref('');
const quickAddFollowUpAt = ref('');
const quickAddAssignedUserId = ref('');
const quickAddAppointmentDate = ref('');
const quickAddAppointmentTime = ref('10:00');
const quickAddStage = ref('follow_up');
const quickAddExpectedDate = ref('');
const quickAddProductIds = ref([]);
const quickAddSectionRef = ref(null);
const currentStep = ref(1);
const createSteps = [
    { id: 1, title: 'Basic' },
    { id: 2, title: 'Remote & License' },
    { id: 3, title: 'Address & Notes' },
    { id: 4, title: 'Also Create' },
];

/**
 * @param {number} step
 * @returns {string|null} Error message or null if valid
 */
function validateStep(step) {
    if (step === 1) {
        if (!form.name?.trim()) {
            return 'Customer name is required.';
        }
        if (!form.phone?.trim()) {
            return 'Phone is required.';
        }
        return null;
    }
    if (step === 2 || step === 3) {
        return null;
    }
    if (step === 4) {
        if (!quickAddType.value) return null;
        const prodIds = quickAddProductIds.value.length ? quickAddProductIds.value : (products.value.length ? [products.value[0].id] : []);
        if (!prodIds.length) return 'Please select at least one product, or add products in the system.';
        if (quickAddType.value === 'follow_up' && !quickAddFollowUpAt.value) return 'Please set follow-up date and time.';
        if (quickAddType.value === 'appointment') {
            if (!quickAddAssignedUserId.value) return 'Please select who will attend this appointment.';
            if (!quickAddAppointmentDate.value || !quickAddAppointmentTime.value) return 'Please set appointment date and time.';
        }
        return null;
    }
    return null;
}

function validateAllStepsForCreate() {
    for (let s = 1; s <= 4; s++) {
        const msg = validateStep(s);
        if (msg) return { step: s, message: msg };
    }
    return null;
}

function goToStep(stepId) {
    if (isEdit.value || isSimpleCustomerCreate.value) return;
    if (stepId === currentStep.value) return;
    if (stepId < currentStep.value) {
        error.value = null;
        currentStep.value = stepId;
        return;
    }
    for (let s = currentStep.value; s < stepId; s++) {
        const msg = validateStep(s);
        if (msg) {
            error.value = msg;
            toast.error(msg);
            currentStep.value = s;
            return;
        }
    }
    error.value = null;
    currentStep.value = stepId;
}

function nextStep() {
    const msg = validateStep(currentStep.value);
    if (msg) {
        error.value = msg;
        toast.error(msg);
        return;
    }
    if (currentStep.value < 4) currentStep.value += 1;
}

function prevStep() {
    error.value = null;
    if (currentStep.value > 1) currentStep.value -= 1;
}

function normalizeForWhatsApp(phone) {
    const n = (phone || '').replace(/\s/g, '');
    if (n.startsWith('0') && n.length === 11) return '44' + n.slice(1);
    if (n.startsWith('44') && n.length === 12) return n;
    if (n.startsWith('+44')) return '44' + n.slice(3);
    return n;
}
function normalizeForPhone(wa) {
    const n = (wa || '').replace(/\s/g, '');
    if (n.startsWith('44') && n.length === 12) return '0' + n.slice(2);
    if (n.startsWith('+44')) return '0' + n.slice(3);
    return n;
}
// Phone → WhatsApp: when phone is entered and WhatsApp empty
function syncPhoneToWhatsApp() {
    const phone = (form.phone || '').trim();
    const wa = (form.whatsapp_number || '').trim();
    if (phone && !wa) form.whatsapp_number = normalizeForWhatsApp(phone);
}
// WhatsApp → Phone: when WhatsApp is entered and phone empty
function syncWhatsAppToPhone() {
    const wa = (form.whatsapp_number || '').trim();
    const phone = (form.phone || '').trim();
    if (wa && !phone) form.phone = normalizeForPhone(wa);
}

function finishSaleCreditNavigate() {
    const dest = saleCreditPendingRoute.value;
    showSaleCreditModal.value = false;
    selectedSaleCreditUserId.value = '';
    saleCreditPendingRoute.value = null;
    saleCreditLeadId.value = null;
    if (dest) {
        router.push(dest);
    }
}

function finishSaleCreditSkip() {
    finishSaleCreditNavigate();
}

async function finishSaleCreditConfirm() {
    const selected = users.value.find((u) => String(u.id) === String(selectedSaleCreditUserId.value));
    if (!selected) return;
    if (saleCreditLeadId.value) {
        try {
            await axios.put(`/api/leads/${saleCreditLeadId.value}`, { assigned_to: selected.id });
            await axios.post(`/api/leads/${saleCreditLeadId.value}/activity`, {
                type: 'note',
                description: `Sale credited to ${selected.name} by ${auth.user?.name || 'Admin'}.`,
            });
        } catch (e) {
            toast.error(e?.response?.data?.message || 'Failed to save sale credit.');
            return;
        }
    }
    toast.success(`Sale will go on ${selected.name}.`);
    finishSaleCreditNavigate();
}

const loadCustomer = async () => {
    if (!route.params.id) return;
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/customers/${route.params.id}`);
        const c = data.customer || data;
        form.type = c.type === 'customer' ? 'customer' : 'prospect';
        form.name = c.name || '';
        form.business_name = c.business_name || '';
        form.owner_name = c.owner_name || '';
        form.contact_person_2_name = c.contact_person_2_name || '';
        form.contact_person_2_phone = c.contact_person_2_phone || '';
        form.phone = c.phone || '';
        form.email = c.email || '';
        form.whatsapp_number = c.whatsapp_number || '';
        form.address = c.address || '';
        form.postcode = c.postcode || '';
        form.city = c.city || '';
        form.vat_number = c.vat_number || '';
        form.notes = c.notes || '';
        form.source = c.source || '';
        const rls = c.remote_licenses && c.remote_licenses.length
            ? c.remote_licenses.map(rl => ({
                anydesk_rustdesk: rl.anydesk_rustdesk || '',
                passwords: rl.passwords || '',
                epos_type: rl.epos_type || '',
                lic_days: rl.lic_days ?? null,
            }))
            : (c.anydesk_rustdesk || c.passwords || c.epos_type || c.lic_days != null)
                ? [{ anydesk_rustdesk: c.anydesk_rustdesk || '', passwords: c.passwords || '', epos_type: c.epos_type || '', lic_days: c.lic_days ?? null }]
                : [{ anydesk_rustdesk: '', passwords: '', epos_type: '', lic_days: null }];
        form.remote_licenses = rls;
    } catch (err) {
        toast.error('Failed to load customer');
        router.push({ path: '/customers', query: { type: form.type === 'customer' ? 'customer' : 'prospect' } });
    } finally {
        loading.value = false;
    }
};

const handleSubmit = async () => {
    error.value = null;
    if (!auth.initialized) {
        await auth.bootstrap();
    }
    if (isSimpleCustomerCreate.value || isEdit.value) {
        const msg = validateStep(1);
        if (msg) {
            error.value = msg;
            toast.error(msg);
            return;
        }
    } else {
        const fail = validateAllStepsForCreate();
        if (fail) {
            error.value = fail.message;
            currentStep.value = fail.step;
            toast.error(fail.message);
            return;
        }
    }

    loading.value = true;
    try {
        const payload = {
            ...form,
            type: form.type === 'customer' ? 'customer' : 'prospect',
            won_product_ids: isSimpleCustomerCreate.value ? wonProductIds.value : [],
            remote_licenses: form.remote_licenses.map(rl => ({
                anydesk_rustdesk: rl.anydesk_rustdesk || null,
                passwords: rl.passwords || null,
                epos_type: rl.epos_type || null,
                lic_days: rl.lic_days === '' || rl.lic_days === null ? null : rl.lic_days,
            })).filter(rl => rl.anydesk_rustdesk || rl.passwords || rl.epos_type || rl.lic_days !== null),
        };
        let customerId;
        let deferRedirectForSaleCredit = false;
        if (isEdit.value) {
            await axios.put(`/api/customers/${route.params.id}`, payload);
            toast.success('Customer updated successfully');
        } else {
            const { data } = await axios.post('/api/customers', payload);
            customerId = data.id;
            const wonLeadId = data.won_lead_id || null;
            toast.success('Customer created successfully');
            if (isSimpleCustomerCreate.value && isSaleCreditRole.value && customerId) {
                deferRedirectForSaleCredit = true;
                saleCreditPendingRoute.value = { path: '/customers', query: { type: 'customer' } };
                saleCreditLeadId.value = wonLeadId;
                saleCreditContextText.value = wonProductIds.value.length
                    ? 'This customer was saved with won products. Who should this sale go on?'
                    : 'Customer created. Who should get credit for this customer?';
                selectedSaleCreditUserId.value = '';
                showSaleCreditModal.value = true;
            }
            if (!isSimpleCustomerCreate.value && quickAddType.value && customerId) {
                const prodIds = quickAddProductIds.value.length ? quickAddProductIds.value : (products.value.length ? [products.value[0].id] : []);
                if (quickAddType.value === 'follow_up') {
                    await axios.post('/api/leads/followup-or-lead', {
                        customer_id: customerId,
                        type: 'follow_up',
                        comment: quickAddComment.value || 'Follow-up from customer creation',
                        product_ids: prodIds,
                        follow_up_at: quickAddFollowUpAt.value,
                    });
                } else if (quickAddType.value === 'lead') {
                    await axios.post('/api/leads/followup-or-lead', {
                        customer_id: customerId,
                        type: 'lead',
                        comment: quickAddComment.value || 'Lead from customer creation',
                        product_ids: prodIds,
                        stage: quickAddStage.value,
                        expected_closing_date: quickAddExpectedDate.value || null,
                        source: form.source || null,
                    });
                } else if (quickAddType.value === 'appointment') {
                    const leadRes = await axios.post('/api/leads', {
                        customer_id: customerId,
                        stage: 'follow_up',
                        source: 'appointment',
                        product_ids: prodIds,
                        comment: quickAddComment.value || `Appointment ${quickAddAppointmentDate.value} at ${quickAddAppointmentTime.value}`,
                    });
                    await axios.post(`/api/leads/${leadRes.data.id}/activity`, {
                        type: 'appointment',
                        description: quickAddComment.value || `Appointment scheduled for ${quickAddAppointmentDate.value} at ${quickAddAppointmentTime.value}`,
                        meta: { appointment_date: quickAddAppointmentDate.value, appointment_time: quickAddAppointmentTime.value },
                        assigned_user_id: quickAddAssignedUserId.value || null,
                    });
                }
            }
        }
        if (deferRedirectForSaleCredit) {
            // User confirms or skips in modal — see finishSaleCreditNavigate
        } else if (customerId && !isSimpleCustomerCreate.value && quickAddType.value) {
            router.push(`/customers/${customerId}`);
        } else {
            router.push({ path: '/customers', query: { type: payload.type || 'prospect' } });
        }
    } catch (err) {
        error.value = err.response?.data?.message || err.response?.data?.errors ? Object.values(err.response.data.errors || {}).flat().join(', ') : 'Failed to save';
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    if (!auth.initialized) {
        await auth.bootstrap();
    }
    try {
        const [{ data: productsData }, { data: usersData }] = await Promise.all([
            axios.get('/api/products'),
            axios.get('/api/users'),
        ]);
        products.value = productsData || [];
        users.value = Array.isArray(usersData) ? usersData : (usersData?.data ?? []);
    } catch (_) {}
    if (isEdit.value) {
        loadCustomer();
    } else {
        const now = new Date();
        now.setHours(now.getHours() + 1);
        quickAddFollowUpAt.value = now.toISOString().slice(0, 16);
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        quickAddAppointmentDate.value = tomorrow.toISOString().slice(0, 10);
    }
});

watch(quickAddType, async (type) => {
    if (!type || isEdit.value || isSimpleCustomerCreate.value) return;
    if (currentStep.value !== 4) currentStep.value = 4;
    await nextTick();
    quickAddSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

// Same component instance when switching e.g. add prospect ↔ add customer — keep type in sync with URL
watch(
    () => (isEdit.value ? null : String(route.query.type || 'prospect')),
    (t) => {
        if (isEdit.value) return;
        form.type = t === 'customer' ? 'customer' : 'prospect';
        currentStep.value = 1;
        error.value = null;
    },
);
</script>
