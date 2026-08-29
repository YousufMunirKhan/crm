<template>
    <ListingPageShell
        title="Today's activity"
        subtitle="Log what you've done today — your entries appear in the admin daily report."
        :badge="activityBadge"
    >
        <template #filters>
            <div class="listing-filters-row">
                <div>
                    <label class="listing-label" for="todaysactivityview-filter-by-date">Filter by date</label>
                    <input id="todaysactivityview-filter-by-date"
                        v-model="filterDate"
                        type="date"
                        class="form-input w-full sm:w-44"
                        @change="loadActivities(1)"
                    />
                </div>
                <BaseButton v-if="filterDate !== todayStr" variant="outline" @click="resetDateFilter">
                    Today
                </BaseButton>
            </div>
        </template>

        <div class="rounded-card border border-slate-200 bg-slate-50/50 p-4 sm:p-5 mx-3 mt-3 mb-4 sm:mx-5">
            <h2 class="text-sm font-semibold text-slate-800 mb-3">Add activity</h2>
            <form @submit.prevent="submitActivity" class="space-y-4">
                <div>
                    <label class="listing-label" for="todaysactivityview-date">Date</label>
                    <input id="todaysactivityview-date" v-model="form.activity_date" type="date" required class="form-input max-w-xs" />
                </div>
                <div>
                    <label class="listing-label" for="todaysactivityview-what-did-you-do">What did you do? *</label>
                    <textarea id="todaysactivityview-what-did-you-do"
                        v-model="form.description"
                        rows="4"
                        required
                        placeholder="E.g. Called 5 customers, sent 2 quotations, followed up with ABC Ltd…"
                        class="form-textarea resize-none"
                    />
                </div>
                <BaseButton variant="primary" type="submit" :loading="saving">
                    <template #icon><PlusIcon class="icon-sm" aria-hidden="true" /></template>
                    Add activity
                </BaseButton>
            </form>
        </div>

        <div v-if="loading" class="px-4 sm:px-6 py-6 space-y-3" aria-busy="true">
            <span v-for="n in 4" :key="`sk-${n}`" class="skeleton-text block w-full" />
        </div>
        <EmptyState
            v-else-if="activities.length === 0"
            heading="No activities yet"
            description="Add one above to get started."
        >
            <template #icon><BoltIcon class="icon" aria-hidden="true" /></template>
        </EmptyState>
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
                        <BaseButton
                            variant="ghost"
                            size="icon"
                            label="Edit activity"
                            title="Edit"
                            @click="editActivity(activity)"
                        >
                            <PencilSquareIcon class="icon" aria-hidden="true" />
                        </BaseButton>
                        <BaseButton
                            variant="ghost"
                            size="icon"
                            label="Delete activity"
                            title="Delete"
                            class="hover:text-danger-700 hover:bg-danger-50"
                            @click="confirmDelete(activity)"
                        >
                            <TrashIcon class="icon" aria-hidden="true" />
                        </BaseButton>
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

    <BaseModal
        :model-value="!!editing"
        title="Edit activity"
        size="sm"
        :close-on-backdrop="false"
        @update:model-value="editing = null"
    >
        <form id="edit-activity-form" novalidate @submit.prevent="saveEdit">
            <label class="form-label" for="todaysactivityview-edit-description">What did you do?</label>
            <textarea
                id="todaysactivityview-edit-description"
                v-model="editForm.description"
                rows="4"
                class="form-textarea"
                placeholder="What did you do?"
            />
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="editing = null">Cancel</BaseButton>
            <BaseButton
                variant="primary"
                type="submit"
                form="edit-activity-form"
                block-mobile
                :loading="saving"
            >
                Save
            </BaseButton>
        </template>
    </BaseModal>

    <ConfirmDialog
        :model-value="!!deleting"
        title="Delete activity?"
        message="This cannot be undone."
        confirm-label="Delete"
        :loading="saving"
        @cancel="deleting = null"
        @confirm="doDelete"
    />
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';
import { BoltIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import Pagination from '@/components/Pagination.vue';
import ListingPageShell from '@/components/ListingPageShell.vue';
import { BaseButton, BaseModal, ConfirmDialog, EmptyState } from '@/components/base';

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
