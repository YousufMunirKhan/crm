<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ title }}</h3>
                <p class="text-sm text-slate-600 mb-4">{{ message }}</p>

                <div class="flex justify-end gap-3">
                    <button
                        @click="$emit('cancel')"
                        class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="$emit('confirm')"
                        :disabled="loading"
                        class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50"
                    >
                        {{ loading ? 'Deleting...' : 'Delete' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: {
        type: String,
        default: 'Confirm Delete',
    },
    message: {
        type: String,
        default: 'Are you sure you want to delete this item? This action cannot be undone.',
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['confirm', 'cancel']);
</script>
