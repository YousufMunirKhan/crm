<template>
    <ListingPageShell
        title="Salary slips"
        subtitle="Filter by employee, month, and currency — download PDFs or email slips in one place."
        :badge="slipsBadge"
    >
        <template #actions>
            <BaseButton variant="primary" to="/salaries" block-mobile>
                <template #icon>
                    <PlusIcon class="icon" aria-hidden="true" />
                </template>
                Create salary slip
            </BaseButton>
        </template>

        <template #filters>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4 items-end">
                <div>
                    <label class="form-label" for="salaryslipsview-employee">Employee</label>
                    <select id="salaryslipsview-employee" v-model="filters.user_id" class="form-select" @change="loadSalaries(1)">
                        <option value="">All employees</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="salaryslipsview-month">Month</label>
                    <input id="salaryslipsview-month" v-model="filters.month" type="month" class="form-input" @change="loadSalaries(1)" />
                </div>
                <div>
                    <label class="form-label" for="salaryslipsview-currency">Currency</label>
                    <select id="salaryslipsview-currency" v-model="filters.currency" class="form-select" @change="loadSalaries(1)">
                        <option value="">All currencies</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="PKR">PKR (Rs)</option>
                    </select>
                </div>
                <BaseButton variant="outline" type="button" class="w-full" @click="clearFilters">Clear filters</BaseButton>
            </div>
        </template>

        <div v-if="loading" class="px-5 py-8 space-y-3" aria-busy="true">
            <p class="sr-only">Loading salary slips…</p>
            <div class="skeleton-text w-1/3"></div>
            <div class="skeleton-text w-2/3"></div>
            <div class="skeleton-text w-1/2"></div>
            <div class="skeleton-text w-3/4"></div>
        </div>

        <EmptyState
            v-else-if="salaries.length === 0"
            heading="No salary slips found"
            description="No salary slips match the current filters. Create one to get started."
        >
            <template #icon>
                <BanknotesIcon class="icon" aria-hidden="true" />
            </template>
            <template #action>
                <BaseButton variant="soft" to="/salaries">Create your first salary slip</BaseButton>
            </template>
        </EmptyState>

        <template v-else>
            <!-- Mobile Card View -->
            <div class="lg:hidden p-4 space-y-3">
                <div v-for="salary in salaries" :key="salary.id" class="table-card">
                    <div class="flex justify-between items-start gap-3">
                        <div class="min-w-0">
                            <div class="text-xs text-slate-500">Employee</div>
                            <div class="font-semibold text-slate-900">{{ salary.user?.name }}</div>
                            <div class="text-xs text-slate-500">{{ salary.user?.role?.name || 'N/A' }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="text-xs text-slate-500">Net salary</div>
                            <div class="font-semibold text-slate-900 tabular-nums">
                                {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.net_salary) }}
                            </div>
                            <div class="text-xs text-slate-500">{{ salary.currency }}</div>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div>
                            <span class="text-slate-500">Month:</span>
                            <span class="ml-2 text-slate-900">{{ formatMonth(salary.month) }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Base:</span>
                            <span class="ml-2 text-slate-900 tabular-nums">{{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.base_salary) }}</span>
                        </div>
                        <div>
                            <span class="text-slate-500">Days:</span>
                            <span class="ml-2 text-slate-900 tabular-nums">{{ salary.attendance_days || '-' }}</span>
                        </div>
                    </div>
                    <div class="flex gap-2 pt-2 border-t border-slate-200">
                        <BaseButton variant="outline" class="flex-1" :to="`/salaries/${salary.id}/edit`">Edit</BaseButton>
                        <BaseButton variant="outline" type="button" class="flex-1" @click="downloadSlip(salary.id)">Download</BaseButton>
                        <BaseButton variant="soft" type="button" class="flex-1" @click="sendEmail(salary.id)">Email</BaseButton>
                    </div>
                </div>
            </div>

            <div class="hidden lg:block table-wrap">
                <table class="table min-w-[900px]">
                    <caption class="sr-only">Salary slips with employee, month, base and net salary, currency, attendance days and available actions</caption>
                    <thead class="table-thead">
                        <tr>
                            <th scope="col" class="table-th">Employee</th>
                            <th scope="col" class="table-th">Month</th>
                            <th scope="col" class="table-th-num">Base salary</th>
                            <th scope="col" class="table-th-num">Net salary</th>
                            <th scope="col" class="table-th">Currency</th>
                            <th scope="col" class="table-th-num">Attendance days</th>
                            <th scope="col" class="table-th">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="salary in salaries" :key="salary.id" class="table-row">
                            <td class="table-td-strong whitespace-nowrap">
                                <div>{{ salary.user?.name }}</div>
                                <div class="text-xs font-normal text-slate-500">{{ salary.user?.role?.name || 'N/A' }}</div>
                            </td>
                            <td class="table-td whitespace-nowrap">{{ formatMonth(salary.month) }}</td>
                            <td class="table-td-num whitespace-nowrap">
                                {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.base_salary) }}
                            </td>
                            <td class="table-td-num whitespace-nowrap font-semibold text-slate-900">
                                {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.net_salary) }}
                            </td>
                            <td class="table-td whitespace-nowrap">{{ salary.currency }}</td>
                            <td class="table-td-num whitespace-nowrap">{{ salary.attendance_days || '—' }} days</td>
                            <td class="table-td-actions">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <BaseButton variant="ghost" size="sm" :to="`/salaries/${salary.id}/edit`">Edit</BaseButton>
                                    <BaseButton variant="ghost" size="sm" type="button" @click="downloadSlip(salary.id)">Download</BaseButton>
                                    <BaseButton variant="ghost" size="sm" type="button" @click="sendEmail(salary.id)">Email</BaseButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <template #pagination>
            <Pagination
                v-if="pagination"
                :pagination="pagination"
                embedded
                result-label="records"
                singular-label="record"
                @page-change="loadSalaries"
            />
        </template>
    </ListingPageShell>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { useToastStore } from '@/stores/toast';
import { BaseButton, EmptyState } from '@/components/base';
import { BanknotesIcon, PlusIcon } from '@heroicons/vue/24/outline';

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

const slipsBadge = computed(() => {
    if (loading.value || !pagination.value?.total) return null;
    const t = pagination.value.total;
    return `${t} ${t === 1 ? 'slip' : 'slips'}`;
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
