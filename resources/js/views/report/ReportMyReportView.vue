<template>
    <div class="max-w-4xl mx-auto p-4 md:p-6 space-y-6">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h1 class="text-2xl font-bold text-slate-900">My Report</h1>
            <div class="flex items-center gap-3">
                <label class="text-sm text-slate-600">Month</label>
                <select
                    v-model="selectedMonth"
                    @change="loadReport"
                    class="px-3 py-2 border border-slate-300 rounded-lg text-sm"
                >
                    <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <svg class="animate-spin h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <template v-else-if="self">
            <!-- Ranking badge -->
            <div
                v-if="self.rank"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-50 to-amber-100 border border-amber-200"
            >
                <span class="text-2xl font-bold text-amber-700">#{{ self.rank }}</span>
                <span class="text-sm text-amber-800">
                    out of {{ totalEmployeesWithTargets }} employee{{ totalEmployeesWithTargets !== 1 ? 's' : '' }} with targets
                </span>
            </div>

            <!-- Target vs Achievement cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="text-sm font-medium text-slate-500 mb-1">Appointments</div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-slate-900">{{ self.achieved_appointments }}</span>
                        <span class="text-slate-500">/ {{ self.target_appointments }}</span>
                    </div>
                    <div class="mt-2">
                        <div class="flex justify-between text-xs text-slate-500 mb-1">
                            <span>Progress</span>
                            <span>{{ self.appointment_progress }}%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div
                                class="h-2 rounded-full bg-emerald-500 transition-all"
                                :style="{ width: `${Math.min(self.appointment_progress || 0, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="text-sm font-medium text-slate-500 mb-1">Sales (Products Won)</div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-slate-900">{{ self.achieved_sales }}</span>
                        <span class="text-slate-500">/ {{ self.target_sales }}</span>
                    </div>
                    <div class="mt-2">
                        <div class="flex justify-between text-xs text-slate-500 mb-1">
                            <span>Progress</span>
                            <span>{{ self.sales_progress }}%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div
                                class="h-2 rounded-full bg-blue-500 transition-all"
                                :style="{ width: `${Math.min(self.sales_progress || 0, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5">
                    <div class="text-sm font-medium text-slate-500 mb-1">Revenue</div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-2xl font-bold text-slate-900">£{{ formatNumber(self.achieved_revenue || 0) }}</span>
                        <span class="text-slate-500">/ £{{ formatNumber(self.target_revenue || 0) }}</span>
                    </div>
                    <div class="mt-2">
                        <div class="flex justify-between text-xs text-slate-500 mb-1">
                            <span>Progress</span>
                            <span>{{ self.revenue_progress }}%</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-2">
                            <div
                                class="h-2 rounded-full bg-indigo-500 transition-all"
                                :style="{ width: `${Math.min(self.revenue_progress || 0, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="!self.rank && (self.target_appointments || self.target_sales || self.target_revenue)" class="text-sm text-slate-500">
                No ranking yet — you may not be in the Sales/CallAgent role or targets were set recently.
            </div>
            <div v-if="!self.target_appointments && !self.target_sales && !self.target_revenue" class="text-sm text-slate-500 bg-slate-50 rounded-lg p-4">
                No targets set for this month. Ask your admin to set targets in Employee Goals.
            </div>
        </template>

        <div v-else class="text-center py-12 text-slate-500">
            Unable to load your report.
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const selectedMonth = ref(new Date().toISOString().slice(0, 7));
const loading = ref(false);
const self = ref(null);
const totalEmployeesWithTargets = ref(0);

const monthOptions = (() => {
    const opts = [];
    const now = new Date();
    for (let i = 0; i < 12; i++) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        opts.push({
            value: d.toISOString().slice(0, 7),
            label: d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }),
        });
    }
    return opts;
})();

const formatNumber = (n) => new Intl.NumberFormat('en-GB').format(n);

const loadReport = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/reporting/employee-self-report', {
            params: { month: selectedMonth.value },
        });
        self.value = res.data.self ?? null;
        totalEmployeesWithTargets.value = res.data.total_employees_with_targets ?? 0;
    } catch (e) {
        console.error('Failed to load report:', e);
        self.value = null;
    } finally {
        loading.value = false;
    }
};

onMounted(loadReport);
</script>
