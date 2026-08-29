<template>
    <div class="w-full min-w-0">
        <router-view v-if="isAdmin" />

        <ListingPageShell
            v-else
            title="My attendance"
            subtitle="Check in when you start and check out when you finish — hours sync for payroll."
            :badge="attendanceShellBadge"
        >
            <template #actions>
                <BaseButton variant="outline" block-mobile @click="exportAttendance">
                    <template #icon><ArrowDownTrayIcon class="icon" aria-hidden="true" /></template>
                    Export CSV
                </BaseButton>
            </template>

            <template #toolbar>
                <AttendanceClock @updated="loadAttendance(1)" />
            </template>

            <BaseTable
                :columns="attendanceColumns"
                :rows="attendanceList"
                :loading="loadingAttendance"
                row-key="id"
                min-width="720px"
                caption="Your attendance records: date, check-in proof, check-out proof and hours worked."
            >
                <template #cell-date="{ row }">
                    <span class="font-semibold text-slate-800">{{ formatDate(row.date) }}</span>
                </template>

                <template #cell-check_in="{ row }">
                    <div class="flex items-start gap-2">
                        <img
                            v-if="row.check_in_photo_url"
                            :src="row.check_in_photo_url"
                            alt="Check-in proof"
                            class="h-10 w-10 shrink-0 rounded object-cover"
                        />
                        <div class="min-w-0">
                            <div class="font-medium text-slate-800">{{ formatTime(row.check_in_at) }}</div>
                            <div v-if="row.check_in_location_name" class="break-words text-xs text-slate-600">
                                {{ row.check_in_location_name }}
                            </div>
                            <a
                                v-if="row.check_in_map_url"
                                :href="row.check_in_map_url"
                                target="_blank"
                                rel="noopener"
                                class="link inline-flex min-h-8 items-center gap-1 text-xs"
                            >
                                <MapPinIcon class="icon-sm" aria-hidden="true" />
                                Open map
                            </a>
                            <div v-else class="text-xs text-slate-500">Location not captured</div>
                        </div>
                    </div>
                </template>

                <template #cell-check_out="{ row }">
                    <div class="flex items-start gap-2">
                        <img
                            v-if="row.check_out_photo_url"
                            :src="row.check_out_photo_url"
                            alt="Check-out proof"
                            class="h-10 w-10 shrink-0 rounded object-cover"
                        />
                        <div class="min-w-0">
                            <div class="font-medium text-slate-800">
                                {{ row.check_out_at ? formatTime(row.check_out_at) : 'In progress' }}
                            </div>
                            <div v-if="row.check_out_location_name" class="break-words text-xs text-slate-600">
                                {{ row.check_out_location_name }}
                            </div>
                            <a
                                v-if="row.check_out_map_url"
                                :href="row.check_out_map_url"
                                target="_blank"
                                rel="noopener"
                                class="link inline-flex min-h-8 items-center gap-1 text-xs"
                            >
                                <MapPinIcon class="icon-sm" aria-hidden="true" />
                                Open map
                            </a>
                            <div v-else class="text-xs text-slate-500">Location not captured</div>
                        </div>
                    </div>
                </template>

                <template #cell-work_hours="{ row }">
                    <span class="font-semibold text-slate-800">{{ parseFloat(row.work_hours || 0).toFixed(2) }}h</span>
                </template>

                <template #mobile="{ row }">
                    <div class="table-card">
                        <div class="flex items-start justify-between gap-3">
                            <span class="font-semibold text-slate-900">{{ formatDate(row.date) }}</span>
                            <span class="text-sm font-semibold text-slate-700">
                                {{ parseFloat(row.work_hours || 0).toFixed(2) }}h
                            </span>
                        </div>
                        <div class="grid grid-cols-1 gap-2 text-xs sm:grid-cols-2">
                            <div class="flex items-start gap-2 rounded-lg border border-slate-200 bg-white p-2">
                                <img
                                    v-if="row.check_in_photo_url"
                                    :src="row.check_in_photo_url"
                                    alt="Check-in proof"
                                    class="h-10 w-10 shrink-0 rounded object-cover"
                                />
                                <div class="min-w-0">
                                    <div class="text-eyebrow uppercase text-slate-500">Check-in</div>
                                    <div class="font-medium text-slate-800">{{ formatTime(row.check_in_at) }}</div>
                                    <div v-if="row.check_in_location_name" class="break-words text-slate-600">
                                        {{ row.check_in_location_name }}
                                    </div>
                                    <a
                                        v-if="row.check_in_map_url"
                                        :href="row.check_in_map_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="link inline-flex min-h-8 items-center gap-1"
                                    >
                                        <MapPinIcon class="icon-sm" aria-hidden="true" />
                                        Open map
                                    </a>
                                    <div v-else class="text-slate-500">Location not captured</div>
                                </div>
                            </div>
                            <div class="flex items-start gap-2 rounded-lg border border-slate-200 bg-white p-2">
                                <img
                                    v-if="row.check_out_photo_url"
                                    :src="row.check_out_photo_url"
                                    alt="Check-out proof"
                                    class="h-10 w-10 shrink-0 rounded object-cover"
                                />
                                <div class="min-w-0">
                                    <div class="text-eyebrow uppercase text-slate-500">Check-out</div>
                                    <div class="font-medium text-slate-800">
                                        {{ row.check_out_at ? formatTime(row.check_out_at) : 'In progress' }}
                                    </div>
                                    <div v-if="row.check_out_location_name" class="break-words text-slate-600">
                                        {{ row.check_out_location_name }}
                                    </div>
                                    <a
                                        v-if="row.check_out_map_url"
                                        :href="row.check_out_map_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="link inline-flex min-h-8 items-center gap-1"
                                    >
                                        <MapPinIcon class="icon-sm" aria-hidden="true" />
                                        Open map
                                    </a>
                                    <div v-else class="text-slate-500">Location not captured</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template #empty>
                    <EmptyState
                        heading="No attendance records found"
                        description="Your check-ins will appear here once you clock in for the day."
                    >
                        <template #icon><ClockIcon class="icon" aria-hidden="true" /></template>
                    </EmptyState>
                </template>
            </BaseTable>

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

        <!-- Salary Form Modal -->
        <BaseModal
            v-model="showSalaryForm"
            :title="editingSalary ? 'Edit Salary' : 'Add Salary'"
            size="lg"
            :close-on-backdrop="false"
            @close="closeSalaryForm"
        >
            <form id="hr-salary-form" class="space-y-4" novalidate @submit.prevent="saveSalary">
                <div class="form-grid-2">
                    <div>
                        <label class="form-label" for="hrview-employee">
                            Employee<span class="form-required" aria-hidden="true">*</span>
                        </label>
                        <select
                            id="hrview-employee"
                            v-model="salaryForm.user_id"
                            required
                            :disabled="editingSalary"
                            class="form-select"
                        >
                            <option value="">Select employee...</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                {{ user.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="hrview-month">
                            Month<span class="form-required" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="hrview-month"
                            v-model="salaryForm.month"
                            type="month"
                            required
                            :disabled="editingSalary"
                            class="form-input"
                        />
                    </div>
                </div>

                <div>
                    <label class="form-label" for="hrview-currency">
                        Currency<span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <select id="hrview-currency" v-model="salaryForm.currency" required class="form-select">
                        <option value="GBP">GBP (£)</option>
                        <option value="PKR">PKR (₨)</option>
                    </select>
                </div>

                <div class="form-grid-2">
                    <div>
                        <label class="form-label" for="hrview-base-salary">
                            Base Salary<span class="form-required" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="hrview-base-salary"
                            v-model.number="salaryForm.base_salary"
                            type="number"
                            step="0.01"
                            min="0"
                            required
                            class="form-input"
                        />
                    </div>
                    <div>
                        <label class="form-label" for="hrview-traveling-allowance">Traveling Allowance</label>
                        <input
                            id="hrview-traveling-allowance"
                            v-model.number="salaryForm.allowances"
                            type="number"
                            step="0.01"
                            min="0"
                            placeholder="Enter amount or 0"
                            class="form-input"
                            aria-describedby="hrview-traveling-allowance-hint"
                        />
                        <p id="hrview-traveling-allowance-hint" class="form-hint">
                            This amount will be added to the base salary
                        </p>
                    </div>
                </div>

                <div>
                    <label class="form-label" for="hrview-attendance-days">Attendance Days</label>
                    <input
                        id="hrview-attendance-days"
                        v-model.number="salaryForm.attendance_days"
                        type="number"
                        min="0"
                        class="form-input"
                        placeholder="Auto-calculated if not set"
                    />
                </div>

                <fieldset class="form-fieldset">
                    <legend class="form-legend">Bonuses</legend>
                    <div v-for="(bonus, index) in salaryForm.bonuses" :key="index" class="mb-2 flex flex-wrap gap-2">
                        <div class="min-w-0 flex-1">
                            <label class="sr-only" :for="`hrview-bonus-name-${index}`">Bonus {{ index + 1 }} name</label>
                            <input
                                :id="`hrview-bonus-name-${index}`"
                                v-model="bonus.name"
                                type="text"
                                placeholder="Bonus name"
                                class="form-input"
                            />
                        </div>
                        <div class="w-32">
                            <label class="sr-only" :for="`hrview-bonus-amount-${index}`">Bonus {{ index + 1 }} amount</label>
                            <input
                                :id="`hrview-bonus-amount-${index}`"
                                v-model.number="bonus.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Amount"
                                class="form-input"
                            />
                        </div>
                        <BaseButton
                            variant="danger"
                            size="icon"
                            :label="`Remove bonus ${index + 1}`"
                            @click="removeBonus(index)"
                        >
                            <template #icon><TrashIcon class="icon" aria-hidden="true" /></template>
                        </BaseButton>
                    </div>
                    <BaseButton variant="outline" size="sm" @click="addBonus">
                        <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                        Add Bonus
                    </BaseButton>
                </fieldset>

                <fieldset class="form-fieldset">
                    <legend class="form-legend">Deductions Detail</legend>
                    <div
                        v-for="(deduction, index) in salaryForm.deductions_detail"
                        :key="index"
                        class="mb-2 flex flex-wrap gap-2"
                    >
                        <div class="min-w-0 flex-1">
                            <label class="sr-only" :for="`hrview-deduction-name-${index}`">
                                Deduction {{ index + 1 }} name
                            </label>
                            <input
                                :id="`hrview-deduction-name-${index}`"
                                v-model="deduction.name"
                                type="text"
                                placeholder="Deduction name"
                                class="form-input"
                            />
                        </div>
                        <div class="w-32">
                            <label class="sr-only" :for="`hrview-deduction-amount-${index}`">
                                Deduction {{ index + 1 }} amount
                            </label>
                            <input
                                :id="`hrview-deduction-amount-${index}`"
                                v-model.number="deduction.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Amount"
                                class="form-input"
                            />
                        </div>
                        <BaseButton
                            variant="danger"
                            size="icon"
                            :label="`Remove deduction ${index + 1}`"
                            @click="removeDeduction(index)"
                        >
                            <template #icon><TrashIcon class="icon" aria-hidden="true" /></template>
                        </BaseButton>
                    </div>
                    <BaseButton variant="outline" size="sm" @click="addDeduction">
                        <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                        Add Deduction
                    </BaseButton>
                </fieldset>

                <div>
                    <label class="form-label" for="hrview-notes">Notes</label>
                    <textarea
                        id="hrview-notes"
                        v-model="salaryForm.notes"
                        rows="3"
                        class="form-textarea"
                        placeholder="Additional notes..."
                    ></textarea>
                </div>

                <div class="rounded-control border border-slate-200 bg-slate-50 p-3">
                    <div class="text-eyebrow uppercase text-slate-500">Net Salary</div>
                    <div class="mt-1 text-lg font-semibold text-slate-900 tabular-nums">
                        {{ salaryForm.currency === 'PKR' ? '₨' : '£' }}{{ formatNumber(calculateNetSalary()) }}
                        <span class="ml-2 text-sm font-normal text-slate-500">({{ salaryForm.currency }})</span>
                    </div>
                </div>

                <p v-if="salaryError" class="callout callout-danger" role="alert">
                    {{ salaryError }}
                </p>
            </form>

            <template #actions>
                <BaseButton variant="outline" block-mobile @click="closeSalaryForm">Cancel</BaseButton>
                <BaseButton
                    variant="primary"
                    type="submit"
                    form="hr-salary-form"
                    block-mobile
                    :loading="savingSalary"
                >
                    {{ editingSalary ? 'Update' : 'Create' }}
                </BaseButton>
            </template>
        </BaseModal>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import {
    ArrowDownTrayIcon,
    ClockIcon,
    MapPinIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import AttendanceClock from '@/components/AttendanceClock.vue';
import { BaseButton, BaseModal, BaseTable, EmptyState } from '@/components/base';
import { exportToCSV as exportCSV } from '@/utils/exportCsv';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';

const toast = useToastStore();
const auth = useAuthStore();

const isAdmin = computed(() => {
    const role = auth.user?.role?.name;
    return role === 'Admin' || role === 'Manager' || role === 'System Admin';
});

const attendanceShellBadge = computed(() => {
    if (isAdmin.value) return null;
    const t = attendancePagination.value?.total;
    if (t == null || t === 0) return null;
    return `${t} ${t === 1 ? 'record' : 'records'}`;
});

const attendanceColumns = [
    { key: 'date', label: 'Date' },
    { key: 'check_in', label: 'Check-in' },
    { key: 'check_out', label: 'Check-out' },
    { key: 'work_hours', label: 'Hours', align: 'right' },
];

const checkedIn = ref(false);
const attendanceList = ref([]);
const attendancePagination = ref(null);
const attendanceFilter = ref({ user_id: '' });
const loadingAttendance = ref(true);
const salaries = ref([]);
const salaryPagination = ref(null);
const users = ref([]);
const showSalaryForm = ref(false);
const editingSalary = ref(null);
const savingSalary = ref(false);
const salaryError = ref(null);
const todayAttendanceCount = ref(0);

const salaryForm = ref({
    user_id: '',
    month: '',
    base_salary: 0,
    allowances: 0,
    deductions: 0,
    currency: 'GBP',
    bonuses: [],
    deductions_detail: [],
    attendance_days: null,
    notes: '',
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num || 0);
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};

const formatTime = (datetime) => {
    if (!datetime) return '-';
    return new Date(datetime).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};

const formatMonth = (month) => {
    if (!month) return '-';
    const [year, monthNum] = month.split('-');
    const date = new Date(year, monthNum - 1);
    return date.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
};

const checkIn = async () => {
    try {
        await axios.post('/api/hr/attendance/check-in');
        checkedIn.value = true;
        loadAttendance(1);
        toast.success('Checked in successfully!');
    } catch (error) {
        console.error('Failed to check in:', error);
        toast.error(error.response?.data?.error || 'Failed to check in. Please try again.');
    }
};

const checkOut = async () => {
    try {
        await axios.post('/api/hr/attendance/check-out');
        checkedIn.value = false;
        loadAttendance(1);
        toast.success('Checked out successfully!');
    } catch (error) {
        console.error('Failed to check out:', error);
        toast.error(error.response?.data?.error || 'Failed to check out. Please try again.');
    }
};

const loadAttendance = async (page = 1) => {
    loadingAttendance.value = true;
    try {
        const params = { per_page: 10, page };
        if (isAdmin.value && attendanceFilter.value.user_id) {
            params.user_id = attendanceFilter.value.user_id;
        }
        const { data } = await axios.get('/api/hr/attendance', { params });
        attendanceList.value = data.data || [];
        attendancePagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page || 10,
            total: data.total || 0,
        };

        // Check if user is currently checked in (non-admin only)
        if (!isAdmin.value && data.data && data.data.length > 0) {
            const today = new Date().toISOString().split('T')[0];
            const todayRecord = data.data.find(a => a.date === today && !a.check_out_at);
            checkedIn.value = !!todayRecord;
        }

        // Calculate today's attendance count (admin only)
        if (isAdmin.value) {
            const today = new Date().toISOString().split('T')[0];
            todayAttendanceCount.value = data.data?.filter(a => a.date === today && a.check_in_at).length || 0;
        }
    } catch (error) {
        console.error('Failed to load attendance:', error);
    } finally {
        loadingAttendance.value = false;
    }
};

const loadSalaries = async (page = 1) => {
    try {
        const { data } = await axios.get('/api/hr/salaries', {
            params: { per_page: 10, page },
        });
        salaries.value = data.data || [];
        salaryPagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page || 10,
            total: data.total || 0,
        };
    } catch (error) {
        console.error('Failed to load salaries:', error);
    }
};

const loadUsers = async () => {
    try {
        // Load all users including admin - request more records to ensure we get everyone
        const { data } = await axios.get('/api/users', { params: { per_page: 1000 } });
        // Get all users including admin - handle both array and paginated response
        users.value = data.data || data || [];
        console.log('Loaded users for HR:', users.value.length, users.value.map(u => ({ id: u.id, name: u.name, role: u.role?.name })));
    } catch (error) {
        console.error('Failed to load users:', error);
    }
};

const openSalaryForm = (salary = null) => {
    editingSalary.value = salary;
    if (salary) {
        salaryForm.value = {
            user_id: salary.user_id,
            month: salary.month,
            base_salary: parseFloat(salary.base_salary),
            allowances: parseFloat(salary.allowances || 0),
            deductions: parseFloat(salary.deductions || 0),
            currency: salary.currency || 'GBP',
            bonuses: salary.bonuses && Array.isArray(salary.bonuses) ? [...salary.bonuses] : [],
            deductions_detail: salary.deductions_detail && Array.isArray(salary.deductions_detail) ? [...salary.deductions_detail] : [],
            attendance_days: salary.attendance_days,
            notes: salary.notes || '',
        };
    } else {
        salaryForm.value = {
            user_id: '',
            month: new Date().toISOString().slice(0, 7),
            base_salary: 0,
            allowances: 0,
            deductions: 0,
            currency: 'GBP',
            bonuses: [],
            deductions_detail: [],
            attendance_days: null,
            notes: '',
        };
    }
    salaryError.value = null;
    showSalaryForm.value = true;
};

const closeSalaryForm = () => {
    showSalaryForm.value = false;
    editingSalary.value = null;
    salaryForm.value = {
        user_id: '',
        month: '',
        base_salary: 0,
        allowances: 0,
        deductions: 0,
        currency: 'GBP',
        bonuses: [],
        deductions_detail: [],
        attendance_days: null,
        notes: '',
    };
    salaryError.value = null;
};

const addBonus = () => {
    salaryForm.value.bonuses.push({ name: '', amount: 0 });
};

const removeBonus = (index) => {
    salaryForm.value.bonuses.splice(index, 1);
};

const addDeduction = () => {
    salaryForm.value.deductions_detail.push({ name: '', amount: 0 });
};

const removeDeduction = (index) => {
    salaryForm.value.deductions_detail.splice(index, 1);
};

const calculateNetSalary = () => {
    const base = parseFloat(salaryForm.value.base_salary) || 0;
    const travelingAllowance = parseFloat(salaryForm.value.allowances) || 0;
    const bonuses = salaryForm.value.bonuses.reduce((sum, b) => sum + (parseFloat(b.amount) || 0), 0);
    const deductionsDetail = salaryForm.value.deductions_detail.reduce((sum, d) => sum + (parseFloat(d.amount) || 0), 0);
    // Base + Traveling Allowance + Bonuses - Deductions Detail
    return base + travelingAllowance + bonuses - deductionsDetail;
};

const saveSalary = async () => {
    savingSalary.value = true;
    salaryError.value = null;

    try {
        const payload = {
            ...salaryForm.value,
            allowances: salaryForm.value.allowances || 0, // Traveling Allowance
            deductions: 0, // Not used anymore, but keep for backward compatibility
            bonuses: salaryForm.value.bonuses.filter(b => b.name && b.amount > 0),
            deductions_detail: salaryForm.value.deductions_detail.filter(d => d.name && d.amount > 0),
        };

        if (editingSalary.value) {
            await axios.put(`/api/hr/salaries/${editingSalary.value.id}`, payload);
        } else {
            await axios.post('/api/hr/salaries', payload);
        }
        closeSalaryForm();
        loadSalaries(salaryPagination.value?.current_page || 1);
        toast.success('Salary saved successfully!');
    } catch (error) {
        if (error.response?.data?.errors) {
            salaryError.value = Object.values(error.response.data.errors).flat().join(', ');
        } else if (error.response?.data?.message) {
            salaryError.value = error.response.data.message;
        } else {
            salaryError.value = 'Failed to save salary. Please try again.';
        }
        console.error('Failed to save salary:', error);
    } finally {
        savingSalary.value = false;
    }
};

const downloadSalarySlip = async (id) => {
    try {
        const response = await axios.get(`/api/hr/salaries/${id}/slip`, {
            responseType: 'blob',
        });
        const url = window.URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `salary_slip_${id}.pdf`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        toast.success('Salary slip downloaded!');
    } catch (error) {
        console.error('Failed to download salary slip:', error);
        toast.error('Failed to download salary slip. Please try again.');
    }
};

const sendSalarySlipEmail = async (id) => {
    try {
        await axios.post(`/api/hr/salaries/${id}/send-email`);
        toast.success('Salary slip sent via email!');
    } catch (error) {
        console.error('Failed to send salary slip:', error);
        toast.error(error.response?.data?.error || 'Failed to send email. Please try again.');
    }
};

const exportAttendance = async () => {
    try {
        const params = { per_page: 10000 };
        if (isAdmin.value && attendanceFilter.value.user_id) {
            params.user_id = attendanceFilter.value.user_id;
        }
        const { data } = await axios.get('/api/hr/attendance', { params });
        const allAttendance = data.data || [];

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

        exportCSV(allAttendance, columns, `attendance_export_${new Date().toISOString().split('T')[0]}.csv`);
    } catch (error) {
        console.error('Export failed:', error);
        toast.error('Failed to export attendance. Please try again.');
    }
};

const exportSalaries = async () => {
    try {
        const { data } = await axios.get('/api/hr/salaries', { params: { per_page: 10000 } });
        const allSalaries = data.data || [];

        const columns = [
            { key: 'month', label: 'Month' },
            { key: 'user.name', label: 'Employee' },
            { key: 'base_salary', label: 'Base Salary' },
            { key: 'allowances', label: 'Allowances' },
            { key: 'deductions', label: 'Deductions' },
            { key: 'net_salary', label: 'Net Salary' },
            { key: 'attendance_days', label: 'Attendance Days' },
        ];

        exportCSV(allSalaries, columns, `salaries_export_${new Date().toISOString().split('T')[0]}.csv`);
    } catch (error) {
        console.error('Export failed:', error);
        toast.error('Failed to export salaries. Please try again.');
    }
};

onMounted(() => {
    loadAttendance();
    if (isAdmin.value) {
        loadSalaries();
    }
    loadUsers();
});
</script>
