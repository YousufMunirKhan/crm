<template>
    <ListingPageShell
        :title="scopeIsEveryone ? 'Appointments' : 'My appointments'"
        :subtitle="scopeIsEveryone
            ? 'Visits and meetings across the team for the day you pick.'
            : 'Visits and meetings for the day you pick — open a card for full detail and status.'"
        :badge="appointmentsBadge"
    >
        <template #filters>
            <div class="listing-filters-row">
                <div>
                    <label class="listing-label" for="appointmentsview-date">Date</label>
                    <input id="appointmentsview-date"
                        v-model="selectedDate"
                        type="date"
                        class="form-input w-full sm:w-44"
                        @change="loadAppointments"
                    />
                </div>
                <BaseButton v-if="selectedDate !== todayStr" variant="outline" @click="resetDate">
                    Today
                </BaseButton>
                <div v-if="canSeeEveryone" class="w-full sm:w-auto">
                    <label class="listing-label" for="appointmentsview-scope">Whose</label>
                    <select id="appointmentsview-scope" v-model="scope" class="form-select w-full sm:w-44" @change="reload">
                        <option value="all">Everyone</option>
                        <option value="mine">Just mine</option>
                    </select>
                </div>
            </div>
        </template>

        <!--
            Appointments whose date has passed with nobody saying what happened.
            They sit above the day's list because this screen shows one date at a
            time, so they are otherwise only reachable by guessing the date.
        -->
        <div v-if="awaiting.length" class="px-3 pt-3 pb-1 sm:px-5 sm:pt-5">
            <div class="rounded-card border border-warning-200 bg-warning-50/60 p-3 sm:p-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <h2 class="text-sm font-semibold text-warning-900">
                        {{ awaiting.length }} {{ awaiting.length === 1 ? 'appointment needs' : 'appointments need' }} an outcome
                    </h2>
                    <p class="text-xs text-warning-800">The date has passed and nobody said what happened.</p>
                </div>

                <ul class="mt-3 space-y-2">
                    <li
                        v-for="apt in awaiting"
                        :key="apt.id"
                        class="rounded-control border border-warning-200 bg-white p-3"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="min-w-0">
                                <router-link
                                    :to="`/appointments/${apt.id}`"
                                    class="block text-sm font-semibold text-slate-900 hover:underline truncate"
                                >
                                    {{ apt.business_name || apt.customer?.business_name || apt.customer?.name || 'Customer' }}
                                </router-link>
                                <p class="text-xs text-slate-600 mt-0.5">
                                    {{ formatDate(apt.appointment_date) }} at {{ apt.appointment_time || '10:00' }}
                                    <span class="text-slate-400">·</span> {{ daysAgo(apt.appointment_date) }}
                                    <template v-if="scopeIsEveryone && apt.owner">
                                        <span class="text-slate-400">·</span> {{ apt.owner }}
                                    </template>
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-1.5">
                                <button
                                    v-for="choice in outcomeChoices"
                                    :key="choice.value"
                                    type="button"
                                    class="inline-flex items-center rounded-control border px-2.5 text-xs font-medium
                                           h-11 sm:h-8 touch-manipulation transition-colors
                                           disabled:opacity-50 disabled:pointer-events-none
                                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                    :class="choice.class"
                                    :disabled="saving === apt.id"
                                    @click="setOutcome(apt, choice.value)"
                                >
                                    {{ choice.label }}
                                </button>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <div v-if="loading" class="space-y-3 px-3 pb-3 sm:px-5 sm:pb-5" aria-busy="true">
            <div v-for="n in 4" :key="`sk-${n}`" class="table-card">
                <span class="skeleton-text block w-1/2" />
                <span class="skeleton-text block w-3/4" />
            </div>
        </div>
        <EmptyState
            v-else-if="!appointments.length"
            heading="No appointments for this date"
            description="Pick another date above, or jump back to today."
        >
            <template #icon><CalendarDaysIcon class="icon" aria-hidden="true" /></template>
        </EmptyState>
        <div v-else class="space-y-3 px-3 pb-3 sm:px-5 sm:pb-5">
            <div
                v-for="apt in appointments"
                :key="apt.id"
                class="rounded-card border border-slate-200 bg-slate-50/40 overflow-hidden hover:border-slate-300 transition-colors"
            >
                <router-link :to="`/appointments/${apt.id}`" class="block p-4 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-slate-900">
                                {{ apt.business_name || apt.customer?.business_name || apt.customer?.name || 'Customer' }}
                            </div>
                            <div v-if="apt.business_name || apt.customer?.business_name" class="text-sm text-slate-600 mt-0.5">
                                Contact: {{ apt.customer?.name || 'N/A' }}
                            </div>
                            <div class="text-sm text-slate-600 mt-1">
                                {{ formatDate(apt.appointment_date) }} at {{ apt.appointment_time || '10:00' }}
                            </div>
                            <div v-if="apt.products_to_sell?.length" class="flex flex-wrap gap-1.5 mt-2">
                                <span
                                    v-for="product in apt.products_to_sell"
                                    :key="product"
                                    class="px-2 py-0.5 rounded bg-primary-50 text-primary-700 border border-primary-100 text-xs font-medium"
                                >
                                    {{ product }}
                                </span>
                            </div>
                            <div v-else class="text-xs text-warning-800 mt-2">
                                No product selected on this lead.
                            </div>
                            <div class="text-xs text-slate-500 mt-2">
                                Lead #{{ apt.lead_id }}
                                <span v-if="apt.lead?.stage"> - {{ formatStage(apt.lead.stage) }}</span>
                                <span v-if="apt.lead?.source"> - {{ apt.lead.source }}</span>
                            </div>
                            <div class="text-xs text-slate-500 mt-1">
                                <span>Created by: {{ apt.user?.name || 'Unknown' }}</span>
                                <span v-if="apt.assignee?.name"> • Assigned to: {{ apt.assignee.name }}</span>
                            </div>
                            <div v-if="apt.description" class="text-sm text-slate-500 mt-1 line-clamp-2">
                                {{ apt.description }}
                            </div>
                            <BaseBadge :tone="statusTone(apt.appointment_status)" class="mt-2">
                                {{ statusLabel(apt.appointment_status) }}
                            </BaseBadge>
                        </div>
                        <ChevronRightIcon class="icon text-slate-500" aria-hidden="true" />
                    </div>
                </router-link>
            </div>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { CalendarDaysIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseBadge, BaseButton, EmptyState } from '@/components/base';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';

const toast = useToastStore();

const loading = ref(true);
const appointments = ref([]);
const selectedDate = ref('');

/**
 * Past appointments still marked pending.
 *
 * 35 of 39 appointments in the system sit at "pending" forever, which makes
 * held-rate and no-show-rate impossible to measure and leaves the calendar
 * saying things are still ahead when they happened weeks ago. Closing one is a
 * single tap here rather than a page, a form and a save.
 */
const awaiting = ref([]);
const saving = ref(null);

const auth = useAuthStore();

/** Admins and managers run the diary for the team, not only for themselves. */
const canSeeEveryone = computed(() =>
    ['Admin', 'Manager', 'System Admin'].includes(auth.user?.role?.name));

const scope = ref('all');
const scopeIsEveryone = computed(() => canSeeEveryone.value && scope.value === 'all');

function reload() {
    loadAppointments();
    loadAwaiting();
}

const outcomeChoices = [
    { value: 'completed', label: 'It happened', class: 'border-success-200 bg-success-50 text-success-800 hover:bg-success-100' },
    { value: 'no_show', label: 'No show', class: 'border-danger-200 bg-danger-50 text-danger-800 hover:bg-danger-100' },
    { value: 'cancelled', label: 'Cancelled', class: 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' },
];

const todayStr = computed(() => {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
});

const appointmentsBadge = computed(() =>
    !loading.value && appointments.value.length ? `${appointments.value.length} this day` : null,
);

function resetDate() {
    selectedDate.value = todayStr.value;
    loadAppointments();
}

function formatDate(ymd) {
    if (!ymd) return '—';
    const [y, m, d] = ymd.split('-');
    return `${d}/${m}/${y}`;
}

function statusLabel(s) {
    const map = { pending: 'Pending', completed: 'Completed', cancelled: 'Cancelled', no_show: 'No show', rescheduled: 'Rescheduled' };
    return map[s] || s;
}

function statusTone(s) {
    const map = {
        pending: 'warning',
        completed: 'success',
        cancelled: 'neutral',
        no_show: 'danger',
        rescheduled: 'primary',
    };
    return map[s] || 'neutral';
}

function formatStage(stage) {
    return String(stage || '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

function daysAgo(ymd) {
    if (!ymd) return '';
    const then = new Date(`${ymd}T00:00:00`);
    const days = Math.round((new Date(new Date().toDateString()) - then) / 86400000);
    if (days <= 0) return 'today';
    return days === 1 ? 'yesterday' : `${days} days ago`;
}

async function loadAwaiting() {
    try {
        const res = await axios.get('/api/appointments', {
            params: { needs_outcome: 1, ...(scopeIsEveryone.value ? {} : { mine: 1 }) },
        });
        awaiting.value = res.data ?? [];
    } catch {
        awaiting.value = [];
    }
}

async function setOutcome(apt, status) {
    if (saving.value) return;
    saving.value = apt.id;

    try {
        await axios.put(`/api/appointments/${apt.id}`, { appointment_status: status });
        awaiting.value = awaiting.value.filter((a) => a.id !== apt.id);
        toast.success('Saved. Thanks - that is what the held-rate is built from.');
        await loadAppointments();
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not save that.');
    } finally {
        saving.value = null;
    }
}

async function loadAppointments() {
    const date = selectedDate.value || todayStr.value;
    loading.value = true;
    try {
        const res = await axios.get('/api/appointments', {
            params: { date, ...(scopeIsEveryone.value ? {} : { mine: 1 }) },
        });
        appointments.value = res.data ?? [];
    } catch (e) {
        console.error(e);
        appointments.value = [];
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    selectedDate.value = todayStr.value;
    loadAppointments();
    loadAwaiting();
});
</script>
