<template>
        <div class="max-w-7xl mx-auto p-3 sm:p-6 space-y-4 sm:space-y-6">
        <div class="flex flex-col gap-3">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Lead Pipeline</h1>
            <div class="flex flex-wrap items-center gap-2">
                <input
                    v-model="filters.from"
                    type="date"
                    class="px-3 py-2 border border-slate-300 rounded-lg text-sm"
                    placeholder="From"
                />
                <input
                    v-model="filters.to"
                    type="date"
                    class="px-3 py-2 border border-slate-300 rounded-lg text-sm"
                    placeholder="To"
                />
                <label class="flex items-center gap-2 px-3 py-2 border border-slate-300 rounded-lg bg-white cursor-pointer">
                    <input v-model="filters.assigned_by_me" type="checkbox" class="rounded border-slate-300" />
                    <span class="text-sm text-slate-700">Leads I assigned</span>
                </label>
                <select
                    v-if="isAdmin"
                    v-model="filters.assigned_to"
                    class="px-3 py-2 border border-slate-300 rounded-lg text-sm min-w-[150px]"
                >
                    <option value="">All Employees</option>
                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                        {{ emp.name }}
                    </option>
                </select>
                <button
                    @click="applyFilters"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm"
                >
                    Apply
                </button>
                <button
                    @click="clearFilters"
                    class="px-3 py-2 border border-slate-300 text-slate-600 rounded-lg hover:bg-slate-50 text-sm"
                >
                    Clear
                </button>
                <button
                    @click="openCreateForm"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 text-sm"
                >
                    + Create Lead
                </button>
            </div>
        </div>

        <template v-if="loading">
            <div class="flex justify-center items-center py-16">
                <div class="text-slate-500">Loading pipeline...</div>
            </div>
        </template>
        <template v-else-if="isEmpty">
            <div class="bg-white rounded-xl shadow-sm p-12 text-center">
            <div class="text-slate-400 mb-4">
                <svg class="mx-auto h-16 w-16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-slate-700 mb-2">No Leads Found</h3>
            <p class="text-slate-500 mb-6">
                There are no leads in your pipeline yet. Create a new lead to get started.
            </p>
            <button
                @click="openCreateForm"
                class="px-6 py-3 bg-slate-900 text-white rounded-lg hover:bg-slate-800"
            >
                + Create Your First Lead
            </button>
            </div>
        </template>
        <template v-else>
            <div class="overflow-x-auto overflow-y-hidden pb-2 -mx-3 sm:mx-0">
            <div class="flex gap-3 sm:grid sm:grid-cols-6 min-w-0 sm:min-w-full" style="min-width: min(100%, 1680px);">
                <div
                    v-for="stageKey in pipelineStageOrder"
                    :key="stageKey"
                    class="bg-slate-50 rounded-lg p-3 sm:p-4 flex-shrink-0 w-[280px] sm:w-auto sm:min-w-0"
                >
                    <div class="flex justify-between items-center mb-2 sm:mb-3">
                        <h3 class="font-semibold text-slate-900 capitalize text-sm sm:text-base">
                            {{ stageKey.replace('_', ' ') }}
                        </h3>
                        <span class="text-xs bg-white px-2 py-0.5 sm:py-1 rounded">{{ (pipeline[stageKey] || []).length }}</span>
                    </div>
                    <div class="space-y-2 max-h-[55vh] sm:max-h-[60vh] overflow-y-auto overflow-x-hidden">
                        <div v-if="!(pipeline[stageKey] || []).length" class="text-center py-4 text-slate-400 text-xs sm:text-sm">
                            No leads
                        </div>
                        <div
                            v-for="lead in (pipeline[stageKey] || [])"
                            :key="lead.id"
                            class="bg-white rounded-lg p-3 shadow-sm cursor-pointer hover:shadow-md transition active:bg-slate-50 touch-manipulation"
                            @click="viewLead(lead)"
                        >
                            <div class="font-medium text-slate-900 text-sm">{{ lead.customer?.name }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">{{ lead.customer?.phone }}</div>
                            <!-- Appointment: who created & when -->
                            <div v-if="getLeadAppointment(lead)" class="mt-1.5 text-xs text-amber-700 bg-amber-50 rounded px-2 py-1 flex items-center gap-1">
                                <span>📅</span>
                                <span>{{ getLeadAppointment(lead) }}</span>
                            </div>
                            <div v-if="lead.items && lead.items.length > 0" class="mt-1 space-y-0.5">
                                <div
                                    v-for="item in lead.items"
                                    :key="item.id"
                                    class="text-xs font-medium"
                                    :class="{
                                        'text-green-600': item.status === 'won',
                                        'text-red-500': item.status === 'lost',
                                        'text-blue-600': item.status === 'pending' || !item.status
                                    }"
                                >
                                    {{ item.product?.name || 'Unknown Product' }}
                                    <span v-if="item.status && item.status !== 'pending'" class="text-xs opacity-75">({{ item.status }})</span>
                                </div>
                            </div>
                            <div v-else-if="lead.product" class="text-xs font-medium text-blue-600 mt-1">
                                {{ lead.product.name }}
                            </div>
                            <div v-else class="text-xs text-slate-400 mt-1 italic">
                                No Product
                            </div>
                            <div class="text-xs font-medium text-slate-700 mt-1.5">
                                £{{ formatNumber(getLeadValue(lead)) }}
                            </div>
                            <div v-if="lead.assignee" class="text-xs text-slate-500 mt-0.5">
                                {{ lead.assignee.name }}
                            </div>
                            <div class="flex gap-1.5 sm:gap-2 mt-2 flex-wrap">
                            <button
                                @click.stop="openFollowUpModal(lead)"
                                class="text-xs text-amber-600 hover:underline flex items-center gap-1 min-h-[44px] min-w-[44px] sm:min-h-0 sm:min-w-0 py-1 sm:py-0 -my-1"
                                title="Schedule follow-up"
                            >
                                <svg class="w-3.5 h-3.5 sm:w-3 sm:h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                                <span class="hidden sm:inline">Follow-up</span>
                            </button>
                            <button
                                @click.stop="openActivityModal(lead)"
                                class="text-xs text-green-600 hover:underline flex items-center gap-1 min-h-[44px] min-w-[44px] sm:min-h-0 sm:min-w-0 py-1 sm:py-0 -my-1"
                            >
                                <svg class="w-3.5 h-3.5 sm:w-3 sm:h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                <span class="hidden sm:inline">Log</span>
                            </button>
                            <button
                                @click.stop="openEditForm(lead)"
                                class="text-xs text-blue-600 hover:underline min-h-[44px] min-w-[44px] sm:min-h-0 sm:min-w-0 py-1 sm:py-0 -my-1"
                            >
                                Edit
                            </button>
                            <button
                                @click.stop="openDeleteConfirm(lead)"
                                class="text-xs text-red-600 hover:underline min-h-[44px] min-w-[44px] sm:min-h-0 sm:min-w-0 py-1 sm:py-0 -my-1"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            </div>
            </div>
        </template>

        <!-- Lead Form Modal -->
        <LeadForm
            v-if="showForm"
            :lead="selectedLead"
            @close="closeForm"
            @saved="handleSaved"
        />

        <!-- Delete Confirmation Modal -->
        <DeleteConfirm
            v-if="showDeleteConfirm"
            title="Delete Lead"
            :message="`Are you sure you want to delete this lead?`"
            :loading="deleting"
            @confirm="confirmDelete"
            @cancel="closeDeleteConfirm"
        />

        <!-- Log Activity Modal -->
        <LogActivityModal
            v-if="showActivityModal && activityLead"
            :lead="activityLead"
            @close="closeActivityModal"
            @saved="handleActivitySaved"
        />

        <!-- Schedule Follow-up Modal -->
        <ScheduleFollowUpModal
            v-if="showFollowUpModal && followUpLead"
            :lead="followUpLead"
            @close="closeFollowUpModal"
            @saved="handleFollowUpSaved"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import LeadForm from '@/components/LeadForm.vue';
import DeleteConfirm from '@/components/DeleteConfirm.vue';
import LogActivityModal from '@/components/LogActivityModal.vue';
import ScheduleFollowUpModal from '@/components/ScheduleFollowUpModal.vue';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const toast = useToastStore();
const auth = useAuthStore();

const pipelineStageOrder = ['follow_up', 'lead', 'hot_lead', 'quotation', 'won', 'lost'];
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

// Check if pipeline is empty (all stages have 0 leads)
const isEmpty = computed(() => {
    const stages = Object.values(pipeline.value);
    if (stages.length === 0) return true;
    return stages.every(stage => !stage || stage.length === 0);
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num);
};

const getLeadValue = (lead) => {
    // For won leads, calculate from items if available
    if (lead.stage === 'won' && lead.items && lead.items.length > 0) {
        const itemsTotal = lead.items.reduce((sum, item) => {
            return sum + (parseFloat(item.total_price) || 0);
        }, 0);
        return itemsTotal > 0 ? itemsTotal : (lead.pipeline_value || 0);
    }
    // Use total_value if available (from backend), otherwise pipeline_value
    return lead.total_value || lead.pipeline_value || 0;
};

/** Format next/latest appointment for a lead (from activities type=appointment). */
function getLeadAppointment(lead) {
    const activities = lead.activities || [];
    const appt = activities.find(a => a.type === 'appointment') || activities[0];
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
    if (lead.customer_id) {
        router.push(`/customers/${lead.customer_id}`);
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
    loadEmployees();
    loadPipeline();
});
</script>
