<template>
    <ListingPageShell
        title="Salary slips"
        subtitle="Filter by employee, month, and currency — download PDFs or email slips in one place."
        :badge="slipsBadge"
    >
        <template #actions>
            <router-link to="/salaries" class="listing-btn-accent w-full sm:w-auto text-center touch-manipulation">
                + Create salary slip
            </router-link>
        </template>

        <template #filters>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4 items-end">
                <div>
                    <label class="listing-label">Employee</label>
                    <select v-model="filters.user_id" class="listing-input" @change="loadSalaries(1)">
                        <option value="">All employees</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">
                            {{ user.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="listing-label">Month</label>
                    <input v-model="filters.month" type="month" class="listing-input" @change="loadSalaries(1)" />
                </div>
                <div>
                    <label class="listing-label">Currency</label>
                    <select v-model="filters.currency" class="listing-input" @change="loadSalaries(1)">
                        <option value="">All currencies</option>
                        <option value="GBP">GBP (£)</option>
                        <option value="PKR">PKR (Rs)</option>
                    </select>
                </div>
                <button type="button" class="listing-btn-outline w-full" @click="clearFilters">Clear filters</button>
            </div>
        </template>

        <div v-if="loading" class="px-5 py-14 text-center text-slate-500 text-sm">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-2 border-slate-200 border-t-blue-600"></div>
            <p class="mt-2">Loading salary slips…</p>
        </div>

        <div v-else-if="salaries.length === 0" class="px-5 py-12 text-center text-slate-500 text-sm">
            <p class="text-lg text-slate-700 mb-4">No salary slips found</p>
            <router-link to="/salaries" class="listing-btn-accent inline-flex touch-manipulation">Create your first salary slip</router-link>
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

                <div class="hidden lg:block overflow-x-auto">
                    <table class="w-full min-w-[900px]">
                        <thead class="listing-thead">
                            <tr>
                                <th class="listing-th">Employee</th>
                                <th class="listing-th">Month</th>
                                <th class="listing-th">Base salary</th>
                                <th class="listing-th">Net salary</th>
                                <th class="listing-th">Currency</th>
                                <th class="listing-th">Attendance days</th>
                                <th class="listing-th">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="salary in salaries" :key="salary.id" class="listing-row">
                                <td class="listing-td-strong whitespace-nowrap">
                                    <div>{{ salary.user?.name }}</div>
                                    <div class="text-xs font-normal text-slate-500">{{ salary.user?.role?.name || 'N/A' }}</div>
                                </td>
                                <td class="listing-td whitespace-nowrap">{{ formatMonth(salary.month) }}</td>
                                <td class="listing-td whitespace-nowrap">
                                    {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.base_salary) }}
                                </td>
                                <td class="listing-td whitespace-nowrap font-semibold text-slate-900">
                                    {{ getCurrencySymbol(salary.currency) }}{{ formatNumber(salary.net_salary) }}
                                </td>
                                <td class="listing-td whitespace-nowrap">{{ salary.currency }}</td>
                                <td class="listing-td whitespace-nowrap">{{ salary.attendance_days || '—' }} days</td>
                                <td class="listing-td whitespace-nowrap">
                                    <div class="flex flex-wrap gap-x-3 gap-y-1">
                                        <router-link :to="`/salaries/${salary.id}/edit`" class="listing-link-edit">Edit</router-link>
                                        <button type="button" class="listing-link-edit" @click="downloadSlip(salary.id)">Download</button>
                                        <button type="button" class="text-purple-600 hover:text-purple-800 text-sm font-medium" @click="sendEmail(salary.id)">
                                            Email
                                        </button>
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

