<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">Send Email</h2>
                    <p class="text-sm text-slate-500 mt-1">Select customer and template to send</p>
                </div>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-6 space-y-6">
                <!-- Template Selection -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Select Template *</label>
                    <select
                        v-model="selectedTemplateId"
                        @change="loadTemplate"
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Choose a template...</option>
                        <optgroup v-for="category in templateCategories" :key="category" :label="category">
                            <option
                                v-for="template in templates.filter(t => t.category === category.toLowerCase().replace(' ', '_'))"
                                :key="template.id"
                                :value="template.id"
                            >
                                {{ template.name }}
                            </option>
                        </optgroup>
                    </select>
                </div>

                <!-- Customer Selection -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Select Customer *</label>
                    <div class="flex gap-2">
                        <input
                            v-model="customerSearch"
                            @input="searchCustomers"
                            type="text"
                            placeholder="Search customers..."
                            class="flex-1 px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>
                    <div v-if="customerSearch" class="mt-2 max-h-48 overflow-y-auto border border-slate-300 rounded-lg">
                        <div
                            v-for="customer in filteredCustomers"
                            :key="customer.id"
                            @click="selectCustomer(customer)"
                            class="p-3 hover:bg-slate-50 cursor-pointer border-b border-slate-200 last:border-0"
                        >
                            <div class="font-medium text-slate-900">{{ customer.name }}</div>
                            <div class="text-sm text-slate-600">{{ customer.email || 'No email' }}</div>
                        </div>
                        <div v-if="filteredCustomers.length === 0" class="p-3 text-sm text-slate-500 text-center">
                            No customers found
                        </div>
                    </div>
                    <div v-if="selectedCustomer" class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-medium text-blue-900">{{ selectedCustomer.name }}</div>
                                <div class="text-sm text-blue-700">{{ selectedCustomer.email }}</div>
                            </div>
                            <button
                                @click="selectedCustomer = null"
                                class="text-blue-600 hover:text-blue-800"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Email Preview -->
                <div v-if="selectedTemplate && selectedCustomer">
                    <label class="block text-sm font-medium text-slate-700 mb-2">Preview</label>
                    <div class="border border-slate-300 rounded-lg p-4 bg-slate-50 max-h-64 overflow-y-auto">
                        <div class="text-sm font-semibold mb-2">Subject: {{ previewSubject }}</div>
                        <div class="text-xs text-slate-600 whitespace-pre-wrap">{{ previewContent }}</div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 border-t border-slate-200 flex justify-end gap-3">
                <button
                    @click="$emit('close')"
                    class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                >
                    Cancel
                </button>
                <button
                    @click="sendEmail"
                    :disabled="!selectedTemplate || !selectedCustomer || sending"
                    class="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
                >
                    {{ sending ? 'Sending...' : 'Send Email' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const emit = defineEmits(['close', 'sent']);

const templates = ref([]);
const customers = ref([]);
const selectedTemplateId = ref('');
const selectedTemplate = ref(null);
const selectedCustomer = ref(null);
const customerSearch = ref('');
const filteredCustomers = ref([]);
const sending = ref(false);

const templateCategories = ['Welcome', 'Epos', 'Teya', 'Appointment', 'Invoice', 'Follow Up', 'Quote', 'Thank You', 'Reminder', 'Custom'];

const previewSubject = computed(() => {
    if (!selectedTemplate || !selectedCustomer) return '';
    return replaceVariables(selectedTemplate.subject, selectedCustomer);
});

const previewContent = computed(() => {
    if (!selectedTemplate || !selectedCustomer) return '';
    // Simple preview - in real implementation, render the full template
    return 'Email preview will show the full template with customer data...';
});

const loadTemplates = async () => {
    try {
        const response = await axios.get('/api/email-templates?active=1');
        templates.value = response.data;
    } catch (error) {
        console.error('Failed to load templates:', error);
        toast.error('Failed to load templates');
    }
};

const loadTemplate = async () => {
    if (!selectedTemplateId.value) {
        selectedTemplate.value = null;
        return;
    }
    try {
        const response = await axios.get(`/api/email-templates/${selectedTemplateId.value}`);
        selectedTemplate.value = response.data;
    } catch (error) {
        console.error('Failed to load template:', error);
        toast.error('Failed to load template');
    }
};

const searchCustomers = async () => {
    if (!customerSearch.value || customerSearch.value.length < 2) {
        filteredCustomers.value = [];
        return;
    }
    try {
        const response = await axios.get(`/api/customers?search=${customerSearch.value}&per_page=10`);
        filteredCustomers.value = response.data.data || [];
    } catch (error) {
        console.error('Failed to search customers:', error);
    }
};

const selectCustomer = (customer) => {
    selectedCustomer.value = customer;
    customerSearch.value = '';
    filteredCustomers.value = [];
};

const replaceVariables = (text, customer) => {
    if (!text || !customer) return text;
    return text
        .replace(/\{\{customer_name\}\}/g, customer.name || '')
        .replace(/\{\{customer_email\}\}/g, customer.email || '')
        .replace(/\{\{customer_phone\}\}/g, customer.phone || '')
        .replace(/\{\{company_name\}\}/g, 'Switch & Save');
};

const sendEmail = async () => {
    if (!selectedTemplate || !selectedCustomer) {
        toast.error('Please select both template and customer');
        return;
    }

    if (!selectedCustomer.value.email) {
        toast.error('Customer does not have an email address');
        return;
    }

    sending.value = true;
    try {
        await axios.post('/api/email-templates/send', {
            template_id: selectedTemplate.value.id,
            customer_id: selectedCustomer.value.id,
        });
        toast.success('Email sent successfully!');
        emit('sent');
        emit('close');
    } catch (error) {
        console.error('Failed to send email:', error);
        toast.error(error.response?.data?.message || 'Failed to send email');
    } finally {
        sending.value = false;
    }
};

onMounted(() => {
    loadTemplates();
});
</script>

