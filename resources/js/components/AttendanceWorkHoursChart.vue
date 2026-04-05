<script setup>
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    BarElement,
    CategoryScale,
    LinearScale,
} from 'chart.js';

ChartJS.register(Title, Tooltip, Legend, BarElement, CategoryScale, LinearScale);

const props = defineProps({
    payload: { type: Object, default: null },
});

const DATASET_COLORS = [
    '#6366f1',
    '#22c55e',
    '#f59e0b',
    '#ef4444',
    '#8b5cf6',
    '#06b6d4',
    '#ec4899',
    '#84cc16',
    '#64748b',
    '#14b8a6',
    '#f97316',
    '#0ea5e9',
];

const chartData = computed(() => {
    const p = props.payload;
    if (!p?.users?.length) {
        return { labels: [], datasets: [] };
    }
    return {
        labels: p.label_display || [],
        datasets: p.users.map((u, i) => ({
            label: u.name,
            data: (u.hours || []).map((h) => (h === null || h === undefined ? null : Number(h))),
            backgroundColor: DATASET_COLORS[i % DATASET_COLORS.length],
            borderRadius: 4,
            maxBarThickness: 28,
        })),
    };
});

const chartOptions = computed(() => {
    const users = props.payload?.users || [];
    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: {
                position: 'bottom',
                labels: { boxWidth: 10, padding: 12, font: { size: 11 } },
            },
            tooltip: {
                callbacks: {
                    label(ctx) {
                        const u = users[ctx.datasetIndex];
                        const d = u?.details?.[ctx.dataIndex];
                        const name = ctx.dataset.label || '';
                        if (!d) {
                            return `${name}: No record`;
                        }
                        const inT = d.check_in || '—';
                        const outT = d.check_out || (d.open_shift ? 'Still in' : '—');
                        let line = `${name}: In ${inT} · Out ${outT}`;
                        if (d.work_hours != null) {
                            line += ` (${d.work_hours}h)`;
                        } else if (d.open_shift) {
                            line += ' (not checked out yet)';
                        }
                        return line;
                    },
                },
            },
        },
        scales: {
            x: {
                stacked: false,
                grid: { display: false },
                ticks: { maxRotation: 45, minRotation: 0, font: { size: 10 } },
            },
            y: {
                beginAtZero: true,
                title: { display: true, text: 'Hours worked' },
                suggestedMax: 10,
                ticks: { stepSize: 1 },
            },
        },
    };
});

const chartKey = computed(() => {
    const p = props.payload;
    if (!p) return 'empty';
    return `${p.preset}-${p.date_from}-${p.date_to}-${(p.users || []).map((u) => u.id).join(',')}`;
});
</script>

<template>
    <div class="relative w-full min-h-[280px] h-[min(440px,52vh)]">
        <Bar
            v-if="payload?.users?.length"
            :key="chartKey"
            :data="chartData"
            :options="chartOptions"
        />
        <p v-else class="text-sm text-slate-500 text-center py-14 px-4 leading-relaxed">
            No attendance in this period. This chart only lists people who checked in at least once; everyone else is hidden.
        </p>
    </div>
</template>
