<template>
    <div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        <!-- Header: one list per URL (?type=prospect | ?type=customer), no tab switcher -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900">{{ activeTab === 'prospect' ? 'Prospects' : 'Customers' }}</h1>
                <p class="text-sm text-slate-500 mt-1">
                    {{ pagination?.total || 0 }} {{ activeTab === 'prospect' ? 'prospects' : 'customers' }}
                </p>
                <p class="mt-2">
                    <router-link
                        v-if="activeTab === 'prospect'"
                        :to="{ path: '/customers', query: { type: 'customer' } }"
                        class="text-sm text-blue-600 hover:text-blue-800 hover:underline"
                    >
                        Go to Customers
                    </router-link>
                    <router-link
                        v-else
                        :to="{ path: '/customers', query: { type: 'prospect' } }"
                        class="text-sm text-blue-600 hover:text-blue-800 hover:underline"
                    >
                        Go to Prospects
                    </router-link>
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    @click="exportToCSV"
                    :disabled="exporting"
                    class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700 text-sm disabled:opacity-50"
                >
                    {{ exporting ? 'Exporting...' : 'Export CSV' }}
                </button>
                <button
                    @click="showImportModal = true"
                    class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700 text-sm"
                >
                    Import
                </button>
                <router-link
                    :to="{ path: '/customers/add', query: { type: activeTab } }"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 text-sm"
                >
                    + {{ activeTab === 'prospect' ? 'Add Prospect' : 'Add Customer' }}
                </router-link>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="bg-white rounded-xl shadow-sm p-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                    </svg>
                    Filters
                </h3>
                <button
                    @click="showFilters = !showFilters"
                    class="text-sm text-blue-600 hover:text-blue-800"
                >
                    {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
                </button>
            </div>

            <!-- Quick Search -->
            <div class="relative">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <input
                    v-model="filters.search"
                    type="text"
                    placeholder="Quick search by name, phone, email, city, or postcode..."
                    class="w-full pl-10 pr-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
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

        <!-- Loading State -->
        <div v-if="loading" class="bg-white rounded-xl shadow-sm p-12 text-center">
            <div class="inline-flex items-center gap-2 text-slate-500">
                <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Loading customers...
            </div>
        </div>

        <!-- Empty State -->
        <div v-else-if="customers.length === 0" class="bg-white rounded-xl shadow-sm p-12 text-center">
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
                class="inline-block px-6 py-3 bg-slate-900 text-white rounded-lg hover:bg-slate-800"
            >
                + Add Your First {{ activeTab === 'prospect' ? 'Prospect' : 'Customer' }}
            </router-link>
            <button
                v-else
                @click="clearFilters"
                class="px-6 py-3 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50"
            >
                Clear Filters
            </button>
        </div>

        <!-- Customers Table -->
        <div v-else class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px]">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Location</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Created By</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Assigned</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wider">Stats</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr 
                            v-for="customer in customers" 
                            :key="customer.id" 
                            class="hover:bg-slate-50 transition-colors"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-semibold text-sm">
                                        {{ getInitials(customer.name) }}
                                    </div>
                                    <div>
                                        <router-link
                                            :to="`/customers/${customer.id}`"
                                            class="font-medium text-slate-900 hover:text-blue-600"
                                        >
                                            {{ customer.name }}
                                        </router-link>
                                        <div v-if="customer.vat_number" class="text-xs text-slate-500">
                                            VAT: {{ customer.vat_number }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 text-sm text-slate-900">
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        {{ customer.phone || '-' }}
                                    </div>
                                    <div v-if="customer.email" class="flex items-center gap-2 text-sm text-slate-600">
                                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                        </svg>
                                        {{ customer.email }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div v-if="customer.city || customer.postcode" class="space-y-1">
                                    <div v-if="customer.city" class="text-sm text-slate-900">{{ customer.city }}</div>
                                    <div v-if="customer.postcode" class="text-xs text-slate-500 font-mono">{{ customer.postcode }}</div>
                                </div>
                                <span v-else class="text-slate-400 text-sm">-</span>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="customer.creator" class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-medium">
                                    {{ customer.creator.name }}
                                </span>
                                <span v-else class="text-slate-400 text-xs">-</span>
                            </td>
                            <td class="px-6 py-4">
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
                            <td class="px-6 py-4">
                                <div class="flex gap-3 text-xs">
                                    <div class="text-center">
                                        <div class="font-semibold text-slate-900">{{ customer.leads?.length || 0 }}</div>
                                        <div class="text-slate-500">Leads</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="font-semibold text-slate-900">{{ customer.invoices?.length || 0 }}</div>
                                        <div class="text-slate-500">Invoices</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="font-semibold text-slate-900">{{ customer.tickets?.length || 0 }}</div>
                                        <div class="text-slate-500">Tickets</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex justify-end gap-2">
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

            <!-- Pagination -->
            <div v-if="pagination && pagination.last_page > 1" class="px-6 py-4 border-t border-slate-200 bg-slate-50">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <div class="text-sm text-slate-600">
                        Showing {{ ((pagination.current_page - 1) * pagination.per_page) + 1 }} 
                        to {{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }} 
                        of {{ pagination.total }} customers
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="loadCustomers(1)"
                            :disabled="pagination.current_page === 1"
                            class="p-2 border border-slate-300 rounded-lg hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                            </svg>
                        </button>
                        <button
                            @click="loadCustomers(pagination.current_page - 1)"
                            :disabled="pagination.current_page === 1"
                            class="p-2 border border-slate-300 rounded-lg hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                            </svg>
                        </button>
                        
                        <div class="flex items-center gap-1">
                            <template v-for="page in visiblePages" :key="page">
                                <button
                                    v-if="page !== '...'"
                                    @click="loadCustomers(page)"
                                    :class="[
                                        'w-10 h-10 rounded-lg text-sm font-medium transition-colors',
                                        page === pagination.current_page
                                            ? 'bg-slate-900 text-white'
                                            : 'border border-slate-300 hover:bg-white text-slate-700'
                                    ]"
                                >
                                    {{ page }}
                                </button>
                                <span v-else class="px-2 text-slate-400">...</span>
                            </template>
                        </div>

                        <button
                            @click="loadCustomers(pagination.current_page + 1)"
                            :disabled="pagination.current_page === pagination.last_page"
                            class="p-2 border border-slate-300 rounded-lg hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <button
                            @click="loadCustomers(pagination.last_page)"
                            :disabled="pagination.current_page === pagination.last_page"
                            class="p-2 border border-slate-300 rounded-lg hover:bg-white disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7" />
                            </svg>
                        </button>

                        <select
                            v-model="perPage"
                            @change="changePerPage"
                            class="ml-4 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option :value="15">15 per page</option>
                            <option :value="25">25 per page</option>
                            <option :value="50">50 per page</option>
                            <option :value="100">100 per page</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <DeleteConfirm
            v-if="showDeleteConfirm"
            title="Delete Customer"
            :message="`Are you sure you want to delete ${customerToDelete?.name}? This will also delete all associated leads, tickets, and invoices.`"
            :loading="deleting"
            @confirm="confirmDelete"
            @cancel="closeDeleteConfirm"
        />

        <!-- Import Modal -->
        <ImportModal
            v-if="showImportModal"
            @close="showImportModal = false"
            @imported="handleImportComplete"
        />

        <!-- Assignment Modal -->
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
           filters.value.phone || 
           filters.value.email || 
           filters.value.city || 
           filters.value.postcode || 
           filters.value.assigned_to;
});

const activeFilterTags = computed(() => {
    const tags = {};
    if (filters.value.name) tags['Name'] = filters.value.name;
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

const visiblePages = computed(() => {
    if (!pagination.value) return [];
    const current = pagination.value.current_page;
    const last = pagination.value.last_page;
    const pages = [];

    if (last <= 7) {
        for (let i = 1; i <= last; i++) pages.push(i);
    } else {
        pages.push(1);
        if (current > 3) pages.push('...');
        
        const start = Math.max(2, current - 1);
        const end = Math.min(last - 1, current + 1);
        
        for (let i = start; i <= end; i++) pages.push(i);
        
        if (current < last - 2) pages.push('...');
        pages.push(last);
    }
    
    return pages;
});

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
        if (filters.value.phone) params.phone = filters.value.phone;
        if (filters.value.email) params.email = filters.value.email;
        if (filters.value.city) params.city = filters.value.city;
        if (filters.value.postcode) params.postcode = filters.value.postcode;
        
        const { data } = await axios.get('/api/customers', { params });
        const allCustomers = data.data || [];
        
        const columns = [
            { key: 'name', label: 'Name' },
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
