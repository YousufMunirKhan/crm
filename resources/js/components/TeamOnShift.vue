<template>
    <div class="min-w-0 overflow-hidden rounded-card border border-slate-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3">
            <h3 class="text-sm font-semibold text-slate-800">Who is on shift</h3>
            <div class="flex items-center gap-2">
                <router-link to="/hr/attendance-report" class="text-xs font-medium text-primary-700 hover:underline">
                    Attendance
                </router-link>
                <button
                    type="button"
                    @click="load"
                    :disabled="loading"
                    class="min-h-9 min-w-9 inline-flex items-center justify-center rounded-lg p-2 text-slate-500 transition-colors hover:bg-slate-50 hover:text-slate-600 touch-manipulation"
                    title="Refresh"
                >
                    <ArrowPathIcon class="icon-sm" :class="{ 'animate-spin': loading }" aria-hidden="true" />
                </button>
            </div>
        </div>

        <div v-if="loading && !data" class="flex items-center justify-center py-8" aria-busy="true">
            <span class="spinner h-6 w-6 border-4 text-primary-600" role="status" aria-label="Loading" />
        </div>

        <div v-else-if="data" class="p-4">
            <!-- The counts first. Someone opening this wants a number before names. -->
            <div class="grid grid-cols-3 gap-2">
                <div class="rounded-lg bg-success-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold tabular-nums text-success-700">{{ data.counts.on_shift }}</div>
                    <div class="text-[11px] font-medium uppercase tracking-wide text-success-700">On shift</div>
                </div>
                <div class="rounded-lg bg-slate-100 px-3 py-2 text-center">
                    <div class="text-xl font-bold tabular-nums text-slate-700">{{ data.counts.finished }}</div>
                    <div class="text-[11px] font-medium uppercase tracking-wide text-slate-600">Finished</div>
                </div>
                <div class="rounded-lg bg-warning-50 px-3 py-2 text-center">
                    <div class="text-xl font-bold tabular-nums text-warning-800">{{ data.counts.not_in }}</div>
                    <div class="text-[11px] font-medium uppercase tracking-wide text-warning-800">Not in</div>
                </div>
            </div>

            <ul v-if="data.on_shift.length" class="mt-3 divide-y divide-slate-50">
                <li
                    v-for="person in data.on_shift"
                    :key="person.id"
                    class="flex items-start justify-between gap-3 py-2"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full bg-success-500" aria-hidden="true" />
                            <span class="truncate text-sm font-medium text-slate-900">{{ person.name }}</span>
                        </div>
                        <p class="mt-0.5 pl-4 text-xs text-slate-500">
                            <span>in at {{ formatTime(person.checked_in_at) }}</span>
                            <template v-if="person.location_name">
                                <span class="text-slate-400"> · </span>
                                <span class="break-words">{{ person.location_name }}</span>
                            </template>
                        </p>
                    </div>
                    <span class="shrink-0 text-xs tabular-nums text-slate-500">{{ elapsed(person.minutes_on_shift) }}</span>
                </li>
            </ul>

            <p v-else class="mt-3 text-sm text-slate-500">Nobody is clocked in right now.</p>

            <details v-if="data.finished.length" class="mt-3 border-t border-slate-100 pt-2">
                <summary class="cursor-pointer text-xs font-medium text-slate-600 touch-manipulation">
                    Finished today ({{ data.finished.length }})
                </summary>
                <ul class="mt-1 divide-y divide-slate-50">
                    <li v-for="person in data.finished" :key="person.id" class="flex items-center justify-between gap-3 py-2">
                        <span class="truncate text-sm text-slate-700">{{ person.name }}</span>
                        <span
                            v-if="person.never_clocked_out"
                            class="shrink-0 text-xs text-warning-800"
                            title="The shift was closed automatically. No finish time was recorded."
                        >
                            never clocked out
                        </span>
                        <span v-else class="shrink-0 text-xs tabular-nums text-slate-500">
                            {{ formatTime(person.checked_in_at) }} – {{ formatTime(person.checked_out_at) }}
                        </span>
                    </li>
                </ul>
            </details>

            <details v-if="data.not_in.length" class="mt-2 border-t border-slate-100 pt-2">
                <summary class="cursor-pointer text-xs font-medium text-slate-600 touch-manipulation">
                    Not clocked in ({{ data.not_in.length }})
                </summary>
                <ul class="mt-1 flex flex-wrap gap-x-3 gap-y-1 py-2">
                    <li v-for="person in data.not_in" :key="person.id" class="text-sm text-slate-600">
                        {{ person.name }}
                    </li>
                </ul>
            </details>
        </div>
    </div>
</template>

<script setup>
import { ArrowPathIcon } from '@heroicons/vue/24/outline';
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import { ukTime } from '@/utils/datetime';

const loading = ref(true);
const data = ref(null);
let timer = null;

// UK time, not the reader's. Somebody looking at this from Karachi was seeing
// shift times shifted five hours from the ones on the report.
const formatTime = (iso) => ukTime(iso);

/** Minutes are what the server counts in; hours are what people read in. */
const elapsed = (minutes) => {
    const total = Number(minutes) || 0;
    const hours = Math.floor(total / 60);
    const rest = total % 60;

    return hours ? `${hours}h ${rest}m` : `${rest}m`;
};

async function load() {
    loading.value = true;

    try {
        const response = await axios.get('/api/hr/attendance/today-roster');
        data.value = response.data;
    } catch (error) {
        // A dashboard panel that cannot load is not worth an error banner over
        // the rest of the page; it just does not draw.
        console.error('Could not load the shift roster:', error);
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    load();
    // People clock in and out through the morning, so this goes stale quickly.
    timer = setInterval(load, 60 * 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>
