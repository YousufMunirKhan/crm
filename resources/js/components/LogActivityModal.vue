<template>
    <!--
        "Schedule" and "Log activity" in the record header open this same
        component - Schedule just preselects the appointment type. So clicking
        Schedule used to open a dialog headed "Log Activity" that then asked
        for a future date. It is two jobs, and it now says which one it is
        doing: booking something that has not happened, or writing down
        something that has.
    -->
    <BaseModal
        :model-value="true"
        :title="isBooking ? 'Book an appointment' : 'Log what happened'"
        :description="headerDescription"
        size="md"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <form id="log-activity-form" class="space-y-5" @submit.prevent="handleSubmit">
            <!-- Activity Type -->
            <fieldset v-if="!isBooking" class="form-fieldset">
                <legend class="form-legend">What did you do? <span class="form-required" aria-hidden="true">*</span></legend>
                <!--
                    Was icon-beside-label in a five-column grid, so "Appointment"
                    ran out of its own tile and "Quote Sent" wrapped while its
                    neighbours did not. Stacked at every width, so the longest
                    label sets the row height and they all match.
                -->
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                    <label
                        v-for="type in activityTypes"
                        :key="type.value"
                        class="flex min-h-[72px] cursor-pointer flex-col items-center justify-center gap-1.5 rounded-card border-2 px-2 py-3 text-center transition-colors touch-manipulation"
                        :class="form.activity_type === type.value ? 'border-primary-500 bg-primary-50 shadow-sm' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                    >
                        <input
                            type="radio"
                            v-model="form.activity_type"
                            :value="type.value"
                            class="sr-only"
                        />
                        <component
                            :is="type.icon"
                            class="icon shrink-0"
                            :class="form.activity_type === type.value ? 'text-primary-600' : 'text-slate-500'"
                            aria-hidden="true"
                        />
                        <span
                            class="text-xs font-medium leading-tight"
                            :class="form.activity_type === type.value ? 'text-primary-900' : 'text-slate-700'"
                        >{{ type.label }}</span>
                    </label>
                </div>
            </fieldset>

            <!-- Appointment: Assign to (first step) -->
            <div v-if="form.activity_type === 'appointment'" class="space-y-4 rounded-card border border-primary-200 bg-primary-50/80 p-4 sm:p-5">
                <h3 class="flex flex-col gap-1 font-semibold text-primary-900 sm:flex-row sm:items-center">
                    <span class="flex items-center gap-2">
                        <CalendarDaysIcon class="icon shrink-0" aria-hidden="true" />
                        Appointment Details
                    </span>
                    <span class="text-xs font-normal text-primary-700">(Email sent to customer, admin &amp; assignee)</span>
                </h3>
                <div class="space-y-2 rounded-control border border-primary-100 bg-white p-3">
                    <div class="text-eyebrow text-primary-800">Sales context sent to agent</div>
                    <div class="text-sm text-slate-700">
                        <span class="font-medium">Business:</span>
                        {{ briefCustomer.business_name || briefCustomer.name || 'Not provided' }}
                    </div>
                    <div v-if="briefCustomer.business_name" class="text-sm text-slate-700">
                        <span class="font-medium">Contact:</span>
                        {{ briefCustomer.name || 'N/A' }}
                    </div>
                    <div class="text-sm text-slate-700">
                        <span class="font-medium">Lead:</span>
                        #{{ lead?.id }} - {{ formatStage(lead?.stage) || 'No stage' }}
                    </div>
                    <div>
                        <div class="mb-1 text-xs font-medium text-primary-800">What to sell</div>
                        <div v-if="leadProducts.length" class="flex flex-wrap gap-1.5">
                            <span
                                v-for="product in leadProducts"
                                :key="product"
                                class="rounded border border-primary-200 bg-primary-50 px-2 py-1 text-xs font-medium text-primary-800"
                            >
                                {{ product }}
                            </span>
                        </div>
                        <div v-else class="text-xs text-warning-800">
                            No product is attached to this lead yet. Add products before scheduling if possible.
                        </div>
                    </div>
                </div>
                <div>
                    <label class="form-label" for="logactivitymodal-assign-to-who-will-attend">Assign to (who will attend) <span class="form-required" aria-hidden="true">*</span></label>
                    <select id="logactivitymodal-assign-to-who-will-attend"
                        v-model="form.assigned_user_id"
                        required
                        class="form-select"
                    >
                        <option value="">Select team member...</option>
                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role?.name || '—' }})</option>
                    </select>
                    <p class="mt-1 text-xs text-primary-800">The assigned person will receive an email with the appointment time and notes.</p>
                </div>
                <div class="form-grid-2">
                    <div>
                        <label class="form-label" for="logactivitymodal-appointment-date">Appointment Date <span class="form-required" aria-hidden="true">*</span></label>
                        <input id="logactivitymodal-appointment-date"
                            v-model="form.appointment_date"
                            type="date"
                            :required="form.activity_type === 'appointment'"
                            class="form-input"
                        />
                    </div>
                    <div>
                        <label class="form-label" for="logactivitymodal-appointment-time">Appointment Time <span class="form-required" aria-hidden="true">*</span></label>
                        <input id="logactivitymodal-appointment-time"
                            v-model="form.appointment_time"
                            type="time"
                            :required="form.activity_type === 'appointment'"
                            class="form-input"
                        />
                    </div>
                </div>
                <p class="flex items-start gap-2 text-xs text-primary-800">
                    <InformationCircleIcon class="icon-sm mt-px shrink-0" aria-hidden="true" />
                    <span>Confirmation emails will be sent to the customer and admin notification email.</span>
                </p>
            </div>

            <!-- Activity Date & Time (for non-appointments) -->
            <div v-if="!isBooking" class="form-grid-2">
                <div>
                    <label class="form-label" for="logactivitymodal-activity-date-time">When was this? <span class="form-required" aria-hidden="true">*</span></label>
                    <input id="logactivitymodal-activity-date-time"
                        v-model="form.activity_at"
                        type="datetime-local"
                        required
                        class="form-input"
                    />
                    <p class="form-hint">Already set to now - only change it if you are catching up on something older.</p>
                </div>
                <div>
                    <label class="form-label" for="logactivitymodal-duration-minutes">How long did it take?</label>
                    <input id="logactivitymodal-duration-minutes"
                        v-model.number="form.duration"
                        type="number"
                        min="1"
                        placeholder="Minutes"
                        class="form-input"
                    />
                    <p class="form-hint">Optional.</p>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="form-label" for="logactivitymodal-notes-summary">
                    {{ isBooking ? 'Brief for whoever attends' : 'What was said?' }}
                    <span class="form-required" aria-hidden="true">*</span>
                </label>
                <textarea id="logactivitymodal-notes-summary"
                    v-model="form.notes"
                    rows="3"
                    required
                    class="form-textarea resize-none"
                    :placeholder="isBooking
                        ? 'What they need, what to pitch, anything the attendee should know before walking in.'
                        : 'e.g. Spoke to the owner. Using Worldpay, unhappy with the rates. Wants a quote for two card machines by Friday.'"
                />
                <p class="form-hint">
                    This is what the next person sees on the timeline - write it for them, not for yourself.
                </p>
            </div>

            <!--
                Outcome used to default to "Neutral", so every activity logged
                without a thought recorded itself as neutral. A default answer
                to a question nobody was asked is not data, so it starts unset.
            -->
            <fieldset v-if="!isBooking" class="form-fieldset">
                <legend class="form-legend">How did it go? <span class="font-normal text-slate-500">(optional)</span></legend>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="outcome in outcomes"
                        :key="outcome.value"
                        class="flex min-h-[44px] cursor-pointer items-center gap-2 rounded-card border-2 px-3 py-2.5 transition-all touch-manipulation"
                        :class="form.outcome === outcome.value ? outcome.activeClass : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                    >
                        <input
                            type="radio"
                            v-model="form.outcome"
                            :value="outcome.value"
                            class="sr-only"
                        />
                        <span class="text-sm font-medium">{{ outcome.label }}</span>
                    </label>
                </div>
            </fieldset>

            <!--
                This box was ticked by default with an empty date that was also
                required, so the dialog opened in a state that could not be
                saved: fill in the notes, press the button, get an error about a
                field you never touched. It now opens with tomorrow already in
                it, and the quick buttons sit above the date they change rather
                than below it.
            -->
            <div v-if="!isBooking" class="border-t border-slate-200 pt-4">
                <label class="form-choice">
                    <input
                        type="checkbox"
                        v-model="form.schedule_followup"
                        id="schedule_followup_modal"
                        class="form-checkbox"
                    />
                    <span class="font-medium text-slate-800">Remind me to follow this up</span>
                </label>

                <div v-if="form.schedule_followup" class="mt-3 rounded-card border border-slate-200 bg-slate-50 p-4">
                    <div class="mb-3 flex flex-wrap gap-2">
                        <BaseButton
                            v-for="preset in followUpPresets"
                            :key="preset.days"
                            size="sm"
                            :variant="isFollowUpPreset(preset.days) ? 'soft' : 'outline'"
                            @click="setQuickFollowUp(preset.days)"
                        >{{ preset.label }}</BaseButton>
                    </div>
                    <label class="form-label" for="logactivitymodal-next-follow-up-date">Or pick a date and time</label>
                    <input id="logactivitymodal-next-follow-up-date"
                        v-model="form.next_follow_up_at"
                        type="datetime-local"
                        :required="form.schedule_followup"
                        class="form-input"
                    />
                    <p class="form-hint">This is what puts the lead on the follow-ups list.</p>
                </div>
                <p v-else class="mt-2 text-xs text-slate-500">
                    Leave this off and the lead drops off the follow-up list.
                </p>
            </div>

            <!-- Error Message -->
            <p v-if="error" class="callout callout-danger" role="alert">
                {{ error }}
            </p>
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="$emit('close')">Cancel</BaseButton>
            <BaseButton
                variant="primary"
                type="submit"
                form="log-activity-form"
                block-mobile
                :loading="loading"
            >
                {{ loading ? 'Saving...' : (isBooking ? 'Book appointment' : 'Save to timeline') }}
            </BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { computed, ref, onMounted, watch } from 'vue';
import axios from 'axios';
import {
    BuildingOffice2Icon,
    CalendarDaysIcon,
    ChatBubbleLeftRightIcon,
    DevicePhoneMobileIcon,
    DocumentTextIcon,
    EnvelopeIcon,
    InformationCircleIcon,
    PencilSquareIcon,
    PhoneIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import { BaseButton, BaseModal } from '@/components/base';

const toast = useToastStore();
const users = ref([]);

const props = defineProps({
    lead: {
        type: Object,
        required: true,
    },
    initialActivityType: {
        type: String,
        default: 'call',
    },
    /**
     * The leads on this page come from `customer.leads`, so they carry no
     * nested customer of their own. Passing it in beats re-fetching it.
     */
    customer: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const loading = ref(false);
const error = ref(null);

/**
 * Emoji rendered differently on every platform and sat beside Heroicons
 * everywhere else in the app, so these use the same set as the rest of it.
 *
 * "Appointment" is gone from this list. It is not something that happened -
 * it is something being booked - and the record header already has a Schedule
 * button for that, which opens this same dialog in booking mode.
 */
const activityTypes = [
    { value: 'call', label: 'Call', icon: PhoneIcon },
    { value: 'whatsapp', label: 'WhatsApp', icon: ChatBubbleLeftRightIcon },
    { value: 'email', label: 'Email', icon: EnvelopeIcon },
    { value: 'sms', label: 'SMS', icon: DevicePhoneMobileIcon },
    { value: 'meeting', label: 'Meeting', icon: UserGroupIcon },
    { value: 'visit', label: 'Visit', icon: BuildingOffice2Icon },
    { value: 'quote_sent', label: 'Quote sent', icon: DocumentTextIcon },
    { value: 'other', label: 'Other', icon: PencilSquareIcon },
];

const outcomes = [
    { value: 'positive', label: 'Positive', activeClass: 'border-success-500 bg-success-50 text-success-700' },
    { value: 'neutral', label: 'Neutral', activeClass: 'border-primary-500 bg-primary-50 text-primary-700' },
    { value: 'negative', label: 'Negative', activeClass: 'border-danger-500 bg-danger-50 text-danger-700' },
    { value: 'no_answer', label: 'No Answer', activeClass: 'border-slate-500 bg-slate-100 text-slate-700' },
];

/**
 * The dialog is opened from two buttons. Schedule means "book something for
 * later"; Log activity means "write down what just happened". They need
 * different fields and different words, and conflating them is what made this
 * dialog ask for a future date under the heading "Log Activity".
 */
const isBooking = computed(() => form.value.activity_type === 'appointment');

const headerDescription = computed(() => {
    const c = props.lead?.customer || props.customer || {};
    const who = [c.name, c.business_name].filter(Boolean).join(' · ') || 'this customer';

    return `${who} — lead #${props.lead?.id}`;
});

const followUpPresets = [
    { days: 1, label: 'Tomorrow' },
    { days: 2, label: 'In 2 days' },
    { days: 3, label: 'In 3 days' },
    { days: 7, label: 'Next week' },
];

/** Whichever of the two sources actually has the customer on it. */
const briefCustomer = computed(() => props.lead?.customer || props.customer || {});

const leadProducts = computed(() => {
    const names = (props.lead?.items || [])
        .map((item) => item.product?.name)
        .filter(Boolean);
    if (names.length) {
        return [...new Set(names)];
    }
    return props.lead?.product?.name ? [props.lead.product.name] : [];
});

function formatStage(stage) {
    return String(stage || '')
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (c) => c.toUpperCase());
}

/** Local time, not UTC - toISOString() shifts the clock for anyone off GMT. */
function localDateTimeValue(date) {
    const d = new Date(date);
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());

    return d.toISOString().slice(0, 16);
}

function defaultFollowUp() {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    d.setHours(9, 0, 0, 0);

    return localDateTimeValue(d);
}

const form = ref({
    activity_type: 'call',
    activity_at: localDateTimeValue(new Date()),
    duration: null,
    notes: '',
    // Was 'neutral'. See the outcome fieldset - a default answer is not data.
    outcome: '',
    schedule_followup: true,
    // The box is ticked by default, so the date it requires has to be here from
    // the start; otherwise the dialog opens in a state that cannot be saved.
    next_follow_up_at: defaultFollowUp(),
    // Appointment specific fields
    assigned_user_id: '',
    appointment_date: '',
    appointment_time: '10:00',
});

onMounted(async () => {
    try {
        const res = await axios.get('/api/users');
        users.value = Array.isArray(res.data) ? res.data : (res.data?.data ?? []);
    } catch (e) {
        console.error('Failed to load users for appointment assignee', e);
    }
});

watch(
    () => [props.lead?.id, props.initialActivityType],
    () => {
        // 'appointment' is deliberately not one of the tiles - it is the
        // booking mode the Schedule button opens - so it has to be allowed
        // here explicitly or Schedule silently lands on "Call".
        const allowed = [...activityTypes.map((t) => t.value), 'appointment'];
        const next = allowed.includes(props.initialActivityType) ? props.initialActivityType : 'call';
        form.value.activity_type = next;
    },
    { immediate: true }
);

// toISOString() is UTC, so this used to write the wrong hour for anyone not on
// GMT - "Tomorrow 10:00" landed at 09:00 during British Summer Time.
const followUpFor = (days) => {
    const date = new Date();
    date.setDate(date.getDate() + days);
    date.setHours(9, 0, 0, 0);

    return localDateTimeValue(date);
};

const setQuickFollowUp = (days) => {
    form.value.next_follow_up_at = followUpFor(days);
};

/** Highlights the preset that matches what is in the field. */
const isFollowUpPreset = (days) => form.value.next_follow_up_at === followUpFor(days);

const handleSubmit = async () => {
    // Validate lead exists
    if (!props.lead || !props.lead.id) {
        error.value = 'Error: No lead selected. Please try again.';
        console.error('Lead object:', props.lead);
        return;
    }

    // Validate required fields
    if (!form.value.notes || !form.value.notes.trim()) {
        error.value = 'Please enter notes/summary for this activity.';
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        console.log('Logging activity for lead:', props.lead.id);

        // Build meta object based on activity type
        const meta = {
            activity_at: form.value.activity_at,
            duration: form.value.duration,
            outcome: form.value.outcome,
        };

        // Add appointment-specific data
        if (form.value.activity_type === 'appointment') {
            meta.appointment_date = form.value.appointment_date;
            meta.appointment_time = form.value.appointment_time;
        }

        const payload = {
            type: form.value.activity_type,
            description: form.value.notes,
            meta,
        };
        if (form.value.activity_type === 'appointment' && form.value.assigned_user_id) {
            payload.assigned_user_id = form.value.assigned_user_id;
        }
        const activityResponse = await axios.post(`/api/leads/${props.lead.id}/activity`, payload);

        console.log('Activity created:', activityResponse.data);

        // Update next follow-up date if scheduled
        if (form.value.schedule_followup && form.value.next_follow_up_at) {
            console.log('Updating follow-up date to:', form.value.next_follow_up_at);
            await axios.put(`/api/leads/${props.lead.id}`, {
                next_follow_up_at: form.value.next_follow_up_at,
            });
        }

        toast.success('Activity logged successfully!');
        emit('saved');
        emit('close');
    } catch (err) {
        console.error('Error logging activity:', err);
        console.error('Response:', err.response);

        if (err.response?.status === 404) {
            error.value = 'Lead not found. It may have been deleted.';
        } else if (err.response?.status === 422) {
            // Validation error
            const errors = err.response?.data?.errors;
            if (errors) {
                error.value = Object.values(errors).flat().join(', ');
            } else {
                error.value = err.response?.data?.message || 'Validation failed.';
            }
        } else if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else {
            error.value = 'Failed to log activity. Please try again.';
        }
    } finally {
        loading.value = false;
    }
};
</script>
