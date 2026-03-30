<template>
    <div class="w-full min-w-0 max-w-7xl mx-auto p-3 sm:p-4 lg:p-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
            <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Employees</h1>
            <router-link
                to="/employees"
                class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition text-sm lg:text-base text-center"
            >
                + Add Employee
            </router-link>
        </div>

        <!-- Search and Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
            <div class="flex flex-col sm:flex-row gap-3 sm:gap-4">
                <div class="flex-1">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search employees..."
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 text-base"
                        @input="debouncedSearch"
                    />
                </div>
                <select
                    v-model="roleFilter"
                    @change="loadEmployees"
                    class="w-full sm:w-auto px-4 py-2.5 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 text-base"
                >
                    <option value="">All Roles</option>
                    <option value="Admin">Admin</option>
                    <option value="Manager">Manager</option>
                    <option value="Sales">Sales</option>
                    <option value="CallAgent">Call Agent</option>
                    <option value="System Admin">System Admin</option>
                </select>
            </div>
        </div>

        <!-- Employee Cards -->
        <div v-if="loading" class="text-center py-12">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900"></div>
            <p class="mt-2 text-slate-600">Loading employees...</p>
        </div>

        <div v-else-if="employees.length === 0" class="bg-white rounded-xl shadow-sm p-12 text-center">
            <p class="text-slate-500 text-lg">No employees found</p>
            <router-link
                to="/employees"
                class="mt-4 inline-block px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition"
            >
                Add Your First Employee
            </router-link>
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div
                v-for="employee in employees"
                :key="employee.id"
                @click="viewEmployee(employee.id)"
                class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition cursor-pointer border border-slate-200 hover:border-slate-300"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between mb-4 min-w-0">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold text-slate-900 break-words">{{ employee.name }}</h3>
                        <p class="text-sm text-slate-500 mt-1">{{ employee.role?.name || 'No Role' }}</p>
                    </div>
                    <span
                        class="px-2 py-1 text-xs font-medium rounded shrink-0 self-start"
                        :class="getRoleBadgeClass(employee.role?.name)"
                    >
                        {{ employee.role?.name || 'N/A' }}
                    </span>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex items-center text-slate-600">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span>{{ employee.email || 'No email' }}</span>
                    </div>
                    <div v-if="employee.phone" class="flex items-center text-slate-600">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        <span>{{ employee.phone }}</span>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-200">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-500">This Month</span>
                        <span class="font-medium text-slate-900">{{ employee.attendance_stats?.this_month || 0 }} days</span>
                    </div>
                    <div class="flex justify-between items-center text-sm mt-2">
                        <span class="text-slate-500">This Year</span>
                        <span class="font-medium text-slate-900">{{ employee.attendance_stats?.this_year || 0 }} days</span>
                    </div>
                </div>
            </div>
        </div>

        <Pagination
            v-if="pagination && pagination.last_page > 1"
            :pagination="pagination"
            @page-change="loadEmployees"
        />
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import Pagination from '@/components/Pagination.vue';

const router = useRouter();

const employees = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const roleFilter = ref('');
const pagination = ref(null);
let searchTimeout = null;

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

const debouncedSearch = () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        loadEmployees(1);
    }, 300);
};

const loadEmployees = async (page = 1) => {
    loading.value = true;
    try {
        const params = {
            per_page: 12,
            page,
        };
        
        if (searchQuery.value) {
            params.search = searchQuery.value;
        }
        
        if (roleFilter.value) {
            params.role = roleFilter.value;
        }

        const { data } = await axios.get('/api/hr/employees', { params });
        
        // Fetch attendance stats for each employee
        const employeesWithStats = await Promise.all(
            (data.data || data || []).map(async (employee) => {
                try {
                    const statsResponse = await axios.get(`/api/hr/employees/${employee.id}/attendance-stats`);
                    return {
                        ...employee,
                        attendance_stats: statsResponse.data,
                    };
                } catch (error) {
                    return {
                        ...employee,
                        attendance_stats: { this_month: 0, this_year: 0 },
                    };
                }
            })
        );
        
        employees.value = employeesWithStats;
        
        if (data.current_page) {
            pagination.value = {
                current_page: data.current_page,
                last_page: data.last_page,
                per_page: data.per_page || 12,
                total: data.total || 0,
            };
        } else {
            pagination.value = null;
        }
    } catch (error) {
        console.error('Failed to load employees:', error);
    } finally {
        loading.value = false;
    }
};

const viewEmployee = (employeeId) => {
    router.push(`/hr/employees/${employeeId}`);
};

onMounted(() => {
    loadEmployees();
});
</script>

