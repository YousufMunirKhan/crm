<template>
    <div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        <div class="flex flex-col gap-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-900">Target vs Achievement</h1>
                    <p class="text-sm text-slate-600 mt-1">Track monthly targets and performance by employee.</p>
                </div>
            </div>

            <!-- Summary card -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 sm:p-5">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div
                            class="w-16 h-16 rounded-full grid place-items-center"
                            :style="ringStyle(overallPercent)"
                            aria-label="Overall progress"
                        >
                            <div class="w-12 h-12 bg-white rounded-full grid place-items-center text-lg font-bold text-slate-900">
                                {{ overallPercent }}%
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-x-6 gap-y-2">
                            <div>
                                <div class="text-sm text-slate-500">Total Target</div>
                                <div class="text-lg font-semibold text-slate-900">{{ totalTarget }}</div>
                            </div>
                            <div>
                                <div class="text-sm text-slate-500">Total Achieved</div>
                                <div class="text-lg font-semibold text-slate-900">{{ totalAchieved }}</div>
                            </div>
                            <div class="col-span-2">
                                <div class="text-sm text-slate-500">Overall</div>
                                <div class="text-sm font-semibold text-slate-900">{{ overallPercent }}%</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="text-sm text-slate-600">Month</span>
                        <div class="inline-flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-2 py-1.5">
                            <button
                                type="button"
                                class="w-8 h-8 rounded-lg hover:bg-white border border-transparent hover:border-slate-200 text-slate-600"
                                @click="shiftMonth(-1)"
                                title="Previous month"
                            >
                                ‹
                            </button>
                            <select
                                v-model="selectedMonth"
                                @change="loadData"
                                class="bg-transparent text-sm font-medium text-slate-800 focus:outline-none"
                            >
                                <option v-for="m in monthOptions" :key="m.value" :value="m.value">{{ m.label }}</option>
                            </select>
                            <button
                                type="button"
                                class="w-8 h-8 rounded-lg hover:bg-white border border-transparent hover:border-slate-200 text-slate-600"
                                @click="shiftMonth(1)"
                                title="Next month"
                                :disabled="selectedMonth === monthOptions[0]?.value"
                            >
                                ›
                            </button>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                        <div
                            class="h-2.5 rounded-full bg-gradient-to-r from-blue-600 via-emerald-500 to-emerald-500"
                            :style="{ width: `${overallPercent}%` }"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div v-if="loading" class="flex justify-center py-12">
            <svg class="animate-spin h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
        </div>

        <div v-else-if="data.length === 0" class="bg-white rounded-xl shadow-sm border border-slate-200 p-12 text-center text-slate-500">
            No employees with targets for this month. Set targets in
            <router-link to="/employees/goals" class="text-blue-600 hover:underline">Employee Goals</router-link>.
        </div>

        <div v-else class="space-y-6">
            <!-- Appointments board -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-4 sm:px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Appointments</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Ranked by appointment achievement.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px]">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700">Rank</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700">Employee</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700">Appointments</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700">Progress</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="(row, idx) in appointmentsRows" :key="row.employee_id" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <span :class="rankClass(idx + 1)" class="inline-flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold">
                                        {{ idx + 1 }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-slate-200 text-slate-600 grid place-items-center text-xs font-bold">
                                            {{ initials(row.employee_name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-sm font-semibold text-slate-900 truncate">{{ row.employee_name }}</div>
                                            <div v-if="idx === 0" class="inline-flex items-center gap-2 mt-1">
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-medium">Top Performer</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700 font-medium">
                                    {{ row.achieved_appointments }} / {{ row.target_appointments || 0 }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-2 rounded-full bg-gradient-to-r from-blue-600 to-emerald-500" :style="{ width: `${clampPct(row.appointment_progress)}%` }" />
                                        </div>
                                        <div class="text-sm font-semibold text-slate-700 w-14 text-right">{{ clampPct(row.appointment_progress) }}%</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Sales + Revenue board -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="px-4 sm:px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-slate-900">Sales & Revenue</h2>
                        <p class="text-xs text-slate-500 mt-0.5">Ranked by overall achievement.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px]">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700">Rank</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700">Employee</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700">Sales</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700">Revenue</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-slate-700">Overall</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="row in data" :key="row.employee_id" class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <span :class="rankClass(row.rank)" class="inline-flex items-center justify-center w-9 h-9 rounded-full text-sm font-bold">
                                        {{ row.rank }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ row.employee_name }}</td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-slate-700">{{ row.achieved_sales }} / {{ row.target_sales || 0 }}</div>
                                    <div class="flex items-center gap-2 mt-2">
                                        <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-2 rounded-full bg-gradient-to-r from-blue-600 to-amber-400" :style="{ width: `${clampPct(row.sales_progress)}%` }" />
                                        </div>
                                        <div class="text-xs font-semibold text-slate-600 w-12 text-right">{{ clampPct(row.sales_progress) }}%</div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-sm font-medium text-slate-700">£{{ formatNumber(row.achieved_revenue) }} / £{{ formatNumber(row.target_revenue) }}</div>
                                    <div class="flex items-center gap-2 mt-2">
                                        <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-2 rounded-full bg-gradient-to-r from-emerald-500 to-emerald-600" :style="{ width: `${clampPct(row.revenue_progress)}%` }" />
                                        </div>
                                        <div class="text-xs font-semibold text-slate-600 w-12 text-right">{{ clampPct(row.revenue_progress) }}%</div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <div class="text-sm font-bold" :class="overallClass(row.overall_progress)">{{ clampPct(row.overall_progress) }}%</div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';

const formatMonth = (date) => {
    const d = date instanceof Date ? date : new Date(date);
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    return `${y}-${m}`;
};

const selectedMonth = ref(formatMonth(new Date()));
const loading = ref(false);
const data = ref([]);

const monthOptions = (() => {
    const opts = [];
    const now = new Date();
    for (let i = 0; i < 12; i++) {
        const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
        opts.push({
            value: formatMonth(d),
            label: d.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' }),
        });
    }
    return opts;
})();

const formatNumber = (n) => new Intl.NumberFormat('en-GB').format(n);

const clampPct = (n) => {
    const x = Number(n ?? 0);
    if (!Number.isFinite(x)) return 0;
    return Math.max(0, Math.min(100, Math.round(x)));
};

const totalTarget = computed(() => data.value.reduce((sum, r) => sum + (Number(r.target_appointments) || 0), 0));
const totalAchieved = computed(() => data.value.reduce((sum, r) => sum + (Number(r.achieved_appointments) || 0), 0));
const overallPercent = computed(() => {
    const t = totalTarget.value;
    const a = totalAchieved.value;
    if (t <= 0) return 0;
    return clampPct((a / t) * 100);
});

const appointmentsRows = computed(() => {
    return [...data.value].sort((a, b) => (Number(b.appointment_progress) || 0) - (Number(a.appointment_progress) || 0));
});

function initials(name) {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    const a = (parts[0] || '').slice(0, 1).toUpperCase();
    const b = (parts[1] || '').slice(0, 1).toUpperCase();
    return (a + b) || '—';
}

function rankClass(rank) {
    if (rank === 1) return 'bg-amber-100 text-amber-800';
    if (rank === 2) return 'bg-slate-200 text-slate-700';
    if (rank === 3) return 'bg-amber-100/70 text-amber-700';
    return 'bg-slate-100 text-slate-600';
}

function overallClass(pct) {
    const p = Number(pct ?? 0);
    if (p >= 100) return 'text-emerald-600';
    if (p >= 50) return 'text-amber-600';
    return 'text-slate-700';
}

function ringStyle(pct) {
    const p = clampPct(pct);
    return {
        background: `conic-gradient(#2563eb ${p * 3.6}deg, #e2e8f0 0deg)`,
    };
}

function shiftMonth(delta) {
    const [y, m] = String(selectedMonth.value).split('-').map(Number);
    if (!y || !m) return;
    const d = new Date(y, m - 1, 1);
    d.setMonth(d.getMonth() + delta);
    selectedMonth.value = formatMonth(d);
    loadData();
}

const loadData = async () => {
    loading.value = true;
    try {
        const res = await axios.get('/api/reporting/target-vs-achievement', {
            params: { month: selectedMonth.value },
        });
        data.value = res.data.data || [];
    } catch (e) {
        console.error('Failed to load data:', e);
        data.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(loadData);
</script>
