<template>
    <div class="bg-white rounded-xl shadow-sm p-4 md:p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-slate-900">Attendance</h3>
            <div class="flex items-center gap-2">
                <div class="text-sm text-slate-500">{{ currentDate }}</div>
                <button
                    @click="refreshStatus"
                    :disabled="loading"
                    class="p-1 text-slate-400 hover:text-slate-600 transition-colors"
                    title="Refresh"
                >
                    <svg class="w-4 h-4" :class="{ 'animate-spin': loading }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </button>
            </div>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-8">
            <div class="animate-spin w-8 h-8 border-4 border-blue-500 border-t-transparent rounded-full"></div>
        </div>

        <div v-else class="space-y-4">
            <!-- Status Display -->
            <div class="flex items-center gap-4 p-4 rounded-lg" :class="statusBgClass">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" :class="statusIconClass">
                    <svg v-if="status.checked_in && !status.checked_out" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <svg v-else-if="status.checked_out" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="font-semibold text-slate-900">{{ statusText }}</div>
                    <div v-if="status.check_in_time" class="text-sm text-slate-600">
                        Check-in: {{ formatTime(status.check_in_time) }}
                    </div>
                    <div v-if="status.check_out_time" class="text-sm text-slate-600">
                        Check-out: {{ formatTime(status.check_out_time) }}
                    </div>
                </div>
            </div>

            <!-- Action Button -->
            <div class="flex gap-3">
                <button
                    v-if="!status.checked_in"
                    @click="checkIn"
                    :disabled="actionLoading"
                    class="flex-1 px-4 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                >
                    <span v-if="actionLoading">Processing...</span>
                    <span v-else>🕐 Time In</span>
                </button>
                <button
                    v-else-if="!status.checked_out"
                    @click="checkOut"
                    :disabled="actionLoading"
                    class="flex-1 px-4 py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                >
                    <span v-if="actionLoading">Processing...</span>
                    <span v-else>🕐 Time Out</span>
                </button>
                <div v-else class="flex-1 px-4 py-3 bg-slate-100 text-slate-600 rounded-lg text-center font-medium">
                    ✅ Shift Complete
                </div>
            </div>

            <!-- Working Hours -->
            <div v-if="status.checked_in && status.check_in_time" class="text-center">
                <div class="text-sm text-slate-500">Working Hours</div>
                <div class="text-2xl font-bold text-slate-900">{{ workingHours }}</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const loading = ref(true);
const actionLoading = ref(false);
const status = ref({
    checked_in: false,
    checked_out: false,
    check_in_time: null,
    check_out_time: null,
});
const serverDate = ref('');

let workingTimer = null;
const elapsedSeconds = ref(0);

const currentDate = computed(() => {
    return new Date().toLocaleDateString('en-GB', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
});

const statusText = computed(() => {
    if (status.value.checked_out) return 'Shift Completed';
    if (status.value.checked_in) return 'Currently Working';
    return 'Not Checked In';
});

const statusBgClass = computed(() => {
    if (status.value.checked_out) return 'bg-slate-100';
    if (status.value.checked_in) return 'bg-green-50';
    return 'bg-amber-50';
});

const statusIconClass = computed(() => {
    if (status.value.checked_out) return 'bg-slate-200 text-slate-600';
    if (status.value.checked_in) return 'bg-green-200 text-green-600';
    return 'bg-amber-200 text-amber-600';
});

const workingHours = computed(() => {
    if (!status.value.check_in_time) return '00:00:00';
    
    const hours = Math.floor(elapsedSeconds.value / 3600);
    const minutes = Math.floor((elapsedSeconds.value % 3600) / 60);
    const seconds = elapsedSeconds.value % 60;
    
    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
});

const formatTime = (timeString) => {
    if (!timeString) return '';
    const date = new Date(timeString);
    return date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};

const calculateElapsed = () => {
    if (!status.value.check_in_time) return 0;
    
    const checkIn = new Date(status.value.check_in_time);
    const endTime = status.value.check_out_time 
        ? new Date(status.value.check_out_time) 
        : new Date();
    
    return Math.floor((endTime - checkIn) / 1000);
};

const startTimer = () => {
    if (workingTimer) clearInterval(workingTimer);
    
    elapsedSeconds.value = calculateElapsed();
    
    if (status.value.checked_in && !status.value.checked_out) {
        workingTimer = setInterval(() => {
            elapsedSeconds.value++;
        }, 1000);
    }
};

const fetchStatus = async () => {
    try {
        // Add timestamp to prevent caching
        const response = await axios.get('/api/hr/attendance/today', {
            params: { _t: Date.now() }
        });
        status.value = response.data;
        serverDate.value = response.data.server_date || '';
        startTimer();
    } catch (error) {
        console.error('Failed to fetch attendance status:', error);
    } finally {
        loading.value = false;
    }
};

const toast = useToastStore();

const checkIn = async () => {
    actionLoading.value = true;
    try {
        await axios.post('/api/hr/attendance/check-in');
        await fetchStatus();
        toast.success('Successfully checked in!', 'Time In');
    } catch (error) {
        console.error('Check-in failed:', error);
        toast.error(error.response?.data?.error || 'Check-in failed', 'Error');
    } finally {
        actionLoading.value = false;
    }
};

const checkOut = async () => {
    actionLoading.value = true;
    try {
        await axios.post('/api/hr/attendance/check-out');
        await fetchStatus();
        toast.success('Successfully checked out!', 'Time Out');
    } catch (error) {
        console.error('Check-out failed:', error);
        toast.error(error.response?.data?.error || 'Check-out failed', 'Error');
    } finally {
        actionLoading.value = false;
    }
};

const refreshStatus = async () => {
    loading.value = true;
    // Reset status to default before fetching
    status.value = {
        checked_in: false,
        checked_out: false,
        check_in_time: null,
        check_out_time: null,
    };
    elapsedSeconds.value = 0;
    if (workingTimer) {
        clearInterval(workingTimer);
        workingTimer = null;
    }
    await fetchStatus();
};

onMounted(() => {
    fetchStatus();
});

onUnmounted(() => {
    if (workingTimer) clearInterval(workingTimer);
});
</script>

