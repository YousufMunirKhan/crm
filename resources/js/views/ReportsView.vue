<template>
    <div class="w-full min-w-0 max-w-7xl mx-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-5">
        <div class="flex flex-col gap-4">
            <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-2xl font-bold text-slate-900">Business Reports</h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ selectedEmployee ? `Focused on ${selectedEmployee.name}` : 'All employee performance and sales movement' }}
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1.2fr_auto_auto] gap-2 w-full lg:w-auto">
                    <input v-model="filters.from" type="date" class="report-input" />
                    <input v-model="filters.to" type="date" class="report-input" />
                    <select v-model="filters.employee_id" class="report-input">
                        <option value="">All Employees</option>
                        <option v-for="emp in employees" :key="emp.id" :value="String(emp.id)">
                            {{ emp.name }}
                        </option>
                    </select>
                    <button type="button" class="report-btn-primary" :disabled="loading" @click="loadReports">
                        {{ loading ? 'Loading...' : 'Apply' }}
                    </button>
                    <button type="button" class="report-btn-outline" :disabled="teamRows.length === 0" @click="exportTeamCsv">
                        Export CSV
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button
                    v-for="range in quickRanges"
                    :key="range.key"
                    type="button"
                    class="px-3 py-1.5 rounded border text-xs font-medium"
                    :class="activeQuickRange === range.key ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                    @click="setQuickRange(range.key)"
                >
                    {{ range.label }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-6 gap-3">
            <div v-for="card in summaryCards" :key="card.label" class="bg-white border border-slate-200 rounded-lg p-4 min-w-0">
                <div class="text-xs font-semibold text-slate-500 uppercase">{{ card.label }}</div>
                <div class="mt-2 text-2xl font-bold text-slate-900 tabular-nums break-words">{{ card.value }}</div>
                <div class="mt-1 text-xs text-slate-500">{{ card.help }}</div>
            </div>
        </div>

        <div class="bg-white border border-slate-200 rounded-lg p-3">
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="section in sections"
                    :key="section.key"
                    type="button"
                    class="px-3 py-2 rounded text-sm font-medium"
                    :class="activeSection === section.key ? 'bg-slate-900 text-white' : 'text-slate-600 hover:bg-slate-100'"
                    @click="activeSection = section.key"
                >
                    {{ section.label }}
                </button>
            </div>
        </div>

        <section v-if="activeSection === 'overview'" class="space-y-4">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 bg-white border border-slate-200 rounded-lg p-4 sm:p-5">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Where the business stands</h2>
                            <p class="text-sm text-slate-500">Sales are counted when product lines are won in the selected period.</p>
                        </div>
                        <div class="text-xs font-medium text-slate-500">{{ periodLabel }}</div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div v-for="item in operatingCards" :key="item.label" class="rounded border border-slate-200 bg-slate-50 p-3">
                            <div class="text-xs text-slate-500">{{ item.label }}</div>
                            <div class="mt-1 text-lg font-bold text-slate-900">{{ item.value }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ item.help }}</div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5">
                    <h2 class="text-lg font-semibold text-slate-900 mb-4">Communication</h2>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <span class="text-slate-600">Sent</span>
                            <span class="font-semibold text-slate-900">{{ commData.sent || 0 }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-slate-600">Received</span>
                            <span class="font-semibold text-slate-900">{{ commData.received || 0 }}</span>
                        </div>
                        <div v-for="[channel, count] in communicationChannels" :key="channel" class="flex justify-between gap-3">
                            <span class="text-slate-600 capitalize">{{ channel }}</span>
                            <span class="font-semibold text-slate-900">{{ count }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Sales workflow</h2>
                        <p class="text-sm text-slate-500">Follow the customer journey from contact to final outcome.</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-7 gap-2">
                    <div v-for="stage in stageRows" :key="stage.key" class="rounded border border-slate-200 p-3">
                        <div class="text-xs font-medium text-slate-500">{{ stage.label }}</div>
                        <div class="mt-2 text-xl font-bold text-slate-900">{{ stage.count }}</div>
                        <div class="mt-1 h-2 bg-slate-100 rounded overflow-hidden">
                            <div class="h-2 bg-blue-600 rounded" :style="{ width: `${stage.percent}%` }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-else-if="activeSection === 'team'" class="bg-white border border-slate-200 rounded-lg overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Team Performance</h2>
                    <p class="text-sm text-slate-500">Compare users using the same date range and sales basis.</p>
                </div>
                <button type="button" class="report-btn-outline" :disabled="teamRows.length === 0" @click="filters.employee_id = ''; loadReports()">
                    Show All
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1040px]">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="report-th">Employee</th>
                            <th class="report-th text-right">Appointments</th>
                            <th class="report-th text-right">New Leads</th>
                            <th class="report-th text-right">Open Pipeline</th>
                            <th class="report-th text-right">Won Products</th>
                            <th class="report-th text-right">Won Leads</th>
                            <th class="report-th text-right">Conversion</th>
                            <th class="report-th text-right">Sales Revenue</th>
                            <th class="report-th text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in teamRows" :key="row.employee_id" class="hover:bg-slate-50">
                            <td class="report-td font-semibold text-slate-900">{{ row.employee_name }}</td>
                            <td class="report-td text-right">{{ row.appointments }}</td>
                            <td class="report-td text-right">{{ row.leads }}</td>
                            <td class="report-td text-right">{{ row.open_pipeline }}</td>
                            <td class="report-td text-right text-green-700 font-semibold">{{ row.won_products }}</td>
                            <td class="report-td text-right">{{ row.won_leads }}</td>
                            <td class="report-td text-right">{{ row.conversion_rate }}%</td>
                            <td class="report-td text-right font-semibold text-slate-900">GBP {{ formatNumber(row.revenue) }}</td>
                            <td class="report-td text-right">
                                <button type="button" class="text-sm font-medium text-blue-700 hover:underline" @click="selectEmployee(row.employee_id, 'employee')">
                                    View
                                </button>
                            </td>
                        </tr>
                        <tr v-if="teamRows.length === 0">
                            <td class="px-4 py-8 text-center text-sm text-slate-500" colspan="9">No team data for this period.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-else-if="activeSection === 'employee'" class="space-y-4">
            <div v-if="!filters.employee_id" class="bg-white border border-slate-200 rounded-lg p-8 text-center">
                <h2 class="text-lg font-semibold text-slate-900">Choose an employee</h2>
                <p class="mt-1 text-sm text-slate-500">Select one user above or use View from the team table.</p>
            </div>

            <template v-else>
                <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">{{ selectedEmployee?.name || 'Employee' }} Report</h2>
                            <p class="text-sm text-slate-500">Month target view plus product sales detail.</p>
                        </div>
                        <div class="w-full sm:w-56">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Target month</label>
                            <select v-model="selectedMonth" class="report-input w-full" @change="loadEmployeeReports">
                                <option v-for="month in monthOptions" :key="month.value" :value="month.value">{{ month.label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div v-if="employeeLoading" class="bg-white border border-slate-200 rounded-lg p-10 text-center text-sm text-slate-500">
                    Loading employee report...
                </div>

                <template v-else>
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3">
                        <div v-for="card in employeeActivityCards" :key="card.label" class="bg-white border border-slate-200 rounded-lg p-4">
                            <div class="text-sm text-slate-500">{{ card.label }}</div>
                            <div class="mt-1 text-xl font-bold text-slate-900">{{ card.value }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ card.help }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div v-for="card in employeeTargetCards" :key="card.label" class="bg-white border border-slate-200 rounded-lg p-4">
                            <div class="text-sm text-slate-500">{{ card.label }}</div>
                            <div class="mt-1 text-xl font-bold text-slate-900">{{ card.value }}</div>
                            <div class="mt-3 h-2 bg-slate-100 rounded overflow-hidden">
                                <div class="h-2 bg-emerald-600 rounded" :style="{ width: `${card.progress}%` }"></div>
                            </div>
                            <div class="mt-1 text-xs text-slate-500">{{ card.progress }}% achieved</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5">
                            <h3 class="text-base font-semibold text-slate-900 mb-3">Last Week</h3>
                            <div class="text-sm text-slate-500">{{ employeeOverview.last_week?.label || '-' }}</div>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div>
                                    <div class="text-xs text-slate-500">Won products</div>
                                    <div class="text-xl font-bold text-slate-900">{{ employeeOverview.last_week?.won_line_items || 0 }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-slate-500">Revenue</div>
                                    <div class="text-xl font-bold text-slate-900">GBP {{ formatNumber(employeeOverview.last_week?.total_revenue || 0) }}</div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5">
                            <h3 class="text-base font-semibold text-slate-900 mb-3">Selected Month</h3>
                            <div class="text-sm text-slate-500">
                                {{ employeeOverview.selected_month?.period?.from || '-' }} to {{ employeeOverview.selected_month?.period?.to || '-' }}
                            </div>
                            <div class="mt-3 grid grid-cols-2 gap-3">
                                <div>
                                    <div class="text-xs text-slate-500">Won products</div>
                                    <div class="text-xl font-bold text-slate-900">{{ employeeOverview.selected_month?.won_line_items || 0 }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-slate-500">Revenue</div>
                                    <div class="text-xl font-bold text-slate-900">GBP {{ formatNumber(employeeOverview.selected_month?.total_revenue || 0) }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
                        <div class="p-4 sm:p-5 border-b border-slate-100">
                            <h3 class="text-base font-semibold text-slate-900">Products Won In Current Filter</h3>
                            <p class="text-sm text-slate-500">{{ productReport.period?.from || filters.from }} to {{ productReport.period?.to || filters.to }}</p>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[760px]">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="report-th">Product</th>
                                        <th class="report-th">Customer</th>
                                        <th class="report-th text-right">Qty</th>
                                        <th class="report-th text-right">Unit Price</th>
                                        <th class="report-th text-right">Total</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <tr v-for="(item, index) in productReport.products || []" :key="index">
                                        <td class="report-td font-semibold text-slate-900">{{ item.product_name }}</td>
                                        <td class="report-td">{{ item.customer_name }}</td>
                                        <td class="report-td text-right">{{ item.quantity }}</td>
                                        <td class="report-td text-right">GBP {{ formatNumber(item.unit_price) }}</td>
                                        <td class="report-td text-right font-semibold">GBP {{ formatNumber(item.total_price) }}</td>
                                    </tr>
                                    <tr v-if="!(productReport.products || []).length">
                                        <td class="px-4 py-8 text-center text-sm text-slate-500" colspan="5">No won products for this employee in the selected range.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </template>
        </section>

        <section v-else-if="activeSection === 'pipeline'" class="bg-white border border-slate-200 rounded-lg overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-slate-100">
                <h2 class="text-lg font-semibold text-slate-900">Pipeline Report</h2>
                <p class="text-sm text-slate-500">Counts are lead opportunities created in the selected period; appointments are booked activities.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px]">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="report-th">Employee</th>
                            <th class="report-th text-right">Follow-up</th>
                            <th class="report-th text-right">Lead</th>
                            <th class="report-th text-right">Hot Lead</th>
                            <th class="report-th text-right">Appointment</th>
                            <th class="report-th text-right">Quotation</th>
                            <th class="report-th text-right">Won</th>
                            <th class="report-th text-right">Lost</th>
                            <th class="report-th text-right">Products W/L/P</th>
                            <th class="report-th text-right">Pipeline Value</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="row in allEmployeesPipeline" :key="row.employee_id">
                            <td class="report-td font-semibold text-slate-900">{{ row.employee_name }}</td>
                            <td class="report-td text-right">{{ row.follow_up || 0 }}</td>
                            <td class="report-td text-right">{{ row.lead || 0 }}</td>
                            <td class="report-td text-right">{{ row.hot_lead || 0 }}</td>
                            <td class="report-td text-right">{{ row.appointments || 0 }}</td>
                            <td class="report-td text-right">{{ row.quotation || 0 }}</td>
                            <td class="report-td text-right text-green-700 font-semibold">{{ row.won || 0 }}</td>
                            <td class="report-td text-right text-red-600">{{ row.lost || 0 }}</td>
                            <td class="report-td text-right">
                                {{ row.products?.won || 0 }} / {{ row.products?.lost || 0 }} / {{ row.products?.pending || 0 }}
                            </td>
                            <td class="report-td text-right font-semibold">GBP {{ formatNumber(row.total_value || 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-else-if="activeSection === 'revenue'" class="space-y-4">
            <div class="bg-white border border-slate-200 rounded-lg overflow-hidden">
                <div class="p-4 sm:p-5 border-b border-slate-100">
                    <h2 class="text-lg font-semibold text-slate-900">Product And Revenue Report</h2>
                    <p class="text-sm text-slate-500">Sales revenue is won product lines. Billed revenue is invoice total.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px]">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="report-th">Employee</th>
                                <th class="report-th text-right">Sales Revenue</th>
                                <th class="report-th text-right">Billed Revenue</th>
                                <th class="report-th text-right">Total Revenue</th>
                                <th class="report-th text-right">Won Products</th>
                                <th class="report-th text-right">Lost Products</th>
                                <th class="report-th text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in revenueByEmployee" :key="row.employee_id">
                                <td class="report-td font-semibold text-slate-900">{{ row.employee_name }}</td>
                                <td class="report-td text-right">GBP {{ formatNumber(row.lead_revenue || 0) }}</td>
                                <td class="report-td text-right">GBP {{ formatNumber(row.invoice_revenue || 0) }}</td>
                                <td class="report-td text-right font-semibold">GBP {{ formatNumber(row.revenue || 0) }}</td>
                                <td class="report-td text-right text-green-700 font-semibold">{{ row.products?.won || 0 }}</td>
                                <td class="report-td text-right text-red-600">{{ row.products?.lost || 0 }}</td>
                                <td class="report-td text-right">
                                    <button type="button" class="text-sm font-medium text-blue-700 hover:underline" @click="selectEmployee(row.employee_id, 'employee')">
                                        Products
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section v-else-if="activeSection === 'daily'" class="space-y-4">
            <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5">
                <h2 class="text-lg font-semibold text-slate-900">Today And Field Activity</h2>
                <p class="text-sm text-slate-500">Follow-ups are always for today. Activity locations use the selected date range.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5">
                    <h3 class="text-base font-semibold text-slate-900 mb-4">Today's Follow-ups</h3>
                    <div v-if="todaysFollowUps.length === 0" class="text-sm text-slate-500 py-8 text-center">No follow-ups scheduled for today.</div>
                    <div v-else class="space-y-4">
                        <div v-for="group in todaysFollowUps" :key="group.agent_id" class="border border-slate-200 rounded p-3">
                            <div class="font-semibold text-slate-900">{{ group.agent_name }} ({{ group.count }})</div>
                            <div class="mt-3 space-y-2">
                                <div v-for="followUp in group.follow_ups" :key="followUp.id" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm border-l-2 border-blue-200 pl-3 py-2">
                                    <div class="min-w-0">
                                        <div class="font-medium text-slate-900 truncate">{{ followUp.customer?.name || 'Unknown customer' }}</div>
                                        <div class="text-slate-500 truncate">{{ productNames(followUp) || 'No product selected' }}</div>
                                    </div>
                                    <div class="flex gap-2 shrink-0">
                                        <button type="button" class="px-2 py-1 rounded bg-green-600 text-white text-xs" @click="openActivityModal(followUp)">Log</button>
                                        <router-link v-if="followUp.customer_id" :to="`/customers/${followUp.customer_id}`" class="px-2 py-1 rounded bg-blue-600 text-white text-xs">View</router-link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-lg p-4 sm:p-5">
                    <h3 class="text-base font-semibold text-slate-900 mb-4">Recent Visits And Meetings</h3>
                    <div v-if="teamLocationStatus.length === 0" class="text-sm text-slate-500 py-8 text-center">No recent visits or meetings.</div>
                    <div v-else class="space-y-4">
                        <div v-for="agent in teamLocationStatus" :key="agent.agent_id" class="border border-slate-200 rounded p-3">
                            <div class="font-semibold text-slate-900">{{ agent.agent_name }} ({{ agent.activity_count }})</div>
                            <div class="mt-2 space-y-1">
                                <div v-for="customer in agent.customers" :key="customer.id" class="text-sm text-slate-600">
                                    {{ customer.name }} - {{ customer.phone || 'No phone' }}
                                    <span v-if="customer.address" class="text-xs text-slate-500">({{ customer.address }})</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <LogActivityModal
            v-if="showActivityModal && activityLead"
            :lead="activityLead"
            @close="closeActivityModal"
            @saved="handleActivitySaved"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import LogActivityModal from '@/components/LogActivityModal.vue';

const dateToInput = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const today = new Date();
const filters = ref({
    from: dateToInput(new Date(today.getFullYear(), today.getMonth(), 1)),
    to: dateToInput(today),
    period: 'month',
    employee_id: '',
});

const selectedMonth = ref(`${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}`);
const activeSection = ref('overview');
const activeQuickRange = ref('this_month');
const loading = ref(false);
const employeeLoading = ref(false);

const employees = ref([]);
const executiveData = ref({});
const funnelData = ref({ funnel: {} });
const commData = ref({});
const agentData = ref([]);
const allEmployeesPipeline = ref([]);
const todaysFollowUps = ref([]);
const revenueByEmployee = ref([]);
const teamLocationStatus = ref([]);
const salesTrend = ref([]);
const employeeOverview = ref({});
const productReport = ref({ products: [], total_revenue: 0, period: {} });
const showActivityModal = ref(false);
const activityLead = ref(null);

const sections = [
    { key: 'overview', label: 'Overview' },
    { key: 'team', label: 'Team' },
    { key: 'employee', label: 'Employee' },
    { key: 'pipeline', label: 'Pipeline' },
    { key: 'revenue', label: 'Products & Revenue' },
    { key: 'daily', label: 'Today' },
];

const quickRanges = [
    { key: 'today', label: 'Today' },
    { key: 'this_week', label: 'This Week' },
    { key: 'this_month', label: 'This Month' },
    { key: 'last_month', label: 'Last Month' },
    { key: 'year', label: 'This Year' },
];

const monthOptions = (() => {
    const opts = [];
    for (let i = 0; i < 12; i++) {
        const d = new Date(today.getFullYear(), today.getMonth() - i, 1);
        const value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        opts.push({ value, label: d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }) });
    }
    return opts;
})();

const selectedEmployee = computed(() => employees.value.find((emp) => String(emp.id) === String(filters.value.employee_id)));

const periodLabel = computed(() => `${filters.value.from || '-'} to ${filters.value.to || '-'}`);

const communicationChannels = computed(() => Object.entries(commData.value.by_channel || {}));

const revenueTotals = computed(() => revenueByEmployee.value.reduce((totals, row) => {
    totals.sales += Number(row.lead_revenue || 0);
    totals.billed += Number(row.invoice_revenue || 0);
    totals.total += Number(row.revenue || 0);
    return totals;
}, { sales: 0, billed: 0, total: 0 }));

const pipelineTotals = computed(() => allEmployeesPipeline.value.reduce((totals, row) => {
    totals.follow_up += Number(row.follow_up || 0);
    totals.lead += Number(row.lead || 0);
    totals.hot_lead += Number(row.hot_lead || 0);
    totals.appointments += Number(row.appointments || 0);
    totals.quotation += Number(row.quotation || 0);
    totals.won += Number(row.won || 0);
    totals.lost += Number(row.lost || 0);
    totals.value += Number(row.total_value || 0);
    totals.won_products += Number(row.products?.won || 0);
    totals.lost_products += Number(row.products?.lost || 0);
    return totals;
}, {
    follow_up: 0,
    lead: 0,
    hot_lead: 0,
    appointments: 0,
    quotation: 0,
    won: 0,
    lost: 0,
    value: 0,
    won_products: 0,
    lost_products: 0,
}));

const todayFollowUpCount = computed(() => todaysFollowUps.value.reduce((sum, group) => sum + Number(group.count || 0), 0));

const summaryCards = computed(() => [
    { label: 'Sales Revenue', value: `GBP ${formatNumber(revenueTotals.value.sales)}`, help: 'Won product lines' },
    { label: 'Billed Revenue', value: `GBP ${formatNumber(revenueTotals.value.billed)}`, help: 'Invoices in period' },
    { label: 'Won Products', value: pipelineTotals.value.won_products, help: 'Closed product lines' },
    { label: 'Appointments', value: pipelineTotals.value.appointments, help: 'Booked activities' },
    { label: 'Pipeline Value', value: `GBP ${formatNumber(pipelineTotals.value.value)}`, help: 'Open and closed lead value' },
    { label: 'Due Today', value: todayFollowUpCount.value, help: 'Follow-ups to handle' },
]);

const operatingCards = computed(() => [
    { label: 'New opportunities', value: executiveData.value.followups_count || 0, help: 'Leads created in period' },
    { label: 'Conversion', value: `${executiveData.value.conversion_rate || 0}%`, help: 'Won deals against new leads' },
    { label: 'Open pipeline', value: `GBP ${formatNumber(executiveData.value.pipeline_value || 0)}`, help: 'Not won or lost yet' },
    { label: 'Tickets open', value: executiveData.value.tickets?.open || 0, help: 'Support items still active' },
]);

const stageRows = computed(() => {
    const raw = [
        { key: 'follow_up', label: 'Follow-up', count: pipelineTotals.value.follow_up },
        { key: 'lead', label: 'Lead', count: pipelineTotals.value.lead },
        { key: 'hot_lead', label: 'Hot Lead', count: pipelineTotals.value.hot_lead },
        { key: 'appointment', label: 'Appointment', count: pipelineTotals.value.appointments },
        { key: 'quotation', label: 'Quotation', count: pipelineTotals.value.quotation },
        { key: 'won', label: 'Won', count: pipelineTotals.value.won },
        { key: 'lost', label: 'Lost', count: pipelineTotals.value.lost },
    ];
    const max = Math.max(...raw.map((stage) => Number(stage.count || 0)), 1);
    return raw.map((stage) => ({
        ...stage,
        percent: Math.min(100, Math.round((Number(stage.count || 0) / max) * 100)),
    }));
});

const teamRows = computed(() => {
    const pipelineById = new Map(allEmployeesPipeline.value.map((row) => [String(row.employee_id), row]));
    const revenueById = new Map(revenueByEmployee.value.map((row) => [String(row.employee_id), row]));

    return agentData.value.map((agent) => {
        const pipeline = pipelineById.get(String(agent.id)) || {};
        const revenue = revenueById.get(String(agent.id)) || {};
        return {
            employee_id: agent.id,
            employee_name: agent.name,
            appointments: Number(agent.appointments_count || pipeline.appointments || 0),
            leads: Number(agent.leads_count || 0),
            open_pipeline: Number(pipeline.follow_up || 0) + Number(pipeline.lead || 0) + Number(pipeline.hot_lead || 0) + Number(pipeline.quotation || 0),
            won_products: Number(agent.won_products || pipeline.products?.won || 0),
            won_leads: Number(agent.won_leads || 0),
            conversion_rate: Number(agent.conversion_rate || 0),
            revenue: Number(revenue.lead_revenue ?? agent.revenue ?? 0),
        };
    }).sort((a, b) => b.revenue - a.revenue || b.won_products - a.won_products);
});

const selectedTeamRow = computed(() => teamRows.value.find((row) => String(row.employee_id) === String(filters.value.employee_id)));

const employeeActivityCards = computed(() => {
    const row = selectedTeamRow.value || {};
    const self = employeeOverview.value.targets?.self || {};
    return [
        { label: 'New leads', value: row.leads ?? self.new_leads ?? 0, help: 'Assigned in selected range' },
        { label: 'Open pipeline', value: `GBP ${formatNumber(row.open_pipeline ?? self.open_pipeline ?? 0)}`, help: 'Follow-up, lead, hot lead, quotation' },
        { label: 'Appointments', value: row.appointments ?? self.achieved_appointments ?? 0, help: 'Booked or handled' },
        { label: 'Won products', value: row.won_products ?? self.achieved_sales ?? 0, help: 'Closed line items' },
        { label: 'Sales revenue', value: `GBP ${formatNumber(row.revenue ?? self.achieved_revenue ?? 0)}`, help: 'Won product line value' },
    ];
});

const employeeTargetCards = computed(() => {
    const self = employeeOverview.value.targets?.self;
    if (!self) {
        return [
            { label: 'Appointments', value: '0 / 0', progress: 0 },
            { label: 'Sales', value: '0 / 0', progress: 0 },
            { label: 'Revenue', value: 'GBP 0 / GBP 0', progress: 0 },
        ];
    }
    return [
        {
            label: 'Appointments',
            value: `${self.achieved_appointments || 0} / ${self.target_appointments || 0}`,
            progress: clampPct(self.appointment_progress),
        },
        {
            label: 'Sales',
            value: `${self.achieved_sales || 0} / ${self.target_sales || 0}`,
            progress: clampPct(self.sales_progress),
        },
        {
            label: 'Revenue',
            value: `GBP ${formatNumber(self.achieved_revenue || 0)} / GBP ${formatNumber(self.target_revenue || 0)}`,
            progress: clampPct(self.revenue_progress),
        },
    ];
});

const formatNumber = (num) => new Intl.NumberFormat('en-GB', { maximumFractionDigits: 2 }).format(Number(num || 0));

const clampPct = (value) => Math.max(0, Math.min(100, Math.round(Number(value || 0))));

const productNames = (lead) => (lead.items || [])
    .map((item) => item.product?.name)
    .filter(Boolean)
    .join(', ');

const setQuickRange = (key) => {
    activeQuickRange.value = key;
    const now = new Date();
    let from = new Date(now);
    let to = new Date(now);

    if (key === 'this_week') {
        const day = now.getDay() || 7;
        from = new Date(now.getFullYear(), now.getMonth(), now.getDate() - day + 1);
    } else if (key === 'this_month') {
        from = new Date(now.getFullYear(), now.getMonth(), 1);
    } else if (key === 'last_month') {
        from = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        to = new Date(now.getFullYear(), now.getMonth(), 0);
    } else if (key === 'year') {
        from = new Date(now.getFullYear(), 0, 1);
    }

    filters.value.from = dateToInput(from);
    filters.value.to = dateToInput(to);
    selectedMonth.value = filters.value.from.slice(0, 7);
    loadReports();
};

const loadEmployees = async () => {
    try {
        const response = await axios.get('/api/users', { params: { for_sales_report: 1 } });
        employees.value = response.data.data || response.data || [];
    } catch (error) {
        console.error('Failed to load employees:', error);
        employees.value = [];
    }
};

const reportParams = () => {
    const params = {
        from: filters.value.from,
        to: filters.value.to,
        period: filters.value.period,
    };
    if (filters.value.employee_id) {
        params.agent_id = filters.value.employee_id;
    }
    return params;
};

const loadReports = async () => {
    loading.value = true;
    try {
        const params = reportParams();
        const results = await Promise.allSettled([
            axios.get('/api/reporting/executive', { params }),
            axios.get('/api/reporting/funnel', { params }),
            axios.get('/api/reporting/communications', { params }),
            axios.get('/api/reporting/agents', { params }),
            axios.get('/api/reporting/all-employees-pipeline', { params }),
            axios.get('/api/reporting/todays-followups', { params }),
            axios.get('/api/reporting/revenue-by-employee', { params }),
            axios.get('/api/reporting/team-location-status', { params }),
            axios.get('/api/reporting/sales-performance', { params }),
        ]);

        const dataAt = (index, fallback) => {
            if (results[index]?.status !== 'fulfilled') {
                console.error('Report request failed:', results[index]?.reason);
                return fallback;
            }
            return results[index].value.data || fallback;
        };

        executiveData.value = dataAt(0, {});
        funnelData.value = dataAt(1, { funnel: {} });
        commData.value = dataAt(2, {});
        agentData.value = dataAt(3, []);
        allEmployeesPipeline.value = dataAt(4, []);
        todaysFollowUps.value = dataAt(5, []);
        revenueByEmployee.value = dataAt(6, []);
        teamLocationStatus.value = dataAt(7, []);
        salesTrend.value = dataAt(8, []);

        if (filters.value.employee_id) {
            await loadEmployeeReports();
        } else {
            employeeOverview.value = {};
            productReport.value = { products: [], total_revenue: 0, period: {} };
        }
    } catch (error) {
        console.error('Failed to load reports:', error);
    } finally {
        loading.value = false;
    }
};

const loadEmployeeReports = async () => {
    if (!filters.value.employee_id) return;
    employeeLoading.value = true;
    try {
        const [overviewRes, productsRes] = await Promise.all([
            axios.get('/api/reporting/employee-performance-overview', {
                params: { agent_id: filters.value.employee_id, month: selectedMonth.value },
            }),
            axios.get('/api/reporting/products-sold-by-employee', {
                params: {
                    agent_id: filters.value.employee_id,
                    from: filters.value.from,
                    to: filters.value.to,
                },
            }),
        ]);
        employeeOverview.value = overviewRes.data || {};
        productReport.value = productsRes.data || { products: [], total_revenue: 0, period: {} };
    } catch (error) {
        console.error('Failed to load employee report:', error);
        employeeOverview.value = {};
        productReport.value = { products: [], total_revenue: 0, period: {} };
    } finally {
        employeeLoading.value = false;
    }
};

const selectEmployee = (employeeId, section = 'employee') => {
    filters.value.employee_id = String(employeeId);
    activeSection.value = section;
    loadReports();
};

const exportTeamCsv = () => {
    const header = ['Employee', 'Appointments', 'New Leads', 'Open Pipeline', 'Won Products', 'Won Leads', 'Conversion', 'Sales Revenue'];
    const rows = teamRows.value.map((row) => [
        row.employee_name,
        row.appointments,
        row.leads,
        row.open_pipeline,
        row.won_products,
        row.won_leads,
        `${row.conversion_rate}%`,
        row.revenue,
    ]);
    const csv = [header, ...rows]
        .map((line) => line.map((cell) => `"${String(cell ?? '').replaceAll('"', '""')}"`).join(','))
        .join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `business-report-${filters.value.from}-${filters.value.to}.csv`;
    link.click();
    URL.revokeObjectURL(url);
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

watch(() => filters.value.employee_id, (value) => {
    if (!value) {
        employeeOverview.value = {};
        productReport.value = { products: [], total_revenue: 0, period: {} };
    }
});

onMounted(async () => {
    await loadEmployees();
    await loadReports();
});
</script>

<style scoped>
.report-input {
    width: 100%;
    min-width: 0;
    border: 1px solid #cbd5e1;
    border-radius: 0.5rem;
    background: #ffffff;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
}

.report-btn-primary {
    border-radius: 0.5rem;
    background: #0f172a;
    color: #ffffff;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.25rem;
}

.report-btn-outline {
    border: 1px solid #cbd5e1;
    border-radius: 0.5rem;
    background: #ffffff;
    color: #334155;
    padding: 0.5rem 1rem;
    font-size: 0.875rem;
    font-weight: 500;
    line-height: 1.25rem;
}

.report-th {
    padding: 0.75rem 1rem;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    line-height: 1rem;
    color: #475569;
    text-transform: uppercase;
}

.report-td {
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    line-height: 1.25rem;
    color: #475569;
}

.report-btn-primary:hover {
    background: #1e293b;
}

.report-btn-outline:hover {
    background: #f8fafc;
}

.report-btn-primary:disabled,
.report-btn-outline:disabled {
    cursor: not-allowed;
    opacity: 0.5;
}
</style>
