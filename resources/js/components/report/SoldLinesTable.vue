<template>
    <div v-if="!rows?.length" class="text-sm text-slate-500 py-6 text-center border border-dashed border-slate-200 rounded-xl">
        No won lines in this period.
    </div>
    <div v-else class="overflow-x-auto rounded-xl border border-slate-200 shadow-sm">
        <table class="w-full min-w-[560px] text-sm">
            <thead class="listing-thead">
                <tr>
                    <th class="listing-th">Product</th>
                    <th class="listing-th">Customer</th>
                    <th class="listing-th text-right">Qty</th>
                    <th class="listing-th text-right">Total</th>
                    <th class="listing-th">Closed</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(p, i) in rows" :key="i" class="listing-row">
                    <td class="listing-td-strong">{{ p.product_name }}</td>
                    <td class="listing-td">
                        <router-link v-if="p.customer_id" :to="`/customers/${p.customer_id}`" class="listing-link-edit">
                            {{ p.customer_name }}
                        </router-link>
                        <span v-else>{{ p.customer_name }}</span>
                    </td>
                    <td class="listing-td text-right">{{ p.quantity }}</td>
                    <td class="listing-td text-right font-semibold">£{{ formatNumber(p.total_price) }}</td>
                    <td class="listing-td text-slate-500 text-xs">{{ p.closed_at || '—' }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
defineProps({
    rows: {
        type: Array,
        default: () => [],
    },
});

const formatNumber = (n) => new Intl.NumberFormat('en-GB', { maximumFractionDigits: 2 }).format(Number(n || 0));
</script>
