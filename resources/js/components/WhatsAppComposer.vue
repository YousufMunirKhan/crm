<template>
    <div class="bg-slate-50/80 rounded-xl border border-slate-200 p-4 sm:p-5">
        <h3 class="text-base font-semibold text-slate-800 mb-3 flex items-center gap-2">
            <span class="text-emerald-600">💬</span> WhatsApp
        </h3>

        <div class="mb-3">
            <label class="block text-xs font-medium text-slate-600 mb-1">WhatsApp number</label>
            <input
                v-model="whatsappNumber"
                type="text"
                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                placeholder="Enter WhatsApp number"
            />
        </div>

        <!-- Template choice (reuse message templates for WhatsApp body) -->
        <div class="mb-3">
            <label class="block text-xs font-medium text-slate-600 mb-1">Message template</label>
            <select
                v-model="selectedTemplateId"
                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                @change="onTemplateSelect"
            >
                <option value="">— Write your own —</option>
                <option v-for="t in messageTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
        </div>

        <!-- Message: only when writing your own; when template chosen, no need to type -->
        <div class="mb-3" v-if="!selectedTemplateId">
            <label class="block text-xs font-medium text-slate-600 mb-1">Message</label>
            <textarea
                v-model="message"
                rows="4"
                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                placeholder="Type your WhatsApp message..."
            />
        </div>
        <p v-else class="mb-3 text-sm text-slate-500">Message will be sent from the selected template. Switch to "Write your own" to type a custom message.</p>

        <div class="flex flex-wrap gap-2 mb-4">
            <button
                @click="sendMessage"
                :disabled="sending || (!selectedTemplateId && !message?.trim())"
                class="px-4 py-2 text-sm font-medium bg-emerald-600 text-white rounded-lg hover:bg-emerald-700 disabled:opacity-50"
            >
                {{ sending ? 'Sending...' : 'Send WhatsApp' }}
            </button>
        </div>

        <div v-if="error" class="mb-3 text-sm text-red-600 bg-red-50 p-2 rounded">
            {{ error }}
        </div>

        <!-- WhatsApp log -->
        <div v-if="logs && logs.length > 0" class="mt-4 pt-4 border-t border-slate-200">
            <h4 class="text-xs font-semibold text-slate-600 uppercase tracking-wide mb-2">Sent WhatsApp</h4>
            <ul class="space-y-2 max-h-48 overflow-y-auto">
                <li
                    v-for="log in logs"
                    :key="log.id"
                    class="text-sm p-2 rounded bg-white border border-slate-100"
                >
                    <div class="text-slate-800 line-clamp-2">{{ log.message || '(No message)' }}</div>
                    <div class="text-xs text-slate-500 mt-0.5">{{ formatLogDate(log.created_at) }} · {{ log.status }}</div>
                </li>
            </ul>
        </div>
        <p v-else class="text-xs text-slate-500 mt-4 pt-4 border-t border-slate-200">No WhatsApp messages sent yet.</p>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
    customer: { type: Object, required: true },
    leadId: { type: [Number, String], default: null },
    logs: { type: Array, default: () => [] },
});

const emit = defineEmits(['sent', 'saved']);

const whatsappNumber = ref('');
const message = ref('');
const selectedTemplateId = ref('');
const sending = ref(false);
const savingNumber = ref(false);
const error = ref(null);
const messageTemplates = ref([]);

onMounted(() => {
    whatsappNumber.value = props.customer?.whatsapp_number || props.customer?.phone || '';
    loadTemplates();
});

watch(() => props.customer, (newCustomer) => {
    if (newCustomer) {
        whatsappNumber.value = newCustomer.whatsapp_number || newCustomer.phone || '';
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

async function saveWhatsAppNumber() {
    if (!whatsappNumber.value.trim()) {
        error.value = 'Please enter a WhatsApp number';
        return;
    }
    savingNumber.value = true;
    error.value = null;
    try {
        await axios.put(`/api/customers/${props.customer.id}/contact-methods`, {
            whatsapp_number: whatsappNumber.value.trim(),
        });
        emit('saved');
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to save WhatsApp number';
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
            channel: 'whatsapp',
            message: message.value.trim(),
            to_number: whatsappNumber.value.trim() || undefined,
        });
        message.value = '';
        selectedTemplateId.value = '';
        emit('sent');
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to send WhatsApp message';
    } finally {
        sending.value = false;
    }
}
</script>
