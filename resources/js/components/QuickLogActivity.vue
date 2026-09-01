<template>
    <div class="flex flex-wrap items-center gap-1">
        <button
            v-for="action in actions"
            :key="action.type + action.outcome"
            type="button"
            class="inline-flex items-center gap-1 rounded-control border border-slate-200 bg-white px-2
                   text-xs font-medium text-slate-600 transition-colors touch-manipulation
                   hover:border-primary-300 hover:bg-primary-50 hover:text-primary-800
                   disabled:opacity-50 disabled:pointer-events-none
                   focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
            :class="compact ? 'h-8' : 'h-11 sm:h-8'"
            :disabled="saving"
            :title="action.title"
            @click.stop.prevent="log(action)"
        >
            <component :is="action.icon" class="icon-sm shrink-0" aria-hidden="true" />
            <span>{{ action.label }}</span>
        </button>

        <span v-if="justLogged" class="text-xs font-medium text-success-700">{{ justLogged }}</span>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import {
    ChatBubbleLeftRightIcon,
    PhoneIcon,
    PhoneXMarkIcon,
} from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';

/**
 * Records a call outcome from the list, without opening the lead.
 *
 * The reason this exists: across 571 leads the whole company has logged three
 * calls, ever. Not because the work is not happening - because writing it down
 * costs a page load, a hunt for a button, a modal and a required notes field,
 * and a rep working a call list will not pay that fifty times a day. Everything
 * downstream depends on this being cheap: "untouched for 30 days" cannot tell
 * a neglected lead from an unrecorded one, and no fair measure of a
 * salesperson exists while the record is empty.
 *
 * One tap, one row in the timeline, no navigation. Notes stay available on the
 * lead for the times they are worth writing.
 */
const props = defineProps({
    leadId: { type: [Number, String], required: true },
    /** Dense table rows do not want 44px controls. */
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['logged']);

const toast = useToastStore();
const saving = ref(false);
const justLogged = ref('');

const actions = [
    {
        type: 'call',
        outcome: 'positive',
        label: 'Spoke',
        title: 'Log: spoke to them',
        description: 'Called and spoke to them.',
        icon: PhoneIcon,
    },
    {
        type: 'call',
        outcome: 'no_answer',
        label: 'No answer',
        title: 'Log: called, no answer',
        description: 'Called, no answer.',
        icon: PhoneXMarkIcon,
    },
    {
        type: 'whatsapp',
        outcome: 'neutral',
        label: 'Messaged',
        title: 'Log: sent a message',
        description: 'Sent them a message.',
        icon: ChatBubbleLeftRightIcon,
    },
];

async function log(action) {
    if (saving.value) return;

    saving.value = true;

    try {
        await axios.post(`/api/leads/${props.leadId}/activity`, {
            type: action.type,
            description: action.description,
            meta: {
                activity_at: new Date().toISOString(),
                outcome: action.outcome,
                // Marks the row as one-tap rather than written, so the timeline
                // can tell a quick log from a considered note later.
                quick: true,
            },
        });

        justLogged.value = 'Logged';
        emit('logged', { leadId: props.leadId, ...action });
        setTimeout(() => { justLogged.value = ''; }, 2500);
    } catch (e) {
        toast.error(e?.response?.data?.message || 'Could not log that.');
    } finally {
        saving.value = false;
    }
}
</script>
