<template>
    <div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">WhatsApp Templates</h1>
                <p class="text-sm text-slate-600 mt-1">
                    Create templates here (submitted to Meta for approval) or sync from Meta. Use <strong>View</strong> to see the exact JSON payload the CRM sends when messaging.
                </p>
            </div>
            <div class="flex gap-3">
                <button
                    @click="syncTemplates"
                    :disabled="syncing"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 text-sm"
                >
                    {{ syncing ? 'Syncing...' : '🔄 Sync from Meta' }}
                </button>
                <button
                    @click="showCreateModal = true"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors text-sm"
                >
                    + Create Template
                </button>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm p-4 flex gap-4">
            <select
                v-model="filters.status"
                class="px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
            >
                <option value="">All Status</option>
                <option value="PENDING">Pending</option>
                <option value="APPROVED">Approved</option>
                <option value="REJECTED">Rejected</option>
            </select>
            <select
                v-model="filters.category"
                class="px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
            >
                <option value="">All Categories</option>
                <option value="TRANSACTIONAL">Transactional</option>
                <option value="MARKETING">Marketing</option>
            </select>
        </div>

        <!-- Templates Table -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden overflow-x-auto">
            <div v-if="loading" class="flex justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900"></div>
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
                            <span
                                class="px-2 py-1 text-xs font-medium rounded-full"
                                :class="{
                                    'bg-yellow-100 text-yellow-800': template.status === 'PENDING',
                                    'bg-green-100 text-green-800': template.status === 'APPROVED',
                                    'bg-red-100 text-red-800': template.status === 'REJECTED',
                                }"
                            >
                                {{ template.status }}
                            </span>
                            <div v-if="template.rejection_reason" class="text-xs text-red-600 mt-1">
                                {{ template.rejection_reason }}
                            </div>
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-500">
                            {{ new Date(template.created_at).toLocaleDateString() }}
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex justify-end gap-2">
                                <button
                                    v-if="template.status === 'REJECTED'"
                                    @click="resubmitTemplate(template.id)"
                                    class="px-3 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700"
                                >
                                    Resubmit
                                </button>
                                <button
                                    @click="viewTemplate(template)"
                                    class="px-3 py-1 text-xs bg-slate-600 text-white rounded hover:bg-slate-700"
                                >
                                    View
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Create Template Modal -->
        <div v-if="showCreateModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b border-slate-200">
                    <h2 class="text-xl font-bold text-slate-900">Create WhatsApp Template</h2>
                </div>
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Template Name *</label>
                        <input
                            v-model="newTemplate.name"
                            type="text"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="hello_world"
                        >
                        <p class="text-xs text-slate-500 mt-1">Lowercase, underscores only. Must be unique.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Category *</label>
                            <select
                                v-model="newTemplate.category"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="TRANSACTIONAL">Transactional</option>
                                <option value="MARKETING">Marketing</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Language</label>
                            <input
                                v-model="newTemplate.language"
                                type="text"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="en_US"
                            >
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Body Text *</label>
                        <textarea
                            v-model="newTemplate.bodyText"
                            rows="4"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Hello {{1}}, welcome to our service!"
                        ></textarea>
                        <p class="text-xs text-slate-500 mt-1" v-pre>
                            Use {{1}}, {{2}} for variables — the CRM adds sample examples for Meta automatically. Named fields (e.g. {{name}}) are better created in Meta Business Suite, then sync here.
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Footer (optional)</label>
                        <input
                            v-model="newTemplate.footerText"
                            type="text"
                            maxlength="60"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Reply STOP to opt out"
                        >
                        <p class="text-xs text-slate-500 mt-1">Max 60 characters — no variables in footer.</p>
                    </div>
                </div>
                <div class="p-6 border-t border-slate-200 flex justify-end gap-3">
                    <button
                        @click="showCreateModal = false"
                        class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        @click="createTemplate"
                        :disabled="creating"
                        class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                    >
                        {{ creating ? 'Creating...' : 'Create Template' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- View / Preview modal -->
        <div v-if="viewingTemplate" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-xl shadow-xl max-w-3xl w-full max-h-[92vh] overflow-y-auto">
                <div class="p-6 border-b border-slate-200 flex justify-between items-start gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">{{ viewingTemplate.name }}</h2>
                        <p class="text-sm text-slate-500 mt-1">
                            {{ viewingTemplate.status }} · {{ viewingTemplate.language }} · {{ viewingTemplate.category }}
                        </p>
                    </div>
                    <button type="button" class="text-slate-500 hover:text-slate-800 text-2xl leading-none" @click="closeViewModal" aria-label="Close">×</button>
                </div>
                <div class="p-6 space-y-4">
                    <p class="text-sm text-slate-600">
                        Below is what this CRM builds for Meta’s <code class="text-xs bg-slate-100 px-1 rounded">/messages</code> call.
                        Adjust sample variables and recipient, then refresh preview.
                    </p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Sample “to” number (E.164, optional)</label>
                            <input
                                v-model="previewSampleTo"
                                type="text"
                                class="w-full px-3 py-2 text-sm border border-slate-300 rounded-lg"
                                placeholder="447700900123"
                                @change="runPreview"
                            >
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Parameter format (from sync)</label>
                            <div class="text-sm text-slate-800 py-2">{{ viewingTemplate.parameter_format || '—' }}</div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">
                            <span v-pre>template_params</span> (JSON: <span v-pre>["val1","val2"]</span> for positional, or <span v-pre>{"name":"x"}</span> for named)
                        </label>
                        <textarea
                            v-model="previewParamsJson"
                            rows="3"
                            class="w-full px-3 py-2 text-sm font-mono border border-slate-300 rounded-lg"
                            placeholder='["Alice","Tuesday"] or {"customer_name":"Alice"}'
                            @keydown.ctrl.enter="runPreview"
                        ></textarea>
                    </div>
                    <button
                        type="button"
                        class="px-4 py-2 bg-slate-900 text-white text-sm rounded-lg hover:bg-slate-800 disabled:opacity-50"
                        :disabled="previewLoading"
                        @click="runPreview"
                    >
                        {{ previewLoading ? 'Loading…' : 'Refresh preview' }}
                    </button>
                    <div v-if="previewError" class="text-sm text-red-600 bg-red-50 p-3 rounded-lg">{{ previewError }}</div>
                    <div v-if="previewResult" class="space-y-4 border-t border-slate-200 pt-4">
                        <p class="text-xs text-slate-500">{{ previewResult.sample_to_note }}</p>
                        <div v-if="previewResult.header_preview" class="rounded-lg bg-slate-50 border border-slate-200 p-3">
                            <div class="text-xs font-semibold text-slate-500 uppercase mb-1">Header preview</div>
                            <div class="text-sm text-slate-900 whitespace-pre-wrap">{{ previewResult.header_preview }}</div>
                        </div>
                        <div class="rounded-lg bg-emerald-50 border border-emerald-200 p-3">
                            <div class="text-xs font-semibold text-emerald-800 uppercase mb-1">Body preview (as filled by CRM)</div>
                            <div class="text-sm text-slate-900 whitespace-pre-wrap">{{ previewResult.body_preview || '(no body text)' }}</div>
                        </div>
                        <div v-if="previewResult.url_button_dynamic_note" class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded p-2">
                            {{ previewResult.url_button_dynamic_note }}
                        </div>
                        <details open class="text-sm">
                            <summary class="cursor-pointer font-medium text-slate-700">graph_payload (exact JSON)</summary>
                            <pre class="mt-2 p-3 bg-slate-900 text-emerald-100 text-xs rounded-lg overflow-x-auto whitespace-pre-wrap break-all">{{ JSON.stringify(previewResult.graph_payload, null, 2) }}</pre>
                        </details>
                    </div>
                    <div v-if="viewingTemplate.components_json?.length" class="border-t border-slate-200 pt-4">
                        <details class="text-sm">
                            <summary class="cursor-pointer font-medium text-slate-700">Stored components (from CRM / Meta sync)</summary>
                            <pre class="mt-2 p-3 bg-slate-50 text-xs rounded-lg overflow-x-auto whitespace-pre-wrap break-all">{{ JSON.stringify(viewingTemplate.components_json, null, 2) }}</pre>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

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

