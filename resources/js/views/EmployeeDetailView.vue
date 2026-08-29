<template>
    <div class="w-full min-w-0 max-w-7xl mx-auto p-3 sm:p-4 lg:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4 lg:mb-6 min-w-0">
            <div class="flex items-center gap-3 lg:gap-4 min-w-0">
                <BaseButton
                    variant="ghost"
                    size="icon"
                    label="Go back"
                    class="shrink-0"
                    @click="$router.back()"
                >
                    <template #icon>
                        <ArrowLeftIcon class="icon" aria-hidden="true" />
                    </template>
                </BaseButton>
                <h1 class="text-xl lg:text-2xl font-bold text-slate-900 break-words">Employee Details</h1>
            </div>
            <BaseButton
                v-if="employee"
                variant="primary"
                block-mobile
                class="shrink-0"
                @click="goToEdit"
            >
                <template #icon>
                    <PencilSquareIcon class="icon" aria-hidden="true" />
                </template>
                Edit Employee
            </BaseButton>
        </div>

        <div v-if="loading" class="card card-body space-y-3" aria-busy="true">
            <span class="sr-only">Loading employee details…</span>
            <div class="skeleton-text w-1/3"></div>
            <div class="skeleton-text w-2/3"></div>
            <div class="skeleton-text w-1/2"></div>
            <div class="skeleton-text w-3/4"></div>
        </div>

        <div v-else-if="employee" class="space-y-6">
            <!-- Employee Info Card -->
            <BaseCard class="min-w-0">
                <template #header>
                    <h2 class="card-title break-words">{{ employee.name }}</h2>
                    <p class="card-subtitle">{{ employee.role?.name || 'No Role' }}</p>
                </template>
                <template #actions>
                    <BaseBadge :tone="getRoleTone(employee.role?.name)">
                        {{ employee.role?.name || 'N/A' }}
                    </BaseBadge>
                </template>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <span class="text-eyebrow text-slate-500 uppercase">Email</span>
                        <p class="text-slate-900 font-medium">{{ employee.email || 'N/A' }}</p>
                    </div>
                    <div>
                        <span class="text-eyebrow text-slate-500 uppercase">Phone</span>
                        <p class="text-slate-900 font-medium">{{ employee.phone || 'N/A' }}</p>
                    </div>
                    <div>
                        <span class="text-eyebrow text-slate-500 uppercase">Employee ID</span>
                        <p class="text-slate-900 font-medium">EMP{{ String(employee.id).padStart(3, '0') }}</p>
                    </div>
                    <div>
                        <span class="text-eyebrow text-slate-500 uppercase">Joined Date</span>
                        <p class="text-slate-900 font-medium">
                            {{ employee.created_at ? formatDate(employee.created_at) : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <span class="text-eyebrow text-slate-500 uppercase">Date of Birth</span>
                        <p class="text-slate-900 font-medium">
                            {{ employee.date_of_birth ? formatDate(employee.date_of_birth) : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <span class="text-eyebrow text-slate-500 uppercase">Status</span>
                        <p class="mt-0.5">
                            <BaseBadge
                                v-if="employee.is_active === false || employee.is_active === 0"
                                tone="danger"
                            >
                                Inactive
                            </BaseBadge>
                            <BaseBadge v-else tone="success">Active</BaseBadge>
                        </p>
                    </div>
                </div>
            </BaseCard>

            <!-- Bank details -->
            <BaseCard title="Bank details">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-eyebrow text-slate-500 uppercase">Account holder name</span>
                        <p class="text-slate-900 font-medium">
                            {{ employee.bank_account_name || 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <span class="text-eyebrow text-slate-500 uppercase">Bank name</span>
                        <p class="text-slate-900 font-medium">
                            {{ employee.bank_name || 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <span class="text-eyebrow text-slate-500 uppercase">Sort code</span>
                        <p class="text-slate-900 font-medium">
                            {{ employee.bank_sort_code || 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <span class="text-eyebrow text-slate-500 uppercase">Account number</span>
                        <p class="text-slate-900 font-medium">
                            {{ employee.bank_account_number || 'N/A' }}
                        </p>
                    </div>
                </div>
            </BaseCard>

            <!-- Documents -->
            <BaseCard title="Documents">
                <div
                    v-if="documentError"
                    ref="documentErrorRef"
                    class="callout callout-danger mb-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-600/40"
                    role="alert"
                    tabindex="-1"
                >
                    {{ documentError }}
                </div>

                <form
                    id="employee-document-form"
                    novalidate
                    class="grid grid-cols-1 md:grid-cols-[2fr,2fr,auto] gap-3 mb-4 items-end"
                    @submit.prevent="uploadDocument"
                >
                    <div>
                        <label class="form-label" for="employeedetailview-document-name">Document name</label>
                        <input
                            id="employeedetailview-document-name"
                            v-model="newDocName"
                            type="text"
                            placeholder="e.g. Passport, Contract"
                            class="form-input"
                        />
                    </div>
                    <div>
                        <label class="form-label" for="employeedetailview-document-file">Document file</label>
                        <input
                            id="employeedetailview-document-file"
                            ref="fileInput"
                            type="file"
                            class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 rounded-control"
                        />
                    </div>
                    <BaseButton
                        type="submit"
                        variant="soft"
                        block-mobile
                        :loading="uploading"
                        form="employee-document-form"
                    >
                        <template #icon>
                            <ArrowUpTrayIcon class="icon" aria-hidden="true" />
                        </template>
                        {{ uploading ? 'Uploading...' : 'Add' }}
                    </BaseButton>
                </form>

                <EmptyState
                    v-if="documents.length === 0"
                    heading="No documents uploaded yet"
                    description="Add a name and a file above to attach the first document to this employee."
                >
                    <template #icon>
                        <DocumentTextIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>
                <div v-else class="space-y-2">
                    <div
                        v-for="doc in documents"
                        :key="doc.id"
                        class="flex items-center justify-between gap-3 text-sm border border-slate-200 rounded-card px-3 py-2"
                    >
                        <div class="min-w-0">
                            <div class="font-medium text-slate-900 break-words">{{ doc.name }}</div>
                            <div class="text-xs text-slate-500">
                                Added {{ formatDate(doc.created_at) }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a
                                :href="doc.file_path"
                                target="_blank"
                                rel="noopener"
                                class="link text-xs"
                            >
                                View
                            </a>
                            <BaseButton
                                variant="ghost"
                                size="sm"
                                :label="`Delete ${doc.name}`"
                                @click="requestDeleteDocument(doc)"
                            >
                                <template #icon>
                                    <TrashIcon class="icon-sm" aria-hidden="true" />
                                </template>
                                Delete
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </BaseCard>

            <!-- Attendance Statistics -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6">
                <StatCard label="This Month" :value="stats.this_month || 0" caption="Days Present" tone="primary">
                    <template #icon>
                        <CalendarDaysIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard label="This Year" :value="stats.this_year || 0" caption="Days Present" tone="success">
                    <template #icon>
                        <ChartBarIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
                <StatCard label="Total Hours" :value="formatHours(stats.total_hours || 0)" caption="This Month" tone="neutral">
                    <template #icon>
                        <ClockIcon class="icon" aria-hidden="true" />
                    </template>
                </StatCard>
            </div>

            <!-- Attendance Records -->
            <BaseCard>
                <template #header>
                    <h2 class="card-title">Attendance Records</h2>
                </template>
                <template #actions>
                    <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                        <div>
                            <label class="form-label sr-only" for="employeedetailview-month-filter">Filter by month</label>
                            <select
                                id="employeedetailview-month-filter"
                                v-model="monthFilter"
                                class="form-select w-full sm:w-auto"
                                @change="loadAttendanceReport"
                            >
                                <option value="">All Months</option>
                                <option v-for="month in recentMonths" :key="month.value" :value="month.value">
                                    {{ month.label }}
                                </option>
                            </select>
                        </div>
                        <BaseButton variant="outline" block-mobile @click="exportAttendance">
                            <template #icon>
                                <ArrowDownTrayIcon class="icon" aria-hidden="true" />
                            </template>
                            Export CSV
                        </BaseButton>
                    </div>
                </template>

                <div
                    v-if="employeeMonthlySummary"
                    class="mb-4 grid grid-cols-1 gap-3 rounded-card border border-slate-200 bg-slate-50 p-3 sm:grid-cols-2 lg:grid-cols-5"
                >
                    <div>
                        <MetricLabel label="Total Hours" tooltip="Total completed work hours in the selected month. Open shifts are not counted until check-out." />
                        <div class="mt-1 text-lg font-bold text-slate-900">{{ formatHours(employeeMonthlySummary.total_hours) }}h</div>
                    </div>
                    <div>
                        <MetricLabel label="Present Days" tooltip="Number of days where this employee checked in during the selected month." />
                        <div class="mt-1 text-lg font-bold text-slate-900">{{ employeeMonthlySummary.present_days }}</div>
                    </div>
                    <div>
                        <MetricLabel label="Completed Shifts" tooltip="Attendance records with both check-in and check-out. These records calculate work hours." />
                        <div class="mt-1 text-lg font-bold text-slate-900">{{ employeeMonthlySummary.completed_shifts }}</div>
                    </div>
                    <div>
                        <MetricLabel label="Avg / Day" tooltip="Total completed hours divided by checked-in days." />
                        <div class="mt-1 text-lg font-bold text-slate-900">{{ formatHours(employeeMonthlySummary.average_hours_per_present_day) }}h</div>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-1">
                        <MetricLabel label="Open Shifts" tooltip="Checked-in records that do not have a check-out yet. Hours are not final for these records." />
                        <div class="mt-1 text-lg font-bold text-slate-900">{{ employeeMonthlySummary.open_shifts }}</div>
                    </div>
                </div>

                <EmptyState
                    v-if="attendanceList.length === 0"
                    heading="No attendance records found"
                    description="Nothing was recorded for this employee in the selected period."
                >
                    <template #icon>
                        <CalendarDaysIcon class="icon" aria-hidden="true" />
                    </template>
                </EmptyState>

                <template v-else>
                    <!-- Mobile Card View -->
                    <div class="lg:hidden space-y-3">
                        <div v-for="attendance in attendanceList" :key="attendance.id" class="table-card">
                            <div class="flex justify-between items-start mb-2">
                                <div class="font-medium text-slate-900">{{ formatDate(attendance.date) }}</div>
                                <BaseBadge :tone="attendance.check_out_at ? 'success' : 'warning'">
                                    {{ attendance.check_out_at ? 'Completed' : 'In Progress' }}
                                </BaseBadge>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <span class="text-slate-500">Check In:</span>
                                    <span class="ml-2 text-slate-900">{{ formatTime(attendance.check_in_at) }}</span>
                                </div>
                                <div>
                                    <span class="text-slate-500">Check Out:</span>
                                    <span class="ml-2 text-slate-900">{{ attendance.check_out_at ? formatTime(attendance.check_out_at) : '-' }}</span>
                                </div>
                                <div class="col-span-2">
                                    <span class="text-slate-500">Hours:</span>
                                    <span class="ml-2 font-medium text-slate-900">{{ parseFloat(attendance.work_hours || 0).toFixed(2) }}h</span>
                                </div>
                                <div class="col-span-2 grid grid-cols-1 gap-2 pt-2 sm:grid-cols-2">
                                    <div class="flex items-center gap-2 rounded-control border border-slate-200 bg-white p-2">
                                        <img
                                            v-if="attendance.check_in_photo_url"
                                            :src="attendance.check_in_photo_url"
                                            alt="Check-in proof"
                                            class="h-10 w-10 rounded object-cover"
                                        />
                                        <div class="text-xs">
                                            <div class="font-medium text-slate-700">Check-in proof</div>
                                            <div v-if="attendance.check_in_location_name" class="break-words text-slate-600">
                                                {{ attendance.check_in_location_name }}
                                            </div>
                                            <a
                                                v-if="attendance.check_in_map_url"
                                                :href="attendance.check_in_map_url"
                                                target="_blank"
                                                rel="noopener"
                                                class="link"
                                            >
                                                Open map
                                            </a>
                                            <div v-else class="text-slate-500">Not captured</div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 rounded-control border border-slate-200 bg-white p-2">
                                        <img
                                            v-if="attendance.check_out_photo_url"
                                            :src="attendance.check_out_photo_url"
                                            alt="Check-out proof"
                                            class="h-10 w-10 rounded object-cover"
                                        />
                                        <div class="text-xs">
                                            <div class="font-medium text-slate-700">Check-out proof</div>
                                            <div v-if="attendance.check_out_location_name" class="break-words text-slate-600">
                                                {{ attendance.check_out_location_name }}
                                            </div>
                                            <a
                                                v-if="attendance.check_out_map_url"
                                                :href="attendance.check_out_map_url"
                                                target="_blank"
                                                rel="noopener"
                                                class="link"
                                            >
                                                Open map
                                            </a>
                                            <div v-else class="text-slate-500">Not captured</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table View -->
                    <div class="hidden lg:block table-wrap">
                        <table class="table">
                            <caption class="sr-only">Attendance records for {{ employee.name }}</caption>
                            <thead class="table-thead">
                                <tr>
                                    <th scope="col" class="table-th">Date</th>
                                    <th scope="col" class="table-th">Check In</th>
                                    <th scope="col" class="table-th">Check Out</th>
                                    <th scope="col" class="table-th">Work Hours</th>
                                    <th scope="col" class="table-th">Proof</th>
                                    <th scope="col" class="table-th">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="attendance in attendanceList" :key="attendance.id" class="table-row">
                                    <td class="table-td-strong whitespace-nowrap">
                                        {{ formatDate(attendance.date) }}
                                    </td>
                                    <td class="table-td whitespace-nowrap">
                                        {{ formatTime(attendance.check_in_at) }}
                                    </td>
                                    <td class="table-td whitespace-nowrap">
                                        {{ attendance.check_out_at ? formatTime(attendance.check_out_at) : '-' }}
                                    </td>
                                    <td class="table-td-strong whitespace-nowrap">
                                        {{ parseFloat(attendance.work_hours || 0).toFixed(2) }}h
                                    </td>
                                    <td class="table-td">
                                        <div class="flex flex-wrap gap-3">
                                            <div class="flex items-center gap-2">
                                                <img
                                                    v-if="attendance.check_in_photo_url"
                                                    :src="attendance.check_in_photo_url"
                                                    alt="Check-in proof"
                                                    class="h-9 w-9 rounded object-cover"
                                                />
                                                <a
                                                    v-if="attendance.check_in_map_url"
                                                    :href="attendance.check_in_map_url"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="link text-xs"
                                                >
                                                    In map
                                                </a>
                                                <span v-if="!attendance.check_in_photo_url && !attendance.check_in_map_url" class="text-xs text-slate-500">No in proof</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <img
                                                    v-if="attendance.check_out_photo_url"
                                                    :src="attendance.check_out_photo_url"
                                                    alt="Check-out proof"
                                                    class="h-9 w-9 rounded object-cover"
                                                />
                                                <a
                                                    v-if="attendance.check_out_map_url"
                                                    :href="attendance.check_out_map_url"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="link text-xs"
                                                >
                                                    Out map
                                                </a>
                                                <span v-if="!attendance.check_out_photo_url && !attendance.check_out_map_url" class="text-xs text-slate-500">No out proof</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="table-td whitespace-nowrap">
                                        <BaseBadge :tone="attendance.check_out_at ? 'success' : 'warning'">
                                            {{ attendance.check_out_at ? 'Completed' : 'In Progress' }}
                                        </BaseBadge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>

                <Pagination
                    v-if="attendancePagination && attendancePagination.last_page > 1"
                    :pagination="attendancePagination"
                    @page-change="loadAttendance"
                />
            </BaseCard>
        </div>

        <ConfirmDialog
            v-model="confirmDeleteDocOpen"
            title="Delete this document?"
            :message="documentToDelete ? `“${documentToDelete.name}” will be permanently removed from this employee.` : 'Delete this document?'"
            confirm-label="Delete document"
            cancel-label="Keep document"
            tone="danger"
            :loading="deletingDocument"
            @confirm="confirmDeleteDocument"
            @cancel="cancelDeleteDocument"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, computed, defineComponent, h, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import {
    ArrowDownTrayIcon,
    ArrowLeftIcon,
    ArrowUpTrayIcon,
    CalendarDaysIcon,
    ChartBarIcon,
    ClockIcon,
    DocumentTextIcon,
    PencilSquareIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import {
    BaseBadge,
    BaseButton,
    BaseCard,
    ConfirmDialog,
    EmptyState,
    StatCard,
} from '@/components/base';
import Pagination from '@/components/Pagination.vue';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const employee = ref(null);
const stats = ref({});
const attendanceList = ref([]);
const attendancePagination = ref(null);
const monthlyReport = ref(null);
const loading = ref(false);
const dateToMonth = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    return `${y}-${m}`;
};
const monthFilter = ref(dateToMonth(new Date()));
const documents = ref([]);
const newDocName = ref('');
const uploading = ref(false);
const fileInput = ref(null);
const documentError = ref(null);
const documentErrorRef = ref(null);
const confirmDeleteDocOpen = ref(false);
const documentToDelete = ref(null);
const deletingDocument = ref(false);

const recentMonths = computed(() => {
    const months = [];
    const now = new Date();
    for (let i = 0; i < 12; i++) {
        const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
        months.push({
            value: date.toISOString().slice(0, 7),
            label: date.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }),
        });
    }
    return months;
});

/** Visual tone only — the role name itself is unchanged. */
const getRoleTone = (roleName) => {
    const tones = {
        'Admin': 'primary',
        'Manager': 'primary',
        'Sales': 'success',
        'CallAgent': 'warning',
        'System Admin': 'danger',
    };
    return tones[roleName] || 'neutral';
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatTime = (datetime) => {
    if (!datetime) return '-';
    return new Date(datetime).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};

const formatHours = (hours) => {
    return parseFloat(hours || 0).toFixed(1);
};

const employeeMonthlySummary = computed(() => monthlyReport.value?.employees?.[0] || null);

const MetricLabel = defineComponent({
    name: 'MetricLabel',
    props: {
        label: { type: String, required: true },
        tooltip: { type: String, required: true },
    },
    setup(props) {
        return () => h('div', {
            class: 'flex min-w-0 items-center gap-1 text-xs font-medium text-slate-500',
            title: props.tooltip,
        }, [
            h('span', { class: 'min-w-0 truncate' }, props.label),
            h('span', { class: 'group relative inline-flex shrink-0' }, [
                h('button', {
                    type: 'button',
                    class: 'grid h-4 w-4 place-items-center rounded-full border border-slate-300 bg-white text-[10px] font-bold leading-none text-slate-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40',
                    'aria-label': `${props.label}: ${props.tooltip}`,
                }, '?'),
                h('span', {
                    class: 'pointer-events-none absolute bottom-full left-0 z-dropdown mb-2 hidden w-56 rounded-md bg-slate-900 px-3 py-2 text-left text-[11px] font-medium normal-case leading-4 tracking-normal text-white shadow-dropdown group-hover:block group-focus-within:block',
                }, props.tooltip),
            ]),
        ]);
    },
});

const loadEmployee = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/users/${route.params.id}`);
        employee.value = data;
    } catch (error) {
        console.error('Failed to load employee:', error);
        toast.error('Failed to load employee details');
    } finally {
        loading.value = false;
    }
};

const loadStats = async () => {
    try {
        const { data } = await axios.get(`/api/hr/employees/${route.params.id}/attendance-stats`);
        stats.value = data;
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
};

const loadAttendance = async (page = 1) => {
    try {
        const params = {
            user_id: route.params.id,
            per_page: 15,
            page,
        };

        if (monthFilter.value) {
            params.month = monthFilter.value;
        }

        const { data } = await axios.get('/api/hr/attendance', { params });
        attendanceList.value = data.data || [];
        attendancePagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page || 15,
            total: data.total || 0,
        };
    } catch (error) {
        console.error('Failed to load attendance:', error);
    }
};

const loadMonthlyReport = async () => {
    if (!monthFilter.value) {
        monthlyReport.value = null;
        return;
    }

    try {
        const { data } = await axios.get('/api/hr/attendance/monthly-report', {
            params: {
                user_id: route.params.id,
                month: monthFilter.value,
            },
        });
        monthlyReport.value = data;
    } catch (error) {
        console.error('Failed to load monthly attendance report:', error);
        monthlyReport.value = null;
    }
};

const loadAttendanceReport = async () => {
    await Promise.all([
        loadAttendance(1),
        loadMonthlyReport(),
    ]);
};

const exportAttendance = async () => {
    try {
        const params = { user_id: route.params.id, per_page: 10000 };
        if (monthFilter.value) {
            params.month = monthFilter.value;
        }
        const { data } = await axios.get('/api/hr/attendance', { params });
        const allAttendance = data.data || [];

        const columns = [
            { key: 'date', label: 'Date' },
            { key: 'check_in_at', label: 'Check In' },
            { key: 'check_out_at', label: 'Check Out' },
            { key: 'work_hours', label: 'Work Hours' },
            { key: 'check_in_latitude', label: 'Check In Latitude' },
            { key: 'check_in_longitude', label: 'Check In Longitude' },
            { key: 'check_in_location_name', label: 'Check In Location Name' },
            { key: 'check_in_location_accuracy', label: 'Check In Accuracy' },
            { key: 'check_in_photo_url', label: 'Check In Photo' },
            { key: 'check_in_map_url', label: 'Check In Map' },
            { key: 'check_out_latitude', label: 'Check Out Latitude' },
            { key: 'check_out_longitude', label: 'Check Out Longitude' },
            { key: 'check_out_location_name', label: 'Check Out Location Name' },
            { key: 'check_out_location_accuracy', label: 'Check Out Accuracy' },
            { key: 'check_out_photo_url', label: 'Check Out Photo' },
            { key: 'check_out_map_url', label: 'Check Out Map' },
        ];

        exportCSV(allAttendance, columns, `attendance_${employee.value?.name}_${new Date().toISOString().split('T')[0]}.csv`);
        toast.success('Attendance exported successfully!');
    } catch (error) {
        console.error('Export failed:', error);
        toast.error('Failed to export attendance');
    }
};

const loadDocuments = async () => {
    try {
        const { data } = await axios.get(`/api/hr/employees/${route.params.id}/documents`);
        documents.value = data || [];
    } catch (e) {
        console.error('Failed to load documents', e);
    }
};

const uploadDocument = async () => {
    documentError.value = null;
    const missing = [];
    if (!newDocName.value) missing.push('a document name');
    if (!fileInput.value?.files?.length) missing.push('a file to upload');
    if (missing.length) {
        documentError.value = `Please provide ${missing.join(' and ')} before adding the document.`;
        await nextTick();
        documentErrorRef.value?.focus();
        return;
    }
    uploading.value = true;
    try {
        const formData = new FormData();
        formData.append('name', newDocName.value);
        formData.append('file', fileInput.value.files[0]);
        await axios.post(`/api/hr/employees/${route.params.id}/documents`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        newDocName.value = '';
        if (fileInput.value) fileInput.value.value = '';
        await loadDocuments();
    } catch (e) {
        console.error('Failed to upload document', e);
        toast.error('Failed to upload document');
    } finally {
        uploading.value = false;
    }
};

const requestDeleteDocument = (doc) => {
    documentToDelete.value = doc;
    confirmDeleteDocOpen.value = true;
};

const cancelDeleteDocument = () => {
    if (deletingDocument.value) return;
    confirmDeleteDocOpen.value = false;
    documentToDelete.value = null;
};

const confirmDeleteDocument = async () => {
    const doc = documentToDelete.value;
    if (!doc) return;
    deletingDocument.value = true;
    try {
        await axios.delete(`/api/hr/employees/${route.params.id}/documents/${doc.id}`);
        await loadDocuments();
    } catch (e) {
        console.error('Failed to delete document', e);
        toast.error('Failed to delete document');
    } finally {
        deletingDocument.value = false;
        confirmDeleteDocOpen.value = false;
        documentToDelete.value = null;
    }
};

const goToEdit = () => {
    router.push({ name: 'employee-edit', params: { id: route.params.id } });
};

onMounted(() => {
    loadEmployee();
    loadStats();
    loadAttendanceReport();
    loadDocuments();
});
</script>
