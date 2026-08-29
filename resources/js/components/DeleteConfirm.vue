<template>
    <!--
      Thin wrapper over ConfirmDialog. Existing call sites mount this with
      `v-if` and listen for `confirm` / `cancel`, so the props and emits are
      unchanged; the dialog now gets a focus trap, Escape, scroll lock and
      role="dialog" for free.
    -->
    <ConfirmDialog
        :model-value="true"
        :title="title"
        :message="message"
        :confirm-label="loading ? 'Deleting...' : 'Delete'"
        cancel-label="Cancel"
        tone="danger"
        :loading="loading"
        @cancel="$emit('cancel')"
        @confirm="$emit('confirm')"
    />
</template>

<script setup>
import ConfirmDialog from '@/components/base/ConfirmDialog.vue';

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
