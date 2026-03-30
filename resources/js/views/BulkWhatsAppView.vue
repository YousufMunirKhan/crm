<template>
    <div class="h-full flex bg-slate-50">
        <!-- Left Sidebar - Customer List -->
        <div class="w-80 bg-white border-r border-slate-200 flex flex-col">
            <div class="p-4 border-b border-slate-200">
                <h2 class="text-lg font-semibold text-slate-900 mb-3">Customers</h2>
                <input
                    v-model="searchQuery"
                    @input="loadCustomers"
                    type="text"
                    placeholder="Search customers..."
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500 text-sm"
                />
            </div>
            
            <div class="flex-1 overflow-y-auto">
                <div v-if="loadingCustomers" class="p-4 text-center text-slate-500">
                    Loading...
                </div>
                <div v-else-if="customers.length === 0" class="p-4 text-center text-slate-500">
                    No customers found
                </div>
                <div v-else class="divide-y divide-slate-100">
                    <div
                        v-for="customer in customers"
                        :key="customer.id"
                        @click="selectCustomer(customer)"
                        :class="[
                            'p-4 cursor-pointer hover:bg-slate-50 transition-colors',
                            selectedCustomerIds.includes(customer.id) ? 'bg-blue-50 border-l-4 border-blue-500' : ''
                        ]"
                    >
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex-1">
                                <div class="font-medium text-slate-900">{{ customer.name }}</div>
                                <div class="text-xs text-slate-500 mt-1">
                                    {{ customer.whatsapp_number || customer.phone || 'No WhatsApp' }}
                                </div>
                            </div>
                            <input
                                type="checkbox"
                                :checked="selectedCustomerIds.includes(customer.id)"
                                @click.stop="toggleCustomer(customer.id)"
                                class="mt-1"
                            />
                        </div>
                        
                        <!-- Last Message Preview -->
                        <div v-if="customer.last_whatsapp_message" class="mt-2 p-2 bg-slate-50 rounded text-xs">
                            <div class="text-slate-600 line-clamp-2">
                                {{ customer.last_whatsapp_message.message }}
                            </div>
                            <div class="flex items-center justify-between mt-1 text-slate-400">
                                <span>{{ formatDate(customer.last_whatsapp_message.created_at) }}</span>
                                <span v-if="customer.last_whatsapp_message.campaign_name" class="text-blue-600">
                                    📢 Campaign
                                </span>
                                <span v-else class="text-green-600">💬 Single</span>
                            </div>
                            <div v-if="customer.last_whatsapp_message.media_url" class="mt-1">
                                <img 
                                    :src="customer.last_whatsapp_message.media_url" 
                                    alt="Media"
                                    class="w-full h-20 object-cover rounded"
                                />
                            </div>
                        </div>
                        <div v-else class="mt-2 text-xs text-slate-400 italic">
                            No messages sent yet
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="p-4 border-t border-slate-200 bg-slate-50">
                <div class="text-sm text-slate-600 mb-2">
                    Selected: {{ selectedCustomerIds.length }} / {{ customers.length }}
                </div>
                <button
                    @click="selectAll"
                    class="text-sm text-blue-600 hover:text-blue-800 mr-3"
                >
                    Select All
                </button>
                <button
                    @click="clearSelection"
                    class="text-sm text-slate-600 hover:text-slate-800"
                >
                    Clear
                </button>
            </div>
        </div>

        <!-- Right Side - Message Composer -->
        <div class="flex-1 flex flex-col">
            <div class="p-6 border-b border-slate-200 bg-white">
                <h1 class="text-2xl font-bold text-slate-900">Bulk WhatsApp Messaging</h1>
                <p class="text-sm text-slate-500 mt-1">Send messages to one or multiple customers</p>
            </div>

            <div class="flex-1 overflow-y-auto p-6">
                <div class="max-w-3xl mx-auto space-y-6">
                    <!-- Message Type Selection -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-slate-900 mb-4">Message Type</h3>
                        <div class="flex gap-4">
                            <button
                                @click="messageType = 'single'"
                                :class="[
                                    'px-4 py-2 rounded-lg transition-colors',
                                    messageType === 'single' 
                                        ? 'bg-slate-900 text-white' 
                                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                ]"
                            >
                                Single Message
                            </button>
                            <button
                                @click="messageType = 'campaign'"
                                :class="[
                                    'px-4 py-2 rounded-lg transition-colors',
                                    messageType === 'campaign' 
                                        ? 'bg-slate-900 text-white' 
                                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                                ]"
                            >
                                Campaign (Bulk)
                            </button>
                        </div>
                    </div>

                    <!-- Campaign Name (if campaign) -->
                    <div v-if="messageType === 'campaign'" class="bg-white rounded-xl shadow-sm p-6">
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Campaign Name
                        </label>
                        <input
                            v-model="campaignName"
                            type="text"
                            placeholder="e.g., Summer Sale Promotion"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>

                    <!-- Message Composer -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Message
                        </label>
                        <textarea
                            v-model="message"
                            rows="6"
                            placeholder="Type your message here..."
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        ></textarea>
                        <div class="text-xs text-slate-500 mt-1">
                            {{ message.length }} characters
                        </div>
                    </div>

                    <!-- Image Upload -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <label class="block text-sm font-medium text-slate-700 mb-2">
                            Image/Media (Optional)
                        </label>
                        <div v-if="!selectedImage" class="border-2 border-dashed border-slate-300 rounded-lg p-6 text-center">
                            <input
                                ref="fileInput"
                                type="file"
                                accept="image/*"
                                @change="handleImageSelect"
                                class="hidden"
                            />
                            <button
                                @click="fileInput?.click()"
                                class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg hover:bg-slate-200"
                            >
                                📷 Upload Image
                            </button>
                            <p class="text-xs text-slate-500 mt-2">Max 10MB</p>
                        </div>
                        <div v-else class="relative">
                            <img :src="imagePreview" alt="Preview" class="w-full h-64 object-cover rounded-lg" />
                            <button
                                @click="removeImage"
                                class="absolute top-2 right-2 px-3 py-1 bg-red-500 text-white rounded-lg hover:bg-red-600"
                            >
                                Remove
                            </button>
                        </div>
                    </div>

                    <!-- Send Options -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-slate-900 mb-4">Send Options</h3>
                        
                        <div v-if="messageType === 'campaign'" class="mb-4">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="sendToAll"
                                    type="checkbox"
                                    class="rounded"
                                />
                                <span class="text-sm text-slate-700">Send to all customers (ignore selection)</span>
                            </label>
                        </div>

                        <div class="mb-4">
                            <label class="flex items-center gap-2">
                                <input
                                    v-model="scheduleMessage"
                                    type="checkbox"
                                    class="rounded"
                                />
                                <span class="text-sm text-slate-700">Schedule for later</span>
                            </label>
                        </div>

                        <div v-if="scheduleMessage" class="mb-4">
                            <label class="block text-sm font-medium text-slate-700 mb-2">
                                Schedule Date & Time
                            </label>
                            <input
                                v-model="scheduledDateTime"
                                type="datetime-local"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                            />
                        </div>
                    </div>

                    <!-- Send Button -->
                    <div class="bg-white rounded-xl shadow-sm p-6">
                        <button
                            @click="sendMessage"
                            :disabled="sending || !canSend"
                            class="w-full px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed font-medium"
                        >
                            {{ sending ? 'Sending...' : getSendButtonText() }}
                        </button>
                    </div>

                    <!-- Campaign History -->
                    <div v-if="messageType === 'campaign'" class="bg-white rounded-xl shadow-sm p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-slate-900">Campaign History</h3>
                            <button
                                @click="loadCampaigns"
                                class="text-sm text-blue-600 hover:text-blue-800"
                            >
                                Refresh
                            </button>
                        </div>
                        <div v-if="loadingCampaigns" class="text-center text-slate-500 py-4">
                            Loading...
                        </div>
                        <div v-else-if="campaigns.length === 0" class="text-center text-slate-500 py-4">
                            No campaigns yet
                        </div>
                        <div v-else class="space-y-3">
                            <div
                                v-for="campaign in campaigns"
                                :key="campaign.id"
                                class="border border-slate-200 rounded-lg p-4 hover:bg-slate-50"
                            >
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <div class="font-medium text-slate-900">{{ campaign.name }}</div>
                                        <div class="text-xs text-slate-500 mt-1">
                                            Created: {{ formatDate(campaign.created_at) }}
                                        </div>
                                    </div>
                                    <span :class="getStatusClass(campaign.status)">
                                        {{ campaign.status }}
                                    </span>
                                </div>
                                <div class="text-sm text-slate-600 mb-2 line-clamp-2">
                                    {{ campaign.message }}
                                </div>
                                <div class="flex items-center justify-between text-xs text-slate-500">
                                    <span>Total: {{ campaign.total_customers }} | Sent: {{ campaign.sent_count }} | Failed: {{ campaign.failed_count }}</span>
                                    <button
                                        @click="viewCampaign(campaign.id)"
                                        class="text-blue-600 hover:text-blue-800"
                                    >
                                        View Details
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

const customers = ref([]);
const selectedCustomerIds = ref([]);
const searchQuery = ref('');
const loadingCustomers = ref(false);
const message = ref('');
const selectedImage = ref(null);
const imagePreview = ref(null);
const messageType = ref('single'); // 'single' or 'campaign'
const campaignName = ref('');
const sendToAll = ref(false);
const scheduleMessage = ref(false);
const scheduledDateTime = ref('');
const sending = ref(false);
const campaigns = ref([]);
const loadingCampaigns = ref(false);

const canSend = computed(() => {
    if (!message.value.trim()) return false;
    if (messageType.value === 'campaign' && !campaignName.value.trim()) return false;
    if (messageType.value === 'single' && selectedCustomerIds.value.length === 0) return false;
    if (scheduleMessage.value && !scheduledDateTime.value) return false;
    return true;
});

const loadCustomers = async () => {
    loadingCustomers.value = true;
    try {
        const params = new URLSearchParams();
        if (searchQuery.value) params.append('search', searchQuery.value);
        params.append('per_page', '100');
        
        const response = await axios.get(`/api/bulk-whatsapp/customers?${params}`);
        console.log('API Response:', response.data);
        
        // Handle paginated response
        if (response.data && response.data.data) {
            customers.value = response.data.data;
            console.log('Customers loaded:', customers.value.length);
        } else if (Array.isArray(response.data)) {
            customers.value = response.data;
            console.log('Customers loaded (array):', customers.value.length);
        } else {
            console.warn('Unexpected response format:', response.data);
            customers.value = [];
        }
    } catch (error) {
        console.error('Error loading customers:', error);
        console.error('Error response:', error.response);
        console.error('Error details:', error.response?.data);
        customers.value = [];
        alert('Failed to load customers. Check console for details.');
    } finally {
        loadingCustomers.value = false;
    }
};

const selectCustomer = (customer) => {
    if (!selectedCustomerIds.value.includes(customer.id)) {
        selectedCustomerIds.value.push(customer.id);
    }
};

const toggleCustomer = (customerId) => {
    const index = selectedCustomerIds.value.indexOf(customerId);
    if (index > -1) {
        selectedCustomerIds.value.splice(index, 1);
    } else {
        selectedCustomerIds.value.push(customerId);
    }
};

const selectAll = () => {
    selectedCustomerIds.value = customers.value.map(c => c.id);
};

const clearSelection = () => {
    selectedCustomerIds.value = [];
};

const handleImageSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
        if (file.size > 10 * 1024 * 1024) {
            alert('Image size must be less than 10MB');
            return;
        }
        selectedImage.value = file;
        const reader = new FileReader();
        reader.onload = (e) => {
            imagePreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const fileInput = ref(null);

const removeImage = () => {
    selectedImage.value = null;
    imagePreview.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const sendMessage = async () => {
    if (!canSend.value) return;

    sending.value = true;
    try {
        const formData = new FormData();
        
        if (messageType.value === 'single') {
            // Single message
            formData.append('customer_ids', JSON.stringify(selectedCustomerIds.value));
            formData.append('message', message.value);
            if (selectedImage.value) {
                formData.append('media', selectedImage.value);
            }
            
            const response = await axios.post('/api/bulk-whatsapp/send-single', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            
            alert(`Sent: ${response.data.sent}, Failed: ${response.data.failed}`);
            
            // Reset form
            message.value = '';
            selectedImage.value = null;
            imagePreview.value = null;
            selectedCustomerIds.value = [];
        } else {
            // Campaign
            formData.append('name', campaignName.value);
            formData.append('message', message.value);
            formData.append('send_type', sendToAll.value ? 'all' : 'selected');
            if (!sendToAll.value) {
                formData.append('selected_customer_ids', JSON.stringify(selectedCustomerIds.value));
            }
            if (selectedImage.value) {
                formData.append('media', selectedImage.value);
            }
            if (scheduleMessage.value && scheduledDateTime.value) {
                formData.append('scheduled_at', scheduledDateTime.value);
            }
            
            const response = await axios.post('/api/bulk-whatsapp/campaigns', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            });
            
            alert('Campaign created successfully!');
            
            // Reset form
            message.value = '';
            campaignName.value = '';
            selectedImage.value = null;
            imagePreview.value = null;
            selectedCustomerIds.value = [];
            scheduleMessage.value = false;
            scheduledDateTime.value = '';
            
            // Reload campaigns
            loadCampaigns();
        }
    } catch (error) {
        console.error('Error sending message:', error);
        alert('Error sending message: ' + (error.response?.data?.message || error.message));
    } finally {
        sending.value = false;
    }
};

const getSendButtonText = () => {
    if (messageType.value === 'single') {
        return `Send to ${selectedCustomerIds.value.length} customer(s)`;
    } else {
        if (sendToAll.value) {
            return 'Create Campaign (All Customers)';
        } else {
            return `Create Campaign (${selectedCustomerIds.value.length} customers)`;
        }
    }
};

const loadCampaigns = async () => {
    loadingCampaigns.value = true;
    try {
        const response = await axios.get('/api/bulk-whatsapp/campaigns');
        campaigns.value = response.data.data || response.data;
    } catch (error) {
        console.error('Error loading campaigns:', error);
    } finally {
        loadingCampaigns.value = false;
    }
};

const viewCampaign = (id) => {
    // TODO: Open campaign details modal
    alert('Campaign details coming soon');
};

const getStatusClass = (status) => {
    const classes = {
        'draft': 'px-2 py-1 bg-slate-100 text-slate-700 rounded text-xs',
        'scheduled': 'px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs',
        'sending': 'px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs',
        'completed': 'px-2 py-1 bg-green-100 text-green-700 rounded text-xs',
        'failed': 'px-2 py-1 bg-red-100 text-red-700 rounded text-xs',
    };
    return classes[status] || classes.draft;
};

const formatDate = (date) => {
    if (!date) return '';
    return new Date(date).toLocaleString();
};

onMounted(() => {
    loadCustomers();
    if (messageType.value === 'campaign') {
        loadCampaigns();
    }
});
</script>

