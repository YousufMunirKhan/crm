<template>
    <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Leads</h1>
            <div class="flex flex-wrap gap-2 sm:gap-3">
                <label class="flex items-center gap-2 px-3 py-2 border border-slate-300 rounded-lg bg-white cursor-pointer text-sm">
                    <input v-model="filters.assigned_by_me" type="checkbox" class="rounded border-slate-300" @change="loadLeads(1)" />
                    <span class="text-slate-700 whitespace-nowrap">Leads I assigned</span>
                </label>
                <select
                    v-model="filters.stage"
                    @change="loadLeads"
                    class="px-3 sm:px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 text-sm w-full sm:w-auto"
                >
                    <option value="">All Stages</option>
                    <option value="follow_up">Follow Up</option>
                    <option value="lead">Lead</option>
                    <option value="hot_lead">Hot Lead</option>
                    <option value="quotation">Quotation</option>
                    <option value="won">Won</option>
                    <option value="lost">Lost</option>
                </select>
                <button
                    @click="exportToCSV"
                    class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700 text-sm"
                >
                    Export CSV
                </button>
                <button
                    @click="openCreateForm"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 text-sm whitespace-nowrap"
                >
                    + Add Lead
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden overflow-x-auto">
            <table class="w-full min-w-[640px]">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Customer</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Stage</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Source</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Assigned To</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Value</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="lead in leads" :key="lead.id" class="hover:bg-slate-50">
                        <td class="px-6 py-4 text-sm text-slate-900">{{ lead.customer?.name }}</td>
                        <td class="px-6 py-4 text-sm">
                            <span class="px-2 py-1 rounded text-xs" :class="getStageClass(lead.stage)">
                                {{ formatStage(lead.stage) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">{{ lead.source || '-' }}</td>
                        <td class="px-6 py-4 text-sm text-slate-600">{{ lead.assignee?.name || '-' }}</td>
                        <td class="px-6 py-4 text-sm font-medium text-slate-900">£{{ formatNumber(lead.pipeline_value || 0) }}</td>
                        <td class="px-6 py-4 text-sm">
                            <div class="flex gap-2">
                                <button
                                    @click="openActivityModal(lead)"
                                    class="text-purple-600 hover:underline"
                                    title="Log Activity"
                                >
                                    Log
                                </button>
                                <router-link
                                    v-if="lead.customer_id"
                                    :to="`/customers/${lead.customer_id}`"
                                    class="text-blue-600 hover:underline"
                                >
                                    View
                                </router-link>
                                <button
                                    @click="openEditForm(lead)"
                                    class="text-green-600 hover:underline"
                                >
                                    Edit
                                </button>
                                <button
                                    @click="openDeleteConfirm(lead)"
                                    class="text-red-600 hover:underline"
                                >
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Pagination
            v-if="pagination"
            :pagination="pagination"
            @page-change="loadLeads"
        />

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
            :message="`Are you sure you want to delete this lead? This will also delete all associated activities.`"
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
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import LeadForm from '@/components/LeadForm.vue';
import DeleteConfirm from '@/components/DeleteConfirm.vue';
import LogActivityModal from '@/components/LogActivityModal.vue';
import Pagination from '@/components/Pagination.vue';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';
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

const formatStage = (stage) => {
    return stage?.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) || '';
};

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

