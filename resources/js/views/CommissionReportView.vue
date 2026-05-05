<template>
    <ListingPageShell title="Commission Report" :subtitle="subtitle" :badge="badgeText">
        <template #filters>
            <div class="space-y-4">
                <div class="flex flex-wrap gap-2 border-b border-slate-200 pb-3">
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                        :class="mainTab === 'user' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        @click="mainTab = 'user'"
                    >
                        By user
                    </button>
                    <button
                        type="button"
                        class="px-4 py-2 text-sm font-medium rounded-lg transition-colors"
                        :class="mainTab === 'internal' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'"
                        @click="mainTab = 'internal'"
                    >
                        Internal
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">From</label>
                        <input v-model="filters.from" type="date" class="listing-input" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">To</label>
                        <input v-model="filters.to" type="date" class="listing-input" />
                    </div>
                    <div v-if="mainTab === 'internal'">
                        <label class="block text-xs font-medium text-slate-600 mb-1">User (optional)</label>
                        <select v-model="filters.internal_user_id" class="listing-input">
                            <option value="">All users in range</option>
                            <option v-for="u in users" :key="'i-' + u.id" :value="String(u.id)">{{ u.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Currency</label>
                        <select v-model="filters.currency" class="listing-input">
                            <option value="">All</option>
                            <option value="GBP">£ GBP</option>
                            <option value="PKR">PKR</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Role</label>
                        <select v-model="filters.commission_role" class="listing-input">
                            <option value="">All</option>
                            <option value="single_owner">Single Owner</option>
                            <option value="appointment_creator">Appointment Creator</option>
                            <option value="closer">Closer</option>
                        </select>
                    </div>
                    <button type="button" class="listing-btn-primary" @click="loadReport">Apply</button>
                </div>
            </div>
        </template>

        <div class="p-4 sm:p-6">
            <div v-if="loading" class="text-sm text-slate-500">Loading commission report...</div>

            <template v-else-if="mainTab === 'user'">
                <div v-if="!rows.length" class="text-sm text-slate-500">No commission entries found for this range.</div>
                <div v-else class="flex flex-col lg:flex-row gap-4 min-h-[320px]">
                    <aside class="lg:w-56 shrink-0 rounded-lg border border-slate-200 bg-slate-50 p-2 max-h-80 lg:max-h-none overflow-y-auto">
                        <p class="text-xs font-medium text-slate-500 px-2 py-1">Users with commission</p>
                        <button
                            v-for="s in userSummaries"
                            :key="s.id"
                            type="button"
                            class="w-full text-left px-3 py-2 rounded-md text-sm mb-1 transition-colors"
                            :class="
                                selectedUserId === s.id
                                    ? 'bg-slate-900 text-white'
                                    : 'text-slate-800 hover:bg-white border border-transparent hover:border-slate-200'
                            "
                            @click="selectedUserId = s.id"
                        >
                            <span class="font-medium block truncate">{{ s.name }}</span>
                            <span class="text-xs opacity-80">{{ formatMoney('GBP', s.totals.GBP) }} · PKR {{ formatAmountNumber(s.totals.PKR) }}</span>
                        </button>
                    </aside>
                    <div class="flex-1 min-w-0 space-y-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap gap-2 text-sm">
                                <span class="px-2 py-1 rounded bg-slate-100 text-slate-700">{{ formatMoney('GBP', displayTotals.GBP) }} total</span>
                                <span class="px-2 py-1 rounded bg-slate-100 text-slate-700">PKR {{ formatAmountNumber(displayTotals.PKR) }} total</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="px-4 py-2 text-sm font-medium rounded-lg border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 disabled:opacity-50"
                                    :disabled="!selectedUserId || downloadingUserPdf"
                                    @click="downloadUserPdf"
                                >
                                    {{ downloadingUserPdf ? 'Preparing…' : 'Download PDF' }}
                                </button>
                                <button
                                    type="button"
                                    class="listing-btn-primary"
                                    :disabled="!selectedUserId || sendingUser || !selectedUserEmail"
                                    @click="sendToUser"
                                >
                                    {{ sendingUser ? 'Sending…' : 'Email report to this user' }}
                                </button>
                            </div>
                        </div>
                        <p v-if="selectedUserId && !selectedUserEmail" class="text-sm text-amber-700">
                            This user has no valid email — add an email on their profile to send the report.
                        </p>
                        <div class="overflow-x-auto rounded-lg border border-slate-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-slate-600">
                                    <tr>
                                        <th class="px-4 py-2 text-left">Date</th>
                                        <th class="px-4 py-2 text-left">Customer</th>
                                        <th class="px-4 py-2 text-left">Product</th>
                                        <th class="px-4 py-2 text-left">Role</th>
                                        <th class="px-4 py-2 text-right">Amount</th>
                                        <th class="px-4 py-2 text-left">Assigned by</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="r in displayRows" :key="r.id">
                                        <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ formatDate(r.created_at) }}</td>
                                        <td class="px-4 py-2">{{ r.customer_name || '-' }}</td>
                                        <td class="px-4 py-2">{{ r.product_name || '-' }}</td>
                                        <td class="px-4 py-2">{{ humanRole(r.commission_role) }}</td>
                                        <td class="px-4 py-2 text-right font-medium">{{ formatMoney(r.commission_currency, r.commission_amount) }}</td>
                                        <td class="px-4 py-2">{{ r.assigned_by_user_name || '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>

            <template v-else>
                <div v-if="!rows.length" class="text-sm text-slate-500">No commission entries found for this range.</div>
                <div v-else class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p class="text-sm text-slate-600">
                            Summary for admins and managers (same filters). The email includes a consolidated PDF for the period.
                        </p>
                        <div class="flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="px-4 py-2 text-sm font-medium rounded-lg border border-slate-300 bg-white text-slate-800 hover:bg-slate-50 disabled:opacity-50"
                                :disabled="downloadingFullPdf"
                                @click="downloadFullPdf"
                            >
                                {{ downloadingFullPdf ? 'Preparing…' : 'Download PDF' }}
                            </button>
                            <button type="button" class="listing-btn-primary" :disabled="sendingInternal" @click="sendInternal">
                                {{ sendingInternal ? 'Sending…' : 'Email internal report to admins' }}
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 text-sm mb-2">
                        <span class="px-2 py-1 rounded bg-slate-100 text-slate-700">{{ formatMoney('GBP', totals.GBP) }} org total</span>
                        <span class="px-2 py-1 rounded bg-slate-100 text-slate-700">PKR {{ formatAmountNumber(totals.PKR) }} org total</span>
                    </div>
                    <div v-for="block in internalBlocks" :key="block.id" class="rounded-lg border border-slate-200 overflow-hidden mb-4">
                        <div class="px-4 py-2 bg-slate-50 border-b border-slate-100 font-semibold text-slate-900">{{ block.name }}</div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-white text-slate-600">
                                    <tr>
                                        <th class="px-4 py-2 text-left">Date</th>
                                        <th class="px-4 py-2 text-left">Customer</th>
                                        <th class="px-4 py-2 text-left">Product</th>
                                        <th class="px-4 py-2 text-left">Role</th>
                                        <th class="px-4 py-2 text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="r in block.rows" :key="r.id">
                                        <td class="px-4 py-2 whitespace-nowrap text-slate-600">{{ formatDate(r.created_at) }}</td>
                                        <td class="px-4 py-2">{{ r.customer_name || '-' }}</td>
                                        <td class="px-4 py-2">{{ r.product_name || '-' }}</td>
                                        <td class="px-4 py-2">{{ humanRole(r.commission_role) }}</td>
                                        <td class="px-4 py-2 text-right font-medium">{{ formatMoney(r.commission_currency, r.commission_amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();
const loading = ref(false);
const rows = ref([]);
const users = ref([]);
const mainTab = ref('user');
const selectedUserId = ref(null);
const sendingUser = ref(false);
const sendingInternal = ref(false);
const downloadingUserPdf = ref(false);
const downloadingFullPdf = ref(false);

function toYmd(d) {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

const now = new Date();
const defaults = {
    from: toYmd(new Date(now.getFullYear(), now.getMonth() - 2, now.getDate())),
    to: toYmd(now),
};

const filters = reactive({
    from: defaults.from,
    to: defaults.to,
    currency: '',
    commission_role: '',
    internal_user_id: '',
});

const subtitle = 'Filter by period, review commission user-wise, and email PDF summaries — or send the internal admin report.';

const badgeText = computed(() => {
    if (loading.value) return '…';
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

const internalRows = computed(() => {
    if (!filters.internal_user_id) {
        return rows.value;
    }
    const id = Number(filters.internal_user_id);
    return rows.value.filter((r) => Number(r.credited_user_id) === id);
});

const internalBlocks = computed(() => {
    const map = new Map();
    for (const r of internalRows.value) {
        const id = r.credited_user_id;
        if (!map.has(id)) {
            map.set(id, { id, name: r.credited_user_name || `User #${id}`, rows: [] });
        }
        map.get(id).rows.push(r);
    }
    const list = Array.from(map.values());
    list.sort((a, b) => String(a.name).localeCompare(String(b.name)));
    return list;
});

const userSummaries = computed(() => {
    const map = new Map();
    for (const r of rows.value) {
        const id = r.credited_user_id;
        if (!map.has(id)) {
            map.set(id, { id, name: r.credited_user_name || `User #${id}`, totals: { GBP: 0, PKR: 0 } });
        }
        const s = map.get(id);
        const c = r.commission_currency;
        if (c === 'GBP' || c === 'PKR') {
            s.totals[c] += Number(r.commission_amount || 0);
        }
    }
    const list = Array.from(map.values());
    list.sort((a, b) => String(a.name).localeCompare(String(b.name)));
    return list;
});

const displayRows = computed(() => {
    if (selectedUserId.value == null || selectedUserId.value === '') return [];
    const sid = Number(selectedUserId.value);
    return rows.value.filter((r) => Number(r.credited_user_id) === sid);
});

const displayTotals = computed(() => {
    const t = { GBP: 0, PKR: 0 };
    for (const r of displayRows.value) {
        const c = r.commission_currency;
        if (c === 'GBP' || c === 'PKR') {
            t[c] += Number(r.commission_amount || 0);
        }
    }
    return t;
});

const selectedUserEmail = computed(() => {
    if (selectedUserId.value == null || selectedUserId.value === '') return '';
    const sid = Number(selectedUserId.value);
    const u = users.value.find((x) => Number(x.id) === sid);
    const e = u?.email;
    if (typeof e === 'string' && e.includes('@')) return e.trim();
    return '';
});

watch(
    [userSummaries, mainTab],
    () => {
        if (mainTab.value !== 'user') return;
        const list = userSummaries.value;
        if (!list.length) {
            selectedUserId.value = null;
            return;
        }
        const cur = selectedUserId.value == null ? null : Number(selectedUserId.value);
        if (cur == null || !list.some((s) => Number(s.id) === cur)) {
            selectedUserId.value = list[0].id;
        }
    },
    { immediate: true },
);

function formatAmountNumber(value) {
    return new Intl.NumberFormat('en-GB', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));
}

function formatMoney(currency, amount) {
    const n = formatAmountNumber(amount);
    if (currency === 'GBP') {
        return `£${n}`;
    }
    if (currency === 'PKR') {
        return `PKR ${n}`;
    }
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
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to load commission report.');
    } finally {
        loading.value = false;
    }
}

async function sendToUser() {
    if (!selectedUserId.value || !filters.from || !filters.to) return;
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
    const p = {
        from: filters.from,
        to: filters.to,
        ...(filters.currency ? { currency: filters.currency } : {}),
        ...(filters.commission_role ? { commission_role: filters.commission_role } : {}),
    };
    if (filters.internal_user_id) {
        p.credited_user_id = Number(filters.internal_user_id);
    }
    return p;
}

/**
 * @param {string} url
 * @param {Record<string, unknown>} params
 * @param {string} fallbackName
 */
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
        const dispo = res.headers['content-disposition'] || '';
        let filename = fallbackName;
        const m = /filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/.exec(dispo);
        if (m && m[1]) {
            filename = String(m[1]).replace(/['"]/g, '').trim();
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
    if (!selectedUserId.value || !filters.from || !filters.to) return;
    downloadingUserPdf.value = true;
    try {
        await downloadPdfGet(
            '/api/commission-management/report/pdf/user',
            pdfDownloadParamsForUser(),
            'commission_summary.pdf',
        );
    } finally {
        downloadingUserPdf.value = false;
    }
}

async function downloadFullPdf() {
    if (!filters.from || !filters.to) return;
    downloadingFullPdf.value = true;
    try {
        await downloadPdfGet(
            '/api/commission-management/report/pdf/full',
            pdfDownloadParamsForFull(),
            'commission_report.pdf',
        );
    } finally {
        downloadingFullPdf.value = false;
    }
}

async function sendInternal() {
    if (!filters.from || !filters.to) return;
    sendingInternal.value = true;
    try {
        const body = {
            from: filters.from,
            to: filters.to,
            currency: filters.currency || undefined,
            commission_role: filters.commission_role || undefined,
        };
        if (filters.internal_user_id) {
            body.credited_user_id = Number(filters.internal_user_id);
        }
        const { data } = await axios.post('/api/commission-management/report/send-internal', body);
        toast.success(data?.message || 'Internal report sent.');
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Failed to send internal report.');
    } finally {
        sendingInternal.value = false;
    }
}

async function bootstrap() {
    await Promise.all([loadUsers(), loadReport()]);
}

bootstrap();
</script>
