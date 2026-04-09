<template>
    <div class="min-h-screen bg-slate-50 w-full min-w-0 overflow-x-hidden">
        <div class="max-w-3xl mx-auto px-3 sm:px-6 py-6 lg:py-8 w-full min-w-0">
            <div v-if="loadError" class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800 text-sm mb-6">
                {{ loadError }}
                <router-link to="/tickets" class="block mt-2 font-medium text-red-900 underline">Back to tickets</router-link>
            </div>

            <template v-else>
                <div class="mb-6">
                    <router-link
                        :to="`/tickets/${ticketId}`"
                        class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 text-sm mb-4"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to ticket
                    </router-link>
                    <h1 class="text-2xl font-bold text-slate-900">Edit ticket</h1>
                    <p v-if="ticketNumber" class="text-slate-600 mt-1 text-sm font-mono">{{ ticketNumber }}</p>
                    <p class="text-slate-500 mt-1 text-sm">Changes save to the same record. Add comments from the ticket detail page.</p>
                </div>

                <div v-if="pageLoading" class="text-center py-12 text-slate-500 text-sm">Loading…</div>

                <form v-else class="form-card !overflow-visible" @submit.prevent="handleSubmit">
                    <div class="form-section-head-mint">
                        <h2 class="form-section-title-mint text-xl">Update ticket</h2>
                        <p class="form-section-desc-mint">Assignees and creator receive email when someone posts a comment on the ticket detail page.</p>
                    </div>

                    <div class="form-body space-y-4">
                        <div>
                            <label class="form-label">Customer</label>
                            <select v-model="form.customer_id" class="form-input">
                                <option value="">No customer</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">
                                    {{ c.name }} — {{ c.phone }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Subject *</label>
                            <input v-model="form.subject" type="text" required class="form-input" />
                        </div>

                        <div>
                            <label class="form-label">Description</label>
                            <textarea
                                ref="descriptionTextareaRef"
                                v-model="form.description"
                                rows="8"
                                class="form-textarea-ticket-description"
                            />
                            <p class="text-xs text-slate-500 mt-1">Drag the corner to resize. The field expands as you type or paste.</p>
                        </div>

                        <div>
                            <label class="form-label">Reference link</label>
                            <input v-model="form.reference_url" type="url" class="form-input" placeholder="https://..." />
                        </div>

                        <div>
                            <label class="form-label">Attachments</label>
                            <p v-if="existingAttachments.length" class="text-xs text-slate-600 mb-2">
                                <span class="font-medium">Current:</span>
                                <span v-for="(att, i) in existingAttachments" :key="att.id" class="inline-flex items-center gap-1 ml-1">
                                    <a :href="att.url" target="_blank" rel="noopener" class="text-violet-600 hover:underline">{{ att.original_name }}</a>
                                    <button type="button" class="text-red-600 text-xs" @click="removeAttachment(att)">Remove</button>
                                    <span v-if="i < existingAttachments.length - 1">·</span>
                                </span>
                            </p>
                            <input
                                ref="attachmentInputRef"
                                type="file"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100"
                                @change="onFiles"
                            />
                            <ul v-if="pendingFiles.length" class="mt-2 text-xs text-slate-600 space-y-1">
                                <li v-for="(f, i) in pendingFiles" :key="i" class="flex justify-between gap-2">
                                    <span class="truncate">{{ f.name }}</span>
                                    <button type="button" class="text-red-600 shrink-0" @click="pendingFiles.splice(i, 1)">Remove</button>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <label class="form-label">Expected resolution (hours)</label>
                            <input
                                v-model.number="form.estimated_resolve_hours"
                                type="number"
                                min="1"
                                max="8760"
                                class="form-input"
                                placeholder="Empty = use priority SLA"
                            />
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Priority</label>
                                <select v-model="form.priority" class="form-input">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">Status</label>
                                <select v-model="form.status" class="form-input">
                                    <option value="open">Open</option>
                                    <option value="in_progress">Working</option>
                                    <option value="on_hold">On hold</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <span class="form-label">Assign to (one or more)</span>
                            <div class="rounded-xl border border-slate-200 bg-white p-3 max-h-48 overflow-y-auto space-y-2">
                                <label
                                    v-for="u in users"
                                    :key="u.id"
                                    class="flex items-center gap-2 text-sm text-slate-800 cursor-pointer"
                                >
                                    <input v-model="form.assigned_user_ids" type="checkbox" :value="Number(u.id)" class="form-checkbox" />
                                    {{ u.name }}
                                </label>
                                <p v-if="!users.length" class="text-sm text-slate-400">No users loaded.</p>
                            </div>
                            <div
                                v-if="editCommentRecipientRows.length > 0"
                                class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-800"
                            >
                                <div class="font-semibold text-slate-900 mb-1">After save, comment emails will go to:</div>
                                <ul class="space-y-1 list-none">
                                    <li v-for="row in editCommentRecipientRows" :key="row.email" class="break-all">
                                        <span class="font-medium">{{ row.name }}</span>
                                        <span class="text-slate-600"> — {{ row.email }}</span>
                                    </li>
                                </ul>
                                <p class="mt-2 text-slate-600">The person who posts a comment does not receive email for their own comment.</p>
                            </div>
                            <div
                                v-else-if="(form.assigned_user_ids || []).length > 0 || ticketCreator"
                                class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950"
                            >
                                No valid recipient emails found for the current assignees or creator. Check user profiles or Settings → admin notification email.
                            </div>
                        </div>

                        <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded-xl border border-red-100">
                            {{ error }}
                        </div>
                    </div>

                    <div class="form-actions">
                        <router-link :to="`/tickets/${ticketId}`" class="form-btn-cancel text-center">Cancel</router-link>
                        <button type="submit" :disabled="saving" class="form-btn-submit">
                            {{ saving ? 'Saving…' : 'Save changes' }}
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAutosizeTextarea } from '@/composables/useAutosizeTextarea';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const ticketId = computed(() => route.params.id);
const ticketNumber = ref('');
const pageLoading = ref(true);
const loadError = ref(null);
const saving = ref(false);
const error = ref(null);
const customers = ref([]);
const users = ref([]);
const existingAttachments = ref([]);
const pendingFiles = ref([]);
const attachmentInputRef = ref(null);
/** Ticket creator (for comment recipient preview). */
const ticketCreator = ref(null);

const form = ref({
    customer_id: '',
    subject: '',
    description: '',
    reference_url: '',
    priority: 'medium',
    status: 'open',
    estimated_resolve_hours: null,
    assigned_user_ids: [],
});

const { textareaRef: descriptionTextareaRef } = useAutosizeTextarea(() => form.value.description);

const editCommentRecipientRows = computed(() => {
    const seen = new Set();
    const rows = [];
    const pushUser = (u) => {
        if (!u || !u.email || typeof u.email !== 'string') return;
        const email = u.email.trim();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return;
        const key = email.toLowerCase();
        if (seen.has(key)) return;
        seen.add(key);
        rows.push({ name: u.name || email, email });
    };
    (form.value.assigned_user_ids || []).forEach((id) => {
        const u = users.value.find((x) => Number(x.id) === Number(id));
        pushUser(u);
    });
    pushUser(ticketCreator.value);
    return rows;
});

onMounted(async () => {
    pageLoading.value = true;
    loadError.value = null;
    try {
        const [custRes, usersRes, ticketRes] = await Promise.all([
            axios.get('/api/customers', { params: { per_page: 100 } }),
            axios.get('/api/users'),
            axios.get(`/api/tickets/${ticketId.value}`),
        ]);
        customers.value = custRes.data.data || [];
        users.value = Array.isArray(usersRes.data) ? usersRes.data : usersRes.data?.data || [];

        const t = ticketRes.data;
        ticketNumber.value = t.ticket_number || '';
        ticketCreator.value = t.creator || null;
        existingAttachments.value = Array.isArray(t.attachments) ? [...t.attachments] : [];
        const assigneeIds = (t.assignees || []).map((a) => Number(a.id));
        form.value = {
            customer_id: t.customer_id || '',
            subject: t.subject || '',
            description: t.description || '',
            reference_url: t.reference_url || '',
            priority: t.priority || 'medium',
            status: t.status || 'open',
            estimated_resolve_hours: t.estimated_resolve_hours ?? null,
            assigned_user_ids: assigneeIds.length ? assigneeIds : (t.assigned_to ? [Number(t.assigned_to)] : []),
        };
    } catch (err) {
        if (err.response?.status === 403) {
            loadError.value = 'You do not have access to this ticket.';
        } else if (err.response?.status === 404) {
            loadError.value = 'Ticket not found.';
        } else {
            loadError.value = err.response?.data?.message || 'Failed to load ticket.';
        }
    } finally {
        pageLoading.value = false;
    }
});

function onFiles(e) {
    const picked = Array.from(e.target.files || []);
    if (picked.length) pendingFiles.value = [...pendingFiles.value, ...picked];
    e.target.value = '';
}

async function removeAttachment(att) {
    if (!window.confirm('Remove this file?')) return;
    try {
        await axios.delete(`/api/tickets/${ticketId.value}/attachments/${att.id}`);
        existingAttachments.value = existingAttachments.value.filter((a) => a.id !== att.id);
        toast.success('Attachment removed');
    } catch (err) {
        toast.error(err.response?.data?.message || 'Could not remove file');
    }
}

async function handleSubmit() {
    saving.value = true;
    error.value = null;
    try {
        const payload = {
            subject: form.value.subject,
            description: form.value.description || null,
            reference_url: form.value.reference_url || null,
            priority: form.value.priority,
            status: form.value.status,
            customer_id: form.value.customer_id || null,
            assigned_user_ids: form.value.assigned_user_ids || [],
            estimated_resolve_hours:
                form.value.estimated_resolve_hours === '' || form.value.estimated_resolve_hours === undefined
                    ? null
                    : form.value.estimated_resolve_hours,
        };
        await axios.put(`/api/tickets/${ticketId.value}`, payload);

        if (pendingFiles.value.length > 0) {
            const fd = new FormData();
            pendingFiles.value.forEach((f) => fd.append('attachments[]', f));
            const { data } = await axios.post(`/api/tickets/${ticketId.value}/attachments`, fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            existingAttachments.value = data.attachments || existingAttachments.value;
            pendingFiles.value = [];
            if (attachmentInputRef.value) attachmentInputRef.value.value = '';
        }

        toast.success('Ticket updated');
        router.push(`/tickets/${ticketId.value}`);
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to save ticket';
    } finally {
        saving.value = false;
    }
}
</script>
