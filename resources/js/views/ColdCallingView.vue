<template>
    <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Cold calling</h1>
            <p class="text-sm text-slate-600 mt-1">
                Discover UK businesses near a postcode via Google Places. Every listing is stored in <strong>cold calling</strong> only; converting to a prospect also creates a separate row in <strong>CRM customers</strong> (type prospect) without removing this listing.
            </p>
            <div
                v-if="settingsStatus"
                class="mt-4 p-4 rounded-xl flex flex-wrap items-center gap-3"
                :class="settingsStatus.configured ? 'bg-emerald-50 border border-emerald-200' : 'bg-amber-50 border border-amber-200'"
            >
                <span :class="settingsStatus.configured ? 'text-emerald-600' : 'text-amber-600'" class="text-lg">{{ settingsStatus.configured ? '✓' : '⚠' }}</span>
                <span class="text-sm" :class="settingsStatus.configured ? 'text-emerald-800' : 'text-amber-800'">
                    {{ settingsStatus.message }}
                </span>
                <router-link
                    to="/settings"
                    class="text-sm font-medium underline"
                    :class="settingsStatus.configured ? 'text-emerald-800' : 'text-amber-800'"
                >
                    Open Settings → Cold calling
                </router-link>
            </div>
            <div
                v-if="settingsStatus?.claude_message"
                class="mt-2 p-3 rounded-xl text-sm border"
                :class="settingsStatus.claude_enrichment_enabled
                    ? 'bg-violet-50 border-violet-200 text-violet-900'
                    : 'bg-slate-50 border-slate-200 text-slate-600'"
            >
                <span class="font-medium">Claude AI:</span>
                {{ settingsStatus.claude_message }}
            </div>
            <p class="mt-3 text-xs text-slate-500">
                By default discovery runs in the same request (no queue worker). If you set
                <code class="bg-slate-100 px-1 rounded">COLD_CALLING_RUN_SYNC=false</code> in
                <code class="bg-slate-100 px-1 rounded">.env</code>, run
                <code class="bg-slate-100 px-1 rounded">php artisan queue:work</code> so jobs are processed.
            </p>
        </div>

        <div class="flex gap-2 border-b border-slate-200 overflow-x-auto pb-px scrollbar-thin flex-nowrap">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                type="button"
                @click="activeTab = tab.id"
                :class="[
                    'px-4 py-2 text-sm font-medium rounded-t-lg transition-colors whitespace-nowrap shrink-0',
                    activeTab === tab.id
                        ? 'bg-white border border-b-0 border-slate-200 text-slate-900'
                        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50',
                ]"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- New search -->
        <div v-show="activeTab === 'search'" class="relative bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            <div
                v-if="discoveryBusy"
                class="absolute inset-0 z-10 rounded-xl bg-white/80 backdrop-blur-[1px] flex flex-col items-center justify-center gap-3"
                aria-live="polite"
                aria-busy="true"
            >
                <span class="inline-block h-10 w-10 rounded-full border-2 border-emerald-600 border-t-transparent animate-spin" />
                <p class="text-sm font-medium text-slate-800">Searching Google Places…</p>
                <p class="text-xs text-slate-500 px-6 text-center max-w-md">
                    Fetching nearby and text results, then place details. This can take a minute when loading many new businesses.
                </p>
            </div>
            <h2 class="text-lg font-semibold text-slate-900">New discovery</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">UK postcode</label>
                    <input
                        v-model="form.postcode"
                        type="text"
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 uppercase"
                        placeholder="e.g. M1 1AE"
                    >
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Radius (meters)</label>
                    <input
                        v-model.number="form.radius_meters"
                        type="number"
                        min="500"
                        max="50000"
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    >
                </div>
                <div class="flex items-end">
                    <label class="flex items-center gap-2 cursor-pointer pb-2">
                        <input v-model="form.enrich_email" type="checkbox" class="rounded border-slate-300 text-emerald-600" />
                        <span class="text-sm text-slate-700">Scrape website for email &amp; phone (this run)</span>
                    </label>
                </div>
            </div>
            <div class="flex flex-wrap gap-3">
                <button
                    type="button"
                    :disabled="starting || !form.postcode.trim()"
                    @click="startRun"
                    class="px-5 py-2.5 bg-emerald-700 text-white rounded-lg hover:bg-emerald-800 disabled:opacity-50 font-medium text-sm"
                >
                    {{ starting ? 'Starting…' : 'Run discovery' }}
                </button>
            </div>
            <div v-if="activeRun" class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm space-y-1">
                <p class="font-medium text-slate-800">Run #{{ activeRun.id }} — {{ activeRun.status }}</p>
                <p v-if="activeRun.error_message" class="text-red-700">{{ activeRun.error_message }}</p>
                <p v-else class="text-slate-600">
                    New this run: {{ activeRun.new_count }} · Already saved (same place): {{ activeRun.duplicate_count }} · Errors: {{ activeRun.error_count }} · Place details fetched:
                    {{ activeRun.details_fetched }}
                </p>
                <p v-if="activeRun.meta?.stopped_reason && activeRun.status === 'completed'" class="text-xs text-slate-500">
                    <span v-if="activeRun.meta.stopped_reason === 'new_target_reached'">Stopped after reaching the per-run new-business target (see Settings).</span>
                    <span v-else-if="activeRun.meta.stopped_reason === 'text_search_exhausted'">No more text-search pages from Google for this query.</span>
                    <span v-else-if="activeRun.meta.stopped_reason === 'max_text_pages_cap'">Stopped at the safety limit for text-search pages; increase radius or run again for more.</span>
                </p>
                <p v-if="activeRun.meta?.text_search_query && activeRun.status === 'completed'" class="text-xs text-sky-800">
                    <span class="font-medium">Text search:</span> {{ activeRun.meta.text_search_query }}
                </p>
                <p v-if="activeRun.status === 'completed' && activeRun.meta?.ingest_skipped && Object.keys(activeRun.meta.ingest_skipped).length" class="text-xs text-slate-600">
                    <span class="font-medium">Not saved (small-business filters):</span>
                    {{ formatIngestSkipped(activeRun.meta.ingest_skipped) }}
                </p>
            </div>
        </div>

        <!-- Saved contacts -->
        <div v-show="activeTab === 'contacts'" class="space-y-4">
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 space-y-3">
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="min-w-[140px] flex-1">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
                        <input
                            v-model="filters.q"
                            type="search"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                            placeholder="Name, phone, email…"
                            @keyup.enter="loadContacts(1)"
                        >
                    </div>
                    <div class="w-36">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Postcode</label>
                        <input
                            v-model="filters.postcode"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm uppercase"
                            placeholder="Filter"
                            @keyup.enter="loadContacts(1)"
                        >
                    </div>
                    <div class="w-44">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Prospect</label>
                        <select
                            v-model="filters.prospect"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white"
                            @change="loadContacts(1)"
                        >
                            <option value="">All</option>
                            <option value="1">CC prospects</option>
                            <option value="0">Not marked</option>
                        </select>
                    </div>
                    <button type="button" class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm" @click="loadContacts(1)">Apply</button>
                    <button
                        type="button"
                        class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50"
                        @click="exportCsv"
                    >
                        Export CSV
                    </button>
                    <button
                        type="button"
                        :disabled="enrichingWebsites"
                        class="px-4 py-2 border border-emerald-300 bg-emerald-50 text-emerald-900 rounded-lg text-sm font-medium hover:bg-emerald-100 disabled:opacity-50"
                        title="Opens homepage + /contact-style pages and extracts mailto/tel and visible text (UK numbers). Respects current filters; max 25 rows per click."
                        @click="bulkEnrichWebsites"
                    >
                        {{ enrichingWebsites ? 'Scraping sites…' : 'Fill from websites' }}
                    </button>
                </div>
                <div class="flex flex-wrap gap-3 items-end pt-1 border-t border-slate-100">
                    <div class="w-40">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Email on record</label>
                        <select v-model="filters.missing_email" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white" @change="loadContacts(1)">
                            <option value="">Any</option>
                            <option value="1">Missing email</option>
                            <option value="0">Has email</option>
                        </select>
                    </div>
                    <div class="w-40">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Website</label>
                        <select v-model="filters.has_website" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white" @change="loadContacts(1)">
                            <option value="">Any</option>
                            <option value="1">Has website</option>
                            <option value="0">No website</option>
                        </select>
                    </div>
                    <div class="w-28">
                        <label class="block text-xs font-medium text-slate-600 mb-1" title="Google review count; blank = no limit. Null/unknown counts as passing (often smaller listings).">Max reviews</label>
                        <input
                            v-model.number="filters.max_reviews"
                            type="number"
                            min="0"
                            max="50000"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                            placeholder="Any"
                            @keyup.enter="loadContacts(1)"
                        >
                    </div>
                    <div class="min-w-[180px] flex-1 max-w-md">
                        <label class="block text-xs font-medium text-slate-600 mb-1">Exclude name contains</label>
                        <input
                            v-model="filters.exclude_name"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm"
                            placeholder="e.g. Tesco, McDonalds, Sainsbury"
                            @keyup.enter="loadContacts(1)"
                        >
                    </div>
                </div>
            </div>
            <p class="text-xs text-slate-500 -mt-2">
                Use <strong>Missing email</strong> + <strong>Has website</strong> for a calling/scrape queue. <strong>Max reviews</strong> biases toward quieter listings (imperfect proxy for “smaller”). <strong>Exclude name</strong> drops rows whose name contains any comma-separated term. Scraping cannot read JS-only or Cloudflare sites — see note below.
            </p>
            <p class="text-xs text-slate-500 -mt-2">
                Each row with an empty email and a <strong>website</strong> has <strong>Scrape site for email</strong> — found addresses save immediately; you can still edit the field. Bulk “Fill from websites” does the same for up to 25 rows per click. Many sites hide details or block bots — best-effort only; use only where you have a lawful basis to contact businesses.
            </p>

            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-3 py-2 font-medium">Name</th>
                            <th class="px-3 py-2 font-medium">Phone</th>
                            <th class="px-3 py-2 font-medium">Email</th>
                            <th class="px-3 py-2 font-medium">Website</th>
                            <th class="px-3 py-2 font-medium">Address</th>
                            <th class="px-3 py-2 font-medium">Rating</th>
                            <th class="px-3 py-2 font-medium w-44">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in contacts" :key="c.id" class="border-t border-slate-100 hover:bg-slate-50/80">
                            <td class="px-3 py-2">
                                <div class="font-medium text-slate-900">{{ c.name || '—' }}</div>
                                <div v-if="c.prospect_marked_at || c.crm_customer_id" class="flex flex-wrap gap-1 mt-1">
                                    <span
                                        v-if="c.prospect_marked_at"
                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-violet-100 text-violet-800"
                                    >CC prospect</span>
                                    <span
                                        v-if="c.crm_customer_id"
                                        class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-200 text-slate-800"
                                    >CRM #{{ c.crm_customer_id }}</span>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-slate-700 whitespace-nowrap">{{ c.phone || c.international_phone || '—' }}</td>
                            <td class="px-3 py-2 align-top">
                                <input
                                    v-model="c.email"
                                    type="email"
                                    class="w-full min-w-[8rem] px-2 py-1 border border-slate-200 rounded text-xs"
                                    placeholder="—"
                                    @input="clearScrapeHint(c.id)"
                                    @change="saveContactEmail(c)"
                                >
                                <template v-if="isEmailEmpty(c)">
                                    <button
                                        v-if="c.website"
                                        type="button"
                                        :disabled="scrapingContactId === c.id"
                                        class="mt-1.5 block w-full text-left text-[11px] font-medium text-emerald-700 hover:text-emerald-800 hover:underline disabled:opacity-50 disabled:no-underline"
                                        title="Opens the site like bulk “Fill from websites” and saves email/phone if found."
                                        @click="scrapeSiteForContact(c)"
                                    >
                                        {{ scrapingContactId === c.id ? 'Scraping site…' : 'Scrape site for email' }}
                                    </button>
                                    <p v-else class="mt-1.5 text-[10px] text-slate-400">Add a website URL to scrape for email.</p>
                                    <p v-if="scrapeHints[c.id]" class="mt-1 text-[10px] text-amber-800 leading-snug">{{ scrapeHints[c.id] }}</p>
                                </template>
                                <span v-if="c.email_source" class="block text-[10px] text-slate-400 mt-0.5">
                                    {{ c.email_source === 'enrichment_claude' ? 'Claude AI' : c.email_source }}
                                </span>
                            </td>
                            <td class="px-3 py-2">
                                <a v-if="c.website" :href="c.website" target="_blank" rel="noopener" class="text-emerald-700 underline truncate max-w-[10rem] inline-block">Link</a>
                                <span v-else>—</span>
                            </td>
                            <td class="px-3 py-2 text-slate-600 max-w-xs truncate" :title="c.formatted_address">{{ c.formatted_address || '—' }}</td>
                            <td class="px-3 py-2 text-slate-600 whitespace-nowrap">
                                <span v-if="c.rating != null">{{ c.rating }} ({{ c.user_rating_count ?? 0 }})</span>
                                <span v-else>—</span>
                            </td>
                            <td class="px-3 py-2 space-y-1">
                                <button
                                    type="button"
                                    :disabled="!!c.crm_customer_id"
                                    class="block w-full text-left text-xs text-violet-700 hover:underline disabled:text-slate-400 disabled:no-underline"
                                    title="Creates a CRM customer (type prospect) with this listing’s details and links it here."
                                    @click="convertToProspect(c)"
                                >
                                    {{ c.crm_customer_id ? 'In CRM as prospect' : 'Convert to prospect' }}
                                </button>
                                <a
                                    v-if="c.google_maps_uri"
                                    :href="c.google_maps_uri"
                                    target="_blank"
                                    rel="noopener"
                                    class="block text-xs text-slate-500 hover:underline"
                                >Maps</a>
                            </td>
                        </tr>
                        <tr v-if="!contacts.length && !loadingContacts">
                            <td colspan="7" class="px-3 py-8 text-center text-slate-500">No contacts yet. Run a discovery or adjust filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="contactsMeta.total" class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600">
                <span>Page {{ contactsMeta.current_page }} / {{ contactsMeta.last_page }} ({{ contactsMeta.total }} total)</span>
                <div class="flex gap-2">
                    <button
                        type="button"
                        :disabled="contactsMeta.current_page <= 1"
                        class="px-3 py-1 border rounded-lg disabled:opacity-40"
                        @click="loadContacts(contactsMeta.current_page - 1)"
                    >
                        Prev
                    </button>
                    <button
                        type="button"
                        :disabled="contactsMeta.current_page >= contactsMeta.last_page"
                        class="px-3 py-1 border rounded-lg disabled:opacity-40"
                        @click="loadContacts(contactsMeta.current_page + 1)"
                    >
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- Activity -->
        <div v-show="activeTab === 'activity'" class="space-y-4">
            <div class="flex justify-between items-center">
                <h2 class="text-lg font-semibold text-slate-900">Discovery runs</h2>
                <button type="button" class="text-sm text-emerald-700 hover:underline" @click="loadRuns">Refresh</button>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-3 py-2 font-medium">ID</th>
                            <th class="px-3 py-2 font-medium">User</th>
                            <th class="px-3 py-2 font-medium">Postcode</th>
                            <th class="px-3 py-2 font-medium">Radius</th>
                            <th class="px-3 py-2 font-medium">Status</th>
                            <th class="px-3 py-2 font-medium">New / Dup / Err</th>
                            <th class="px-3 py-2 font-medium">Finished</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in runs" :key="r.id" class="border-t border-slate-100">
                            <td class="px-3 py-2">{{ r.id }}</td>
                            <td class="px-3 py-2">{{ r.user?.name || r.user_id }}</td>
                            <td class="px-3 py-2 font-mono">{{ r.postcode_input }}</td>
                            <td class="px-3 py-2">{{ r.radius_meters }}m</td>
                            <td class="px-3 py-2">
                                <span
                                    :class="{
                                        'text-emerald-700': r.status === 'completed',
                                        'text-amber-700': r.status === 'processing' || r.status === 'pending',
                                        'text-red-700': r.status === 'failed',
                                    }"
                                >{{ r.status }}</span>
                            </td>
                            <td class="px-3 py-2">{{ r.new_count }} / {{ r.duplicate_count }} / {{ r.error_count }}</td>
                            <td class="px-3 py-2 text-slate-500 whitespace-nowrap">{{ r.finished_at || '—' }}</td>
                        </tr>
                        <tr v-if="!runs.length && !loadingRuns">
                            <td colspan="7" class="px-3 py-8 text-center text-slate-500">No runs yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h3 class="text-md font-semibold text-slate-900 pt-4">Export logs</h3>
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-600">
                        <tr>
                            <th class="px-3 py-2 font-medium">When</th>
                            <th class="px-3 py-2 font-medium">User</th>
                            <th class="px-3 py-2 font-medium">Rows</th>
                            <th class="px-3 py-2 font-medium">Filters</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in exportLogs" :key="log.id" class="border-t border-slate-100">
                            <td class="px-3 py-2 whitespace-nowrap">{{ log.created_at }}</td>
                            <td class="px-3 py-2">{{ log.user?.name || log.user_id }}</td>
                            <td class="px-3 py-2">{{ log.row_count }}</td>
                            <td class="px-3 py-2 text-slate-500 text-xs font-mono">{{ JSON.stringify(log.filters) }}</td>
                        </tr>
                        <tr v-if="!exportLogs.length">
                            <td colspan="4" class="px-3 py-6 text-center text-slate-500">No exports yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

function formatIngestSkipped(obj) {
    const labels = {
        skipped_high_review_count: 'too many Google reviews',
        skipped_excluded_name: 'name matched blocklist',
        skipped_excluded_place_type: 'chain / mall / supermarket type',
    };
    return Object.entries(obj)
        .map(([k, v]) => `${labels[k] || k}: ${v}`)
        .join(' · ');
}

const tabs = [
    { id: 'search', label: 'New search' },
    { id: 'contacts', label: 'Saved contacts' },
    { id: 'activity', label: 'Activity & logs' },
];

const activeTab = ref('search');
const settingsStatus = ref(null);
const starting = ref(false);
const form = reactive({
    postcode: '',
    radius_meters: 5000,
    enrich_email: false,
});

const activeRun = ref(null);
let pollTimer = null;

const discoveryBusy = computed(() => {
    if (starting.value) {
        return true;
    }
    const r = activeRun.value;
    return !!(r && (r.status === 'pending' || r.status === 'processing'));
});

const filters = reactive({
    q: '',
    postcode: '',
    prospect: '',
    missing_email: '',
    has_website: '',
    max_reviews: '',
    exclude_name: '',
});
const contacts = ref([]);
const contactsMeta = ref({ current_page: 1, last_page: 1, per_page: 25, total: 0 });
const loadingContacts = ref(false);
const enrichingWebsites = ref(false);
const scrapingContactId = ref(null);
/** Row id → message when last scrape found nothing (or error). Cleared when user edits email. */
const scrapeHints = ref({});

const runs = ref([]);
const loadingRuns = ref(false);
const exportLogs = ref([]);

async function loadSettingsStatus() {
    try {
        const { data } = await axios.get('/api/cold-calling/settings-status');
        settingsStatus.value = data;
    } catch {
        settingsStatus.value = { configured: false, message: 'Could not load API status.' };
    }
}

async function startRun() {
    starting.value = true;
    try {
        const { data } = await axios.post('/api/cold-calling/runs', {
            postcode: form.postcode.trim(),
            radius_meters: form.radius_meters,
            enrich_email: form.enrich_email,
        });
        activeRun.value = data.run;
        toast.success(data.message || 'Discovery started');
        pollRun();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to start discovery');
    } finally {
        starting.value = false;
    }
}

function pollRun() {
    if (pollTimer) clearInterval(pollTimer);
    if (!activeRun.value?.id) return;
    pollTimer = setInterval(async () => {
        try {
            const { data } = await axios.get(`/api/cold-calling/runs/${activeRun.value.id}`);
            activeRun.value = data.run;
            if (['completed', 'failed'].includes(data.run.status)) {
                clearInterval(pollTimer);
                pollTimer = null;
                loadContacts(1);
                loadRuns();
            }
        } catch {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }, 2500);
}

function contactListParams() {
    const maxRev = filters.max_reviews;
    return {
        q: filters.q || undefined,
        postcode: filters.postcode || undefined,
        prospect: filters.prospect || undefined,
        missing_email: filters.missing_email || undefined,
        has_website: filters.has_website || undefined,
        max_reviews: maxRev === '' || maxRev === null || Number.isNaN(Number(maxRev)) ? undefined : Number(maxRev),
        exclude_name: filters.exclude_name?.trim() || undefined,
    };
}

async function loadContacts(page = 1) {
    loadingContacts.value = true;
    try {
        const { data } = await axios.get('/api/cold-calling/contacts', {
            params: {
                page,
                per_page: contactsMeta.value.per_page || 25,
                ...contactListParams(),
            },
        });
        contacts.value = data.data;
        contactsMeta.value = data.meta;
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to load contacts');
    } finally {
        loadingContacts.value = false;
    }
}

function isEmailEmpty(c) {
    return !c?.email || !String(c.email).trim();
}

function clearScrapeHint(id) {
    if (!scrapeHints.value[id]) {
        return;
    }
    const next = { ...scrapeHints.value };
    delete next[id];
    scrapeHints.value = next;
}

async function saveContactEmail(c) {
    try {
        await axios.put(`/api/cold-calling/contacts/${c.id}`, { email: c.email || null });
        toast.success('Saved');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Save failed');
    }
}

async function scrapeSiteForContact(c) {
    const id = c.id;
    scrapingContactId.value = id;
    clearScrapeHint(id);
    try {
        const { data } = await axios.post(`/api/cold-calling/contacts/${id}/enrich-website`);
        if (data.contact) {
            Object.assign(c, data.contact);
        }
        if (data.saved) {
            toast.success(data.message || 'Saved from website');
        } else {
            scrapeHints.value = { ...scrapeHints.value, [id]: data.message || 'No email found — add manually if you have it.' };
        }
    } catch (e) {
        const msg = e.response?.data?.message || 'Could not scrape this site.';
        scrapeHints.value = { ...scrapeHints.value, [id]: msg };
        toast.warning(msg);
    } finally {
        scrapingContactId.value = null;
    }
}

async function convertToProspect(c) {
    try {
        const { data } = await axios.post(`/api/cold-calling/contacts/${c.id}/mark-prospect`);
        if (data.contact) Object.assign(c, data.contact);
        toast.success(data.message || 'Converted to prospect');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Could not convert to prospect');
    }
}

async function bulkEnrichWebsites() {
    enrichingWebsites.value = true;
    try {
        const { data } = await axios.post('/api/cold-calling/contacts/bulk-enrich-websites', {
            limit: 25,
            ...contactListParams(),
        });
        toast.success(data.message || `Updated ${data.updated} of ${data.checked}`);
        await loadContacts(contactsMeta.value.current_page || 1);
    } catch (e) {
        toast.error(e.response?.data?.message || 'Website enrichment failed');
    } finally {
        enrichingWebsites.value = false;
    }
}

async function exportCsv() {
    try {
        const res = await axios.get('/api/cold-calling/contacts/export', {
            params: contactListParams(),
            responseType: 'blob',
        });
        const url = window.URL.createObjectURL(res.data);
        const a = document.createElement('a');
        a.href = url;
        a.download = `cold-calling-${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
        toast.success('Export started');
        loadExportLogs();
    } catch (e) {
        toast.error('Export failed');
    }
}

async function loadRuns() {
    loadingRuns.value = true;
    try {
        const { data } = await axios.get('/api/cold-calling/runs', { params: { per_page: 30 } });
        runs.value = data.data;
    } catch {
        runs.value = [];
    } finally {
        loadingRuns.value = false;
    }
}

async function loadExportLogs() {
    try {
        const { data } = await axios.get('/api/cold-calling/export-logs');
        exportLogs.value = data.data;
    } catch {
        exportLogs.value = [];
    }
}

watch(activeTab, (t) => {
    if (t === 'contacts') loadContacts(1);
    if (t === 'activity') {
        loadRuns();
        loadExportLogs();
    }
});

onMounted(async () => {
    await loadSettingsStatus();
    await loadContacts(1);
});

onUnmounted(() => {
    if (pollTimer) clearInterval(pollTimer);
});
</script>
