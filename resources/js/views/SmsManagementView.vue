<template>
    <ListingPageShell
        title="SMS Management"
        subtitle="Filter contacts, choose a template or write a custom message, and send bulk SMS — the report tab lists every send, newest first."
        :badge="smsBadge"
    >
        <template #actions>
            <BaseButton variant="outline" to="/settings" block-mobile>
                <template #icon><Cog6ToothIcon class="icon-sm" aria-hidden="true" /></template>
                Open Settings
            </BaseButton>
        </template>

        <template #filters>
            <div class="listing-filters-row">
                <div class="tab-list" role="tablist" aria-label="SMS section">
                    <button
                        type="button"
                        role="tab"
                        class="tab inline-flex items-center gap-2"
                        :class="activeTab === 'send' ? 'tab-active' : ''"
                        :aria-selected="activeTab === 'send' ? 'true' : 'false'"
                        @click="activeTab = 'send'"
                    >
                        <PaperAirplaneIcon class="icon-sm" aria-hidden="true" />
                        Send SMS
                    </button>
                    <button
                        type="button"
                        role="tab"
                        class="tab inline-flex items-center gap-2"
                        :class="activeTab === 'report' ? 'tab-active' : ''"
                        :aria-selected="activeTab === 'report' ? 'true' : 'false'"
                        @click="activeTab = 'report'; loadReport()"
                    >
                        <ListBulletIcon class="icon-sm" aria-hidden="true" />
                        Report
                    </button>
                </div>

                <template v-if="activeTab === 'report'">
                    <div>
                        <label class="form-label" for="smsmanagementview-from">From</label>
                        <input id="smsmanagementview-from" v-model="reportDateFrom" type="date" class="form-input w-full sm:w-44" />
                    </div>
                    <div>
                        <label class="form-label" for="smsmanagementview-to">To</label>
                        <input id="smsmanagementview-to" v-model="reportDateTo" type="date" class="form-input w-full sm:w-44" />
                    </div>
                    <BaseButton variant="soft" :loading="loadingReport" @click="loadReport()">
                        <template #icon><FunnelIcon class="icon-sm" aria-hidden="true" /></template>
                        Apply
                    </BaseButton>
                </template>
            </div>
        </template>

        <div class="px-4 sm:px-6 py-5 sm:py-6 space-y-5">
            <!-- SMS status from Settings -->
            <p
                v-if="smsStatus"
                class="callout flex items-start gap-2"
                :class="smsStatus.configured ? 'callout-success' : 'callout-warning'"
                role="status"
                aria-live="polite"
            >
                <CheckCircleIcon v-if="smsStatus.configured" class="icon shrink-0" aria-hidden="true" />
                <ExclamationTriangleIcon v-else class="icon shrink-0" aria-hidden="true" />
                <span>{{ smsStatus.message }}</span>
            </p>

            <!-- Tab: Send SMS -->
            <template v-if="activeTab === 'send'">
                <!-- Step 1: Audience -->
                <section class="card card-body">
                    <h2 class="text-lg font-semibold text-slate-900 mb-4">1. Audience</h2>
                    <div class="flex flex-wrap gap-4">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="audience" value="prospect" class="form-radio" />
                            <span class="text-sm font-medium text-slate-700">Prospects only</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="audience" value="customer" class="form-radio" />
                            <span class="text-sm font-medium text-slate-700">Customers only</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" v-model="audience" value="both" class="form-radio" />
                            <span class="text-sm font-medium text-slate-700">Both (Prospects &amp; Customers)</span>
                        </label>
                    </div>
                </section>

                <!-- Step 2: Product filters -->
                <section class="card card-body">
                    <h2 class="text-lg font-semibold text-slate-900 mb-2">2. Product filters</h2>
                    <p class="text-sm text-slate-500 mb-4">Add rules to narrow who receives the SMS. Leave empty or set "All" to include everyone in the selected audience.</p>
                    <div class="space-y-3">
                        <div
                            v-for="(filter, index) in productFilters"
                            :key="index"
                            class="flex flex-wrap items-center gap-3"
                        >
                            <div>
                                <label class="sr-only" :for="`smsmanagementview-filter-product-${index}`">Product for rule {{ index + 1 }}</label>
                                <select
                                    :id="`smsmanagementview-filter-product-${index}`"
                                    v-model="filter.product_id"
                                    class="form-select min-w-[180px]"
                                >
                                    <option :value="null">Select product</option>
                                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="sr-only" :for="`smsmanagementview-filter-rule-${index}`">Rule {{ index + 1 }} condition</label>
                                <select
                                    :id="`smsmanagementview-filter-rule-${index}`"
                                    v-model="filter.rule"
                                    class="form-select"
                                >
                                    <option value="all">All (no filter)</option>
                                    <option value="has">Has product</option>
                                    <option value="does_not_have">Does not have product</option>
                                </select>
                            </div>
                            <BaseButton
                                v-if="productFilters.length > 1"
                                variant="ghost"
                                size="icon"
                                class="text-danger-700"
                                :label="`Remove product rule ${index + 1}`"
                                @click="removeFilter(index)"
                            >
                                <TrashIcon class="icon" aria-hidden="true" />
                            </BaseButton>
                        </div>
                        <BaseButton variant="ghost" size="sm" @click="addFilter">
                            <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                            Add product rule
                        </BaseButton>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-3 items-end">
                        <div class="min-w-[200px] flex-1 max-w-md">
                            <label class="form-label" for="smsmanagementview-search-by-customer-name-optional">Search by customer name (optional)</label>
                            <input id="smsmanagementview-search-by-customer-name-optional"
                                v-model="searchQuery"
                                type="search"
                                placeholder="e.g. Smith"
                                class="form-input w-full"
                                @keydown.enter.prevent="applyFilters"
                            />
                        </div>
                        <BaseButton variant="soft" :loading="loadingContacts" @click="applyFilters">
                            <template #icon><MagnifyingGlassIcon class="icon-sm" aria-hidden="true" /></template>
                            Apply filters
                        </BaseButton>
                    </div>
                </section>

                <!-- Step 3: Recipients (with phone) -->
                <section class="card card-body">
                    <h2 class="text-lg font-semibold text-slate-900 mb-4">3. Recipients</h2>
                    <div v-if="loadingContacts" class="space-y-2" aria-busy="true">
                        <span class="sr-only">Loading recipients…</span>
                        <div v-for="n in 4" :key="n" class="skeleton-text w-full" aria-hidden="true"></div>
                    </div>
                    <EmptyState
                        v-else-if="totalContacts === 0 && !hasApplied"
                        heading="No recipients selected yet"
                        description="Choose an audience and any product rules above, then select “Apply filters” to see who will receive the SMS. Only contacts with a phone number are included."
                    >
                        <template #icon><UsersIcon class="icon" aria-hidden="true" /></template>
                    </EmptyState>
                    <EmptyState
                        v-else-if="totalContacts === 0"
                        heading="No contacts match these filters"
                        description="Nobody in the selected audience matches the current product rules and search, or none of the matches have a phone number. Try widening the rules."
                    >
                        <template #icon><UsersIcon class="icon" aria-hidden="true" /></template>
                    </EmptyState>
                    <div v-else>
                        <p class="text-sm text-slate-700 mb-3">
                            <strong>{{ totalContacts }}</strong> match filters;
                            <strong class="text-success-700">{{ sendableTotal }}</strong> selected to receive.
                            <span v-if="totalContacts > contacts.length" class="text-slate-500">(page {{ contactsPage }} of {{ contactsLastPage }})</span>
                        </p>
                        <div class="flex flex-wrap gap-2 mb-3">
                            <BaseButton variant="ghost" size="sm" @click="selectAllRecipientsOnPage">Select all on this page</BaseButton>
                            <BaseButton variant="ghost" size="sm" @click="deselectAllRecipientsOnPage">Uncheck all on this page</BaseButton>
                            <BaseButton variant="ghost" size="sm" @click="clearRecipientExclusions">Include everyone again</BaseButton>
                        </div>
                        <div class="border border-slate-200 rounded-card overflow-hidden max-h-64 overflow-y-auto overflow-x-auto">
                            <table class="table min-w-[320px]">
                                <caption class="sr-only">Contacts matching the current filters. Untick a row to exclude that contact from this send.</caption>
                                <thead class="table-thead table-thead-sticky">
                                    <tr>
                                        <th scope="col" class="table-th w-10 text-center">
                                            <span class="sr-only">Include</span>
                                            <CheckIcon class="icon-sm mx-auto" aria-hidden="true" />
                                        </th>
                                        <th scope="col" class="table-th">Name</th>
                                        <th scope="col" class="table-th">Phone</th>
                                        <th scope="col" class="table-th">Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in contacts" :key="c.id" class="table-row">
                                        <td class="table-td text-center">
                                            <input
                                                type="checkbox"
                                                class="form-checkbox"
                                                :aria-label="`Include ${c.name}`"
                                                :checked="recipientIncluded(c.id)"
                                                @change="toggleRecipient(c.id, $event.target.checked)"
                                            />
                                        </td>
                                        <td class="table-td-strong">{{ c.name }}</td>
                                        <td class="table-td">{{ c.phone }}</td>
                                        <td class="table-td capitalize">{{ c.type }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <Pagination
                            v-if="contactsLastPage > 1"
                            :pagination="contactsPagination"
                            result-label="contacts"
                            singular-label="contact"
                            @page-change="goToContactsPage"
                        />
                    </div>
                </section>

                <!-- Step 4: Message (template or custom) -->
                <section class="card card-body">
                    <h2 class="text-lg font-semibold text-slate-900 mb-4">4. Message</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="form-label" for="smsmanagementview-message-template-optional">Message template (optional)</label>
                            <select id="smsmanagementview-message-template-optional"
                                v-model="selectedTemplateId"
                                class="form-select w-full max-w-md"
                                @change="loadPreview"
                            >
                                <option :value="null">Custom message (enter below)</option>
                                <option v-for="t in messageTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="smsmanagementview-message-text">Message text</label>
                            <textarea
                                id="smsmanagementview-message-text"
                                v-model="customMessage"
                                rows="3"
                                class="form-textarea w-full"
                                placeholder="Hi {{first_name}}, your message here..."
                                @input="loadPreview"
                            ></textarea>
                            <p v-pre class="form-hint">Use {{customer_name}}, {{first_name}}, {{customer_phone}}, {{company_name}}</p>
                        </div>
                        <div v-if="preview" class="rounded-card border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                            <strong>Preview:</strong> {{ preview }}
                        </div>
                        <p v-if="totalContacts > 0" class="text-sm text-slate-600">
                            SMS will go to <strong>{{ sendableTotal }}</strong> recipient(s) ({{ totalContacts }} match<span v-if="excludedRecipientIds.length">; {{ excludedRecipientIds.length }} excluded</span>).
                        </p>
                    </div>
                </section>

                <!-- Step 5: Send -->
                <section class="card card-body">
                    <h2 class="text-lg font-semibold text-slate-900 mb-4">5. Send</h2>
                    <BaseButton
                        variant="primary"
                        size="lg"
                        block-mobile
                        :loading="sending"
                        :disabled="sendableTotal === 0 || !canSend"
                        @click="sendBulk"
                    >
                        <template #icon><PaperAirplaneIcon class="icon-sm" aria-hidden="true" /></template>
                        {{ sending ? 'Sending...' : `Send SMS to ${sendableTotal} recipient(s)` }}
                    </BaseButton>
                    <p
                        v-if="sendResult"
                        class="callout mt-3 flex items-start gap-2"
                        :class="sendResult.failed ? 'callout-warning' : 'callout-success'"
                        role="status"
                        aria-live="polite"
                    >
                        <ExclamationTriangleIcon v-if="sendResult.failed" class="icon shrink-0" aria-hidden="true" />
                        <CheckCircleIcon v-else class="icon shrink-0" aria-hidden="true" />
                        <span>{{ sendResult.message }}</span>
                    </p>
                </section>
            </template>

            <!-- Tab: Report -->
            <template v-if="activeTab === 'report'">
                <section class="card card-body">
                    <h2 class="text-lg font-semibold text-slate-900 mb-1">Sent SMS report</h2>
                    <p class="text-sm text-slate-600 mb-4">Who received SMS, when, and which template. Use the date range above to narrow the period.</p>

                    <div v-if="reportSummary" class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                        <StatCard label="Total sent" :value="reportSummary.total_sent" tone="success">
                            <template #icon><CheckCircleIcon class="icon" aria-hidden="true" /></template>
                        </StatCard>
                        <StatCard label="Total failed" :value="reportSummary.total_failed" tone="danger">
                            <template #icon><XCircleIcon class="icon" aria-hidden="true" /></template>
                        </StatCard>
                    </div>

                    <div v-if="loadingReport" class="space-y-2 py-2" aria-busy="true">
                        <span class="sr-only">Loading report…</span>
                        <div v-for="n in 5" :key="n" class="skeleton-text w-full" aria-hidden="true"></div>
                    </div>
                    <EmptyState
                        v-else-if="reportData.length === 0"
                        heading="No sent SMS in this period"
                        description="Nothing was sent between the selected dates. Widen the date range, or switch to the Send SMS tab to start a new bulk send."
                    >
                        <template #icon><DevicePhoneMobileIcon class="icon" aria-hidden="true" /></template>
                    </EmptyState>
                    <div v-else class="table-wrap border border-slate-200 rounded-card">
                        <table class="table">
                            <caption class="sr-only">SMS messages sent in the selected period, newest first.</caption>
                            <thead class="table-thead">
                                <tr>
                                    <th scope="col" class="table-th">Recipient</th>
                                    <th scope="col" class="table-th">Phone</th>
                                    <th scope="col" class="table-th">Template</th>
                                    <th scope="col" class="table-th">Status</th>
                                    <th scope="col" class="table-th">Sent at</th>
                                    <th scope="col" class="table-th">Sent by</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in reportData" :key="row.id" class="table-row">
                                    <td class="table-td-strong">{{ row.recipient_name }}</td>
                                    <td class="table-td">{{ row.recipient_phone }}</td>
                                    <td class="table-td">{{ row.template_name }}</td>
                                    <td class="table-td">
                                        <BaseBadge :status="row.status">{{ formatCommLogStatus(row.status) }}</BaseBadge>
                                        <span v-if="row.error_message" class="block text-xs text-slate-500 truncate max-w-[200px]" :title="row.error_message">{{ row.error_message }}</span>
                                    </td>
                                    <td class="table-td">{{ formatDate(row.sent_at) }}</td>
                                    <td class="table-td">{{ row.sent_by_name || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </template>
        </div>

        <template v-if="activeTab === 'report' && reportLastPage > 1" #pagination>
            <Pagination
                :pagination="reportPagination"
                embedded
                result-label="messages"
                singular-label="message"
                @page-change="goToReportPage"
            />
        </template>
    </ListingPageShell>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { formatCommLogStatus } from '@/utils/displayFormat';
import { formatDateTimeUsDisplay } from '@/utils/dateFormatUi';
import ListingPageShell from '@/components/ListingPageShell.vue';
import Pagination from '@/components/Pagination.vue';
import { BaseBadge, BaseButton, EmptyState, StatCard } from '@/components/base';
import {
    CheckCircleIcon,
    CheckIcon,
    Cog6ToothIcon,
    DevicePhoneMobileIcon,
    ExclamationTriangleIcon,
    FunnelIcon,
    ListBulletIcon,
    MagnifyingGlassIcon,
    PaperAirplaneIcon,
    PlusIcon,
    TrashIcon,
    UsersIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';

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
const reportTotal = ref(0);
const reportDateFrom = ref('');
const reportDateTo = ref('');
const loadingReport = ref(false);

const canSend = computed(() => selectedTemplateId.value || (customMessage.value && customMessage.value.trim().length > 0));

const sendableTotal = computed(() =>
    Math.max(0, totalContacts.value - new Set(excludedRecipientIds.value).size)
);

const smsBadge = computed(() => {
    if (activeTab.value === 'report') {
        return reportSummary.value ? `${reportSummary.value.total_sent} sent` : null;
    }
    return hasApplied.value ? `${sendableTotal.value} selected` : null;
});

const contactsPagination = computed(() => ({
    current_page: contactsPage.value,
    last_page: contactsLastPage.value,
    per_page: contactsPerPage.value,
    total: totalContacts.value,
}));

const reportPagination = computed(() => ({
    current_page: reportPage.value,
    last_page: reportLastPage.value,
    per_page: 20,
    total: reportTotal.value,
}));

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
    return formatDateTimeUsDisplay(iso);
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
        reportTotal.value = data.total ?? reportData.value.length;
    } catch (e) {
        console.error(e);
        reportData.value = [];
        reportSummary.value = null;
        reportTotal.value = 0;
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
