<template>
    <ListingPageShell
        title="My report"
        subtitle="Your targets vs achievements for the month — same metrics as the team leaderboard."
        :badge="myReportBadge"
    >
        <template #filters>
            <div>
                <label class="listing-label">Month</label>
                <select v-model="selectedMonth" class="listing-input w-full sm:w-56" @change="loadReport">
                    <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                </select>
            </div>
        </template>

        <div v-if="loading" class="px-5 py-14 flex justify-center text-slate-500 text-sm">
            <svg class="animate-spin h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <div v-else-if="self" class="px-4 sm:px-5 pb-5 space-y-4">
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

            <div
                v-if="self.sales_target_lines?.length"
                class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden"
            >
                <div class="px-5 py-3 border-b border-slate-100">
                    <h2 class="text-sm font-semibold text-slate-900">Sales targets by product & category</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Won line items in the month — category rows include every product in that category.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-medium text-slate-600">
                            <tr>
                                <th class="px-4 py-2">Target</th>
                                <th class="px-4 py-2">Achieved</th>
                                <th class="px-4 py-2">Target qty</th>
                                <th class="px-4 py-2">Progress</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(ln, idx) in self.sales_target_lines" :key="idx">
                                <td class="px-4 py-2 text-slate-900">{{ ln.label }}</td>
                                <td class="px-4 py-2 font-medium">{{ ln.achieved_quantity }}</td>
                                <td class="px-4 py-2 text-slate-600">{{ ln.target_quantity }}</td>
                                <td class="px-4 py-2">
                                    <span class="text-slate-600">{{ lineProgress(ln) }}%</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-if="!self.rank && (self.target_appointments || self.target_sales || self.target_revenue)" class="text-sm text-slate-500">
                No ranking yet — you may not be in the Sales/CallAgent role or targets were set recently.
            </div>
            <div v-if="!self.target_appointments && !self.target_sales && !self.target_revenue" class="text-sm text-slate-500 bg-slate-50 rounded-lg p-4">
                No targets set for this month. Ask your admin to use Set targets (Employees).
            </div>
        </div>

        <div v-else class="px-5 py-12 text-center text-slate-500 text-sm">Unable to load your report.</div>
    </ListingPageShell>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import ListingPageShell from '@/components/ListingPageShell.vue';

const selectedMonth = ref(new Date().toISOString().slice(0, 7));
const loading = ref(false);
const self = ref(null);
const totalEmployeesWithTargets = ref(0);

const myReportBadge = computed(() => {
    if (loading.value || !self.value) return null;
    if (self.value.rank != null) return `Rank #${self.value.rank}`;
    return totalEmployeesWithTargets.value ? `${totalEmployeesWithTargets.value} in leaderboard` : null;
});

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

const lineProgress = (ln) => {
    const t = Number(ln.target_quantity || 0);
    const a = Number(ln.achieved_quantity || 0);
    if (t <= 0) return a > 0 ? 100 : 0;
    return Math.min(100, Math.round((a / t) * 100));
};

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
