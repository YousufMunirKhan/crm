<template>
    <div class="min-h-screen bg-slate-100 w-full min-w-0 overflow-x-hidden">
        <!-- Top Navigation Bar -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-20">
            <div class="px-4 sm:px-6 py-3 sm:py-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 min-w-0">
                        <router-link
                            :to="customersListRoute"
                            class="text-base sm:text-sm font-semibold sm:font-normal text-slate-600 hover:text-slate-900 flex items-center gap-1"
                        >
                            <svg class="w-4 h-4 sm:w-4 sm:h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                            {{ customerTypeLabel === 'Customer' ? 'Back to Customers' : 'Back to Prospects' }}
                        </router-link>
                        <nav class="text-base sm:text-sm font-semibold sm:font-normal text-slate-600 flex items-center gap-1 flex-wrap">
                            <router-link to="/" class="text-teal-600 hover:text-teal-700 font-medium">Dashboard</router-link>
                            <span class="text-slate-300">/</span>
                            <template v-if="isLeadWorkspace">
                                <router-link to="/leads/pipeline" class="hover:text-slate-900">Leads</router-link>
                                <span class="text-slate-300">/</span>
                                <span class="text-slate-700">#{{ route.params.id }}</span>
                                <span class="text-slate-300">/</span>
                            </template>
                            <template v-else>
                                <router-link :to="customersListRoute" class="hover:text-slate-900">{{ customerTypeLabel === 'Customer' ? 'Customers' : 'Prospects' }}</router-link>
                                <span class="text-slate-300">/</span>
                            </template>
                            <span class="text-slate-900 font-semibold sm:font-medium truncate">{{ customer?.name || 'Loading...' }}</span>
                        </nav>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 sm:gap-4 w-full sm:w-auto shrink-0">
                        <router-link
                            :to="`/customers/${customer?.id}/edit`"
                            class="px-3 sm:px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700 touch-manipulation text-center flex-1 sm:flex-initial min-w-[8rem]"
                        >
                            Edit Customer
                        </router-link>
                        <button
                            @click="logout"
                            class="px-3 sm:px-4 py-2 text-sm bg-[#7C3AED] text-white rounded-lg hover:bg-[#6d28d9] shadow-sm shadow-violet-600/25 touch-manipulation flex-1 sm:flex-initial min-w-[8rem] font-medium"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="max-w-[1600px] mx-auto px-3 sm:px-5 py-4 sm:py-5 w-full min-w-0">
            <!-- Deal header (pipeline-style) -->
            <div v-if="customer" class="bg-white rounded-lg border border-slate-200 shadow-sm mb-4 p-4 sm:p-5">
                <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <h1 class="text-2xl font-semibold text-slate-900 tracking-tight break-words">{{ customer.name }}</h1>
                        <p v-if="customer.business_name" class="text-sm text-slate-500 mt-0.5">{{ customer.business_name }}</p>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 mt-2 text-sm">
                            <a v-if="customer.phone" :href="'tel:' + customer.phone" class="text-sky-700 hover:underline font-medium">{{ customer.phone }}</a>
                            <a v-if="customer.email" :href="'mailto:' + customer.email" class="text-sky-700 hover:underline font-medium truncate max-w-full">{{ customer.email }}</a>
                            <span v-if="customer.city" class="text-slate-600">{{ customer.city }}</span>
                        </div>
                    </div>
                    <div v-if="activeLead" class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2 shrink-0">
                        <label class="sr-only" for="active-lead-select">Active lead</label>
                        <select
                            id="active-lead-select"
                            v-model.number="selectedLeadId"
                            class="px-3 py-2 text-sm border border-slate-300 rounded-md bg-white min-w-[11rem] focus:ring-2 focus:ring-sky-500 focus:border-sky-500"
                            @change="onActiveLeadSelectChange"
                        >
                            <option v-for="l in allLeads" :key="l.id" :value="l.id">Lead #{{ l.id }} · {{ formatStage(l.stage) }}</option>
                        </select>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="px-4 py-2 text-sm font-semibold rounded-md bg-[#22a06b] text-white hover:bg-[#1c8a5a] disabled:opacity-50 touch-manipulation" :disabled="stageUpdating || activeLead.stage === 'won'" @click="submitMarkLeadWon">Won</button>
                            <button type="button" class="px-4 py-2 text-sm font-semibold rounded-md bg-red-600 text-white hover:bg-red-700 disabled:opacity-50 touch-manipulation" :disabled="stageUpdating || activeLead.stage === 'lost'" @click="showLostLeadModal = true; lostReasonInput = ''">Lost</button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="px-3 py-2 text-sm font-medium rounded-md border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 touch-manipulation" @click="openScheduleModal(activeLead)">Schedule</button>
                            <button type="button" class="px-3 py-2 text-sm font-medium rounded-md border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 touch-manipulation" @click="openActivityModal(activeLead)">Log activity</button>
                        </div>
                    </div>
                    <p v-else class="text-sm text-slate-500 shrink-0">No lead yet — use <strong>Focus</strong> below to add products.</p>
                </div>
                <div v-if="activeLead" class="mt-4 flex rounded-md overflow-hidden border border-slate-200">
                    <div
                        v-for="st in pipelineStageOrder"
                        :key="st"
                        class="flex-1 min-w-0 text-center py-2.5 px-0.5 sm:px-2 text-[10px] sm:text-xs font-bold uppercase tracking-tight border-r border-white/25 last:border-r-0"
                        :class="pipelineStageVisualClass(activeLead.stage, st)"
                    >
                        {{ formatStagePipe(st) }}
                    </div>
                </div>
                <p v-if="activeLead?.assignee" class="text-xs text-slate-500 mt-3">Owner: <span class="font-medium text-slate-800">{{ activeLead.assignee.name }}</span></p>
            </div>

            <!-- Full-width on desktop: avoids 3-column grid inside a narrow sidebar -->
            <div v-if="customer" class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 sm:p-5 mb-4 lg:mb-5 w-full min-w-0">
                <h2 class="text-base font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">
                    {{ customerTypeLabel }} details
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-5 [word-break:break-word]">
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Name</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.name || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Business name</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.business_name || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Owner name</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.owner_name || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Phone number 1</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.phone || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Contact Person 2 Name</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.contact_person_2_name || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Contact Person 2 Phone</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.contact_person_2_phone || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Email</span><div class="font-medium text-slate-900 mt-0.5 break-all">{{ customer.email || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Secondary email</span><div class="font-medium text-slate-900 mt-0.5 break-all">{{ customer.email_secondary || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">WhatsApp number</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.whatsapp_number || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">SMS number</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.sms_number || '—' }}</div></div>
                    <div class="min-w-0 sm:col-span-2 lg:col-span-2 xl:col-span-2"><span class="text-xs text-slate-500 uppercase tracking-wide block">Address</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.address || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Postcode</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.postcode || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">City</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.city || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">VAT number</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.vat_number || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Source</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.source || lead?.source || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">AnyDesk / RustDesk</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.anydesk_rustdesk || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">EPOS type</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.epos_type || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Licence days</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.lic_days ?? '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Birthday</span><div class="font-medium text-slate-900 mt-0.5">{{ formatDate(customer.birthday) || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Category</span><div class="font-medium text-slate-900 mt-0.5 break-words">{{ customer.category || '—' }}</div></div>
                    <div class="min-w-0"><span class="text-xs text-slate-500 uppercase tracking-wide block">Created on</span><div class="font-medium text-slate-900 mt-0.5">{{ formatDate(customer.created_at) || '—' }}</div></div>
                    <div class="min-w-0 sm:col-span-2 lg:col-span-3 xl:col-span-4"><span class="text-xs text-slate-500 uppercase tracking-wide block">Assigned employees</span><div class="font-medium text-slate-900 mt-0.5 flex flex-wrap gap-1"><template v-if="customer.assigned_users?.length"><span v-for="u in customer.assigned_users" :key="u.id" class="inline-flex px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-sm">{{ u.name }}</span></template><span v-else>—</span></div></div>
                </div>
                <div v-if="customer.notes" class="mt-4 pt-4 border-t border-slate-200">
                    <span class="text-xs text-slate-500 uppercase tracking-wide block">Notes</span>
                    <div class="font-medium text-slate-900 mt-0.5 whitespace-pre-wrap break-words">{{ customer.notes }}</div>
                </div>
                <div v-if="activeLead?.expected_closing_date" class="mt-4 pt-4 border-t border-slate-200">
                    <span class="text-xs text-slate-500 uppercase tracking-wide block">Expected closing date</span>
                    <div class="font-medium text-slate-900 mt-0.5">{{ formatDate(activeLead.expected_closing_date) }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 lg:gap-5 items-start">
                <aside class="lg:col-span-4 xl:col-span-3 space-y-4 order-2 lg:order-1 min-w-0">
            <!-- Leads + activity filters → History for selected lead -->
            <div v-if="customer" class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                <button
                    type="button"
                    class="w-full flex items-center justify-between gap-3 px-4 sm:px-5 py-3.5 text-left hover:bg-slate-50/80 transition-colors touch-manipulation"
                    :aria-expanded="showLeadsPanel"
                    @click="showLeadsPanel = !showLeadsPanel"
                >
                    <div class="min-w-0">
                        <span class="text-base font-semibold text-slate-900">Leads &amp; activity</span>
                        <p class="text-xs text-slate-500 mt-0.5 truncate">Filter by time or person — click a lead for its timeline</p>
                    </div>
                    <svg class="w-5 h-5 text-slate-400 shrink-0 transition-transform" :class="showLeadsPanel ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>
                <div v-show="showLeadsPanel" class="px-4 sm:px-5 pb-4 border-t border-slate-100 space-y-3">
                    <div class="flex flex-wrap gap-1.5 pt-3">
                        <button type="button" class="px-2.5 py-1 text-xs font-semibold rounded-md border transition-colors touch-manipulation" :class="leadActivityPreset === 'all' ? 'border-sky-600 bg-sky-50 text-sky-900' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" @click="setActivityPreset('all')">All</button>
                        <button type="button" class="px-2.5 py-1 text-xs font-semibold rounded-md border transition-colors touch-manipulation" :class="leadActivityPreset === 'today' ? 'border-sky-600 bg-sky-50 text-sky-900' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" @click="setActivityPreset('today')">Today</button>
                        <button type="button" class="px-2.5 py-1 text-xs font-semibold rounded-md border transition-colors touch-manipulation" :class="leadActivityPreset === 'week' ? 'border-sky-600 bg-sky-50 text-sky-900' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" @click="setActivityPreset('week')">Week</button>
                        <button type="button" class="px-2.5 py-1 text-xs font-semibold rounded-md border transition-colors touch-manipulation" :class="leadActivityPreset === 'month' ? 'border-sky-600 bg-sky-50 text-sky-900' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'" @click="setActivityPreset('month')">Month</button>
                    </div>
                    <div class="grid grid-cols-1 gap-2">
                        <div>
                            <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">One day</label>
                            <input v-model="leadFilterSingleDate" type="date" class="mt-0.5 w-full text-sm border border-slate-300 rounded-md px-2 py-1.5" @change="onSingleDayPicked" />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">From</label>
                                <input v-model="leadFilterFrom" type="date" class="mt-0.5 w-full text-sm border border-slate-300 rounded-md px-2 py-1.5" @change="onRangeChanged" />
                            </div>
                            <div>
                                <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">To</label>
                                <input v-model="leadFilterTo" type="date" class="mt-0.5 w-full text-sm border border-slate-300 rounded-md px-2 py-1.5" @change="onRangeChanged" />
                            </div>
                        </div>
                        <div v-if="isAdminForFilter">
                            <label class="text-[11px] font-semibold text-slate-500 uppercase tracking-wide">Activity by</label>
                            <select v-model="leadFilterUserId" class="mt-0.5 w-full text-sm border border-slate-300 rounded-md px-2 py-1.5 bg-white">
                                <option value="">Anyone</option>
                                <option v-for="emp in filterEmployees" :key="emp.id" :value="String(emp.id)">{{ emp.name }}</option>
                            </select>
                        </div>
                        <p v-else class="text-[11px] text-slate-500">Managers and admins can filter by the person who logged an activity.</p>
                        <button type="button" class="text-xs text-sky-700 hover:text-sky-900 font-medium text-left" @click="clearActivityFilters">Clear filters</button>
                    </div>
                    <p v-if="allLeads.length === 0" class="text-sm text-slate-500 py-2">No leads yet for this {{ customerTypeLabel.toLowerCase() }}.</p>
                    <template v-else>
                        <p class="text-xs text-slate-500">
                            Showing <span class="font-semibold text-slate-700">{{ sidebarLeadsFiltered.length }}</span> of {{ allLeads.length }} leads
                            <span v-if="activityFilterActive">(with activity in the current filter)</span>
                        </p>
                        <div v-if="sidebarLeadsFiltered.length === 0" class="text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-md px-3 py-2">
                            No leads match this filter. Try <strong>All</strong> or widen the date range.
                        </div>
                        <nav aria-label="Leads for this customer" class="space-y-1 max-h-[min(420px,50vh)] overflow-y-auto -mx-1 px-1">
                            <button
                                v-for="l in sidebarLeadsFiltered"
                                :key="l.id"
                                type="button"
                                class="w-full text-left rounded-lg border px-3 py-2.5 transition-colors touch-manipulation"
                                :class="Number(selectedLeadId) === Number(l.id) ? 'border-sky-400 bg-sky-50/90 ring-1 ring-sky-200' : 'border-slate-200 bg-slate-50/50 hover:bg-slate-100/80'"
                                @click="openLeadHistoryFromSidebar(l)"
                            >
                                <div class="font-medium text-slate-900 text-sm leading-snug pr-1">{{ leadSidebarTitle(l) }}</div>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                    <span class="font-mono text-xs text-sky-700">#{{ l.id }}</span>
                                    <span class="inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-medium" :class="getStageClass(l.stage)">{{ formatStage(l.stage) }}</span>
                                </div>
                                <div class="text-[11px] text-slate-500 mt-1">Created {{ formatDate(l.created_at) || '—' }}<template v-if="l.creator?.name"> · {{ l.creator.name }}</template></div>
                                <div v-if="l.assignee?.name" class="text-[11px] text-slate-600">Owner: {{ l.assignee.name }}</div>
                            </button>
                        </nav>
                        <p class="text-[11px] text-slate-500">Click a lead for <strong>History</strong>: appointments, follow-ups, messages, notes.</p>
                    </template>
                </div>
            </div>

            <!-- Prospect for / Purchased (categorization) -->
            <div v-if="customer && (prospectProductNames.length > 0 || purchasedProductNames.length > 0)" class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 sm:p-5">
                <h2 class="text-base font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-200">Products</h2>
                <div v-if="prospectProductNames.length > 0" class="mb-3">
                    <span class="text-xs text-slate-500 uppercase tracking-wide">Prospect for</span>
                    <div class="font-medium text-slate-900 mt-0.5 flex flex-wrap gap-2">
                        <span v-for="name in prospectProductNames" :key="name" class="inline-flex px-2 py-1 rounded bg-amber-50 text-amber-800 text-sm">{{ name }}</span>
                    </div>
                </div>
                <div v-if="purchasedProductNames.length > 0">
                    <span class="text-xs text-slate-500 uppercase tracking-wide">Purchased</span>
                    <div class="font-medium text-slate-900 mt-0.5 flex flex-wrap gap-2">
                        <span v-for="name in purchasedProductNames" :key="name" class="inline-flex px-2 py-1 rounded bg-emerald-50 text-emerald-800 text-sm">{{ name }}</span>
                    </div>
                </div>
            </div>

            <!-- Assignment log (customer) -->
            <div v-if="customer?.assignments?.length" class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 sm:p-5">
                <h2 class="text-base font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-200">Assignment log</h2>
                <ul class="space-y-2">
                    <li v-for="a in customer.assignments" :key="a.id" class="text-sm text-slate-700">
                        Assigned to <strong>{{ a.user?.name || '—' }}</strong> by <strong>{{ (a.assignedBy && a.assignedBy.name) || '—' }}</strong> on {{ formatDate(a.assigned_at) }}
                        <span v-if="a.notes" class="text-slate-500"> — {{ a.notes }}</span>
                    </li>
                </ul>
            </div>

            <!-- What Customer / Prospect Has Section -->
            <div v-if="customerHasItems.length > 0" class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 sm:p-5">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">What {{ customerTypeLabel }} Has</h3>
                <div class="grid grid-cols-1 gap-3">
                    <div
                        v-for="item in customerHasItems"
                        :key="item.id"
                        class="border border-slate-200 rounded-lg p-4"
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
            </div>

            <!-- What to Sell Next Section -->
            <div v-if="nextProducts.length > 0" class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 sm:p-5">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">What to Sell Next</h3>
                <div class="grid grid-cols-1 gap-3">
                    <div
                        v-for="suggestion in nextProducts"
                        :key="suggestion.product.id"
                        class="border border-purple-200 rounded-lg p-4 bg-purple-50"
                    >
                        <div class="font-medium text-purple-900">{{ suggestion.product.name }}</div>
                        <div class="text-sm text-purple-700 mt-1">
                            Suggested because customer has: {{ suggestion.suggested_by }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar: tickets & invoices -->
            <div v-if="tickets.length > 0 || invoices.length > 0" class="bg-white rounded-lg border border-slate-200 shadow-sm p-4 sm:p-5 space-y-4">
                <div v-if="tickets.length > 0">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Tickets</h3>
                    <ul class="space-y-2">
                        <li v-for="t in tickets" :key="t.id" class="text-sm border border-slate-100 rounded-md p-2">
                            <div class="font-medium text-slate-900">{{ t.ticket_number }}</div>
                            <div class="text-slate-600 truncate">{{ t.subject }}</div>
                        </li>
                    </ul>
                </div>
                <div v-if="invoices.length > 0">
                    <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Invoices</h3>
                    <ul class="space-y-2">
                        <li v-for="inv in invoices" :key="inv.id" class="text-sm border border-slate-100 rounded-md p-2 flex justify-between gap-2">
                            <span class="font-medium text-slate-900">{{ inv.invoice_number }}</span>
                            <span class="text-slate-700">£{{ formatNumber(inv.total) }}</span>
                        </li>
                    </ul>
                </div>
            </div>
                </aside>

                <main class="lg:col-span-8 xl:col-span-9 space-y-4 order-1 lg:order-2 min-w-0">
                    <div class="bg-white rounded-lg border border-slate-200 shadow-sm overflow-hidden">
                        <nav class="flex border-b border-slate-200 text-sm font-semibold bg-slate-50/90" aria-label="Workspace">
                            <button type="button" class="flex-1 sm:flex-none px-4 py-3 border-b-2 transition-colors touch-manipulation min-h-[44px]" :class="workspaceMainTab === 'focus' ? 'border-sky-600 text-sky-900 bg-white' : 'border-transparent text-slate-600 hover:text-slate-900'" @click="workspaceMainTab = 'focus'">Focus</button>
                            <button type="button" class="flex-1 sm:flex-none px-4 py-3 border-b-2 transition-colors touch-manipulation min-h-[44px]" :class="workspaceMainTab === 'messages' ? 'border-sky-600 text-sky-900 bg-white' : 'border-transparent text-slate-600 hover:text-slate-900'" @click="workspaceMainTab = 'messages'">Messages</button>
                            <button type="button" class="flex-1 sm:flex-none px-4 py-3 border-b-2 transition-colors touch-manipulation min-h-[44px]" :class="workspaceMainTab === 'history' ? 'border-sky-600 text-sky-900 bg-white' : 'border-transparent text-slate-600 hover:text-slate-900'" @click="workspaceMainTab = 'history'">History</button>
                        </nav>
                        <div class="p-4 sm:p-5">
                            <div v-show="workspaceMainTab === 'focus'" class="space-y-5">
            <!-- Appointment timeline (active lead) -->
            <div>
                <h3 class="text-base font-semibold text-slate-900 mb-1">Appointments</h3>
                <p class="text-sm text-slate-500 mb-3">Scheduled visits for the selected lead. Use <strong>Schedule</strong> above or Log activity → Appointment.</p>
                <div v-if="appointmentsForActiveLead.length === 0" class="rounded-lg border border-dashed border-slate-200 bg-slate-50/80 py-6 sm:py-8 px-4 text-center text-sm text-slate-500">
                    No appointment scheduled. Click <strong>Schedule</strong> in the header to book one.
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="apt in appointmentsForActiveLead"
                        :key="apt.id"
                        class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 p-4 rounded-lg border"
                        :class="apt.appointment_status === 'completed' ? 'bg-green-50 border-green-100' : apt.appointment_status === 'cancelled' || apt.appointment_status === 'no_show' ? 'bg-slate-50 border-slate-200' : 'bg-amber-50 border-amber-100'"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-slate-900">{{ apt.description || 'Appointment' }}</div>
                            <div class="text-sm text-slate-600 mt-0.5">
                                {{ formatAppointmentDate(apt.appointment_date) }} at {{ apt.appointment_time || '10:00' }}
                            </div>
                            <div class="text-xs text-slate-500 mt-1">Lead #{{ apt.lead_id }}</div>
                            <div v-if="apt.assignee?.name" class="text-xs text-slate-500 mt-0.5">Assigned to: {{ apt.assignee.name }}</div>
                            <div v-if="apt.outcome_notes" class="text-sm text-slate-700 mt-2 p-2 bg-white/60 rounded">{{ apt.outcome_notes }}</div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
                            <span
                                class="px-2 py-1 rounded text-xs font-medium"
                                :class="getAppointmentStatusClass(apt.appointment_status)"
                            >
                                {{ getAppointmentStatusLabel(apt.appointment_status) }}
                            </span>
                            <router-link
                                :to="`/appointments/${apt.id}`"
                                class="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700"
                            >
                                View / Update
                            </router-link>
                            <button
                                @click="openCompleteForAppointment(apt)"
                                class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700"
                            >
                                Complete
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active leads & line items (all open leads) -->
            <div v-if="activeLeads.length > 0" class="rounded-lg border border-slate-200 bg-slate-50/50 p-4 sm:p-5">
                <h3 class="text-base font-semibold text-slate-900 mb-3">Leads &amp; products</h3>
                <div class="space-y-4">
                    <div
                        v-for="leadRow in activeLeads"
                        :key="leadRow.id"
                        class="border border-slate-200 rounded-lg p-4 bg-white"
                    >
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3 mb-3">
                            <div>
                                <div class="font-medium text-slate-900">
                                    Lead #{{ leadRow.id }}
                                </div>
                                <div class="text-sm text-slate-600 mt-1">
                                    Stage: <span :class="getStageClass(leadRow.stage)" class="px-2 py-1 rounded text-xs">
                                        {{ formatStage(leadRow.stage) }}
                                    </span>
                                </div>
                                <div v-if="leadRow.next_follow_up_at" class="text-sm text-amber-700 mt-1 font-medium">
                                    Next follow-up: {{ formatDate(leadRow.next_follow_up_at) }}
                                </div>
                                <div v-if="(leadRow.assignment_logs || leadRow.assignmentLogs)?.length" class="text-xs text-slate-500 mt-2">
                                    Assignment: <span v-for="(log, i) in (leadRow.assignment_logs || leadRow.assignmentLogs)" :key="log.id">
                                        {{ i ? '; ' : '' }}to {{ (log.new_assignee || log.newAssignee)?.name || '—' }} by {{ (log.assigned_by_user || log.assignedByUser)?.name || '—' }} on {{ formatDate(log.assigned_at) }}
                                    </span>
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="openActivityModal(leadRow)"
                                class="px-4 py-2 bg-sky-600 text-white text-sm rounded-md hover:bg-sky-700 flex items-center justify-center gap-2 w-full sm:w-auto"
                            >
                                Log activity
                            </button>
                        </div>
                        <div v-if="leadRow.items && leadRow.items.length > 0" class="mt-3 pt-3 border-t border-slate-100">
                            <div class="text-sm font-medium text-slate-700 mb-2">Products</div>
                            <div class="space-y-2">
                                <div
                                    v-for="item in leadRow.items"
                                    :key="item.id"
                                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-3 rounded-lg min-w-0"
                                    :class="getItemStatusClass(item.status)"
                                >
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <span v-if="item.status === 'won'" class="text-green-600">✓</span>
                                        <span v-else-if="item.status === 'lost'" class="text-red-600">✗</span>
                                        <span v-else class="text-amber-600">○</span>
                                        <div>
                                            <div class="font-medium text-slate-900">{{ item.product?.name }}</div>
                                            <div v-if="item.status === 'won'" class="text-xs text-slate-600">
                                                Qty: {{ item.quantity }} × £{{ parseFloat(item.unit_price || 0).toFixed(2) }} = £{{ parseFloat(item.total_price || 0).toFixed(2) }}
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="item.status === 'pending'" class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                                        <button
                                            type="button"
                                            @click="openCloseItemModal(leadRow, item, 'won')"
                                            class="px-3 py-1 text-xs bg-green-600 text-white rounded-md hover:bg-green-700 touch-manipulation"
                                        >
                                            Won
                                        </button>
                                        <button
                                            type="button"
                                            @click="openCloseItemModal(leadRow, item, 'lost')"
                                            class="px-3 py-1 text-xs bg-red-600 text-white rounded-md hover:bg-red-700 touch-manipulation"
                                        >
                                            Lost
                                        </button>
                                    </div>
                                    <span v-else class="text-xs px-2 py-1 rounded shrink-0" :class="item.status === 'won' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                        {{ formatLineItemStatus(item.status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <FollowUpLeadForm
                v-if="customer"
                :customer-id="customer.id"
                :existing-lead="activeLead"
                @saved="handleFormSaved"
                @cancel="showForm = false"
            />

                            </div>

                            <div v-show="workspaceMainTab === 'messages'" class="space-y-4">
            <div>
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
            </div>
                            </div>

                            <div v-show="workspaceMainTab === 'history'">
                                <p v-if="historyTimelineLoading" class="text-sm text-slate-500 mb-2">Updating history…</p>
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
        <div v-if="showCloseItemModal" class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl shadow-xl p-4 sm:p-6 max-w-md w-full max-h-[90vh] overflow-y-auto">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">
                    Close Product: {{ closeItemData.item?.product?.name }}
                </h3>

                <div v-if="closeItemData.status === 'won'" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Quantity *</label>
                        <input
                            v-model.number="closeItemData.quantity"
                            type="number"
                            min="1"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Unit Price (£) *</label>
                        <input
                            v-model.number="closeItemData.unit_price"
                            type="number"
                            step="0.01"
                            min="0"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                        <textarea
                            v-model="closeItemData.notes"
                            rows="2"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="Optional notes..."
                        />
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Lost Reason *</label>
                        <textarea
                            v-model="closeItemData.lost_reason"
                            rows="3"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500"
                            placeholder="Why was this product lost?"
                        />
                    </div>
                </div>

                <div v-if="closeItemError" class="mt-4 text-sm text-red-600 bg-red-50 p-3 rounded">
                    {{ closeItemError }}
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <button
                        type="button"
                        @click="showCloseItemModal = false"
                        class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="confirmCloseItem"
                        :disabled="closeItemLoading"
                        class="px-4 py-2 text-sm text-white rounded-lg"
                        :class="closeItemData.status === 'won' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'"
                    >
                        {{ closeItemLoading ? 'Saving...' : (closeItemData.status === 'won' ? 'Mark as Won' : 'Mark as Lost') }}
                    </button>
                </div>
            </div>
        </div>

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

        <div v-if="showLostLeadModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-2">Mark lead as Lost</h3>
                <p class="text-sm text-slate-600 mb-4">Please provide a reason. This is required for reporting.</p>
                <textarea
                    v-model="lostReasonInput"
                    rows="3"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-red-500"
                    placeholder="Lost reason..."
                />
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" class="px-4 py-2 text-sm border border-slate-300 rounded-lg" @click="showLostLeadModal = false">Cancel</button>
                    <button
                        type="button"
                        class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg disabled:opacity-50"
                        :disabled="stageUpdating"
                        @click="submitMarkLeadLost"
                    >
                        Save
                    </button>
                </div>
            </div>
        </div>

        <!-- Complete Follow-up / Appointment Modal -->
        <div v-if="showCompleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-slate-200">
                    <h3 class="text-xl font-semibold text-slate-900">Complete Appointment / Follow-up</h3>
                </div>
                <form @submit.prevent="completeFollowUp" class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Remarks / Notes *</label>
                        <textarea
                            v-model="completeForm.remarks"
                            rows="4"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter your remarks..."
                        />
                    </div>
                    <div>
                        <label class="flex items-center gap-2">
                            <input v-model="completeForm.saleHappened" type="checkbox" class="rounded border-slate-300 text-blue-600" />
                            <span class="text-sm text-slate-700">Sale won (counts as sale; prospect becomes customer)</span>
                        </label>
                    </div>
                    <div v-if="completeForm.saleHappened">
                        <label class="block text-sm font-medium text-slate-700 mb-2">New Stage</label>
                        <select v-model="completeForm.newStage" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="won">Won</option>
                            <option value="quotation">Quotation</option>
                            <option value="hot_lead">Hot Lead</option>
                            <option value="lead">Lead</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Next Follow-up Date (Optional)</label>
                        <input
                            v-model="completeForm.nextFollowUpAt"
                            type="datetime-local"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" @click="closeCompleteModal" :disabled="completingFollowUp" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 disabled:opacity-50">Cancel</button>
                        <button type="submit" :disabled="completingFollowUp" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50">
                            {{ completingFollowUp ? 'Saving...' : 'Complete' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
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
        return 'bg-emerald-500 text-white';
    }
    if (si === ci) {
        return 'bg-sky-600 text-white';
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
const getAppointmentStatusClass = (s) => {
    const map = { pending: 'bg-amber-100 text-amber-800', completed: 'bg-green-100 text-green-800', cancelled: 'bg-slate-100 text-slate-600', no_show: 'bg-red-100 text-red-800', rescheduled: 'bg-blue-100 text-blue-800' };
    return map[s] || 'bg-slate-100 text-slate-600';
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
        const payload = {
            remarks: completeForm.value.remarks,
            sale_happened: completeForm.value.saleHappened,
            new_stage: completeForm.value.saleHappened ? completeForm.value.newStage : null,
        };
        if (completeForm.value.nextFollowUpAt) payload.next_follow_up_at = completeForm.value.nextFollowUpAt;
        await axios.post(`/api/leads/${selectedFollowUp.value.id}/complete-followup`, payload);
        closeCompleteModal();
        await loadData();
        const saleWon = completeForm.value.saleHappened && completeForm.value.newStage === 'won';
        toast.success(saleWon ? 'Appointment completed. Sale counted; prospect is now a customer.' : 'Completed.');
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

const getStageClass = (stage) => {
    const classes = {
        follow_up: 'bg-blue-100 text-blue-800',
        lead: 'bg-green-100 text-green-800',
        hot_lead: 'bg-orange-100 text-orange-800',
        quotation: 'bg-purple-100 text-purple-800',
        won: 'bg-emerald-100 text-emerald-800',
        lost: 'bg-red-100 text-red-800',
    };
    return classes[stage] || 'bg-slate-100 text-slate-800';
};

const getStatusClass = (status) => {
    const classes = {
        open: 'bg-yellow-100 text-yellow-800',
        in_progress: 'bg-blue-100 text-blue-800',
        resolved: 'bg-green-100 text-green-800',
        closed: 'bg-slate-100 text-slate-800',
    };
    return classes[status] || 'bg-slate-100 text-slate-800';
};

const getItemStatusClass = (status) => {
    const classes = {
        pending: 'bg-amber-50 border border-amber-200',
        won: 'bg-green-50 border border-green-200',
        lost: 'bg-red-50 border border-red-200',
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

const logout = () => {
    auth.logout();
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
