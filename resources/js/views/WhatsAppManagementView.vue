<template>
    <div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">WhatsApp Management</h1>
            <p class="text-sm text-slate-600 mt-1">
                Filter contacts like email campaigns, search by name, pick an <strong>approved</strong> Meta template, and send to everyone matching the filters. Single-chat WhatsApp from a customer record is unchanged.
            </p>
            <div
                v-if="waStatus"
                class="mt-4 p-4 rounded-xl flex flex-wrap items-center gap-3"
                :class="waStatus.configured ? 'bg-green-50 border border-green-200' : 'bg-amber-50 border border-amber-200'"
            >
                <span v-if="waStatus.configured" class="text-green-600 text-lg">✓</span>
                <span v-else class="text-amber-600 text-lg">⚠</span>
                <span class="text-sm" :class="waStatus.configured ? 'text-green-800' : 'text-amber-800'">
                    {{ waStatus.message }}
                </span>
                <router-link
                    to="/settings"
                    class="text-sm font-medium underline"
                    :class="waStatus.configured ? 'text-green-700 hover:text-green-900' : 'text-amber-700 hover:text-amber-900'"
                >
                    Open Settings
                </router-link>
                <router-link
                    to="/whatsapp-templates"
                    class="text-sm font-medium text-emerald-700 hover:text-emerald-900 underline"
                >
                    WhatsApp Templates
                </router-link>
            </div>
        </div>

        <div class="flex gap-2 border-b border-slate-200 overflow-x-auto pb-px scrollbar-thin -mx-1 px-1 sm:mx-0 sm:px-0 flex-nowrap">
            <button
                type="button"
                @click="activeTab = 'send'"
                :class="tabClass('send')"
            >
                Send template
            </button>
            <button
                type="button"
                @click="activeTab = 'report'; loadReport()"
                :class="tabClass('report')"
            >
                Report
            </button>
            <button
                type="button"
                @click="activeTab = 'sendByDate'"
                :class="tabClass('sendByDate')"
            >
                Send by Date
            </button>
        </div>

        <!-- Send -->
        <template v-if="activeTab === 'send'">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">1. Audience</h2>
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="audience" type="radio" value="prospect" class="rounded-full text-slate-900 focus:ring-slate-500" />
                        <span class="text-sm font-medium text-slate-700">Prospects only</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="audience" type="radio" value="customer" class="rounded-full text-slate-900 focus:ring-slate-500" />
                        <span class="text-sm font-medium text-slate-700">Customers only</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input v-model="audience" type="radio" value="both" class="rounded-full text-slate-900 focus:ring-slate-500" />
                        <span class="text-sm font-medium text-slate-700">Both</span>
                    </label>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-2">2. Product filters</h2>
                <p class="text-sm text-slate-500 mb-4">Same rules as Email Management. Leave “All” to include everyone in the audience.</p>
                <div class="space-y-3">
                    <div v-for="(filter, index) in productFilters" :key="index" class="flex flex-wrap items-center gap-3">
                        <select v-model="filter.product_id" class="px-3 py-2 border border-slate-300 rounded-lg text-sm min-w-[180px] focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option :value="null">Select product</option>
                            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                        <select v-model="filter.rule" class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="all">All (no filter)</option>
                            <option value="has">Has product</option>
                            <option value="does_not_have">Does not have product</option>
                        </select>
                        <button v-if="productFilters.length > 1" type="button" class="text-red-600 hover:text-red-800 text-sm" @click="removeFilter(index)">Remove</button>
                    </div>
                    <button type="button" class="text-sm text-emerald-600 hover:text-emerald-800" @click="addFilter">+ Add product rule</button>
                </div>
                <div class="mt-4 flex flex-wrap gap-3 items-end">
                    <div class="min-w-[200px] flex-1 max-w-md">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Search by customer name (optional)</label>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="e.g. Smith"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <button
                        type="button"
                        :disabled="loadingContacts"
                        class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50 text-sm font-medium"
                        @click="applyFilters"
                    >
                        {{ loadingContacts ? 'Loading...' : 'Apply filters' }}
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">3. Recipients</h2>
                <p class="text-xs text-slate-500 mb-3">Only contacts with a WhatsApp number or phone are included. Sending uses the same filtered set (all pages), not only the table below.</p>
                <div v-if="totalContacts === 0 && !hasApplied" class="text-slate-500 text-sm">Click “Apply filters” to load recipients.</div>
                <div v-else-if="totalContacts === 0" class="text-slate-500 text-sm">No contacts match (or none have phone / WhatsApp).</div>
                <div v-else>
                    <p class="text-sm text-slate-700 mb-3">
                        <strong>{{ totalContacts }}</strong> match;
                        <strong class="text-emerald-700">{{ sendableTotal }}</strong> selected to receive.
                        <span v-if="totalContacts > contacts.length" class="text-slate-500">(page {{ contactsPage }} of {{ contactsLastPage }})</span>
                    </p>
                    <div class="flex flex-wrap gap-2 mb-3 text-sm">
                        <button type="button" class="text-emerald-700 hover:text-emerald-900" @click="selectAllRecipientsOnPage">Select all on this page</button>
                        <span class="text-slate-300">|</span>
                        <button type="button" class="text-emerald-700 hover:text-emerald-900" @click="deselectAllRecipientsOnPage">Uncheck all on this page</button>
                        <span class="text-slate-300">|</span>
                        <button type="button" class="text-slate-600 hover:text-slate-900" @click="clearRecipientExclusions">Include everyone again</button>
                    </div>
                    <div class="flex flex-wrap gap-3 mb-4 items-center">
                        <button
                            type="button"
                            :disabled="exporting"
                            class="px-3 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 text-sm disabled:opacity-50"
                            @click="exportCsv"
                        >
                            {{ exporting ? 'Exporting...' : 'Export CSV' }}
                        </button>
                        <div v-if="contactsLastPage > 1" class="flex items-center gap-2 text-sm">
                            <button type="button" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50" :disabled="contactsPage <= 1" @click="goToContactsPage(contactsPage - 1)">Previous</button>
                            <span class="text-slate-600">Page {{ contactsPage }} of {{ contactsLastPage }}</span>
                            <button type="button" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50" :disabled="contactsPage >= contactsLastPage" @click="goToContactsPage(contactsPage + 1)">Next</button>
                        </div>
                    </div>
                    <div class="border border-slate-200 rounded-lg overflow-hidden max-h-64 overflow-y-auto overflow-x-auto">
                        <table class="w-full text-sm min-w-[320px]">
                            <thead class="bg-slate-50 sticky top-0">
                                <tr>
                                    <th class="w-10 px-2 py-2 font-medium text-slate-700 text-center">✓</th>
                                    <th class="text-left px-3 sm:px-4 py-2 font-medium text-slate-700">Name</th>
                                    <th class="text-left px-3 sm:px-4 py-2 font-medium text-slate-700">WhatsApp / Phone</th>
                                    <th class="text-left px-3 sm:px-4 py-2 font-medium text-slate-700">Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in contacts" :key="c.id" class="border-t border-slate-100 hover:bg-slate-50">
                                    <td class="px-2 py-2 text-center">
                                        <input
                                            type="checkbox"
                                            class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                            :checked="recipientIncluded(c.id)"
                                            @change="toggleRecipient(c.id, $event.target.checked)"
                                        />
                                    </td>
                                    <td class="px-3 sm:px-4 py-2">{{ c.name }}</td>
                                    <td class="px-3 sm:px-4 py-2 font-mono text-xs">{{ c.display_phone }}</td>
                                    <td class="px-3 sm:px-4 py-2 capitalize">{{ c.type }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">4. Template & preview</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Approved WhatsApp template</label>
                        <select
                            v-model="selectedTemplateId"
                            class="w-full max-w-md px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            @change="loadPreview"
                        >
                            <option :value="null">Select template</option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }} ({{ t.language }})</option>
                        </select>
                    </div>
                    <div v-if="totalContacts > 0" class="text-sm text-slate-600">
                        Bulk send goes to <strong>{{ sendableTotal }}</strong> recipient(s) ({{ totalContacts }} match<span v-if="excludedRecipientIds.length">; {{ excludedRecipientIds.length }} excluded</span>). Variables are filled per customer (same as single send).
                    </div>
                    <div v-if="preview.body_preview || preview.header_preview" class="border border-slate-200 rounded-lg p-4 bg-slate-50 text-sm space-y-2">
                        <div class="font-medium text-slate-800">{{ preview.template_name || 'Preview' }}</div>
                        <div v-if="preview.header_preview" class="text-slate-600"><span class="text-slate-400">Header:</span> {{ preview.header_preview }}</div>
                        <div class="whitespace-pre-wrap text-slate-900">{{ preview.body_preview || '—' }}</div>
                    </div>
                    <div v-else-if="selectedTemplateId && !loadingPreview" class="text-sm text-slate-500">Could not load preview.</div>
                    <div v-if="loadingPreview" class="text-sm text-slate-500">Loading preview…</div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">5. Send</h2>
                <button
                    type="button"
                    :disabled="sending || sendableTotal === 0 || !selectedTemplateId"
                    class="px-6 py-3 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 font-medium"
                    @click="sendBulk"
                >
                    {{ sending ? 'Sending...' : `Send template to ${sendableTotal} recipient(s)` }}
                </button>
                <p v-if="sendResult" class="mt-3 text-sm" :class="sendResult.failed ? 'text-amber-600' : 'text-green-600'">{{ sendResult.message }}</p>
                <div v-if="sendResult?.failed_list?.length" class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg max-h-48 overflow-y-auto">
                    <p class="text-sm font-medium text-amber-900 mb-2">Failures (showing up to {{ sendResult.failed_list.length }})</p>
                    <ul class="text-xs space-y-1 text-amber-900">
                        <li v-for="(f, i) in sendResult.failed_list" :key="i">{{ f.name }} — {{ f.error }}</li>
                    </ul>
                </div>
            </div>
        </template>

        <!-- Send by date -->
        <template v-if="activeTab === 'sendByDate'">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200 space-y-6">
                <h2 class="text-lg font-semibold text-slate-900">Send by Date</h2>
                <p class="text-sm text-slate-600">Filter by customer/prospect creation date, then send an approved template to all matches with a phone/WhatsApp number.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">From date</label>
                        <input v-model="dateFilterFrom" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">To date</label>
                        <input v-model="dateFilterTo" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Audience</label>
                        <select v-model="dateFilterAudience" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                            <option value="prospect">Prospects only</option>
                            <option value="customer">Customers only</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Search name (optional)</label>
                        <input
                            v-model="dateSearchQuery"
                            type="search"
                            placeholder="e.g. Smith"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
                            @keydown.enter.prevent="applyDateFilter"
                        />
                    </div>
                    <div class="flex items-end">
                        <button
                            type="button"
                            :disabled="loadingDateContacts"
                            class="w-full px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50 text-sm font-medium"
                            @click="applyDateFilter"
                        >
                            {{ loadingDateContacts ? 'Loading...' : 'Apply' }}
                        </button>
                    </div>
                </div>
                <p class="text-xs text-slate-500">Leave dates empty for all records (still scoped by audience).</p>

                <div v-if="hasDateApplied" class="space-y-4">
                    <div class="flex flex-wrap items-center gap-4">
                        <p class="text-sm text-slate-700">
                            <strong>{{ dateTotalContacts }}</strong> match;
                            <strong class="text-emerald-700">{{ dateSendableTotal }}</strong> selected.
                        </p>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Template</label>
                            <select
                                v-model="dateSelectedTemplateId"
                                class="px-3 py-2 border border-slate-300 rounded-lg text-sm min-w-[200px] focus:outline-none focus:ring-2 focus:ring-emerald-500"
                                @change="loadDatePreview"
                            >
                                <option :value="null">Select template</option>
                                <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            :disabled="sendingByDate || dateSendableTotal === 0 || !dateSelectedTemplateId"
                            class="px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50 text-sm font-medium"
                            @click="sendByDate"
                        >
                            {{ sendingByDate ? 'Sending...' : `Send to ${dateSendableTotal}` }}
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2 text-sm">
                        <button type="button" class="text-emerald-700 hover:text-emerald-900" @click="selectAllDateRecipientsOnPage">Select all on this page</button>
                        <span class="text-slate-300">|</span>
                        <button type="button" class="text-emerald-700 hover:text-emerald-900" @click="deselectAllDateRecipientsOnPage">Uncheck all on this page</button>
                        <span class="text-slate-300">|</span>
                        <button type="button" class="text-slate-600 hover:text-slate-900" @click="clearDateRecipientExclusions">Include everyone again</button>
                    </div>
                    <div class="border border-slate-200 rounded-lg overflow-hidden max-h-56 overflow-y-auto">
                        <table class="w-full text-sm min-w-[280px]">
                            <thead class="bg-slate-50 sticky top-0">
                                <tr>
                                    <th class="w-10 px-2 py-2 text-center">✓</th>
                                    <th class="text-left px-3 py-2 font-medium text-slate-700">Name</th>
                                    <th class="text-left px-3 py-2 font-medium text-slate-700">Phone</th>
                                    <th class="text-left px-3 py-2 font-medium text-slate-700">Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in dateContacts" :key="c.id" class="border-t border-slate-100">
                                    <td class="px-2 py-2 text-center">
                                        <input
                                            type="checkbox"
                                            class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                                            :checked="dateRecipientIncluded(c.id)"
                                            @change="toggleDateRecipient(c.id, $event.target.checked)"
                                        />
                                    </td>
                                    <td class="px-3 py-2">{{ c.name }}</td>
                                    <td class="px-3 py-2 font-mono text-xs">{{ c.display_phone }}</td>
                                    <td class="px-3 py-2 capitalize">{{ c.type }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="datePreview.body_preview" class="border border-slate-200 rounded-lg p-3 text-sm bg-slate-50 whitespace-pre-wrap">{{ datePreview.body_preview }}</div>
                    <p v-if="dateSendResult" class="text-sm" :class="dateSendResult.failed ? 'text-amber-600' : 'text-green-600'">{{ dateSendResult.message }}</p>
                </div>
                <div v-else class="text-slate-500 text-sm py-4">Click Apply to load recipients.</div>
            </div>
        </template>

        <!-- Report -->
        <template v-if="activeTab === 'report'">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Sent WhatsApp (bulk) report</h2>
                <p class="text-sm text-slate-600 mb-4">Logged sends from this screen (template messages). Filter by date.</p>
                <div class="flex flex-wrap gap-3 mb-4 items-end">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
                        <input v-model="reportDateFrom" type="date" class="px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
                        <input v-model="reportDateTo" type="date" class="px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                    </div>
                    <button type="button" :disabled="loadingReport" class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 text-sm disabled:opacity-50" @click="loadReport">
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
                <div v-if="loadingReport" class="text-sm text-slate-500 py-4">Loading…</div>
                <div v-else-if="reportData.length === 0" class="text-sm text-slate-500 py-4">No records in this period.</div>
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
                                    <td class="px-4 py-2 font-mono text-xs">{{ row.recipient_phone }}</td>
                                    <td class="px-4 py-2">{{ row.template_name }}</td>
                                    <td class="px-4 py-2">
                                        <span :class="row.status === 'sent' ? 'text-green-600' : 'text-red-600'">{{ formatCommLogStatus(row.status) }}</span>
                                        <span v-if="row.error_message" class="block text-xs text-slate-500 truncate max-w-[220px]" :title="row.error_message">{{ row.error_message }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-slate-600">{{ formatDate(row.sent_at) }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ row.sent_by_name || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="reportLastPage > 1" class="flex items-center gap-2 mt-4 text-sm">
                        <button type="button" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50" :disabled="reportPage <= 1" @click="goToReportPage(reportPage - 1)">Previous</button>
                        <span class="text-slate-600">Page {{ reportPage }} of {{ reportLastPage }}</span>
                        <button type="button" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50" :disabled="reportPage >= reportLastPage" @click="goToReportPage(reportPage + 1)">Next</button>
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
const waStatus = ref(null);
const products = ref([]);
const templates = ref([]);
const contacts = ref([]);
const totalContacts = ref(0);
const contactsPage = ref(1);
const contactsPerPage = ref(50);
const contactsLastPage = ref(1);
const hasApplied = ref(false);
const loadingContacts = ref(false);
const exporting = ref(false);
const selectedTemplateId = ref(null);
const preview = ref({ template_name: '', body_preview: '', header_preview: '' });
const loadingPreview = ref(false);
const sending = ref(false);
const sendResult = ref(null);

const reportData = ref([]);
const reportSummary = ref(null);
const reportPage = ref(1);
const reportLastPage = ref(1);
const reportDateFrom = ref('');
const reportDateTo = ref('');
const loadingReport = ref(false);

const dateFilterFrom = ref('');
const dateFilterTo = ref('');
const dateFilterAudience = ref('both');
const dateSearchQuery = ref('');
const dateExcludedRecipientIds = ref([]);
const dateContacts = ref([]);
const dateTotalContacts = ref(0);
const hasDateApplied = ref(false);
const loadingDateContacts = ref(false);
const dateSelectedTemplateId = ref(null);
const datePreview = ref({ body_preview: '' });
const sendingByDate = ref(false);
const dateSendResult = ref(null);

const sendableTotal = computed(() =>
    Math.max(0, totalContacts.value - new Set(excludedRecipientIds.value).size)
);

const dateSendableTotal = computed(() =>
    Math.max(0, dateTotalContacts.value - new Set(dateExcludedRecipientIds.value).size)
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

function dateRecipientIncluded(id) {
    return !dateExcludedRecipientIds.value.includes(id);
}

function toggleDateRecipient(id, checked) {
    const s = new Set(dateExcludedRecipientIds.value);
    if (checked) s.delete(id);
    else s.add(id);
    dateExcludedRecipientIds.value = [...s];
}

function selectAllDateRecipientsOnPage() {
    const s = new Set(dateExcludedRecipientIds.value);
    dateContacts.value.forEach((c) => s.delete(c.id));
    dateExcludedRecipientIds.value = [...s];
}

function deselectAllDateRecipientsOnPage() {
    const s = new Set(dateExcludedRecipientIds.value);
    dateContacts.value.forEach((c) => s.add(c.id));
    dateExcludedRecipientIds.value = [...s];
}

function clearDateRecipientExclusions() {
    dateExcludedRecipientIds.value = [];
}

function buildSendPayload() {
    const ex = [...new Set(excludedRecipientIds.value)];
    return {
        ...buildPayload(),
        ...(ex.length ? { exclude_customer_ids: ex } : {}),
    };
}

function tabClass(id) {
    return [
        'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap shrink-0',
        activeTab.value === id
            ? 'bg-white border border-b-0 border-slate-200 text-slate-900'
            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50',
    ];
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

async function loadContactsPage(page = 1) {
    const pageNum = typeof page === 'number' && Number.isInteger(page) ? page : 1;
    loadingContacts.value = true;
    contactsPage.value = pageNum;
    try {
        const payload = { ...buildPayload(), page: pageNum, per_page: contactsPerPage.value };
        const { data } = await axios.post('/api/whatsapp-management/filtered-contacts', payload);
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

async function exportCsv() {
    exporting.value = true;
    try {
        const res = await axios.post('/api/whatsapp-management/export', buildPayload(), { responseType: 'blob' });
        const url = URL.createObjectURL(res.data);
        const a = document.createElement('a');
        a.href = url;
        a.download = `whatsapp-contacts-${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        console.error(e);
    } finally {
        exporting.value = false;
    }
}

async function loadPreview() {
    if (!selectedTemplateId.value) {
        preview.value = { template_name: '', body_preview: '', header_preview: '' };
        return;
    }
    loadingPreview.value = true;
    try {
        const { data } = await axios.get(`/api/whatsapp-management/preview-template/${selectedTemplateId.value}`);
        preview.value = {
            template_name: data.template_name || '',
            body_preview: data.body_preview || '',
            header_preview: data.header_preview || '',
        };
    } catch (e) {
        console.error(e);
        preview.value = { template_name: '', body_preview: '', header_preview: '' };
    } finally {
        loadingPreview.value = false;
    }
}

async function sendBulk() {
    if (sendableTotal.value === 0 || !selectedTemplateId.value) return;
    sending.value = true;
    sendResult.value = null;
    try {
        const payload = { whatsapp_template_id: selectedTemplateId.value, ...buildSendPayload() };
        const { data } = await axios.post('/api/whatsapp-management/send', payload);
        sendResult.value = {
            message: data.message,
            failed: (data.failed || 0) > 0,
            failed_list: data.failed_list || [],
        };
        excludedRecipientIds.value = [];
        loadContactsPage(contactsPage.value);
    } catch (e) {
        sendResult.value = {
            message: e.response?.data?.message || e.message || 'Send failed',
            failed: true,
            failed_list: e.response?.data?.failed_list || [],
        };
    } finally {
        sending.value = false;
    }
}

function formatDate(iso) {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString();
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
        const { data } = await axios.get('/api/whatsapp-management/sent-report', { params });
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

async function applyDateFilter() {
    loadingDateContacts.value = true;
    dateSendResult.value = null;
    dateExcludedRecipientIds.value = [];
    try {
        const payload = {
            audience: dateFilterAudience.value,
            product_filters: [],
        };
        if (dateFilterFrom.value) payload.date_from = dateFilterFrom.value;
        if (dateFilterTo.value) payload.date_to = dateFilterTo.value;
        const ds = (dateSearchQuery.value || '').trim();
        if (ds) payload.search = ds;
        const { data } = await axios.post('/api/whatsapp-management/filtered-contacts', { ...payload, page: 1, per_page: 1000 });
        dateContacts.value = data.contacts || [];
        dateTotalContacts.value = data.total ?? 0;
        hasDateApplied.value = true;
        if (dateSelectedTemplateId.value) loadDatePreview();
    } catch (e) {
        console.error(e);
        dateContacts.value = [];
        dateTotalContacts.value = 0;
        hasDateApplied.value = true;
    } finally {
        loadingDateContacts.value = false;
    }
}

async function loadDatePreview() {
    if (!dateSelectedTemplateId.value) return;
    try {
        const { data } = await axios.get(`/api/whatsapp-management/preview-template/${dateSelectedTemplateId.value}`);
        datePreview.value = { body_preview: data.body_preview || '' };
    } catch {
        datePreview.value = { body_preview: '' };
    }
}

async function sendByDate() {
    if (dateSendableTotal.value === 0 || !dateSelectedTemplateId.value) return;
    sendingByDate.value = true;
    dateSendResult.value = null;
    try {
        const payload = {
            whatsapp_template_id: dateSelectedTemplateId.value,
            audience: dateFilterAudience.value,
            product_filters: [],
        };
        if (dateFilterFrom.value) payload.date_from = dateFilterFrom.value;
        if (dateFilterTo.value) payload.date_to = dateFilterTo.value;
        const ds = (dateSearchQuery.value || '').trim();
        if (ds) payload.search = ds;
        const dex = [...new Set(dateExcludedRecipientIds.value)];
        if (dex.length) payload.exclude_customer_ids = dex;
        const { data } = await axios.post('/api/whatsapp-management/send', payload);
        dateSendResult.value = { message: data.message, failed: (data.failed || 0) > 0 };
        await applyDateFilter();
    } catch (e) {
        dateSendResult.value = { message: e.response?.data?.message || e.message || 'Send failed', failed: true };
    } finally {
        sendingByDate.value = false;
    }
}

onMounted(async () => {
    try {
        const [prodsRes, statusRes, tmplRes] = await Promise.all([
            axios.get('/api/products'),
            axios.get('/api/whatsapp-management/whatsapp-status'),
            axios.get('/api/whatsapp-management/approved-templates'),
        ]);
        products.value = prodsRes.data?.data ?? prodsRes.data ?? [];
        waStatus.value = statusRes.data ?? null;
        templates.value = tmplRes.data?.data ?? [];
    } catch (e) {
        console.error(e);
    }
});
</script>
