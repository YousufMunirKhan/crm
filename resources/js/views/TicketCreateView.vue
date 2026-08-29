<template>
    <div class="min-h-screen bg-slate-50 w-full min-w-0 overflow-x-hidden">
        <div class="max-w-3xl mx-auto px-3 sm:px-6 py-6 lg:py-8 w-full min-w-0">
            <div class="mb-6">
                <BaseButton to="/tickets" variant="ghost" size="sm" class="mb-4">
                    <template #icon>
                        <ArrowLeftIcon class="icon" aria-hidden="true" />
                    </template>
                    Back to Tickets
                </BaseButton>
                <h1 class="text-2xl font-bold text-slate-900">Create Ticket</h1>
                <p class="text-slate-500 mt-1 text-sm">Assign one or more team members — everyone on the list is notified by email on new comments.</p>
            </div>

            <form id="ticket-create-form" novalidate class="form-card !overflow-visible" @submit.prevent="handleSubmit">
                <div class="form-section-head-mint">
                    <h2 class="form-section-title-mint text-xl">New support ticket</h2>
                    <p class="form-section-desc-mint">Describe the issue and choose who should work on it.</p>
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
                            {{ errorFields.length ? 'Please fix the following before saving:' : 'This ticket could not be created' }}
                        </p>
                        <ul v-if="errorFields.length" class="mt-1.5 list-disc pl-5 space-y-0.5">
                            <li v-for="failed in errorFields" :key="failed.field">
                                <span class="font-medium">{{ failed.label }}</span> — {{ failed.message }}
                            </li>
                        </ul>
                        <p v-else class="mt-1">{{ error }}</p>
                    </div>

                    <div>
                        <label class="form-label" for="ticketcreateview-customer">Customer</label>
                        <select id="ticketcreateview-customer" v-model="form.customer_id" class="form-select">
                            <option value="">Select customer (optional)</option>
                            <option v-for="c in customers" :key="c.id" :value="c.id">
                                {{ c.name }} — {{ c.phone }}
                            </option>
                        </select>
                        <p class="form-hint">Or enter a phone number below if not in the list.</p>
                    </div>

                    <div v-if="!form.customer_id">
                        <label class="form-label" for="ticketcreateview-customer-phone">Customer phone</label>
                        <input
                            id="ticketcreateview-customer-phone"
                            v-model="form.customer_phone"
                            type="text"
                            class="form-input"
                            placeholder="e.g. 07700900123"
                        />
                    </div>

                    <div>
                        <label class="form-label" for="ticketcreateview-subject">
                            Subject<span class="form-required" aria-hidden="true">*</span>
                        </label>
                        <input
                            id="ticketcreateview-subject"
                            v-model="form.subject"
                            type="text"
                            required
                            class="form-input"
                            :aria-invalid="fieldErrors.subject ? 'true' : undefined"
                            :aria-describedby="fieldErrors.subject ? 'ticketcreateview-subject-error' : undefined"
                        />
                        <p v-if="fieldErrors.subject" id="ticketcreateview-subject-error" class="form-error">
                            {{ fieldErrors.subject }}
                        </p>
                    </div>

                    <div>
                        <label class="form-label" for="ticketcreateview-description">Description</label>
                        <textarea id="ticketcreateview-description"
                            ref="descriptionTextareaRef"
                            v-model="form.description"
                            rows="8"
                            class="form-textarea-ticket-description"
                        />
                        <p class="form-hint">Drag the corner to resize. The field expands as you type or paste.</p>
                    </div>

                    <div class="callout callout-warning">
                        <p class="font-semibold">Large files</p>
                        <p class="mt-1">
                            Prefer uploading to Google Drive or Sheets and paste the link in <strong>Reference link</strong> below.
                        </p>
                    </div>

                    <div>
                        <label class="form-label" for="ticketcreateview-reference-link-drive-sheet">Reference link (Drive / Sheet)</label>
                        <input id="ticketcreateview-reference-link-drive-sheet" v-model="form.reference_url" type="url" class="form-input" placeholder="https://..." />
                    </div>

                    <div>
                        <label class="form-label" for="ticketcreateview-attachments">Attachments</label>
                        <input id="ticketcreateview-attachments"
                            ref="attachmentInputRef"
                            type="file"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"
                            class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 rounded-control"
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
                        <label class="form-label" for="ticketcreateview-estimated-resolve-hours">Expected resolution (hours)</label>
                        <input
                            id="ticketcreateview-estimated-resolve-hours"
                            v-model.number="form.estimated_resolve_hours"
                            type="number"
                            min="1"
                            max="8760"
                            class="form-input"
                            placeholder="Leave empty for priority-based SLA"
                            :aria-invalid="fieldErrors.estimated_resolve_hours ? 'true' : undefined"
                            :aria-describedby="fieldErrors.estimated_resolve_hours ? 'ticketcreateview-estimated-resolve-hours-error' : undefined"
                        />
                        <p
                            v-if="fieldErrors.estimated_resolve_hours"
                            id="ticketcreateview-estimated-resolve-hours-error"
                            class="form-error"
                        >
                            {{ fieldErrors.estimated_resolve_hours }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label" for="ticketcreateview-priority">Priority</label>
                            <select id="ticketcreateview-priority" v-model="form.priority" class="form-select">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>

                    <fieldset class="form-fieldset">
                        <legend class="form-legend">Assign to (one or more)</legend>
                        <p class="text-xs text-slate-500 mb-2">Each selected person receives assignment email and comment notifications.</p>
                        <div class="rounded-card border border-slate-200 bg-white p-3 max-h-48 overflow-y-auto space-y-2">
                            <label
                                v-for="u in users"
                                :key="u.id"
                                class="form-choice text-slate-800"
                            >
                                <input v-model="form.assigned_user_ids" type="checkbox" :value="Number(u.id)" class="form-checkbox" />
                                {{ u.name }}
                            </label>
                            <p v-if="!users.length" class="text-sm text-slate-500">No users loaded.</p>
                        </div>
                        <div
                            v-if="createCommentRecipientRows.length > 0"
                            class="mt-3 callout callout-info text-xs"
                        >
                            <div class="font-semibold mb-1">Comment emails will go to:</div>
                            <ul class="space-y-1 list-none">
                                <li v-for="row in createCommentRecipientRows" :key="row.email" class="break-all">
                                    <span class="font-medium">{{ row.name }}</span>
                                    <span> — {{ row.email }}</span>
                                </li>
                            </ul>
                            <p class="mt-2">The person who writes a comment does not get an email for their own comment.</p>
                        </div>
                        <div
                            v-else-if="(form.assigned_user_ids || []).length > 0"
                            class="mt-3 callout callout-warning text-xs"
                        >
                            Selected assignees have no email on file. Add addresses to user profiles or rely on Settings → admin notification email.
                        </div>
                    </fieldset>
                </div>

                <div class="form-actions">
                    <BaseButton to="/tickets" variant="outline" block-mobile>Cancel</BaseButton>
                    <BaseButton
                        type="submit"
                        variant="primary"
                        size="lg"
                        block-mobile
                        :loading="loading"
                        form="ticket-create-form"
                    >
                        {{ loading ? 'Creating…' : 'Create ticket' }}
                    </BaseButton>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useAutosizeTextarea } from '@/composables/useAutosizeTextarea';
import { useRouter } from 'vue-router';
import axios from 'axios';
import { ArrowLeftIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { BaseButton } from '@/components/base';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const toast = useToastStore();
const auth = useAuthStore();

const customers = ref([]);
const users = ref([]);
const loading = ref(false);
const error = ref(null);
const pendingFiles = ref([]);
const attachmentInputRef = ref(null);
const errorSummaryRef = ref(null);
const fieldErrors = ref({});

const form = ref({
    customer_id: '',
    customer_phone: '',
    subject: '',
    description: '',
    reference_url: '',
    priority: 'medium',
    estimated_resolve_hours: null,
    assigned_user_ids: [],
});

const { textareaRef: descriptionTextareaRef, syncHeight: syncDescriptionHeight } = useAutosizeTextarea(
    () => form.value.description,
);

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

const createCommentRecipientRows = computed(() => {
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
    pushUser(auth.user);
    return rows;
});

onMounted(async () => {
    if (!auth.initialized) auth.bootstrap();
    try {
        const [cr, ur] = await Promise.all([
            axios.get('/api/customers', { params: { per_page: 100 } }),
            axios.get('/api/users'),
        ]);
        customers.value = cr.data.data || [];
        users.value = Array.isArray(ur.data) ? ur.data : ur.data?.data || [];
    } catch (e) {
        console.error(e);
    }
});

function onFiles(e) {
    const picked = Array.from(e.target.files || []);
    if (picked.length) pendingFiles.value = [...pendingFiles.value, ...picked];
    e.target.value = '';
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

    loading.value = true;
    try {
        const payload = { ...form.value };
        if (!payload.customer_id) {
            delete payload.customer_id;
        }
        if (payload.reference_url === '') payload.reference_url = null;
        if (payload.estimated_resolve_hours === '' || payload.estimated_resolve_hours === undefined) {
            payload.estimated_resolve_hours = null;
        }

        if (pendingFiles.value.length > 0) {
            const fd = new FormData();
            Object.keys(payload).forEach((key) => {
                if (key === 'assigned_user_ids') return;
                const v = payload[key];
                if (v === null || v === undefined || v === '') return;
                fd.append(key, typeof v === 'number' ? String(v) : v);
            });
            (payload.assigned_user_ids || []).forEach((id) => fd.append('assigned_user_ids[]', String(id)));
            pendingFiles.value.forEach((f) => fd.append('attachments[]', f));
            await axios.post('/api/tickets', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
        } else {
            await axios.post('/api/tickets', payload);
        }
        toast.success('Ticket created');
        router.push('/tickets');
    } catch (err) {
        error.value = err.response?.data?.message || 'Failed to create ticket';
        await focusErrorSummary();
    } finally {
        loading.value = false;
    }
}
</script>
