<template>
    <div class="min-h-screen bg-slate-100 w-full min-w-0 overflow-x-hidden">
        <!-- Record context bar (breadcrumbs + user menu come from AppLayout) -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-sticky">
            <div class="px-4 sm:px-6 py-3 sm:py-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <router-link
                        :to="customersListRoute"
                        class="link inline-flex items-center gap-1.5 text-sm min-w-0"
                    >
                        <ArrowLeftIcon class="icon-sm shrink-0" aria-hidden="true" />
                        {{ customerTypeLabel === 'Customer' ? 'Back to Customers' : 'Back to Prospects' }}
                    </router-link>
                    <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto shrink-0">
                        <BaseButton variant="outline" :to="`/customers/${customer?.id}/edit`" block-mobile>
                            Edit Customer
                        </BaseButton>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="max-w-[1600px] mx-auto px-3 sm:px-5 py-4 sm:py-5 w-full min-w-0">
            <!-- Deal header (pipeline-style) -->
            <BaseCard v-if="customer" class="mb-4">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <h1 class="text-page-title text-slate-900 break-words">{{ customer.name }}</h1>
                        <p v-if="customer.business_name" class="text-sm text-slate-500 mt-0.5">{{ customer.business_name }}</p>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm">
                            <a v-if="customer.phone" :href="'tel:' + customer.phone" class="link">{{ customer.phone }}</a>
                            <a v-if="customer.email" :href="'mailto:' + customer.email" class="link truncate max-w-full">{{ customer.email }}</a>
                            <span v-if="customer.city" class="text-slate-600">{{ customer.city }}</span>
                        </div>
                    </div>
                    <div v-if="activeLead" class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2 shrink-0">
                        <div class="sm:min-w-[11rem]">
                            <label class="sr-only" for="active-lead-select">Active lead</label>
                            <select
                                id="active-lead-select"
                                v-model.number="selectedLeadId"
                                class="form-select"
                                @change="onActiveLeadSelectChange"
                            >
                                <option v-for="l in allLeads" :key="l.id" :value="l.id">Lead #{{ l.id }} · {{ formatStage(l.stage) }}</option>
                            </select>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <BaseButton
                                variant="success"
                                :disabled="stageUpdating || activeLead.stage === 'won'"
                                @click="submitMarkLeadWon"
                            >
                                Won
                            </BaseButton>
                            <BaseButton
                                variant="danger"
                                :disabled="stageUpdating || activeLead.stage === 'lost'"
                                @click="showLostLeadModal = true; lostReasonInput = ''"
                            >
                                Lost
                            </BaseButton>
                            <BaseButton
                                v-if="isAdminRole && activeLead.stage === 'won'"
                                variant="primary"
                                @click="openChangeSaleCreditForLead(activeLead)"
                            >
                                Change Sale Owner
                            </BaseButton>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <BaseButton variant="outline" @click="openScheduleModal(activeLead)">
                                <template #icon><CalendarDaysIcon class="icon-sm" aria-hidden="true" /></template>
                                Schedule
                            </BaseButton>
                            <BaseButton variant="outline" @click="openActivityModal(activeLead)">
                                <template #icon><ClipboardDocumentIcon class="icon-sm" aria-hidden="true" /></template>
                                Log activity
                            </BaseButton>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-500 shrink-0">No lead yet — use <strong>Focus</strong> below to add products.</p>
                </div>
                <ol v-if="activeLead" class="mt-4 flex rounded-control overflow-hidden border border-slate-200" aria-label="Lead pipeline stage">
                    <li
                        v-for="st in pipelineStageOrder"
                        :key="st"
                        class="flex-1 min-w-0 text-center py-2.5 px-0.5 sm:px-2 text-[10px] sm:text-xs font-bold uppercase tracking-tight border-r border-white/25 last:border-r-0"
                        :class="pipelineStageVisualClass(activeLead.stage, st)"
                        :aria-current="activeLead.stage === st ? 'step' : undefined"
                    >
                        {{ formatStagePipe(st) }}
                    </li>
                </ol>
                <p v-if="activeLead?.assignee" class="text-xs text-slate-500 mt-3">Owner: <span class="font-medium text-slate-800">{{ activeLead.assignee.name }}</span></p>
            </BaseCard>

            <!-- Full-width on desktop: avoids 3-column grid inside a narrow sidebar -->
            <BaseCard v-if="customer" :title="`${customerTypeLabel} details`" class="mb-4 lg:mb-5 w-full min-w-0">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-5 [word-break:break-word]">
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Name</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.name || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Business name</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.business_name || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Owner name</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.owner_name || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Phone number 1</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.phone || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Contact Person 2 Name</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.contact_person_2_name || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Contact Person 2 Phone</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.contact_person_2_phone || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Email</span><div class="font-medium text-slate-900 mt-0.5 break-all">{{ customer.email || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Secondary email</span><div class="font-medium text-slate-900 mt-0.5 break-all">{{ customer.email_secondary || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">WhatsApp number</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.whatsapp_number || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">SMS number</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.sms_number || '—' }}</div></div>
                    <div class="min-w-0 sm:col-span-2 lg:col-span-2 xl:col-span-2"><span class="text-eyebrow text-slate-500 uppercase block">Address</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.address || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Postcode</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.postcode || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">City</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.city || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">VAT number</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.vat_number || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Source</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.source || lead?.source || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">AnyDesk / RustDesk</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.anydesk_rustdesk || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">EPOS type</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.epos_type || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Licence days</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.lic_days ?? '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Birthday</span><div class="font-medium text-slate-900 mt-0.5">{{ formatDate(customer.birthday) || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Category</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.category || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-eyebrow text-slate-500 uppercase block">Created on</span><div class="font-medium text-slate-900 mt-0.5">{{ formatDate(customer.created_at) || '—' }}</div></div>
                    <div class="min-w-0 sm:col-span-2 lg:col-span-3 xl:col-span-4"><span class="text-eyebrow text-slate-500 uppercase block">Assigned employees</span><div class="font-medium text-slate-900 mt-0.5 flex flex-wrap gap-1"><template v-if="customer.assigned_users?.length"><span v-for="u in customer.assigned_users" :key="u.id" class="chip">{{ u.name }}</span></template><span v-else>—</span></div></div>
                </div>
                <div v-if="customer.notes" class="mt-4 pt-4 border-t border-slate-200">
                    <span class="text-eyebrow text-slate-500 uppercase block">Notes</span>
                    <div class="font-medium text-slate-900 mt-0.5 whitespace-pre-wrap break-words">{{ customer.notes }}</div>
                </div>
                <div v-if="activeLead?.expected_closing_date" class="mt-4 pt-4 border-t border-slate-200">
                    <span class="text-eyebrow text-slate-500 uppercase block">Expected closing date</span>
                    <div class="font-medium text-slate-900 mt-0.5">{{ formatDate(activeLead.expected_closing_date) }}</div>
                </div>
            </BaseCard>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-5 items-start">
                <aside class="lg:col-span-4 xl:col-span-3 space-y-4 order-2 lg:order-1 min-w-0">
            <!-- Leads + activity filters → History for selected lead -->
            <div v-if="customer" class="card overflow-hidden">
                <h2>
                    <button
                        type="button"
                        class="w-full flex items-center justify-between gap-3 px-4 sm:px-5 py-3.5 text-left hover:bg-slate-50/80 transition-colors touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500/40"
                        :aria-expanded="showLeadsPanel"
                        aria-controls="customerleadview-leads-panel"
                        @click="showLeadsPanel = !showLeadsPanel"
                    >
                        <span class="min-w-0">
                            <span class="block text-base font-semibold text-slate-900">Leads &amp; activity</span>
                            <span class="block text-xs font-normal text-slate-500 mt-0.5 truncate">Filter by time or person — click a lead for its timeline</span>
                        </span>
                        <ChevronDownIcon
                            class="icon text-slate-500 shrink-0 transition-transform"
                            :class="showLeadsPanel ? 'rotate-180' : ''"
                            aria-hidden="true"
                        />
                    </button>
                </h2>
                <div id="customerleadview-leads-panel" v-show="showLeadsPanel" class="px-4 sm:px-5 pb-4 border-t border-slate-100 space-y-3">
                    <div class="flex flex-wrap gap-1.5 pt-3">
                        <BaseButton :variant="leadActivityPreset === 'all' ? 'primary' : 'outline'" :aria-pressed="leadActivityPreset === 'all'" @click="setActivityPreset('all')">All</BaseButton>
                        <BaseButton :variant="leadActivityPreset === 'today' ? 'primary' : 'outline'" :aria-pressed="leadActivityPreset === 'today'" @click="setActivityPreset('today')">Today</BaseButton>
                        <BaseButton :variant="leadActivityPreset === 'week' ? 'primary' : 'outline'" :aria-pressed="leadActivityPreset === 'week'" @click="setActivityPreset('week')">Week</BaseButton>
                        <BaseButton :variant="leadActivityPreset === 'month' ? 'primary' : 'outline'" :aria-pressed="leadActivityPreset === 'month'" @click="setActivityPreset('month')">Month</BaseButton>
                    </div>
                    <div class="grid grid-cols-1 gap-2">
                        <div>
                            <label class="form-label" for="customerleadview-one-day">One day</label>
                            <input id="customerleadview-one-day" v-model="leadFilterSingleDate" type="date" class="form-input" @change="onSingleDayPicked" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="form-label" for="customerleadview-from">From</label>
                                <input id="customerleadview-from" v-model="leadFilterFrom" type="date" class="form-input" @change="onRangeChanged" />
                            </div>
                            <div>
                                <label class="form-label" for="customerleadview-to">To</label>
                                <input id="customerleadview-to" v-model="leadFilterTo" type="date" class="form-input" @change="onRangeChanged" />
                            </div>
                        </div>
                        <div v-if="isAdminForFilter">
                            <label class="form-label" for="customerleadview-activity-by">Activity by</label>
                            <select id="customerleadview-activity-by" v-model="leadFilterUserId" class="form-select">
                                <option value="">Anyone</option>
                                <option v-for="emp in filterEmployees" :key="emp.id" :value="String(emp.id)">{{ emp.name }}</option>
                            </select>
                        </div>
                        <p v-else class="form-hint">Managers and admins can filter by the person who logged an activity.</p>
                        <div>
                            <BaseButton variant="ghost" @click="clearActivityFilters">Clear filters</BaseButton>
                        </div>
                    </div>
                    <p v-if="allLeads.length === 0" class="text-sm text-slate-500 py-2">No leads yet for this {{ customerTypeLabel.toLowerCase() }}.</p>
                    <template v-else>
                        <p class="text-xs text-slate-500">
                            Showing <span class="font-semibold text-slate-700">{{ sidebarLeadsFiltered.length }}</span> of {{ allLeads.length }} leads
                            <span v-if="activityFilterActive">(with activity in the current filter)</span>
                        </p>
                        <div v-if="sidebarLeadsFiltered.length === 0" class="callout callout-warning">
                            No leads match this filter. Try <strong>All</strong> or widen the date range.
                        </div>
                        <nav aria-label="Leads for this customer" class="space-y-1 max-h-[min(420px,50vh)] overflow-y-auto -mx-1 px-1">
                            <button
                                v-for="l in sidebarLeadsFiltered"
                                :key="l.id"
                                type="button"
                                class="w-full text-left rounded-card border px-3 py-2.5 transition-colors touch-manipulation focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                :class="Number(selectedLeadId) === Number(l.id) ? 'border-primary-400 bg-primary-50/90 ring-1 ring-primary-200' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/80'"
                                :aria-current="Number(selectedLeadId) === Number(l.id) ? 'true' : undefined"
                                @click="openLeadHistoryFromSidebar(l)"
                            >
                                <span class="block font-medium text-slate-900 text-sm leading-snug pr-1">{{ leadSidebarTitle(l) }}</span>
                                <span class="flex flex-wrap items-center gap-1.5 mt-1">
                                    <span class="font-mono text-xs text-primary-700">#{{ l.id }}</span>
                                    <BaseBadge :status="l.stage">{{ formatStage(l.stage) }}</BaseBadge>
                                </span>
                                <span class="block text-[11px] text-slate-500 mt-1">Created {{ formatDate(l.created_at) || '—' }}<template v-if="l.creator?.name"> · {{ l.creator.name }}</template></span>
                                <span v-if="l.assignee?.name" class="block text-[11px] text-slate-600">Owner: {{ l.assignee.name }}</span>
                            </button>
                        </nav>
                        <p class="text-[11px] text-slate-500">Click a lead for <strong>History</strong>: appointments, follow-ups, messages, notes.</p>
                    </template>
                </div>
            </div>

            <!-- Prospect for / Purchased (categorization) -->
            <BaseCard v-if="customer && (prospectProductNames.length > 0 || purchasedProductNames.length > 0)" title="Products">
                <div v-if="prospectProductNames.length > 0" class="mb-3">
                    <span class="text-eyebrow text-slate-500 uppercase block">Prospect for</span>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <BaseBadge v-for="name in prospectProductNames" :key="name" tone="warning">{{ name }}</BaseBadge>
                    </div>
                </div>
                <div v-if="purchasedProductNames.length > 0">
                    <span class="text-eyebrow text-slate-500 uppercase block">Purchased</span>
                    <div class="mt-1 flex flex-wrap gap-2">
                        <BaseBadge v-for="name in purchasedProductNames" :key="name" tone="success">{{ name }}</BaseBadge>
                    </div>
                </div>
            </BaseCard>

            <!-- Assignment log (customer) -->
            <BaseCard v-if="customer?.assignments?.length" title="Assignment log">
                <ul class="space-y-2">
                    <li v-for="a in customer.assignments" :key="a.id" class="text-sm text-slate-700">
                        Assigned to <strong>{{ a.user?.name || '—' }}</strong> by <strong>{{ (a.assignedBy && a.assignedBy.name) || '—' }}</strong> on {{ formatDate(a.assigned_at) }}
                        <span v-if="a.notes" class="text-slate-500"> — {{ a.notes }}</span>
                    </li>
                </ul>
            </BaseCard>

            <!-- What Customer / Prospect Has Section -->
            <BaseCard v-if="customerHasItems.length > 0" :title="`What ${customerTypeLabel} Has`">
                <div class="grid grid-cols-1 gap-3">
                    <div
                        v-for="item in customerHasItems"
                        :key="item.id"
                        class="border border-slate-200 rounded-card p-4"
                    >
                        <div class="font-medium text-slate-900">{{ item.product?.name }}</div>
                        <div class="text-sm text-slate-600 mt-1">
                            Quantity: {{ item.quantity }} × £{{ parseFloat(item.unit_price || 0).toFixed(2) }}
                        </div>
                        <div class="text-sm font-medium text-slate-900 mt-2">
                            Total: £{{ parseFloat(item.total_price || 0).toFixed(2) }}
                        </div>
                        <div v-if="item.notes" class="text-xs text-slate-500 mt-2">{{ item.notes }}</div>
                    </div>
                </div>
            </BaseCard>

            <!-- What to Sell Next Section -->
            <BaseCard v-if="nextProducts.length > 0" title="What to Sell Next">
                <div class="grid grid-cols-1 gap-3">
                    <div
                        v-for="suggestion in nextProducts"
                        :key="suggestion.product.id"
                        class="callout callout-info"
                    >
                        <div class="font-medium text-primary-900">{{ suggestion.product.name }}</div>
                        <div class="text-sm text-primary-800 mt-1">
                            Suggested because customer has: {{ suggestion.suggested_by }}
                        </div>
                    </div>
                </div>
            </BaseCard>

            <!-- Sidebar: tickets & invoices -->
            <BaseCard v-if="tickets.length > 0 || invoices.length > 0">
                <div class="space-y-4">
                    <div v-if="tickets.length > 0">
                        <h2 class="text-eyebrow text-slate-500 uppercase mb-2">Tickets</h2>
                        <ul class="space-y-2">
                            <li v-for="t in tickets" :key="t.id" class="text-sm border border-slate-100 rounded-control p-2">
                                <div class="font-medium text-slate-900">{{ t.ticket_number }}</div>
                                <div class="text-slate-600 truncate">{{ t.subject }}</div>
                            </li>
                        </ul>
                    </div>
                    <div v-if="invoices.length > 0">
                        <h2 class="text-eyebrow text-slate-500 uppercase mb-2">Invoices</h2>
                        <ul class="space-y-2">
                            <li v-for="inv in invoices" :key="inv.id" class="text-sm border border-slate-100 rounded-control p-2 flex justify-between gap-2">
                                <span class="font-medium text-slate-900">{{ inv.invoice_number }}</span>
                                <span class="text-slate-700">£{{ formatNumber(inv.total) }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </BaseCard>
                </aside>

                <main class="lg:col-span-8 xl:col-span-9 space-y-4 order-1 lg:order-2 min-w-0">
                    <div class="card overflow-hidden">
                        <nav class="tab-list border-b border-slate-200 bg-slate-50/90" aria-label="Workspace">
                            <button type="button" class="tab min-h-[42px]" :class="workspaceMainTab === 'focus' ? 'tab-active' : ''" :aria-current="workspaceMainTab === 'focus' ? 'page' : undefined" @click="workspaceMainTab = 'focus'">Focus</button>
                            <button type="button" class="tab min-h-[42px]" :class="workspaceMainTab === 'messages' ? 'tab-active' : ''" :aria-current="workspaceMainTab === 'messages' ? 'page' : undefined" @click="workspaceMainTab = 'messages'">Messages</button>
                            <button type="button" class="tab min-h-[42px]" :class="workspaceMainTab === 'history' ? 'tab-active' : ''" :aria-current="workspaceMainTab === 'history' ? 'page' : undefined" @click="workspaceMainTab = 'history'">History</button>
                        </nav>
                        <div class="p-4 sm:p-5">
                            <div v-show="workspaceMainTab === 'focus'" class="space-y-5">
            <!-- Appointment timeline (active lead) -->
            <section>
                <h2 class="text-base font-semibold text-slate-900 mb-1">Appointments</h2>
                <p class="text-sm text-slate-500 mb-3">Scheduled visits for the selected lead. Use <strong>Schedule</strong> above or Log activity → Appointment.</p>
                <EmptyState
                    v-if="appointmentsForActiveLead.length === 0"
                    heading="No appointment scheduled"
                    description="Click Schedule in the header to book one."
                >
                    <template #icon><CalendarDaysIcon class="icon" aria-hidden="true" /></template>
                </EmptyState>
                <div v-else class="space-y-3">
                    <div
                        v-for="apt in appointmentsForActiveLead"
                        :key="apt.id"
                        class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 p-4 rounded-card border"
                        :class="apt.appointment_status === 'completed' ? 'bg-success-50 border-success-200' : apt.appointment_status === 'cancelled' || apt.appointment_status === 'no_show' ? 'bg-slate-50 border-slate-200' : 'bg-warning-50 border-warning-200'"
                    >
                        <div class="flex-1 min-w-0">
                            <h3 class="font-medium text-slate-900">{{ apt.description || 'Appointment' }}</h3>
                            <div class="text-sm text-slate-600 mt-0.5">
                                {{ formatAppointmentDate(apt.appointment_date) }} at {{ apt.appointment_time || '10:00' }}
                            </div>
                            <div class="text-xs text-slate-500 mt-1">Lead #{{ apt.lead_id }}</div>
                            <div v-if="apt.assignee?.name" class="text-xs text-slate-500 mt-0.5">Assigned to: {{ apt.assignee.name }}</div>
                            <div v-if="apt.outcome_notes" class="text-sm text-slate-700 mt-2 p-2 bg-white/60 rounded-control">{{ apt.outcome_notes }}</div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
                            <BaseBadge :status="apt.appointment_status">
                                {{ getAppointmentStatusLabel(apt.appointment_status) }}
                            </BaseBadge>
                            <BaseButton variant="outline" :to="`/appointments/${apt.id}`">View / Update</BaseButton>
                            <BaseButton variant="success" @click="openCompleteForAppointment(apt)">
                                <template #icon><CheckIcon class="icon-sm" aria-hidden="true" /></template>
                                Complete
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Active leads & line items (all open leads) -->
            <section v-if="activeLeads.length > 0" class="rounded-card border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                <h2 class="text-base font-semibold text-slate-900 mb-3">Leads &amp; products</h2>
                <div class="space-y-4">
                    <div
                        v-for="leadRow in activeLeads"
                        :key="leadRow.id"
                        class="border border-slate-200 rounded-card p-4 bg-white"
                    >
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3 mb-3">
                            <div>
                                <h3 class="font-medium text-slate-900">
                                    Lead #{{ leadRow.id }}
                                </h3>
                                <div class="text-sm text-slate-600 mt-1 flex flex-wrap items-center gap-1.5">
                                    Stage: <BaseBadge :status="leadRow.stage">{{ formatStage(leadRow.stage) }}</BaseBadge>
                                </div>
                                <div v-if="leadRow.next_follow_up_at" class="text-sm text-warning-800 mt-1 font-medium">
                                    Next follow-up: {{ formatDate(leadRow.next_follow_up_at) }}
                                </div>
                                <div v-if="(leadRow.assignment_logs || leadRow.assignmentLogs)?.length" class="text-xs text-slate-500 mt-2">
                                    Assignment: <span v-for="(log, i) in (leadRow.assignment_logs || leadRow.assignmentLogs)" :key="log.id">
                                        {{ i ? '; ' : '' }}to {{ (log.new_assignee || log.newAssignee)?.name || '—' }} by {{ (log.assigned_by_user || log.assignedByUser)?.name || '—' }} on {{ formatDate(log.assigned_at) }}
                                    </span>
                                </div>
                            </div>
                            <BaseButton variant="primary" block-mobile @click="openActivityModal(leadRow)">
                                <template #icon><ClipboardDocumentIcon class="icon-sm" aria-hidden="true" /></template>
                                Log activity
                            </BaseButton>
                        </div>
                        <div v-if="leadRow.items && leadRow.items.length > 0" class="mt-3 pt-3 border-t border-slate-100">
                            <div class="text-sm font-medium text-slate-700 mb-2">Products</div>
                            <div class="space-y-2">
                                <div
                                    v-for="item in leadRow.items"
                                    :key="item.id"
                                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-3 rounded-card min-w-0"
                                    :class="getItemStatusClass(item.status)"
                                >
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <template v-if="item.status === 'won'">
                                            <CheckCircleIcon class="icon text-success-700 shrink-0" aria-hidden="true" />
                                            <span class="sr-only">Won:</span>
                                        </template>
                                        <template v-else-if="item.status === 'lost'">
                                            <XCircleIcon class="icon text-danger-600 shrink-0" aria-hidden="true" />
                                            <span class="sr-only">Lost:</span>
                                        </template>
                                        <template v-else>
                                            <ClockIcon class="icon text-warning-800 shrink-0" aria-hidden="true" />
                                            <span class="sr-only">Pending:</span>
                                        </template>
                                        <div>
                                            <div class="font-medium text-slate-900">{{ item.product?.name }}</div>
                                            <div v-if="item.status === 'won'" class="text-xs text-slate-600">
                                                Qty: {{ item.quantity }} × £{{ parseFloat(item.unit_price || 0).toFixed(2) }} = £{{ parseFloat(item.total_price || 0).toFixed(2) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="item.status === 'pending'" class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                                        <BaseButton
                                            variant="success"
                                            :label="`Mark ${item.product?.name || 'product'} as won`"
                                            @click="openCloseItemModal(leadRow, item, 'won')"
                                        >
                                            Won
                                        </BaseButton>
                                        <BaseButton
                                            variant="danger"
                                            :label="`Mark ${item.product?.name || 'product'} as lost`"
                                            @click="openCloseItemModal(leadRow, item, 'lost')"
                                        >
                                            Lost
                                        </BaseButton>
                                    </div>
                                    <BaseBadge v-else :status="item.status" class="shrink-0">
                                        {{ formatLineItemStatus(item.status) }}
                                    </BaseBadge>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <FollowUpLeadForm
                v-if="customer"
                :customer-id="customer.id"
                :existing-lead="activeLead"
                @saved="handleFormSaved"
                @cancel="showForm = false"
            />

                            </div>

                            <div v-show="workspaceMainTab === 'messages'" class="space-y-4">
            <section>
                <h2 class="text-base font-semibold text-slate-900 mb-1">Send messages</h2>
                <p class="text-sm text-slate-500 mb-4">Email, SMS, and WhatsApp are logged in <strong>History</strong>. WhatsApp follows Meta’s delivery rules.</p>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <EmailComposer
                        v-if="customer"
                        :customer="customer"
                        :lead-id="activeLead?.id"
                        :logs="communicationLogs.emails"
                        :show-inline-logs="false"
                        @sent="handleMessageSent"
                        @saved="handleContactSaved"
                    />
                    <SMSComposer
                        v-if="customer"
                        :customer="customer"
                        :lead-id="activeLead?.id"
                        :logs="communicationLogs.sms"
                        :show-inline-logs="false"
                        @sent="handleMessageSent"
                        @saved="handleContactSaved"
                    />
                    <WhatsAppComposer
                        v-if="customer"
                        :customer="customer"
                        :lead-id="activeLead?.id"
                        :logs="communicationLogs.whatsapp"
                        :show-inline-logs="false"
                        @sent="handleMessageSent"
                        @saved="handleContactSaved"
                        @refresh-logs="loadCommunicationLogsOnly"
                    />
                </div>
            </section>
                            </div>

                            <div v-show="workspaceMainTab === 'history'" :aria-busy="historyTimelineLoading ? 'true' : 'false'">
                                <p v-if="historyTimelineLoading" class="text-sm text-slate-500 mb-2" role="status" aria-live="polite">Updating history…</p>
            <TimelineSection
                :timeline="displayHistoryTimeline"
                class="border-0 shadow-none"
            />
                            </div>

                        </div>
                    </div>
                </main>
            </div>
        </div>

        <!-- Close line item (Won / Lost) -->
        <BaseModal
            v-model="showCloseItemModal"
            :title="`Close Product: ${closeItemData.item?.product?.name || ''}`"
            size="md"
            :close-on-backdrop="false"
        >
            <div v-if="closeItemData.status === 'won'" class="space-y-4">
                <div>
                    <label class="form-label" for="customerleadview-quantity">Quantity <span class="form-required" aria-hidden="true">*</span></label>
                    <input id="customerleadview-quantity"
                        v-model.number="closeItemData.quantity"
                        type="number"
                        min="1"
                        required
                        class="form-input"
                    />
                </div>
                <div>
                    <label class="form-label" for="customerleadview-unit-price">Unit Price (£) <span class="form-required" aria-hidden="true">*</span></label>
                    <input id="customerleadview-unit-price"
                        v-model.number="closeItemData.unit_price"
                        type="number"
                        step="0.01"
                        min="0"
                        required
                        class="form-input"
                    />
                </div>
                <div>
                    <label class="form-label" for="customerleadview-notes">Notes</label>
                    <textarea id="customerleadview-notes"
                        v-model="closeItemData.notes"
                        rows="2"
                        class="form-textarea"
                        placeholder="Optional notes..."
                    />
                </div>
            </div>

            <div v-else class="space-y-4">
                <div>
                    <label class="form-label" for="customerleadview-lost-reason">Lost Reason <span class="form-required" aria-hidden="true">*</span></label>
                    <textarea id="customerleadview-lost-reason"
                        v-model="closeItemData.lost_reason"
                        rows="3"
                        required
                        class="form-textarea"
                        placeholder="Why was this product lost?"
                    />
                </div>
            </div>

            <p v-if="closeItemError" class="callout callout-danger mt-4" role="alert">
                {{ closeItemError }}
            </p>

            <template #actions>
                <BaseButton variant="outline" block-mobile @click="showCloseItemModal = false">Cancel</BaseButton>
                <BaseButton
                    :variant="closeItemData.status === 'won' ? 'success' : 'danger'"
                    block-mobile
                    :loading="closeItemLoading"
                    @click="confirmCloseItem"
                >
                    {{ closeItemLoading ? 'Saving...' : (closeItemData.status === 'won' ? 'Mark as Won' : 'Mark as Lost') }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Assignment Modal -->
        <CustomerAssignmentModal
            v-if="showAssignmentModal && customer"
            :customer="customer"
            @close="showAssignmentModal = false"
            @assigned="handleAssignmentComplete"
        />

        <!-- Log Activity Modal -->
        <LogActivityModal
            v-if="showActivityModal && activityLead"
            :lead="activityLead"
            :initial-activity-type="activityModalInitialType"
            @close="closeActivityModal"
            @saved="handleActivitySaved"
        />

        <!-- Mark lead as Lost -->
        <BaseModal
            v-model="showLostLeadModal"
            title="Mark lead as Lost"
            description="Please provide a reason. This is required for reporting."
            size="md"
            :close-on-backdrop="false"
        >
            <div>
                <label class="form-label" for="customerleadview-lead-lost-reason">
                    Lost reason <span class="form-required" aria-hidden="true">*</span>
                </label>
                <textarea
                    id="customerleadview-lead-lost-reason"
                    v-model="lostReasonInput"
                    rows="3"
                    required
                    class="form-textarea"
                    placeholder="Lost reason..."
                />
            </div>

            <template #actions>
                <BaseButton variant="outline" block-mobile @click="showLostLeadModal = false">Cancel</BaseButton>
                <BaseButton variant="danger" block-mobile :loading="stageUpdating" @click="submitMarkLeadLost">
                    Save
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Complete Follow-up / Appointment Modal -->
        <BaseModal
            v-model="showCompleteModal"
            title="Complete Appointment / Follow-up"
            size="md"
            :close-on-backdrop="false"
            @close="closeCompleteModal"
        >
            <form id="complete-followup-form" class="space-y-4" @submit.prevent="completeFollowUp">
                <div>
                    <label class="form-label" for="customerleadview-remarks-notes">
                        Remarks / Notes <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <textarea id="customerleadview-remarks-notes"
                        v-model="completeForm.remarks"
                        rows="4"
                        required
                        class="form-textarea"
                        placeholder="Enter your remarks..."
                    />
                </div>
                <div>
                    <label class="form-choice">
                        <input v-model="completeForm.saleHappened" type="checkbox" class="form-checkbox" />
                        <span>Sale won (counts as sale; prospect becomes customer)</span>
                    </label>
                </div>
                <div v-if="completeForm.saleHappened">
                    <label class="form-label" for="customerleadview-new-stage">New Stage</label>
                    <select id="customerleadview-new-stage" v-model="completeForm.newStage" class="form-select">
                        <option value="won">Won</option>
                        <option value="quotation">Quotation</option>
                        <option value="hot_lead">Hot Lead</option>
                        <option value="lead">Lead</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="customerleadview-next-follow-up-date-optional">Next Follow-up Date (Optional)</label>
                    <input id="customerleadview-next-follow-up-date-optional"
                        v-model="completeForm.nextFollowUpAt"
                        type="datetime-local"
                        class="form-input"
                    />
                </div>
            </form>

            <template #actions>
                <BaseButton variant="outline" block-mobile :disabled="completingFollowUp" @click="closeCompleteModal">
                    Cancel
                </BaseButton>
                <BaseButton
                    variant="success"
                    type="submit"
                    form="complete-followup-form"
                    block-mobile
                    :loading="completingFollowUp"
                >
                    {{ completingFollowUp ? 'Saving...' : 'Complete' }}
                </BaseButton>
            </template>
        </BaseModal>

        <!-- Sale credit -->
        <BaseModal
            v-model="showSaleCreditModal"
            title="Sale Credit"
            :description="saleCreditContextText"
            size="md"
            @close="closeSaleCreditModal"
        >
            <div>
                <label class="form-label" for="customerleadview-select-user-for-this-sale">Select user for this sale</label>
                <select id="customerleadview-select-user-for-this-sale" v-model="selectedSaleCreditUserId" class="form-select">
                    <option value="">Select user...</option>
                    <option v-for="u in saleCreditUsers" :key="u.id" :value="String(u.id)">{{ u.name }} ({{ u.role?.name || '—' }})</option>
                </select>
            </div>

            <template #actions>
                <BaseButton variant="outline" block-mobile @click="closeSaleCreditModal">Cancel</BaseButton>
                <BaseButton variant="primary" block-mobile :disabled="!selectedSaleCreditUserId" @click="confirmSaleCreditSelection">
                    Confirm
                </BaseButton>
            </template>
        </BaseModal>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import FollowUpLeadForm from '@/components/FollowUpLeadForm.vue';
import LogActivityModal from '@/components/LogActivityModal.vue';
import TimelineSection from '@/components/TimelineSection.vue';
import WhatsAppComposer from '@/components/WhatsAppComposer.vue';
import EmailComposer from '@/components/EmailComposer.vue';
import SMSComposer from '@/components/SMSComposer.vue';
import CustomerAssignmentModal from '@/components/CustomerAssignmentModal.vue';
import { BaseBadge, BaseButton, BaseCard, BaseModal, EmptyState } from '@/components/base';
import {
    ArrowLeftIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    CheckIcon,
    ChevronDownIcon,
    ClipboardDocumentIcon,
    ClockIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import { formatLeadStage, formatLineItemStatus } from '@/utils/displayFormat';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();

const isLeadWorkspace = computed(() => route.meta.workspaceFromLead === true);
const resolvedCustomerId = ref(null);
const workspaceLeadId = ref(null);
const selectedLeadId = ref(null);
const activityModalInitialType = ref('call');
const showLostLeadModal = ref(false);
const lostReasonInput = ref('');
const stageUpdating = ref(false);

const effectiveCustomerId = computed(() => {
    if (isLeadWorkspace.value && resolvedCustomerId.value) {
        return String(resolvedCustomerId.value);
    }
    return route.params.id ? String(route.params.id) : '';
});

function pickRepresentativeLeadId(leads) {
    const list = (leads || []).filter(Boolean);
    if (!list.length) {
        return null;
    }
    const byUpdated = (a, b) => new Date(b.updated_at) - new Date(a.updated_at);
    const won = [...list].filter((l) => l.stage === 'won').sort(byUpdated)[0];
    if (won) {
        return won.id;
    }
    const lost = [...list].filter((l) => l.stage === 'lost').sort(byUpdated)[0];
    if (lost) {
        return lost.id;
    }
    const rank = { follow_up: 1, lead: 2, hot_lead: 3, quotation: 4 };
    return [...list].sort((a, b) => {
        const ra = rank[a.stage] ?? 0;
        const rb = rank[b.stage] ?? 0;
        if (ra !== rb) {
            return rb - ra;
        }
        return byUpdated(a, b);
    })[0]?.id ?? null;
}

const customer = ref(null);
const lead = ref(null);
const allLeads = ref([]);

const activeLead = computed(() => {
    const id = selectedLeadId.value;
    if (id && allLeads.value?.length) {
        const found = allLeads.value.find((l) => l.id === id);
        if (found) {
            return found;
        }
    }
    return lead.value;
});

const pipelineStageOrder = ['follow_up', 'lead', 'hot_lead', 'quotation', 'won', 'lost'];

function formatStagePipe(stage) {
    const map = {
        follow_up: 'Follow-up',
        lead: 'Lead',
        hot_lead: 'Hot',
        quotation: 'Quote',
        won: 'Won',
        lost: 'Lost',
    };
    return map[stage] || formatLeadStage(stage, '-');
}

function pipelineStageVisualClass(currentStage, segmentStage) {
    const order = pipelineStageOrder;
    const ci = order.indexOf(currentStage);
    const si = order.indexOf(segmentStage);
    if (ci < 0 || si < 0) {
        return 'bg-slate-100 text-slate-500';
    }
    if (si < ci) {
        return 'bg-success-600 text-white';
    }
    if (si === ci) {
        return 'bg-primary-600 text-white';
    }
    return 'bg-slate-200 text-slate-600';
}

/** History scoped to active lead (hide customer-level comms without lead_id when a lead is selected). */
const timelineForActiveLead = computed(() => {
    const items = timeline.value || [];
    const lid = activeLead.value?.id;
    if (!lid) {
        return items;
    }
    return items.filter((item) => {
        if (item.type === 'ticket') {
            return true;
        }
        if (item.lead_id === null || item.lead_id === undefined) {
            return false;
        }
        return Number(item.lead_id) === Number(lid);
    });
});

const tickets = ref([]);
const invoices = ref([]);
const timeline = ref([]);
const customerHasItems = ref([]);
const nextProducts = ref([]);
const activeLeads = ref([]);
const appointments = ref([]);
const workspaceMainTab = ref('focus');
const showLeadsPanel = ref(true);

const leadActivityPreset = ref('all');
const leadFilterSingleDate = ref('');
const leadFilterFrom = ref('');
const leadFilterTo = ref('');
const leadFilterUserId = ref('');
const filterEmployees = ref([]);
const saleCreditUsers = ref([]);
const showSaleCreditModal = ref(false);
const selectedSaleCreditUserId = ref('');
const saleCreditContextText = ref('Select who should get this sale.');
const saleCreditLeadId = ref(null);
const historyTimelineApi = ref(null);
const historyTimelineLoading = ref(false);
const matchingActivityLeadIds = ref(null);

const isAdminForFilter = computed(() => {
    const role = auth.user?.role?.name;
    return role === 'Admin' || role === 'Manager' || role === 'System Admin';
});

function formatLocalYmd(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function setActivityPreset(p) {
    leadActivityPreset.value = p;
    if (p !== 'single') {
        leadFilterSingleDate.value = '';
    }
    if (p !== 'range' && p !== 'single') {
        leadFilterFrom.value = '';
        leadFilterTo.value = '';
    }
}

function onSingleDayPicked() {
    if (leadFilterSingleDate.value) {
        leadActivityPreset.value = 'single';
        leadFilterFrom.value = '';
        leadFilterTo.value = '';
    }
}

function onRangeChanged() {
    if (leadFilterFrom.value || leadFilterTo.value) {
        leadActivityPreset.value = 'range';
        leadFilterSingleDate.value = '';
    }
}

function clearActivityFilters() {
    setActivityPreset('all');
    leadFilterUserId.value = '';
}

function getTimelineDateQuery() {
    const now = new Date();
    const p = leadActivityPreset.value;
    if (p === 'today') {
        return { on: formatLocalYmd(now) };
    }
    if (p === 'week') {
        const day = now.getDay();
        const diff = day === 0 ? -6 : 1 - day;
        const start = new Date(now);
        start.setDate(now.getDate() + diff);
        const end = new Date(start);
        end.setDate(start.getDate() + 6);
        return { from: formatLocalYmd(start), to: formatLocalYmd(end) };
    }
    if (p === 'month') {
        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        return { from: formatLocalYmd(start), to: formatLocalYmd(end) };
    }
    if (p === 'single' && leadFilterSingleDate.value) {
        return { on: leadFilterSingleDate.value };
    }
    if (p === 'range') {
        const q = {};
        if (leadFilterFrom.value) {
            q.from = leadFilterFrom.value;
        }
        if (leadFilterTo.value) {
            q.to = leadFilterTo.value;
        }
        return q;
    }
    return {};
}

const activityFilterActive = computed(() => {
    if (leadFilterUserId.value !== '' && leadFilterUserId.value != null) {
        return true;
    }
    const p = leadActivityPreset.value;
    if (p === 'all') {
        return false;
    }
    if (p === 'single') {
        return !!leadFilterSingleDate.value;
    }
    if (p === 'range') {
        return !!(leadFilterFrom.value || leadFilterTo.value);
    }
    return true;
});

function buildUnifiedTimelineParams(leadScoped) {
    const params = { ...getTimelineDateQuery() };
    if (leadFilterUserId.value !== '' && leadFilterUserId.value != null) {
        params.user_id = leadFilterUserId.value;
    }
    if (leadScoped && selectedLeadId.value) {
        params.lead_id = selectedLeadId.value;
    }
    return params;
}

async function fetchMatchingLeadIds() {
    const cid = effectiveCustomerId.value;
    if (!cid || !activityFilterActive.value) {
        matchingActivityLeadIds.value = null;
        return;
    }
    try {
        const { data } = await axios.get(`/api/customers/${cid}/unified-timeline`, {
            params: buildUnifiedTimelineParams(false),
        });
        matchingActivityLeadIds.value = data.matching_lead_ids || [];
    } catch {
        matchingActivityLeadIds.value = [];
    }
}

async function fetchHistoryTimeline() {
    const cid = effectiveCustomerId.value;
    if (!cid || workspaceMainTab.value !== 'history') {
        return;
    }
    historyTimelineLoading.value = true;
    try {
        const { data } = await axios.get(`/api/customers/${cid}/unified-timeline`, {
            params: buildUnifiedTimelineParams(true),
        });
        historyTimelineApi.value = Array.isArray(data.timeline) ? data.timeline : [];
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not load history');
        historyTimelineApi.value = null;
    } finally {
        historyTimelineLoading.value = false;
    }
}

const sidebarLeadsFiltered = computed(() => {
    const list = allLeads.value || [];
    const ids = matchingActivityLeadIds.value;
    if (!activityFilterActive.value || ids === null) {
        return list;
    }
    const set = new Set(ids);
    return list.filter((l) => set.has(l.id));
});

const displayHistoryTimeline = computed(() => {
    if (historyTimelineApi.value !== null) {
        return historyTimelineApi.value;
    }
    return timelineForActiveLead.value;
});

async function loadFilterEmployees() {
    if (!isAdminForFilter.value) {
        return;
    }
    try {
        const res = await axios.get('/api/users');
        filterEmployees.value = Array.isArray(res.data) ? res.data : res.data?.data || [];
    } catch {
        filterEmployees.value = [];
    }
}

async function loadSaleCreditUsers() {
    if (!isAdminRole.value) {
        return;
    }
    try {
        const res = await axios.get('/api/users');
        saleCreditUsers.value = Array.isArray(res.data) ? res.data : res.data?.data || [];
    } catch {
        saleCreditUsers.value = [];
    }
}

function openSaleCreditModal(contextText, leadId = null) {
    if (!isAdminRole.value) return;
    saleCreditContextText.value = contextText || 'Select who should get this sale.';
    saleCreditLeadId.value = leadId;
    selectedSaleCreditUserId.value = '';
    if (!saleCreditUsers.value.length) {
        loadSaleCreditUsers();
    }
    showSaleCreditModal.value = true;
}

function closeSaleCreditModal() {
    showSaleCreditModal.value = false;
    selectedSaleCreditUserId.value = '';
    saleCreditLeadId.value = null;
}

function openChangeSaleCreditForLead(leadObj) {
    if (!leadObj?.id || !isAdminRole.value) return;
    openSaleCreditModal(`Reassign sale credit for Lead #${leadObj.id}.`, leadObj.id);
}

async function confirmSaleCreditSelection() {
    const selected = saleCreditUsers.value.find((u) => String(u.id) === String(selectedSaleCreditUserId.value));
    if (!selected || !saleCreditLeadId.value) return;
    try {
        await axios.put(`/api/leads/${saleCreditLeadId.value}`, { assigned_to: selected.id });
        await axios.post(`/api/leads/${saleCreditLeadId.value}/activity`, {
            type: 'note',
            description: `Sale credited to ${selected.name} by ${auth.user?.name || 'Admin'}.`,
        });
        toast.success(`Sale will go on ${selected.name}.`);
        await loadData();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to save sale credit.');
        return;
    }
    closeSaleCreditModal();
}

/** Readable lead line for sidebar menu (products / deal). */
function leadSidebarTitle(lead) {
    if (!lead) {
        return 'Lead';
    }
    const items = lead.items || [];
    const names = items.map((i) => i.product?.name).filter(Boolean);
    const fromItems = names.length ? names.slice(0, 2).join(', ') : '';
    const primary = fromItems || lead.product?.name || '';
    if (primary) {
        return names.length > 2 ? `${primary} (+${names.length - 2} more)` : primary;
    }
    return `Deal #${lead.id}`;
}

function openLeadHistoryFromSidebar(l) {
    selectedLeadId.value = l.id;
    if (!isLeadWorkspace.value) {
        router.replace({ path: route.path, query: { ...route.query, lead: String(l.id) } });
    }
    workspaceMainTab.value = 'history';
    historyTimelineApi.value = null;
    fetchHistoryTimeline();
}

let historyRefreshTimer = null;
function scheduleHistoryRefresh() {
    clearTimeout(historyRefreshTimer);
    historyRefreshTimer = setTimeout(() => {
        fetchMatchingLeadIds();
        if (workspaceMainTab.value === 'history') {
            fetchHistoryTimeline();
        }
    }, 280);
}

watch(
    () => [
        effectiveCustomerId.value,
        workspaceMainTab.value,
        selectedLeadId.value,
        leadActivityPreset.value,
        leadFilterSingleDate.value,
        leadFilterFrom.value,
        leadFilterTo.value,
        leadFilterUserId.value,
    ],
    () => {
        scheduleHistoryRefresh();
    },
);

const appointmentsForActiveLead = computed(() => {
    const lid = activeLead.value?.id;
    if (!lid) {
        return [];
    }
    return (appointments.value || []).filter((a) => Number(a.lead_id) === Number(lid));
});

const showForm = ref(true);
const showCompleteModal = ref(false);
const completingFollowUp = ref(false);
const selectedFollowUp = ref(null);
const completeForm = ref({
    remarks: '',
    saleHappened: false,
    newStage: 'won',
    nextFollowUpAt: '',
});
const isAdminRole = computed(() => {
    const r = auth.user?.role?.name;
    return r === 'Admin' || r === 'System Admin' || r === 'Manager';
});
const showAssignmentModal = ref(false);
const communicationLogs = ref({ emails: [], sms: [], whatsapp: [] });

/** Refetch only message logs (lightweight) — used for WhatsApp replies without full page reload */
const loadCommunicationLogsOnly = async () => {
    const cid = effectiveCustomerId.value;
    if (!cid) {
        return;
    }
    try {
        const logsRes = await axios.get(`/api/customers/${cid}/communication-logs`);
        communicationLogs.value = logsRes.data || { emails: [], sms: [], whatsapp: [] };
    } catch {
        communicationLogs.value = { emails: [], sms: [], whatsapp: [] };
    }
};

let communicationLogsPollTimer = null;

function scheduleCommunicationLogsPolling() {
    if (communicationLogsPollTimer) {
        clearInterval(communicationLogsPollTimer);
    }
    communicationLogsPollTimer = setInterval(() => {
        if (document.visibilityState === 'visible' && effectiveCustomerId.value) {
            loadCommunicationLogsOnly();
        }
    }, 20000);
}

function onVisibilityRefreshLogs() {
    if (document.visibilityState === 'visible' && effectiveCustomerId.value) {
        loadCommunicationLogsOnly();
    }
}

// Close Item Modal
const showCloseItemModal = ref(false);
const closeItemLoading = ref(false);
const closeItemError = ref(null);
const closeItemData = ref({
    lead: null,
    item: null,
    status: 'won',
    quantity: 1,
    unit_price: 0,
    notes: '',
    lost_reason: '',
});

// Log Activity Modal
const showActivityModal = ref(false);
const activityLead = ref(null);

const openActivityModal = (leadObj) => {
    activityModalInitialType.value = 'call';
    activityLead.value = leadObj;
    showActivityModal.value = true;
};

const openScheduleModal = (leadObj) => {
    activityModalInitialType.value = 'appointment';
    activityLead.value = leadObj || activeLead.value;
    showActivityModal.value = true;
};

const closeActivityModal = () => {
    showActivityModal.value = false;
    activityLead.value = null;
};

const handleActivitySaved = () => {
    loadData();
    closeActivityModal();
};

const submitMarkLeadWon = async () => {
    const l = activeLead.value;
    if (!l || stageUpdating.value) {
        return;
    }
    stageUpdating.value = true;
    try {
        await axios.put(`/api/leads/${l.id}`, { stage: 'won' });
        toast.success('Lead marked as Won. Customer type updated if applicable.');
        await loadData();
        openSaleCreditModal('Lead marked as won.', l.id);
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not mark as Won. Add products to the lead first if required.');
    } finally {
        stageUpdating.value = false;
    }
};

const submitMarkLeadLost = async () => {
    const l = activeLead.value;
    if (!l || stageUpdating.value) {
        return;
    }
    if (!lostReasonInput.value.trim()) {
        toast.error('Please enter a lost reason.');
        return;
    }
    stageUpdating.value = true;
    try {
        await axios.put(`/api/leads/${l.id}`, { stage: 'lost', lost_reason: lostReasonInput.value.trim() });
        toast.success('Lead marked as Lost.');
        showLostLeadModal.value = false;
        lostReasonInput.value = '';
        await loadData();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to update lead.');
    } finally {
        stageUpdating.value = false;
    }
};

const formatAppointmentDate = (dateStr) => {
    if (!dateStr) return '—';
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
};
const getAppointmentStatusLabel = (s) => {
    const map = { pending: 'Pending', completed: 'Completed', cancelled: 'Cancelled', no_show: 'No show', rescheduled: 'Rescheduled' };
    return map[s] || s || 'Pending';
};
const openCompleteForAppointment = (apt) => {
    if (!apt?.lead_id) return;
    selectedFollowUp.value = { id: apt.lead_id };
    completeForm.value = { remarks: '', saleHappened: false, newStage: 'won', nextFollowUpAt: '' };
    showCompleteModal.value = true;
};

const closeCompleteModal = () => {
    showCompleteModal.value = false;
    selectedFollowUp.value = null;
};

const completeFollowUp = async () => {
    if (!selectedFollowUp.value?.id || completingFollowUp.value) return;
    completingFollowUp.value = true;
    try {
        const saleWon = completeForm.value.saleHappened && completeForm.value.newStage === 'won';
        const payload = {
            remarks: completeForm.value.remarks,
            sale_happened: completeForm.value.saleHappened,
            new_stage: completeForm.value.saleHappened ? completeForm.value.newStage : null,
        };
        if (completeForm.value.nextFollowUpAt) payload.next_follow_up_at = completeForm.value.nextFollowUpAt;
        await axios.post(`/api/leads/${selectedFollowUp.value.id}/complete-followup`, payload);
        closeCompleteModal();
        await loadData();
        toast.success(saleWon ? 'Appointment completed. Sale counted; prospect is now a customer.' : 'Completed.');
        if (saleWon) {
            openSaleCreditModal('Follow-up completed as won.', selectedFollowUp.value?.id || null);
        }
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to complete');
    } finally {
        completingFollowUp.value = false;
    }
};

// Prospect for = products from any non-won lead (follow_up, lead, hot_lead, quotation, lost). Purchased = only won.
const prospectProductNames = computed(() => {
    const names = new Set();
    (allLeads.value || []).filter(l => l && l.stage !== 'won').forEach(l => {
        if (l.items) l.items.forEach(i => { if (i.product?.name) names.add(i.product.name); });
        if (l.product?.name) names.add(l.product.name);
    });
    return [...names];
});

const purchasedProductNames = computed(() => {
    const names = new Set();
    (allLeads.value || []).filter(l => l && l.stage === 'won').forEach(l => {
        if (l.items) l.items.filter(i => i.status === 'won').forEach(i => { if (i.product?.name) names.add(i.product.name); });
    });
    return [...names];
});

const customerTypeLabel = computed(() => {
    const type = customer.value?.type;
    return type === 'customer' ? 'Customer' : 'Prospect';
});

const customersListRoute = computed(() => {
    const type = customer.value?.type === 'customer' ? 'customer' : 'prospect';
    return { path: '/customers', query: { type } };
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num);
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
};

const formatStage = (stage) => formatLeadStage(stage, '-');

const getItemStatusClass = (status) => {
    const classes = {
        pending: 'bg-warning-50 border border-warning-200',
        won: 'bg-success-50 border border-success-200',
        lost: 'bg-danger-50 border border-danger-200',
    };
    return classes[status] || 'bg-slate-50 border border-slate-200';
};

const openCloseItemModal = (leadItem, item, status) => {
    closeItemData.value = {
        lead: leadItem,
        item: item,
        status: status,
        quantity: item.quantity || 1,
        unit_price: item.unit_price || 0,
        notes: item.notes || '',
        lost_reason: '',
    };
    closeItemError.value = null;
    showCloseItemModal.value = true;
};

const confirmCloseItem = async () => {
    closeItemLoading.value = true;
    closeItemError.value = null;

    try {
        const wasWon = closeItemData.value.status === 'won';
        const productLabel = closeItemData.value.item?.product?.name || 'Selected product';
        const payload = {
            status: closeItemData.value.status,
        };

        if (closeItemData.value.status === 'won') {
            if (!closeItemData.value.quantity || closeItemData.value.quantity < 1) {
                closeItemError.value = 'Quantity is required';
                closeItemLoading.value = false;
                return;
            }
            if (closeItemData.value.unit_price < 0) {
                closeItemError.value = 'Unit price must be 0 or greater';
                closeItemLoading.value = false;
                return;
            }
            payload.quantity = closeItemData.value.quantity;
            payload.unit_price = closeItemData.value.unit_price;
            payload.notes = closeItemData.value.notes;
        } else {
            if (!closeItemData.value.lost_reason) {
                closeItemError.value = 'Lost reason is required';
                closeItemLoading.value = false;
                return;
            }
            payload.lost_reason = closeItemData.value.lost_reason;
        }

        await axios.post(`/api/leads/${closeItemData.value.lead.id}/items/${closeItemData.value.item.id}/close`, payload);
        
        toast.success(`Product marked as ${formatLineItemStatus(closeItemData.value.status)}!`);
        showCloseItemModal.value = false;
        await loadData();
        if (wasWon) {
            openSaleCreditModal(`Product won: ${productLabel}.`, closeItemData.value.lead?.id || null);
        }
    } catch (err) {
        closeItemError.value = err.response?.data?.message || 'Failed to close item. Please try again.';
        console.error('Error closing item:', err);
    } finally {
        closeItemLoading.value = false;
    }
};

async function resolveLeadWorkspace() {
    if (!isLeadWorkspace.value) {
        resolvedCustomerId.value = null;
        workspaceLeadId.value = null;
        return;
    }
    try {
        const { data } = await axios.get(`/api/leads/${route.params.id}`);
        resolvedCustomerId.value = data.customer_id;
        workspaceLeadId.value = data.id;
        selectedLeadId.value = data.id;
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Lead not found.');
        router.push('/leads/pipeline');
    }
}

const loadData = async () => {
        const cid = effectiveCustomerId.value;
        if (!cid) {
            return;
        }
        try {
            const { data } = await axios.get(`/api/customers/${cid}`);
            customer.value = data.customer;
            lead.value = data.lead;
            tickets.value = data.tickets || [];
            invoices.value = data.invoices || [];
            timeline.value = data.timeline || [];
            appointments.value = data.appointments || [];
            await loadCommunicationLogsOnly();
        
        // Use customer_has_items and next_products from the response if available
        if (data.customer_has_items) {
            customerHasItems.value = data.customer_has_items;
        }
        if (data.next_products) {
            nextProducts.value = data.next_products;
        }

        // Load all leads for this customer
        try {
            const leadsResponse = await axios.get(`/api/customers/${cid}/leads`);
            allLeads.value = leadsResponse.data || [];

            const qLead = route.query.lead ? parseInt(String(route.query.lead), 10) : NaN;
            if (!Number.isNaN(qLead) && allLeads.value.some((l) => l.id === qLead)) {
                selectedLeadId.value = qLead;
            } else if (isLeadWorkspace.value && workspaceLeadId.value) {
                selectedLeadId.value = workspaceLeadId.value;
            } else {
                const pick = pickRepresentativeLeadId(allLeads.value);
                selectedLeadId.value = pick;
            }

            // Get items from won leads — only WON items (exclude lost items)
            if (!data.customer_has_items || data.customer_has_items.length === 0) {
                customerHasItems.value = [];
                allLeads.value.forEach(leadItem => {
                    if (leadItem.stage === 'won' && leadItem.items) {
                        leadItem.items.filter(i => i.status === 'won').forEach(item => {
                            customerHasItems.value.push(item);
                        });
                    }
                });
            }

            // Get active leads (follow_up, lead, hot_lead, quotation)
            activeLeads.value = allLeads.value.filter(l => 
                l && ['follow_up', 'lead', 'hot_lead', 'quotation'].includes(l.stage)
            );
        } catch (err) {
            console.error('Error loading leads:', err);
            allLeads.value = [];
            activeLeads.value = [];
        }

        // Load next products to sell (if not already set from response)
        if (!data.next_products || data.next_products.length === 0) {
            try {
                const nextProductsResponse = await axios.get(`/api/customers/${cid}/next-products`);
                nextProducts.value = nextProductsResponse.data?.suggested_products || [];
            } catch (err) {
                console.error('Error loading next products:', err);
                nextProducts.value = [];
            }
        }

            historyTimelineApi.value = null;
            await fetchMatchingLeadIds();
            if (workspaceMainTab.value === 'history') {
                await fetchHistoryTimeline();
            }
    } catch (error) {
        console.error('Failed to load customer data:', error);
        if (error.response?.status === 404) {
            toast.error('Customer not found.');
            router.push({ path: '/customers', query: { type: 'prospect' } });
        } else if (error.response?.status === 403) {
            toast.error('You do not have access to this record.');
            router.push(isLeadWorkspace.value ? '/leads/pipeline' : { path: '/customers', query: { type: 'prospect' } });
        } else {
            console.error('Error details:', error.response?.data || error.message);
        }
    }
};

const openAssignmentModal = () => {
    showAssignmentModal.value = true;
};

const handleAssignmentComplete = () => {
    loadData();
};


const handleFormSaved = async () => {
    await loadData();
};

const handleMessageSent = async () => {
    await loadData();
};

const handleContactSaved = async () => {
    await loadData();
};

function onActiveLeadSelectChange() {
    const id = selectedLeadId.value;
    if (!id || isLeadWorkspace.value) {
        return;
    }
    router.replace({ path: route.path, query: { ...route.query, lead: String(id) } });
}

onMounted(async () => {
    if (isLeadWorkspace.value) {
        await resolveLeadWorkspace();
    }
    await loadFilterEmployees();
    if (isAdminRole.value) {
        await loadSaleCreditUsers();
    }
    await loadData();
    document.addEventListener('visibilitychange', onVisibilityRefreshLogs);
    scheduleCommunicationLogsPolling();
});

let skipInitialRouteWatch = true;
watch(
    () => route.params.id,
    async (newId, oldId) => {
        if (skipInitialRouteWatch) {
            skipInitialRouteWatch = false;
            return;
        }
        if (!newId || newId === oldId) {
            return;
        }
        if (isLeadWorkspace.value) {
            await resolveLeadWorkspace();
        }
        await loadData();
        loadCommunicationLogsOnly();
        scheduleCommunicationLogsPolling();
    }
);

onUnmounted(() => {
    document.removeEventListener('visibilitychange', onVisibilityRefreshLogs);
    if (communicationLogsPollTimer) {
        clearInterval(communicationLogsPollTimer);
        communicationLogsPollTimer = null;
    }
});
</script>
