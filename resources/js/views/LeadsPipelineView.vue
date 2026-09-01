<template>
    <div class="page">
        <!-- Header -->
        <div class="flex flex-col gap-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                    <p class="page-lead">
                        Drag cards between stages to update progress. On mobile, scroll sideways to see every column — use the grip to drag.
                    </p>
                </div>
                <BaseButton to="/leads" variant="outline" class="shrink-0">
                    <template #icon>
                        <TableCellsIcon class="icon" aria-hidden="true" />
                    </template>
                    Table view and stats
                </BaseButton>
            </div>

            <div class="card p-3 sm:p-4 flex flex-wrap items-end gap-3">
                <div class="w-full sm:w-[10.5rem]">
                    <BaseInput v-model="filters.from" label="From" type="date" />
                </div>
                <div class="w-full sm:w-[10.5rem]">
                    <BaseInput v-model="filters.to" label="To" type="date" />
                </div>
                <label class="form-choice min-h-[42px] w-full sm:w-auto cursor-pointer select-none">
                    <input v-model="filters.assigned_by_me" type="checkbox" class="form-checkbox" />
                    <span class="text-sm text-slate-700">Leads I assigned</span>
                </label>
                <div v-if="isAdmin" class="w-full sm:w-auto min-w-[160px]">
                    <label class="form-label" for="leads-pipeline-assigned-to">Assigned to</label>
                    <select
                        id="leads-pipeline-assigned-to"
                        v-model="filters.assigned_to"
                        class="form-select"
                    >
                        <option value="">All Employees</option>
                        <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                            {{ emp.name }}
                        </option>
                    </select>
                </div>
                <BaseButton variant="soft" @click="applyFilters">
                    <template #icon>
                        <FunnelIcon class="icon" aria-hidden="true" />
                    </template>
                    Apply
                </BaseButton>
                <BaseButton variant="outline" @click="clearFilters">
                    <template #icon>
                        <ArrowPathIcon class="icon" aria-hidden="true" />
                    </template>
                    Clear
                </BaseButton>
                <BaseButton variant="primary" block-mobile @click="openCreateForm">
                    <template #icon>
                        <PlusIcon class="icon" aria-hidden="true" />
                    </template>
                    Create Lead
                </BaseButton>
            </div>

            <!-- Visible columns (quotation is not on the board; default: Lead, Hot lead, Won, Lost) -->
            <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center gap-2 sm:gap-3 p-3 sm:px-4 rounded-panel bg-slate-50 border border-slate-200">
                <span class="text-eyebrow text-slate-600 uppercase shrink-0">Board columns</span>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <label
                        v-for="stageKey in boardStageOptions"
                        :key="stageKey"
                        class="inline-flex items-center gap-2 cursor-pointer select-none text-sm text-slate-700"
                    >
                        <input
                            type="checkbox"
                            class="form-checkbox"
                            :checked="visibleStages.includes(stageKey)"
                            @change="onToggleVisibleStage(stageKey, ($event.target).checked)"
                        />
                        <span>{{ formatLeadStage(stageKey) }}</span>
                    </label>
                </div>
            </div>
        </div>

        <template v-if="loading">
            <div
                class="card flex flex-col justify-center items-center py-24 gap-3 text-slate-600"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <span class="spinner w-8 h-8 border-[3px]" aria-hidden="true" />
                <div class="text-sm text-slate-500">Loading pipeline…</div>
            </div>
        </template>

        <template v-else-if="isEmpty">
            <BaseCard :padded="false">
                <EmptyState
                    heading="No leads in this view"
                    description="Adjust filters or create a lead to see your pipeline."
                >
                    <template #icon>
                        <ClipboardDocumentIcon class="icon" aria-hidden="true" />
                    </template>
                    <template #action>
                        <BaseButton variant="soft" @click="openCreateForm">
                            <template #icon>
                                <PlusIcon class="icon" aria-hidden="true" />
                            </template>
                            Create your first lead
                        </BaseButton>
                    </template>
                </EmptyState>
            </BaseCard>
        </template>

        <template v-else>
            <!-- Fixed-width columns + horizontal scroll; only user-selected stages (no quotation on this board) -->
            <div class="rounded-panel border border-slate-200 bg-slate-50/80 p-2 sm:p-3 shadow-inner">
                <p class="sm:hidden text-center text-[11px] text-slate-500 pb-2 px-1">
                    Swipe sideways · drag cards with the grip
                </p>
                <div
                    class="flex flex-nowrap gap-3 md:gap-4 overflow-x-auto overflow-y-hidden pb-2 pt-0.5 scroll-smooth snap-x snap-mandatory overscroll-x-contain [scrollbar-gutter:stable]"
                >
                    <div
                        v-for="stageKey in visibleStageColumns"
                        :key="stageKey"
                        class="flex flex-col shrink-0 snap-center w-[min(100vw-2rem,380px)] sm:w-[340px] md:w-[360px] lg:w-[380px] min-h-[min(70vh,560px)] max-h-[min(82vh,820px)] rounded-card border border-slate-200 bg-white shadow-card overflow-hidden"
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
                            <BaseBadge tone="neutral" class="tabular-nums font-semibold shrink-0">
                                {{ (pipeline[stageKey] || []).length }}
                            </BaseBadge>
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
                                                class="drag-handle mt-0.5 shrink-0 w-7 h-7 rounded-control border border-slate-200 bg-slate-50 text-slate-500 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center cursor-grab active:cursor-grabbing focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                                aria-label="Drag to move lead"
                                                @click.stop
                                            >
                                                <Bars3Icon class="icon-sm" aria-hidden="true" />
                                            </button>
                                            <div class="flex-1 min-w-0 space-y-1.5">
                                                <button
                                                    type="button"
                                                    class="text-left w-full rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
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
                                                                {{ lead.customer?.name || lead.customer?.business_name || 'Unknown' }}
                                                            </div>
                                                            <div
                                                                v-if="lead.customer?.business_name && lead.customer?.business_name !== lead.customer?.name"
                                                                class="text-xs text-slate-500 mt-0.5 truncate"
                                                                :title="lead.customer.business_name"
                                                            >
                                                                {{ lead.customer.business_name }}
                                                            </div>
                                                            <div class="text-xs text-slate-500 mt-0.5 truncate" :title="lead.customer?.phone || ''">
                                                                {{ lead.customer?.phone || '—' }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </button>

                                                <div
                                                    v-if="getLeadAppointment(lead)"
                                                    class="flex items-start gap-1.5 text-xs text-warning-800 bg-warning-50 border border-warning-200 rounded-control px-2 py-1 leading-snug"
                                                >
                                                    <CalendarDaysIcon class="icon-sm mt-px" aria-hidden="true" />
                                                    <span class="line-clamp-2">{{ getLeadAppointment(lead) }}</span>
                                                </div>

                                                <div
                                                    v-if="lead.items && lead.items.length"
                                                    class="flex flex-wrap gap-1 max-h-[3.25rem] overflow-hidden"
                                                >
                                                    <span
                                                        v-for="item in lead.items.slice(0, 4)"
                                                        :key="item.id"
                                                        class="chip max-w-full leading-snug line-clamp-1"
                                                        :title="(item.product?.name || 'Product') + (item.status && item.status !== 'pending' ? ' · ' + formatLineItemStatus(item.status) : '')"
                                                    >
                                                        {{ item.product?.name || 'Product' }}
                                                        <span v-if="item.status && item.status !== 'pending'" class="text-slate-500 font-normal">
                                                            · {{ formatLineItemStatus(item.status) }}
                                                        </span>
                                                    </span>
                                                    <span v-if="lead.items.length > 4" class="text-xs text-slate-500 self-center">
                                                        +{{ lead.items.length - 4 }}
                                                    </span>
                                                </div>
                                                <div v-else-if="lead.product" class="chip max-w-full line-clamp-2">
                                                    {{ lead.product.name }}
                                                </div>
                                                <div v-else class="text-xs text-slate-500">
                                                    No product
                                                </div>

                                                <div class="flex items-end justify-between gap-2 pt-0.5">
                                                    <div>
                                                        <div class="text-eyebrow uppercase text-slate-500 leading-none mb-0.5">
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
                                                    <BaseButton
                                                        variant="ghost"
                                                        size="icon"
                                                        label="Schedule follow-up"
                                                        class="no-drag"
                                                        title="Schedule follow-up"
                                                        @click.stop="openFollowUpModal(lead)"
                                                    >
                                                        <CalendarDaysIcon class="icon-sm" aria-hidden="true" />
                                                    </BaseButton>
                                                    <BaseButton
                                                        variant="ghost"
                                                        size="icon"
                                                        label="Log activity"
                                                        class="no-drag"
                                                        title="Log activity"
                                                        @click.stop="openActivityModal(lead)"
                                                    >
                                                        <PhoneIcon class="icon-sm" aria-hidden="true" />
                                                    </BaseButton>
                                                    <BaseButton
                                                        variant="ghost"
                                                        size="icon"
                                                        label="Edit lead"
                                                        class="no-drag"
                                                        title="Edit lead"
                                                        @click.stop="openEditForm(lead)"
                                                    >
                                                        <PencilSquareIcon class="icon-sm" aria-hidden="true" />
                                                    </BaseButton>
                                                    <BaseButton
                                                        v-if="canDeleteLeads"
                                                        variant="ghost"
                                                        size="icon"
                                                        label="Delete lead"
                                                        class="no-drag text-danger-700 hover:bg-danger-50 hover:text-danger-800"
                                                        title="Delete lead"
                                                        @click.stop="openDeleteConfirm(lead)"
                                                    >
                                                        <TrashIcon class="icon-sm" aria-hidden="true" />
                                                    </BaseButton>
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
                                <PlusIcon class="icon w-8 h-8 text-slate-300 mb-2" aria-hidden="true" />
                                <p class="text-xs text-slate-500">Drop leads here</p>
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
            :message="deleteLeadConfirmMessage"
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
        <BaseModal
            v-model="showLostModal"
            title="Mark as lost"
            description="A reason is required. The lead stays in the previous stage until you confirm."
            size="sm"
            :close-on-backdrop="false"
            @close="cancelLostModal"
        >
            <form id="lead-lost-reason-form" class="space-y-3" novalidate @submit.prevent="confirmLostModal">
                <div>
                    <label class="form-label" for="leadspipelineview-lost-reason">
                        Lost reason
                        <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <textarea
                        id="leadspipelineview-lost-reason"
                        v-model="lostReasonInput"
                        rows="3"
                        class="form-textarea"
                        placeholder="e.g. Chose competitor, no budget, not interested…"
                    />
                </div>
            </form>
            <template #actions>
                <BaseButton variant="outline" block-mobile @click="cancelLostModal">Cancel</BaseButton>
                <BaseButton
                    variant="danger"
                    type="submit"
                    form="lead-lost-reason-form"
                    block-mobile
                    :loading="lostSaving"
                >
                    Confirm lost
                </BaseButton>
            </template>
        </BaseModal>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import Draggable from 'vuedraggable';
import {
    ArrowPathIcon,
    Bars3Icon,
    CalendarDaysIcon,
    ClipboardDocumentIcon,
    FunnelIcon,
    PencilSquareIcon,
    PhoneIcon,
    PlusIcon,
    TableCellsIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import {
    BaseBadge,
    BaseButton,
    BaseCard,
    BaseInput,
    BaseModal,
    EmptyState,
} from '@/components/base';
import LeadForm from '@/components/LeadForm.vue';
import LostReasonPicker from '@/components/LostReasonPicker.vue';
import { isLostReasonComplete } from '@/constants/lostReasons';
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
    follow_up: { dot: 'bg-primary-500', bar: 'border-l-[3px] border-l-primary-500' },
    lead: { dot: 'bg-warning-500', bar: 'border-l-[3px] border-l-warning-500' },
    hot_lead: { dot: 'bg-orange-500', bar: 'border-l-[3px] border-l-orange-500' },
    won: { dot: 'bg-success-500', bar: 'border-l-[3px] border-l-success-500' },
    lost: { dot: 'bg-danger-500', bar: 'border-l-[3px] border-l-danger-500' },
};

function stageColumnHeaderTint(stageKey) {
    const map = {
        follow_up: 'bg-primary-50',
        lead: 'bg-warning-50',
        hot_lead: 'bg-orange-50',
        won: 'bg-success-50',
        lost: 'bg-danger-50',
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

const canDeleteLeads = computed(() => {
    const role = auth.user?.role?.name;
    return role === 'Admin' || role === 'System Admin';
});

const deleteLeadConfirmMessage =
    'This permanently removes the lead and related CRM data (line items, activities, assignment history, linked communications, and commission rows for this lead). The customer record is kept. Continue?';

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
    lostReasonCode.value = '';
}

async function confirmLostModal() {
    const lead = lostModalLead.value;
    if (!lead || !lostReasonReady.value) {
        toast.error('Please choose a reason.');
        return;
    }
    lostSaving.value = true;
    movingLeadId.value = lead.id;
    try {
        await axios.put(`/api/leads/${lead.id}`, {
            stage: 'lost',
            lost_reason_code: lostReasonCode.value,
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
        toast.success('Lead deleted.');
        loadPipeline();
    } catch (error) {
        console.error('Failed to delete lead:', error);
        const msg = error?.response?.data?.message || 'Failed to delete lead. Please try again.';
        toast.error(msg);
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
