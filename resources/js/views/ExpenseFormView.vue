<template>
    <div class="min-h-screen bg-slate-50 w-full min-w-0 overflow-x-hidden">
        <div class="max-w-3xl mx-auto px-3 sm:px-6 py-6 lg:py-8 w-full min-w-0">
            <div v-if="loadError" class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800 text-sm mb-6">
                {{ loadError }}
                <router-link to="/expenses" class="block mt-2 font-medium text-red-900 underline">Back to expenses</router-link>
            </div>

            <template v-else>
                <div class="mb-6">
                    <router-link
                        to="/expenses"
                        class="inline-flex items-center gap-2 text-slate-600 hover:text-slate-900 text-sm mb-4"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to expenses
                    </router-link>
                    <h1 class="text-2xl font-bold text-slate-900">{{ isEdit ? 'Edit expense' : 'Add expense' }}</h1>
                    <p class="text-slate-500 mt-1 text-sm">
                        Record amount, category, and optional receipts. Status tracks whether the item is still open or settled.
                    </p>
                </div>

                <div v-if="pageLoading" class="text-center py-12 text-slate-500 text-sm">Loading…</div>

                <form v-else class="form-card !overflow-visible" @submit.prevent="handleSubmit">
                    <div class="form-section-head-mint">
                        <h2 class="form-section-title-mint text-xl">{{ isEdit ? 'Update expense' : 'New expense' }}</h2>
                        <p class="form-section-desc-mint">Fields marked * are required.</p>
                    </div>

                    <div class="form-body space-y-4">
                        <div>
                            <label class="form-label">Date *</label>
                            <input v-model="form.date" type="date" required class="form-input" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Amount *</label>
                                <input
                                    v-model.number="form.amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label">Currency *</label>
                                <select v-model="form.currency" required class="form-input">
                                    <option value="GBP">GBP (£)</option>
                                    <option value="PKR">PKR (₨)</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Reason *</label>
                            <input
                                v-model="form.reason"
                                type="text"
                                required
                                class="form-input"
                                placeholder="e.g., Office supplies, travel"
                            />
                        </div>
                        <div>
                            <label class="form-label">Category *</label>
                            <select v-model="form.category" required class="form-input">
                                <option value="">Select category…</option>
                                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Description</label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="form-input resize-none"
                                placeholder="Additional details…"
                            />
                        </div>
                        <div>
                            <label class="form-label">Status</label>
                            <select v-model="form.status" class="form-input">
                                <option value="open">Open (pending reimbursement / outstanding)</option>
                                <option value="closed">Closed (received / settled)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Documents</label>
                            <p class="text-xs text-slate-500 mb-2">
                                Upload receipts or invoices (PDF, images, Office, CSV — max 20 MB each).
                            </p>
                            <input
                                ref="attachmentInputRef"
                                type="file"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100"
                                @change="onAttachmentFilesSelected"
                            />
                            <ul v-if="pendingFiles.length" class="mt-2 text-xs text-slate-600 space-y-1">
                                <li v-for="(f, i) in pendingFiles" :key="i" class="flex justify-between gap-2">
                                    <span class="truncate">{{ f.name }}</span>
                                    <button type="button" class="text-red-600 shrink-0" @click="removePendingFile(i)">Remove</button>
                                </li>
                            </ul>
                            <div v-if="existingAttachments.length" class="mt-4 pt-4 border-t border-slate-200">
                                <p class="text-xs font-medium text-slate-700 mb-2">Attached files</p>
                                <ul class="space-y-2">
                                    <li
                                        v-for="att in existingAttachments"
                                        :key="att.id"
                                        class="flex items-center justify-between gap-2 text-sm"
                                    >
                                        <a
                                            :href="att.url"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="text-violet-600 hover:underline truncate"
                                        >
                                            {{ att.original_name }}
                                        </a>
                                        <button type="button" class="text-xs text-red-600 shrink-0" @click="deleteAttachment(att)">
                                            Remove
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded-xl border border-red-100">
                            {{ error }}
                        </div>
                    </div>

                    <div class="form-actions">
                        <router-link to="/expenses" class="form-btn-cancel text-center">Cancel</router-link>
                        <button type="submit" :disabled="saving" class="form-btn-submit">
                            {{ saving ? 'Saving…' : isEdit ? 'Update expense' : 'Create expense' }}
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const expenseId = computed(() => (route.params.id ? String(route.params.id) : null));
const isEdit = computed(() => route.name === 'expense-edit' && expenseId.value);

const pageLoading = ref(false);
const loadError = ref(null);
const saving = ref(false);
const error = ref(null);
const pendingFiles = ref([]);
const attachmentInputRef = ref(null);
const existingAttachments = ref([]);

const categories = [
    'Office',
    'Travel',
    'Marketing',
    'Utilities',
    'Equipment',
    'Software',
    'Training',
    'Other',
];

function defaultForm() {
    return {
        date: new Date().toISOString().split('T')[0],
        amount: 0,
        currency: 'GBP',
        reason: '',
        description: '',
        category: '',
        status: 'open',
    };
}

const form = ref(defaultForm());

function onAttachmentFilesSelected(event) {
    const picked = Array.from(event.target.files || []);
    if (picked.length) pendingFiles.value = [...pendingFiles.value, ...picked];
    event.target.value = '';
}

function removePendingFile(index) {
    pendingFiles.value.splice(index, 1);
}

async function deleteAttachment(att) {
    if (!isEdit.value || !expenseId.value) return;
    try {
        await axios.delete(`/api/hr/expenses/${expenseId.value}/attachments/${att.id}`);
        existingAttachments.value = existingAttachments.value.filter((a) => a.id !== att.id);
        toast.success('File removed');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Could not remove file');
    }
}

async function loadExpense() {
    if (!isEdit.value || !expenseId.value) return;
    pendingFiles.value = [];
    if (attachmentInputRef.value) attachmentInputRef.value.value = '';
    pageLoading.value = true;
    loadError.value = null;
    try {
        const { data } = await axios.get(`/api/hr/expenses/${expenseId.value}`);
        form.value = {
            date: data.date,
            amount: parseFloat(data.amount),
            currency: data.currency || 'GBP',
            reason: data.reason,
            description: data.description || '',
            category: data.category || '',
            status: data.status || 'open',
        };
        existingAttachments.value = Array.isArray(data.attachments) ? [...data.attachments] : [];
    } catch (err) {
        if (err.response?.status === 404) {
            loadError.value = 'Expense not found.';
        } else if (err.response?.status === 403) {
            loadError.value = 'You do not have permission to view this expense.';
        } else {
            loadError.value = err.response?.data?.message || 'Failed to load expense.';
        }
    } finally {
        pageLoading.value = false;
    }
}

async function handleSubmit() {
    saving.value = true;
    error.value = null;
    const payload = { ...form.value };

    try {
        if (isEdit.value && expenseId.value) {
            await axios.put(`/api/hr/expenses/${expenseId.value}`, payload);
            if (pendingFiles.value.length > 0) {
                const fd = new FormData();
                pendingFiles.value.forEach((f) => fd.append('attachments[]', f));
                const { data } = await axios.post(`/api/hr/expenses/${expenseId.value}/attachments`, fd, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                });
                existingAttachments.value = data.attachments || existingAttachments.value;
                pendingFiles.value = [];
                if (attachmentInputRef.value) attachmentInputRef.value.value = '';
            }
        } else if (pendingFiles.value.length > 0) {
            const fd = new FormData();
            Object.entries(payload).forEach(([key, val]) => {
                if (val === null || val === undefined) return;
                fd.append(key, typeof val === 'number' ? String(val) : val);
            });
            pendingFiles.value.forEach((f) => fd.append('attachments[]', f));
            await axios.post('/api/hr/expenses', fd, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
        } else {
            await axios.post('/api/hr/expenses', payload);
        }
        toast.success('Expense saved successfully');
        router.push('/expenses');
    } catch (err) {
        if (err.response?.data?.errors) {
            error.value = Object.values(err.response.data.errors).flat().join(', ');
        } else if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else {
            error.value = 'Failed to save expense. Please try again.';
        }
    } finally {
        saving.value = false;
    }
}

watch(
    () => [route.name, route.params.id],
    () => {
        error.value = null;
        if (route.name === 'expense-edit' && route.params.id) {
            loadExpense();
            return;
        }
        if (route.name === 'expense-create') {
            loadError.value = null;
            pageLoading.value = false;
            form.value = defaultForm();
            existingAttachments.value = [];
            pendingFiles.value = [];
            if (attachmentInputRef.value) attachmentInputRef.value.value = '';
        }
    },
    { immediate: true },
);
</script>
