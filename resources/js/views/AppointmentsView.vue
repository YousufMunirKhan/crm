<template>
    <div class="max-w-4xl mx-auto p-4 sm:p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">My Appointments</h1>
            <div class="flex items-center gap-3">
                <label class="text-sm font-medium text-slate-700">Date</label>
                <input
                    v-model="selectedDate"
                    type="date"
                    @change="loadAppointments"
                    class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                <button
                    v-if="selectedDate !== todayStr"
                    @click="resetDate"
                    class="px-3 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg"
                >
                    Today
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-12 text-slate-500">Loading...</div>
        <div v-else-if="!appointments.length" class="bg-white rounded-xl shadow-sm p-8 text-center text-slate-500">
            No appointments for this date.
        </div>
        <div v-else class="space-y-3">
            <div
                v-for="apt in appointments"
                :key="apt.id"
                class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden hover:border-blue-300 transition-colors"
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
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const loading = ref(true);
const appointments = ref([]);
const selectedDate = ref('');

const todayStr = computed(() => {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
});

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
