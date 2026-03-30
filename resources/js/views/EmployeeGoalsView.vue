<template>
    <div class="w-full min-w-0 max-w-7xl mx-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Employee Goals</h1>
                <p class="text-sm text-slate-500 mt-1">
                    Set monthly targets for appointments and sales, and see what each employee achieved.
                </p>
            </div>
            <div class="flex flex-wrap gap-3 items-stretch sm:items-center w-full sm:w-auto">
                <input
                    v-model="selectedMonth"
                    type="month"
                    class="w-full sm:w-auto min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                    @change="loadData"
                />
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-hidden min-w-0">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[800px]">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wide">Employee</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wide">Appointments (Target / Achieved)</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wide">Sales (Target / Achieved)</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wide">Revenue (Target / Won)</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wide">Progress</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr v-if="loading">
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                Loading...
                            </td>
                        </tr>
                        <tr v-else-if="rows.length === 0">
                            <td colspan="6" class="px-6 py-8 text-center text-slate-500">
                                No employees found for this month.
                            </td>
                        </tr>
                        <tr
                            v-for="row in rows"
                            :key="row.user_id"
                            class="hover:bg-slate-50"
                        >
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium text-slate-900">
                                    {{ row.user?.name || 'Employee' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ row.user?.role?.name || '—' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">
                                {{ row.target_appointments }} / {{ row.achieved_appointments }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">
                                {{ row.target_sales }} / {{ row.achieved_sales }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-700">
                                £{{ formatNumber(row.target_revenue) }} / £{{ formatNumber(row.achieved_revenue) }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <div class="w-40">
                                    <div class="flex justify-between text-xs text-slate-500 mb-1">
                                        <span>{{ row.appointment_progress }}%</span>
                                        <span>Appointments</span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                                        <div
                                            class="h-2 rounded-full bg-gradient-to-r from-emerald-500 to-emerald-600"
                                            :style="{ width: `${row.appointment_progress}%` }"
                                        ></div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm">
                                <button
                                    class="px-3 py-1 text-xs rounded-lg bg-slate-900 text-white hover:bg-slate-800"
                                    @click="openEdit(row)"
                                >
                                    Set / Edit Goals
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Edit goals modal -->
        <div
            v-if="editing"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md p-6 space-y-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-slate-900">
                        Set Goals – {{ editing.user?.name || 'Employee' }}
                    </h2>
                    <button
                        class="text-slate-400 hover:text-slate-600"
                        @click="closeEdit"
                    >
                        ✕
                    </button>
                </div>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Month</label>
                        <input
                            v-model="editForm.month"
                            type="month"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                            disabled
                        />
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Target appointments</label>
                            <input
                                v-model.number="editForm.target_appointments"
                                type="number"
                                min="0"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Target sales</label>
                            <input
                                v-model.number="editForm.target_sales"
                                type="number"
                                min="0"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Target revenue (£)</label>
                        <input
                            v-model.number="editForm.target_revenue"
                            type="number"
                            min="0"
                            step="0.01"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button
                        class="px-4 py-2 rounded-lg border border-slate-300 text-sm text-slate-700 hover:bg-slate-50"
                        @click="closeEdit"
                    >
                        Cancel
                    </button>
                    <button
                        class="px-4 py-2 rounded-lg bg-slate-900 text-sm text-white hover:bg-slate-800 disabled:opacity-50"
                        :disabled="saving"
                        @click="saveGoals"
                    >
                        {{ saving ? 'Saving...' : 'Save goals' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useRoute } from 'vue-router';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const toast = useToastStore();

const loading = ref(false);
const saving = ref(false);
const rows = ref([]);
const selectedMonth = ref('');
const editing = ref(null);
const editForm = ref({
    month: '',
    target_appointments: 0,
    target_sales: 0,
    target_revenue: 0,
});

const selectedEmployeeId = computed(() => {
    // Optional ?employee_id= or coming from /hr/employees/:id/goals in future
    return route.query.employee_id ? Number(route.query.employee_id) : null;
});

const formatNumber = (num) => {
    const n = Number(num || 0);
    return new Intl.NumberFormat('en-GB', { maximumFractionDigits: 2 }).format(n);
};

const getDefaultMonth = () => {
    const d = new Date();
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};

const loadData = async () => {
    const month = selectedMonth.value || getDefaultMonth();
    selectedMonth.value = month;
    loading.value = true;
    try {
        const [targetsRes, agentsRes] = await Promise.all([
            axios.get('/api/hr/employee-targets', { params: { month } }),
            axios.get('/api/reporting/agents', {
                params: {
                    month,
                    agent_id: selectedEmployeeId.value || undefined,
                },
            }),
        ]);

        const targetsRaw = targetsRes.data?.data || [];
        const agents = agentsRes.data || [];

        const byUser = {};
        for (const t of targetsRaw) {
            byUser[t.user_id] = {
                user_id: t.user_id,
                user: t.user,
                target_appointments: t.target_appointments || 0,
                target_sales: t.target_sales || 0,
                target_revenue: t.target_revenue || 0,
                achieved_appointments: 0,
                achieved_sales: 0,
                achieved_revenue: 0,
            };
        }

        for (const ag of agents) {
            if (selectedEmployeeId.value && ag.id !== selectedEmployeeId.value) continue;
            const existing =
                byUser[ag.id] ||
                {
                    user_id: ag.id,
                    user: { id: ag.id, name: ag.name, role: ag.role || null },
                    target_appointments: 0,
                    target_sales: 0,
                    target_revenue: 0,
                    achieved_appointments: 0,
                    achieved_sales: 0,
                    achieved_revenue: 0,
                };
            existing.achieved_appointments = ag.appointments_count || 0;
            // Sales = individual products marked WON
            existing.achieved_sales = ag.won_products || ag.won_count || 0;
            existing.achieved_revenue = ag.revenue || 0;
            byUser[ag.id] = existing;
        }

        const list = Object.values(byUser).map((t) => {
            const denom = t.target_appointments || 0;
            const progress =
                denom > 0 ? Math.min(100, Math.round((t.achieved_appointments / denom) * 100)) : 0;
            return {
                ...t,
                appointment_progress: progress,
            };
        });

        rows.value = list.sort((a, b) => (b.achieved_appointments || 0) - (a.achieved_appointments || 0));
    } catch (error) {
        console.error('Failed to load employee goals:', error);
        toast.error('Failed to load employee goals');
        rows.value = [];
    } finally {
        loading.value = false;
    }
};

const openEdit = (row) => {
    editing.value = row;
    editForm.value = {
        month: selectedMonth.value || getDefaultMonth(),
        target_appointments: row.target_appointments || 0,
        target_sales: row.target_sales || 0,
        target_revenue: row.target_revenue || 0,
    };
};

const closeEdit = () => {
    editing.value = null;
};

const saveGoals = async () => {
    if (!editing.value) return;
    saving.value = true;
    try {
        await axios.put(`/api/hr/employee-targets/${editing.value.user_id}`, {
            month: editForm.value.month,
            target_appointments: editForm.value.target_appointments || 0,
            target_sales: editForm.value.target_sales || 0,
            target_revenue: editForm.value.target_revenue || 0,
        });
        toast.success('Goals saved');
        closeEdit();
        await loadData();
    } catch (error) {
        console.error('Failed to save goals:', error);
        toast.error(error.response?.data?.message || 'Failed to save goals');
    } finally {
        saving.value = false;
    }
};

onMounted(() => {
    if (!selectedMonth.value) {
        selectedMonth.value = getDefaultMonth();
    }
    loadData();
});
</script>

