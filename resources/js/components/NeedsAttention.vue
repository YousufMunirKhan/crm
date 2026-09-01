<template>
    <section v-if="!loading && data" class="space-y-4">
        <!-- ── What is rotting ────────────────────────────────────────────── -->
        <div>
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-base font-semibold text-slate-900">Needs attention</h2>
                <p class="text-xs text-slate-500">
                    The business as it stands right now — not filtered by the dates above.
                </p>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <router-link
                    v-for="tile in tiles"
                    :key="tile.key"
                    :to="tile.to"
                    class="group flex min-h-[44px] flex-col gap-1 rounded-card border p-4 transition-colors
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                    :class="tile.value > 0
                        ? 'border-danger-200 bg-danger-50/70 hover:bg-danger-50'
                        : 'border-slate-200 bg-white hover:bg-slate-50'"
                >
                    <span class="text-xs font-medium uppercase tracking-wide"
                          :class="tile.value > 0 ? 'text-danger-800' : 'text-slate-500'">
                        {{ tile.label }}
                    </span>
                    <span class="text-3xl font-bold tabular-nums leading-none"
                          :class="tile.value > 0 ? 'text-danger-700' : 'text-slate-400'">
                        {{ tile.value }}
                    </span>
                    <span class="text-xs leading-snug text-slate-600">{{ tile.caption }}</span>
                </router-link>
            </div>
        </div>

        <!-- ── Who owns the neglect ───────────────────────────────────────── -->
        <div v-if="data.by_owner.length" class="rounded-card border border-slate-200 bg-white">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-800">Open book, by person</h3>
                <p class="text-xs text-slate-500">
                    Share of their own book gone quiet — not a league table of totals.
                </p>
            </div>

            <ul class="divide-y divide-slate-50">
                <li v-for="owner in data.by_owner" :key="owner.id" class="px-4 py-3">
                    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                        <span class="text-sm font-medium text-slate-900">{{ owner.name }}</span>
                        <span class="text-xs tabular-nums text-slate-600">
                            {{ owner.quiet }} of {{ owner.book }} untouched in 30 days
                            <span v-if="owner.book >= 10" class="ml-1 font-semibold" :class="pctTone(owner.quiet_pct)">
                                {{ owner.quiet_pct }}%
                            </span>
                            <!--
                                Below ten leads every percentage is 0 or 100 and
                                says nothing about the person. Showing the count
                                and withholding the rate is the honest output.
                            -->
                            <span v-else class="ml-1 text-slate-400">too few to rate</span>
                        </span>
                    </div>
                    <div v-if="owner.book >= 10" class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                        <div class="h-full rounded-full" :class="barTone(owner.quiet_pct)"
                             :style="{ width: `${owner.quiet_pct}%` }" />
                    </div>
                </li>
            </ul>
        </div>

        <!-- ── The worklist ───────────────────────────────────────────────── -->
        <div v-if="data.stalest.length" class="rounded-card border border-slate-200 bg-white">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-800">Longest without contact</h3>
                <router-link to="/leads?stale_days=30" class="text-xs font-medium text-primary-700 hover:underline">
                    See all {{ data.leads.quiet_30 }}
                </router-link>
            </div>

            <!-- A count tells you the shape of the problem; twenty names with a
                 number beside them is the thing somebody can act on. -->
            <ul class="divide-y divide-slate-50">
                <li
                    v-for="lead in data.stalest"
                    :key="lead.id"
                    class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="min-w-0">
                        <router-link
                            v-if="lead.customer_id"
                            :to="`/customers/${lead.customer_id}`"
                            class="link break-words text-sm font-medium"
                        >{{ lead.name || 'Customer' }}</router-link>
                        <span v-else class="text-sm font-medium text-slate-900">{{ lead.name || 'Customer' }}</span>

                        <p class="mt-0.5 text-xs text-slate-500">
                            <span class="font-medium text-danger-700 tabular-nums">{{ lead.days_since_contact }} days</span>
                            <span class="text-slate-400"> · </span>
                            <span>{{ lead.owner || 'nobody' }}</span>
                            <span class="text-slate-400"> · </span>
                            <span>{{ formatLeadStage(lead.stage) }}</span>
                        </p>

                        <div v-if="lead.phone" class="mt-1 flex items-center gap-1">
                            <a :href="`tel:${lead.phone}`" class="text-sm tabular-nums text-primary-700 hover:underline">
                                {{ lead.phone }}
                            </a>
                            <CopyButton :value="lead.phone" label="phone number" size="compact" />
                        </div>
                    </div>

                    <QuickLogActivity :lead-id="lead.id" compact class="shrink-0" @logged="refresh" />
                </li>
            </ul>
        </div>

        <!-- ── What is missing from the records ───────────────────────────── -->
        <div class="flex flex-wrap gap-x-4 gap-y-1 rounded-card border border-slate-200 bg-slate-50/70 px-4 py-3 text-xs text-slate-600">
            <span class="font-medium text-slate-700">Data quality</span>
            <router-link to="/customers" class="hover:underline">
                {{ data.data_quality.customers_without_email }} of
                {{ data.data_quality.customers_total }} customers have no email
            </router-link>
            <span>{{ data.data_quality.leads_without_source }} open leads with no source</span>
            <span>{{ data.tickets.resolved_not_closed }} tickets resolved but never closed</span>
            <span v-if="data.data_quality.losses_without_reason">
                {{ data.data_quality.losses_without_reason }} losses with no recorded reason
            </span>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import CopyButton from '@/components/CopyButton.vue';
import QuickLogActivity from '@/components/QuickLogActivity.vue';
import { formatLeadStage } from '@/utils/displayFormat';

/**
 * The owner's first question, answered before anything else on the page.
 *
 * The dashboard under this was built entirely out of date ranges - leads this
 * week, won this week, win rate this week - so a quiet week rendered a screen
 * of zeroes and taught nobody anything. Two of those figures could never work
 * here at all: prices are deliberately never recorded, so revenue is £0 by
 * design, and 391 leads are marked won against 3 lost, which measures how
 * people file rather than how they sell.
 *
 * What is honest in this data is timestamps and ownership. So the question this
 * answers is not "how did we do last week" but "what is rotting, and who owns
 * it" - and every number on it is a link to the list behind it, because a
 * figure an owner cannot act on is decoration.
 */
const loading = ref(true);
const data = ref(null);

const tiles = computed(() => {
    if (! data.value) return [];

    const { leads, follow_ups: followUps, tickets, appointments } = data.value;

    return [
        {
            key: 'quiet',
            label: 'Leads gone quiet',
            value: leads.quiet_30,
            caption: `${leads.never_contacted} of them have never been contacted at all.`,
            to: '/leads?stale_days=30',
        },
        {
            key: 'overdue',
            label: 'Overdue follow-ups',
            value: followUps.overdue,
            caption: followUps.overdue
                ? `Dates our own people set. The oldest passed ${followUps.oldest_days} days ago.`
                : 'Every promised date is still ahead of us.',
            to: '/follow-ups?overdue=1',
        },
        {
            key: 'unowned',
            label: 'Nobody assigned',
            value: leads.unassigned + tickets.unassigned,
            caption: `${leads.unassigned} leads and ${tickets.unassigned} open tickets have no owner.`,
            to: '/leads?assigned_to=unassigned',
        },
        {
            key: 'tickets',
            label: 'Tickets ageing',
            value: tickets.over_7_days,
            caption: `Of ${tickets.open} open, ${tickets.over_90_days} are past 90 days and ${tickets.sla_breached} have breached SLA.`,
            to: '/tickets',
        },
        {
            key: 'appointments',
            label: 'Appointments unclosed',
            value: appointments.awaiting_outcome,
            caption: 'Past their date with nobody saying whether they happened.',
            to: '/appointments',
        },
    ].filter((t) => t.key !== 'appointments' || t.value > 0);
});

/** Red once most of a book has gone quiet; amber before that. */
function pctTone(pct) {
    if (pct >= 75) return 'text-danger-700';
    if (pct >= 40) return 'text-warning-800';

    return 'text-slate-600';
}

function barTone(pct) {
    if (pct >= 75) return 'bg-danger-500';
    if (pct >= 40) return 'bg-warning-500';

    return 'bg-success-600';
}

async function refresh() {
    try {
        const response = await axios.get('/api/dashboard/attention');
        data.value = response.data;
    } catch {
        data.value = null;
    } finally {
        loading.value = false;
    }
}

onMounted(refresh);
</script>
