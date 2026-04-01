<template>
    <ListingPageShell
        title="Today's activity"
        subtitle="Log what you've done today — your entries appear in the admin daily report."
        :badge="activityBadge"
    >
        <template #filters>
            <div class="listing-filters-row">
                <div>
                    <label class="listing-label">Filter by date</label>
                    <input
                        v-model="filterDate"
                        type="date"
                        class="listing-input w-full sm:w-44"
                        @change="loadActivities(1)"
                    />
                </div>
                <button
                    v-if="filterDate !== todayStr"
                    type="button"
                    class="listing-btn-outline"
                    @click="resetDateFilter"
                >
                    Today
                </button>
            </div>
        </template>

        <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-4 sm:p-5 mx-3 mt-3 mb-4 sm:mx-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-3">Add activity</h2>
            <form @submit.prevent="submitActivity" class="space-y-4">
                <div>
                    <label class="listing-label">Date</label>
                    <input v-model="form.activity_date" type="date" required class="listing-input max-w-xs" />
                </div>
                <div>
                    <label class="listing-label">What did you do? *</label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        required
                        placeholder="E.g. Called 5 customers, sent 2 quotations, followed up with ABC Ltd…"
                        class="listing-input resize-none"
                    />
                </div>
                <button type="submit" :disabled="saving" class="listing-btn-accent disabled:opacity-50 touch-manipulation">
                    {{ saving ? 'Saving…' : 'Add activity' }}
                </button>
            </form>
        </div>

        <div v-if="loading" class="px-5 py-14 text-center text-slate-500 text-sm">Loading…</div>
        <div v-else-if="activities.length === 0" class="px-5 py-12 text-center text-slate-500 text-sm">
            No activities yet. Add one above to get started.
        </div>
        <div v-else class="divide-y divide-slate-100 border-t border-slate-100">
            <div
                v-for="activity in activities"
                :key="activity.id"
                class="px-4 sm:px-6 py-4 hover:bg-slate-50/50 transition-colors group"
            >
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-slate-900 whitespace-pre-wrap text-sm">{{ activity.description }}</p>
                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-500">
                            <span>{{ formatDate(activity.activity_date) }}</span>
                            <span>•</span>
                            <span>{{ formatTime(activity.created_at) }}</span>
                        </div>
                    </div>
                    <div class="flex gap-2 sm:flex-shrink-0">
                        <button
                            type="button"
                            class="p-2 text-slate-600 hover:text-blue-600 hover:bg-blue-50 rounded-lg"
                            title="Edit"
                            @click="editActivity(activity)"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            class="p-2 text-slate-600 hover:text-red-600 hover:bg-red-50 rounded-lg"
                            title="Delete"
                            @click="confirmDelete(activity)"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <template #pagination>
            <Pagination
                v-if="pagination"
                :pagination="pagination"
                embedded
                result-label="activities"
                singular-label="activity"
                @page-change="loadActivities"
            />
        </template>
    </ListingPageShell>

    <div v-if="editing" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="editing = null">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6">
            <h3 class="text-lg font-semibold text-slate-900 mb-4">Edit activity</h3>
            <textarea
                v-model="editForm.description"
                rows="4"
                class="listing-input mb-4"
                placeholder="What did you do?"
            />
            <div class="flex justify-end gap-2">
                <button type="button" class="listing-btn-outline" @click="editing = null">Cancel</button>
                <button type="button" :disabled="saving" class="listing-btn-primary disabled:opacity-50" @click="saveEdit">
                    Save
                </button>
            </div>
        </div>
    </div>

    <div v-if="deleting" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4" @click.self="deleting = null">
        <div class="bg-white rounded-xl shadow-xl max-w-sm w-full p-6">
            <h3 class="text-lg font-semibold text-slate-900 mb-2">Delete activity?</h3>
            <p class="text-sm text-slate-600 mb-4">This cannot be undone.</p>
            <div class="flex justify-end gap-2">
                <button type="button" class="listing-btn-outline" @click="deleting = null">Cancel</button>
                <button
                    type="button"
                    :disabled="saving"
                    class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-medium hover:bg-red-700 disabled:opacity-50"
                    @click="doDelete"
                >
                    Delete
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';

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

const activityBadge = computed(() => {
    if (loading.value || !pagination.value?.total) return null;
    const t = pagination.value.total;
    return `${t} ${t === 1 ? 'entry' : 'entries'}`;
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
