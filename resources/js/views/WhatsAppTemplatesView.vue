<template>
    <div class="page">
        <div class="flex flex-wrap justify-between items-start gap-4">
            <div>
                <p class="page-lead">
                    Create templates here (submitted to Meta for approval) or sync from Meta. Use <strong>View</strong> to see the exact JSON payload the CRM sends when messaging.
                </p>
            </div>
            <div class="flex gap-3">
                <BaseButton variant="outline" :loading="syncing" @click="syncTemplates">
                    <template #icon><ArrowPathIcon class="icon" aria-hidden="true" /></template>
                    {{ syncing ? 'Syncing...' : 'Sync from Meta' }}
                </BaseButton>
                <BaseButton variant="primary" @click="showCreateModal = true">
                    <template #icon><PlusIcon class="icon" aria-hidden="true" /></template>
                    Create Template
                </BaseButton>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 flex flex-wrap gap-4">
            <div class="min-w-[12rem]">
                <label class="form-label" for="whatsapptemplatesview-filter-status">Status</label>
                <select
                    id="whatsapptemplatesview-filter-status"
                    v-model="filters.status"
                    class="form-select"
                >
                    <option value="">All Status</option>
                    <option value="PENDING">Pending</option>
                    <option value="APPROVED">Approved</option>
                    <option value="REJECTED">Rejected</option>
                </select>
            </div>
            <div class="min-w-[12rem]">
                <label class="form-label" for="whatsapptemplatesview-filter-category">Category</label>
                <select
                    id="whatsapptemplatesview-filter-category"
                    v-model="filters.category"
                    class="form-select"
                >
                    <option value="">All Categories</option>
                    <option value="TRANSACTIONAL">Transactional</option>
                    <option value="MARKETING">Marketing</option>
                </select>
            </div>
        </div>

        <!-- Templates Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden overflow-x-auto" :aria-busy="loading ? 'true' : 'false'">
            <div v-if="loading" class="flex justify-center py-12" role="status" aria-live="polite">
                <span class="spinner w-8 h-8 text-slate-600" aria-hidden="true" />
                <span class="sr-only">Loading templates…</span>
            </div>
            <div v-else-if="templates.length === 0" class="p-12 text-center text-slate-500">
                No templates found. Create your first template to get started.
            </div>
            <table v-else class="w-full min-w-[640px]">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Language</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Created</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="template in templates" :key="template.id" class="hover:bg-slate-50">
                        <td class="px-6 py-4">
                            <div class="font-medium text-slate-900">{{ template.name }}</div>
                            <div v-if="template.meta_template_id" class="text-xs text-slate-500">
                                Meta ID: {{ template.meta_template_id }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">{{ template.category }}</td>
                        <td class="px-6 py-4 text-sm text-slate-600">{{ template.language }}</td>
                        <td class="px-6 py-4">
                            <BaseBadge :tone="templateTone(template.status)">
                                {{ formatApiEnumLabel(template.status) }}
                            </BaseBadge>
                            <div v-if="template.rejection_reason" class="text-xs text-danger-700 mt-1">
                                {{ template.rejection_reason }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500">
                            {{ formatDateUsDisplay(template.created_at) }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <BaseButton
                                    v-if="template.status === 'REJECTED'"
                                    size="sm"
                                    variant="soft"
                                    @click="resubmitTemplate(template.id)"
                                >
                                    <template #icon><ArrowPathIcon class="icon-sm" aria-hidden="true" /></template>
                                    Resubmit
                                </BaseButton>
                                <BaseButton size="sm" variant="outline" @click="viewTemplate(template)">
                                    <template #icon><EyeIcon class="icon-sm" aria-hidden="true" /></template>
                                    View
                                </BaseButton>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create Template Modal -->
        <BaseModal
            v-model="showCreateModal"
            title="Create WhatsApp Template"
            size="md"
            :close-on-backdrop="false"
        >
            <form id="whatsapp-create-template-form" class="space-y-4" novalidate @submit.prevent="createTemplate">
                <div>
                    <label class="form-label" for="whatsapptemplatesview-template-name">Template Name <span class="form-required">*</span></label>
                    <input id="whatsapptemplatesview-template-name"
                        v-model="newTemplate.name"
                        type="text"
                        class="form-input"
                        placeholder="hello_world"
                    >
                    <p class="form-hint">Lowercase, underscores only. Must be unique.</p>
                </div>
                <div class="form-grid-2">
                    <div>
                        <label class="form-label" for="whatsapptemplatesview-category">Category <span class="form-required">*</span></label>
                        <select id="whatsapptemplatesview-category"
                            v-model="newTemplate.category"
                            class="form-select"
                        >
                            <option value="TRANSACTIONAL">Transactional</option>
                            <option value="MARKETING">Marketing</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="whatsapptemplatesview-language">Language</label>
                        <input id="whatsapptemplatesview-language"
                            v-model="newTemplate.language"
                            type="text"
                            class="form-input"
                            placeholder="en_US"
                        >
                    </div>
                </div>
                <div>
                    <label class="form-label" for="whatsapptemplatesview-body-text">Body Text <span class="form-required">*</span></label>
                    <textarea id="whatsapptemplatesview-body-text"
                        v-model="newTemplate.bodyText"
                        rows="4"
                        class="form-textarea"
                        placeholder="Hello {{1}}, welcome to our service!"
                    ></textarea>
                    <p class="form-hint" v-pre>
                        Use {{1}}, {{2}} for variables — the CRM adds sample examples for Meta automatically. Named fields (e.g. {{name}}) are better created in Meta Business Suite, then sync here.
                    </p>
                </div>
                <div>
                    <label class="form-label" for="whatsapptemplatesview-footer-optional">Footer (optional)</label>
                    <input id="whatsapptemplatesview-footer-optional"
                        v-model="newTemplate.footerText"
                        type="text"
                        maxlength="60"
                        class="form-input"
                        placeholder="Reply STOP to opt out"
                    >
                    <p class="form-hint">Max 60 characters — no variables in footer.</p>
                </div>
            </form>

            <template #actions>
                <BaseButton variant="outline" block-mobile @click="showCreateModal = false">Cancel</BaseButton>
                <BaseButton
                    variant="soft"
                    type="submit"
                    form="whatsapp-create-template-form"
                    block-mobile
                    :loading="creating"
                >{{ creating ? 'Creating...' : 'Create Template' }}</BaseButton>
            </template>
        </BaseModal>

        <!-- View / Preview modal -->
        <BaseModal
            :model-value="!!viewingTemplate"
            :title="viewingTemplate?.name || ''"
            :description="viewDescription"
            size="lg"
            @close="closeViewModal"
        >
            <div v-if="viewingTemplate" class="space-y-4">
                <p class="text-sm text-slate-600">
                    Below is what this CRM builds for Meta’s <code class="text-xs bg-slate-100 px-1 rounded">/messages</code> call.
                    Adjust sample variables and recipient, then refresh preview.
                </p>
                <div class="form-grid-2">
                    <div>
                        <label class="form-label" for="whatsapptemplatesview-sample-to-number-e-164-optional">Sample “to” number (E.164, optional)</label>
                        <input id="whatsapptemplatesview-sample-to-number-e-164-optional"
                            v-model="previewSampleTo"
                            type="text"
                            class="form-input"
                            placeholder="447700900123"
                            @change="runPreview"
                        >
                    </div>
                    <div>
                        <p class="form-label">Parameter format (from sync)</p>
                        <div class="text-sm text-slate-800 py-2">{{ viewingTemplate.parameter_format || '—' }}</div>
                    </div>
                </div>
                <div>
                    <label class="form-label" for="whatsapptemplatesview-preview-params">
                        <span v-pre>template_params</span> (JSON: <span v-pre>["val1","val2"]</span> for positional, or <span v-pre>{"name":"x"}</span> for named)
                    </label>
                    <textarea
                        id="whatsapptemplatesview-preview-params"
                        v-model="previewParamsJson"
                        rows="3"
                        class="form-textarea font-mono"
                        placeholder='["Alice","Tuesday"] or {"customer_name":"Alice"}'
                        @keydown.ctrl.enter="runPreview"
                    ></textarea>
                </div>
                <BaseButton variant="soft" :loading="previewLoading" @click="runPreview">
                    <template #icon><ArrowPathIcon class="icon" aria-hidden="true" /></template>
                    {{ previewLoading ? 'Loading…' : 'Refresh preview' }}
                </BaseButton>
                <div v-if="previewError" class="callout callout-danger" role="alert">{{ previewError }}</div>
                <div v-if="previewResult" class="space-y-4 border-t border-slate-200 pt-4">
                    <p class="text-xs text-slate-500">{{ previewResult.sample_to_note }}</p>
                    <div v-if="previewResult.header_preview" class="rounded-card bg-slate-50 border border-slate-200 p-3">
                        <div class="text-eyebrow text-slate-500 uppercase mb-1">Header preview</div>
                        <div class="text-sm text-slate-900 whitespace-pre-wrap">{{ previewResult.header_preview }}</div>
                    </div>
                    <div class="rounded-card bg-success-50 border border-success-200 p-3">
                        <div class="text-eyebrow text-success-800 uppercase mb-1">Body preview (as filled by CRM)</div>
                        <div class="text-sm text-slate-900 whitespace-pre-wrap">{{ previewResult.body_preview || '(no body text)' }}</div>
                    </div>
                    <div v-if="previewResult.url_button_dynamic_note" class="callout callout-warning">
                        {{ previewResult.url_button_dynamic_note }}
                    </div>
                    <details open class="text-sm">
                        <summary class="cursor-pointer font-medium text-slate-700">graph_payload (exact JSON)</summary>
                        <pre class="mt-2 p-3 bg-slate-900 text-success-100 text-xs rounded-card overflow-x-auto whitespace-pre-wrap break-all">{{ JSON.stringify(previewResult.graph_payload, null, 2) }}</pre>
                    </details>
                </div>
                <div v-if="viewingTemplate.components_json?.length" class="border-t border-slate-200 pt-4">
                    <details class="text-sm">
                        <summary class="cursor-pointer font-medium text-slate-700">Stored components (from CRM / Meta sync)</summary>
                        <pre class="mt-2 p-3 bg-slate-50 text-xs rounded-card overflow-x-auto whitespace-pre-wrap break-all">{{ JSON.stringify(viewingTemplate.components_json, null, 2) }}</pre>
                    </details>
                </div>
            </div>
        </BaseModal>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { ArrowPathIcon, EyeIcon, PlusIcon } from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import { BaseBadge, BaseButton, BaseModal } from '@/components/base';
import { formatApiEnumLabel } from '@/utils/displayFormat';
import { formatDateUsDisplay } from '@/utils/dateFormatUi';

const toast = useToastStore();

const loading = ref(false);
const syncing = ref(false);
const creating = ref(false);
const showCreateModal = ref(false);
const templates = ref([]);

const filters = reactive({
    status: '',
    category: '',
});

const newTemplate = reactive({
    name: '',
    category: 'TRANSACTIONAL',
    language: 'en_US',
    bodyText: '',
    footerText: '',
});

const viewingTemplate = ref(null);
const previewParamsJson = ref('[]');
const previewSampleTo = ref('');
const previewResult = ref(null);
const previewLoading = ref(false);
const previewError = ref(null);

const viewDescription = computed(() => {
    if (!viewingTemplate.value) return '';
    const t = viewingTemplate.value;
    return `${formatApiEnumLabel(t.status)} · ${t.language} · ${formatApiEnumLabel(t.category)}`;
});

function templateTone(status) {
    if (status === 'APPROVED') return 'success';
    if (status === 'REJECTED') return 'danger';
    if (status === 'PENDING') return 'warning';
    return 'neutral';
}

function slugTemplateName(raw) {
    return raw
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9_]+/g, '_')
        .replace(/_+/g, '_')
        .replace(/^_|_$/g, '');
}

const loadTemplates = async () => {
    loading.value = true;
    try {
        const params = new URLSearchParams();
        if (filters.status) params.append('status', filters.status);
        if (filters.category) params.append('category', filters.category);

        const response = await axios.get(`/api/whatsapp/templates?${params.toString()}`);
        templates.value = response.data.data || response.data;
    } catch (error) {
        console.error('Failed to load templates:', error);
        toast.error('Failed to load templates');
    } finally {
        loading.value = false;
    }
};

const syncTemplates = async () => {
    syncing.value = true;
    try {
        const response = await axios.post('/api/whatsapp/templates/sync');
        toast.success(`Synced ${response.data.synced} templates`);
        if (response.data.errors?.length > 0) {
            console.warn('Sync errors:', response.data.errors);
        }
        await loadTemplates();
    } catch (error) {
        console.error('Failed to sync templates:', error);
        toast.error('Failed to sync templates');
    } finally {
        syncing.value = false;
    }
};

const createTemplate = async () => {
    if (!newTemplate.name?.trim() || !newTemplate.bodyText?.trim()) {
        toast.error('Please fill in name and body');
        return;
    }

    const name = slugTemplateName(newTemplate.name);
    if (!name) {
        toast.error('Template name must contain letters or numbers (use underscores)');
        return;
    }

    creating.value = true;
    try {
        const components = [
            {
                type: 'BODY',
                text: newTemplate.bodyText.trim(),
            },
        ];
        if (newTemplate.footerText?.trim()) {
            components.push({
                type: 'FOOTER',
                text: newTemplate.footerText.trim().slice(0, 60),
            });
        }

        await axios.post('/api/whatsapp/templates', {
            name,
            category: newTemplate.category,
            language: newTemplate.language,
            components,
        });

        toast.success('Template saved and submitted to Meta (pending approval)');
        showCreateModal.value = false;
        Object.assign(newTemplate, {
            name: '',
            category: 'TRANSACTIONAL',
            language: 'en_US',
            bodyText: '',
            footerText: '',
        });
        await loadTemplates();
    } catch (error) {
        console.error('Failed to create template:', error);
        const d = error.response?.data;
        let msg = d?.message || 'Failed to create template';
        if (d?.meta_error?.message) {
            msg += ` — ${d.meta_error.message}`;
        }
        if (d?.hint) {
            msg += ` ${d.hint}`;
        }
        toast.error(msg);
        await loadTemplates();
    } finally {
        creating.value = false;
    }
};

const resubmitTemplate = async (id) => {
    try {
        await axios.post(`/api/whatsapp/templates/${id}/resubmit`);
        toast.success('Template resubmitted to Meta');
        await loadTemplates();
    } catch (error) {
        console.error('Failed to resubmit template:', error);
        toast.error('Failed to resubmit template');
    }
};

async function openViewModal(template) {
    viewingTemplate.value = template;
    previewParamsJson.value = '[]';
    previewSampleTo.value = '';
    previewResult.value = null;
    previewError.value = null;
    await runPreview();
}

function closeViewModal() {
    viewingTemplate.value = null;
    previewResult.value = null;
    previewError.value = null;
}

async function runPreview() {
    if (!viewingTemplate.value) {
        return;
    }
    previewLoading.value = true;
    previewError.value = null;
    let params;
    try {
        params = JSON.parse(previewParamsJson.value || '[]');
    } catch {
        previewLoading.value = false;
        previewError.value = 'Invalid JSON in template_params';
        return;
    }
    if (params !== null && typeof params === 'object' && !Array.isArray(params)) {
        params = { ...params };
    } else if (!Array.isArray(params)) {
        previewLoading.value = false;
        previewError.value = 'template_params must be a JSON array or object';
        return;
    }
    try {
        const { data } = await axios.post('/api/whatsapp/templates/preview', {
            template_name: viewingTemplate.value.name,
            template_params: params,
            language: viewingTemplate.value.language || undefined,
            sample_to: previewSampleTo.value?.trim() || undefined,
        });
        previewResult.value = data;
    } catch (err) {
        previewResult.value = null;
        previewError.value = err.response?.data?.message || err.message || 'Preview failed';
    } finally {
        previewLoading.value = false;
    }
}

const viewTemplate = (template) => {
    openViewModal(template);
};

watch([() => filters.status, () => filters.category], () => {
    loadTemplates();
});

onMounted(() => {
    loadTemplates();
});
</script>
