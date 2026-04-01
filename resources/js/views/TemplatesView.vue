<template>
    <div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <div class="min-w-0 flex-1">
                <h1 class="text-2xl font-bold text-slate-900">Templates</h1>
                <p class="text-sm text-slate-600 mt-1">Manage email, SMS, and WhatsApp templates</p>
                <p v-if="activeTab === 'email'" class="text-sm text-slate-600 mt-2 max-w-xl leading-relaxed">
                    <a
                        href="/downloads/email-merge-tags-guide.md"
                        download="CRM-Email-Merge-Tags-and-Placeholders.md"
                        class="inline-flex items-center gap-1.5 font-medium text-blue-700 hover:text-blue-900 underline decoration-blue-300 underline-offset-2"
                    >
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        Email merge tags &amp; placeholders guide
                    </a>
                    <span class="text-slate-500 font-normal">
                        — download: every placeholder, what it becomes, HTML snippets, and a copy-paste block for AI tools.
                    </span>
                </p>
            </div>
            <div class="flex gap-3">
                <button
                    v-if="activeTab === 'email'"
                    @click="openSendModal"
                    class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    Send Email
                </button>
                <button
                    v-if="activeTab === 'email'"
                    type="button"
                    @click="showHtmlImport = true"
                    class="px-4 py-2 border border-slate-300 text-slate-800 rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    Import HTML
                </button>
                <button
                    v-if="activeTab !== 'assignments'"
                    @click="openCreateModal"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors flex items-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Create Template
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-xl shadow-sm p-2 flex gap-2">
            <button
                v-for="tab in tabs"
                :key="tab.id"
                @click="activeTab = tab.id; loadTemplates()"
                class="px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2"
                :class="activeTab === tab.id 
                    ? 'bg-slate-900 text-white' 
                    : 'text-slate-600 hover:bg-slate-100'"
            >
                <span>{{ tab.icon }}</span>
                {{ tab.name }}
            </button>
        </div>

        <!-- Template Assignments Tab -->
        <div v-if="activeTab === 'assignments'" class="bg-white rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-semibold text-slate-900 mb-4">Template Assignments</h2>
            <p class="text-sm text-slate-600 mb-6">Assign templates to specific functions (appointments, invoices, etc.)</p>
            
            <div class="space-y-4">
                <div
                    v-for="functionType in functionTypes"
                    :key="functionType"
                    class="border border-slate-200 rounded-lg p-4"
                >
                    <h3 class="font-medium text-slate-900 mb-3 capitalize">{{ functionType.replace('_', ' ') }}</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <!-- Email Template Assignment -->
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Email Template</label>
                            <select
                                v-model="assignments[functionType].email"
                                @change="saveAssignment(functionType, 'email', assignments[functionType].email)"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                            <label class="block text-sm font-medium text-slate-700 mb-1">SMS Template</label>
                            <select
                                v-model="assignments[functionType].sms"
                                @change="saveAssignment(functionType, 'sms', assignments[functionType].sms)"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                            <label class="block text-sm font-medium text-slate-700 mb-1">WhatsApp Template</label>
                            <select
                                v-model="assignments[functionType].whatsapp"
                                @change="saveAssignment(functionType, 'whatsapp', assignments[functionType].whatsapp)"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
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
        <div v-if="activeTab === 'email'">
            <div v-if="loading" class="flex justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900"></div>
            </div>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="template in templates"
                    :key="template.id"
                    class="bg-white rounded-xl shadow-sm border border-slate-200 hover:shadow-md transition-shadow overflow-hidden"
                >
                    <div class="h-32 bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center">
                        <div class="text-white text-center">
                            <div class="text-3xl mb-2">📧</div>
                            <div class="text-sm font-medium">{{ template.category }}</div>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-slate-900 mb-1">{{ template.name }}</h3>
                        <p class="text-sm text-slate-600 mb-3 line-clamp-2">{{ template.description || 'No description' }}</p>
                        <div class="flex items-center justify-between text-xs text-slate-500 mb-3">
                            <span>Subject: {{ template.subject }}</span>
                            <span :class="template.is_active ? 'text-green-600' : 'text-red-600'">
                                {{ template.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <button
                                @click="editTemplate(template)"
                                class="flex-1 px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                            >
                                Edit
                            </button>
                            <button
                                @click="duplicateTemplate(template, 'email')"
                                class="px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                                title="Duplicate"
                            >
                                📋
                            </button>
                            <button
                                v-if="!template.is_prebuilt"
                                @click="deleteTemplate(template, 'email')"
                                class="px-3 py-2 text-sm border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors"
                                title="Delete"
                            >
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="templates.length === 0" class="col-span-full text-center py-12">
                    <div class="text-6xl mb-4">📧</div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">No email templates yet</h3>
                    <button
                        @click="openCreateModal"
                        class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                    >
                        Create Template
                    </button>
                </div>
            </div>
        </div>

        <!-- SMS Templates Tab -->
        <div v-if="activeTab === 'sms'">
            <div v-if="loading" class="flex justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900"></div>
            </div>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="template in templates"
                    :key="template.id"
                    class="bg-white rounded-xl shadow-sm border border-slate-200 hover:shadow-md transition-shadow overflow-hidden"
                >
                    <div class="h-32 bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center">
                        <div class="text-white text-center">
                            <div class="text-3xl mb-2">📱</div>
                            <div class="text-sm font-medium">{{ template.category }}</div>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-slate-900 mb-1">{{ template.name }}</h3>
                        <p class="text-sm text-slate-600 mb-3 line-clamp-3">{{ template.message || 'No message' }}</p>
                        <div class="flex items-center justify-between text-xs text-slate-500 mb-3">
                            <span>{{ template.message?.length || 0 }} characters</span>
                            <span :class="template.is_active ? 'text-green-600' : 'text-red-600'">
                                {{ template.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <button
                                @click="editSmsTemplate(template)"
                                class="flex-1 px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                            >
                                Edit
                            </button>
                            <button
                                @click="duplicateTemplate(template, 'sms')"
                                class="px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                            >
                                📋
                            </button>
                            <button
                                @click="deleteTemplate(template, 'sms')"
                                class="px-3 py-2 text-sm border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors"
                            >
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="templates.length === 0" class="col-span-full text-center py-12">
                    <div class="text-6xl mb-4">📱</div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">No SMS templates yet</h3>
                    <button
                        @click="openCreateSmsModal"
                        class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                    >
                        Create Template
                    </button>
                </div>
            </div>
        </div>

        <!-- WhatsApp Templates Tab -->
        <div v-if="activeTab === 'whatsapp'">
            <div v-if="loading" class="flex justify-center py-12">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900"></div>
            </div>
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="template in templates"
                    :key="template.id"
                    class="bg-white rounded-xl shadow-sm border border-slate-200 hover:shadow-md transition-shadow overflow-hidden"
                >
                    <div class="h-32 bg-gradient-to-br from-emerald-500 to-teal-600 flex items-center justify-center">
                        <div class="text-white text-center">
                            <div class="text-3xl mb-2">💬</div>
                            <div class="text-sm font-medium">{{ template.category }}</div>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="font-semibold text-slate-900 mb-1">{{ template.name }}</h3>
                        <p class="text-sm text-slate-600 mb-3 line-clamp-3">{{ template.message || 'No message' }}</p>
                        <div v-if="template.media_url" class="text-xs text-slate-500 mb-2">
                            📎 Media: {{ template.media_type || 'image' }}
                        </div>
                        <div class="flex items-center justify-between text-xs text-slate-500 mb-3">
                            <span>{{ template.message?.length || 0 }} characters</span>
                            <span :class="template.is_active ? 'text-green-600' : 'text-red-600'">
                                {{ template.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <button
                                @click="editWhatsappTemplate(template)"
                                class="flex-1 px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                            >
                                Edit
                            </button>
                            <button
                                @click="duplicateTemplate(template, 'whatsapp')"
                                class="px-3 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                            >
                                📋
                            </button>
                            <button
                                @click="deleteTemplate(template, 'whatsapp')"
                                class="px-3 py-2 text-sm border border-red-300 text-red-600 rounded-lg hover:bg-red-50 transition-colors"
                            >
                                🗑️
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="templates.length === 0" class="col-span-full text-center py-12">
                    <div class="text-6xl mb-4">💬</div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">No WhatsApp templates yet</h3>
                    <button
                        @click="openCreateWhatsappModal"
                        class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors"
                    >
                        Create Template
                    </button>
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
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
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

const tabs = [
    { id: 'email', name: 'Email Templates', icon: '📧' },
    { id: 'sms', name: 'SMS Templates', icon: '📱' },
    { id: 'whatsapp', name: 'WhatsApp Templates', icon: '💬' },
    { id: 'assignments', name: 'Template Assignments', icon: '⚙️' },
];

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

const deleteTemplate = async (template, type) => {
    if (!confirm(`Are you sure you want to delete "${template.name}"?`)) {
        return;
    }

    try {
        const endpoint = type === 'email' ? 'email-templates' : type === 'sms' ? 'message-templates' : 'whatsapp-templates';
        await axios.delete(`/api/${endpoint}/${template.id}`);
        toast.success('Template deleted successfully');
        loadTemplates();
    } catch (error) {
        console.error('Failed to delete template:', error);
        toast.error(error.response?.data?.message || 'Failed to delete template');
    }
};

onMounted(() => {
    loadTemplates();
});
</script>
