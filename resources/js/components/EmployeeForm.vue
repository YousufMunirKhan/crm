<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto p-4">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-3xl w-full mx-4 my-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-slate-900">
                    {{ employee ? 'Edit Employee' : 'Add New Employee' }}
                </h3>
                <button
                    @click="$emit('close')"
                    class="text-slate-400 hover:text-slate-600"
                >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form @submit.prevent="handleSubmit" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Name *</label>
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
                        <label class="block text-sm font-medium text-slate-700 mb-1">Password {{ employee ? '(leave blank to keep current)' : '*' }}</label>
                        <input
                            v-model="form.password"
                            type="password"
                            :required="!employee"
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
                        <label class="block text-sm font-medium text-slate-700 mb-1">Role *</label>
                        <select
                            v-model="form.role_id"
                            required
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="">Select Role</option>
                            <option v-for="role in roles" :key="role.id" :value="role.id">
                                {{ role.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Employee Type</label>
                        <select
                            v-model="form.employee_type"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="">Select Type</option>
                            <option value="field_worker">Field Worker</option>
                            <option value="call_center">Call Center</option>
                            <option value="ticket_manager">Ticket Manager</option>
                        </select>
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

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Hire Date</label>
                        <input
                            v-model="form.hire_date"
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

                <div v-if="canEditNavPerms" class="border border-slate-200 rounded-lg p-4 bg-slate-50 space-y-3">
                    <h4 class="text-sm font-medium text-slate-900">Sidebar menu access</h4>
                    <p class="text-xs text-slate-600">
                        By default this user gets every menu item their role allows. Turn on to hide sections (Dashboard always stays).
                    </p>
                    <label class="flex items-center gap-2 text-sm text-slate-800 cursor-pointer">
                        <input
                            v-model="restrictMenu"
                            type="checkbox"
                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        Limit sidebar to selected sections only
                    </label>
                    <div
                        v-if="restrictMenu"
                        class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-52 overflow-y-auto border border-slate-200 rounded-lg p-3 bg-white"
                    >
                        <label
                            v-for="opt in NAV_SECTION_OPTIONS"
                            v-show="opt.key !== 'dashboard'"
                            :key="opt.key"
                            class="flex items-center gap-2 text-sm text-slate-700 cursor-pointer"
                        >
                            <input
                                v-model="sectionChecks[opt.key]"
                                type="checkbox"
                                class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                            />
                            {{ opt.label }}
                        </label>
                    </div>
                </div>

                <!-- Attachments when creating/editing (optional uploads) -->
                <div class="border-t pt-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="text-sm font-medium text-slate-900">Attachments</h4>
                        <span class="text-xs text-slate-500">Optional – ID, contract, proof, etc.</span>
                    </div>
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
                        <div v-for="(doc, index) in queuedDocuments" :key="index" class="flex items-center justify-between">
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

                <div v-if="!employee" class="border-t pt-4">
                    <label class="flex items-center space-x-2">
                        <input
                            v-model="form.send_contract"
                            type="checkbox"
                            class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span class="text-sm text-slate-700">Send employment contract via email</span>
                    </label>
                </div>

                <div class="flex justify-end space-x-3 pt-4">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                    >
                        {{ loading ? 'Saving...' : (employee ? 'Update' : 'Create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';
import { NAV_SECTION_OPTIONS } from '@/constants/navSections';

const toast = useToastStore();
const auth = useAuthStore();

const canEditNavPerms = computed(() => {
    const r = auth.user?.role?.name;
    return r === 'Admin' || r === 'System Admin';
});

function defaultSectionChecks() {
    const o = {};
    for (const { key } of NAV_SECTION_OPTIONS) {
        o[key] = true;
    }
    return o;
}

const sectionChecks = ref(defaultSectionChecks());
const restrictMenu = ref(false);

const props = defineProps({
    employee: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const form = ref({
    name: '',
    email: '',
    password: '',
    role_id: '',
    employee_type: '',
    phone: '',
    address: '',
    hire_date: '',
    send_contract: false,
    is_active: true,
});

const roles = ref([]);
const loading = ref(false);
const queuedDocuments = ref([]);
const newDocName = ref('');
const fileInput = ref(null);

const loadRoles = async () => {
    try {
        const response = await axios.get('/api/roles');
        roles.value = response.data;
    } catch (error) {
        console.error('Failed to load roles:', error);
    }
};

watch(() => props.employee, (newEmployee) => {
    if (newEmployee) {
        form.value = {
            name: newEmployee.name || '',
            email: newEmployee.email || '',
            password: '',
            role_id: newEmployee.role_id || '',
            employee_type: newEmployee.employee_type || '',
            phone: newEmployee.phone || '',
            address: newEmployee.address || '',
            hire_date: newEmployee.hire_date || '',
            send_contract: false,
            is_active: newEmployee.is_active ?? true,
        };
        const np = newEmployee.nav_permissions;
        if (np && typeof np === 'object' && Object.keys(np).length > 0) {
            restrictMenu.value = true;
            const d = defaultSectionChecks();
            for (const key of Object.keys(d)) {
                d[key] = !!np[key];
            }
            sectionChecks.value = d;
        } else {
            restrictMenu.value = false;
            sectionChecks.value = defaultSectionChecks();
        }
    } else {
        form.value = {
            name: '',
            email: '',
            password: '',
            role_id: '',
            employee_type: '',
            phone: '',
            address: '',
            hire_date: '',
            send_contract: false,
            is_active: true,
        };
        restrictMenu.value = false;
        sectionChecks.value = defaultSectionChecks();
    }
}, { immediate: true });

const handleSubmit = async () => {
    loading.value = true;
    try {
        const payload = { ...form.value };
        
        // Remove password if empty (for updates)
        if (props.employee && !payload.password) {
            delete payload.password;
        }

        // Remove empty strings and convert to null for optional fields
        if (!payload.phone) payload.phone = null;
        if (!payload.address) payload.address = null;
        if (!payload.hire_date) payload.hire_date = null;
        if (!payload.employee_type) payload.employee_type = null;

        if (canEditNavPerms.value) {
            if (restrictMenu.value) {
                const nav_permissions = {};
                for (const { key } of NAV_SECTION_OPTIONS) {
                    nav_permissions[key] = !!sectionChecks.value[key];
                }
                payload.nav_permissions = nav_permissions;
            } else {
                payload.nav_permissions = null;
            }
        }

        // Ensure send_contract is boolean
        if (payload.send_contract === undefined) {
            payload.send_contract = false;
        }

        let userId = props.employee?.id;

        if (props.employee) {
            // For updates, don't send send_contract (only for new employees)
            delete payload.send_contract;
            const response = await axios.put(`/api/users/${props.employee.id}`, payload);
            userId = response.data?.id || props.employee.id;
            if (props.employee.id === auth.user?.id) {
                await auth.bootstrap();
            }
            toast.success('Employee updated successfully!');
        } else {
            // For new employees, ensure password is provided
            if (!payload.password) {
                toast.warning('Password is required for new employees');
                loading.value = false;
                return;
            }
            const response = await axios.post('/api/users', payload);
            userId = response.data?.id;
            
            // Show success message
            if (payload.send_contract) {
                toast.success('Employee created successfully! Contract will be sent via email.');
            } else {
                toast.success('Employee created successfully!');
            }
        }

        // Upload any queued documents after user exists
        if (userId && queuedDocuments.value.length) {
            for (const doc of queuedDocuments.value) {
                const fd = new FormData();
                fd.append('name', doc.name);
                fd.append('file', doc.file);
                try {
                    await axios.post(`/api/hr/employees/${userId}/documents`, fd, {
                        headers: { 'Content-Type': 'multipart/form-data' },
                    });
                } catch (e) {
                    console.error('Failed to upload document', e);
                }
            }
        }

        queuedDocuments.value = [];
        newDocName.value = '';
        if (fileInput.value) fileInput.value.value = '';

        emit('saved');
        emit('close');
    } catch (error) {
        console.error('Failed to save employee:', error);
        let errorMessage = 'Failed to save employee. Please check the form and try again.';
        
        if (error.response?.data?.message) {
            errorMessage = error.response.data.message;
        } else if (error.response?.data?.errors) {
            const errors = Object.values(error.response.data.errors).flat();
            errorMessage = errors.join(', ');
        }
        
        toast.error(errorMessage);
    } finally {
        loading.value = false;
    }
};

onMounted(loadRoles);

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

