<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50" @click.self="$emit('close')">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[92vh] flex flex-col overflow-hidden">
            <div class="p-5 border-b border-slate-200 flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Import HTML email template</h2>
                    <p class="text-sm text-slate-600 mt-1">
                        Upload a <code class="text-xs bg-slate-100 px-1 rounded">.html</code> file. Use the merge tags below inside your markup (same as sent email).
                    </p>
                    <p class="text-xs text-slate-500 mt-2">
                        Full guide:
                        <code class="bg-slate-100 px-1 rounded">docs/EMAIL_TEMPLATE_HTML_AND_MERGE_TAGS.md</code>
                        · AI / copy-paste tag table + prompt:
                        <code class="bg-slate-100 px-1 rounded">docs/EMAIL_MERGE_TAGS_AI_PROMPT_PACK.md</code>
                    </p>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 p-1" aria-label="Close" @click="$emit('close')">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form class="flex-1 overflow-y-auto p-5 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">HTML file *</label>
                    <input
                        ref="fileInput"
                        type="file"
                        accept=".html,.htm,.txt,text/html"
                        required
                        class="block w-full text-sm text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-slate-100 file:text-slate-800"
                        @change="onFile"
                    />
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Template name *</label>
                        <input v-model="name" type="text" required maxlength="255" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="e.g. Spring promo" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Category *</label>
                        <select v-model="category" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
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
                        <label class="block text-sm font-medium text-slate-700 mb-1">Subject *</label>
                        <input v-model="subject" type="text" required maxlength="255" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Hello {{first_name}}" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                        <input v-model="description" type="text" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" />
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Preview line (inbox snippet)</label>
                        <input v-model="previewLine" type="text" maxlength="500" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm" placeholder="Optional preheader text" />
                    </div>
                </div>
                <div class="space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                    <label class="flex items-start gap-2 text-sm text-slate-800 cursor-pointer">
                        <input v-model="extractBody" type="checkbox" class="mt-1 rounded border-slate-300" />
                        <span>
                            <strong>Extract &lt;body&gt; only</strong> (recommended if the file is a full HTML page). Styles in
                            <code class="text-xs">&lt;head&gt;</code> are not kept—put important styles inline on elements.
                        </span>
                    </label>
                    <label class="flex items-start gap-2 text-sm text-slate-800 cursor-pointer">
                        <input v-model="skipBrandFooter" type="checkbox" class="mt-1 rounded border-slate-300" />
                        <span>
                            <strong>Skip default CRM footer</strong> (use if your HTML already has unsubscribe / company block).
                        </span>
                    </label>
                </div>

                <div v-if="loadingTags" class="text-sm text-slate-500">Loading merge tags…</div>
                <div v-else class="border border-slate-200 rounded-lg overflow-hidden">
                    <button
                        type="button"
                        class="w-full flex items-center justify-between px-3 py-2 bg-slate-100 text-left text-sm font-medium text-slate-800"
                        @click="showTags = !showTags"
                    >
                        Merge tags &amp; HTML examples
                        <span>{{ showTags ? '▼' : '▶' }}</span>
                    </button>
                    <div v-show="showTags" class="p-3 max-h-64 overflow-y-auto space-y-4 bg-white">
                        <div v-if="htmlExamples && Object.keys(htmlExamples).length" class="space-y-2">
                            <p class="text-xs font-semibold text-slate-700 uppercase tracking-wide">Copy-paste snippets</p>
                            <div v-for="(code, key) in htmlExamples" :key="key" class="text-xs">
                                <div class="flex items-center justify-between gap-2 mb-1">
                                    <span class="font-mono text-slate-600">{{ formatExampleKey(key) }}</span>
                                    <button type="button" class="text-blue-600 hover:underline shrink-0" @click="copyText(code)">Copy</button>
                                </div>
                                <pre class="p-2 bg-slate-900 text-slate-100 rounded overflow-x-auto whitespace-pre-wrap break-all">{{ code }}</pre>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <p class="text-xs font-semibold text-slate-700 uppercase tracking-wide">All tags</p>
                            <div v-for="(items, group) in groupedTags" :key="group">
                                <p class="text-xs font-medium text-slate-500 mb-1">{{ group }}</p>
                                <table class="w-full text-xs border-collapse">
                                    <tbody>
                                        <tr v-for="row in items" :key="row.tag" class="border-b border-slate-100">
                                            <td class="py-1.5 pr-2 align-top whitespace-nowrap">
                                                <button
                                                    type="button"
                                                    class="font-mono text-blue-700 hover:underline"
                                                    :title="'Copy ' + row.tag"
                                                    @click="copyText(row.tag)"
                                                >
                                                    {{ row.tag }}
                                                </button>
                                            </td>
                                            <td class="py-1.5 text-slate-600 align-top">{{ row.description }}</td>
                                            <td class="py-1.5 pl-2 text-slate-400 align-top w-24">{{ row.example }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
            </form>

            <div class="p-5 border-t border-slate-200 flex justify-end gap-3 bg-slate-50">
                <button type="button" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-white" @click="$emit('close')">
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="submitting || !file"
                    class="px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed"
                    @click="submit"
                >
                    {{ submitting ? 'Importing…' : 'Create template' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

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
