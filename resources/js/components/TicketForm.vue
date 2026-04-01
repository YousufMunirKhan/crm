<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-slate-200">
                <h2 class="text-xl font-semibold text-slate-900">
                    {{ ticket ? 'Edit Ticket' : 'Create New Ticket' }}
                </h2>
            </div>

            <form @submit.prevent="handleSubmit" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Customer</label>
                    <select
                        v-model="form.customer_id"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    >
                        <option value="">Select Customer (Optional)</option>
                        <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                            {{ customer.name }} - {{ customer.phone }}
                        </option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Or enter customer phone below</p>
                </div>

                <div v-if="!form.customer_id">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Customer Phone</label>
                    <input
                        v-model="form.customer_phone"
                        type="text"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Subject *</label>
                    <input
                        v-model="form.subject"
                        type="text"
                        required
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                    />
                </div>

                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                    <p class="font-medium text-amber-900">Files &amp; long documents (internal)</p>
                    <p class="mt-1 text-amber-900/90">
                        For larger files or collaborative docs, it’s <strong>preferred</strong> that you upload to
                        <strong>Google Drive</strong> or <strong>Google Sheets</strong>, then paste the shared link in
                        <strong>Reference link</strong> below. You can still attach files here if needed.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reference link (Google Drive / Sheet URL)</label>
                    <input
                        v-model="form.reference_url"
                        type="url"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        placeholder="https://drive.google.com/... or https://docs.google.com/..."
                    />
                    <p class="text-xs text-slate-500 mt-1">This URL is stored on the ticket and included in notification emails.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Attachments</label>
                    <input
                        ref="attachmentInputRef"
                        type="file"
                        multiple
                        accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"
                        class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200"
                        @change="onAttachmentFilesSelected"
                    />
                    <ul v-if="pendingAttachmentFiles.length" class="mt-2 text-xs text-slate-600 space-y-1">
                        <li v-for="(f, i) in pendingAttachmentFiles" :key="i" class="flex justify-between gap-2">
                            <span class="truncate">{{ f.name }}</span>
                            <button type="button" class="text-red-600 shrink-0" @click="removePendingFile(i)">Remove</button>
                        </li>
                    </ul>
                    <div v-if="ticket && ticketAttachments.length" class="mt-3 pt-3 border-t border-slate-200">
                        <p class="text-xs font-medium text-slate-700 mb-2">Current files</p>
                        <ul class="space-y-2">
                            <li
                                v-for="att in ticketAttachments"
                                :key="att.id"
                                class="flex items-center justify-between gap-2 text-sm"
                            >
                                <a :href="att.url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline truncate">{{ att.original_name }}</a>
                                <button type="button" class="text-xs text-red-600 shrink-0" @click="deleteTicketAttachment(att)">Delete</button>
                            </li>
                        </ul>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Expected resolution (hours)</label>
                    <input
                        v-model.number="form.estimated_resolve_hours"
                        type="number"
                        min="1"
                        max="8760"
                        step="1"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        placeholder="e.g. 24 — leave empty to use priority-based SLA"
                    />
                    <p class="text-xs text-slate-500 mt-1">If set, the due-by time is calculated from when the ticket is saved. Assigned users receive this in the assignment email.</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Priority</label>
                        <select
                            v-model="form.priority"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>

                    <div v-if="ticket">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select
                            v-model="form.status"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-500"
                        >
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="on_hold">On Hold</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Assign to (one or more)</label>
                        <div class="max-h-40 overflow-y-auto rounded-lg border border-slate-200 p-2 space-y-2 bg-slate-50/50">
                            <label
                                v-for="user in users"
                                :key="user.id"
                                class="flex items-center gap-2 text-sm text-slate-800 cursor-pointer"
                            >
                                <input v-model="form.assigned_user_ids" type="checkbox" :value="user.id" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30" />
                                {{ user.name }}
                            </label>
                        </div>
                    </div>
                </div>

                <div v-if="ticket" class="mt-4 pt-4 border-t border-slate-200 space-y-3">
                    <h3 class="text-sm font-semibold text-slate-900">Comments</h3>
                    <div v-if="comments.length" class="space-y-2 max-h-48 overflow-y-auto">
                        <div
                            v-for="msg in comments"
                            :key="msg.id"
                            class="border border-slate-200 rounded-lg p-2 bg-slate-50"
                        >
                            <div class="flex items-baseline justify-between gap-2 mb-1">
                                <span class="text-xs font-medium text-slate-800">{{ msg.user?.name || 'Unknown' }}</span>
                                <span class="text-[11px] text-slate-500">{{ formatDateTime(msg.created_at) }}</span>
                            </div>
                            <p class="text-xs text-slate-700 whitespace-pre-wrap">{{ msg.message }}</p>
                        </div>
                    </div>
                    <p v-else class="text-xs text-slate-500">No comments yet.</p>
                    <div class="space-y-2">
                        <textarea
                            v-model="newComment"
                            rows="3"
                            class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                            placeholder="Add a comment about this ticket..."
                        />
                        <div class="flex justify-end">
                            <button
                                type="button"
                                :disabled="commentLoading || !newComment.trim()"
                                @click="addComment"
                                class="px-4 py-2 text-sm rounded-lg bg-slate-900 text-white hover:bg-slate-800 disabled:opacity-50"
                            >
                                {{ commentLoading ? 'Adding...' : 'Add Comment' }}
                            </button>
                        </div>
                        <p v-if="commentError" class="text-xs text-red-600">{{ commentError }}</p>
                    </div>
                </div>

                <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded">
                    {{ error }}
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="loading"
                        class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                    >
                        {{ loading ? 'Saving...' : (ticket ? 'Update' : 'Create') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const props = defineProps({
    ticket: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const form = ref({
    customer_id: null,
    customer_phone: '',
    subject: '',
    description: '',
    reference_url: '',
    priority: 'medium',
    estimated_resolve_hours: null,
    status: 'open',
    assigned_user_ids: [],
});

const customers = ref([]);
const users = ref([]);
const loading = ref(false);
const error = ref(null);
const pendingAttachmentFiles = ref([]);
const attachmentInputRef = ref(null);
const ticketAttachments = ref([]);

const comments = ref([]);
const newComment = ref('');
const commentLoading = ref(false);
const commentError = ref(null);

const formatDateTime = (value) => {
    if (!value) return '';
    const d = new Date(value);
    return d.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

onMounted(async () => {
    try {
        const [customersRes, usersRes] = await Promise.all([
            axios.get('/api/customers', { params: { per_page: 100 } }),
            axios.get('/api/users'),
        ]);
        customers.value = customersRes.data.data || [];
        users.value = usersRes.data || [];
    } catch (err) {
        console.error('Failed to load data:', err);
    }

    if (props.ticket) {
        form.value = {
            customer_id: props.ticket.customer_id,
            customer_phone: '',
            subject: props.ticket.subject,
            description: props.ticket.description || '',
            reference_url: props.ticket.reference_url || '',
            priority: props.ticket.priority,
            estimated_resolve_hours: props.ticket.estimated_resolve_hours ?? null,
            status: props.ticket.status,
            assigned_user_ids: [],
        };

        try {
            const { data } = await axios.get(`/api/tickets/${props.ticket.id}`);
            comments.value = data.messages || [];
            ticketAttachments.value = data.attachments ? [...data.attachments] : [];
            const ids = (data.assignees || []).map((a) => a.id);
            form.value.assigned_user_ids = ids.length ? ids : (data.assigned_to ? [data.assigned_to] : []);
        } catch (err) {
            console.error('Failed to load ticket comments:', err);
        }
    }
});

const onAttachmentFilesSelected = (event) => {
    const picked = Array.from(event.target.files || []);
    if (picked.length) {
        pendingAttachmentFiles.value = [...pendingAttachmentFiles.value, ...picked];
    }
    event.target.value = '';
};

const removePendingFile = (index) => {
    pendingAttachmentFiles.value.splice(index, 1);
};

const deleteTicketAttachment = async (att) => {
    if (!props.ticket?.id) return;
    try {
        await axios.delete(`/api/tickets/${props.ticket.id}/attachments/${att.id}`);
        ticketAttachments.value = ticketAttachments.value.filter((a) => a.id !== att.id);
        toast.success('Attachment removed');
    } catch (err) {
        toast.error(err.response?.data?.message || 'Could not remove file');
    }
};

const buildFormDataPayload = (payload) => {
    const fd = new FormData();
    const appendIf = (key, val) => {
        if (val === null || val === undefined || val === '') return;
        fd.append(key, typeof val === 'number' ? String(val) : val);
    };
    appendIf('subject', payload.subject);
    appendIf('description', payload.description);
    appendIf('reference_url', payload.reference_url);
    appendIf('priority', payload.priority);
    if (payload.customer_id) fd.append('customer_id', String(payload.customer_id));
    appendIf('customer_phone', payload.customer_phone);
    (payload.assigned_user_ids || []).forEach((id) => fd.append('assigned_user_ids[]', String(id)));
    if (payload.estimated_resolve_hours != null && payload.estimated_resolve_hours !== '') {
        fd.append('estimated_resolve_hours', String(payload.estimated_resolve_hours));
    }
    pendingAttachmentFiles.value.forEach((f) => fd.append('attachments[]', f));
    return fd;
};

const handleSubmit = async () => {
    loading.value = true;
    error.value = null;

    try {
        const payload = { ...form.value };
        if (!payload.customer_id) {
            delete payload.customer_id;
        }
        if (payload.estimated_resolve_hours === '' || payload.estimated_resolve_hours === undefined) {
            payload.estimated_resolve_hours = null;
        }
        if (payload.reference_url === '') {
            payload.reference_url = null;
        }

        if (props.ticket) {
            await axios.put(`/api/tickets/${props.ticket.id}`, payload);
            if (pendingAttachmentFiles.value.length > 0) {
                const fd = new FormData();
                pendingAttachmentFiles.value.forEach((f) => fd.append('attachments[]', f));
                await axios.post(`/api/tickets/${props.ticket.id}/attachments`, fd, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
            }
        } else if (pendingAttachmentFiles.value.length > 0) {
            const fd = buildFormDataPayload(payload);
            await axios.post('/api/tickets', fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
        } else {
            await axios.post('/api/tickets', payload);
        }
        pendingAttachmentFiles.value = [];
        if (attachmentInputRef.value) attachmentInputRef.value.value = '';
        emit('saved');
        emit('close');
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to save ticket';
    } finally {
        loading.value = false;
    }
};

const addComment = async () => {
    if (!props.ticket || !newComment.value.trim()) return;
    commentLoading.value = true;
    commentError.value = null;
    try {
        const { data } = await axios.post(`/api/tickets/${props.ticket.id}/messages`, {
            message: newComment.value.trim(),
            is_internal: false,
        });
        comments.value.unshift(data);
        newComment.value = '';
    } catch (err) {
        commentError.value = err.response?.data?.message || 'Failed to add comment';
    } finally {
        commentLoading.value = false;
    }
};
</script>

