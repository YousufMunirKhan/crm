<template>
    <ListingPageShell
        title="Where the team is"
        subtitle="Everyone currently on shift, and the trail for a chosen day."
        :badge="badge"
    >
        <template #filters>
            <div class="listing-filters-row">
                <div class="w-full sm:w-auto sm:min-w-[12rem]">
                    <label class="listing-label" for="teammap-person">Person</label>
                    <select id="teammap-person" v-model="selectedUserId" class="form-select" @change="loadTrail">
                        <option value="">Everyone on shift now</option>
                        <option v-for="p in people" :key="p.user?.id" :value="p.user?.id">
                            {{ p.user?.name }}
                        </option>
                    </select>
                </div>
                <div v-if="selectedUserId" class="w-full sm:w-auto">
                    <label class="listing-label" for="teammap-date">Day</label>
                    <input id="teammap-date" v-model="selectedDate" type="date" class="form-input w-full sm:w-44" @change="loadTrail" />
                </div>
                <BaseButton variant="outline" :loading="loading" @click="refresh">Refresh</BaseButton>
            </div>
        </template>

        <div class="space-y-4 px-3 pb-3 sm:px-5 sm:pb-5">
            <!--
                Phones that have stopped reporting, first. On iOS the person is
                asked again, weeks later, whether to keep allowing background
                location - answer "While Using" and tracking stops with no
                error and nothing on this end. Android has battery optimisation
                and force-quit. In all of them the trail simply stops, and a
                silent phone looks exactly like somebody who did nothing.
            -->
            <div v-if="silent.length" class="rounded-card border border-warning-200 bg-warning-50/70 p-3 sm:p-4">
                <h2 class="text-sm font-semibold text-warning-900">
                    {{ silent.length }} {{ silent.length === 1 ? 'phone has' : 'phones have' }} stopped reporting
                </h2>
                <p class="mt-0.5 text-xs text-warning-800">
                    They are clocked in but nothing has come through. Usually the location
                    permission was changed, or the app was closed - not a sign of what the
                    person has been doing.
                </p>
                <ul class="mt-3 space-y-1.5">
                    <li v-for="p in silent" :key="p.user?.id" class="text-sm text-slate-800">
                        <span class="font-medium">{{ p.user?.name }}</span>
                        <span class="text-slate-500">
                            —
                            <template v-if="p.ever_reported">last seen {{ sinceLabel(p.silent_minutes) }}</template>
                            <template v-else>nothing since clocking in {{ sinceLabel(p.silent_minutes) }}</template>
                        </span>
                    </li>
                </ul>
            </div>

            <div ref="mapEl" class="h-[26rem] w-full overflow-hidden rounded-card border border-slate-200 sm:h-[32rem]" />

            <p v-if="!loading && !hasAnything" class="callout callout-info">
                Nothing to show. Either nobody is clocked in, or no phone has reported yet.
            </p>

            <!-- The trail as a list, because a map is hard to read on a phone. -->
            <div v-if="selectedUserId && trail.length" class="rounded-card border border-slate-200 bg-white">
                <div class="border-b border-slate-100 px-4 py-3">
                    <h2 class="text-sm font-semibold text-slate-800">
                        {{ trailUserName }} — {{ trail.length }} readings
                    </h2>
                    <p v-if="shift" class="mt-0.5 text-xs text-slate-500">
                        On shift {{ timeOnly(shift.check_in_at) }}
                        <template v-if="shift.check_out_at">to {{ timeOnly(shift.check_out_at) }}</template>
                        <template v-else>— still out</template>
                    </p>
                </div>
                <ul class="max-h-80 divide-y divide-slate-50 overflow-y-auto">
                    <li v-for="(p, i) in trail" :key="i" class="flex items-center justify-between gap-3 px-4 py-2 text-sm">
                        <span class="tabular-nums text-slate-800">{{ timeOnly(p.recorded_at) }}</span>
                        <span class="text-xs" :class="p.usable ? 'text-slate-500' : 'text-warning-800'">
                            <template v-if="p.usable">±{{ Math.round(p.accuracy ?? 0) }}m</template>
                            <template v-else>too vague to place (±{{ Math.round(p.accuracy ?? 0) }}m)</template>
                        </span>
                        <span v-if="p.battery_level !== null" class="text-xs tabular-nums text-slate-400">
                            {{ p.battery_level }}%
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, nextTick } from 'vue';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseButton } from '@/components/base';

/**
 * Where field staff are, and where they have been today.
 *
 * Only ever shows readings taken during a shift - the server refuses to store
 * anything else - so this screen cannot show somebody's evening even by
 * accident.
 */
const mapEl = ref(null);
const loading = ref(false);
const people = ref([]);
const trail = ref([]);
const shift = ref(null);
const selectedUserId = ref('');
const selectedDate = ref(new Date().toISOString().slice(0, 10));

let L = null;
let map = null;
let layer = null;

const silent = computed(() => people.value.filter((p) => !p.reporting));

const badge = computed(() => {
    if (loading.value) return null;
    const n = people.value.length;

    return n ? `${n} on shift` : 'nobody on shift';
});

const hasAnything = computed(() => people.value.length > 0 || trail.value.length > 0);

const trailUserName = computed(() =>
    people.value.find((p) => String(p.user?.id) === String(selectedUserId.value))?.user?.name ?? 'Trail');

function timeOnly(iso) {
    return iso ? new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : '';
}

function sinceLabel(minutes) {
    if (minutes < 60) return `${minutes} min ago`;
    const h = Math.floor(minutes / 60);

    return `${h}h ${minutes % 60}m ago`;
}

async function ensureMap() {
    if (map) return;

    L = (await import('leaflet')).default;
    await import('leaflet/dist/leaflet.css');

    await nextTick();
    if (!mapEl.value) return;

    map = L.map(mapEl.value).setView([54.5, -3], 6);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap',
        maxZoom: 19,
    }).addTo(map);
}

function clearLayer() {
    if (layer && map) {
        map.removeLayer(layer);
    }
    layer = L ? L.layerGroup() : null;
    if (layer && map) layer.addTo(map);
}

function draw() {
    if (!map || !L) return;

    clearLayer();
    const bounds = [];

    if (selectedUserId.value) {
        // A vague reading is drawn as a circle the size of its own error
        // rather than a pin, because a 2km fix shown as a dot is a lie about
        // where somebody was.
        const line = [];

        trail.value.forEach((p, i) => {
            const pos = [p.latitude, p.longitude];
            bounds.push(pos);

            if (p.usable) {
                line.push(pos);
                L.circleMarker(pos, {
                    radius: 5,
                    color: '#0f5f5c',
                    fillColor: '#0f5f5c',
                    fillOpacity: 0.9,
                    weight: 1,
                }).bindPopup(`${timeOnly(p.recorded_at)} · ±${Math.round(p.accuracy ?? 0)}m`).addTo(layer);
            } else {
                L.circle(pos, {
                    radius: p.accuracy ?? 1000,
                    color: '#85560a',
                    fillColor: '#85560a',
                    fillOpacity: 0.08,
                    weight: 1,
                    dashArray: '4',
                }).bindPopup(`${timeOnly(p.recorded_at)} · too vague to place`).addTo(layer);
            }

            if (i === 0 || i === trail.value.length - 1) {
                L.marker(pos).bindPopup(i === 0 ? 'First reading' : 'Last reading').addTo(layer);
            }
        });

        // The line joins only the readings good enough to trust; drawing
        // through a vague one invents a journey.
        if (line.length > 1) {
            L.polyline(line, { color: '#0f5f5c', weight: 3, opacity: 0.6 }).addTo(layer);
        }
    } else {
        people.value.filter((p) => p.latitude !== null).forEach((p) => {
            const pos = [p.latitude, p.longitude];
            bounds.push(pos);

            L.marker(pos)
                .bindPopup(`<strong>${p.user?.name ?? ''}</strong><br>last seen ${sinceLabel(p.silent_minutes)}`)
                .addTo(layer);
        });
    }

    if (bounds.length) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
    }
}

async function loadLive() {
    try {
        const { data } = await axios.get('/api/hr/attendance/live-map');
        people.value = data.people ?? [];
    } catch {
        people.value = [];
    }
}

async function loadTrail() {
    if (!selectedUserId.value) {
        trail.value = [];
        shift.value = null;
        draw();

        return;
    }

    try {
        const { data } = await axios.get(`/api/hr/attendance/location/${selectedUserId.value}`, {
            params: { date: selectedDate.value },
        });
        trail.value = data.points ?? [];
        shift.value = data.shift;
    } catch {
        trail.value = [];
        shift.value = null;
    }

    draw();
}

async function refresh() {
    loading.value = true;

    try {
        await ensureMap();
        await loadLive();
        await loadTrail();
        draw();
    } finally {
        loading.value = false;
    }
}

let timer = null;

onMounted(async () => {
    await refresh();
    // Readings arrive every fifteen minutes, so anything faster is a request
    // for a number that cannot have changed.
    timer = setInterval(loadLive, 5 * 60 * 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
    if (map) {
        map.remove();
        map = null;
    }
});
</script>
