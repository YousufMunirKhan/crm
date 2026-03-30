<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">
                        {{ template?.id ? 'Edit WhatsApp Template' : 'Create WhatsApp Template' }}
                    </h2>
                    <p class="text-sm text-slate-500 mt-1">Create WhatsApp message template</p>
                </div>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="saveTemplate" class="flex-1 overflow-y-auto p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Template Name *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Appointment Reminder"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Category *</label>
                    <select
                        v-model="form.category"
                        required
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="appointment_reminder">Appointment Reminder</option>
                        <option value="follow_up">Follow-up</option>
                        <option value="payment_reminder">Payment Reminder</option>
                        <option value="thank_you">Thank You</option>
                        <option value="custom">Custom</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Message *</label>
                    <textarea
                        v-model="form.message"
                        rows="6"
                        required
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Hello {{customer_name}}, your appointment is scheduled for {{appointment_date}} at {{appointment_time}}."
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Media (Optional)</label>
                    <div class="space-y-2">
                        <select
                            v-model="form.media_type"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="">No Media</option>
                            <option value="image">Image</option>
                            <option value="video">Video</option>
                            <option value="document">Document</option>
                        </select>
                        <div v-if="form.media_type">
                            <input
                                ref="mediaInput"
                                type="file"
                                @change="handleMediaUpload"
                                :accept="getMediaAccept()"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <div v-if="form.media_url" class="mt-2">
                                <img
                                    v-if="form.media_type === 'image'"
                                    :src="form.media_url"
                                    alt="Preview"
                                    class="max-w-full h-32 object-contain rounded"
                                />
                                <div v-else class="p-3 bg-slate-100 rounded">
                                    📎 {{ form.media_type }} uploaded
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Available Variables</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="variable in variables"
                            :key="variable"
                            type="button"
                            @click="insertVariable(variable)"
                            class="px-3 py-1 text-xs bg-slate-100 border border-slate-300 rounded hover:bg-slate-200"
                        >
                            {{ variable }}
                        </button>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        id="whatsapp_active"
                        class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                    />
                    <label for="whatsapp_active" class="text-sm text-slate-700">Active</label>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="saving"
                        class="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                    >
                        {{ saving ? 'Saving...' : 'Save Template' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import axios from 'axios';
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
    const textarea = document.querySelector('textarea');
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

