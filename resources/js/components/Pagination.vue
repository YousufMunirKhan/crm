<template>
    <div
        v-if="pagination && (pagination.total > 0 || pagination.last_page > 1)"
        :class="[
            'flex w-full min-w-0 max-w-full flex-col gap-3',
            embedded ? 'px-5 sm:px-6 py-3.5 bg-slate-50/40' : 'items-center justify-between border-t border-slate-200 bg-white px-4 py-3 sm:px-6',
        ]"
    >
        <!-- Multi-page -->
        <template v-if="pagination.last_page > 1">
            <div class="flex flex-1 justify-between sm:hidden w-full">
                <BaseButton
                    variant="outline"
                    :disabled="pagination.current_page === 1"
                    @click="$emit('page-change', pagination.current_page - 1)"
                >
                    <template #icon><ChevronLeftIcon class="icon-sm" aria-hidden="true" /></template>
                    Previous
                </BaseButton>
                <BaseButton
                    variant="outline"
                    :disabled="pagination.current_page === pagination.last_page"
                    @click="$emit('page-change', pagination.current_page + 1)"
                >
                    Next
                    <ChevronRightIcon class="icon-sm" aria-hidden="true" />
                </BaseButton>
            </div>
            <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between w-full gap-4">
                <div>
                    <p class="text-sm text-slate-600">
                        Showing
                        <span class="font-medium text-slate-800">{{ (pagination.current_page - 1) * pagination.per_page + 1 }}</span>
                        to
                        <span class="font-medium text-slate-800">{{ Math.min(pagination.current_page * pagination.per_page, pagination.total) }}</span>
                        of
                        <span class="font-medium text-slate-800">{{ pagination.total }}</span>
                        {{ resultLabel }}
                    </p>
                </div>
                <div>
                    <nav class="isolate inline-flex -space-x-px rounded-lg shadow-sm" aria-label="Pagination">
                        <BaseButton
                            variant="ghost"
                            size="sm"
                            label="Previous page"
                            class="rounded-r-none px-2.5 py-2 ring-1 ring-inset ring-slate-200"
                            :disabled="pagination.current_page === 1"
                            @click="$emit('page-change', pagination.current_page - 1)"
                        >
                            <ChevronLeftIcon class="icon" aria-hidden="true" />
                        </BaseButton>
                        <button
                            v-for="page in visiblePages"
                            :key="page"
                            type="button"
                            :aria-current="page === pagination.current_page ? 'page' : undefined"
                            :aria-label="`Go to page ${page}`"
                            @click="$emit('page-change', page)"
                            :class="[
                                'relative inline-flex items-center px-3.5 py-2 text-sm font-semibold tabular-nums transition-colors',
                                'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 focus-visible:z-20',
                                page === pagination.current_page
                                    ? 'z-10 bg-primary-600 text-white ring-1 ring-inset ring-primary-600'
                                    : 'text-slate-700 ring-1 ring-inset ring-slate-200 hover:bg-slate-50',
                            ]"
                        >
                            {{ page }}
                        </button>
                        <BaseButton
                            variant="ghost"
                            size="sm"
                            label="Next page"
                            class="rounded-l-none px-2.5 py-2 ring-1 ring-inset ring-slate-200"
                            :disabled="pagination.current_page === pagination.last_page"
                            @click="$emit('page-change', pagination.current_page + 1)"
                        >
                            <ChevronRightIcon class="icon" aria-hidden="true" />
                        </BaseButton>
                    </nav>
                </div>
            </div>
        </template>

        <!-- Single page: still show count so layout feels complete -->
        <p
            v-else-if="showSinglePageSummary && pagination.total > 0"
            class="text-sm text-slate-500 text-center sm:text-left w-full"
        >
            Showing all
            <span class="font-medium text-slate-700">{{ pagination.total }}</span>
            {{ pagination.total === 1 ? singularLabel : resultLabel }}
        </p>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/outline';
import BaseButton from '@/components/base/BaseButton.vue';

const props = defineProps({
    pagination: {
        type: Object,
        required: true,
    },
    /** Sit inside ListingPageShell footer without extra outer border */
    embedded: {
        type: Boolean,
        default: false,
    },
    resultLabel: {
        type: String,
        default: 'results',
    },
    singularLabel: {
        type: String,
        default: 'result',
    },
    showSinglePageSummary: {
        type: Boolean,
        default: true,
    },
});

defineEmits(['page-change']);

const visiblePages = computed(() => {
    if (!props.pagination) return [];

    const current = props.pagination.current_page;
    const last = props.pagination.last_page;
    const pages = [];

    let start = Math.max(1, current - 3);
    let end = Math.min(last, start + 6);

    if (end - start < 6) {
        start = Math.max(1, end - 6);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    return pages;
});
</script>
