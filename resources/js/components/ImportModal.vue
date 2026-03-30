<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b border-slate-200">
                <h2 class="text-xl font-semibold text-slate-900">Import Customers</h2>
            </div>

            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2">Upload Excel/CSV File</label>
                    <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-slate-300 border-dashed rounded-lg hover:border-slate-400 transition">
                        <div class="space-y-1 text-center">
                            <svg class="mx-auto h-12 w-12 text-slate-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <div class="flex text-sm text-slate-600">
                                <label for="file-upload" class="relative cursor-pointer rounded-md font-medium text-blue-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-blue-500 focus-within:ring-offset-2 hover:text-blue-500">
                                    <span>Upload a file</span>
                                    <input id="file-upload" name="file-upload" type="file" class="sr-only" accept=".xlsx,.xls,.csv" @change="handleFileSelect" />
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs text-slate-500">Excel (.xlsx, .xls) or CSV files up to 10MB</p>
                        </div>
                    </div>
                    <div v-if="selectedFile" class="mt-2 text-sm text-slate-600">
                        Selected: <span class="font-medium">{{ selectedFile.name }}</span>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button
                        type="button"
                        @click="downloadTemplate"
                        :disabled="downloadingTemplate"
                        class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-slate-100 text-slate-800 rounded-lg hover:bg-slate-200 border border-slate-300 disabled:opacity-50"
                    >
                        {{ downloadingTemplate ? 'Downloading...' : 'Download template (with product columns)' }}
                    </button>
                </div>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-blue-900 mb-2">Expected Format:</h3>
                    <ul class="text-xs text-blue-800 space-y-1 list-disc list-inside">
                        <li>First row: headers. Name and Phone are required. Optional: contact_person_2_name, contact_person_2_phone, email, address, city, postcode, vat_number, business_name, owner_name, whatsapp_number, sms_number, notes, source, remote_1_anydesk_rustdesk, remote_1_passwords, remote_1_epos_type, remote_1_lic_days (and remote_2_*, remote_3_* for multiple Remote & License entries), birthday, category.</li>
                        <li>Template includes one column per product in your DB — use numeric value for quantity purchased, or Y/Yes for prospect.</li>
                    </ul>
                </div>

                <!-- Column Mapping -->
                <div v-if="preview.length > 0 && previewHeaders.length > 0" class="border border-slate-200 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-slate-700 mb-3">Column Mapping</h3>
                    <div class="space-y-2">
                        <div
                            v-for="field in requiredFields"
                            :key="field.key"
                            class="flex items-center gap-3"
                        >
                            <label class="text-sm text-slate-700 w-32">
                                {{ field.label }}
                                <span v-if="field.required" class="text-red-500">*</span>
                            </label>
                            <select
                                v-model="columnMapping[field.key]"
                                class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">-- Select Column --</option>
                                <option v-for="header in previewHeaders" :key="header" :value="header">
                                    {{ header }}
                                </option>
                            </select>
                        </div>
                        <div
                            v-for="field in optionalFields"
                            :key="field.key"
                            class="flex items-center gap-3"
                        >
                            <label class="text-sm text-slate-700 w-32">{{ field.label }}</label>
                            <select
                                v-model="columnMapping[field.key]"
                                class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="">-- Select Column (Optional) --</option>
                                <option v-for="header in previewHeaders" :key="header" :value="header">
                                    {{ header }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div v-if="preview.length > 0" class="border border-slate-200 rounded-lg overflow-hidden">
                    <div class="bg-slate-50 px-4 py-2 border-b border-slate-200">
                        <p class="text-sm font-medium text-slate-700">Preview (first 5 rows):</p>
                    </div>
                    <div class="overflow-x-auto max-h-64">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th v-for="header in previewHeaders" :key="header" class="px-3 py-2 text-left text-xs font-medium text-slate-700 uppercase">
                                        {{ header }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-slate-200">
                                <tr v-for="(row, index) in preview" :key="index">
                                    <td v-for="header in previewHeaders" :key="header" class="px-3 py-2 text-xs text-slate-600">
                                        {{ row[header] || '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div v-if="error" class="text-sm text-red-600 bg-red-50 p-3 rounded">
                    {{ error }}
                </div>

                <div v-if="importResult" class="text-sm bg-green-50 p-3 rounded">
                    <p class="text-green-800 font-medium">Import completed!</p>
                    <p class="text-green-700 mt-1">
                        Successfully imported: {{ importResult.success }} customers
                        <span v-if="importResult.errors > 0" class="text-red-600">
                            <br>Errors: {{ importResult.errors }} rows
                        </span>
                    </p>
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                    <button
                        type="button"
                        @click="$emit('close')"
                        class="px-4 py-2 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="handleImport"
                        :disabled="!selectedFile || loading || preview.length === 0"
                        class="px-4 py-2 text-sm bg-slate-900 text-white rounded-lg hover:bg-slate-800 disabled:opacity-50"
                    >
                        {{ loading ? 'Importing...' : 'Import Customers' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import * as XLSX from 'xlsx';

const emit = defineEmits(['close', 'imported']);

const selectedFile = ref(null);
const preview = ref([]);
const previewHeaders = ref([]);
const loading = ref(false);
const error = ref(null);
const importResult = ref(null);
const columnMapping = ref({});

const requiredFields = [
    { key: 'name', label: 'Name' },
    { key: 'phone', label: 'Phone' },
];

const downloadingTemplate = ref(false);
async function downloadTemplate() {
    downloadingTemplate.value = true;
    try {
        const { data } = await axios.get('/api/customers/import-template', { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `customers_import_template_${new Date().toISOString().slice(0, 10)}.csv`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
    } catch (err) {
        console.error('Download template failed', err);
    } finally {
        downloadingTemplate.value = false;
    }
}

const optionalFields = [
    { key: 'email', label: 'Email' },
    { key: 'contact_person_2_name', label: 'Contact Person 2 Name' },
    { key: 'contact_person_2_phone', label: 'Contact Person 2 Phone' },
    { key: 'address', label: 'Address' },
    { key: 'city', label: 'City' },
    { key: 'postcode', label: 'Postcode' },
    { key: 'vat_number', label: 'VAT Number' },
    { key: 'business_name', label: 'Business name' },
    { key: 'owner_name', label: 'Owner name' },
    { key: 'whatsapp_number', label: 'WhatsApp number' },
    { key: 'email_secondary', label: 'Email secondary' },
    { key: 'sms_number', label: 'SMS number' },
    { key: 'notes', label: 'Notes' },
    { key: 'source', label: 'Source' },
    { key: 'anydesk_rustdesk', label: 'AnyDesk/RustDesk' },
    { key: 'passwords', label: 'Passwords' },
    { key: 'epos_type', label: 'EPOS type' },
    { key: 'lic_days', label: 'Licence days' },
    { key: 'birthday', label: 'Birthday' },
    { key: 'category', label: 'Category' },
];

const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    selectedFile.value = file;
    error.value = null;
    importResult.value = null;
    preview.value = [];
    previewHeaders.value = [];

    const reader = new FileReader();
    
    reader.onload = (e) => {
        try {
            const data = new Uint8Array(e.target.result);
            const workbook = XLSX.read(data, { type: 'array' });
            const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
            const jsonData = XLSX.utils.sheet_to_json(firstSheet, { header: 1 });

            if (jsonData.length === 0) {
                error.value = 'File is empty';
                return;
            }

            // First row as headers
            previewHeaders.value = jsonData[0].map(h => String(h).trim());
            
            // Convert to object format for preview
            preview.value = jsonData.slice(1, 6).map(row => {
                const obj = {};
                previewHeaders.value.forEach((header, index) => {
                    obj[header] = row[index] || '';
                });
                return obj;
            });

            // Auto-detect column mapping
            autoDetectMapping();
        } catch (err) {
            error.value = 'Failed to read file: ' + err.message;
            console.error('File read error:', err);
        }
    };

    if (file.name.endsWith('.csv')) {
        reader.readAsText(file);
    } else {
        reader.readAsArrayBuffer(file);
    }
};

const autoDetectMapping = () => {
    const mapping = {};
    const headerLower = previewHeaders.value.map(h => h.toLowerCase().trim());
    
    // Map required fields
    requiredFields.forEach(field => {
        const possibleNames = [
            field.key,
            field.key.replace('_', ' '),
            field.key.replace('_', ''),
        ];
        
        for (const name of possibleNames) {
            const index = headerLower.findIndex(h => h.includes(name) || name.includes(h));
            if (index !== -1) {
                mapping[field.key] = previewHeaders.value[index];
                break;
            }
        }
    });

    // Map optional fields
    optionalFields.forEach(field => {
        const possibleNames = [
            field.key,
            field.key.replace('_', ' '),
            field.key.replace('_', ''),
        ];
        
        for (const name of possibleNames) {
            const index = headerLower.findIndex(h => h.includes(name) || name.includes(h));
            if (index !== -1) {
                mapping[field.key] = previewHeaders.value[index];
                break;
            }
        }
    });

    columnMapping.value = mapping;
};

const validateMapping = () => {
    for (const field of requiredFields) {
        if (!columnMapping.value[field.key]) {
            return `Please map the ${field.label} column`;
        }
    }
    return null;
};

const handleImport = async () => {
    if (!selectedFile.value) return;

    const validationError = validateMapping();
    if (validationError) {
        error.value = validationError;
        return;
    }

    loading.value = true;
    error.value = null;
    importResult.value = null;

    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);

        const response = await axios.post('/api/customers/import', formData, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        importResult.value = response.data;
        
        // Emit success and close after 2 seconds
        setTimeout(() => {
            emit('imported');
            emit('close');
        }, 2000);
    } catch (err) {
        if (err.response?.data?.message) {
            error.value = err.response.data.message;
        } else if (err.response?.data?.errors) {
            error.value = Object.values(err.response.data.errors).flat().join(', ');
        } else {
            error.value = 'Failed to import customers. Please check the file format.';
        }
        console.error('Import error:', err);
    } finally {
        loading.value = false;
    }
};
</script>

