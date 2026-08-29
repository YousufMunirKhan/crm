<template>
    <ListingPageShell title="Commission Reports" :subtitle="subtitle" :badge="badgeText">
        <template #actions>
            <BaseButton variant="outline" to="/commission/allocate" block-mobile>Allocation</BaseButton>
        </template>

        <template #filters>
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                    <div>
                        <label class="form-label" for="commissionreportview-from">From</label>
                        <input id="commissionreportview-from" v-model="filters.from" type="date" class="form-input" />
                    </div>
                    <div>
                        <label class="form-label" for="commissionreportview-to">To</label>
                        <input id="commissionreportview-to" v-model="filters.to" type="date" class="form-input" />
                    </div>
                    <div>
                        <div class="mb-1">
                            <HelpLabel
                                label="User"
                                for-id="commissionreportview-user"
                                tooltip="Choose All users for an admin report, or choose one employee for an individual report."
                            />
                        </div>
                        <select id="commissionreportview-user" v-model="filters.user_id" class="form-select">
                            <option value="">All users</option>
                            <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="mb-1">
                            <HelpLabel
                                label="Currency"
                                for-id="commissionreportview-currency"
                                tooltip="Filter to one currency, or leave All to show GBP and PKR separately."
                            />
                        </div>
                        <select id="commissionreportview-currency" v-model="filters.currency" class="form-select">
                            <option value="">All</option>
                            <option value="GBP">GBP</option>
                            <option value="PKR">PKR</option>
                        </select>
                    </div>
                    <div>
                        <div class="mb-1">
                            <HelpLabel
                                label="Role"
                                for-id="commissionreportview-role"
                                tooltip="Filter by why the commission was given: single owner, appointment creator, or closer."
                            />
                        </div>
                        <select id="commissionreportview-role" v-model="filters.commission_role" class="form-select">
                            <option value="">All roles</option>
                            <option value="single_owner">Single Owner</option>
                            <option value="appointment_creator">Appointment Creator</option>
                            <option value="closer">Closer</option>
                        </select>
                    </div>
                    <BaseButton variant="primary" type="button" class="w-full" :disabled="loading" :loading="loading" @click="loadReport">
                        {{ loading ? 'Loading...' : 'Apply' }}
                    </BaseButton>
                </div>
                <div class="flex flex-wrap gap-2">
                    <BaseButton variant="outline" size="sm" type="button" @click="applyDatePreset('thisMonth')">This month</BaseButton>
                    <BaseButton variant="outline" size="sm" type="button" @click="applyDatePreset('lastMonth')">Last month</BaseButton>
                    <BaseButton variant="ghost" size="sm" type="button" @click="resetFilters">Reset</BaseButton>
                </div>
            </div>
        </template>

        <div class="p-4 sm:p-6 space-y-6">
            <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                <StatCard
                    v-for="card in summaryCards"
                    :key="card.label"
                    :label="card.label"
                    :value="card.value"
                    :caption="card.detail"
                    :title="card.tooltip"
                />
            </section>

            <section class="callout callout-info">
                <h2 class="font-semibold text-slate-950">How to read commission reports</h2>
                <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-slate-700 md:grid-cols-3">
                    <div class="rounded-control bg-white/80 px-3 py-2 ring-1 ring-primary-100">
                        <span class="font-semibold text-slate-900">1. Set filters</span>
                        <span class="block text-xs text-slate-500">Date, user, currency, and role control every number below.</span>
                    </div>
                    <div class="rounded-control bg-white/80 px-3 py-2 ring-1 ring-primary-100">
                        <span class="font-semibold text-slate-900">2. Review totals</span>
                        <span class="block text-xs text-slate-500">Summary boxes and user cards update from the filtered data.</span>
                    </div>
                    <div class="rounded-control bg-white/80 px-3 py-2 ring-1 ring-primary-100">
                        <span class="font-semibold text-slate-900">3. Send report</span>
                        <span class="block text-xs text-slate-500">Use admin email for all users, or select one user for their report.</span>
                    </div>
                </div>
            </section>

            <BaseCard :padded="false">
                <template #header>
                    <HelpLabel label="Monthly Report" tooltip="This report uses commission record dates, not the original sale date." text-class="font-semibold text-slate-900" />
                    <p class="text-sm text-slate-500">{{ periodText }}</p>
                </template>
                <template #actions>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 w-full lg:w-auto">
                        <BaseButton
                            variant="outline"
                            type="button"
                            class="w-full"
                            title="Download one consolidated PDF using the current filters."
                            :disabled="!rows.length || downloadingFullPdf"
                            :loading="downloadingFullPdf"
                            @click="downloadFullPdf"
                        >
                            <template #icon>
                                <ArrowDownTrayIcon class="icon" aria-hidden="true" />
                            </template>
                            {{ downloadingFullPdf ? 'Preparing...' : 'Download full PDF' }}
                        </BaseButton>
                        <BaseButton
                            variant="outline"
                            type="button"
                            class="w-full"
                            title="Download a PDF for the selected user. Select one person first."
                            :disabled="!canSendSelectedUser || downloadingUserPdf"
                            :loading="downloadingUserPdf"
                            @click="downloadUserPdf"
                        >
                            <template #icon>
                                <ArrowDownTrayIcon class="icon" aria-hidden="true" />
                            </template>
                            {{ downloadingUserPdf ? 'Preparing...' : 'User PDF' }}
                        </BaseButton>
                        <BaseButton
                            variant="soft"
                            type="button"
                            class="w-full"
                            title="Email the filtered commission report to configured admin and manager recipients."
                            :disabled="!rows.length || sendingInternal"
                            :loading="sendingInternal"
                            @click="sendInternal"
                        >
                            <template #icon>
                                <EnvelopeIcon class="icon" aria-hidden="true" />
                            </template>
                            {{ sendingInternal ? 'Sending...' : 'Email admins' }}
                        </BaseButton>
                    </div>
                </template>

                <div v-if="loading" class="p-6 space-y-3" aria-busy="true">
                    <p class="sr-only">Loading commission report…</p>
                    <div class="skeleton-text w-1/3"></div>
                    <div class="skeleton-text w-2/3"></div>
                    <div class="skeleton-text w-1/2"></div>
                    <div class="skeleton-text w-3/4"></div>
                </div>
                <EmptyState
                    v-else-if="!rows.length"
                    heading="No commission entries"
                    description="No commission entries found for this filter."
                >
                    <template #icon>
                        <BanknotesIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>
                <div v-else class="grid grid-cols-1 xl:grid-cols-[18rem_minmax(0,1fr)]">
                    <aside class="border-b xl:border-b-0 xl:border-r border-slate-100 bg-slate-50 p-3">
                        <div class="px-2 pb-2">
                            <HelpLabel label="People" tooltip="Select All users to inspect the full report, or select one person to download/email their individual PDF." text-class="text-eyebrow text-slate-500 uppercase" />
                        </div>
                        <div class="space-y-2 max-h-80 xl:max-h-[44rem] overflow-y-auto">
                            <button
                                type="button"
                                class="w-full rounded-control border px-3 py-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                :class="selectedUserId == null ? 'border-slate-900 bg-white shadow-card' : 'border-transparent bg-white/70 hover:bg-white hover:border-slate-200'"
                                :aria-pressed="selectedUserId == null ? 'true' : 'false'"
                                @click="selectUser(null)"
                            >
                                <span class="block font-semibold text-slate-900">All users</span>
                                <span class="mt-1 block text-xs text-slate-500 tabular-nums">{{ rows.length }} entries</span>
                                <span class="mt-2 flex flex-wrap gap-1 text-xs text-slate-700">
                                    <span class="rounded bg-slate-100 px-2 py-1 tabular-nums">{{ formatMoney('GBP', totals.GBP) }}</span>
                                    <span class="rounded bg-slate-100 px-2 py-1 tabular-nums">{{ formatMoney('PKR', totals.PKR) }}</span>
                                </span>
                            </button>
                            <button
                                v-for="s in userSummaries"
                                :key="s.id"
                                type="button"
                                class="w-full rounded-control border px-3 py-3 text-left transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                :class="selectedUserId === s.id ? 'border-slate-900 bg-white shadow-card' : 'border-transparent bg-white/70 hover:bg-white hover:border-slate-200'"
                                :aria-pressed="selectedUserId === s.id ? 'true' : 'false'"
                                @click="selectUser(s.id)"
                            >
                                <span class="block font-semibold text-slate-900 truncate">{{ s.name }}</span>
                                <span class="mt-1 block text-xs text-slate-500 tabular-nums">{{ s.count }} entries</span>
                                <span class="mt-2 flex flex-wrap gap-1 text-xs text-slate-700">
                                    <span class="rounded bg-slate-100 px-2 py-1 tabular-nums">{{ formatMoney('GBP', s.totals.GBP) }}</span>
                                    <span class="rounded bg-slate-100 px-2 py-1 tabular-nums">{{ formatMoney('PKR', s.totals.PKR) }}</span>
                                </span>
                            </button>
                        </div>
                    </aside>

                    <div class="min-w-0">
                        <div class="px-4 py-3 border-b border-slate-100 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <HelpLabel :label="selectedUserName" tooltip="The table below shows commission entries for the selected person, or all users when All users is selected." text-class="font-semibold text-slate-900" />
                                <p class="text-sm text-slate-500 tabular-nums">
                                    {{ displayRows.length }} commission {{ displayRows.length === 1 ? 'entry' : 'entries' }}
                                </p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <BaseButton
                                    variant="soft"
                                    type="button"
                                    class="w-full"
                                    title="Email the selected user's commission PDF to their saved profile email."
                                    :disabled="!canSendSelectedUser || sendingUser"
                                    :loading="sendingUser"
                                    @click="sendToUser"
                                >
                                    <template #icon>
                                        <EnvelopeIcon class="icon" aria-hidden="true" />
                                    </template>
                                    {{ sendingUser ? 'Sending...' : 'Email this user' }}
                                </BaseButton>
                                <p v-if="selectedUserId && !selectedUserEmail" class="text-sm text-warning-800 sm:self-center">
                                    User email missing.
                                </p>
                            </div>
                        </div>

                        <div class="hidden lg:block table-wrap">
                            <table class="table">
                                <caption class="sr-only">Commission entries with date, customer, product, role, who assigned them and the commission amount</caption>
                                <thead class="table-thead">
                                    <tr>
                                        <th scope="col" class="table-th">Date</th>
                                        <th scope="col" class="table-th">Customer</th>
                                        <th scope="col" class="table-th">Product</th>
                                        <th scope="col" class="table-th">Role</th>
                                        <th scope="col" class="table-th">Assigned by</th>
                                        <th scope="col" class="table-th-num">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="r in displayRows" :key="r.id" class="table-row">
                                        <td class="table-td whitespace-nowrap">{{ formatDate(r.created_at) }}</td>
                                        <td class="table-td">{{ r.customer_name || '-' }}</td>
                                        <td class="table-td">{{ r.product_name || '-' }}</td>
                                        <td class="table-td">{{ humanRole(r.commission_role) }}</td>
                                        <td class="table-td">{{ r.assigned_by_user_name || '-' }}</td>
                                        <td class="table-td-num font-semibold text-slate-950">{{ formatMoney(r.commission_currency, r.commission_amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="lg:hidden divide-y divide-slate-100">
                            <article v-for="r in displayRows" :key="r.id" class="p-4 space-y-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-xs text-slate-500">Customer</p>
                                        <p class="font-semibold text-slate-900 break-words">{{ r.customer_name || '-' }}</p>
                                        <p class="mt-2 text-xs text-slate-500">Product</p>
                                        <p class="text-sm text-slate-600 break-words">{{ r.product_name || '-' }}</p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="text-xs text-slate-500">Amount</p>
                                        <p class="font-semibold text-slate-950 whitespace-nowrap tabular-nums">{{ formatMoney(r.commission_currency, r.commission_amount) }}</p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <p class="text-xs text-slate-500">Date</p>
                                        <p class="font-medium text-slate-800">{{ formatDate(r.created_at) }}</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Role</p>
                                        <p class="font-medium text-slate-800">{{ humanRole(r.commission_role) }}</p>
                                    </div>
                                    <div class="col-span-2">
                                        <p class="text-xs text-slate-500">Assigned by</p>
                                        <p class="font-medium text-slate-800">{{ r.assigned_by_user_name || '-' }}</p>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>
                </div>
            </BaseCard>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { computed, defineComponent, h, reactive, ref, watch } from 'vue';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { useToastStore } from '@/stores/toast';
import { BaseButton, BaseCard, EmptyState, StatCard } from '@/components/base';
import { ArrowDownTrayIcon, BanknotesIcon, EnvelopeIcon } from '@heroicons/vue/24/outline';

const toast = useToastStore();
const loading = ref(false);
const rows = ref([]);
const users = ref([]);
const selectedUserId = ref(null);
const sendingUser = ref(false);
const sendingInternal = ref(false);
const downloadingUserPdf = ref(false);
const downloadingFullPdf = ref(false);

const filters = reactive({
    from: '',
    to: '',
    user_id: '',
    currency: '',
    commission_role: '',
});

const subtitle = 'Filter monthly commission, review user totals, and send PDF reports from one place.';

const badgeText = computed(() => {
    if (loading.value) return 'Loading';
    return `${rows.value.length} entries`;
});

const totals = computed(() => {
    const t = { GBP: 0, PKR: 0 };
    for (const r of rows.value) {
        const c = r.commission_currency;
        if (c === 'GBP' || c === 'PKR') {
            t[c] += Number(r.commission_amount || 0);
        }
    }
    return t;
});

const userSummaries = computed(() => {
    const map = new Map();
    for (const r of rows.value) {
        const id = r.credited_user_id;
        if (!map.has(id)) {
            map.set(id, { id, name: r.credited_user_name || `User #${id}`, totals: { GBP: 0, PKR: 0 }, count: 0 });
        }
        const summary = map.get(id);
        summary.count += 1;
        const currency = r.commission_currency;
        if (currency === 'GBP' || currency === 'PKR') {
            summary.totals[currency] += Number(r.commission_amount || 0);
        }
    }
    return Array.from(map.values()).sort((a, b) => String(a.name).localeCompare(String(b.name)));
});

const topUser = computed(() => {
    if (!userSummaries.value.length) return null;
    return [...userSummaries.value].sort((a, b) => (b.totals.GBP + b.totals.PKR) - (a.totals.GBP + a.totals.PKR))[0];
});

const displayRows = computed(() => {
    if (selectedUserId.value == null || selectedUserId.value === '') return rows.value;
    const sid = Number(selectedUserId.value);
    return rows.value.filter((r) => Number(r.credited_user_id) === sid);
});

const selectedUserName = computed(() => {
    if (selectedUserId.value == null || selectedUserId.value === '') return 'All users';
    return userSummaries.value.find((s) => Number(s.id) === Number(selectedUserId.value))?.name || 'Selected user';
});

const selectedUserEmail = computed(() => {
    if (selectedUserId.value == null || selectedUserId.value === '') return '';
    const user = users.value.find((x) => Number(x.id) === Number(selectedUserId.value));
    const email = user?.email;
    return typeof email === 'string' && email.includes('@') ? email.trim() : '';
});

const canSendSelectedUser = computed(() => !!selectedUserId.value && !!selectedUserEmail.value && !!displayRows.value.length);

const periodText = computed(() => {
    if (!filters.from && !filters.to) return 'Default period';
    if (filters.from && filters.to) return `${filters.from} to ${filters.to}`;
    return filters.from ? `From ${filters.from}` : `Until ${filters.to}`;
});

const summaryCards = computed(() => [
    {
        label: 'Entries',
        value: rows.value.length,
        detail: 'Commission rows in this filter',
        tooltip: 'Total commission rows matching the selected date, user, currency, and role filters.',
    },
    {
        label: 'Users',
        value: userSummaries.value.length,
        detail: 'People with commission',
        tooltip: 'Number of employees who have at least one commission row in the filtered report.',
    },
    {
        label: 'GBP total',
        value: formatMoney('GBP', totals.value.GBP),
        detail: 'Filtered GBP commission',
        tooltip: 'Total GBP commission amount for the current filter. PKR is not converted into GBP.',
    },
    {
        label: 'PKR total',
        value: formatMoney('PKR', totals.value.PKR),
        detail: 'Filtered PKR commission',
        tooltip: 'Total PKR commission amount for the current filter. GBP is not converted into PKR.',
    },
    {
        label: 'Most entries',
        value: topUser.value?.name || '-',
        detail: topUser.value ? `${topUser.value.count} entries` : 'No entries yet',
        tooltip: 'The person with the highest number of commission rows in this filtered report.',
    },
]);

watch(
    userSummaries,
    () => {
        if (!userSummaries.value.length) {
            selectedUserId.value = null;
            return;
        }
        if (filters.user_id) {
            selectedUserId.value = Number(filters.user_id);
            return;
        }
        if (selectedUserId.value == null) {
            return;
        }
        if (!userSummaries.value.some((s) => Number(s.id) === Number(selectedUserId.value))) {
            selectedUserId.value = null;
        }
    },
    { immediate: true },
);

function toYmd(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

function currentCalendarMonthRange() {
    const now = new Date();
    return {
        from: toYmd(new Date(now.getFullYear(), now.getMonth(), 1)),
        to: toYmd(now),
    };
}

function lastCalendarMonthRange() {
    const now = new Date();
    return {
        from: toYmd(new Date(now.getFullYear(), now.getMonth() - 1, 1)),
        to: toYmd(new Date(now.getFullYear(), now.getMonth(), 0)),
    };
}

function defaultRange() {
    const now = new Date();
    return {
        from: toYmd(new Date(now.getFullYear(), now.getMonth() - 2, now.getDate())),
        to: toYmd(now),
    };
}

function applyDatePreset(preset) {
    const range = preset === 'lastMonth' ? lastCalendarMonthRange() : currentCalendarMonthRange();
    filters.from = range.from;
    filters.to = range.to;
    loadReport();
}

function resetFilters() {
    const range = defaultRange();
    filters.from = range.from;
    filters.to = range.to;
    filters.user_id = '';
    filters.currency = '';
    filters.commission_role = '';
    selectedUserId.value = null;
    loadReport();
}

function selectUser(id) {
    selectedUserId.value = id;
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

function humanRole(role) {
    if (role === 'appointment_creator') return 'Appointment Creator';
    if (role === 'single_owner') return 'Single Owner';
    if (role === 'closer') return 'Closer';
    return role || '-';
}

function formatDate(iso) {
    if (!iso) return '-';
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

function reportParams() {
    return {
        from: filters.from || undefined,
        to: filters.to || undefined,
        credited_user_id: filters.user_id || undefined,
        currency: filters.currency || undefined,
        commission_role: filters.commission_role || undefined,
    };
}

async function loadUsers() {
    const { data } = await axios.get('/api/commission-management/users');
    users.value = Array.isArray(data) ? data : [];
}

async function loadReport() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/commission-management/report', {
            params: reportParams(),
        });
        rows.value = Array.isArray(data) ? data : [];
        selectedUserId.value = filters.user_id ? Number(filters.user_id) : null;
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to load commission report.');
    } finally {
        loading.value = false;
    }
}

async function sendToUser() {
    if (sendingUser.value || !canSendSelectedUser.value || !filters.from || !filters.to) return;
    sendingUser.value = true;
    try {
        const { data } = await axios.post('/api/commission-management/report/send-to-user', {
            from: filters.from,
            to: filters.to,
            credited_user_id: selectedUserId.value,
            currency: filters.currency || undefined,
            commission_role: filters.commission_role || undefined,
        });
        toast.success(data?.message || 'Report sent.');
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to send report.');
    } finally {
        sendingUser.value = false;
    }
}

function pdfDownloadParamsForUser() {
    return {
        from: filters.from,
        to: filters.to,
        credited_user_id: selectedUserId.value,
        ...(filters.currency ? { currency: filters.currency } : {}),
        ...(filters.commission_role ? { commission_role: filters.commission_role } : {}),
    };
}

function pdfDownloadParamsForFull() {
    return {
        from: filters.from,
        to: filters.to,
        ...(filters.user_id ? { credited_user_id: Number(filters.user_id) } : {}),
        ...(filters.currency ? { currency: filters.currency } : {}),
        ...(filters.commission_role ? { commission_role: filters.commission_role } : {}),
    };
}

async function downloadPdfGet(url, params, fallbackName) {
    try {
        const res = await axios.get(url, {
            params,
            responseType: 'blob',
            validateStatus: (s) => s < 500,
        });
        if (res.status === 422 || res.status === 403) {
            const text = await res.data.text();
            try {
                const j = JSON.parse(text);
                toast.error(j.message || 'Download failed.');
            } catch {
                toast.error('Download failed.');
            }
            return;
        }
        if (res.status >= 400) {
            toast.error('Download failed.');
            return;
        }
        const disposition = res.headers['content-disposition'] || '';
        let filename = fallbackName;
        const match = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(disposition);
        if (match && match[1]) {
            filename = String(match[1]).replace(/['"]/g, '').trim();
        }
        const href = URL.createObjectURL(res.data);
        const a = document.createElement('a');
        a.href = href;
        a.download = filename;
        a.click();
        URL.revokeObjectURL(href);
        toast.success('PDF downloaded.');
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to download PDF.');
    }
}

async function downloadUserPdf() {
    if (downloadingUserPdf.value || !canSendSelectedUser.value || !filters.from || !filters.to) return;
    downloadingUserPdf.value = true;
    try {
        await downloadPdfGet('/api/commission-management/report/pdf/user', pdfDownloadParamsForUser(), 'commission_summary.pdf');
    } finally {
        downloadingUserPdf.value = false;
    }
}

async function downloadFullPdf() {
    if (downloadingFullPdf.value || !rows.value.length || !filters.from || !filters.to) return;
    downloadingFullPdf.value = true;
    try {
        await downloadPdfGet('/api/commission-management/report/pdf/full', pdfDownloadParamsForFull(), 'commission_report.pdf');
    } finally {
        downloadingFullPdf.value = false;
    }
}

async function sendInternal() {
    if (sendingInternal.value || !rows.value.length || !filters.from || !filters.to) return;
    sendingInternal.value = true;
    try {
        const body = {
            from: filters.from,
            to: filters.to,
            credited_user_id: filters.user_id ? Number(filters.user_id) : undefined,
            currency: filters.currency || undefined,
            commission_role: filters.commission_role || undefined,
        };
        const { data } = await axios.post('/api/commission-management/report/send-internal', body);
        toast.success(data?.message || 'Internal report sent.');
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to send internal report.');
    } finally {
        sendingInternal.value = false;
    }
}

async function bootstrap() {
    const range = defaultRange();
    filters.from = range.from;
    filters.to = range.to;
    try {
        await Promise.all([loadUsers(), loadReport()]);
    } catch (e) {
        // Loaders show their own toast messages.
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
        /** When set, the text renders as a real <label for> tied to that control id. */
        forId: { type: String, default: '' },
    },
    setup(props) {
        return () => h('div', {
            class: [
                'flex min-w-0 items-center gap-1',
                props.align === 'right' ? 'justify-end' : 'justify-start',
            ],
            title: props.tooltip,
        }, [
            h(
                props.forId ? 'label' : 'span',
                {
                    class: ['min-w-0 truncate', props.textClass],
                    ...(props.forId ? { for: props.forId } : {}),
                },
                props.label,
            ),
            h('span', { class: 'group relative inline-flex shrink-0' }, [
                h('button', {
                    type: 'button',
                    class: 'grid h-4 w-4 place-items-center rounded-full border border-slate-300 bg-white text-[10px] font-bold leading-none text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40',
                    'aria-label': `${props.label}: ${props.tooltip}`,
                }, '?'),
                h('span', {
                    class: [
                        'pointer-events-none absolute bottom-full z-dropdown mb-2 hidden w-60 rounded-md bg-slate-900 px-3 py-2 text-left text-[11px] font-medium leading-4 text-white shadow-dropdown group-hover:block group-focus-within:block',
                        props.align === 'right' ? 'right-0' : 'left-0',
                    ],
                }, props.tooltip),
            ]),
        ]);
    },
});
</script>
