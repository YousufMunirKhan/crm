<template>
    <div class="w-full min-w-0 max-w-7xl mx-auto p-3 sm:p-4 md:p-6 space-y-4 sm:space-y-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 min-w-0">
            <div class="min-w-0">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900">POS support</h1>
                <p class="text-sm text-slate-600 mt-1">
                    Technical issues reported from desktop POS (ingested via API).
                </p>
            </div>
            <select
                v-model="statusFilter"
                class="w-full sm:w-auto min-w-0 px-3 py-2 border border-slate-300 rounded-lg text-sm"
                @change="loadItems(1)"
            >
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="solved">Solved</option>
                <option value="not_an_issue">Not an Issue</option>
            </select>
        </div>

        <div class="bg-white rounded-xl shadow-sm overflow-x-auto min-w-0">
            <table class="w-full min-w-[900px]">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Shop</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Message</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Telephone</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-700 uppercase">Created</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-700 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <tr v-for="row in items" :key="row.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ row.pos_shop_name || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700 max-w-xs truncate" :title="messagePreview(row)">
                            {{ messagePreview(row) }}
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ row.pos_telephone || '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs font-medium" :class="statusClass(row.pos_support_status)">
                                {{ statusLabel(row.pos_support_status) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap">{{ formatDt(row.pos_submitted_at || row.created_at) }}</td>
                        <td class="px-4 py-3 text-right text-sm space-x-2">
                            <router-link
                                :to="`/tickets/${row.id}`"
                                class="text-blue-600 hover:underline"
                            >View</router-link>
                            <button type="button" class="text-slate-900 hover:underline" @click="openStatusModal(row)">Update status</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-if="!loading && items.length === 0" class="text-center py-8 text-slate-500 text-sm">No POS support tickets.</p>
        </div>

        <div v-if="pagination.last_page > 1" class="flex justify-between items-center text-sm text-slate-600">
            <span>Page {{ pagination.current_page }} / {{ pagination.last_page }}</span>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="px-3 py-1 border rounded-lg disabled:opacity-40"
                    :disabled="pagination.current_page <= 1"
                    @click="loadItems(pagination.current_page - 1)"
                >Previous</button>
                <button
                    type="button"
                    class="px-3 py-1 border rounded-lg disabled:opacity-40 touch-manipulation"
                    :disabled="pagination.current_page >= pagination.last_page"
                    @click="loadItems(pagination.current_page + 1)"
                >Next</button>
            </div>
        </div>

        <div
            v-if="showModal"
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="closeModal"
        >
            <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-6 space-y-4">
                <h2 class="text-lg font-semibold text-slate-900">Update status</h2>
                <p class="text-sm text-slate-600">{{ editing?.pos_shop_name }} — POS ID {{ editing?.pos_external_id }}</p>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                    <select v-model="formStatus" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="pending">Pending</option>
                        <option value="solved">Solved</option>
                        <option value="not_an_issue">Not an Issue</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Resolution / reason notes
                        <span v-if="formStatus !== 'pending'" class="text-red-600">*</span>
                    </label>
                    <textarea
                        v-model="formNotes"
                        rows="4"
                        class="w-full border rounded-lg px-3 py-2 text-sm"
                        :placeholder="formStatus === 'solved' ? 'Describe how the issue was resolved…' : formStatus === 'not_an_issue' ? 'Explain why this was not an issue…' : 'Optional while Pending'"
                    />
                    <p v-if="formError" class="text-sm text-red-600 mt-1">{{ formError }}</p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="px-4 py-2 text-sm border rounded-lg" @click="closeModal">Cancel</button>
                    <button
                        type="button"
                        class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg disabled:opacity-50"
                        :disabled="saving"
                        @click="saveStatus"
                    >{{ saving ? 'Saving…' : 'Save' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const items = ref([]);
const loading = ref(true);
const statusFilter = ref('');
const pagination = ref({
    current_page: 1,
    last_page: 1,
    per_page: 20,
    total: 0,
});

const showModal = ref(false);
const editing = ref(null);
const formStatus = ref('pending');
const formNotes = ref('');
const formError = ref(null);
const saving = ref(false);

function messagePreview(row) {
    const m = row.description?.split('\n').find((l) => l.startsWith('Message: '));
    if (m) return m.replace(/^Message:\s*/, '').slice(0, 140) || '—';
    return (row.subject || '').slice(0, 140) || '—';
}

function statusLabel(s) {
    if (s === 'solved') return 'Solved';
    if (s === 'not_an_issue') return 'Not an Issue';
    return 'Pending';
}

function statusClass(s) {
    if (s === 'solved') return 'bg-green-100 text-green-800';
    if (s === 'not_an_issue') return 'bg-slate-200 text-slate-800';
    return 'bg-amber-100 text-amber-900';
}

function formatDt(v) {
    if (!v) return '—';
    return new Date(v).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' });
}

async function loadItems(page = 1) {
    loading.value = true;
    try {
        const params = { per_page: 20, page };
        if (statusFilter.value) params.pos_support_status = statusFilter.value;
        const { data } = await axios.get('/api/pos-support-tickets', { params });
        items.value = data.data || [];
        pagination.value = {
            current_page: data.current_page || 1,
            last_page: data.last_page || 1,
            per_page: data.per_page || 20,
            total: data.total ?? items.value.length,
        };
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to load');
        items.value = [];
    } finally {
        loading.value = false;
    }
}

function openStatusModal(row) {
    editing.value = row;
    formStatus.value = row.pos_support_status || 'pending';
    formNotes.value = row.pos_resolution_notes || '';
    formError.value = null;
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    editing.value = null;
}

async function saveStatus() {
    formError.value = null;
    if (!editing.value) return;
    if (formStatus.value !== 'pending' && !formNotes.value.trim()) {
        formError.value = 'Notes are required for Solved and Not an Issue.';
        return;
    }
    saving.value = true;
    try {
        await axios.patch(`/api/pos-support-tickets/${editing.value.id}/status`, {
            pos_support_status: formStatus.value,
            pos_resolution_notes: formNotes.value.trim() || null,
        });
        toast.success('Status updated');
        closeModal();
        await loadItems(pagination.value.current_page);
    } catch (e) {
        const msg = e.response?.data?.message || e.response?.data?.errors?.pos_resolution_notes?.[0];
        formError.value = msg || 'Save failed';
    } finally {
        saving.value = false;
    }
}

onMounted(() => loadItems());
</script>
