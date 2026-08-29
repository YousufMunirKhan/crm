<template>
    <ListingPageShell
        title="Employees"
        subtitle="Manage team members, roles, types, and activation — HR and directory data stay in sync."
        :badge="employeesBadge"
    >
        <template #actions>
            <BaseButton v-if="canAddEmployee" variant="primary" block-mobile @click="openCreateForm">
                <template #icon><PlusIcon class="icon" aria-hidden="true" /></template>
                Add employee
            </BaseButton>
            <BaseButton variant="outline" block-mobile @click="goToGoals">
                Set targets
            </BaseButton>
            <BaseButton
                v-if="canBulkResetPasswords"
                variant="outline"
                block-mobile
                class="border-warning-300 text-warning-800 hover:bg-warning-50"
                @click="openResetAllModal"
            >
                <template #icon><KeyIcon class="icon" aria-hidden="true" /></template>
                Reset all passwords
            </BaseButton>
        </template>

        <template #filters>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 lg:gap-4 items-end w-full min-w-0">
                <div class="sm:col-span-2 lg:col-span-3">
                    <label class="listing-label" for="employeesview-search">Search</label>
                    <input id="employeesview-search"
                        v-model="search"
                        type="text"
                        placeholder="Employee name or email..."
                        class="listing-input"
                        @input="handleSearch"
                    />
                </div>
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="listing-label" for="employeesview-role">Role</label>
                    <select id="employeesview-role" v-model="filters.role" class="listing-input" @change="loadEmployees(1)">
                        <option value="">All roles</option>
                        <option v-for="role in roles" :key="role.id" :value="role.name">
                            {{ role.name }}
                        </option>
                    </select>
                </div>
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="listing-label" for="employeesview-type">Type</label>
                    <select id="employeesview-type" v-model="filters.employee_type" class="listing-input" @change="loadEmployees(1)">
                        <option value="">All types</option>
                        <option value="field_worker">Field Worker</option>
                        <option value="call_center">Call Center</option>
                        <option value="ticket_manager">Ticket Manager</option>
                    </select>
                </div>
                <div class="sm:col-span-1 lg:col-span-2">
                    <label class="listing-label" for="employeesview-status">Status</label>
                    <select id="employeesview-status" v-model="filters.is_active" class="listing-input" @change="loadEmployees(1)">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                        <option value="all">All</option>
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-3 flex flex-wrap gap-2">
                    <BaseButton variant="soft" block-mobile @click="loadEmployees(1)">
                        <template #icon><FunnelIcon class="icon" aria-hidden="true" /></template>
                        Filter
                    </BaseButton>
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
                            <BaseBadge v-if="employee.employee_type" tone="primary">
                                {{ formatEmployeeType(employee.employee_type) }}
                            </BaseBadge>
                            <span v-else class="text-slate-500">—</span>
                        </td>
                        <td class="listing-td">
                            <BaseBadge v-if="employee.is_active === false || employee.is_active === 0" tone="danger" dot>
                                Inactive
                            </BaseBadge>
                            <BaseBadge v-else tone="success" dot>
                                Active
                            </BaseBadge>
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
                                    class="text-success-700 hover:text-success-800 font-medium text-sm rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                    @click="askToggleStatus(employee, true)"
                                >
                                    Activate
                                </button>
                                <button
                                    v-else-if="employee.email !== 'admin@switchsave.com'"
                                    type="button"
                                    class="listing-link-delete rounded-control focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                    @click="askToggleStatus(employee, false)"
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
                    <BaseBadge v-if="employee.is_active === false || employee.is_active === 0" tone="danger" dot>Inactive</BaseBadge>
                    <BaseBadge v-else tone="success" dot>Active</BaseBadge>
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

    <BaseModal
        v-model="showResetAllModal"
        title="Reset password for all users"
        size="sm"
        :dismissible="!resetAllSubmitting"
        :close-on-backdrop="false"
    >
        <form id="reset-all-passwords-form" class="space-y-4" novalidate @submit.prevent="submitResetAllPasswords">
            <p class="text-sm text-slate-600">
                Sets the <strong>same new password</strong> for every account except yours (you stay signed in). Optional: keep the protected admin login unchanged.
                All affected users are signed out on other devices and must log in again.
            </p>
            <div class="space-y-3">
                <div>
                    <label class="form-label" for="employeesview-new-password-min-8-characters">New password (min 8 characters)</label>
                    <input id="employeesview-new-password-min-8-characters" v-model="resetAllForm.password" type="password" autocomplete="new-password" class="form-input" />
                </div>
                <div>
                    <label class="form-label" for="employeesview-confirm-password">Confirm password</label>
                    <input id="employeesview-confirm-password" v-model="resetAllForm.password_confirmation" type="password" autocomplete="new-password" class="form-input" />
                </div>
                <div>
                    <label class="form-label" for="employeesview-type-reset-all-to-confirm">Type <code class="kbd">RESET ALL</code> to confirm</label>
                    <input id="employeesview-type-reset-all-to-confirm" v-model="resetAllForm.confirm_phrase" type="text" class="form-input" placeholder="RESET ALL" autocomplete="off" />
                </div>
                <label class="form-choice">
                    <input v-model="resetAllForm.skip_protected_accounts" type="checkbox" class="form-checkbox" />
                    <span>Do not change <code class="kbd">admin@switchsave.com</code></span>
                </label>
            </div>
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile :disabled="resetAllSubmitting" @click="closeResetAllModal">
                Cancel
            </BaseButton>
            <BaseButton
                variant="danger"
                type="submit"
                form="reset-all-passwords-form"
                block-mobile
                :loading="resetAllSubmitting"
            >
                {{ resetAllSubmitting ? 'Working…' : 'Apply to all users' }}
            </BaseButton>
        </template>
    </BaseModal>

    <ConfirmDialog
        v-model="showStatusConfirm"
        :title="statusConfirmTitle"
        :message="statusConfirmMessage"
        :confirm-label="statusTarget?.makeActive ? 'Activate' : 'Inactivate'"
        :tone="statusTarget?.makeActive ? 'primary' : 'danger'"
        :loading="statusSubmitting"
        @confirm="toggleStatus"
        @cancel="closeStatusConfirm"
    />
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
import { BaseBadge, BaseButton, BaseModal, ConfirmDialog } from '@/components/base';
import { FunnelIcon, KeyIcon, PlusIcon } from '@heroicons/vue/24/outline';

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

/** Activate / inactivate confirmation (replaces window.confirm). */
const showStatusConfirm = ref(false);
const statusSubmitting = ref(false);
const statusTarget = ref(null);

const statusConfirmTitle = computed(() =>
    statusTarget.value?.makeActive ? 'Activate employee' : 'Inactivate employee',
);

const statusConfirmMessage = computed(() => {
    if (!statusTarget.value) return '';
    return statusTarget.value.makeActive
        ? `Do you want to activate ${statusTarget.value.employee.name}?`
        : `Do you want to inactivate ${statusTarget.value.employee.name}?`;
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

const askToggleStatus = (employee, makeActive) => {
    statusTarget.value = { employee, makeActive };
    showStatusConfirm.value = true;
};

const closeStatusConfirm = () => {
    if (statusSubmitting.value) return;
    showStatusConfirm.value = false;
    statusTarget.value = null;
};

const toggleStatus = async () => {
    if (!statusTarget.value || statusSubmitting.value) return;
    const { employee, makeActive } = statusTarget.value;
    const actionText = makeActive ? 'activate' : 'inactivate';

    statusSubmitting.value = true;
    try {
        await axios.put(`/api/users/${employee.id}`, { is_active: makeActive });
        showStatusConfirm.value = false;
        statusTarget.value = null;
        await loadEmployees(pagination.value?.current_page || 1);
        toast.success(`Employee ${actionText}d successfully.`);
    } catch (error) {
        console.error('Failed to update employee status:', error);
        let errorMessage = `Failed to ${actionText} employee. Please try again.`;
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        }
        toast.error(errorMessage);
    } finally {
        statusSubmitting.value = false;
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

