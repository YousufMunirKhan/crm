<template>
    <BaseModal
        :model-value="true"
        title="Send Email"
        description="Select customer and template to send"
        size="lg"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <div class="space-y-6">
            <!-- Template Selection -->
            <div>
                <label class="form-label" for="sendemailmodal-select-template">
                    Select Template
                    <span class="form-required" aria-hidden="true">*</span>
                </label>
                <select
                    id="sendemailmodal-select-template"
                    v-model="selectedTemplateId"
                    class="form-select"
                    @change="loadTemplate"
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
                <label class="form-label" for="sendemailmodal-customer-search">
                    Select Customer
                    <span class="form-required" aria-hidden="true">*</span>
                </label>
                <div class="relative">
                    <MagnifyingGlassIcon
                        class="icon pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-500"
                        aria-hidden="true"
                    />
                    <input
                        id="sendemailmodal-customer-search"
                        v-model="customerSearch"
                        type="text"
                        placeholder="Search customers..."
                        class="form-input-search"
                        @input="searchCustomers"
                    />
                </div>
                <div
                    v-if="customerSearch"
                    class="mt-2 max-h-48 overflow-y-auto rounded-card border border-slate-200"
                >
                    <button
                        v-for="customer in filteredCustomers"
                        :key="customer.id"
                        type="button"
                        class="block w-full border-b border-slate-200 p-3 text-left last:border-0 hover:bg-slate-50 focus-visible:outline-none focus-visible:bg-slate-50 focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500/40"
                        @click="selectCustomer(customer)"
                    >
                        <span class="block font-medium text-slate-900">{{ customer.name }}</span>
                        <span class="block text-sm text-slate-600">{{ customer.email || 'No email' }}</span>
                    </button>
                    <p v-if="filteredCustomers.length === 0" class="p-3 text-center text-sm text-slate-500">
                        No customers found
                    </p>
                </div>
                <div v-if="selectedCustomer" class="callout callout-info mt-2">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-medium">{{ selectedCustomer.name }}</div>
                            <div class="text-sm">{{ selectedCustomer.email }}</div>
                        </div>
                        <BaseButton
                            variant="ghost"
                            size="icon"
                            label="Clear selected customer"
                            @click="selectedCustomer = null"
                        >
                            <XMarkIcon class="icon" aria-hidden="true" />
                        </BaseButton>
                    </div>
                </div>
            </div>

            <!-- Email Preview -->
            <div v-if="selectedTemplate && selectedCustomer">
                <h3 class="subsection-title">Preview</h3>
                <div class="max-h-64 overflow-y-auto rounded-card border border-slate-200 bg-slate-50 p-4">
                    <div class="mb-2 text-sm font-semibold text-slate-900">Subject: {{ previewSubject }}</div>
                    <div class="whitespace-pre-wrap text-xs text-slate-600">{{ previewContent }}</div>
                </div>
            </div>
        </div>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="$emit('close')">
                Cancel
            </BaseButton>
            <BaseButton
                variant="primary"
                block-mobile
                :disabled="!selectedTemplate || !selectedCustomer || sending"
                :loading="sending"
                @click="sendEmail"
            >
                <template #icon>
                    <PaperAirplaneIcon class="icon" aria-hidden="true" />
                </template>
                {{ sending ? 'Sending...' : 'Send Email' }}
            </BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { MagnifyingGlassIcon, PaperAirplaneIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { BaseButton, BaseModal } from '@/components/base';
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
