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
                <button
                    type="button"
                    @click="$emit('page-change', pagination.current_page - 1)"
                    :disabled="pagination.current_page === 1"
                    class="relative inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Previous
                </button>
                <button
                    type="button"
                    @click="$emit('page-change', pagination.current_page + 1)"
                    :disabled="pagination.current_page === pagination.last_page"
                    class="relative inline-flex items-center rounded-lg border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    Next
                </button>
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
                        <button
                            type="button"
                            @click="$emit('page-change', pagination.current_page - 1)"
                            :disabled="pagination.current_page === 1"
                            class="relative inline-flex items-center rounded-l-lg px-2.5 py-2 text-slate-500 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span class="sr-only">Previous</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z" clip-rule="evenodd" />
                            </svg>
                        </button>
                        <button
                            v-for="page in visiblePages"
                            :key="page"
                            type="button"
                            @click="$emit('page-change', page)"
                            :class="[
                                'relative inline-flex items-center px-3.5 py-2 text-sm font-semibold focus:z-20',
                                page === pagination.current_page
                                    ? 'z-10 bg-[#7C3AED] text-white ring-1 ring-inset ring-[#7C3AED]'
                                    : 'text-slate-700 ring-1 ring-inset ring-slate-200 hover:bg-slate-50',
                            ]"
                        >
                            {{ page }}
                        </button>
                        <button
                            type="button"
                            @click="$emit('page-change', pagination.current_page + 1)"
                            :disabled="pagination.current_page === pagination.last_page"
                            class="relative inline-flex items-center rounded-r-lg px-2.5 py-2 text-slate-500 ring-1 ring-inset ring-slate-200 hover:bg-slate-50 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span class="sr-only">Next</span>
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                            </svg>
                        </button>
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
