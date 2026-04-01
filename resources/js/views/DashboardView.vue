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

        <!-- Filters for Admin: compact on mobile, full row on desktop -->
        <div v-if="canUseOrgDashboardFilters" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <button
                type="button"
                @click="filtersOpen = !filtersOpen"
                class="md:hidden w-full min-h-12 flex items-center justify-between px-4 py-3 text-left text-slate-700 hover:bg-slate-50 transition-colors touch-manipulation active:bg-slate-50"
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
                                class="w-full min-h-11 px-3 py-2 border border-slate-300 rounded-lg text-base sm:text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        <div class="sm:col-span-1 lg:col-span-3">
                            <label class="block text-xs font-medium text-slate-500 mb-1">To</label>
                            <input
                                v-model="filters.to"
                                type="date"
                                class="w-full min-h-11 px-3 py-2 border border-slate-300 rounded-lg text-base sm:text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        <div class="sm:col-span-1 lg:col-span-3">
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
                        <div class="flex flex-col sm:flex-row gap-2 sm:col-span-2 lg:col-span-3 lg:flex-nowrap">
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

        <!-- Attendance Clock for non-admin -->
        <AttendanceClock />

        <!-- Main KPIs — clean cards (1 col phone, 2 col tablet, 4 desktop) -->
        <div class="grid grid-cols-1 min-[420px]:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div
                class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 cursor-pointer hover:shadow-md transition-shadow min-w-0"
                @click="goToTotalLeadsListing"
            >
                <div class="flex items-start justify-between gap-2 sm:gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide sm:text-xs">Active opportunities</p>
                        <p class="text-2xl font-semibold text-slate-900 tabular-nums mt-1 sm:text-3xl">{{ dashboardData.total_leads || 0 }}</p>
                        <p class="text-xs text-slate-400 mt-2 line-clamp-2">{{ filterPeriodLabel }}</p>
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
                        <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide sm:text-xs">Revenue</p>
                        <p class="text-xl font-semibold text-slate-900 tabular-nums mt-1 break-all sm:break-words sm:text-2xl md:text-3xl">£{{ formatNumber(dashboardData.revenue || 0) }}</p>
                        <p class="text-xs text-slate-400 mt-2 line-clamp-2">{{ filterPeriodLabel }}</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 flex items-center justify-center shrink-0 sm:w-11 sm:h-11">
                        <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 min-w-0">
                <div class="flex items-start justify-between gap-2 sm:gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-medium text-slate-500 uppercase tracking-wide sm:text-xs">Pipeline value</p>
                        <p class="text-xl font-semibold text-slate-900 tabular-nums mt-1 break-all sm:break-words sm:text-2xl md:text-3xl">£{{ formatNumber(dashboardData.pipeline_value || 0) }}</p>
                        <p class="text-xs text-slate-400 mt-2">Open stages</p>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-violet-100 flex items-center justify-center shrink-0 sm:w-11 sm:h-11">
                        <svg class="w-5 h-5 text-violet-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
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

        <!-- Trends + stage mix -->
        <div v-if="chartLeadsByMonth.length" class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
            <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-slate-100 p-4 sm:p-5 md:p-6 min-w-0">
                <h3 class="text-sm font-semibold text-slate-900 mb-3 sm:mb-4">Opportunities by month</h3>
                <div class="overflow-x-auto overscroll-x-contain -mx-1 px-1 pb-1 sm:mx-0 sm:px-0">
                    <div class="flex items-end justify-between gap-1.5 h-36 min-w-[300px] sm:min-w-0 sm:h-40 sm:gap-2">
                        <div
                            v-for="m in chartLeadsByMonth"
                            :key="m.key"
                            class="flex min-w-0 flex-1 flex-col items-center gap-1 sm:gap-2"
                        >
                            <div class="flex h-24 w-full flex-col justify-end overflow-hidden rounded-t-lg bg-slate-50 sm:h-28">
                                <div
                                    class="w-full min-h-[4px] rounded-t-lg bg-gradient-to-t from-indigo-600 to-indigo-400 transition-all"
                                    :style="{ height: `${chartBarHeightPercent(m.total)}%` }"
                                />
                            </div>
                            <span class="text-[9px] font-medium text-slate-500 sm:text-xs">{{ m.label }}</span>
                            <span class="text-[10px] tabular-nums text-slate-400 sm:text-xs">{{ m.total }}</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex min-w-0 flex-col rounded-2xl border border-slate-100 bg-white p-4 shadow-sm sm:p-5 md:p-6">
                <h3 class="text-sm font-semibold text-slate-900 mb-3 sm:mb-4">Stage mix</h3>
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:justify-center sm:gap-6">
                    <div class="relative h-32 w-32 shrink-0 sm:h-36 sm:w-36">
                        <div
                            class="w-full h-full rounded-full"
                            :style="{ background: pipelineDonutGradient }"
                        />
                        <div class="absolute inset-0 flex items-center justify-center">
                            <div class="flex h-[52%] w-[52%] flex-col items-center justify-center rounded-full bg-white shadow-inner">
                                <span class="text-base font-bold text-slate-900 sm:text-lg">{{ pipelineTotalCount }}</span>
                                <span class="text-[9px] uppercase text-slate-500 sm:text-[10px]">Total</span>
                            </div>
                        </div>
                    </div>
                    <ul class="w-full max-w-sm space-y-2 text-xs sm:mt-0 sm:max-w-none sm:flex-1">
                    <li v-for="seg in pipelineLegend" :key="seg.key" class="flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2 min-w-0">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: seg.color }" />
                            <span class="text-slate-600 truncate">{{ seg.label }}</span>
                        </span>
                        <span class="font-medium tabular-nums text-slate-900">{{ seg.count }}</span>
                    </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Secondary Stats Row -->
        <div class="grid grid-cols-2 gap-2 sm:gap-3 md:grid-cols-4 md:gap-4">
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
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
            <div class="flex min-w-0 items-center gap-3 rounded-xl bg-white p-4 shadow-sm sm:gap-4 sm:p-5">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-green-100 sm:h-14 sm:w-14">
                    <svg class="h-6 w-6 text-green-600 sm:h-7 sm:w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="text-xs sm:text-sm text-slate-500">Products Won</div>
                    <div class="text-xl sm:text-2xl font-bold text-green-600 tabular-nums">{{ productStats.won || 0 }}</div>
                </div>
            </div>
            <div class="flex min-w-0 items-center gap-3 rounded-xl bg-white p-4 shadow-sm sm:gap-4 sm:p-5">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:h-14 sm:w-14">
                    <svg class="h-6 w-6 text-red-600 sm:h-7 sm:w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="text-xs sm:text-sm text-slate-500">Products Lost</div>
                    <div class="text-xl sm:text-2xl font-bold text-red-600 tabular-nums">{{ productStats.lost || 0 }}</div>
                </div>
            </div>
            <div class="flex min-w-0 items-center gap-3 rounded-xl bg-white p-4 shadow-sm sm:gap-4 sm:p-5">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-amber-100 sm:h-14 sm:w-14">
                    <svg class="h-6 w-6 text-amber-600 sm:h-7 sm:w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="text-xs sm:text-sm text-slate-500">Products Pending</div>
                    <div class="text-xl sm:text-2xl font-bold text-amber-600 tabular-nums">{{ productStats.pending || 0 }}</div>
                </div>
            </div>
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
                        <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap sm:items-center sm:justify-end sm:gap-3">
                            <div class="text-sm font-medium text-slate-700 tabular-nums">£{{ formatNumber(getLeadValue(lead)) }}</div>
                            <div class="flex w-full gap-2 sm:w-auto sm:justify-end">
                                <button
                                    @click="openActivityModal(lead)"
                                    class="min-h-10 flex-1 rounded-md bg-green-600 px-3 py-2 text-xs font-medium text-white opacity-100 transition-opacity hover:bg-green-700 touch-manipulation sm:min-h-0 sm:flex-none sm:px-2 sm:py-1.5 sm:opacity-0 sm:group-hover:opacity-100"
                                >
                                    Log
                                </button>
                                <router-link
                                    v-if="lead.customer_id"
                                    :to="`/customers/${lead.customer_id}`"
                                    class="flex min-h-10 flex-1 items-center justify-center rounded-md bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700 touch-manipulation sm:min-h-0 sm:flex-none sm:px-2 sm:py-1.5 sm:opacity-0 sm:group-hover:opacity-100"
                                >
                                    View
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Stats Row -->
        <div class="grid grid-cols-1 min-[420px]:grid-cols-2 gap-3 md:grid-cols-4 md:gap-4">
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
const isManager = computed(() => auth.user?.role?.name === 'Manager');
/** Admin, System Admin, and Manager can filter the org dashboard; everyone else is self-scoped on the API. */
const canUseOrgDashboardFilters = computed(() => isAdmin.value || isManager.value);

const dashboardMeta = ref({});
const chartLeadsByMonth = ref([]);

const welcomeName = computed(() => {
    const n = user.value?.name?.trim();
    if (!n) return 'there';
    return n.split(/\s+/)[0];
});

const isSelfDashboardScope = computed(() => dashboardMeta.value.viewer_scope === 'self');

const loading = ref(false);
const dashboardData = ref({});
const pipelineStats = ref({});

const PIPELINE_DONUT_SEGMENTS = [
    { key: 'follow_up', label: 'Follow up', color: '#3b82f6' },
    { key: 'lead', label: 'Lead', color: '#eab308' },
    { key: 'hot_lead', label: 'Hot lead', color: '#f97316' },
    { key: 'quotation', label: 'Quotation', color: '#a855f7' },
    { key: 'won', label: 'Won', color: '#22c55e' },
    { key: 'lost', label: 'Lost', color: '#ef4444' },
];

const pipelineLegend = computed(() =>
    PIPELINE_DONUT_SEGMENTS.map((seg) => ({
        ...seg,
        count: num(pipelineStats.value[seg.key]),
    }))
);

const pipelineTotalCount = computed(() => pipelineLegend.value.reduce((s, seg) => s + seg.count, 0));

const pipelineDonutGradient = computed(() => {
    const total = pipelineTotalCount.value;
    if (total <= 0) return 'conic-gradient(#e2e8f0 0deg 360deg)';
    let angle = 0;
    const parts = [];
    for (const seg of pipelineLegend.value) {
        const c = seg.count;
        if (c <= 0) continue;
        const deg = (c / total) * 360;
        const start = angle;
        angle += deg;
        parts.push(`${seg.color} ${start}deg ${angle}deg`);
    }
    return parts.length ? `conic-gradient(${parts.join(', ')})` : 'conic-gradient(#e2e8f0 0deg 360deg)';
});

function chartBarHeightPercent(total) {
    const rows = chartLeadsByMonth.value;
    if (!rows.length) return 0;
    const m = Math.max(...rows.map((r) => num(r.total)), 1);
    return Math.max(8, Math.round((num(total) / m) * 100));
}
const productStats = ref({});
const recentLeads = ref([]);
const todayFollowUps = ref([]);
const todayAppointments = ref([]);
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

const performerMonthTarget = computed(() => {
    const p = monthlyTopPerformer.value;
    if (!p) return null;
    const id = Number(p.id);
    return employeeTargets.value.find((t) => Number(t.user_id) === id) || null;
});

// Filters for org roles: leave from/to empty by default so KPIs use full month (API startOfMonth → endOfDay).
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
    if (canUseOrgDashboardFilters.value && (filters.value.from || filters.value.to || filters.value.employee_id)) {
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
    filters.value = {
        from: '',
        to: '',
        employee_id: '',
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

        dashboardMeta.value = data.meta || {};
        chartLeadsByMonth.value = data.charts?.leads_by_month || [];
        
        // Use filtered stats if admin has applied filters, otherwise use monthly
        const hasFilters = canUseOrgDashboardFilters.value && (filters.value.from || filters.value.to || filters.value.employee_id);
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

        quickStats.value = {
            total_customers: data.all_customers || 0,
            total_invoices: data.total_invoices || 0,
            open_tickets: ticketsRes.data.total || ticketsRes.data.data?.length || 0,
            // Active agents = agents who actually handled at least one lead in the period
            active_agents: activeAgents,
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
    if (canUseOrgDashboardFilters.value) {
        loadEmployees();
    }
    loadDashboard();
});
</script>
