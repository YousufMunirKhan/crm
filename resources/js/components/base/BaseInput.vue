<template>
    <div class="min-w-0">
        <label :for="id" class="form-label">
            {{ label }}
            <span v-if="required" class="text-danger-600" aria-hidden="true">*</span>
        </label>

        <textarea
            v-if="type === 'textarea'"
            :id="id"
            :value="modelValue"
            v-bind="$attrs"
            :required="required"
            :aria-describedby="describedBy"
            :aria-invalid="!!error || undefined"
            class="form-textarea"
            @input="$emit('update:modelValue', $event.target.value)"
        />
        <input
            v-else
            :id="id"
            :type="type"
            :value="modelValue"
            v-bind="$attrs"
            :required="required"
            :aria-describedby="describedBy"
            :aria-invalid="!!error || undefined"
            class="form-input"
            @input="$emit('update:modelValue', type === 'number' ? toNumber($event.target.value) : $event.target.value)"
        />

        <p v-if="error" :id="`${id}-error`" class="mt-1 text-xs text-danger-700">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed, useId } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    required: { type: Boolean, default: false },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
});

defineEmits(['update:modelValue']);

// A stable generated id keeps label/for and input/id in step without callers
// having to invent unique names.
const id = `field-${useId()}`;

const describedBy = computed(() => {
    if (props.error) return `${id}-error`;
    if (props.hint) return `${id}-hint`;
    return undefined;
});

function toNumber(value) {
    if (value === '') return null;
    const n = Number(value);
    return Number.isNaN(n) ? value : n;
}
</script>
