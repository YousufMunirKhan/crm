<template>
    <!--
        No background and no min-height of its own. The layout's <main> already
        paints bg-surface-sunken; adding bg-slate-50 on top of it put a second,
        slightly different tone behind the page, and min-h-screen forced a full
        viewport of it below the content - which is the empty block at the
        bottom of a short ticket.
    -->
    <div class="w-full min-w-0 overflow-x-hidden">
        <div class="page-narrow">
            <!-- Back + Header -->
            <div class="mb-6">
                <BaseButton to="/tickets" variant="ghost" size="sm" class="mb-4">
                    <template #icon>
                        <ArrowLeftIcon class="icon" aria-hidden="true" />
                    </template>
                    Back to Tickets
                </BaseButton>

                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 min-w-0">
                    <div class="min-w-0">
                        <h2 class="text-page-title text-slate-900 break-words">
                            {{ ticket ? ticket.ticket_number : 'Ticket' }}
                        </h2>
                        <p v-if="ticket" class="text-base sm:text-lg text-slate-700 mt-0.5 break-words">{{ ticket.subject }}</p>
                    </div>
                    <div v-if="ticket" class="flex flex-wrap items-center gap-2">
                        <BaseButton :to="`/tickets/${ticket.id}/edit`" variant="outline" size="sm">
                            <template #icon>
                                <PencilSquareIcon class="icon-sm" aria-hidden="true" />
                            </template>
                            Edit ticket
                        </BaseButton>
                        <BaseBadge :tone="getStatusTone(ticket.status)">{{ getStatusLabel(ticket.status) }}</BaseBadge>
                        <BaseBadge :tone="getPriorityTone(ticket.priority)">
                            Priority: {{ ticket.priority }}
                        </BaseBadge>
                    </div>
                </div>

                <div v-if="loading && !ticket" class="mt-3 space-y-2 max-w-sm" aria-busy="true">
                    <span class="sr-only">Loading ticket…</span>
                    <div class="skeleton-text w-2/3"></div>
                    <div class="skeleton-text w-1/2"></div>
                </div>
            </div>

            <template v-if="ticket">
                <!--
                    One strip for where the ticket stands.

                    Created, SLA and Resolved used to be printed twice - once in
                    an "Admin overview" card and again in the details grid a few
                    hundred pixels below - and the comment count appeared three
                    separate times on this page.
                -->
                <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-card border border-slate-200 bg-white p-3">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Raised</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">{{ formatDateTime(ticket.created_at) }}</p>
                        <p class="mt-0.5 text-xs text-slate-600">{{ ageLabel }}</p>
                    </div>

                    <div class="rounded-card border p-3" :class="slaTone">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Deadline</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            {{ ticket.sla_due_at ? formatDateTime(ticket.sla_due_at) : 'None set' }}
                        </p>
                        <p class="mt-0.5 text-xs" :class="slaBreached ? 'font-medium text-danger-700' : 'text-slate-600'">
                            {{ slaLabel }}
                        </p>
                    </div>

                    <div class="rounded-card border border-slate-200 bg-white p-3">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Resolved</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">
                            {{ ticket.resolved_at ? formatDateTime(ticket.resolved_at) : 'Not yet' }}
                        </p>
                        <p v-if="ticket.resolved_at" class="mt-0.5 text-xs text-slate-600">
                            Took {{ formatDuration(ticket.created_at, ticket.resolved_at) }}
                        </p>
                    </div>

                    <div class="rounded-card border p-3"
                         :class="assignedNames ? 'border-slate-200 bg-white' : 'border-danger-200 bg-danger-50/60'">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-slate-500">Assigned to</p>
                        <p class="mt-1 text-sm font-semibold break-words"
                           :class="assignedNames ? 'text-slate-900' : 'text-danger-700'">
                            {{ assignedNames || 'Nobody' }}
                        </p>
                    </div>
                </div>

                <BaseCard title="Ticket details" class="mb-6">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- 116 of 150 tickets have no customer, so an endless
                             row of "—" was telling nobody anything. -->
                        <div v-if="ticket.customer_id">
                            <span class="text-eyebrow uppercase text-slate-500">Customer</span>
                            <div class="mt-0.5">
                                <router-link :to="`/customers/${ticket.customer_id}`" class="link">
                                    {{ ticket.customer?.business_name || ticket.customer?.name || 'Customer' }}
                                </router-link>
                            </div>
                        </div>
                        <div>
                            <span class="text-eyebrow uppercase text-slate-500">Raised by</span>
                            <div class="mt-0.5 text-slate-900">{{ ticket.creator?.name || 'The POS feed' }}</div>
                        </div>
                        <div v-if="ticket.reference_url" class="sm:col-span-2">
                            <span class="text-eyebrow uppercase text-slate-500">Reference link</span>
                            <div class="mt-0.5">
                                <a
                                    :href="ticket.reference_url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="link break-all"
                                >{{ ticket.reference_url }}</a>
                            </div>
                        </div>
                    </div>

                    <div v-if="ticket.description" class="mt-4 border-t border-slate-200 pt-4">
                        <span class="text-eyebrow uppercase text-slate-500">What was reported</span>
                        <div class="mt-1 whitespace-pre-wrap text-slate-700">{{ ticket.description }}</div>
                    </div>
                </BaseCard>

                <BaseCard title="Move it on" class="mb-6">
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <div>
                            <label class="form-label" for="ticketdetailview-status">Status</label>
                            <select
                                id="ticketdetailview-status"
                                v-model="editStatus"
                                class="form-select"
                                @change="updateTicketField('status')"
                            >
                                <option value="open">Open</option>
                                <option value="in_progress">Working</option>
                                <option value="on_hold">On Hold</option>
                                <option value="resolved">Resolved</option>
                                <option value="closed">Closed</option>
                            </select>
                            <p class="form-hint">Saves as soon as you change it.</p>
                        </div>

                        <fieldset v-if="isStaffAdmin" class="form-fieldset min-w-0">
                            <legend class="form-legend">Who is on it</legend>
                            <!--
                                These labels are `inline-flex`, and the container
                                used `space-y`, which only separates block
                                elements - so thirteen names ran together on one
                                line and every checkbox appeared to belong to the
                                name after it. A grid gives each its own cell.
                            -->
                            <div class="grid grid-cols-1 gap-x-4 gap-y-1 rounded-card border border-slate-200 bg-white p-3 sm:grid-cols-2">
                                <label
                                    v-for="u in users"
                                    :key="u.id"
                                    class="form-choice text-slate-800"
                                >
                                    <input
                                        v-model="editAssigneeIds"
                                        type="checkbox"
                                        :value="Number(u.id)"
                                        class="form-checkbox"
                                    />
                                    <span class="truncate">{{ u.name }}</span>
                                </label>
                            </div>
                            <div class="mt-3">
                                <BaseButton variant="soft" @click="saveAssignees">
                                    <template #icon>
                                        <CheckIcon class="icon" aria-hidden="true" />
                                    </template>
                                    Save who is on it
                                </BaseButton>
                            </div>
                        </fieldset>
                    </div>
                </BaseCard>

                <BaseCard title="Attachments" class="mb-6">
                    <ul v-if="ticket.attachments && ticket.attachments.length" class="space-y-2">
                        <li
                            v-for="att in ticket.attachments"
                            :key="att.id"
                            class="flex items-center justify-between gap-2 text-sm"
                        >
                            <a :href="att.url" target="_blank" rel="noopener noreferrer" class="link truncate">{{ att.original_name }}</a>
                            <BaseButton
                                variant="ghost"
                                size="sm"
                                class="shrink-0"
                                :label="`Remove ${att.original_name}`"
                                @click="requestRemoveTicketAttachment(att)"
                            >
                                <template #icon>
                                    <TrashIcon class="icon-sm" aria-hidden="true" />
                                </template>
                                Remove
                            </BaseButton>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-slate-500">Nothing attached.</p>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <label class="form-label sr-only" for="ticketdetailview-attachments">Add attachments</label>
                        <input
                            id="ticketdetailview-attachments"
                            ref="detailAttachmentInputRef"
                            type="file"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"
                            class="form-file"
                            @change="onDetailAttachmentFilesSelected"
                        />
                        <BaseButton
                            variant="soft"
                            :disabled="!detailPendingAttachmentFiles.length"
                            :loading="detailAttachmentUploading"
                            @click="uploadDetailAttachments"
                        >
                            <template #icon>
                                <ArrowUpTrayIcon class="icon" aria-hidden="true" />
                            </template>
                            {{ detailAttachmentUploading ? 'Uploading…' : 'Upload selected' }}
                        </BaseButton>
                    </div>

                    <ul v-if="detailPendingAttachmentFiles.length" class="mt-2 space-y-1 text-xs text-slate-600">
                        <li v-for="(f, i) in detailPendingAttachmentFiles" :key="i" class="flex items-center justify-between gap-2">
                            <span class="truncate">{{ f.name }}</span>
                            <BaseButton
                                variant="ghost"
                                size="sm"
                                class="shrink-0"
                                :label="`Remove ${f.name}`"
                                @click="removeDetailPendingFile(i)"
                            >
                                <template #icon>
                                    <TrashIcon class="icon-sm" aria-hidden="true" />
                                </template>
                                Remove
                            </BaseButton>
                        </li>
                    </ul>

                    <!-- A hint, not a warning. It was a full amber callout sitting
                         above the attachments themselves. -->
                    <p class="form-hint mt-3">
                        For anything large, put it on Google Drive and paste the link in the
                        reference field instead.
                    </p>
                </BaseCard>

                <!-- Comments -->
                <BaseCard title="Comments" :subtitle="commentCountLabel">
                    <!-- Comment list -->
                    <EmptyState
                        v-if="!(ticket.messages && ticket.messages.length)"
                        heading="No comments yet"
                        description="Add the first comment below — assignees and the ticket creator are notified by email."
                    >
                        <template #icon>
                            <ChatBubbleLeftRightIcon class="icon" aria-hidden="true" />
                        </template>
                    </EmptyState>
                    <div v-else class="space-y-4 mb-6">
                        <div
                            v-for="msg in sortedMessages"
                            :key="msg.id"
                            class="flex gap-3 p-4 rounded-card bg-slate-50 border border-slate-100"
                        >
                            <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center text-slate-600 font-semibold text-sm flex-shrink-0" aria-hidden="true">
                                {{ getInitials(msg.user?.name) }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-baseline gap-2">
                                    <span class="font-medium text-slate-900">{{ msg.user?.name || 'Unknown' }}</span>
                                    <span class="text-xs text-slate-500">{{ formatDateTime(msg.created_at) }}</span>
                                    <BaseBadge v-if="msg.is_internal" tone="warning">Internal</BaseBadge>
                                </div>
                                <p class="text-slate-700 text-sm mt-1 whitespace-pre-wrap">{{ msg.message }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Add comment -->
                    <div class="border-t border-slate-200 pt-4 pb-1">
                        <label class="form-label" for="ticketdetailview-comment">Add a comment</label>
                        <p class="text-xs text-slate-500 mb-2">
                            Assignees and the ticket creator are notified by email, except the person who posted the comment.
                            <span v-if="isStaffAdmin"> Internal notes are not emailed.</span>
                        </p>

                        <div
                            v-if="newCommentInternal && isStaffAdmin"
                            class="mb-3 callout callout-warning text-xs"
                        >
                            <strong>Internal note:</strong> no email will be sent for this comment.
                        </div>
                        <div
                            v-else-if="commentRecipientRows.length > 0"
                            class="mb-3 callout callout-info text-xs"
                        >
                            <div class="font-semibold mb-1">Email will be sent to:</div>
                            <ul class="space-y-1 list-none">
                                <li v-for="row in commentRecipientRows" :key="row.email" class="break-all">
                                    <span class="font-medium">{{ row.name }}</span>
                                    <span> — {{ row.email }}</span>
                                </li>
                            </ul>
                        </div>
                        <div
                            v-else-if="!newCommentInternal || !isStaffAdmin"
                            class="mb-3 callout callout-warning text-xs"
                        >
                            No other recipients with a valid email on file (you may be the only assignee, or profiles lack email). The system may still use the admin notification address from
                            <strong>Settings</strong> if no one else qualifies.
                        </div>

                        <textarea
                            id="ticketdetailview-comment"
                            ref="commentTextareaRef"
                            v-model="newComment"
                            rows="6"
                            class="form-textarea min-h-[10rem] max-h-[min(70vh,36rem)] overflow-x-hidden"
                            placeholder="Write your comment..."
                            :aria-invalid="commentError ? 'true' : undefined"
                            :aria-describedby="commentError ? 'ticketdetailview-comment-error' : undefined"
                        />
                        <p class="form-hint">Drag the corner to resize; grows with longer notes.</p>
                        <label v-if="isStaffAdmin" class="form-choice mt-3">
                            <input v-model="newCommentInternal" type="checkbox" class="form-checkbox" />
                            Internal note (no email)
                        </label>
                        <p
                            v-if="commentError"
                            id="ticketdetailview-comment-error"
                            class="callout callout-danger mt-2"
                            role="alert"
                        >{{ commentError }}</p>
                        <div class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4">
                            <BaseButton
                                variant="primary"
                                block-mobile
                                :loading="commentSending"
                                @click="addComment"
                            >
                                <template #icon>
                                    <PaperAirplaneIcon class="icon" aria-hidden="true" />
                                </template>
                                {{ commentSending ? 'Sending…' : 'Post comment' }}
                            </BaseButton>
                        </div>
                    </div>
                </BaseCard>
            </template>

            <div v-else-if="!loading && error" class="callout callout-danger text-center" role="alert">
                <p>{{ error }}</p>
                <div class="mt-3">
                    <BaseButton to="/tickets" variant="ghost" size="sm">
                        <template #icon>
                            <ArrowLeftIcon class="icon" aria-hidden="true" />
                        </template>
                        Back to Tickets
                    </BaseButton>
                </div>
            </div>
        </div>

        <ConfirmDialog
            v-model="confirmRemoveOpen"
            title="Remove this file?"
            :message="attachmentToRemove ? `“${attachmentToRemove.original_name}” will be deleted from this ticket.` : 'Remove this file?'"
            confirm-label="Remove file"
            cancel-label="Keep file"
            tone="danger"
            :loading="removingAttachment"
            @confirm="confirmRemoveTicketAttachment"
            @cancel="cancelRemoveTicketAttachment"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useAutosizeTextarea } from '@/composables/useAutosizeTextarea';
import { useRoute } from 'vue-router';
import axios from 'axios';
import {
    ArrowLeftIcon,
    ArrowUpTrayIcon,
    ChatBubbleLeftRightIcon,
    CheckIcon,
    PaperAirplaneIcon,
    PencilSquareIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import { BaseBadge, BaseButton, BaseCard, ConfirmDialog, EmptyState } from '@/components/base';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';
import { formatTicketStatus } from '@/utils/displayFormat';

const route = useRoute();
const toast = useToastStore();
const auth = useAuthStore();

const ticket = ref(null);
const loading = ref(true);
const error = ref(null);
const users = ref([]);
const newComment = ref('');
const { textareaRef: commentTextareaRef, syncHeight: syncCommentHeight } = useAutosizeTextarea(() => newComment.value, {
    minHeightPx: 160,
});
const commentSending = ref(false);
const commentError = ref(null);
const editStatus = ref('open');
const editAssigneeIds = ref([]);
const newCommentInternal = ref(false);
const detailPendingAttachmentFiles = ref([]);
const detailAttachmentUploading = ref(false);
const detailAttachmentInputRef = ref(null);
const confirmRemoveOpen = ref(false);
const attachmentToRemove = ref(null);
const removingAttachment = ref(false);

const isStaffAdmin = computed(() => {
    const n = auth.user?.role?.name;
    return ['Admin', 'Manager', 'System Admin'].includes(n);
});

/** Who is on it, or nothing - so the strip can say "Nobody" in red. */
const assignedNames = computed(() => {
    const list = ticket.value?.assignees;

    if (Array.isArray(list) && list.length) {
        return list.map((a) => a.name).filter(Boolean).join(', ');
    }

    return ticket.value?.assignee?.name || '';
});

const isFinished = computed(() => ['resolved', 'closed'].includes(ticket.value?.status));

/** How long this has been going on, in the words a person would use. */
const ageLabel = computed(() => {
    if (! ticket.value?.created_at) return '';

    const days = Math.floor((Date.now() - new Date(ticket.value.created_at)) / 86400000);

    if (isFinished.value) return days <= 0 ? 'Closed the same day' : `${days} days from raised to now`;
    if (days <= 0) return 'Raised today';

    return `Open ${days} ${days === 1 ? 'day' : 'days'}`;
});

/**
 * Past its deadline and still not finished.
 *
 * A ticket with no `sla_due_at` cannot breach at all - which was the state of
 * every POS ticket in the system until recently - so that is said out loud
 * rather than shown as a passing green.
 */
const slaBreached = computed(() =>
    !! ticket.value?.sla_due_at && ! isFinished.value && new Date(ticket.value.sla_due_at) < new Date());

const slaLabel = computed(() => {
    if (! ticket.value?.sla_due_at) return 'No deadline, so it can never be late';
    if (isFinished.value) return 'Finished';
    if (slaBreached.value) return 'Past its deadline';

    const hours = Math.round((new Date(ticket.value.sla_due_at) - Date.now()) / 3600000);

    return hours <= 0 ? 'Due now' : `${hours} ${hours === 1 ? 'hour' : 'hours'} left`;
});

const slaTone = computed(() => {
    if (slaBreached.value) return 'border-danger-200 bg-danger-50/60';
    if (! ticket.value?.sla_due_at) return 'border-warning-200 bg-warning-50/50';

    return 'border-slate-200 bg-white';
});

/** "0 comment(s)" reads as a bug in the page rather than a count. */
const commentCountLabel = computed(() => {
    const n = (ticket.value?.messages || []).length;

    if (n === 0) return 'Nothing said yet';

    return `${n} ${n === 1 ? 'comment' : 'comments'}, newest last`;
});

const sortedMessages = computed(() => {
    const list = ticket.value?.messages || [];
    return [...list].sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
});

/** Matches server comment recipients: assignees + assigned_to + creator, excluding current user; requires valid email. */
const commentRecipientRows = computed(() => {
    if (!ticket.value || newCommentInternal.value) {
        return [];
    }
    const myId = auth.user?.id != null ? Number(auth.user.id) : null;
    const seen = new Set();
    const rows = [];

    const pushUser = (u) => {
        if (!u || !u.email || typeof u.email !== 'string') return;
        const email = u.email.trim();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return;
        const uid = u.id != null ? Number(u.id) : null;
        if (myId !== null && uid === myId) return;
        const key = email.toLowerCase();
        if (seen.has(key)) return;
        seen.add(key);
        rows.push({ name: u.name || email, email });
    };

    (ticket.value.assignees || []).forEach(pushUser);

    const aid = ticket.value.assigned_to != null ? Number(ticket.value.assigned_to) : null;
    if (aid) {
        const fromAssignees = (ticket.value.assignees || []).find((a) => Number(a.id) === aid);
        if (fromAssignees) {
            pushUser(fromAssignees);
        } else {
            pushUser(ticket.value.assignee);
            const u = users.value.find((x) => Number(x.id) === aid);
            pushUser(u);
        }
    }

    pushUser(ticket.value.creator);

    return rows;
});

function getStatusLabel(status) {
    return formatTicketStatus(status, 'Open');
}

function formatDuration(start, end) {
    if (!start || !end) return '—';
    const a = new Date(start).getTime();
    const b = new Date(end).getTime();
    if (b <= a) return '—';
    const ms = b - a;
    const h = Math.floor(ms / 3600000);
    const m = Math.floor((ms % 3600000) / 60000);
    if (h > 48) {
        const d = Math.floor(h / 24);
        return `${d}d ${h % 24}h`;
    }
    if (h > 0) return `${h}h ${m}m`;
    return `${m}m`;
}

/** Visual tone only — the raw status value is untouched. */
function getStatusTone(status) {
    const tones = {
        open: 'warning',
        in_progress: 'primary',
        on_hold: 'warning',
        resolved: 'success',
        closed: 'neutral',
    };
    return tones[status] || 'neutral';
}

function getPriorityTone(priority) {
    const tones = {
        urgent: 'danger',
        high: 'danger',
        medium: 'warning',
        low: 'primary',
    };
    return tones[priority] || 'neutral';
}

function formatDateTime(value) {
    if (!value) return '—';
    const d = new Date(value);
    return d.toLocaleString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getInitials(name) {
    if (!name) return '?';
    return name.split(' ').map(n => n[0]).join('').toUpperCase().slice(0, 2);
}

async function loadTicket() {
    if (!route.params.id) return;
    loading.value = true;
    error.value = null;
    try {
        const { data } = await axios.get(`/api/tickets/${route.params.id}`);
        ticket.value = {
            ...data,
            attachments: data.attachments || [],
        };
        editStatus.value = data.status;
        editAssigneeIds.value = (data.assignees || []).map((a) => Number(a.id));
    } catch (err) {
        if (err.response?.status === 403) {
            error.value = 'You do not have access to this ticket.';
        } else if (err.response?.status === 404) {
            error.value = 'Ticket not found.';
        } else {
            error.value = err.response?.data?.message || 'Failed to load ticket.';
        }
        ticket.value = null;
    } finally {
        loading.value = false;
    }
}

async function loadUsers() {
    try {
        const { data } = await axios.get('/api/users');
        users.value = Array.isArray(data) ? data : (data?.data || []);
    } catch (_) {
        users.value = [];
    }
}

async function updateTicketField(field) {
    if (!ticket.value) return;
    try {
        const payload = { status: editStatus.value };
        const { data } = await axios.put(`/api/tickets/${ticket.value.id}`, payload);
        ticket.value = {
            ...ticket.value,
            ...data,
            attachments: data.attachments ?? ticket.value.attachments ?? [],
            messages: ticket.value.messages,
        };
        if (field === 'status') editStatus.value = data.status;
        toast.success('Ticket updated');
    } catch (err) {
        toast.error(err.response?.data?.message || 'Failed to update');
    }
}

async function saveAssignees() {
    if (!ticket.value) return;
    try {
        const { data } = await axios.put(`/api/tickets/${ticket.value.id}`, {
            assigned_user_ids: editAssigneeIds.value,
        });
        ticket.value = {
            ...ticket.value,
            ...data,
            attachments: data.attachments ?? ticket.value.attachments ?? [],
            messages: ticket.value.messages,
        };
        editAssigneeIds.value = (data.assignees || []).map((a) => Number(a.id));
        toast.success('Assignees updated');
    } catch (err) {
        toast.error(err.response?.data?.message || 'Failed to update assignees');
    }
}

function onDetailAttachmentFilesSelected(event) {
    const picked = Array.from(event.target.files || []);
    if (picked.length) {
        detailPendingAttachmentFiles.value = [...detailPendingAttachmentFiles.value, ...picked];
    }
    event.target.value = '';
}

function removeDetailPendingFile(index) {
    detailPendingAttachmentFiles.value.splice(index, 1);
}

async function uploadDetailAttachments() {
    if (!ticket.value?.id || !detailPendingAttachmentFiles.value.length) return;
    detailAttachmentUploading.value = true;
    try {
        const fd = new FormData();
        detailPendingAttachmentFiles.value.forEach((f) => fd.append('attachments[]', f));
        const { data } = await axios.post(`/api/tickets/${ticket.value.id}/attachments`, fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        ticket.value.attachments = data.attachments || [];
        detailPendingAttachmentFiles.value = [];
        if (detailAttachmentInputRef.value) detailAttachmentInputRef.value.value = '';
        toast.success('Files uploaded');
    } catch (err) {
        toast.error(err.response?.data?.message || 'Upload failed');
    } finally {
        detailAttachmentUploading.value = false;
    }
}

function requestRemoveTicketAttachment(att) {
    attachmentToRemove.value = att;
    confirmRemoveOpen.value = true;
}

function cancelRemoveTicketAttachment() {
    if (removingAttachment.value) return;
    confirmRemoveOpen.value = false;
    attachmentToRemove.value = null;
}

async function confirmRemoveTicketAttachment() {
    const att = attachmentToRemove.value;
    if (!ticket.value?.id || !att) return;
    removingAttachment.value = true;
    try {
        await axios.delete(`/api/tickets/${ticket.value.id}/attachments/${att.id}`);
        ticket.value.attachments = (ticket.value.attachments || []).filter((a) => a.id !== att.id);
        toast.success('Attachment removed');
    } catch (err) {
        toast.error(err.response?.data?.message || 'Could not remove file');
    } finally {
        removingAttachment.value = false;
        confirmRemoveOpen.value = false;
        attachmentToRemove.value = null;
    }
}

async function addComment() {
    if (!ticket.value) return;
    if (!newComment.value.trim()) {
        commentError.value = 'Write a comment before posting.';
        return;
    }
    commentSending.value = true;
    commentError.value = null;
    try {
        const { data } = await axios.post(`/api/tickets/${ticket.value.id}/messages`, {
            message: newComment.value.trim(),
            is_internal: !!(isStaffAdmin.value && newCommentInternal.value),
        });
        if (!ticket.value.messages) ticket.value.messages = [];
        ticket.value.messages.push(data);
        newComment.value = '';
        newCommentInternal.value = false;
        await nextTick();
        syncCommentHeight();
        toast.success('Comment added');
    } catch (err) {
        commentError.value = err.response?.data?.message || 'Failed to add comment';
    } finally {
        commentSending.value = false;
    }
}

watch(() => route.params.id, loadTicket, { immediate: false });

onMounted(() => {
    if (!auth.initialized) auth.bootstrap();
    loadUsers();
    loadTicket();
});
</script>
