<template>
    <div class="flex items-baseline gap-2">
        <span class="text-sm font-semibold tabular-nums text-slate-900">{{ time }}</span>
        <span class="text-xs text-slate-500">{{ zone }} · UK time</span>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { UK_TIMEZONE, ukZoneLabel } from '@/utils/datetime';

/**
 * The UK clock, on screen for everybody.
 *
 * Half the staff are not in the UK, and every shift, report and deadline here
 * is a UK one. Without this the person reading a 9am start has to work out
 * whether that is nine where they are or nine where the business is.
 *
 * The zone label is shown too, because BST and GMT are an hour apart and the
 * difference is invisible otherwise.
 */
const time = ref('');
const zone = ref('');
let timer = null;

const tick = () => {
    const now = new Date();

    time.value = now.toLocaleTimeString('en-GB', {
        timeZone: UK_TIMEZONE,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    });

    zone.value = ukZoneLabel(now);
};

onMounted(() => {
    tick();
    timer = setInterval(tick, 1000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>
