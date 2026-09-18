<template>
    <ListingPageShell
        title="Internal Email Management"
        subtitle="Every email the system has sent on its own — who it went to, whether it arrived, and whether it was opened."
        :badge="summaryBadge"
    >
        <template #filters>
            <div class="listing-filters-row">
                <div>
                    <label class="listing-label" for="internalemails-from">From</label>
                    <input id="internalemails-from" v-model="filters.from" type="date" class="form-input w-full sm:w-40" @change="reload" />
                </div>
                <div>
                    <label class="listing-label" for="internalemails-to">To</label>
                    <input id="internalemails-to" v-model="filters.to" type="date" class="form-input w-full sm:w-40" @change="reload" />
                </div>
                <div class="w-full sm:w-auto">
                    <label class="listing-label" for="internalemails-kind">Email</label>
                    <select id="internalemails-kind" v-model="filters.kind" class="form-select w-full sm:w-56" @change="reload">
                        <option value="">All</option>
                        <option v-for="k in kinds" :key="k.key" :value="k.key">{{ k.label }}</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <label class="listing-label" for="internalemails-status">Status</label>
                    <select id="internalemails-status" v-model="filters.status" class="form-select w-full sm:w-36" @change="reload">
                        <option value="">Any</option>
                        <option value="sent">Sent</option>
                        <option value="failed">Failed</option>
                        <option value="pending">Stuck</option>
                    </select>
                </div>
                <div class="w-full sm:w-auto">
                    <label class="listing-label" for="internalemails-opened">Opened</label>
                    <select id="internalemails-opened" v-model="filters.opened" class="form-select w-full sm:w-36" @change="reload">
                        <option value="">Any</option>
                        <option value="yes">Opened</option>
                        <option value="no">Not opened</option>
                    </select>
                </div>
                <div class="w-full sm:flex-1 sm:min-w-[200px]">
                    <label class="listing-label" for="internalemails-search">Search</label>
                    <input
                        id="internalemails-search"
                        v-model="filters.search"
                        type="search"
                        placeholder="Address or subject"
                        class="form-input w-full"
                        @keyup.enter="reload"
                    />
                </div>
                <BaseButton variant="outline" @click="resetFilters">Reset</BaseButton>
            </div>
        </template>

        <div class="px-3 pt-3 sm:px-5 sm:pt-5">
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div v-for="tile in tiles" :key="tile.label" class="rounded-card border p-3 sm:p-4" :class="tile.class">
                    <div class="text-[11px] font-medium uppercase tracking-wide" :class="tile.labelClass">{{ tile.label }}</div>
                    <div class="text-2xl font-bold leading-none tabular-nums sm:text-3xl mt-1" :class="tile.valueClass">{{ tile.value }}</div>
                    <div class="text-[11px] leading-snug text-slate-600 mt-1">{{ tile.caption }}</div>
                </div>
            </div>

            <!--
                Opens are what a mail client chose to tell us, not what a person
                did. Apple Mail fetches the image before anybody looks, so this
                reads high; a blank means nothing was reported, not that nobody read it.
            -->
            <p class="callout callout-info mt-3 text-xs">
                Opens are counted by a tracking image. Some clients block it and Apple Mail loads it automatically,
                so treat this as a floor rather than an exact figure.
            </p>
        </div>

        <div v-if="loading" class="space-y-3 px-3 py-3 sm:px-5 sm:py-5" aria-busy="true">
            <div v-for="n in 5" :key="`sk-${n}`" class="table-card">
                <span class="skeleton-text block w-1/3" />
                <span class="skeleton-text block w-2/3" />
            </div>
        </div>

        <EmptyState
            v-else-if="!rows.length"
            heading="Nothing sent in this range"
            description="Widen the dates, or clear the filters."
        >
            <template #icon><EnvelopeIcon class="icon" aria-hidden="true" /></template>
        </EmptyState>

        <div v-else class="overflow-x-auto min-w-0 px-3 pb-3 sm:px-5 sm:pb-5">
            <table class="w-full min-w-[820px]">
                <thead class="listing-thead">
                    <tr>
                        <th class="listing-th">Sent</th>
                        <th class="listing-th">Email</th>
                        <th class="listing-th">To</th>
                        <th class="listing-th">Subject</th>
                        <th class="listing-th">Status</th>
                        <th class="listing-th">Opened</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="row in rows" :key="row.id">
                        <tr class="listing-row cursor-pointer" @click="toggle(row.id)">
                            <td class="listing-td whitespace-nowrap">
                                <div class="text-slate-900">{{ formatDateTime(row.sent_at || row.created_at) }}</div>
                            </td>
                            <td class="listing-td">
                                <div class="text-slate-900">{{ row.kind }}</div>
                                <div v-if="row.customer" class="text-xs text-slate-500">{{ row.customer.name }}</div>
                            </td>
                            <td class="listing-td break-all">{{ row.recipient }}</td>
                            <td class="listing-td max-w-[280px] truncate" :title="row.subject">{{ row.subject }}</td>
                            <td class="listing-td">
                                <BaseBadge :tone="statusTone(row.status)">{{ statusLabel(row.status) }}</BaseBadge>
                            </td>
                            <td class="listing-td whitespace-nowrap">
                                <span v-if="row.opened_at" class="text-success-700 font-medium">
                                    {{ formatDateTime(row.opened_at) }}
                                    <span v-if="row.open_count > 1" class="text-slate-500">· {{ row.open_count }}×</span>
                                </span>
                                <span v-else class="text-slate-400">Not reported</span>
                            </td>
                        </tr>
                        <tr v-if="expanded === row.id">
                            <td colspan="6" class="bg-slate-50/70 px-4 py-3 text-sm">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div><span class="text-slate-500">Reference:</span> <span class="font-mono text-xs">{{ row.reference || '—' }}</span></div>
                                    <div><span class="text-slate-500">Recorded:</span> {{ formatDateTime(row.created_at) }}</div>
                                    <div class="sm:col-span-2"><span class="text-slate-500">Subject:</span> {{ row.subject }}</div>
                                    <div v-if="row.error" class="sm:col-span-2 callout callout-danger">
                                        {{ row.error }}
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>

            <div v-if="meta.last_page > 1" class="flex items-center justify-between gap-3 pt-4">
                <BaseButton variant="outline" :disabled="meta.current_page <= 1" @click="go(meta.current_page - 1)">
                    Previous
                </BaseButton>
                <span class="text-sm text-slate-600">Page {{ meta.current_page }} of {{ meta.last_page }}</span>
                <BaseButton variant="outline" :disabled="meta.current_page >= meta.last_page" @click="go(meta.current_page + 1)">
                    Next
                </BaseButton>
            </div>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import axios from 'axios';
import { EnvelopeIcon } from '@heroicons/vue/24/outline';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseBadge, BaseButton, EmptyState } from '@/components/base';

const loading = ref(true);
const rows = ref([]);
const kinds = ref([]);
const expanded = ref(null);
const summary = reactive({ total: 0, sent: 0, failed: 0, opened: 0 });
const meta = reactive({ current_page: 1, last_page: 1, per_page: 50, total: 0 });

const defaultFrom = () => {
    const d = new Date();
    d.setDate(d.getDate() - 30);
    return d.toISOString().slice(0, 10);
};

const filters = reactive({ from: defaultFrom(), to: '', kind: '', status: '', opened: '', search: '' });

const summaryBadge = computed(() => (!loading.value && summary.total ? `${summary.total} sends` : null));

const tiles = computed(() => [
    {
        label: 'Sends', value: summary.total, caption: 'in this range',
        class: 'border-slate-200 bg-white', labelClass: 'text-slate-500', valueClass: 'text-slate-900',
    },
    {
        label: 'Delivered', value: summary.sent, caption: 'accepted by the mail server',
        class: 'border-success-200 bg-success-50/60', labelClass: 'text-success-800', valueClass: 'text-success-700',
    },
    {
        label: 'Failed', value: summary.failed, caption: summary.failed ? 'open a row for the reason' : 'nothing refused',
        class: summary.failed ? 'border-danger-200 bg-danger-50/70' : 'border-slate-200 bg-white',
        labelClass: summary.failed ? 'text-danger-800' : 'text-slate-500',
        valueClass: summary.failed ? 'text-danger-700' : 'text-slate-900',
    },
    {
        label: 'Opened', value: summary.opened, caption: openedCaption.value,
        class: 'border-primary-200 bg-primary-50/60', labelClass: 'text-primary-800', valueClass: 'text-primary-700',
    },
]);

const openedCaption = computed(() => {
    if (!summary.sent) return 'nothing to open yet';
    return `${Math.round((summary.opened / summary.sent) * 100)}% of delivered`;
});

function statusLabel(status) {
    // "pending" means the row was written and the send never came back, which
    // is a stuck send rather than one waiting its turn.
    return { sent: 'Sent', failed: 'Failed', pending: 'Stuck' }[status] || status;
}

function statusTone(status) {
    return { sent: 'success', failed: 'danger', pending: 'warning' }[status] || 'neutral';
}

function formatDateTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('en-GB', {
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: 'Europe/London',
    });
}

function toggle(id) {
    expanded.value = expanded.value === id ? null : id;
}

function resetFilters() {
    Object.assign(filters, { from: defaultFrom(), to: '', kind: '', status: '', opened: '', search: '' });
    reload();
}

function reload() {
    load(1);
}

function go(page) {
    load(page);
}

async function load(page = 1) {
    loading.value = true;
    expanded.value = null;
    try {
        const params = { page, per_page: meta.per_page };
        for (const [key, value] of Object.entries(filters)) {
            if (value) params[key] = value;
        }

        const { data } = await axios.get('/api/internal-emails', { params });
        rows.value = data.data ?? [];
        kinds.value = data.kinds ?? [];
        Object.assign(summary, data.summary ?? {});
        Object.assign(meta, data.meta ?? {});
    } catch (e) {
        console.error('Failed to load internal emails:', e);
        rows.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(() => load(1));
</script>
