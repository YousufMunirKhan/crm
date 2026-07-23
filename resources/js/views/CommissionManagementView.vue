<template>
    <ListingPageShell title="Commission Workspace" :subtitle="subtitle" :badge="badgeText">
        <template #actions>
            <router-link to="/commission/report" class="listing-btn-outline w-full sm:w-auto text-center">
                Reports
            </router-link>
        </template>

        <template #filters>
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
                        <input v-model="filters.from" type="date" class="listing-input" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
                        <input v-model="filters.to" type="date" class="listing-input" />
                    </div>
                    <div>
                        <div class="mb-1">
                            <HelpLabel label="Status" tooltip="Use Needs allocation to find won sales that still need commission. Allocated shows sales already saved." />
                        </div>
                        <select v-model="filters.processed" class="listing-input">
                            <option value="all">All sales</option>
                            <option value="no">Needs allocation</option>
                            <option value="yes">Allocated</option>
                        </select>
                    </div>
                    <button type="button" class="listing-btn-primary w-full" :disabled="loading" @click="loadSales">
                        {{ loading ? 'Loading...' : 'Apply' }}
                    </button>
                    <button type="button" class="listing-btn-outline w-full" @click="applyDatePreset('thisMonth')">
                        This month
                    </button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="px-3 py-2 text-xs sm:text-sm rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50" @click="applyDatePreset('twoMonths')">
                        Last 2 months
                    </button>
                    <button type="button" class="px-3 py-2 text-xs sm:text-sm rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50" @click="applyDatePreset('lastMonth')">
                        Last month
                    </button>
                    <span class="inline-flex items-center text-xs text-slate-500">
                        Based on won product close date.
                    </span>
                </div>
            </div>
        </template>

        <div class="p-4 sm:p-6 space-y-6">
            <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
                <div v-for="card in overviewCards" :key="card.label" class="rounded-lg border border-slate-200 bg-white p-4">
                    <HelpLabel :label="card.label" :tooltip="card.tooltip" />
                    <p class="mt-2 text-2xl font-bold text-slate-950">{{ card.value }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ card.detail }}</p>
                </div>
            </section>

            <section class="rounded-lg border border-violet-200 bg-violet-50/70 p-4">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-950">How to add commission</h2>
                        <p class="mt-1 text-sm text-slate-700">
                            First filter the won sales, open a pending sale, choose the employee, enter the commission amount, select currency and role, then save.
                        </p>
                    </div>
                    <div class="grid grid-cols-1 gap-2 text-sm text-slate-700 sm:grid-cols-3 lg:w-[34rem]">
                        <div class="rounded-lg bg-white/80 px-3 py-2 ring-1 ring-violet-100">
                            <span class="font-semibold text-slate-900">1. Open sale</span>
                            <span class="block text-xs text-slate-500">Use Needs allocation.</span>
                        </div>
                        <div class="rounded-lg bg-white/80 px-3 py-2 ring-1 ring-violet-100">
                            <span class="font-semibold text-slate-900">2. Add split rows</span>
                            <span class="block text-xs text-slate-500">One row per person.</span>
                        </div>
                        <div class="rounded-lg bg-white/80 px-3 py-2 ring-1 ring-violet-100">
                            <span class="font-semibold text-slate-900">3. Save once</span>
                            <span class="block text-xs text-slate-500">Existing allocation is replaced.</span>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_22rem] gap-5">
                <div class="space-y-4 min-w-0">
                    <div class="rounded-lg border border-slate-200 bg-white overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-100 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <HelpLabel label="Sales Queue" tooltip="This list shows won product sales from the selected period. Pending sales need commission; allocated sales already have commission rows." text-class="font-semibold text-slate-900" />
                                <p class="text-sm text-slate-500">Allocate commission once for each won product sale.</p>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-xs sm:flex sm:flex-wrap">
                                <button
                                    v-for="tab in statusTabs"
                                    :key="tab.value"
                                    type="button"
                                    class="rounded-lg px-3 py-2 font-medium transition-colors"
                                    :class="filters.processed === tab.value ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                                    @click="setStatus(tab.value)"
                                >
                                    {{ tab.label }}
                                </button>
                            </div>
                        </div>

                        <div v-if="loading" class="p-6 text-sm text-slate-500">Loading commission sales...</div>
                        <div v-else-if="!sales.length" class="p-6 text-sm text-slate-500">No won sales found for this filter.</div>

                        <div v-else>
                            <div class="hidden lg:block overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-slate-50 text-slate-600">
                                        <tr>
                                            <th class="px-4 py-3 text-left">Customer</th>
                                            <th class="px-4 py-3 text-left">Product</th>
                                            <th class="px-4 py-3 text-left whitespace-nowrap">Sale date</th>
                                            <th class="px-4 py-3 text-left">Lead owner</th>
                                            <th class="px-4 py-3 text-left">Commission</th>
                                            <th class="px-4 py-3 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr v-for="row in sales" :key="saleKey(row)" class="hover:bg-slate-50/70">
                                            <td class="px-4 py-3">
                                                <p class="font-medium text-slate-900">{{ row.customer_name || '-' }}</p>
                                                <p class="text-xs text-slate-500">Lead #{{ row.lead_id }}</p>
                                            </td>
                                            <td class="px-4 py-3">{{ row.product_name || '-' }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ formatSaleDate(row.closed_at) }}</td>
                                            <td class="px-4 py-3">{{ row.lead_assigned_to_name || '-' }}</td>
                                            <td class="px-4 py-3">
                                                <span :class="statusPillClass(row)">
                                                    {{ row.commission_processed ? allocationLabel(row) : 'Needs allocation' }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                <button
                                                    type="button"
                                                    class="listing-btn-primary !py-1.5 !text-xs"
                                                    title="Open this won sale and enter the commission user, amount, currency, and role."
                                                    @click="openAllocation(row)"
                                                >
                                                    {{ row.commission_processed ? 'Review' : 'Allocate' }}
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="lg:hidden divide-y divide-slate-100">
                                <article v-for="row in sales" :key="saleKey(row)" class="p-4 space-y-3">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <p class="font-semibold text-slate-900 break-words">{{ row.customer_name || '-' }}</p>
                                            <p class="text-sm text-slate-600 break-words">{{ row.product_name || '-' }}</p>
                                        </div>
                                        <span :class="statusPillClass(row)">
                                            {{ row.commission_processed ? 'Allocated' : 'Pending' }}
                                        </span>
                                    </div>
                                    <div class="grid grid-cols-2 gap-2 text-sm">
                                        <div>
                                            <p class="text-xs text-slate-500">Sale date</p>
                                            <p class="font-medium text-slate-800">{{ formatSaleDate(row.closed_at) }}</p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-slate-500">Lead owner</p>
                                            <p class="font-medium text-slate-800">{{ row.lead_assigned_to_name || '-' }}</p>
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        class="listing-btn-primary w-full"
                                        title="Open this won sale and enter the commission details."
                                        @click="openAllocation(row)"
                                    >
                                        {{ row.commission_processed ? 'Review allocation' : 'Allocate commission' }}
                                    </button>
                                </article>
                            </div>
                        </div>
                    </div>
                </div>

                <aside class="space-y-4">
                    <section class="rounded-lg border border-slate-200 bg-white overflow-hidden">
                        <div class="px-4 py-3 border-b border-slate-100">
                            <HelpLabel label="Commission Users" tooltip="Only users marked Eligible can be selected inside the allocation modal." text-class="font-semibold text-slate-900" />
                            <p class="text-sm text-slate-500">Only eligible users can receive commission.</p>
                        </div>
                        <div class="p-4 grid grid-cols-2 gap-3">
                            <div class="rounded-lg bg-slate-50 p-3">
                                <p class="text-xs text-slate-500">Eligible</p>
                                <p class="text-xl font-bold text-slate-950">{{ eligibleUsers.length }}</p>
                            </div>
                            <div class="rounded-lg bg-slate-50 p-3">
                                <p class="text-xs text-slate-500">Disabled</p>
                                <p class="text-xl font-bold text-slate-950">{{ users.length - eligibleUsers.length }}</p>
                            </div>
                        </div>
                        <div class="max-h-80 overflow-y-auto divide-y divide-slate-100">
                            <div v-for="u in users" :key="u.id" class="px-4 py-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-slate-800 truncate">{{ u.name }}</p>
                                    <p class="text-xs text-slate-500 truncate">{{ u.role?.name || '-' }}</p>
                                </div>
                                <label class="inline-flex items-center gap-2 text-sm text-slate-700 shrink-0">
                                    <input
                                        :checked="!!u.commission_eligible"
                                        type="checkbox"
                                        class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        @change="toggleEligibility(u, $event)"
                                    />
                                    Eligible
                                </label>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-4">
                        <HelpLabel label="Allocation Rules" tooltip="These rules stop accidental duplicate commission entries while keeping the same live database structure." text-class="font-semibold text-slate-900" />
                        <div class="mt-3 space-y-3 text-sm text-slate-600">
                            <p>Saving an allocation replaces the existing allocation for that sale, so the same sale is not added twice.</p>
                            <p>A user cannot be repeated with the same role and currency inside one split.</p>
                        </div>
                    </section>
                </aside>
            </section>
        </div>
    </ListingPageShell>

    <div v-if="showAllocationModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-3 sm:p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[92vh] flex flex-col">
            <div class="px-4 sm:px-6 py-4 border-b border-slate-200 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="text-lg font-semibold text-slate-900">Allocate Commission</h3>
                    <p class="text-sm text-slate-600 mt-1 break-words">
                        {{ activeSale?.customer_name || '-' }} - {{ activeSale?.product_name || '-' }}
                    </p>
                </div>
                <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50" @click="closeAllocation">
                    Close
                </button>
            </div>

            <div class="p-4 sm:p-6 space-y-4 overflow-y-auto">
                <section class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Sale date</p>
                        <p class="font-semibold text-slate-900">{{ formatSaleDate(activeSale?.closed_at) }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Lead owner</p>
                        <p class="font-semibold text-slate-900">{{ activeSale?.lead_assigned_to_name || '-' }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3">
                        <p class="text-xs text-slate-500">Sale value</p>
                        <p class="font-semibold text-slate-900">{{ formatAmountNumber(activeSale?.total_price) }}</p>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-100 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <HelpLabel label="Split Rows" tooltip="Use one row for a single owner, or multiple rows when commission is split between people." text-class="font-semibold text-slate-900" />
                            <p class="text-sm text-slate-500">Add each person who should receive commission for this sale.</p>
                        </div>
                        <button
                            type="button"
                            class="listing-btn-outline w-full sm:w-auto"
                            title="Add another person to split commission for this same sale."
                            @click="addSplitRow"
                        >
                            Add row
                        </button>
                    </div>

                    <div class="divide-y divide-slate-100">
                        <div v-for="(entry, idx) in allocationRows" :key="entry.local_id" class="p-4 space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                                <div class="md:col-span-3">
                                    <HelpLabel label="User" tooltip="Select the employee who should receive this commission row." />
                                    <select v-model="entry.credited_user_id" class="listing-input mt-1">
                                        <option value="">Select user...</option>
                                        <option v-for="u in eligibleUsers" :key="u.id" :value="u.id">{{ u.name }}</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <HelpLabel label="Amount" tooltip="Enter the commission amount only, not the full sale value." />
                                    <input v-model.number="entry.commission_amount" type="number" step="0.01" min="0.01" class="listing-input mt-1" />
                                </div>
                                <div class="md:col-span-2">
                                    <HelpLabel label="Currency" tooltip="Choose the currency for this commission amount. GBP and PKR are tracked separately in reports." />
                                    <select v-model="entry.commission_currency" class="listing-input mt-1">
                                        <option value="GBP">GBP</option>
                                        <option value="PKR">PKR</option>
                                    </select>
                                </div>
                                <div class="md:col-span-3">
                                    <HelpLabel label="Role" tooltip="Single Owner means one person gets commission. Closer and Appointment Creator are useful for split commission." />
                                    <select v-model="entry.commission_role" class="listing-input mt-1">
                                        <option value="single_owner">Single Owner</option>
                                        <option value="appointment_creator">Appointment Creator</option>
                                        <option value="closer">Closer</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2 flex items-end">
                                    <button
                                        type="button"
                                        class="w-full rounded-lg border border-rose-200 px-3 py-2 text-sm font-medium text-rose-700 hover:bg-rose-50 disabled:opacity-50"
                                        :disabled="allocationRows.length === 1"
                                        @click="removeSplitRow(idx)"
                                    >
                                        Remove
                                    </button>
                                </div>
                            </div>
                            <div>
                                <HelpLabel label="Notes" tooltip="Optional internal note explaining why this person received commission." />
                                <input v-model="entry.notes" type="text" class="listing-input mt-1" placeholder="Optional note for this commission row" />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-500">GBP total</p>
                        <p class="mt-1 text-xl font-bold text-slate-950">{{ formatMoney('GBP', allocationTotals.GBP) }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-wide text-slate-500">PKR total</p>
                        <p class="mt-1 text-xl font-bold text-slate-950">{{ formatMoney('PKR', allocationTotals.PKR) }}</p>
                    </div>
                </section>
            </div>

            <div class="px-4 sm:px-6 py-4 border-t border-slate-200 flex flex-col-reverse sm:flex-row sm:justify-end gap-2">
                <button type="button" class="listing-btn-outline w-full sm:w-auto" @click="closeAllocation">Cancel</button>
                <button
                    type="button"
                    class="listing-btn-primary w-full sm:w-auto"
                    title="Save this commission allocation. Existing allocation for this sale will be replaced."
                    :disabled="savingAllocation"
                    @click="saveAllocation"
                >
                    {{ savingAllocation ? 'Saving...' : 'Save allocation' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, defineComponent, h, reactive, ref } from 'vue';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();
const loading = ref(false);
const users = ref([]);
const sales = ref([]);
const showAllocationModal = ref(false);
const savingAllocation = ref(false);
const activeSale = ref(null);
const allocationRows = ref([]);
let nextLocalId = 1;

const filters = reactive({
    from: '',
    to: '',
    processed: 'all',
});

const subtitle = 'Review won sales, allocate commission once, and keep eligible commission users clean.';
const statusTabs = [
    { value: 'all', label: 'All' },
    { value: 'no', label: 'Pending' },
    { value: 'yes', label: 'Allocated' },
];

const eligibleUsers = computed(() => users.value.filter((u) => !!u.commission_eligible));
const pendingCount = computed(() => sales.value.filter((row) => !row.commission_processed).length);
const allocatedCount = computed(() => sales.value.filter((row) => row.commission_processed).length);
const badgeText = computed(() => `${sales.value.length} sales`);

const allocatedTotals = computed(() => {
    const totals = { GBP: 0, PKR: 0 };
    for (const row of sales.value) {
        for (const entry of row.commission_entries || []) {
            const currency = entry.commission_currency;
            if (currency === 'GBP' || currency === 'PKR') {
                totals[currency] += Number(entry.commission_amount || 0);
            }
        }
    }
    return totals;
});

const overviewCards = computed(() => [
    {
        label: 'Needs allocation',
        value: pendingCount.value,
        detail: 'Won sales still waiting for commission',
        tooltip: 'Count of won sales in this filter that do not have any commission allocation yet.',
    },
    {
        label: 'Allocated',
        value: allocatedCount.value,
        detail: 'Sales already processed in this view',
        tooltip: 'Count of won sales in this filter that already have one or more commission rows.',
    },
    {
        label: 'GBP commission',
        value: formatMoney('GBP', allocatedTotals.value.GBP),
        detail: 'Allocated total in current filter',
        tooltip: 'Total GBP commission already allocated for the sales currently shown.',
    },
    {
        label: 'PKR commission',
        value: formatMoney('PKR', allocatedTotals.value.PKR),
        detail: 'Allocated total in current filter',
        tooltip: 'Total PKR commission already allocated for the sales currently shown.',
    },
]);

const allocationTotals = computed(() => {
    const totals = { GBP: 0, PKR: 0 };
    for (const row of allocationRows.value) {
        const currency = row.commission_currency;
        if ((currency === 'GBP' || currency === 'PKR') && Number(row.commission_amount) > 0) {
            totals[currency] += Number(row.commission_amount);
        }
    }
    return totals;
});

function pad2(n) {
    return String(n).padStart(2, '0');
}

function toYmd(d) {
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function defaultLastTwoMonthsRange() {
    const to = new Date();
    const from = new Date(to.getFullYear(), to.getMonth() - 2, to.getDate());
    return { from: toYmd(from), to: toYmd(to) };
}

function currentCalendarMonthRange() {
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth(), 1);
    return { from: toYmd(first), to: toYmd(now) };
}

function lastCalendarMonthRange() {
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    const last = new Date(now.getFullYear(), now.getMonth(), 0);
    return { from: toYmd(first), to: toYmd(last) };
}

function applyDatePreset(preset) {
    let range = defaultLastTwoMonthsRange();
    if (preset === 'thisMonth') range = currentCalendarMonthRange();
    if (preset === 'lastMonth') range = lastCalendarMonthRange();
    filters.from = range.from;
    filters.to = range.to;
    loadSales();
}

function setStatus(value) {
    filters.processed = value;
    loadSales();
}

function formatSaleDate(iso) {
    if (!iso) return '-';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function formatAmountNumber(value) {
    return new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));
}

function formatMoney(currency, amount) {
    const n = formatAmountNumber(amount);
    if (currency === 'GBP') return `GBP ${n}`;
    if (currency === 'PKR') return `PKR ${n}`;
    return `${currency || ''} ${n}`.trim();
}

function saleKey(row) {
    return `${row.lead_id}-${row.lead_item_id || 'lead'}`;
}

function statusPillClass(row) {
    return [
        'inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap',
        row.commission_processed ? 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' : 'bg-amber-50 text-amber-800 ring-1 ring-amber-200',
    ];
}

function allocationLabel(row) {
    const count = Array.isArray(row.commission_entries) ? row.commission_entries.length : 0;
    return count === 1 ? '1 allocation' : `${count} allocations`;
}

function emptyAllocationRow() {
    return {
        local_id: nextLocalId++,
        credited_user_id: '',
        commission_amount: '',
        commission_currency: 'GBP',
        commission_role: 'single_owner',
        notes: '',
    };
}

function addSplitRow() {
    allocationRows.value.push(emptyAllocationRow());
}

function removeSplitRow(index) {
    if (allocationRows.value.length === 1) return;
    allocationRows.value.splice(index, 1);
}

async function loadUsers() {
    const { data } = await axios.get('/api/commission-management/users');
    users.value = Array.isArray(data) ? data : [];
}

async function loadSales() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/commission-management/sales', {
            params: {
                from: filters.from || undefined,
                to: filters.to || undefined,
                processed: filters.processed,
            },
        });
        sales.value = Array.isArray(data) ? data : [];
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to load commission sales.');
    } finally {
        loading.value = false;
    }
}

async function toggleEligibility(user, event) {
    const checked = !!event?.target?.checked;
    try {
        await axios.patch(`/api/commission-management/users/${user.id}/eligibility`, {
            commission_eligible: checked,
        });
        user.commission_eligible = checked;
        toast.success(`Commission eligibility updated for ${user.name}.`);
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to update eligibility.');
        event.target.checked = !!user.commission_eligible;
    }
}

function openAllocation(row) {
    activeSale.value = row;
    const existing = Array.isArray(row.commission_entries) ? row.commission_entries : [];
    allocationRows.value = existing.length
        ? existing.map((x) => ({
              local_id: nextLocalId++,
              credited_user_id: x.credited_user_id,
              commission_amount: x.commission_amount,
              commission_currency: x.commission_currency || 'GBP',
              commission_role: x.commission_role || 'single_owner',
              notes: x.notes || '',
          }))
        : [emptyAllocationRow()];
    showAllocationModal.value = true;
}

function closeAllocation(force = false) {
    if (savingAllocation.value && !force) return;
    showAllocationModal.value = false;
    activeSale.value = null;
    allocationRows.value = [];
}

function cleanAllocationRows() {
    return allocationRows.value
        .filter((row) => row.credited_user_id && Number(row.commission_amount) > 0)
        .map((row) => ({
            credited_user_id: Number(row.credited_user_id),
            commission_amount: Number(row.commission_amount),
            commission_currency: row.commission_currency || 'GBP',
            commission_role: row.commission_role || 'single_owner',
            notes: row.notes || null,
        }));
}

function hasDuplicateAllocation(rows) {
    const seen = new Set();
    for (const row of rows) {
        const key = `${row.credited_user_id}-${row.commission_role}-${row.commission_currency}`;
        if (seen.has(key)) return true;
        seen.add(key);
    }
    return false;
}

async function saveAllocation() {
    if (savingAllocation.value || !activeSale.value) return;

    const cleanRows = cleanAllocationRows();
    if (!cleanRows.length) {
        toast.error('Add at least one valid allocation row.');
        return;
    }
    if (hasDuplicateAllocation(cleanRows)) {
        toast.error('Duplicate allocation found. Combine rows for the same user, role, and currency before saving.');
        return;
    }

    savingAllocation.value = true;
    try {
        await axios.post('/api/commission-management/allocations', {
            lead_id: activeSale.value.lead_id,
            lead_item_id: activeSale.value.lead_item_id,
            allocations: cleanRows,
        });
        toast.success('Commission allocation saved.');
        closeAllocation(true);
        await loadSales();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to save allocation.');
    } finally {
        savingAllocation.value = false;
    }
}

async function bootstrap() {
    const defaults = defaultLastTwoMonthsRange();
    filters.from = defaults.from;
    filters.to = defaults.to;
    try {
        await Promise.all([loadUsers(), loadSales()]);
    } catch (e) {
        // Individual loaders show the relevant message.
    }
}

bootstrap();

const HelpLabel = defineComponent({
    name: 'HelpLabel',
    props: {
        label: { type: String, required: true },
        tooltip: { type: String, required: true },
        textClass: { type: String, default: 'text-xs font-medium text-slate-500' },
        align: { type: String, default: 'left' },
    },
    setup(props) {
        return () => h('div', {
            class: [
                'flex min-w-0 items-center gap-1',
                props.align === 'right' ? 'justify-end' : 'justify-start',
            ],
            title: props.tooltip,
        }, [
            h('span', { class: ['min-w-0 truncate', props.textClass] }, props.label),
            h('span', { class: 'group relative inline-flex shrink-0' }, [
                h('button', {
                    type: 'button',
                    class: 'grid h-4 w-4 place-items-center rounded-full border border-slate-300 bg-white text-[10px] font-bold leading-none text-slate-500 focus:outline-none focus:ring-2 focus:ring-violet-200',
                    'aria-label': `${props.label}: ${props.tooltip}`,
                }, '?'),
                h('span', {
                    class: [
                        'pointer-events-none absolute bottom-full z-40 mb-2 hidden w-60 rounded-md bg-slate-900 px-3 py-2 text-left text-[11px] font-medium leading-4 text-white shadow-lg group-hover:block group-focus-within:block',
                        props.align === 'right' ? 'right-0' : 'left-0',
                    ],
                }, props.tooltip),
            ]),
        ]);
    },
});
</script>
