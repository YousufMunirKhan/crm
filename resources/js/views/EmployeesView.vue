<template>
    <div class="w-full min-w-0 max-w-7xl mx-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 min-w-0">
            <h1 class="text-2xl font-bold text-slate-900">Employees</h1>
            <div class="flex flex-wrap gap-3 w-full sm:w-auto min-w-0">
                <select
                    v-model="filters.role"
                    @change="loadEmployees"
                    class="w-full sm:w-auto min-w-0 px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                >
                    <option value="">All Roles</option>
                    <option v-for="role in roles" :key="role.id" :value="role.name">
                        {{ role.name }}
                    </option>
                </select>
                <select
                    v-model="filters.employee_type"
                    @change="loadEmployees"
                    class="w-full sm:w-auto min-w-0 px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                >
                    <option value="">All Types</option>
                    <option value="field_worker">Field Worker</option>
                    <option value="call_center">Call Center</option>
                    <option value="ticket_manager">Ticket Manager</option>
                </select>
                <select
                    v-model="filters.is_active"
                    @change="loadEmployees"
                    class="w-full sm:w-auto min-w-0 px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                >
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                    <option value="all">All</option>
                </select>
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search employees..."
                    class="w-full min-w-0 flex-1 basis-full sm:basis-auto sm:min-w-[12rem] px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    @input="handleSearch"
                />
                <button
                    v-if="canAddEmployee"
                    @click="openCreateForm"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 touch-manipulation flex-1 sm:flex-initial min-w-[8rem] text-center"
                >
                    + Add Employee
                </button>
                <button
                    @click="goToGoals"
                    class="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg hover:bg-slate-50 touch-manipulation flex-1 sm:flex-initial min-w-[8rem] text-center"
                >
                    View Goals
                </button>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden min-w-0">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[600px]">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Name</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Email</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Role</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Type</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Status</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Phone</th>
                            <th class="px-4 md:px-6 py-3 text-left text-xs font-medium text-slate-700 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-if="employees.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                No employees found
                            </td>
                        </tr>
                        <tr v-for="employee in employees" :key="employee.id" class="hover:bg-slate-50">
                            <td class="px-4 md:px-6 py-4 text-sm text-slate-900">{{ employee.name }}</td>
                            <td class="px-4 md:px-6 py-4 text-sm text-slate-600">{{ employee.email }}</td>
                            <td class="px-4 md:px-6 py-4 text-sm text-slate-600">{{ employee.role?.name || '-' }}</td>
                            <td class="px-4 md:px-6 py-4 text-sm text-slate-600">
                                <span v-if="employee.employee_type" class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs">
                                    {{ formatEmployeeType(employee.employee_type) }}
                                </span>
                                <span v-else class="text-slate-400">-</span>
                            </td>
                            <td class="px-4 md:px-6 py-4 text-sm">
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
                            </td>
                            <td class="px-4 md:px-6 py-4 text-sm text-slate-600">{{ employee.phone || '-' }}</td>
                            <td class="px-4 md:px-6 py-4 text-sm">
                                <div class="flex flex-wrap gap-2">
                                    <router-link
                                        :to="`/hr/employees/${employee.id}`"
                                        class="text-blue-600 hover:underline text-xs md:text-sm"
                                    >
                                        View
                                    </router-link>
                                    <button
                                        @click="openEditForm(employee)"
                                        class="text-green-600 hover:underline text-xs md:text-sm"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        v-if="employee.contract_pdf_path"
                                        @click="downloadContract(employee)"
                                        class="text-blue-600 hover:underline text-xs md:text-sm"
                                    >
                                        Contract
                                    </button>
                                    <button
                                        v-if="employee.email !== 'admin@switchsave.com' && (employee.is_active === false || employee.is_active === 0)"
                                        @click="toggleStatus(employee, true)"
                                        class="text-green-600 hover:underline text-xs md:text-sm"
                                    >
                                        Activate
                                    </button>
                                    <button
                                        v-else-if="employee.email !== 'admin@switchsave.com'"
                                        @click="toggleStatus(employee, false)"
                                        class="text-red-600 hover:underline text-xs md:text-sm"
                                    >
                                        Inactivate
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Pagination
            v-if="pagination"
            :pagination="pagination"
            @page-change="handlePageChange"
        />

        <EmployeeForm
            v-if="showForm"
            :employee="selectedEmployee"
            @close="closeForm"
            @saved="handleSaved"
        />

        <DeleteConfirm
            v-if="showDeleteConfirm"
            :item="selectedEmployee"
            item-name="employee"
            @confirm="handleDelete"
            @cancel="closeDeleteConfirm"
        />
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { useToastStore } from '@/stores/toast';
import EmployeeForm from '@/components/EmployeeForm.vue';
import Pagination from '@/components/Pagination.vue';

const auth = useAuthStore();
const router = useRouter();
const toast = useToastStore();

const employees = ref([]);
const roles = ref([]);
const search = ref('');
const filters = ref({
    role: '',
    employee_type: '',
    is_active: '1',
});
const pagination = ref(null);
const showForm = ref(false);
const selectedEmployee = ref(null);

// Check if user can add employees (Admin or Manager)
const canAddEmployee = computed(() => {
    const userRole = auth.user?.role?.name;
    return userRole === 'Admin' || userRole === 'Manager';
});

const formatEmployeeType = (type) => {
    const types = {
        field_worker: 'Field Worker',
        call_center: 'Call Center',
        ticket_manager: 'Ticket Manager',
    };
    return types[type] || type;
};

const loadEmployees = async (page = 1) => {
    try {
        const params = {
            page,
            per_page: 15,
        };

        if (filters.value.role) {
            params.role = filters.value.role;
        }

        if (filters.value.employee_type) {
            params.employee_type = filters.value.employee_type;
        }

        if (filters.value.is_active) {
            params.is_active = filters.value.is_active;
        }

        if (search.value) {
            params.search = search.value;
        }

        const response = await axios.get('/api/users', { params });
        
        // Handle both paginated and non-paginated responses
        if (response.data.data) {
            // Paginated response
            employees.value = response.data.data;
            pagination.value = {
                current_page: response.data.current_page,
                last_page: response.data.last_page,
                per_page: response.data.per_page,
                total: response.data.total,
            };
        } else if (Array.isArray(response.data)) {
            // Non-paginated response (array)
            employees.value = response.data;
            pagination.value = null;
        } else {
            employees.value = [];
            pagination.value = null;
        }
    } catch (error) {
        console.error('Failed to load employees:', error);
        employees.value = [];
        pagination.value = null;
    }
};

const loadRoles = async () => {
    try {
        const response = await axios.get('/api/roles');
        roles.value = response.data;
    } catch (error) {
        console.error('Failed to load roles:', error);
    }
};

let searchTimeout = null;
const handleSearch = () => {
    // Debounce search
    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
    searchTimeout = setTimeout(() => {
        loadEmployees(1); // Reset to first page on search
    }, 500);
};

const handlePageChange = (page) => {
    loadEmployees(page);
};

const openCreateForm = () => {
    selectedEmployee.value = null;
    showForm.value = true;
};

const openEditForm = (employee) => {
    router.push({ name: 'employee-edit', params: { id: employee.id } });
};

const closeForm = () => {
    showForm.value = false;
    selectedEmployee.value = null;
};

const handleSaved = () => {
    loadEmployees(pagination.value?.current_page || 1);
};

const toggleStatus = async (employee, makeActive) => {
    const actionText = makeActive ? 'activate' : 'inactivate';
    const confirmMessage = makeActive
        ? `Do you want to activate ${employee.name}?`
        : `Do you want to inactivate ${employee.name}?`;

    if (!window.confirm(confirmMessage)) {
        return;
    }

    try {
        await axios.put(`/api/users/${employee.id}`, { is_active: makeActive });
        await loadEmployees(pagination.value?.current_page || 1);
        toast.success(`Employee ${actionText}d successfully.`);
    } catch (error) {
        console.error('Failed to update employee status:', error);
        let errorMessage = `Failed to ${actionText} employee. Please try again.`;
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        }
        toast.error(errorMessage);
    }
};

const downloadContract = (employee) => {
    if (employee.contract_pdf_path) {
        window.open(`/storage/${employee.contract_pdf_path}`, '_blank');
    }
};

const goToGoals = () => {
    router.push({ name: 'employee-goals' });
};

onMounted(async () => {
    // Ensure auth is initialized
    if (!auth.initialized) {
        await auth.bootstrap();
    }
    loadEmployees();
    loadRoles();
});
</script>

