<template>
    <div class="max-w-6xl mx-auto p-4 sm:p-6 space-y-6">
        <div>
            <h1 class="text-page-title text-slate-900">Email Management</h1>
            <p class="text-sm text-slate-600 mt-1">Filter contacts, export list, choose template and send bulk emails. SMTP settings from Settings → Email/SMTP.</p>
            <!-- SMTP status from Settings -->
            <div
                v-if="smtpStatus"
                class="callout mt-4 flex flex-wrap items-center gap-3"
                :class="smtpStatus.configured ? 'callout-success' : 'callout-warning'"
                role="status"
                aria-live="polite"
            >
                <CheckCircleIcon v-if="smtpStatus.configured" class="icon" aria-hidden="true" />
                <ExclamationTriangleIcon v-else class="icon" aria-hidden="true" />
                <span class="text-sm">{{ smtpStatus.message }}</span>
                <router-link to="/settings" class="link">Open Settings</router-link>
            </div>
        </div>

        <!-- Tabs: Send Email | Report -->
        <div class="tab-list border-b border-slate-200" role="tablist" aria-label="Email management sections">
            <button
                v-for="tab in tabOptions"
                :key="tab.value"
                type="button"
                role="tab"
                :class="['tab', activeTab === tab.value ? 'tab-active' : '']"
                :aria-selected="activeTab === tab.value"
                @click="selectTab(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Tab: Send Email -->
        <template v-if="activeTab === 'send'">
            <!-- Step 1: Audience -->
            <BaseCard title="1. Audience">
                <fieldset class="form-fieldset">
                    <legend class="sr-only">Audience</legend>
                    <div class="flex flex-wrap gap-4">
                        <label class="form-choice">
                            <input v-model="audience" type="radio" value="prospect" class="form-radio" />
                            <span class="text-sm font-medium text-slate-700">Prospects only</span>
                        </label>
                        <label class="form-choice">
                            <input v-model="audience" type="radio" value="customer" class="form-radio" />
                            <span class="text-sm font-medium text-slate-700">Customers only</span>
                        </label>
                        <label class="form-choice">
                            <input v-model="audience" type="radio" value="both" class="form-radio" />
                            <span class="text-sm font-medium text-slate-700">Both (Prospects &amp; Customers)</span>
                        </label>
                    </div>
                </fieldset>
            </BaseCard>

            <!-- Step 2: Product filters -->
            <BaseCard
                title="2. Product filters"
                subtitle='Add rules to narrow who receives the email. Leave empty or set "All" to include everyone in the selected audience.'
            >
                <div class="space-y-3">
                    <div
                        v-for="(filter, index) in productFilters"
                        :key="index"
                        class="flex flex-wrap items-end gap-3"
                    >
                        <div class="min-w-[180px]">
                            <label class="form-label" :for="`email-filter-product-${index}`">Product</label>
                            <select
                                :id="`email-filter-product-${index}`"
                                v-model="filter.product_id"
                                class="form-select"
                            >
                                <option :value="null">Select product</option>
                                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
                        <div class="min-w-[180px]">
                            <label class="form-label" :for="`email-filter-rule-${index}`">Rule</label>
                            <select
                                :id="`email-filter-rule-${index}`"
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
                            size="sm"
                            class="text-danger-700 hover:bg-danger-50 hover:text-danger-800"
                            @click="removeFilter(index)"
                        >
                            <template #icon>
                                <TrashIcon class="icon-sm" aria-hidden="true" />
                            </template>
                            Remove
                        </BaseButton>
                    </div>
                    <BaseButton variant="outline" size="sm" @click="addFilter">
                        <template #icon>
                            <PlusIcon class="icon-sm" aria-hidden="true" />
                        </template>
                        Add product rule
                    </BaseButton>
                </div>
                <div class="mt-4 flex flex-wrap gap-3 items-end">
                    <div class="min-w-[200px] flex-1 max-w-md">
                        <label class="form-label" for="emailmanagementview-search-by-customer-name-optional">Search by customer name (optional)</label>
                        <input id="emailmanagementview-search-by-customer-name-optional"
                            v-model="searchQuery"
                            type="search"
                            placeholder="e.g. Smith"
                            class="form-input"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <BaseButton variant="soft" :loading="loadingContacts" @click="applyFilters">
                        <template #icon>
                            <FunnelIcon class="icon" aria-hidden="true" />
                        </template>
                        {{ loadingContacts ? 'Loading...' : 'Apply filters' }}
                    </BaseButton>
                </div>
            </BaseCard>

            <!-- Result: Contact list (paginated) & export -->
            <BaseCard title="3. Recipients">
                <div v-if="totalContacts === 0 && !hasApplied" class="text-slate-500 text-sm">
                    Click "Apply filters" to see who will receive the email.
                </div>
                <div v-else-if="totalContacts === 0" class="text-slate-500 text-sm">
                    No contacts match the current filters (or none have an email).
                </div>
                <div v-else>
                    <p class="text-sm text-slate-700 mb-3" role="status" aria-live="polite">
                        <strong>{{ totalContacts }}</strong> match filters;
                        <strong class="text-primary-700">{{ sendableTotal }}</strong> selected to receive (uncheck rows to exclude).
                        <span v-if="totalContacts > contacts.length" class="text-slate-500">(showing page {{ contactsPage }} of {{ contactsLastPage }})</span>
                    </p>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <BaseButton variant="ghost" size="sm" @click="selectAllRecipientsOnPage">Select all on this page</BaseButton>
                        <BaseButton variant="ghost" size="sm" @click="deselectAllRecipientsOnPage">Uncheck all on this page</BaseButton>
                        <BaseButton variant="ghost" size="sm" @click="clearRecipientExclusions">Include everyone again</BaseButton>
                    </div>
                    <div class="flex flex-wrap gap-3 mb-4 items-center">
                        <BaseButton variant="outline" size="sm" :loading="exporting" @click="exportCsv">
                            <template #icon>
                                <ArrowDownTrayIcon class="icon-sm" aria-hidden="true" />
                            </template>
                            {{ exporting ? 'Exporting...' : 'Export CSV' }}
                        </BaseButton>
                        <div v-if="contactsLastPage > 1" class="flex items-center gap-2 text-sm">
                            <BaseButton
                                variant="outline"
                                size="sm"
                                :disabled="contactsPage <= 1"
                                @click="goToContactsPage(contactsPage - 1)"
                            >
                                <template #icon>
                                    <ChevronLeftIcon class="icon-sm" aria-hidden="true" />
                                </template>
                                Previous
                            </BaseButton>
                            <span class="text-slate-600">Page {{ contactsPage }} of {{ contactsLastPage }}</span>
                            <BaseButton
                                variant="outline"
                                size="sm"
                                :disabled="contactsPage >= contactsLastPage"
                                @click="goToContactsPage(contactsPage + 1)"
                            >
                                <template #icon>
                                    <ChevronRightIcon class="icon-sm" aria-hidden="true" />
                                </template>
                                Next
                            </BaseButton>
                        </div>
                    </div>
                    <div
                        class="table-wrap border border-slate-200 rounded-card max-h-64 overflow-y-auto"
                        :aria-busy="loadingContacts ? 'true' : 'false'"
                    >
                        <table class="table min-w-[320px]">
                            <caption class="sr-only">Contacts matching the current filters</caption>
                            <thead class="table-thead table-thead-sticky">
                                <tr>
                                    <th scope="col" class="table-th w-10 text-center">
                                        <span class="sr-only">Include in send</span>
                                        <CheckIcon class="icon-sm inline-block" aria-hidden="true" />
                                    </th>
                                    <th scope="col" class="table-th">Name</th>
                                    <th scope="col" class="table-th">Email</th>
                                    <th scope="col" class="table-th">Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in contacts" :key="c.id" class="table-row">
                                    <td class="table-td text-center">
                                        <input
                                            type="checkbox"
                                            class="form-checkbox"
                                            :checked="recipientIncluded(c.id)"
                                            :aria-label="`Include ${c.name || c.email} in this send`"
                                            @change="toggleRecipient(c.id, $event.target.checked)"
                                        />
                                    </td>
                                    <td class="table-td-strong">{{ c.name }}</td>
                                    <td class="table-td">{{ c.email }}</td>
                                    <td class="table-td capitalize">{{ c.type }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </BaseCard>

            <!-- Step 4: Template & preview -->
            <BaseCard title="4. Template &amp; preview">
                <div class="space-y-4">
                    <div class="max-w-md">
                        <label class="form-label" for="emailmanagementview-choose-template">Choose template</label>
                        <select id="emailmanagementview-choose-template"
                            v-model="selectedTemplateId"
                            class="form-select"
                            @change="loadPreview"
                        >
                            <option :value="null">Select a template</option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div v-if="totalContacts > 0" class="text-sm text-slate-600">
                        This email will go to <strong>{{ sendableTotal }}</strong> recipient(s) ({{ totalContacts }} match filters<span v-if="excludedRecipientIds.length">; {{ excludedRecipientIds.length }} excluded</span>).
                    </div>
                    <div v-if="preview.subject || preview.content" class="border border-slate-200 rounded-card overflow-hidden">
                        <div class="px-4 py-2 bg-slate-50 border-b border-slate-200 text-sm font-medium text-slate-700">
                            Preview: {{ preview.template_name || 'Template' }}
                        </div>
                        <div class="p-4">
                            <p class="text-sm text-slate-600 mb-2"><strong>Subject:</strong> {{ preview.subject }}</p>
                            <p class="text-xs text-slate-500 mb-2">Visual preview (full HTML document loads in frame — same as recipients see).</p>
                            <iframe
                                v-if="preview.content"
                                class="w-full min-h-[520px] border border-slate-200 rounded-card bg-white"
                                title="Email preview"
                                sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                                :srcdoc="preview.content"
                            />
                        </div>
                    </div>
                    <div v-else-if="selectedTemplateId && !loadingPreview" class="text-sm text-slate-500">
                        Select a template to see preview.
                    </div>
                    <div v-else-if="loadingPreview" class="text-sm text-slate-500" role="status" aria-live="polite">
                        Loading preview...
                    </div>
                </div>
            </BaseCard>

            <!-- Step 5: Send -->
            <BaseCard title="5. Send">
                <BaseButton
                    variant="primary"
                    size="lg"
                    block-mobile
                    :loading="sending"
                    :disabled="sendableTotal === 0 || !selectedTemplateId"
                    @click="sendBulk"
                >
                    <template #icon>
                        <PaperAirplaneIcon class="icon" aria-hidden="true" />
                    </template>
                    {{ sending ? 'Sending...' : `Send email to ${sendableTotal} recipient(s)` }}
                </BaseButton>
                <p
                    v-if="sendResult"
                    class="callout mt-3"
                    :class="sendResult.failed ? 'callout-warning' : 'callout-success'"
                    :role="sendResult.failed ? 'alert' : 'status'"
                    aria-live="polite"
                >
                    {{ sendResult.message }}
                </p>
            </BaseCard>
        </template>

        <!-- Tab: Send by Date -->
        <template v-if="activeTab === 'sendByDate'">
            <BaseCard
                title="Send by Date"
                subtitle="Filter customers and prospects by creation date, choose a template, and send email."
            >
                <div class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        <div>
                            <label class="form-label" for="emailmanagementview-from-date">From date</label>
                            <input id="emailmanagementview-from-date" v-model="dateFilterFrom" type="date" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label" for="emailmanagementview-to-date">To date</label>
                            <input id="emailmanagementview-to-date" v-model="dateFilterTo" type="date" class="form-input" />
                        </div>
                        <div>
                            <label class="form-label" for="emailmanagementview-audience">Audience</label>
                            <select id="emailmanagementview-audience" v-model="dateFilterAudience" class="form-select">
                                <option value="prospect">Prospects only</option>
                                <option value="customer">Customers only</option>
                                <option value="both">Both</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="emailmanagementview-search-name-optional">Search name (optional)</label>
                            <input id="emailmanagementview-search-name-optional"
                                v-model="dateSearchQuery"
                                type="search"
                                placeholder="e.g. Smith"
                                class="form-input"
                                @keydown.enter.prevent="applyDateFilter"
                            />
                        </div>
                        <div class="flex items-end">
                            <BaseButton
                                variant="soft"
                                class="w-full"
                                :loading="loadingDateContacts"
                                @click="applyDateFilter"
                            >
                                <template #icon>
                                    <FunnelIcon class="icon" aria-hidden="true" />
                                </template>
                                {{ loadingDateContacts ? 'Loading...' : 'Apply' }}
                            </BaseButton>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">Leave dates empty for all. Filter by when the customer/prospect was created.</p>

                    <div v-if="hasDateApplied" class="space-y-4">
                        <div class="flex flex-wrap items-end gap-4">
                            <p class="text-sm text-slate-700" role="status" aria-live="polite">
                                <strong>{{ dateTotalContacts }}</strong> match;
                                <strong class="text-primary-700">{{ dateSendableTotal }}</strong> selected to send.
                            </p>
                            <div class="min-w-[200px]">
                                <label class="form-label" for="emailmanagementview-choose-template-2">Choose template</label>
                                <select id="emailmanagementview-choose-template-2"
                                    v-model="dateSelectedTemplateId"
                                    class="form-select"
                                    @change="loadDatePreview"
                                >
                                    <option :value="null">Select template</option>
                                    <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <BaseButton
                                variant="soft"
                                :loading="sendingByDate"
                                :disabled="dateSendableTotal === 0 || !dateSelectedTemplateId"
                                @click="sendByDate"
                            >
                                <template #icon>
                                    <PaperAirplaneIcon class="icon" aria-hidden="true" />
                                </template>
                                {{ sendingByDate ? 'Sending...' : `Send to ${dateSendableTotal} recipient(s)` }}
                            </BaseButton>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <BaseButton variant="ghost" size="sm" @click="selectAllDateRecipientsOnPage">Select all on this page</BaseButton>
                            <BaseButton variant="ghost" size="sm" @click="deselectAllDateRecipientsOnPage">Uncheck all on this page</BaseButton>
                            <BaseButton variant="ghost" size="sm" @click="clearDateRecipientExclusions">Include everyone again</BaseButton>
                        </div>

                        <div v-if="datePreview.subject || datePreview.content" class="border border-slate-200 rounded-card overflow-hidden">
                            <div class="px-4 py-2 bg-slate-50 border-b border-slate-200 text-sm font-medium text-slate-700">
                                Preview: {{ datePreview.template_name || 'Template' }}
                            </div>
                            <div class="p-4">
                                <p class="text-sm text-slate-600 mb-2"><strong>Subject:</strong> {{ datePreview.subject }}</p>
                                <iframe
                                    v-if="datePreview.content"
                                    class="w-full min-h-[480px] border border-slate-200 rounded-card bg-white"
                                    title="Email preview"
                                    sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                                    :srcdoc="datePreview.content"
                                />
                            </div>
                        </div>

                        <div
                            class="table-wrap border border-slate-200 rounded-card max-h-64 overflow-y-auto"
                            :aria-busy="loadingDateContacts ? 'true' : 'false'"
                        >
                            <table class="table min-w-[320px]">
                                <caption class="sr-only">Recipients matching the selected date range</caption>
                                <thead class="table-thead table-thead-sticky">
                                    <tr>
                                        <th scope="col" class="table-th w-10 text-center">
                                            <span class="sr-only">Include in send</span>
                                            <CheckIcon class="icon-sm inline-block" aria-hidden="true" />
                                        </th>
                                        <th scope="col" class="table-th">Name</th>
                                        <th scope="col" class="table-th">Email</th>
                                        <th scope="col" class="table-th">Type</th>
                                        <th scope="col" class="table-th">Template</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in dateContacts" :key="c.id" class="table-row">
                                        <td class="table-td text-center">
                                            <input
                                                type="checkbox"
                                                class="form-checkbox"
                                                :checked="dateRecipientIncluded(c.id)"
                                                :aria-label="`Include ${c.name || c.email} in this send`"
                                                @change="toggleDateRecipient(c.id, $event.target.checked)"
                                            />
                                        </td>
                                        <td class="table-td-strong">{{ c.name }}</td>
                                        <td class="table-td">{{ c.email }}</td>
                                        <td class="table-td capitalize">{{ c.type }}</td>
                                        <td class="table-td">{{ dateSelectedTemplateName }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p
                            v-if="dateSendResult"
                            class="callout"
                            :class="dateSendResult.failed ? 'callout-warning' : 'callout-success'"
                            :role="dateSendResult.failed ? 'alert' : 'status'"
                            aria-live="polite"
                        >
                            {{ dateSendResult.message }}
                        </p>
                        <div v-if="dateSendResult?.failed_list?.length" class="callout callout-warning">
                            <p class="text-sm font-medium mb-2">Failed recipients ({{ dateSendResult.failed_list.length }})</p>
                            <div class="max-h-40 overflow-y-auto text-xs space-y-1">
                                <div v-for="(f, i) in dateSendResult.failed_list" :key="i" class="py-1 border-b border-warning-200 last:border-0">
                                    <span class="font-medium">{{ f.name || f.email }}</span> {{ f.email }} — <span class="text-danger-800" :title="f.error">{{ f.error }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-slate-500 text-sm py-4">Click Apply to see recipients matching the date range.</div>
                </div>
            </BaseCard>
        </template>

        <!-- Tab: Report -->
        <template v-if="activeTab === 'report'">
            <BaseCard
                title="Sent email report"
                subtitle="Filter by date range. Opens use a small tracking image when the inbox loads images (not 100% accurate). Bounces are classified from the mail server error when possible; do not resend to bounced addresses."
            >
                <div class="tab-list border-b border-slate-200 pb-3 mb-4">
                    <button
                        v-for="opt in reportScopeOptions"
                        :key="opt.value"
                        type="button"
                        :class="['tab', reportScope === opt.value ? 'tab-active' : 'bg-slate-100']"
                        :aria-pressed="reportScope === opt.value"
                        @click="setReportScope(opt.value)"
                    >
                        {{ opt.label }}
                    </button>
                </div>
                <div class="flex flex-wrap gap-3 mb-4 items-end">
                    <div>
                        <label class="form-label" for="emailmanagementview-from">From</label>
                        <input id="emailmanagementview-from" v-model="reportDateFrom" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label" for="emailmanagementview-to">To</label>
                        <input id="emailmanagementview-to" v-model="reportDateTo" type="date" class="form-input" />
                    </div>
                    <BaseButton variant="soft" :loading="loadingReport" @click="loadReport">
                        <template #icon>
                            <FunnelIcon class="icon" aria-hidden="true" />
                        </template>
                        {{ loadingReport ? 'Loading...' : 'Apply' }}
                    </BaseButton>
                    <BaseButton
                        v-if="reportScope === 'opened'"
                        variant="outline"
                        :loading="exportingOpenedReport"
                        :disabled="reportSummary && (reportSummary.total_opened ?? 0) === 0"
                        @click="exportOpenedEmailsCsv"
                    >
                        <template #icon>
                            <ArrowDownTrayIcon class="icon" aria-hidden="true" />
                        </template>
                        {{ exportingOpenedReport ? 'Exporting…' : 'Export CSV' }}
                    </BaseButton>
                </div>
                <div v-if="reportScope === 'opened'" class="callout callout-info mb-4 text-xs">
                    Lists delivered emails where the tracking pixel loaded at least once (same date filter as above). Export downloads every matching row, not only this page.
                </div>
                <div
                    v-if="reportSummary && reportScope === 'retry_queue' && (reportSummary.total_failed_retryable ?? 0) > 0"
                    class="callout callout-warning mb-4 flex flex-wrap items-center gap-3"
                >
                    <BaseButton variant="soft" :loading="queueingRetryAll" @click="queueAllRetries">
                        <template #icon>
                            <ArrowPathIcon class="icon" aria-hidden="true" />
                        </template>
                        {{ queueingRetryAll ? 'Queuing…' : `Queue all retries (${reportSummary.total_failed_retryable})` }}
                    </BaseButton>
                    <p class="text-xs max-w-xl">
                        Queues every failed send in this filter (not only this page), up to
                        {{ reportRetryChunkSize }} per background job, staggered so SMTP is not hammered. Use
                        <code class="kbd">php artisan queue:work</code>
                        when <code class="kbd">QUEUE_CONNECTION</code> is not <code class="kbd">sync</code>.
                    </p>
                </div>
                <div v-if="reportSummary" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
                    <StatCard label="Delivered" :value="reportSummary.total_sent ?? 0" tone="success">
                        <template #icon><CheckCircleIcon class="icon" aria-hidden="true" /></template>
                    </StatCard>
                    <StatCard label="Opened" :value="reportSummary.total_opened ?? 0" tone="primary">
                        <template #icon><EnvelopeIcon class="icon" aria-hidden="true" /></template>
                    </StatCard>
                    <StatCard label="Retry queue" :value="reportSummary.total_failed_retryable ?? 0" tone="warning">
                        <template #icon><ArrowPathIcon class="icon" aria-hidden="true" /></template>
                    </StatCard>
                    <StatCard label="Bounced" :value="reportSummary.total_bounced ?? 0" tone="warning">
                        <template #icon><ExclamationTriangleIcon class="icon" aria-hidden="true" /></template>
                    </StatCard>
                    <StatCard label="Skipped (data)" :value="reportSummary.total_failed_validation ?? 0">
                        <template #icon><InformationCircleIcon class="icon" aria-hidden="true" /></template>
                    </StatCard>
                    <StatCard label="All issues" :value="reportSummary.total_failed ?? 0" tone="danger">
                        <template #icon><XCircleIcon class="icon" aria-hidden="true" /></template>
                    </StatCard>
                </div>
                <div v-if="loadingReport" class="text-sm text-slate-500 py-4" role="status" aria-live="polite">Loading report...</div>
                <div v-else-if="reportData.length === 0" class="text-sm text-slate-500 py-4">{{ reportEmptyMessage }}</div>
                <div v-else>
                    <div class="table-wrap border border-slate-200 rounded-card" :aria-busy="loadingReport ? 'true' : 'false'">
                        <table class="table">
                            <caption class="sr-only">Sent email activity</caption>
                            <thead class="table-thead">
                                <tr>
                                    <th scope="col" class="table-th">Recipient</th>
                                    <th scope="col" class="table-th">Email</th>
                                    <th scope="col" class="table-th">Template</th>
                                    <th scope="col" class="table-th">Status</th>
                                    <th scope="col" class="table-th">{{ reportScope === 'opened' ? 'First opened' : 'Opened' }}</th>
                                    <th scope="col" class="table-th">Sent at</th>
                                    <th scope="col" class="table-th">Sent by</th>
                                    <th scope="col" class="table-th w-28">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in reportData" :key="row.id" class="table-row">
                                    <td class="table-td-strong">{{ row.recipient_name }}</td>
                                    <td class="table-td">{{ row.recipient_email }}</td>
                                    <td class="table-td">{{ row.template_name }}</td>
                                    <td class="table-td">
                                        <BaseBadge :tone="reportStatusTone(row.status)">
                                            {{ formatCommLogStatus(row.status) }}
                                        </BaseBadge>
                                        <span v-if="row.error_message" class="block text-xs text-slate-500 truncate max-w-[220px]" :title="row.error_message">{{ row.error_message }}</span>
                                    </td>
                                    <td class="table-td">
                                        <template v-if="row.status === 'sent'">
                                            <span v-if="row.seen" class="text-primary-700 font-medium" :title="row.opened_at ? 'First opened: ' + formatDate(row.opened_at) : ''">
                                                {{ reportScope === 'opened' && row.opened_at ? formatDate(row.opened_at) : 'Opened' }}
                                            </span>
                                            <span v-else class="text-slate-500">Not opened</span>
                                            <span v-if="row.open_count > 1" class="block text-xs text-slate-500">({{ row.open_count }} loads)</span>
                                        </template>
                                        <span v-else class="text-slate-500">—</span>
                                    </td>
                                    <td class="table-td">{{ formatDate(row.sent_at) }}</td>
                                    <td class="table-td">{{ row.sent_by_name || '—' }}</td>
                                    <td class="table-td-actions">
                                        <BaseButton
                                            v-if="row.can_resend"
                                            variant="ghost"
                                            size="sm"
                                            :loading="resendingId === row.id"
                                            @click="resendSentEmail(row)"
                                        >
                                            <template #icon>
                                                <PaperAirplaneIcon class="icon-sm" aria-hidden="true" />
                                            </template>
                                            {{ resendingId === row.id ? 'Sending…' : 'Resend' }}
                                        </BaseButton>
                                        <span v-else class="text-slate-500 text-xs">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="reportLastPage > 1" class="flex items-center gap-2 mt-4 text-sm">
                        <BaseButton
                            variant="outline"
                            size="sm"
                            :disabled="reportPage <= 1"
                            @click="goToReportPage(reportPage - 1)"
                        >
                            <template #icon>
                                <ChevronLeftIcon class="icon-sm" aria-hidden="true" />
                            </template>
                            Previous
                        </BaseButton>
                        <span class="text-slate-600">Page {{ reportPage }} of {{ reportLastPage }}</span>
                        <BaseButton
                            variant="outline"
                            size="sm"
                            :disabled="reportPage >= reportLastPage"
                            @click="goToReportPage(reportPage + 1)"
                        >
                            <template #icon>
                                <ChevronRightIcon class="icon-sm" aria-hidden="true" />
                            </template>
                            Next
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>
        </template>

        <!-- Tab: Upload list -->
        <template v-if="activeTab === 'upload'">
            <BaseCard title="Upload email list">
                <p class="text-sm text-slate-600 mb-4">Upload a CSV file with columns: <strong>email</strong> (required), <strong>name</strong> (optional). Choose which template will be sent to this list.</p>
                <div class="flex flex-wrap gap-4 items-end">
                    <div class="w-64">
                        <label class="form-label" for="emailmanagementview-list-name">List name</label>
                        <input id="emailmanagementview-list-name"
                            v-model="uploadListName"
                            type="text"
                            placeholder="e.g. Campaign March 2025"
                            class="form-input"
                        />
                    </div>
                    <div class="min-w-[200px]">
                        <label class="form-label" for="emailmanagementview-template-which-email-will-be-sent">Template (which email will be sent)</label>
                        <select id="emailmanagementview-template-which-email-will-be-sent"
                            v-model="uploadTemplateId"
                            class="form-select"
                        >
                            <option :value="null">Select template</option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="emailmanagementview-csv-file">CSV file</label>
                        <input
                            id="emailmanagementview-csv-file"
                            type="file"
                            accept=".csv,.txt"
                            class="block text-sm text-slate-600 file:mr-3 file:py-2 file:px-4 file:rounded-control file:border-0 file:bg-slate-100 file:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 rounded-control"
                            @change="onUploadFileChange"
                        />
                    </div>
                    <BaseButton
                        variant="soft"
                        :loading="uploading"
                        :disabled="!uploadListName || !uploadFile"
                        @click="uploadList"
                    >
                        <template #icon>
                            <ArrowUpTrayIcon class="icon" aria-hidden="true" />
                        </template>
                        {{ uploading ? 'Uploading...' : 'Upload & save list' }}
                    </BaseButton>
                </div>
                <p v-if="uploadResult" class="callout callout-success mt-3" role="status" aria-live="polite">{{ uploadResult }}</p>
            </BaseCard>

            <BaseCard title="Saved email lists">
                <div v-if="loadingLists" class="text-sm text-slate-500 py-4" role="status" aria-live="polite">Loading...</div>
                <EmptyState
                    v-else-if="savedLists.length === 0"
                    heading="No uploaded lists yet"
                    description="Upload a CSV above to create your first list."
                >
                    <template #icon>
                        <ListBulletIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>
                <div v-else>
                    <div class="table-wrap border border-slate-200 rounded-card" :aria-busy="loadingLists ? 'true' : 'false'">
                        <table class="table">
                            <caption class="sr-only">Saved email lists</caption>
                            <thead class="table-thead">
                                <tr>
                                    <th scope="col" class="table-th">Name</th>
                                    <th scope="col" class="table-th">Template</th>
                                    <th scope="col" class="table-th">Date</th>
                                    <th scope="col" class="table-th-num">Total</th>
                                    <th scope="col" class="table-th-num">Sent</th>
                                    <th scope="col" class="table-th-num">Failed</th>
                                    <th scope="col" class="table-th">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="list in savedLists" :key="list.id" class="table-row">
                                    <td class="table-td-strong">{{ list.name }}</td>
                                    <td class="table-td">{{ list.template_name || '—' }}</td>
                                    <td class="table-td">{{ formatDate(list.created_at) }}</td>
                                    <td class="table-td-num">{{ list.total }}</td>
                                    <td class="table-td-num text-success-700 font-medium">{{ list.sent_count }}</td>
                                    <td class="table-td-num text-danger-700 font-medium">{{ list.failed_count }}</td>
                                    <td class="table-td-actions">
                                        <BaseButton variant="ghost" size="sm" class="mr-1" @click="viewListRecipients(list)">
                                            <template #icon>
                                                <EyeIcon class="icon-sm" aria-hidden="true" />
                                            </template>
                                            View
                                        </BaseButton>
                                        <BaseButton variant="ghost" size="sm" @click="selectListForSend(list)">
                                            <template #icon>
                                                <PaperAirplaneIcon class="icon-sm" aria-hidden="true" />
                                            </template>
                                            Send
                                        </BaseButton>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="listLastPage > 1" class="flex items-center gap-2 mt-4 text-sm">
                        <BaseButton variant="outline" size="sm" :disabled="listPage <= 1" @click="goToListPage(listPage - 1)">
                            <template #icon>
                                <ChevronLeftIcon class="icon-sm" aria-hidden="true" />
                            </template>
                            Previous
                        </BaseButton>
                        <span class="text-slate-600">Page {{ listPage }} of {{ listLastPage }}</span>
                        <BaseButton variant="outline" size="sm" :disabled="listPage >= listLastPage" @click="goToListPage(listPage + 1)">
                            <template #icon>
                                <ChevronRightIcon class="icon-sm" aria-hidden="true" />
                            </template>
                            Next
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>

            <!-- View recipients modal -->
            <BaseModal
                :model-value="!!viewingList"
                :title="viewingList ? `${viewingList.name} – Recipients` : ''"
                size="lg"
                @close="viewingList = null"
            >
                <div v-if="loadingListRecipients" class="text-sm text-slate-500" role="status" aria-live="polite">Loading...</div>
                <div v-else>
                    <div class="table-wrap border border-slate-200 rounded-card max-h-[50vh] overflow-y-auto">
                        <table class="table min-w-[280px]">
                            <caption class="sr-only">Recipients in this list</caption>
                            <thead class="table-thead table-thead-sticky">
                                <tr>
                                    <th scope="col" class="table-th">Email</th>
                                    <th scope="col" class="table-th">Name</th>
                                    <th scope="col" class="table-th">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="r in listRecipients" :key="r.id" class="table-row">
                                    <td class="table-td">{{ r.email }}</td>
                                    <td class="table-td">{{ r.name || '—' }}</td>
                                    <td class="table-td">
                                        <BaseBadge :tone="listRecipientTone(r.status)">{{ formatCommLogStatus(r.status) }}</BaseBadge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-if="listRecipientsLastPage > 1" class="flex items-center gap-2 mt-3 text-sm">
                        <BaseButton
                            variant="outline"
                            size="sm"
                            :disabled="listRecipientsPage <= 1"
                            @click="goToListRecipientsPage(listRecipientsPage - 1)"
                        >
                            <template #icon>
                                <ChevronLeftIcon class="icon-sm" aria-hidden="true" />
                            </template>
                            Previous
                        </BaseButton>
                        <span class="text-slate-600">Page {{ listRecipientsPage }} of {{ listRecipientsLastPage }}</span>
                        <BaseButton
                            variant="outline"
                            size="sm"
                            :disabled="listRecipientsPage >= listRecipientsLastPage"
                            @click="goToListRecipientsPage(listRecipientsPage + 1)"
                        >
                            <template #icon>
                                <ChevronRightIcon class="icon-sm" aria-hidden="true" />
                            </template>
                            Next
                        </BaseButton>
                    </div>
                </div>
            </BaseModal>

            <!-- Send to list: template + send -->
            <BaseCard v-if="selectedListForSend" :title="`Send to list: ${selectedListForSend.name}`">
                <p class="text-sm text-slate-600 mb-4">This will send the chosen template to <strong>{{ selectedListForSend.total }}</strong> recipient(s).</p>
                <div class="flex flex-wrap gap-4 items-end">
                    <div class="min-w-[200px]">
                        <label class="form-label" for="emailmanagementview-template">Template</label>
                        <select id="emailmanagementview-template"
                            v-model="sendToListTemplateId"
                            class="form-select"
                        >
                            <option :value="null">Select template</option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                        </select>
                    </div>
                    <BaseButton
                        variant="soft"
                        :loading="sendingToList"
                        :disabled="!sendToListTemplateId"
                        @click="sendToList"
                    >
                        <template #icon>
                            <PaperAirplaneIcon class="icon" aria-hidden="true" />
                        </template>
                        {{ sendingToList ? 'Sending...' : `Send to ${selectedListForSend.total} recipients` }}
                    </BaseButton>
                    <BaseButton variant="ghost" @click="selectedListForSend = null">Cancel</BaseButton>
                </div>
                <p
                    v-if="sendToListResult"
                    class="callout mt-3"
                    :class="sendToListResult.failed ? 'callout-warning' : 'callout-success'"
                    :role="sendToListResult.failed ? 'alert' : 'status'"
                    aria-live="polite"
                >
                    {{ sendToListResult.message }}
                </p>
            </BaseCard>
        </template>

        <!-- Bulk retry confirmation: replaces the old native browser confirm dialog -->
        <ConfirmDialog
            v-model="showQueueRetryConfirm"
            title="Queue all retries"
            :message="queueRetryConfirmMessage"
            confirm-label="Queue retries"
            tone="primary"
            :loading="queueingRetryAll"
            @confirm="confirmQueueAllRetries"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import {
    ArrowDownTrayIcon,
    ArrowPathIcon,
    ArrowUpTrayIcon,
    CheckCircleIcon,
    CheckIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    EnvelopeIcon,
    ExclamationTriangleIcon,
    EyeIcon,
    FunnelIcon,
    InformationCircleIcon,
    ListBulletIcon,
    PaperAirplaneIcon,
    PlusIcon,
    TrashIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import {
    BaseBadge,
    BaseButton,
    BaseCard,
    BaseModal,
    ConfirmDialog,
    EmptyState,
    StatCard,
} from '@/components/base';
import { formatCommLogStatus } from '@/utils/displayFormat';
import { formatDateTimeUsDisplay } from '@/utils/dateFormatUi';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const activeTab = ref('send');

const tabOptions = [
    { value: 'send', label: 'Send Email' },
    { value: 'report', label: 'Report' },
    { value: 'upload', label: 'Upload list' },
    { value: 'sendByDate', label: 'Send by Date' },
];

/** Keeps each tab's original side effect (report loads the report, upload loads the lists). */
function selectTab(value) {
    activeTab.value = value;
    if (value === 'report') loadReport();
    if (value === 'upload') loadSavedLists();
}

/** Delivered / bounced / everything else — mirrors the previous colour mapping. */
function reportStatusTone(status) {
    if (status === 'sent') return 'success';
    if (status === 'bounced') return 'warning';
    return 'danger';
}

function listRecipientTone(status) {
    if (status === 'sent') return 'success';
    if (status === 'failed') return 'danger';
    return 'neutral';
}
const audience = ref('both');
const searchQuery = ref('');
const excludedRecipientIds = ref([]);
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
const reportScope = ref('all');
const resendingId = ref(null);
const queueingRetryAll = ref(false);
const reportRetryChunkSize = ref(5);
const exportingOpenedReport = ref(false);
const showQueueRetryConfirm = ref(false);

const queueRetryConfirmMessage = computed(
    () =>
        `Queue ${reportSummary.value?.total_failed_retryable ?? 0} resend(s) in the background (batches of ${reportRetryChunkSize.value})?`,
);

const reportScopeOptions = [
    { value: 'all', label: 'All activity' },
    { value: 'sent', label: 'Delivered only' },
    { value: 'opened', label: 'Opened' },
    { value: 'retry_queue', label: 'Retry queue' },
    { value: 'bounces', label: 'Bounces' },
];

const reportEmptyMessage = computed(() => {
    if (reportScope.value === 'bounces') return 'No bounces in this period.';
    if (reportScope.value === 'retry_queue') return 'Nothing in the retry queue for this period.';
    if (reportScope.value === 'sent') return 'No delivered emails in this period.';
    if (reportScope.value === 'opened') return 'No opened emails in this period (tracking pixel may be blocked by the inbox).';
    return 'No email activity in this period.';
});

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
const dateSearchQuery = ref('');
const dateExcludedRecipientIds = ref([]);
const dateContacts = ref([]);
const dateTotalContacts = ref(0);
const hasDateApplied = ref(false);
const loadingDateContacts = ref(false);
const dateSelectedTemplateId = ref(null);
const datePreview = ref({ subject: '', content: '', template_name: '' });
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
    if (sendableTotal.value === 0 || !selectedTemplateId.value) return;
    sending.value = true;
    sendResult.value = null;
    try {
        const payload = { template_id: selectedTemplateId.value, ...buildSendPayload() };
        const { data } = await axios.post('/api/email-management/send', payload);
        sendResult.value = { message: data.message, failed: (data.failed || 0) > 0 };
        excludedRecipientIds.value = [];
        loadContactsPage(contactsPage.value);
        if (activeTab.value === 'report') loadReport();
    } catch (e) {
        sendResult.value = { message: e.response?.data?.message || e.message || 'Send failed', failed: true };
    } finally {
        sending.value = false;
    }
}

function formatDate(iso) {
    return formatDateTimeUsDisplay(iso);
}

function setReportScope(scope) {
    reportScope.value = scope;
    loadReport(1);
}

async function loadReport(page = 1) {
    loadingReport.value = true;
    reportPage.value = page;
    try {
        const params = { page, per_page: 20, scope: reportScope.value };
        if (reportDateFrom.value) params.date_from = reportDateFrom.value;
        if (reportDateTo.value) params.date_to = reportDateTo.value;
        const { data } = await axios.get('/api/email-management/sent-report', { params });
        reportRetryChunkSize.value = data.retry_chunk_size ?? 5;
        reportData.value = data.data || [];
        reportSummary.value = data.summary || {
            total_sent: 0,
            total_failed: 0,
            total_failed_retryable: 0,
            total_failed_validation: 0,
            total_bounced: 0,
            total_opened: 0,
        };
        reportLastPage.value = data.last_page ?? 1;
    } catch (e) {
        console.error(e);
        reportData.value = [];
        reportSummary.value = null;
    } finally {
        loadingReport.value = false;
    }
}

async function resendSentEmail(row) {
    if (!row?.id || !row.can_resend) return;
    resendingId.value = row.id;
    try {
        const { data } = await axios.post(`/api/email-management/sent/${row.id}/resend`);
        toast.success(data.message || 'Email sent.');
        await loadReport(reportPage.value);
    } catch (e) {
        const msg = e.response?.data?.message || e.message || 'Resend failed';
        toast.error(msg);
    } finally {
        resendingId.value = null;
    }
}

async function exportOpenedEmailsCsv() {
    if ((reportSummary.value?.total_opened ?? 0) < 1) {
        toast.warning('No opened emails to export for this filter.');
        return;
    }
    exportingOpenedReport.value = true;
    try {
        const params = { scope: 'opened' };
        if (reportDateFrom.value) params.date_from = reportDateFrom.value;
        if (reportDateTo.value) params.date_to = reportDateTo.value;
        const res = await axios.get('/api/email-management/sent-report/export', { params, responseType: 'blob' });
        const url = URL.createObjectURL(res.data);
        const a = document.createElement('a');
        a.href = url;
        a.download = `email-opened-report-${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
        toast.success('Export downloaded.');
    } catch (e) {
        toast.error(e.response?.data?.message || e.message || 'Export failed');
    } finally {
        exportingOpenedReport.value = false;
    }
}

function queueAllRetries() {
    if (!reportSummary.value || (reportSummary.value.total_failed_retryable ?? 0) < 1) return;
    showQueueRetryConfirm.value = true;
}

async function confirmQueueAllRetries() {
    if (!reportSummary.value || (reportSummary.value.total_failed_retryable ?? 0) < 1) {
        showQueueRetryConfirm.value = false;
        return;
    }
    queueingRetryAll.value = true;
    try {
        const payload = {};
        if (reportDateFrom.value) payload.date_from = reportDateFrom.value;
        if (reportDateTo.value) payload.date_to = reportDateTo.value;
        const { data } = await axios.post('/api/email-management/retry-queue', payload);
        toast.success(data.message || 'Queued.');
        await loadReport(reportPage.value);
    } catch (e) {
        toast.error(e.response?.data?.message || e.message || 'Could not queue retries');
    } finally {
        queueingRetryAll.value = false;
        showQueueRetryConfirm.value = false;
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
    if (dateSendableTotal.value === 0 || !dateSelectedTemplateId.value) return;
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
        const ds = (dateSearchQuery.value || '').trim();
        if (ds) payload.search = ds;
        const dex = [...new Set(dateExcludedRecipientIds.value)];
        if (dex.length) payload.exclude_customer_ids = dex;
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
