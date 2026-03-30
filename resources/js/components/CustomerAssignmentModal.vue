<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 overflow-y-auto p-4">
        <div class="bg-white rounded-xl shadow-xl p-6 max-w-2xl w-full mx-4 my-8">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-semibold text-slate-900">
                    Assign Customer: {{ customer?.name }}
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

            <div v-if="loading" class="text-center py-8 text-slate-500">
                Loading...
            </div>

            <form v-else @submit.prevent="handleSubmit" class="space-y-4">
                <!-- Current Assignments -->
                <div v-if="currentAssignments.length > 0" class="mb-6">
                    <h4 class="text-sm font-medium text-slate-700 mb-3">Currently Assigned To:</h4>
                    <div class="space-y-2">
                        <div
                            v-for="assignment in currentAssignments"
                            :key="assignment.user_id"
                            class="flex items-center justify-between p-3 bg-slate-50 rounded-lg"
                        >
                            <div class="flex-1">
                                <div class="font-medium text-slate-900">{{ assignment.user.name }}</div>
                                <div class="text-xs text-slate-500">
                                    Assigned by {{ assignment.assigned_by_user?.name || 'Unknown' }} on
                                    {{ formatDate(assignment.assigned_at) }}
                                </div>
                                <div v-if="assignment.notes" class="text-xs text-slate-600 mt-1">
                                    {{ assignment.notes }}
                                </div>
                            </div>
                            <button
                                type="button"
                                @click="handleUnassign(assignment.user_id)"
                                class="px-3 py-1 bg-red-600 text-white text-sm rounded-lg hover:bg-red-700"
                            >
                                Remove
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Assign to Employees -->
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Assign to Employee(s) *
                    </label>
                    <select
                        v-model="selectedUserIds"
                        multiple
                        required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 min-h-[120px]"
                    >
                        <option v-for="employee in availableEmployees" :key="employee.id" :value="employee.id">
                            {{ employee.name }} ({{ employee.role?.name }})
                        </option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Hold Ctrl/Cmd to select multiple employees</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Notes (Optional)
                    </label>
                    <textarea
                        v-model="form.notes"
                        rows="3"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="Reason for assignment..."
                    />
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
                        :disabled="loading || selectedUserIds.length === 0"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50"
                    >
                        {{ loading ? 'Assigning...' : 'Assign' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

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

const handleUnassign = async (userId) => {
    if (!confirm('Are you sure you want to remove this assignment?')) return;

    try {
        await axios.delete(`/api/customers/${props.customer.id}/assign/${userId}`);
        await loadCurrentAssignments();
        emit('assigned');
    } catch (error) {
        console.error('Failed to unassign customer:', error);
        toast.error('Failed to remove assignment. Please try again.');
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

