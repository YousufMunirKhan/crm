<template>
    <div class="space-y-3">
        <div>
            <span class="form-label mb-2 block">
                Why was it lost?
                <span class="form-required" aria-hidden="true">*</span>
            </span>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" role="radiogroup" aria-label="Reason lost">
                <button
                    v-for="reason in reasons"
                    :key="reason.code"
                    type="button"
                    role="radio"
                    :aria-checked="code === reason.code"
                    class="flex min-h-[44px] items-center gap-2 rounded-control border px-3 py-2 text-left
                           text-sm transition-colors touch-manipulation
                           focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                    :class="code === reason.code
                        ? 'border-danger-400 bg-danger-50 text-danger-900 font-medium'
                        : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50'"
                    @click="choose(reason.code)"
                >
                    <span
                        class="grid h-4 w-4 shrink-0 place-items-center rounded-full border"
                        :class="code === reason.code ? 'border-danger-500' : 'border-slate-300'"
                        aria-hidden="true"
                    >
                        <span v-if="code === reason.code" class="h-2 w-2 rounded-full bg-danger-500" />
                    </span>
                    <span>{{ reason.label }}</span>
                </button>
            </div>
        </div>

        <div v-if="selected">
            <label class="form-label" :for="detailId">
                {{ selected.detail_required ? 'What happened?' : 'Anything worth adding?' }}
                <span v-if="selected.detail_required" class="form-required" aria-hidden="true">*</span>
                <span v-else class="form-optional">Optional</span>
            </label>
            <textarea
                :id="detailId"
                v-model="detail"
                rows="2"
                class="form-textarea"
                :placeholder="selected.hint"
            />
            <p class="form-hint">{{ selected.hint }}</p>
        </div>

        <p v-if="revisitable" class="callout callout-info">
            This one is worth coming back to. Set a follow-up date on the lead before you
            close it and it will reappear on your list instead of being forgotten.
        </p>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { LOST_REASONS } from '@/constants/lostReasons';

/**
 * Asks why a lead or a product line was lost, in one tap.
 *
 * The old version of this was a required textarea. Across 571 leads it has been
 * filled in once - so staff mark everything "won" instead, and the company's
 * record reads 391 won against 3 lost. Nothing built on win rate, conversion or
 * the funnel means anything while that is true, and none of it can be fixed by
 * reporting; it has to be fixed at the moment of capture.
 *
 * Detail is still offered, and insisted on only where the label alone tells you
 * nothing you could act on - "something else", or a competitor whose name is
 * the entire point.
 */
const props = defineProps({
    /** v-model:code - the canonical reason, what reporting groups on. */
    code: { type: String, default: '' },
    /** v-model:detail - free text, appended to the stored reason. */
    detail: { type: String, default: '' },
    idPrefix: { type: String, default: 'lost-reason' },
});

const emit = defineEmits(['update:code', 'update:detail']);

const reasons = LOST_REASONS;
const detailId = computed(() => `${props.idPrefix}-detail`);

const selected = computed(() => reasons.find((r) => r.code === props.code) ?? null);

/**
 * "Wrong time" and "tied into a contract" are not really losses, they are
 * losses with a date on them - worth saying so while the rep is still here.
 */
const revisitable = computed(() => ['timing', 'in_contract'].includes(props.code));

const detail = ref(props.detail);

watch(() => props.detail, (value) => { detail.value = value; });
watch(detail, (value) => emit('update:detail', value));

function choose(code) {
    emit('update:code', code);
}
</script>
