<template>
    <div class="max-w-7xl mx-auto p-4 lg:p-6">
        <div class="flex items-center justify-between gap-3 lg:gap-4 mb-4 lg:mb-6">
            <div class="flex items-center gap-3 lg:gap-4">
                <button
                    @click="$router.back()"
                    class="p-2 hover:bg-slate-100 rounded-lg transition"
                >
                    <svg class="w-5 h-5 lg:w-6 lg:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Employee Details</h1>
            </div>
            <button
                v-if="employee"
                @click="goToEdit"
                class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800"
            >
                Edit Employee
            </button>
        </div>

        <div v-if="loading" class="text-center py-12">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900"></div>
            <p class="mt-2 text-slate-600">Loading employee details...</p>
        </div>

        <div v-else-if="employee" class="space-y-6">
            <!-- Employee Info Card -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-start justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-semibold text-slate-900">{{ employee.name }}</h2>
                        <p class="text-slate-500 mt-1">{{ employee.role?.name || 'No Role' }}</p>
                    </div>
                    <span
                        class="px-3 py-1 text-sm font-medium rounded"
                        :class="getRoleBadgeClass(employee.role?.name)"
                    >
                        {{ employee.role?.name || 'N/A' }}
                    </span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-sm text-slate-500">Email</label>
                        <p class="text-slate-900 font-medium">{{ employee.email || 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">Phone</label>
                        <p class="text-slate-900 font-medium">{{ employee.phone || 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">Employee ID</label>
                        <p class="text-slate-900 font-medium">EMP{{ String(employee.id).padStart(3, '0') }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">Joined Date</label>
                        <p class="text-slate-900 font-medium">
                            {{ employee.created_at ? formatDate(employee.created_at) : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">Date of Birth</label>
                        <p class="text-slate-900 font-medium">
                            {{ employee.date_of_birth ? formatDate(employee.date_of_birth) : 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <label class="text-sm text-slate-500">Status</label>
                        <p class="text-slate-900 font-medium">
                            <span
                                v-if="employee.is_active === false || employee.is_active === 0"
                                class="px-2 py-1 rounded-full bg-red-100 text-red-700 text-xs font-medium"
                            >
                                Inactive
                            </span>
                            <span
                                v-else
                                class="px-2 py-1 rounded-full bg-green-100 text-green-700 text-xs font-medium"
                            >
                                Active
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Bank details -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <h3 class="text-base font-semibold text-slate-900 mb-4">Bank details</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <label class="text-xs text-slate-500">Account holder name</label>
                        <p class="text-slate-900 font-medium">
                            {{ employee.bank_account_name || 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Bank name</label>
                        <p class="text-slate-900 font-medium">
                            {{ employee.bank_name || 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Sort code</label>
                        <p class="text-slate-900 font-medium">
                            {{ employee.bank_sort_code || 'N/A' }}
                        </p>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">Account number</label>
                        <p class="text-slate-900 font-medium">
                            {{ employee.bank_account_number || 'N/A' }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- Documents -->
            <div class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-base font-semibold text-slate-900">Documents</h3>
                </div>
                <form
                    class="grid grid-cols-1 md:grid-cols-[2fr,2fr,auto] gap-3 mb-4"
                    @submit.prevent="uploadDocument"
                >
                    <input
                        v-model="newDocName"
                        type="text"
                        placeholder="Document name (e.g. Passport, Contract)"
                        class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                    <input
                        ref="fileInput"
                        type="file"
                        class="text-sm"
                    />
                    <button
                        type="submit"
                        :disabled="uploading"
                        class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                    >
                        {{ uploading ? 'Uploading...' : 'Add' }}
                    </button>
                </form>
                <div v-if="documents.length === 0" class="text-sm text-slate-500">
                    No documents uploaded yet.
                </div>
                <div v-else class="space-y-2">
                    <div
                        v-for="doc in documents"
                        :key="doc.id"
                        class="flex items-center justify-between text-sm border border-slate-200 rounded-lg px-3 py-2"
                    >
                        <div>
                            <div class="font-medium text-slate-900">{{ doc.name }}</div>
                            <div class="text-xs text-slate-500">
                                Added {{ formatDate(doc.created_at) }}
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a
                                :href="doc.file_path"
                                target="_blank"
                                rel="noopener"
                                class="text-blue-600 hover:underline text-xs"
                            >
                                View
                            </a>
                            <button
                                class="text-red-600 hover:underline text-xs"
                                @click="deleteDocument(doc)"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Attendance Statistics -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 lg:gap-6">
                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-medium text-slate-500">This Month</h3>
                        <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <p class="text-3xl font-bold text-slate-900">{{ stats.this_month || 0 }}</p>
                    <p class="text-sm text-slate-500 mt-1">Days Present</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-medium text-slate-500">This Year</h3>
                        <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                    <p class="text-3xl font-bold text-slate-900">{{ stats.this_year || 0 }}</p>
                    <p class="text-sm text-slate-500 mt-1">Days Present</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm p-6">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-medium text-slate-500">Total Hours</h3>
                        <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <p class="text-3xl font-bold text-slate-900">{{ formatHours(stats.total_hours || 0) }}</p>
                    <p class="text-sm text-slate-500 mt-1">This Month</p>
                </div>
            </div>

            <!-- Attendance Records -->
            <div class="bg-white rounded-xl shadow-sm p-4 lg:p-6">
                <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3 mb-4">
                    <h3 class="text-lg font-semibold text-slate-900">Attendance Records</h3>
                    <div class="flex flex-col sm:flex-row gap-2">
                        <select
                            v-model="monthFilter"
                            @change="loadAttendance"
                            class="w-full sm:w-auto px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="">All Months</option>
                            <option v-for="month in recentMonths" :key="month.value" :value="month.value">
                                {{ month.label }}
                            </option>
                        </select>
                        <button
                            @click="exportAttendance"
                            class="w-full sm:w-auto px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700"
                        >
                            Export CSV
                        </button>
                    </div>
                </div>

                <div v-if="attendanceList.length === 0" class="text-center py-8 text-slate-400 text-sm">
                    No attendance records found
                </div>
                
                <template v-else>
                    <!-- Mobile Card View -->
                    <div class="lg:hidden space-y-3">
                        <div v-for="attendance in attendanceList" :key="attendance.id" class="p-4 bg-slate-50 rounded-lg">
                            <div class="flex justify-between items-start mb-2">
                                <div class="font-medium text-slate-900">{{ formatDate(attendance.date) }}</div>
                                <span
                                    class="px-2 py-1 text-xs font-medium rounded"
                                    :class="attendance.check_out_at ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'"
                                >
                                    {{ attendance.check_out_at ? 'Completed' : 'In Progress' }}
                                </span>
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
                            </div>
                        </div>
                    </div>

                    <!-- Desktop Table View -->
                    <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Check In</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Check Out</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Work Hours</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-200">
                            <tr v-for="attendance in attendanceList" :key="attendance.id" class="hover:bg-slate-50">
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-900">
                                    {{ formatDate(attendance.date) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600">
                                    {{ formatTime(attendance.check_in_at) }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-600">
                                    {{ attendance.check_out_at ? formatTime(attendance.check_out_at) : '-' }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-sm text-slate-900 font-medium">
                                    {{ parseFloat(attendance.work_hours || 0).toFixed(2) }}h
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span
                                        class="px-2 py-1 text-xs font-medium rounded"
                                        :class="attendance.check_out_at ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'"
                                    >
                                        {{ attendance.check_out_at ? 'Completed' : 'In Progress' }}
                                    </span>
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
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
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
const loading = ref(false);
const monthFilter = ref('');
const documents = ref([]);
const newDocName = ref('');
const uploading = ref(false);
const fileInput = ref(null);

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

const getRoleBadgeClass = (roleName) => {
    const classes = {
        'Admin': 'bg-purple-100 text-purple-700',
        'Manager': 'bg-blue-100 text-blue-700',
        'Sales': 'bg-green-100 text-green-700',
        'CallAgent': 'bg-yellow-100 text-yellow-700',
        'System Admin': 'bg-red-100 text-red-700',
    };
    return classes[roleName] || 'bg-slate-100 text-slate-700';
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
    if (!newDocName.value || !fileInput.value?.files?.length) {
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

const deleteDocument = async (doc) => {
    if (!window.confirm(`Delete document "${doc.name}"?`)) return;
    try {
        await axios.delete(`/api/hr/employees/${route.params.id}/documents/${doc.id}`);
        await loadDocuments();
    } catch (e) {
        console.error('Failed to delete document', e);
        toast.error('Failed to delete document');
    }
};

const goToEdit = () => {
    router.push({ name: 'employee-edit', params: { id: route.params.id } });
};

onMounted(() => {
    loadEmployee();
    loadStats();
    loadAttendance();
    loadDocuments();
});
</script>

