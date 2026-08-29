<template>
    <BaseCard class="overflow-hidden">
        <template #header>
            <h3 class="card-title flex items-center gap-2">
                <ChatBubbleLeftRightIcon class="icon text-primary-600" aria-hidden="true" />
                WhatsApp
            </h3>
        </template>

        <p class="text-xs text-slate-500 mb-3 leading-relaxed">
            Outbound sends use your connected business number. <strong>Replies</strong> show below as <span class="text-primary-800 font-medium">Received</span> once Meta posts to your
            <code class="kbd">/api/whatsapp/webhook</code>
            (HTTPS). This page refreshes the log every ~20s while open, when you return to the tab, or when you click Refresh.
        </p>

        <div class="mb-3">
            <label class="form-label" for="whatsappcomposer-whatsapp-number">WhatsApp number</label>
            <input id="whatsappcomposer-whatsapp-number"
                v-model="whatsappNumber"
                type="text"
                class="form-input"
                placeholder="Enter WhatsApp number"
            />
        </div>

        <div
            class="callout mb-3 text-xs"
            :class="withinWindow ? 'callout-success' : 'callout-warning'"
            role="status"
            aria-live="polite"
        >
            {{ windowStatusMessage }}
        </div>

        <!-- Meta-approved WhatsApp template -->
        <div class="mb-3">
            <label class="form-label" for="whatsappcomposer-approved-whatsapp-template">Approved WhatsApp template</label>
            <select id="whatsappcomposer-approved-whatsapp-template"
                v-model="selectedTemplateId"
                class="form-select"
            >
                <option value="">— No template (normal text) —</option>
                <option v-for="t in messageTemplates" :key="t.id" :value="t.id">{{ t.name }}</option>
            </select>
        </div>

        <!-- Message: only when writing your own; when template chosen, no need to type -->
        <div class="mb-3" v-if="!selectedTemplateId">
            <label class="form-label" for="whatsappcomposer-message">Message</label>
            <textarea id="whatsappcomposer-message"
                v-model="message"
                rows="4"
                class="form-textarea"
                placeholder="Type your WhatsApp message..."
            />
        </div>
        <p v-else class="mb-3 text-sm text-slate-500">This will send as an approved template message.</p>

        <div
            v-if="selectedTemplateId && templateHints?.uses_named_parameters && namedKeysCombined.length"
            class="mb-3 rounded-card border border-warning-200 bg-warning-50/80 p-3 space-y-2"
        >
            <div class="text-xs font-semibold text-warning-800">Template variables (CRM auto-fills from customer — override if needed)</div>
            <div
                v-for="key in namedKeysCombined"
                :key="key"
                class="grid grid-cols-1 sm:grid-cols-3 gap-1 sm:gap-2 items-center text-xs"
            >
                <label class="text-slate-600 font-mono truncate sm:text-right sm:pr-2" :for="`whatsappcomposer-template-param-${key}`" :title="key">{{ key }}</label>
                <input
                    :id="`whatsappcomposer-template-param-${key}`"
                    v-model="templateParamValues[key]"
                    type="text"
                    class="form-input sm:col-span-2"
                    :placeholder="templateHints?.suggested_template_params?.[key] || '—'"
                    @input="onTemplateParamInput"
                >
            </div>
        </div>
        <p
            v-else-if="selectedTemplateId && templateHints?.uses_named_parameters && !namedKeysCombined.length"
            class="callout callout-info mb-3 text-xs"
        >
            Named template variables are filled from the customer automatically (e.g. <span class="font-mono">name</span> → customer name).
            If Meta didn’t return variable names, run <strong>Sync from Meta</strong> on WhatsApp Templates. You can still send — defaults apply in the background.
        </p>

        <div
            v-if="selectedTemplateId && (templatePreview || templatePreviewError)"
            class="mb-3 rounded-card border border-slate-200 bg-white p-3 text-xs space-y-2"
        >
            <div class="font-semibold text-slate-700">What the CRM sends (preview)</div>
            <p v-if="templatePreviewError" class="text-warning-800">{{ templatePreviewError }}</p>
            <template v-else-if="templatePreview">
                <p class="text-slate-500">{{ templatePreview.sample_to_note }}</p>
                <div v-if="templatePreview.header_preview" class="text-slate-600">
                    <span class="text-slate-500">Header:</span> {{ templatePreview.header_preview }}
                </div>
                <div class="rounded-control bg-success-50 border border-success-100 px-2 py-2 text-slate-900 whitespace-pre-wrap">
                    {{ templatePreview.body_preview || '—' }}
                </div>
                <details class="text-slate-500">
                    <summary class="cursor-pointer text-slate-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 rounded-sm">Exact Graph JSON</summary>
                    <pre class="mt-1 p-2 bg-slate-900 text-success-100 rounded-control text-[10px] overflow-x-auto max-h-40">{{ JSON.stringify(templatePreview.graph_payload, null, 2) }}</pre>
                </details>
            </template>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            <BaseButton
                variant="success"
                :disabled="(!selectedTemplateId && !message?.trim()) || (!withinWindow && !selectedTemplateId)"
                :loading="sending"
                @click="sendMessage"
            >
                <template #icon>
                    <PaperAirplaneIcon class="icon-sm" aria-hidden="true" />
                </template>
                {{ sending ? 'Sending...' : 'Send WhatsApp' }}
            </BaseButton>
        </div>

        <div v-if="error" class="callout callout-danger mb-3" role="alert">
            {{ error }}
        </div>
        <div
            v-if="metaError && (metaError.code != null || metaError.message || metaError.fbtrace_id)"
            class="callout callout-danger mb-3 text-xs font-mono space-y-1"
            role="alert"
        >
            <div class="font-sans font-semibold">Meta API error</div>
            <div v-if="metaError.http_status != null">HTTP {{ metaError.http_status }}</div>
            <div v-if="metaError.code != null">code: {{ metaError.code }}</div>
            <div v-if="metaError.error_subcode != null">error_subcode: {{ metaError.error_subcode }}</div>
            <div v-if="metaError.type">type: {{ metaError.type }}</div>
            <div v-if="metaError.message" class="whitespace-pre-wrap break-words">{{ metaError.message }}</div>
            <div v-if="metaError.fbtrace_id" class="text-slate-600">fbtrace_id: {{ metaError.fbtrace_id }}</div>
        </div>

        <!-- WhatsApp log (outbound + inbound from webhook) -->
        <template v-if="showInlineLogs">
        <div v-if="logs && logs.length > 0" class="mt-4 pt-4 border-t border-slate-200">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h4 class="text-eyebrow text-slate-600 uppercase">WhatsApp messages</h4>
                <BaseButton variant="ghost" size="sm" @click="refreshLogs">
                    <template #icon>
                        <ArrowPathIcon class="icon-sm" aria-hidden="true" />
                    </template>
                    Refresh log
                </BaseButton>
            </div>
            <ul class="space-y-2 max-h-56 overflow-y-auto">
                <li
                    v-for="log in logs"
                    :key="log.id"
                    class="text-sm p-2 rounded-control border"
                    :class="log.direction === 'inbound' ? 'bg-primary-50 border-primary-100' : 'bg-white border-slate-100'"
                >
                    <div class="flex items-center gap-2 mb-0.5">
                        <BaseBadge :tone="log.direction === 'inbound' ? 'primary' : 'neutral'">
                            {{ log.direction === 'inbound' ? 'Received' : 'Sent' }}
                        </BaseBadge>
                    </div>
                    <div class="text-slate-800 line-clamp-3 whitespace-pre-wrap">{{ log.message || '(No message)' }}</div>
                    <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 mt-1">
                        <span>{{ formatLogDate(log.created_at) }}</span>
                        <BaseBadge :status="log.status">{{ formatCommLogStatus(log.status) }}</BaseBadge>
                    </div>
                    <p v-if="log.status === 'failed' && log.failure_hint" class="callout callout-warning text-xs px-2 py-1.5 mt-2">
                        {{ log.failure_hint }}
                    </p>
                    <div
                        v-if="log.status === 'failed' && log.meta_error && (log.meta_error.code != null || log.meta_error.message || log.meta_error.fbtrace_id || log.meta_error.http_status != null)"
                        class="callout callout-danger text-xs px-2 py-1.5 mt-2 font-mono space-y-0.5"
                    >
                        <div v-if="log.meta_error.http_status != null">HTTP {{ log.meta_error.http_status }}</div>
                        <div v-if="log.meta_error.code != null">code {{ log.meta_error.code }}</div>
                        <div v-if="log.meta_error.error_subcode != null">subcode {{ log.meta_error.error_subcode }}</div>
                        <div v-if="log.meta_error.message" class="whitespace-pre-wrap break-words">{{ log.meta_error.message }}</div>
                        <div v-if="log.meta_error.fbtrace_id" class="text-slate-600">fbtrace {{ log.meta_error.fbtrace_id }}</div>
                    </div>
                    <p v-else-if="log.status === 'failed' && log.send_error" class="text-xs text-danger-800 font-mono mt-2 whitespace-pre-wrap break-words">
                        {{ log.send_error }}
                    </p>
                </li>
            </ul>
        </div>
        <div v-else class="mt-4 pt-4 border-t border-slate-200">
            <EmptyState
                heading="No WhatsApp messages logged yet"
                description="Replies need the Meta webhook. Stay on this page or click refresh."
            >
                <template #icon>
                    <ChatBubbleLeftRightIcon class="icon" aria-hidden="true" />
                </template>
                <template #action>
                    <BaseButton variant="outline" size="sm" @click="refreshLogs">
                        <template #icon>
                            <ArrowPathIcon class="icon-sm" aria-hidden="true" />
                        </template>
                        Refresh
                    </BaseButton>
                </template>
            </EmptyState>
        </div>
        </template>
    </BaseCard>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { ArrowPathIcon, ChatBubbleLeftRightIcon, PaperAirplaneIcon } from '@heroicons/vue/24/outline';
import { BaseBadge, BaseButton, BaseCard, EmptyState } from '@/components/base';
import { formatCommLogStatus } from '@/utils/displayFormat';

const props = defineProps({
    customer: { type: Object, required: true },
    leadId: { type: [Number, String], default: null },
    logs: { type: Array, default: () => [] },
    showInlineLogs: { type: Boolean, default: true },
});

const emit = defineEmits(['sent', 'saved', 'refreshLogs']);

function refreshLogs() {
    emit('refreshLogs');
}

const whatsappNumber = ref('');
const message = ref('');
const selectedTemplateId = ref('');
const sending = ref(false);
const savingNumber = ref(false);
const error = ref(null);
const metaError = ref(null);
const messageTemplates = ref([]);
const withinWindow = ref(true);
const windowStatusMessage = ref('Checking WhatsApp window...');
const templatePreview = ref(null);
const templatePreviewError = ref(null);
const templateHints = ref(null);
const templateParamValues = ref({});

const namedKeysCombined = computed(() => {
    const h = templateHints.value;
    if (!h) return [];
    const a = [...(h.header_named_keys || []), ...(h.body_named_keys || [])];
    return [...new Set(a)];
});

let previewDebounce = null;
function onTemplateParamInput() {
    if (previewDebounce) clearTimeout(previewDebounce);
    previewDebounce = setTimeout(() => loadTemplatePreview(), 400);
}

onMounted(() => {
    whatsappNumber.value = props.customer?.whatsapp_number || props.customer?.phone || '';
    loadTemplates();
    loadWindowStatus();
});

watch(() => props.customer, (newCustomer) => {
    if (newCustomer) {
        whatsappNumber.value = newCustomer.whatsapp_number || newCustomer.phone || '';
        loadWindowStatus();
    }
}, { deep: true });

watch(() => props.leadId, async () => {
    if (selectedTemplateId.value) {
        await loadTemplateHints();
        await loadTemplatePreview();
    }
});

watch([selectedTemplateId, () => whatsappNumber.value], async () => {
    if (selectedTemplateId.value) {
        await loadTemplateHints();
        await loadTemplatePreview();
    } else {
        templateHints.value = null;
        templateParamValues.value = {};
        templatePreview.value = null;
        templatePreviewError.value = null;
    }
});

function formatLogDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleString('en-GB', { dateStyle: 'short', timeStyle: 'short' });
}

async function loadTemplates() {
    try {
        const { data } = await axios.get('/api/whatsapp/templates', {
            params: { status: 'APPROVED', per_page: 200 },
        });
        messageTemplates.value = data?.data || [];
        if (selectedTemplateId.value) {
            await loadTemplateHints();
        }
        await loadTemplatePreview();
    } catch (_) {
        messageTemplates.value = [];
    }
}

async function loadTemplateHints() {
    templateHints.value = null;
    templateParamValues.value = {};
    if (!selectedTemplateId.value || !props.customer?.id) {
        return;
    }
    const t = messageTemplates.value.find((x) => x.id == selectedTemplateId.value);
    if (!t?.id) {
        return;
    }
    try {
        const params = new URLSearchParams();
        params.set('customer_id', String(props.customer.id));
        if (props.leadId) {
            params.set('lead_id', String(props.leadId));
        }
        const { data } = await axios.get(`/api/whatsapp/templates/${t.id}/parameter-hints?${params.toString()}`);
        templateHints.value = data;
        const keys = [
            ...new Set([...(data.header_named_keys || []), ...(data.body_named_keys || [])]),
        ];
        const merged = { ...(data.suggested_template_params || {}) };
        keys.forEach((k) => {
            if (merged[k] === undefined || merged[k] === null) {
                merged[k] = '';
            }
        });
        templateParamValues.value = merged;
    } catch {
        templateHints.value = null;
    }
}

async function loadTemplatePreview() {
    templatePreview.value = null;
    templatePreviewError.value = null;
    if (!selectedTemplateId.value) {
        return;
    }
    const t = messageTemplates.value.find((x) => x.id == selectedTemplateId.value);
    if (!t?.name) {
        return;
    }
    try {
        const { data } = await axios.post('/api/whatsapp/templates/preview', {
            template_name: t.name,
            template_params: { ...templateParamValues.value },
            language: t.language || undefined,
            sample_to: whatsappNumber.value?.trim() || undefined,
            customer_id: props.customer.id,
            lead_id: props.leadId || undefined,
        });
        templatePreview.value = data;
    } catch (err) {
        templatePreviewError.value = err.response?.data?.message || 'Could not load template preview';
    }
}


async function loadWindowStatus() {
    if (!props.customer?.id) return;
    try {
        const { data } = await axios.get(`/api/whatsapp/customers/${props.customer.id}/window-status`);
        withinWindow.value = !!data?.within_window;
        windowStatusMessage.value = data?.message || (withinWindow.value
            ? 'Customer is within 24-hour session.'
            : 'Outside 24-hour window. Use approved template.');
    } catch (_) {
        withinWindow.value = true;
        windowStatusMessage.value = 'Unable to verify 24-hour window right now.';
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
    if (!selectedTemplateId.value && !message.value.trim()) {
        error.value = 'Please enter a message';
        return;
    }
    if (!withinWindow.value && !selectedTemplateId.value) {
        error.value = 'Outside 24-hour window. Select an approved WhatsApp template.';
        return;
    }
    sending.value = true;
    error.value = null;
    metaError.value = null;
    try {
        const selectedTemplate = messageTemplates.value.find((x) => x.id == selectedTemplateId.value);
        await axios.post('/api/communications', {
            customer_id: props.customer.id,
            lead_id: props.leadId || undefined,
            channel: 'whatsapp',
            message: selectedTemplateId.value ? null : message.value.trim(),
            to_number: whatsappNumber.value.trim() || undefined,
            template_name: selectedTemplate?.name || undefined,
            language: selectedTemplate?.language || undefined,
            ...(selectedTemplateId.value ? { template_params: { ...templateParamValues.value } } : {}),
        });
        message.value = '';
        selectedTemplateId.value = '';
        templatePreview.value = null;
        templatePreviewError.value = null;
        templateHints.value = null;
        templateParamValues.value = {};
        await loadWindowStatus();
        emit('sent');
    } catch (err) {
        const d = err.response?.data;
        const hint = d?.hint ? ` ${d.hint}` : '';
        error.value = (d?.message || 'Failed to send WhatsApp message') + hint;
        metaError.value = d?.meta_error && typeof d.meta_error === 'object' ? d.meta_error : null;
    } finally {
        sending.value = false;
    }
}
</script>
