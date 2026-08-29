<template>
    <TabGroup as="div" class="w-full min-w-0" :selected-index="activeSectionIndex" @change="onSectionChange">
        <ListingPageShell
            title="Business Reports"
            :subtitle="selectedEmployee ? `Focused on ${selectedEmployee.name}` : 'All employee performance and sales movement'"
            :badge="periodLabel"
        >
            <template #actions>
                <BaseButton variant="primary" :loading="loading" block-mobile @click="loadReports">
                    Apply
                </BaseButton>
                <BaseButton
                    variant="outline"
                    :disabled="teamRows.length === 0"
                    block-mobile
                    @click="exportTeamCsv"
                >
                    <template #icon><ArrowDownTrayIcon class="icon" aria-hidden="true" /></template>
                    Export CSV
                </BaseButton>
            </template>

            <template #filters>
                <div class="space-y-3">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="min-w-0">
                            <label class="form-label" for="reportsview-from">From</label>
                            <input id="reportsview-from" v-model="filters.from" type="date" class="form-input" />
                        </div>
                        <div class="min-w-0">
                            <label class="form-label" for="reportsview-to">To</label>
                            <input id="reportsview-to" v-model="filters.to" type="date" class="form-input" />
                        </div>
                        <div class="min-w-0">
                            <label class="form-label" for="reportsview-employee">Employee</label>
                            <select id="reportsview-employee" v-model="filters.employee_id" class="form-select">
                                <option value="">All Employees</option>
                                <option v-for="emp in employees" :key="emp.id" :value="String(emp.id)">
                                    {{ emp.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2" role="group" aria-label="Quick date ranges">
                        <button
                            v-for="range in quickRanges"
                            :key="range.key"
                            type="button"
                            :class="['tab', activeQuickRange === range.key ? 'tab-active' : '']"
                            :aria-pressed="activeQuickRange === range.key ? 'true' : 'false'"
                            @click="setQuickRange(range.key)"
                        >
                            {{ range.label }}
                        </button>
                    </div>
                </div>
            </template>

            <template #toolbar>
                <TabList class="tab-list" aria-label="Report sections">
                    <Tab v-for="section in sections" :key="section.key" v-slot="{ selected }" as="template">
                        <button type="button" :class="['tab', selected ? 'tab-active' : '']">
                            {{ section.label }}
                        </button>
                    </Tab>
                </TabList>
            </template>

            <TabPanels class="px-4 py-4 sm:px-6 sm:py-6">
                <!-- Overview -->
                <TabPanel class="space-y-4 focus-visible:outline-none">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <StatCard
                            v-for="card in summaryCards"
                            :key="card.label"
                            :label="card.label"
                            :value="card.value"
                            :caption="card.help"
                        />
                    </div>

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                        <BaseCard
                            class="lg:col-span-2"
                            title="Where the business stands"
                            subtitle="Sales are counted when product lines are won in the selected period."
                        >
                            <template #actions>
                                <span class="text-xs font-medium text-slate-500">{{ periodLabel }}</span>
                            </template>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <StatCard
                                    v-for="item in operatingCards"
                                    :key="item.label"
                                    :label="item.label"
                                    :value="item.value"
                                    :caption="item.help"
                                />
                            </div>
                        </BaseCard>

                        <BaseCard title="Communication">
                            <dl class="space-y-3 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-600">Sent</dt>
                                    <dd class="font-semibold text-slate-900 tabular-nums">{{ commData.sent || 0 }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-slate-600">Received</dt>
                                    <dd class="font-semibold text-slate-900 tabular-nums">{{ commData.received || 0 }}</dd>
                                </div>
                                <div
                                    v-for="[channel, count] in communicationChannels"
                                    :key="channel"
                                    class="flex justify-between gap-3"
                                >
                                    <dt class="capitalize text-slate-600">{{ channel }}</dt>
                                    <dd class="font-semibold text-slate-900 tabular-nums">{{ count }}</dd>
                                </div>
                            </dl>
                        </BaseCard>
                    </div>

                    <BaseCard
                        title="Sales workflow"
                        subtitle="Follow the customer journey from contact to final outcome."
                    >
                        <div class="grid grid-cols-2 gap-2 md:grid-cols-4 xl:grid-cols-7">
                            <div
                                v-for="stage in stageRows"
                                :key="stage.key"
                                class="rounded-control border border-slate-200 p-3"
                            >
                                <div class="text-eyebrow uppercase text-slate-500">{{ stage.label }}</div>
                                <div class="mt-2 text-xl font-bold tabular-nums text-slate-900">{{ stage.count }}</div>
                                <div class="mt-2 h-2 overflow-hidden rounded bg-slate-100" aria-hidden="true">
                                    <div class="h-2 rounded bg-primary-600" :style="{ width: `${stage.percent}%` }"></div>
                                </div>
                            </div>
                        </div>
                    </BaseCard>
                </TabPanel>

                <!-- Team -->
                <TabPanel class="focus-visible:outline-none">
                    <BaseCard
                        :padded="false"
                        title="Team Performance"
                        subtitle="Compare users using the same date range and sales basis."
                    >
                        <template #actions>
                            <BaseButton
                                variant="outline"
                                size="sm"
                                :disabled="teamRows.length === 0"
                                @click="filters.employee_id = ''; loadReports()"
                            >
                                Show All
                            </BaseButton>
                        </template>

                        <div v-if="teamRows.length" class="table-wrap">
                            <table class="table min-w-[1040px]">
                                <caption class="sr-only">
                                    Team performance for {{ periodLabel }}: appointments, new leads, open pipeline,
                                    won products, won leads, conversion rate and sales revenue per employee.
                                </caption>
                                <thead class="table-thead">
                                    <tr>
                                        <th scope="col" class="table-th">Employee</th>
                                        <th scope="col" class="table-th-num">Appointments</th>
                                        <th scope="col" class="table-th-num">New Leads</th>
                                        <th scope="col" class="table-th-num">Open Pipeline</th>
                                        <th scope="col" class="table-th-num">Won Products</th>
                                        <th scope="col" class="table-th-num">Won Leads</th>
                                        <th scope="col" class="table-th-num">Conversion</th>
                                        <th scope="col" class="table-th-num">Sales Revenue</th>
                                        <th scope="col" class="table-th-num">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in teamRows" :key="row.employee_id" class="table-row">
                                        <td class="table-td-strong">{{ row.employee_name }}</td>
                                        <td class="table-td-num">{{ row.appointments }}</td>
                                        <td class="table-td-num">{{ row.leads }}</td>
                                        <td class="table-td-num">{{ row.open_pipeline }}</td>
                                        <td class="table-td-num font-semibold text-success-700">{{ row.won_products }}</td>
                                        <td class="table-td-num">{{ row.won_leads }}</td>
                                        <td class="table-td-num">{{ row.conversion_rate }}%</td>
                                        <td class="table-td-num font-semibold text-slate-900">
                                            GBP {{ formatNumber(row.revenue) }}
                                        </td>
                                        <td class="table-td-actions">
                                            <BaseButton
                                                variant="ghost"
                                                size="sm"
                                                @click="selectEmployee(row.employee_id, 'employee')"
                                            >
                                                View
                                            </BaseButton>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <EmptyState v-else heading="No team data for this period.">
                            <template #icon><UsersIcon class="icon" aria-hidden="true" /></template>
                        </EmptyState>
                    </BaseCard>
                </TabPanel>

                <!-- Employee -->
                <TabPanel class="space-y-4 focus-visible:outline-none">
                    <BaseCard v-if="!filters.employee_id" :padded="false">
                        <EmptyState
                            heading="Choose an employee"
                            description="Select one user above or use View from the team table."
                        >
                            <template #icon><UserIcon class="icon" aria-hidden="true" /></template>
                        </EmptyState>
                    </BaseCard>

                    <template v-else>
                        <BaseCard
                            :title="`${selectedEmployee?.name || 'Employee'} Report`"
                            subtitle="Month target view plus product sales detail."
                        >
                            <div class="w-full sm:w-56">
                                <label class="form-label" for="reportsview-target-month">Target month</label>
                                <select
                                    id="reportsview-target-month"
                                    v-model="selectedMonth"
                                    class="form-select"
                                    @change="loadEmployeeReports"
                                >
                                    <option v-for="month in monthOptions" :key="month.value" :value="month.value">
                                        {{ month.label }}
                                    </option>
                                </select>
                            </div>
                        </BaseCard>

                        <div v-if="employeeLoading" class="card card-body space-y-3" aria-busy="true">
                            <p class="sr-only" role="status">Loading employee report...</p>
                            <span class="skeleton-text block w-1/3"></span>
                            <span class="skeleton-text block w-full"></span>
                            <span class="skeleton-text block w-5/6"></span>
                        </div>

                        <template v-else>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                <StatCard
                                    v-for="card in employeeActivityCards"
                                    :key="card.label"
                                    :label="card.label"
                                    :value="card.value"
                                    :caption="card.help"
                                />
                            </div>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <div v-for="card in employeeTargetCards" :key="card.label" class="stat-card">
                                    <p class="stat-label">{{ card.label }}</p>
                                    <p class="stat-value">{{ card.value }}</p>
                                    <div class="mt-3 h-2 overflow-hidden rounded bg-slate-100" aria-hidden="true">
                                        <div class="h-2 rounded bg-success-600" :style="{ width: `${card.progress}%` }"></div>
                                    </div>
                                    <p class="stat-caption">{{ card.progress }}% achieved</p>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                                <BaseCard title="Last Week" :subtitle="employeeOverview.last_week?.label || '-'">
                                    <div class="grid grid-cols-2 gap-3">
                                        <StatCard
                                            label="Won products"
                                            :value="employeeOverview.last_week?.won_line_items || 0"
                                        />
                                        <StatCard
                                            label="Revenue"
                                            :value="`GBP ${formatNumber(employeeOverview.last_week?.total_revenue || 0)}`"
                                        />
                                    </div>
                                </BaseCard>

                                <BaseCard
                                    title="Selected Month"
                                    :subtitle="`${employeeOverview.selected_month?.period?.from || '-'} to ${employeeOverview.selected_month?.period?.to || '-'}`"
                                >
                                    <div class="grid grid-cols-2 gap-3">
                                        <StatCard
                                            label="Won products"
                                            :value="employeeOverview.selected_month?.won_line_items || 0"
                                        />
                                        <StatCard
                                            label="Revenue"
                                            :value="`GBP ${formatNumber(employeeOverview.selected_month?.total_revenue || 0)}`"
                                        />
                                    </div>
                                </BaseCard>
                            </div>

                            <BaseCard
                                :padded="false"
                                title="Products Won In Current Filter"
                                :subtitle="`${productReport.period?.from || filters.from} to ${productReport.period?.to || filters.to}`"
                            >
                                <div v-if="(productReport.products || []).length" class="table-wrap">
                                    <table class="table min-w-[760px]">
                                        <caption class="sr-only">
                                            Products won by {{ selectedEmployee?.name || 'the selected employee' }}:
                                            product, customer, quantity, unit price and total.
                                        </caption>
                                        <thead class="table-thead">
                                            <tr>
                                                <th scope="col" class="table-th">Product</th>
                                                <th scope="col" class="table-th">Customer</th>
                                                <th scope="col" class="table-th-num">Qty</th>
                                                <th scope="col" class="table-th-num">Unit Price</th>
                                                <th scope="col" class="table-th-num">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(item, index) in productReport.products || []"
                                                :key="index"
                                                class="table-row"
                                            >
                                                <td class="table-td-strong">{{ item.product_name }}</td>
                                                <td class="table-td">{{ item.customer_name }}</td>
                                                <td class="table-td-num">{{ item.quantity }}</td>
                                                <td class="table-td-num">GBP {{ formatNumber(item.unit_price) }}</td>
                                                <td class="table-td-num font-semibold">
                                                    GBP {{ formatNumber(item.total_price) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <EmptyState
                                    v-else
                                    heading="No won products for this employee in the selected range."
                                >
                                    <template #icon><CubeIcon class="icon" aria-hidden="true" /></template>
                                </EmptyState>
                            </BaseCard>
                        </template>
                    </template>
                </TabPanel>

                <!-- Pipeline -->
                <TabPanel class="focus-visible:outline-none">
                    <BaseCard
                        :padded="false"
                        title="Pipeline Report"
                        subtitle="Counts are lead opportunities created in the selected period; appointments are booked activities."
                    >
                        <div v-if="allEmployeesPipeline.length" class="table-wrap">
                            <table class="table min-w-[1120px]">
                                <caption class="sr-only">
                                    Pipeline stage counts and value per employee for {{ periodLabel }}.
                                </caption>
                                <thead class="table-thead">
                                    <tr>
                                        <th scope="col" class="table-th">Employee</th>
                                        <th scope="col" class="table-th-num">Follow-up</th>
                                        <th scope="col" class="table-th-num">Lead</th>
                                        <th scope="col" class="table-th-num">Hot Lead</th>
                                        <th scope="col" class="table-th-num">Appointment</th>
                                        <th scope="col" class="table-th-num">Quotation</th>
                                        <th scope="col" class="table-th-num">Won</th>
                                        <th scope="col" class="table-th-num">Lost</th>
                                        <th scope="col" class="table-th-num">Products W/L/P</th>
                                        <th scope="col" class="table-th-num">Pipeline Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in allEmployeesPipeline" :key="row.employee_id" class="table-row">
                                        <td class="table-td-strong">{{ row.employee_name }}</td>
                                        <td class="table-td-num">{{ row.follow_up || 0 }}</td>
                                        <td class="table-td-num">{{ row.lead || 0 }}</td>
                                        <td class="table-td-num">{{ row.hot_lead || 0 }}</td>
                                        <td class="table-td-num">{{ row.appointments || 0 }}</td>
                                        <td class="table-td-num">{{ row.quotation || 0 }}</td>
                                        <td class="table-td-num font-semibold text-success-700">{{ row.won || 0 }}</td>
                                        <td class="table-td-num text-danger-700">{{ row.lost || 0 }}</td>
                                        <td class="table-td-num">
                                            {{ row.products?.won || 0 }} / {{ row.products?.lost || 0 }} /
                                            {{ row.products?.pending || 0 }}
                                        </td>
                                        <td class="table-td-num font-semibold">
                                            GBP {{ formatNumber(row.total_value || 0) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <EmptyState v-else heading="No pipeline data for this period.">
                            <template #icon><ChartBarIcon class="icon" aria-hidden="true" /></template>
                        </EmptyState>
                    </BaseCard>
                </TabPanel>

                <!-- Products & Revenue -->
                <TabPanel class="focus-visible:outline-none">
                    <BaseCard
                        :padded="false"
                        title="Product And Revenue Report"
                        subtitle="Sales revenue is won product lines. Billed revenue is invoice total."
                    >
                        <div v-if="revenueByEmployee.length" class="table-wrap">
                            <table class="table min-w-[860px]">
                                <caption class="sr-only">
                                    Sales, billed and total revenue plus won and lost product counts per employee
                                    for {{ periodLabel }}.
                                </caption>
                                <thead class="table-thead">
                                    <tr>
                                        <th scope="col" class="table-th">Employee</th>
                                        <th scope="col" class="table-th-num">Sales Revenue</th>
                                        <th scope="col" class="table-th-num">Billed Revenue</th>
                                        <th scope="col" class="table-th-num">Total Revenue</th>
                                        <th scope="col" class="table-th-num">Won Products</th>
                                        <th scope="col" class="table-th-num">Lost Products</th>
                                        <th scope="col" class="table-th-num">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in revenueByEmployee" :key="row.employee_id" class="table-row">
                                        <td class="table-td-strong">{{ row.employee_name }}</td>
                                        <td class="table-td-num">GBP {{ formatNumber(row.lead_revenue || 0) }}</td>
                                        <td class="table-td-num">GBP {{ formatNumber(row.invoice_revenue || 0) }}</td>
                                        <td class="table-td-num font-semibold">GBP {{ formatNumber(row.revenue || 0) }}</td>
                                        <td class="table-td-num font-semibold text-success-700">
                                            {{ row.products?.won || 0 }}
                                        </td>
                                        <td class="table-td-num text-danger-700">{{ row.products?.lost || 0 }}</td>
                                        <td class="table-td-actions">
                                            <BaseButton
                                                variant="ghost"
                                                size="sm"
                                                @click="selectEmployee(row.employee_id, 'employee')"
                                            >
                                                Products
                                            </BaseButton>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <EmptyState v-else heading="No revenue data for this period.">
                            <template #icon><BanknotesIcon class="icon" aria-hidden="true" /></template>
                        </EmptyState>
                    </BaseCard>
                </TabPanel>

                <!-- Today -->
                <TabPanel class="space-y-4 focus-visible:outline-none">
                    <BaseCard
                        title="Today And Field Activity"
                        subtitle="Follow-ups are always for today. Activity locations use the selected date range."
                        :padded="false"
                    />

                    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <BaseCard title="Today's Follow-ups" :padded="todaysFollowUps.length > 0">
                            <EmptyState v-if="todaysFollowUps.length === 0" heading="No follow-ups scheduled for today.">
                                <template #icon><CalendarDaysIcon class="icon" aria-hidden="true" /></template>
                            </EmptyState>
                            <div v-else class="space-y-4">
                                <div
                                    v-for="group in todaysFollowUps"
                                    :key="group.agent_id"
                                    class="rounded-control border border-slate-200 p-3"
                                >
                                    <h3 class="subsection-title">
                                        {{ group.agent_name }} ({{ group.count }})
                                    </h3>
                                    <ul class="mt-3 space-y-2">
                                        <li
                                            v-for="followUp in group.follow_ups"
                                            :key="followUp.id"
                                            class="flex flex-col gap-2 border-l-2 border-primary-200 py-2 pl-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                                        >
                                            <div class="min-w-0">
                                                <div class="truncate font-medium text-slate-900">
                                                    {{ followUp.customer?.name || 'Unknown customer' }}
                                                </div>
                                                <div class="truncate text-slate-500">
                                                    {{ productNames(followUp) || 'No product selected' }}
                                                </div>
                                            </div>
                                            <div class="flex shrink-0 gap-2">
                                                <BaseButton variant="soft" size="sm" @click="openActivityModal(followUp)">
                                                    Log
                                                </BaseButton>
                                                <BaseButton
                                                    v-if="followUp.customer_id"
                                                    variant="outline"
                                                    size="sm"
                                                    :to="`/customers/${followUp.customer_id}`"
                                                >
                                                    View
                                                </BaseButton>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </BaseCard>

                        <BaseCard title="Recent Visits And Meetings" :padded="teamLocationStatus.length > 0">
                            <EmptyState v-if="teamLocationStatus.length === 0" heading="No recent visits or meetings.">
                                <template #icon><MapPinIcon class="icon" aria-hidden="true" /></template>
                            </EmptyState>
                            <div v-else class="space-y-4">
                                <div
                                    v-for="agent in teamLocationStatus"
                                    :key="agent.agent_id"
                                    class="rounded-control border border-slate-200 p-3"
                                >
                                    <h3 class="subsection-title">
                                        {{ agent.agent_name }} ({{ agent.activity_count }})
                                    </h3>
                                    <ul class="mt-2 space-y-1">
                                        <li
                                            v-for="customer in agent.customers"
                                            :key="customer.id"
                                            class="text-sm text-slate-600"
                                        >
                                            {{ customer.name }} - {{ customer.phone || 'No phone' }}
                                            <span v-if="customer.address" class="text-xs text-slate-500">
                                                ({{ customer.address }})
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </BaseCard>
                    </div>
                </TabPanel>
            </TabPanels>
        </ListingPageShell>

        <LogActivityModal
            v-if="showActivityModal && activityLead"
            :lead="activityLead"
            @close="closeActivityModal"
            @saved="handleActivitySaved"
        />
    </TabGroup>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue';
import {
    ArrowDownTrayIcon,
    BanknotesIcon,
    CalendarDaysIcon,
    ChartBarIcon,
    CubeIcon,
    MapPinIcon,
    UserIcon,
    UsersIcon,
} from '@heroicons/vue/24/outline';
import LogActivityModal from '@/components/LogActivityModal.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseButton, BaseCard, EmptyState, StatCard } from '@/components/base';

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

/** Bridges the string-keyed `activeSection` to the headless tab index. */
const activeSectionIndex = computed(() => {
    const index = sections.findIndex((section) => section.key === activeSection.value);
    return index === -1 ? 0 : index;
});

const onSectionChange = (index) => {
    activeSection.value = sections[index]?.key || 'overview';
};

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
