<template>
    <div class="min-h-screen bg-slate-50 w-full min-w-0 overflow-x-hidden">
        <div class="max-w-3xl mx-auto px-3 sm:px-6 py-6 lg:py-8 w-full min-w-0">
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
                <h1 class="text-2xl font-bold text-slate-900">Create Ticket</h1>
                <p class="text-slate-500 mt-1 text-sm">Assign one or more team members — everyone on the list is notified by email on new comments.</p>
            </div>

            <form class="form-card !overflow-visible" @submit.prevent="handleSubmit">
                <div class="form-section-head-mint">
                    <h2 class="form-section-title-mint text-xl">New support ticket</h2>
                    <p class="form-section-desc-mint">Describe the issue and choose who should work on it.</p>
                </div>

                <div class="form-body space-y-4">
                    <div>
                        <label class="form-label">Customer</label>
                        <select v-model="form.customer_id" class="form-input">
                            <option value="">Select customer (optional)</option>
                            <option v-for="c in customers" :key="c.id" :value="c.id">
                                {{ c.name }} — {{ c.phone }}
                            </option>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Or enter a phone number below if not in the list.</p>
                    </div>

                    <div v-if="!form.customer_id">
                        <label class="form-label">Customer phone</label>
                        <input v-model="form.customer_phone" type="text" class="form-input" placeholder="e.g. 07700900123" />
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

                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
                        <p class="font-medium text-amber-900">Large files</p>
                        <p class="mt-1 text-amber-900/90">
                            Prefer uploading to Google Drive or Sheets and paste the link in <strong>Reference link</strong> below.
                        </p>
                    </div>

                    <div>
                        <label class="form-label">Reference link (Drive / Sheet)</label>
                        <input v-model="form.reference_url" type="url" class="form-input" placeholder="https://..." />
                    </div>

                    <div>
                        <label class="form-label">Attachments</label>
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
                            placeholder="Leave empty for priority-based SLA"
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
                    </div>

                    <div>
                        <span class="form-label">Assign to (one or more)</span>
                        <p class="text-xs text-slate-500 mb-2">Each selected person receives assignment email and comment notifications.</p>
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
                            v-if="createCommentRecipientRows.length > 0"
                            class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-800"
                        >
                            <div class="font-semibold text-slate-900 mb-1">Comment emails will go to:</div>
                            <ul class="space-y-1 list-none">
                                <li v-for="row in createCommentRecipientRows" :key="row.email" class="break-all">
                                    <span class="font-medium">{{ row.name }}</span>
                                    <span class="text-slate-600"> — {{ row.email }}</span>
                                </li>
                            </ul>
                            <p class="mt-2 text-slate-600">The person who writes a comment does not get an email for their own comment.</p>
                        </div>
                        <div
                            v-else-if="(form.assigned_user_ids || []).length > 0"
                            class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-950"
                        >
                            Selected assignees have no email on file. Add addresses to user profiles or rely on Settings → admin notification email.
                        </div>
                    </div>

                    <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded-xl border border-red-100">
                        {{ error }}
                    </div>
                </div>

                <div class="form-actions">
                    <router-link to="/tickets" class="form-btn-cancel text-center">Cancel</router-link>
                    <button type="submit" :disabled="loading" class="form-btn-submit">
                        {{ loading ? 'Creating…' : 'Create ticket' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useAutosizeTextarea } from '@/composables/useAutosizeTextarea';
import { useRouter } from 'vue-router';
import axios from 'axios';
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

async function handleSubmit() {
    loading.value = true;
    error.value = null;
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
    } finally {
        loading.value = false;
    }
}
</script>
