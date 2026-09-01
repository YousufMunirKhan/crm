<template>
    <section v-if="!loading && data" class="space-y-4">
        <div>
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-base font-semibold text-slate-900">Your book</h2>
                <p class="text-xs text-slate-500">Where things stand today, not filtered by any date.</p>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <router-link
                    v-for="tile in tiles"
                    :key="tile.key"
                    :to="tile.to"
                    class="flex min-h-[44px] flex-col gap-1 rounded-card border p-3 transition-colors sm:p-4
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                    :class="tile.value > 0
                        ? 'border-danger-200 bg-danger-50/70 hover:bg-danger-50'
                        : 'border-success-200 bg-success-50/60 hover:bg-success-50'"
                >
                    <span class="text-[11px] font-medium uppercase tracking-wide"
                          :class="tile.value > 0 ? 'text-danger-800' : 'text-success-800'">
                        {{ tile.label }}
                    </span>
                    <span class="text-2xl font-bold leading-none tabular-nums sm:text-3xl"
                          :class="tile.value > 0 ? 'text-danger-700' : 'text-success-700'">
                        {{ tile.value }}
                    </span>
                    <span class="text-[11px] leading-snug text-slate-600">{{ tile.caption }}</span>
                </router-link>
            </div>
        </div>

        <!--
            The worklist, not just the count. A rep who is told "69 of your
            leads have gone quiet" and given no list has been handed a
            complaint; the same rep given five names and a phone number has been
            handed a morning's work.
        -->
        <div v-if="data.stalest.length" class="rounded-card border border-slate-200 bg-white">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 px-4 py-3">
                <h3 class="text-sm font-semibold text-slate-800">Ring these first</h3>
                <router-link to="/leads?stale_days=30" class="text-xs font-medium text-primary-700 hover:underline">
                    See all {{ data.leads.quiet_30 }}
                </router-link>
            </div>

            <ul class="divide-y divide-slate-50">
                <li
                    v-for="lead in data.stalest.slice(0, 5)"
                    :key="lead.id"
                    class="flex flex-col gap-2 px-4 py-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <div class="min-w-0">
                        <router-link
                            v-if="lead.customer_id"
                            :to="`/customers/${lead.customer_id}`"
                            class="link break-words text-sm font-medium"
                        >{{ lead.name || 'Customer' }}</router-link>
                        <span v-else class="text-sm font-medium text-slate-900">{{ lead.name || 'Customer' }}</span>

                        <p class="mt-0.5 text-xs text-slate-500">
                            <span class="font-medium tabular-nums text-danger-700">{{ lead.days_since_contact }} days</span>
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

        <p v-else class="callout callout-success">
            Nothing in your book has gone quiet. That is the good version of this screen.
        </p>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import CopyButton from '@/components/CopyButton.vue';
import QuickLogActivity from '@/components/QuickLogActivity.vue';
import { formatLeadStage } from '@/utils/displayFormat';

/**
 * A salesperson's own version of the owner's "needs attention".
 *
 * Their dashboard opened on counts of leads added this week and this year -
 * numbers nobody acts on - while the things they could actually fix that
 * morning were on other screens or on none. Of 177 open leads company-wide, 169
 * have had no contact in a month and 155 have never been contacted at all;
 * those belong to somebody, and that somebody is who this is for.
 *
 * It reads from the same service as the owner's band, scoped to this person, so
 * a rep and their manager can never be looking at two different answers to the
 * same question.
 */
const loading = ref(true);
const data = ref(null);

const tiles = computed(() => {
    if (! data.value) return [];

    const { leads, follow_ups: followUps, appointments, tickets } = data.value;

    return [
        {
            key: 'quiet',
            label: 'Gone quiet',
            value: leads.quiet_30,
            caption: `of your ${leads.open} open leads, no contact in 30 days`,
            to: '/leads?stale_days=30',
        },
        {
            key: 'overdue',
            label: 'Overdue',
            value: followUps.overdue,
            caption: followUps.overdue
                ? `oldest passed ${followUps.oldest_days} days ago`
                : 'nothing you promised has slipped',
            to: '/followups?overdue=1',
        },
        {
            key: 'appointments',
            label: 'To close out',
            value: appointments.awaiting_outcome,
            caption: 'appointments with no outcome recorded',
            to: '/appointments',
        },
        {
            key: 'tickets',
            label: 'Your tickets',
            value: tickets.open,
            caption: tickets.over_7_days
                ? `${tickets.over_7_days} older than a week`
                : 'nothing older than a week',
            to: '/tickets',
        },
    ];
});

async function refresh() {
    try {
        const response = await axios.get('/api/dashboard/my-work');
        data.value = response.data;
    } catch {
        data.value = null;
    } finally {
        loading.value = false;
    }
}

onMounted(refresh);
</script>
