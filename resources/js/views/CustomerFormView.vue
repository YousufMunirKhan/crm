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
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">
                    {{ isEdit ? 'Edit Customer' : (form.type === 'customer' ? 'Add New Customer' : 'Add New Prospect') }}
                </h1>
                <p class="text-slate-500 mt-1 text-sm">
                    {{ isEdit ? 'Update customer information' : (form.type === 'customer' ? 'Fill in the details to add a new customer' : 'Fill in the details to add a new prospect') }}
                </p>
            </div>

            <!-- Form Card -->
            <form @submit.prevent="handleSubmit" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-4 sm:p-6 lg:p-8 space-y-6 lg:space-y-8">
                    <!-- Required fields -->
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center">1</span>
                            Basic Information
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Customer Name *</label>
                                <input
                                    v-model="form.name"
                                    type="text"
                                    required
                                    placeholder="Full name or primary contact"
                                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Business Name</label>
                                <input
                                    v-model="form.business_name"
                                    type="text"
                                    placeholder="Company or business name"
                                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Owner Name</label>
                                <input
                                    v-model="form.owner_name"
                                    type="text"
                                    placeholder="Owner or director name"
                                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Contact Person 2 Name</label>
                                <input
                                    v-model="form.contact_person_2_name"
                                    type="text"
                                    placeholder="Second contact name"
                                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Contact Person 2 Phone</label>
                                <input
                                    v-model="form.contact_person_2_phone"
                                    type="tel"
                                    placeholder="e.g. 07700900123"
                                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Phone *</label>
                                <input
                                    v-model="form.phone"
                                    type="tel"
                                    required
                                    placeholder="e.g. 07700900123"
                                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                    @blur="syncPhoneToWhatsApp"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Customer WhatsApp</label>
                                <input
                                    v-model="form.whatsapp_number"
                                    type="tel"
                                    placeholder="e.g. 447700900123"
                                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                    @blur="syncWhatsAppToPhone"
                                />
                                <p class="text-xs text-slate-500 mt-1">Phone and WhatsApp sync when one is empty; you can change either.</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Customer Email</label>
                                <input
                                    v-model="form.email"
                                    type="email"
                                    placeholder="customer@example.com"
                                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Source</label>
                                <select
                                    v-model="form.source"
                                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
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
                        </div>
                    </div>

                    <!-- Optional remote/license fields (multiple) -->
                    <div>
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
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Anydesk or Rustdesk</label>
                                    <input
                                        v-model="rl.anydesk_rustdesk"
                                        type="text"
                                        placeholder="ID or connection details"
                                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                    />
                                </div>
                                <div class="sm:col-span-2 lg:col-span-1">
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Passwords</label>
                                    <input
                                        v-model="rl.passwords"
                                        type="text"
                                        placeholder="Relevant passwords"
                                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">ePOS Type</label>
                                    <input
                                        v-model="rl.epos_type"
                                        type="text"
                                        placeholder="e.g. TouchBistro, Square"
                                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Lic-days (Optional)</label>
                                    <input
                                        v-model="rl.lic_days"
                                        type="text"
                                        placeholder="e.g. 30, 90, 1 Year"
                                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                    />
                                </div>
                            </div>
                        </div>
                        <button
                            type="button"
                            @click="addRemoteLicense"
                            class="text-sm text-blue-600 hover:text-blue-800"
                        >
                            + Add Remote & License
                        </button>
                    </div>

                    <!-- Address -->
                    <div>
                        <h2 class="text-base font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-xs flex items-center justify-center">3</span>
                            Address & Notes
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                                <textarea
                                    v-model="form.address"
                                    rows="2"
                                    placeholder="Street address"
                                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500 resize-none"
                                />
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">City</label>
                                    <input
                                        v-model="form.city"
                                        type="text"
                                        placeholder="City"
                                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                    />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Postcode</label>
                                    <input
                                        v-model="form.postcode"
                                        type="text"
                                        placeholder="Postcode"
                                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">VAT Number</label>
                                <input
                                    v-model="form.vat_number"
                                    type="text"
                                    placeholder="VAT number"
                                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500"
                                />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                                <textarea
                                    v-model="form.notes"
                                    rows="3"
                                    placeholder="Additional notes"
                                    class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-slate-900 placeholder-slate-400 text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500 resize-none"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Also create: Follow-up / Appointment / Lead (only when adding) -->
                    <div v-if="!isEdit" class="border-t border-slate-200 pt-6">
                        <h2 class="text-base font-semibold text-slate-900 mb-3 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-xs flex items-center justify-center">+</span>
                            Also create (optional)
                        </h2>
                        <p class="text-sm text-slate-500 mb-4">Quickly add a follow-up, appointment, or lead when creating this customer.</p>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                            <label
                                class="flex items-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all"
                                :class="!quickAddType ? 'border-slate-300 bg-slate-100' : 'border-slate-200 hover:border-slate-300'"
                            >
                                <input v-model="quickAddType" type="radio" value="" class="sr-only" />
                                <span>—</span>
                                <span class="font-medium text-sm">None</span>
                            </label>
                            <label
                                class="flex items-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all"
                                :class="quickAddType === 'follow_up' ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:border-slate-300'"
                            >
                                <input v-model="quickAddType" type="radio" value="follow_up" class="sr-only" />
                                <span>🔄</span>
                                <span class="font-medium text-sm">Follow-up</span>
                            </label>
                            <label
                                class="flex items-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all"
                                :class="quickAddType === 'appointment' ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:border-slate-300'"
                            >
                                <input v-model="quickAddType" type="radio" value="appointment" class="sr-only" />
                                <span>📅</span>
                                <span class="font-medium text-sm">Appointment</span>
                            </label>
                            <label
                                class="flex items-center gap-2 p-3 rounded-xl border-2 cursor-pointer transition-all"
                                :class="quickAddType === 'lead' ? 'border-blue-500 bg-blue-50' : 'border-slate-200 hover:border-slate-300'"
                            >
                                <input v-model="quickAddType" type="radio" value="lead" class="sr-only" />
                                <span>➕</span>
                                <span class="font-medium text-sm">Lead</span>
                            </label>
                        </div>
                        <div v-if="quickAddType" class="space-y-4 p-4 bg-slate-50 rounded-xl">
                            <div v-if="quickAddType === 'follow_up' || quickAddType === 'lead' || quickAddType === 'appointment'">
                                <label class="block text-sm font-medium text-slate-700 mb-1">Product(s) *</label>
                                <div class="border border-slate-200 rounded-lg p-3 max-h-40 overflow-y-auto bg-white">
                                    <label v-for="p in products" :key="p.id" class="flex items-center gap-2 py-1.5 cursor-pointer">
                                        <input type="checkbox" :value="p.id" v-model="quickAddProductIds" class="rounded" />
                                        <span class="text-sm">{{ p.name }}</span>
                                    </label>
                                    <p v-if="!products.length" class="text-sm text-slate-400 py-2">Loading products...</p>
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                                <textarea v-model="quickAddComment" rows="2" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" placeholder="Comment or notes..." />
                            </div>
                            <div v-if="quickAddType === 'follow_up'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Follow-up Date & Time *</label>
                                    <input v-model="quickAddFollowUpAt" type="datetime-local" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" />
                                </div>
                            </div>
                            <div v-if="quickAddType === 'appointment'" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Assign to (who will attend) *</label>
                                    <select v-model="quickAddAssignedUserId" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
                                        <option value="">Select team member...</option>
                                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role?.name || '—' }})</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Date *</label>
                                        <input v-model="quickAddAppointmentDate" type="date" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" />
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-slate-700 mb-1">Time *</label>
                                        <input v-model="quickAddAppointmentTime" type="time" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" />
                                    </div>
                                </div>
                            </div>
                            <div v-if="quickAddType === 'lead'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Stage</label>
                                    <select v-model="quickAddStage" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm">
                                        <option value="follow_up">Follow-up</option>
                                        <option value="lead">Lead</option>
                                        <option value="hot_lead">Hot Lead</option>
                                        <option value="quotation">Quotation</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Expected Closing Date</label>
                                    <input v-model="quickAddExpectedDate" type="date" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-sm" />
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
                <div class="px-4 sm:px-6 lg:px-8 py-4 bg-slate-50 border-t border-slate-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
                    <router-link
                        :to="form.type === 'prospect' ? { path: '/customers', query: { type: 'prospect' } } : { path: '/customers', query: { type: 'customer' } }"
                        class="px-6 py-2.5 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 font-medium text-sm text-center touch-manipulation"
                    >
                        Cancel
                    </router-link>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="px-6 py-2.5 bg-slate-900 text-white rounded-lg hover:bg-slate-800 font-medium disabled:opacity-50 disabled:cursor-not-allowed text-sm touch-manipulation"
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
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const isEdit = computed(() => !!route.params.id);

const form = reactive({
    type: 'prospect',
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

function addRemoteLicense() {
    form.remote_licenses.push({ anydesk_rustdesk: '', passwords: '', epos_type: '', lic_days: null });
}

function removeRemoteLicense(idx) {
    form.remote_licenses.splice(idx, 1);
}

const loading = ref(false);
const error = ref(null);

const quickAddType = ref('');
const quickAddComment = ref('');
const quickAddFollowUpAt = ref('');
const quickAddAssignedUserId = ref('');
const quickAddAppointmentDate = ref('');
const quickAddAppointmentTime = ref('10:00');
const quickAddStage = ref('follow_up');
const quickAddExpectedDate = ref('');
const quickAddProductIds = ref([]);
const products = ref([]);
const users = ref([]);

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
    loading.value = true;
    error.value = null;
    try {
        const payload = {
            ...form,
            type: form.type === 'customer' ? 'customer' : 'prospect',
            remote_licenses: form.remote_licenses.map(rl => ({
                anydesk_rustdesk: rl.anydesk_rustdesk || null,
                passwords: rl.passwords || null,
                epos_type: rl.epos_type || null,
                lic_days: rl.lic_days === '' || rl.lic_days === null ? null : rl.lic_days,
            })).filter(rl => rl.anydesk_rustdesk || rl.passwords || rl.epos_type || rl.lic_days !== null),
        };
        let customerId;
        if (isEdit.value) {
            await axios.put(`/api/customers/${route.params.id}`, payload);
            toast.success('Customer updated successfully');
        } else {
            const { data } = await axios.post('/api/customers', payload);
            customerId = data.id;
            toast.success('Customer created successfully');

            if (quickAddType.value && customerId) {
                const prodIds = quickAddProductIds.value && quickAddProductIds.value.length ? quickAddProductIds.value : (products.value.length ? [products.value[0].id] : []);
                if (quickAddType.value === 'follow_up') {
                    if (!quickAddFollowUpAt.value) {
                        error.value = 'Please set follow-up date and time.';
                        loading.value = false;
                        return;
                    }
                    if (!prodIds.length) {
                        error.value = 'Please select at least one product.';
                        loading.value = false;
                        return;
                    }
                    await axios.post('/api/leads/followup-or-lead', {
                        customer_id: customerId,
                        type: 'follow_up',
                        comment: quickAddComment.value || 'Follow-up from customer creation',
                        product_ids: prodIds,
                        follow_up_at: quickAddFollowUpAt.value,
                    });
                    toast.success('Follow-up created');
                } else if (quickAddType.value === 'lead') {
                    if (!prodIds.length) {
                        error.value = 'Please select at least one product.';
                        loading.value = false;
                        return;
                    }
                    await axios.post('/api/leads/followup-or-lead', {
                        customer_id: customerId,
                        type: 'lead',
                        comment: quickAddComment.value || 'Lead from customer creation',
                        product_ids: prodIds,
                        stage: quickAddStage.value,
                        expected_closing_date: quickAddExpectedDate.value || null,
                        source: form.source || null,
                    });
                    toast.success('Lead created');
                } else if (quickAddType.value === 'appointment') {
                    if (!quickAddAssignedUserId.value) {
                        error.value = 'Please select who will attend this appointment.';
                        loading.value = false;
                        return;
                    }
                    if (!quickAddAppointmentDate.value || !quickAddAppointmentTime.value) {
                        error.value = 'Please set appointment date and time.';
                        loading.value = false;
                        return;
                    }
                    const apptProdIds = prodIds.length ? prodIds : (products.value.length ? [products.value[0].id] : []);
                    if (!apptProdIds.length) {
                        error.value = 'Please add products first (Products menu) or select a product above.';
                        loading.value = false;
                        return;
                    }
                    const leadRes = await axios.post('/api/leads', {
                        customer_id: customerId,
                        stage: 'follow_up',
                        source: 'appointment',
                        product_ids: apptProdIds,
                        comment: quickAddComment.value || `Appointment ${quickAddAppointmentDate.value} at ${quickAddAppointmentTime.value}`,
                    });
                    const leadId = leadRes.data.id;
                    const activityPayload = {
                        type: 'appointment',
                        description: quickAddComment.value || `Appointment scheduled for ${quickAddAppointmentDate.value} at ${quickAddAppointmentTime.value}`,
                        meta: {
                            appointment_date: quickAddAppointmentDate.value,
                            appointment_time: quickAddAppointmentTime.value,
                        },
                    };
                    if (quickAddAssignedUserId.value) {
                        activityPayload.assigned_user_id = quickAddAssignedUserId.value;
                    }
                    await axios.post(`/api/leads/${leadId}/activity`, activityPayload);
                    toast.success('Appointment scheduled');
                }
            }
        }
        if (customerId && quickAddType.value) {
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
        form.type = route.query.type === 'customer' ? 'customer' : 'prospect';
        const now = new Date();
        now.setHours(now.getHours() + 1);
        quickAddFollowUpAt.value = now.toISOString().slice(0, 16);
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        quickAddAppointmentDate.value = tomorrow.toISOString().slice(0, 10);
    }
});
</script>
