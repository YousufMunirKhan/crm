<template>
    <BaseModal
        :model-value="true"
        :title="employee ? 'Edit Employee' : 'Add New Employee'"
        size="lg"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <form id="employee-form" class="space-y-4" @submit.prevent="handleSubmit">
            <div class="form-grid-2">
                <div>
                    <label class="form-label" for="employeeform-name">Name <span class="form-required" aria-hidden="true">*</span></label>
                    <input id="employeeform-name"
                        v-model="form.name"
                        type="text"
                        required
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label" for="employeeform-email">Email <span class="form-required" aria-hidden="true">*</span></label>
                    <input id="employeeform-email"
                        v-model="form.email"
                        type="email"
                        required
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label" for="employeeform-password">Password {{ employee ? '(leave blank to keep current)' : '*' }}</label>
                    <input id="employeeform-password"
                        v-model="form.password"
                        type="password"
                        :required="!employee"
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label" for="employeeform-phone">Phone</label>
                    <input id="employeeform-phone"
                        v-model="form.phone"
                        type="text"
                        class="form-input"
                    />
                </div>

                <div>
                    <label class="form-label" for="employeeform-role">Role <span class="form-required" aria-hidden="true">*</span></label>
                    <select id="employeeform-role"
                        v-model="form.role_id"
                        required
                        class="form-select"
                    >
                        <option value="">Select Role</option>
                        <option v-for="role in roles" :key="role.id" :value="role.id">
                            {{ role.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="employeeform-employee-type">Employee Type</label>
                    <select id="employeeform-employee-type"
                        v-model="form.employee_type"
                        class="form-select"
                    >
                        <option value="">Select Type</option>
                        <option value="field_worker">Field Worker</option>
                        <option value="call_center">Call Center</option>
                        <option value="ticket_manager">Ticket Manager</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="employeeform-status">Status</label>
                    <select id="employeeform-status"
                        v-model="form.is_active"
                        class="form-select"
                    >
                        <option :value="true">Active</option>
                        <option :value="false">Inactive</option>
                    </select>
                </div>

                <div>
                    <label class="form-label" for="employeeform-hire-date">Hire Date</label>
                    <input id="employeeform-hire-date"
                        v-model="form.hire_date"
                        type="date"
                        class="form-input"
                    />
                </div>
            </div>

            <div>
                <label class="form-label" for="employeeform-address">Address</label>
                <textarea id="employeeform-address"
                    v-model="form.address"
                    rows="2"
                    class="form-textarea"
                />
            </div>

            <p v-if="canEditNavPerms" class="callout callout-info">
                Their role decides what they can see. If this person needs something their role
                does not cover, open their employee page after saving and add it there - one
                section at a time, with an end date if it is only for a while.
            </p>

            <!-- Attachments when creating/editing (optional uploads) -->
            <div class="border-t border-slate-200 pt-4 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="form-section-title">Attachments</h3>
                    <span class="text-xs text-slate-500">Optional – ID, contract, proof, etc.</span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-[2fr,2fr,auto] gap-3 items-end">
                    <div>
                        <label class="form-label" for="employeeform-attachment-name">Attachment name</label>
                        <input id="employeeform-attachment-name"
                            v-model="newDocName"
                            type="text"
                            placeholder="Attachment name"
                            class="form-input text-sm"
                        />
                    </div>
                    <div>
                        <span class="form-label">Files</span>
                        <label
                            class="inline-flex cursor-pointer items-center gap-2 rounded-control border border-dashed border-slate-300 px-3 py-2 text-xs text-slate-600 hover:bg-slate-50 focus-within:ring-2 focus-within:ring-primary-500/40"
                        >
                            <ArrowUpTrayIcon class="icon-sm text-slate-500" aria-hidden="true" />
                            <span>Browse files</span>
                            <input
                                ref="fileInput"
                                type="file"
                                multiple
                                class="sr-only"
                            />
                        </label>
                    </div>
                    <BaseButton variant="primary" size="sm" @click="queueDocument">
                        <template #icon>
                            <PlusIcon class="icon-sm" aria-hidden="true" />
                        </template>
                        Add
                    </BaseButton>
                </div>
                <ul v-if="queuedDocuments.length" class="space-y-1 text-xs text-slate-700">
                    <li v-for="(doc, index) in queuedDocuments" :key="index" class="flex items-center justify-between gap-3">
                        <span>{{ doc.name }} ({{ doc.file?.name }})</span>
                        <BaseButton
                            variant="ghost"
                            size="sm"
                            @click="removeQueuedDocument(index)"
                        >
                            <template #icon>
                                <TrashIcon class="icon-sm text-danger-700" aria-hidden="true" />
                            </template>
                            Remove
                        </BaseButton>
                    </li>
                </ul>
            </div>

            <div v-if="!employee" class="border-t border-slate-200 pt-4">
                <label class="form-choice" for="employeeform-send-contract">
                    <input id="employeeform-send-contract"
                        v-model="form.send_contract"
                        type="checkbox"
                        class="form-checkbox"
                    />
                    <span>Send employment contract via email</span>
                </label>
            </div>
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="$emit('close')">Cancel</BaseButton>
            <BaseButton
                variant="primary"
                type="submit"
                form="employee-form"
                block-mobile
                :loading="loading"
            >
                {{ loading ? 'Saving...' : (employee ? 'Update' : 'Create') }}
            </BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import axios from 'axios';
import { ArrowUpTrayIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { BaseButton, BaseModal } from '@/components/base';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';

const toast = useToastStore();
const auth = useAuthStore();

const canEditNavPerms = computed(() => {
    const r = auth.user?.role?.name;
    return r === 'Admin' || r === 'System Admin';
});


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

