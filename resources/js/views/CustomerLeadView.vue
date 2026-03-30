<template>
    <div class="min-h-screen bg-slate-50 w-full min-w-0 overflow-x-hidden">
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
                            <router-link to="/" class="hover:text-slate-900">Dashboard</router-link>
                            <span class="text-slate-300">/</span>
                            <router-link :to="customersListRoute" class="hover:text-slate-900">{{ customerTypeLabel === 'Customer' ? 'Customers' : 'Prospects' }}</router-link>
                            <span class="text-slate-300">/</span>
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
                            class="px-3 sm:px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 touch-manipulation flex-1 sm:flex-initial min-w-[8rem]"
                        >
                            Logout
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-3 sm:px-6 py-4 sm:py-6 w-full min-w-0">
            <!-- Customer Header (compact) -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 p-4 sm:p-5 mb-4 sm:mb-6">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3">
                    <div class="min-w-0">
                        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 break-words">{{ customer?.name }}</h1>
                        <p class="text-sm text-slate-500 mt-0.5">{{ customer?.business_name || '—' }}</p>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-sm text-slate-600">
                            <span v-if="customer?.email">{{ customer.email }}</span>
                            <span v-if="customer?.phone">{{ customer.phone }}</span>
                            <span v-if="customer?.city">{{ customer.city }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span
                            v-if="lead"
                            class="px-3 py-1 rounded-full text-xs font-medium"
                            :class="getStageClass(lead.stage)"
                        >
                            {{ formatStage(lead.stage) }}
                        </span>
                        <span v-else class="px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-600">
                            No Lead
                        </span>
                    </div>
                </div>
            </div>

            <!-- Full Customer / Prospect Details (all fields) -->
            <div v-if="customer" class="bg-white rounded-xl shadow-sm border border-slate-200/60 p-4 sm:p-6 mb-4 sm:mb-6">
                <h2 class="text-base font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">
                    {{ customerTypeLabel }} details
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Name</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.name || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Business name</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.business_name || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Owner name</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.owner_name || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Phone number 1</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.phone || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Contact Person 2 Name</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.contact_person_2_name || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Contact Person 2 Phone</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.contact_person_2_phone || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Email</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.email || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Secondary email</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.email_secondary || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">WhatsApp number</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.whatsapp_number || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">SMS number</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.sms_number || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Address</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.address || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Postcode</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.postcode || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">City</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.city || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">VAT number</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.vat_number || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Source</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.source || lead?.source || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">AnyDesk / RustDesk</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.anydesk_rustdesk || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">EPOS type</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.epos_type || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Licence days</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.lic_days ?? '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Birthday</span><div class="font-medium text-slate-900 mt-0.5">{{ formatDate(customer.birthday) || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Category</span><div class="font-medium text-slate-900 mt-0.5">{{ customer.category || '—' }}</div></div>
                    <div><span class="text-xs text-slate-500 uppercase tracking-wide">Created on</span><div class="font-medium text-slate-900 mt-0.5">{{ formatDate(customer.created_at) || '—' }}</div></div>
                    <div class="sm:col-span-2 lg:col-span-1"><span class="text-xs text-slate-500 uppercase tracking-wide">Assigned employees</span><div class="font-medium text-slate-900 mt-0.5 flex flex-wrap gap-1"><template v-if="customer.assigned_users?.length"><span v-for="u in customer.assigned_users" :key="u.id" class="inline-flex px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-sm">{{ u.name }}</span></template><span v-else>—</span></div></div>
                </div>
                <div v-if="customer.notes" class="mt-4 pt-4 border-t border-slate-200">
                    <span class="text-xs text-slate-500 uppercase tracking-wide">Notes</span>
                    <div class="font-medium text-slate-900 mt-0.5 whitespace-pre-wrap">{{ customer.notes }}</div>
                </div>
                <div v-if="lead?.expected_closing_date" class="mt-4 pt-4 border-t border-slate-200">
                    <span class="text-xs text-slate-500 uppercase tracking-wide">Expected closing date</span>
                    <div class="font-medium text-slate-900 mt-0.5">{{ formatDate(lead.expected_closing_date) }}</div>
                </div>
            </div>

            <!-- Prospect for / Purchased (categorization) -->
            <div v-if="customer && (prospectProductNames.length > 0 || purchasedProductNames.length > 0)" class="bg-white rounded-xl shadow-sm border border-slate-200/60 p-4 sm:p-6 mb-4 sm:mb-6">
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
            <div v-if="customer?.assignments?.length" class="bg-white rounded-xl shadow-sm border border-slate-200/60 p-4 sm:p-6 mb-4 sm:mb-6">
                <h2 class="text-base font-semibold text-slate-800 mb-3 pb-2 border-b border-slate-200">Assignment log</h2>
                <ul class="space-y-2">
                    <li v-for="a in customer.assignments" :key="a.id" class="text-sm text-slate-700">
                        Assigned to <strong>{{ a.user?.name || '—' }}</strong> by <strong>{{ (a.assignedBy && a.assignedBy.name) || '—' }}</strong> on {{ formatDate(a.assigned_at) }}
                        <span v-if="a.notes" class="text-slate-500"> — {{ a.notes }}</span>
                    </li>
                </ul>
            </div>

            <!-- What Customer / Prospect Has Section -->
            <div v-if="customerHasItems.length > 0" class="bg-white rounded-xl shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">What {{ customerTypeLabel }} Has</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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
            <div v-if="nextProducts.length > 0" class="bg-white rounded-xl shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">What to Sell Next</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
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

            <!-- Appointment timeline -->
            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-1">📅 View appointment timeline</h3>
                <p class="text-sm text-slate-500 mb-4">Appointments for this customer with status. You can complete or update an appointment from the Appointments page.</p>
                <div v-if="appointments.length === 0" class="text-center py-6 text-slate-400 text-sm">
                    No appointments yet. Use the form below to add one (Log Activity → Appointment).
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="apt in appointments"
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

            <!-- Active Follow-ups Section -->
            <div v-if="activeLeads.length > 0" class="bg-white rounded-xl shadow-sm p-4 sm:p-6 mb-4 sm:mb-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">Active Leads & Products</h3>
                <div class="space-y-4">
                    <div
                        v-for="activeLead in activeLeads"
                        :key="activeLead.id"
                        class="border border-slate-200 rounded-lg p-4"
                    >
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-3 mb-3">
                            <div>
                                <div class="font-medium text-slate-900">
                                    Lead #{{ activeLead.id }}
                                </div>
                                <div class="text-sm text-slate-600 mt-1">
                                    Stage: <span :class="getStageClass(activeLead.stage)" class="px-2 py-1 rounded text-xs">
                                        {{ formatStage(activeLead.stage) }}
                                    </span>
                                </div>
                                <div v-if="activeLead.next_follow_up_at" class="text-sm text-amber-600 mt-1 font-medium">
                                    📅 Next Follow-up: {{ formatDate(activeLead.next_follow_up_at) }}
                                </div>
                                <div v-if="(activeLead.assignment_logs || activeLead.assignmentLogs)?.length" class="text-xs text-slate-500 mt-2">
                                    Assignment: <span v-for="(log, i) in (activeLead.assignment_logs || activeLead.assignmentLogs)" :key="log.id">
                                        {{ i ? '; ' : '' }}to {{ (log.new_assignee || log.newAssignee)?.name || '—' }} by {{ (log.assigned_by_user || log.assignedByUser)?.name || '—' }} on {{ formatDate(log.assigned_at) }}
                                    </span>
                                </div>
                            </div>
                            <button
                                @click="openActivityModal(activeLead)"
                                class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 flex items-center justify-center gap-2 w-full sm:w-auto"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                Log Activity
                            </button>
                        </div>
                        
                        <!-- Products on this lead -->
                        <div v-if="activeLead.items && activeLead.items.length > 0" class="mt-3 pt-3 border-t border-slate-100">
                            <div class="text-sm font-medium text-slate-700 mb-2">Products:</div>
                            <div class="space-y-2">
                                <div
                                    v-for="item in activeLead.items"
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
                                            @click="openCloseItemModal(activeLead, item, 'won')"
                                            class="px-3 py-1 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700 touch-manipulation"
                                        >
                                            Won
                                        </button>
                                        <button
                                            @click="openCloseItemModal(activeLead, item, 'lost')"
                                            class="px-3 py-1 text-xs bg-red-600 text-white rounded-lg hover:bg-red-700 touch-manipulation"
                                        >
                                            Lost
                                        </button>
                                    </div>
                                    <span v-else class="text-xs px-2 py-1 rounded shrink-0" :class="item.status === 'won' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'">
                                        {{ item.status.toUpperCase() }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Close Item Modal -->
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
                            @click="showCloseItemModal = false"
                            class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
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

            <!-- Follow-up/Lead Form -->
            <FollowUpLeadForm
                v-if="customer"
                :customer-id="customer.id"
                :existing-lead="lead"
                @saved="handleFormSaved"
                @cancel="showForm = false"
            />

            <!-- Timeline Section -->
            <TimelineSection
                :timeline="timeline"
                class="mb-6"
            />

            <!-- Tickets & Invoices -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6">
                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 min-w-0">
                    <h3 class="text-lg font-semibold text-slate-700 mb-4">Tickets</h3>
                    <div v-if="tickets.length === 0" class="text-center py-8 text-slate-400 text-sm">
                        No tickets yet
                    </div>
                    <ul v-else class="space-y-3">
                        <li
                            v-for="t in tickets"
                            :key="t.id"
                            class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between p-3 bg-slate-50 rounded-lg min-w-0"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-slate-900">{{ t.ticket_number }}</div>
                                <div class="text-sm text-slate-600 mt-1 break-words">{{ t.subject }}</div>
                            </div>
                            <span class="text-xs px-2 py-1 rounded shrink-0 self-start" :class="getStatusClass(t.status)">
                                {{ t.status }}
                            </span>
                        </li>
                    </ul>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 min-w-0">
                    <h3 class="text-lg font-semibold text-slate-700 mb-4">Invoices</h3>
                    <div v-if="invoices.length === 0" class="text-center py-8 text-slate-400 text-sm">
                        No invoices yet
                    </div>
                    <ul v-else class="space-y-3">
                        <li
                            v-for="inv in invoices"
                            :key="inv.id"
                            class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between p-3 bg-slate-50 rounded-lg min-w-0"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-slate-900">{{ inv.invoice_number }}</div>
                                <div class="text-sm text-slate-600 mt-1">{{ formatDate(inv.invoice_date) }}</div>
                            </div>
                            <div class="text-left sm:text-right shrink-0">
                                <div class="font-medium text-slate-900">£{{ formatNumber(inv.total) }}</div>
                                <div class="text-xs text-slate-500 mt-1">{{ inv.status }}</div>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Send Messages: Email, SMS, WhatsApp (template + send + log) -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200/60 p-4 sm:p-6 mb-4 sm:mb-6">
                <h2 class="text-base font-semibold text-slate-800 mb-4 pb-2 border-b border-slate-200">Send messages</h2>
                <p class="text-sm text-slate-500 mb-4">Choose a template (or write your own), then send. All sent messages are logged below.</p>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <EmailComposer
                        v-if="customer"
                        :customer="customer"
                        :lead-id="lead?.id"
                        :logs="communicationLogs.emails"
                        @sent="handleMessageSent"
                        @saved="handleContactSaved"
                    />
                    <SMSComposer
                        v-if="customer"
                        :customer="customer"
                        :lead-id="lead?.id"
                        :logs="communicationLogs.sms"
                        @sent="handleMessageSent"
                        @saved="handleContactSaved"
                    />
                    <WhatsAppComposer
                        v-if="customer"
                        :customer="customer"
                        :lead-id="lead?.id"
                        :logs="communicationLogs.whatsapp"
                        @sent="handleMessageSent"
                        @saved="handleContactSaved"
                    />
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
            @close="closeActivityModal"
            @saved="handleActivitySaved"
        />

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
import { ref, computed, onMounted } from 'vue';
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

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const toast = useToastStore();

const customer = ref(null);
const lead = ref(null);
const allLeads = ref([]);
const tickets = ref([]);
const invoices = ref([]);
const timeline = ref([]);
const customerHasItems = ref([]);
const nextProducts = ref([]);
const activeLeads = ref([]);
const appointments = ref([]);
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
    activityLead.value = leadObj;
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

const formatStage = (stage) => {
    if (!stage) return '-';
    return stage.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};

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
        
        toast.success(`Product marked as ${closeItemData.value.status}!`);
        showCloseItemModal.value = false;
        await loadData();
    } catch (err) {
        closeItemError.value = err.response?.data?.message || 'Failed to close item. Please try again.';
        console.error('Error closing item:', err);
    } finally {
        closeItemLoading.value = false;
    }
};

const loadData = async () => {
        try {
            const { data } = await axios.get(`/api/customers/${route.params.id}`);
            customer.value = data.customer;
            lead.value = data.lead;
            tickets.value = data.tickets || [];
            invoices.value = data.invoices || [];
            timeline.value = data.timeline || [];
            appointments.value = data.appointments || [];
            try {
                const logsRes = await axios.get(`/api/customers/${route.params.id}/communication-logs`);
                communicationLogs.value = logsRes.data || { emails: [], sms: [], whatsapp: [] };
            } catch (_) {
                communicationLogs.value = { emails: [], sms: [], whatsapp: [] };
            }
        
        // Use customer_has_items and next_products from the response if available
        if (data.customer_has_items) {
            customerHasItems.value = data.customer_has_items;
        }
        if (data.next_products) {
            nextProducts.value = data.next_products;
        }

        // Load all leads for this customer
        try {
            const leadsResponse = await axios.get(`/api/customers/${route.params.id}/leads`);
            allLeads.value = leadsResponse.data || [];

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
                const nextProductsResponse = await axios.get(`/api/customers/${route.params.id}/next-products`);
                nextProducts.value = nextProductsResponse.data?.suggested_products || [];
            } catch (err) {
                console.error('Error loading next products:', err);
                nextProducts.value = [];
            }
        }
    } catch (error) {
        console.error('Failed to load customer data:', error);
        if (error.response?.status === 404) {
            toast.error('Customer not found.');
            router.push({ path: '/customers', query: { type: 'prospect' } });
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

onMounted(loadData);
</script>
