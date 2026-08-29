<template>
    <BaseModal
        :model-value="true"
        :title="template?.id ? 'Edit WhatsApp Template' : 'Create WhatsApp Template'"
        description="Create WhatsApp message template"
        size="md"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <form id="whatsapp-template-form" class="space-y-4" @submit.prevent="saveTemplate">
            <div>
                <label class="form-label" for="whatsapptemplatemodal-template-name">Template Name *</label>
                <input id="whatsapptemplatemodal-template-name"
                    v-model="form.name"
                    type="text"
                    required
                    class="form-input"
                    placeholder="Appointment Reminder"
                />
            </div>

            <div>
                <label class="form-label" for="whatsapptemplatemodal-category">Category *</label>
                <select id="whatsapptemplatemodal-category"
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
                <label class="form-label" for="whatsapptemplatemodal-message">Message *</label>
                <textarea id="whatsapptemplatemodal-message"
                    ref="messageInput"
                    v-model="form.message"
                    rows="6"
                    required
                    class="form-textarea"
                    placeholder="Hello {{customer_name}}, your appointment is scheduled for {{appointment_date}} at {{appointment_time}}."
                />
            </div>

            <div class="space-y-2">
                <label class="form-label" for="whatsapptemplatemodal-media-type">Media (Optional)</label>
                <select
                    id="whatsapptemplatemodal-media-type"
                    v-model="form.media_type"
                    class="form-select"
                >
                    <option value="">No Media</option>
                    <option value="image">Image</option>
                    <option value="video">Video</option>
                    <option value="document">Document</option>
                </select>
                <div v-if="form.media_type">
                    <label class="form-label" for="whatsapptemplatemodal-media-file">Media file</label>
                    <input
                        id="whatsapptemplatemodal-media-file"
                        ref="mediaInput"
                        type="file"
                        @change="handleMediaUpload"
                        :accept="getMediaAccept()"
                        class="form-input"
                    />
                    <div v-if="form.media_url" class="mt-2">
                        <img
                            v-if="form.media_type === 'image'"
                            :src="form.media_url"
                            alt="Preview"
                            class="max-w-full h-32 object-contain rounded"
                        />
                        <div v-else class="flex items-center gap-2 p-3 bg-slate-100 rounded text-sm text-slate-700">
                            <DocumentTextIcon class="icon-sm" aria-hidden="true" />
                            <span>{{ form.media_type }} uploaded</span>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <p id="whatsapp-variables-label" class="form-label">Available Variables</p>
                <div class="flex flex-wrap gap-2" role="group" aria-labelledby="whatsapp-variables-label">
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
                    id="whatsapp_active"
                    class="form-checkbox"
                />
                <label for="whatsapp_active" class="text-sm text-slate-700">Active</label>
            </div>
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="$emit('close')">Cancel</BaseButton>
            <BaseButton
                variant="primary"
                type="submit"
                form="whatsapp-template-form"
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
import { DocumentTextIcon } from '@heroicons/vue/24/outline';
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
const mediaInput = ref(null);
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
    media_url: '',
    media_type: '',
    is_active: true,
});

if (props.template) {
    form.name = props.template.name || '';
    form.category = props.template.category || 'custom';
    form.message = props.template.message || '';
    form.media_url = props.template.media_url || '';
    form.media_type = props.template.media_type || '';
    form.is_active = props.template.is_active !== false;
}

const getMediaAccept = () => {
    if (form.media_type === 'image') return 'image/*';
    if (form.media_type === 'video') return 'video/*';
    if (form.media_type === 'document') return '.pdf,.doc,.docx';
    return '*';
};

const handleMediaUpload = async () => {
    const file = mediaInput.value?.files[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('media', file);

    try {
        const response = await axios.post('/api/whatsapp-templates/upload-media', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        form.media_url = response.data.url;
        form.media_type = response.data.type;
        toast.success('Media uploaded successfully');
    } catch (error) {
        console.error('Failed to upload media:', error);
        toast.error('Failed to upload media');
    }
};

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
            await axios.put(`/api/whatsapp-templates/${props.template.id}`, form);
            toast.success('WhatsApp template updated successfully');
        } else {
            await axios.post('/api/whatsapp-templates', form);
            toast.success('WhatsApp template created successfully');
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
