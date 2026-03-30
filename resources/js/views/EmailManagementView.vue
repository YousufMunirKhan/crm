<template>
    <div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Email Management</h1>
            <p class="text-sm text-slate-600 mt-1">Filter contacts, export list, choose template and send bulk emails. SMTP settings from Settings → Email/SMTP.</p>
            <!-- SMTP status from Settings -->
            <div
                v-if="smtpStatus"
                class="mt-4 p-4 rounded-xl flex flex-wrap items-center gap-3"
                :class="smtpStatus.configured ? 'bg-green-50 border border-green-200' : 'bg-amber-50 border border-amber-200'"
            >
                <span v-if="smtpStatus.configured" class="text-green-600 text-lg">✓</span>
                <span v-else class="text-amber-600 text-lg">⚠</span>
                <span class="text-sm" :class="smtpStatus.configured ? 'text-green-800' : 'text-amber-800'">
                    {{ smtpStatus.message }}
                </span>
                <router-link
                    to="/settings"
                    class="text-sm font-medium underline"
                    :class="smtpStatus.configured ? 'text-green-700 hover:text-green-900' : 'text-amber-700 hover:text-amber-900'"
                >
                    Open Settings
                </router-link>
            </div>
        </div>

        <!-- Tabs: Send Email | Report -->
        <div class="flex gap-2 border-b border-slate-200 overflow-x-auto pb-px scrollbar-thin -mx-1 px-1 sm:mx-0 sm:px-0 flex-nowrap">
            <button
                type="button"
                @click="activeTab = 'send'"
                :class="[
                    'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap shrink-0',
                    activeTab === 'send'
                        ? 'bg-white border border-b-0 border-slate-200 text-slate-900'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                ]"
            >
                Send Email
            </button>
            <button
                type="button"
                @click="activeTab = 'report'; loadReport()"
                :class="[
                    'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap shrink-0',
                    activeTab === 'report'
                        ? 'bg-white border border-b-0 border-slate-200 text-slate-900'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                ]"
            >
                Report
            </button>
            <button
                type="button"
                @click="activeTab = 'upload'; loadSavedLists()"
                :class="[
                    'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap shrink-0',
                    activeTab === 'upload'
                        ? 'bg-white border border-b-0 border-slate-200 text-slate-900'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                ]"
            >
                Upload list
            </button>
            <button
                type="button"
                @click="activeTab = 'sendByDate'"
                :class="[
                    'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap shrink-0',
                    activeTab === 'sendByDate'
                        ? 'bg-white border border-b-0 border-slate-200 text-slate-900'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50'
                ]"
            >
                Send by Date
            </button>
        </div>

        <!-- Tab: Send Email -->
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
                <p class="text-sm text-slate-500 mb-4">Add rules to narrow who receives the email. Leave empty or set "All" to include everyone in the selected audience.</p>
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
                    <button
                        type="button"
                        @click="addFilter"
                        class="text-sm text-blue-600 hover:text-blue-800"
                    >
                        + Add product rule
                    </button>
                </div>
                <div class="mt-4">
                    <button
                        type="button"
                        @click="applyFilters()"
                        :disabled="loadingContacts"
                        class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50 text-sm font-medium"
                    >
                        {{ loadingContacts ? 'Loading...' : 'Apply filters' }}
                    </button>
                </div>
            </div>

            <!-- Result: Contact list (paginated) & export -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">3. Recipients</h2>
                <div v-if="totalContacts === 0 && !hasApplied" class="text-slate-500 text-sm">
                    Click "Apply filters" to see who will receive the email.
                </div>
                <div v-else-if="totalContacts === 0" class="text-slate-500 text-sm">
                    No contacts match the current filters (or none have an email).
                </div>
                <div v-else>
                    <p class="text-sm text-slate-700 mb-3">
                        <strong>{{ totalContacts }}</strong> recipient(s) will receive the email.
                        <span v-if="totalContacts > contacts.length" class="text-slate-500">(showing page {{ contactsPage }} of {{ contactsLastPage }})</span>
                    </p>
                    <div class="flex flex-wrap gap-3 mb-4 items-center">
                        <button
                            type="button"
                            @click="exportCsv"
                            :disabled="exporting"
                            class="px-3 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200 text-sm disabled:opacity-50"
                        >
                            {{ exporting ? 'Exporting...' : 'Export CSV' }}
                        </button>
                        <div v-if="contactsLastPage > 1" class="flex items-center gap-2 text-sm">
                            <button
                                type="button"
                                @click="goToContactsPage(contactsPage - 1)"
                                :disabled="contactsPage <= 1"
                                class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50"
                            >
                                Previous
                            </button>
                            <span class="text-slate-600">Page {{ contactsPage }} of {{ contactsLastPage }}</span>
                            <button
                                type="button"
                                @click="goToContactsPage(contactsPage + 1)"
                                :disabled="contactsPage >= contactsLastPage"
                                class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                    <div class="border border-slate-200 rounded-lg overflow-hidden max-h-64 overflow-y-auto overflow-x-auto">
                        <table class="w-full text-sm min-w-[320px]">
                            <thead class="bg-slate-50 sticky top-0">
                                <tr>
                                    <th class="text-left px-3 sm:px-4 py-2 font-medium text-slate-700">Name</th>
                                    <th class="text-left px-3 sm:px-4 py-2 font-medium text-slate-700">Email</th>
                                    <th class="text-left px-3 sm:px-4 py-2 font-medium text-slate-700">Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in contacts" :key="c.id" class="border-t border-slate-100 hover:bg-slate-50">
                                    <td class="px-3 sm:px-4 py-2">{{ c.name }}</td>
                                    <td class="px-3 sm:px-4 py-2">{{ c.email }}</td>
                                    <td class="px-3 sm:px-4 py-2 capitalize">{{ c.type }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Step 4: Template & preview -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">4. Template & preview</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Choose template</label>
                        <select
                            v-model="selectedTemplateId"
                            @change="loadPreview"
                            class="w-full max-w-md px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="null">Select a template</option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div v-if="totalContacts > 0" class="text-sm text-slate-600">
                        This email will go to <strong>{{ totalContacts }}</strong> recipient(s).
                    </div>
                    <div v-if="preview.subject || preview.content" class="border border-slate-200 rounded-lg overflow-hidden">
                        <div class="px-4 py-2 bg-slate-50 border-b border-slate-200 text-sm font-medium text-slate-700">
                            Preview: {{ preview.template_name || 'Template' }}
                        </div>
                        <div class="p-4">
                            <p class="text-sm text-slate-600 mb-2"><strong>Subject:</strong> {{ preview.subject }}</p>
                            <div
                                class="border border-slate-200 rounded-lg p-4 bg-white text-sm overflow-auto max-h-80"
                                v-html="preview.content"
                            />
                        </div>
                    </div>
                    <div v-else-if="selectedTemplateId && !loadingPreview" class="text-sm text-slate-500">
                        Select a template to see preview.
                    </div>
                    <div v-else-if="loadingPreview" class="text-sm text-slate-500">
                        Loading preview...
                    </div>
                </div>
            </div>

            <!-- Step 5: Send -->
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">5. Send</h2>
                <button
                    type="button"
                    @click="sendBulk"
                    :disabled="sending || totalContacts === 0 || !selectedTemplateId"
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 font-medium"
                >
                    {{ sending ? 'Sending...' : `Send email to ${totalContacts} recipient(s)` }}
                </button>
                <p v-if="sendResult" class="mt-3 text-sm" :class="sendResult.failed ? 'text-amber-600' : 'text-green-600'">
                    {{ sendResult.message }}
                </p>
            </div>
        </template>

        <!-- Tab: Send by Date -->
        <template v-if="activeTab === 'sendByDate'">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200 space-y-6">
                <h2 class="text-lg font-semibold text-slate-900">Send by Date</h2>
                <p class="text-sm text-slate-600">Filter customers and prospects by creation date, choose a template, and send email.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">From date</label>
                        <input v-model="dateFilterFrom" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">To date</label>
                        <input v-model="dateFilterTo" type="date" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Audience</label>
                        <select v-model="dateFilterAudience" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="prospect">Prospects only</option>
                            <option value="customer">Customers only</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button
                            type="button"
                            @click="applyDateFilter"
                            :disabled="loadingDateContacts"
                            class="w-full px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50 text-sm font-medium"
                        >
                            {{ loadingDateContacts ? 'Loading...' : 'Apply' }}
                        </button>
                    </div>
                </div>
                <p class="text-xs text-slate-500">Leave dates empty for all. Filter by when the customer/prospect was created.</p>

                <div v-if="hasDateApplied" class="space-y-4">
                    <div class="flex flex-wrap items-center gap-4">
                        <p class="text-sm text-slate-700">
                            <strong>{{ dateTotalContacts }}</strong> recipient(s) will receive the email.
                        </p>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Choose template</label>
                            <select
                                v-model="dateSelectedTemplateId"
                                @change="loadDatePreview"
                                class="px-3 py-2 border border-slate-300 rounded-lg text-sm min-w-[200px] focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option :value="null">Select template</option>
                                <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            @click="sendByDate"
                            :disabled="sendingByDate || dateTotalContacts === 0 || !dateSelectedTemplateId"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                        >
                            {{ sendingByDate ? 'Sending...' : `Send to ${dateTotalContacts} recipient(s)` }}
                        </button>
                    </div>

                    <div v-if="datePreview.subject || datePreview.content" class="border border-slate-200 rounded-lg overflow-hidden">
                        <div class="px-4 py-2 bg-slate-50 border-b border-slate-200 text-sm font-medium text-slate-700">
                            Preview: {{ datePreview.template_name || 'Template' }}
                        </div>
                        <div class="p-4">
                            <p class="text-sm text-slate-600 mb-2"><strong>Subject:</strong> {{ datePreview.subject }}</p>
                            <div class="border border-slate-200 rounded-lg p-4 bg-white text-sm overflow-auto max-h-60" v-html="datePreview.content" />
                        </div>
                    </div>

                    <div class="border border-slate-200 rounded-lg overflow-hidden max-h-64 overflow-y-auto">
                        <table class="w-full text-sm min-w-[320px]">
                            <thead class="bg-slate-50 sticky top-0">
                                <tr>
                                    <th class="text-left px-3 py-2 font-medium text-slate-700">Name</th>
                                    <th class="text-left px-3 py-2 font-medium text-slate-700">Email</th>
                                    <th class="text-left px-3 py-2 font-medium text-slate-700">Type</th>
                                    <th class="text-left px-3 py-2 font-medium text-slate-700">Template</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in dateContacts" :key="c.id" class="border-t border-slate-100">
                                    <td class="px-3 py-2">{{ c.name }}</td>
                                    <td class="px-3 py-2">{{ c.email }}</td>
                                    <td class="px-3 py-2 capitalize">{{ c.type }}</td>
                                    <td class="px-3 py-2 text-slate-600">{{ dateSelectedTemplateName }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-if="dateSendResult" class="text-sm" :class="dateSendResult.failed ? 'text-amber-600' : 'text-green-600'">{{ dateSendResult.message }}</p>
                    <div v-if="dateSendResult?.failed_list?.length" class="p-4 bg-amber-50 border border-amber-200 rounded-lg">
                        <p class="text-sm font-medium text-amber-800 mb-2">Failed recipients ({{ dateSendResult.failed_list.length }})</p>
                        <div class="max-h-40 overflow-y-auto text-xs space-y-1">
                            <div v-for="(f, i) in dateSendResult.failed_list" :key="i" class="py-1 border-b border-amber-100 last:border-0">
                                <span class="font-medium">{{ f.name || f.email }}</span> {{ f.email }} — <span class="text-red-600" :title="f.error">{{ f.error }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="text-slate-500 text-sm py-4">Click Apply to see recipients matching the date range.</div>
            </div>
        </template>

        <!-- Tab: Report -->
        <template v-if="activeTab === 'report'">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Sent email report</h2>
                <p class="text-sm text-slate-600 mb-4">Who received emails, when, and which template. Filter by date range.</p>
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
                <div v-else-if="reportData.length === 0" class="text-sm text-slate-500 py-4">No sent emails in this period.</div>
                <div v-else>
                    <div class="border border-slate-200 rounded-lg overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Recipient</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Email</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Template</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Status</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Sent at</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Sent by</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in reportData" :key="row.id" class="border-t border-slate-100 hover:bg-slate-50">
                                    <td class="px-4 py-2">{{ row.recipient_name }}</td>
                                    <td class="px-4 py-2">{{ row.recipient_email }}</td>
                                    <td class="px-4 py-2">{{ row.template_name }}</td>
                                    <td class="px-4 py-2">
                                        <span :class="row.status === 'sent' ? 'text-green-600' : 'text-red-600'">{{ row.status }}</span>
                                        <span v-if="row.error_message" class="block text-xs text-slate-500 truncate max-w-[200px]" :title="row.error_message">{{ row.error_message }}</span>
                                    </td>
                                    <td class="px-4 py-2 text-slate-600">{{ formatDate(row.sent_at) }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ row.sent_by_name || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="reportLastPage > 1" class="flex items-center gap-2 mt-4 text-sm">
                        <button
                            type="button"
                            @click="goToReportPage(reportPage - 1)"
                            :disabled="reportPage <= 1"
                            class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50"
                        >
                            Previous
                        </button>
                        <span class="text-slate-600">Page {{ reportPage }} of {{ reportLastPage }}</span>
                        <button
                            type="button"
                            @click="goToReportPage(reportPage + 1)"
                            :disabled="reportPage >= reportLastPage"
                            class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50"
                        >
                            Next
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Tab: Upload list -->
        <template v-if="activeTab === 'upload'">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Upload email list</h2>
                <p class="text-sm text-slate-600 mb-4">Upload a CSV file with columns: <strong>email</strong> (required), <strong>name</strong> (optional). Choose which template will be sent to this list.</p>
                <div class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">List name</label>
                        <input
                            v-model="uploadListName"
                            type="text"
                            placeholder="e.g. Campaign March 2025"
                            class="px-3 py-2 border border-slate-300 rounded-lg text-sm w-64 focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Template (which email will be sent)</label>
                        <select
                            v-model="uploadTemplateId"
                            class="px-3 py-2 border border-slate-300 rounded-lg text-sm min-w-[200px] focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="null">Select template</option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">CSV file</label>
                        <input
                            type="file"
                            accept=".csv,.txt"
                            @change="onUploadFileChange"
                            class="block text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-700"
                        />
                    </div>
                    <button
                        type="button"
                        @click="uploadList"
                        :disabled="uploading || !uploadListName || !uploadFile"
                        class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50 text-sm font-medium"
                    >
                        {{ uploading ? 'Uploading...' : 'Upload & save list' }}
                    </button>
                </div>
                <p v-if="uploadResult" class="mt-3 text-sm text-green-600">{{ uploadResult }}</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Saved email lists</h2>
                <div v-if="loadingLists" class="text-sm text-slate-500 py-4">Loading...</div>
                <div v-else-if="savedLists.length === 0" class="text-sm text-slate-500 py-4">No uploaded lists yet. Upload a CSV above.</div>
                <div v-else>
                    <div class="border border-slate-200 rounded-lg overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Name</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Template</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Date</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Total</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Sent</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Failed</th>
                                    <th class="text-left px-4 py-2 font-medium text-slate-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="list in savedLists" :key="list.id" class="border-t border-slate-100 hover:bg-slate-50">
                                    <td class="px-4 py-2 font-medium">{{ list.name }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ list.template_name || '—' }}</td>
                                    <td class="px-4 py-2 text-slate-600">{{ formatDate(list.created_at) }}</td>
                                    <td class="px-4 py-2">{{ list.total }}</td>
                                    <td class="px-4 py-2 text-green-600">{{ list.sent_count }}</td>
                                    <td class="px-4 py-2 text-red-600">{{ list.failed_count }}</td>
                                    <td class="px-4 py-2">
                                        <button type="button" @click="viewListRecipients(list)" class="text-blue-600 hover:text-blue-800 mr-3">View</button>
                                        <button type="button" @click="selectListForSend(list)" class="text-blue-600 hover:text-blue-800">Send</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="listLastPage > 1" class="flex items-center gap-2 mt-4 text-sm">
                        <button type="button" @click="goToListPage(listPage - 1)" :disabled="listPage <= 1" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50">Previous</button>
                        <span class="text-slate-600">Page {{ listPage }} of {{ listLastPage }}</span>
                        <button type="button" @click="goToListPage(listPage + 1)" :disabled="listPage >= listLastPage" class="px-2 py-1 border border-slate-300 rounded disabled:opacity-50">Next</button>
                    </div>
                </div>
            </div>

            <!-- View recipients modal -->
            <div v-if="viewingList" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" @click.self="viewingList = null">
                <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[80vh] flex flex-col">
                    <div class="p-4 border-b border-slate-200 flex justify-between items-center">
                        <h3 class="font-semibold text-slate-900">{{ viewingList.name }} – Recipients</h3>
                        <button type="button" @click="viewingList = null" class="text-slate-500 hover:text-slate-700">×</button>
                    </div>
                    <div class="p-4 overflow-y-auto overflow-x-auto flex-1 min-h-0">
                        <div v-if="loadingListRecipients" class="text-sm text-slate-500">Loading...</div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-sm min-w-[280px]">
                                <thead class="bg-slate-50"><tr><th class="text-left px-2 py-1">Email</th><th class="text-left px-2 py-1">Name</th><th class="text-left px-2 py-1">Status</th></tr></thead>
                                <tbody>
                                    <tr v-for="r in listRecipients" :key="r.id" class="border-t border-slate-100">
                                        <td class="px-2 py-1">{{ r.email }}</td>
                                        <td class="px-2 py-1">{{ r.name || '—' }}</td>
                                        <td class="px-2 py-1"><span :class="r.status === 'sent' ? 'text-green-600' : r.status === 'failed' ? 'text-red-600' : 'text-slate-500'">{{ r.status }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                            <div v-if="listRecipientsLastPage > 1" class="flex gap-2 mt-3 text-sm">
                                <button type="button" @click="goToListRecipientsPage(listRecipientsPage - 1)" :disabled="listRecipientsPage <= 1" class="px-2 py-1 border rounded disabled:opacity-50">Previous</button>
                                <span>Page {{ listRecipientsPage }} of {{ listRecipientsLastPage }}</span>
                                <button type="button" @click="goToListRecipientsPage(listRecipientsPage + 1)" :disabled="listRecipientsPage >= listRecipientsLastPage" class="px-2 py-1 border rounded disabled:opacity-50">Next</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Send to list: template + send -->
            <div v-if="selectedListForSend" class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-4">Send to list: {{ selectedListForSend.name }}</h2>
                <p class="text-sm text-slate-600 mb-4">This will send the chosen template to <strong>{{ selectedListForSend.total }}</strong> recipient(s).</p>
                <div class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Template</label>
                        <select
                            v-model="sendToListTemplateId"
                            class="px-3 py-2 border border-slate-300 rounded-lg text-sm min-w-[200px] focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="null">Select template</option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <button
                        type="button"
                        @click="sendToList"
                        :disabled="sendingToList || !sendToListTemplateId"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 text-sm font-medium"
                    >
                        {{ sendingToList ? 'Sending...' : `Send to ${selectedListForSend.total} recipients` }}
                    </button>
                    <button type="button" @click="selectedListForSend = null" class="px-4 py-2 text-slate-600 hover:text-slate-800 text-sm">Cancel</button>
                </div>
                <p v-if="sendToListResult" class="mt-3 text-sm" :class="sendToListResult.failed ? 'text-amber-600' : 'text-green-600'">{{ sendToListResult.message }}</p>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const activeTab = ref('send');
const audience = ref('both');
const productFilters = ref([{ product_id: null, rule: 'all' }]);
const smtpStatus = ref(null);
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
const preview = ref({ subject: '', content: '', template_name: '' });
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

const uploadListName = ref('');
const uploadTemplateId = ref(null);
const uploadFile = ref(null);
const uploading = ref(false);
const uploadResult = ref('');
const savedLists = ref([]);
const loadingLists = ref(false);
const listPage = ref(1);
const listLastPage = ref(1);
const viewingList = ref(null);
const listRecipients = ref([]);
const listRecipientsPage = ref(1);
const listRecipientsLastPage = ref(1);
const loadingListRecipients = ref(false);
const selectedListForSend = ref(null);
const sendToListTemplateId = ref(null);
const sendingToList = ref(false);
const sendToListResult = ref(null);

// Send by Date tab
const dateFilterFrom = ref('');
const dateFilterTo = ref('');
const dateFilterAudience = ref('both');
const dateContacts = ref([]);
const dateTotalContacts = ref(0);
const hasDateApplied = ref(false);
const loadingDateContacts = ref(false);
const dateSelectedTemplateId = ref(null);
const datePreview = ref({ subject: '', content: '', template_name: '' });
const sendingByDate = ref(false);
const dateSendResult = ref(null);

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
    return { audience: audience.value, product_filters };
}

async function applyFilters(page = 1) {
    const pageNum = typeof page === 'number' && Number.isInteger(page) ? page : 1;
    loadingContacts.value = true;
    sendResult.value = null;
    contactsPage.value = pageNum;
    try {
        const payload = { ...buildPayload(), page: pageNum, per_page: contactsPerPage.value };
        const { data } = await axios.post('/api/email-management/filtered-contacts', payload);
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

function goToContactsPage(page) {
    if (page < 1 || page > contactsLastPage.value) return;
    applyFilters(page);
}

async function exportCsv() {
    exporting.value = true;
    try {
        const res = await axios.post('/api/email-management/export', buildPayload(), { responseType: 'blob' });
        const url = URL.createObjectURL(res.data);
        const a = document.createElement('a');
        a.href = url;
        a.download = `email-contacts-${new Date().toISOString().slice(0, 10)}.csv`;
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
        preview.value = { subject: '', content: '', template_name: '' };
        return;
    }
    loadingPreview.value = true;
    try {
        const { data } = await axios.get(`/api/email-management/preview-template/${selectedTemplateId.value}`);
        preview.value = {
            subject: data.subject || '',
            content: data.content || '',
            template_name: data.template_name || '',
        };
    } catch (e) {
        console.error(e);
        preview.value = { subject: '', content: '', template_name: '' };
    } finally {
        loadingPreview.value = false;
    }
}

async function sendBulk() {
    if (totalContacts.value === 0 || !selectedTemplateId.value) return;
    sending.value = true;
    sendResult.value = null;
    try {
        const payload = { template_id: selectedTemplateId.value, ...buildPayload() };
        const { data } = await axios.post('/api/email-management/send', payload);
        sendResult.value = { message: data.message, failed: (data.failed || 0) > 0 };
        applyFilters(contactsPage.value);
        if (activeTab.value === 'report') loadReport();
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
        const { data } = await axios.get('/api/email-management/sent-report', { params });
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

function onUploadFileChange(e) {
    uploadFile.value = e.target.files?.[0] ?? null;
    uploadResult.value = '';
}

async function uploadList() {
    if (!uploadListName.value || !uploadFile.value) return;
    uploading.value = true;
    uploadResult.value = '';
    try {
        const form = new FormData();
        form.append('name', uploadListName.value);
        form.append('file', uploadFile.value);
        if (uploadTemplateId.value) form.append('template_id', uploadTemplateId.value);
        const { data } = await axios.post('/api/email-management/lists/upload', form, { headers: { 'Content-Type': 'multipart/form-data' } });
        uploadResult.value = `List "${data.list.name}" saved with ${data.list.recipients_count} recipient(s)` + (data.list.template_name ? ` (template: ${data.list.template_name})` : '') + '.';
        uploadListName.value = '';
        uploadFile.value = null;
        loadSavedLists();
    } catch (e) {
        uploadResult.value = e.response?.data?.message || e.message || 'Upload failed';
    } finally {
        uploading.value = false;
    }
}

async function loadSavedLists(page = 1) {
    loadingLists.value = true;
    listPage.value = page;
    try {
        const { data } = await axios.get('/api/email-management/lists', { params: { page, per_page: 20 } });
        savedLists.value = data.data || [];
        listLastPage.value = data.last_page ?? 1;
    } catch (e) {
        savedLists.value = [];
    } finally {
        loadingLists.value = false;
    }
}

function goToListPage(page) {
    if (page < 1 || page > listLastPage.value) return;
    loadSavedLists(page);
}

async function viewListRecipients(list) {
    viewingList.value = list;
    listRecipientsPage.value = 1;
    loadListRecipients();
}

async function loadListRecipients(page = 1) {
    if (!viewingList.value) return;
    loadingListRecipients.value = true;
    listRecipientsPage.value = page;
    try {
        const { data } = await axios.get(`/api/email-management/lists/${viewingList.value.id}/recipients`, { params: { page, per_page: 50 } });
        listRecipients.value = data.data || [];
        listRecipientsLastPage.value = data.last_page ?? 1;
    } catch (e) {
        listRecipients.value = [];
    } finally {
        loadingListRecipients.value = false;
    }
}

function goToListRecipientsPage(page) {
    if (page < 1 || page > listRecipientsLastPage.value) return;
    loadListRecipients(page);
}

const dateSelectedTemplateName = computed(() => {
    const t = templates.value.find(x => x.id == dateSelectedTemplateId.value);
    return t?.name || datePreview.value?.template_name || '—';
});

async function applyDateFilter() {
    loadingDateContacts.value = true;
    dateSendResult.value = null;
    try {
        const payload = {
            audience: dateFilterAudience.value,
            product_filters: [],
        };
        if (dateFilterFrom.value) payload.date_from = dateFilterFrom.value;
        if (dateFilterTo.value) payload.date_to = dateFilterTo.value;
        const { data } = await axios.post('/api/email-management/filtered-contacts', { ...payload, page: 1, per_page: 1000 });
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
        const { data } = await axios.get(`/api/email-management/preview-template/${dateSelectedTemplateId.value}`);
        datePreview.value = { subject: data.subject || '', content: data.content || '', template_name: data.template_name || '' };
    } catch (e) {
        datePreview.value = { subject: '', content: '', template_name: '' };
    }
}

async function sendByDate() {
    if (dateTotalContacts.value === 0 || !dateSelectedTemplateId.value) return;
    sendingByDate.value = true;
    dateSendResult.value = null;
    try {
        const payload = {
            template_id: dateSelectedTemplateId.value,
            audience: dateFilterAudience.value,
            product_filters: [],
        };
        if (dateFilterFrom.value) payload.date_from = dateFilterFrom.value;
        if (dateFilterTo.value) payload.date_to = dateFilterTo.value;
        const { data } = await axios.post('/api/email-management/send', payload);
        dateSendResult.value = {
            message: data.message,
            failed: (data.failed || 0) > 0,
            failed_list: data.failed_list || [],
        };
        await applyDateFilter();
    } catch (e) {
        dateSendResult.value = { message: e.response?.data?.message || e.message || 'Send failed', failed: true, failed_list: [] };
    } finally {
        sendingByDate.value = false;
    }
}

function selectListForSend(list) {
    selectedListForSend.value = list;
    sendToListTemplateId.value = list.template_id ?? null;
    sendToListResult.value = null;
}

async function sendToList() {
    if (!selectedListForSend.value || !sendToListTemplateId.value) return;
    sendingToList.value = true;
    sendToListResult.value = null;
    try {
        const { data } = await axios.post('/api/email-management/send-to-list', {
            template_id: sendToListTemplateId.value,
            email_list_id: selectedListForSend.value.id,
        });
        sendToListResult.value = { message: data.message, failed: (data.failed || 0) > 0 };
        loadSavedLists(listPage.value);
    } catch (e) {
        sendToListResult.value = { message: e.response?.data?.message || e.message || 'Send failed', failed: true };
    } finally {
        sendingToList.value = false;
    }
}

onMounted(async () => {
    try {
        const [prodsRes, tmplRes, smtpRes] = await Promise.all([
            axios.get('/api/products'),
            axios.get('/api/email-templates-for-sending'),
            axios.get('/api/email-management/smtp-status'),
        ]);
        products.value = prodsRes.data?.data ?? prodsRes.data ?? [];
        templates.value = tmplRes.data ?? [];
        smtpStatus.value = smtpRes.data ?? null;
    } catch (e) {
        console.error(e);
    }
});
</script>
