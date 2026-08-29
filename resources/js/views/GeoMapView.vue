<template>
    <ListingPageShell
        title="Geo map"
        subtitle="Where your customers are, and what they're worth."
        :badge="badge"
    >
        <template #filters>
            <div class="listing-filters-row">
                <div class="flex-1 min-w-0 sm:max-w-xs">
                    <label for="geo-city" class="form-label">City</label>
                    <input id="geo-city" v-model="filters.city" type="text" class="form-input" placeholder="e.g. Manchester" />
                </div>
                <div class="flex-1 min-w-0 sm:max-w-xs">
                    <label for="geo-postcode" class="form-label">Postcode</label>
                    <input id="geo-postcode" v-model="filters.postcode" type="text" class="form-input" placeholder="e.g. M1" />
                </div>
                <div class="flex-1 min-w-0 sm:max-w-xs">
                    <label for="geo-source" class="form-label">Source</label>
                    <select id="geo-source" v-model="filters.source" class="form-select">
                        <option value="">All sources</option>
                        <option v-for="(label, value) in sources" :key="value" :value="value">{{ label }}</option>
                    </select>
                </div>
                <BaseButton variant="primary" block-mobile class="shrink-0" @click="load">
                    <template #icon><FunnelIcon class="icon" aria-hidden="true" /></template>
                    Apply
                </BaseButton>
            </div>
        </template>

        <div v-if="loading" class="px-5 py-12 text-center text-slate-500 text-sm" aria-busy="true">
            <span class="spinner" role="status" aria-label="Loading" />
            <span class="ml-2 align-middle">Loading map…</span>
        </div>

        <EmptyState
            v-else-if="!customers.length"
            heading="Nothing to plot"
            description="No customers matched, or none have coordinates recorded yet."
        >
            <template #icon><MapPinIcon class="icon" aria-hidden="true" /></template>
        </EmptyState>

        <div v-else>
            <p v-if="truncated" class="callout callout-warning mx-5 mt-4" role="status" aria-live="polite">
                Showing {{ returned }} of {{ total }} customers. Narrow the filters to see the rest.
            </p>
            <div ref="mapEl" class="h-[65vh] min-h-[420px] w-full" aria-label="Customer map"></div>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { FunnelIcon, MapPinIcon } from '@heroicons/vue/24/outline';
import ListingPageShell from '@/components/ListingPageShell.vue';
import EmptyState from '@/components/base/EmptyState.vue';
import { BaseButton } from '@/components/base';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const mapEl = ref(null);
const loading = ref(false);
const customers = ref([]);
const total = ref(0);
const returned = ref(0);
const truncated = ref(false);

const filters = reactive({ city: '', postcode: '', source: '' });

const sources = {
    call_center: 'Call centre',
    ground_field: 'Ground / field',
    website: 'Website',
    meta: 'Meta',
    tiktok: 'TikTok',
    google_ads: 'Google Ads',
    organic_lead: 'Organic',
    referral: 'Referral',
    cold_calling: 'Cold calling',
};

const badge = computed(() => (total.value ? `${total.value} plotted` : null));

let map = null;
let layer = null;
let L = null;

function money(value) {
    return new Intl.NumberFormat('en-GB', { style: 'currency', currency: 'GBP' }).format(value || 0);
}

async function ensureMap() {
    if (map) return;

    // Leaflet and its CSS are loaded on demand: this is the only screen that
    // needs them, and they are a meaningful chunk of the bundle.
    L = (await import('leaflet')).default;
    await import('leaflet/dist/leaflet.css');

    await nextTick();
    if (!mapEl.value) return;

    map = L.map(mapEl.value).setView([54.5, -3], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);

    layer = L.layerGroup().addTo(map);
}

function plot() {
    if (!map || !layer) return;

    layer.clearLayers();

    const points = [];

    customers.value.forEach((c) => {
        if (!Number.isFinite(c.latitude) || !Number.isFinite(c.longitude)) return;

        // Radius carries revenue so the map reads at a glance.
        const radius = c.revenue > 0 ? Math.min(22, 6 + Math.log10(c.revenue + 1) * 4) : 6;

        L.circleMarker([c.latitude, c.longitude], {
            radius,
            color: '#2563eb',
            weight: 1.5,
            fillColor: '#2563eb',
            fillOpacity: 0.35,
        })
            .bindPopup(
                `<strong>${escapeHtml(c.name || 'Customer')}</strong><br>` +
                `${escapeHtml([c.city, c.postcode].filter(Boolean).join(', '))}<br>` +
                `Revenue: ${money(c.revenue)}<br>` +
                `Tickets: ${c.tickets_count ?? 0}`,
            )
            .addTo(layer);

        points.push([c.latitude, c.longitude]);
    });

    if (points.length) {
        map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 13 });
    }
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[c]);
}

async function load() {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/reporting/geo', { params: filters });

        customers.value = (data.customers || []).map((c) => ({
            ...c,
            latitude: Number(c.latitude),
            longitude: Number(c.longitude),
        }));
        total.value = data.total ?? customers.value.length;
        returned.value = data.returned ?? customers.value.length;
        truncated.value = !!data.truncated;

        loading.value = false;

        if (customers.value.length) {
            await ensureMap();
            plot();
        }
    } catch (e) {
        loading.value = false;
        toast.error(e.response?.data?.message || 'Could not load the map.');
    }
}

onMounted(load);

onBeforeUnmount(() => {
    map?.remove();
    map = null;
    layer = null;
});
</script>
