<template>
    <div class="bg-slate-50/80 rounded-xl border border-slate-200 p-4 sm:p-5">
        <h3 class="text-base font-semibold text-slate-800 mb-3 flex items-center gap-2">
            <span class="text-blue-600">📧</span> Email
        </h3>

        <!-- Recipient -->
        <div class="mb-3">
            <label class="block text-xs font-medium text-slate-600 mb-1">Send to</label>
            <select
                v-model="sendToEmail"
                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
                <option :value="primaryEmail">{{ primaryEmail || 'Primary email' }}</option>
                <option v-if="secondaryEmail" :value="secondaryEmail">Secondary</option>
            </select>
        </div>

        <!-- Template choice -->
        <div class="mb-3">
            <label class="block text-xs font-medium text-slate-600 mb-1">Email template</label>
            <select
                v-model="selectedTemplateId"
                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                @change="onTemplateSelect"
            >
                <option value="">— Write your own —</option>
                <option v-for="t in emailTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
        </div>

        <!-- Only show subject/message when writing your own -->
        <template v-if="!selectedTemplateId">
            <div class="mb-3">
                <label class="block text-xs font-medium text-slate-600 mb-1">Subject</label>
                <input
                    v-model="subject"
                    type="text"
                    class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Email subject"
                />
            </div>
            <div class="mb-3">
                <label class="block text-xs font-medium text-slate-600 mb-1">Message</label>
                <textarea
                    v-model="message"
                    rows="4"
                    class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Type your email message..."
                />
            </div>
        </template>
        <p v-else class="mb-3 text-sm text-slate-500">Content will be sent from the selected template. No need to type subject or message.</p>

        <div class="flex flex-wrap gap-2 mb-4">
            <button
                v-if="selectedTemplateId"
                @click="sendWithTemplate"
                :disabled="sending"
                class="px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
            >
                {{ sending ? 'Sending...' : 'Send with template' }}
            </button>
            <button
                v-else
                @click="sendMessage"
                :disabled="!message?.trim() || !subject?.trim() || sending"
                class="px-4 py-2 text-sm font-medium bg-slate-700 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
            >
                {{ sending ? 'Sending...' : 'Send email' }}
            </button>
        </div>

        <div v-if="error" class="mb-3 text-sm text-red-600 bg-red-50 p-2 rounded">
            {{ error }}
        </div>

        <!-- Email log -->
        <div v-if="showInlineLogs && logs && logs.length > 0" class="mt-4 pt-4 border-t border-slate-200">
            <h4 class="text-xs font-semibold text-slate-600 uppercase tracking-wide mb-2">Sent emails</h4>
            <ul class="space-y-2 max-h-48 overflow-y-auto">
                <li
                    v-for="log in logs"
                    :key="log.id"
                    class="text-sm p-2 rounded bg-white border border-slate-100"
                >
                    <div class="font-medium text-slate-800 truncate">{{ log.subject || '(No subject)' }}</div>
                    <div class="text-xs text-slate-500 mt-0.5">{{ formatLogDate(log.created_at) }} · {{ formatCommLogStatus(log.status) }}</div>
                    <p v-if="log.message" class="text-xs text-slate-600 mt-1 line-clamp-2">{{ stripHtml(log.message) }}</p>
                </li>
            </ul>
        </div>
        <p v-else-if="showInlineLogs" class="text-xs text-slate-500 mt-4 pt-4 border-t border-slate-200">No emails sent yet.</p>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { formatCommLogStatus } from '@/utils/displayFormat';

const props = defineProps({
    customer: { type: Object, required: true },
    leadId: { type: [Number, String], default: null },
    logs: { type: Array, default: () => [] },
    showInlineLogs: { type: Boolean, default: true },
});

const emit = defineEmits(['sent', 'saved']);

const primaryEmail = ref('');
const secondaryEmail = ref('');
const sendToEmail = ref('');
const selectedTemplateId = ref('');
const subject = ref('');
const message = ref('');
const sending = ref(false);
const savingEmails = ref(false);
const error = ref(null);
const emailTemplates = ref([]);

onMounted(() => {
    primaryEmail.value = props.customer?.email || '';
    secondaryEmail.value = props.customer?.email_secondary || '';
    sendToEmail.value = props.customer?.email || '';
    loadTemplates();
});

watch(() => props.customer, (newCustomer) => {
    if (newCustomer) {
        primaryEmail.value = newCustomer.email || '';
        secondaryEmail.value = newCustomer.email_secondary || '';
        sendToEmail.value = newCustomer.email || '';
    }
}, { deep: true });

watch(() => props.logs, (v) => {}, { deep: true });

function formatLogDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'short' });
}

function stripHtml(html) {
    if (!html || typeof html !== 'string') return '';
    const div = document.createElement('div');
    div.innerHTML = html;
    return div.textContent || div.innerText || '';
}

async function loadTemplates() {
    try {
        const { data } = await axios.get('/api/email-templates-for-sending');
        emailTemplates.value = data || [];
    } catch (_) {
        emailTemplates.value = [];
    }
}

function onTemplateSelect() {
    if (!selectedTemplateId.value) return;
    const t = emailTemplates.value.find(x => x.id == selectedTemplateId.value);
    if (t) {
        subject.value = t.subject || '';
        message.value = '';
    }
}

async function sendWithTemplate() {
    if (!selectedTemplateId.value) return;
    sending.value = true;
    error.value = null;
    try {
        await axios.post(`/api/customers/${props.customer.id}/send-template-email`, {
            template_id: selectedTemplateId.value,
            lead_id: props.leadId || undefined,
        });
        emit('sent');
        selectedTemplateId.value = '';
        subject.value = '';
        message.value = '';
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to send email';
    } finally {
        sending.value = false;
    }
}

async function saveEmails() {
    savingEmails.value = true;
    error.value = null;
    try {
        await axios.put(`/api/customers/${props.customer.id}`, {
            email: primaryEmail.value.trim() || null,
            email_secondary: secondaryEmail.value.trim() || null,
        });
        emit('saved');
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to save email addresses';
    } finally {
        savingEmails.value = false;
    }
}

async function sendMessage() {
    if (!message.value.trim() || !subject.value.trim()) {
        error.value = 'Please enter both subject and message';
        return;
    }
    sending.value = true;
    error.value = null;
    try {
        await axios.post('/api/communications', {
            customer_id: props.customer.id,
            lead_id: props.leadId,
            channel: 'email',
            message: message.value.trim(),
            subject: subject.value.trim(),
            to_email: sendToEmail.value.trim() || undefined,
        });
        message.value = '';
        subject.value = '';
        emit('sent');
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to send email';
    } finally {
        sending.value = false;
    }
}
</script>
