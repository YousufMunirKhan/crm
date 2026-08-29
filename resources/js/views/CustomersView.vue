<template>
    <div class="w-full min-w-0">
    <ListingPageShell
        :title="customersPageTitle"
        :subtitle="customersPageSubtitle"
        :badge="customersBadge"
    >
        <template #actions>
            <div class="flex flex-wrap gap-2 w-full sm:w-auto justify-stretch sm:justify-end">
                <BaseButton
                    variant="outline"
                    class="flex-1 min-w-[8rem] sm:flex-initial"
                    :loading="exporting"
                    @click="exportToCSV"
                >
                    <template #icon>
                        <ArrowDownTrayIcon class="icon-sm" aria-hidden="true" />
                    </template>
                    {{ exporting ? 'Exporting...' : 'Export CSV' }}
                </BaseButton>
                <BaseButton
                    variant="outline"
                    class="flex-1 min-w-[8rem] sm:flex-initial"
                    @click="showImportModal = true"
                >
                    <template #icon>
                        <ArrowUpTrayIcon class="icon-sm" aria-hidden="true" />
                    </template>
                    Import
                </BaseButton>
                <BaseButton
                    variant="primary"
                    class="flex-1 min-w-[8rem] sm:flex-initial"
                    :to="{ path: '/customers/add', query: { type: activeTab } }"
                >
                    <template #icon>
                        <PlusIcon class="icon-sm" aria-hidden="true" />
                    </template>
                    {{ activeTab === 'prospect' ? 'Add prospect' : 'Add customer' }}
                </BaseButton>
            </div>
        </template>

        <template #filters>
            <div class="space-y-4 min-w-0">
                <p class="text-sm">
                    <router-link
                        v-if="activeTab === 'prospect'"
                        :to="{ path: '/customers', query: { type: 'customer' } }"
                        class="link"
                    >
                        Go to Customers →
                    </router-link>
                    <router-link
                        v-else
                        :to="{ path: '/customers', query: { type: 'prospect' } }"
                        class="link"
                    >
                        Go to Prospects →
                    </router-link>
                </p>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-4">
                <h2 class="text-sm font-semibold text-slate-700 flex items-center gap-2">
                    <FunnelIcon class="icon-sm text-slate-500" aria-hidden="true" />
                    Filters
                </h2>
                <BaseButton
                    variant="ghost"
                    size="sm"
                    class="self-start sm:self-auto"
                    :aria-expanded="showFilters ? 'true' : 'false'"
                    aria-controls="customersview-advanced-filters"
                    @click="showFilters = !showFilters"
                >
                    {{ showFilters ? 'Hide Filters' : 'Show Filters' }}
                </BaseButton>
            </div>

            <!-- Quick Search -->
            <div class="relative min-w-0 w-full">
                <label class="sr-only" for="customersview-search">Search customers</label>
                <MagnifyingGlassIcon
                    class="absolute left-3 top-1/2 -translate-y-1/2 icon text-slate-500 pointer-events-none"
                    aria-hidden="true"
                />
                <input id="customersview-search"
                    v-model="filters.search"
                    type="text"
                    placeholder="Search name, business, phone, email, city…"
                    class="form-input-search max-w-full box-border"
                    @input="handleSearch"
                />
            </div>

            <!-- Advanced Filters -->
            <div id="customersview-advanced-filters" v-show="showFilters" class="mt-4 pt-4 border-t border-slate-200">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="form-label" for="customersview-name">Name</label>
                        <input id="customersview-name"
                            v-model="filters.name"
                            type="text"
                            placeholder="Filter by name..."
                            class="form-input"
                            @input="handleFilterChange"
                        />
                    </div>
                    <div>
                        <label class="form-label" for="customersview-business-name">Business name</label>
                        <input id="customersview-business-name"
                            v-model="filters.business_name"
                            type="text"
                            placeholder="Filter by business name..."
                            class="form-input"
                            @input="handleFilterChange"
                        />
                    </div>
                    <div>
                        <label class="form-label" for="customersview-phone">Phone</label>
                        <input id="customersview-phone"
                            v-model="filters.phone"
                            type="text"
                            placeholder="Filter by phone..."
                            class="form-input"
                            @input="handleFilterChange"
                        />
                    </div>
                    <div>
                        <label class="form-label" for="customersview-email">Email</label>
                        <input id="customersview-email"
                            v-model="filters.email"
                            type="text"
                            placeholder="Filter by email..."
                            class="form-input"
                            @input="handleFilterChange"
                        />
                    </div>
                    <div>
                        <label class="form-label" for="customersview-city">City</label>
                        <input id="customersview-city"
                            v-model="filters.city"
                            type="text"
                            placeholder="Filter by city..."
                            class="form-input"
                            @input="handleFilterChange"
                        />
                    </div>
                    <div>
                        <label class="form-label" for="customersview-postcode">Postcode</label>
                        <input id="customersview-postcode"
                            v-model="filters.postcode"
                            type="text"
                            placeholder="Filter by postcode..."
                            class="form-input"
                            @input="handleFilterChange"
                        />
                    </div>
                    <div>
                        <label class="form-label" for="customersview-assigned-to">Assigned To</label>
                        <select id="customersview-assigned-to"
                            v-model="filters.assigned_to"
                            class="form-select"
                            @change="applyFilters"
                        >
                            <option value="">All Users</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="customersview-sort-by">Sort By</label>
                        <select id="customersview-sort-by"
                            v-model="filters.sort_by"
                            class="form-select"
                            @change="applyFilters"
                        >
                            <option value="created_at">Date Created</option>
                            <option value="name">Name</option>
                            <option value="city">City</option>
                            <option value="postcode">Postcode</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="customersview-order">Order</label>
                        <select id="customersview-order"
                            v-model="filters.sort_order"
                            class="form-select"
                            @change="applyFilters"
                        >
                            <option value="desc">Newest First</option>
                            <option value="asc">Oldest First</option>
                        </select>
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2 mt-4">
                    <BaseButton variant="outline" @click="clearFilters">
                        Clear Filters
                    </BaseButton>
                    <BaseButton variant="soft" @click="applyFilters">
                        Apply Filters
                    </BaseButton>
                </div>
            </div>

            <!-- Active Filters Display -->
            <div v-if="hasActiveFilters" class="mt-3 flex flex-wrap gap-2">
                <span
                    v-for="(value, key) in activeFilterTags"
                    :key="key"
                    class="chip"
                >
                    {{ key }}: {{ value }}
                    <button
                        type="button"
                        class="chip-remove"
                        :aria-label="`Remove ${key} filter`"
                        @click="removeFilter(key)"
                    >
                        <XMarkIcon class="w-3 h-3 shrink-0" aria-hidden="true" />
                    </button>
                </span>
            </div>
            </div>
        </template>

        <!-- Loading State -->
        <div
            v-if="loading"
            class="px-5 sm:px-6 py-8 space-y-3"
            role="status"
            aria-live="polite"
            aria-busy="true"
        >
            <span class="sr-only">Loading customers…</span>
            <div
                v-for="n in 6"
                :key="`customer-skeleton-${n}`"
                class="skeleton-text"
                :class="n % 3 === 0 ? 'w-1/2' : 'w-full'"
            />
        </div>

        <!-- Empty State -->
        <EmptyState
            v-else-if="customers.length === 0"
            :heading="`No ${activeTab === 'prospect' ? 'prospects' : 'customers'} found`"
            :description="hasActiveFilters ? 'Try adjusting your filters or search terms.' : (activeTab === 'prospect' ? 'Get started by adding your first prospect.' : 'Get started by adding your first customer.')"
        >
            <template #icon>
                <UsersIcon class="icon" aria-hidden="true" />
            </template>
            <template #action>
                <BaseButton
                    v-if="!hasActiveFilters"
                    variant="soft"
                    :to="{ path: '/customers/add', query: { type: activeTab } }"
                >
                    <template #icon>
                        <PlusIcon class="icon-sm" aria-hidden="true" />
                    </template>
                    Add Your First {{ activeTab === 'prospect' ? 'Prospect' : 'Customer' }}
                </BaseButton>
                <BaseButton v-else variant="outline" @click="clearFilters">
                    Clear Filters
                </BaseButton>
            </template>
        </EmptyState>

        <!-- Customers Table (lg+); card list below lg — avoids broken table-fixed on narrow widths -->
        <div v-else class="min-w-0">
            <div class="hidden lg:block table-wrap">
                <table class="table min-w-[860px] table-auto">
                    <caption class="sr-only">
                        {{ activeTab === 'prospect' ? 'Prospects' : 'Customers' }} — contact details, location, assignment and actions
                    </caption>
                    <thead class="table-thead border-b border-slate-200">
                        <tr>
                            <th scope="col" class="table-th">Customer</th>
                            <th scope="col" class="hidden xl:table-cell table-th">Business name</th>
                            <th scope="col" class="table-th">Contact</th>
                            <th scope="col" class="hidden lg:table-cell table-th">Location</th>
                            <th scope="col" class="hidden lg:table-cell table-th">Created By</th>
                            <th scope="col" class="table-th">Assigned</th>
                            <th scope="col" class="table-th">
                                {{ activeTab === 'prospect' ? 'Leads' : 'Products Won' }}
                            </th>
                            <th scope="col" class="table-th text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="customer in customers"
                            :key="customer.id"
                            class="table-row"
                        >
                            <td class="table-td min-w-0 max-w-[14rem]">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="w-10 h-10 shrink-0 rounded-full bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center text-white font-semibold text-sm" aria-hidden="true">
                                        {{ getInitials(customer.name) }}
                                    </div>
                                    <div class="min-w-0">
                                        <router-link
                                            :to="`/customers/${customer.id}`"
                                            class="font-medium text-slate-900 hover:text-primary-700 block truncate rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                        >
                                            {{ customer.name }}
                                        </router-link>
                                        <div v-if="customer.vat_number" class="text-xs text-slate-500 truncate">
                                            VAT: {{ customer.vat_number }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden xl:table-cell table-td min-w-0 max-w-[10rem]">
                                <span v-if="customer.business_name" class="text-slate-900 break-words">{{ customer.business_name }}</span>
                                <span v-else class="text-slate-500">—</span>
                            </td>
                            <td class="table-td min-w-0 max-w-[12rem]">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 text-slate-900 min-w-0">
                                        <PhoneIcon class="icon-sm text-slate-500" aria-hidden="true" />
                                        <span class="truncate">{{ customer.phone || '-' }}</span>
                                    </div>
                                    <div v-if="customer.email" class="flex items-center gap-2 text-slate-600 min-w-0">
                                        <EnvelopeIcon class="icon-sm text-slate-500" aria-hidden="true" />
                                        <span class="truncate">{{ customer.email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden lg:table-cell table-td min-w-0">
                                <div v-if="customer.city || customer.postcode" class="space-y-1">
                                    <div v-if="customer.city" class="text-slate-900 break-words">{{ customer.city }}</div>
                                    <div v-if="customer.postcode" class="text-xs text-slate-500 font-mono">{{ customer.postcode }}</div>
                                </div>
                                <span v-else class="text-slate-500">-</span>
                            </td>
                            <td class="hidden lg:table-cell table-td">
                                <BaseBadge v-if="customer.creator" tone="success">{{ customer.creator.name }}</BaseBadge>
                                <span v-else class="text-slate-500 text-xs">-</span>
                            </td>
                            <td class="table-td min-w-0 max-w-[10rem]">
                                <div v-if="customer.assigned_users && customer.assigned_users.length > 0" class="flex flex-wrap gap-1">
                                    <BaseBadge
                                        v-for="user in customer.assigned_users.slice(0, 2)"
                                        :key="user.id"
                                        tone="primary"
                                    >
                                        {{ user.name }}
                                    </BaseBadge>
                                    <BaseBadge v-if="customer.assigned_users.length > 2" tone="neutral">
                                        +{{ customer.assigned_users.length - 2 }}
                                    </BaseBadge>
                                </div>
                                <span v-else class="text-slate-500 text-xs">Unassigned</span>
                            </td>
                            <td class="table-td whitespace-nowrap">
                                <BaseBadge tone="success">
                                    {{ activeTab === 'prospect' ? (customer.leads?.length || 0) : (customer.won_products_count || 0) }}
                                </BaseBadge>
                            </td>
                            <td class="table-td-actions">
                                <div class="flex flex-wrap justify-end gap-1 sm:gap-2">
                                    <BaseButton
                                        size="icon"
                                        variant="ghost"
                                        :to="`/customers/${customer.id}`"
                                        :label="`View ${customer.name}`"
                                    >
                                        <EyeIcon class="icon-sm" aria-hidden="true" />
                                    </BaseButton>
                                    <BaseButton
                                        size="icon"
                                        variant="ghost"
                                        :label="`Assign ${customer.name}`"
                                        @click="openAssignmentModal(customer)"
                                    >
                                        <UserPlusIcon class="icon-sm" aria-hidden="true" />
                                    </BaseButton>
                                    <BaseButton
                                        size="icon"
                                        variant="ghost"
                                        :to="`/customers/${customer.id}/edit`"
                                        :label="`Edit ${customer.name}`"
                                    >
                                        <PencilSquareIcon class="icon-sm" aria-hidden="true" />
                                    </BaseButton>
                                    <BaseButton
                                        size="icon"
                                        variant="ghost"
                                        class="text-danger-700 hover:text-danger-800 hover:bg-danger-50"
                                        :label="`Delete ${customer.name}`"
                                        @click="openDeleteConfirm(customer)"
                                    >
                                        <TrashIcon class="icon-sm" aria-hidden="true" />
                                    </BaseButton>
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
                    class="table-card space-y-3 min-w-0"
                >
                    <div class="flex items-start justify-between gap-3 min-w-0">
                        <router-link
                            :to="`/customers/${customer.id}`"
                            class="font-semibold text-slate-900 hover:text-primary-700 break-words min-w-0 flex-1 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                        >
                            {{ customer.name }}
                        </router-link>
                        <div class="shrink-0 inline-flex flex-col items-end gap-1">
                            <span class="text-eyebrow text-slate-500 uppercase">
                                {{ activeTab === 'prospect' ? 'Leads' : 'Won' }}
                            </span>
                            <BaseBadge tone="success">
                                {{ activeTab === 'prospect' ? (customer.leads?.length || 0) : (customer.won_products_count || 0) }}
                            </BaseBadge>
                        </div>
                    </div>
                    <div v-if="customer.business_name" class="text-sm text-slate-600 break-words">
                        <span class="text-slate-500">Business name:</span> {{ customer.business_name }}
                    </div>
                    <div class="text-sm text-slate-600 space-y-1">
                        <div class="break-words"><span class="text-slate-500">Phone:</span> {{ customer.phone || '—' }}</div>
                        <div v-if="customer.email" class="break-all"><span class="text-slate-500">Email:</span> {{ customer.email }}</div>
                    </div>
                    <div class="text-sm text-slate-600 break-words">
                        <span class="text-slate-500">Location:</span>
                        {{ customer.city || '—' }}<span v-if="customer.postcode"> · {{ customer.postcode }}</span>
                    </div>
                    <div v-if="customer.creator" class="text-xs">
                        <span class="text-slate-500">Created by </span>
                        <BaseBadge tone="success">{{ customer.creator.name }}</BaseBadge>
                    </div>
                    <div class="text-sm text-slate-700">
                        <span class="text-slate-500 text-xs uppercase tracking-wide font-semibold">Assigned</span>
                        <div class="mt-1 flex flex-wrap gap-1">
                            <template v-if="customer.assigned_users && customer.assigned_users.length">
                                <BaseBadge
                                    v-for="user in customer.assigned_users"
                                    :key="user.id"
                                    tone="primary"
                                >
                                    {{ user.name }}
                                </BaseBadge>
                            </template>
                            <span v-else class="text-slate-500 text-xs">Unassigned</span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 pt-2 border-t border-slate-200">
                        <BaseButton size="sm" variant="outline" :to="`/customers/${customer.id}`">View</BaseButton>
                        <BaseButton size="sm" variant="outline" @click="openAssignmentModal(customer)">Assign</BaseButton>
                        <BaseButton size="sm" variant="outline" :to="`/customers/${customer.id}/edit`">Edit</BaseButton>
                        <BaseButton size="sm" variant="danger" @click="openDeleteConfirm(customer)">Delete</BaseButton>
                    </div>
                </div>
            </div>
        </div>

        <template #pagination>
            <div v-if="pagination && customers.length && !loading" class="border-t border-slate-100">
                <div class="px-5 sm:px-6 py-3 flex flex-wrap justify-end items-center gap-3 bg-slate-50/50">
                    <label class="text-xs font-medium text-slate-600" for="customersview-per-page">Rows per page</label>
                    <select id="customersview-per-page" v-model="perPage" class="form-select w-auto min-w-[9rem]" @change="changePerPage">
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
import {
    ArrowDownTrayIcon,
    ArrowUpTrayIcon,
    EnvelopeIcon,
    EyeIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    PencilSquareIcon,
    PhoneIcon,
    PlusIcon,
    TrashIcon,
    UserPlusIcon,
    UsersIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline';
import { BaseBadge, BaseButton, EmptyState } from '@/components/base';
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
