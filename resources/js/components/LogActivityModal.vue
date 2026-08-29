<template>
    <BaseModal
        :model-value="true"
        title="Log Activity"
        :description="headerDescription"
        size="md"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <form id="log-activity-form" class="space-y-5" @submit.prevent="handleSubmit">
            <!-- Activity Type -->
            <fieldset class="form-fieldset">
                <legend class="form-legend">Activity Type <span class="form-required" aria-hidden="true">*</span></legend>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                    <label
                        v-for="type in activityTypes"
                        :key="type.value"
                        class="flex min-h-[56px] cursor-pointer flex-col items-center justify-center gap-1 rounded-card border-2 p-3 transition-all touch-manipulation sm:flex-row sm:gap-2"
                        :class="form.activity_type === type.value ? 'border-primary-500 bg-primary-50 shadow-sm' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                    >
                        <input
                            type="radio"
                            v-model="form.activity_type"
                            :value="type.value"
                            class="sr-only"
                        />
                        <span class="text-xl sm:text-2xl" aria-hidden="true">{{ type.icon }}</span>
                        <span class="text-center text-xs font-medium text-slate-700 sm:text-sm">{{ type.label }}</span>
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
                        {{ lead?.customer?.business_name || lead?.customer?.name || 'Not provided' }}
                    </div>
                    <div v-if="lead?.customer?.business_name" class="text-sm text-slate-700">
                        <span class="font-medium">Contact:</span>
                        {{ lead?.customer?.name || 'N/A' }}
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
            <div v-if="form.activity_type !== 'appointment'" class="form-grid-2">
                <div>
                    <label class="form-label" for="logactivitymodal-activity-date-time">Activity Date &amp; Time <span class="form-required" aria-hidden="true">*</span></label>
                    <input id="logactivitymodal-activity-date-time"
                        v-model="form.activity_at"
                        type="datetime-local"
                        required
                        class="form-input"
                    />
                </div>
                <div>
                    <label class="form-label" for="logactivitymodal-duration-minutes">Duration (minutes)</label>
                    <input id="logactivitymodal-duration-minutes"
                        v-model.number="form.duration"
                        type="number"
                        min="1"
                        placeholder="e.g., 15"
                        class="form-input"
                    />
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="form-label" for="logactivitymodal-notes-summary">Notes/Summary <span class="form-required" aria-hidden="true">*</span></label>
                <textarea id="logactivitymodal-notes-summary"
                    v-model="form.notes"
                    rows="3"
                    required
                    class="form-textarea resize-none"
                    :placeholder="form.activity_type === 'appointment' ? 'Add appointment brief: customer need, pain point, what to sell, and any instructions for the agent.' : 'What was discussed? What was the outcome?'"
                />
            </div>

            <!-- Outcome -->
            <fieldset class="form-fieldset">
                <legend class="form-legend">Outcome</legend>
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

            <!-- Schedule Next Follow-up -->
            <div class="border-t border-slate-200 pt-4">
                <div class="mb-3 flex items-center gap-2">
                    <input
                        type="checkbox"
                        v-model="form.schedule_followup"
                        id="schedule_followup_modal"
                        class="form-checkbox h-4 w-4"
                    />
                    <label for="schedule_followup_modal" class="text-sm font-medium text-slate-700">
                        Schedule next follow-up
                    </label>
                </div>

                <div v-if="form.schedule_followup" class="rounded-card border border-warning-200 bg-warning-50 p-4">
                    <label class="form-label" for="logactivitymodal-next-follow-up-date">Next Follow-up Date <span class="form-required" aria-hidden="true">*</span></label>
                    <input id="logactivitymodal-next-follow-up-date"
                        v-model="form.next_follow_up_at"
                        type="datetime-local"
                        :required="form.schedule_followup"
                        class="form-input"
                    />
                </div>

                <!-- Quick Schedule Buttons -->
                <div v-if="form.schedule_followup" class="mt-3 flex flex-wrap gap-2">
                    <BaseButton variant="outline" @click="setQuickFollowUp(1)">Tomorrow</BaseButton>
                    <BaseButton variant="outline" @click="setQuickFollowUp(2)">In 2 days</BaseButton>
                    <BaseButton variant="outline" @click="setQuickFollowUp(3)">In 3 days</BaseButton>
                    <BaseButton variant="outline" @click="setQuickFollowUp(7)">In 1 week</BaseButton>
                </div>
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
                {{ loading ? 'Saving...' : 'Log Activity' }}
            </BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { computed, ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { CalendarDaysIcon, InformationCircleIcon } from '@heroicons/vue/24/outline';
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
});

const emit = defineEmits(['close', 'saved']);

const loading = ref(false);
const error = ref(null);

const activityTypes = [
    { value: 'call', label: 'Call', icon: '📞' },
    { value: 'meeting', label: 'Meeting', icon: '🤝' },
    { value: 'appointment', label: 'Appointment', icon: '📅' },
    { value: 'email', label: 'Email', icon: '📧' },
    { value: 'visit', label: 'Visit', icon: '🏢' },
    { value: 'whatsapp', label: 'WhatsApp', icon: '💬' },
    { value: 'sms', label: 'SMS', icon: '📱' },
    { value: 'quote_sent', label: 'Quote Sent', icon: '📄' },
    { value: 'other', label: 'Other', icon: '📝' },
];

const outcomes = [
    { value: 'positive', label: 'Positive', activeClass: 'border-success-500 bg-success-50 text-success-700' },
    { value: 'neutral', label: 'Neutral', activeClass: 'border-primary-500 bg-primary-50 text-primary-700' },
    { value: 'negative', label: 'Negative', activeClass: 'border-danger-500 bg-danger-50 text-danger-700' },
    { value: 'no_answer', label: 'No Answer', activeClass: 'border-slate-500 bg-slate-100 text-slate-700' },
];

const headerDescription = computed(
    () => `${props.lead?.customer?.name || 'Customer'} — Lead #${props.lead?.id}`,
);

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

const form = ref({
    activity_type: 'call',
    activity_at: new Date().toISOString().slice(0, 16),
    duration: null,
    notes: '',
    outcome: 'neutral',
    schedule_followup: true,
    next_follow_up_at: '',
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
        const allowed = activityTypes.map((t) => t.value);
        const next = allowed.includes(props.initialActivityType) ? props.initialActivityType : 'call';
        form.value.activity_type = next;
    },
    { immediate: true }
);

const setQuickFollowUp = (days) => {
    const date = new Date();
    date.setDate(date.getDate() + days);
    date.setHours(10, 0, 0, 0);
    form.value.next_follow_up_at = date.toISOString().slice(0, 16);
};

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
