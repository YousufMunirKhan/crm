<template>
    <div class="min-w-0">
        <label :for="id" class="form-label">
            {{ label }}
            <span v-if="required" class="text-danger-600" aria-hidden="true">*</span>
        </label>

        <select
            :id="id"
            :value="modelValue"
            v-bind="$attrs"
            :required="required"
            :disabled="disabled"
            :aria-describedby="describedBy"
            :aria-invalid="!!error || undefined"
            class="form-select"
            @change="$emit('update:modelValue', $event.target.value)"
        >
            <option v-if="placeholder" value="" :disabled="required">{{ placeholder }}</option>
            <option
                v-for="option in normalisedOptions"
                :key="String(option.value)"
                :value="option.value"
                :disabled="option.disabled"
            >
                {{ option.label }}
            </option>
        </select>

        <p v-if="error" :id="`${id}-error`" class="form-error">{{ error }}</p>
        <p v-else-if="hint" :id="`${id}-hint`" class="form-hint">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed, useId } from 'vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number, null], default: '' },
    label: { type: String, required: true },
    /** { value, label, disabled? } objects, or bare primitives. */
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: '' },
    required: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
});

defineEmits(['update:modelValue']);

// Mirrors BaseInput: a stable generated id keeps label/for and control/id in step.
const id = `field-${useId()}`;

const normalisedOptions = computed(() =>
    (props.options || []).map((option) =>
        option !== null && typeof option === 'object'
            ? { value: option.value, label: option.label ?? String(option.value), disabled: !!option.disabled }
            : { value: option, label: String(option), disabled: false },
    ),
);

const describedBy = computed(() => {
    if (props.error) return `${id}-error`;
    if (props.hint) return `${id}-hint`;
    return undefined;
});
</script>
