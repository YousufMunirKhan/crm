<template>
    <BaseModal
        :model-value="true"
        :title="template?.id ? 'Edit SMS Template' : 'Create SMS Template'"
        description="Create SMS message template"
        size="md"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <form id="sms-template-form" class="space-y-4" @submit.prevent="saveTemplate">
            <div>
                <label class="form-label" for="smstemplatemodal-template-name">Template Name *</label>
                <input id="smstemplatemodal-template-name"
                    v-model="form.name"
                    type="text"
                    required
                    class="form-input"
                    placeholder="Appointment Reminder"
                />
            </div>

            <div>
                <label class="form-label" for="smstemplatemodal-category">Category *</label>
                <select id="smstemplatemodal-category"
                    v-model="form.category"
                    required
                    class="form-select"
                >
                    <option value="appointment_reminder">Appointment Reminder</option>
                    <option value="follow_up">Follow-up</option>
                    <option value="payment_reminder">Payment Reminder</option>
                    <option value="thank_you">Thank You</option>
                    <option value="custom">Custom</option>
                </select>
            </div>

            <div>
                <label class="form-label" for="smstemplatemodal-message">Message *</label>
                <textarea id="smstemplatemodal-message"
                    ref="messageInput"
                    v-model="form.message"
                    rows="6"
                    required
                    class="form-textarea"
                    placeholder="Hello {{customer_name}}, your appointment is scheduled for {{appointment_date}} at {{appointment_time}}."
                />
                <p class="form-hint">
                    {{ form.message.length }} characters (SMS limit: 160 characters)
                </p>
            </div>

            <div>
                <p id="sms-variables-label" class="form-label">Available Variables</p>
                <div class="flex flex-wrap gap-2" role="group" aria-labelledby="sms-variables-label">
                    <BaseButton
                        v-for="variable in variables"
                        :key="variable"
                        variant="outline"
                        size="sm"
                        @click="insertVariable(variable)"
                    >
                        {{ variable }}
                    </BaseButton>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input
                    v-model="form.is_active"
                    type="checkbox"
                    id="sms_active"
                    class="form-checkbox"
                />
                <label for="sms_active" class="text-sm text-slate-700">Active</label>
            </div>
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="$emit('close')">Cancel</BaseButton>
            <BaseButton
                variant="primary"
                type="submit"
                form="sms-template-form"
                block-mobile
                :loading="saving"
            >
                {{ saving ? 'Saving...' : 'Save Template' }}
            </BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';
import { BaseButton, BaseModal } from '@/components/base';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const props = defineProps({
    template: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const messageInput = ref(null);

const variables = [
    '{{customer_name}}',
    '{{customer_phone}}',
    '{{appointment_date}}',
    '{{appointment_time}}',
    '{{company_name}}',
    '{{company_phone}}',
    '{{invoice_number}}',
    '{{invoice_amount}}',
];

const form = reactive({
    name: '',
    category: 'custom',
    message: '',
    is_active: true,
});

if (props.template) {
    form.name = props.template.name || '';
    form.category = props.template.category || 'custom';
    form.message = props.template.message || '';
    form.is_active = props.template.is_active !== false;
}

const insertVariable = (variable) => {
    const textarea = messageInput.value;
    if (textarea) {
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        form.message = form.message.substring(0, start) + variable + ' ' + form.message.substring(end);
        textarea.focus();
        textarea.setSelectionRange(start + variable.length + 1, start + variable.length + 1);
    }
};

const saveTemplate = async () => {
    saving.value = true;
    try {
        if (props.template?.id) {
            await axios.put(`/api/message-templates/${props.template.id}`, form);
            toast.success('SMS template updated successfully');
        } else {
            await axios.post('/api/message-templates', form);
            toast.success('SMS template created successfully');
        }
        emit('saved');
        emit('close');
    } catch (error) {
        console.error('Failed to save template:', error);
        toast.error(error.response?.data?.message || 'Failed to save template');
    } finally {
        saving.value = false;
    }
};
</script>
