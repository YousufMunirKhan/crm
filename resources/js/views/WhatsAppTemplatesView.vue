<template>
    <div class="max-w-7xl mx-auto p-4 md:p-6 space-y-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">WhatsApp Templates</h1>
                <p class="text-sm text-slate-600 mt-1">Manage WhatsApp message templates for Meta Cloud API</p>
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
                        <p class="text-xs text-slate-500 mt-1">Use {{1}}, {{2}}, etc. for variables</p>
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
});

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
    if (!newTemplate.name || !newTemplate.bodyText) {
        toast.error('Please fill in all required fields');
        return;
    }

    creating.value = true;
    try {
        // Build components array for Meta API
        const components = [
            {
                type: 'BODY',
                text: newTemplate.bodyText,
            }
        ];

        await axios.post('/api/whatsapp/templates', {
            name: newTemplate.name,
            category: newTemplate.category,
            language: newTemplate.language,
            components: components,
        });

        toast.success('Template created and submitted to Meta');
        showCreateModal.value = false;
        Object.assign(newTemplate, {
            name: '',
            category: 'TRANSACTIONAL',
            language: 'en_US',
            bodyText: '',
        });
        await loadTemplates();
    } catch (error) {
        console.error('Failed to create template:', error);
        toast.error(error.response?.data?.message || 'Failed to create template');
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

const viewTemplate = (template) => {
    // You can implement a detailed view modal here
    alert(`Template: ${template.name}\nStatus: ${template.status}\nCategory: ${template.category}`);
};

watch([() => filters.status, () => filters.category], () => {
    loadTemplates();
});

onMounted(() => {
    loadTemplates();
});
</script>

