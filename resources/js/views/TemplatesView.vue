<template>
    <ListingPageShell
        title="Templates"
        subtitle="Email, SMS and WhatsApp message templates, newest first — pick a channel tab to browse, or assign templates to the functions that send them."
        :badge="templatesBadge"
    >
        <template #actions>
            <BaseButton
                v-if="activeTab === 'email'"
                variant="outline"
                block-mobile
                @click="openSendModal"
            >
                <template #icon><EnvelopeIcon class="icon-sm" aria-hidden="true" /></template>
                Send Email
            </BaseButton>
            <BaseButton
                v-if="activeTab === 'email'"
                variant="outline"
                block-mobile
                @click="showHtmlImport = true"
            >
                <template #icon><ArrowUpTrayIcon class="icon-sm" aria-hidden="true" /></template>
                Import HTML
            </BaseButton>
            <BaseButton
                v-if="activeTab !== 'assignments'"
                variant="primary"
                block-mobile
                @click="openCreateModal"
            >
                <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                Create Template
            </BaseButton>
        </template>

        <template #filters>
            <div class="listing-filters-row">
                <div class="tab-list" role="tablist" aria-label="Template channel">
                    <button
                        v-for="tab in tabs"
                        :key="tab.id"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === tab.id ? 'true' : 'false'"
                        class="tab inline-flex items-center gap-2"
                        :class="activeTab === tab.id ? 'tab-active' : ''"
                        @click="activeTab = tab.id; loadTemplates()"
                    >
                        <component :is="tab.icon" class="icon-sm" aria-hidden="true" />
                        {{ tab.name }}
                    </button>
                </div>
            </div>
        </template>

        <template v-if="activeTab === 'email'" #toolbar>
            <p class="callout callout-info">
                <a
                    href="/downloads/email-merge-tags-guide.md"
                    download="CRM-Email-Merge-Tags-and-Placeholders.md"
                    class="link inline-flex items-center gap-1.5 font-medium"
                >
                    <DocumentArrowDownIcon class="icon-sm shrink-0" aria-hidden="true" />
                    Email merge tags &amp; placeholders guide
                </a>
                <span class="text-slate-600 font-normal">
                    — download: every placeholder, what it becomes, HTML snippets, and a copy-paste block for AI tools.
                </span>
            </p>
        </template>

        <!-- Template Assignments Tab -->
        <div v-if="activeTab === 'assignments'" class="px-4 sm:px-6 py-5 sm:py-6">
            <h2 class="text-lg font-semibold text-slate-900 mb-1">Template Assignments</h2>
            <p class="text-sm text-slate-600 mb-6">Assign templates to specific functions (appointments, invoices, etc.)</p>

            <div class="space-y-4">
                <div
                    v-for="functionType in functionTypes"
                    :key="functionType"
                    class="border border-slate-200 rounded-card p-4"
                >
                    <h3 class="font-medium text-slate-900 mb-3 capitalize">{{ functionType.replace('_', ' ') }}</h3>
                    <div class="form-grid-3">
                        <!-- Email Template Assignment -->
                        <div>
                            <label class="form-label" :for="`templatesview-${functionType}-email`">Email Template</label>
                            <select
                                :id="`templatesview-${functionType}-email`"
                                v-model="assignments[functionType].email"
                                class="form-select w-full"
                                @change="saveAssignment(functionType, 'email', assignments[functionType].email)"
                            >
                                <option value="">None</option>
                                <option
                                    v-for="template in emailTemplates"
                                    :key="template.id"
                                    :value="template.id"
                                >
                                    {{ template.name }}
                                </option>
                            </select>
                        </div>
                        <!-- SMS Template Assignment -->
                        <div>
                            <label class="form-label" :for="`templatesview-${functionType}-sms`">SMS Template</label>
                            <select
                                :id="`templatesview-${functionType}-sms`"
                                v-model="assignments[functionType].sms"
                                class="form-select w-full"
                                @change="saveAssignment(functionType, 'sms', assignments[functionType].sms)"
                            >
                                <option value="">None</option>
                                <option
                                    v-for="template in smsTemplates"
                                    :key="template.id"
                                    :value="template.id"
                                >
                                    {{ template.name }}
                                </option>
                            </select>
                        </div>
                        <!-- WhatsApp Template Assignment -->
                        <div>
                            <label class="form-label" :for="`templatesview-${functionType}-whatsapp`">WhatsApp Template</label>
                            <select
                                :id="`templatesview-${functionType}-whatsapp`"
                                v-model="assignments[functionType].whatsapp"
                                class="form-select w-full"
                                @change="saveAssignment(functionType, 'whatsapp', assignments[functionType].whatsapp)"
                            >
                                <option value="">None</option>
                                <option
                                    v-for="template in whatsappTemplates"
                                    :key="template.id"
                                    :value="template.id"
                                >
                                    {{ template.name }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Templates Tab -->
        <div v-if="activeTab === 'email'" class="px-4 sm:px-6 py-5 sm:py-6">
            <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" aria-busy="true">
                <span class="sr-only">Loading email templates…</span>
                <div v-for="n in 3" :key="n" class="card p-4 space-y-3" aria-hidden="true">
                    <div class="skeleton h-24 w-full rounded-card"></div>
                    <div class="skeleton-text w-2/3"></div>
                    <div class="skeleton-text w-full"></div>
                    <div class="skeleton-text w-1/2"></div>
                </div>
            </div>
            <EmptyState
                v-else-if="templates.length === 0"
                heading="No email templates yet"
                description="Email templates hold the subject line and HTML body reused by campaigns and automated sends. Create one, or import an existing HTML design."
            >
                <template #icon><EnvelopeIcon class="icon" aria-hidden="true" /></template>
                <template #action>
                    <BaseButton variant="primary" @click="openCreateModal">
                        <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                        Create Template
                    </BaseButton>
                </template>
            </EmptyState>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="template in templates"
                    :key="template.id"
                    class="card overflow-hidden hover:shadow-card-hover transition-shadow"
                >
                    <div class="h-32 bg-gradient-to-br from-primary-500 to-primary-600 flex items-center justify-center">
                        <div class="text-white text-center px-3">
                            <EnvelopeIcon class="w-8 h-8 mx-auto mb-2" aria-hidden="true" />
                            <div class="text-sm font-medium">{{ template.category }}</div>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-slate-900 mb-1">{{ template.name }}</h3>
                        <p class="text-sm text-slate-600 mb-3 line-clamp-2">{{ template.description || 'No description' }}</p>
                        <div class="flex items-center justify-between gap-2 text-xs text-slate-500 mb-3">
                            <span class="truncate">Subject: {{ template.subject }}</span>
                            <BaseBadge :status="template.is_active ? 'active' : 'inactive'">
                                {{ template.is_active ? 'Active' : 'Inactive' }}
                            </BaseBadge>
                        </div>
                        <div class="flex gap-2">
                            <BaseButton variant="outline" size="sm" class="flex-1" @click="editTemplate(template)">
                                <template #icon><PencilSquareIcon class="icon-sm" aria-hidden="true" /></template>
                                Edit
                            </BaseButton>
                            <BaseButton
                                variant="outline"
                                size="sm"
                                :label="`Duplicate ${template.name}`"
                                @click="duplicateTemplate(template, 'email')"
                            >
                                <DocumentDuplicateIcon class="icon-sm" aria-hidden="true" />
                            </BaseButton>
                            <BaseButton
                                v-if="!template.is_prebuilt"
                                variant="outline"
                                size="sm"
                                class="text-danger-700"
                                :label="`Delete ${template.name}`"
                                @click="askDeleteTemplate(template, 'email')"
                            >
                                <TrashIcon class="icon-sm" aria-hidden="true" />
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SMS Templates Tab -->
        <div v-if="activeTab === 'sms'" class="px-4 sm:px-6 py-5 sm:py-6">
            <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" aria-busy="true">
                <span class="sr-only">Loading SMS templates…</span>
                <div v-for="n in 3" :key="n" class="card p-4 space-y-3" aria-hidden="true">
                    <div class="skeleton h-24 w-full rounded-card"></div>
                    <div class="skeleton-text w-2/3"></div>
                    <div class="skeleton-text w-full"></div>
                    <div class="skeleton-text w-1/2"></div>
                </div>
            </div>
            <EmptyState
                v-else-if="templates.length === 0"
                heading="No SMS templates yet"
                description="SMS templates store the short message text and merge tags used for bulk and automated texts. Create your first one to get started."
            >
                <template #icon><DevicePhoneMobileIcon class="icon" aria-hidden="true" /></template>
                <template #action>
                    <BaseButton variant="primary" @click="openCreateSmsModal">
                        <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                        Create Template
                    </BaseButton>
                </template>
            </EmptyState>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="template in templates"
                    :key="template.id"
                    class="card overflow-hidden hover:shadow-card-hover transition-shadow"
                >
                    <div class="h-32 bg-gradient-to-br from-success-500 to-success-600 flex items-center justify-center">
                        <div class="text-white text-center px-3">
                            <DevicePhoneMobileIcon class="w-8 h-8 mx-auto mb-2" aria-hidden="true" />
                            <div class="text-sm font-medium">{{ template.category }}</div>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-slate-900 mb-1">{{ template.name }}</h3>
                        <p class="text-sm text-slate-600 mb-3 line-clamp-3">{{ template.message || 'No message' }}</p>
                        <div class="flex items-center justify-between gap-2 text-xs text-slate-500 mb-3">
                            <span>{{ template.message?.length || 0 }} characters</span>
                            <BaseBadge :status="template.is_active ? 'active' : 'inactive'">
                                {{ template.is_active ? 'Active' : 'Inactive' }}
                            </BaseBadge>
                        </div>
                        <div class="flex gap-2">
                            <BaseButton variant="outline" size="sm" class="flex-1" @click="editSmsTemplate(template)">
                                <template #icon><PencilSquareIcon class="icon-sm" aria-hidden="true" /></template>
                                Edit
                            </BaseButton>
                            <BaseButton
                                variant="outline"
                                size="sm"
                                :label="`Duplicate ${template.name}`"
                                @click="duplicateTemplate(template, 'sms')"
                            >
                                <DocumentDuplicateIcon class="icon-sm" aria-hidden="true" />
                            </BaseButton>
                            <BaseButton
                                variant="outline"
                                size="sm"
                                class="text-danger-700"
                                :label="`Delete ${template.name}`"
                                @click="askDeleteTemplate(template, 'sms')"
                            >
                                <TrashIcon class="icon-sm" aria-hidden="true" />
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- WhatsApp Templates Tab -->
        <div v-if="activeTab === 'whatsapp'" class="px-4 sm:px-6 py-5 sm:py-6">
            <div v-if="loading" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" aria-busy="true">
                <span class="sr-only">Loading WhatsApp templates…</span>
                <div v-for="n in 3" :key="n" class="card p-4 space-y-3" aria-hidden="true">
                    <div class="skeleton h-24 w-full rounded-card"></div>
                    <div class="skeleton-text w-2/3"></div>
                    <div class="skeleton-text w-full"></div>
                    <div class="skeleton-text w-1/2"></div>
                </div>
            </div>
            <EmptyState
                v-else-if="templates.length === 0"
                heading="No WhatsApp templates yet"
                description="WhatsApp templates hold the message body and any media attachment sent through the WhatsApp channel. Create your first one to get started."
            >
                <template #icon><ChatBubbleLeftRightIcon class="icon" aria-hidden="true" /></template>
                <template #action>
                    <BaseButton variant="primary" @click="openCreateWhatsappModal">
                        <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                        Create Template
                    </BaseButton>
                </template>
            </EmptyState>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="template in templates"
                    :key="template.id"
                    class="card overflow-hidden hover:shadow-card-hover transition-shadow"
                >
                    <div class="h-32 bg-gradient-to-br from-success-500 to-primary-600 flex items-center justify-center">
                        <div class="text-white text-center px-3">
                            <ChatBubbleLeftRightIcon class="w-8 h-8 mx-auto mb-2" aria-hidden="true" />
                            <div class="text-sm font-medium">{{ template.category }}</div>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-slate-900 mb-1">{{ template.name }}</h3>
                        <p class="text-sm text-slate-600 mb-3 line-clamp-3">{{ template.message || 'No message' }}</p>
                        <p v-if="template.media_url" class="flex items-center gap-1.5 text-xs text-slate-500 mb-2">
                            <PhotoIcon class="icon-sm" aria-hidden="true" />
                            Media: {{ template.media_type || 'image' }}
                        </p>
                        <div class="flex items-center justify-between gap-2 text-xs text-slate-500 mb-3">
                            <span>{{ template.message?.length || 0 }} characters</span>
                            <BaseBadge :status="template.is_active ? 'active' : 'inactive'">
                                {{ template.is_active ? 'Active' : 'Inactive' }}
                            </BaseBadge>
                        </div>
                        <div class="flex gap-2">
                            <BaseButton variant="outline" size="sm" class="flex-1" @click="editWhatsappTemplate(template)">
                                <template #icon><PencilSquareIcon class="icon-sm" aria-hidden="true" /></template>
                                Edit
                            </BaseButton>
                            <BaseButton
                                variant="outline"
                                size="sm"
                                :label="`Duplicate ${template.name}`"
                                @click="duplicateTemplate(template, 'whatsapp')"
                            >
                                <DocumentDuplicateIcon class="icon-sm" aria-hidden="true" />
                            </BaseButton>
                            <BaseButton
                                variant="outline"
                                size="sm"
                                class="text-danger-700"
                                :label="`Delete ${template.name}`"
                                @click="askDeleteTemplate(template, 'whatsapp')"
                            >
                                <TrashIcon class="icon-sm" aria-hidden="true" />
                            </BaseButton>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modals -->
        <EmailHtmlImportModal
            v-if="showHtmlImport"
            @close="showHtmlImport = false"
            @imported="handleHtmlImported"
        />

        <SmsTemplateModal
            v-if="showSmsModal"
            :template="editingTemplate"
            @close="showSmsModal = false"
            @saved="handleTemplateSaved"
        />

        <WhatsappTemplateModal
            v-if="showWhatsappModal"
            :template="editingTemplate"
            @close="showWhatsappModal = false"
            @saved="handleTemplateSaved"
        />

        <SendEmailModal
            v-if="showSendModal"
            @close="showSendModal = false"
            @sent="handleEmailSent"
        />

        <ConfirmDialog
            v-model="showDeleteConfirm"
            title="Delete template"
            :message="deleteConfirmMessage"
            confirm-label="Delete template"
            tone="danger"
            :loading="deletingTemplate"
            @confirm="confirmDeleteTemplate"
            @cancel="cancelDeleteTemplate"
        />
    </ListingPageShell>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseBadge, BaseButton, ConfirmDialog, EmptyState } from '@/components/base';
import {
    ArrowUpTrayIcon,
    ChatBubbleLeftRightIcon,
    Cog6ToothIcon,
    DevicePhoneMobileIcon,
    DocumentArrowDownIcon,
    DocumentDuplicateIcon,
    EnvelopeIcon,
    PencilSquareIcon,
    PhotoIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import EmailHtmlImportModal from '@/components/EmailHtmlImportModal.vue';
import SendEmailModal from '@/components/SendEmailModal.vue';
import SmsTemplateModal from '@/components/SmsTemplateModal.vue';
import WhatsappTemplateModal from '@/components/WhatsappTemplateModal.vue';

const toast = useToastStore();
const route = useRoute();
const router = useRouter();

const activeTab = ref('email');
const loading = ref(true);
const templates = ref([]);
const emailTemplates = ref([]);
const smsTemplates = ref([]);
const whatsappTemplates = ref([]);
const showHtmlImport = ref(false);
const showSmsModal = ref(false);
const showWhatsappModal = ref(false);
const showSendModal = ref(false);
const editingTemplate = ref(null);
const showDeleteConfirm = ref(false);
const deletingTemplate = ref(false);
const deleteTarget = ref(null);

const tabs = [
    { id: 'email', name: 'Email Templates', icon: EnvelopeIcon },
    { id: 'sms', name: 'SMS Templates', icon: DevicePhoneMobileIcon },
    { id: 'whatsapp', name: 'WhatsApp Templates', icon: ChatBubbleLeftRightIcon },
    { id: 'assignments', name: 'Template Assignments', icon: Cog6ToothIcon },
];

const templatesBadge = computed(() => {
    if (activeTab.value === 'assignments') return null;
    return loading.value ? null : `${templates.value.length} total`;
});

const deleteConfirmMessage = computed(() =>
    deleteTarget.value
        ? `Are you sure you want to delete "${deleteTarget.value.template.name}"? This cannot be undone.`
        : ''
);

watch(
    () => route.query.tab,
    (tab) => {
        if (typeof tab === 'string' && tabs.some((t) => t.id === tab)) {
            activeTab.value = tab;
            loadTemplates();
        }
    }
);

const functionTypes = [
    'appointment',
    'invoice',
    'welcome',
    'follow_up',
    'quote',
    'thank_you',
    'payment_reminder',
];

const assignments = reactive({});

const loadTemplates = async () => {
    loading.value = true;
    try {
        if (activeTab.value === 'email') {
            const response = await axios.get('/api/email-templates');
            templates.value = response.data;
            emailTemplates.value = response.data;
        } else if (activeTab.value === 'sms') {
            const response = await axios.get('/api/message-templates');
            templates.value = response.data;
            smsTemplates.value = response.data;
        } else if (activeTab.value === 'whatsapp') {
            const response = await axios.get('/api/whatsapp-templates');
            templates.value = response.data;
            whatsappTemplates.value = response.data;
        } else if (activeTab.value === 'assignments') {
            await loadAssignments();
        }
    } catch (error) {
        console.error('Failed to load templates:', error);
        toast.error('Failed to load templates');
    } finally {
        loading.value = false;
    }
};

const loadAssignments = async () => {
    try {
        // Initialize assignments object
        functionTypes.forEach(type => {
            if (!assignments[type]) {
                assignments[type] = { email: '', sms: '', whatsapp: '' };
            }
        });

        // Load all templates for dropdowns
        const [emailRes, smsRes, whatsappRes] = await Promise.all([
            axios.get('/api/email-templates?active=1'),
            axios.get('/api/message-templates?active=1'),
            axios.get('/api/whatsapp-templates?active=1'),
        ]);

        emailTemplates.value = emailRes.data;
        smsTemplates.value = smsRes.data;
        whatsappTemplates.value = whatsappRes.data;

        // Load current assignments
        const assignmentsRes = await axios.get('/api/template-assignments');
        const assignmentsData = assignmentsRes.data;

        // Populate assignments
        functionTypes.forEach(type => {
            const emailAssignment = assignmentsData[type]?.find(a => a.template_type === 'email');
            const smsAssignment = assignmentsData[type]?.find(a => a.template_type === 'sms');
            const whatsappAssignment = assignmentsData[type]?.find(a => a.template_type === 'whatsapp');

            assignments[type] = {
                email: emailAssignment?.template_id || '',
                sms: smsAssignment?.template_id || '',
                whatsapp: whatsappAssignment?.template_id || '',
            };
        });
    } catch (error) {
        console.error('Failed to load assignments:', error);
    }
};

const saveAssignment = async (functionType, templateType, templateId) => {
    try {
        await axios.put('/api/template-assignments', {
            function_type: functionType,
            template_type: templateType,
            template_id: templateId || null,
        });
        toast.success('Template assignment saved');
    } catch (error) {
        console.error('Failed to save assignment:', error);
        toast.error('Failed to save assignment');
    }
};

const handleHtmlImported = (template) => {
    showHtmlImport.value = false;
    loadTemplates();
    if (template?.id) {
        router.push({ name: 'email-template-edit', params: { id: String(template.id) } });
    }
};

const openCreateModal = () => {
    router.push({ name: 'email-template-new' });
};

const editTemplate = (template) => {
    router.push({ name: 'email-template-edit', params: { id: String(template.id) } });
};

const openCreateSmsModal = () => {
    editingTemplate.value = null;
    showSmsModal.value = true;
};

const editSmsTemplate = (template) => {
    editingTemplate.value = template;
    showSmsModal.value = true;
};

const openCreateWhatsappModal = () => {
    editingTemplate.value = null;
    showWhatsappModal.value = true;
};

const editWhatsappTemplate = (template) => {
    editingTemplate.value = template;
    showWhatsappModal.value = true;
};

const openSendModal = () => {
    showSendModal.value = true;
};

const handleTemplateSaved = () => {
    loadTemplates();
    if (activeTab.value === 'assignments') {
        loadAssignments();
    }
};

const handleEmailSent = () => {
    loadTemplates();
};

const duplicateTemplate = async (template, type) => {
    try {
        const endpoint = type === 'email' ? 'email-templates' : type === 'sms' ? 'message-templates' : 'whatsapp-templates';
        await axios.post(`/api/${endpoint}/${template.id}/duplicate`);
        toast.success('Template duplicated successfully');
        loadTemplates();
    } catch (error) {
        console.error('Failed to duplicate template:', error);
        toast.error('Failed to duplicate template');
    }
};

const askDeleteTemplate = (template, type) => {
    deleteTarget.value = { template, type };
    showDeleteConfirm.value = true;
};

const cancelDeleteTemplate = () => {
    showDeleteConfirm.value = false;
    deleteTarget.value = null;
};

const confirmDeleteTemplate = async () => {
    if (!deleteTarget.value) return;
    const { template, type } = deleteTarget.value;
    deletingTemplate.value = true;

    try {
        const endpoint = type === 'email' ? 'email-templates' : type === 'sms' ? 'message-templates' : 'whatsapp-templates';
        await axios.delete(`/api/${endpoint}/${template.id}`);
        toast.success('Template deleted successfully');
        showDeleteConfirm.value = false;
        deleteTarget.value = null;
        loadTemplates();
    } catch (error) {
        console.error('Failed to delete template:', error);
        toast.error(error.response?.data?.message || 'Failed to delete template');
    } finally {
        deletingTemplate.value = false;
    }
};

onMounted(() => {
    loadTemplates();
});
</script>
