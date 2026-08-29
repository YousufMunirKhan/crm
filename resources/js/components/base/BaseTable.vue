<template>
    <div :aria-busy="loading ? 'true' : undefined">
        <!-- Desktop -->
        <div class="table-wrap hidden md:block">
            <table class="table" :style="{ minWidth }">
                <caption v-if="caption" class="sr-only">{{ caption }}</caption>
                <thead class="table-thead">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            :class="[column.align === 'right' ? 'table-th-num' : 'table-th', column.class]"
                            :style="column.width ? { width: column.width } : undefined"
                        >
                            {{ column.label }}
                        </th>
                        <th v-if="$slots.actions" scope="col" class="table-th-num">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>

                <tbody v-if="loading">
                    <tr v-for="n in 5" :key="`sk-${n}`">
                        <td v-for="column in columns" :key="column.key" class="table-td">
                            <span class="skeleton-text block w-full" />
                        </td>
                        <td v-if="$slots.actions" class="table-td-actions">
                            <span class="skeleton-text block w-full" />
                        </td>
                    </tr>
                </tbody>

                <tbody v-else-if="rows.length">
                    <tr v-for="(row, index) in rows" :key="keyFor(row, index)" class="table-row">
                        <td
                            v-for="column in columns"
                            :key="column.key"
                            :class="[column.align === 'right' ? 'table-td-num' : 'table-td', column.class]"
                        >
                            <slot
                                :name="`cell-${column.key}`"
                                :row="row"
                                :value="valueFor(row, column)"
                                :index="index"
                            >
                                {{ valueFor(row, column) }}
                            </slot>
                        </td>
                        <td v-if="$slots.actions" class="table-td-actions">
                            <div class="inline-flex items-center justify-end gap-1">
                                <slot name="actions" :row="row" :index="index" />
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <slot v-if="!loading && !rows.length" name="empty">
                <EmptyState heading="Nothing to show" />
            </slot>
        </div>

        <!-- Mobile: one card per row, field labels repeated as visible text. -->
        <div class="md:hidden p-4 space-y-3">
            <template v-if="loading">
                <div v-for="n in 5" :key="`msk-${n}`" class="table-card">
                    <span class="skeleton-text block w-2/3" />
                    <span class="skeleton-text block w-full" />
                </div>
            </template>

            <template v-else-if="rows.length">
                <div v-for="(row, index) in rows" :key="keyFor(row, index)">
                    <slot name="mobile" :row="row" :index="index">
                        <div class="table-card">
                            <div
                                v-for="column in mobileColumns"
                                :key="column.key"
                                class="flex items-start justify-between gap-3"
                            >
                                <span class="text-eyebrow uppercase text-slate-500 shrink-0">
                                    {{ column.mobileLabel ?? column.label }}
                                </span>
                                <span class="text-sm text-slate-700 text-right min-w-0 break-words">
                                    <slot
                                        :name="`cell-${column.key}`"
                                        :row="row"
                                        :value="valueFor(row, column)"
                                        :index="index"
                                    >
                                        {{ valueFor(row, column) }}
                                    </slot>
                                </span>
                            </div>
                            <div
                                v-if="$slots.actions"
                                class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-slate-200"
                            >
                                <slot name="actions" :row="row" :index="index" />
                            </div>
                        </div>
                    </slot>
                </div>
            </template>

            <slot v-else name="empty">
                <EmptyState heading="Nothing to show" />
            </slot>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import EmptyState from './EmptyState.vue';

const props = defineProps({
    /** { key, label, align?, width?, class?, mobileLabel?, hideOnMobile? } */
    columns: { type: Array, required: true },
    rows: { type: Array, required: true },
    rowKey: { type: [String, Function], default: 'id' },
    loading: { type: Boolean, default: false },
    minWidth: { type: String, default: '640px' },
    caption: { type: String, default: '' },
});

const mobileColumns = computed(() => props.columns.filter((column) => !column.hideOnMobile));

function keyFor(row, index) {
    if (typeof props.rowKey === 'function') return props.rowKey(row, index);
    return row?.[props.rowKey] ?? index;
}

function valueFor(row, column) {
    const value = row?.[column.key];
    return value === null || value === undefined ? '' : value;
}
</script>
