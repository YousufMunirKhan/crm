<template>
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-7xl max-h-[95vh] overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="p-6 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">
                        {{ template?.id ? 'Edit Template' : 'Create New Template' }}
                    </h2>
                    <p class="text-sm text-slate-500 mt-1">Build responsive email templates</p>
                </div>
                <button @click="$emit('close')" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Main Content -->
            <div class="flex-1 flex overflow-hidden">
                <!-- Left Sidebar - Sections & Settings -->
                <div class="w-80 border-r border-slate-200 flex flex-col">
                    <!-- Template Info -->
                    <div class="p-4 border-b border-slate-200 space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Template Name *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Welcome Email"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Category *</label>
                            <select
                                v-model="form.category"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="welcome">Welcome Email</option>
                                <option value="epos">Epos Description</option>
                                <option value="teya">Teya Product</option>
                                <option value="appointment">Appointment</option>
                                <option value="invoice">Invoice</option>
                                <option value="follow_up">Follow-up</option>
                                <option value="quote">Quote</option>
                                <option value="thank_you">Thank You</option>
                                <option value="reminder">Reminder</option>
                                <option value="custom">Custom</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Email Subject *</label>
                            <input
                                v-model="form.subject"
                                type="text"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Welcome to {{company_name}}"
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
                            <textarea
                                v-model="form.description"
                                rows="2"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Template description..."
                            />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Preview Line</label>
                            <input
                                v-model="form.content.preview_line"
                                type="text"
                                class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="e.g. View this offer in your inbox"
                            />
                            <p class="text-xs text-slate-500 mt-1">Text shown next to subject in inbox (Gmail, Outlook, etc.)</p>
                        </div>
                    </div>

                    <!-- Available Sections -->
                    <div class="flex-1 overflow-y-auto p-4">
                        <h3 class="text-sm font-semibold text-slate-900 mb-3">Add Section</h3>
                        <div class="space-y-2">
                            <button
                                v-for="sectionType in availableSections"
                                :key="sectionType.type"
                                @click="addSection(sectionType.type)"
                                class="w-full text-left px-3 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors flex items-center gap-2"
                            >
                                <span class="text-xl">{{ sectionType.icon }}</span>
                                <span class="text-sm font-medium text-slate-700">{{ sectionType.label }}</span>
                            </button>
                        </div>

                        <!-- Section/line style (when section or line selected) - font, size, color -->
                        <div v-if="styleTarget" class="mt-4 p-3 bg-slate-50 rounded-lg border border-slate-200">
                            <h3 class="text-sm font-semibold text-slate-900 mb-3">{{ styleTargetLabel }}</h3>
                            <p class="text-xs text-slate-500 mb-3">Change font, size & color. Preview updates below.</p>
                            <div class="space-y-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Font</label>
                                    <select
                                        v-model="styleTarget.font_family"
                                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="">Default (Arial)</option>
                                        <option value="Arial, sans-serif">Arial</option>
                                        <option value="Georgia, serif">Georgia</option>
                                        <option value="Helvetica, sans-serif">Helvetica</option>
                                        <option value="Tahoma, sans-serif">Tahoma</option>
                                        <option value="Verdana, sans-serif">Verdana</option>
                                        <option value="Times New Roman, serif">Times New Roman</option>
                                        <option value="Courier New, monospace">Courier New</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Size</label>
                                    <select
                                        v-model="styleTarget.font_size"
                                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                    >
                                        <option value="">Default</option>
                                        <option value="12px">12px</option>
                                        <option value="14px">14px</option>
                                        <option value="16px">16px</option>
                                        <option value="18px">18px</option>
                                        <option value="20px">20px</option>
                                        <option value="24px">24px</option>
                                        <option value="28px">28px</option>
                                        <option value="32px">32px</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-1">Color</label>
                                    <div class="flex gap-2 items-center">
                                        <input
                                            v-model="styleTarget.font_color"
                                            type="color"
                                            class="h-9 w-14 border border-slate-300 rounded cursor-pointer p-0"
                                        />
                                        <input
                                            v-model="styleTarget.font_color"
                                            type="text"
                                            class="flex-1 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            placeholder="#333333"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Variables -->
                        <div class="mt-6">
                            <h3 class="text-sm font-semibold text-slate-900 mb-3">Variables</h3>
                            <div class="space-y-1">
                                <button
                                    v-for="variable in availableVariables"
                                    :key="variable"
                                    @click="insertVariable(variable)"
                                    class="w-full text-left px-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded hover:bg-slate-100 transition-colors"
                                >
                                    {{ variable }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Center - Template Builder -->
                <div class="flex-1 flex flex-col overflow-hidden">
                    <!-- Preview Toggle -->
                    <div class="p-3 border-b border-slate-200 flex items-center justify-between bg-slate-50">
                        <div class="flex gap-2">
                            <button
                                @click="previewMode = 'desktop'"
                                :class="previewMode === 'desktop' ? 'bg-blue-600 text-white' : 'bg-white text-slate-700'"
                                class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 transition-colors"
                            >
                                🖥️ Desktop
                            </button>
                            <button
                                @click="previewMode = 'mobile'"
                                :class="previewMode === 'mobile' ? 'bg-blue-600 text-white' : 'bg-white text-slate-700'"
                                class="px-3 py-1.5 text-sm rounded-lg border border-slate-300 transition-colors"
                            >
                                📱 Mobile
                            </button>
                        </div>
                        <button
                            @click="loadPrebuiltTemplate"
                            class="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                        >
                            📋 Load Pre-built
                        </button>
                    </div>

                    <!-- Template Canvas -->
                    <div class="flex-1 overflow-y-auto bg-slate-100 p-6">
                        <div
                            :class="[
                                'bg-white mx-auto shadow-lg',
                                previewMode === 'mobile' ? 'max-w-sm' : 'max-w-2xl'
                            ]"
                        >
                            <!-- Template Sections -->
                            <div v-if="form.content.sections.length === 0" class="p-12 text-center text-slate-400">
                                <div class="text-4xl mb-4">📧</div>
                                <p>Click "Add Section" to start building your template</p>
                            </div>

                            <div
                                v-for="(section, index) in form.content.sections"
                                :key="index"
                                class="relative group border-b border-slate-200"
                                :class="{ 'border-blue-500 border-2': selectedSectionIndex === index }"
                            >
                                <!-- Section Controls -->
                                <div class="absolute top-2 right-2 z-10 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                    <button
                                        @click="moveSection(index, 'up')"
                                        :disabled="index === 0"
                                        class="p-1 bg-white border border-slate-300 rounded text-xs hover:bg-slate-50 disabled:opacity-50"
                                        title="Move Up"
                                    >
                                        ⬆️
                                    </button>
                                    <button
                                        @click="moveSection(index, 'down')"
                                        :disabled="index === form.content.sections.length - 1"
                                        class="p-1 bg-white border border-slate-300 rounded text-xs hover:bg-slate-50 disabled:opacity-50"
                                        title="Move Down"
                                    >
                                        ⬇️
                                    </button>
                                    <button
                                        @click="duplicateSection(index)"
                                        class="p-1 bg-white border border-slate-300 rounded text-xs hover:bg-slate-50"
                                        title="Duplicate"
                                    >
                                        📋
                                    </button>
                                    <button
                                        @click="removeSection(index)"
                                        class="p-1 bg-white border border-red-300 text-red-600 rounded text-xs hover:bg-red-50"
                                        title="Remove"
                                    >
                                        🗑️
                                    </button>
                                </div>

                                <!-- Section Content -->
                                <div @click="selectedSectionIndex = index; selectedBlockIndex = null" class="p-4">
                                    <!-- Header Section -->
                                    <div v-if="section.type === 'header'" class="text-center py-6">
                                        <img
                                            v-if="section.content.logo"
                                            :src="section.content.logo"
                                            alt="Logo"
                                            class="h-12 mx-auto mb-4 cursor-pointer"
                                            @click="editSectionImage(index, 'logo')"
                                        />
                                        <div
                                            v-else
                                            @click="editSectionImage(index, 'logo')"
                                            class="h-12 mx-auto mb-4 bg-slate-100 border-2 border-dashed border-slate-300 rounded flex items-center justify-center cursor-pointer hover:bg-slate-200"
                                        >
                                            <span class="text-slate-400 text-sm">Click to add logo</span>
                                        </div>
                                        <input
                                            v-model="section.content.text"
                                            type="text"
                                            :style="getSectionPreviewStyle(section)"
                                            class="text-2xl font-bold text-center w-full border-none focus:outline-none focus:ring-2 focus:ring-blue-500 rounded px-2"
                                            placeholder="Header Text"
                                        />
                                    </div>

                                    <!-- Text Section - single paragraph textarea -->
                                    <div v-if="section.type === 'text'" class="p-2">
                                        <textarea
                                            v-model="section.content.blocks[0].text"
                                            rows="4"
                                            :style="getSectionPreviewStyle(section)"
                                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white resize-none overflow-hidden"
                                            placeholder="Write your paragraph here – you can use {{variables}} and press Enter for new lines"
                                            @input="autoResizeTextArea($event)"
                                            @focus="autoResizeTextArea($event)"
                                            @click.stop="selectedSectionIndex = index"
                                        />
                                    </div>

                                    <!-- Image Section -->
                                    <div v-if="section.type === 'image'" class="text-center">
                                        <div class="relative inline-block">
                                            <img
                                                v-if="section.content.image_url"
                                                :src="section.content.image_url"
                                                alt="Image"
                                                class="max-w-full h-auto mx-auto cursor-pointer rounded"
                                                @click="editSectionImage(index, 'image_url')"
                                            />
                                            <div
                                                v-else
                                                @click="editSectionImage(index, 'image_url')"
                                                class="h-48 bg-slate-100 border-2 border-dashed border-slate-300 rounded flex items-center justify-center cursor-pointer hover:bg-slate-200"
                                            >
                                                <span class="text-slate-400">Click to add image</span>
                                            </div>
                                            <!-- Clickable indicator -->
                                            <div v-if="section.content.link_url && section.content.link_url !== '#'" class="absolute top-2 right-2 bg-blue-600 text-white text-xs px-2 py-1 rounded">
                                                🔗 Link
                                            </div>
                                        </div>
                                        <div class="mt-2 space-y-2">
                                            <input
                                                v-model="section.content.alt"
                                                type="text"
                                                class="w-full text-sm text-center border border-slate-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                placeholder="Image alt text"
                                            />
                                            <!-- Link URL - shows when section is selected -->
                                            <div v-if="selectedSectionIndex === index">
                                                <input
                                                    v-model="section.content.link_url"
                                                    type="url"
                                                    @click.stop
                                                    class="w-full text-sm border border-slate-300 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    placeholder="Image link URL (optional) - https://example.com or # for no link"
                                                />
                                                <p class="text-xs text-slate-500 mt-1">
                                                    Leave empty or use # to make image non-clickable
                                                </p>
                                            </div>
                                            <div v-else-if="section.content.link_url && section.content.link_url !== '#'" class="text-xs text-slate-500">
                                                🔗 Links to: {{ section.content.link_url }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Button Section -->
                                    <div v-if="section.type === 'button'" class="py-4">
                                        <div class="text-center space-y-2">
                                            <!-- Button Text Input -->
                                            <div class="inline-block">
                                                <input
                                                    v-model="section.content.text"
                                                    type="text"
                                                    :style="{ minWidth: '200px', ...getSectionPreviewStyle(section) }"
                                                    class="px-6 py-3 bg-blue-600 text-white rounded-lg font-medium border-2 border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                                    placeholder="Button Text"
                                                />
                                            </div>
                                            <!-- URL Input - appears when button is clicked or focused -->
                                            <div v-if="selectedSectionIndex === index" class="mt-3">
                                                <input
                                                    v-model="section.content.url"
                                                    type="url"
                                                    @click.stop
                                                    class="w-full max-w-md mx-auto px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white"
                                                    placeholder="https://switch-and-save.uk/ or # for no link"
                                                />
                                                <p class="text-xs text-slate-500 mt-1">
                                                    Enter URL or # for no link
                                                </p>
                                            </div>
                                            <!-- URL Display when not editing -->
                                            <div v-else-if="section.content.url && section.content.url !== '#'" class="text-xs text-slate-500 mt-1">
                                                🔗 {{ section.content.url }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Two Column Section -->
                                    <div v-if="section.type === 'two_column'" class="grid grid-cols-2 gap-4">
                                        <div :style="getSectionPreviewStyle(section)">
                                            <input
                                                v-model="section.content.left_text"
                                                type="text"
                                                :style="getSectionPreviewStyle(section)"
                                                class="w-full border-none focus:outline-none focus:ring-2 focus:ring-blue-500 rounded p-2 bg-transparent"
                                                placeholder="Left column text"
                                            />
                                        </div>
                                        <div :style="getSectionPreviewStyle(section)">
                                            <input
                                                v-model="section.content.right_text"
                                                type="text"
                                                :style="getSectionPreviewStyle(section)"
                                                class="w-full border-none focus:outline-none focus:ring-2 focus:ring-blue-500 rounded p-2 bg-transparent"
                                                placeholder="Right column text"
                                            />
                                        </div>
                                    </div>

                                    <!-- Footer Section -->
                                    <div v-if="section.type === 'footer'" class="text-center py-6 bg-slate-50" :style="getSectionPreviewStyle(section)">
                                        <input
                                            v-model="section.content.text"
                                            type="text"
                                            :style="getSectionPreviewStyle(section)"
                                            class="w-full text-sm text-center border-none focus:outline-none focus:ring-2 focus:ring-blue-500 rounded px-2 mb-2 bg-transparent"
                                            placeholder="Footer text"
                                        />
                                        <div class="text-xs text-slate-500">
                                            {{companyName}} | Company No: {{companyRegNo}} | VAT: {{companyVat}}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="p-6 border-t border-slate-200 flex justify-end gap-3">
                <button
                    @click="$emit('close')"
                    class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors"
                >
                    Cancel
                </button>
                <button
                    @click="showTestSendModal = true"
                    :disabled="!form.subject || !form.content.sections.length"
                    class="px-4 py-2 border border-blue-300 text-blue-600 rounded-lg hover:bg-blue-50 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Send test email to verify variables and layout"
                >
                    Test send
                </button>
                <button
                    @click="saveTemplate"
                    :disabled="saving"
                    class="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
                >
                    {{ saving ? 'Saving...' : 'Save Template' }}
                </button>
            </div>
        </div>

        <!-- Test Send Modal -->
        <div v-if="showTestSendModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60]" @click.self="showTestSendModal = false">
            <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4 shadow-xl">
                <h3 class="text-lg font-semibold text-slate-900 mb-2">Test send template</h3>
                <p class="text-sm text-slate-600 mb-4">Sends the current template to your email with sample data so you can verify variables and layout.</p>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Send to email *</label>
                    <input
                        v-model="testSendEmail"
                        type="email"
                        class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                        placeholder="your@email.com"
                    />
                </div>
                <div class="flex justify-end gap-3">
                    <button @click="showTestSendModal = false" class="px-4 py-2 border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700">
                        Cancel
                    </button>
                    <button
                        @click="doTestSend"
                        :disabled="!testSendEmail || sendingTest"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ sendingTest ? 'Sending...' : 'Send test' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Image Upload Modal -->
        <div v-if="showImageUpload" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-[60]">
            <div class="bg-white rounded-xl p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">Upload Image</h3>
                <input
                    ref="imageInput"
                    type="file"
                    accept="image/*"
                    @change="handleImageUpload"
                    class="mb-4"
                />
                <div class="flex justify-end gap-3">
                    <button @click="showImageUpload = false" class="px-4 py-2 border rounded-lg">Cancel</button>
                    <button @click="uploadImage" :disabled="uploadingImage" class="px-4 py-2 bg-blue-600 text-white rounded-lg">
                        {{ uploadingImage ? 'Uploading...' : 'Upload' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue';
import axios from 'axios';
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();

const props = defineProps({
    template: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['close', 'saved']);

const saving = ref(false);
const previewMode = ref('desktop');
const selectedSectionIndex = ref(null);
const selectedBlockIndex = ref(null);
const showImageUpload = ref(false);
const showTestSendModal = ref(false);
const testSendEmail = ref('');
const sendingTest = ref(false);
const uploadingImage = ref(false);
const imageInput = ref(null);
const currentImageEdit = ref({ sectionIndex: null, field: null });

const companyName = computed(() => 'Switch & Save');
const companyRegNo = computed(() => '15051352');
const companyVat = computed(() => 'GB50915794');

const availableSections = [
    { type: 'header', label: 'Header', icon: '📋' },
    { type: 'text', label: 'Text', icon: '📝' },
    { type: 'image', label: 'Image', icon: '🖼️' },
    { type: 'button', label: 'Button', icon: '🔘' },
    { type: 'two_column', label: 'Two Columns', icon: '📊' },
    { type: 'footer', label: 'Footer', icon: '📄' },
];

const availableVariables = [
    '{{customer_name}}',
    '{{first_name}}',
    '{{customer_email}}',
    '{{customer_phone}}',
    '{{appointment_date}}',
    '{{appointment_time}}',
    '{{company_name}}',
    '{{company_phone}}',
    '{{company_address}}',
    '{{lead_id}}',
    '{{product_name}}',
];

const form = reactive({
    name: '',
    category: 'custom',
    subject: '',
    description: '',
    content: {
        sections: [],
        preview_line: '',
    },
});

const hasStyleSupport = (section) => {
    if (!section) return false;
    return ['header', 'text', 'button', 'two_column', 'footer'].includes(section.type);
};

// Style target: for text section = selected block; for others = section content
const styleTarget = computed(() => {
    if (selectedSectionIndex.value == null) return null;
    const section = form.content.sections[selectedSectionIndex.value];
    if (!section) return null;
    // For text sections, style applies to the whole paragraph/section
    if (section.type === 'text') {
        return section.content || null;
    }
    if (['header', 'button', 'two_column', 'footer'].includes(section.type)) {
        return section.content || null;
    }
    return null;
});

const styleTargetLabel = computed(() => {
    const section = form.content.sections[selectedSectionIndex.value];
    if (!section) return 'Section style';
    if (section.type === 'text') {
        return 'Paragraph style';
    }
    return 'Section style';
});

/** Build inline style object from section or block font/color/size for live preview */
function getSectionPreviewStyle(sectionOrBlock) {
    if (!sectionOrBlock) return {};
    const c = sectionOrBlock?.content || sectionOrBlock;
    const style = {};
    if (c.font_family) style.fontFamily = c.font_family;
    if (c.font_size) style.fontSize = c.font_size;
    if (c.font_color) style.color = c.font_color;
    return style;
}

/** Get block style (for per-line text blocks) */
function getBlockPreviewStyle(block) {
    if (!block) return {};
    const style = {};
    if (block.font_family) style.fontFamily = block.font_family;
    if (block.font_size) style.fontSize = block.font_size;
    if (block.font_color) style.color = block.font_color;
    return style;
}

// Normalize section content - text uses blocks (per-line), others use section-level style
function normalizeSectionContent(section) {
    if (!section || !section.content) return section;
    if (section.type === 'text') {
        // Migrate old format { text } to blocks
        if (section.content.text !== undefined && !section.content.blocks) {
            const lines = (section.content.text || '').split('\n').filter(Boolean);
            section.content = {
                blocks: lines.length ? lines.map(t => ({ text: t, font_family: '', font_size: '', font_color: '' }))
                    : [{ text: '', font_family: '', font_size: '', font_color: '' }],
            };
        } else if (!section.content.blocks?.length) {
            section.content.blocks = [{ text: section.content.text || '', font_family: '', font_size: '', font_color: '' }];
        } else {
            section.content.blocks = section.content.blocks.map(b =>
                ({ text: b.text ?? '', font_family: b.font_family ?? '', font_size: b.font_size ?? '', font_color: b.font_color ?? '' })
            );
        }
    } else if (hasStyleSupport(section)) {
        section.content = { font_family: '', font_size: '', font_color: '', ...section.content };
    }
    return section;
}

// Initialize form from template prop
if (props.template) {
    form.name = props.template.name || '';
    form.category = props.template.category || 'custom';
    form.subject = props.template.subject || '';
    form.description = props.template.description || '';
    const content = props.template.content || { sections: [] };
    content.sections = (content.sections || []).map(normalizeSectionContent);
    content.preview_line = content.preview_line ?? '';
    form.content = content;
}

const addSection = (type) => {
    const section = {
        type,
        content: getDefaultSectionContent(type),
    };
    form.content.sections.push(section);
    selectedSectionIndex.value = form.content.sections.length - 1;
    selectedBlockIndex.value = type === 'text' ? 0 : null;
};

const getDefaultSectionContent = (type) => {
    const styleDefaults = { font_family: '', font_size: '', font_color: '' };
    const defaults = {
        header: { logo: '', text: '', ...styleDefaults },
        text: { blocks: [{ text: '', font_family: '', font_size: '', font_color: '' }] },
        image: { image_url: '', alt: '', link_url: '' },
        button: { text: 'Click Here', url: '#', ...styleDefaults },
        two_column: { left_text: '', right_text: '', ...styleDefaults },
        footer: { text: '', ...styleDefaults },
    };
    return defaults[type] || {};
};

const removeSection = (index) => {
    form.content.sections.splice(index, 1);
    if (selectedSectionIndex.value >= form.content.sections.length) {
        selectedSectionIndex.value = null;
    }
    if (selectedSectionIndex.value === null) {
        selectedBlockIndex.value = null;
    }
};

// Single-paragraph mode: keep API-compatible blocks array but only use index 0
const addTextBlock = () => {};
const removeTextBlock = () => {};

const duplicateSection = (index) => {
    const section = JSON.parse(JSON.stringify(form.content.sections[index]));
    form.content.sections.splice(index + 1, 0, section);
};

const moveSection = (index, direction) => {
    const newIndex = direction === 'up' ? index - 1 : index + 1;
    if (newIndex >= 0 && newIndex < form.content.sections.length) {
        const section = form.content.sections.splice(index, 1)[0];
        form.content.sections.splice(newIndex, 0, section);
        selectedSectionIndex.value = newIndex;
    }
};

const editSectionImage = (sectionIndex, field) => {
    currentImageEdit.value = { sectionIndex, field };
    showImageUpload.value = true;
};

const handleImageUpload = () => {
    // File selected, ready to upload
};

const uploadImage = async () => {
    const file = imageInput.value?.files[0];
    if (!file) {
        toast.error('Please select an image');
        return;
    }

    uploadingImage.value = true;
    const formData = new FormData();
    formData.append('image', file);

    try {
        const response = await axios.post('/api/email-templates/upload-image', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });

        // Update the section with the image URL
        const { sectionIndex, field } = currentImageEdit.value;
        form.content.sections[sectionIndex].content[field] = response.data.url;
        
        showImageUpload.value = false;
        toast.success('Image uploaded successfully');
    } catch (error) {
        console.error('Failed to upload image:', error);
        toast.error('Failed to upload image');
    } finally {
        uploadingImage.value = false;
    }
};

const insertVariable = (variable) => {
    if (selectedSectionIndex.value == null) return;
    const section = form.content.sections[selectedSectionIndex.value];
    if (section.type === 'text' && section.content?.blocks) {
        const idx = 0;
        section.content.blocks[idx].text += (section.content.blocks[idx].text ? ' ' : '') + variable + ' ';
    }
};

function autoResizeTextArea(event) {
    const el = event?.target;
    if (!el) return;
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
}

const loadPrebuiltTemplate = async () => {
    // Show modal to select pre-built template
    const category = form.category || 'welcome';
    try {
        const response = await axios.get(`/api/email-templates?category=${category}&active=1`);
        const prebuiltTemplates = response.data.filter(t => t.is_prebuilt);
        
        if (prebuiltTemplates.length > 0) {
            // Load the first pre-built template of this category
            const template = prebuiltTemplates[0];
            form.name = template.name;
            form.subject = template.subject;
            form.description = template.description;
            form.content = template.content;
            toast.success('Pre-built template loaded');
        } else {
            toast.info('No pre-built templates found for this category');
        }
    } catch (error) {
        console.error('Failed to load pre-built template:', error);
        toast.error('Failed to load pre-built template');
    }
};

const doTestSend = async () => {
    const email = testSendEmail.value?.trim();
    if (!email) {
        toast.error('Please enter an email address');
        return;
    }
    if (!form.subject) {
        toast.error('Please enter a subject');
        return;
    }
    if (!form.content.sections.length) {
        toast.error('Add at least one section to the template');
        return;
    }

    sendingTest.value = true;
    try {
        await axios.post('/api/email-templates/test-send', {
            email,
            subject: form.subject,
            content: form.content,
        });
        toast.success('Test email sent to ' + email);
        showTestSendModal.value = false;
        testSendEmail.value = '';
    } catch (error) {
        const msg = error.response?.data?.message || 'Failed to send test email';
        toast.error(msg);
    } finally {
        sendingTest.value = false;
    }
};

const saveTemplate = async () => {
    if (!form.name || !form.subject) {
        toast.error('Please fill in template name and subject');
        return;
    }

    saving.value = true;
    try {
        if (props.template?.id) {
            await axios.put(`/api/email-templates/${props.template.id}`, form);
            toast.success('Template updated successfully');
        } else {
            await axios.post('/api/email-templates', form);
            toast.success('Template created successfully');
        }
        emit('saved');
        emit('close');
    } catch (error) {
        console.error('Failed to save template:', error);
        toast.error(error.response?.data?.message || 'Failed to save template');
    } finally {
        saving.value = false;
    }
};
</script>

