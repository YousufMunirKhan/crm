<template>
    <div class="page">
        <p class="page-lead">
            Your own book and your own record. Nobody else sees a different version of these numbers.
        </p>

        <MyWork />

        <BaseCard title="What you have recorded" :subtitle="periodLabel">
            <template #actions>
                <div class="tab-list" role="group" aria-label="Period">
                    <button
                        v-for="range in ranges"
                        :key="range.key"
                        type="button"
                        :class="['tab', activeRange === range.key ? 'tab-active' : '']"
                        :aria-pressed="activeRange === range.key ? 'true' : 'false'"
                        @click="setRange(range.key)"
                    >
                        {{ range.label }}
                    </button>
                </div>
            </template>

            <p v-if="loading" class="py-8 text-center text-sm text-slate-500" role="status">Loading…</p>

            <div v-else class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div
                    v-for="card in activityCards"
                    :key="card.label"
                    class="rounded-card border border-slate-200 bg-white p-3 sm:p-4"
                >
                    <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">{{ card.label }}</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900 sm:text-3xl">{{ card.value }}</p>
                    <p class="mt-1 text-[11px] leading-snug text-slate-600">{{ card.help }}</p>
                </div>
            </div>
        </BaseCard>

        <BaseCard
            v-if="target"
            title="Your targets this month"
            subtitle="Set by your manager. Counted from appointments held and product lines won."
        >
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div v-for="row in targetRows" :key="row.label">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-sm font-medium text-slate-800">{{ row.label }}</span>
                        <span class="text-sm tabular-nums text-slate-600">{{ row.value }}</span>
                    </div>
                    <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                        <div class="h-full rounded-full bg-primary-600" :style="{ width: `${row.progress}%` }" />
                    </div>
                </div>
            </div>
        </BaseCard>

        <p v-else class="callout callout-info">
            No targets are set for you this month. Ask your manager if you think there should be.
        </p>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import MyWork from '@/components/MyWork.vue';
import { BaseCard } from '@/components/base';
import { useAuthStore } from '@/stores/auth';

/**
 * A salesperson's own report.
 *
 * What was here: a revenue tile, a revenue target with a progress bar, a rank,
 * and an "open pipeline" in pounds. Prices are deliberately never recorded, so
 * every one of those read £0 - a person opening their own report was shown a
 * page telling them their work was worth nothing, four times over.
 *
 * What replaces it is the part of their record that is real: what they have let
 * go quiet, what they promised and missed, and what they actually did in the
 * period. The book health at the top is the same component and the same service
 * the owner's dashboard uses, scoped to this person, so a rep and their manager
 * cannot end up quoting different numbers at each other.
 */
const auth = useAuthStore();

const loading = ref(true);
const agent = ref({});
const target = ref(null);
const activeRange = ref('this_month');

const ranges = [
    { key: 'this_week', label: 'This week' },
    { key: 'this_month', label: 'This month' },
    { key: 'last_month', label: 'Last month' },
];

const periodLabel = computed(() =>
    ranges.find((r) => r.key === activeRange.value)?.label ?? '');

const activityCards = computed(() => [
    {
        label: 'Contacts logged',
        value: agent.value.contacts ?? 0,
        help: 'Calls, visits, emails and messages you recorded',
    },
    {
        label: 'Appointments',
        value: agent.value.appointments_count ?? 0,
        help: 'Booked or handled in this period',
    },
    {
        label: 'Products won',
        value: agent.value.won_products ?? 0,
        help: 'Line items you closed as won',
    },
    {
        label: 'New leads',
        value: agent.value.leads_count ?? 0,
        help: 'Assigned to you in this period',
    },
]);

const targetRows = computed(() => {
    const t = target.value;

    if (! t) return [];

    return [
        {
            label: 'Appointments',
            value: `${t.achieved_appointments || 0} of ${t.target_appointments || 0}`,
            progress: pct(t.achieved_appointments, t.target_appointments),
        },
        {
            label: 'Sales',
            value: `${t.achieved_sales || 0} of ${t.target_sales || 0}`,
            progress: pct(t.achieved_sales, t.target_sales),
        },
    ];
});

function pct(done, of) {
    const total = Number(of || 0);

    return total > 0 ? Math.min(100, Math.round((Number(done || 0) / total) * 100)) : 0;
}

/** The date window for the chosen range, as the reporting API expects it. */
function rangeParams() {
    const now = new Date();
    const iso = (d) => d.toISOString().slice(0, 10);

    if (activeRange.value === 'this_week') {
        const start = new Date(now);
        // Monday, not Sunday - a UK working week.
        start.setDate(now.getDate() - ((now.getDay() + 6) % 7));

        return { from: iso(start), to: iso(now) };
    }

    if (activeRange.value === 'last_month') {
        const start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        const end = new Date(now.getFullYear(), now.getMonth(), 0);

        return { from: iso(start), to: iso(end) };
    }

    return { from: iso(new Date(now.getFullYear(), now.getMonth(), 1)), to: iso(now) };
}

async function load() {
    loading.value = true;

    const params = rangeParams();
    const month = params.from.slice(0, 7);

    try {
        const [agentsRes, targetsRes] = await Promise.all([
            axios.get('/api/reporting/agents', { params }),
            axios.get('/api/hr/employee-targets', { params: { month } }).catch(() => ({ data: { data: [] } })),
        ]);

        const rows = Array.isArray(agentsRes.data) ? agentsRes.data : (agentsRes.data?.data ?? []);

        // The endpoint already narrows a non-manager to their own row; the find
        // is for managers opening their own report.
        agent.value = rows.find((r) => String(r.id) === String(auth.user?.id)) ?? rows[0] ?? {};

        const targets = targetsRes.data?.data ?? [];
        const mine = targets.find((t) => String(t.user_id) === String(auth.user?.id));

        target.value = mine && (Number(mine.target_appointments) > 0 || Number(mine.target_sales) > 0)
            ? mine
            : null;
    } catch {
        agent.value = {};
        target.value = null;
    } finally {
        loading.value = false;
    }
}

function setRange(key) {
    activeRange.value = key;
    load();
}

onMounted(load);
</script>
