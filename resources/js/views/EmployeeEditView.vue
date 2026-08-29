<template>
    <div class="min-h-screen bg-slate-50 w-full min-w-0">
        <div class="page-narrow">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 min-w-0">
                <div class="flex items-center gap-2 min-w-0">
                    <BaseButton
                        variant="ghost"
                        size="icon"
                        label="Go back"
                        class="shrink-0"
                        @click="$router.back()"
                    >
                        <template #icon>
                            <ArrowLeftIcon class="icon" aria-hidden="true" />
                        </template>
                    </BaseButton>
                    <h2 class="text-page-title text-slate-900">
                        {{ isSelfHrOnly ? 'Bank details & documents' : 'Edit Employee' }}
                    </h2>
                </div>
            </div>

            <div v-if="loading" class="form-card p-6 space-y-3" aria-busy="true">
                <span class="sr-only">Loading employee…</span>
                <div class="skeleton-text w-1/3"></div>
                <div class="skeleton-text w-full"></div>
                <div class="skeleton-text w-5/6"></div>
                <div class="skeleton-text w-2/3"></div>
            </div>

            <form
                v-else
                id="employee-edit-form"
                novalidate
                class="form-card min-w-0"
                @submit.prevent="handleSubmit"
            >
                <div class="form-section-head-mint">
                    <h2 class="form-section-title-mint text-lg lg:text-xl">
                        {{ isSelfHrOnly ? 'Your HR profile' : 'Employee record' }}
                    </h2>
                    <p class="form-section-desc-mint">
                        {{ isSelfHrOnly ? 'Update bank details and attachments below.' : 'Personal, job, and payroll-related fields in one place.' }}
                    </p>
                </div>

                <div class="form-body space-y-8">
                    <!-- Validation summary: focused on a failed submit -->
                    <div
                        v-if="error || errorFields.length"
                        ref="errorSummaryRef"
                        class="callout callout-danger focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-600/40"
                        role="alert"
                        tabindex="-1"
                    >
                        <p class="font-semibold">
                            {{ errorFields.length ? 'Please fix the following before saving:' : 'This employee could not be saved' }}
                        </p>
                        <ul v-if="errorFields.length" class="mt-1.5 list-disc pl-5 space-y-0.5">
                            <li v-for="failed in errorFields" :key="failed.field">
                                <span class="font-medium">{{ failed.label }}</span> — {{ failed.message }}
                            </li>
                        </ul>
                        <p v-else class="mt-1">{{ error }}</p>
                    </div>

                    <div v-if="isSelfHrOnly" class="callout callout-info">
                        <p class="font-semibold">Signed in as {{ form.name || '—' }}</p>
                        <p>{{ form.email }}</p>
                        <p class="text-xs mt-2">
                            Update your bank details and upload HR documents here. For name, role, or other changes, contact HR.
                        </p>
                    </div>

                    <!-- Personal details -->
                    <div v-if="!isSelfHrOnly" class="space-y-4">
                        <h2 class="form-section-title text-base">Personal details</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label" for="employeeeditview-full-name">
                                    Full name<span class="form-required" aria-hidden="true">*</span>
                                </label>
                                <input id="employeeeditview-full-name"
                                    v-model="form.name"
                                    type="text"
                                    required
                                    class="form-input"
                                    :aria-invalid="fieldErrors.name ? 'true' : undefined"
                                    :aria-describedby="fieldErrors.name ? 'employeeeditview-full-name-error' : undefined"
                                />
                                <p v-if="fieldErrors.name" id="employeeeditview-full-name-error" class="form-error">
                                    {{ fieldErrors.name }}
                                </p>
                            </div>
                            <div>
                                <label class="form-label" for="employeeeditview-email">
                                    Email<span class="form-required" aria-hidden="true">*</span>
                                </label>
                                <input id="employeeeditview-email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    class="form-input"
                                    :aria-invalid="fieldErrors.email ? 'true' : undefined"
                                    :aria-describedby="fieldErrors.email ? 'employeeeditview-email-error' : undefined"
                                />
                                <p v-if="fieldErrors.email" id="employeeeditview-email-error" class="form-error">
                                    {{ fieldErrors.email }}
                                </p>
                            </div>
                            <div>
                                <label class="form-label" for="employeeeditview-phone">Phone</label>
                                <input id="employeeeditview-phone"
                                    v-model="form.phone"
                                    type="text"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label" for="employeeeditview-date-of-birth">Date of birth</label>
                                <input id="employeeeditview-date-of-birth"
                                    v-model="form.date_of_birth"
                                    type="date"
                                    class="form-input"
                                />
                            </div>
                        </div>
                        <div>
                            <label class="form-label" for="employeeeditview-address">Address</label>
                            <textarea id="employeeeditview-address"
                                v-model="form.address"
                                rows="2"
                                class="form-input resize-none"
                            />
                        </div>
                    </div>

                    <!-- Job & access -->
                    <div v-if="!isSelfHrOnly" class="space-y-4 border-t border-slate-100 pt-6">
                        <h2 class="form-section-title text-base">Job &amp; access</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label" for="employeeeditview-role">
                                    Role<span class="form-required" aria-hidden="true">*</span>
                                </label>
                                <select id="employeeeditview-role"
                                    v-model="form.role_id"
                                    required
                                    class="form-select"
                                    :aria-invalid="fieldErrors.role_id ? 'true' : undefined"
                                    :aria-describedby="fieldErrors.role_id ? 'employeeeditview-role-error' : undefined"
                                >
                                    <option value="">Select role</option>
                                    <option v-for="role in roles" :key="role.id" :value="role.id">
                                        {{ role.name }}
                                    </option>
                                </select>
                                <p v-if="fieldErrors.role_id" id="employeeeditview-role-error" class="form-error">
                                    {{ fieldErrors.role_id }}
                                </p>
                            </div>
                            <div>
                                <label class="form-label" for="employeeeditview-employee-type">Employee type</label>
                                <select
                                    id="employeeeditview-employee-type"
                                    v-model="form.employee_type"
                                    class="form-select"
                                >
                                    <option value="">Select type</option>
                                    <option value="field_worker">Field Worker</option>
                                    <option value="call_center">Call Center</option>
                                    <option value="ticket_manager">Ticket Manager</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="employeeeditview-hire-date">Hire date</label>
                                <input id="employeeeditview-hire-date"
                                    v-model="form.hire_date"
                                    type="date"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label" for="employeeeditview-status">Status</label>
                                <select id="employeeeditview-status"
                                    v-model="form.is_active"
                                    class="form-select"
                                >
                                    <option :value="true">Active</option>
                                    <option :value="false">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div v-if="canEditNavPerms" class="space-y-4 border border-slate-200 rounded-card p-4 bg-slate-50/80">
                        <h2 class="form-section-title text-base">Sidebar menu access</h2>
                        <p class="text-xs text-slate-600">
                            Leave this off for default role menu. Turn on to limit this user to selected sections.
                        </p>
                        <label class="form-choice text-slate-800">
                            <input
                                v-model="restrictMenu"
                                type="checkbox"
                                class="form-checkbox"
                            />
                            Limit sidebar to selected sections only
                        </label>
                        <fieldset
                            v-if="restrictMenu"
                            class="form-fieldset"
                        >
                            <legend class="form-legend">Visible sections</legend>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 max-h-56 overflow-y-auto border border-slate-200 rounded-card p-3 bg-white">
                                <label
                                    v-for="opt in NAV_SECTION_OPTIONS"
                                    v-show="opt.key !== 'dashboard'"
                                    :key="opt.key"
                                    class="form-choice text-slate-700"
                                >
                                    <input
                                        v-model="sectionChecks[opt.key]"
                                        type="checkbox"
                                        class="form-checkbox"
                                    />
                                    {{ opt.label }}
                                </label>
                            </div>
                        </fieldset>
                    </div>

                    <!-- Bank details -->
                    <div class="space-y-4 border-t border-slate-100 pt-6">
                        <h2 class="form-section-title text-base">Bank details</h2>
                        <p class="text-xs text-slate-500">
                            These details are used for HR and salary purposes only.
                        </p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label" for="employeeeditview-account-holder-name">Account holder name</label>
                                <input id="employeeeditview-account-holder-name"
                                    v-model="form.bank_account_name"
                                    type="text"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label" for="employeeeditview-bank-name">Bank name</label>
                                <input id="employeeeditview-bank-name"
                                    v-model="form.bank_name"
                                    type="text"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label" for="employeeeditview-sort-code">Sort code</label>
                                <input id="employeeeditview-sort-code"
                                    v-model="form.bank_sort_code"
                                    type="text"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label" for="employeeeditview-account-number">Account number</label>
                                <input id="employeeeditview-account-number"
                                    v-model="form.bank_account_number"
                                    type="text"
                                    class="form-input"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Documents: existing + new attachments -->
                    <div class="space-y-4 border-t border-slate-100 pt-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <h2 class="form-section-title text-base">Attachments</h2>
                            <span class="text-xs text-slate-500">Existing files plus new ones you add here.</span>
                        </div>

                        <!-- Existing documents -->
                        <div v-if="documents.length" class="space-y-1 text-xs text-slate-700 border border-slate-200 rounded-card p-3">
                            <div class="font-semibold text-slate-800 mb-1">Current files</div>
                            <div
                                v-for="doc in documents"
                                :key="doc.id"
                                class="flex items-center justify-between gap-2"
                            >
                                <div class="min-w-0">
                                    <span class="font-medium text-slate-900">{{ doc.name }}</span>
                                    <span class="text-slate-500 ml-1">({{ formatDate(doc.created_at) }})</span>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <a
                                        :href="doc.file_path"
                                        target="_blank"
                                        rel="noopener"
                                        class="link"
                                    >
                                        View
                                    </a>
                                    <BaseButton
                                        v-if="canManageDocuments"
                                        variant="ghost"
                                        size="sm"
                                        :label="`Remove ${doc.name}`"
                                        :loading="removingDocId === doc.id"
                                        @click="requestRemoveDocument(doc)"
                                    >
                                        <template #icon>
                                            <TrashIcon class="icon-sm" aria-hidden="true" />
                                        </template>
                                        {{ removingDocId === doc.id ? 'Removing…' : 'Remove' }}
                                    </BaseButton>
                                </div>
                            </div>
                        </div>

                        <!-- Add new attachments -->
                        <div class="grid grid-cols-1 md:grid-cols-[2fr,2fr,auto] gap-3 items-end">
                            <div>
                                <label class="form-label" for="employeeeditview-attachment-name">Attachment name</label>
                                <input
                                    id="employeeeditview-attachment-name"
                                    v-model="newDocName"
                                    type="text"
                                    placeholder="Attachment name"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <span class="form-label">Files</span>
                                <label
                                    class="inline-flex items-center gap-2 px-3 py-2 min-h-[42px] border border-dashed border-slate-300 rounded-control text-xs text-slate-600 cursor-pointer hover:bg-slate-50 focus-within:outline-none focus-within:ring-2 focus-within:ring-primary-500/40"
                                >
                                    <ArrowUpTrayIcon class="icon-sm text-slate-500" aria-hidden="true" />
                                    <span>Browse files</span>
                                    <input
                                        id="employeeeditview-attachment-files"
                                        ref="fileInput"
                                        type="file"
                                        multiple
                                        class="sr-only"
                                    />
                                </label>
                            </div>
                            <BaseButton variant="soft" block-mobile @click="queueDocument">
                                <template #icon>
                                    <PlusIcon class="icon" aria-hidden="true" />
                                </template>
                                Add
                            </BaseButton>
                        </div>
                        <div v-if="queuedDocuments.length" class="space-y-1 text-xs text-slate-700">
                            <div
                                v-for="(doc, index) in queuedDocuments"
                                :key="index"
                                class="flex items-center justify-between gap-2"
                            >
                                <span class="truncate">{{ doc.name }} ({{ doc.file?.name }})</span>
                                <BaseButton
                                    variant="ghost"
                                    size="sm"
                                    class="shrink-0"
                                    :label="`Remove ${doc.name}`"
                                    @click="removeQueuedDocument(index)"
                                >
                                    <template #icon>
                                        <TrashIcon class="icon-sm" aria-hidden="true" />
                                    </template>
                                    Remove
                                </BaseButton>
                            </div>
                        </div>
                    </div>

                    <!-- Password -->
                    <div v-if="!isSelfHrOnly" class="space-y-2 border-t border-slate-100 pt-6">
                        <label class="form-label" for="employeeeditview-password-leave-blank-to-keep-current">Password (leave blank to keep current)</label>
                        <input id="employeeeditview-password-leave-blank-to-keep-current"
                            v-model="form.password"
                            type="password"
                            class="form-input max-w-md"
                        />
                    </div>
                </div>

                <div class="form-actions">
                    <BaseButton variant="outline" block-mobile @click="$router.back()">
                        Cancel
                    </BaseButton>
                    <BaseButton
                        type="submit"
                        variant="primary"
                        size="lg"
                        block-mobile
                        :loading="saving"
                        form="employee-edit-form"
                    >
                        {{ saving ? 'Saving...' : 'Save changes' }}
                    </BaseButton>
                </div>
            </form>
        </div>

        <ConfirmDialog
            v-model="confirmRemoveDocOpen"
            title="Remove this document?"
            :message="documentToRemove ? `“${documentToRemove.name}” will be permanently removed from this employee.` : 'Remove this document?'"
            confirm-label="Remove document"
            cancel-label="Keep document"
            tone="danger"
            :loading="removingDocId !== null"
            @confirm="confirmRemoveDocument"
            @cancel="cancelRemoveDocument"
        />
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import { ArrowLeftIcon, ArrowUpTrayIcon, PlusIcon, TrashIcon } from '@heroicons/vue/24/outline';
import { BaseButton, ConfirmDialog } from '@/components/base';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';
import { NAV_SECTION_OPTIONS } from '@/constants/navSections';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const auth = useAuthStore();

const loading = ref(true);
const saving = ref(false);
const roles = ref([]);
const form = ref({
    name: '',
    email: '',
    password: '',
    role_id: '',
    employee_type: '',
    phone: '',
    address: '',
    hire_date: '',
    date_of_birth: '',
    is_active: true,
    bank_account_name: '',
    bank_name: '',
    bank_sort_code: '',
    bank_account_number: '',
});

const queuedDocuments = ref([]);
const newDocName = ref('');
const fileInput = ref(null);
const documents = ref([]);
const restrictMenu = ref(false);
const removingDocId = ref(null);
const error = ref(null);
const errorSummaryRef = ref(null);
const fieldErrors = ref({});
const confirmRemoveDocOpen = ref(false);
const documentToRemove = ref(null);

const FIELD_LABELS = {
    name: 'Full name',
    email: 'Email',
    role_id: 'Role',
};

const errorFields = computed(() =>
    Object.keys(fieldErrors.value).map((field) => ({
        field,
        label: FIELD_LABELS[field] || field,
        message: fieldErrors.value[field],
    })),
);

const isElevatedRole = computed(() => {
    const r = auth.user?.role?.name;
    return r === 'Admin' || r === 'Manager' || r === 'System Admin';
});

const isSelf = computed(() => {
    if (!auth.user?.id || route.params.id === undefined || route.params.id === '') return false;
    return Number(route.params.id) === Number(auth.user.id);
});

/** Non-admin viewing own record: bank + documents only (server enforces the same). */
const isSelfHrOnly = computed(() => isSelf.value && !isElevatedRole.value);

const canManageDocuments = computed(() => isElevatedRole.value || isSelf.value);

function defaultSectionChecks() {
    const out = {};
    for (const { key } of NAV_SECTION_OPTIONS) out[key] = true;
    return out;
}
const sectionChecks = ref(defaultSectionChecks());

const canEditNavPerms = computed(() => {
    const r = auth.user?.role?.name;
    return r === 'Admin' || r === 'System Admin';
});

const loadRoles = async () => {
    try {
        const { data } = await axios.get('/api/roles');
        roles.value = data;
    } catch (e) {
        console.error('Failed to load roles', e);
    }
};

const loadEmployee = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/users/${route.params.id}`);
        form.value = {
            name: data.name || '',
            email: data.email || '',
            password: '',
            role_id: data.role_id || '',
            employee_type: data.employee_type || '',
            phone: data.phone || '',
            address: data.address || '',
            hire_date: data.hire_date || '',
            date_of_birth: data.date_of_birth || '',
            is_active: data.is_active ?? true,
            bank_account_name: data.bank_account_name || '',
            bank_name: data.bank_name || '',
            bank_sort_code: data.bank_sort_code || '',
            bank_account_number: data.bank_account_number || '',
        };
        const np = data.nav_permissions;
        if (np && typeof np === 'object' && Object.keys(np).length > 0) {
            restrictMenu.value = true;
            const checks = defaultSectionChecks();
            for (const key of Object.keys(checks)) checks[key] = !!np[key];
            sectionChecks.value = checks;
        } else {
            restrictMenu.value = false;
            sectionChecks.value = defaultSectionChecks();
        }

        // Load existing documents for this employee
        try {
            const docsRes = await axios.get(`/api/hr/employees/${route.params.id}/documents`);
            documents.value = docsRes.data || [];
        } catch (e) {
            console.error('Failed to load employee documents', e);
        }
    } catch (e) {
        console.error('Failed to load employee', e);
        toast.error('Failed to load employee');
    } finally {
        loading.value = false;
    }
};

const formatDate = (date) => {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
};

/** Client-side checks only — the payload itself is unchanged. */
function validate() {
    const errs = {};
    if (!isSelfHrOnly.value) {
        if (!String(form.value.name ?? '').trim()) {
            errs.name = 'Enter the employee’s full name.';
        }
        const email = String(form.value.email ?? '').trim();
        if (!email) {
            errs.email = 'Enter an email address.';
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errs.email = 'Enter a valid email address, for example name@example.com.';
        }
        if (form.value.role_id === '' || form.value.role_id === null || form.value.role_id === undefined) {
            errs.role_id = 'Choose a role for this employee.';
        }
    }
    fieldErrors.value = errs;
    return Object.keys(errs).length === 0;
}

async function focusErrorSummary() {
    await nextTick();
    errorSummaryRef.value?.focus();
}

const handleSubmit = async () => {
    error.value = null;
    if (!validate()) {
        await focusErrorSummary();
        return;
    }

    saving.value = true;
    try {
        let payload;
        if (isSelfHrOnly.value) {
            payload = {
                bank_account_name: form.value.bank_account_name || null,
                bank_name: form.value.bank_name || null,
                bank_sort_code: form.value.bank_sort_code || null,
                bank_account_number: form.value.bank_account_number || null,
            };
        } else {
            payload = { ...form.value };
            if (!payload.password) {
                delete payload.password;
            }
            if (!payload.employee_type) payload.employee_type = null;
            if (!payload.phone) payload.phone = null;
            if (!payload.address) payload.address = null;
            if (!payload.hire_date) payload.hire_date = null;
            if (!payload.date_of_birth) payload.date_of_birth = null;
            if (canEditNavPerms.value) {
                if (restrictMenu.value) {
                    const nav_permissions = {};
                    for (const { key } of NAV_SECTION_OPTIONS) {
                        nav_permissions[key] = !!sectionChecks.value[key];
                    }
                    payload.nav_permissions = nav_permissions;
                } else {
                    payload.nav_permissions = null;
                }
            }
        }

        await axios.put(`/api/users/${route.params.id}`, payload);
        if (route.params.id == auth.user?.id) {
            await auth.bootstrap();
        }

        // Upload any queued documents
        if (queuedDocuments.value.length) {
            for (const doc of queuedDocuments.value) {
                const fd = new FormData();
                fd.append('name', doc.name);
                fd.append('file', doc.file);
                try {
                    await axios.post(`/api/hr/employees/${route.params.id}/documents`, fd, {
                        headers: { 'Content-Type': 'multipart/form-data' },
                    });
                } catch (e) {
                    console.error('Failed to upload document', e);
                }
            }
        }

        toast.success(isSelfHrOnly.value ? 'Saved.' : 'Employee updated.');
        if (isSelfHrOnly.value) {
            const role = auth.user?.role?.name;
            router.push({
                name: role === 'Sales' || role === 'CallAgent' ? 'sales-dashboard' : 'dashboard',
            });
        } else {
            router.push('/employees');
        }
    } catch (e) {
        console.error('Failed to save employee', e);
        let msg = 'Failed to save employee';
        if (e.response?.data?.message) msg = e.response.data.message;
        if (e.response?.data?.errors) {
            const errs = Object.values(e.response.data.errors).flat();
            msg = errs.join(', ');
        }
        error.value = msg;
        toast.error(msg);
        await focusErrorSummary();
    } finally {
        saving.value = false;
    }
};

const requestRemoveDocument = (doc) => {
    if (!canManageDocuments.value) return;
    documentToRemove.value = doc;
    confirmRemoveDocOpen.value = true;
};

const cancelRemoveDocument = () => {
    if (removingDocId.value !== null) return;
    confirmRemoveDocOpen.value = false;
    documentToRemove.value = null;
};

const confirmRemoveDocument = async () => {
    const doc = documentToRemove.value;
    if (!canManageDocuments.value || !doc) return;
    removingDocId.value = doc.id;
    try {
        await axios.delete(`/api/hr/employees/${route.params.id}/documents/${doc.id}`);
        documents.value = documents.value.filter((d) => d.id !== doc.id);
        toast.success('Document removed');
    } catch (e) {
        toast.error(e.response?.data?.message || 'Could not remove document');
    } finally {
        removingDocId.value = null;
        confirmRemoveDocOpen.value = false;
        documentToRemove.value = null;
    }
};

onMounted(async () => {
    if (!isSelfHrOnly.value) {
        await loadRoles();
    }
    await loadEmployee();
});

const queueDocument = () => {
    if (!fileInput.value?.files?.length) return;

    const files = Array.from(fileInput.value.files);
    files.forEach((f) => {
        queuedDocuments.value.push({
            name: newDocName.value || 'Attachment',
            file: f,
        });
    });

    newDocName.value = '';
    if (fileInput.value) fileInput.value.value = '';
};

const removeQueuedDocument = (index) => {
    queuedDocuments.value.splice(index, 1);
};
</script>
