<template>
    <BaseModal
        :model-value="true"
        title="Import HTML email template"
        description="Upload a .html file. Use the merge tags below inside your markup (same as sent email)."
        size="lg"
        :close-on-backdrop="false"
        @close="$emit('close')"
    >
        <form id="email-html-import-form" class="space-y-4" @submit.prevent="submit">
            <p class="text-xs text-slate-500">
                Full guide:
                <code class="rounded bg-slate-100 px-1">docs/EMAIL_TEMPLATE_HTML_AND_MERGE_TAGS.md</code>
                · AI / copy-paste tag table + prompt:
                <code class="rounded bg-slate-100 px-1">docs/EMAIL_MERGE_TAGS_AI_PROMPT_PACK.md</code>
            </p>

            <div>
                <label class="form-label" for="emailhtmlimportmodal-html-file">
                    HTML file <span class="form-required" aria-hidden="true">*</span>
                </label>
                <input
                    id="emailhtmlimportmodal-html-file"
                    ref="fileInput"
                    type="file"
                    accept=".html,.htm,.txt,text/html"
                    required
                    class="form-file"
                    @change="onFile"
                />
            </div>

            <div class="form-grid-2">
                <div class="sm:col-span-2">
                    <label class="form-label" for="emailhtmlimportmodal-template-name">
                        Template name <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="emailhtmlimportmodal-template-name"
                        v-model="name"
                        type="text"
                        required
                        maxlength="255"
                        class="form-input"
                        placeholder="e.g. Spring promo"
                    />
                </div>
                <div>
                    <label class="form-label" for="emailhtmlimportmodal-category">
                        Category <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <select id="emailhtmlimportmodal-category" v-model="category" class="form-select">
                        <option value="custom">Custom</option>
                        <option value="welcome">Welcome</option>
                        <option value="follow_up">Follow-up</option>
                        <option value="quote">Quote</option>
                        <option value="thank_you">Thank you</option>
                        <option value="reminder">Reminder</option>
                        <option value="appointment">Appointment</option>
                        <option value="invoice">Invoice</option>
                        <option value="epos">Epos</option>
                        <option value="teya">Teya</option>
                    </select>
                </div>
                <div>
                    <label class="form-label" for="emailhtmlimportmodal-subject">
                        Subject <span class="form-required" aria-hidden="true">*</span>
                    </label>
                    <input
                        id="emailhtmlimportmodal-subject"
                        v-model="subject"
                        type="text"
                        required
                        maxlength="255"
                        class="form-input"
                        placeholder="Hello {{first_name}}"
                    />
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label" for="emailhtmlimportmodal-description">Description</label>
                    <input
                        id="emailhtmlimportmodal-description"
                        v-model="description"
                        type="text"
                        class="form-input"
                    />
                </div>
                <div class="sm:col-span-2">
                    <label class="form-label" for="emailhtmlimportmodal-preview-line-inbox-snippet">
                        Preview line (inbox snippet)
                    </label>
                    <input
                        id="emailhtmlimportmodal-preview-line-inbox-snippet"
                        v-model="previewLine"
                        type="text"
                        maxlength="500"
                        class="form-input"
                        placeholder="Optional preheader text"
                    />
                </div>
            </div>

            <fieldset class="form-fieldset space-y-2 rounded-control border border-slate-200 bg-slate-50 p-3">
                <legend class="sr-only">Import options</legend>
                <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-800">
                    <input v-model="extractBody" type="checkbox" class="form-checkbox mt-1" />
                    <span>
                        <strong>Extract &lt;body&gt; only</strong> (recommended if the file is a full HTML page). Styles in
                        <code class="text-xs">&lt;head&gt;</code> are not kept—put important styles inline on elements.
                    </span>
                </label>
                <label class="flex cursor-pointer items-start gap-2 text-sm text-slate-800">
                    <input v-model="skipBrandFooter" type="checkbox" class="form-checkbox mt-1" />
                    <span>
                        <strong>Skip default CRM footer</strong> (use if your HTML already has unsubscribe / company block).
                    </span>
                </label>
            </fieldset>

            <p v-if="loadingTags" class="text-sm text-slate-500" role="status" aria-live="polite">Loading merge tags…</p>
            <div v-else class="overflow-hidden rounded-control border border-slate-200">
                <button
                    id="emailhtmlimportmodal-tags-toggle"
                    type="button"
                    class="flex w-full items-center justify-between gap-2 bg-slate-100 px-3 py-2 text-left text-sm font-medium text-slate-800 hover:bg-slate-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500/40"
                    :aria-expanded="showTags ? 'true' : 'false'"
                    aria-controls="emailhtmlimportmodal-tags-panel"
                    @click="showTags = !showTags"
                >
                    Merge tags &amp; HTML examples
                    <ChevronDownIcon v-if="showTags" class="icon-sm" aria-hidden="true" />
                    <ChevronRightIcon v-else class="icon-sm" aria-hidden="true" />
                </button>
                <div
                    id="emailhtmlimportmodal-tags-panel"
                    v-show="showTags"
                    role="region"
                    aria-labelledby="emailhtmlimportmodal-tags-toggle"
                    class="max-h-64 space-y-4 overflow-y-auto bg-white p-3"
                >
                    <div v-if="htmlExamples && Object.keys(htmlExamples).length" class="space-y-2">
                        <p class="text-eyebrow text-slate-600 uppercase">Copy-paste snippets</p>
                        <div v-for="(code, key) in htmlExamples" :key="key" class="text-xs">
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <span class="font-mono text-slate-600">{{ formatExampleKey(key) }}</span>
                                <button
                                    type="button"
                                    class="link shrink-0 inline-flex items-center gap-1 text-xs"
                                    :aria-label="`Copy ${formatExampleKey(key)} snippet`"
                                    @click="copyText(code)"
                                >
                                    <ClipboardDocumentIcon class="icon-sm" aria-hidden="true" />
                                    Copy
                                </button>
                            </div>
                            <pre class="overflow-x-auto whitespace-pre-wrap break-all rounded bg-slate-900 p-2 text-slate-100">{{ code }}</pre>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <p class="text-eyebrow text-slate-600 uppercase">All tags</p>
                        <div v-for="(items, group) in groupedTags" :key="group">
                            <p class="mb-1 text-xs font-medium text-slate-500">{{ group }}</p>
                            <table class="w-full border-collapse text-xs">
                                <caption class="sr-only">{{ group }} merge tags</caption>
                                <thead class="sr-only">
                                    <tr>
                                        <th scope="col">Merge tag</th>
                                        <th scope="col">Description</th>
                                        <th scope="col">Example</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in items" :key="row.tag" class="border-b border-slate-100">
                                        <td class="whitespace-nowrap py-1.5 pr-2 align-top">
                                            <button
                                                type="button"
                                                class="link font-mono text-xs"
                                                :title="'Copy ' + row.tag"
                                                :aria-label="'Copy ' + row.tag"
                                                @click="copyText(row.tag)"
                                            >
                                                {{ row.tag }}
                                            </button>
                                        </td>
                                        <td class="py-1.5 align-top text-slate-600">{{ row.description }}</td>
                                        <td class="w-24 py-1.5 pl-2 align-top text-slate-500">{{ row.example }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="error" class="callout callout-danger" role="alert">{{ error }}</p>
        </form>

        <template #actions>
            <BaseButton variant="outline" block-mobile @click="$emit('close')">Cancel</BaseButton>
            <BaseButton
                variant="primary"
                block-mobile
                :disabled="!file"
                :loading="submitting"
                @click="submit"
            >
                {{ submitting ? 'Importing…' : 'Create template' }}
            </BaseButton>
        </template>
    </BaseModal>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { ChevronDownIcon, ChevronRightIcon, ClipboardDocumentIcon } from '@heroicons/vue/24/outline';
import { useToastStore } from '@/stores/toast';
import { BaseButton, BaseModal } from '@/components/base';

const emit = defineEmits(['close', 'imported']);

const toast = useToastStore();
const fileInput = ref(null);
const file = ref(null);
const name = ref('');
const category = ref('custom');
const subject = ref('');
const description = ref('');
const previewLine = ref('');
const extractBody = ref(true);
const skipBrandFooter = ref(true);
const showTags = ref(true);
const tags = ref([]);
const htmlExamples = ref({});
const loadingTags = ref(true);
const submitting = ref(false);
const error = ref('');

const groupedTags = computed(() => {
    const m = {};
    for (const row of tags.value) {
        const g = row.group || 'Other';
        if (!m[g]) {
            m[g] = [];
        }
        m[g].push(row);
    }
    return m;
});

function formatExampleKey(key) {
    return String(key).replace(/_/g, ' ');
}

function onFile(e) {
    const f = e.target.files?.[0];
    file.value = f || null;
    error.value = '';
}

async function loadMergeTags() {
    loadingTags.value = true;
    try {
        const { data } = await axios.get('/api/email-templates/merge-tags');
        tags.value = data.tags || [];
        htmlExamples.value = data.html_examples || {};
    } catch {
        tags.value = [];
        htmlExamples.value = {};
        toast.error('Could not load merge tags list');
    } finally {
        loadingTags.value = false;
    }
}

function copyText(text) {
    const t = String(text || '');
    if (!t) {
        return;
    }
    navigator.clipboard.writeText(t).then(
        () => toast.success('Copied to clipboard'),
        () => toast.error('Copy failed')
    );
}

async function submit() {
    error.value = '';
    if (!file.value) {
        error.value = 'Choose an HTML file';
        return;
    }
    submitting.value = true;
    try {
        const fd = new FormData();
        fd.append('html_file', file.value);
        fd.append('name', name.value.trim());
        fd.append('category', category.value);
        fd.append('subject', subject.value.trim());
        if (description.value.trim()) {
            fd.append('description', description.value.trim());
        }
        if (previewLine.value.trim()) {
            fd.append('preview_line', previewLine.value.trim());
        }
        fd.append('extract_body', extractBody.value ? '1' : '0');
        fd.append('skip_brand_footer', skipBrandFooter.value ? '1' : '0');

        const { data } = await axios.post('/api/email-templates/import-html', fd, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toast.success('Template created from HTML');
        emit('imported', data);
        emit('close');
    } catch (e) {
        error.value = e.response?.data?.message || e.message || 'Import failed';
    } finally {
        submitting.value = false;
    }
}

onMounted(() => {
    loadMergeTags();
});
</script>
