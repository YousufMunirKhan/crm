<template>
    <div class="w-full min-w-0 overflow-x-hidden">
        <div class="page-narrow">
            <div v-if="loadError" class="callout callout-danger mb-6" role="alert">
                <p>{{ loadError }}</p>
                <div class="mt-3">
                    <BaseButton to="/tickets" variant="ghost" size="sm">
                        <template #icon>
                            <ArrowLeftIcon class="icon" aria-hidden="true" />
                        </template>
                        Back to tickets
                    </BaseButton>
                </div>
            </div>

            <template v-else>
                <div>
                    <BaseButton :to="`/tickets/${ticketId}`" variant="ghost" size="sm" class="mb-4">
                        <template #icon>
                            <ArrowLeftIcon class="icon" aria-hidden="true" />
                        </template>
                        Back to ticket
                    </BaseButton>
                    <p v-if="ticketNumber" class="text-sm font-mono text-slate-600">{{ ticketNumber }}</p>
                    <p class="page-lead mt-1">Changes save to the same record. Add comments from the ticket detail page.</p>
                </div>

                <div v-if="pageLoading" class="form-card p-6 space-y-3" aria-busy="true">
                    <span class="sr-only">Loading ticket…</span>
                    <div class="skeleton-text w-1/3"></div>
                    <div class="skeleton-text w-full"></div>
                    <div class="skeleton-text w-5/6"></div>
                    <div class="skeleton-text w-2/3"></div>
                    <div class="skeleton-text w-1/2"></div>
                </div>

                <form v-else id="ticket-edit-form" novalidate class="form-card !overflow-visible" @submit.prevent="handleSubmit">
                    <div class="form-section-head-mint">
                        <h2 class="form-section-title-mint text-xl">Update ticket</h2>
                        <p class="form-section-desc-mint">Assignees and creator receive email when someone posts a comment on the ticket detail page.</p>
                    </div>

                    <div class="form-body space-y-4">
                        <!-- Validation summary: focused on a failed submit -->
                        <div
                            v-if="error || errorFields.length"
                            ref="errorSummaryRef"
                            class="callout callout-danger focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-600/40"
                            role="alert"
                            tabindex="-1"
                        >
                            <p class="font-semibold">
                                {{ errorFields.length ? 'Please fix the following before saving:' : 'This ticket could not be saved' }}
                            </p>
                            <ul v-if="errorFields.length" class="mt-1.5 list-disc pl-5 space-y-0.5">
                                <li v-for="failed in errorFields" :key="failed.field">
                                    <span class="font-medium">{{ failed.label }}</span> — {{ failed.message }}
                                </li>
                            </ul>
                            <p v-else class="mt-1">{{ error }}</p>
                        </div>

                        <div>
                            <label class="form-label" for="ticketeditview-customer">Customer</label>
                            <select id="ticketeditview-customer" v-model="form.customer_id" class="form-select">
                                <option value="">No customer</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">
                                    {{ c.name }} — {{ c.phone }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label" for="ticketeditview-subject">
                                Subject<span class="form-required" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="ticketeditview-subject"
                                v-model="form.subject"
                                type="text"
                                required
                                class="form-input"
                                :aria-invalid="fieldErrors.subject ? 'true' : undefined"
                                :aria-describedby="fieldErrors.subject ? 'ticketeditview-subject-error' : undefined"
                            />
                            <p v-if="fieldErrors.subject" id="ticketeditview-subject-error" class="form-error">
                                {{ fieldErrors.subject }}
                            </p>
                        </div>

                        <div>
                            <label class="form-label" for="ticketeditview-description">Description</label>
                            <textarea
                                id="ticketeditview-description"
                                ref="descriptionTextareaRef"
                                v-model="form.description"
                                rows="8"
                                class="form-textarea-ticket-description"
                            />
                            <p class="form-hint">Drag the corner to resize. The field expands as you type or paste.</p>
                        </div>

                        <div>
                            <label class="form-label" for="ticketeditview-reference-link">Reference link</label>
                            <input id="ticketeditview-reference-link" v-model="form.reference_url" type="url" class="form-input" placeholder="https://..." />
                        </div>

                        <div>
                            <label class="form-label" for="ticketeditview-attachments">Attachments</label>
                            <p v-if="existingAttachments.length" class="text-xs text-slate-600 mb-2">
                                <span class="font-medium">Current:</span>
                                <span v-for="(att, i) in existingAttachments" :key="att.id" class="inline-flex items-center gap-1 ml-1">
                                    <a :href="att.url" target="_blank" rel="noopener" class="link">{{ att.original_name }}</a>
                                    <BaseButton
                                        variant="ghost"
                                        size="sm"
                                        :label="`Remove ${att.original_name}`"
                                        @click="requestRemoveAttachment(att)"
                                    >
                                        <template #icon>
                                            <TrashIcon class="icon-sm" aria-hidden="true" />
                                        </template>
                                        Remove
                                    </BaseButton>
                                    <span v-if="i < existingAttachments.length - 1" aria-hidden="true">·</span>
                                </span>
                            </p>
                            <input
                                id="ticketeditview-attachments"
                                ref="attachmentInputRef"
                                type="file"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                class="form-file"
                                @change="onFiles"
                            />
                            <ul v-if="pendingFiles.length" class="mt-2 text-xs text-slate-600 space-y-1">
                                <li v-for="(f, i) in pendingFiles" :key="i" class="flex items-center justify-between gap-2">
                                    <span class="truncate">{{ f.name }}</span>
                                    <BaseButton
                                        variant="ghost"
                                        size="sm"
                                        class="shrink-0"
                                        :label="`Remove ${f.name}`"
                                        @click="pendingFiles.splice(i, 1)"
                                    >
                                        <template #icon>
                                            <TrashIcon class="icon-sm" aria-hidden="true" />
                                        </template>
                                        Remove
                                    </BaseButton>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <label class="form-label" for="ticketeditview-estimated-resolve-hours">Expected resolution (hours)</label>
                            <input
                                id="ticketeditview-estimated-resolve-hours"
                                v-model.number="form.estimated_resolve_hours"
                                type="number"
                                min="1"
                                max="8760"
                                class="form-input"
                                placeholder="Empty = use priority SLA"
                                :aria-invalid="fieldErrors.estimated_resolve_hours ? 'true' : undefined"
                                :aria-describedby="fieldErrors.estimated_resolve_hours ? 'ticketeditview-estimated-resolve-hours-error' : undefined"
                            />
                            <p
                                v-if="fieldErrors.estimated_resolve_hours"
                                id="ticketeditview-estimated-resolve-hours-error"
                                class="form-error"
                            >
                                {{ fieldErrors.estimated_resolve_hours }}
                            </p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label" for="ticketeditview-priority">Priority</label>
                                <select id="ticketeditview-priority" v-model="form.priority" class="form-select">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="ticketeditview-status">Status</label>
                                <select id="ticketeditview-status" v-model="form.status" class="form-select">
                                    <option value="open">Open</option>
                                    <option value="in_progress">Working</option>
                                    <option value="on_hold">On hold</option>
                                    <option value="resolved">Resolved</option>
                                    <option value="closed">Closed</option>
                                </select>
                            </div>
                        </div>

                        <fieldset class="form-fieldset">
                            <legend class="form-legend">Assign to (one or more)</legend>
                            <div class="rounded-card border border-slate-200 bg-white p-3 max-h-56 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-4 gap-y-1">
                                <label
                                    v-for="u in users"
                                    :key="u.id"
                                    class="form-choice !flex min-w-0 text-slate-800"
                                >
                                    <input v-model="form.assigned_user_ids" type="checkbox" :value="Number(u.id)" class="form-checkbox" />
                                    {{ u.name }}
                                </label>
                                <p v-if="!users.length" class="text-sm text-slate-500 sm:col-span-2 lg:col-span-3">No users loaded.</p>
                            </div>
                            <div
                                v-if="editCommentRecipientRows.length > 0"
                                class="mt-3 callout callout-info text-xs"
                            >
                                <div class="font-semibold mb-1">After save, comment emails will go to:</div>
                                <ul class="space-y-1 list-none">
                                    <li v-for="row in editCommentRecipientRows" :key="row.email" class="break-all">
                                        <span class="font-medium">{{ row.name }}</span>
                                        <span> — {{ row.email }}</span>
                                    </li>
                                </ul>
                                <p class="mt-2">The person who posts a comment does not receive email for their own comment.</p>
                            </div>
                            <div
                                v-else-if="(form.assigned_user_ids || []).length > 0 || ticketCreator"
                                class="mt-3 callout callout-warning text-xs"
                            >
                                No valid recipient emails found for the current assignees or creator. Check user profiles or Settings → admin notification email.
                            </div>
                        </fieldset>
                    </div>

                    <div class="form-actions">
                        <BaseButton :to="`/tickets/${ticketId}`" variant="outline" block-mobile>Cancel</BaseButton>
                        <BaseButton
                            type="submit"
                            variant="primary"
                            size="lg"
                            block-mobile
                            :loading="saving"
                            form="ticket-edit-form"
                        >
                            {{ saving ? 'Saving…' : 'Save changes' }}
                        </BaseButton>
                    </div>
                </form>
            </template>
        </div>

        <ConfirmDialog
            v-model="confirmRemoveOpen"
            title="Remove this file?"
            :message="attachmentToRemove ? `“${attachmentToRemove.original_name}” will be deleted from this ticket.` : 'Remove this file?'"
            confirm-label="Remove file"
            cancel-label="Keep file"
            tone="danger"
            :loading="removingAttachment"
            @confirm="confirmRemoveAttachment"
            @cancel="cancelRemoveAttachment"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useAutosizeTextarea } from '@/composables/useAutosizeTextarea';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { ArrowLeftIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { BaseButton, ConfirmDialog } from '@/components/base';
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
const errorSummaryRef = ref(null);
const fieldErrors = ref({});
const confirmRemoveOpen = ref(false);
const attachmentToRemove = ref(null);
const removingAttachment = ref(false);
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

const FIELD_LABELS = {
    subject: 'Subject',
    estimated_resolve_hours: 'Expected resolution (hours)',
};

const errorFields = computed(() =>
    Object.keys(fieldErrors.value).map((field) => ({
        field,
        label: FIELD_LABELS[field] || field,
        message: fieldErrors.value[field],
    })),
);

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

function requestRemoveAttachment(att) {
    attachmentToRemove.value = att;
    confirmRemoveOpen.value = true;
}

function cancelRemoveAttachment() {
    if (removingAttachment.value) return;
    confirmRemoveOpen.value = false;
    attachmentToRemove.value = null;
}

async function confirmRemoveAttachment() {
    const att = attachmentToRemove.value;
    if (!att) return;
    removingAttachment.value = true;
    try {
        await axios.delete(`/api/tickets/${ticketId.value}/attachments/${att.id}`);
        existingAttachments.value = existingAttachments.value.filter((a) => a.id !== att.id);
        toast.success('Attachment removed');
    } catch (err) {
        toast.error(err.response?.data?.message || 'Could not remove file');
    } finally {
        removingAttachment.value = false;
        confirmRemoveOpen.value = false;
        attachmentToRemove.value = null;
    }
}

/** Client-side checks only — the payload itself is unchanged. */
function validate() {
    const errs = {};
    if (!String(form.value.subject ?? '').trim()) {
        errs.subject = 'Enter a subject so the ticket can be identified.';
    }
    const hours = form.value.estimated_resolve_hours;
    if (hours !== null && hours !== undefined && hours !== '') {
        const n = Number(hours);
        if (!Number.isFinite(n) || n < 1 || n > 8760) {
            errs.estimated_resolve_hours = 'Enter a whole number of hours between 1 and 8760, or leave it empty.';
        }
    }
    fieldErrors.value = errs;
    return Object.keys(errs).length === 0;
}

async function focusErrorSummary() {
    await nextTick();
    errorSummaryRef.value?.focus();
}

async function handleSubmit() {
    error.value = null;
    if (!validate()) {
        await focusErrorSummary();
        return;
    }

    saving.value = true;
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
        await focusErrorSummary();
    } finally {
        saving.value = false;
    }
}
</script>
