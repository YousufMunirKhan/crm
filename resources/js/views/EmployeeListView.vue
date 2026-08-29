<template>
    <ListingPageShell
        title="Employees"
        subtitle="Directory with attendance snapshot — open a card for full HR profile."
        :badge="listBadge"
    >
        <template #actions>
            <BaseButton variant="primary" to="/employees" block-mobile>
                <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                Add employee
            </BaseButton>
        </template>

        <template #filters>
            <div class="listing-filters-row">
                <div class="flex-1 min-w-0">
                    <label class="listing-label" for="employeelistview-search">Search</label>
                    <input id="employeelistview-search"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search employees…"
                        class="form-input"
                        @input="debouncedSearch"
                    />
                </div>
                <div class="w-full sm:w-48">
                    <label class="listing-label" for="employeelistview-role">Role</label>
                    <select id="employeelistview-role" v-model="roleFilter" class="form-select" @change="loadEmployees">
                        <option value="">All roles</option>
                        <option value="Admin">Admin</option>
                        <option value="Manager">Manager</option>
                        <option value="Sales">Sales</option>
                        <option value="CallAgent">Call Agent</option>
                        <option value="System Admin">System Admin</option>
                    </select>
                </div>
            </div>
        </template>

        <div
            v-if="loading"
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 px-3 py-6 sm:px-5"
            aria-busy="true"
        >
            <div v-for="n in 6" :key="`sk-${n}`" class="table-card">
                <span class="skeleton-text block w-2/3" />
                <span class="skeleton-text block w-1/2" />
                <span class="skeleton-text block w-full" />
            </div>
        </div>

        <EmptyState
            v-else-if="employees.length === 0"
            heading="No employees found"
            description="Adjust the search or role filter, or add your first employee."
        >
            <template #icon><UsersIcon class="icon" aria-hidden="true" /></template>
            <template #action>
                <BaseButton variant="primary" to="/employees">Add your first employee</BaseButton>
            </template>
        </EmptyState>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 px-3 pb-1 sm:px-5">
            <div
                v-for="employee in employees"
                :key="employee.id"
                class="rounded-card border border-slate-200 bg-slate-50/40 p-5 hover:border-slate-300 transition cursor-pointer shadow-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                role="button"
                tabindex="0"
                @click="viewEmployee(employee.id)"
                @keydown.enter.prevent="viewEmployee(employee.id)"
                @keydown.space.prevent="viewEmployee(employee.id)"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between mb-4 min-w-0">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold text-slate-900 break-words">{{ employee.name }}</h3>
                        <p class="text-sm text-slate-500 mt-1">{{ employee.role?.name || 'No role' }}</p>
                    </div>
                    <BaseBadge :tone="getRoleTone(employee.role?.name)" class="shrink-0 self-start">
                        {{ employee.role?.name || 'N/A' }}
                    </BaseBadge>
                </div>

                <div class="space-y-2 text-sm">
                    <div class="flex items-center text-slate-600">
                        <EnvelopeIcon class="icon-sm mr-2" aria-hidden="true" />
                        <span class="truncate">{{ employee.email || 'No email' }}</span>
                    </div>
                    <div v-if="employee.phone" class="flex items-center text-slate-600">
                        <PhoneIcon class="icon-sm mr-2" aria-hidden="true" />
                        <span>{{ employee.phone }}</span>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-200/80">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-slate-500">This month</span>
                        <span class="font-medium text-slate-900">{{ employee.attendance_stats?.this_month || 0 }} days</span>
                    </div>
                    <div class="flex justify-between items-center text-sm mt-2">
                        <span class="text-slate-500">This year</span>
                        <span class="font-medium text-slate-900">{{ employee.attendance_stats?.this_year || 0 }} days</span>
                    </div>
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
                @page-change="loadEmployees"
            />
        </template>
    </ListingPageShell>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { EnvelopeIcon, PhoneIcon, PlusIcon, UsersIcon } from '@heroicons/vue/24/outline';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseBadge, BaseButton, EmptyState } from '@/components/base';

const router = useRouter();

const employees = ref([]);
const loading = ref(false);
const searchQuery = ref('');
const roleFilter = ref('');
const pagination = ref(null);
let searchTimeout = null;

const listBadge = computed(() => {
    if (loading.value || !pagination.value?.total) return null;
    const t = pagination.value.total;
    return `${t} ${t === 1 ? 'employee' : 'employees'}`;
});

const getRoleTone = (roleName) => {
    const tones = {
        Admin: 'primary',
        Manager: 'primary',
        Sales: 'success',
        CallAgent: 'warning',
        'System Admin': 'danger',
    };
    return tones[roleName] || 'neutral';
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
            }),
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
