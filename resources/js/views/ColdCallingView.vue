<template>
    <ListingPageShell
        title="Cold calling"
        subtitle="UK businesses discovered near a postcode via Google Places, newest saved listings first — converting one to a prospect also creates a separate CRM customer row."
        :badge="contactsBadge"
    >
        <template #actions>
            <BaseButton variant="primary" block-mobile @click="exportCsv">
                <template #icon><ArrowDownTrayIcon class="icon-sm" aria-hidden="true" /></template>
                Export CSV
            </BaseButton>
            <BaseButton
                variant="outline"
                block-mobile
                :loading="enrichingWebsites"
                title="Opens homepage + /contact-style pages and extracts mailto/tel and visible text (UK numbers). Respects current filters; max 25 rows per click."
                @click="bulkEnrichWebsites"
            >
                <template #icon><LinkIcon class="icon-sm" aria-hidden="true" /></template>
                {{ enrichingWebsites ? 'Scraping sites…' : 'Fill from websites' }}
            </BaseButton>
        </template>

        <template #filters>
            <div class="space-y-4">
                <div class="tab-list" role="tablist" aria-label="Cold calling sections">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.id ? 'true' : 'false'"
                        :class="['tab', activeTab === tab.id ? 'tab-active' : '']"
                        @click="activeTab = tab.id"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div v-show="activeTab === 'contacts'" class="listing-filters-row">
                    <div class="w-full sm:flex-1 sm:min-w-[12rem] sm:max-w-md">
                        <label class="listing-label" for="coldcallingview-search">Search</label>
                        <div class="relative">
                            <MagnifyingGlassIcon
                                class="icon absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"
                                aria-hidden="true"
                            />
                            <input
                                id="coldcallingview-search"
                                v-model="filters.q"
                                type="search"
                                class="form-input-search"
                                placeholder="Name, phone, email…"
                                @keyup.enter="loadContacts(1)"
                            >
                        </div>
                    </div>
                    <div class="w-full sm:w-36">
                        <label class="listing-label" for="coldcallingview-postcode">Postcode</label>
                        <input
                            id="coldcallingview-postcode"
                            v-model="filters.postcode"
                            type="text"
                            class="form-input uppercase"
                            placeholder="Filter"
                            @keyup.enter="loadContacts(1)"
                        >
                    </div>
                    <div class="w-full sm:w-44">
                        <label class="listing-label" for="coldcallingview-prospect">Prospect</label>
                        <select
                            id="coldcallingview-prospect"
                            v-model="filters.prospect"
                            class="form-select"
                            @change="loadContacts(1)"
                        >
                            <option value="">All</option>
                            <option value="1">CC prospects</option>
                            <option value="0">Not marked</option>
                        </select>
                    </div>
                    <div class="w-full sm:w-40">
                        <label class="listing-label" for="coldcallingview-email-on-record">Email on record</label>
                        <select
                            id="coldcallingview-email-on-record"
                            v-model="filters.missing_email"
                            class="form-select"
                            @change="loadContacts(1)"
                        >
                            <option value="">Any</option>
                            <option value="1">Missing email</option>
                            <option value="0">Has email</option>
                        </select>
                    </div>
                    <div class="w-full sm:w-40">
                        <label class="listing-label" for="coldcallingview-website">Website</label>
                        <select
                            id="coldcallingview-website"
                            v-model="filters.has_website"
                            class="form-select"
                            @change="loadContacts(1)"
                        >
                            <option value="">Any</option>
                            <option value="1">Has website</option>
                            <option value="0">No website</option>
                        </select>
                    </div>
                    <div class="w-full sm:w-32">
                        <label
                            class="listing-label"
                            title="Google review count; blank = no limit. Null/unknown counts as passing (often smaller listings)."
                            for="coldcallingview-max-reviews"
                        >Max reviews</label>
                        <input
                            id="coldcallingview-max-reviews"
                            v-model.number="filters.max_reviews"
                            type="number"
                            min="0"
                            max="50000"
                            class="form-input"
                            placeholder="Any"
                            @keyup.enter="loadContacts(1)"
                        >
                    </div>
                    <div class="w-full sm:flex-1 sm:min-w-[12rem] sm:max-w-md">
                        <label class="listing-label" for="coldcallingview-exclude-name-contains">Exclude name contains</label>
                        <input
                            id="coldcallingview-exclude-name-contains"
                            v-model="filters.exclude_name"
                            type="text"
                            class="form-input"
                            placeholder="e.g. Tesco, McDonalds, Sainsbury"
                            @keyup.enter="loadContacts(1)"
                        >
                    </div>
                    <BaseButton variant="soft" block-mobile class="shrink-0" @click="loadContacts(1)">
                        <template #icon><FunnelIcon class="icon-sm" aria-hidden="true" /></template>
                        Apply
                    </BaseButton>
                </div>
            </div>
        </template>

        <template #toolbar>
            <div class="space-y-3">
                <div
                    v-if="settingsStatus"
                    class="callout flex flex-wrap items-center gap-3"
                    :class="settingsStatus.configured ? 'callout-success' : 'callout-warning'"
                >
                    <CheckCircleIcon v-if="settingsStatus.configured" class="icon shrink-0" aria-hidden="true" />
                    <ExclamationTriangleIcon v-else class="icon shrink-0" aria-hidden="true" />
                    <span class="min-w-0">{{ settingsStatus.message }}</span>
                    <router-link
                        to="/settings"
                        class="link font-semibold"
                    >
                        Open Settings → Cold calling
                    </router-link>
                </div>

                <div
                    v-if="settingsStatus?.claude_message"
                    class="callout"
                    :class="settingsStatus.claude_enrichment_enabled ? 'callout-info' : 'border-slate-200 bg-slate-50 text-slate-700'"
                >
                    <span class="font-semibold">Claude AI:</span>
                    {{ settingsStatus.claude_message }}
                </div>

                <p class="text-xs text-slate-500">
                    By default discovery runs in the same request (no queue worker). If you set
                    <code class="kbd">COLD_CALLING_RUN_SYNC=false</code> in
                    <code class="kbd">.env</code>, run
                    <code class="kbd">php artisan queue:work</code> so jobs are processed.
                </p>
            </div>
        </template>

        <!-- New search -->
        <div v-show="activeTab === 'search'" class="p-4 sm:p-6">
            <div class="card relative p-5 sm:p-6 space-y-4">
                <div
                    v-if="discoveryBusy"
                    class="absolute inset-0 z-sticky rounded-card bg-white/80 backdrop-blur-[1px] flex flex-col items-center justify-center gap-3"
                    role="status"
                    aria-live="polite"
                    aria-busy="true"
                >
                    <span class="spinner w-10 h-10 text-primary-600" aria-hidden="true" />
                    <p class="text-sm font-medium text-slate-800">Searching Google Places…</p>
                    <p class="text-xs text-slate-500 px-6 text-center max-w-md">
                        Fetching nearby and text results, then place details. This can take a minute when loading many new businesses.
                    </p>
                </div>

                <h2 class="card-title">New discovery</h2>

                <div class="form-grid-3">
                    <div>
                        <label class="form-label" for="coldcallingview-uk-postcode">UK postcode</label>
                        <input
                            id="coldcallingview-uk-postcode"
                            v-model="form.postcode"
                            type="text"
                            class="form-input uppercase"
                            placeholder="e.g. M1 1AE"
                        >
                    </div>
                    <div>
                        <label class="form-label" for="coldcallingview-radius-meters">Radius (meters)</label>
                        <input
                            id="coldcallingview-radius-meters"
                            v-model.number="form.radius_meters"
                            type="number"
                            min="500"
                            max="50000"
                            class="form-input"
                        >
                    </div>
                    <div class="flex items-end">
                        <label class="form-choice cursor-pointer">
                            <input v-model="form.enrich_email" type="checkbox" class="form-checkbox" />
                            <span class="text-sm text-slate-700">Scrape website for email &amp; phone (this run)</span>
                        </label>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <BaseButton
                        variant="primary"
                        block-mobile
                        :disabled="starting || !form.postcode.trim()"
                        @click="startRun"
                    >
                        <template #icon><MagnifyingGlassIcon class="icon-sm" aria-hidden="true" /></template>
                        {{ starting ? 'Starting…' : 'Run discovery' }}
                    </BaseButton>
                </div>

                <div v-if="activeRun" class="callout callout-info space-y-1">
                    <p class="font-semibold">Run #{{ activeRun.id }} — {{ activeRun.status }}</p>
                    <p v-if="activeRun.error_message" class="text-danger-700">{{ activeRun.error_message }}</p>
                    <p v-else>
                        New this run: {{ activeRun.new_count }} · Already saved (same place): {{ activeRun.duplicate_count }} · Errors: {{ activeRun.error_count }} · Place details fetched:
                        {{ activeRun.details_fetched }}
                    </p>
                    <p v-if="activeRun.meta?.stopped_reason && activeRun.status === 'completed'" class="text-xs">
                        <span v-if="activeRun.meta.stopped_reason === 'new_target_reached'">Stopped after reaching the per-run new-business target (see Settings).</span>
                        <span v-else-if="activeRun.meta.stopped_reason === 'text_search_exhausted'">No more text-search pages from Google for this query.</span>
                        <span v-else-if="activeRun.meta.stopped_reason === 'max_text_pages_cap'">Stopped at the safety limit for text-search pages; increase radius or run again for more.</span>
                    </p>
                    <p v-if="activeRun.meta?.text_search_query && activeRun.status === 'completed'" class="text-xs">
                        <span class="font-semibold">Text search:</span> {{ activeRun.meta.text_search_query }}
                    </p>
                    <p v-if="activeRun.status === 'completed' && activeRun.meta?.ingest_skipped && Object.keys(activeRun.meta.ingest_skipped).length" class="text-xs">
                        <span class="font-semibold">Not saved (small-business filters):</span>
                        {{ formatIngestSkipped(activeRun.meta.ingest_skipped) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Saved contacts -->
        <div v-show="activeTab === 'contacts'">
            <div class="px-4 sm:px-6 pt-4 space-y-2">
                <p class="text-xs text-slate-500">
                    Use <strong>Missing email</strong> + <strong>Has website</strong> for a calling/scrape queue. <strong>Max reviews</strong> biases toward quieter listings (imperfect proxy for “smaller”). <strong>Exclude name</strong> drops rows whose name contains any comma-separated term. Scraping cannot read JS-only or Cloudflare sites — see note below.
                </p>
                <p class="text-xs text-slate-500">
                    Each row with an empty email and a <strong>website</strong> has <strong>Scrape site for email</strong> — found addresses save immediately; you can still edit the field. Bulk “Fill from websites” does the same for up to 25 rows per click. Many sites hide details or block bots — best-effort only; use only where you have a lawful basis to contact businesses.
                </p>
            </div>

            <div v-if="loadingContacts" class="p-4 sm:p-6 space-y-3" aria-busy="true">
                <span v-for="n in 6" :key="n" class="skeleton-text block w-full" />
            </div>

            <EmptyState
                v-else-if="!contacts.length"
                heading="No contacts yet"
                description="Run a discovery for a UK postcode on the New search tab, or widen the filters above."
            >
                <template #icon><BuildingOfficeIcon class="icon" aria-hidden="true" /></template>
                <template #action>
                    <BaseButton variant="outline" @click="activeTab = 'search'">Start a discovery</BaseButton>
                </template>
            </EmptyState>

            <div v-else class="table-wrap">
                <table class="table" style="min-width: 960px">
                    <caption class="sr-only">Saved cold calling contacts</caption>
                    <thead class="table-thead">
                        <tr>
                            <th scope="col" class="table-th">Name</th>
                            <th scope="col" class="table-th">Phone</th>
                            <th scope="col" class="table-th">Email</th>
                            <th scope="col" class="table-th">Website</th>
                            <th scope="col" class="table-th">Address</th>
                            <th scope="col" class="table-th">Rating</th>
                            <th scope="col" class="table-th w-44">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="c in contacts" :key="c.id" class="table-row">
                            <td class="table-td">
                                <div class="font-semibold text-slate-900">{{ c.name || '—' }}</div>
                                <div v-if="c.prospect_marked_at || c.crm_customer_id" class="flex flex-wrap gap-1 mt-1">
                                    <BaseBadge v-if="c.prospect_marked_at" tone="primary">CC prospect</BaseBadge>
                                    <BaseBadge v-if="c.crm_customer_id" tone="neutral">CRM #{{ c.crm_customer_id }}</BaseBadge>
                                </div>
                            </td>
                            <td class="table-td whitespace-nowrap">{{ c.phone || c.international_phone || '—' }}</td>
                            <td class="table-td align-top">
                                <label class="sr-only" :for="`coldcallingview-contact-email-${c.id}`">
                                    Email for {{ c.name || `contact #${c.id}` }}
                                </label>
                                <input
                                    :id="`coldcallingview-contact-email-${c.id}`"
                                    v-model="c.email"
                                    type="email"
                                    class="form-input min-w-[8rem] text-sm"
                                    placeholder="—"
                                    @input="clearScrapeHint(c.id)"
                                    @change="saveContactEmail(c)"
                                >
                                <template v-if="isEmailEmpty(c)">
                                    <BaseButton
                                        v-if="c.website"
                                        variant="ghost"
                                        size="sm"
                                        class="mt-1.5"
                                        :loading="scrapingContactId === c.id"
                                        title="Opens the site like bulk “Fill from websites” and saves email/phone if found."
                                        @click="scrapeSiteForContact(c)"
                                    >
                                        {{ scrapingContactId === c.id ? 'Scraping site…' : 'Scrape site for email' }}
                                    </BaseButton>
                                    <p v-else class="mt-1.5 text-xs text-slate-500">Add a website URL to scrape for email.</p>
                                    <p v-if="scrapeHints[c.id]" class="mt-1 text-xs text-warning-800 leading-snug">{{ scrapeHints[c.id] }}</p>
                                </template>
                                <span v-if="c.email_source" class="block text-xs text-slate-500 mt-0.5">
                                    {{ c.email_source === 'enrichment_claude' ? 'Claude AI' : c.email_source }}
                                </span>
                            </td>
                            <td class="table-td">
                                <a
                                    v-if="c.website"
                                    :href="c.website"
                                    target="_blank"
                                    rel="noopener"
                                    class="link truncate max-w-[10rem] inline-block"
                                >Link</a>
                                <span v-else>—</span>
                            </td>
                            <td class="table-td max-w-xs truncate" :title="c.formatted_address">{{ c.formatted_address || '—' }}</td>
                            <td class="table-td whitespace-nowrap">
                                <span v-if="c.rating != null">{{ c.rating }} ({{ c.user_rating_count ?? 0 }})</span>
                                <span v-else>—</span>
                            </td>
                            <td class="table-td space-y-1">
                                <BaseButton
                                    variant="ghost"
                                    size="sm"
                                    :disabled="!!c.crm_customer_id"
                                    title="Creates a CRM customer (type prospect) with this listing’s details and links it here."
                                    @click="convertToProspect(c)"
                                >
                                    <template #icon><UserPlusIcon class="icon-sm" aria-hidden="true" /></template>
                                    {{ c.crm_customer_id ? 'In CRM as prospect' : 'Convert to prospect' }}
                                </BaseButton>
                                <a
                                    v-if="c.google_maps_uri"
                                    :href="c.google_maps_uri"
                                    target="_blank"
                                    rel="noopener"
                                    class="link block text-xs"
                                >Maps</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Activity -->
        <div v-show="activeTab === 'activity'" class="p-4 sm:p-6 space-y-4">
            <div class="flex flex-wrap justify-between items-center gap-3">
                <h2 class="card-title">Discovery runs</h2>
                <BaseButton variant="outline" @click="loadRuns">
                    <template #icon><ArrowPathIcon class="icon-sm" aria-hidden="true" /></template>
                    Refresh
                </BaseButton>
            </div>

            <div class="card overflow-hidden">
                <div v-if="loadingRuns" class="p-4 space-y-3" aria-busy="true">
                    <span v-for="n in 4" :key="n" class="skeleton-text block w-full" />
                </div>
                <EmptyState
                    v-else-if="!runs.length"
                    heading="No discovery runs yet"
                    description="Start one from the New search tab to see its progress and counts here."
                >
                    <template #icon><ListBulletIcon class="icon" aria-hidden="true" /></template>
                </EmptyState>
                <div v-else class="table-wrap">
                    <table class="table" style="min-width: 720px">
                        <caption class="sr-only">Recent Google Places discovery runs</caption>
                        <thead class="table-thead">
                            <tr>
                                <th scope="col" class="table-th">ID</th>
                                <th scope="col" class="table-th">User</th>
                                <th scope="col" class="table-th">Postcode</th>
                                <th scope="col" class="table-th">Radius</th>
                                <th scope="col" class="table-th">Status</th>
                                <th scope="col" class="table-th">New / Dup / Err</th>
                                <th scope="col" class="table-th">Finished</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in runs" :key="r.id" class="table-row">
                                <td class="table-td">{{ r.id }}</td>
                                <td class="table-td">{{ r.user?.name || r.user_id }}</td>
                                <td class="table-td font-mono">{{ r.postcode_input }}</td>
                                <td class="table-td">{{ r.radius_meters }}m</td>
                                <td class="table-td">
                                    <BaseBadge :tone="runTone(r.status)">{{ r.status }}</BaseBadge>
                                </td>
                                <td class="table-td">{{ r.new_count }} / {{ r.duplicate_count }} / {{ r.error_count }}</td>
                                <td class="table-td whitespace-nowrap">{{ r.finished_at || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <h3 class="card-title pt-2">Export logs</h3>
            <div class="card overflow-hidden">
                <EmptyState
                    v-if="!exportLogs.length"
                    heading="No exports yet"
                    description="Every CSV export of this list is recorded here with its row count and filters."
                >
                    <template #icon><DocumentTextIcon class="icon" aria-hidden="true" /></template>
                </EmptyState>
                <div v-else class="table-wrap">
                    <table class="table" style="min-width: 640px">
                        <caption class="sr-only">Cold calling CSV export history</caption>
                        <thead class="table-thead">
                            <tr>
                                <th scope="col" class="table-th">When</th>
                                <th scope="col" class="table-th">User</th>
                                <th scope="col" class="table-th">Rows</th>
                                <th scope="col" class="table-th">Filters</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in exportLogs" :key="log.id" class="table-row">
                                <td class="table-td whitespace-nowrap">{{ log.created_at }}</td>
                                <td class="table-td">{{ log.user?.name || log.user_id }}</td>
                                <td class="table-td">{{ log.row_count }}</td>
                                <td class="table-td text-xs font-mono">{{ JSON.stringify(log.filters) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <template #pagination>
            <Pagination
                v-if="activeTab === 'contacts' && contactsMeta.total"
                :pagination="contactsMeta"
                embedded
                result-label="contacts"
                singular-label="contact"
                @page-change="loadContacts"
            />
        </template>
    </ListingPageShell>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted, watch } from 'vue';
import axios from 'axios';
import {
    ArrowDownTrayIcon,
    ArrowPathIcon,
    BuildingOfficeIcon,
    CheckCircleIcon,
    DocumentTextIcon,
    ExclamationTriangleIcon,
    FunnelIcon,
    LinkIcon,
    ListBulletIcon,
    MagnifyingGlassIcon,
    UserPlusIcon,
} from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseBadge, BaseButton, EmptyState } from '@/components/base';

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

const contactsBadge = computed(() =>
    contactsMeta.value?.total ? `${contactsMeta.value.total} Total` : null,
);

function runTone(status) {
    if (status === 'completed') return 'success';
    if (status === 'processing' || status === 'pending') return 'warning';
    if (status === 'failed') return 'danger';
    return 'neutral';
}

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
