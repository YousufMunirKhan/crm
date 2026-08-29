<template>
    <ListingPageShell
        title="My appointments"
        subtitle="Visits and meetings for the day you pick — open a card for full detail and status."
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
            </div>
        </template>

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

const loading = ref(true);
const appointments = ref([]);
const selectedDate = ref('');

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

async function loadAppointments() {
    const date = selectedDate.value || todayStr.value;
    loading.value = true;
    try {
        const res = await axios.get('/api/appointments', { params: { date } });
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
});
</script>
