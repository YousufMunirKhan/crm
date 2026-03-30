<template>
    <div class="max-w-7xl mx-auto w-full min-w-0 p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
        <!-- Filters for Admin: compact on mobile, full row on desktop -->
        <div v-if="isAdmin" class="bg-white rounded-xl shadow-sm border border-slate-200/80 overflow-hidden">
            <button
                type="button"
                @click="filtersOpen = !filtersOpen"
                class="md:hidden w-full flex items-center justify-between px-4 py-3 text-left text-slate-700 hover:bg-slate-50 transition-colors"
            >
                <span class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    <span class="font-medium text-sm">Filters</span>
                    <span v-if="activeFilterCount" class="px-2 py-0.5 bg-slate-900 text-white text-xs rounded-full">{{ activeFilterCount }}</span>
                </span>
                <span class="text-slate-400" :class="filtersOpen ? 'rotate-180' : ''">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                </span>
            </button>
            <div :class="['md:block', filtersOpen ? 'block' : 'hidden']">
                <div class="p-4 pt-0 md:pt-4 border-t md:border-t-0 border-slate-100">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 lg:gap-4 lg:items-end">
                        <div class="sm:col-span-1 lg:col-span-3">
                            <label class="block text-xs font-medium text-slate-500 mb-1">From</label>
                            <input
                                v-model="filters.from"
                                type="date"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        <div class="sm:col-span-1 lg:col-span-3">
                            <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
                            <input
                                v-model="filters.to"
                                type="date"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        <div class="sm:col-span-1 lg:col-span-3">
                            <label class="block text-xs font-medium text-slate-500 mb-1">Employee</label>
                            <select
                                v-model="filters.employee_id"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="">All Employees</option>
                                <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                                    {{ emp.name }}
                                </option>
                            </select>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-2 sm:col-span-2 lg:col-span-3 lg:flex-nowrap">
                            <button
                                @click="loadDashboard"
                                :disabled="loading"
                                class="flex-1 px-4 py-2 bg-slate-900 text-white rounded-lg text-sm font-medium hover:bg-slate-800 disabled:opacity-50 inline-flex items-center justify-center gap-2 order-2 sm:order-1"
                            >
                                <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ loading ? 'Applying...' : 'Apply' }}
                            </button>
                            <button
                                @click="resetFilters"
                                class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 order-1 sm:order-2"
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

        <!-- Attendance Clock for non-admin -->
        <AttendanceClock />

        <!-- Main Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
            <div
                class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-3 sm:p-5 text-white cursor-pointer hover:shadow-xl transition-transform transform hover:-translate-y-0.5 min-w-0"
                @click="goToTotalLeadsListing"
            >
                <div class="flex items-start sm:items-center justify-between gap-2 min-w-0">
                    <div class="min-w-0 flex-1">
                        <div class="text-blue-100 text-xs sm:text-sm font-medium leading-tight">Total Leads</div>
                        <div class="text-2xl sm:text-3xl font-bold mt-0.5 sm:mt-1 tabular-nums">{{ dashboardData.total_leads || 0 }}</div>
                        <div class="text-blue-200 text-[10px] sm:text-xs mt-1 sm:mt-2 line-clamp-2">{{ filterPeriodLabel }}</div>
                    </div>
                    <div class="bg-blue-400/30 p-2 sm:p-3 rounded-lg shrink-0">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-xl shadow-lg p-3 sm:p-5 text-white min-w-0">
                <div class="flex items-start sm:items-center justify-between gap-2 min-w-0">
                    <div class="min-w-0 flex-1">
                        <div class="text-emerald-100 text-xs sm:text-sm font-medium leading-tight">Revenue</div>
                        <div class="text-2xl sm:text-3xl font-bold mt-0.5 sm:mt-1 tabular-nums break-words">£{{ formatNumber(dashboardData.revenue || 0) }}</div>
                        <div class="text-emerald-200 text-[10px] sm:text-xs mt-1 sm:mt-2 line-clamp-2">{{ filterPeriodLabel }}</div>
                    </div>
                    <div class="bg-emerald-400/30 p-2 sm:p-3 rounded-lg shrink-0">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-violet-500 to-violet-600 rounded-xl shadow-lg p-3 sm:p-5 text-white min-w-0">
                <div class="flex items-start sm:items-center justify-between gap-2 min-w-0">
                    <div class="min-w-0 flex-1">
                        <div class="text-violet-100 text-xs sm:text-sm font-medium leading-tight">Pipeline Value</div>
                        <div class="text-2xl sm:text-3xl font-bold mt-0.5 sm:mt-1 tabular-nums break-words">£{{ formatNumber(dashboardData.pipeline_value || 0) }}</div>
                        <div class="text-violet-200 text-[10px] sm:text-xs mt-1 sm:mt-2">Active deals</div>
                    </div>
                    <div class="bg-violet-400/30 p-2 sm:p-3 rounded-lg shrink-0">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-br from-amber-500 to-amber-600 rounded-xl shadow-lg p-3 sm:p-5 text-white min-w-0">
                <div class="flex items-start sm:items-center justify-between gap-2 min-w-0">
                    <div class="min-w-0 flex-1">
                        <div class="text-amber-100 text-xs sm:text-sm font-medium leading-tight">Conversion Rate</div>
                        <div class="text-2xl sm:text-3xl font-bold mt-0.5 sm:mt-1 tabular-nums">{{ dashboardData.conversion_rate || 0 }}%</div>
                        <div class="text-amber-200 text-[10px] sm:text-xs mt-1 sm:mt-2">Won / Total</div>
                    </div>
                    <div class="bg-amber-400/30 p-2 sm:p-3 rounded-lg shrink-0">
                        <svg class="w-6 h-6 sm:w-8 sm:h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Secondary Stats Row -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
            <div
                class="bg-white rounded-xl shadow-sm p-3 sm:p-4 border-l-4 border-blue-500 cursor-pointer hover:bg-blue-50 transition-colors min-w-0"
                @click="goToLeadsListing('follow_up')"
            >
                <div class="text-[10px] sm:text-xs text-slate-500 uppercase tracking-wider leading-tight">Follow-ups</div>
                <div class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5 sm:mt-1 tabular-nums">{{ pipelineStats.follow_up || 0 }}</div>
            </div>
            <div
                class="bg-white rounded-xl shadow-sm p-3 sm:p-4 border-l-4 border-yellow-500 cursor-pointer hover:bg-yellow-50 transition-colors min-w-0"
                @click="goToLeadsListing('lead')"
            >
                <div class="text-[10px] sm:text-xs text-slate-500 uppercase tracking-wider leading-tight">Leads</div>
                <div class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5 sm:mt-1 tabular-nums">{{ pipelineStats.lead || 0 }}</div>
            </div>
            <div
                class="bg-white rounded-xl shadow-sm p-3 sm:p-4 border-l-4 border-orange-500 cursor-pointer hover:bg-orange-50 transition-colors min-w-0"
                @click="goToLeadsListing('hot_lead')"
            >
                <div class="text-[10px] sm:text-xs text-slate-500 uppercase tracking-wider leading-tight">Hot Leads</div>
                <div class="text-xl sm:text-2xl font-bold text-slate-900 mt-0.5 sm:mt-1 tabular-nums">{{ pipelineStats.hot_lead || 0 }}</div>
            </div>
            <div
                class="bg-white rounded-xl shadow-sm p-3 sm:p-4 border-l-4 border-green-500 cursor-pointer hover:bg-green-50 transition-colors min-w-0"
                @click="goToLeadsListing('won')"
            >
                <div class="text-[10px] sm:text-xs text-slate-500 uppercase tracking-wider leading-tight">Won</div>
                <div class="text-xl sm:text-2xl font-bold text-green-600 mt-0.5 sm:mt-1 tabular-nums">{{ pipelineStats.won || 0 }}</div>
            </div>
        </div>

        <!-- Products Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4">
            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5 flex items-center gap-3 sm:gap-4 min-w-0">
                <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="text-xs sm:text-sm text-slate-500">Products Won</div>
                    <div class="text-xl sm:text-2xl font-bold text-green-600 tabular-nums">{{ productStats.won || 0 }}</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5 flex items-center gap-3 sm:gap-4 min-w-0">
                <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center">
                    <svg class="w-7 h-7 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="text-xs sm:text-sm text-slate-500">Products Lost</div>
                    <div class="text-xl sm:text-2xl font-bold text-red-600 tabular-nums">{{ productStats.lost || 0 }}</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-5 flex items-center gap-3 sm:gap-4 min-w-0">
                <div class="w-14 h-14 bg-amber-100 rounded-full flex items-center justify-center">
                    <svg class="w-7 h-7 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="text-xs sm:text-sm text-slate-500">Products Pending</div>
                    <div class="text-xl sm:text-2xl font-bold text-amber-600 tabular-nums">{{ productStats.pending || 0 }}</div>
                </div>
            </div>
        </div>

        <!-- Monthly #1 Spotlight (visible to everyone, hidden when no activity) -->
        <div
            v-if="monthlyTopPerformer"
            class="bg-gradient-to-r from-amber-50 via-white to-emerald-50 border border-amber-100 rounded-2xl shadow-sm p-4 sm:p-5 md:p-6 min-w-0"
        >
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 min-w-0">
                <div class="flex items-center gap-3 sm:gap-4 min-w-0">
                    <div class="w-12 h-12 rounded-full bg-amber-500 text-white flex items-center justify-center font-bold text-lg">
                        1
                    </div>
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-100 text-amber-800 text-xs font-semibold mb-2">
                            🏅 Top Performer of the Month
                        </div>
                        <div class="text-xl font-bold text-slate-900">{{ monthlyTopPerformer.name }}</div>
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
                        <div class="text-xs text-slate-500">Sales Won</div>
                        <div class="text-sm font-bold text-slate-900">{{ monthlyTopPerformer.won_products || monthlyTopPerformer.won_count || 0 }}</div>
                    </div>
                    <div class="px-3 py-2 bg-white rounded-lg border border-slate-100">
                        <div class="text-xs text-slate-500">Conversion</div>
                        <div class="text-sm font-bold text-slate-900">{{ monthlyTopPerformer.conversion_rate || 0 }}%</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Follow-ups & Top Performers -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
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
                        <div class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                            <button
                                @click="openActivityModal(followUp)"
                                class="opacity-100 sm:opacity-0 sm:group-hover:opacity-100 px-2 py-1.5 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition-opacity touch-manipulation"
                            >
                                Log
                            </button>
                            <router-link
                                v-if="followUp.customer_id"
                                :to="`/customers/${followUp.customer_id}`"
                                class="px-2 py-1.5 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 touch-manipulation"
                            >
                                View
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Performers -->
            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 min-w-0">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4">
                    <h3 class="text-base sm:text-lg font-semibold text-slate-900">🏆 Top Performers</h3>
                    <span class="text-xs sm:text-sm text-slate-500">This Month</span>
                </div>
                <div v-if="topPerformers.length === 0" class="text-center py-8 text-slate-400">
                    No performance data available
                </div>
                <div v-else class="space-y-4">
                    <div
                        v-for="(performer, index) in topPerformers"
                        :key="performer.id"
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4"
                    >
                        <div
                            class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-white shrink-0"
                            :class="{
                                'bg-yellow-500': index === 0,
                                'bg-slate-400': index === 1,
                                'bg-amber-600': index === 2,
                                'bg-slate-300': index > 2
                            }"
                        >
                            {{ index + 1 }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="font-medium text-slate-900 truncate">{{ performer.name }}</div>
                            <div class="text-xs text-slate-500">{{ performer.leads_count }} leads • {{ performer.won_count }} won</div>
                        </div>
                        <div class="text-left sm:text-right sm:ml-auto shrink-0">
                            <div class="font-bold text-green-600">£{{ formatNumber(performer.revenue) }}</div>
                            <div class="text-xs text-slate-500">{{ performer.conversion_rate }}% conv.</div>
                        </div>
                    </div>
                </div>
                <div class="mt-6 border-t border-slate-100 pt-4" v-if="employeeTargets.length && isAdmin">
                    <h4 class="text-sm font-semibold text-slate-800 mb-3">Targets vs Achievements (this month)</h4>
                    <div class="space-y-3 max-h-64 overflow-y-auto">
                        <div
                            v-for="t in employeeTargets"
                            :key="t.user_id"
                            class="flex flex-col gap-1 p-3 rounded-lg bg-slate-50"
                        >
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                                <div class="font-medium text-slate-900 truncate min-w-0">
                                    {{ t.user?.name || 'Employee' }}
                                </div>
                                <div class="text-xs text-slate-500 sm:ml-2 shrink-0">
                                    {{ t.achieved_appointments }} / {{ t.target_appointments || 0 }} appointments
                                </div>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden">
                                <div
                                    class="h-1.5 rounded-full bg-gradient-to-r from-emerald-500 to-emerald-600"
                                    :style="{ width: `${t.appointment_progress}%` }"
                                ></div>
                            </div>
                            <div class="flex items-center justify-between text-xs text-slate-600 mt-1">
                                <div>
                                    Sales: {{ t.achieved_sales }} / {{ t.target_sales || 0 }}
                                </div>
                                <div>
                                    Won value: £{{ formatNumber(t.achieved_revenue) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Today's Appointments (full width row) -->
            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 lg:col-span-2 min-w-0">
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
                        <div class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                            <button
                                v-if="apt.lead_id"
                                @click="openCompleteForAppointment(apt)"
                                class="opacity-100 sm:opacity-0 sm:group-hover:opacity-100 px-2 py-1.5 text-xs bg-green-600 text-white rounded hover:bg-green-700 transition-opacity touch-manipulation"
                            >
                                Complete
                            </button>
                            <router-link
                                v-if="apt.customer_id"
                                :to="`/customers/${apt.customer_id}`"
                                class="px-2 py-1.5 text-xs bg-amber-600 text-white rounded hover:bg-amber-700 touch-manipulation"
                            >
                                View
                            </router-link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Targets (any role with a target set, no revenue shown) -->
        <div
            v-if="myTarget"
            class="bg-white rounded-xl shadow-sm p-4 sm:p-5 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 min-w-0"
        >
            <div class="min-w-0">
                <h3 class="text-base sm:text-lg font-semibold text-slate-900">🎯 My Targets (this month)</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Set by your admin. Achievements are calculated automatically from your appointments and won sales.
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-4 sm:items-center w-full sm:w-auto">
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Appointments</div>
                    <div class="text-sm font-medium text-slate-900">
                        {{ myTarget.achieved_appointments }} / {{ myTarget.target_appointments || 0 }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-slate-500 uppercase tracking-wide">Sales</div>
                    <div class="text-sm font-medium text-slate-900">
                        {{ myTarget.achieved_sales }} / {{ myTarget.target_sales || 0 }}
                    </div>
                </div>
                <div class="w-full sm:w-40">
                    <div class="flex justify-between text-xs text-slate-500 mb-1">
                        <span>Progress</span>
                        <span>{{ myTarget.appointment_progress }}%</span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                        <div
                            class="h-2 rounded-full bg-gradient-to-r from-emerald-500 to-emerald-600"
                            :style="{ width: `${myTarget.appointment_progress}%` }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lead Sources & Recent Leads -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Lead Sources -->
            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 min-w-0">
                <h3 class="text-base sm:text-lg font-semibold text-slate-900 mb-4">📊 Lead Sources</h3>
                <div v-if="Object.keys(leadSources).length === 0" class="text-center py-8 text-slate-400">
                    No lead source data
                </div>
                <div v-else class="space-y-3">
                    <div v-for="(count, source) in leadSources" :key="source" class="flex items-center gap-3">
                        <div class="flex-1">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm font-medium text-slate-700">{{ source || 'Unknown' }}</span>
                                <span class="text-sm text-slate-500">{{ count }}</span>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-2">
                                <div
                                    class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full transition-all duration-500"
                                    :style="{ width: `${(count / maxSourceCount) * 100}%` }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Leads -->
            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 min-w-0">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4">
                    <h3 class="text-base sm:text-lg font-semibold text-slate-900">🆕 Recent Leads</h3>
                    <router-link to="/leads/pipeline" class="text-sm text-blue-600 hover:underline shrink-0">View All</router-link>
                </div>
                <div v-if="recentLeads.length === 0" class="text-center py-8 text-slate-400">
                    No recent leads
                </div>
                <div v-else class="space-y-3">
                    <div
                        v-for="lead in recentLeads"
                        :key="lead.id"
                        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between p-3 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors group"
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
                            <div class="flex items-center gap-2 mt-1">
                                <span
                                    class="px-2 py-0.5 text-xs rounded"
                                    :class="getStageClass(lead.stage)"
                                >
                                    {{ formatStage(lead.stage) }}
                                </span>
                                <span class="text-xs text-slate-500">{{ lead.source || '-' }}</span>
                            </div>
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
        </div>

        <!-- Quick Stats Row -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4">
            <div
                class="bg-white rounded-xl shadow-sm p-3 sm:p-5 cursor-pointer hover:bg-blue-50 transition-colors min-w-0"
                @click="router.push({ path: '/customers', query: { type: 'customer' } })"
            >
                <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 bg-blue-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[10px] sm:text-xs text-slate-500 leading-tight">Total Customers</div>
                        <div class="text-lg sm:text-xl font-bold text-slate-900 tabular-nums">{{ quickStats.total_customers || 0 }}</div>
                    </div>
                </div>
            </div>
            <div
                class="bg-white rounded-xl shadow-sm p-3 sm:p-5 cursor-pointer hover:bg-green-50 transition-colors min-w-0"
                @click="router.push('/invoices')"
            >
                <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 bg-green-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[10px] sm:text-xs text-slate-500 leading-tight">Total Invoices</div>
                        <div class="text-lg sm:text-xl font-bold text-slate-900 tabular-nums">{{ quickStats.total_invoices || 0 }}</div>
                    </div>
                </div>
            </div>
            <div
                class="bg-white rounded-xl shadow-sm p-3 sm:p-5 cursor-pointer hover:bg-orange-50 transition-colors min-w-0"
                @click="router.push('/tickets')"
            >
                <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 bg-orange-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[10px] sm:text-xs text-slate-500 leading-tight">Open Tickets</div>
                        <div class="text-lg sm:text-xl font-bold text-slate-900 tabular-nums">{{ quickStats.open_tickets || 0 }}</div>
                    </div>
                </div>
            </div>
            <div
                class="bg-white rounded-xl shadow-sm p-3 sm:p-5 cursor-pointer hover:bg-purple-50 transition-colors min-w-0"
                @click="router.push('/employees')"
            >
                <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <div class="w-9 h-9 sm:w-10 sm:h-10 bg-purple-100 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <div class="text-[10px] sm:text-xs text-slate-500 leading-tight">Active Agents</div>
                        <div class="text-lg sm:text-xl font-bold text-slate-900 tabular-nums">{{ quickStats.active_agents || 0 }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Open Tickets -->
        <div v-if="openTickets.length > 0" class="bg-white rounded-xl shadow-sm p-4 sm:p-6 min-w-0">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4">
                <h3 class="text-base sm:text-lg font-semibold text-slate-900">🎫 Open Tickets</h3>
                <router-link to="/tickets" class="text-sm text-blue-600 hover:underline shrink-0">View All</router-link>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div
                    v-for="ticket in openTickets.slice(0, 6)"
                    :key="ticket.id"
                    class="p-4 border border-slate-200 rounded-lg hover:border-slate-300 transition-colors"
                >
                    <div class="font-medium text-slate-900 truncate">{{ ticket.subject }}</div>
                    <div class="flex items-center gap-2 mt-2">
                        <span
                            class="px-2 py-0.5 text-xs rounded"
                            :class="{
                                'bg-red-100 text-red-700': ticket.priority === 'high',
                                'bg-yellow-100 text-yellow-700': ticket.priority === 'medium',
                                'bg-green-100 text-green-700': ticket.priority === 'low'
                            }"
                        >
                            {{ ticket.priority }}
                        </span>
                        <router-link
                            v-if="ticket.customer_id"
                            :to="`/customers/${ticket.customer_id}`"
                            class="text-xs text-blue-600 hover:text-blue-800 hover:underline"
                        >
                            {{ ticket.customer?.name || 'Customer' }}
                        </router-link>
                        <span v-else class="text-xs text-slate-500">{{ ticket.customer?.name || 'Customer' }}</span>
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
        <div v-if="showCompleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-md w-full max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-slate-200">
                    <h3 class="text-xl font-semibold text-slate-900">Complete Follow-up / Appointment</h3>
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
                            {{ completingFollowUp ? 'Saving...' : 'Complete' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import AttendanceClock from '@/components/AttendanceClock.vue';
import LogActivityModal from '@/components/LogActivityModal.vue';

const auth = useAuthStore();
const toast = useToastStore();
const router = useRouter();
const user = computed(() => auth.user);
const isAdmin = computed(() => {
    const role = auth.user?.role?.name;
    return role === 'Admin' || role === 'System Admin';
});

const loading = ref(false);
const dashboardData = ref({});
const pipelineStats = ref({});
const productStats = ref({});
const recentLeads = ref([]);
const todayFollowUps = ref([]);
const todayAppointments = ref([]);
const topPerformers = ref([]);
const monthlyTopPerformer = ref(null);
const leadSources = ref({});
const quickStats = ref({});
const openTickets = ref([]);
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
const myTarget = computed(() => {
    if (!user.value) return null;
    return employeeTargets.value.find((t) => t.user_id === user.value.id) || null;
});

// Filters for admin — default today/tomorrow set on mount so they're always current
function getDefaultFilterDates() {
    const now = new Date();
    const from = now.toISOString().split('T')[0];
    const next = new Date(now);
    next.setDate(next.getDate() + 1);
    const to = next.toISOString().split('T')[0];
    return { from, to };
}
const filters = ref({
    from: '',
    to: '',
    employee_id: ''
});
const filtersOpen = ref(false);

const activeFilterCount = computed(() => {
    let n = 0;
    if (filters.value.from) n++;
    if (filters.value.to) n++;
    if (filters.value.employee_id) n++;
    return n;
});

const formatFilterDate = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr + 'T12:00:00');
    return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};

const filterPeriodLabel = computed(() => {
    if (isAdmin.value && (filters.value.from || filters.value.to || filters.value.employee_id)) {
        const parts = [];
        if (filters.value.from && filters.value.to) {
            const from = new Date(filters.value.from).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
            const to = new Date(filters.value.to).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
            parts.push(`${from} - ${to}`);
        } else if (filters.value.from) {
            parts.push(`From ${new Date(filters.value.from).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}`);
        } else if (filters.value.to) {
            parts.push(`Until ${new Date(filters.value.to).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' })}`);
        }
        if (filters.value.employee_id) {
            const emp = employees.value.find(e => e.id == filters.value.employee_id);
            if (emp) parts.push(emp.name);
        }
        return parts.length > 0 ? parts.join(' • ') : 'Filtered period';
    }
    return 'This month';
});

const maxSourceCount = computed(() => {
    return Math.max(...Object.values(leadSources.value), 1);
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num || 0);
};

const formatTime = (dateString) => {
    if (!dateString) return '';
    return new Date(dateString).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};

const formatStage = (stage) => {
    if (!stage) return '-';
    return stage.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const getStageClass = (stage) => {
    const classes = {
        follow_up: 'bg-blue-100 text-blue-800',
        lead: 'bg-yellow-100 text-yellow-800',
        hot_lead: 'bg-orange-100 text-orange-800',
        quotation: 'bg-purple-100 text-purple-800',
        won: 'bg-green-100 text-green-800',
        lost: 'bg-red-100 text-red-800',
    };
    return classes[stage] || 'bg-slate-100 text-slate-800';
};

const getLeadValue = (lead) => {
    if (lead.stage === 'won' && lead.items && lead.items.length > 0) {
        const itemsTotal = lead.items.reduce((sum, item) => {
            return sum + (parseFloat(item.total_price) || 0);
        }, 0);
        return itemsTotal > 0 ? itemsTotal : (lead.pipeline_value || 0);
    }
    return lead.total_value || lead.pipeline_value || 0;
};

const loadEmployees = async () => {
    try {
        const response = await axios.get('/api/users');
        employees.value = response.data || [];
    } catch (error) {
        console.error('Failed to load employees:', error);
    }
};

const resetFilters = () => {
    const now = new Date();
    const nextDay = new Date(now);
    nextDay.setDate(nextDay.getDate() + 1);
    filters.value = {
        from: now.toISOString().split('T')[0],
        to: nextDay.toISOString().split('T')[0],
        employee_id: ''
    };
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

        const [dashboardRes, ticketsRes, agentsRes, targetsRes] = await Promise.all([
            axios.get('/api/dashboard', { params }),
            axios.get('/api/tickets?status=open&per_page=10'),
            axios.get('/api/reporting/agents', { params: agentTargetParams }),
            axios.get('/api/hr/employee-targets', { params: { month: currentMonth } }),
        ]);

        const data = dashboardRes.data;
        
        // Use filtered stats if admin has applied filters, otherwise use monthly
        const hasFilters = isAdmin.value && (filters.value.from || filters.value.to || filters.value.employee_id);
        const statsSource = hasFilters && data.stats?.filtered ? data.stats.filtered : data.stats?.monthly;
        const totalOpportunities =
            (statsSource && typeof statsSource.total_opportunities === 'number')
                ? statsSource.total_opportunities
                // Fallback for older API responses: approximate using active leads + won
                : ((statsSource?.leads || 0) + (statsSource?.won || 0));
        
        // Main dashboard data
        dashboardData.value = {
            total_leads: statsSource?.leads || 0,
            revenue: statsSource?.revenue || 0,
            pipeline_value: Object.values(data.pipeline || {}).reduce((sum, val) => sum + (val || 0), 0),
            conversion_rate: totalOpportunities > 0
                ? Math.min(100, Math.round(((statsSource?.won || 0) / totalOpportunities) * 100))
                : 0,
            // Also store monthly for comparison
            monthly: data.stats?.monthly || {},
            yearly: data.stats?.yearly || {},
            daily: data.stats?.daily || {},
        };

        // Pipeline stats
        pipelineStats.value = data.pipeline || {};

        // Product stats
        productStats.value = data.products || {};

        // Today's follow-ups
        todayFollowUps.value = data.today_follow_ups || [];
        todayAppointments.value = data.today_appointments || [];

        // Recent leads
        recentLeads.value = data.recent_leads || [];

        // Lead sources
        leadSources.value = data.lead_sources || {};

        // Quick stats
        const agents = normalizeAgentsList(agentsRes.data);
        const activeAgents = agents.filter((a) => num(a.leads_count) > 0).length;

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

        quickStats.value = {
            total_customers: data.all_customers || 0,
            total_invoices: data.total_invoices || 0,
            open_tickets: ticketsRes.data.total || ticketsRes.data.data?.length || 0,
            // Active agents = agents who actually handled at least one lead in the period
            active_agents: activeAgents,
        };

        // Top performers: must match on-screen stats — only if they have won sales or revenue (£).
        // (Leads alone do not count; avoids "1 lead • 0 won £0" clutter.)
        topPerformers.value = agents
            .filter((a) => {
                const wonProducts = num(a.won_count) || num(a.won_products);
                const wonLeads = num(a.won_leads);
                const revenue = num(a.revenue);
                return wonProducts > 0 || wonLeads > 0 || revenue > 0;
            })
            .sort((a, b) => num(b.revenue) - num(a.revenue))
            .slice(0, 5);

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

        // Open tickets
        openTickets.value = ticketsRes.data.data || [];

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

// Navigate from summary cards into detailed lists
const goToTotalLeadsListing = () => {
    // Total Leads = all active leads in the selected period (any stage)
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
    // Default filter dates to today and tomorrow (so they're always current when opening dashboard)
    if (isAdmin.value) {
        const { from, to } = getDefaultFilterDates();
        filters.value.from = from;
        filters.value.to = to;
        loadEmployees();
    }
    loadDashboard();
});
</script>
