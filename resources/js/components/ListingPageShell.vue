<template>
    <div class="listing-shell-outer w-full min-w-0 max-w-7xl mx-auto">
        <div class="rounded-panel bg-white shadow-card border border-slate-200/90 overflow-hidden">
            <!--
                The page title is NOT rendered here. It lives once, in the
                AppLayout top bar, so a screen never states its own name twice.
                This header carries only what is specific to the listing:
                an optional count, a lead-in line, and the page actions.
                It collapses entirely when a page supplies none of them.
            -->
            <header
                v-if="subtitle || hasBadge || $slots.actions"
                class="px-5 sm:px-6 pt-5 sm:pt-6 pb-4 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div class="min-w-0 flex-1 space-y-2">
                    <span v-if="hasBadge" class="badge badge-primary">
                        {{ badge }}
                    </span>
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
                class="px-4 sm:px-6 py-4 sm:py-5 bg-surface-muted border-y border-slate-100"
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
import { computed } from 'vue';

const props = defineProps({
    /**
     * Retained so every existing call site keeps working and so the value
     * stays available to callers/tests, but no longer rendered - see the
     * comment in the template above.
     */
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

const hasBadge = computed(
    () => props.badge !== null && props.badge !== undefined && props.badge !== '',
);
</script>
