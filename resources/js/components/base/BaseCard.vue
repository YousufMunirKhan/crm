<template>
    <component
        :is="as"
        :class="['card', interactive ? 'card-interactive' : '']"
        :tabindex="interactive ? 0 : undefined"
        :role="interactive ? 'button' : undefined"
        @click="interactive ? $emit('click', $event) : undefined"
        @keydown="onActivateKey"
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
const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    /** Wrap the default slot in .card-body. */
    padded: { type: Boolean, default: true },
    /** Adds .card-interactive plus the keyboard affordances a clickable card needs. */
    interactive: { type: Boolean, default: false },
    as: { type: String, default: 'div' },
});

const emit = defineEmits(['click']);

/**
 * Enter and Space activate an interactive card, and only when the card itself
 * is what has focus.
 *
 * This was `@keydown.enter.prevent` / `@keydown.space.prevent` with the emit
 * behind an `interactive` ternary. Vue applies `.prevent` unconditionally - it
 * compiles to preventDefault() running before the handler, so it fired on
 * every card whether interactive or not. Keydown bubbles, so a space typed
 * into an input inside a card was cancelled on its way up and never reached
 * the field: every form wrapped in a card silently refused spaces, and Enter
 * could not open a new line in a textarea.
 */
function onActivateKey(event) {
    if (! props.interactive) {
        return;
    }

    // A key pressed inside a nested control belongs to that control.
    if (event.target !== event.currentTarget) {
        return;
    }

    if (event.key !== 'Enter' && event.key !== ' ' && event.key !== 'Spacebar') {
        return;
    }

    event.preventDefault();
    emit('click', event);
}
</script>
