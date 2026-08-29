<template>
    <ListingPageShell
        title="WhatsApp Management"
        subtitle="Bulk WhatsApp template sends: filter contacts, pick an approved Meta template and send to everyone matching, newest matches first."
        :badge="whatsappBadge"
    >
        <template #actions>
            <BaseButton variant="primary" block-mobile :loading="exporting" @click="exportCsv">
                <template #icon><ArrowDownTrayIcon class="icon-sm" aria-hidden="true" /></template>
                {{ exporting ? 'Exporting...' : 'Export CSV' }}
            </BaseButton>
            <BaseButton variant="outline" block-mobile to="/whatsapp-templates">
                <template #icon><DocumentTextIcon class="icon-sm" aria-hidden="true" /></template>
                WhatsApp Templates
            </BaseButton>
            <BaseButton variant="outline" block-mobile to="/settings">
                <template #icon><Cog6ToothIcon class="icon-sm" aria-hidden="true" /></template>
                Settings
            </BaseButton>
        </template>

        <template #filters>
            <div class="space-y-4">
                <div class="tab-list" role="tablist" aria-label="WhatsApp management sections">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'send' ? 'true' : 'false'"
                        :class="['tab', activeTab === 'send' ? 'tab-active' : '']"
                        @click="activeTab = 'send'"
                    >
                        Send template
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'report' ? 'true' : 'false'"
                        :class="['tab', activeTab === 'report' ? 'tab-active' : '']"
                        @click="activeTab = 'report'; loadReport()"
                    >
                        Report
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === 'sendByDate' ? 'true' : 'false'"
                        :class="['tab', activeTab === 'sendByDate' ? 'tab-active' : '']"
                        @click="activeTab = 'sendByDate'"
                    >
                        Send by Date
                    </button>
                </div>

                <!-- Send template filters -->
                <div v-show="activeTab === 'send'" class="listing-filters-row">
                    <fieldset class="form-fieldset w-full sm:w-auto">
                        <legend class="form-legend">Audience</legend>
                        <div class="flex flex-wrap gap-4">
                            <label class="form-choice cursor-pointer">
                                <input v-model="audience" type="radio" value="prospect" class="form-radio" />
                                <span class="text-sm font-medium text-slate-700">Prospects only</span>
                            </label>
                            <label class="form-choice cursor-pointer">
                                <input v-model="audience" type="radio" value="customer" class="form-radio" />
                                <span class="text-sm font-medium text-slate-700">Customers only</span>
                            </label>
                            <label class="form-choice cursor-pointer">
                                <input v-model="audience" type="radio" value="both" class="form-radio" />
                                <span class="text-sm font-medium text-slate-700">Both</span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="w-full">
                        <p class="listing-label">Product filters</p>
                        <p class="form-hint mb-2">
                            Same rules as Email Management. Leave “All” to include everyone in the audience.
                        </p>
                        <div class="space-y-3">
                            <div
                                v-for="(filter, index) in productFilters"
                                :key="index"
                                class="flex flex-wrap items-center gap-3"
                            >
                                <div class="min-w-[180px]">
                                    <label class="sr-only" :for="`whatsappmanagementview-product-${index}`">
                                        Product for rule {{ index + 1 }}
                                    </label>
                                    <select
                                        :id="`whatsappmanagementview-product-${index}`"
                                        v-model="filter.product_id"
                                        class="form-select"
                                    >
                                        <option :value="null">Select product</option>
                                        <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                                    </select>
                                </div>
                                <div class="min-w-[180px]">
                                    <label class="sr-only" :for="`whatsappmanagementview-rule-${index}`">
                                        Rule {{ index + 1 }}
                                    </label>
                                    <select
                                        :id="`whatsappmanagementview-rule-${index}`"
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
                                    class="text-danger-700 hover:text-danger-800"
                                    @click="removeFilter(index)"
                                >
                                    <template #icon><TrashIcon class="icon-sm" aria-hidden="true" /></template>
                                    Remove
                                </BaseButton>
                            </div>
                            <BaseButton variant="outline" @click="addFilter">
                                <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                                Add product rule
                            </BaseButton>
                        </div>
                    </div>

                    <div class="w-full sm:flex-1 sm:min-w-[12rem] sm:max-w-md">
                        <label
                            class="listing-label"
                            for="whatsappmanagementview-search-by-customer-name-optional"
                        >Search by customer name (optional)</label>
                        <div class="relative">
                            <MagnifyingGlassIcon
                                class="icon absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"
                                aria-hidden="true"
                            />
                            <input
                                id="whatsappmanagementview-search-by-customer-name-optional"
                                v-model="searchQuery"
                                type="search"
                                placeholder="e.g. Smith"
                                class="form-input-search"
                                @keydown.enter.prevent="applyFilters"
                            />
                        </div>
                    </div>
                    <BaseButton
                        variant="soft"
                        block-mobile
                        class="shrink-0"
                        :loading="loadingContacts"
                        @click="applyFilters"
                    >
                        <template #icon><FunnelIcon class="icon-sm" aria-hidden="true" /></template>
                        {{ loadingContacts ? 'Loading...' : 'Apply filters' }}
                    </BaseButton>
                </div>

                <!-- Send by date filters -->
                <div v-show="activeTab === 'sendByDate'" class="listing-filters-row">
                    <div class="w-full sm:w-auto sm:min-w-[10rem]">
                        <label class="listing-label" for="whatsappmanagementview-from-date">From date</label>
                        <input
                            id="whatsappmanagementview-from-date"
                            v-model="dateFilterFrom"
                            type="date"
                            class="form-input"
                        />
                    </div>
                    <div class="w-full sm:w-auto sm:min-w-[10rem]">
                        <label class="listing-label" for="whatsappmanagementview-to-date">To date</label>
                        <input
                            id="whatsappmanagementview-to-date"
                            v-model="dateFilterTo"
                            type="date"
                            class="form-input"
                        />
                    </div>
                    <div class="w-full sm:w-auto sm:min-w-[11rem]">
                        <label class="listing-label" for="whatsappmanagementview-audience">Audience</label>
                        <select
                            id="whatsappmanagementview-audience"
                            v-model="dateFilterAudience"
                            class="form-select"
                        >
                            <option value="prospect">Prospects only</option>
                            <option value="customer">Customers only</option>
                            <option value="both">Both</option>
                        </select>
                    </div>
                    <div class="w-full sm:flex-1 sm:min-w-[12rem] sm:max-w-sm">
                        <label class="listing-label" for="whatsappmanagementview-search-name-optional">Search name (optional)</label>
                        <div class="relative">
                            <MagnifyingGlassIcon
                                class="icon absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"
                                aria-hidden="true"
                            />
                            <input
                                id="whatsappmanagementview-search-name-optional"
                                v-model="dateSearchQuery"
                                type="search"
                                placeholder="e.g. Smith"
                                class="form-input-search"
                                @keydown.enter.prevent="applyDateFilter"
                            />
                        </div>
                    </div>
                    <BaseButton
                        variant="soft"
                        block-mobile
                        class="shrink-0"
                        :loading="loadingDateContacts"
                        @click="applyDateFilter"
                    >
                        <template #icon><FunnelIcon class="icon-sm" aria-hidden="true" /></template>
                        {{ loadingDateContacts ? 'Loading...' : 'Apply' }}
                    </BaseButton>
                    <p class="w-full form-hint">Leave dates empty for all records (still scoped by audience).</p>
                </div>

                <!-- Report filters -->
                <div v-show="activeTab === 'report'" class="listing-filters-row">
                    <div class="w-full sm:w-auto sm:min-w-[10rem]">
                        <label class="listing-label" for="whatsappmanagementview-from">From</label>
                        <input
                            id="whatsappmanagementview-from"
                            v-model="reportDateFrom"
                            type="date"
                            class="form-input"
                        />
                    </div>
                    <div class="w-full sm:w-auto sm:min-w-[10rem]">
                        <label class="listing-label" for="whatsappmanagementview-to">To</label>
                        <input
                            id="whatsappmanagementview-to"
                            v-model="reportDateTo"
                            type="date"
                            class="form-input"
                        />
                    </div>
                    <BaseButton
                        variant="soft"
                        block-mobile
                        class="shrink-0"
                        :loading="loadingReport"
                        @click="loadReport"
                    >
                        <template #icon><FunnelIcon class="icon-sm" aria-hidden="true" /></template>
                        {{ loadingReport ? 'Loading...' : 'Apply' }}
                    </BaseButton>
                </div>
            </div>
        </template>

        <template #toolbar>
            <div
                v-if="waStatus"
                class="callout flex flex-wrap items-center gap-3"
                :class="waStatus.configured ? 'callout-success' : 'callout-warning'"
                role="status"
            >
                <CheckCircleIcon v-if="waStatus.configured" class="icon shrink-0" aria-hidden="true" />
                <ExclamationTriangleIcon v-else class="icon shrink-0" aria-hidden="true" />
                <span class="min-w-0">{{ waStatus.message }}</span>
            </div>
            <p v-else class="text-sm text-slate-500">
                Single-chat WhatsApp from a customer record is unchanged by anything on this screen.
            </p>
        </template>

        <!-- Send -->
        <template v-if="activeTab === 'send'">
            <div class="p-4 sm:p-6 space-y-5">
                <section class="card p-5 sm:p-6 space-y-3">
                    <h2 class="card-title">Recipients</h2>
                    <p class="text-xs text-slate-500">
                        Only contacts with a WhatsApp number or phone are included. Sending uses the same filtered set (all pages), not only the table below.
                    </p>

                    <div v-if="loadingContacts" class="space-y-3" aria-busy="true">
                        <span v-for="n in 5" :key="n" class="skeleton-text block w-full" />
                    </div>

                    <EmptyState
                        v-else-if="totalContacts === 0 && !hasApplied"
                        heading="No recipients loaded yet"
                        description="Choose an audience and any product rules above, then select “Apply filters” to load the recipient list."
                    >
                        <template #icon><ChatBubbleLeftRightIcon class="icon" aria-hidden="true" /></template>
                    </EmptyState>

                    <EmptyState
                        v-else-if="totalContacts === 0"
                        heading="No contacts match"
                        description="Nobody matches these filters, or none of the matches have a phone / WhatsApp number on record."
                    >
                        <template #icon><UsersIcon class="icon" aria-hidden="true" /></template>
                    </EmptyState>

                    <div v-else class="space-y-3">
                        <p class="text-sm text-slate-700">
                            <strong>{{ totalContacts }}</strong> match;
                            <strong class="text-success-700">{{ sendableTotal }}</strong> selected to receive.
                            <span v-if="totalContacts > contacts.length" class="text-slate-500">(page {{ contactsPage }} of {{ contactsLastPage }})</span>
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <BaseButton variant="ghost" @click="selectAllRecipientsOnPage">Select all on this page</BaseButton>
                            <BaseButton variant="ghost" @click="deselectAllRecipientsOnPage">Uncheck all on this page</BaseButton>
                            <BaseButton variant="ghost" @click="clearRecipientExclusions">Include everyone again</BaseButton>
                        </div>
                        <div class="table-wrap border border-slate-200 rounded-card max-h-64 overflow-y-auto">
                            <table class="table" style="min-width: 320px">
                                <caption class="sr-only">Recipients matching the current filters</caption>
                                <thead class="table-thead table-thead-sticky">
                                    <tr>
                                        <th scope="col" class="table-th w-10 text-center">
                                            <span class="sr-only">Include recipient</span>
                                        </th>
                                        <th scope="col" class="table-th">Name</th>
                                        <th scope="col" class="table-th">WhatsApp / Phone</th>
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
                                        <td class="table-td">{{ c.name }}</td>
                                        <td class="table-td font-mono text-xs">{{ c.display_phone }}</td>
                                        <td class="table-td capitalize">{{ c.type }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="card p-5 sm:p-6 space-y-4">
                    <h2 class="card-title">Template &amp; preview</h2>
                    <div class="max-w-md">
                        <label
                            class="form-label"
                            for="whatsappmanagementview-approved-whatsapp-template"
                        >Approved WhatsApp template</label>
                        <select
                            id="whatsappmanagementview-approved-whatsapp-template"
                            v-model="selectedTemplateId"
                            class="form-select"
                            @change="loadPreview"
                        >
                            <option :value="null">Select template</option>
                            <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }} ({{ t.language }})</option>
                        </select>
                    </div>
                    <p v-if="totalContacts > 0" class="text-sm text-slate-600">
                        Bulk send goes to <strong>{{ sendableTotal }}</strong> recipient(s) ({{ totalContacts }} match<span v-if="excludedRecipientIds.length">; {{ excludedRecipientIds.length }} excluded</span>). Variables are filled per customer (same as single send).
                    </p>
                    <div
                        v-if="preview.body_preview || preview.header_preview"
                        class="card bg-slate-50 p-4 text-sm space-y-2"
                    >
                        <p class="font-semibold text-slate-800">{{ preview.template_name || 'Preview' }}</p>
                        <p v-if="preview.header_preview" class="text-slate-600">
                            <span class="text-slate-500">Header:</span> {{ preview.header_preview }}
                        </p>
                        <p class="whitespace-pre-wrap text-slate-900">{{ preview.body_preview || '—' }}</p>
                    </div>
                    <p v-else-if="selectedTemplateId && !loadingPreview" class="text-sm text-slate-500">Could not load preview.</p>
                    <div v-if="loadingPreview" class="space-y-2" aria-busy="true">
                        <span class="skeleton-text block w-1/3" />
                        <span class="skeleton-text block w-full" />
                    </div>
                </section>

                <section class="card p-5 sm:p-6 space-y-3">
                    <h2 class="card-title">Send</h2>
                    <BaseButton
                        variant="success"
                        size="lg"
                        block-mobile
                        :loading="sending"
                        :disabled="sendableTotal === 0 || !selectedTemplateId"
                        @click="sendBulk"
                    >
                        <template #icon><PaperAirplaneIcon class="icon-sm" aria-hidden="true" /></template>
                        {{ sending ? 'Sending...' : `Send template to ${sendableTotal} recipient(s)` }}
                    </BaseButton>
                    <p
                        v-if="sendResult"
                        class="callout"
                        :class="sendResult.failed ? 'callout-warning' : 'callout-success'"
                        role="status"
                        aria-live="polite"
                    >{{ sendResult.message }}</p>
                    <div v-if="sendResult?.failed_list?.length" class="callout callout-warning max-h-48 overflow-y-auto">
                        <p class="font-semibold mb-2">Failures (showing up to {{ sendResult.failed_list.length }})</p>
                        <ul class="text-xs space-y-1">
                            <li v-for="(f, i) in sendResult.failed_list" :key="i">{{ f.name }} — {{ f.error }}</li>
                        </ul>
                    </div>
                </section>
            </div>
        </template>

        <!-- Send by date -->
        <template v-if="activeTab === 'sendByDate'">
            <div class="p-4 sm:p-6">
                <section class="card p-5 sm:p-6 space-y-5">
                    <div>
                        <h2 class="card-title">Send by Date</h2>
                        <p class="card-subtitle">
                            Filter by customer/prospect creation date, then send an approved template to all matches with a phone/WhatsApp number.
                        </p>
                    </div>

                    <div v-if="loadingDateContacts" class="space-y-3" aria-busy="true">
                        <span v-for="n in 5" :key="n" class="skeleton-text block w-full" />
                    </div>

                    <div v-else-if="hasDateApplied" class="space-y-4">
                        <div class="flex flex-wrap items-end gap-4">
                            <p class="text-sm text-slate-700">
                                <strong>{{ dateTotalContacts }}</strong> match;
                                <strong class="text-success-700">{{ dateSendableTotal }}</strong> selected.
                            </p>
                            <div class="min-w-[200px]">
                                <label class="form-label" for="whatsappmanagementview-template">Template</label>
                                <select
                                    id="whatsappmanagementview-template"
                                    v-model="dateSelectedTemplateId"
                                    class="form-select"
                                    @change="loadDatePreview"
                                >
                                    <option :value="null">Select template</option>
                                    <option v-for="t in templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                            </div>
                            <BaseButton
                                variant="success"
                                block-mobile
                                :loading="sendingByDate"
                                :disabled="dateSendableTotal === 0 || !dateSelectedTemplateId"
                                @click="sendByDate"
                            >
                                <template #icon><PaperAirplaneIcon class="icon-sm" aria-hidden="true" /></template>
                                {{ sendingByDate ? 'Sending...' : `Send to ${dateSendableTotal}` }}
                            </BaseButton>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <BaseButton variant="ghost" @click="selectAllDateRecipientsOnPage">Select all on this page</BaseButton>
                            <BaseButton variant="ghost" @click="deselectAllDateRecipientsOnPage">Uncheck all on this page</BaseButton>
                            <BaseButton variant="ghost" @click="clearDateRecipientExclusions">Include everyone again</BaseButton>
                        </div>

                        <div class="table-wrap border border-slate-200 rounded-card max-h-56 overflow-y-auto">
                            <table class="table" style="min-width: 280px">
                                <caption class="sr-only">Recipients matching the selected date range</caption>
                                <thead class="table-thead table-thead-sticky">
                                    <tr>
                                        <th scope="col" class="table-th w-10 text-center">
                                            <span class="sr-only">Include recipient</span>
                                        </th>
                                        <th scope="col" class="table-th">Name</th>
                                        <th scope="col" class="table-th">Phone</th>
                                        <th scope="col" class="table-th">Type</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="c in dateContacts" :key="c.id" class="table-row">
                                        <td class="table-td text-center">
                                            <input
                                                type="checkbox"
                                                class="form-checkbox"
                                                :aria-label="`Include ${c.name}`"
                                                :checked="dateRecipientIncluded(c.id)"
                                                @change="toggleDateRecipient(c.id, $event.target.checked)"
                                            />
                                        </td>
                                        <td class="table-td">{{ c.name }}</td>
                                        <td class="table-td font-mono text-xs">{{ c.display_phone }}</td>
                                        <td class="table-td capitalize">{{ c.type }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <p
                            v-if="datePreview.body_preview"
                            class="card bg-slate-50 p-3 text-sm whitespace-pre-wrap"
                        >{{ datePreview.body_preview }}</p>
                        <p
                            v-if="dateSendResult"
                            class="callout"
                            :class="dateSendResult.failed ? 'callout-warning' : 'callout-success'"
                            role="status"
                            aria-live="polite"
                        >{{ dateSendResult.message }}</p>
                    </div>

                    <EmptyState
                        v-else
                        heading="No recipients loaded yet"
                        description="Pick a date range and audience above, then select “Apply” to load everyone who matches."
                    >
                        <template #icon><CalendarDaysIcon class="icon" aria-hidden="true" /></template>
                    </EmptyState>
                </section>
            </div>
        </template>

        <!-- Report -->
        <template v-if="activeTab === 'report'">
            <div class="p-4 sm:p-6 space-y-5">
                <div>
                    <h2 class="card-title">Sent WhatsApp (bulk) report</h2>
                    <p class="card-subtitle">Logged sends from this screen (template messages). Filter by date.</p>
                </div>

                <div v-if="reportSummary" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <StatCard
                        label="Total sent"
                        :value="reportSummary.total_sent"
                        caption="Delivered to Meta in this period"
                        tone="success"
                    >
                        <template #icon><PaperAirplaneIcon class="icon" aria-hidden="true" /></template>
                    </StatCard>
                    <StatCard
                        label="Total failed"
                        :value="reportSummary.total_failed"
                        caption="Rejected or errored in this period"
                        tone="danger"
                    >
                        <template #icon><ExclamationTriangleIcon class="icon" aria-hidden="true" /></template>
                    </StatCard>
                </div>

                <div v-if="loadingReport" class="space-y-3" aria-busy="true">
                    <span v-for="n in 6" :key="n" class="skeleton-text block w-full" />
                </div>

                <EmptyState
                    v-else-if="reportData.length === 0"
                    heading="No records in this period"
                    description="Nothing was sent from this screen between those dates — widen the range or clear the filters above."
                >
                    <template #icon><DocumentTextIcon class="icon" aria-hidden="true" /></template>
                </EmptyState>

                <div v-else class="card overflow-hidden">
                    <div class="table-wrap">
                        <table class="table" style="min-width: 720px">
                            <caption class="sr-only">Bulk WhatsApp messages sent in the selected period</caption>
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
                                    <td class="table-td">{{ row.recipient_name }}</td>
                                    <td class="table-td font-mono text-xs">{{ row.recipient_phone }}</td>
                                    <td class="table-td">{{ row.template_name }}</td>
                                    <td class="table-td">
                                        <BaseBadge :tone="row.status === 'sent' ? 'success' : 'danger'">
                                            {{ formatCommLogStatus(row.status) }}
                                        </BaseBadge>
                                        <span
                                            v-if="row.error_message"
                                            class="block text-xs text-slate-500 truncate max-w-[220px]"
                                            :title="row.error_message"
                                        >{{ row.error_message }}</span>
                                    </td>
                                    <td class="table-td">{{ formatDate(row.sent_at) }}</td>
                                    <td class="table-td">{{ row.sent_by_name || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>

        <template #pagination>
            <Pagination
                v-if="activeTab === 'send' && hasApplied"
                :pagination="contactsPagination"
                embedded
                result-label="recipients"
                singular-label="recipient"
                @page-change="goToContactsPage"
            />
            <div
                v-else-if="activeTab === 'report' && reportLastPage > 1"
                class="flex flex-wrap items-center gap-3 px-5 sm:px-6 py-3.5 bg-slate-50/40"
            >
                <BaseButton variant="outline" :disabled="reportPage <= 1" @click="goToReportPage(reportPage - 1)">
                    <template #icon><ChevronLeftIcon class="icon-sm" aria-hidden="true" /></template>
                    Previous
                </BaseButton>
                <span class="text-sm text-slate-600">Page {{ reportPage }} of {{ reportLastPage }}</span>
                <BaseButton
                    variant="outline"
                    :disabled="reportPage >= reportLastPage"
                    @click="goToReportPage(reportPage + 1)"
                >
                    <template #icon><ChevronRightIcon class="icon-sm" aria-hidden="true" /></template>
                    Next
                </BaseButton>
            </div>
        </template>
    </ListingPageShell>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import {
    ArrowDownTrayIcon,
    CalendarDaysIcon,
    ChatBubbleLeftRightIcon,
    CheckCircleIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    Cog6ToothIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    PaperAirplaneIcon,
    PlusIcon,
    TrashIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import { formatCommLogStatus } from '@/utils/displayFormat';
import { formatDateTimeUsDisplay } from '@/utils/dateFormatUi';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseBadge, BaseButton, EmptyState, StatCard } from '@/components/base';

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

const whatsappBadge = computed(() =>
    hasApplied.value && totalContacts.value ? `${totalContacts.value} Total` : null,
);

/** Shape the existing contact paging refs into what <Pagination> expects. */
const contactsPagination = computed(() => ({
    current_page: contactsPage.value,
    last_page: contactsLastPage.value,
    per_page: contactsPerPage.value,
    total: totalContacts.value,
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
    return formatDateTimeUsDisplay(iso);
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
