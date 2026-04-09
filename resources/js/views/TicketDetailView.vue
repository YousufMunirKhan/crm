<template>
    <div class="min-h-screen bg-slate-50 w-full min-w-0 overflow-x-hidden">
        <div class="max-w-4xl mx-auto px-3 sm:px-6 py-6 lg:py-8 w-full min-w-0">
            <!-- Back + Header -->
            <div class="mb-6">
                <router-link
                    to="/tickets"
                    class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 text-sm mb-4"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Tickets
                </router-link>
                <div v-if="loading && !ticket" class="flex items-center gap-2 text-slate-500">
                    <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Loading ticket...
                </div>
                <div v-else-if="ticket" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 min-w-0">
                    <div class="min-w-0">
                        <h1 class="text-xl sm:text-2xl font-bold text-slate-900 break-words">{{ ticket.ticket_number }}</h1>
                        <p class="text-base sm:text-lg text-slate-700 mt-0.5 break-words">{{ ticket.subject }}</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <router-link
                            :to="`/tickets/${ticket.id}/edit`"
                            class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium bg-white border border-slate-200 text-slate-800 hover:bg-slate-50 shadow-sm"
                        >
                            Edit ticket
                        </router-link>
                        <span
                            class="px-3 py-1 rounded-lg text-sm font-medium"
                            :class="getStatusClass(ticket.status)"
                        >
                            {{ getStatusLabel(ticket.status) }}
                        </span>
                        <span
                            class="px-3 py-1 rounded-lg text-sm font-medium"
                            :class="getPriorityClass(ticket.priority)"
                        >
                            {{ ticket.priority }}
                        </span>
                    </div>
                </div>
            </div>

            <template v-if="ticket">
                <!-- Admin lifecycle overview -->
                <div
                    v-if="isStaffAdmin"
                    class="bg-indigo-50 border border-indigo-100 rounded-xl p-4 sm:p-5 mb-6"
                >
                    <h2 class="text-sm font-semibold text-indigo-900 uppercase tracking-wide mb-3">Admin overview</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 text-sm">
                        <div class="bg-white/80 rounded-lg p-3 border border-indigo-100">
                            <div class="text-indigo-600 text-xs font-medium">Created</div>
                            <div class="text-slate-900 font-medium mt-0.5">{{ formatDateTime(ticket.created_at) }}</div>
                        </div>
                        <div class="bg-white/80 rounded-lg p-3 border border-indigo-100">
                            <div class="text-indigo-600 text-xs font-medium">Expected resolution</div>
                            <div class="text-slate-900 font-medium mt-0.5">
                                {{ ticket.estimated_resolve_hours ? `${ticket.estimated_resolve_hours} hour(s)` : 'Priority-based SLA' }}
                            </div>
                            <div v-if="ticket.sla_due_at" class="text-xs text-slate-600 mt-1">Due by {{ formatDateTime(ticket.sla_due_at) }}</div>
                        </div>
                        <div class="bg-white/80 rounded-lg p-3 border border-indigo-100">
                            <div class="text-indigo-600 text-xs font-medium">Resolved</div>
                            <div class="text-slate-900 font-medium mt-0.5">{{ ticket.resolved_at ? formatDateTime(ticket.resolved_at) : 'Not resolved yet' }}</div>
                            <div v-if="ticket.resolved_at && ticket.created_at" class="text-xs text-slate-600 mt-1">
                                Time to resolve: {{ formatDuration(ticket.created_at, ticket.resolved_at) }}
                            </div>
                        </div>
                        <div class="bg-white/80 rounded-lg p-3 border border-indigo-100 sm:col-span-2 lg:col-span-3">
                            <div class="text-indigo-600 text-xs font-medium">Comments</div>
                            <div class="text-slate-900 font-medium mt-0.5">{{ (ticket.messages || []).length }} on this ticket — newest at the bottom.</div>
                        </div>
                    </div>
                </div>

                <!-- Details Card -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-6">
                    <div class="px-4 sm:px-6 py-4 border-b border-slate-200 bg-slate-50">
                        <h2 class="text-base font-semibold text-slate-900">Ticket Details</h2>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Customer</span>
                                <div class="mt-0.5">
                                    <router-link
                                        v-if="ticket.customer_id"
                                        :to="`/customers/${ticket.customer_id}`"
                                        class="text-slate-900 font-medium hover:text-blue-600 hover:underline"
                                    >
                                        {{ ticket.customer?.name || '—' }}
                                    </router-link>
                                    <span v-else class="text-slate-600">—</span>
                                </div>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Assigned</span>
                                <div class="mt-0.5 text-slate-900">
                                    <template v-if="ticket.assignees && ticket.assignees.length">
                                        {{ ticket.assignees.map((a) => a.name).join(', ') }}
                                    </template>
                                    <template v-else>{{ ticket.assignee?.name || 'Unassigned' }}</template>
                                </div>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Created By</span>
                                <div class="mt-0.5 text-slate-900">{{ ticket.creator?.name || '—' }}</div>
                            </div>
                            <div>
                                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Created At</span>
                                <div class="mt-0.5 text-slate-900">{{ formatDateTime(ticket.created_at) }}</div>
                            </div>
                            <div v-if="ticket.resolved_at">
                                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Resolved At</span>
                                <div class="mt-0.5 text-slate-900">{{ formatDateTime(ticket.resolved_at) }}</div>
                            </div>
                            <div v-if="ticket.sla_due_at">
                                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">SLA Due</span>
                                <div class="mt-0.5 text-slate-900">{{ formatDateTime(ticket.sla_due_at) }}</div>
                            </div>
                            <div v-if="ticket.estimated_resolve_hours">
                                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Est. resolve (hours)</span>
                                <div class="mt-0.5 text-slate-900">{{ ticket.estimated_resolve_hours }}</div>
                            </div>
                            <div v-if="ticket.reference_url" class="sm:col-span-2">
                                <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Reference link (Drive / Sheet)</span>
                                <div class="mt-0.5">
                                    <a
                                        :href="ticket.reference_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="text-blue-600 font-medium hover:underline break-all"
                                    >{{ ticket.reference_url }}</a>
                                </div>
                            </div>
                        </div>
                        <div v-if="ticket.description" class="mt-4 pt-4 border-t border-slate-200">
                            <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Description</span>
                            <div class="mt-1 text-slate-700 whitespace-pre-wrap">{{ ticket.description }}</div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-slate-200">
                            <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950 mb-3">
                                <span class="font-semibold">Internal file sharing:</span>
                                for larger files it is <strong>preferred</strong> to use Google Drive or Google Sheets and paste the link above (or in the description). You can still add attachments here.
                            </div>
                            <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Attachments</span>
                            <ul v-if="ticket.attachments && ticket.attachments.length" class="mt-2 space-y-2">
                                <li
                                    v-for="att in ticket.attachments"
                                    :key="att.id"
                                    class="flex items-center justify-between gap-2 text-sm"
                                >
                                    <a :href="att.url" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline truncate">{{ att.original_name }}</a>
                                    <button
                                        type="button"
                                        class="text-xs text-red-600 shrink-0"
                                        @click="removeTicketAttachment(att)"
                                    >Remove</button>
                                </li>
                            </ul>
                            <p v-else class="mt-1 text-sm text-slate-500">No files attached yet.</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <input
                                    ref="detailAttachmentInputRef"
                                    type="file"
                                    multiple
                                    accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                    class="text-sm text-slate-600 file:mr-2 file:py-1.5 file:px-2 file:rounded file:border-0 file:bg-slate-100"
                                    @change="onDetailAttachmentFilesSelected"
                                />
                                <button
                                    type="button"
                                    :disabled="detailAttachmentUploading || !detailPendingAttachmentFiles.length"
                                    @click="uploadDetailAttachments"
                                    class="px-3 py-1.5 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                                >
                                    {{ detailAttachmentUploading ? 'Uploading…' : 'Upload selected' }}
                                </button>
                            </div>
                            <ul v-if="detailPendingAttachmentFiles.length" class="mt-2 text-xs text-slate-600 space-y-1">
                                <li v-for="(f, i) in detailPendingAttachmentFiles" :key="i" class="flex justify-between gap-2">
                                    <span class="truncate">{{ f.name }}</span>
                                    <button type="button" class="text-red-600" @click="removeDetailPendingFile(i)">Remove</button>
                                </li>
                            </ul>
                        </div>

                        <!-- Quick edit: Status & Assign -->
                        <div class="mt-4 pt-4 border-t border-slate-200 grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 items-start">
                            <div class="w-full max-w-md">
                                <label class="block text-xs font-medium text-slate-500 mb-1">Status</label>
                                <select
                                    v-model="editStatus"
                                    @change="updateTicketField('status')"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500"
                                >
                                    <option value="open">Open</option>
                                    <option value="in_progress">Working</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                            <div v-if="isStaffAdmin" class="w-full min-w-0">
                                <label class="block text-xs font-medium text-slate-500 mb-1">Assignees</label>
                                <div class="max-h-40 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2 space-y-1.5">
                                    <label
                                        v-for="u in users"
                                        :key="u.id"
                                        class="flex items-center gap-2 text-sm text-slate-800 cursor-pointer"
                                    >
                                        <input
                                            v-model="editAssigneeIds"
                                            type="checkbox"
                                            :value="Number(u.id)"
                                            class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500/30"
                                        />
                                        {{ u.name }}
                                    </label>
                                </div>
                                <div class="mt-3 flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center justify-center px-4 py-2 rounded-lg text-sm font-semibold bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 transition-colors"
                                        @click="saveAssignees"
                                    >
                                        Save assignees
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Comments -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-200">
                    <div class="px-4 sm:px-6 py-4 border-b border-slate-200 bg-slate-50">
                        <h2 class="text-base font-semibold text-slate-900">Comments</h2>
                        <p class="text-sm text-slate-500 mt-0.5">{{ (ticket.messages || []).length }} comment(s)</p>
                    </div>
                    <div class="p-4 sm:p-6">
                        <!-- Comment list -->
                        <div v-if="!(ticket.messages && ticket.messages.length)" class="text-center py-8 text-slate-500 text-sm">
                            No comments yet. Add one below.
                        </div>
                        <div v-else class="space-y-4 mb-6">
                            <div
                                v-for="msg in sortedMessages"
                                :key="msg.id"
                                class="flex gap-3 p-4 rounded-lg bg-slate-50 border border-slate-100"
                            >
                                <div class="w-10 h-10 rounded-full bg-slate-200 flex items-center justify-center text-slate-600 font-semibold text-sm flex-shrink-0">
                                    {{ getInitials(msg.user?.name) }}
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-wrap items-baseline gap-2">
                                        <span class="font-medium text-slate-900">{{ msg.user?.name || 'Unknown' }}</span>
                                        <span class="text-xs text-slate-500">{{ formatDateTime(msg.created_at) }}</span>
                                        <span
                                            v-if="msg.is_internal"
                                            class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-900 font-medium"
                                        >Internal</span>
                                    </div>
                                    <p class="text-slate-700 text-sm mt-1 whitespace-pre-wrap">{{ msg.message }}</p>
                                </div>
                            </div>
                        </div>
                        <!-- Add comment -->
                        <div class="border-t border-slate-200 pt-4 pb-1">
                            <label class="block text-sm font-medium text-slate-700 mb-2">Add a comment</label>
                            <p class="text-xs text-slate-500 mb-2">
                                Assignees and the ticket creator are notified by email, except the person who posted the comment.
                                <span v-if="isStaffAdmin"> Internal notes are not emailed.</span>
                            </p>

                            <div
                                v-if="newCommentInternal && isStaffAdmin"
                                class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950"
                            >
                                <strong>Internal note:</strong> no email will be sent for this comment.
                            </div>
                            <div
                                v-else-if="commentRecipientRows.length > 0"
                                class="mb-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-800"
                            >
                                <div class="font-semibold text-slate-900 mb-1">Email will be sent to:</div>
                                <ul class="space-y-1 list-none">
                                    <li v-for="row in commentRecipientRows" :key="row.email" class="break-all">
                                        <span class="font-medium">{{ row.name }}</span>
                                        <span class="text-slate-600"> — {{ row.email }}</span>
                                    </li>
                                </ul>
                            </div>
                            <div
                                v-else-if="!newCommentInternal || !isStaffAdmin"
                                class="mb-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950"
                            >
                                No other recipients with a valid email on file (you may be the only assignee, or profiles lack email). The system may still use the admin notification address from
                                <strong>Settings</strong> if no one else qualifies.
                            </div>

                            <textarea
                                ref="commentTextareaRef"
                                v-model="newComment"
                                rows="6"
                                class="w-full min-h-[10rem] max-h-[min(70vh,36rem)] px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:border-slate-500 resize-y overflow-x-hidden"
                                placeholder="Write your comment..."
                            />
                            <p class="text-xs text-slate-500 mt-1">Drag the corner to resize; grows with longer notes.</p>
                            <label v-if="isStaffAdmin" class="mt-3 flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                                <input v-model="newCommentInternal" type="checkbox" class="rounded border-slate-300 text-slate-900 focus:ring-slate-500" />
                                Internal note (no email)
                            </label>
                            <p v-if="commentError" class="text-sm text-red-600 mt-2">{{ commentError }}</p>
                            <div class="mt-4 flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-4">
                                <button
                                    type="button"
                                    :disabled="commentSending || !newComment.trim()"
                                    @click="addComment"
                                    class="w-full sm:w-auto shrink-0 inline-flex justify-center px-5 py-2.5 bg-slate-900 text-white rounded-lg text-sm font-semibold hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm"
                                >
                                    {{ commentSending ? 'Sending…' : 'Post comment' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div v-else-if="!loading && error" class="bg-red-50 border border-red-200 rounded-xl p-6 text-center">
                <p class="text-red-700">{{ error }}</p>
                <router-link to="/tickets" class="inline-block mt-3 text-sm text-red-600 hover:underline">Back to Tickets</router-link>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { useAutosizeTextarea } from '@/composables/useAutosizeTextarea';
import { useRoute } from 'vue-router';
import axios from 'axios';
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

const isStaffAdmin = computed(() => {
    const n = auth.user?.role?.name;
    return ['Admin', 'Manager', 'System Admin'].includes(n);
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

function getStatusClass(status) {
    const classes = {
        open: 'bg-yellow-100 text-yellow-800',
        in_progress: 'bg-blue-100 text-blue-800',
        on_hold: 'bg-amber-100 text-amber-800',
        resolved: 'bg-green-100 text-green-800',
        closed: 'bg-slate-100 text-slate-800',
    };
    return classes[status] || 'bg-slate-100 text-slate-800';
}

function getPriorityClass(priority) {
    const classes = {
        urgent: 'bg-red-100 text-red-800',
        high: 'bg-orange-100 text-orange-800',
        medium: 'bg-yellow-100 text-yellow-800',
        low: 'bg-blue-100 text-blue-800',
    };
    return classes[priority] || 'bg-slate-100 text-slate-800';
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

async function removeTicketAttachment(att) {
    if (!ticket.value?.id) return;
    try {
        await axios.delete(`/api/tickets/${ticket.value.id}/attachments/${att.id}`);
        ticket.value.attachments = (ticket.value.attachments || []).filter((a) => a.id !== att.id);
        toast.success('Attachment removed');
    } catch (err) {
        toast.error(err.response?.data?.message || 'Could not remove file');
    }
}

async function addComment() {
    if (!ticket.value || !newComment.value.trim()) return;
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
