<template>
    <div class="listing-shell-outer w-full min-w-0 max-w-7xl mx-auto px-3 sm:px-4 md:px-6 py-4 sm:py-6">
        <div
            class="rounded-2xl bg-white shadow-md shadow-slate-900/[0.06] border border-slate-200/90 overflow-hidden"
        >
            <header
                class="px-5 sm:px-6 pt-5 sm:pt-6 pb-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between border-b border-slate-100"
            >
                <div class="min-w-0 flex-1 space-y-1.5">
                    <div class="flex flex-wrap items-center gap-3 gap-y-2">
                        <h1 class="text-xl sm:text-2xl font-bold text-slate-800 tracking-tight">
                            {{ title }}
                        </h1>
                        <span
                            v-if="badge !== null && badge !== undefined && badge !== ''"
                            class="inline-flex items-center rounded-full bg-violet-50 px-2.5 py-0.5 text-xs font-semibold text-violet-900 ring-1 ring-inset ring-violet-200/80"
                        >
                            {{ badge }}
                        </span>
                    </div>
                    <p
                        v-if="subtitle"
                        class="text-sm text-slate-500 leading-relaxed max-w-3xl"
                    >
                        {{ subtitle }}
                    </p>
                </div>
                <div
                    v-if="$slots.actions"
                    class="flex flex-wrap items-stretch sm:items-center gap-3 shrink-0 w-full sm:w-auto justify-stretch sm:justify-end"
                >
                    <slot name="actions" />
                </div>
            </header>

            <div
                v-if="$slots.filters"
                class="px-4 sm:px-6 py-4 sm:py-5 bg-gradient-to-b from-slate-50/95 to-slate-50/70 border-b border-slate-100/90"
            >
                <div class="w-full min-w-0 max-w-full">
                    <slot name="filters" />
                </div>
            </div>

            <div v-if="$slots.toolbar" class="px-5 sm:px-6 py-4 bg-white border-b border-slate-100">
                <slot name="toolbar" />
            </div>

            <div class="listing-shell-body min-w-0">
                <slot />
            </div>

            <footer v-if="$slots.pagination" class="border-t border-slate-100 bg-white">
                <slot name="pagination" />
            </footer>
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        required: true,
    },
    subtitle: {
        type: String,
        default: '',
    },
    /** e.g. "6 Total" or raw number (shown as "N Total") */
    badge: {
        type: [String, Number],
        default: null,
    },
});
</script>
