<template>
    <div class="max-w-4xl mx-auto p-4 sm:p-6 space-y-6">
        <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Today's Activity</h1>
        <p class="text-sm text-slate-600">Log what you've done today. Your activities are visible to admins in the daily report.</p>

        <!-- Add Activity Form -->
        <div class="bg-white rounded-xl shadow-sm p-4 sm:p-6 border border-slate-200">
            <h2 class="text-lg font-semibold text-slate-900 mb-4">Add Activity</h2>
            <form @submit.prevent="submitActivity" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Date</label>
                    <input
                        v-model="form.activity_date"
                        type="date"
                        required
                        class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">What did you do? *</label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        required
                        placeholder="E.g. Called 5 customers, sent 2 quotations, followed up with ABC Ltd..."
                        class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-base focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="saving"
                    class="w-full sm:w-auto px-6 py-2.5 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50 font-medium"
                >
                    {{ saving ? 'Saving...' : 'Add Activity' }}
                </button>
            </form>
        </div>

        <!-- Activities List -->
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-slate-200">
            <div class="px-4 sm:px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900">My Activities</h2>
                <div class="flex items-center gap-2">
                    <input
                        v-model="filterDate"
                        type="date"
                        @change="loadActivities(1)"
                        class="px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <button
                        v-if="filterDate !== todayStr"
                        @click="resetDateFilter"
                        class="px-3 py-2 text-sm text-blue-600 hover:bg-blue-50 rounded-lg"
                    >
                        Today
                    </button>
                </div>
            </div>

            <div v-if="loading" class="p-8 text-center text-slate-500">Loading...</div>
            <div v-else-if="activities.length === 0" class="p-8 text-center text-slate-500">
                No activities yet. Add one above to get started.
            </div>
            <div v-else class="divide-y divide-slate-200">
                <div
                    v-for="activity in activities"
                    :key="activity.id"
                    class="p-4 sm:p-6 hover:bg-slate-50/50 transition-colors group"
                >
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-slate-900 whitespace-pre-wrap">{{ activity.description }}</p>
                            <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500">
                                <span>{{ formatDate(activity.activity_date) }}</span>
                                <span>•</span>
                                <span>{{ formatTime(activity.created_at) }}</span>
                            </div>
                        </div>
                        <div class="flex gap-2 sm:flex-shrink-0">
                            <button
                                @click="editActivity(activity)"
                                class="p-2 text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg"
                                title="Edit"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                            </button>
                            <button
                                @click="confirmDelete(activity)"
                                class="p-2 text-slate-600 hover:text-red-600 hover:bg-red-50 rounded-lg"
                                title="Delete"
                            >
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <Pagination
                v-if="pagination && pagination.last_page > 1"
                :pagination="pagination"
                @page-change="loadActivities"
            />
        </div>

        <!-- Edit Modal -->
        <div v-if="editing" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="editing = null">
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-4">Edit Activity</h3>
                <textarea
                    v-model="editForm.description"
                    rows="4"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 mb-4"
                    placeholder="What did you do?"
                />
                <div class="flex justify-end gap-2">
                    <button @click="editing = null" class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50">Cancel</button>
                    <button @click="saveEdit" :disabled="saving" class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50">Save</button>
                </div>
            </div>
        </div>

        <!-- Delete Confirm -->
        <div v-if="deleting" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="deleting = null">
            <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
                <h3 class="text-lg font-semibold text-slate-900 mb-2">Delete Activity?</h3>
                <p class="text-sm text-slate-600 mb-4">This cannot be undone.</p>
                <div class="flex justify-end gap-2">
                    <button @click="deleting = null" class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50">Cancel</button>
                    <button @click="doDelete" :disabled="saving" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50">Delete</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import Pagination from '@/components/Pagination.vue';

const toast = useToastStore();
const activities = ref([]);
const pagination = ref(null);
const loading = ref(false);
const saving = ref(false);
const filterDate = ref('');
const editing = ref(null);
const deleting = ref(null);
const editForm = ref({ description: '' });

const todayStr = computed(() => new Date().toISOString().slice(0, 10));

const form = ref({
    activity_date: new Date().toISOString().slice(0, 10),
    description: '',
});

const formatDate = (d) => {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
};

const formatTime = (d) => {
    if (!d) return '';
    return new Date(d).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
};

const loadActivities = async (page = 1) => {
    loading.value = true;
    try {
        const params = { page, per_page: 10 };
        if (filterDate.value) params.date = filterDate.value;
        const { data } = await axios.get('/api/daily-activities', { params });
        activities.value = data.data || [];
        pagination.value = {
            current_page: data.current_page,
            last_page: data.last_page,
            per_page: data.per_page,
            total: data.total,
        };
    } catch (e) {
        toast.error('Failed to load activities');
    } finally {
        loading.value = false;
    }
};

const submitActivity = async () => {
    saving.value = true;
    try {
        await axios.post('/api/daily-activities', form.value);
        toast.success('Activity added');
        form.value.description = '';
        form.value.activity_date = todayStr.value;
        loadActivities(pagination.value?.current_page || 1);
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to add activity');
    } finally {
        saving.value = false;
    }
};

const editActivity = (a) => {
    editing.value = a;
    editForm.value = { description: a.description };
};

const saveEdit = async () => {
    if (!editing.value) return;
    saving.value = true;
    try {
        await axios.put(`/api/daily-activities/${editing.value.id}`, { description: editForm.value.description });
        toast.success('Activity updated');
        editing.value = null;
        loadActivities(pagination.value?.current_page || 1);
    } catch (e) {
        toast.error('Failed to update');
    } finally {
        saving.value = false;
    }
};

const confirmDelete = (a) => {
    deleting.value = a;
};

const doDelete = async () => {
    if (!deleting.value) return;
    saving.value = true;
    try {
        await axios.delete(`/api/daily-activities/${deleting.value.id}`);
        toast.success('Activity deleted');
        deleting.value = null;
        loadActivities(pagination.value?.current_page || 1);
    } catch (e) {
        toast.error('Failed to delete');
    } finally {
        saving.value = false;
    }
};

const resetDateFilter = () => {
    filterDate.value = todayStr.value;
    loadActivities(1);
};

onMounted(() => {
    filterDate.value = todayStr.value;
    loadActivities();
});
</script>
