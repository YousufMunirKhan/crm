<template>
    <ListingPageShell title="Commission Reports" :subtitle="subtitle" :badge="badgeText">
        <template #actions>
            <router-link to="/commission/allocate" class="listing-btn-outline w-full sm:w-auto text-center">
                Allocation
            </router-link>
        </template>

        <template #filters>
            <div class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
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
                            <HelpLabel label="User" tooltip="Choose All users for an admin report, or choose one employee for an individual report." />
                        </div>
                        <select v-model="filters.user_id" class="listing-input">
                            <option value="">All users</option>
                            <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                        </select>
                    </div>
                    <div>
                        <div class="mb-1">
                            <HelpLabel label="Currency" tooltip="Filter to one currency, or leave All to show GBP and PKR separately." />
                        </div>
                        <select v-model="filters.currency" class="listing-input">
                            <option value="">All</option>
                            <option value="GBP">GBP</option>
                            <option value="PKR">PKR</option>
                        </select>
                    </div>
                    <div>
                        <div class="mb-1">
                            <HelpLabel label="Role" tooltip="Filter by why the commission was given: single owner, appointment creator, or closer." />
                        </div>
                        <select v-model="filters.commission_role" class="listing-input">
                            <option value="">All roles</option>
                            <option value="single_owner">Single Owner</option>
                            <option value="appointment_creator">Appointment Creator</option>
                            <option value="closer">Closer</option>
                        </select>
                    </div>
                    <button type="button" class="listing-btn-primary w-full" :disabled="loading" @click="loadReport">
                        {{ loading ? 'Loading...' : 'Apply' }}
                    </button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="px-3 py-2 text-xs sm:text-sm rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50" @click="applyDatePreset('thisMonth')">
                        This month
                    </button>
                    <button type="button" class="px-3 py-2 text-xs sm:text-sm rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50" @click="applyDatePreset('lastMonth')">
                        Last month
                    </button>
                    <button type="button" class="px-3 py-2 text-xs sm:text-sm rounded-lg border border-slate-300 bg-white text-slate-700 hover:bg-slate-50" @click="resetFilters">
                        Reset
                    </button>
                </div>
            </div>
        </template>

        <div class="p-4 sm:p-6 space-y-6">
            <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                <div v-for="card in summaryCards" :key="card.label" class="rounded-lg border border-slate-200 bg-white p-4">
                    <HelpLabel :label="card.label" :tooltip="card.tooltip" />
                    <p class="mt-2 text-2xl font-bold text-slate-950">{{ card.value }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ card.detail }}</p>
                </div>
            </section>

            <section class="rounded-lg border border-sky-200 bg-sky-50/70 p-4">
                <h2 class="font-semibold text-slate-950">How to read commission reports</h2>
                <div class="mt-3 grid grid-cols-1 gap-2 text-sm text-slate-700 md:grid-cols-3">
                    <div class="rounded-lg bg-white/80 px-3 py-2 ring-1 ring-sky-100">
                        <span class="font-semibold text-slate-900">1. Set filters</span>
                        <span class="block text-xs text-slate-500">Date, user, currency, and role control every number below.</span>
                    </div>
                    <div class="rounded-lg bg-white/80 px-3 py-2 ring-1 ring-sky-100">
                        <span class="font-semibold text-slate-900">2. Review totals</span>
                        <span class="block text-xs text-slate-500">Summary boxes and user cards update from the filtered data.</span>
                    </div>
                    <div class="rounded-lg bg-white/80 px-3 py-2 ring-1 ring-sky-100">
                        <span class="font-semibold text-slate-900">3. Send report</span>
                        <span class="block text-xs text-slate-500">Use admin email for all users, or select one user for their report.</span>
                    </div>
                </div>
            </section>

            <section class="rounded-lg border border-slate-200 bg-white overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <HelpLabel label="Monthly Report" tooltip="This report uses commission record dates, not the original sale date." text-class="font-semibold text-slate-900" />
                        <p class="text-sm text-slate-500">{{ periodText }}</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 w-full lg:w-auto">
                        <button
                            type="button"
                            class="listing-btn-outline w-full disabled:opacity-50"
                            title="Download one consolidated PDF using the current filters."
                            :disabled="!rows.length || downloadingFullPdf"
                            @click="downloadFullPdf"
                        >
                            {{ downloadingFullPdf ? 'Preparing...' : 'Download full PDF' }}
                        </button>
                        <button
                            type="button"
                            class="listing-btn-outline w-full disabled:opacity-50"
                            title="Download a PDF for the selected user. Select one person first."
                            :disabled="!canSendSelectedUser || downloadingUserPdf"
                            @click="downloadUserPdf"
                        >
                            {{ downloadingUserPdf ? 'Preparing...' : 'User PDF' }}
                        </button>
                        <button
                            type="button"
                            class="listing-btn-primary w-full disabled:opacity-50"
                            title="Email the filtered commission report to configured admin and manager recipients."
                            :disabled="!rows.length || sendingInternal"
                            @click="sendInternal"
                        >
                            {{ sendingInternal ? 'Sending...' : 'Email admins' }}
                        </button>
                    </div>
                </div>

                <div v-if="loading" class="p-6 text-sm text-slate-500">Loading commission report...</div>
                <div v-else-if="!rows.length" class="p-6 text-sm text-slate-500">No commission entries found for this filter.</div>
                <div v-else class="grid grid-cols-1 xl:grid-cols-[18rem_minmax(0,1fr)]">
                    <aside class="border-b xl:border-b-0 xl:border-r border-slate-100 bg-slate-50 p-3">
                        <div class="px-2 pb-2">
                            <HelpLabel label="People" tooltip="Select All users to inspect the full report, or select one person to download/email their individual PDF." text-class="text-xs font-semibold uppercase tracking-wide text-slate-500" />
                        </div>
                        <div class="space-y-2 max-h-80 xl:max-h-[44rem] overflow-y-auto">
                            <button
                                type="button"
                                class="w-full rounded-lg border px-3 py-3 text-left transition-colors"
                                :class="selectedUserId == null ? 'border-slate-900 bg-white shadow-sm' : 'border-transparent bg-white/70 hover:bg-white hover:border-slate-200'"
                                @click="selectUser(null)"
                            >
                                <span class="block font-semibold text-slate-900">All users</span>
                                <span class="mt-1 block text-xs text-slate-500">{{ rows.length }} entries</span>
                                <span class="mt-2 flex flex-wrap gap-1 text-xs text-slate-700">
                                    <span class="rounded bg-slate-100 px-2 py-1">{{ formatMoney('GBP', totals.GBP) }}</span>
                                    <span class="rounded bg-slate-100 px-2 py-1">{{ formatMoney('PKR', totals.PKR) }}</span>
                                </span>
                            </button>
                            <button
                                v-for="s in userSummaries"
                                :key="s.id"
                                type="button"
                                class="w-full rounded-lg border px-3 py-3 text-left transition-colors"
                                :class="selectedUserId === s.id ? 'border-slate-900 bg-white shadow-sm' : 'border-transparent bg-white/70 hover:bg-white hover:border-slate-200'"
                                @click="selectUser(s.id)"
                            >
                                <span class="block font-semibold text-slate-900 truncate">{{ s.name }}</span>
                                <span class="mt-1 block text-xs text-slate-500">{{ s.count }} entries</span>
                                <span class="mt-2 flex flex-wrap gap-1 text-xs text-slate-700">
                                    <span class="rounded bg-slate-100 px-2 py-1">{{ formatMoney('GBP', s.totals.GBP) }}</span>
                                    <span class="rounded bg-slate-100 px-2 py-1">{{ formatMoney('PKR', s.totals.PKR) }}</span>
                                </span>
                            </button>
                        </div>
                    </aside>

                    <div class="min-w-0">
                        <div class="px-4 py-3 border-b border-slate-100 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <HelpLabel :label="selectedUserName" tooltip="The table below shows commission entries for the selected person, or all users when All users is selected." text-class="font-semibold text-slate-900" />
                                <p class="text-sm text-slate-500">
                                    {{ displayRows.length }} commission {{ displayRows.length === 1 ? 'entry' : 'entries' }}
                                </p>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <button
                                    type="button"
                                    class="listing-btn-primary w-full disabled:opacity-50"
                                    title="Email the selected user's commission PDF to their saved profile email."
                                    :disabled="!canSendSelectedUser || sendingUser"
                                    @click="sendToUser"
                                >
                                    {{ sendingUser ? 'Sending...' : 'Email this user' }}
                                </button>
                                <p v-if="selectedUserId && !selectedUserEmail" class="text-sm text-amber-700 sm:self-center">
                                    User email missing.
                                </p>
                            </div>
                        </div>

                        <div class="hidden lg:block overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Date</th>
                                        <th class="px-4 py-3 text-left">Customer</th>
                                        <th class="px-4 py-3 text-left">Product</th>
                                        <th class="px-4 py-3 text-left">Role</th>
                                        <th class="px-4 py-3 text-left">Assigned by</th>
                                        <th class="px-4 py-3 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="r in displayRows" :key="r.id" class="hover:bg-slate-50/70">
                                        <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ formatDate(r.created_at) }}</td>
                                        <td class="px-4 py-3">{{ r.customer_name || '-' }}</td>
                                        <td class="px-4 py-3">{{ r.product_name || '-' }}</td>
                                        <td class="px-4 py-3">{{ humanRole(r.commission_role) }}</td>
                                        <td class="px-4 py-3">{{ r.assigned_by_user_name || '-' }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-slate-950">{{ formatMoney(r.commission_currency, r.commission_amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="lg:hidden divide-y divide-slate-100">
                            <article v-for="r in displayRows" :key="r.id" class="p-4 space-y-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-semibold text-slate-900 break-words">{{ r.customer_name || '-' }}</p>
                                        <p class="text-sm text-slate-600 break-words">{{ r.product_name || '-' }}</p>
                                    </div>
                                    <p class="font-semibold text-slate-950 whitespace-nowrap">{{ formatMoney(r.commission_currency, r.commission_amount) }}</p>
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
            </section>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { computed, defineComponent, h, reactive, ref, watch } from 'vue';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { useToastStore } from '@/stores/toast';

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
