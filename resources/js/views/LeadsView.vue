<template>
    <ListingPageShell
        title="Leads"
        subtitle="Pipeline opportunities by stage — export, assign, and jump to customer records."
        :badge="leadsBadge"
    >
        <template #actions>
            <button type="button" class="listing-btn-outline w-full sm:w-auto" @click="exportToCSV">
                Export CSV
            </button>
            <button type="button" class="listing-btn-accent w-full sm:w-auto touch-manipulation" @click="openCreateForm">
                + Add Lead
            </button>
        </template>

        <template #filters>
            <div class="flex flex-col lg:flex-row lg:flex-wrap gap-3 lg:items-end">
                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 cursor-pointer w-fit">
                    <input v-model="filters.assigned_by_me" type="checkbox" class="rounded border-slate-300 text-blue-600" @change="loadLeads(1)" />
                    <span class="whitespace-nowrap">Leads I assigned</span>
                </label>
                <div class="w-full sm:w-48">
                    <label class="listing-label">Stage</label>
                    <select v-model="filters.stage" class="listing-input" @change="loadLeads(1)">
                        <option value="">All stages</option>
                        <option value="follow_up">Follow Up</option>
                        <option value="lead">Lead</option>
                        <option value="hot_lead">Hot Lead</option>
                        <option value="quotation">Quotation</option>
                        <option value="won">Won</option>
                        <option value="lost">Lost</option>
                    </select>
                </div>
                <button type="button" class="listing-btn-primary" @click="loadLeads(1)">Filter</button>
            </div>
        </template>

        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[640px]">
                <thead class="listing-thead">
                    <tr>
                        <th class="listing-th">Customer</th>
                        <th class="listing-th">Stage</th>
                        <th class="listing-th">Source</th>
                        <th class="listing-th">Assigned To</th>
                        <th class="listing-th">Value</th>
                        <th class="listing-th">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="lead in leads" :key="lead.id" class="listing-row">
                        <td class="listing-td-strong">{{ lead.customer?.name }}</td>
                        <td class="listing-td">
                            <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium" :class="getStageClass(lead.stage)">
                                {{ formatStage(lead.stage) }}
                            </span>
                        </td>
                        <td class="listing-td">{{ lead.source || '—' }}</td>
                        <td class="listing-td">{{ lead.assignee?.name || '—' }}</td>
                        <td class="listing-td font-semibold text-slate-800">£{{ formatNumber(lead.pipeline_value || 0) }}</td>
                        <td class="listing-td">
                            <div class="flex flex-wrap gap-x-3 gap-y-1">
                                <button type="button" class="text-violet-600 hover:text-violet-800 font-medium text-sm" title="Log Activity" @click="openActivityModal(lead)">
                                    Log
                                </button>
                                <router-link v-if="lead.customer_id" :to="`/customers/${lead.customer_id}`" class="listing-link-edit">
                                    View
                                </router-link>
                                <button type="button" class="listing-link-edit" @click="openEditForm(lead)">Edit</button>
                                <button type="button" class="listing-link-delete" @click="openDeleteConfirm(lead)">Delete</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="md:hidden space-y-3 px-3 pb-3">
            <div
                v-for="lead in leads"
                :key="`mobile-${lead.id}`"
                class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-2"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="text-sm font-semibold text-slate-900">{{ lead.customer?.name || '—' }}</div>
                    <span class="inline-flex rounded-md px-2 py-0.5 text-xs font-medium" :class="getStageClass(lead.stage)">
                        {{ formatStage(lead.stage) }}
                    </span>
                </div>
                <div class="text-sm text-slate-600">Source: {{ lead.source || '—' }}</div>
                <div class="text-sm text-slate-600">Assigned: {{ lead.assignee?.name || '—' }}</div>
                <div class="text-sm font-semibold text-slate-800">Value: £{{ formatNumber(lead.pipeline_value || 0) }}</div>
                <div class="flex flex-wrap gap-3 pt-1">
                    <button type="button" class="text-violet-600 hover:text-violet-800 font-medium text-sm" @click="openActivityModal(lead)">Log</button>
                    <router-link v-if="lead.customer_id" :to="`/customers/${lead.customer_id}`" class="listing-link-edit">View</router-link>
                    <button type="button" class="listing-link-edit" @click="openEditForm(lead)">Edit</button>
                    <button type="button" class="listing-link-delete" @click="openDeleteConfirm(lead)">Delete</button>
                </div>
            </div>
        </div>

        <template #pagination>
            <Pagination
                v-if="pagination"
                :pagination="pagination"
                embedded
                result-label="leads"
                singular-label="lead"
                @page-change="loadLeads"
            />
        </template>
    </ListingPageShell>

    <LeadForm
        v-if="showForm"
        :lead="selectedLead"
        @close="closeForm"
        @saved="handleSaved"
    />

    <DeleteConfirm
        v-if="showDeleteConfirm"
        title="Delete Lead"
        :message="`Are you sure you want to delete this lead? This will also delete all associated activities.`"
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
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import LeadForm from '@/components/LeadForm.vue';
import DeleteConfirm from '@/components/DeleteConfirm.vue';
import LogActivityModal from '@/components/LogActivityModal.vue';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';
import { formatLeadStage } from '@/utils/displayFormat';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();
const leads = ref([]);
const filters = ref({ stage: '', assigned_by_me: false });
const pagination = ref(null);
const showForm = ref(false);
const selectedLead = ref(null);
const showDeleteConfirm = ref(false);
const leadToDelete = ref(null);
const deleting = ref(false);
const showActivityModal = ref(false);
const activityLead = ref(null);

const leadsBadge = computed(() =>
    pagination.value?.total != null ? `${pagination.value.total} Total` : null,
);

const formatStage = (stage) => formatLeadStage(stage, '');

const getStageClass = (stage) => {
    const classes = {
        follow_up: 'bg-blue-100 text-blue-800',
        lead: 'bg-yellow-100 text-yellow-800',
        hot_lead: 'bg-orange-100 text-orange-800',
        quotation: 'bg-purple-100 text-purple-800',
        won: 'bg-green-100 text-green-800',
        lost: 'bg-red-100 text-red-800',
    };
    return classes[stage] || 'bg-slate-100 text-slate-800';
};

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num);
};

const loadLeads = async (page = 1) => {
    try {
        const params = { page, per_page: 15 };
        if (filters.value.stage) params.stage = filters.value.stage;
        if (filters.value.assigned_by_me) params.assigned_by_me = 1;

        const { data } = await axios.get('/api/leads', { params });
        leads.value = data.data || [];
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page || 15,
            total: data.total || 0,
        };
    } catch (error) {
        console.error('Failed to load leads:', error);
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
    loadLeads(pagination.value?.current_page || 1);
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
        loadLeads(pagination.value?.current_page || 1);
    } catch (error) {
        console.error('Failed to delete lead:', error);
        toast.error('Failed to delete lead. Please try again.');
    } finally {
        deleting.value = false;
    }
};

const exportToCSV = async () => {
    try {
        const params = { per_page: 10000 };
        if (filters.value.stage) {
            params.stage = filters.value.stage;
        }
        
        const { data } = await axios.get('/api/leads', { params });
        const allLeads = data.data || [];
        
        const columns = [
            { key: 'customer.name', label: 'Customer' },
            { key: 'stage', label: 'Stage' },
            { key: 'source', label: 'Source' },
            { key: 'assignee.name', label: 'Assigned To' },
            { key: 'pipeline_value', label: 'Value' },
        ];
        
        exportCSV(allLeads, columns, `leads_export_${new Date().toISOString().split('T')[0]}.csv`);
    } catch (error) {
        console.error('Export failed:', error);
        toast.error('Failed to export leads. Please try again.');
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

const handleActivitySaved = () => {
    loadLeads(pagination.value?.current_page || 1);
    closeActivityModal();
};

onMounted(() => loadLeads());
</script>

