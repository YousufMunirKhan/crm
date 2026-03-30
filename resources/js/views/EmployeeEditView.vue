<template>
    <div class="max-w-4xl mx-auto p-4 lg:p-6 space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button
                    @click="$router.back()"
                    class="p-2 hover:bg-slate-100 rounded-lg transition"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
                <h1 class="text-xl lg:text-2xl font-bold text-slate-900">Edit Employee</h1>
            </div>
        </div>

        <div v-if="loading" class="text-center py-12 text-slate-500">
            Loading...
        </div>

        <form
            v-else
            class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 lg:p-6 space-y-6"
            @submit.prevent="handleSubmit"
        >
            <!-- Personal details -->
            <div class="space-y-4">
                <h2 class="text-sm font-semibold text-slate-900">Personal details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Full name *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
                        <input
                            v-model="form.email"
                            type="email"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                        <input
                            v-model="form.phone"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Date of birth</label>
                        <input
                            v-model="form.date_of_birth"
                            type="date"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Address</label>
                    <textarea
                        v-model="form.address"
                        rows="2"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>
            </div>

            <!-- Job & access -->
            <div class="space-y-4">
                <h2 class="text-sm font-semibold text-slate-900">Job & access</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Role *</label>
                        <select
                            v-model="form.role_id"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="">Select role</option>
                            <option v-for="role in roles" :key="role.id" :value="role.id">
                                {{ role.name }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Employee type</label>
                        <select
                            v-model="form.employee_type"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="">Select type</option>
                            <option value="field_worker">Field Worker</option>
                            <option value="call_center">Call Center</option>
                            <option value="ticket_manager">Ticket Manager</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Hire date</label>
                        <input
                            v-model="form.hire_date"
                            type="date"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select
                            v-model="form.is_active"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option :value="true">Active</option>
                            <option :value="false">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Bank details -->
            <div class="space-y-4">
                <h2 class="text-sm font-semibold text-slate-900">Bank details</h2>
                <p class="text-xs text-slate-500">
                    These details are used for HR and salary purposes only.
                </p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Account holder name</label>
                        <input
                            v-model="form.bank_account_name"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Bank name</label>
                        <input
                            v-model="form.bank_name"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Sort code</label>
                        <input
                            v-model="form.bank_sort_code"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Account number</label>
                        <input
                            v-model="form.bank_account_number"
                            type="text"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        />
                    </div>
                </div>
            </div>

            <!-- Documents: existing + new attachments -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-slate-900">Attachments</h2>
                    <span class="text-xs text-slate-500">Existing files plus new ones you add here.</span>
                </div>

                <!-- Existing documents -->
                <div v-if="documents.length" class="space-y-1 text-xs text-slate-700 border border-slate-200 rounded-lg p-3">
                    <div class="font-semibold text-slate-800 mb-1">Current files</div>
                    <div
                        v-for="doc in documents"
                        :key="doc.id"
                        class="flex items-center justify-between"
                    >
                        <div>
                            <span class="font-medium text-slate-900">{{ doc.name }}</span>
                            <span class="text-slate-500 ml-1">({{ formatDate(doc.created_at) }})</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <a
                                :href="doc.file_path"
                                target="_blank"
                                rel="noopener"
                                class="text-blue-600 hover:underline"
                            >
                                View
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Add new attachments -->
                <div class="grid grid-cols-1 md:grid-cols-[2fr,2fr,auto] gap-3 items-center">
                    <input
                        v-model="newDocName"
                        type="text"
                        placeholder="Attachment name"
                        class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                    <div>
                        <label
                            class="inline-flex items-center gap-2 px-3 py-2 border border-dashed border-slate-300 rounded-lg text-xs text-slate-600 cursor-pointer hover:bg-slate-50"
                        >
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1M4 8V7a3 3 0 013-3h4m4 0h2a3 3 0 013 3v1M16 4l-4-4-4 4m4-4v12" />
                            </svg>
                            <span>Browse files</span>
                            <input
                                ref="fileInput"
                                type="file"
                                multiple
                                class="hidden"
                            />
                        </label>
                    </div>
                    <button
                        type="button"
                        class="px-3 py-2 text-xs bg-slate-900 text-white rounded-lg hover:bg-slate-800"
                        @click="queueDocument"
                    >
                        Add
                    </button>
                </div>
                <div v-if="queuedDocuments.length" class="space-y-1 text-xs text-slate-700">
                    <div
                        v-for="(doc, index) in queuedDocuments"
                        :key="index"
                        class="flex items-center justify-between"
                    >
                        <span>{{ doc.name }} ({{ doc.file?.name }})</span>
                        <button
                            type="button"
                            class="text-red-600 hover:underline"
                            @click="removeQueuedDocument(index)"
                        >
                            Remove
                        </button>
                    </div>
                </div>
            </div>

            <!-- Password -->
            <div class="space-y-2">
                <label class="block text-sm font-medium text-slate-700 mb-1">Password (leave blank to keep current)</label>
                <input
                    v-model="form.password"
                    type="password"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                />
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button
                    type="button"
                    class="px-4 py-2 border border-slate-300 rounded-lg text-sm text-slate-700 hover:bg-slate-50"
                    @click="$router.back()"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    :disabled="saving"
                    class="px-4 py-2 rounded-lg bg-slate-900 text-sm text-white hover:bg-slate-800 disabled:opacity-50"
                >
                    {{ saving ? 'Saving...' : 'Save changes' }}
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const loading = ref(true);
const saving = ref(false);
const roles = ref([]);
const form = ref({
    name: '',
    email: '',
    password: '',
    role_id: '',
    employee_type: '',
    phone: '',
    address: '',
    hire_date: '',
    date_of_birth: '',
    is_active: true,
    bank_account_name: '',
    bank_name: '',
    bank_sort_code: '',
    bank_account_number: '',
});

const queuedDocuments = ref([]);
const newDocName = ref('');
const fileInput = ref(null);
const documents = ref([]);

const loadRoles = async () => {
    try {
        const { data } = await axios.get('/api/roles');
        roles.value = data;
    } catch (e) {
        console.error('Failed to load roles', e);
    }
};

const loadEmployee = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/users/${route.params.id}`);
        form.value = {
            name: data.name || '',
            email: data.email || '',
            password: '',
            role_id: data.role_id || '',
            employee_type: data.employee_type || '',
            phone: data.phone || '',
            address: data.address || '',
            hire_date: data.hire_date || '',
            date_of_birth: data.date_of_birth || '',
            is_active: data.is_active ?? true,
            bank_account_name: data.bank_account_name || '',
            bank_name: data.bank_name || '',
            bank_sort_code: data.bank_sort_code || '',
            bank_account_number: data.bank_account_number || '',
        };

        // Load existing documents for this employee
        try {
            const docsRes = await axios.get(`/api/hr/employees/${route.params.id}/documents`);
            documents.value = docsRes.data || [];
        } catch (e) {
            console.error('Failed to load employee documents', e);
        }
    } catch (e) {
        console.error('Failed to load employee', e);
        toast.error('Failed to load employee');
    } finally {
        loading.value = false;
    }
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const handleSubmit = async () => {
    saving.value = true;
    try {
        const payload = { ...form.value };
        if (!payload.password) {
            delete payload.password;
        }
        if (!payload.employee_type) payload.employee_type = null;
        if (!payload.phone) payload.phone = null;
        if (!payload.address) payload.address = null;
        if (!payload.hire_date) payload.hire_date = null;
        if (!payload.date_of_birth) payload.date_of_birth = null;

        await axios.put(`/api/users/${route.params.id}`, payload);

        // Upload any queued documents
        if (queuedDocuments.value.length) {
            for (const doc of queuedDocuments.value) {
                const fd = new FormData();
                fd.append('name', doc.name);
                fd.append('file', doc.file);
                try {
                    await axios.post(`/api/hr/employees/${route.params.id}/documents`, fd, {
                        headers: { 'Content-Type': 'multipart/form-data' },
                    });
                } catch (e) {
                    console.error('Failed to upload document', e);
                }
            }
        }

        toast.success('Employee updated.');
        router.push('/employees');
    } catch (e) {
        console.error('Failed to save employee', e);
        let msg = 'Failed to save employee';
        if (e.response?.data?.message) msg = e.response.data.message;
        if (e.response?.data?.errors) {
            const errs = Object.values(e.response.data.errors).flat();
            msg = errs.join(', ');
        }
        toast.error(msg);
    } finally {
        saving.value = false;
    }
};

onMounted(async () => {
    await loadRoles();
    await loadEmployee();
});

const queueDocument = () => {
    if (!fileInput.value?.files?.length) return;

    const files = Array.from(fileInput.value.files);
    files.forEach((f) => {
        queuedDocuments.value.push({
            name: newDocName.value || 'Attachment',
            file: f,
        });
    });

    newDocName.value = '';
    if (fileInput.value) fileInput.value.value = '';
};

const removeQueuedDocument = (index) => {
    queuedDocuments.value.splice(index, 1);
};
</script>

