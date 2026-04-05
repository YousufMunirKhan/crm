<template>
    <div class="w-full min-w-0 mx-auto px-3 sm:px-5 py-4 sm:py-6 space-y-5">
        <!-- Header -->
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                    <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900">Lead Pipeline</h1>
                    <p class="text-sm text-slate-500 mt-1 max-w-xl">
                        Drag cards between stages to update progress. On mobile, scroll sideways to see every column — use the grip to drag.
                    </p>
                </div>
                <router-link
                    to="/leads"
                    class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold border border-slate-200 bg-white text-slate-800 hover:bg-slate-50 shadow-sm shrink-0 touch-manipulation"
                >
                    Table view and stats
                </router-link>
            </div>

            <div class="flex flex-wrap items-center gap-2 p-3 sm:p-4 rounded-2xl bg-white border border-slate-200/80 shadow-sm">
                <input
                    v-model="filters.from"
                    type="date"
                    class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50/50 focus:ring-2 focus:ring-slate-300 focus:border-slate-300 outline-none"
                />
                <input
                    v-model="filters.to"
                    type="date"
                    class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50/50 focus:ring-2 focus:ring-slate-300 focus:border-slate-300 outline-none"
                />
                <label class="flex items-center gap-2 px-3 py-2 border border-slate-200 rounded-xl bg-white cursor-pointer select-none">
                    <input v-model="filters.assigned_by_me" type="checkbox" class="rounded border-slate-300 text-slate-900" />
                    <span class="text-sm text-slate-700">Leads I assigned</span>
                </label>
                <select
                    v-if="isAdmin"
                    v-model="filters.assigned_to"
                    class="px-3 py-2 border border-slate-200 rounded-xl text-sm min-w-[160px] bg-white"
                >
                    <option value="">All Employees</option>
                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                        {{ emp.name }}
                    </option>
                </select>
                <button
                    type="button"
                    @click="applyFilters"
                    class="px-4 py-2 bg-slate-900 text-white rounded-xl text-sm font-medium hover:bg-slate-800 touch-manipulation"
                >
                    Apply
                </button>
                <button
                    type="button"
                    @click="clearFilters"
                    class="px-4 py-2 border border-slate-200 text-slate-700 rounded-xl text-sm font-medium hover:bg-slate-50 touch-manipulation"
                >
                    Clear
                </button>
                <button
                    type="button"
                    @click="openCreateForm"
                    class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-semibold hover:bg-emerald-700 shadow-sm shadow-emerald-600/20 touch-manipulation w-full sm:w-auto"
                >
                    + Create Lead
                </button>
            </div>

            <!-- Visible columns (quotation is not on the board; default: Lead, Hot lead, Won, Lost) -->
            <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 sm:gap-3 p-3 sm:px-4 rounded-2xl bg-slate-50 border border-slate-200/90">
                <span class="text-xs font-semibold text-slate-600 uppercase tracking-wide shrink-0">Board columns</span>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <label
                        v-for="stageKey in boardStageOptions"
                        :key="stageKey"
                        class="inline-flex items-center gap-2 cursor-pointer select-none text-sm text-slate-700"
                    >
                        <input
                            type="checkbox"
                            class="rounded border-slate-300 text-slate-900 focus:ring-slate-400"
                            :checked="visibleStages.includes(stageKey)"
                            @change="onToggleVisibleStage(stageKey, ($event.target).checked)"
                        />
                        <span>{{ formatLeadStage(stageKey) }}</span>
                    </label>
                </div>
            </div>
        </div>

        <template v-if="loading">
            <div class="flex flex-col justify-center items-center py-24 gap-3 rounded-2xl border border-slate-200 bg-white">
                <div class="h-9 w-9 border-2 border-slate-200 border-t-slate-800 rounded-full animate-spin" />
                <div class="text-sm text-slate-500">Loading pipeline…</div>
            </div>
        </template>

        <template v-else-if="isEmpty">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-12 sm:p-16 text-center">
                <div class="mx-auto w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-400 mb-5">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-slate-800 mb-2">No leads in this view</h3>
                <p class="text-slate-500 mb-8 max-w-md mx-auto text-sm">
                    Adjust filters or create a lead to see your pipeline.
                </p>
                <button
                    type="button"
                    @click="openCreateForm"
                    class="px-6 py-3 bg-slate-900 text-white rounded-xl text-sm font-semibold hover:bg-slate-800"
                >
                    + Create your first lead
                </button>
            </div>
        </template>

        <template v-else>
            <!-- Fixed-width columns + horizontal scroll; only user-selected stages (no quotation on this board) -->
            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-2 sm:p-3 shadow-inner">
                <p class="sm:hidden text-center text-[11px] text-slate-500 pb-2 px-1">
                    Swipe sideways · drag cards with the grip
                </p>
                <div
                    class="flex flex-nowrap gap-3 md:gap-4 overflow-x-auto overflow-y-hidden pb-2 pt-0.5 scroll-smooth snap-x snap-mandatory overscroll-x-contain [scrollbar-gutter:stable]"
                >
                    <div
                        v-for="stageKey in visibleStageColumns"
                        :key="stageKey"
                        class="flex flex-col shrink-0 snap-center w-[min(100vw-2rem,380px)] sm:w-[340px] md:w-[360px] lg:w-[380px] min-h-[min(70vh,560px)] max-h-[min(82vh,820px)] rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden"
                    >
                        <!-- Column header -->
                        <div
                            class="px-3 py-2.5 border-b border-slate-200 shrink-0 flex items-center justify-between gap-2"
                            :class="stageColumnHeaderTint(stageKey)"
                        >
                            <div class="flex items-center gap-2 min-w-0">
                                <span
                                    class="h-2 w-2 rounded-full shrink-0"
                                    :class="stageMeta[stageKey].dot"
                                />
                                <h3 class="font-semibold text-slate-900 text-sm leading-tight truncate">
                                    {{ formatLeadStage(stageKey) }}
                                </h3>
                            </div>
                            <span
                                class="tabular-nums text-xs font-semibold text-slate-600 bg-white/90 border border-slate-200/80 px-2 py-0.5 rounded-md shrink-0 min-w-[1.75rem] text-center"
                            >
                                {{ (pipeline[stageKey] || []).length }}
                            </span>
                        </div>

                        <!-- Droppable list -->
                        <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden p-2">
                            <Draggable
                                :model-value="pipeline[stageKey] || []"
                                group="leads-pipeline"
                                item-key="id"
                                :animation="200"
                                :delay="touchDragDelay"
                                :delay-on-touch-only="true"
                                :touch-start-threshold="6"
                                :force-fallback="true"
                                fallback-class="pipeline-drag-fallback"
                                ghost-class="pipeline-ghost"
                                chosen-class="pipeline-chosen"
                                drag-class="pipeline-drag-active"
                                handle=".drag-handle"
                                :disabled="loading || movingLeadId !== null"
                                class="min-h-[88px] flex flex-col gap-2 pb-4"
                                @update:model-value="(v) => setStageList(stageKey, v)"
                                @start="onDragStart(stageKey)"
                                @change="(e) => onPipelineChange(stageKey, e)"
                            >
                                <template #item="{ element: lead }">
                                    <div
                                        class="group relative rounded-lg border bg-white touch-manipulation transition-[box-shadow,border-color] duration-150 border-slate-200 hover:border-slate-300 hover:shadow-md overflow-hidden"
                                        :class="[
                                            stageMeta[stageKey].bar,
                                            movingLeadId === lead.id ? 'opacity-60 pointer-events-none' : '',
                                        ]"
                                        @click.self="viewLead(lead)"
                                    >
                                        <div class="p-2.5 pl-2 flex gap-2 items-start">
                                            <button
                                                type="button"
                                                class="drag-handle mt-0.5 shrink-0 w-7 h-7 rounded-md border border-slate-200 bg-slate-50 text-slate-400 hover:text-slate-600 hover:bg-slate-100 flex items-center justify-center cursor-grab active:cursor-grabbing"
                                                aria-label="Drag to move lead"
                                                @click.stop
                                            >
                                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                    <path d="M8 6a2 2 0 11-4 0 2 2 0 014 0zm0 6a2 2 0 11-4 0 2 2 0 014 0zm0 6a2 2 0 11-4 0 2 2 0 014 0zm8-12a2 2 0 11-4 0 2 2 0 014 0zm0 6a2 2 0 11-4 0 2 2 0 014 0zm0 6a2 2 0 11-4 0 2 2 0 014 0z" />
                                                </svg>
                                            </button>
                                            <div class="flex-1 min-w-0 space-y-1.5">
                                                <button
                                                    type="button"
                                                    class="text-left w-full"
                                                    @click="viewLead(lead)"
                                                >
                                                    <div class="flex gap-2.5 items-start">
                                                        <span
                                                            class="shrink-0 w-10 h-10 rounded-lg text-xs font-semibold flex items-center justify-center bg-slate-700 text-white"
                                                        >
                                                            {{ customerInitials(lead.customer?.name) }}
                                                        </span>
                                                        <div class="min-w-0 flex-1 pt-0.5">
                                                            <div
                                                                class="font-semibold text-slate-900 text-[0.9375rem] sm:text-base leading-snug line-clamp-2 break-words"
                                                            >
                                                                {{ lead.customer?.name || 'Unknown' }}
                                                            </div>
                                                            <div class="text-xs text-slate-500 mt-0.5 truncate" :title="lead.customer?.phone || ''">
                                                                {{ lead.customer?.phone || '—' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </button>

                                                <div
                                                    v-if="getLeadAppointment(lead)"
                                                    class="text-xs text-amber-900 bg-amber-50/90 border border-amber-100/80 rounded-md px-2 py-1 leading-snug line-clamp-2"
                                                >
                                                    <span class="mr-1" aria-hidden="true">📅</span>{{ getLeadAppointment(lead) }}
                                                </div>

                                                <div
                                                    v-if="lead.items && lead.items.length"
                                                    class="flex flex-wrap gap-1 max-h-[3.25rem] overflow-hidden"
                                                >
                                                    <span
                                                        v-for="item in lead.items.slice(0, 4)"
                                                        :key="item.id"
                                                        class="inline max-w-full text-xs text-slate-700 bg-slate-100 px-2 py-0.5 rounded leading-snug line-clamp-1"
                                                        :title="(item.product?.name || 'Product') + (item.status && item.status !== 'pending' ? ' · ' + formatLineItemStatus(item.status) : '')"
                                                    >
                                                        {{ item.product?.name || 'Product' }}
                                                        <span v-if="item.status && item.status !== 'pending'" class="text-slate-500 font-normal">
                                                            · {{ formatLineItemStatus(item.status) }}
                                                        </span>
                                                    </span>
                                                    <span v-if="lead.items.length > 4" class="text-xs text-slate-400 self-center">
                                                        +{{ lead.items.length - 4 }}
                                                    </span>
                                                </div>
                                                <div v-else-if="lead.product" class="text-xs text-slate-700 bg-slate-100 rounded-md px-2 py-1 line-clamp-2">
                                                    {{ lead.product.name }}
                                                </div>
                                                <div v-else class="text-xs text-slate-400">
                                                    No product
                                                </div>

                                                <div class="flex items-end justify-between gap-2 pt-0.5">
                                                    <div>
                                                        <div class="text-[10px] uppercase tracking-wide text-slate-400 font-medium leading-none mb-0.5">
                                                            Value
                                                        </div>
                                                        <div class="text-sm font-semibold text-slate-800 tabular-nums">
                                                            £{{ formatNumber(getLeadValue(lead)) }}
                                                        </div>
                                                    </div>
                                                    <div
                                                        v-if="lead.assignee"
                                                        class="text-xs text-slate-600 text-right min-w-0 max-w-[55%] truncate px-2 py-0.5 rounded-md bg-slate-50 border border-slate-100"
                                                        :title="lead.assignee.name"
                                                    >
                                                        {{ lead.assignee.name }}
                                                    </div>
                                                </div>

                                                <!-- Single compact row: icon-only (saves vertical space on all breakpoints) -->
                                                <div class="flex items-center justify-end gap-0.5 pt-1.5 mt-1 border-t border-slate-100">
                                                    <button
                                                        type="button"
                                                        class="no-drag h-8 w-8 inline-flex items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                                                        title="Schedule follow-up"
                                                        aria-label="Schedule follow-up"
                                                        @click.stop="openFollowUpModal(lead)"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="no-drag h-8 w-8 inline-flex items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                                                        title="Log activity"
                                                        aria-label="Log activity"
                                                        @click.stop="openActivityModal(lead)"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="no-drag h-8 w-8 inline-flex items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-800"
                                                        title="Edit lead"
                                                        aria-label="Edit lead"
                                                        @click.stop="openEditForm(lead)"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="no-drag h-8 w-8 inline-flex items-center justify-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600"
                                                        title="Delete lead"
                                                        aria-label="Delete lead"
                                                        @click.stop="openDeleteConfirm(lead)"
                                                    >
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </Draggable>

                            <div
                                v-if="!(pipeline[stageKey] || []).length"
                                class="pointer-events-none flex flex-col items-center justify-center py-12 text-center px-3"
                            >
                                <svg class="w-8 h-8 text-slate-200 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4v16m8-8H4" />
                                </svg>
                                <p class="text-xs text-slate-400">Drop leads here</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <LeadForm
            v-if="showForm"
            :lead="selectedLead"
            @close="closeForm"
            @saved="handleSaved"
        />

        <DeleteConfirm
            v-if="showDeleteConfirm"
            title="Delete Lead"
            :message="`Are you sure you want to delete this lead?`"
            :loading="deleting"
            @confirm="confirmDelete"
            @cancel="closeDeleteConfirm"
        />

        <LogActivityModal
            v-if="showActivityModal && activityLead"
            :lead="activityLead"
            @close="closeActivityModal"
            @saved="handleActivitySaved"
        />

        <ScheduleFollowUpModal
            v-if="showFollowUpModal && followUpLead"
            :lead="followUpLead"
            @close="closeFollowUpModal"
            @saved="handleFollowUpSaved"
        />

        <!-- Lost reason (required by API when marking lost) -->
        <Teleport to="body">
            <div
                v-if="showLostModal"
                class="fixed inset-0 z-[100] flex items-end sm:items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm"
                @click.self="cancelLostModal"
            >
                <div
                    class="w-full max-w-md bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden"
                    role="dialog"
                    aria-labelledby="lost-modal-title"
                >
                    <div class="px-5 py-4 border-b border-slate-100">
                        <h2 id="lost-modal-title" class="text-lg font-semibold text-slate-900">Mark as lost</h2>
                        <p class="text-sm text-slate-500 mt-1">
                            A reason is required. The lead stays in the previous stage until you confirm.
                        </p>
                    </div>
                    <div class="p-5 space-y-3">
                        <label class="block text-sm font-medium text-slate-700">Lost reason</label>
                        <textarea
                            v-model="lostReasonInput"
                            rows="3"
                            class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-slate-300 outline-none resize-y"
                            placeholder="e.g. Chose competitor, no budget, not interested…"
                        />
                        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end pt-2">
                            <button
                                type="button"
                                class="px-4 py-2.5 rounded-xl text-sm font-medium border border-slate-200 text-slate-700 hover:bg-slate-50"
                                @click="cancelLostModal"
                            >
                                Cancel
                            </button>
                            <button
                                type="button"
                                class="px-4 py-2.5 rounded-xl text-sm font-semibold bg-slate-900 text-white hover:bg-slate-800 disabled:opacity-50"
                                :disabled="lostSaving || !lostReasonInput.trim()"
                                @click="confirmLostModal"
                            >
                                {{ lostSaving ? 'Saving…' : 'Confirm lost' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import Draggable from 'vuedraggable';
import LeadForm from '@/components/LeadForm.vue';
import DeleteConfirm from '@/components/DeleteConfirm.vue';
import LogActivityModal from '@/components/LogActivityModal.vue';
import ScheduleFollowUpModal from '@/components/ScheduleFollowUpModal.vue';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';
import { formatLineItemStatus, formatLeadStage } from '@/utils/displayFormat';

const router = useRouter();
const toast = useToastStore();
const auth = useAuthStore();

/** Stages that can appear on this board (quotation excluded — manage elsewhere, e.g. lead form / table). */
const boardStageOptions = ['follow_up', 'lead', 'hot_lead', 'won', 'lost'];

const DEFAULT_VISIBLE_STAGES = ['lead', 'hot_lead', 'won', 'lost'];
const PIPELINE_VISIBLE_STAGES_KEY = 'crm_lead_pipeline_visible_stages';

const visibleStages = ref([...DEFAULT_VISIBLE_STAGES]);

/** Column order left → right when multiple are selected */
const visibleStageColumns = computed(() =>
    boardStageOptions.filter((s) => visibleStages.value.includes(s)),
);

function loadVisibleStagesFromStorage() {
    try {
        const raw = localStorage.getItem(PIPELINE_VISIBLE_STAGES_KEY);
        if (!raw) return;
        const parsed = JSON.parse(raw);
        if (!Array.isArray(parsed)) return;
        const allowed = new Set(boardStageOptions);
        const next = boardStageOptions.filter((s) => parsed.includes(s) && allowed.has(s));
        if (next.length) {
            visibleStages.value = next;
        }
    } catch {
        /* ignore */
    }
}

function persistVisibleStages() {
    try {
        localStorage.setItem(PIPELINE_VISIBLE_STAGES_KEY, JSON.stringify(visibleStages.value));
    } catch {
        /* ignore */
    }
}

function onToggleVisibleStage(stageKey, checked) {
    let next = checked
        ? [...new Set([...visibleStages.value, stageKey])]
        : visibleStages.value.filter((s) => s !== stageKey);
    next = boardStageOptions.filter((s) => next.includes(s));
    if (!next.length) {
        toast.warning('Keep at least one column visible.');
        return;
    }
    visibleStages.value = next;
    persistVisibleStages();
}

/** Stage dot (column header) + subtle left accent on cards only */
const stageMeta = {
    follow_up: { dot: 'bg-sky-500', bar: 'border-l-[3px] border-l-sky-500' },
    lead: { dot: 'bg-amber-500', bar: 'border-l-[3px] border-l-amber-500' },
    hot_lead: { dot: 'bg-orange-500', bar: 'border-l-[3px] border-l-orange-500' },
    won: { dot: 'bg-emerald-500', bar: 'border-l-[3px] border-l-emerald-500' },
    lost: { dot: 'bg-rose-500', bar: 'border-l-[3px] border-l-rose-500' },
};

function stageColumnHeaderTint(stageKey) {
    const map = {
        follow_up: 'bg-sky-50/90',
        lead: 'bg-amber-50/90',
        hot_lead: 'bg-orange-50/90',
        won: 'bg-emerald-50/90',
        lost: 'bg-rose-50/90',
    };
    return map[stageKey] || 'bg-slate-50';
}

function customerInitials(name) {
    if (!name || typeof name !== 'string') return '?';
    const parts = name.trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '?';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

const pipeline = ref({});
const loading = ref(true);
const showForm = ref(false);
const selectedLead = ref(null);
const showDeleteConfirm = ref(false);
const leadToDelete = ref(null);
const deleting = ref(false);
const employees = ref([]);
const showActivityModal = ref(false);
const activityLead = ref(null);
const showFollowUpModal = ref(false);
const followUpLead = ref(null);

const dragSourceStage = ref(null);
const movingLeadId = ref(null);
const showLostModal = ref(false);
const lostModalLead = ref(null);
const lostReasonInput = ref('');
const lostSaving = ref(false);

/** Slight delay on touch so scrolling the board isn’t mistaken for a drag */
const touchDragDelay = 180;

const filters = ref({
    from: '',
    to: '',
    assigned_to: '',
    assigned_by_me: false,
});

const isAdmin = computed(() => {
    const role = auth.user?.role?.name;
    return role === 'Admin' || role === 'System Admin' || role === 'Manager';
});

const loadEmployees = async () => {
    if (!isAdmin.value) return;
    try {
        const response = await axios.get('/api/users');
        employees.value = response.data.data || response.data || [];
    } catch (error) {
        console.error('Failed to load employees:', error);
    }
};

/** True only when the API board has no leads in any stage (including quotation — hidden from board but still “data exists”). */
const isEmpty = computed(() => {
    const p = pipeline.value;
    if (!p || typeof p !== 'object') return true;
    const allPipelineStages = ['follow_up', 'lead', 'hot_lead', 'quotation', 'won', 'lost'];
    return allPipelineStages.every((key) => !p[key] || p[key].length === 0);
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num);
};

const getLeadValue = (lead) => {
    if (lead.stage === 'won' && lead.items && lead.items.length > 0) {
        const itemsTotal = lead.items.reduce((sum, item) => {
            return sum + (parseFloat(item.total_price) || 0);
        }, 0);
        return itemsTotal > 0 ? itemsTotal : (lead.pipeline_value || 0);
    }
    return lead.total_value || lead.pipeline_value || 0;
};

function getLeadAppointment(lead) {
    const activities = lead.activities || [];
    const appt = activities.find((a) => a.type === 'appointment') || activities[0];
    if (!appt || !appt.meta) return null;
    const date = appt.meta.appointment_date;
    const time = appt.meta.appointment_time || '00:00';
    if (!date) return null;
    try {
        const d = new Date(date + (time ? 'T' + time : ''));
        if (isNaN(d.getTime())) return null;
        return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }) + (time ? ' at ' + time : '');
    } catch {
        return date + (time ? ' ' + time : '');
    }
}

function setStageList(stageKey, list) {
    if (!pipeline.value[stageKey]) {
        pipeline.value[stageKey] = [];
    }
    pipeline.value[stageKey] = list;
}

function onDragStart(stageKey) {
    dragSourceStage.value = stageKey;
}

function revertLeadToStage(lead, fromStage, toStage) {
    const fromList = pipeline.value[fromStage];
    if (!fromList) return;
    const fi = fromList.findIndex((l) => l.id === lead.id);
    if (fi !== -1) fromList.splice(fi, 1);
    const toList = pipeline.value[toStage];
    if (toList) toList.push(lead);
}

async function persistStageChange(lead, newStage, previousStage) {
    movingLeadId.value = lead.id;
    try {
        await axios.put(`/api/leads/${lead.id}`, { stage: newStage });
        lead.stage = newStage;
        toast.success(`Moved to ${formatLeadStage(newStage)}`);
    } catch (err) {
        revertLeadToStage(lead, newStage, previousStage);
        const msg = err.response?.data?.message || 'Could not update stage';
        toast.error(msg);
    } finally {
        movingLeadId.value = null;
    }
}

function onPipelineChange(stageKey, evt) {
    if (evt.moved) return;
    if (!evt.added) return;

    const lead = evt.added.element;
    const fromStage = dragSourceStage.value;
    if (!fromStage || fromStage === stageKey) return;

    if (stageKey === 'lost') {
        revertLeadToStage(lead, stageKey, fromStage);
        lostModalLead.value = lead;
        lostReasonInput.value = '';
        showLostModal.value = true;
        return;
    }

    void persistStageChange(lead, stageKey, fromStage);
}

function cancelLostModal() {
    showLostModal.value = false;
    lostModalLead.value = null;
    lostReasonInput.value = '';
}

async function confirmLostModal() {
    const lead = lostModalLead.value;
    if (!lead || !lostReasonInput.value.trim()) {
        toast.error('Please enter a lost reason');
        return;
    }
    lostSaving.value = true;
    movingLeadId.value = lead.id;
    try {
        await axios.put(`/api/leads/${lead.id}`, {
            stage: 'lost',
            lost_reason: lostReasonInput.value.trim(),
        });
        toast.success('Lead marked as lost');
        cancelLostModal();
        await loadPipeline();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to update lead');
    } finally {
        lostSaving.value = false;
        movingLeadId.value = null;
    }
}

const loadPipeline = async () => {
    loading.value = true;
    try {
        const params = {};
        if (filters.value.from) params.from = filters.value.from;
        if (filters.value.to) params.to = filters.value.to;
        if (filters.value.assigned_to) params.assigned_to = filters.value.assigned_to;
        if (filters.value.assigned_by_me) params.assigned_by_me = 1;

        const { data } = await axios.get('/api/leads/pipeline/board', { params });
        pipeline.value = data;
    } catch (error) {
        console.error('Failed to load pipeline:', error);
        pipeline.value = {};
    } finally {
        loading.value = false;
    }
};

const applyFilters = () => {
    loadPipeline();
};

const clearFilters = () => {
    filters.value = {
        from: '',
        to: '',
        assigned_to: '',
        assigned_by_me: false,
    };
    loadPipeline();
};

const viewLead = (lead) => {
    if (lead.id) {
        router.push(`/leads/${lead.id}`);
    }
};

const openCreateForm = () => {
    selectedLead.value = null;
    showForm.value = true;
};

const openEditForm = (lead) => {
    selectedLead.value = lead;
    showForm.value = true;
};

const closeForm = () => {
    showForm.value = false;
    selectedLead.value = null;
};

const handleSaved = () => {
    loadPipeline();
    closeForm();
};

const openDeleteConfirm = (lead) => {
    leadToDelete.value = lead;
    showDeleteConfirm.value = true;
};

const closeDeleteConfirm = () => {
    showDeleteConfirm.value = false;
    leadToDelete.value = null;
};

const confirmDelete = async () => {
    if (!leadToDelete.value) return;

    deleting.value = true;
    try {
        await axios.delete(`/api/leads/${leadToDelete.value.id}`);
        closeDeleteConfirm();
        loadPipeline();
    } catch (error) {
        console.error('Failed to delete lead:', error);
        toast.error('Failed to delete lead. Please try again.');
    } finally {
        deleting.value = false;
    }
};

const openActivityModal = (lead) => {
    activityLead.value = lead;
    showActivityModal.value = true;
};

const closeActivityModal = () => {
    showActivityModal.value = false;
    activityLead.value = null;
};

const openFollowUpModal = (lead) => {
    followUpLead.value = lead;
    showFollowUpModal.value = true;
};

const closeFollowUpModal = () => {
    showFollowUpModal.value = false;
    followUpLead.value = null;
};

const handleFollowUpSaved = () => {
    loadPipeline();
    closeFollowUpModal();
};

const handleActivitySaved = () => {
    loadPipeline();
    closeActivityModal();
};

onMounted(() => {
    loadVisibleStagesFromStorage();
    loadEmployees();
    loadPipeline();
});
</script>

<style scoped>
.pipeline-ghost {
    opacity: 0.45;
    background: rgb(241 245 249);
    border: 2px dashed rgb(148 163 184);
    border-radius: 0.75rem;
}
.pipeline-chosen {
    cursor: grabbing;
}
.pipeline-drag-active {
    opacity: 0.95;
    transform: rotate(1deg);
    box-shadow: 0 20px 40px -12px rgb(15 23 42 / 0.25);
}
:deep(.pipeline-drag-fallback) {
    opacity: 0.98 !important;
    box-shadow: 0 20px 40px -12px rgb(15 23 42 / 0.3) !important;
    border-radius: 0.75rem !important;
}
</style>
