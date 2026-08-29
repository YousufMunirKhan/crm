<template>
    <BaseModal
        :model-value="modelValue"
        :title="title"
        size="sm"
        :dismissible="!loading"
        :close-on-backdrop="!loading"
        @update:model-value="onModelUpdate"
        @close="$emit('cancel')"
    >
        <slot>
            <p v-if="message" class="text-sm text-slate-600 leading-relaxed">{{ message }}</p>
        </slot>

        <template #actions>
            <BaseButton variant="outline" block-mobile :disabled="loading" @click="cancel">
                {{ cancelLabel }}
            </BaseButton>
            <BaseButton
                :variant="tone === 'danger' ? 'danger' : 'primary'"
                block-mobile
                :loading="loading"
                @click="$emit('confirm')"
            >
                {{ confirmLabel }}
            </BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import BaseModal from './BaseModal.vue';
import BaseButton from './BaseButton.vue';

const props = defineProps({
    modelValue: { type: Boolean, required: true },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    tone: { type: String, default: 'danger' }, // danger | primary
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'confirm', 'cancel']);

// BaseModal emits both update:modelValue(false) and close on Escape/backdrop/X.
// `cancel` is emitted from the close handler, so this only forwards the state.
function onModelUpdate(value) {
    emit('update:modelValue', value);
}

function cancel() {
    emit('update:modelValue', false);
    emit('cancel');
}
</script>
