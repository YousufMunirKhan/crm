<template>
    <BaseCard class="overflow-hidden">
        <template #header>
            <h3 class="card-title flex items-center gap-2">
                <EnvelopeIcon class="icon text-primary-600" aria-hidden="true" />
                Email
            </h3>
        </template>

        <!-- Recipient -->
        <div class="mb-3">
            <label class="form-label" for="emailcomposer-send-to">Send to</label>
            <select id="emailcomposer-send-to"
                v-model="sendToEmail"
                class="form-select"
            >
                <option :value="primaryEmail">{{ primaryEmail || 'Primary email' }}</option>
                <option v-if="secondaryEmail" :value="secondaryEmail">Secondary</option>
            </select>
        </div>

        <!-- Template choice -->
        <div class="mb-3">
            <label class="form-label" for="emailcomposer-email-template">Email template</label>
            <select id="emailcomposer-email-template"
                v-model="selectedTemplateId"
                class="form-select"
                @change="onTemplateSelect"
            >
                <option value="">— Write your own —</option>
                <option v-for="t in emailTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
        </div>

        <!-- Only show subject/message when writing your own -->
        <template v-if="!selectedTemplateId">
            <div class="mb-3">
                <label class="form-label" for="emailcomposer-subject">Subject</label>
                <input id="emailcomposer-subject"
                    v-model="subject"
                    type="text"
                    class="form-input"
                    placeholder="Email subject"
                />
            </div>
            <div class="mb-3">
                <label class="form-label" for="emailcomposer-message">Message</label>
                <textarea id="emailcomposer-message"
                    v-model="message"
                    rows="4"
                    class="form-textarea"
                    placeholder="Type your email message..."
                />
            </div>
        </template>
        <p v-else class="mb-3 text-sm text-slate-500">Content will be sent from the selected template. No need to type subject or message.</p>

        <div class="flex flex-wrap gap-2 mb-4">
            <BaseButton
                v-if="selectedTemplateId"
                variant="primary"
                :loading="sending"
                @click="sendWithTemplate"
            >
                <template #icon>
                    <PaperAirplaneIcon class="icon-sm" aria-hidden="true" />
                </template>
                {{ sending ? 'Sending...' : 'Send with template' }}
            </BaseButton>
            <BaseButton
                v-else
                variant="primary"
                :disabled="!message?.trim() || !subject?.trim()"
                :loading="sending"
                @click="sendMessage"
            >
                <template #icon>
                    <PaperAirplaneIcon class="icon-sm" aria-hidden="true" />
                </template>
                {{ sending ? 'Sending...' : 'Send email' }}
            </BaseButton>
        </div>

        <div v-if="error" class="callout callout-danger mb-3" role="alert">
            {{ error }}
        </div>

        <!-- Email log -->
        <div v-if="showInlineLogs && logs && logs.length > 0" class="mt-4 pt-4 border-t border-slate-200">
            <h4 class="text-eyebrow text-slate-600 uppercase mb-2">Sent emails</h4>
            <ul class="space-y-2 max-h-48 overflow-y-auto">
                <li
                    v-for="log in logs"
                    :key="log.id"
                    class="text-sm p-2 rounded-control bg-white border border-slate-100"
                >
                    <div class="font-medium text-slate-800 truncate">{{ log.subject || '(No subject)' }}</div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 mt-1">
                        <span>{{ formatLogDate(log.created_at) }}</span>
                        <BaseBadge :status="log.status">{{ formatCommLogStatus(log.status) }}</BaseBadge>
                    </div>
                    <p v-if="log.message" class="text-xs text-slate-600 mt-1 line-clamp-2">{{ stripHtml(log.message) }}</p>
                </li>
            </ul>
        </div>
        <div v-else-if="showInlineLogs" class="mt-4 pt-4 border-t border-slate-200">
            <EmptyState heading="No emails sent yet." description="Emails you send from here are logged in this list.">
                <template #icon>
                    <EnvelopeIcon class="icon" aria-hidden="true" />
                </template>
            </EmptyState>
        </div>
    </BaseCard>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { EnvelopeIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline';
import { BaseBadge, BaseButton, BaseCard, EmptyState } from '@/components/base';
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
