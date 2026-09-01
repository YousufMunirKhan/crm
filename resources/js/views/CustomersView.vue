<template>
    <div class="w-full min-w-0">
    <!--
        No subtitle: the Prospects/Customers switch in the filters slot now
        says what this list is far faster than a sentence that wrapped to four
        lines beside the buttons and pushed the first row off the fold.
    -->
    <ListingPageShell
        :title="customersPageTitle"
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
            <div class="space-y-3 min-w-0">
                <!-- Which list am I looking at, and one click to the other. -->
                <div class="tab-list" role="tablist" aria-label="Contact type">
                    <router-link
                        role="tab"
                        :aria-selected="activeTab === 'prospect' ? 'true' : 'false'"
                        :to="{ path: '/customers', query: { type: 'prospect' } }"
                        class="tab"
                        :class="activeTab === 'prospect' ? 'tab-active' : ''"
                    >
                        Prospects
                    </router-link>
                    <router-link
                        role="tab"
                        :aria-selected="activeTab === 'customer' ? 'true' : 'false'"
                        :to="{ path: '/customers', query: { type: 'customer' } }"
                        class="tab"
                        :class="activeTab === 'customer' ? 'tab-active' : ''"
                    >
                        Customers
                    </router-link>
                </div>

                <!-- Search + sort on one row; everything else behind "More filters". -->
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center min-w-0">
                    <div class="relative min-w-0 flex-1">
                        <label class="sr-only" for="customersview-search">Search {{ activeTab === 'prospect' ? 'prospects' : 'customers' }}</label>
                        <MagnifyingGlassIcon
                            class="absolute left-3 top-1/2 -translate-y-1/2 icon text-slate-500 pointer-events-none"
                            aria-hidden="true"
                        />
                        <input id="customersview-search"
                            v-model="filters.search"
                            type="text"
                            placeholder="Name, company, owner, phone, email, VAT, postcode…"
                            class="form-input-search max-w-full box-border"
                            @input="handleSearch"
                        />
                    </div>
                    <label class="sr-only" for="customersview-sort">Sort</label>
                    <select
                        id="customersview-sort"
                        v-model="sortChoice"
                        class="form-select w-full sm:w-auto sm:min-w-[11rem] shrink-0"
                    >
                        <option value="created_at:desc">Newest first</option>
                        <option value="created_at:asc">Oldest first</option>
                        <option value="name:asc">Name A–Z</option>
                        <option value="name:desc">Name Z–A</option>
                        <option value="city:asc">City A–Z</option>
                        <option value="postcode:asc">Postcode A–Z</option>
                    </select>
                    <BaseButton
                        variant="ghost"
                        class="shrink-0"
                        :aria-expanded="showFilters ? 'true' : 'false'"
                        aria-controls="customersview-advanced-filters"
                        @click="showFilters = !showFilters"
                    >
                        <template #icon>
                            <FunnelIcon class="icon-sm" aria-hidden="true" />
                        </template>
                        More filters
                    </BaseButton>
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
                <table class="table min-w-[1000px] table-auto">
                    <caption class="sr-only">
                        {{ activeTab === 'prospect' ? 'Prospects' : 'Customers' }} — contact details, pipeline stage, next follow-up and actions
                    </caption>
                    <thead class="table-thead border-b border-slate-200">
                        <tr>
                            <th scope="col" class="table-th">Customer / Company</th>
                            <th scope="col" class="table-th">Contact</th>
                            <th scope="col" class="table-th">Stage</th>
                            <th scope="col" class="table-th">Next follow-up</th>
                            <th v-if="anyValue" scope="col" class="hidden 2xl:table-cell table-th-num">Value (£)</th>
                            <th scope="col" class="hidden xl:table-cell table-th">Assigned</th>
                            <th scope="col" class="hidden 2xl:table-cell table-th">Added by</th>
                            <th scope="col" class="table-th text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="customer in customers"
                            :key="customer.id"
                            class="table-row"
                        >
                            <td class="table-td min-w-0 w-[22rem] max-w-[22rem]">
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
                                        <div
                                            v-if="customer.business_name"
                                            class="text-xs text-slate-600 break-words line-clamp-2"
                                            :title="customer.business_name"
                                        >
                                            {{ customer.business_name }}
                                        </div>
                                        <div v-else-if="customer.vat_number" class="text-xs text-slate-500 truncate">
                                            VAT: {{ customer.vat_number }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="table-td min-w-0 max-w-[13rem]">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2 text-slate-900 min-w-0">
                                        <PhoneIcon class="icon-sm text-slate-500" aria-hidden="true" />
                                        <a
                                            v-if="customer.phone"
                                            :href="`tel:${customer.phone}`"
                                            class="truncate hover:text-primary-700 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                        >{{ customer.phone }}</a>
                                        <span v-else class="text-slate-400">No phone</span>
                                        <CopyButton v-if="customer.phone" :value="customer.phone" label="phone number" size="compact" />
                                    </div>
                                    <div v-if="customer.email" class="flex items-center gap-2 text-slate-600 min-w-0">
                                        <EnvelopeIcon class="icon-sm text-slate-500" aria-hidden="true" />
                                        <span class="truncate">{{ customer.email }}</span>
                                        <CopyButton :value="customer.email" label="email address" size="compact" />
                                    </div>
                                    <div v-if="locationOf(customer)" class="flex items-center gap-2 text-xs text-slate-500 min-w-0">
                                        <MapPinIcon class="icon-sm text-slate-400" aria-hidden="true" />
                                        <span class="truncate">{{ locationOf(customer) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="table-td whitespace-nowrap">
                                <BaseBadge v-if="stageOf(customer)" :tone="leadStageTone(stageOf(customer))">
                                    {{ formatLeadStage(stageOf(customer)) }}
                                </BaseBadge>
                                <span v-else class="text-slate-400 text-xs">No lead yet</span>
                            </td>
                            <td class="table-td whitespace-nowrap">
                                <span
                                    v-if="followUpOf(customer)"
                                    :class="followUpClass(followUpOf(customer).tone)"
                                >
                                    {{ followUpOf(customer).label }}
                                </span>
                                <span v-else class="text-slate-400 text-xs">Not scheduled</span>
                            </td>
                            <td v-if="anyValue" class="hidden 2xl:table-cell table-td-num">
                                <span v-if="valueOf(customer) > 0" class="font-semibold text-slate-800">
                                    {{ formatNumber(valueOf(customer)) }}
                                </span>
                                <span v-else class="text-slate-300">—</span>
                            </td>
                            <td class="hidden xl:table-cell table-td min-w-0 max-w-[10rem]">
                                <div v-if="customer.assigned_users && customer.assigned_users.length > 0" class="flex flex-wrap gap-1">
                                    <span
                                        v-for="user in customer.assigned_users.slice(0, 2)"
                                        :key="user.id"
                                        class="text-slate-700 truncate"
                                    >
                                        {{ user.name }}
                                    </span>
                                    <span v-if="customer.assigned_users.length > 2" class="text-xs text-slate-500">
                                        +{{ customer.assigned_users.length - 2 }}
                                    </span>
                                </div>
                                <span v-else class="text-slate-400 text-xs">Unassigned</span>
                            </td>
                            <td class="hidden 2xl:table-cell table-td text-xs text-slate-500">
                                {{ customer.creator?.name || '—' }}
                            </td>
                            <td class="table-td-actions">
                                <div class="table-actions">
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
                    variant="ghost-danger"
                    
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
                    <!-- Identity + stage: the two things that decide whether to read on. -->
                    <div class="flex items-start justify-between gap-3 min-w-0">
                        <div class="min-w-0 flex-1">
                            <router-link
                                :to="`/customers/${customer.id}`"
                                class="font-semibold text-slate-900 hover:text-primary-700 break-words rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                            >
                                {{ customer.name }}
                            </router-link>
                            <div v-if="customer.business_name" class="text-xs text-slate-500 break-words mt-0.5">
                                {{ customer.business_name }}
                            </div>
                        </div>
                        <BaseBadge v-if="stageOf(customer)" :tone="leadStageTone(stageOf(customer))" class="shrink-0">
                            {{ formatLeadStage(stageOf(customer)) }}
                        </BaseBadge>
                    </div>

                    <!-- The triage line: when to call, and what it is worth. -->
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                        <span class="inline-flex items-center gap-1.5">
                            <CalendarDaysIcon class="icon-sm text-slate-400" aria-hidden="true" />
                            <span v-if="followUpOf(customer)" :class="followUpClass(followUpOf(customer).tone)">
                                {{ followUpOf(customer).label }}
                            </span>
                            <span v-else class="text-slate-400">Not scheduled</span>
                        </span>
                        <span v-if="valueOf(customer) > 0" class="font-semibold text-slate-800 tabular-nums">
                            £{{ formatNumber(valueOf(customer)) }}
                        </span>
                    </div>

                    <div class="text-sm text-slate-600 space-y-1">
                        <div class="flex items-center gap-1 min-w-0">
                            <a
                                v-if="customer.phone"
                                :href="`tel:${customer.phone}`"
                                class="min-w-0 break-words hover:text-primary-700"
                            >{{ customer.phone }}</a>
                            <span v-else class="text-slate-400">No phone</span>
                            <CopyButton v-if="customer.phone" :value="customer.phone" label="phone number" />
                        </div>
                        <div v-if="customer.email" class="flex items-center gap-1 min-w-0">
                            <span class="min-w-0 break-all text-slate-500">{{ customer.email }}</span>
                            <CopyButton :value="customer.email" label="email address" />
                        </div>
                        <div v-if="locationOf(customer)" class="text-xs text-slate-500 break-words">
                            {{ locationOf(customer) }}
                        </div>
                    </div>

                    <div class="text-xs text-slate-500">
                        <template v-if="customer.assigned_users && customer.assigned_users.length">
                            Assigned to {{ customer.assigned_users.map((u) => u.name).join(', ') }}
                        </template>
                        <template v-else>Unassigned</template>
                    </div>

                    <!--
                        One clear action (View). Edit/Assign are quiet, and Delete
                        is an icon rather than a solid red block repeated down the
                        whole list.
                    -->
                    <div class="flex items-center gap-2 pt-2 border-t border-slate-200">
                        <BaseButton size="sm" variant="soft" :to="`/customers/${customer.id}`">View</BaseButton>
                        <BaseButton size="sm" variant="ghost" @click="openAssignmentModal(customer)">Assign</BaseButton>
                        <BaseButton size="sm" variant="ghost" :to="`/customers/${customer.id}/edit`">Edit</BaseButton>
                        <BaseButton
                            size="icon"
                            variant="ghost-danger"
                            class="ml-auto"
                            :label="`Delete ${customer.name}`"
                            @click="openDeleteConfirm(customer)"
                        >
                            <TrashIcon class="icon-sm" aria-hidden="true" />
                        </BaseButton>
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
    CalendarDaysIcon,
    EnvelopeIcon,
    EyeIcon,
    FunnelIcon,
    MagnifyingGlassIcon,
    MapPinIcon,
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
import { formatLeadStage, leadStageTone } from '@/utils/displayFormat';
import { describeDueDate } from '@/utils/dateFormatUi';
import { useToastStore } from '@/stores/toast';
import CopyButton from '@/components/CopyButton.vue';

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

/** Only worth a column when something on the page actually has one. */
const anyValue = computed(() => customers.value.some((c) => valueOf(c) > 0));

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

const customersBadge = computed(() =>
    pagination.value?.total != null ? `${pagination.value.total} Total` : null,
);

const getInitials = (name) => {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
};

/**
 * One control instead of two. The API still receives sort_by + sort_order
 * exactly as before; this only pairs them for the person choosing.
 */
const sortChoice = computed({
    get: () => `${filters.value.sort_by || 'created_at'}:${filters.value.sort_order || 'desc'}`,
    set: (value) => {
        const [by, order] = String(value).split(':');
        filters.value.sort_by = by;
        filters.value.sort_order = order;
        applyFilters();
    },
});

/* --------------------------------------------------------------------------
 * Row summaries.
 *
 * The listing used to answer "who created this record". These answer "who
 * should I call, and when". Everything here is derived from the `leads`
 * relation the index endpoint already eager-loads - no extra requests.
 * ----------------------------------------------------------------------- */

const CLOSED_STAGES = ['won', 'lost'];

/** Leads still worth acting on, newest first. */
function openLeads(customer) {
    return (customer?.leads || []).filter((l) => !CLOSED_STAGES.includes(l.stage));
}

/**
 * The lead that best represents this contact right now: the most advanced
 * open lead, or - when everything is closed - the most recent outcome.
 */
function primaryLead(customer) {
    const leads = customer?.leads || [];
    if (!leads.length) return null;

    const open = openLeads(customer);
    if (open.length) {
        // Furthest along the pipeline is the one that matters most.
        const rank = { quotation: 4, hot_lead: 3, lead: 2, follow_up: 1 };
        return [...open].sort((a, b) => (rank[b.stage] || 0) - (rank[a.stage] || 0))[0];
    }
    return [...leads].sort(
        (a, b) => new Date(b.updated_at || 0) - new Date(a.updated_at || 0),
    )[0];
}

function stageOf(customer) {
    return primaryLead(customer)?.stage || null;
}

/** Soonest scheduled follow-up across open leads - the actual to-do date. */
function followUpOf(customer) {
    const dates = openLeads(customer)
        .map((l) => l.next_follow_up_at)
        .filter(Boolean)
        .sort((a, b) => new Date(a) - new Date(b));
    return dates.length ? describeDueDate(dates[0]) : null;
}

function followUpClass(tone) {
    return {
        overdue: 'text-danger-700 font-semibold',
        today: 'text-warning-700 font-semibold',
        soon: 'text-slate-700',
        later: 'text-slate-500',
    }[tone] || 'text-slate-500';
}

/**
 * Won accounts are worth what they bought; open pipeline is worth what it
 * might close for. Mirrors getLeadValue() on the Leads list.
 */
function valueOf(customer) {
    return (customer?.leads || []).reduce((sum, lead) => {
        const v = parseFloat(lead.total_value ?? lead.pipeline_value ?? 0);
        return sum + (Number.isFinite(v) ? v : 0);
    }, 0);
}

function locationOf(customer) {
    return [customer?.city, customer?.postcode].filter(Boolean).join(' · ');
}

function formatNumber(num) {
    return new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(num || 0);
}

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
