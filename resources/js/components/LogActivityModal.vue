<template>
    <div class="fixed inset-0 bg-black/50 flex items-end sm:items-center justify-center z-50 p-0 sm:p-4 overflow-y-auto">
        <div class="bg-white rounded-t-2xl sm:rounded-xl shadow-xl w-full sm:max-w-2xl max-h-[95vh] sm:max-h-[90vh] overflow-y-auto flex flex-col">
            <div class="sticky top-0 bg-white p-4 sm:p-6 border-b border-slate-200 flex-shrink-0">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg sm:text-xl font-semibold text-slate-900 flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Log Activity
                        </h2>
                        <p class="text-sm text-slate-500 mt-1">
                            {{ lead?.customer?.name || 'Customer' }} — Lead #{{ lead?.id }}
                        </p>
                    </div>
                    <button @click="$emit('close')" class="p-2 -m-2 text-slate-400 hover:text-slate-600 hover:bg-slate-100 rounded-lg transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <form @submit.prevent="handleSubmit" class="p-4 sm:p-6 space-y-5 flex-1 overflow-y-auto">
                <!-- Activity Type -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-3">Activity Type *</label>
                    <div class="grid grid-cols-3 sm:grid-cols-5 gap-2">
                        <label
                            v-for="type in activityTypes"
                            :key="type.value"
                            class="flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 p-3 border-2 rounded-xl cursor-pointer transition-all min-h-[56px] touch-manipulation"
                            :class="form.activity_type === type.value ? 'border-blue-500 bg-blue-50 shadow-sm' : 'border-slate-200 hover:border-slate-300 hover:bg-slate-50'"
                        >
                            <input
                                type="radio"
                                v-model="form.activity_type"
                                :value="type.value"
                                class="sr-only"
                            />
                            <span class="text-xl sm:text-2xl">{{ type.icon }}</span>
                            <span class="text-xs sm:text-sm font-medium text-slate-700 text-center">{{ type.label }}</span>
                        </label>
                    </div>
                </div>

                <!-- Appointment: Assign to (first step) -->
                <div v-if="form.activity_type === 'appointment'" class="p-4 sm:p-5 bg-blue-50/80 rounded-xl border border-blue-200 space-y-4">
                    <h3 class="font-semibold text-blue-900 flex flex-col sm:flex-row sm:items-center gap-1">
                        <span>📅 Appointment Details</span>
                        <span class="text-xs font-normal text-blue-700">(Email sent to customer, admin & assignee)</span>
                    </h3>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Assign to (who will attend) *</label>
                        <select
                            v-model="form.assigned_user_id"
                            required
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        >
                            <option value="">Select team member...</option>
                            <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role?.name || '—' }})</option>
                        </select>
                        <p class="text-xs text-blue-700 mt-1">The assigned person will receive an email with the appointment time and notes.</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Appointment Date *</label>
                            <input
                                v-model="form.appointment_date"
                                type="date"
                                :required="form.activity_type === 'appointment'"
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Appointment Time *</label>
                            <input
                                v-model="form.appointment_time"
                                type="time"
                                :required="form.activity_type === 'appointment'"
                                class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                    </div>
                    <p class="text-xs text-blue-700">
                        ℹ️ Confirmation emails will be sent to the customer and admin notification email.
                    </p>
                </div>

                <!-- Activity Date & Time (for non-appointments) -->
                <div v-if="form.activity_type !== 'appointment'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Activity Date & Time *</label>
                        <input
                            v-model="form.activity_at"
                            type="datetime-local"
                            required
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Duration (minutes)</label>
                        <input
                            v-model.number="form.duration"
                            type="number"
                            min="1"
                            placeholder="e.g., 15"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>
                </div>

                <!-- Notes -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Notes/Summary *</label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        required
                        class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                        placeholder="What was discussed? What was the outcome?"
                    />
                </div>

                <!-- Outcome -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Outcome</label>
                    <div class="flex flex-wrap gap-2">
                        <label
                            v-for="outcome in outcomes"
                            :key="outcome.value"
                            class="flex items-center gap-2 px-3 py-2.5 border-2 rounded-xl cursor-pointer transition-all min-h-[44px] touch-manipulation"
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
                </div>

                <!-- Schedule Next Follow-up -->
                <div class="border-t border-slate-200 pt-4">
                    <div class="flex items-center gap-2 mb-3">
                        <input
                            type="checkbox"
                            v-model="form.schedule_followup"
                            id="schedule_followup_modal"
                            class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                        />
                        <label for="schedule_followup_modal" class="text-sm font-medium text-slate-700">
                            Schedule next follow-up
                        </label>
                    </div>

                    <div v-if="form.schedule_followup" class="p-4 bg-amber-50 rounded-xl border border-amber-200">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Next Follow-up Date *</label>
                        <input
                            v-model="form.next_follow_up_at"
                            type="datetime-local"
                            :required="form.schedule_followup"
                            class="w-full px-4 py-2.5 border border-slate-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500"
                        />
                    </div>

                    <!-- Quick Schedule Buttons -->
                    <div v-if="form.schedule_followup" class="flex flex-wrap gap-2 mt-3">
                        <button
                            type="button"
                            @click="setQuickFollowUp(1)"
                            class="px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 touch-manipulation"
                        >
                            Tomorrow
                        </button>
                        <button
                            type="button"
                            @click="setQuickFollowUp(2)"
                            class="px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 touch-manipulation"
                        >
                            In 2 days
                        </button>
                        <button
                            type="button"
                            @click="setQuickFollowUp(3)"
                            class="px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 touch-manipulation"
                        >
                            In 3 days
                        </button>
                        <button
                            type="button"
                            @click="setQuickFollowUp(7)"
                            class="px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 touch-manipulation"
                        >
                            In 1 week
                        </button>
                    </div>
                </div>

                <!-- Error Message -->
                <div v-if="error" class="text-sm text-red-600 bg-red-50 p-4 rounded-xl border border-red-200">
                    {{ error }}
                </div>

                <!-- Buttons -->
                <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-slate-200">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="w-full sm:w-auto px-4 py-2.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 flex items-center justify-center gap-2 font-medium"
                    >
                        <svg v-if="loading" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ loading ? 'Saving...' : 'Log Activity' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();
const users = ref([]);

const props = defineProps({
    lead: {
        type: Object,
        required: true,
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
    { value: 'positive', label: 'Positive', activeClass: 'border-green-500 bg-green-50 text-green-700' },
    { value: 'neutral', label: 'Neutral', activeClass: 'border-blue-500 bg-blue-50 text-blue-700' },
    { value: 'negative', label: 'Negative', activeClass: 'border-red-500 bg-red-50 text-red-700' },
    { value: 'no_answer', label: 'No Answer', activeClass: 'border-slate-500 bg-slate-100 text-slate-700' },
];

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

