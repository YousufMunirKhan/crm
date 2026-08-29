<template>
    <div class="px-5 py-12 text-center">
        <div
            v-if="$slots.icon"
            class="w-12 h-12 rounded-full bg-slate-100 grid place-items-center mx-auto mb-4 text-slate-500"
        >
            <slot name="icon" />
        </div>
        <p class="text-sm font-semibold text-slate-800">{{ heading }}</p>
        <p v-if="description" class="mt-1 text-sm text-slate-500 max-w-md mx-auto leading-relaxed">
            {{ description }}
        </p>
        <div v-if="$slots.action" class="mt-5">
            <slot name="action" />
        </div>
    </div>
</template>

<script setup>
defineProps({
    heading: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>
