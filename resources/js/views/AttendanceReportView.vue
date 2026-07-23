<template>
    <ListingPageShell
        title="Attendance Report"
        subtitle="All employee attendance records with check-in/check-out proof, photos, and map links."
        :badge="reportBadge"
    >
        <template #filters>
            <div class="listing-filters-row">
                <div class="w-full sm:w-48">
                    <label class="listing-label">From</label>
                    <input v-model="filters.from_date" type="date" class="listing-input" @change="loadReport(1)" />
                </div>
                <div class="w-full sm:w-48">
                    <label class="listing-label">To</label>
                    <input v-model="filters.to_date" type="date" class="listing-input" @change="loadReport(1)" />
                </div>
                <div class="w-full sm:w-64">
                    <label class="listing-label">Employee</label>
                    <select v-model="filters.user_id" class="listing-input" @change="loadReport(1)">
                        <option value="">All employees</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
                <div class="w-full sm:w-48">
                    <label class="listing-label">Proof</label>
                    <select v-model="filters.proof" class="listing-input" @change="loadReport(1)">
                        <option value="">All records</option>
                        <option value="with_check_in">With check-in proof</option>
                        <option value="missing_check_in">Missing check-in proof</option>
                        <option value="with_check_out">With check-out proof</option>
                        <option value="missing_check_out">Missing check-out proof</option>
                    </select>
                </div>
            </div>
        </template>

        <template #actions>
            <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                <button type="button" class="listing-btn-outline flex-1 sm:flex-none" @click="setToday">Today</button>
                <button type="button" class="listing-btn-outline flex-1 sm:flex-none" @click="setThisMonth">This month</button>
                <button type="button" class="listing-btn-outline flex-1 sm:flex-none" :disabled="monthlyEmployees.length === 0" @click="exportMonthlySummary">
                    Export Monthly Summary
                </button>
                <button type="button" class="listing-btn-primary flex-1 sm:flex-none" :disabled="attendanceList.length === 0" @click="exportAttendance">
                    Export CSV
                </button>
            </div>
        </template>

        <div class="px-3 pb-3 sm:px-5">
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Monthly evaluation</h2>
                        <p class="text-sm text-slate-500">{{ reportRangeLabel }}</p>
                    </div>
                    <div class="text-sm font-semibold text-slate-700">
                        {{ monthlyReport?.employee_count || 0 }} employee{{ (monthlyReport?.employee_count || 0) === 1 ? '' : 's' }}
                    </div>
                </div>

                <div v-if="monthlyLoading" class="py-8 text-center text-sm text-slate-500">
                    Loading monthly summary...
                </div>

                <template v-else>
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <div class="rounded-lg bg-slate-50 p-3">
                            <MetricLabel label="Total hours" tooltip="Total completed work hours in the selected period. Open shifts are not counted until check-out." />
                            <div class="mt-1 text-xl font-bold text-slate-900">{{ formatHours(monthlyTotals.total_hours) }}h</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <MetricLabel label="Present days" tooltip="Number of days where an employee checked in during the selected period." />
                            <div class="mt-1 text-xl font-bold text-slate-900">{{ monthlyTotals.present_days }}</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <MetricLabel label="Completed shifts" tooltip="Attendance records with both check-in and check-out. These records calculate work hours." />
                            <div class="mt-1 text-xl font-bold text-slate-900">{{ monthlyTotals.completed_shifts }}</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3">
                            <MetricLabel label="Open shifts" tooltip="Employees checked in but have not checked out yet. Hours are not final for these records." />
                            <div class="mt-1 text-xl font-bold text-slate-900">{{ monthlyTotals.open_shifts }}</div>
                        </div>
                        <div class="rounded-lg bg-slate-50 p-3 sm:col-span-2 lg:col-span-1">
                            <MetricLabel label="Missing proof" tooltip="Count of check-in or check-out records missing photo proof or GPS location proof." />
                            <div class="mt-1 text-xl font-bold text-slate-900">{{ monthlyMissingProof }}</div>
                        </div>
                    </div>

                    <div v-if="monthlyEmployees.length === 0" class="mt-4 rounded-lg bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">
                        No monthly attendance data found for this selection.
                    </div>

                    <div v-else class="mt-4">
                        <div class="grid grid-cols-1 gap-3 lg:hidden">
                            <div
                                v-for="employee in monthlyEmployees"
                                :key="employee.user_id"
                                class="rounded-lg border border-slate-200 bg-slate-50 p-3"
                            >
                                <div class="font-semibold text-slate-900">{{ employee.employee_name }}</div>
                                <div class="mt-3 grid grid-cols-2 gap-2 text-sm">
                                    <div>
                                        <MetricLabel label="Hours" tooltip="Total completed work hours for this employee in the selected period." />
                                        <div class="font-semibold text-slate-900">{{ formatHours(employee.total_hours) }}h</div>
                                    </div>
                                    <div>
                                        <MetricLabel label="Present" tooltip="Days where this employee checked in during the selected period." />
                                        <div class="font-semibold text-slate-900">{{ employee.present_days }} days</div>
                                    </div>
                                    <div>
                                        <MetricLabel label="Avg/day" tooltip="Total completed hours divided by checked-in days." />
                                        <div class="font-semibold text-slate-900">{{ formatHours(employee.average_hours_per_present_day) }}h</div>
                                    </div>
                                    <div>
                                        <MetricLabel label="Open shifts" tooltip="Checked-in records that do not have a check-out yet." />
                                        <div class="font-semibold text-slate-900">{{ employee.open_shifts }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="hidden overflow-x-auto lg:block">
                            <table class="w-full">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Employee</th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <MetricLabel label="Total Hours" tooltip="Total completed work hours in the selected period." align="right" />
                                        </th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <MetricLabel label="Present Days" tooltip="Days where the employee checked in." align="right" />
                                        </th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <MetricLabel label="Completed" tooltip="Records with both check-in and check-out." align="right" />
                                        </th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <MetricLabel label="Open" tooltip="Checked-in records without check-out." align="right" />
                                        </th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <MetricLabel label="Avg / Day" tooltip="Total completed hours divided by checked-in days." align="right" />
                                        </th>
                                        <th class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <MetricLabel label="Missing Proof" tooltip="Missing photo or GPS proof for check-in/check-out." align="right" />
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    <tr v-for="employee in monthlyEmployees" :key="employee.user_id" class="hover:bg-slate-50">
                                        <td class="px-3 py-3 text-sm font-medium text-slate-900">{{ employee.employee_name }}</td>
                                        <td class="px-3 py-3 text-right text-sm font-semibold text-slate-900">{{ formatHours(employee.total_hours) }}h</td>
                                        <td class="px-3 py-3 text-right text-sm text-slate-700">{{ employee.present_days }}</td>
                                        <td class="px-3 py-3 text-right text-sm text-slate-700">{{ employee.completed_shifts }}</td>
                                        <td class="px-3 py-3 text-right text-sm text-slate-700">{{ employee.open_shifts }}</td>
                                        <td class="px-3 py-3 text-right text-sm text-slate-700">{{ formatHours(employee.average_hours_per_present_day) }}h</td>
                                        <td class="px-3 py-3 text-right text-sm text-slate-700">
                                            {{ employee.missing_check_in_proof + employee.missing_check_out_proof }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div v-if="loading" class="px-5 py-14 text-center text-sm text-slate-500">
            Loading attendance...
        </div>

        <div v-else-if="attendanceList.length === 0" class="px-5 py-12 text-center text-sm text-slate-500">
            No attendance records found for the selected filters.
        </div>

        <div v-else class="space-y-3 px-3 pb-3 sm:px-5">
            <div
                v-for="record in attendanceList"
                :key="record.id"
                class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <div class="font-semibold text-slate-900">{{ record.user?.name || 'Unknown employee' }}</div>
                            <span
                                class="rounded px-2 py-1 text-xs font-medium"
                                :class="record.check_out_at ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'"
                            >
                                {{ record.check_out_at ? 'Completed' : 'In progress' }}
                            </span>
                        </div>
                        <div class="mt-1 text-sm text-slate-500">
                            {{ formatDate(record.date) }} | {{ formatTime(record.check_in_at) }} to {{ record.check_out_at ? formatTime(record.check_out_at) : 'In progress' }}
                        </div>
                    </div>

                    <div class="text-sm font-semibold text-slate-700 lg:text-right">
                        {{ Number(record.work_hours || 0).toFixed(2) }}h
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
                    <proof-panel
                        title="Check-in proof"
                        :photo-url="record.check_in_photo_url"
                        :map-url="record.check_in_map_url"
                        :latitude="record.check_in_latitude"
                        :longitude="record.check_in_longitude"
                        :location-name="record.check_in_location_name"
                        :accuracy="record.check_in_location_accuracy"
                        :captured-at="record.check_in_location_captured_at"
                    />
                    <proof-panel
                        title="Check-out proof"
                        :photo-url="record.check_out_photo_url"
                        :map-url="record.check_out_map_url"
                        :latitude="record.check_out_latitude"
                        :longitude="record.check_out_longitude"
                        :location-name="record.check_out_location_name"
                        :accuracy="record.check_out_location_accuracy"
                        :captured-at="record.check_out_location_captured_at"
                    />
                </div>
            </div>
        </div>

        <template #pagination>
            <Pagination
                v-if="attendancePagination"
                :pagination="attendancePagination"
                embedded
                result-label="records"
                singular-label="record"
                @page-change="loadAttendance"
            />
        </template>
    </ListingPageShell>
</template>

<script setup>
import { computed, defineComponent, h, onMounted, ref } from 'vue';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';
import Pagination from '@/components/Pagination.vue';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const dateToInput = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const today = new Date();
const filters = ref({
    from_date: dateToInput(new Date(today.getFullYear(), today.getMonth(), 1)),
    to_date: dateToInput(today),
    user_id: '',
    proof: '',
});

const users = ref([]);
const attendanceList = ref([]);
const attendancePagination = ref(null);
const monthlyReport = ref(null);
const loading = ref(false);
const monthlyLoading = ref(false);

const reportBadge = computed(() => {
    const total = attendancePagination.value?.total;
    if (!total) return null;
    return `${total} ${total === 1 ? 'record' : 'records'}`;
});

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatTime = (datetime) => {
    if (!datetime) return '-';
    return new Date(datetime).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};

const formatHours = (hours) => Number(hours || 0).toFixed(2);

const reportRangeLabel = computed(() => {
    if (!filters.value.from_date || !filters.value.to_date) return 'Selected attendance period';
    return `${formatDate(filters.value.from_date)} to ${formatDate(filters.value.to_date)}`;
});

const monthlyEmployees = computed(() => monthlyReport.value?.employees || []);
const monthlyTotals = computed(() => monthlyReport.value?.totals || {
    total_hours: 0,
    present_days: 0,
    completed_shifts: 0,
    open_shifts: 0,
    missing_check_in_proof: 0,
    missing_check_out_proof: 0,
});
const monthlyMissingProof = computed(() => (
    Number(monthlyTotals.value.missing_check_in_proof || 0) + Number(monthlyTotals.value.missing_check_out_proof || 0)
));

const baseParams = (perPage = 15, page = 1) => {
    const params = {
        per_page: perPage,
        page,
        from_date: filters.value.from_date,
        to_date: filters.value.to_date,
    };

    if (filters.value.user_id) params.user_id = filters.value.user_id;
    if (filters.value.proof) params.proof = filters.value.proof;

    return params;
};

const summaryParams = () => {
    const params = {
        from_date: filters.value.from_date,
        to_date: filters.value.to_date,
    };

    if (filters.value.user_id) params.user_id = filters.value.user_id;
    if (filters.value.proof) params.proof = filters.value.proof;

    return params;
};

const loadUsers = async () => {
    try {
        const { data } = await axios.get('/api/users', { params: { per_page: 1000 } });
        users.value = data.data || data || [];
    } catch (error) {
        console.error('Failed to load users:', error);
        users.value = [];
    }
};

const loadAttendance = async (page = 1) => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/hr/attendance', { params: baseParams(15, page) });
        attendanceList.value = data.data || [];
        attendancePagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page || 15,
            total: data.total || 0,
        };
    } catch (error) {
        console.error('Failed to load attendance report:', error);
        toast.error('Failed to load attendance report.');
        attendanceList.value = [];
    } finally {
        loading.value = false;
    }
};

const loadMonthlyReport = async () => {
    monthlyLoading.value = true;
    try {
        const { data } = await axios.get('/api/hr/attendance/monthly-report', { params: summaryParams() });
        monthlyReport.value = data;
    } catch (error) {
        console.error('Failed to load monthly attendance report:', error);
        toast.error('Failed to load monthly attendance summary.');
        monthlyReport.value = null;
    } finally {
        monthlyLoading.value = false;
    }
};

const loadReport = async (page = 1) => {
    await Promise.all([
        loadAttendance(page),
        loadMonthlyReport(),
    ]);
};

const setToday = () => {
    const now = new Date();
    filters.value.from_date = dateToInput(now);
    filters.value.to_date = dateToInput(now);
    loadReport(1);
};

const setThisMonth = () => {
    const now = new Date();
    filters.value.from_date = dateToInput(new Date(now.getFullYear(), now.getMonth(), 1));
    filters.value.to_date = dateToInput(now);
    loadReport(1);
};

const exportMonthlySummary = () => {
    if (!monthlyEmployees.value.length) {
        toast.error('No monthly summary to export.');
        return;
    }

    const columns = [
        { key: 'employee_name', label: 'Employee' },
        { key: 'employee_email', label: 'Email' },
        { key: 'total_hours', label: 'Total Hours' },
        { key: 'present_days', label: 'Present Days' },
        { key: 'completed_shifts', label: 'Completed Shifts' },
        { key: 'open_shifts', label: 'Open Shifts' },
        { key: 'average_hours_per_present_day', label: 'Average Hours Per Present Day' },
        { key: 'average_completed_shift_hours', label: 'Average Completed Shift Hours' },
        { key: 'missing_check_in_proof', label: 'Missing Check In Proof' },
        { key: 'missing_check_out_proof', label: 'Missing Check Out Proof' },
        { key: 'first_attendance_date', label: 'First Attendance Date' },
        { key: 'last_attendance_date', label: 'Last Attendance Date' },
    ];

    exportCSV(monthlyEmployees.value, columns, `attendance-monthly-summary-${filters.value.from_date}-${filters.value.to_date}.csv`);
    toast.success('Monthly attendance summary exported.');
};

const exportAttendance = async () => {
    try {
        const { data } = await axios.get('/api/hr/attendance', { params: baseParams(10000, 1) });
        const rows = data.data || [];
        if (!rows.length) {
            toast.error('No attendance records to export.');
            return;
        }

        const columns = [
            { key: 'date', label: 'Date' },
            { key: 'user.name', label: 'Employee' },
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

        exportCSV(rows, columns, `attendance-report-${filters.value.from_date}-${filters.value.to_date}.csv`);
        toast.success('Attendance report exported.');
    } catch (error) {
        console.error('Failed to export attendance report:', error);
        toast.error('Failed to export attendance report.');
    }
};

const MetricLabel = defineComponent({
    name: 'MetricLabel',
    props: {
        label: { type: String, required: true },
        tooltip: { type: String, required: true },
        align: { type: String, default: 'left' },
    },
    setup(props) {
        return () => h('div', {
            class: [
                'flex min-w-0 items-center gap-1 text-xs font-medium text-slate-500',
                props.align === 'right' ? 'justify-end' : 'justify-start',
            ],
            title: props.tooltip,
        }, [
            h('span', { class: 'min-w-0 truncate' }, props.label),
            h('span', { class: 'group relative inline-flex shrink-0' }, [
                h('button', {
                    type: 'button',
                    class: 'grid h-4 w-4 place-items-center rounded-full border border-slate-300 bg-white text-[10px] font-bold leading-none text-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-200',
                    'aria-label': `${props.label}: ${props.tooltip}`,
                }, '?'),
                h('span', {
                    class: [
                        'pointer-events-none absolute bottom-full z-30 mb-2 hidden w-56 rounded-md bg-slate-900 px-3 py-2 text-left text-[11px] font-medium normal-case leading-4 tracking-normal text-white shadow-lg group-hover:block group-focus-within:block',
                        props.align === 'right' ? 'right-0' : 'left-0',
                    ],
                }, props.tooltip),
            ]),
        ]);
    },
});

const ProofPanel = defineComponent({
    name: 'ProofPanel',
    props: {
        title: { type: String, required: true },
        photoUrl: { type: String, default: null },
        mapUrl: { type: String, default: null },
        latitude: { type: [String, Number], default: null },
        longitude: { type: [String, Number], default: null },
        locationName: { type: String, default: null },
        accuracy: { type: [String, Number], default: null },
        capturedAt: { type: String, default: null },
    },
    setup(props) {
        const hasLocation = () => props.locationName || (props.latitude && props.longitude) || props.mapUrl;
        const photoNode = () => {
            if (!props.photoUrl) {
                return h('div', {
                    class: 'grid aspect-[4/3] w-full place-items-center rounded-lg border border-dashed border-slate-300 bg-white text-sm font-medium text-slate-400',
                }, 'No photo captured');
            }

            return h('a', {
                href: props.photoUrl,
                target: '_blank',
                rel: 'noopener',
                class: 'block overflow-hidden rounded-lg border border-slate-200 bg-white',
                title: 'Open attendance photo',
            }, [
                h('img', {
                    src: props.photoUrl,
                    alt: props.title,
                    loading: 'lazy',
                    class: 'aspect-[4/3] w-full object-cover',
                }),
            ]);
        };

        return () => h('div', { class: 'rounded-lg border border-slate-200 bg-slate-50 p-3' }, [
            h('div', { class: 'flex items-center justify-between gap-3' }, [
                h('div', { class: 'text-sm font-semibold text-slate-900' }, props.title),
                props.capturedAt ? h('div', { class: 'shrink-0 text-xs font-medium text-slate-500' }, formatTime(props.capturedAt)) : null,
            ]),
            h('div', { class: 'mt-3 grid gap-3 sm:grid-cols-[9rem,1fr] sm:items-start' }, [
                photoNode(),
                h('div', { class: 'min-w-0 rounded-lg border border-slate-200 bg-white p-3' }, [
                    h('div', { class: 'text-[11px] font-semibold uppercase tracking-wide text-slate-500' }, 'Location'),
                    hasLocation()
                        ? h('div', { class: 'mt-1 space-y-2' }, [
                            props.locationName
                                ? h('div', { class: 'break-words text-sm font-semibold leading-5 text-slate-900' }, props.locationName)
                                : h('div', { class: 'text-sm font-medium text-slate-500' }, 'Location name not available'),
                            props.latitude && props.longitude
                                ? h('div', { class: 'break-words text-xs text-slate-500' }, `${props.latitude}, ${props.longitude}`)
                                : null,
                            props.accuracy
                                ? h('div', { class: 'text-xs text-slate-500' }, `Accuracy ${Math.round(Number(props.accuracy))}m`)
                                : null,
                            props.mapUrl
                                ? h('a', {
                                    href: props.mapUrl,
                                    target: '_blank',
                                    rel: 'noopener',
                                    class: 'inline-flex min-h-9 items-center rounded-md border border-blue-200 bg-blue-50 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-100',
                                }, 'View on map')
                                : null,
                        ])
                        : h('div', { class: 'mt-1 text-sm font-medium text-slate-400' }, 'No location captured'),
                ]),
            ]),
        ]);
    },
});

onMounted(async () => {
    await loadUsers();
    await loadReport();
});
</script>
