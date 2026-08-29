<template>
    <ListingPageShell
        title="Import & export"
        subtitle="Bring records in from a spreadsheet, or take a copy out. Imports are previewed before anything is written."
    >
        <div class="form-body space-y-10">
            <!-- Import -->
            <section class="space-y-4">
                <h2 class="form-section-title">Import</h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="ie-type" class="form-label">Record type</label>
                        <select id="ie-type" v-model="type" class="form-select" @change="resetPreview">
                            <option value="customers">Customers</option>
                            <option value="leads">Leads</option>
                            <option value="invoices">Invoices</option>
                        </select>
                    </div>
                    <div>
                        <label for="ie-file" class="form-label">File</label>
                        <input
                            id="ie-file"
                            ref="fileInput"
                            type="file"
                            accept=".csv,.xlsx,.xls"
                            class="form-input"
                            aria-describedby="ie-file-hint"
                            @change="onFile"
                        />
                        <p id="ie-file-hint" class="form-hint">CSV or Excel, up to 10&nbsp;MB.</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <BaseButton variant="soft" :disabled="!file" :loading="previewing" @click="runPreview">
                        <template #icon><TableCellsIcon class="icon" aria-hidden="true" /></template>
                        {{ previewing ? 'Reading…' : 'Preview file' }}
                    </BaseButton>
                    <label class="form-choice" for="ie-skip-duplicates">
                        <input id="ie-skip-duplicates" v-model="skipDuplicates" type="checkbox" class="form-checkbox" />
                        Skip rows that already exist
                    </label>
                </div>

                <!-- Column mapping -->
                <div v-if="preview" class="space-y-4">
                    <div class="rounded-card border border-slate-200 overflow-hidden">
                        <div class="form-section-head flex items-center justify-between gap-3">
                            <p class="text-sm font-semibold text-slate-800">
                                {{ preview.rows?.length || 0 }} row(s) found
                            </p>
                            <BaseBadge v-if="unmappedRequired.length" tone="warning">
                                {{ unmappedRequired.length }} required field(s) unmapped
                            </BaseBadge>
                        </div>

                        <div class="p-4 space-y-3">
                            <p class="text-xs text-slate-500">
                                Match each field to a column from your file. Leave a field blank to skip it.
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div v-for="field in targetFields" :key="field.key">
                                    <label :for="`map-${field.key}`" class="form-label">
                                        {{ field.label }}
                                        <span v-if="field.required" class="form-required" aria-hidden="true">*</span>
                                    </label>
                                    <select :id="`map-${field.key}`" v-model="mapping[field.key]" class="form-select">
                                        <option value="">— skip —</option>
                                        <option v-for="col in preview.headings" :key="col" :value="col">{{ col }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sample rows -->
                    <div v-if="preview.rows?.length" class="table-wrap rounded-card border border-slate-200">
                        <table class="table min-w-[560px]">
                            <caption class="sr-only">First five rows of the uploaded file</caption>
                            <thead class="table-thead">
                                <tr>
                                    <th v-for="col in preview.headings" :key="col" scope="col" class="table-th">{{ col }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in preview.rows.slice(0, 5)" :key="i" class="table-row">
                                    <td v-for="col in preview.headings" :key="col" class="table-td truncate max-w-[16rem]">
                                        {{ row[col] ?? '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end">
                        <BaseButton
                            variant="primary"
                            size="lg"
                            block-mobile
                            :disabled="unmappedRequired.length > 0"
                            :loading="importing"
                            @click="runImport"
                        >
                            <template #icon><ArrowUpTrayIcon class="icon" aria-hidden="true" /></template>
                            {{ importing ? 'Importing…' : `Import ${preview.rows?.length || 0} row(s)` }}
                        </BaseButton>
                    </div>
                </div>

                <div v-if="result" class="callout callout-success" role="status" aria-live="polite">
                    <p class="font-semibold">Import finished</p>
                    <p class="mt-1">
                        Imported {{ result.imported ?? 0 }} ·
                        Skipped {{ result.skipped ?? 0 }} ·
                        Failed {{ result.failed ?? 0 }}
                    </p>
                </div>
            </section>

            <hr class="divider" />

            <!-- Export -->
            <section class="space-y-4">
                <h2 class="form-section-title">Export</h2>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="ie-export-type" class="form-label">Record type</label>
                        <select id="ie-export-type" v-model="exportType" class="form-select">
                            <option value="customers">Customers</option>
                            <option value="leads">Leads</option>
                            <option value="invoices">Invoices</option>
                            <option value="tickets">Tickets</option>
                        </select>
                    </div>
                    <div>
                        <label for="ie-export-format" class="form-label">Format</label>
                        <select id="ie-export-format" v-model="exportFormat" class="form-select">
                            <option value="xlsx">Excel (.xlsx)</option>
                            <option value="csv">CSV</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <BaseButton variant="soft" class="w-full" :loading="exporting" @click="runExport">
                            <template #icon><ArrowDownTrayIcon class="icon" aria-hidden="true" /></template>
                            {{ exporting ? 'Preparing…' : 'Download' }}
                        </BaseButton>
                    </div>
                </div>
            </section>

            <hr class="divider" />

            <!-- History -->
            <section class="space-y-3">
                <h2 class="form-section-title">Recent imports</h2>

                <EmptyState v-if="!logs.length" heading="No imports yet" description="Past imports are listed here." />

                <div v-else class="table-wrap rounded-card border border-slate-200">
                    <table class="table min-w-[560px]">
                        <caption class="sr-only">Recent import runs</caption>
                        <thead class="table-thead">
                            <tr>
                                <th scope="col" class="table-th">When</th>
                                <th scope="col" class="table-th">Type</th>
                                <th scope="col" class="table-th">File</th>
                                <th scope="col" class="table-th-num">Imported</th>
                                <th scope="col" class="table-th-num">Failed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in logs" :key="log.id" class="table-row">
                                <td class="table-td">{{ when(log.created_at) }}</td>
                                <td class="table-td">{{ log.type }}</td>
                                <td class="table-td truncate max-w-[14rem]">{{ log.file_name || '—' }}</td>
                                <td class="table-td-num font-semibold text-slate-800">{{ log.imported_count ?? 0 }}</td>
                                <td class="table-td-num">{{ log.failed_count ?? 0 }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </ListingPageShell>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { ArrowDownTrayIcon, ArrowUpTrayIcon, TableCellsIcon } from '@heroicons/vue/24/outline';
import ListingPageShell from '@/components/ListingPageShell.vue';
import EmptyState from '@/components/base/EmptyState.vue';
import { BaseBadge, BaseButton } from '@/components/base';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const type = ref('customers');
const file = ref(null);
const fileInput = ref(null);
const preview = ref(null);
const mapping = reactive({});
const skipDuplicates = ref(true);
const previewing = ref(false);
const importing = ref(false);
const result = ref(null);

const exportType = ref('customers');
const exportFormat = ref('xlsx');
const exporting = ref(false);

const logs = ref([]);

/** Importable fields per record type, and which the importer requires. */
const FIELDS = {
    customers: [
        { key: 'name', label: 'Name', required: true },
        { key: 'phone', label: 'Phone', required: true },
        { key: 'email', label: 'Email' },
        { key: 'business_name', label: 'Business name' },
        { key: 'address', label: 'Address' },
        { key: 'city', label: 'City' },
        { key: 'postcode', label: 'Postcode' },
        { key: 'source', label: 'Source' },
        { key: 'notes', label: 'Notes' },
    ],
    leads: [
        { key: 'customer_phone', label: 'Customer phone', required: true },
        { key: 'stage', label: 'Stage' },
        { key: 'source', label: 'Source' },
        { key: 'pipeline_value', label: 'Pipeline value' },
    ],
    invoices: [
        { key: 'customer_phone', label: 'Customer phone', required: true },
        { key: 'invoice_date', label: 'Invoice date' },
        { key: 'total', label: 'Total', required: true },
    ],
};

const targetFields = computed(() => FIELDS[type.value] || []);

const unmappedRequired = computed(() =>
    targetFields.value.filter((f) => f.required && !mapping[f.key]),
);

function when(value) {
    return value ? new Date(value).toLocaleString('en-GB') : '';
}

function resetPreview() {
    preview.value = null;
    result.value = null;
    Object.keys(mapping).forEach((k) => delete mapping[k]);
}

function onFile(event) {
    file.value = event.target.files?.[0] ?? null;
    resetPreview();
}

async function runPreview() {
    if (!file.value) return;

    previewing.value = true;
    try {
        const form = new FormData();
        form.append('file', file.value);
        form.append('type', type.value);

        const { data } = await axios.post('/api/import-export/preview', form);
        preview.value = data;

        // Pre-match columns whose heading looks like the field name.
        targetFields.value.forEach((field) => {
            const match = (data.headings || []).find(
                (h) => h.toLowerCase().replace(/[^a-z]/g, '') === field.key.replace(/[^a-z]/g, ''),
            );
            mapping[field.key] = match || '';
        });
    } catch (e) {
        toast.error(e.response?.data?.message || 'Could not read that file.');
    } finally {
        previewing.value = false;
    }
}

async function runImport() {
    importing.value = true;
    result.value = null;
    try {
        const form = new FormData();
        form.append('file', file.value);
        form.append('type', type.value);
        form.append('skip_duplicates', skipDuplicates.value ? '1' : '0');
        Object.entries(mapping).forEach(([field, column]) => {
            if (column) form.append(`mapping[${field}]`, column);
        });

        const { data } = await axios.post('/api/import-export/import', form);
        result.value = data;
        toast.success('Import finished');
        preview.value = null;
        if (fileInput.value) fileInput.value.value = '';
        file.value = null;
        await loadLogs();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Import failed.');
    } finally {
        importing.value = false;
    }
}

async function runExport() {
    exporting.value = true;
    try {
        const response = await axios.get('/api/import-export/export', {
            params: { type: exportType.value, format: exportFormat.value },
            responseType: 'blob',
        });

        const url = URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.download = `${exportType.value}.${exportFormat.value}`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    } catch (e) {
        toast.error('Export failed.');
    } finally {
        exporting.value = false;
    }
}

async function loadLogs() {
    try {
        const { data } = await axios.get('/api/import-export/logs');
        logs.value = data.data ?? data ?? [];
    } catch {
        logs.value = [];
    }
}

onMounted(loadLogs);
</script>
