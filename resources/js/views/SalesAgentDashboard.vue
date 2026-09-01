<template>
    <div class="page">
        <p class="page-lead">{{ greeting }} Here is what needs you today.</p>

        <AttendanceClock />

        <MyWork />

        <!--
            The day in one line. This used to be three cards sharing a grid row
            with the attendance panel, so each stretched to its height and put a
            single digit at the top of a 470px white box - three of them, above
            anything a rep actually has to do.
        -->
        <div class="flex flex-wrap gap-2">
            <!-- Only what the band above does not already say. -->
            <span class="inline-flex min-h-[44px] items-center gap-2 rounded-control border border-slate-200 bg-white px-3 text-sm text-slate-700">
                <CalendarDaysIcon class="icon-sm shrink-0 text-slate-400" aria-hidden="true" />
                {{ displayFollowUpsToday.length }} due today
            </span>
            <span class="inline-flex min-h-[44px] items-center gap-2 rounded-control border border-slate-200 bg-white px-3 text-sm text-slate-700">
                <ClockIcon class="icon-sm shrink-0 text-slate-400" aria-hidden="true" />
                {{ todayAppointments.length }}
                {{ todayAppointments.length === 1 ? 'appointment' : 'appointments' }} today
            </span>
            <span class="inline-flex min-h-[44px] items-center gap-2 rounded-control border border-slate-200 bg-white px-3 text-sm text-slate-700">
                <ArrowTrendingUpIcon class="icon-sm shrink-0 text-slate-400" aria-hidden="true" />
                {{ stats.yearly?.won || 0 }} won this year
            </span>
        </div>

        <!-- My Targets (if admin has set them for this month) -->
        <section
            v-if="hasRealTarget"
            class="card bg-gradient-to-r from-primary-50 via-white to-success-50 border-primary-200 px-4 py-4 md:px-6 md:py-5 flex flex-col gap-4"
        >
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 shrink-0">
                        <ViewfinderCircleIcon class="icon" aria-hidden="true" />
                    </span>
                    <div>
                        <h2 class="text-sm md:text-base font-semibold text-slate-900">My Targets (this month)</h2>
                        <p class="text-[11px] md:text-xs text-slate-500">
                            Set by your admin. Updated automatically from your appointments and won sales.
                        </p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 sm:gap-3">
                    <span class="chip tabular-nums">
                        <span class="inline-block w-2 h-2 rounded-full bg-success-600" aria-hidden="true"></span>
                        {{ myTarget.achieved_appointments }} / {{ myTarget.target_appointments || 0 }} appointments
                    </span>
                    <span class="chip tabular-nums">
                        <span class="inline-block w-2 h-2 rounded-full bg-primary-600" aria-hidden="true"></span>
                        {{ myTarget.achieved_sales }} / {{ myTarget.target_sales || 0 }} sales
                    </span>
                </div>
            </div>
            <div class="space-y-2">
                <div class="flex justify-between items-center text-xs text-slate-500">
                    <span>Overall progress</span>
                    <span class="font-medium text-slate-700 tabular-nums">{{ myTarget.overall_progress }}%</span>
                </div>
                <div class="w-full bg-slate-200/80 rounded-full h-2.5 overflow-hidden" aria-hidden="true">
                    <div
                        class="h-2.5 rounded-full bg-primary-600 transition-all duration-500"
                        :style="{ width: `${Math.min(100, myTarget.overall_progress || 0)}%` }"
                    ></div>
                </div>
            </div>
        </section>

        <!-- Monthly #1 Spotlight (all users) -->
        <section
            v-if="monthlyTopPerformer"
            class="card bg-gradient-to-r from-warning-50 via-white to-success-50 border-warning-200 px-4 py-4 md:px-6 md:py-5 min-w-0"
        >
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 min-w-0">
                <div class="flex items-center gap-3">
                    <span class="w-10 h-10 rounded-full bg-warning-500 text-white flex items-center justify-center font-bold shrink-0" aria-hidden="true">1</span>
                    <div>
                        <h2 class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-warning-100 text-warning-800 text-xs font-semibold mb-1">
                            <TrophyIcon class="icon-sm" aria-hidden="true" />
                            Top Performer of the Month
                        </h2>
                        <div class="text-lg font-bold text-slate-900">{{ monthlyTopPerformer.name }}</div>
                        <div class="text-xs text-slate-600 tabular-nums">
                            {{ monthlyTopPerformer.leads_count || 0 }} leads •
                            {{ monthlyTopPerformer.won_products || monthlyTopPerformer.won_count || 0 }} won
                        </div>
                    </div>
                </div>
                <div class="text-left sm:text-right text-sm w-full sm:w-auto shrink-0">
                    <!-- This said £0 next to a real win count, which reads as
                         "their wins were worth nothing". Prices are deliberately
                         not recorded, so a count is the honest figure. -->
                    <div class="font-bold tabular-nums text-success-700">
                        {{ monthlyTopPerformer.won_products || monthlyTopPerformer.won_count || 0 }} won
                    </div>
                    <div class="text-xs tabular-nums text-slate-500">
                        from {{ monthlyTopPerformer.leads_count || 0 }} leads
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Actions -->
        <div class="flex flex-col sm:flex-row flex-wrap gap-2 sm:gap-3">
            <BaseButton variant="primary" block-mobile :to="{ path: '/customers', query: { type: 'prospect' } }">
                <template #icon>
                    <UserPlusIcon class="icon" aria-hidden="true" />
                </template>
                Add Customer
            </BaseButton>
            <BaseButton variant="success" block-mobile to="/leads/pipeline">
                <template #icon>
                    <PlusCircleIcon class="icon" aria-hidden="true" />
                </template>
                Add Lead
            </BaseButton>
        </div>

        <!-- Follow-ups & Appointments Section -->
        <BaseCard>
            <template #header>
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 min-w-0 w-full">
                    <label class="text-sm font-medium text-slate-700 shrink-0" for="salesagentdashboard-view-date">View date:</label>
                    <input id="salesagentdashboard-view-date"
                        v-model="selectedDate"
                        type="date"
                        class="form-input w-full sm:w-auto min-w-0 max-w-full"
                        @change="onDateChange"
                    />
                    <BaseButton
                        v-if="selectedDate !== todayStr"
                        variant="ghost"
                        class="shrink-0"
                        @click="resetDate"
                    >
                        Today
                    </BaseButton>
                </div>
            </template>
            <template #actions>
                <div class="tab-list" role="group" aria-label="Follow-up range">
                    <button
                        type="button"
                        :class="['tab', activeTab === 'today' ? 'tab-active' : '']"
                        :aria-pressed="activeTab === 'today' ? 'true' : 'false'"
                        @click="activeTab = 'today'"
                    >
                        Today
                    </button>
                    <button
                        type="button"
                        :class="['tab', activeTab === 'next7' ? 'tab-active' : '']"
                        :aria-pressed="activeTab === 'next7' ? 'true' : 'false'"
                        @click="activeTab = 'next7'"
                    >
                        Next 7 Days
                    </button>
                </div>
            </template>

            <!-- Today / Selected Date Follow-ups -->
            <div v-show="activeTab === 'today'" class="space-y-6">
                <section v-if="overdueFollowUps.length" ref="workSection">
                    <h2 class="mb-3 flex items-center gap-2 text-base font-semibold text-slate-900">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-control bg-danger-100 text-danger-700">
                            <ExclamationTriangleIcon class="icon" aria-hidden="true" />
                        </span>
                        Overdue
                        <span class="rounded-full bg-danger-100 px-2 py-0.5 text-xs font-semibold text-danger-800 tabular-nums">
                            {{ overdueFollowUps.length }}
                        </span>
                    </h2>
                    <p class="mb-3 text-xs text-slate-500">
                        You set these dates yourself and they have passed. Oldest first.
                    </p>
                    <div class="space-y-3">
                        <div
                            v-for="fu in overdueOnDashboard"
                            :key="`overdue-${fu.id}`"
                            class="flex min-w-0 flex-col gap-3 rounded-card border border-danger-200 bg-danger-50/60 p-4 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0 flex-1">
                                <router-link
                                    v-if="fu.customer_id"
                                    :to="`/customers/${fu.customer_id}`"
                                    class="link break-words"
                                >
                                    {{ fu.customer?.business_name || fu.customer?.name || 'Customer' }}
                                </router-link>
                                <div v-else class="font-medium text-slate-900">
                                    {{ fu.customer?.name || 'Customer' }}
                                </div>
                                <div class="mt-1 text-xs font-medium text-danger-800 tabular-nums">
                                    {{ overdueLabel(fu.next_follow_up_at) }}
                                </div>
                                <div v-if="fu.customer?.phone" class="mt-1.5 flex items-center gap-1">
                                    <a
                                        :href="`tel:${fu.customer.phone}`"
                                        class="text-sm text-primary-700 hover:underline tabular-nums"
                                    >{{ fu.customer.phone }}</a>
                                    <CopyButton :value="fu.customer.phone" label="phone number" size="compact" />
                                </div>
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2 sm:justify-end">
                                <BaseButton variant="outline" @click="openActivityModal(fu)">Log</BaseButton>
                                <BaseButton variant="success" @click="openCompleteModal(fu)">Done</BaseButton>
                            </div>
                        </div>

                        <BaseButton
                            v-if="overdueFollowUps.length > OVERDUE_ON_DASHBOARD"
                            variant="outline"
                            block-mobile
                            to="/followups?overdue=1"
                        >
                            See all {{ overdueFollowUps.length }} overdue
                        </BaseButton>
                    </div>
                </section>

                <section>
                    <h2 class="text-base font-semibold text-slate-900 mb-3 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-control bg-primary-100 text-primary-700 flex items-center justify-center shrink-0">
                            <CalendarDaysIcon class="icon" aria-hidden="true" />
                        </span>
                        {{ selectedDate === todayStr ? "Today's Follow-ups" : `Follow-ups for ${formatSelectedDateLabel(selectedDate)}` }}
                    </h2>
                    <p v-if="loading" class="text-center py-8 text-slate-500" role="status" aria-live="polite">Loading...</p>
                    <EmptyState
                        v-else-if="displayFollowUpsToday.length === 0"
                        :heading="selectedDate === todayStr ? 'No follow-ups scheduled for today' : 'No follow-ups for this date'"
                    >
                        <template #icon>
                            <CalendarDaysIcon class="icon" aria-hidden="true" />
                        </template>
                    </EmptyState>
                    <div v-else class="space-y-3">
                        <div
                            v-for="fu in displayFollowUpsToday"
                            :key="fu.id"
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-4 bg-slate-50 rounded-card hover:bg-slate-100 transition-colors group min-w-0"
                        >
                            <div class="flex-1 min-w-0">
                                <router-link
                                    v-if="fu.customer_id"
                                    :to="`/customers/${fu.customer_id}`"
                                    class="link break-words"
                                >
                                    {{ fu.customer?.name || 'Customer' }}
                                </router-link>
                                <div v-else class="font-medium text-slate-900">
                                    {{ fu.customer?.name || 'Customer' }}
                                </div>
                                <div class="text-sm text-slate-600 mt-0.5">
                                    {{ fu.items?.length ? fu.items.map(i => i.product?.name).filter(Boolean).join(', ') : '-' }}
                                </div>
                                <div class="text-xs text-slate-500 mt-1 tabular-nums">{{ formatDateTime(fu.next_follow_up_at) }}</div>
                            </div>
                            <div class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                                <BaseButton variant="outline" @click="openActivityModal(fu)">Log Activity</BaseButton>
                                <BaseButton variant="success" @click="openCompleteModal(fu)">Mark as Done</BaseButton>
                                <BaseButton
                                    v-if="fu.customer_id"
                                    variant="primary"
                                    :to="`/customers/${fu.customer_id}`"
                                >
                                    View
                                </BaseButton>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Today's Appointments -->
                <section>
                    <h2 class="text-base font-semibold text-slate-900 mb-3 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-control bg-warning-100 text-warning-800 flex items-center justify-center shrink-0">
                            <ClockIcon class="icon" aria-hidden="true" />
                        </span>
                        Today's Appointments
                    </h2>
                    <EmptyState v-if="todayAppointments.length === 0" heading="No appointments scheduled for today">
                        <template #icon>
                            <ClockIcon class="icon" aria-hidden="true" />
                        </template>
                    </EmptyState>
                    <div v-else class="space-y-3">
                        <div
                            v-for="apt in todayAppointments"
                            :key="apt.id"
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-4 bg-warning-50 rounded-card hover:bg-warning-100 transition-colors min-w-0"
                        >
                            <div class="flex-1 min-w-0">
                                <router-link
                                    v-if="apt.customer_id"
                                    :to="`/customers/${apt.customer_id}`"
                                    class="link break-words"
                                >
                                    {{ apt.customer?.name || 'Customer' }}
                                </router-link>
                                <div v-else class="font-medium text-slate-900">
                                    {{ apt.customer?.name || 'Customer' }}
                                </div>
                                <div class="text-sm text-slate-600 mt-0.5">{{ apt.description || 'Appointment' }}</div>
                                <div class="text-xs text-slate-500 mt-1 tabular-nums">{{ apt.appointment_time || '10:00' }}</div>
                            </div>
                            <div class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                                <BaseButton
                                    v-if="apt.lead_id"
                                    variant="success"
                                    @click="openCompleteModal({ id: apt.lead_id })"
                                >
                                    Complete
                                </BaseButton>
                                <BaseButton
                                    v-if="apt.customer_id"
                                    variant="primary"
                                    :to="`/customers/${apt.customer_id}`"
                                >
                                    View
                                </BaseButton>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Next 7 Days Follow-ups -->
            <div v-show="activeTab === 'next7'" class="space-y-4">
                <h2 class="text-base font-semibold text-slate-900 flex items-center gap-2">
                    <span class="w-8 h-8 rounded-control bg-success-100 text-success-800 flex items-center justify-center shrink-0">
                        <ListBulletIcon class="icon" aria-hidden="true" />
                    </span>
                    Follow-ups (Next 7 Days)
                </h2>
                <EmptyState v-if="next7DaysFollowUps.length === 0" heading="No follow-ups scheduled in the next 7 days">
                    <template #icon>
                        <ListBulletIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>
                <div v-else class="space-y-3 max-h-96 overflow-y-auto">
                    <div
                        v-for="fu in next7DaysFollowUps"
                        :key="fu.id"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-4 bg-slate-50 rounded-card hover:bg-slate-100 transition-colors group min-w-0"
                    >
                        <div class="flex-1 min-w-0">
                            <router-link
                                v-if="fu.customer_id"
                                :to="`/customers/${fu.customer_id}`"
                                class="link break-words"
                            >
                                {{ fu.customer?.name || 'Customer' }}
                            </router-link>
                            <div v-else class="font-medium text-slate-900">
                                {{ fu.customer?.name || 'Customer' }}
                            </div>
                            <div class="text-sm text-slate-600 mt-0.5">
                                {{ fu.items?.length ? fu.items.map(i => i.product?.name).filter(Boolean).join(', ') : '-' }}
                            </div>
                            <div class="text-xs text-slate-500 mt-1 flex items-center gap-2 tabular-nums">
                                <span class="font-medium">{{ formatDateOnly(fu.next_follow_up_at) }}</span>
                                {{ formatTimeOnly(fu.next_follow_up_at) }}
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                            <BaseButton
                                variant="outline"
                                class="sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100 transition-opacity"
                                @click="openActivityModal(fu)"
                            >
                                Log
                            </BaseButton>
                            <BaseButton
                                v-if="fu.customer_id"
                                variant="primary"
                                :to="`/customers/${fu.customer_id}`"
                            >
                                View
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </div>
        </BaseCard>

        <!-- Recent Leads & Assigned Customers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <BaseCard title="Recent Leads">
                <EmptyState v-if="recentLeads.length === 0" heading="No recent leads">
                    <template #icon>
                        <DocumentTextIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>
                <div v-else class="space-y-3">
                    <div
                        v-for="lead in recentLeads"
                        :key="lead.id"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-3 bg-slate-50 rounded-card group min-w-0"
                    >
                        <div class="flex-1 min-w-0">
                            <router-link
                                v-if="lead.customer_id"
                                :to="`/customers/${lead.customer_id}`"
                                class="link break-words"
                            >
                                {{ lead.customer?.business_name || lead.customer?.name || 'Customer' }}
                            </router-link>
                            <div v-else class="font-medium text-slate-900">
                                {{ lead.customer?.business_name || lead.customer?.name || 'Customer' }}
                            </div>
                            <div class="text-xs text-slate-500">{{ formatLeadStage(lead.stage) }} • {{ lead.items?.length ? lead.items.map(i => i.product?.name).filter(Boolean).join(', ') : '-' }}</div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 sm:gap-3 shrink-0 sm:justify-end">
                            <div class="text-xs text-slate-500">{{ sinceLabel(lead.updated_at) }}</div>
                            <BaseButton
                                variant="success"
                                class="sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100 transition-opacity"
                                @click="openActivityModal(lead)"
                            >
                                Log
                            </BaseButton>
                            <BaseButton
                                v-if="lead.customer_id"
                                variant="primary"
                                class="sm:opacity-0 sm:group-hover:opacity-100 sm:focus-visible:opacity-100 transition-opacity"
                                :to="`/customers/${lead.customer_id}`"
                            >
                                View
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </BaseCard>

            <BaseCard title="Assigned Customers">
                <EmptyState v-if="assignedCustomers.length === 0" heading="No assigned customers">
                    <template #icon>
                        <UsersIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>
                <div v-else class="space-y-3">
                    <div
                        v-for="customer in assignedCustomers"
                        :key="customer.id"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-3 bg-slate-50 rounded-card hover:bg-slate-100 transition-colors min-w-0"
                    >
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-slate-900 break-words">
                                {{ customer.business_name || customer.name }}
                            </div>
                            <div v-if="customer.business_name && customer.name" class="text-xs text-slate-500 break-words">
                                {{ customer.name }}
                            </div>
                            <div v-if="customer.phone" class="mt-1 flex items-center gap-1">
                                <a :href="`tel:${customer.phone}`" class="text-sm tabular-nums text-primary-700 hover:underline">
                                    {{ customer.phone }}
                                </a>
                                <CopyButton :value="customer.phone" label="phone number" size="compact" />
                            </div>
                        </div>
                        <BaseButton
                            variant="primary"
                            block-mobile
                            class="shrink-0"
                            :to="`/customers/${customer.id}`"
                        >
                            View
                        </BaseButton>
                    </div>
                </div>
            </BaseCard>
        </div>

        <!-- Pipeline Summary -->
        <section class="min-w-0 space-y-3 sm:space-y-4">
            <h2 class="text-base font-semibold text-slate-900">Pipeline Summary</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
                <StatCard label="Follow Up" :value="pipeline.follow_up || 0" tone="neutral">
                    <template #icon>
                        <ArrowPathIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard label="Lead" :value="pipeline.lead || 0" tone="neutral">
                    <template #icon>
                        <UserPlusIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard label="Hot Lead" :value="pipeline.hot_lead || 0" tone="warning">
                    <template #icon>
                        <BoltIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard label="Quotation" :value="pipeline.quotation || 0" tone="primary">
                    <template #icon>
                        <DocumentTextIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard label="Won" :value="pipeline.won || 0" tone="success">
                    <template #icon>
                        <CheckCircleIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard label="Lost" :value="pipeline.lost || 0" tone="danger">
                    <template #icon>
                        <XCircleIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
            </div>
        </section>

        <!-- Complete Follow-up Modal -->
        <BaseModal
            v-model="showCompleteModal"
            title="Complete Follow-up"
            size="md"
            :close-on-backdrop="false"
            @close="closeCompleteModal"
        >
            <!-- No `novalidate`: the required Remarks field must still block submit, as before. -->
            <form id="sales-agent-complete-followup-form" class="space-y-4" @submit.prevent="completeFollowUp">
                <div>
                    <label class="form-label" for="salesagentdashboard-remarks-notes">
                        Remarks / Notes <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <textarea id="salesagentdashboard-remarks-notes"
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
                        <span>Sale happened</span>
                    </label>
                </div>
                <div v-if="completeForm.saleHappened">
                    <label class="form-label" for="salesagentdashboard-new-stage">New Stage</label>
                    <select id="salesagentdashboard-new-stage" v-model="completeForm.newStage" class="form-select">
                        <option value="lead">Lead</option>
                        <option value="hot_lead">Hot Lead</option>
                        <option value="quotation">Quotation</option>
                        <option value="won">Won</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="salesagentdashboard-next-follow-up-date-optional">Next Follow-up Date (Optional)</label>
                    <input id="salesagentdashboard-next-follow-up-date-optional"
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
                    form="sales-agent-complete-followup-form"
                    block-mobile
                    :loading="completingFollowUp"
                >
                    {{ completingFollowUp ? 'Saving...' : 'Complete Follow-up' }}
                </BaseButton>
            </template>
        </BaseModal>

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
import {
    ArrowPathIcon,
    ArrowTrendingUpIcon,
    BoltIcon,
    CalendarDaysIcon,
    CheckCircleIcon,
    ClockIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    ListBulletIcon,
    PlusCircleIcon,
    TrophyIcon,
    UserPlusIcon,
    UsersIcon,
    ViewfinderCircleIcon,
    XCircleIcon,
} from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';
import {
    BaseButton,
    BaseCard,
    BaseModal,
    EmptyState,
    StatCard,
} from '@/components/base';
import AttendanceClock from '@/components/AttendanceClock.vue';
import CopyButton from '@/components/CopyButton.vue';
import MyWork from '@/components/MyWork.vue';
import LogActivityModal from '@/components/LogActivityModal.vue';
import { formatLeadStage } from '@/utils/displayFormat';

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

/**
 * Follow-ups whose date has already passed.
 *
 * Not part of the dashboard payload, because every follow-up surface in this
 * product was built to look forward - which is exactly how 33 missed promises
 * became invisible to the people who made them.
 */
const overdueFollowUps = ref([]);

/**
 * How many overdue rows the dashboard itself shows.
 *
 * There are 33 outstanding across the company and one rep can easily hold a
 * dozen. Printing them all turns the first screen into a wall of red that
 * buries the appointments and today's work underneath it - which is how a list
 * stops being read at all. The oldest few, then a link to the rest.
 */
const OVERDUE_ON_DASHBOARD = 5;

const overdueOnDashboard = computed(() => overdueFollowUps.value.slice(0, OVERDUE_ON_DASHBOARD));
const workSection = ref(null);

const greeting = computed(() => {
    const h = new Date().getHours();

    if (h < 12) return 'Good morning.';
    if (h < 17) return 'Good afternoon.';

    return 'Good evening.';
});

/** A target panel with nothing in it is a progress bar stuck at 0%. */
const hasRealTarget = computed(() =>
    !!myTarget.value
    && (Number(myTarget.value.target_appointments) > 0 || Number(myTarget.value.target_sales) > 0));

function overdueLabel(when) {
    if (!when) return 'Overdue';

    const due = new Date(when);
    const days = Math.floor((new Date(new Date().toDateString()) - new Date(due.toDateString())) / 86400000);

    if (days <= 0) return 'Due today';
    if (days === 1) return 'Was due yesterday';

    return `Was due ${days} days ago`;
}

function sinceLabel(when) {
    if (!when) return '';

    const days = Math.floor((Date.now() - new Date(when).getTime()) / 86400000);

    if (days <= 0) return 'Today';
    if (days === 1) return 'Yesterday';
    if (days < 30) return `${days}d ago`;

    return `${Math.floor(days / 30)}mo ago`;
}

async function loadOverdue() {
    try {
        const { data } = await axios.get('/api/followups', { params: { overdue: 1 } });
        const rows = Array.isArray(data) ? data : (data?.data ?? []);

        overdueFollowUps.value = rows
            .slice()
            .sort((a, b) => new Date(a.next_follow_up_at) - new Date(b.next_follow_up_at));
    } catch {
        overdueFollowUps.value = [];
    }
}

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
            const lines = t.lines || [];
            const achievedFromLines = lines.length
                ? lines.reduce((s, l) => s + Number(l.achieved_quantity || 0), 0)
                : 0;
            const targetSales = lines.length
                ? lines.reduce((s, l) => s + Number(l.target_quantity || 0), 0)
                : t.target_sales || 0;
            byUser[t.user_id] = {
                user_id: t.user_id,
                user: t.user,
                lines,
                target_appointments: t.target_appointments || 0,
                target_sales: targetSales,
                target_revenue: t.target_revenue || 0,
                achieved_appointments: 0,
                achieved_sales: achievedFromLines,
                achieved_revenue: 0,
            };
        }

        for (const ag of agents) {
            const existing =
                byUser[ag.id] ||
                {
                    user_id: ag.id,
                    user: { id: ag.id, name: ag.name },
                    lines: [],
                    target_appointments: 0,
                    target_sales: 0,
                    target_revenue: 0,
                    achieved_appointments: 0,
                    achieved_sales: 0,
                    achieved_revenue: 0,
                };
            existing.achieved_appointments = ag.appointments_count || 0;
            if (existing.lines?.length) {
                existing.achieved_sales = existing.lines.reduce(
                    (s, l) => s + Number(l.achieved_quantity || 0),
                    0
                );
            } else {
                existing.achieved_sales = ag.won_products || ag.won_count || 0;
            }
            existing.achieved_revenue = ag.revenue || 0;
            byUser[ag.id] = existing;
        }

        employeeTargets.value = Object.values(byUser).map((t) => {
            const apptDenom = num(t.target_appointments);
            const apptAch = num(t.achieved_appointments);
            const appointment_progress =
                apptDenom > 0
                    ? Math.min(100, Math.round((apptAch / apptDenom) * 100))
                    : apptAch > 0
                      ? 100
                      : 0;
            const salesDenom = num(t.target_sales);
            const salesAch = num(t.achieved_sales);
            const sales_progress =
                salesDenom > 0
                    ? Math.min(100, Math.round((salesAch / salesDenom) * 100))
                    : salesAch > 0
                      ? 100
                      : 0;
            const revDenom = num(t.target_revenue);
            const revAch = num(t.achieved_revenue);
            const revenue_progress =
                revDenom > 0
                    ? Math.min(100, Math.round((revAch / revDenom) * 100))
                    : revAch > 0
                      ? 100
                      : 0;
            const parts = [];
            if (apptDenom > 0) parts.push(appointment_progress);
            if (salesDenom > 0) parts.push(sales_progress);
            if (revDenom > 0) parts.push(revenue_progress);
            const overall_progress = parts.length
                ? Math.round(parts.reduce((a, b) => a + b, 0) / parts.length)
                : 0;
            return {
                ...t,
                appointment_progress,
                sales_progress,
                revenue_progress,
                overall_progress,
            };
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
    loadOverdue();
});
</script>
