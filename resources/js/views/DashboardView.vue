<template>
    <div class="max-w-7xl mx-auto w-full min-w-0 overflow-x-hidden box-border px-3 pb-6 sm:px-4 md:px-6 space-y-5 sm:space-y-8">
        <!-- Welcome -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between sm:gap-4">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold text-slate-900 tracking-tight sm:text-2xl md:text-3xl break-words">
                    Welcome back, {{ welcomeName }}
                </h1>
                <p class="text-xs text-slate-500 mt-1.5 sm:text-sm leading-relaxed">
                    Here’s what’s happening with your pipeline today.
                </p>
            </div>
        </div>

        <div
            v-if="isSelfDashboardScope"
            class="rounded-xl border border-sky-200 bg-sky-50/80 px-3 py-2.5 text-xs text-sky-900 leading-relaxed sm:px-4 sm:py-3 sm:text-sm"
        >
            You’re viewing <strong>your</strong> opportunities, customers, and activity. Admins and managers see the full organization on this screen.
        </div>

        <!-- Date range (+ org user filter): compact on mobile, full row on desktop -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <button
                type="button"
                @click="filtersOpen = !filtersOpen"
                class="md:hidden w-full min-h-12 flex items-center justify-between px-4 py-3 text-left text-slate-700 hover:bg-slate-50 transition-colors touch-manipulation active:bg-slate-50"
            >
                <span class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span class="font-medium text-sm">Date range</span>
                    <span v-if="activeFilterCount" class="px-2 py-0.5 bg-slate-900 text-white text-xs rounded-full">{{ activeFilterCount }}</span>
                </span>
                <span class="text-slate-400" :class="filtersOpen ? 'rotate-180' : ''">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </span>
            </button>
            <div :class="['md:block', filtersOpen ? 'block' : 'hidden']">
                <div class="p-4 pt-0 md:pt-4 border-t md:border-t-0 border-slate-100">
                    <p class="text-xs font-medium text-slate-500 mb-2">Quick range</p>
                    <div class="flex flex-wrap gap-2 mb-4">
                        <button type="button" :class="presetButtonClass('today')" @click="selectDatePreset('today')">Today</button>
                        <button type="button" :class="presetButtonClass('2d')" @click="selectDatePreset('2d')">Last 2 days</button>
                        <button type="button" :class="presetButtonClass('7d')" @click="selectDatePreset('7d')">Last 7 days</button>
                        <button type="button" :class="presetButtonClass('last_week')" @click="selectDatePreset('last_week')">Last week</button>
                        <button type="button" :class="presetButtonClass('week')" @click="selectDatePreset('week')">This week</button>
                        <button type="button" :class="presetButtonClass('month')" @click="selectDatePreset('month')">This month</button>
                        <button type="button" :class="presetButtonClass('30d')" @click="selectDatePreset('30d')">Last 30 days</button>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 lg:gap-4 lg:items-end">
                        <div class="sm:col-span-1 lg:col-span-3">
                            <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
                            <input
                                v-model="filters.from"
                                type="date"
                                class="w-full min-h-11 px-3 py-2 border border-slate-300 rounded-lg text-base sm:text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                @change="onDashboardDateManualChange"
                            />
                        </div>
                        <div class="sm:col-span-1 lg:col-span-3">
                            <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
                            <input
                                v-model="filters.to"
                                type="date"
                                class="w-full min-h-11 px-3 py-2 border border-slate-300 rounded-lg text-base sm:text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                @change="onDashboardDateManualChange"
                            />
                        </div>
                        <div v-if="canUseOrgDashboardFilters" class="sm:col-span-1 lg:col-span-3">
                            <label class="block text-xs font-medium text-slate-500 mb-1">User</label>
                            <select
                                v-model="filters.employee_id"
                                class="w-full min-h-11 px-3 py-2 border border-slate-200 rounded-xl text-base sm:text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 bg-slate-50/50"
                            >
                                <option value="">All users</option>
                                <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                                    {{ emp.name }}
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2 sm:col-span-2" :class="canUseOrgDashboardFilters ? 'lg:col-span-3' : 'lg:col-span-6'">
                            <button
                                @click="loadDashboard"
                                :disabled="loading"
                                class="flex-1 min-h-11 px-4 py-2.5 bg-slate-900 text-white rounded-lg text-sm font-medium hover:bg-slate-800 disabled:opacity-50 inline-flex items-center justify-center gap-2 order-2 sm:order-1 touch-manipulation"
                            >
                                <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ loading ? 'Applying...' : 'Apply' }}
                            </button>
                            <button
                                @click="resetFilters"
                                class="min-h-11 px-4 py-2.5 border border-slate-300 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 order-1 sm:order-2 touch-manipulation"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                    <div v-if="filters.from || filters.to || filters.employee_id" class="mt-3 pt-3 border-t border-slate-100 flex flex-wrap items-center gap-2">
                        <span class="text-xs text-slate-500">Active:</span>
                        <span v-if="filters.from" class="inline-flex items-center gap-1 px-2 py-1 bg-blue-50 text-blue-700 rounded-md text-xs">
                            From {{ formatFilterDate(filters.from) }}
                        </span>
                        <span v-if="filters.to" class="inline-flex items-center gap-1 px-2 py-1 bg-blue-50 text-blue-700 rounded-md text-xs">
                            To {{ formatFilterDate(filters.to) }}
                        </span>
                        <span v-if="filters.employee_id" class="inline-flex items-center px-2 py-1 bg-slate-100 text-slate-700 rounded-md text-xs">
                            {{ employees.find(e => e.id == filters.employee_id)?.name }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main KPIs: total leads, won products, active, win rate -->
        <div class="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div
                class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 cursor-pointer hover:shadow-md transition-shadow min-w-0"
                @click="goToTotalLeadsListing"
            >
                <div class="flex items-start justify-between gap-2 sm:gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide sm:text-xs">Total leads</p>
                        <p class="text-2xl font-semibold text-slate-900 tabular-nums mt-1 sm:text-3xl">{{ dashboardData.total_leads_all ?? 0 }}</p>
                        <p class="text-xs text-slate-400 mt-2 line-clamp-2">All stages · {{ filterPeriodLabel }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center shrink-0 sm:w-11 sm:h-11">
                        <svg class="w-5 h-5 text-slate-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div
                class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 cursor-pointer hover:shadow-md transition-shadow min-w-0"
                @click="goToLeadsListing('won')"
            >
                <div class="flex items-start justify-between gap-2 sm:gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide sm:text-xs">Won products</p>
                        <p class="text-2xl font-semibold text-slate-900 tabular-nums mt-1 sm:text-3xl">{{ dashboardData.won_product_units ?? 0 }}</p>
                        <p class="text-xs text-slate-400 mt-2 line-clamp-2">
                            Units on won line items
                            <span v-if="(dashboardData.won_product_lines || 0) > 0" class="tabular-nums"> · {{ dashboardData.won_product_lines }} line(s)</span>
                        </p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0 sm:w-11 sm:h-11">
                        <svg class="w-5 h-5 text-emerald-700 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                </div>
            </div>

            <div
                class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 cursor-pointer hover:shadow-md transition-shadow min-w-0"
                @click="router.push({ path: '/leads/pipeline' })"
            >
                <div class="flex items-start justify-between gap-2 sm:gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide sm:text-xs">Active opportunities</p>
                        <p class="text-2xl font-semibold text-slate-900 tabular-nums mt-1 sm:text-3xl">{{ dashboardData.total_leads || 0 }}</p>
                        <p class="text-xs text-slate-400 mt-2 line-clamp-2">Open stages · {{ filterPeriodLabel }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-100 flex items-center justify-center shrink-0 sm:w-11 sm:h-11">
                        <svg class="w-5 h-5 text-indigo-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 min-w-0">
                <div class="flex items-start justify-between gap-2 sm:gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide sm:text-xs">Win rate</p>
                        <p class="text-2xl font-semibold text-slate-900 tabular-nums mt-1 sm:text-3xl">{{ dashboardData.conversion_rate || 0 }}%</p>
                        <p class="text-xs text-slate-400 mt-2">Won / total</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-100 flex items-center justify-center shrink-0 sm:w-11 sm:h-11">
                        <svg class="w-5 h-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tickets by status (same date range and assignee as filters) -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
            <button
                type="button"
                class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 text-left cursor-pointer hover:shadow-md hover:border-amber-200/80 transition-all min-w-0 w-full touch-manipulation"
                @click="goToTicketsListing('open')"
            >
                <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide sm:text-xs">Open tickets</p>
                <p class="text-2xl font-semibold text-slate-900 tabular-nums mt-1 sm:text-3xl">{{ ticketStatusCounts.open }}</p>
                <p class="text-xs text-slate-400 mt-2 line-clamp-2">{{ filterPeriodLabel }}</p>
            </button>
            <button
                type="button"
                class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 text-left cursor-pointer hover:shadow-md hover:border-blue-200/80 transition-all min-w-0 w-full touch-manipulation"
                @click="goToTicketsListing('in_progress')"
            >
                <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide sm:text-xs">Working</p>
                <p class="text-2xl font-semibold text-slate-900 tabular-nums mt-1 sm:text-3xl">{{ ticketStatusCounts.in_progress }}</p>
                <p class="text-xs text-slate-400 mt-2 line-clamp-2">In progress · {{ filterPeriodLabel }}</p>
            </button>
            <button
                type="button"
                class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 text-left cursor-pointer hover:shadow-md hover:border-slate-300 transition-all min-w-0 w-full touch-manipulation"
                @click="goToTicketsListing('closed')"
            >
                <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide sm:text-xs">Closed</p>
                <p class="text-2xl font-semibold text-slate-900 tabular-nums mt-1 sm:text-3xl">{{ ticketStatusCounts.closed }}</p>
                <p class="text-xs text-slate-400 mt-2 line-clamp-2">{{ filterPeriodLabel }}</p>
            </button>
        </div>

        <!-- Performer of the month + their targets (always shown; calendar month from reporting API) -->
        <div class="min-w-0 rounded-2xl border border-amber-100 bg-gradient-to-r from-amber-50 via-white to-emerald-50 p-4 shadow-sm sm:p-5 md:p-6">
            <template v-if="monthlyTopPerformer">
                <div class="flex min-w-0 flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="flex min-w-0 items-start gap-3 sm:items-center sm:gap-4">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-amber-500 text-base font-bold text-white sm:h-12 sm:w-12 sm:text-lg">
                            1
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="mb-2 inline-flex max-w-full flex-wrap items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-semibold text-amber-800 sm:gap-2 sm:px-3 sm:text-xs">
                                {{ isSelfDashboardScope ? 'Your performance this month' : 'Top performer of the month' }}
                            </div>
                            <div class="text-lg font-bold text-slate-900 break-words sm:text-xl">{{ monthlyTopPerformer.name }}</div>
                            <div class="text-sm text-slate-600">
                                {{ monthlyTopPerformer.leads_count || 0 }} leads •
                                {{ monthlyTopPerformer.won_products || monthlyTopPerformer.won_count || 0 }} won
                            </div>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 text-center w-full md:max-w-md md:ml-auto">
                        <div class="px-3 py-2 bg-white rounded-lg border border-slate-100 min-w-0">
                            <div class="text-xs text-slate-500">Revenue</div>
                            <div class="text-sm font-bold text-emerald-600 break-words">£{{ formatNumber(monthlyTopPerformer.revenue || 0) }}</div>
                        </div>
                        <div class="px-3 py-2 bg-white rounded-lg border border-slate-100">
                            <div class="text-xs text-slate-500">Sales won</div>
                            <div class="text-sm font-bold text-slate-900">{{ monthlyTopPerformer.won_products || monthlyTopPerformer.won_count || 0 }}</div>
                        </div>
                        <div class="px-3 py-2 bg-white rounded-lg border border-slate-100">
                            <div class="text-xs text-slate-500">Conversion</div>
                            <div class="text-sm font-bold text-slate-900">{{ monthlyTopPerformer.conversion_rate || 0 }}%</div>
                        </div>
                    </div>
                </div>
                <div class="mt-5 pt-5 border-t border-amber-100/80">
                    <h4 class="text-sm font-semibold text-slate-800 mb-3">Their targets (this month)</h4>
                    <div v-if="performerMonthTarget" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="rounded-xl bg-white/80 border border-slate-100 p-3">
                            <div class="text-xs text-slate-500">Appointments</div>
                            <div class="text-sm font-semibold text-slate-900 tabular-nums">
                                {{ performerMonthTarget.achieved_appointments }} / {{ performerMonthTarget.target_appointments || 0 }}
                            </div>
                            <div class="mt-2 h-1.5 bg-slate-200 rounded-full overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-emerald-600"
                                    :style="{ width: `${performerMonthTarget.appointment_progress}%` }"
                                />
                            </div>
                        </div>
                        <div class="rounded-xl bg-white/80 border border-slate-100 p-3">
                            <div class="text-xs text-slate-500">Sales</div>
                            <div class="text-sm font-semibold text-slate-900 tabular-nums">
                                {{ performerMonthTarget.achieved_sales }} / {{ performerMonthTarget.target_sales || 0 }}
                            </div>
                        </div>
                        <div class="rounded-xl bg-white/80 border border-slate-100 p-3">
                            <div class="text-xs text-slate-500">Won value vs target</div>
                            <div class="text-sm font-semibold text-slate-900 break-words">
                                £{{ formatNumber(performerMonthTarget.achieved_revenue) }}
                                <span v-if="num(performerMonthTarget.target_revenue) > 0" class="text-slate-500 font-normal">
                                    / £{{ formatNumber(performerMonthTarget.target_revenue) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-slate-600 rounded-xl bg-white/60 border border-slate-100 p-4">
                        <p class="font-medium text-slate-800">No formal targets set</p>
                        <p class="text-xs text-slate-500 mt-1">
                            Activity this month: {{ monthlyTopPerformer.appointments_count || 0 }} appointments logged.
                        </p>
                    </div>
                </div>
            </template>
            <div v-else class="text-center py-6 text-slate-600">
                <p class="font-medium text-slate-800">No performer data for this month</p>
                <p class="text-sm text-slate-500 mt-1">There are no active sales users with activity in the selected scope yet.</p>
            </div>
        </div>

        <!-- Attendance Clock for non-admin -->
        <AttendanceClock />

        <!-- Attendance: hours per day (only users who checked in) -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 md:p-6 min-w-0">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4 mb-4">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-slate-900">Attendance by day</h3>
                    <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                        Check-in / check-out times and hours worked. Only staff who recorded attendance in the range appear.
                        Hover a bar for times.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    <button
                        type="button"
                        :class="attendancePreset === '3d' ? attendancePresetActiveClass : attendancePresetIdleClass"
                        @click="attendancePreset = '3d'"
                    >
                        Last 3 days
                    </button>
                    <button
                        type="button"
                        :class="attendancePreset === '7d' ? attendancePresetActiveClass : attendancePresetIdleClass"
                        @click="attendancePreset = '7d'"
                    >
                        Last 7 days
                    </button>
                    <button
                        type="button"
                        :class="attendancePreset === 'month' ? attendancePresetActiveClass : attendancePresetIdleClass"
                        @click="attendancePreset = 'month'"
                    >
                        This month
                    </button>
                </div>
            </div>
            <div v-if="attendanceChartLoading" class="flex items-center justify-center min-h-[200px] text-slate-500 text-sm">
                Loading chart…
            </div>
            <div v-else class="overflow-x-auto min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                <div :class="attendancePreset === 'month' ? 'min-w-[min(100%,720px)] sm:min-w-[860px]' : ''">
                    <AttendanceWorkHoursChart :payload="attendanceChartPayload" />
                </div>
            </div>
        </div>

        <!-- Today's Follow-ups & appointments -->
        <div class="space-y-6">
            <!-- Today's Follow-ups -->
            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 min-w-0">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4">
                    <h3 class="text-base sm:text-lg font-semibold text-slate-900">📅 Today's Follow-ups</h3>
                    <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm font-medium shrink-0 self-start sm:self-auto">
                        {{ todayFollowUps.length }}
                    </span>
                </div>
                <div v-if="todayFollowUps.length === 0" class="text-center py-8 text-slate-400">
                    <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    No follow-ups scheduled for today
                </div>
                <div v-else class="space-y-3 max-h-80 overflow-y-auto">
                    <div
                        v-for="followUp in todayFollowUps"
                        :key="followUp.id"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-3 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors group"
                    >
                        <div class="flex-1 min-w-0">
                            <router-link
                                v-if="followUp.customer_id"
                                :to="`/customers/${followUp.customer_id}`"
                                class="font-medium text-slate-900 text-blue-600 hover:text-blue-800 hover:underline break-words"
                            >
                                {{ followUp.customer?.name || 'Customer' }}
                            </router-link>
                            <div v-else class="font-medium text-slate-900">
                                {{ followUp.customer?.name || 'Customer' }}
                            </div>
                            <div class="text-xs text-slate-500">
                                {{ followUp.assignee?.name || 'Unassigned' }} • 
                                {{ formatTime(followUp.next_follow_up_at) }}
                            </div>
                        </div>
                        <div class="flex w-full shrink-0 flex-wrap gap-2 sm:w-auto sm:justify-end">
                            <button
                                @click="openActivityModal(followUp)"
                                class="min-h-10 flex-1 rounded-md bg-green-600 px-3 py-2 text-xs font-medium text-white opacity-100 transition-opacity hover:bg-green-700 touch-manipulation sm:flex-none sm:px-2 sm:py-1.5 sm:opacity-0 sm:group-hover:opacity-100"
                            >
                                Log
                            </button>
                            <router-link
                                v-if="followUp.customer_id"
                                :to="`/customers/${followUp.customer_id}`"
                                class="flex min-h-10 flex-1 items-center justify-center rounded-md bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700 touch-manipulation sm:min-h-0 sm:flex-none sm:px-2 sm:py-1.5"
                            >
                                View
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Appointments -->
            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 min-w-0">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4">
                    <h3 class="text-base sm:text-lg font-semibold text-slate-900">📆 Today's Appointments</h3>
                    <span class="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-sm font-medium shrink-0 self-start sm:self-auto">
                        {{ todayAppointments.length }}
                    </span>
                </div>
                <div v-if="todayAppointments.length === 0" class="text-center py-8 text-slate-400">
                    <svg class="w-12 h-12 mx-auto mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    <p class="text-sm font-medium text-slate-500">No appointments for today</p>
                    <p class="text-xs mt-1 max-w-xs mx-auto">Appointments are always shown for the current date. Add one from a customer or lead (Appointment tab).</p>
                </div>
                <div v-else class="space-y-3 max-h-80 overflow-y-auto">
                    <div
                        v-for="apt in todayAppointments"
                        :key="apt.id"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-3 bg-amber-50 rounded-lg hover:bg-amber-100 transition-colors group"
                    >
                        <div class="flex-1 min-w-0">
                            <router-link
                                v-if="apt.customer_id"
                                :to="`/customers/${apt.customer_id}`"
                                class="font-medium text-slate-900 text-blue-600 hover:text-blue-800 hover:underline break-words"
                            >
                                {{ apt.customer?.name || 'Customer' }}
                            </router-link>
                            <div v-else class="font-medium text-slate-900">
                                {{ apt.customer?.name || 'Customer' }}
                            </div>
                            <div class="text-sm text-slate-600 mt-0.5">{{ apt.description || 'Appointment' }}</div>
                            <div class="text-xs text-slate-500 mt-1">{{ apt.appointment_time || '10:00' }}</div>
                        </div>
                        <div class="flex w-full shrink-0 flex-wrap gap-2 sm:w-auto sm:justify-end">
                            <button
                                v-if="apt.lead_id"
                                @click="openCompleteForAppointment(apt)"
                                class="min-h-10 flex-1 rounded-md bg-green-600 px-3 py-2 text-xs font-medium text-white opacity-100 transition-opacity hover:bg-green-700 touch-manipulation sm:flex-none sm:px-2 sm:py-1.5 sm:opacity-0 sm:group-hover:opacity-100"
                            >
                                Complete
                            </button>
                            <router-link
                                v-if="apt.customer_id"
                                :to="`/customers/${apt.customer_id}`"
                                class="flex min-h-10 flex-1 items-center justify-center rounded-md bg-amber-600 px-3 py-2 text-xs font-medium text-white hover:bg-amber-700 touch-manipulation sm:min-h-0 sm:flex-none sm:px-2 sm:py-1.5"
                            >
                                View
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Log Activity Modal -->
        <LogActivityModal
            v-if="showActivityModal && activityLead"
            :lead="activityLead"
            @close="closeActivityModal"
            @saved="handleActivitySaved"
        />

        <!-- Complete Follow-up / Appointment Modal -->
        <div v-if="showCompleteModal" class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4">
            <div class="max-h-[92vh] w-full max-w-md overflow-y-auto rounded-t-2xl bg-white shadow-xl sm:rounded-xl">
                <div class="border-b border-slate-200 p-4 sm:p-6">
                    <h3 class="text-lg font-semibold text-slate-900 sm:text-xl">Complete Follow-up / Appointment</h3>
                </div>
                <form @submit.prevent="completeFollowUp" class="space-y-4 p-4 sm:p-6">
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
                    <div class="flex flex-col-reverse gap-2 pt-4 sm:flex-row sm:justify-end sm:gap-3">
                        <button type="button" @click="closeCompleteModal" :disabled="completingFollowUp" class="min-h-11 w-full rounded-lg border border-slate-300 px-4 py-2.5 text-slate-700 hover:bg-slate-50 disabled:opacity-50 touch-manipulation sm:w-auto">
                            Cancel
                        </button>
                        <button type="submit" :disabled="completingFollowUp" class="min-h-11 w-full rounded-lg bg-green-600 px-4 py-2.5 text-white hover:bg-green-700 disabled:opacity-50 touch-manipulation sm:w-auto">
                            {{ completingFollowUp ? 'Saving...' : 'Complete' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import AttendanceClock from '@/components/AttendanceClock.vue';
import AttendanceWorkHoursChart from '@/components/AttendanceWorkHoursChart.vue';
import LogActivityModal from '@/components/LogActivityModal.vue';

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const user = computed(() => auth.user);
const isAdmin = computed(() => {
    const role = auth.user?.role?.name;
    return role === 'Admin' || role === 'System Admin';
});
const isManager = computed(() => auth.user?.role?.name === 'Manager');
/** Admin, System Admin, and Manager can filter the org dashboard; everyone else is self-scoped on the API. */
const canUseOrgDashboardFilters = computed(() => isAdmin.value || isManager.value);

const dashboardMeta = ref({});

const welcomeName = computed(() => {
    const n = user.value?.name?.trim();
    if (!n) return 'there';
    return n.split(/\s+/)[0];
});

const isSelfDashboardScope = computed(() => dashboardMeta.value.viewer_scope === 'self');

const loading = ref(false);
const dashboardData = ref({});

const attendancePreset = ref('3d');
const attendanceChartPayload = ref(null);
const attendanceChartLoading = ref(false);

const attendancePresetActiveClass =
    'text-xs font-semibold px-2.5 py-1 rounded-lg border transition-colors touch-manipulation bg-slate-900 text-white border-slate-900';
const attendancePresetIdleClass =
    'text-xs font-semibold px-2.5 py-1 rounded-lg border transition-colors touch-manipulation bg-white border-slate-200 text-slate-700 hover:bg-slate-100';

const todayFollowUps = ref([]);
const todayAppointments = ref([]);
const monthlyTopPerformer = ref(null);
const ticketStatusCounts = ref({ open: 0, in_progress: 0, closed: 0 });
const showActivityModal = ref(false);
const activityLead = ref(null);
const showCompleteModal = ref(false);
const completingFollowUp = ref(false);
const selectedFollowUp = ref(null);
const completeForm = ref({
    remarks: '',
    saleHappened: false,
    newStage: 'won',
    nextFollowUpAt: '',
});
const employees = ref([]);
const employeeTargets = ref([]);

const performerMonthTarget = computed(() => {
    const p = monthlyTopPerformer.value;
    if (!p) return null;
    const id = Number(p.id);
    return employeeTargets.value.find((t) => Number(t.user_id) === id) || null;
});

// Date range defaults to last 7 days on mount; from/to are always sent to /api/dashboard.
const filters = ref({
    from: '',
    to: '',
    employee_id: ''
});
const filtersOpen = ref(false);
/** Tracks which quick preset is selected, or 'custom' after manual date edits. */
const dateRangePreset = ref('7d');

const activeFilterCount = computed(() => {
    let n = 0;
    if (dateRangePreset.value !== '7d') n++;
    if (canUseOrgDashboardFilters.value && filters.value.employee_id) n++;
    return n;
});

const formatFilterDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

const filterPeriodLabel = computed(() => {
    const parts = [];
    if (filters.value.from && filters.value.to) {
        const from = new Date(filters.value.from + 'T12:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
        const to = new Date(filters.value.to + 'T12:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
        parts.push(`${from} - ${to}`);
    } else if (filters.value.from) {
        parts.push(`From ${new Date(filters.value.from + 'T12:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}`);
    } else if (filters.value.to) {
        parts.push(`Until ${new Date(filters.value.to + 'T12:00:00').toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}`);
    }
    if (canUseOrgDashboardFilters.value && filters.value.employee_id) {
        const emp = employees.value.find((e) => e.id == filters.value.employee_id);
        if (emp) parts.push(emp.name);
    }
    return parts.length > 0 ? parts.join(' • ') : 'This month';
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num || 0);
};

const formatTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};

const loadEmployees = async () => {
    try {
        const response = await axios.get('/api/users');
        employees.value = response.data || [];
    } catch (error) {
        console.error('Failed to load employees:', error);
    }
};

function formatLocalYmd(d) {
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function startOfWeekMonday(d) {
    const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
    const day = x.getDay();
    const diff = day === 0 ? -6 : 1 - day;
    x.setDate(x.getDate() + diff);
    return x;
}

/** Sets filters.from / filters.to only (no request). Same calendar rules as the leads list. */
function applyDatePresetRanges(kind) {
    const now = new Date();
    if (kind === 'today') {
        const t = formatLocalYmd(now);
        filters.value.from = t;
        filters.value.to = t;
    } else if (kind === '2d') {
        const end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const start = new Date(end);
        start.setDate(start.getDate() - 1);
        filters.value.from = formatLocalYmd(start);
        filters.value.to = formatLocalYmd(end);
    } else if (kind === '7d') {
        const end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const start = new Date(end);
        start.setDate(start.getDate() - 6);
        filters.value.from = formatLocalYmd(start);
        filters.value.to = formatLocalYmd(end);
    } else if (kind === 'last_week') {
        const thisMonday = startOfWeekMonday(now);
        const lastMonday = new Date(thisMonday);
        lastMonday.setDate(lastMonday.getDate() - 7);
        const lastSunday = new Date(lastMonday);
        lastSunday.setDate(lastMonday.getDate() + 6);
        filters.value.from = formatLocalYmd(lastMonday);
        filters.value.to = formatLocalYmd(lastSunday);
    } else if (kind === 'month') {
        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        filters.value.from = formatLocalYmd(start);
        filters.value.to = formatLocalYmd(end);
    } else if (kind === '30d') {
        const end = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        const start = new Date(end);
        start.setDate(start.getDate() - 29);
        filters.value.from = formatLocalYmd(start);
        filters.value.to = formatLocalYmd(end);
    } else if (kind === 'week') {
        const start = startOfWeekMonday(now);
        const end = new Date(start);
        end.setDate(start.getDate() + 6);
        filters.value.from = formatLocalYmd(start);
        filters.value.to = formatLocalYmd(end);
    }
}

function presetButtonClass(kind) {
    const base =
        'text-xs font-semibold px-2.5 py-1 rounded-lg border transition-colors touch-manipulation';
    const active = dateRangePreset.value === kind;
    if (active) return `${base} bg-slate-900 text-white border-slate-900`;
    return `${base} bg-white border-slate-200 text-slate-700 hover:bg-slate-100`;
}

function onDashboardDateManualChange() {
    dateRangePreset.value = 'custom';
}

function selectDatePreset(kind) {
    applyDatePresetRanges(kind);
    dateRangePreset.value = kind;
    loadDashboard();
}

const resetFilters = () => {
    filters.value.employee_id = '';
    applyDatePresetRanges('7d');
    dateRangePreset.value = '7d';
    loadDashboard();
};

/** Safe numeric parse for API values (strings, decimals, commas). */
function num(v) {
    if (v === null || v === undefined || v === '') return 0;
    if (typeof v === 'number') return Number.isFinite(v) ? v : 0;
    const n = parseFloat(String(v).replace(/,/g, ''));
    return Number.isFinite(n) ? n : 0;
}

/** Reporting /agents may be a bare array or wrapped in { data: [...] }. */
function normalizeAgentsList(payload) {
    if (Array.isArray(payload)) return payload;
    if (payload && Array.isArray(payload.data)) return payload.data;
    return [];
}

const loadAttendanceChart = async () => {
    attendanceChartLoading.value = true;
    try {
        const { data } = await axios.get('/api/hr/attendance/chart-summary', {
            params: { preset: attendancePreset.value },
        });
        attendanceChartPayload.value = data;
    } catch (e) {
        console.error('Failed to load attendance chart:', e);
        attendanceChartPayload.value = null;
    } finally {
        attendanceChartLoading.value = false;
    }
};

watch(attendancePreset, () => {
    loadAttendanceChart();
});

const loadDashboard = async () => {
    loading.value = true;
    try {
        // Build query params for main dashboard APIs (can be date-filtered)
        const params = {};
        if (filters.value.from) params.from = filters.value.from;
        if (filters.value.to) params.to = filters.value.to;
        if (filters.value.employee_id) params.agent_id = filters.value.employee_id;

        // Targets/achievement widgets should always use the current month (local), not the date filter
        const now = new Date();
        const currentMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
        const agentTargetParams = { month: currentMonth };
        if (filters.value.employee_id) agentTargetParams.agent_id = filters.value.employee_id;

        const ticketParams = {};
        if (filters.value.from) ticketParams.from = filters.value.from;
        if (filters.value.to) ticketParams.to = filters.value.to;
        if (filters.value.employee_id) ticketParams.assigned_to = filters.value.employee_id;

        const [dashboardRes, ticketsOpenRes, ticketsWorkingRes, ticketsClosedRes, agentsRes, targetsRes] = await Promise.all([
            axios.get('/api/dashboard', { params }),
            axios.get('/api/tickets', { params: { ...ticketParams, status: 'open', per_page: 1 } }),
            axios.get('/api/tickets', { params: { ...ticketParams, status: 'in_progress', per_page: 1 } }),
            axios.get('/api/tickets', { params: { ...ticketParams, status: 'closed', per_page: 1 } }),
            axios.get('/api/reporting/agents', { params: agentTargetParams }),
            axios.get('/api/hr/employee-targets', { params: { month: currentMonth } }),
        ]);

        const data = dashboardRes.data;

        dashboardMeta.value = data.meta || {};
        
        // Backend always returns stats for the requested from/to (defaults to month if omitted).
        const statsSource = data.stats?.filtered ?? data.stats?.monthly;
        const totalOpportunities =
            (statsSource && typeof statsSource.total_opportunities === 'number')
                ? statsSource.total_opportunities
                // Fallback for older API responses: approximate using active leads + won
                : ((statsSource?.leads || 0) + (statsSource?.won || 0));
        
        // Main dashboard data
        dashboardData.value = {
            total_leads: statsSource?.leads || 0,
            total_leads_all: totalOpportunities,
            won_product_units: statsSource?.won_product_units ?? 0,
            won_product_lines: statsSource?.won_product_lines ?? 0,
            conversion_rate: totalOpportunities > 0
                ? Math.min(100, Math.round(((statsSource?.won || 0) / totalOpportunities) * 100))
                : 0,
            // Also store monthly for comparison
            monthly: data.stats?.monthly || {},
            yearly: data.stats?.yearly || {},
            daily: data.stats?.daily || {},
        };

        // Today's follow-ups
        todayFollowUps.value = data.today_follow_ups || [];
        todayAppointments.value = data.today_appointments || [];

        const agents = normalizeAgentsList(agentsRes.data);

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
        let spotlight = performanceCandidates[0] || null;
        if (!spotlight && agents.length === 1) {
            spotlight = agents[0];
        }
        if (!spotlight && agents.length > 0) {
            spotlight = [...agents].sort((a, b) => {
                const bWon = Math.max(num(b.won_products), num(b.won_count), num(b.won_leads));
                const aWon = Math.max(num(a.won_products), num(a.won_count), num(a.won_leads));
                if (bWon !== aWon) return bWon - aWon;
                if (num(b.revenue) !== num(a.revenue)) return num(b.revenue) - num(a.revenue);
                return num(b.leads_count) - num(a.leads_count);
            })[0];
        }
        monthlyTopPerformer.value = spotlight;

        const openTotal = ticketsOpenRes.data?.total ?? ticketsOpenRes.data?.data?.length ?? 0;
        const workingTotal = ticketsWorkingRes.data?.total ?? ticketsWorkingRes.data?.data?.length ?? 0;
        const closedTotal = ticketsClosedRes.data?.total ?? ticketsClosedRes.data?.data?.length ?? 0;
        ticketStatusCounts.value = {
            open: openTotal,
            in_progress: workingTotal,
            closed: closedTotal,
        };

        // Employee targets / achievement board (current month)
        // Only include: (1) users with at least one non-zero target for this month, or
        // (2) users with real activity (appointments / wins / revenue). Do not list every sales agent.
        const targetsRaw = targetsRes.data?.data || [];
        const targetsByUser = {};

        for (const t of targetsRaw) {
            const tp = num(t.target_appointments);
            const ts = num(t.target_sales);
            const tr = num(t.target_revenue);
            if (tp === 0 && ts === 0 && tr === 0) {
                continue;
            }
            targetsByUser[t.user_id] = {
                user_id: t.user_id,
                user: t.user,
                target_appointments: tp,
                target_sales: ts,
                target_revenue: tr,
                achieved_appointments: 0,
                achieved_sales: 0,
                achieved_revenue: 0,
            };
        }

        const agentHasMeasurableActivity = (ag) =>
            num(ag.appointments_count) > 0 ||
            num(ag.won_products) > 0 ||
            num(ag.won_count) > 0 ||
            num(ag.won_leads) > 0 ||
            num(ag.revenue) > 0;

        for (const ag of agents) {
            const id = ag.id;
            const existing = targetsByUser[id];
            if (!existing && !agentHasMeasurableActivity(ag)) {
                continue;
            }
            const row =
                existing || {
                    user_id: id,
                    user: { id: ag.id, name: ag.name },
                    target_appointments: 0,
                    target_sales: 0,
                    target_revenue: 0,
                    achieved_appointments: 0,
                    achieved_sales: 0,
                    achieved_revenue: 0,
                };
            row.achieved_appointments = num(ag.appointments_count);
            row.achieved_sales = num(ag.won_products) || num(ag.won_count);
            row.achieved_revenue = num(ag.revenue);
            targetsByUser[id] = row;
        }

        employeeTargets.value = Object.values(targetsByUser)
            .map((t) => {
                const denom = num(t.target_appointments);
                const achievedAppt = num(t.achieved_appointments);
                const progress = denom > 0 ? Math.min(100, Math.round((achievedAppt / denom) * 100)) : 0;
                return {
                    ...t,
                    appointment_progress: progress,
                };
            })
            .filter((t) => {
                const hasTargets =
                    num(t.target_appointments) > 0 ||
                    num(t.target_sales) > 0 ||
                    num(t.target_revenue) > 0;
                const hasAchievement =
                    num(t.achieved_appointments) > 0 ||
                    num(t.achieved_sales) > 0 ||
                    num(t.achieved_revenue) > 0;
                return hasTargets || hasAchievement;
            })
            .sort((a, b) => num(b.achieved_appointments) - num(a.achieved_appointments));

    } catch (error) {
        console.error('Failed to load dashboard:', error);
    } finally {
        loading.value = false;
    }
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
        const saleWon = completeForm.value.saleHappened && completeForm.value.newStage === 'won';
        await axios.post(`/api/leads/${selectedFollowUp.value.id}/complete-followup`, payload);
        closeCompleteModal();
        loadDashboard();
        toast.success(saleWon ? 'Appointment completed. Sale counted; prospect is now a customer.' : 'Follow-up / appointment completed.');
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to complete');
    } finally {
        completingFollowUp.value = false;
    }
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
    loadDashboard();
    closeActivityModal();
};

function ticketsListingQuery(status) {
    const q = { status };
    if (filters.value.from) q.from = filters.value.from;
    if (filters.value.to) q.to = filters.value.to;
    if (filters.value.employee_id) q.assigned_to = String(filters.value.employee_id);
    return q;
}

function goToTicketsListing(status) {
    router.push({ path: '/tickets', query: ticketsListingQuery(status) });
}

// Navigate from summary cards into detailed lists
const goToTotalLeadsListing = () => {
    // All leads in the selected period (any stage)
    const query = {};
    if (filters.value.from) query.from = filters.value.from;
    if (filters.value.to) query.to = filters.value.to;
    if (filters.value.employee_id) query.assigned_to = filters.value.employee_id;
    router.push({ path: '/leads', query });
};

const goToFollowUpsListing = () => {
    const query = {};
    if (filters.value.from) query.from = filters.value.from;
    if (filters.value.to) query.to = filters.value.to;
    if (filters.value.employee_id) query.assigned_to = filters.value.employee_id;
    router.push({ path: '/followups', query });
};

const goToLeadsListing = (stage) => {
    const query = { stage };
    // For stage-specific views we show all leads in that stage,
    // but still allow narrowing by employee if selected.
    if (filters.value.employee_id) query.assigned_to = filters.value.employee_id;
    router.push({ path: '/leads', query });
};

onMounted(async () => {
    if (!auth.initialized) {
        await auth.bootstrap();
    }
    applyDatePresetRanges('7d');
    dateRangePreset.value = '7d';
    if (canUseOrgDashboardFilters.value) {
        loadEmployees();
    }
    await loadDashboard();
    loadAttendanceChart();
});
</script>
