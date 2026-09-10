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

            <!--
                Only appears when the browser could not open a camera at all - no
                webcam, or one already held by another app. The control has to be
                pressed by hand: a file dialog cannot be opened from script once
                the click that started this has been awaited away, which is why
                the old off-screen input never showed anything.
            -->
            <div v-if="photoPickerOpen" class="rounded-lg border border-warning-200 bg-warning-50 p-3">
                <div class="text-sm font-medium text-warning-800">No camera available on this device</div>
                <p class="mt-1 text-xs text-warning-800">
                    Take or choose a photo instead, and attendance will be recorded as normal.
                </p>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                    <label class="min-h-11 flex-1 cursor-pointer rounded-lg bg-primary-600 px-4 py-3 text-center text-sm font-medium text-white transition-colors hover:bg-primary-700 touch-manipulation">
                        Take or choose photo
                        <input
                            type="file"
                            accept="image/*"
                            capture="user"
                            class="sr-only"
                            @change="onPhotoPicked"
                        />
                    </label>
                    <button
                        type="button"
                        @click="cancelPhotoPicker"
                        class="min-h-11 rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 touch-manipulation"
                    >
                        Cancel
                    </button>
                </div>
            </div>

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
                    <button
                        v-if="canReplacePhoto && status.checked_in"
                        type="button"
                        @click="replacePhoto('check_in')"
                        :disabled="!!replacingPhoto"
                        class="mt-3 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 touch-manipulation"
                    >
                        {{ replacingPhoto === 'check_in' ? 'Retaking...' : 'Retake photo' }}
                    </button>
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
                    <button
                        v-if="canReplacePhoto && status.checked_out"
                        type="button"
                        @click="replacePhoto('check_out')"
                        :disabled="!!replacingPhoto"
                        class="mt-3 min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs font-medium text-slate-700 transition-colors hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 touch-manipulation"
                    >
                        {{ replacingPhoto === 'check_out' ? 'Retaking...' : 'Retake photo' }}
                    </button>
                </div>
                </div>
            </details>
        </div>

        <!--
            The camera, on screen. The photo is taken when the person presses the
            button, not half a second after the stream opens.
        -->
        <div
            v-if="cameraPreviewOpen"
            class="fixed inset-0 z-modal flex items-center justify-center bg-slate-900/80 p-4"
            role="dialog"
            aria-modal="true"
            aria-label="Attendance photo"
        >
            <div class="w-full max-w-sm rounded-xl bg-white p-4 shadow-lg">
                <div class="text-sm font-medium text-slate-900">Attendance photo</div>
                <p class="mt-1 text-xs text-slate-500">
                    Check you are in frame, then take the photo.
                </p>
                <video
                    ref="previewVideo"
                    autoplay
                    playsinline
                    muted
                    class="mt-3 w-full -scale-x-100 rounded-lg bg-slate-900"
                ></video>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                    <button
                        type="button"
                        @click="takePhotoNow"
                        class="min-h-11 flex-1 rounded-lg bg-success-600 px-4 py-3 text-sm font-medium text-white transition-colors hover:bg-success-700 touch-manipulation"
                    >
                        Take photo
                    </button>
                    <button
                        type="button"
                        @click="cancelCameraPreview"
                        class="min-h-11 rounded-lg border border-slate-300 px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 touch-manipulation"
                    >
                        Cancel
                    </button>
                </div>
            </div>
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
import { ref, computed, nextTick, onMounted, onUnmounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';
import { useShiftLocation } from '@/composables/useShiftLocation';
import { UK_TIMEZONE, ukTime } from '@/utils/datetime';

const emit = defineEmits(['updated']);
const toast = useToastStore();
const auth = useAuthStore();
const shiftLocation = useShiftLocation();

const loading = ref(true);
const actionLoading = ref(false);
const proofError = ref('');
const replacingPhoto = ref('');

/** Shown only when the browser could not open a camera at all. */
const photoPickerOpen = ref(false);
let photoPickerResolve = null;
let photoPickerReject = null;

/** The live camera, shown so people can see themselves before the shutter. */
const cameraPreviewOpen = ref(false);
const previewVideo = ref(null);
let previewResolve = null;
let previewReject = null;

/**
 * Retaking the proof photo is an admin's option, not everyone's.
 *
 * The server enforces this on the route - this only decides whether the button
 * is drawn. It is the same pair of roles either way.
 */
const canReplacePhoto = computed(() => ['Admin', 'System Admin'].includes(auth.role));
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

// The UK date, which is the day the shift is filed under - not the reader's,
// which for anybody far enough east is already tomorrow.
const currentDate = computed(() => new Date().toLocaleDateString('en-GB', {
    timeZone: UK_TIMEZONE,
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

const formatTime = (timeString) => ukTime(timeString);

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

/**
 * Errors that mean "this browser cannot open a camera for you", as opposed to
 * "you said no". A desktop with no webcam, a camera already held by another
 * app, or a machine whose only camera does not report a facing direction all
 * land here - and for all of them the file input is a working way through.
 */
const NO_USABLE_CAMERA = [
    'NotFoundError',
    'DevicesNotFoundError',
    'OverconstrainedError',
    'ConstraintNotSatisfiedError',
    'NotReadableError',
    'TrackStartError',
];

/**
 * Prefer the selfie camera, settle for any.
 *
 * facingMode: 'user' is the right thing to ask for on a phone. On a desktop it
 * often matches nothing, and asking again without it is the difference between
 * a working webcam and "Requested device not found".
 */
const openCameraStream = async () => {
    try {
        return await navigator.mediaDevices.getUserMedia({
            video: { facingMode: 'user', width: { ideal: 960 }, height: { ideal: 720 } },
            audio: false,
        });
    } catch (error) {
        if (error?.name === 'NotAllowedError' || error?.name === 'PermissionDeniedError') {
            throw error;
        }

        return await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
    }
};

const capturePhoto = async () => {
    if (!navigator.mediaDevices?.getUserMedia) {
        return requestPhotoFromUser();
    }

    let stream = null;

    try {
        stream = await openCameraStream();
    } catch (error) {
        if (error?.name === 'NotAllowedError' || error?.name === 'PermissionDeniedError') {
            throw new Error('Please allow camera permission to record attendance.');
        }

        // There is no camera this browser can open. The file input still gets a
        // photo - the camera app on a phone, an existing file on a desktop - and
        // that beats refusing to let somebody clock in at all, which is what
        // "Requested device not found" amounted to.
        if (NO_USABLE_CAMERA.includes(error?.name)) {
            return requestPhotoFromUser();
        }

        throw error;
    }

    try {
        return await photoFromPreview(stream);
    } finally {
        stream.getTracks().forEach((track) => track.stop());
        closeCameraPreview();
    }
};

/**
 * Show the camera and let the person take the photo.
 *
 * The video element used to be created in script and never put on the page: it
 * played out of sight, and half a second later a frame was grabbed and sent.
 * Nobody saw what was taken - whether they were in frame, whether the lens was
 * covered, whether the picture was of the ceiling. For a photo whose whole
 * purpose is to show who was there, that is the wrong way round.
 *
 * The preview is mirrored because that is what people expect to see of
 * themselves. The captured frame is not - the file is evidence, and it should
 * be the way the camera saw it.
 */
const photoFromPreview = async (stream) => {
    cameraPreviewOpen.value = true;
    await nextTick();

    const video = previewVideo.value;

    if (!video) {
        throw new Error('Could not open the camera preview. Please try again.');
    }

    video.srcObject = stream;
    video.muted = true;
    video.playsInline = true;
    await video.play();

    return new Promise((resolve, reject) => {
        previewResolve = resolve;
        previewReject = reject;
    });
};

const takePhotoNow = () => {
    const video = previewVideo.value;

    if (!video?.videoWidth) {
        return;
    }

    const maxWidth = 960;
    const scale = Math.min(1, maxWidth / video.videoWidth);
    const width = Math.round(video.videoWidth * scale);
    const height = Math.round(video.videoHeight * scale);
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    canvas.getContext('2d').drawImage(video, 0, 0, width, height);

    const resolve = previewResolve;
    const reject = previewReject;
    previewResolve = null;
    previewReject = null;

    canvas.toBlob((blob) => {
        if (blob) resolve?.(blob);
        else reject?.(new Error('Could not capture photo. Please try again.'));
    }, 'image/jpeg', 0.82);
};

const cancelCameraPreview = () => {
    const reject = previewReject;
    previewResolve = null;
    previewReject = null;
    reject?.(new Error('Attendance needs a photo, so nothing was recorded.'));
};

const closeCameraPreview = () => {
    cameraPreviewOpen.value = false;

    if (previewVideo.value) {
        previewVideo.value.srcObject = null;
    }

    previewResolve = null;
    previewReject = null;
};

/**
 * Ask the person for a photo, because the camera could not be opened.
 *
 * This used to build an input off-screen and click it in script. That cannot
 * work here: by the time we know the camera failed we have awaited getUserMedia
 * at least once, the user activation from pressing the button is gone, and a
 * browser will not open a file dialog without one. Nothing appeared, and since
 * the promise only ever settled on `change`, the button sat on "Capturing
 * proof..." for good.
 *
 * So the panel is shown instead and the person presses the control themselves,
 * which is a real gesture. Cancelling rejects rather than hanging.
 */
const requestPhotoFromUser = () => new Promise((resolve, reject) => {
    photoPickerResolve = resolve;
    photoPickerReject = reject;
    photoPickerOpen.value = true;
});

const settlePhotoPicker = () => {
    photoPickerOpen.value = false;
    photoPickerResolve = null;
    photoPickerReject = null;
};

const onPhotoPicked = (event) => {
    const file = event.target.files?.[0];
    event.target.value = '';

    // Dismissing the file dialog is not the same as giving up on clocking in -
    // leave the panel up so they can try again.
    if (!file) return;

    const resolve = photoPickerResolve;
    settlePhotoPicker();
    resolve?.(file);
};

const cancelPhotoPicker = () => {
    const reject = photoPickerReject;
    settlePhotoPicker();
    reject?.(new Error('Attendance needs a photo, so nothing was recorded.'));
};

const collectProof = async () => {
    proofError.value = '';

    // Location is attempted, not insisted on here.
    //
    // Somebody whose browser will not give a reading may still have a fixed work
    // address set on their account, and only the server knows that - so the
    // decision belongs there. If they have no fixed address either, the server
    // refuses with the same message this used to throw, so nothing is lost.
    const [photo, position] = await Promise.all([
        capturePhoto(),
        getLocation().catch(() => null),
    ]);

    const formData = new FormData();
    formData.append('photo', photo, `attendance-${Date.now()}.jpg`);

    if (position) {
        formData.append('latitude', String(position.coords.latitude));
        formData.append('longitude', String(position.coords.longitude));
        formData.append('accuracy', String(position.coords.accuracy || 0));
    }

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
        // If something else in the run failed - location refused, say - the camera
        // and the photo panel are left waiting on somebody who has already been
        // told it did not work. Cancelling the preview also stops the stream,
        // because that is what releases the camera light.
        cancelCameraPreview();
        settlePhotoPicker();
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

/**
 * Take the proof photo again for today.
 *
 * Only the photo. The location and the time it was clocked stay as they were
 * recorded - a retake is for a photo that came out unusable, not a way to
 * re-record where somebody was.
 */
const replacePhoto = async (which) => {
    if (replacingPhoto.value) return;

    replacingPhoto.value = which;
    proofError.value = '';

    try {
        const photo = await capturePhoto();
        const form = new FormData();
        form.append('photo', photo, 'attendance.jpg');
        form.append('which', which);

        await axios.post('/api/hr/attendance/today/photo', form);
        await fetchStatus();
        emit('updated');
        toast.success('Photo replaced.', 'Attendance');
    } catch (error) {
        console.error('Photo replace failed:', error);
        const message = error.response?.data?.error || error.message || 'Could not replace the photo.';
        proofError.value = message;
        toast.error(message, 'Error');
    } finally {
        // If something else in the run failed - location refused, say - the camera
        // and the photo panel are left waiting on somebody who has already been
        // told it did not work. Cancelling the preview also stops the stream,
        // because that is what releases the camera light.
        cancelCameraPreview();
        settlePhotoPicker();
        replacingPhoto.value = '';
    }
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
