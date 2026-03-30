<template>
    <div class="w-full min-w-0 max-w-7xl mx-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 min-w-0">
            <h1 class="text-2xl font-bold text-slate-900">Reports & Analytics</h1>
            <div class="flex flex-wrap gap-2 w-full sm:w-auto min-w-0">
                <input
                    v-model="filters.from"
                    type="date"
                    class="w-full sm:w-auto min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm"
                />
                <input
                    v-model="filters.to"
                    type="date"
                    class="w-full sm:w-auto min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm"
                />
                <select
                    v-model="filters.employee_id"
                    class="w-full sm:w-auto min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm sm:min-w-[150px]"
                >
                    <option value="">All Employees</option>
                    <option v-for="emp in employees" :key="emp.id" :value="emp.id">
                        {{ emp.name }}
                    </option>
                </select>
                <button 
                    @click="loadReports" 
                    :disabled="loading"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg text-sm hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 touch-manipulation w-full sm:w-auto"
                >
                    <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ loading ? 'Loading...' : 'Apply' }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 min-w-0">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">Funnel Report</h3>
                <div class="space-y-3">
                    <div
                        v-for="(count, stage) in funnelData.funnel"
                        :key="stage"
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between min-w-0"
                    >
                        <span class="text-sm text-slate-600 capitalize min-w-0">{{ stage.replace('_', ' ') }}</span>
                        <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1 sm:flex-initial sm:justify-end">
                            <div class="flex-1 sm:w-32 max-w-full bg-slate-200 rounded-full h-2 min-w-0">
                                <div
                                    class="bg-blue-600 h-2 rounded-full"
                                    :style="{ width: `${(count / (funnelData.funnel.follow_up || 1)) * 100}%` }"
                                ></div>
                            </div>
                            <span class="text-sm font-medium text-slate-900 w-10 sm:w-12 text-right tabular-nums shrink-0">{{ count }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 min-w-0">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">Communication Analytics</h3>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-600">Sent</span>
                        <span class="text-sm font-medium text-slate-900">{{ commData.sent || 0 }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-600">Received</span>
                        <span class="text-sm font-medium text-slate-900">{{ commData.received || 0 }}</span>
                    </div>
                    <div v-for="(count, channel) in commData.by_channel" :key="channel" class="flex justify-between">
                        <span class="text-sm text-slate-600 capitalize">{{ channel }}</span>
                        <span class="text-sm font-medium text-slate-900">{{ count }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Today's Follow-ups -->
        <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 md:p-6 min-w-0">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Today's Follow-ups by Employee</h3>
            <div v-if="todaysFollowUps.length === 0" class="text-center py-8 text-slate-500">
                No follow-ups scheduled for today
            </div>
            <div v-else class="space-y-4">
                <div
                    v-for="group in todaysFollowUps"
                    :key="group.agent_id"
                    class="border border-slate-200 rounded-lg p-4"
                >
                    <div class="font-medium text-slate-900 mb-2">
                        {{ group.agent_name }} ({{ group.count }} follow-ups)
                    </div>
                    <div class="space-y-2 mt-2">
                        <div
                            v-for="followUp in group.follow_ups"
                            :key="followUp.id"
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between text-sm text-slate-600 pl-4 border-l-2 border-blue-200 py-2 min-w-0"
                        >
                            <div class="min-w-0 break-words">
                                {{ followUp.customer?.name }} - 
                                <span v-if="followUp.items?.length">
                                    {{ followUp.items.map(item => item.product?.name).filter(Boolean).join(', ') }}
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-2 shrink-0 sm:justify-end">
                                <button
                                    @click="openActivityModal(followUp)"
                                    class="px-2 py-1.5 text-xs bg-green-600 text-white rounded hover:bg-green-700 touch-manipulation"
                                >
                                    Log
                                </button>
                                <router-link
                                    v-if="followUp.customer_id"
                                    :to="`/customers/${followUp.customer_id}`"
                                    class="px-2 py-1.5 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 touch-manipulation inline-block text-center"
                                >
                                    View
                                </router-link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Employees Pipeline -->
        <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 md:p-6 min-w-0">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">All Employees Sales Pipeline</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px]">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Employee</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Follow Up</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Lead</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Hot Lead</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Quotation</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Won Leads</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Lost Leads</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Products (W/L/P)</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Won Revenue</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Pipeline Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="employee in allEmployeesPipeline" :key="employee.employee_id">
                            <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ employee.employee_name }}</td>
                            <td class="px-4 py-2 text-sm text-slate-600">{{ employee.follow_up || 0 }}</td>
                            <td class="px-4 py-2 text-sm text-slate-600">{{ employee.lead || 0 }}</td>
                            <td class="px-4 py-2 text-sm text-slate-600">{{ employee.hot_lead || 0 }}</td>
                            <td class="px-4 py-2 text-sm text-slate-600">{{ employee.quotation || 0 }}</td>
                            <td class="px-4 py-2 text-sm text-green-600 font-medium">{{ employee.won || 0 }}</td>
                            <td class="px-4 py-2 text-sm text-red-600">{{ employee.lost || 0 }}</td>
                            <td class="px-4 py-2 text-sm text-slate-600">
                                <span class="text-green-600 font-medium">{{ employee.products?.won || 0 }}</span> /
                                <span class="text-red-500">{{ employee.products?.lost || 0 }}</span> /
                                <span class="text-amber-500">{{ employee.products?.pending || 0 }}</span>
                            </td>
                            <td class="px-4 py-2 text-sm font-bold text-green-700">£{{ formatNumber(employee.won_revenue || 0) }}</td>
                            <td class="px-4 py-2 text-sm font-medium text-slate-900">£{{ formatNumber(employee.total_value || 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Revenue by Employee -->
        <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 md:p-6 min-w-0">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Revenue by Employee</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[600px]">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Employee</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Total Revenue</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Lead Revenue</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Invoice Revenue</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Products Won</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Products Lost</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="employee in revenueByEmployee" :key="employee.employee_id">
                            <td class="px-4 py-2 text-sm font-medium text-slate-900">{{ employee.employee_name }}</td>
                            <td class="px-4 py-2 text-sm font-bold text-slate-900">£{{ formatNumber(employee.revenue || 0) }}</td>
                            <td class="px-4 py-2 text-sm text-slate-600">£{{ formatNumber(employee.lead_revenue || 0) }}</td>
                            <td class="px-4 py-2 text-sm text-slate-600">£{{ formatNumber(employee.invoice_revenue || 0) }}</td>
                            <td class="px-4 py-2 text-sm text-green-600 font-medium">{{ employee.products?.won || 0 }}</td>
                            <td class="px-4 py-2 text-sm text-red-500">{{ employee.products?.lost || 0 }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Team Location Status -->
        <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 md:p-6 min-w-0">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Team Location Status</h3>
            <div v-if="teamLocationStatus.length === 0" class="text-center py-8 text-slate-500">
                No recent customer visits/meetings
            </div>
            <div v-else class="space-y-4">
                <div
                    v-for="agent in teamLocationStatus"
                    :key="agent.agent_id"
                    class="border border-slate-200 rounded-lg p-4"
                >
                    <div class="font-medium text-slate-900 mb-2">
                        {{ agent.agent_name }} ({{ agent.activity_count }} activities)
                    </div>
                    <div class="space-y-2 mt-2">
                        <div
                            v-for="customer in agent.customers"
                            :key="customer.id"
                            class="text-sm text-slate-600 pl-4"
                        >
                            {{ customer.name }} - {{ customer.phone }}
                            <span v-if="customer.address" class="text-xs text-slate-500">({{ customer.address }})</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Agent Performance -->
        <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 md:p-6 min-w-0">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Agent Performance</h3>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[600px]">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Agent</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Leads</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Won Leads</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Won Products</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Conversion</th>
                            <th class="px-4 py-2 text-left text-xs font-medium text-slate-700">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="agent in agentData" :key="agent.id">
                            <td class="px-4 py-2 text-sm text-slate-900">{{ agent.name }}</td>
                            <td class="px-4 py-2 text-sm text-slate-600">{{ agent.leads_count }}</td>
                            <td class="px-4 py-2 text-sm text-green-600 font-medium">{{ agent.won_count }}</td>
                            <td class="px-4 py-2 text-sm text-green-600 font-medium">{{ agent.won_products || 0 }}</td>
                            <td class="px-4 py-2 text-sm text-slate-600">{{ agent.conversion_rate }}%</td>
                            <td class="px-4 py-2 text-sm font-medium text-slate-900">£{{ formatNumber(agent.revenue) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

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
import LogActivityModal from '@/components/LogActivityModal.vue';

const filters = ref({
    from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    to: new Date().toISOString().split('T')[0],
    period: 'month',
    employee_id: '',
});

const employees = ref([]);
const funnelData = ref({ funnel: {} });
const commData = ref({});
const agentData = ref([]);
const allEmployeesPipeline = ref([]);
const todaysFollowUps = ref([]);
const revenueByEmployee = ref([]);
const teamLocationStatus = ref([]);
const showActivityModal = ref(false);
const activityLead = ref(null);
const loading = ref(false);

const loadEmployees = async () => {
    try {
        const response = await axios.get('/api/users', { params: { for_sales_report: 1 } });
        employees.value = response.data.data || response.data || [];
    } catch (error) {
        console.error('Failed to load employees:', error);
    }
};

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num);
};

const loadReports = async () => {
    loading.value = true;
    try {
        const params = {
            from: filters.value.from,
            to: filters.value.to,
            period: filters.value.period,
        };
        
        // Add agent_id filter if employee is selected
        if (filters.value.employee_id) {
            params.agent_id = filters.value.employee_id;
        }

        console.log('Loading reports with params:', params);

        const [
            funnelRes,
            commRes,
            agentsRes,
            pipelineRes,
            followUpsRes,
            revenueRes,
            locationRes,
        ] = await Promise.all([
            axios.get('/api/reporting/funnel', { params }),
            axios.get('/api/reporting/communications', { params }),
            axios.get('/api/reporting/agents', { params }),
            axios.get('/api/reporting/all-employees-pipeline', { params }),
            axios.get('/api/reporting/todays-followups', { params }),
            axios.get('/api/reporting/revenue-by-employee', { params }),
            axios.get('/api/reporting/team-location-status', { params }),
        ]);

        funnelData.value = funnelRes.data;
        commData.value = commRes.data;
        agentData.value = agentsRes.data;
        allEmployeesPipeline.value = pipelineRes.data || [];
        todaysFollowUps.value = followUpsRes.data || [];
        revenueByEmployee.value = revenueRes.data || [];
        teamLocationStatus.value = locationRes.data || [];
        
        console.log('Reports loaded successfully');
    } catch (error) {
        console.error('Failed to load reports:', error);
    } finally {
        loading.value = false;
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
    loadReports();
    closeActivityModal();
};

onMounted(() => {
    loadEmployees();
    loadReports();
});
</script>


