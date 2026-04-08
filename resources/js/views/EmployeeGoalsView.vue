<template>
    <ListingPageShell
        title="Set targets"
        subtitle="Set monthly targets for appointments, revenue, and sales by product or category. A category groups every product with that category on the product record — each won line item for any of those products counts toward that category target."
        :badge="goalsBadge"
    >
        <template #filters>
            <div>
                <label class="listing-label">Month</label>
                <input v-model="selectedMonth" type="month" class="listing-input w-full sm:w-48" @change="loadData" />
            </div>
        </template>

        <div class="overflow-x-auto min-w-0">
            <table class="w-full min-w-[800px]">
                <thead class="listing-thead">
                    <tr>
                        <th class="listing-th">Employee</th>
                        <th class="listing-th">Appointments (target / achieved)</th>
                        <th class="listing-th">Sales (target / achieved) <span class="font-normal text-slate-400">· by product/category</span></th>
                        <th class="listing-th">Revenue (target / won)</th>
                        <th class="listing-th">Progress</th>
                        <th class="listing-th">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="6" class="listing-td text-center text-slate-500 py-10">Loading…</td>
                    </tr>
                    <tr v-else-if="rows.length === 0">
                        <td colspan="6" class="listing-td text-center text-slate-500 py-10">No employees found for this month.</td>
                    </tr>
                    <tr v-for="row in rows" :key="row.user_id" class="listing-row">
                        <td class="listing-td">
                            <div class="font-medium text-slate-900">{{ row.user?.name || 'Employee' }}</div>
                            <div class="text-xs text-slate-500">{{ row.user?.role?.name || '—' }}</div>
                        </td>
                        <td class="listing-td">{{ row.target_appointments }} / {{ row.achieved_appointments }}</td>
                        <td class="listing-td">
                            <span>{{ salesTargetTotal(row) }} / {{ salesAchievedTotal(row) }}</span>
                            <div v-if="row.lines?.length" class="text-xs text-slate-500 mt-1 max-w-xs">
                                <span v-for="(ln, idx) in row.lines" :key="ln.id || idx" class="block truncate">
                                    {{ lineLabel(ln) }}: {{ ln.achieved_quantity ?? 0 }}/{{ ln.target_quantity ?? 0 }}
                                </span>
                            </div>
                        </td>
                        <td class="listing-td">£{{ formatNumber(row.target_revenue) }} / £{{ formatNumber(row.achieved_revenue) }}</td>
                        <td class="listing-td">
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
                        <td class="listing-td">
                            <button type="button" class="listing-btn-primary text-xs py-1.5 px-3" @click="openEdit(row)">
                                Set targets
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </ListingPageShell>

        <!-- Set targets modal -->
        <div
            v-if="editing"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
        >
            <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 space-y-4">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-slate-900">
                        Set targets – {{ editing.user?.name || 'Employee' }}
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
                        <div v-if="!editForm.lines.length">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Target sales (total won items)</label>
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
                    <div class="border border-slate-200 rounded-lg p-4 space-y-3 bg-slate-50/80">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <div>
                                <div class="text-sm font-medium text-slate-800">Sales by product or category</div>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    Add one row per product or per category. For a category, all products that share that category (in Products admin) are included — any of them, when sold (won line item), counts toward the same category target.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="text-sm px-3 py-1.5 rounded-lg border border-slate-300 bg-white hover:bg-slate-50 shrink-0"
                                @click="addTargetLine"
                            >
                                + Add line
                            </button>
                        </div>
                        <p v-if="editForm.lines.length" class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-md px-2 py-1.5">
                            Product/category lines replace the simple “target sales” total. Remove all lines to use the total again.
                        </p>
                        <div v-if="!editForm.lines.length" class="text-xs text-slate-500">No lines — the total above applies.</div>
                        <p v-else-if="!categories.length && products.length" class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-md px-2 py-1.5">
                            No categories found on active products. Set a category on products in Admin → Products so category targets appear here.
                        </p>
                        <div
                            v-for="(line, idx) in editForm.lines"
                            :key="idx"
                            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:gap-3 border-b border-slate-200 pb-4 last:border-0 last:pb-0"
                        >
                            <div class="w-full sm:w-[8.5rem] shrink-0">
                                <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                                <select
                                    v-model="line.line_type"
                                    class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm bg-white"
                                    @change="onLineTypeChange(line)"
                                >
                                    <option value="product">Product</option>
                                    <option value="category">Category</option>
                                </select>
                            </div>
                            <div class="flex-1 min-w-0">
                                <label class="block text-xs font-medium text-slate-600 mb-1">
                                    {{ line.line_type === 'category' ? 'Category' : 'Product' }}
                                </label>
                                <select
                                    v-if="line.line_type === 'product'"
                                    v-model.number="line.product_id"
                                    class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm bg-white min-h-[42px]"
                                >
                                    <option :value="0">Select product…</option>
                                    <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }}</option>
                                </select>
                                <select
                                    v-else
                                    v-model="line.category_name"
                                    class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm bg-white min-h-[42px]"
                                >
                                    <option value="">Select category…</option>
                                    <option
                                        v-if="line.category_name && !categories.includes(line.category_name)"
                                        :value="line.category_name"
                                    >
                                        {{ line.category_name }} (saved)
                                    </option>
                                    <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                                </select>
                            </div>
                            <div class="w-full sm:w-36 shrink-0">
                                <label class="block text-xs font-medium text-slate-600 mb-1">Target (won items)</label>
                                <input
                                    v-model.number="line.target_quantity"
                                    type="number"
                                    min="0"
                                    class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm min-h-[42px]"
                                />
                            </div>
                            <div class="flex sm:flex-col sm:justify-end sm:pb-0.5 shrink-0">
                                <button
                                    type="button"
                                    class="text-sm font-medium text-red-600 hover:text-red-700 py-2.5 sm:px-1 text-left sm:text-right"
                                    @click="removeTargetLine(idx)"
                                >
                                    Remove
                                </button>
                            </div>
                        </div>
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
                        {{ saving ? 'Saving...' : 'Save targets' }}
                    </button>
                </div>
            </div>
        </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useRoute } from 'vue-router';
import { useToastStore } from '@/stores/toast';
import ListingPageShell from '@/components/ListingPageShell.vue';

const route = useRoute();
const toast = useToastStore();

const loading = ref(false);
const saving = ref(false);
const rows = ref([]);
const products = ref([]);
const categories = ref([]);
const selectedMonth = ref('');
const editing = ref(null);
const editForm = ref({
    month: '',
    target_appointments: 0,
    target_sales: 0,
    target_revenue: 0,
    lines: [],
});

const selectedEmployeeId = computed(() => {
    // Optional ?employee_id= or coming from /hr/employees/:id/goals in future
    return route.query.employee_id ? Number(route.query.employee_id) : null;
});

const goalsBadge = computed(() => {
    if (loading.value) return null;
    const n = rows.value.length;
    return n ? `${n} ${n === 1 ? 'employee' : 'employees'}` : null;
});

const formatNumber = (num) => {
    const n = Number(num || 0);
    return new Intl.NumberFormat('en-GB', { maximumFractionDigits: 2 }).format(n);
};

const salesTargetTotal = (row) => {
    const lines = row.lines || [];
    if (lines.length) {
        return lines.reduce((s, l) => s + Number(l.target_quantity || 0), 0);
    }
    return row.target_sales || 0;
};

const salesAchievedTotal = (row) => {
    const lines = row.lines || [];
    if (lines.length) {
        return lines.reduce((s, l) => s + Number(l.achieved_quantity || 0), 0);
    }
    return row.achieved_sales || 0;
};

const lineLabel = (ln) => {
    if (ln.line_type === 'category') return ln.category_name || 'Category';
    return ln.product?.name || (ln.product_id ? `Product #${ln.product_id}` : 'Product');
};

const addTargetLine = () => {
    editForm.value.lines.push({
        line_type: 'product',
        product_id: 0,
        category_name: '',
        target_quantity: 0,
    });
};

const onLineTypeChange = (line) => {
    if (line.line_type === 'product') {
        line.category_name = '';
        line.product_id = line.product_id || 0;
    } else {
        line.product_id = 0;
        line.category_name = line.category_name || '';
    }
};

const removeTargetLine = (idx) => {
    editForm.value.lines.splice(idx, 1);
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
            const lines = t.lines || [];
            const achievedFromLines = lines.length
                ? lines.reduce((s, l) => s + Number(l.achieved_quantity || 0), 0)
                : 0;
            byUser[t.user_id] = {
                user_id: t.user_id,
                user: t.user,
                lines,
                target_appointments: t.target_appointments || 0,
                target_sales: t.target_sales || 0,
                target_revenue: t.target_revenue || 0,
                achieved_appointments: 0,
                achieved_sales: achievedFromLines,
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
                    lines: [],
                    target_appointments: 0,
                    target_sales: 0,
                    target_revenue: 0,
                    achieved_appointments: 0,
                    achieved_sales: 0,
                    achieved_revenue: 0,
                };
            existing.achieved_appointments = ag.appointments_count || 0;
            if (existing.lines?.length) {
                existing.achieved_sales = existing.lines.reduce(
                    (s, l) => s + Number(l.achieved_quantity || 0),
                    0
                );
            } else {
                existing.achieved_sales = ag.won_products || ag.won_count || 0;
            }
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
        console.error('Failed to load employee targets:', error);
        toast.error('Failed to load employee targets');
        rows.value = [];
    } finally {
        loading.value = false;
    }
};

const openEdit = (row) => {
    editing.value = row;
    const lines = (row.lines || []).map((l) => ({
        line_type: l.line_type || 'product',
        product_id: l.product_id || 0,
        category_name: l.category_name || '',
        target_quantity: l.target_quantity ?? 0,
    }));
    editForm.value = {
        month: selectedMonth.value || getDefaultMonth(),
        target_appointments: row.target_appointments || 0,
        target_sales: row.target_sales || 0,
        target_revenue: row.target_revenue || 0,
        lines,
    };
};

const closeEdit = () => {
    editing.value = null;
};

const saveGoals = async () => {
    if (!editing.value) return;
    for (let i = 0; i < editForm.value.lines.length; i++) {
        const ln = editForm.value.lines[i];
        if (ln.line_type === 'product' && !Number(ln.product_id)) {
            toast.error(`Select a product for row ${i + 1}.`);
            return;
        }
        if (ln.line_type === 'category' && !String(ln.category_name || '').trim()) {
            toast.error(`Select a category for row ${i + 1}.`);
            return;
        }
    }
    saving.value = true;
    try {
        const linesPayload = editForm.value.lines.map((ln) => ({
            line_type: ln.line_type,
            product_id: ln.line_type === 'product' ? Number(ln.product_id) : null,
            category_name: ln.line_type === 'category' ? String(ln.category_name || '').trim() : null,
            target_quantity: Number(ln.target_quantity) || 0,
        }));
        await axios.put(`/api/hr/employee-targets/${editing.value.user_id}`, {
            month: editForm.value.month,
            target_appointments: editForm.value.target_appointments || 0,
            target_sales: editForm.value.lines.length ? 0 : editForm.value.target_sales || 0,
            target_revenue: editForm.value.target_revenue || 0,
            lines: linesPayload,
        });
        toast.success('Targets saved');
        closeEdit();
        await loadData();
    } catch (error) {
        console.error('Failed to save targets:', error);
        toast.error(error.response?.data?.message || 'Failed to save targets');
    } finally {
        saving.value = false;
    }
};

const loadPickers = async () => {
    try {
        const [prodRes, catRes] = await Promise.all([
            axios.get('/api/products', { params: { active_only: 'true' } }),
            axios.get('/api/products/categories'),
        ]);
        products.value = Array.isArray(prodRes.data) ? prodRes.data : prodRes.data?.data || [];
        categories.value = catRes.data?.data || [];
    } catch (e) {
        console.error('Failed to load products/categories:', e);
    }
};

onMounted(() => {
    if (!selectedMonth.value) {
        selectedMonth.value = getDefaultMonth();
    }
    loadPickers();
    loadData();
});
</script>

