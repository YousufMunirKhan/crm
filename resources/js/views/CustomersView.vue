<template>
    <div class="w-full min-w-0">
    <ListingPageShell
        :title="customersPageTitle"
        :subtitle="customersPageSubtitle"
        :badge="customersBadge"
    >
        <template #actions>
            <div class="flex flex-wrap gap-2 w-full sm:w-auto justify-stretch sm:justify-end">
                <button
                    type="button"
                    @click="exportToCSV"
                    :disabled="exporting"
                    class="listing-btn-outline flex-1 min-w-[8rem] sm:flex-initial disabled:opacity-50"
                >
                    {{ exporting ? 'Exporting...' : 'Export CSV' }}
                </button>
                <button type="button" class="listing-btn-outline flex-1 min-w-[8rem] sm:flex-initial" @click="showImportModal = true">
                    Import
                </button>
                <router-link
                    :to="{ path: '/customers/add', query: { type: activeTab } }"
                    class="listing-btn-accent flex-1 min-w-[8rem] sm:flex-initial text-center touch-manipulation"
                >
                    + {{ activeTab === 'prospect' ? 'Add prospect' : 'Add customer' }}
                </router-link>
            </div>
        </template>

        <template #filters>
            <div class="space-y-4 min-w-0">
                <p class="text-sm">
                    <router-link
                        v-if="activeTab === 'prospect'"
                        :to="{ path: '/customers', query: { type: 'customer' } }"
                        class="text-blue-600 hover:text-blue-800 font-medium"
                    >
                        Go to Customers →
                    </router-link>
                    <router-link
                        v-else
                        :to="{ path: '/customers', query: { type: 'prospect' } }"
                        class="text-blue-600 hover:text-blue-800 font-medium"
                    >
                        Go to Prospects →
                    </router-link>
                </p>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filters
                </h3>
                <button
                    @click="showFilters = !showFilters"
                    class="text-sm text-blue-600 hover:text-blue-800 touch-manipulation self-start sm:self-auto"
                >
                    {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
                </button>
            </div>

            <!-- Quick Search -->
            <div class="relative min-w-0 w-full">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="filters.search"
                    type="text"
                    placeholder="Search name, business, phone, email, city…"
                    class="w-full min-w-0 max-w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 box-border"
                    @input="handleSearch"
                />
            </div>

            <!-- Advanced Filters -->
            <div v-show="showFilters" class="mt-4 pt-4 border-t border-slate-200">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Name</label>
                        <input
                            v-model="filters.name"
                            type="text"
                            placeholder="Filter by name..."
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @input="handleFilterChange"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Business name</label>
                        <input
                            v-model="filters.business_name"
                            type="text"
                            placeholder="Filter by business name..."
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @input="handleFilterChange"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Phone</label>
                        <input
                            v-model="filters.phone"
                            type="text"
                            placeholder="Filter by phone..."
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @input="handleFilterChange"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Email</label>
                        <input
                            v-model="filters.email"
                            type="text"
                            placeholder="Filter by email..."
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @input="handleFilterChange"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">City</label>
                        <input
                            v-model="filters.city"
                            type="text"
                            placeholder="Filter by city..."
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @input="handleFilterChange"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Postcode</label>
                        <input
                            v-model="filters.postcode"
                            type="text"
                            placeholder="Filter by postcode..."
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @input="handleFilterChange"
                        />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Assigned To</label>
                        <select
                            v-model="filters.assigned_to"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="">All Users</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Sort By</label>
                        <select
                            v-model="filters.sort_by"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="created_at">Date Created</option>
                            <option value="name">Name</option>
                            <option value="city">City</option>
                            <option value="postcode">Postcode</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Order</label>
                        <select
                            v-model="filters.sort_order"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            @change="applyFilters"
                        >
                            <option value="desc">Newest First</option>
                            <option value="asc">Oldest First</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button
                        @click="clearFilters"
                        class="px-4 py-2 text-sm text-slate-600 border border-slate-300 rounded-lg hover:bg-slate-50"
                    >
                        Clear Filters
                    </button>
                    <button
                        @click="applyFilters"
                        class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700"
                    >
                        Apply Filters
                    </button>
                </div>
            </div>

            <!-- Active Filters Display -->
            <div v-if="hasActiveFilters" class="mt-3 flex flex-wrap gap-2">
                <span
                    v-for="(value, key) in activeFilterTags"
                    :key="key"
                    class="inline-flex items-center gap-1 px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs"
                >
                    {{ key }}: {{ value }}
                    <button @click="removeFilter(key)" class="hover:text-blue-600">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </span>
            </div>
            </div>
        </template>

        <!-- Loading State -->
        <div v-if="loading" class="px-5 py-16 text-center text-slate-500">
            <div class="inline-flex items-center gap-2 text-slate-500">
                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Loading customers...
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="customers.length === 0" class="px-5 py-12 text-center">
            <div class="text-slate-400 mb-4">
                <svg class="mx-auto h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-700 mb-2">No {{ activeTab === 'prospect' ? 'Prospects' : 'Customers' }} Found</h3>
            <p class="text-slate-500 mb-6">
                {{ hasActiveFilters ? 'Try adjusting your filters or search terms.' : (activeTab === 'prospect' ? 'Get started by adding your first prospect.' : 'Get started by adding your first customer.') }}
            </p>
            <router-link
                v-if="!hasActiveFilters"
                :to="{ path: '/customers/add', query: { type: activeTab } }"
                class="listing-btn-accent inline-flex px-6 py-3 touch-manipulation"
            >
                + Add Your First {{ activeTab === 'prospect' ? 'Prospect' : 'Customer' }}
            </router-link>
            <button
                v-else
                type="button"
                @click="clearFilters"
                class="listing-btn-outline px-6 py-3"
            >
                Clear Filters
            </button>
        </div>

        <!-- Customers Table (lg+); card list below lg — avoids broken table-fixed on narrow widths -->
        <div v-else class="overflow-hidden min-w-0">
            <div class="hidden lg:block w-full overflow-x-auto">
                <table class="w-full min-w-[860px] table-auto">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Customer</th>
                            <th class="hidden xl:table-cell px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Business name</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Contact</th>
                            <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Location</th>
                            <th class="hidden lg:table-cell px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Created By</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Assigned</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">
                                {{ activeTab === 'prospect' ? 'Leads' : 'Products Won' }}
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr 
                            v-for="customer in customers" 
                            :key="customer.id" 
                            class="hover:bg-slate-50 transition-colors"
                        >
                            <td class="px-6 py-4 min-w-0 max-w-[14rem]">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 shrink-0 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-sm">
                                        {{ getInitials(customer.name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <router-link
                                            :to="`/customers/${customer.id}`"
                                            class="font-medium text-slate-900 hover:text-blue-600 block truncate"
                                        >
                                            {{ customer.name }}
                                        </router-link>
                                        <div v-if="customer.vat_number" class="text-xs text-slate-500 truncate">
                                            VAT: {{ customer.vat_number }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden xl:table-cell px-6 py-4 min-w-0 max-w-[10rem]">
                                <span v-if="customer.business_name" class="text-sm text-slate-900 break-words">{{ customer.business_name }}</span>
                                <span v-else class="text-slate-400 text-sm">—</span>
                            </td>
                            <td class="px-6 py-4 min-w-0 max-w-[12rem]">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 text-sm text-slate-900 min-w-0">
                                        <svg class="w-4 h-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        <span class="truncate">{{ customer.phone || '-' }}</span>
                                    </div>
                                    <div v-if="customer.email" class="flex items-center gap-2 text-sm text-slate-600 min-w-0">
                                        <svg class="w-4 h-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        <span class="truncate">{{ customer.email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden lg:table-cell px-6 py-4 min-w-0">
                                <div v-if="customer.city || customer.postcode" class="space-y-1">
                                    <div v-if="customer.city" class="text-sm text-slate-900 break-words">{{ customer.city }}</div>
                                    <div v-if="customer.postcode" class="text-xs text-slate-500 font-mono">{{ customer.postcode }}</div>
                                </div>
                                <span v-else class="text-slate-400 text-sm">-</span>
                            </td>
                            <td class="hidden lg:table-cell px-6 py-4">
                                <span v-if="customer.creator" class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-medium">
                                    {{ customer.creator.name }}
                                </span>
                                <span v-else class="text-slate-400 text-xs">-</span>
                            </td>
                            <td class="px-6 py-4 min-w-0 max-w-[10rem]">
                                <div v-if="customer.assigned_users && customer.assigned_users.length > 0" class="flex flex-wrap gap-1">
                                    <span
                                        v-for="user in customer.assigned_users.slice(0, 2)"
                                        :key="user.id"
                                        class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-medium"
                                    >
                                        {{ user.name }}
                                    </span>
                                    <span
                                        v-if="customer.assigned_users.length > 2"
                                        class="inline-flex items-center px-2 py-1 bg-slate-100 text-slate-600 rounded text-xs"
                                    >
                                        +{{ customer.assigned_users.length - 2 }}
                                    </span>
                                </div>
                                <span v-else class="text-slate-400 text-xs">Unassigned</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">
                                    {{ activeTab === 'prospect' ? (customer.leads?.length || 0) : (customer.won_products_count || 0) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <div class="flex flex-wrap justify-end gap-1 sm:gap-2">
                                    <router-link
                                        :to="`/customers/${customer.id}`"
                                        class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                        title="View"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </router-link>
                                    <button
                                        @click="openAssignmentModal(customer)"
                                        class="p-2 text-purple-600 hover:bg-purple-50 rounded-lg transition-colors"
                                        title="Assign"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                        </svg>
                                    </button>
                                    <router-link
                                        :to="`/customers/${customer.id}/edit`"
                                        class="p-2 text-green-600 hover:bg-green-50 rounded-lg transition-colors"
                                        title="Edit"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </router-link>
                                    <button
                                        @click="openDeleteConfirm(customer)"
                                        class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                                        title="Delete"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="lg:hidden px-3 pb-4 space-y-3 min-w-0">
                <div
                    v-for="customer in customers"
                    :key="`mobile-${customer.id}`"
                    class="rounded-xl border border-slate-200 bg-white shadow-sm shadow-slate-900/[0.04] p-4 space-y-3 min-w-0"
                >
                    <div class="flex items-start justify-between gap-3 min-w-0">
                        <router-link
                            :to="`/customers/${customer.id}`"
                            class="font-semibold text-slate-900 hover:text-blue-600 break-words min-w-0 flex-1"
                        >
                            {{ customer.name }}
                        </router-link>
                        <div class="shrink-0 inline-flex flex-col items-end gap-1">
                            <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                                {{ activeTab === 'prospect' ? 'Leads' : 'Won' }}
                            </span>
                            <div class="inline-flex items-center px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold tabular-nums">
                                {{ activeTab === 'prospect' ? (customer.leads?.length || 0) : (customer.won_products_count || 0) }}
                            </div>
                        </div>
                    </div>
                    <div v-if="customer.business_name" class="text-sm text-slate-600 break-words">
                        {{ customer.business_name }}
                    </div>
                    <div class="text-sm text-slate-600 space-y-1">
                        <div class="break-words"><span class="text-slate-500">Phone:</span> {{ customer.phone || '—' }}</div>
                        <div v-if="customer.email" class="break-all"><span class="text-slate-500">Email:</span> {{ customer.email }}</div>
                    </div>
                    <div class="text-sm text-slate-600 break-words">
                        {{ customer.city || '—' }}<span v-if="customer.postcode"> · {{ customer.postcode }}</span>
                    </div>
                    <div v-if="customer.creator" class="text-xs">
                        <span class="text-slate-500">Created by </span>
                        <span class="font-medium text-green-800 bg-green-50 px-2 py-0.5 rounded">{{ customer.creator.name }}</span>
                    </div>
                    <div class="text-sm text-slate-700">
                        <span class="text-slate-500 text-xs uppercase tracking-wide font-semibold">Assigned</span>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <template v-if="customer.assigned_users && customer.assigned_users.length">
                                <span
                                    v-for="user in customer.assigned_users"
                                    :key="user.id"
                                    class="inline-flex items-center px-2 py-0.5 bg-blue-100 text-blue-800 rounded text-xs font-medium"
                                >
                                    {{ user.name }}
                                </span>
                            </template>
                            <span v-else class="text-slate-400 text-xs">Unassigned</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-2 pt-1 border-t border-slate-100">
                        <router-link :to="`/customers/${customer.id}`" class="listing-link-edit">View</router-link>
                        <button type="button" class="text-purple-600 hover:text-purple-800 font-medium text-sm" @click="openAssignmentModal(customer)">Assign</button>
                        <router-link :to="`/customers/${customer.id}/edit`" class="listing-link-edit">Edit</router-link>
                        <button type="button" class="listing-link-delete" @click="openDeleteConfirm(customer)">Delete</button>
                    </div>
                </div>
            </div>
        </div>

        <template #pagination>
            <div v-if="pagination && customers.length && !loading" class="border-t border-slate-100">
                <div class="px-5 sm:px-6 py-3 flex flex-wrap justify-end items-center gap-3 bg-slate-50/50">
                    <span class="text-xs font-medium text-slate-600">Rows per page</span>
                    <select v-model="perPage" class="listing-input w-auto min-w-[9rem]" @change="changePerPage">
                        <option :value="15">15 per page</option>
                        <option :value="25">25 per page</option>
                        <option :value="50">50 per page</option>
                        <option :value="100">100 per page</option>
                    </select>
                </div>
                <Pagination
                    :pagination="pagination"
                    embedded
                    :result-label="activeTab === 'prospect' ? 'prospects' : 'customers'"
                    :singular-label="activeTab === 'prospect' ? 'prospect' : 'customer'"
                    @page-change="loadCustomers"
                />
            </div>
        </template>
    </ListingPageShell>

    <DeleteConfirm
        v-if="showDeleteConfirm"
        title="Delete Customer"
        :message="`Are you sure you want to delete ${customerToDelete?.name}? This will also delete all associated leads, tickets, and invoices.`"
        :loading="deleting"
        @confirm="confirmDelete"
        @cancel="closeDeleteConfirm"
    />

    <ImportModal
        v-if="showImportModal"
        @close="showImportModal = false"
        @imported="handleImportComplete"
    />

    <CustomerAssignmentModal
        v-if="showAssignmentModal && customerToAssign"
        :customer="customerToAssign"
        @close="closeAssignmentModal"
        @assigned="handleAssignmentComplete"
    />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';
import DeleteConfirm from '@/components/DeleteConfirm.vue';
import ImportModal from '@/components/ImportModal.vue';
import CustomerAssignmentModal from '@/components/CustomerAssignmentModal.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import Pagination from '@/components/Pagination.vue';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const toast = useToastStore();

const customers = ref([]);
const users = ref([]);
const pagination = ref(null);
const loading = ref(true);
const showDeleteConfirm = ref(false);
const customerToDelete = ref(null);
const deleting = ref(false);
const activeTab = ref(route.query.type === 'customer' ? 'customer' : 'prospect');
const showImportModal = ref(false);
const showAssignmentModal = ref(false);
const customerToAssign = ref(null);
const showFilters = ref(false);
const exporting = ref(false);
const perPage = ref(15);

let searchTimeout = null;

const filters = ref({
    search: '',
    name: '',
    business_name: '',
    phone: '',
    email: '',
    city: '',
    postcode: '',
    assigned_to: '',
    sort_by: 'created_at',
    sort_order: 'desc',
});

const hasActiveFilters = computed(() => {
    return filters.value.search || 
           filters.value.name || 
           filters.value.business_name ||
           filters.value.phone || 
           filters.value.email || 
           filters.value.city || 
           filters.value.postcode || 
           filters.value.assigned_to;
});

const activeFilterTags = computed(() => {
    const tags = {};
    if (filters.value.name) tags['Name'] = filters.value.name;
    if (filters.value.business_name) tags['Business name'] = filters.value.business_name;
    if (filters.value.phone) tags['Phone'] = filters.value.phone;
    if (filters.value.email) tags['Email'] = filters.value.email;
    if (filters.value.city) tags['City'] = filters.value.city;
    if (filters.value.postcode) tags['Postcode'] = filters.value.postcode;
    if (filters.value.assigned_to) {
        const user = users.value.find(u => u.id === filters.value.assigned_to);
        tags['Assigned'] = user?.name || filters.value.assigned_to;
    }
    return tags;
});

const customersPageTitle = computed(() => (activeTab.value === 'prospect' ? 'Prospects' : 'Customers'));

const customersPageSubtitle = computed(() =>
    activeTab.value === 'prospect'
        ? 'Pipeline contacts before conversion — search, filter, and hand off to Customers when won.'
        : 'Converted accounts and ongoing relationships — switch to Prospects for open pipeline.',
);

const customersBadge = computed(() =>
    pagination.value?.total != null ? `${pagination.value.total} Total` : null,
);

const getInitials = (name) => {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
};

const loadUsers = async () => {
    try {
        const { data } = await axios.get('/api/users');
        users.value = data.data || data || [];
    } catch (error) {
        console.error('Failed to load users:', error);
    }
};

const loadCustomers = async (page = 1) => {
    loading.value = true;
    try {
        const params = { 
            page, 
            per_page: perPage.value,
            sort_by: filters.value.sort_by,
            sort_order: filters.value.sort_order,
        };
        
        if (filters.value.search) params.search = filters.value.search;
        if (filters.value.name) params.name = filters.value.name;
        if (filters.value.business_name) params.business_name = filters.value.business_name;
        if (filters.value.phone) params.phone = filters.value.phone;
        if (filters.value.email) params.email = filters.value.email;
        if (filters.value.city) params.city = filters.value.city;
        if (filters.value.postcode) params.postcode = filters.value.postcode;
        if (filters.value.assigned_to) params.assigned_to = filters.value.assigned_to;
        params.type = activeTab.value;

        const { data } = await axios.get('/api/customers', { params });
        customers.value = data.data || [];
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page || perPage.value,
            total: data.total || 0,
        };
    } catch (error) {
        console.error('Failed to load customers:', error);
        toast.error('Failed to load customers. Please try again.');
    } finally {
        loading.value = false;
    }
};

const handleSearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadCustomers(1);
    }, 500);
};

const handleFilterChange = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadCustomers(1);
    }, 500);
};

const applyFilters = () => {
    loadCustomers(1);
};

const clearFilters = () => {
    filters.value = {
        search: '',
        name: '',
        business_name: '',
        phone: '',
        email: '',
        city: '',
        postcode: '',
        assigned_to: '',
        sort_by: 'created_at',
        sort_order: 'desc',
    };
    loadCustomers(1);
};

const removeFilter = (key) => {
    const keyMap = {
        'Name': 'name',
        'Business name': 'business_name',
        'Phone': 'phone',
        'Email': 'email',
        'City': 'city',
        'Postcode': 'postcode',
        'Assigned': 'assigned_to',
    };
    const filterKey = keyMap[key];
    if (filterKey) {
        filters.value[filterKey] = '';
        loadCustomers(1);
    }
};

const changePerPage = () => {
    loadCustomers(1);
};

const exportToCSV = async () => {
    exporting.value = true;
    try {
        const params = { per_page: 10000, type: activeTab.value };
        if (filters.value.search) params.search = filters.value.search;
        if (filters.value.name) params.name = filters.value.name;
        if (filters.value.business_name) params.business_name = filters.value.business_name;
        if (filters.value.phone) params.phone = filters.value.phone;
        if (filters.value.email) params.email = filters.value.email;
        if (filters.value.city) params.city = filters.value.city;
        if (filters.value.postcode) params.postcode = filters.value.postcode;
        
        const { data } = await axios.get('/api/customers', { params });
        const allCustomers = data.data || [];
        
        const columns = [
            { key: 'name', label: 'Name' },
            { key: 'business_name', label: 'Business name' },
            { key: 'phone', label: 'Phone' },
            { key: 'email', label: 'Email' },
            { key: 'address', label: 'Address' },
            { key: 'city', label: 'City' },
            { key: 'postcode', label: 'Postcode' },
            { key: 'vat_number', label: 'VAT Number' },
        ];
        
        exportCSV(allCustomers, columns, `customers_export_${new Date().toISOString().split('T')[0]}.csv`);
        toast.success(`Exported ${allCustomers.length} customers successfully.`);
    } catch (error) {
        console.error('Export failed:', error);
        toast.error('Failed to export customers. Please try again.');
    } finally {
        exporting.value = false;
    }
};

const handleImportComplete = () => {
    loadCustomers(pagination.value?.current_page || 1);
};

const openDeleteConfirm = (customer) => {
    customerToDelete.value = customer;
    showDeleteConfirm.value = true;
};

const closeDeleteConfirm = () => {
    showDeleteConfirm.value = false;
    customerToDelete.value = null;
};

const confirmDelete = async () => {
    if (!customerToDelete.value) return;

    deleting.value = true;
    try {
        await axios.delete(`/api/customers/${customerToDelete.value.id}`);
        toast.success('Customer deleted successfully.');
        closeDeleteConfirm();
        loadCustomers(pagination.value?.current_page || 1);
    } catch (error) {
        console.error('Failed to delete customer:', error);
        toast.error('Failed to delete customer. Please try again.');
    } finally {
        deleting.value = false;
    }
};

const openAssignmentModal = (customer) => {
    customerToAssign.value = customer;
    showAssignmentModal.value = true;
};

const closeAssignmentModal = () => {
    showAssignmentModal.value = false;
    customerToAssign.value = null;
};

const handleAssignmentComplete = () => {
    loadCustomers(pagination.value?.current_page || 1);
};

watch(
    () => route.query.type,
    (type) => {
        if (type !== 'customer' && type !== 'prospect') {
            return;
        }
        const tab = type === 'customer' ? 'customer' : 'prospect';
        activeTab.value = tab;
        loadCustomers(1);
    },
);

onMounted(() => {
    loadUsers();
    activeTab.value = route.query.type === 'customer' ? 'customer' : 'prospect';
    loadCustomers();
});
</script>
