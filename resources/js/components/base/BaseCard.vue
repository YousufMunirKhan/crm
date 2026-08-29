<template>
    <component
        :is="as"
        :class="['card', interactive ? 'card-interactive' : '']"
        :tabindex="interactive ? 0 : undefined"
        :role="interactive ? 'button' : undefined"
        @click="interactive ? $emit('click', $event) : undefined"
        @keydown.enter.prevent="interactive ? $emit('click', $event) : undefined"
        @keydown.space.prevent="interactive ? $emit('click', $event) : undefined"
    >
        <div v-if="$slots.header || title || subtitle || $slots.actions" class="card-head">
            <div class="min-w-0">
                <slot name="header">
                    <h2 v-if="title" class="card-title">{{ title }}</h2>
                    <p v-if="subtitle" class="card-subtitle">{{ subtitle }}</p>
                </slot>
            </div>
            <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 shrink-0">
                <slot name="actions" />
            </div>
        </div>

        <div v-if="padded" class="card-body">
            <slot />
        </div>
        <slot v-else />

        <div v-if="$slots.footer" class="card-foot">
            <slot name="footer" />
        </div>
    </component>
</template>

<script setup>
defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    /** Wrap the default slot in .card-body. */
    padded: { type: Boolean, default: true },
    /** Adds .card-interactive plus the keyboard affordances a clickable card needs. */
    interactive: { type: Boolean, default: false },
    as: { type: String, default: 'div' },
});

defineEmits(['click']);
</script>
