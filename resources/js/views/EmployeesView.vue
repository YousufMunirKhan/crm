<template>
    <ListingPageShell
        title="Employees"
        subtitle="Manage team members, roles, types, and activation — HR and directory data stay in sync."
        :badge="employeesBadge"
    >
        <template #actions>
            <button
                v-if="canAddEmployee"
                type="button"
                @click="openCreateForm"
                class="listing-btn-accent w-full sm:w-auto touch-manipulation"
            >
                + Add employee
            </button>
            <button type="button" @click="goToGoals" class="listing-btn-outline w-full sm:w-auto touch-manipulation">
                Set targets
            </button>
            <button
                v-if="canBulkResetPasswords"
                type="button"
                class="listing-btn-outline w-full sm:w-auto touch-manipulation border-amber-300 text-amber-900 hover:bg-amber-50"
                @click="openResetAllModal"
            >
                Reset all passwords
            </button>
        </template>

        <template #filters>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 lg:gap-4 items-end w-full min-w-0">
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="listing-label">Search</label>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Employee name or email..."
                        class="listing-input"
                        @input="handleSearch"
                    />
                </div>
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="listing-label">Role</label>
                    <select v-model="filters.role" class="listing-input" @change="loadEmployees(1)">
                        <option value="">All roles</option>
                        <option v-for="role in roles" :key="role.id" :value="role.name">
                            {{ role.name }}
                        </option>
                    </select>
                </div>
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="listing-label">Type</label>
                    <select v-model="filters.employee_type" class="listing-input" @change="loadEmployees(1)">
                        <option value="">All types</option>
                        <option value="field_worker">Field Worker</option>
                        <option value="call_center">Call Center</option>
                        <option value="ticket_manager">Ticket Manager</option>
                    </select>
                </div>
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="listing-label">Status</label>
                    <select v-model="filters.is_active" class="listing-input" @change="loadEmployees(1)">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap gap-2">
                    <button type="button" class="listing-btn-primary w-full sm:w-auto" @click="loadEmployees(1)">
                        Filter
                    </button>
                </div>
            </div>
        </template>

        <div class="hidden md:block overflow-x-auto">
            <table class="w-full min-w-[640px]">
                <thead class="listing-thead">
                    <tr>
                        <th class="listing-th">Name</th>
                        <th class="listing-th">Email</th>
                        <th class="listing-th">Role</th>
                        <th class="listing-th">Type</th>
                        <th class="listing-th">Status</th>
                        <th class="listing-th">Phone</th>
                        <th class="listing-th">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="employees.length === 0">
                        <td colspan="7" class="listing-td text-center text-slate-500 py-10">
                            No employees found
                        </td>
                    </tr>
                    <tr v-for="employee in employees" :key="employee.id" class="listing-row">
                        <td class="listing-td-strong">{{ employee.name }}</td>
                        <td class="listing-td">{{ employee.email }}</td>
                        <td class="listing-td">{{ employee.role?.name || '—' }}</td>
                        <td class="listing-td">
                            <span v-if="employee.employee_type" class="inline-flex rounded-md bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-800 ring-1 ring-sky-100">
                                {{ formatEmployeeType(employee.employee_type) }}
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="listing-td">
                            <span
                                v-if="employee.is_active === false || employee.is_active === 0"
                                class="listing-badge-inactive"
                            >
                                Inactive
                            </span>
                            <span v-else class="listing-badge-active">
                                Active
                            </span>
                        </td>
                        <td class="listing-td">{{ employee.phone || '—' }}</td>
                        <td class="listing-td">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <router-link :to="`/hr/employees/${employee.id}`" class="listing-link-edit">
                                    View
                                </router-link>
                                <router-link
                                    :to="{ name: 'report-employee-performance', query: { employee_id: employee.id } }"
                                    class="listing-link-edit"
                                >
                                    Performance
                                </router-link>
                                <button type="button" class="listing-link-edit" @click="openEditForm(employee)">
                                    Edit
                                </button>
                                <button
                                    v-if="employee.contract_pdf_path"
                                    type="button"
                                    class="listing-link-edit"
                                    @click="downloadContract(employee)"
                                >
                                    Contract
                                </button>
                                <button
                                    v-if="employee.email !== 'admin@switchsave.com' && (employee.is_active === false || employee.is_active === 0)"
                                    type="button"
                                    class="text-emerald-600 hover:text-emerald-800 font-medium text-sm"
                                    @click="toggleStatus(employee, true)"
                                >
                                    Activate
                                </button>
                                <button
                                    v-else-if="employee.email !== 'admin@switchsave.com'"
                                    type="button"
                                    class="listing-link-delete"
                                    @click="toggleStatus(employee, false)"
                                >
                                    Inactivate
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="md:hidden space-y-3 px-3 pb-3">
            <div
                v-for="employee in employees"
                :key="`mobile-${employee.id}`"
                class="rounded-xl border border-slate-200 bg-slate-50/40 p-4 space-y-2"
            >
                <div class="flex items-start justify-between gap-2">
                    <div class="text-sm font-semibold text-slate-900">{{ employee.name }}</div>
                    <span v-if="employee.is_active === false || employee.is_active === 0" class="listing-badge-inactive">Inactive</span>
                    <span v-else class="listing-badge-active">Active</span>
                </div>
                <div class="text-sm text-slate-600">{{ employee.email }}</div>
                <div class="text-sm text-slate-600">Role: {{ employee.role?.name || '—' }}</div>
                <div class="text-sm text-slate-600">Type: {{ employee.employee_type ? formatEmployeeType(employee.employee_type) : '—' }}</div>
                <div class="text-sm text-slate-600">Phone: {{ employee.phone || '—' }}</div>
                <div class="flex flex-wrap items-center gap-3 pt-1">
                    <router-link :to="`/hr/employees/${employee.id}`" class="listing-link-edit">View</router-link>
                    <router-link
                        :to="{ name: 'report-employee-performance', query: { employee_id: employee.id } }"
                        class="listing-link-edit"
                    >
                        Performance
                    </router-link>
                    <button type="button" class="listing-link-edit" @click="openEditForm(employee)">Edit</button>
                </div>
            </div>
        </div>

        <template #pagination>
            <Pagination
                v-if="pagination"
                :pagination="pagination"
                embedded
                result-label="employees"
                singular-label="employee"
                @page-change="handlePageChange"
            />
        </template>
    </ListingPageShell>

    <EmployeeForm
        v-if="showForm"
        :employee="selectedEmployee"
        @close="closeForm"
        @saved="handleSaved"
    />

    <div
        v-if="showResetAllModal"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="reset-all-passwords-title"
        @click.self="closeResetAllModal"
    >
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-5 sm:p-6 space-y-4 border border-slate-200">
            <h2 id="reset-all-passwords-title" class="text-lg font-semibold text-slate-900">Reset password for all users</h2>
            <p class="text-sm text-slate-600">
                Sets the <strong>same new password</strong> for every account except yours (you stay signed in). Optional: keep the protected admin login unchanged.
                All affected users are signed out on other devices and must log in again.
            </p>
            <div class="space-y-3">
                <div>
                    <label class="listing-label">New password (min 8 characters)</label>
                    <input v-model="resetAllForm.password" type="password" autocomplete="new-password" class="listing-input w-full" />
                </div>
                <div>
                    <label class="listing-label">Confirm password</label>
                    <input v-model="resetAllForm.password_confirmation" type="password" autocomplete="new-password" class="listing-input w-full" />
                </div>
                <div>
                    <label class="listing-label">Type <code class="text-xs bg-slate-100 px-1 rounded">RESET ALL</code> to confirm</label>
                    <input v-model="resetAllForm.confirm_phrase" type="text" class="listing-input w-full" placeholder="RESET ALL" autocomplete="off" />
                </div>
                <label class="flex items-start gap-2 text-sm text-slate-700 cursor-pointer">
                    <input v-model="resetAllForm.skip_protected_accounts" type="checkbox" class="mt-1 rounded border-slate-300" />
                    <span>Do not change <code class="text-xs bg-slate-100 px-1 rounded">admin@switchsave.com</code></span>
                </label>
            </div>
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-2">
                <button type="button" class="listing-btn-outline w-full sm:w-auto" :disabled="resetAllSubmitting" @click="closeResetAllModal">Cancel</button>
                <button
                    type="button"
                    class="w-full sm:w-auto px-4 py-2.5 rounded-lg text-sm font-medium bg-amber-600 text-white hover:bg-amber-700 disabled:opacity-50"
                    :disabled="resetAllSubmitting"
                    @click="submitResetAllPasswords"
                >
                    {{ resetAllSubmitting ? 'Working…' : 'Apply to all users' }}
                </button>
            </div>
        </div>
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
import ListingPageShell from '@/components/ListingPageShell.vue';

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

/** Bulk reset is limited to full admins (high impact). */
const canBulkResetPasswords = computed(() => {
    const userRole = auth.user?.role?.name;
    return userRole === 'Admin' || userRole === 'System Admin';
});

const showResetAllModal = ref(false);
const resetAllSubmitting = ref(false);
const resetAllForm = ref({
    password: '',
    password_confirmation: '',
    confirm_phrase: '',
    skip_protected_accounts: true,
});

const employeesBadge = computed(() =>
    pagination.value?.total != null ? `${pagination.value.total} Total` : null,
);

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

const openResetAllModal = () => {
    resetAllForm.value = {
        password: '',
        password_confirmation: '',
        confirm_phrase: '',
        skip_protected_accounts: true,
    };
    showResetAllModal.value = true;
};

const closeResetAllModal = () => {
    if (resetAllSubmitting.value) return;
    showResetAllModal.value = false;
};

const submitResetAllPasswords = async () => {
    if (resetAllForm.value.confirm_phrase !== 'RESET ALL') {
        toast.error('Type RESET ALL exactly to confirm.');
        return;
    }
    if (resetAllForm.value.password.length < 8) {
        toast.error('Password must be at least 8 characters.');
        return;
    }
    if (resetAllForm.value.password !== resetAllForm.value.password_confirmation) {
        toast.error('Passwords do not match.');
        return;
    }
    resetAllSubmitting.value = true;
    try {
        const { data } = await axios.post('/api/users/reset-all-passwords', {
            password: resetAllForm.value.password,
            password_confirmation: resetAllForm.value.password_confirmation,
            confirm_phrase: resetAllForm.value.confirm_phrase,
            skip_protected_accounts: resetAllForm.value.skip_protected_accounts,
        });
        toast.success(data?.message || 'Passwords updated.');
        showResetAllModal.value = false;
    } catch (error) {
        const msg =
            error.response?.data?.message ||
            (error.response?.data?.errors && Object.values(error.response.data.errors).flat().join(' ')) ||
            'Request failed.';
        toast.error(msg);
    } finally {
        resetAllSubmitting.value = false;
    }
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

