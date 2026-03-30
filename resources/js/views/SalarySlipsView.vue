<template>
    <div class="w-full min-w-0 max-w-7xl mx-auto p-3 sm:p-4 lg:p-6 space-y-4 lg:space-y-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <div>
                <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Salary Slips</h1>
                <p class="text-xs lg:text-sm text-slate-600 mt-1">View and manage all employee salary slips</p>
            </div>
            <router-link
                to="/salaries"
                class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition text-sm lg:text-base text-center touch-manipulation w-full sm:w-auto"
            >
                + Create Salary Slip
            </router-link>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-3 sm:p-4 min-w-0">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Employee</label>
                    <select
                        v-model="filters.user_id"
                        @change="loadSalaries(1)"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    >
                        <option value="">All Employees</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Month</label>
                    <input
                        v-model="filters.month"
                        type="month"
                        @change="loadSalaries(1)"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Currency</label>
                    <select
                        v-model="filters.currency"
                        @change="loadSalaries(1)"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    >
                        <option value="">All Currencies</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="PKR">PKR (Rs)</option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button
                        @click="clearFilters"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700"
                    >
                        Clear Filters
                    </button>
                </div>
            </div>
        </div>

        <!-- Salary Slips Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden">
            <div v-if="loading" class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900"></div>
                <p class="mt-2 text-slate-600">Loading salary slips...</p>
            </div>

            <div v-else-if="salaries.length === 0" class="text-center py-12">
                <p class="text-slate-500 text-lg">No salary slips found</p>
                <router-link
                    to="/salaries"
                    class="mt-4 inline-block px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition"
                >
                    Create Your First Salary Slip
                </router-link>
            </div>

            <template v-else>
                <!-- Mobile Card View -->
                <div class="lg:hidden divide-y divide-slate-200">
                <div v-for="salary in salaries" :key="salary.id" class="p-4">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <div class="font-semibold text-slate-900">{{ salary.user?.name }}</div>
                            <div class="text-xs text-slate-500">{{ salary.user?.role?.name || 'N/A' }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-semibold text-slate-900">
                                {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.net_salary) }}
                            </div>
                            <div class="text-xs text-slate-500">{{ salary.currency }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm mb-3">
                        <div>
                            <span class="text-slate-500">Month:</span>
                            <span class="ml-2 text-slate-900">{{ formatMonth(salary.month) }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Base:</span>
                            <span class="ml-2 text-slate-900">{{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.base_salary) }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Days:</span>
                            <span class="ml-2 text-slate-900">{{ salary.attendance_days || '-' }}</span>
                        </div>
                    </div>
                    <div class="flex gap-2 pt-2 border-t border-slate-200">
                        <router-link
                            :to="`/salaries/${salary.id}/edit`"
                            class="flex-1 px-3 py-2 text-center text-sm bg-blue-50 text-blue-600 rounded-lg hover:bg-blue-100"
                        >
                            Edit
                        </router-link>
                        <button
                            @click="downloadSlip(salary.id)"
                            class="flex-1 px-3 py-2 text-sm bg-green-50 text-green-600 rounded-lg hover:bg-green-100"
                        >
                            Download
                        </button>
                        <button
                            @click="sendEmail(salary.id)"
                            class="flex-1 px-3 py-2 text-sm bg-purple-50 text-purple-600 rounded-lg hover:bg-purple-100"
                        >
                            Email
                        </button>
                    </div>
                </div>
                </div>

                <!-- Desktop Table View -->
                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Employee</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Month</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Base Salary</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Net Salary</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Currency</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Attendance Days</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-for="salary in salaries" :key="salary.id" class="hover:bg-slate-50">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-slate-900">{{ salary.user?.name }}</div>
                                <div class="text-xs text-slate-500">{{ salary.user?.role?.name || 'N/A' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                                {{ formatMonth(salary.month) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                                {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.base_salary) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-semibold text-slate-900">
                                    {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.net_salary) }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                {{ salary.currency }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600">
                                {{ salary.attendance_days || '-' }} days
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex gap-2">
                                    <router-link
                                        :to="`/salaries/${salary.id}/edit`"
                                        class="text-blue-600 hover:text-blue-800 hover:underline"
                                    >
                                        Edit
                                    </router-link>
                                    <button
                                        @click="downloadSlip(salary.id)"
                                        class="text-green-600 hover:text-green-800 hover:underline"
                                    >
                                        Download
                                    </button>
                                    <button
                                        @click="sendEmail(salary.id)"
                                        class="text-purple-600 hover:text-purple-800 hover:underline"
                                    >
                                        Email
                                    </button>
                                </div>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <Pagination
            v-if="pagination && pagination.last_page > 1"
            :pagination="pagination"
            @page-change="loadSalaries"
        />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import Pagination from '@/components/Pagination.vue';
import { useToastStore } from '@/stores/toast';

const router = useRouter();
const toast = useToastStore();

const salaries = ref([]);
const users = ref([]);
const loading = ref(false);
const pagination = ref(null);

const filters = ref({
    user_id: '',
    month: '',
    currency: '',
});

const formatNumber = (num) => {
    return new Intl.NumberFormat('en-GB').format(num || 0);
};

const formatMonth = (month) => {
    if (!month) return '-';
    const [year, monthNum] = month.split('-');
    const date = new Date(year, monthNum - 1);
    return date.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });
};

const getCurrencySymbol = (currency) => {
    return currency === 'PKR' ? 'Rs ' : '£';
};

const loadSalaries = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            per_page: 15,
            page,
        };

        if (filters.value.user_id) {
            params.user_id = filters.value.user_id;
        }
        if (filters.value.month) {
            params.month = filters.value.month;
        }
        if (filters.value.currency) {
            params.currency = filters.value.currency;
        }

        const { data } = await axios.get('/api/hr/salaries', { params });
        salaries.value = data.data || [];
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page || 15,
            total: data.total || 0,
        };
    } catch (error) {
        console.error('Failed to load salaries:', error);
        toast.error('Failed to load salary slips');
    } finally {
        loading.value = false;
    }
};

const loadUsers = async () => {
    try {
        const { data } = await axios.get('/api/users', { params: { per_page: 1000 } });
        users.value = data.data || data || [];
    } catch (error) {
        console.error('Failed to load users:', error);
    }
};

const clearFilters = () => {
    filters.value = {
        user_id: '',
        month: '',
        currency: '',
    };
    loadSalaries(1);
};

const downloadSlip = async (id) => {
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
        window.URL.revokeObjectURL(url);
        toast.success('Salary slip downloaded!');
    } catch (error) {
        console.error('Failed to download salary slip:', error);
        toast.error('Failed to download salary slip');
    }
};

const sendEmail = async (id) => {
    try {
        await axios.post(`/api/hr/salaries/${id}/send-email`);
        toast.success('Salary slip sent via email!');
    } catch (error) {
        console.error('Failed to send email:', error);
        toast.error(error.response?.data?.error || 'Failed to send email');
    }
};

onMounted(() => {
    loadSalaries();
    loadUsers();
});
</script>

