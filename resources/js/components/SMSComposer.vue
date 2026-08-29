<template>
    <BaseCard class="overflow-hidden">
        <template #header>
            <h3 class="card-title flex items-center gap-2">
                <DevicePhoneMobileIcon class="icon text-primary-600" aria-hidden="true" />
                SMS
            </h3>
        </template>

        <div class="mb-3">
            <label class="form-label" for="smscomposer-sms-number">SMS number</label>
            <input id="smscomposer-sms-number"
                v-model="smsNumber"
                type="text"
                class="form-input"
                placeholder="Enter SMS number"
            />
        </div>

        <!-- Template choice -->
        <div class="mb-3">
            <label class="form-label" for="smscomposer-sms-template">SMS template</label>
            <select id="smscomposer-sms-template"
                v-model="selectedTemplateId"
                class="form-select"
                @change="onTemplateSelect"
            >
                <option value="">— Write your own —</option>
                <option v-for="t in messageTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
        </div>

        <!-- Message: only when writing your own; when template chosen, show read-only preview -->
        <div class="mb-3" v-if="!selectedTemplateId">
            <label class="form-label" for="smscomposer-message">Message</label>
            <textarea id="smscomposer-message"
                v-model="message"
                rows="4"
                maxlength="500"
                class="form-textarea"
                placeholder="Type your SMS message (max 500 characters)..."
            />
            <div class="flex justify-between mt-1 text-xs text-slate-500">
                <span>{{ message.length }} / 500</span>
                <span v-if="message.length > 160">{{ Math.ceil(message.length / 160) }} SMS</span>
            </div>
        </div>
        <template v-else>
            <p class="mb-3 text-sm text-slate-500">Message will be sent from the selected template. Switch to "Write your own" to type a custom message.</p>
        </template>

        <div class="flex flex-wrap gap-2 mb-4">
            <BaseButton
                variant="primary"
                :disabled="!selectedTemplateId && !message?.trim()"
                :loading="sending"
                @click="sendMessage"
            >
                <template #icon>
                    <PaperAirplaneIcon class="icon-sm" aria-hidden="true" />
                </template>
                {{ sending ? 'Sending...' : 'Send SMS' }}
            </BaseButton>
        </div>

        <div v-if="error" class="callout callout-danger mb-3" role="alert">
            {{ error }}
        </div>

        <!-- SMS log -->
        <div v-if="showInlineLogs && logs && logs.length > 0" class="mt-4 pt-4 border-t border-slate-200">
            <h4 class="text-eyebrow text-slate-600 uppercase mb-2">Sent SMS</h4>
            <ul class="space-y-2 max-h-48 overflow-y-auto">
                <li
                    v-for="log in logs"
                    :key="log.id"
                    class="text-sm p-2 rounded-control bg-white border border-slate-100"
                >
                    <div class="text-slate-800 line-clamp-2">{{ log.message || '(No message)' }}</div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 mt-1">
                        <span>{{ formatLogDate(log.created_at) }}</span>
                        <BaseBadge :status="log.status">{{ formatCommLogStatus(log.status) }}</BaseBadge>
                    </div>
                </li>
            </ul>
        </div>
        <div v-else-if="showInlineLogs" class="mt-4 pt-4 border-t border-slate-200">
            <EmptyState heading="No SMS sent yet." description="Messages you send from here are logged in this list.">
                <template #icon>
                    <DevicePhoneMobileIcon class="icon" aria-hidden="true" />
                </template>
            </EmptyState>
        </div>
    </BaseCard>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { DevicePhoneMobileIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline';
import { BaseBadge, BaseButton, BaseCard, EmptyState } from '@/components/base';
import { formatCommLogStatus } from '@/utils/displayFormat';

const props = defineProps({
    customer: { type: Object, required: true },
    leadId: { type: [Number, String], default: null },
    logs: { type: Array, default: () => [] },
    showInlineLogs: { type: Boolean, default: true },
});

const emit = defineEmits(['sent', 'saved']);

const smsNumber = ref('');
const message = ref('');
const selectedTemplateId = ref('');
const sending = ref(false);
const savingNumber = ref(false);
const error = ref(null);
const messageTemplates = ref([]);

function defaultSmsDestination(c) {
    if (!c) return '';
    return (c.sms_number || c.phone || c.whatsapp_number || '').trim();
}

onMounted(() => {
    smsNumber.value = defaultSmsDestination(props.customer);
    loadTemplates();
});

watch(() => props.customer, (newCustomer) => {
    if (newCustomer) {
        smsNumber.value = defaultSmsDestination(newCustomer);
    }
}, { deep: true });

function formatLogDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'short' });
}

async function loadTemplates() {
    try {
        const { data } = await axios.get('/api/message-templates-for-sending');
        messageTemplates.value = data || [];
    } catch (_) {
        messageTemplates.value = [];
    }
}

function onTemplateSelect() {
    if (!selectedTemplateId.value) {
        message.value = '';
        return;
    }
    const t = messageTemplates.value.find(x => x.id == selectedTemplateId.value);
    if (t && t.message) {
        message.value = t.message;
    }
}

async function saveSMSNumber() {
    if (!smsNumber.value.trim()) {
        error.value = 'Please enter an SMS number';
        return;
    }
    savingNumber.value = true;
    error.value = null;
    try {
        await axios.put(`/api/customers/${props.customer.id}/contact-methods`, {
            sms_number: smsNumber.value.trim(),
        });
        emit('saved');
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to save SMS number';
    } finally {
        savingNumber.value = false;
    }
}

async function sendMessage() {
    if (!message.value.trim()) {
        error.value = 'Please enter a message';
        return;
    }
    sending.value = true;
    error.value = null;
    try {
        await axios.post('/api/communications', {
            customer_id: props.customer.id,
            lead_id: props.leadId,
            channel: 'sms',
            message: message.value.trim(),
            to_number: smsNumber.value.trim() || undefined,
        });
        message.value = '';
        selectedTemplateId.value = '';
        emit('sent');
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to send SMS';
    } finally {
        sending.value = false;
    }
}
</script>
