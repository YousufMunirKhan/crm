<template>
    <BaseModal
        :model-value="true"
        :title="ticket ? 'Edit Ticket' : 'Create New Ticket'"
        size="md"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <form id="ticket-form" class="space-y-4" @submit.prevent="handleSubmit">
            <div>
                <label class="form-label" for="ticketform-customer">Customer</label>
                <select id="ticketform-customer"
                    v-model="form.customer_id"
                    class="form-select"
                >
                    <option value="">Select Customer (Optional)</option>
                    <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                        {{ customer.name }} - {{ customer.phone }}
                    </option>
                </select>
                <p class="form-hint">Or enter customer phone below</p>
            </div>

            <div v-if="!form.customer_id">
                <label class="form-label" for="ticketform-customer-phone">Customer Phone</label>
                <input id="ticketform-customer-phone"
                    v-model="form.customer_phone"
                    type="text"
                    class="form-input"
                />
            </div>

            <div>
                <label class="form-label" for="ticketform-subject">Subject <span class="form-required" aria-hidden="true">*</span></label>
                <input id="ticketform-subject"
                    v-model="form.subject"
                    type="text"
                    required
                    class="form-input"
                />
            </div>

            <div>
                <label class="form-label" for="ticketform-description">Description</label>
                <textarea id="ticketform-description"
                    ref="descriptionTextareaRef"
                    v-model="form.description"
                    rows="8"
                    class="form-textarea-ticket-description"
                />
                <p class="form-hint">Drag the corner to resize. Expands as you type or paste.</p>
            </div>

            <div class="callout callout-warning">
                <p class="font-medium">Files &amp; long documents (internal)</p>
                <p class="mt-1">
                    For larger files or collaborative docs, it’s <strong>preferred</strong> that you upload to
                    <strong>Google Drive</strong> or <strong>Google Sheets</strong>, then paste the shared link in
                    <strong>Reference link</strong> below. You can still attach files here if needed.
                </p>
            </div>

            <div>
                <label class="form-label" for="ticketform-reference-link-google-drive-sheet-url">Reference link (Google Drive / Sheet URL)</label>
                <input id="ticketform-reference-link-google-drive-sheet-url"
                    v-model="form.reference_url"
                    type="url"
                    class="form-input"
                    placeholder="https://drive.google.com/... or https://docs.google.com/..."
                />
                <p class="form-hint">This URL is stored on the ticket and included in notification emails.</p>
            </div>

            <div>
                <label class="form-label" for="ticketform-attachments">Attachments</label>
                <input id="ticketform-attachments"
                    ref="attachmentInputRef"
                    type="file"
                    multiple
                    accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"
                    class="block w-full rounded-control text-sm text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-control file:border-0 file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                    @change="onAttachmentFilesSelected"
                />
                <ul v-if="pendingAttachmentFiles.length" class="mt-2 space-y-1 text-xs text-slate-600">
                    <li v-for="(f, i) in pendingAttachmentFiles" :key="i" class="flex items-center justify-between gap-2">
                        <span class="truncate">{{ f.name }}</span>
                        <BaseButton
                            variant="ghost"
                            size="icon"
                            class="shrink-0 text-danger-700"
                            :label="`Remove ${f.name}`"
                            @click="removePendingFile(i)"
                        >
                            <template #icon>
                                <XMarkIcon class="icon-sm" aria-hidden="true" />
                            </template>
                        </BaseButton>
                    </li>
                </ul>
                <div v-if="ticket && ticketAttachments.length" class="mt-3 border-t border-slate-200 pt-3">
                    <p class="text-eyebrow text-slate-500 mb-2">Current files</p>
                    <ul class="space-y-2">
                        <li
                            v-for="att in ticketAttachments"
                            :key="att.id"
                            class="flex items-center justify-between gap-2 text-sm"
                        >
                            <a :href="att.url" target="_blank" rel="noopener noreferrer" class="link truncate">{{ att.original_name }}</a>
                            <BaseButton
                                variant="ghost"
                                size="icon"
                                class="shrink-0 text-danger-700"
                                :label="`Delete ${att.original_name}`"
                                @click="deleteTicketAttachment(att)"
                            >
                                <template #icon>
                                    <TrashIcon class="icon-sm" aria-hidden="true" />
                                </template>
                            </BaseButton>
                        </li>
                    </ul>
                </div>
            </div>

            <div>
                <label class="form-label" for="ticketform-estimated-resolve-hours">Expected resolution (hours)</label>
                <input id="ticketform-estimated-resolve-hours"
                    v-model.number="form.estimated_resolve_hours"
                    type="number"
                    min="1"
                    max="8760"
                    step="1"
                    class="form-input"
                    placeholder="e.g. 24 — leave empty to use priority-based SLA"
                />
                <p class="form-hint">If set, the due-by time is calculated from when the ticket is saved. Assigned users receive this in the assignment email.</p>
            </div>

            <div class="form-grid-2">
                <div>
                    <label class="form-label" for="ticketform-priority">Priority</label>
                    <select id="ticketform-priority"
                        v-model="form.priority"
                        class="form-select"
                    >
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>

                <div v-if="ticket">
                    <label class="form-label" for="ticketform-status">Status</label>
                    <select id="ticketform-status"
                        v-model="form.status"
                        class="form-select"
                    >
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="on_hold">On Hold</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>

                <fieldset class="form-fieldset sm:col-span-2">
                    <legend class="form-legend">Assign to (one or more)</legend>
                    <div class="max-h-40 space-y-2 overflow-y-auto rounded-control border border-slate-200 bg-slate-50/50 p-2">
                        <label
                            v-for="user in users"
                            :key="user.id"
                            class="form-choice flex"
                        >
                            <input v-model="form.assigned_user_ids" type="checkbox" :value="user.id" class="form-checkbox" />
                            {{ user.name }}
                        </label>
                    </div>
                </fieldset>
            </div>

            <div v-if="ticket" class="mt-4 space-y-3 border-t border-slate-200 pt-4">
                <h3 class="subsection-title">Comments</h3>
                <div v-if="comments.length" class="max-h-48 space-y-2 overflow-y-auto">
                    <div
                        v-for="msg in comments"
                        :key="msg.id"
                        class="rounded-control border border-slate-200 bg-slate-50 p-2"
                    >
                        <div class="mb-1 flex items-baseline justify-between gap-2">
                            <span class="text-xs font-medium text-slate-800">{{ msg.user?.name || 'Unknown' }}</span>
                            <span class="text-[11px] text-slate-500">{{ formatDateTime(msg.created_at) }}</span>
                        </div>
                        <p class="text-xs text-slate-700 whitespace-pre-wrap">{{ msg.message }}</p>
                    </div>
                </div>
                <p v-else class="text-xs text-slate-500">No comments yet.</p>
                <div class="space-y-2">
                    <label class="sr-only" for="ticketform-new-comment">Add a comment about this ticket</label>
                    <textarea
                        id="ticketform-new-comment"
                        ref="commentTextareaRef"
                        v-model="newComment"
                        rows="4"
                        class="form-textarea min-h-[7.5rem] max-h-[min(50vh,24rem)] overflow-x-hidden"
                        placeholder="Add a comment about this ticket..."
                    />
                    <div class="flex justify-end">
                        <BaseButton
                            variant="soft"
                            :loading="commentLoading"
                            :disabled="!newComment.trim()"
                            @click="addComment"
                        >
                            {{ commentLoading ? 'Adding...' : 'Add Comment' }}
                        </BaseButton>
                    </div>
                    <p v-if="commentError" class="form-error" role="alert">{{ commentError }}</p>
                </div>
            </div>

            <p v-if="error" class="callout callout-danger" role="alert">
                {{ error }}
            </p>
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="$emit('close')">Cancel</BaseButton>
            <BaseButton
                variant="primary"
                type="submit"
                form="ticket-form"
                block-mobile
                :loading="loading"
            >
                {{ loading ? 'Saving...' : (ticket ? 'Update' : 'Create') }}
            </BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import { useAutosizeTextarea } from '@/composables/useAutosizeTextarea';
import axios from 'axios';
import { TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import { BaseButton, BaseModal } from '@/components/base';

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

const { textareaRef: descriptionTextareaRef } = useAutosizeTextarea(() => form.value.description, {
    minHeightPx: 208,
});
const { textareaRef: commentTextareaRef, syncHeight: syncCommentHeight } = useAutosizeTextarea(() => newComment.value, {
    minHeightPx: 120,
});

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
        nextTick(syncCommentHeight);
    } catch (err) {
        commentError.value = err.response?.data?.message || 'Failed to add comment';
    } finally {
        commentLoading.value = false;
    }
};
</script>
