<template>
    <div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">SMS Management</h1>
            <p class="text-sm text-slate-600 mt-1">Filter contacts, choose template or custom message, and send bulk SMS. Settings from Settings → SMS.</p>
            <!-- SMS status from Settings -->
            <div
                v-if="smsStatus"
                class="mt-4 p-4 rounded-xl flex flex-wrap items-center gap-3"
                :class="smsStatus.configured ? 'bg-green-50 border border-green-200' : 'bg-amber-50 border border-amber-200'"
            >
                <span v-if="smsStatus.configured" class="text-green-600 text-lg">✓</span>
                <span v-else class="text-amber-600 text-lg">⚠</span>
                <span class="text-sm" :class="smsStatus.configured ? 'text-green-800' : 'text-amber-800'">
                    {{ smsStatus.message }}
                </span>
                <router-link
                    to="/settings"
                    class="text-sm font-medium underline"
                    :class="smsStatus.configured ? 'text-green-700 hover:text-green-900' : 'text-amber-700 hover:text-amber-900'"
                >
                    Open Settings
                </router-link>
            </div>
        </div>

        <!-- Tabs: Send SMS | Report -->
        <div class="flex gap-2 border-b border-slate-200 overflow-x-auto pb-px scrollbar-thin -mx-1 px-1 sm:mx-0 sm:px-0">
            <button
                type="button"
                @click="activeTab = 'send'"
                class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap shrink-0"
                :class="activeTab === 'send'
                    ? 'bg-white border border-b-0 border-slate-200 text-slate-900'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'"
            >
                Send SMS
            </button>
            <button
                type="button"
                @click="activeTab = 'report'; loadReport()"
                class="px-4 py-2 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap shrink-0"
                :class="activeTab === 'report'
                    ? 'bg-white border border-b-0 border-slate-200 text-slate-900'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'"
            >
                Report
            </button>
        </div>

        <!-- Tab: Send SMS -->
        <template v-if="activeTab === 'send'">
            <!-- Step 1: Audience -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">1. Audience</h2>
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" v-model="audience" value="prospect" class="rounded-full text-slate-900 focus:ring-slate-500" />
                        <span class="text-sm font-medium text-slate-700">Prospects only</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" v-model="audience" value="customer" class="rounded-full text-slate-900 focus:ring-slate-500" />
                        <span class="text-sm font-medium text-slate-700">Customers only</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" v-model="audience" value="both" class="rounded-full text-slate-900 focus:ring-slate-500" />
                        <span class="text-sm font-medium text-slate-700">Both (Prospects & Customers)</span>
                    </label>
                </div>
            </div>

            <!-- Step 2: Product filters -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-2">2. Product filters</h2>
                <p class="text-sm text-slate-500 mb-4">Add rules to narrow who receives the SMS. Leave empty or set "All" to include everyone in the selected audience.</p>
                <div class="space-y-3">
                    <div
                        v-for="(filter, index) in productFilters"
                        :key="index"
                        class="flex flex-wrap items-center gap-3"
                    >
                        <select
                            v-model="filter.product_id"
                            class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 min-w-[180px]"
                        >
                            <option :value="null">Select product</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <select
                            v-model="filter.rule"
                            class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="all">All (no filter)</option>
                            <option value="has">Has product</option>
                            <option value="does_not_have">Does not have product</option>
                        </select>
                        <button
                            v-if="productFilters.length > 1"
                            type="button"
                            @click="removeFilter(index)"
                            class="text-red-600 hover:text-red-800 text-sm"
                        >
                            Remove
                        </button>
                    </div>
                    <button type="button" @click="addFilter" class="text-sm text-blue-600 hover:text-blue-800">
                        + Add product rule
                    </button>
                </div>
                <div class="mt-4 flex flex-wrap gap-3 items-end">
                    <div class="min-w-[200px] flex-1 max-w-md">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Search by customer name (optional)</label>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="e.g. Smith"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <button
                        type="button"
                        @click="applyFilters"
                        :disabled="loadingContacts"
                        class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50 text-sm font-medium"
                    >
                        {{ loadingContacts ? 'Loading...' : 'Apply filters' }}
                    </button>
                </div>
            </div>

            <!-- Step 3: Recipients (with phone) -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">3. Recipients</h2>
                <div v-if="totalContacts === 0 && !hasApplied" class="text-slate-500 text-sm">
                    Click "Apply filters" to see who will receive the SMS (contacts with phone numbers only).
                </div>
                <div v-else-if="totalContacts === 0" class="text-slate-500 text-sm">
                    No contacts match the current filters (or none have a phone number).
                </div>
                <div v-else>
                    <p class="text-sm text-slate-700 mb-3">
                        <strong>{{ totalContacts }}</strong> match filters;
                        <strong class="text-green-700">{{ sendableTotal }}</strong> selected to receive.
                        <span v-if="totalContacts > contacts.length" class="text-slate-500">(page {{ contactsPage }} of {{ contactsLastPage }})</span>
                    </p>
                    <div class="flex flex-wrap gap-2 mb-3 text-sm">
                        <button type="button" class="text-green-700 hover:text-green-900" @click="selectAllRecipientsOnPage">Select all on this page</button>
                        <span class="text-slate-300">|</span>
                        <button type="button" class="text-green-700 hover:text-green-900" @click="deselectAllRecipientsOnPage">Uncheck all on this page</button>
                        <span class="text-slate-300">|</span>
                        <button type="button" class="text-slate-600 hover:text-slate-900" @click="clearRecipientExclusions">Include everyone again</button>
                    </div>
                    <div v-if="contactsLastPage > 1" class="flex items-center gap-2 mb-3 text-sm">
                        <button type="button" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50" :disabled="contactsPage <= 1" @click="goToContactsPage(contactsPage - 1)">Previous</button>
                        <span class="text-slate-600">Page {{ contactsPage }} of {{ contactsLastPage }}</span>
                        <button type="button" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50" :disabled="contactsPage >= contactsLastPage" @click="goToContactsPage(contactsPage + 1)">Next</button>
                    </div>
                    <div class="border border-slate-200 rounded-lg overflow-hidden max-h-64 overflow-y-auto overflow-x-auto">
                        <table class="w-full text-sm min-w-[320px]">
                            <thead class="bg-slate-50 sticky top-0">
                                <tr>
                                    <th class="w-10 px-2 py-2 font-medium text-slate-700 text-center">✓</th>
                                    <th class="text-left px-3 sm:px-4 py-2 font-medium text-slate-700">Name</th>
                                    <th class="text-left px-3 sm:px-4 py-2 font-medium text-slate-700">Phone</th>
                                    <th class="text-left px-3 sm:px-4 py-2 font-medium text-slate-700">Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in contacts" :key="c.id" class="border-t border-slate-100 hover:bg-slate-50">
                                    <td class="px-2 py-2 text-center">
                                        <input
                                            type="checkbox"
                                            class="rounded border-slate-300 text-green-600 focus:ring-green-500"
                                            :checked="recipientIncluded(c.id)"
                                            @change="toggleRecipient(c.id, $event.target.checked)"
                                        />
                                    </td>
                                    <td class="px-3 sm:px-4 py-2">{{ c.name }}</td>
                                    <td class="px-3 sm:px-4 py-2">{{ c.phone }}</td>
                                    <td class="px-3 sm:px-4 py-2 capitalize">{{ c.type }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Step 4: Message (template or custom) -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">4. Message</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Message template (optional)</label>
                        <select
                            v-model="selectedTemplateId"
                            @change="loadPreview"
                            class="w-full max-w-md px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="null">Custom message (enter below)</option>
                            <option v-for="t in messageTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Message text</label>
                        <textarea
                            v-model="customMessage"
                            @input="loadPreview"
                            rows="3"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Hi {{first_name}}, your message here..."
                        ></textarea>
                        <p class="text-xs text-slate-500 mt-1">Use {{customer_name}}, {{first_name}}, {{customer_phone}}, {{company_name}}</p>
                    </div>
                    <div v-if="preview" class="border border-slate-200 rounded-lg p-4 bg-slate-50 text-sm">
                        <strong>Preview:</strong> {{ preview }}
                    </div>
                    <p v-if="totalContacts > 0" class="text-sm text-slate-600">
                        SMS will go to <strong>{{ sendableTotal }}</strong> recipient(s) ({{ totalContacts }} match<span v-if="excludedRecipientIds.length">; {{ excludedRecipientIds.length }} excluded</span>).
                    </p>
                </div>
            </div>

            <!-- Step 5: Send -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">5. Send</h2>
                <button
                    type="button"
                    @click="sendBulk"
                    :disabled="sending || sendableTotal === 0 || !canSend"
                    class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 font-medium"
                >
                    {{ sending ? 'Sending...' : `Send SMS to ${sendableTotal} recipient(s)` }}
                </button>
                <p v-if="sendResult" class="mt-3 text-sm" :class="sendResult.failed ? 'text-amber-600' : 'text-green-600'">
                    {{ sendResult.message }}
                </p>
            </div>
        </template>

        <!-- Tab: Report -->
        <template v-if="activeTab === 'report'">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Sent SMS report</h2>
                <p class="text-sm text-slate-600 mb-4">Who received SMS, when, and which template. Filter by date range.</p>
                <div class="flex flex-wrap gap-3 mb-4 items-end">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
                        <input v-model="reportDateFrom" type="date" class="px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
                        <input v-model="reportDateTo" type="date" class="px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                    </div>
                    <button
                        type="button"
                        @click="loadReport"
                        :disabled="loadingReport"
                        class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 text-sm disabled:opacity-50"
                    >
                        {{ loadingReport ? 'Loading...' : 'Apply' }}
                    </button>
                </div>
                <div v-if="reportSummary" class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-4">
                    <div class="bg-slate-50 rounded-lg p-4">
                        <div class="text-xs text-slate-500 uppercase">Total sent</div>
                        <div class="text-xl font-semibold text-green-600">{{ reportSummary.total_sent }}</div>
                    </div>
                    <div class="bg-slate-50 rounded-lg p-4">
                        <div class="text-xs text-slate-500 uppercase">Total failed</div>
                        <div class="text-xl font-semibold text-red-600">{{ reportSummary.total_failed }}</div>
                    </div>
                </div>
                <div v-if="loadingReport" class="text-sm text-slate-500 py-4">Loading report...</div>
                <div v-else-if="reportData.length === 0" class="text-sm text-slate-500 py-4">No sent SMS in this period.</div>
                <div v-else>
                    <div class="border border-slate-200 rounded-lg overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Recipient</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Phone</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Template</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Status</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Sent at</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Sent by</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in reportData" :key="row.id" class="border-t border-slate-100 hover:bg-slate-50">
                                    <td class="px-4 py-2">{{ row.recipient_name }}</td>
                                    <td class="px-4 py-2">{{ row.recipient_phone }}</td>
                                    <td class="px-4 py-2">{{ row.template_name }}</td>
                                    <td class="px-4 py-2">
                                        <span :class="row.status === 'sent' ? 'text-green-600' : 'text-red-600'">{{ formatCommLogStatus(row.status) }}</span>
                                        <span v-if="row.error_message" class="block text-xs text-slate-500 truncate max-w-[200px]" :title="row.error_message">{{ row.error_message }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-slate-600">{{ formatDate(row.sent_at) }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ row.sent_by_name || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="reportLastPage > 1" class="flex items-center gap-2 mt-4 text-sm">
                        <button type="button" @click="goToReportPage(reportPage - 1)" :disabled="reportPage <= 1" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50">Previous</button>
                        <span class="text-slate-600">Page {{ reportPage }} of {{ reportLastPage }}</span>
                        <button type="button" @click="goToReportPage(reportPage + 1)" :disabled="reportPage >= reportLastPage" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { formatCommLogStatus } from '@/utils/displayFormat';

const activeTab = ref('send');
const audience = ref('both');
const searchQuery = ref('');
const excludedRecipientIds = ref([]);
const productFilters = ref([{ product_id: null, rule: 'all' }]);
const smsStatus = ref(null);
const products = ref([]);
const messageTemplates = ref([]);
const contacts = ref([]);
const totalContacts = ref(0);
const contactsPage = ref(1);
const contactsLastPage = ref(1);
const contactsPerPage = ref(50);
const hasApplied = ref(false);
const loadingContacts = ref(false);
const selectedTemplateId = ref(null);
const customMessage = ref('');
const preview = ref('');
const sending = ref(false);
const sendResult = ref(null);

const reportData = ref([]);
const reportSummary = ref(null);
const reportPage = ref(1);
const reportLastPage = ref(1);
const reportDateFrom = ref('');
const reportDateTo = ref('');
const loadingReport = ref(false);

const canSend = computed(() => selectedTemplateId.value || (customMessage.value && customMessage.value.trim().length > 0));

const sendableTotal = computed(() =>
    Math.max(0, totalContacts.value - new Set(excludedRecipientIds.value).size)
);

function recipientIncluded(id) {
    return !excludedRecipientIds.value.includes(id);
}

function toggleRecipient(id, checked) {
    const s = new Set(excludedRecipientIds.value);
    if (checked) s.delete(id);
    else s.add(id);
    excludedRecipientIds.value = [...s];
}

function selectAllRecipientsOnPage() {
    const s = new Set(excludedRecipientIds.value);
    contacts.value.forEach((c) => s.delete(c.id));
    excludedRecipientIds.value = [...s];
}

function deselectAllRecipientsOnPage() {
    const s = new Set(excludedRecipientIds.value);
    contacts.value.forEach((c) => s.add(c.id));
    excludedRecipientIds.value = [...s];
}

function clearRecipientExclusions() {
    excludedRecipientIds.value = [];
}

function addFilter() {
    productFilters.value.push({ product_id: null, rule: 'all' });
}

function removeFilter(index) {
    productFilters.value.splice(index, 1);
}

function buildPayload() {
    const product_filters = productFilters.value
        .filter(f => f.product_id != null && f.rule !== 'all')
        .map(f => ({ product_id: Number(f.product_id), rule: f.rule }));
    const payload = { audience: audience.value, product_filters };
    const s = (searchQuery.value || '').trim();
    if (s) payload.search = s;
    return payload;
}

function buildSendPayload() {
    const ex = [...new Set(excludedRecipientIds.value)];
    return {
        ...buildPayload(),
        ...(ex.length ? { exclude_customer_ids: ex } : {}),
    };
}

async function loadContactsPage(page = 1) {
    const pageNum = typeof page === 'number' && Number.isInteger(page) ? page : 1;
    loadingContacts.value = true;
    contactsPage.value = pageNum;
    try {
        const payload = { ...buildPayload(), page: pageNum, per_page: contactsPerPage.value };
        const { data } = await axios.post('/api/sms-management/filtered-contacts', payload);
        contacts.value = data.contacts || [];
        totalContacts.value = data.total ?? 0;
        contactsLastPage.value = data.last_page ?? 1;
        hasApplied.value = true;
        if (selectedTemplateId.value) loadPreview();
    } catch (e) {
        console.error(e);
        contacts.value = [];
        totalContacts.value = 0;
        contactsLastPage.value = 1;
        hasApplied.value = true;
    } finally {
        loadingContacts.value = false;
    }
}

async function applyFilters() {
    sendResult.value = null;
    excludedRecipientIds.value = [];
    await loadContactsPage(1);
}

function goToContactsPage(page) {
    if (page < 1 || page > contactsLastPage.value) return;
    loadContactsPage(page);
}

async function loadPreview() {
    if (!selectedTemplateId.value) {
        preview.value = customMessage.value || '';
        return;
    }
    try {
        const { data } = await axios.get(`/api/sms-management/preview-template/${selectedTemplateId.value}`);
        preview.value = data.message || '';
    } catch (e) {
        preview.value = '';
    }
}

async function sendBulk() {
    if (sendableTotal.value === 0 || !canSend.value) return;
    sending.value = true;
    sendResult.value = null;
    try {
        const payload = { ...buildSendPayload() };
        if (selectedTemplateId.value) payload.template_id = selectedTemplateId.value;
        if (customMessage.value?.trim()) payload.message = customMessage.value.trim();
        const { data } = await axios.post('/api/sms-management/send', payload);
        sendResult.value = { message: data.message, failed: (data.failed || 0) > 0 };
        excludedRecipientIds.value = [];
        loadContactsPage(contactsPage.value);
    } catch (e) {
        sendResult.value = { message: e.response?.data?.message || e.message || 'Send failed', failed: true };
    } finally {
        sending.value = false;
    }
}

function formatDate(iso) {
    if (!iso) return '—';
    try {
        const d = new Date(iso);
        return d.toLocaleString();
    } catch {
        return iso;
    }
}

async function loadReport(page = 1) {
    loadingReport.value = true;
    reportPage.value = page;
    try {
        const params = { page, per_page: 20 };
        if (reportDateFrom.value) params.date_from = reportDateFrom.value;
        if (reportDateTo.value) params.date_to = reportDateTo.value;
        const { data } = await axios.get('/api/sms-management/sent-report', { params });
        reportData.value = data.data || [];
        reportSummary.value = data.summary || { total_sent: 0, total_failed: 0 };
        reportLastPage.value = data.last_page ?? 1;
    } catch (e) {
        console.error(e);
        reportData.value = [];
        reportSummary.value = null;
    } finally {
        loadingReport.value = false;
    }
}

function goToReportPage(page) {
    if (page < 1 || page > reportLastPage.value) return;
    loadReport(page);
}

onMounted(async () => {
    try {
        const [prodsRes, smsStatusRes, msgTmplRes] = await Promise.all([
            axios.get('/api/products'),
            axios.get('/api/sms-management/sms-status'),
            axios.get('/api/message-templates-for-sending'),
        ]);
        products.value = prodsRes.data?.data ?? prodsRes.data ?? [];
        smsStatus.value = smsStatusRes.data ?? null;
        messageTemplates.value = msgTmplRes.data ?? [];
    } catch (e) {
        console.error(e);
    }
});
</script>
