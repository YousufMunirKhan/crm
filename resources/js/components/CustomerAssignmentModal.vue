<template>
    <BaseModal
        :model-value="true"
        :title="`Assign Customer: ${customer?.name ?? ''}`"
        size="md"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <div v-if="loading" class="py-8 text-center text-sm text-slate-500" role="status" aria-live="polite">
            Loading...
        </div>

        <form v-else id="customer-assignment-form" class="space-y-4" @submit.prevent="handleSubmit">
            <!-- Current Assignments -->
            <div v-if="currentAssignments.length > 0" class="mb-6">
                <h3 class="mb-3 text-sm font-medium text-slate-700">Currently Assigned To:</h3>
                <ul class="space-y-2">
                    <li
                        v-for="assignment in currentAssignments"
                        :key="assignment.user_id"
                        class="flex items-center justify-between gap-3 rounded-control bg-slate-50 p-3"
                    >
                        <div class="min-w-0 flex-1">
                            <div class="font-medium text-slate-900">{{ assignment.user.name }}</div>
                            <div class="text-xs text-slate-500">
                                Assigned by {{ assignment.assigned_by_user?.name || 'Unknown' }} on
                                {{ formatDate(assignment.assigned_at) }}
                            </div>
                            <div v-if="assignment.notes" class="mt-1 text-xs text-slate-600">
                                {{ assignment.notes }}
                            </div>
                        </div>
                        <BaseButton
                            variant="danger"
                            :label="`Remove ${assignment.user.name}`"
                            @click="askUnassign(assignment)"
                        >
                            <template #icon>
                                <TrashIcon class="icon-sm" aria-hidden="true" />
                            </template>
                            Remove
                        </BaseButton>
                    </li>
                </ul>
            </div>

            <!-- Assign to Employees -->
            <div>
                <label class="form-label" for="customerassignmentmodal-assign-employees">
                    Assign to Employee(s) <span class="form-required" aria-hidden="true">*</span>
                </label>
                <select
                    id="customerassignmentmodal-assign-employees"
                    v-model="selectedUserIds"
                    multiple
                    required
                    aria-describedby="customerassignmentmodal-assign-employees-hint"
                    class="form-select min-h-[120px] bg-none pr-3"
                >
                    <option v-for="employee in availableEmployees" :key="employee.id" :value="employee.id">
                        {{ employee.name }} ({{ employee.role?.name }})
                    </option>
                </select>
                <p id="customerassignmentmodal-assign-employees-hint" class="form-hint">
                    Hold Ctrl/Cmd to select multiple employees
                </p>
            </div>

            <BaseInput
                v-model="form.notes"
                label="Notes (Optional)"
                type="textarea"
                rows="3"
                placeholder="Reason for assignment..."
            />
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="$emit('close')">Cancel</BaseButton>
            <BaseButton
                variant="primary"
                type="submit"
                form="customer-assignment-form"
                block-mobile
                :disabled="selectedUserIds.length === 0"
                :loading="loading"
            >
                Assign
            </BaseButton>
        </template>
    </BaseModal>

    <ConfirmDialog
        v-model="confirmUnassignOpen"
        title="Remove assignment?"
        :message="`Are you sure you want to remove ${unassignTarget?.user?.name ?? 'this'} from this customer?`"
        confirm-label="Remove"
        :loading="unassigning"
        @confirm="handleUnassign"
        @cancel="unassignTarget = null"
    />
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { TrashIcon } from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import { BaseButton, BaseInput, BaseModal, ConfirmDialog } from '@/components/base';

const toast = useToastStore();

const props = defineProps({
    customer: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['close', 'assigned']);

const loading = ref(false);
const availableEmployees = ref([]);
const currentAssignments = ref([]);
const selectedUserIds = ref([]);
const form = ref({
    notes: '',
});
const confirmUnassignOpen = ref(false);
const unassignTarget = ref(null);
const unassigning = ref(false);

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

const loadEmployees = async () => {
    try {
        const response = await axios.get('/api/users');
        availableEmployees.value = response.data.filter(user =>
            user.role?.name === 'Sales' ||
            user.role?.name === 'CallAgent' ||
            user.role?.name === 'Manager' ||
            user.role?.name === 'Admin'
        );
    } catch (error) {
        console.error('Failed to load employees:', error);
    }
};

const loadCurrentAssignments = async () => {
    if (!props.customer?.id) return;

    try {
        const response = await axios.get(`/api/customers/${props.customer.id}`);
        const customerData = response.data.customer || response.data;

        if (customerData.assigned_users && customerData.assigned_users.length > 0) {
            // Load assigned_by user details
            const userIds = customerData.assigned_users
                .map(u => u.pivot?.assigned_by)
                .filter(Boolean)
                .filter((v, i, a) => a.indexOf(v) === i);

            const usersResponse = await Promise.all(
                userIds.map(id => axios.get(`/api/users/${id}`).catch(() => null))
            );

            const usersMap = {};
            usersResponse.forEach((resp, idx) => {
                if (resp?.data) {
                    usersMap[userIds[idx]] = resp.data;
                }
            });

            currentAssignments.value = customerData.assigned_users.map(assignment => ({
                user_id: assignment.id,
                user: assignment,
                assigned_by: assignment.pivot?.assigned_by,
                assigned_at: assignment.pivot?.assigned_at,
                notes: assignment.pivot?.notes,
                assigned_by_user: usersMap[assignment.pivot?.assigned_by] || null,
            }));
        } else {
            currentAssignments.value = [];
        }
    } catch (error) {
        console.error('Failed to load assignments:', error);
        // Try to get from customer prop directly
        if (props.customer.assigned_users) {
            currentAssignments.value = props.customer.assigned_users.map(assignment => ({
                user_id: assignment.id,
                user: assignment,
                assigned_by: assignment.pivot?.assigned_by,
                assigned_at: assignment.pivot?.assigned_at,
                notes: assignment.pivot?.notes,
                assigned_by_user: null,
            }));
        } else {
            currentAssignments.value = [];
        }
    }
};

const handleSubmit = async () => {
    if (selectedUserIds.value.length === 0) return;

    loading.value = true;
    try {
        await axios.post(`/api/customers/${props.customer.id}/assign`, {
            user_ids: selectedUserIds.value,
            notes: form.value.notes,
        });

        emit('assigned');
        emit('close');
    } catch (error) {
        console.error('Failed to assign customer:', error);
        toast.error('Failed to assign customer. Please try again.');
    } finally {
        loading.value = false;
    }
};

const askUnassign = (assignment) => {
    unassignTarget.value = assignment;
    confirmUnassignOpen.value = true;
};

const handleUnassign = async () => {
    const userId = unassignTarget.value?.user_id;
    if (!userId) return;

    unassigning.value = true;
    try {
        await axios.delete(`/api/customers/${props.customer.id}/assign/${userId}`);
        await loadCurrentAssignments();
        emit('assigned');
    } catch (error) {
        console.error('Failed to unassign customer:', error);
        toast.error('Failed to remove assignment. Please try again.');
    } finally {
        unassigning.value = false;
        confirmUnassignOpen.value = false;
        unassignTarget.value = null;
    }
};

watch(() => props.customer, (newCustomer) => {
    if (newCustomer) {
        // If customer already has assigned_users loaded, use them
        if (newCustomer.assigned_users && newCustomer.assigned_users.length > 0) {
            currentAssignments.value = newCustomer.assigned_users.map(assignment => ({
                user_id: assignment.id,
                user: assignment,
                assigned_by: assignment.pivot?.assigned_by,
                assigned_at: assignment.pivot?.assigned_at,
                notes: assignment.pivot?.notes,
                assigned_by_user: null,
            }));
        } else {
            loadCurrentAssignments();
        }
    }
}, { immediate: true, deep: true });

onMounted(() => {
    loadEmployees();
    if (props.customer) {
        if (props.customer.assigned_users && props.customer.assigned_users.length > 0) {
            currentAssignments.value = props.customer.assigned_users.map(assignment => ({
                user_id: assignment.id,
                user: assignment,
                assigned_by: assignment.pivot?.assigned_by,
                assigned_at: assignment.pivot?.assigned_at,
                notes: assignment.pivot?.notes,
                assigned_by_user: null,
            }));
        } else {
            loadCurrentAssignments();
        }
    }
});
</script>
