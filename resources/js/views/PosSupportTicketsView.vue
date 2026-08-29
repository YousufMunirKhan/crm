<template>
    <ListingPageShell
        title="POS support"
        subtitle="Technical issues reported from desktop POS (ingested via API)."
        :badge="posSupportBadge"
    >
        <template #filters>
            <div>
                <label class="listing-label" for="possupportticketsview-status">Status</label>
                <select id="possupportticketsview-status" v-model="statusFilter" class="listing-input w-full sm:w-56" @change="loadItems(1)">
                    <option value="">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="solved">Solved</option>
                    <option value="not_an_issue">Not an Issue</option>
                </select>
            </div>
        </template>

        <div v-if="loading" class="px-5 py-14 text-center text-slate-500 text-sm" role="status" aria-live="polite">Loading…</div>
        <template v-else>
            <div class="overflow-x-auto min-w-0">
                <table class="w-full min-w-[900px]">
                    <thead class="listing-thead">
                        <tr>
                            <th class="listing-th">Shop</th>
                            <th class="listing-th">Message</th>
                            <th class="listing-th">Telephone</th>
                            <th class="listing-th">Status</th>
                            <th class="listing-th">Created</th>
                            <th class="listing-th text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in items" :key="row.id" class="listing-row">
                            <td class="listing-td-strong">{{ row.pos_shop_name || '—' }}</td>
                            <td class="listing-td max-w-xs truncate" :title="messagePreview(row)">
                                {{ messagePreview(row) }}
                            </td>
                            <td class="listing-td">{{ row.pos_telephone || '—' }}</td>
                            <td class="listing-td">
                                <BaseBadge :tone="statusTone(row.pos_support_status)">
                                    {{ statusLabel(row.pos_support_status) }}
                                </BaseBadge>
                            </td>
                            <td class="listing-td whitespace-nowrap">{{ formatDt(row.pos_submitted_at || row.created_at) }}</td>
                            <td class="listing-td text-right">
                                <div class="flex justify-end gap-2">
                                    <BaseButton size="sm" variant="outline" :to="`/tickets/${row.id}`">
                                        <template #icon><EyeIcon class="icon-sm" aria-hidden="true" /></template>
                                        View
                                    </BaseButton>
                                    <BaseButton size="sm" variant="ghost" @click="openStatusModal(row)">
                                        <template #icon><PencilSquareIcon class="icon-sm" aria-hidden="true" /></template>
                                        Update status
                                    </BaseButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="items.length === 0" class="text-center py-8 text-slate-500 text-sm px-5">No POS support tickets.</p>
        </template>

        <template #pagination>
            <Pagination
                :pagination="pagination"
                embedded
                result-label="tickets"
                singular-label="ticket"
                @page-change="loadItems"
            />
        </template>
    </ListingPageShell>

    <BaseModal
        v-model="showModal"
        title="Update status"
        :description="modalDescription"
        size="md"
        :close-on-backdrop="false"
        @close="closeModal"
    >
        <form id="pos-support-status-form" class="space-y-4" novalidate @submit.prevent="saveStatus">
            <div>
                <label class="form-label" for="possupportticketsview-status-2">Status</label>
                <select id="possupportticketsview-status-2" v-model="formStatus" class="form-select">
                    <option value="pending">Pending</option>
                    <option value="solved">Solved</option>
                    <option value="not_an_issue">Not an Issue</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="possupportticketsview-resolution-notes">
                    Resolution / reason notes
                    <span v-if="formStatus !== 'pending'" class="form-required">*</span>
                </label>
                <textarea
                    id="possupportticketsview-resolution-notes"
                    v-model="formNotes"
                    rows="4"
                    class="form-textarea"
                    :placeholder="formStatus === 'solved' ? 'Describe how the issue was resolved…' : formStatus === 'not_an_issue' ? 'Explain why this was not an issue…' : 'Optional while Pending'"
                />
                <p v-if="formError" class="form-error" role="alert">{{ formError }}</p>
            </div>
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="closeModal">Cancel</BaseButton>
            <BaseButton
                variant="primary"
                type="submit"
                form="pos-support-status-form"
                block-mobile
                :loading="saving"
            >{{ saving ? 'Saving…' : 'Save' }}</BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import axios from 'axios';
import { EyeIcon, PencilSquareIcon } from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import { BaseBadge, BaseButton, BaseModal } from '@/components/base';
import ListingPageShell from '@/components/ListingPageShell.vue';
import Pagination from '@/components/Pagination.vue';

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

const posSupportBadge = computed(() => {
    if (loading.value) return null;
    const t = pagination.value.total;
    if (!t) return null;
    return `${t} ${t === 1 ? 'ticket' : 'tickets'}`;
});

const showModal = ref(false);
const editing = ref(null);
const formStatus = ref('pending');
const formNotes = ref('');
const formError = ref(null);
const saving = ref(false);

const modalDescription = computed(() => {
    if (!editing.value) return '';
    return `${editing.value.pos_shop_name ?? ''} — POS ID ${editing.value.pos_external_id ?? ''}`;
});

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

function statusTone(s) {
    if (s === 'solved') return 'success';
    if (s === 'not_an_issue') return 'neutral';
    return 'warning';
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
