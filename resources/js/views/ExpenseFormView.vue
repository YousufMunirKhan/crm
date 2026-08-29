<template>
    <div class="w-full min-w-0 overflow-x-hidden">
        <div class="page-narrow">
            <div v-if="loadError" class="callout callout-danger mb-6" role="alert">
                <p>{{ loadError }}</p>
                <div class="mt-3">
                    <BaseButton to="/expenses" variant="ghost" size="sm">
                        <template #icon>
                            <ArrowLeftIcon class="icon" aria-hidden="true" />
                        </template>
                        Back to expenses
                    </BaseButton>
                </div>
            </div>

            <template v-else>
                <div>
                    <BaseButton to="/expenses" variant="ghost" size="sm" class="mb-4">
                        <template #icon>
                            <ArrowLeftIcon class="icon" aria-hidden="true" />
                        </template>
                        Back to expenses
                    </BaseButton>
                    <p class="page-lead">
                        Record amount, category, and optional receipts. Status tracks whether the item is still open or settled.
                    </p>
                </div>

                <div v-if="pageLoading" class="form-card p-6 space-y-3" aria-busy="true">
                    <span class="sr-only">Loading expense…</span>
                    <div class="skeleton-text w-1/3"></div>
                    <div class="skeleton-text w-full"></div>
                    <div class="skeleton-text w-5/6"></div>
                    <div class="skeleton-text w-1/2"></div>
                </div>

                <form v-else id="expense-form" novalidate class="form-card !overflow-visible" @submit.prevent="handleSubmit">
                    <div class="form-section-head-mint">
                        <h2 class="form-section-title-mint text-xl">{{ isEdit ? 'Update expense' : 'New expense' }}</h2>
                        <p class="form-section-desc-mint">Fields marked * are required.</p>
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
                                {{ errorFields.length ? 'Please fix the following before saving:' : 'This expense could not be saved' }}
                            </p>
                            <ul v-if="errorFields.length" class="mt-1.5 list-disc pl-5 space-y-0.5">
                                <li v-for="failed in errorFields" :key="failed.field">
                                    <span class="font-medium">{{ failed.label }}</span> — {{ failed.message }}
                                </li>
                            </ul>
                            <p v-else class="mt-1">{{ error }}</p>
                        </div>

                        <div>
                            <label class="form-label" for="expenseformview-date">
                                Date<span class="form-required" aria-hidden="true">*</span>
                            </label>
                            <input
                                id="expenseformview-date"
                                v-model="form.date"
                                type="date"
                                required
                                class="form-input"
                                :aria-invalid="fieldErrors.date ? 'true' : undefined"
                                :aria-describedby="fieldErrors.date ? 'expenseformview-date-error' : undefined"
                            />
                            <p v-if="fieldErrors.date" id="expenseformview-date-error" class="form-error">{{ fieldErrors.date }}</p>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label" for="expenseformview-amount">
                                    Amount<span class="form-required" aria-hidden="true">*</span>
                                </label>
                                <input id="expenseformview-amount"
                                    v-model.number="form.amount"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    required
                                    class="form-input"
                                    :aria-invalid="fieldErrors.amount ? 'true' : undefined"
                                    :aria-describedby="fieldErrors.amount ? 'expenseformview-amount-error' : undefined"
                                />
                                <p v-if="fieldErrors.amount" id="expenseformview-amount-error" class="form-error">{{ fieldErrors.amount }}</p>
                            </div>
                            <div>
                                <label class="form-label" for="expenseformview-currency">
                                    Currency<span class="form-required" aria-hidden="true">*</span>
                                </label>
                                <select id="expenseformview-currency" v-model="form.currency" required class="form-select">
                                    <option value="GBP">GBP (£)</option>
                                    <option value="PKR">PKR (₨)</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label" for="expenseformview-reason">
                                Reason<span class="form-required" aria-hidden="true">*</span>
                            </label>
                            <input id="expenseformview-reason"
                                v-model="form.reason"
                                type="text"
                                required
                                class="form-input"
                                placeholder="e.g., Office supplies, travel"
                                :aria-invalid="fieldErrors.reason ? 'true' : undefined"
                                :aria-describedby="fieldErrors.reason ? 'expenseformview-reason-error' : undefined"
                            />
                            <p v-if="fieldErrors.reason" id="expenseformview-reason-error" class="form-error">{{ fieldErrors.reason }}</p>
                        </div>
                        <div>
                            <label class="form-label" for="expenseformview-category">
                                Category<span class="form-required" aria-hidden="true">*</span>
                            </label>
                            <select
                                id="expenseformview-category"
                                v-model="form.category"
                                required
                                class="form-select"
                                :aria-invalid="fieldErrors.category ? 'true' : undefined"
                                :aria-describedby="fieldErrors.category ? 'expenseformview-category-error' : undefined"
                            >
                                <option value="">Select category…</option>
                                <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
                            </select>
                            <p v-if="fieldErrors.category" id="expenseformview-category-error" class="form-error">{{ fieldErrors.category }}</p>
                        </div>
                        <div>
                            <label class="form-label" for="expenseformview-description">Description</label>
                            <textarea
                                id="expenseformview-description"
                                v-model="form.description"
                                rows="3"
                                class="form-input resize-none"
                                placeholder="Additional details…"
                            />
                        </div>
                        <div>
                            <label class="form-label" for="expenseformview-status">Status</label>
                            <select id="expenseformview-status" v-model="form.status" class="form-select">
                                <option value="open">Open (pending reimbursement / outstanding)</option>
                                <option value="closed">Closed (received / settled)</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" for="expenseformview-documents">Documents</label>
                            <p class="text-xs text-slate-500 mb-2">
                                Upload receipts or invoices (PDF, images, Office, CSV — max 20 MB each).
                            </p>
                            <input
                                id="expenseformview-documents"
                                ref="attachmentInputRef"
                                type="file"
                                multiple
                                accept=".pdf,.jpg,.jpeg,.png,.gif,.webp,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                class="form-file"
                                @change="onAttachmentFilesSelected"
                            />
                            <ul v-if="pendingFiles.length" class="mt-2 text-xs text-slate-600 space-y-1">
                                <li v-for="(f, i) in pendingFiles" :key="i" class="flex items-center justify-between gap-2">
                                    <span class="truncate">{{ f.name }}</span>
                                    <BaseButton
                                        variant="ghost"
                                        size="sm"
                                        class="shrink-0"
                                        :label="`Remove ${f.name}`"
                                        @click="removePendingFile(i)"
                                    >
                                        <template #icon>
                                            <TrashIcon class="icon-sm" aria-hidden="true" />
                                        </template>
                                        Remove
                                    </BaseButton>
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
                                            class="link truncate"
                                        >
                                            {{ att.original_name }}
                                        </a>
                                        <BaseButton
                                            variant="ghost"
                                            size="sm"
                                            class="shrink-0"
                                            :label="`Remove ${att.original_name}`"
                                            @click="requestDeleteAttachment(att)"
                                        >
                                            <template #icon>
                                                <TrashIcon class="icon-sm" aria-hidden="true" />
                                            </template>
                                            Remove
                                        </BaseButton>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <BaseButton to="/expenses" variant="outline" block-mobile>Cancel</BaseButton>
                        <BaseButton
                            type="submit"
                            variant="primary"
                            size="lg"
                            block-mobile
                            :loading="saving"
                            form="expense-form"
                        >
                            {{ saving ? 'Saving…' : isEdit ? 'Update expense' : 'Create expense' }}
                        </BaseButton>
                    </div>
                </form>
            </template>
        </div>

        <ConfirmDialog
            v-model="confirmDeleteOpen"
            title="Remove this file?"
            :message="attachmentToDelete ? `“${attachmentToDelete.original_name}” will be deleted from this expense.` : 'Remove this file?'"
            confirm-label="Remove file"
            cancel-label="Keep file"
            tone="danger"
            :loading="deletingAttachment"
            @confirm="confirmDeleteAttachment"
            @cancel="cancelDeleteAttachment"
        />
    </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { ArrowLeftIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { BaseButton, ConfirmDialog } from '@/components/base';
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
const errorSummaryRef = ref(null);
const fieldErrors = ref({});
const confirmDeleteOpen = ref(false);
const attachmentToDelete = ref(null);
const deletingAttachment = ref(false);

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

const FIELD_LABELS = {
    date: 'Date',
    amount: 'Amount',
    reason: 'Reason',
    category: 'Category',
};

const errorFields = computed(() =>
    Object.keys(fieldErrors.value).map((field) => ({
        field,
        label: FIELD_LABELS[field] || field,
        message: fieldErrors.value[field],
    })),
);

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

function requestDeleteAttachment(att) {
    attachmentToDelete.value = att;
    confirmDeleteOpen.value = true;
}

function cancelDeleteAttachment() {
    if (deletingAttachment.value) return;
    confirmDeleteOpen.value = false;
    attachmentToDelete.value = null;
}

async function confirmDeleteAttachment() {
    const att = attachmentToDelete.value;
    if (!isEdit.value || !expenseId.value || !att) return;
    deletingAttachment.value = true;
    try {
        await axios.delete(`/api/hr/expenses/${expenseId.value}/attachments/${att.id}`);
        existingAttachments.value = existingAttachments.value.filter((a) => a.id !== att.id);
        toast.success('File removed');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Could not remove file');
    } finally {
        deletingAttachment.value = false;
        confirmDeleteOpen.value = false;
        attachmentToDelete.value = null;
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

/** Client-side checks only — the payload itself is unchanged. */
function validate() {
    const errs = {};
    if (!String(form.value.date ?? '').trim()) {
        errs.date = 'Choose the date this expense was incurred.';
    }
    const amount = form.value.amount;
    if (amount === '' || amount === null || amount === undefined) {
        errs.amount = 'Enter the expense amount.';
    } else if (!Number.isFinite(Number(amount)) || Number(amount) < 0) {
        errs.amount = 'Enter an amount of 0 or more.';
    }
    if (!String(form.value.reason ?? '').trim()) {
        errs.reason = 'Say what this expense was for.';
    }
    if (!String(form.value.category ?? '').trim()) {
        errs.category = 'Choose a category.';
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
        await focusErrorSummary();
    } finally {
        saving.value = false;
    }
}

watch(
    () => [route.name, route.params.id],
    () => {
        error.value = null;
        fieldErrors.value = {};
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
