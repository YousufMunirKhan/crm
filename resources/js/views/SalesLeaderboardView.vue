<template>
    <div class="page">
        <!-- Controls -->
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0">
                <p class="page-lead">
                    Everybody with a target this month, ranked on how far through it they are.
                </p>
                <p class="mt-1 flex items-center gap-1.5 text-xs text-slate-500" role="status">
                    <span class="relative flex h-2 w-2" aria-hidden="true">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success-400 opacity-75" />
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-success-500" />
                    </span>
                    <span v-if="updatedAt">Live · updated {{ updatedAt }} UK time</span>
                    <span v-else>Loading…</span>
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <div class="tab-list rounded-control border border-slate-200 bg-white" role="group" aria-label="Period">
                    <button
                        v-for="option in PERIODS"
                        :key="option.key"
                        type="button"
                        :class="['tab flex-1 sm:flex-none', period === option.key ? 'tab-active' : '']"
                        :aria-pressed="period === option.key ? 'true' : 'false'"
                        @click="setPeriod(option.key)"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:flex">
                    <BaseButton
                        v-if="canShare"
                        variant="primary"
                        class="col-span-2"
                        :loading="busy === 'share'"
                        :disabled="!board"
                        @click="shareImage"
                    >
                        <template #icon><ShareIcon class="icon-sm" aria-hidden="true" /></template>
                        Share
                    </BaseButton>
                    <BaseButton variant="soft" :loading="busy === 'image'" :disabled="!board" @click="downloadImage">
                        <template #icon><PhotoIcon class="icon-sm" aria-hidden="true" /></template>
                        Image
                    </BaseButton>
                    <BaseButton variant="soft" :loading="busy === 'pdf'" :disabled="!board" @click="downloadPdf">
                        <template #icon><ArrowDownTrayIcon class="icon-sm" aria-hidden="true" /></template>
                        PDF
                    </BaseButton>
                </div>
            </div>
        </div>

        <p v-if="failed && !board" class="callout callout-danger">
            The leaderboard could not be loaded. It will try again in a moment.
        </p>

        <div v-if="!board && !failed" class="space-y-4" aria-hidden="true">
            <div class="skeleton h-36 w-full" />
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div v-for="n in 4" :key="n" class="skeleton h-24" />
            </div>
            <div class="skeleton h-72 w-full" />
        </div>

        <template v-if="board">
            <!-- Team banner -->
            <section class="overflow-hidden rounded-panel bg-gradient-to-br from-primary-600 via-primary-700 to-primary-800 p-4 text-white shadow-card sm:p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3 sm:gap-4">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-white/15 sm:h-14 sm:w-14">
                            <TrophyIcon class="h-7 w-7 sm:h-8 sm:w-8" aria-hidden="true" />
                        </span>
                        <div class="min-w-0">
                            <p class="text-xs font-medium uppercase tracking-wide text-white/75">{{ bannerEyebrow }}</p>
                            <h2 class="text-xl font-bold leading-tight sm:text-2xl">{{ banner.heading }}</h2>
                            <p class="mt-0.5 text-sm text-white/85">{{ banner.detail }}</p>
                        </div>
                    </div>

                    <div v-if="headline" class="shrink-0 sm:w-64">
                        <div class="flex items-baseline justify-between gap-2">
                            <span class="text-3xl font-bold tabular-nums sm:text-4xl">{{ headline.percent }}%</span>
                            <span class="text-sm tabular-nums text-white/85">{{ headline.done }} / {{ headline.target }} {{ headline.unit }}</span>
                        </div>
                        <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-white/20" aria-hidden="true">
                            <div class="h-full rounded-full bg-white transition-all duration-500" :style="{ width: `${clamp(headline.percent)}%` }" />
                        </div>
                    </div>
                </div>

                <p v-if="me" class="mt-4 rounded-control bg-white/10 px-3 py-2 text-sm">
                    You are <strong class="font-semibold">#{{ me.rank }}</strong> of {{ board.rows.length }}
                    <template v-if="me.leads"> · {{ me.leads.done }} of {{ me.leads.target }} leads {{ periodNoun }}</template>
                    <template v-else-if="me.appointments"> · {{ me.appointments.done }} of {{ me.appointments.target }} appointments this month</template>
                </p>
            </section>

            <!-- Team totals -->
            <div :class="['grid grid-cols-2 gap-3', tiles.length > 4 ? 'lg:grid-cols-5' : 'lg:grid-cols-4']">
                <div
                    v-for="tile in tiles"
                    :key="tile.label"
                    class="stat-card"
                >
                    <p class="stat-label">{{ tile.label }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-slate-900 sm:text-3xl">
                        {{ tile.value }}
                        <span v-if="tile.of !== undefined" class="text-sm font-normal text-slate-500">/ {{ tile.of }}</span>
                    </p>
                    <template v-if="tile.percent !== undefined">
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                            <div
                                :class="['h-full rounded-full transition-all duration-500', tile.alt ? 'bg-primary-400' : 'bg-primary-600']"
                                :style="{ width: `${clamp(tile.percent)}%` }"
                            />
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">{{ tile.percent }}% of target</p>
                    </template>
                    <p v-else class="mt-2 text-xs text-slate-500">{{ tile.caption }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
                <!-- The board -->
                <BaseCard class="xl:col-span-2" :padded="false">
                    <template #header>
                        <h2 class="card-title">Team leaderboard</h2>
                        <p class="card-subtitle">{{ board.range.label }}</p>
                    </template>

                    <EmptyState
                        v-if="!board.rows.length"
                        :heading="`Nobody has a target set for ${board.month_label}`"
                        description="People appear here as soon as they are given a daily lead target or a monthly appointment target."
                    >
                        <template #icon><TrophyIcon class="h-6 w-6" aria-hidden="true" /></template>
                        <template v-if="canSetTargets" #action>
                            <BaseButton variant="primary" to="/employees/goals">Set targets</BaseButton>
                        </template>
                    </EmptyState>

                    <div v-else>
                        <div :class="[ROW_GRID, 'hidden border-b border-slate-200 px-6 py-2.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500 md:grid']">
                            <span class="text-center">#</span>
                            <span>Name</span>
                            <span>Leads · {{ period }}</span>
                            <span>Appointments · month</span>
                            <span class="text-right">Overall</span>
                            <span class="text-right">Status</span>
                        </div>

                        <ol>
                            <li
                                v-for="row in board.rows"
                                :key="row.user_id"
                                :class="[
                                    ROW_GRID,
                                    'grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-3 border-b border-slate-100 px-4 py-3 last:border-b-0 sm:px-6',
                                    row.is_me ? 'bg-primary-50' : '',
                                ]"
                            >
                                <span
                                    :class="['grid h-8 w-8 place-items-center justify-self-center rounded-full text-sm font-bold tabular-nums', rankClass(row.rank)]"
                                    :aria-label="`Rank ${row.rank}`"
                                >
                                    {{ row.rank }}
                                </span>

                                <div class="flex min-w-0 items-center gap-3">
                                    <span
                                        class="hidden h-9 w-9 shrink-0 place-items-center rounded-full bg-primary-600 text-xs font-semibold text-white sm:grid"
                                        aria-hidden="true"
                                    >{{ initials(row.name) }}</span>
                                    <div class="min-w-0">
                                        <p class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                                            <span class="truncate">{{ row.name }}</span>
                                            <span v-if="row.is_me" class="badge badge-primary shrink-0">You</span>
                                        </p>
                                        <p v-if="row.role" class="truncate text-xs text-slate-500">{{ row.role }}</p>
                                    </div>
                                </div>

                                <!-- One line on a phone, two columns of the table from md up. -->
                                <div class="order-last col-span-3 grid grid-cols-2 gap-4 md:order-none md:contents">
                                    <div v-for="metric in metrics(row)" :key="metric.key" class="min-w-0">
                                        <p class="mb-1 text-[11px] font-medium uppercase tracking-wide text-slate-500 md:hidden">{{ metric.label }}</p>
                                        <template v-if="metric.value">
                                            <div class="flex items-baseline justify-between gap-2">
                                                <span class="text-sm tabular-nums text-slate-900">
                                                    <strong class="font-semibold">{{ metric.value.done }}</strong>
                                                    <span class="text-slate-500"> / {{ metric.value.target }}</span>
                                                </span>
                                                <span class="text-xs tabular-nums text-slate-500">{{ metric.value.percent }}%</span>
                                            </div>
                                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                                <div
                                                    :class="['h-full rounded-full transition-all duration-500', metric.bar]"
                                                    :style="{ width: `${clamp(metric.value.percent)}%` }"
                                                />
                                            </div>
                                            <p v-if="metric.note" class="mt-1 text-[11px] text-slate-500">{{ metric.note }}</p>
                                        </template>
                                        <p v-else class="text-xs text-slate-400">No target</p>
                                    </div>
                                </div>

                                <span class="hidden text-right text-sm font-semibold tabular-nums text-primary-700 md:block">{{ row.progress }}%</span>

                                <span class="justify-self-end">
                                    <BaseBadge :tone="statusOf(row).tone" dot>{{ statusOf(row).label }}</BaseBadge>
                                </span>
                            </li>
                        </ol>
                    </div>
                </BaseCard>

                <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-1 xl:content-start">
                    <!-- Top performers -->
                    <BaseCard title="Top performers" :subtitle="topSubtitle">
                        <template #actions>
                            <div class="tab-list" role="group" aria-label="Rank by">
                                <button
                                    v-for="option in TOP_BY"
                                    :key="option.key"
                                    type="button"
                                    :class="['tab px-3 py-1.5 text-xs', topBy === option.key ? 'tab-active' : '']"
                                    :aria-pressed="topBy === option.key ? 'true' : 'false'"
                                    @click="topBy = option.key"
                                >
                                    {{ option.label }}
                                </button>
                            </div>
                        </template>

                        <p v-if="!top.length" class="py-4 text-center text-sm text-slate-500">Nothing logged yet.</p>

                        <ol v-else class="space-y-3">
                            <li v-for="(person, i) in top" :key="person.user_id" class="flex items-center gap-3">
                                <span :class="['grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs font-bold', rankClass(i + 1)]">{{ i + 1 }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="truncate text-sm font-medium text-slate-900">{{ person.name }}</span>
                                        <span class="shrink-0 text-xs tabular-nums text-slate-600">{{ person.count }}</span>
                                    </div>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                        <div
                                            :class="['h-full rounded-full', topBy === 'leads' ? 'bg-primary-600' : 'bg-primary-400']"
                                            :style="{ width: `${person.width}%` }"
                                        />
                                    </div>
                                </div>
                            </li>
                        </ol>
                    </BaseCard>

                    <!-- Last few days -->
                    <BaseCard title="Daily progress" subtitle="The team, over the last week">
                        <div class="flex items-center gap-4 text-xs text-slate-600">
                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-primary-600" aria-hidden="true" />Leads</span>
                            <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-primary-300" aria-hidden="true" />Appointments</span>
                            <span v-if="chartTarget" class="flex items-center gap-1.5">
                                <span class="w-4 border-t-2 border-dashed border-slate-400" aria-hidden="true" />Target {{ chartTarget }}
                            </span>
                        </div>

                        <div class="relative mt-4 h-40">
                            <div
                                v-if="chartTarget"
                                class="pointer-events-none absolute inset-x-0 border-t-2 border-dashed border-slate-300"
                                :style="{ bottom: `${(chartTarget / chartMax) * 100}%` }"
                                aria-hidden="true"
                            />
                            <div class="flex h-full items-end gap-2">
                                <div
                                    v-for="day in board.chart"
                                    :key="day.date"
                                    class="flex h-full min-w-0 flex-1 items-end justify-center gap-1"
                                    :title="`${day.label}: ${day.leads} leads, ${day.appointments} appointments`"
                                >
                                    <div class="flex h-full w-1/2 max-w-[18px] flex-col justify-end">
                                        <span class="mb-0.5 text-center text-[10px] tabular-nums text-slate-600">{{ day.leads }}</span>
                                        <div class="min-h-[2px] rounded-t bg-primary-600 transition-all duration-500" :style="{ height: `${(day.leads / chartMax) * 85}%` }" />
                                    </div>
                                    <div class="flex h-full w-1/2 max-w-[18px] flex-col justify-end">
                                        <span class="mb-0.5 text-center text-[10px] tabular-nums text-slate-500">{{ day.appointments }}</span>
                                        <div class="min-h-[2px] rounded-t bg-primary-300 transition-all duration-500" :style="{ height: `${(day.appointments / chartMax) * 85}%` }" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-2 flex gap-2">
                            <span
                                v-for="day in board.chart"
                                :key="day.date"
                                :class="['min-w-0 flex-1 truncate text-center text-[11px]', day.is_today ? 'font-semibold text-primary-700' : 'text-slate-500']"
                            >
                                {{ day.is_today ? 'Today' : day.label }}
                            </span>
                        </div>
                    </BaseCard>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import { ArrowDownTrayIcon, PhotoIcon, ShareIcon, TrophyIcon } from '@heroicons/vue/24/outline';
import { BaseBadge, BaseButton, BaseCard, EmptyState } from '@/components/base';
import { useAuthStore } from '@/stores/auth';
import { useBrandingStore } from '@/stores/branding';
import { useToastStore } from '@/stores/toast';
import { ukTime } from '@/utils/datetime';
import { LEADERBOARD_STATUS, PERIOD_NOUN, renderLeaderboardImage } from '@/utils/leaderboardImage';

/**
 * The board the whole team reads: who has a target, and where they stand.
 *
 * It opens on today because that is the day anybody can still do something
 * about, and it refreshes itself so it can be left open on a wall or a second
 * monitor. There is no money on it - sales are not on this board at all.
 */
const auth = useAuthStore();
const branding = useBrandingStore();
const toast = useToastStore();

const PERIODS = [
    { key: 'today', label: 'Today' },
    { key: 'week', label: 'This week' },
    { key: 'month', label: 'This month' },
];

const TOP_BY = [
    { key: 'leads', label: 'Leads' },
    { key: 'appointments', label: 'Appointments' },
];

/** The table's columns from md up; on a phone each row lays itself out. */
const ROW_GRID = 'md:grid-cols-[2.5rem_minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_4rem_7.5rem] md:gap-x-5';

const REFRESH_EVERY_MS = 60_000;

const board = ref(null);
const period = ref('today');
const topBy = ref('leads');
const failed = ref(false);
const updatedAt = ref('');
const busy = ref('');
const canShare = ref(false);

let timer = null;
let latest = 0;

const periodNoun = computed(() => PERIOD_NOUN[period.value]);

const me = computed(() => board.value?.rows.find((row) => row.is_me) ?? null);

const canSetTargets = computed(() =>
    ['Admin', 'Manager', 'System Admin'].includes(auth.user?.role?.name) && auth.navSectionAllowed('employees'));

/** The one figure the banner leads with: today's leads, or the month's appointments when nobody is on leads. */
const headline = computed(() => {
    const s = board.value?.summary;

    if (! s) return null;

    if (period.value !== 'today' && s.leads_period.target > 0) {
        return { ...s.leads_period, unit: `leads ${periodNoun.value}` };
    }
    if (s.leads_today.target > 0) {
        return { ...s.leads_today, unit: 'leads today' };
    }
    if (s.appointments_month.target > 0) {
        return { ...s.appointments_month, unit: 'appointments this month' };
    }

    return null;
});

const bannerEyebrow = computed(() =>
    (period.value === 'today' ? board.value.today_label : `${period.value === 'week' ? 'Week' : 'Month'} of ${board.value.range.label}`));

const banner = computed(() => {
    const figure = headline.value;

    if (! figure) {
        return { heading: 'No targets yet', detail: `Nobody has a target set for ${board.value.month_label}.` };
    }

    const left = Math.max(0, figure.target - figure.done);
    const detail = left > 0
        ? `The team is ${left} short of the target for ${figure.unit}.`
        : `The team has reached the target for ${figure.unit}.`;

    if (figure.percent >= 100) return { heading: 'Target hit!', detail };
    if (figure.percent >= 75) return { heading: 'Nearly there', detail };
    if (figure.percent >= 40) return { heading: 'Good progress', detail };

    return { heading: figure.done > 0 ? 'Under way' : 'Not started yet', detail };
});

const tiles = computed(() => {
    const s = board.value.summary;
    const out = [{ label: 'On the board', value: s.members, caption: 'People with a target this month' }];

    if (s.leads_today.target > 0) {
        out.push({ label: 'Leads today', value: s.leads_today.done, of: s.leads_today.target, percent: s.leads_today.percent });
    }
    if (period.value !== 'today' && s.leads_period.target > 0) {
        out.push({ label: `Leads ${periodNoun.value}`, value: s.leads_period.done, of: s.leads_period.target, percent: s.leads_period.percent });
    }

    out.push({ label: 'Appointments today', value: s.appointments_today, caption: 'Booked by people on the board' });

    if (s.appointments_month.target > 0) {
        out.push({
            label: 'Appointments this month',
            value: s.appointments_month.done,
            of: s.appointments_month.target,
            percent: s.appointments_month.percent,
            alt: true,
        });
    }

    return out;
});

const topSubtitle = computed(() =>
    (topBy.value === 'leads' ? `Leads ${periodNoun.value}` : 'Appointments this month'));

const top = computed(() => {
    const people = (board.value?.rows ?? [])
        .map((row) => ({ user_id: row.user_id, name: row.name, count: row[topBy.value]?.done ?? 0 }))
        .filter((person) => person.count > 0)
        .sort((a, b) => b.count - a.count || a.name.localeCompare(b.name))
        .slice(0, 5);

    const most = people[0]?.count ?? 0;

    return people.map((person) => ({ ...person, width: Math.round((person.count / most) * 100) }));
});

const chartTarget = computed(() => board.value?.chart[0]?.lead_target ?? 0);

const chartMax = computed(() =>
    Math.max(1, chartTarget.value, ...(board.value?.chart ?? []).flatMap((day) => [day.leads, day.appointments])));

function metrics(row) {
    return [
        {
            key: 'leads',
            label: `Leads · ${period.value}`,
            value: row.leads,
            bar: 'bg-primary-600',
            note: row.leads && period.value !== 'today' ? `${row.leads.today} today` : '',
        },
        {
            key: 'appointments',
            label: 'Appointments · month',
            value: row.appointments,
            bar: 'bg-primary-400',
            note: row.appointments?.today ? `+${row.appointments.today} today` : '',
        },
    ];
}

function statusOf(row) {
    return LEADERBOARD_STATUS[row.status] ?? LEADERBOARD_STATUS.in_progress;
}

function rankClass(rank) {
    return {
        1: 'bg-warning-500 text-white',
        2: 'bg-slate-400 text-white',
        3: 'bg-warning-700 text-white',
    }[rank] ?? 'bg-slate-100 text-slate-700';
}

function initials(name) {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);

    return ((parts[0]?.[0] || '') + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase() || '?';
}

function clamp(percent) {
    return Math.max(0, Math.min(100, Number(percent) || 0));
}

async function load() {
    const request = ++latest;

    try {
        const { data } = await axios.get('/api/leaderboard', { params: { period: period.value } });

        // A slower answer for a period somebody has since switched away from.
        if (request !== latest) return;

        board.value = data;
        failed.value = false;
        updatedAt.value = ukTime(new Date());
    } catch {
        if (request === latest) failed.value = true;
    }
}

function setPeriod(key) {
    if (period.value === key) return;

    period.value = key;
    load();
}

/** Refresh quietly, and not at all while nobody can see the page. */
function tick() {
    if (! document.hidden) load();
}

function onVisible() {
    if (! document.hidden) load();
}

function fileName(extension) {
    return `sales-leaderboard-${board.value.period}-${board.value.today}.${extension}`;
}

function save(blob, name) {
    const href = URL.createObjectURL(blob);
    const a = document.createElement('a');

    a.href = href;
    a.download = name;
    a.click();
    URL.revokeObjectURL(href);
}

async function imageFile() {
    await document.fonts?.ready;

    const blob = await renderLeaderboardImage(board.value, { companyName: branding.companyName });

    return new File([blob], fileName('png'), { type: 'image/png' });
}

async function downloadImage() {
    busy.value = 'image';

    try {
        const file = await imageFile();

        save(file, file.name);
        toast.success('Image downloaded.');
    } catch {
        toast.error('Could not make the image.');
    } finally {
        busy.value = '';
    }
}

/** Straight into the phone's share sheet, so it reaches the group in two taps. */
async function shareImage() {
    busy.value = 'share';

    try {
        const file = await imageFile();

        await navigator.share({ files: [file], title: 'Sales Leaderboard' });
    } catch (error) {
        // Closing the share sheet is not a failure.
        if (error?.name !== 'AbortError') toast.error('Could not share the image.');
    } finally {
        busy.value = '';
    }
}

async function downloadPdf() {
    busy.value = 'pdf';

    try {
        const { data } = await axios.get('/api/leaderboard/pdf', {
            params: { period: period.value },
            responseType: 'blob',
        });

        save(data, fileName('pdf'));
        toast.success('PDF downloaded.');
    } catch {
        toast.error('Could not download the PDF.');
    } finally {
        busy.value = '';
    }
}

onMounted(() => {
    load();
    branding.loadPublic();

    timer = setInterval(tick, REFRESH_EVERY_MS);
    document.addEventListener('visibilitychange', onVisible);

    try {
        canShare.value = !!navigator.canShare?.({ files: [new File([''], 'board.png', { type: 'image/png' })] });
    } catch {
        canShare.value = false;
    }
});

onUnmounted(() => {
    clearInterval(timer);
    document.removeEventListener('visibilitychange', onVisible);
});
</script>
