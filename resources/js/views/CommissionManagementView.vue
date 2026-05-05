<template>
    <ListingPageShell title="Commission Management" :subtitle="subtitle" :badge="`${sales.length} sales`">
        <template #filters>
            <div class="space-y-3">
                <p class="text-xs text-slate-600">
                    Won sales are filtered by <strong>line-item close date</strong>. The page opens on the
                    <strong>last two months</strong>; use <strong>Last month</strong> or set a custom From/To and click
                    <strong>Apply filters</strong>. (If you clear both dates, the server still applies the last two months.)
                </p>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="px-3 py-2 text-xs sm:text-sm rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50" @click="applyDatePreset('twoMonths')">
                        Last 2 months
                    </button>
                    <button type="button" class="px-3 py-2 text-xs sm:text-sm rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50" @click="applyDatePreset('lastMonth')">
                        Last month
                    </button>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
                        <input v-model="filters.from" type="date" class="listing-input" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
                        <input v-model="filters.to" type="date" class="listing-input" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Commission status</label>
                        <select v-model="filters.processed" class="listing-input">
                            <option value="all">All sales</option>
                            <option value="yes">Processed</option>
                            <option value="no">Unprocessed</option>
                        </select>
                    </div>
                    <button type="button" class="listing-btn-primary w-full md:w-auto" @click="loadSales">Apply filters</button>
                </div>
            </div>
        </template>

        <div class="p-4 sm:p-6 space-y-5">
            <section class="rounded-xl border border-slate-200 bg-white">
                <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                    <h2 class="font-semibold text-slate-900">Commission eligibility</h2>
                    <span class="text-xs text-slate-500">Admin toggle by user</span>
                </div>
                <div class="max-h-64 overflow-y-auto divide-y divide-slate-100">
                    <div
                        v-for="u in users"
                        :key="u.id"
                        class="px-4 py-2.5 flex items-center justify-between gap-2"
                    >
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ u.name }}</p>
                            <p class="text-xs text-slate-500">{{ u.role?.name || '—' }}</p>
                        </div>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
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

            <section class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100">
                    <h2 class="font-semibold text-slate-900">Won sales</h2>
                </div>
                <div v-if="loading" class="p-6 text-sm text-slate-500">Loading sales...</div>
                <div v-else-if="!sales.length" class="p-6 text-sm text-slate-500">No won sales found.</div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-2 text-left">Customer</th>
                                <th class="px-4 py-2 text-left">Product</th>
                                <th class="px-4 py-2 text-left whitespace-nowrap">Sale date</th>
                                <th class="px-4 py-2 text-left">Lead owner</th>
                                <th class="px-4 py-2 text-left">Status</th>
                                <th class="px-4 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in sales" :key="`${row.lead_id}-${row.lead_item_id}`">
                                <td class="px-4 py-2">{{ row.customer_name || '-' }}</td>
                                <td class="px-4 py-2">{{ row.product_name || '-' }}</td>
                                <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ formatSaleDate(row.closed_at) }}</td>
                                <td class="px-4 py-2">{{ row.lead_assigned_to_name || '-' }}</td>
                                <td class="px-4 py-2">
                                    <span :class="row.commission_processed ? 'text-emerald-700' : 'text-amber-700'">
                                        {{ row.commission_processed ? 'Processed' : 'Pending' }}
                                    </span>
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <button type="button" class="listing-btn-primary !py-1.5 !text-xs" @click="openAllocation(row)">
                                        {{ row.commission_processed ? 'Edit allocation' : 'Allocate' }}
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </ListingPageShell>

    <div v-if="showAllocationModal" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full">
            <div class="px-6 py-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Allocate Commission</h3>
                <p class="text-sm text-slate-600 mt-1">
                    {{ activeSale?.customer_name }} - {{ activeSale?.product_name }}
                </p>
            </div>
            <div class="p-6 space-y-3 max-h-[60vh] overflow-y-auto">
                <div
                    v-for="(entry, idx) in allocationRows"
                    :key="idx"
                    class="grid grid-cols-1 md:grid-cols-12 gap-2 border border-slate-200 rounded-lg p-3"
                >
                    <div class="md:col-span-4">
                        <label class="text-xs text-slate-600">User</label>
                        <select v-model="entry.credited_user_id" class="listing-input">
                            <option value="">Select user...</option>
                            <option
                                v-for="u in eligibleUsers"
                                :key="u.id"
                                :value="u.id"
                            >
                                {{ u.name }}
                            </option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="text-xs text-slate-600">Amount</label>
                        <input v-model.number="entry.commission_amount" type="number" step="0.01" min="0.01" class="listing-input" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="text-xs text-slate-600">Currency</label>
                        <select v-model="entry.commission_currency" class="listing-input">
                            <option value="GBP">£ GBP</option>
                            <option value="PKR">PKR</option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <label class="text-xs text-slate-600">Role</label>
                        <select v-model="entry.commission_role" class="listing-input">
                            <option value="single_owner">Single Owner</option>
                            <option value="appointment_creator">Appointment Creator</option>
                            <option value="closer">Closer</option>
                        </select>
                    </div>
                </div>
                <button type="button" class="px-3 py-2 text-sm rounded-md border border-slate-300 text-slate-700 hover:bg-slate-50" @click="addSplitRow">Add split row</button>
            </div>
            <div class="px-6 pb-6 flex justify-end gap-2">
                <button type="button" class="px-3 py-2 text-sm rounded-md border border-slate-300 text-slate-700 hover:bg-slate-50" @click="closeAllocation">Cancel</button>
                <button type="button" class="listing-btn-primary" :disabled="savingAllocation" @click="saveAllocation">
                    {{ savingAllocation ? 'Saving...' : 'Save Allocation' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
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

const filters = reactive({
    from: '',
    to: '',
    processed: 'all',
});

function pad2(n) {
    return String(n).padStart(2, '0');
}

/** @param {Date} d */
function toYmd(d) {
    return `${d.getFullYear()}-${pad2(d.getMonth() + 1)}-${pad2(d.getDate())}`;
}

function defaultLastTwoMonthsRange() {
    const to = new Date();
    const from = new Date(to.getFullYear(), to.getMonth() - 2, to.getDate());
    return { from: toYmd(from), to: toYmd(to) };
}

function lastCalendarMonthRange() {
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth() - 1, 1);
    const last = new Date(now.getFullYear(), now.getMonth(), 0);
    return { from: toYmd(first), to: toYmd(last) };
}

/**
 * @param {'twoMonths' | 'lastMonth'} preset
 */
function applyDatePreset(preset) {
    const r = preset === 'lastMonth' ? lastCalendarMonthRange() : defaultLastTwoMonthsRange();
    filters.from = r.from;
    filters.to = r.to;
    loadSales();
}

function formatSaleDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

const twoMonthDefaults = defaultLastTwoMonthsRange();
filters.from = twoMonthDefaults.from;
filters.to = twoMonthDefaults.to;

const subtitle = 'Manage commission eligibility and manually allocate single or split commission for won sales.';
const eligibleUsers = computed(() => users.value.filter((u) => !!u.commission_eligible));

function emptyAllocationRow() {
    return {
        credited_user_id: '',
        commission_amount: '',
        commission_currency: 'GBP',
        commission_role: 'single_owner',
    };
}

function addSplitRow() {
    allocationRows.value.push(emptyAllocationRow());
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
              credited_user_id: x.credited_user_id,
              commission_amount: x.commission_amount,
              commission_currency: x.commission_currency || 'GBP',
              commission_role: x.commission_role || 'single_owner',
          }))
        : [emptyAllocationRow()];
    showAllocationModal.value = true;
}

function closeAllocation() {
    showAllocationModal.value = false;
    activeSale.value = null;
    allocationRows.value = [];
}

async function saveAllocation() {
    if (!activeSale.value) return;
    const cleanRows = allocationRows.value.filter((row) => row.credited_user_id && Number(row.commission_amount) > 0);
    if (!cleanRows.length) {
        toast.error('Add at least one valid allocation row.');
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
        closeAllocation();
        await loadSales();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to save allocation.');
    } finally {
        savingAllocation.value = false;
    }
}

async function bootstrap() {
    try {
        await Promise.all([loadUsers(), loadSales()]);
    } catch (e) {
        // handled inside loaders
    }
}

bootstrap();
</script>
