<template>
    <div class="max-w-7xl mx-auto w-full min-w-0 p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
        <!-- Attendance & Stats Row (no Revenue) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
            <div class="min-w-0">
                <AttendanceClock />
            </div>
            <div class="lg:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 min-w-0">
                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 md:p-6 min-w-0">
                    <div class="text-sm text-slate-600 mb-1">Today's Active Leads</div>
                    <div class="text-2xl font-bold text-slate-900 tabular-nums">{{ stats.daily?.leads || 0 }}</div>
                    <div class="text-xs text-slate-500 mt-1">Won: {{ stats.daily?.won || 0 }}</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 md:p-6 min-w-0">
                    <div class="text-sm text-slate-600 mb-1">Monthly Active Leads</div>
                    <div class="text-2xl font-bold text-slate-900 tabular-nums">{{ stats.monthly?.leads || 0 }}</div>
                    <div class="text-xs text-slate-500 mt-1">Won: {{ stats.monthly?.won || 0 }}</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 md:p-6 min-w-0">
                    <div class="text-sm text-slate-600 mb-1">Yearly Active Leads</div>
                    <div class="text-2xl font-bold text-slate-900 tabular-nums">{{ stats.yearly?.leads || 0 }}</div>
                    <div class="text-xs text-slate-500 mt-1">Won: {{ stats.yearly?.won || 0 }}</div>
                </div>
            </div>
        </div>

        <!-- My Targets (if admin has set them for this month) -->
        <div
            v-if="myTarget"
            class="bg-gradient-to-r from-sky-50 via-white to-emerald-50 border border-sky-100 rounded-2xl shadow-sm px-4 py-4 md:px-6 md:py-5 flex flex-col gap-4"
        >
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-sky-100 flex items-center justify-center text-sky-700">
                        🎯
                    </div>
                    <div>
                        <h3 class="text-sm md:text-base font-semibold text-slate-900">My Targets (this month)</h3>
                        <p class="text-[11px] md:text-xs text-slate-500">
                            Set by your admin. Updated automatically from your appointments and won sales.
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 sm:gap-3 text-[11px] md:text-xs text-slate-500">
                    <div class="px-2 py-1 rounded-full bg-white/70 border border-slate-200 flex items-center gap-1">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>{{ myTarget.achieved_appointments }} / {{ myTarget.target_appointments || 0 }} appointments</span>
                    </div>
                    <div class="px-2 py-1 rounded-full bg-white/70 border border-slate-200 flex items-center gap-1">
                        <span class="inline-block w-2 h-2 rounded-full bg-indigo-500"></span>
                        <span>{{ myTarget.achieved_sales }} / {{ myTarget.target_sales || 0 }} sales</span>
                    </div>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between items-center text-xs text-slate-500">
                    <span>Overall progress</span>
                    <span class="font-medium text-slate-700">{{ myTarget.appointment_progress }}%</span>
                </div>
                <div class="w-full bg-slate-200/80 rounded-full h-2.5 overflow-hidden">
                    <div
                        class="h-2.5 rounded-full bg-gradient-to-r from-emerald-500 via-emerald-400 to-emerald-600 transition-all duration-500"
                        :style="{ width: `${myTarget.appointment_progress}%` }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Monthly #1 Spotlight (all users) -->
        <div
            v-if="monthlyTopPerformer"
            class="bg-gradient-to-r from-amber-50 via-white to-emerald-50 border border-amber-100 rounded-2xl shadow-sm px-4 py-4 md:px-6 md:py-5 min-w-0"
        >
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 min-w-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-amber-500 text-white flex items-center justify-center font-bold">1</div>
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-semibold mb-1">
                            🏅 Top Performer of the Month
                        </div>
                        <div class="text-lg font-bold text-slate-900">{{ monthlyTopPerformer.name }}</div>
                        <div class="text-xs text-slate-600">
                            {{ monthlyTopPerformer.leads_count || 0 }} leads •
                            {{ monthlyTopPerformer.won_products || monthlyTopPerformer.won_count || 0 }} won
                        </div>
                    </div>
                </div>
                <div class="text-left sm:text-right text-sm w-full sm:w-auto shrink-0">
                    <div class="font-bold text-emerald-600 break-words">£{{ formatNumber(monthlyTopPerformer.revenue || 0) }}</div>
                    <div class="text-xs text-slate-500">{{ monthlyTopPerformer.conversion_rate || 0 }}% conv.</div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="flex flex-col sm:flex-row flex-wrap gap-2 sm:gap-3">
            <router-link
                :to="{ path: '/customers', query: { type: 'prospect' } }"
                class="w-full sm:w-auto text-center px-4 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-sm touch-manipulation"
            >
                Add Customer
            </router-link>
            <router-link
                to="/leads/pipeline"
                class="w-full sm:w-auto text-center px-4 py-2.5 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm touch-manipulation"
            >
                Add Lead
            </router-link>
        </div>

        <!-- Follow-ups & Appointments Section -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <!-- Date Picker & Tabs -->
            <div class="p-4 border-b border-slate-200 bg-slate-50">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 min-w-0">
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 min-w-0 w-full sm:w-auto">
                        <label class="text-sm font-medium text-slate-700 shrink-0">View date:</label>
                        <input
                            v-model="selectedDate"
                            type="date"
                            @change="onDateChange"
                            class="w-full sm:w-auto min-w-0 max-w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        <button
                            v-if="selectedDate !== todayStr"
                            @click="resetDate"
                            class="px-3 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg"
                        >
                            Today
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                        <button
                            @click="activeTab = 'today'"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                                activeTab === 'today'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-slate-200 text-slate-700 hover:bg-slate-300'
                            ]"
                        >
                            Today
                        </button>
                        <button
                            @click="activeTab = 'next7'"
                            :class="[
                                'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                                activeTab === 'next7'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-slate-200 text-slate-700 hover:bg-slate-300'
                            ]"
                        >
                            Next 7 Days
                        </button>
                    </div>
                </div>
            </div>

            <div class="p-4 md:p-6">
                <!-- Today / Selected Date Follow-ups -->
                <div v-show="activeTab === 'today'" class="space-y-6">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 mb-3 flex items-center gap-2">
                            <span class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">📅</span>
                            {{ selectedDate === todayStr ? "Today's Follow-ups" : `Follow-ups for ${formatSelectedDateLabel(selectedDate)}` }}
                        </h3>
                        <div v-if="loading" class="text-center py-8 text-slate-500">Loading...</div>
                        <div v-else-if="displayFollowUpsToday.length === 0" class="text-center py-8 text-slate-400 rounded-lg bg-slate-50">
                            <span>{{ selectedDate === todayStr ? 'No follow-ups scheduled for today' : 'No follow-ups for this date' }}</span>
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="fu in displayFollowUpsToday"
                                :key="fu.id"
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-4 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors group min-w-0"
                            >
                                <div class="flex-1 min-w-0">
                                    <router-link
                                        v-if="fu.customer_id"
                                        :to="`/customers/${fu.customer_id}`"
                                        class="font-medium text-slate-900 text-blue-600 hover:text-blue-800 hover:underline"
                                    >
                                        {{ fu.customer?.name || 'Customer' }}
                                    </router-link>
                                    <div v-else class="font-medium text-slate-900">
                                        {{ fu.customer?.name || 'Customer' }}
                                    </div>
                                    <div class="text-sm text-slate-600 mt-0.5">
                                        {{ fu.items?.length ? fu.items.map(i => i.product?.name).filter(Boolean).join(', ') : '-' }}
                                    </div>
                                    <div class="text-xs text-slate-500 mt-1">{{ formatDateTime(fu.next_follow_up_at) }}</div>
                                </div>
                                <div class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                                    <button
                                        @click="openActivityModal(fu)"
                                        class="px-3 py-1.5 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700 touch-manipulation"
                                    >
                                        Log Activity
                                    </button>
                                    <button
                                        @click="openCompleteModal(fu)"
                                        class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 touch-manipulation"
                                    >
                                        Mark as Done
                                    </button>
                                    <router-link
                                        v-if="fu.customer_id"
                                        :to="`/customers/${fu.customer_id}`"
                                        class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 touch-manipulation"
                                    >
                                        View
                                    </router-link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Today's Appointments -->
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900 mb-3 flex items-center gap-2">
                            <span class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center">📆</span>
                            Today's Appointments
                        </h3>
                        <div v-if="todayAppointments.length === 0" class="text-center py-6 text-slate-400 rounded-lg bg-slate-50">
                            No appointments scheduled for today
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="apt in todayAppointments"
                                :key="apt.id"
                                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-4 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors min-w-0"
                            >
                                <div class="flex-1 min-w-0">
                                    <router-link
                                        v-if="apt.customer_id"
                                        :to="`/customers/${apt.customer_id}`"
                                        class="font-medium text-slate-900 text-blue-600 hover:text-blue-800 hover:underline"
                                    >
                                        {{ apt.customer?.name || 'Customer' }}
                                    </router-link>
                                    <div v-else class="font-medium text-slate-900">
                                        {{ apt.customer?.name || 'Customer' }}
                                    </div>
                                    <div class="text-sm text-slate-600 mt-0.5">{{ apt.description || 'Appointment' }}</div>
                                    <div class="text-xs text-slate-500 mt-1">{{ apt.appointment_time || '10:00' }}</div>
                                </div>
                                <div class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                                    <button
                                        v-if="apt.lead_id"
                                        @click="openCompleteModal({ id: apt.lead_id })"
                                        class="px-3 py-1.5 text-sm bg-green-600 text-white rounded-lg hover:bg-green-700 touch-manipulation"
                                    >
                                        Complete
                                    </button>
                                    <router-link
                                        v-if="apt.customer_id"
                                        :to="`/customers/${apt.customer_id}`"
                                        class="px-3 py-1.5 text-sm bg-amber-600 text-white rounded-lg hover:bg-amber-700 touch-manipulation"
                                    >
                                        View
                                    </router-link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Next 7 Days Follow-ups -->
                <div v-show="activeTab === 'next7'" class="space-y-4">
                    <h3 class="text-lg font-semibold text-slate-900 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-emerald-100 flex items-center justify-center">📋</span>
                        Follow-ups (Next 7 Days)
                    </h3>
                    <div v-if="next7DaysFollowUps.length === 0" class="text-center py-12 text-slate-400 rounded-lg bg-slate-50">
                        No follow-ups scheduled in the next 7 days
                    </div>
                    <div v-else class="space-y-3 max-h-96 overflow-y-auto">
                        <div
                            v-for="fu in next7DaysFollowUps"
                            :key="fu.id"
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-4 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors group min-w-0"
                        >
                                <div class="flex-1 min-w-0">
                                    <router-link
                                        v-if="fu.customer_id"
                                        :to="`/customers/${fu.customer_id}`"
                                        class="font-medium text-slate-900 text-blue-600 hover:text-blue-800 hover:underline"
                                    >
                                        {{ fu.customer?.name || 'Customer' }}
                                    </router-link>
                                    <div v-else class="font-medium text-slate-900">
                                        {{ fu.customer?.name || 'Customer' }}
                                    </div>
                                <div class="text-sm text-slate-600 mt-0.5">
                                    {{ fu.items?.length ? fu.items.map(i => i.product?.name).filter(Boolean).join(', ') : '-' }}
                                </div>
                                <div class="text-xs text-slate-500 mt-1 flex items-center gap-2">
                                    <span class="font-medium">{{ formatDateOnly(fu.next_follow_up_at) }}</span>
                                    {{ formatTimeOnly(fu.next_follow_up_at) }}
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                                <button
                                    @click="openActivityModal(fu)"
                                    class="opacity-100 sm:opacity-0 sm:group-hover:opacity-100 px-3 py-1.5 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-opacity touch-manipulation"
                                >
                                    Log
                                </button>
                                <router-link
                                    v-if="fu.customer_id"
                                    :to="`/customers/${fu.customer_id}`"
                                    class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 touch-manipulation"
                                >
                                    View
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Leads & Assigned Customers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm p-4 md:p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">Recent Leads</h3>
                <div v-if="recentLeads.length === 0" class="text-center text-slate-500 py-8">
                    No recent leads
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="lead in recentLeads"
                        :key="lead.id"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-3 bg-slate-50 rounded-lg group min-w-0"
                    >
                        <div class="flex-1 min-w-0">
                            <router-link
                                v-if="lead.customer_id"
                                :to="`/customers/${lead.customer_id}`"
                                class="font-medium text-slate-900 text-blue-600 hover:text-blue-800 hover:underline"
                            >
                                {{ lead.customer?.name || 'Customer' }}
                            </router-link>
                            <div v-else class="font-medium text-slate-900">
                                {{ lead.customer?.name || 'Customer' }}
                            </div>
                            <div class="text-xs text-slate-500">{{ lead.stage }} • {{ lead.items?.length ? lead.items.map(i => i.product?.name).filter(Boolean).join(', ') : '-' }}</div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:gap-3 shrink-0 sm:justify-end">
                            <div class="text-sm font-medium text-slate-700 tabular-nums">£{{ formatNumber(getLeadValue(lead)) }}</div>
                            <button
                                @click="openActivityModal(lead)"
                                class="opacity-100 sm:opacity-0 sm:group-hover:opacity-100 px-2 py-1.5 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition-opacity touch-manipulation"
                            >
                                Log
                            </button>
                            <router-link
                                v-if="lead.customer_id"
                                :to="`/customers/${lead.customer_id}`"
                                class="opacity-100 sm:opacity-0 sm:group-hover:opacity-100 px-2 py-1.5 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 transition-opacity touch-manipulation"
                            >
                                View
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 min-w-0">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">Assigned Customers</h3>
                <div v-if="assignedCustomers.length === 0" class="text-center text-slate-500 py-8">
                    No assigned customers
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="customer in assignedCustomers"
                        :key="customer.id"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-3 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors min-w-0"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-slate-900 break-words">{{ customer.name }}</div>
                            <div class="text-xs text-slate-500">{{ customer.phone }}</div>
                        </div>
                        <router-link
                            :to="`/customers/${customer.id}`"
                            class="w-full sm:w-auto text-center px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 touch-manipulation shrink-0"
                        >
                            View
                        </router-link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pipeline Summary -->
        <div class="bg-white rounded-xl shadow-sm p-4 md:p-6 min-w-0">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Pipeline Summary</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
                <div class="text-center p-2 sm:p-3 bg-slate-50 rounded-lg min-w-0">
                    <div class="text-xl sm:text-2xl font-bold text-slate-900 tabular-nums">{{ pipeline.follow_up || 0 }}</div>
                    <div class="text-xs text-slate-600 mt-1">Follow Up</div>
                </div>
                <div class="text-center p-2 sm:p-3 bg-slate-50 rounded-lg min-w-0">
                    <div class="text-xl sm:text-2xl font-bold text-slate-900 tabular-nums">{{ pipeline.lead || 0 }}</div>
                    <div class="text-xs text-slate-600 mt-1">Lead</div>
                </div>
                <div class="text-center p-2 sm:p-3 bg-slate-50 rounded-lg min-w-0">
                    <div class="text-xl sm:text-2xl font-bold text-slate-900 tabular-nums">{{ pipeline.hot_lead || 0 }}</div>
                    <div class="text-xs text-slate-600 mt-1">Hot Lead</div>
                </div>
                <div class="text-center p-2 sm:p-3 bg-slate-50 rounded-lg min-w-0">
                    <div class="text-xl sm:text-2xl font-bold text-slate-900 tabular-nums">{{ pipeline.quotation || 0 }}</div>
                    <div class="text-xs text-slate-600 mt-1">Quotation</div>
                </div>
                <div class="text-center p-2 sm:p-3 bg-green-50 rounded-lg min-w-0">
                    <div class="text-xl sm:text-2xl font-bold text-green-700 tabular-nums">{{ pipeline.won || 0 }}</div>
                    <div class="text-xs text-green-600 mt-1">Won</div>
                </div>
                <div class="text-center p-2 sm:p-3 bg-red-50 rounded-lg min-w-0">
                    <div class="text-xl sm:text-2xl font-bold text-red-700 tabular-nums">{{ pipeline.lost || 0 }}</div>
                    <div class="text-xs text-red-600 mt-1">Lost</div>
                </div>
            </div>
        </div>

        <!-- Complete Follow-up Modal -->
        <div
            v-if="showCompleteModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="closeCompleteModal"
        >
            <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-slate-200">
                    <h3 class="text-xl font-semibold text-slate-900">Complete Follow-up</h3>
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
                            <span class="text-sm text-slate-700">Sale happened</span>
                        </label>
                    </div>
                    <div v-if="completeForm.saleHappened">
                        <label class="block text-sm font-medium text-slate-700 mb-2">New Stage</label>
                        <select v-model="completeForm.newStage" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="lead">Lead</option>
                            <option value="hot_lead">Hot Lead</option>
                            <option value="quotation">Quotation</option>
                            <option value="won">Won</option>
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
                        <button type="button" @click="closeCompleteModal" :disabled="completingFollowUp" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 disabled:opacity-50">
                            Cancel
                        </button>
                        <button type="submit" :disabled="completingFollowUp" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50">
                            {{ completingFollowUp ? 'Saving...' : 'Complete Follow-up' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <LogActivityModal
            v-if="showActivityModal && activityLead"
            :lead="activityLead"
            @close="closeActivityModal"
            @saved="handleActivitySaved"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';
import AttendanceClock from '@/components/AttendanceClock.vue';
import LogActivityModal from '@/components/LogActivityModal.vue';

const toast = useToastStore();
const auth = useAuthStore();
const stats = ref({});
const recentLeads = ref([]);
const assignedCustomers = ref([]);
const pipeline = ref({});
const todayFollowUps = ref([]);
const next7DaysFollowUps = ref([]);
const todayAppointments = ref([]);
const employeeTargets = ref([]);
const monthlyTopPerformer = ref(null);
const myTarget = computed(() => {
    const userId = auth.user?.id;
    if (!userId) return null;
    return employeeTargets.value.find((t) => t.user_id === userId) || null;
});
const followUpsByDate = ref([]);
const loading = ref(false);
const showActivityModal = ref(false);
const showCompleteModal = ref(false);
const completingFollowUp = ref(false);
const activityLead = ref(null);
const selectedFollowUp = ref(null);
const activeTab = ref('today');
const selectedDate = ref('');
const completeForm = ref({
    remarks: '',
    saleHappened: false,
    newStage: 'lead',
    nextFollowUpAt: '',
});

const todayStr = computed(() => {
    const d = new Date();
    return d.toISOString().split('T')[0];
});

const displayFollowUpsToday = computed(() => {
    if (selectedDate.value && selectedDate.value !== todayStr.value) {
        return followUpsByDate.value;
    }
    return todayFollowUps.value;
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num || 0);
};

const num = (v) => {
    if (v === null || v === undefined || v === '') return 0;
    if (typeof v === 'number') return Number.isFinite(v) ? v : 0;
    const n = parseFloat(String(v).replace(/,/g, ''));
    return Number.isFinite(n) ? n : 0;
};

const normalizeAgentsList = (payload) => {
    if (Array.isArray(payload)) return payload;
    if (payload && Array.isArray(payload.data)) return payload.data;
    return [];
};

const formatDateTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatDateOnly = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
};

const formatTimeOnly = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};

const formatSelectedDateLabel = (dateStr) => {
    if (!dateStr) return '';
    return new Date(dateStr + 'T12:00:00').toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'short', year: 'numeric' });
};

const getLeadValue = (lead) => {
    if (lead.stage === 'won' && lead.items?.length > 0) {
        const itemsTotal = lead.items.reduce((sum, item) => sum + (parseFloat(item.total_price) || 0), 0);
        return itemsTotal > 0 ? itemsTotal : (lead.pipeline_value || 0);
    }
    return lead.pipeline_value || 0;
};

const loadDashboard = async (dateParam = null) => {
    loading.value = true;
    try {
        const params = dateParam ? { date: dateParam } : {};

        // Targets/achievements for sales agents should always be based on a calendar month.
        // Use the month of the selected date if provided, otherwise the current month.
        const baseDate = dateParam ? new Date(dateParam) : new Date();
        const monthStr = `${baseDate.getFullYear()}-${String(baseDate.getMonth() + 1).padStart(2, '0')}`;

        const [response, agentsRes, targetsRes] = await Promise.all([
            axios.get('/api/dashboard/sales-agent', { params }),
            axios.get('/api/reporting/agents', { params: { month: monthStr } }),
            axios.get('/api/hr/employee-targets', { params: { month: monthStr } }),
        ]);
        stats.value = response.data.stats || {};
        recentLeads.value = response.data.recent_leads || [];
        assignedCustomers.value = response.data.assigned_customers || [];
        pipeline.value = response.data.pipeline || {};
        todayFollowUps.value = response.data.today_follow_ups || [];
        next7DaysFollowUps.value = response.data.next_7_days_follow_ups || [];
        todayAppointments.value = response.data.today_appointments || [];
        followUpsByDate.value = response.data.follow_ups_by_date || [];

        // Build employee target stats for the logged-in user and others (for future use)
        const targetsRaw = targetsRes.data?.data || [];
        const agents = normalizeAgentsList(agentsRes.data);
        const byUser = {};

        const performanceCandidates = agents
            .filter((a) =>
                num(a.leads_count) > 0 ||
                num(a.won_count) > 0 ||
                num(a.won_products) > 0 ||
                num(a.won_leads) > 0 ||
                num(a.revenue) > 0
            )
            .sort((a, b) => {
                const aWon = Math.max(num(a.won_products), num(a.won_count), num(a.won_leads));
                const bWon = Math.max(num(b.won_products), num(b.won_count), num(b.won_leads));
                if (bWon !== aWon) return bWon - aWon;
                if (num(b.revenue) !== num(a.revenue)) return num(b.revenue) - num(a.revenue);
                return num(b.leads_count) - num(a.leads_count);
            });
        monthlyTopPerformer.value = performanceCandidates[0] || null;

        for (const t of targetsRaw) {
            byUser[t.user_id] = {
                user_id: t.user_id,
                user: t.user,
                target_appointments: t.target_appointments || 0,
                target_sales: t.target_sales || 0,
                target_revenue: t.target_revenue || 0,
                achieved_appointments: 0,
                achieved_sales: 0,
                achieved_revenue: 0,
            };
        }

        for (const ag of agents) {
            const existing =
                byUser[ag.id] ||
                {
                    user_id: ag.id,
                    user: { id: ag.id, name: ag.name },
                    target_appointments: 0,
                    target_sales: 0,
                    target_revenue: 0,
                    achieved_appointments: 0,
                    achieved_sales: 0,
                    achieved_revenue: 0,
                };
            existing.achieved_appointments = ag.appointments_count || 0;
            // Sales = individual products marked WON
            existing.achieved_sales = ag.won_products || ag.won_count || 0;
            existing.achieved_revenue = ag.revenue || 0;
            byUser[ag.id] = existing;
        }

        employeeTargets.value = Object.values(byUser).map((t) => {
            const denom = t.target_appointments || 0;
            const progress =
                denom > 0 ? Math.min(100, Math.round((t.achieved_appointments / denom) * 100)) : 0;
            return { ...t, appointment_progress: progress };
        });
    } catch (error) {
        console.error('Failed to load dashboard:', error);
    } finally {
        loading.value = false;
    }
};

const onDateChange = () => {
    if (selectedDate.value === todayStr.value) {
        followUpsByDate.value = todayFollowUps.value;
        return;
    }
    loadDashboard(selectedDate.value);
};

const resetDate = () => {
    selectedDate.value = todayStr.value;
    followUpsByDate.value = [];
    loadDashboard();
};

const openActivityModal = (lead) => {
    activityLead.value = lead;
    showActivityModal.value = true;
};

const closeActivityModal = () => {
    showActivityModal.value = false;
    activityLead.value = null;
};

const handleActivitySaved = () => {
    loadDashboard(selectedDate.value && selectedDate.value !== todayStr.value ? selectedDate.value : undefined);
    closeActivityModal();
};

const openCompleteModal = (fu) => {
    selectedFollowUp.value = fu;
    completeForm.value = { remarks: '', saleHappened: false, newStage: 'lead', nextFollowUpAt: '' };
    showCompleteModal.value = true;
};

const closeCompleteModal = () => {
    showCompleteModal.value = false;
    selectedFollowUp.value = null;
};

const completeFollowUp = async () => {
    if (!selectedFollowUp.value || completingFollowUp.value) return;
    completingFollowUp.value = true;
    try {
        const payload = {
            remarks: completeForm.value.remarks,
            sale_happened: completeForm.value.saleHappened,
            new_stage: completeForm.value.saleHappened ? completeForm.value.newStage : null,
        };
        if (completeForm.value.nextFollowUpAt) {
            payload.next_follow_up_at = completeForm.value.nextFollowUpAt;
        }
        await axios.post(`/api/leads/${selectedFollowUp.value.id}/complete-followup`, payload);
        closeCompleteModal();
        loadDashboard(selectedDate.value && selectedDate.value !== todayStr.value ? selectedDate.value : undefined);
        toast.success('Follow-up completed');
    } catch (e) {
        toast.error('Failed to complete follow-up');
    } finally {
        completingFollowUp.value = false;
    }
};

onMounted(() => {
    selectedDate.value = todayStr.value;
    loadDashboard();
});
</script>
