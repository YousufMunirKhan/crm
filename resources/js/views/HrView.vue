<template>
    <div class="w-full min-w-0">
        <div v-if="isAdmin" class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-6 py-4 lg:py-6 space-y-4">
            <h1 class="text-xl lg:text-2xl font-bold text-slate-800 tracking-tight">HR management</h1>
            <router-view />
        </div>

        <ListingPageShell
            v-else
            title="My attendance"
            subtitle="Check in when you start and check out when you finish — hours sync for payroll."
            :badge="attendanceShellBadge"
        >
            <template #actions>
                <div class="flex flex-wrap gap-2 w-full sm:w-auto justify-stretch sm:justify-end">
                    <button
                        v-if="!checkedIn"
                        type="button"
                        class="inline-flex flex-1 sm:flex-none items-center justify-center px-4 py-2.5 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700"
                        @click="checkIn"
                    >
                        Check in
                    </button>
                    <button
                        v-else
                        type="button"
                        class="inline-flex flex-1 sm:flex-none items-center justify-center px-4 py-2.5 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700"
                        @click="checkOut"
                    >
                        Check out
                    </button>
                    <button type="button" class="listing-btn-outline flex-1 sm:flex-initial" @click="exportAttendance">Export CSV</button>
                </div>
            </template>

            <div v-if="attendanceList.length === 0" class="px-5 py-12 text-center text-slate-500 text-sm">
                No attendance records found
            </div>
            <div v-else class="space-y-3 px-3 pb-2 sm:px-5">
                <div
                    v-for="attendance in attendanceList"
                    :key="attendance.id"
                    class="flex flex-col sm:flex-row sm:items-center sm:justify-between p-4 rounded-xl border border-slate-200 bg-slate-50/40 gap-2"
                >
                    <div class="flex-1">
                        <div class="font-medium text-slate-900">{{ formatDate(attendance.date) }}</div>
                        <div class="text-xs text-slate-500">
                            {{ formatTime(attendance.check_in_at) }} – {{ attendance.check_out_at ? formatTime(attendance.check_out_at) : 'In progress' }}
                        </div>
                    </div>
                    <div class="text-sm font-semibold text-slate-700">{{ parseFloat(attendance.work_hours || 0).toFixed(2) }}h</div>
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

        <!-- Salary Form Modal -->
        <div
            v-if="showSalaryForm"
            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto"
            @click.self="closeSalaryForm"
        >
            <div class="bg-white rounded-xl shadow-lg p-6 w-full max-w-2xl my-8">
                <h2 class="text-xl font-semibold text-slate-900 mb-4">
                    {{ editingSalary ? 'Edit Salary' : 'Add Salary' }}
                </h2>
                <form @submit.prevent="saveSalary" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Employee *</label>
                            <select
                                v-model="salaryForm.user_id"
                                required
                                :disabled="editingSalary"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            >
                                <option value="">Select employee...</option>
                                <option v-for="user in users" :key="user.id" :value="user.id">
                                    {{ user.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Month *</label>
                            <input
                                v-model="salaryForm.month"
                                type="month"
                                required
                                :disabled="editingSalary"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Currency *</label>
                        <select
                            v-model="salaryForm.currency"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="GBP">GBP (£)</option>
                            <option value="PKR">PKR (₨)</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Base Salary *</label>
                            <input
                                v-model.number="salaryForm.base_salary"
                                type="number"
                                step="0.01"
                                min="0"
                                required
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Traveling Allowance</label>
                            <input
                                v-model.number="salaryForm.allowances"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Enter amount or 0"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                            <p class="text-xs text-slate-500 mt-1">This amount will be added to the base salary</p>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Attendance Days</label>
                        <input
                            v-model.number="salaryForm.attendance_days"
                            type="number"
                            min="0"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            placeholder="Auto-calculated if not set"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Bonuses</label>
                        <div v-for="(bonus, index) in salaryForm.bonuses" :key="index" class="flex gap-2 mb-2">
                            <input
                                v-model="bonus.name"
                                type="text"
                                placeholder="Bonus name"
                                class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                            <input
                                v-model.number="bonus.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Amount"
                                class="w-32 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                            <button
                                type="button"
                                @click="removeBonus(index)"
                                class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                            >
                                Remove
                            </button>
                        </div>
                        <button
                            type="button"
                            @click="addBonus"
                            class="px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                        >
                            + Add Bonus
                        </button>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Deductions Detail</label>
                        <div v-for="(deduction, index) in salaryForm.deductions_detail" :key="index" class="flex gap-2 mb-2">
                            <input
                                v-model="deduction.name"
                                type="text"
                                placeholder="Deduction name"
                                class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                            <input
                                v-model.number="deduction.amount"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="Amount"
                                class="w-32 px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                            <button
                                type="button"
                                @click="removeDeduction(index)"
                                class="px-3 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                            >
                                Remove
                            </button>
                        </div>
                        <button
                            type="button"
                            @click="addDeduction"
                            class="px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                        >
                            + Add Deduction
                        </button>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Notes</label>
                        <textarea
                            v-model="salaryForm.notes"
                            rows="3"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            placeholder="Additional notes..."
                        ></textarea>
                    </div>
                    <div class="bg-slate-50 p-3 rounded-lg">
                        <div class="text-sm text-slate-600">Net Salary:</div>
                        <div class="text-lg font-semibold text-slate-900">
                            {{ salaryForm.currency === 'PKR' ? '₨' : '£' }}{{ formatNumber(calculateNetSalary()) }}
                            <span class="text-sm text-slate-500 ml-2">({{ salaryForm.currency }})</span>
                        </div>
                    </div>
                    <div v-if="salaryError" class="text-sm text-red-600 bg-red-50 p-3 rounded">
                        {{ salaryError }}
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                        <button
                            type="button"
                            @click="closeSalaryForm"
                            class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="savingSalary"
                            class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                        >
                            {{ savingSalary ? 'Saving...' : (editingSalary ? 'Update' : 'Create') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
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

const checkedIn = ref(false);
const attendanceList = ref([]);
const attendancePagination = ref(null);
const attendanceFilter = ref({ user_id: '' });
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
