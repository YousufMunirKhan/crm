<template>
    <div class="min-w-0 overflow-hidden rounded-xl bg-white p-4 shadow-sm md:p-6">
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="card-title">Attendance</h3>
            <div class="flex items-center gap-2">
                <div class="text-xs text-slate-500 sm:text-sm">{{ currentDate }}</div>
                <button
                    @click="refreshStatus"
                    :disabled="loading"
                    class="min-h-9 min-w-9 inline-flex items-center justify-center rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-600 touch-manipulation"
                    title="Refresh"
                    type="button"
                >
<ArrowPathIcon class="icon-sm" :class="{ 'animate-spin': loading }" aria-hidden="true" />
                </button>
            </div>
        </div>

        <div v-if="loading" class="flex items-center justify-center py-8" aria-busy="true">
            <span class="spinner w-8 h-8 border-4 text-primary-600" role="status" aria-label="Loading attendance" />
        </div>

        <div v-else class="space-y-4">
            <div class="flex min-w-0 items-center gap-3 rounded-lg p-3 sm:gap-4 sm:p-4" :class="statusBgClass">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full sm:h-12 sm:w-12" :class="statusIconClass">
                    <CheckIcon v-if="status.checked_in && !status.checked_out" class="w-6 h-6" aria-hidden="true" />
                    <ArrowRightOnRectangleIcon v-else-if="status.checked_out" class="w-6 h-6" aria-hidden="true" />
                    <ClockIcon v-else class="w-6 h-6" aria-hidden="true" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-slate-900 break-words">{{ statusText }}</div>
                    <div v-if="status.check_in_time" class="text-sm text-slate-600">
                        Check-in: {{ formatTime(status.check_in_time) }}
                    </div>
                    <div v-if="status.check_out_time" class="text-sm text-slate-600">
                        Check-out: {{ formatTime(status.check_out_time) }}
                    </div>
                </div>
                <div v-if="status.checked_in && status.check_in_time" class="shrink-0 text-right">
                    <div class="text-[11px] uppercase tracking-wide text-slate-500">Worked</div>
                    <div class="text-lg font-bold tabular-nums text-slate-900 sm:text-xl">{{ workingHours }}</div>
                </div>
            </div>

            <div class="flex gap-3 sm:max-w-xs">
                <button
                    v-if="!status.checked_in"
                    @click="checkIn"
                    :disabled="actionLoading"
                    class="min-h-11 flex-1 rounded-lg bg-success-600 px-4 py-3 font-medium text-white transition-colors hover:bg-success-700 disabled:cursor-not-allowed disabled:opacity-50 touch-manipulation"
                >
                    {{ actionLoading ? 'Capturing proof...' : 'Time In' }}
                </button>
                <button
                    v-else-if="!status.checked_out"
                    @click="checkOut"
                    :disabled="actionLoading"
                    class="min-h-11 flex-1 rounded-lg bg-danger-600 px-4 py-3 font-medium text-white transition-colors hover:bg-danger-700 disabled:cursor-not-allowed disabled:opacity-50 touch-manipulation"
                >
                    {{ actionLoading ? 'Capturing proof...' : 'Time Out' }}
                </button>
                <div v-else class="flex-1 px-4 py-3 bg-slate-100 text-slate-600 rounded-lg text-center font-medium">
                    Shift Complete
                </div>
            </div>

            <p v-if="!status.checked_in" class="text-xs text-slate-500">
                Camera and location permission are required for attendance proof. While you are
                clocked in, your location is recorded every 15 minutes if the app is open. It
                stops when you clock out.
            </p>
            <p v-else class="text-xs text-slate-500">
                Your location is recorded every 15 minutes while this app is open, and stops the
                moment you clock out.
            </p>

            <div v-if="proofError" class="rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
                {{ proofError }}
            </div>

            <details v-if="status.attendance" class="rounded-lg border border-slate-200">
                <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-slate-600 touch-manipulation">
                    Photo and location proof
                </summary>
                <div class="grid grid-cols-1 gap-3 border-t border-slate-100 p-3 text-sm sm:grid-cols-2">
                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="font-medium text-slate-900">Check-in proof</div>
                    <div class="mt-2 flex items-center gap-3">
                        <img
                            v-if="status.attendance.check_in_photo_url"
                            :src="status.attendance.check_in_photo_url"
                            alt="Check-in proof"
                            class="h-14 w-14 rounded object-cover"
                        />
                        <div class="min-w-0 text-xs text-slate-500">
                            <a
                                v-if="status.attendance.check_in_map_url"
                                :href="status.attendance.check_in_map_url"
                                target="_blank"
                                rel="noopener"
                                class="font-medium text-primary-700 hover:underline"
                            >
                                Open map
                            </a>
                            <div v-if="status.attendance.check_in_location_name" class="break-words font-medium text-slate-700">
                                {{ status.attendance.check_in_location_name }}
                            </div>
                            <div v-if="status.attendance.check_in_location_accuracy">
                                Accuracy {{ Math.round(Number(status.attendance.check_in_location_accuracy)) }}m
                            </div>
                            <div v-if="!status.attendance.check_in_photo_url && !status.attendance.check_in_map_url">
                                Not captured
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-200 p-3">
                    <div class="font-medium text-slate-900">Check-out proof</div>
                    <div class="mt-2 flex items-center gap-3">
                        <img
                            v-if="status.attendance.check_out_photo_url"
                            :src="status.attendance.check_out_photo_url"
                            alt="Check-out proof"
                            class="h-14 w-14 rounded object-cover"
                        />
                        <div class="min-w-0 text-xs text-slate-500">
                            <a
                                v-if="status.attendance.check_out_map_url"
                                :href="status.attendance.check_out_map_url"
                                target="_blank"
                                rel="noopener"
                                class="font-medium text-primary-700 hover:underline"
                            >
                                Open map
                            </a>
                            <div v-if="status.attendance.check_out_location_name" class="break-words font-medium text-slate-700">
                                {{ status.attendance.check_out_location_name }}
                            </div>
                            <div v-if="status.attendance.check_out_location_accuracy">
                                Accuracy {{ Math.round(Number(status.attendance.check_out_location_accuracy)) }}m
                            </div>
                            <div v-if="!status.attendance.check_out_photo_url && !status.attendance.check_out_map_url">
                                Not captured
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            </details>
        </div>
    </div>
</template>

<script setup>
import {
    ArrowPathIcon,
    ArrowRightOnRectangleIcon,
    CheckIcon,
    ClockIcon,
} from '@heroicons/vue/24/outline';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import { useShiftLocation } from '@/composables/useShiftLocation';

const emit = defineEmits(['updated']);
const toast = useToastStore();
const shiftLocation = useShiftLocation();

const loading = ref(true);
const actionLoading = ref(false);
const proofError = ref('');
const status = ref({
    checked_in: false,
    checked_out: false,
    check_in_time: null,
    check_out_time: null,
    attendance: null,
});
const serverDate = ref('');
const elapsedSeconds = ref(0);

let workingTimer = null;

const currentDate = computed(() => new Date().toLocaleDateString('en-GB', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
}));

const statusText = computed(() => {
    if (status.value.checked_out) return 'Shift Completed';
    if (status.value.checked_in) return 'Currently Working';
    return 'Not Checked In';
});

const statusBgClass = computed(() => {
    if (status.value.checked_out) return 'bg-slate-100';
    if (status.value.checked_in) return 'bg-success-50';
    return 'bg-warning-50';
});

const statusIconClass = computed(() => {
    if (status.value.checked_out) return 'bg-slate-200 text-slate-600';
    if (status.value.checked_in) return 'bg-success-200 text-success-700';
    return 'bg-warning-200 text-warning-800';
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
    const endTime = status.value.check_out_time ? new Date(status.value.check_out_time) : new Date();

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

const getLocation = () => new Promise((resolve, reject) => {
    if (!navigator.geolocation) {
        reject(new Error('Location is not available in this browser.'));
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => resolve(position),
        () => reject(new Error('Please allow location permission to record attendance.')),
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
});

const capturePhoto = async () => {
    if (!navigator.mediaDevices?.getUserMedia) {
        return capturePhotoFromFileInput();
    }

    let stream = null;
    try {
        stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 960 }, height: { ideal: 720 } },
            audio: false,
        });

        const video = document.createElement('video');
        video.srcObject = stream;
        video.muted = true;
        video.playsInline = true;
        video.setAttribute('playsinline', 'true');
        video.setAttribute('webkit-playsinline', 'true');
        await video.play();
        await new Promise((resolve) => setTimeout(resolve, 500));

        const sourceWidth = video.videoWidth || 720;
        const sourceHeight = video.videoHeight || 540;
        const maxWidth = 960;
        const scale = Math.min(1, maxWidth / sourceWidth);
        const width = Math.round(sourceWidth * scale);
        const height = Math.round(sourceHeight * scale);
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        canvas.getContext('2d').drawImage(video, 0, 0, width, height);

        return await new Promise((resolve, reject) => {
            canvas.toBlob((blob) => {
                if (blob) resolve(blob);
                else reject(new Error('Could not capture photo. Please try again.'));
            }, 'image/jpeg', 0.82);
        });
    } catch (error) {
        if (error?.name === 'NotAllowedError' || error?.name === 'PermissionDeniedError') {
            throw new Error('Please allow camera permission to record attendance.');
        }
        throw error;
    } finally {
        if (stream) stream.getTracks().forEach((track) => track.stop());
    }
};

const capturePhotoFromFileInput = () => new Promise((resolve, reject) => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.capture = 'user';
    input.style.position = 'fixed';
    input.style.left = '-9999px';
    document.body.appendChild(input);

    input.addEventListener('change', () => {
        const file = input.files?.[0];
        document.body.removeChild(input);
        if (file) {
            resolve(file);
        } else {
            reject(new Error('Please capture a photo to record attendance.'));
        }
    }, { once: true });

    input.click();
});

const collectProof = async () => {
    proofError.value = '';
    const [photo, position] = await Promise.all([capturePhoto(), getLocation()]);
    const formData = new FormData();
    formData.append('photo', photo, `attendance-${Date.now()}.jpg`);
    formData.append('latitude', String(position.coords.latitude));
    formData.append('longitude', String(position.coords.longitude));
    formData.append('accuracy', String(position.coords.accuracy || 0));
    formData.append('captured_at', new Date().toISOString());
    return formData;
};

const fetchStatus = async () => {
    try {
        const response = await axios.get('/api/hr/attendance/today', {
            params: { _t: Date.now() },
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

const submitAttendance = async (url, successMessage, title) => {
    actionLoading.value = true;
    try {
        const proof = await collectProof();
        await axios.post(url, proof);
        await fetchStatus();
        emit('updated');
        toast.success(successMessage, title);
    } catch (error) {
        console.error(`${title} failed:`, error);
        const message = error.response?.data?.error || error.message || `${title} failed`;
        proofError.value = message;
        toast.error(message, 'Error');
    } finally {
        actionLoading.value = false;
    }
};

// Start and stop the shift tracker here rather than waiting for a reload -
// somebody clocks in and then goes straight out.
const checkIn = async () => {
    await submitAttendance('/api/hr/attendance/check-in', 'Successfully checked in!', 'Time In');
    shiftLocation.refresh();
};

const checkOut = async () => {
    await submitAttendance('/api/hr/attendance/check-out', 'Successfully checked out!', 'Time Out');
    shiftLocation.stop();
};

const refreshStatus = async () => {
    loading.value = true;
    status.value = {
        checked_in: false,
        checked_out: false,
        check_in_time: null,
        check_out_time: null,
        attendance: null,
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
