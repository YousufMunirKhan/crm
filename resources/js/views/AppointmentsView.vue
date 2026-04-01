<template>
    <ListingPageShell
        title="My appointments"
        subtitle="Visits and meetings for the day you pick — open a card for full detail and status."
        :badge="appointmentsBadge"
    >
        <template #filters>
            <div class="listing-filters-row">
                <div>
                    <label class="listing-label">Date</label>
                    <input
                        v-model="selectedDate"
                        type="date"
                        class="listing-input w-full sm:w-44"
                        @change="loadAppointments"
                    />
                </div>
                <button
                    v-if="selectedDate !== todayStr"
                    type="button"
                    class="listing-btn-outline"
                    @click="resetDate"
                >
                    Today
                </button>
            </div>
        </template>

        <div v-if="loading" class="px-5 py-14 text-center text-slate-500 text-sm">Loading…</div>
        <div v-else-if="!appointments.length" class="px-5 py-12 text-center text-slate-500 text-sm">
            No appointments for this date.
        </div>
        <div v-else class="space-y-3 px-3 pb-3 sm:px-5 sm:pb-5">
            <div
                v-for="apt in appointments"
                :key="apt.id"
                class="rounded-xl border border-slate-200 bg-slate-50/40 overflow-hidden hover:border-slate-300 transition-colors"
            >
                <router-link :to="`/appointments/${apt.id}`" class="block p-4 sm:p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-slate-900">
                                {{ apt.customer?.name || 'Customer' }}
                            </div>
                            <div class="text-sm text-slate-600 mt-1">
                                {{ formatDate(apt.appointment_date) }} at {{ apt.appointment_time || '10:00' }}
                            </div>
                            <div class="text-xs text-slate-500 mt-1">
                                <span>Created by: {{ apt.user?.name || 'Unknown' }}</span>
                                <span v-if="apt.assignee?.name"> • Assigned to: {{ apt.assignee.name }}</span>
                            </div>
                            <div v-if="apt.description" class="text-sm text-slate-500 mt-1 line-clamp-2">
                                {{ apt.description }}
                            </div>
                            <span
                                :class="statusClass(apt.appointment_status)"
                                class="inline-block mt-2 px-2 py-0.5 rounded text-xs font-medium"
                            >
                                {{ statusLabel(apt.appointment_status) }}
                            </span>
                        </div>
                        <div class="text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
                        </div>
                    </div>
                </router-link>
            </div>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';

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

function statusClass(s) {
    const map = {
        pending: 'bg-amber-100 text-amber-800',
        completed: 'bg-green-100 text-green-800',
        cancelled: 'bg-slate-100 text-slate-600',
        no_show: 'bg-red-100 text-red-800',
        rescheduled: 'bg-blue-100 text-blue-800',
    };
    return map[s] || 'bg-slate-100 text-slate-600';
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
