<template>
    <div :class="builderOuterClass">
        <div :class="builderInnerClass">
            <!-- Header -->
            <div class="p-4 sm:p-6 border-b border-slate-200 flex flex-wrap items-start justify-between gap-3 shrink-0">
                <div>
                    <h2 class="text-xl font-semibold text-slate-900">
                        {{ template?.id ? 'Edit Template' : 'Create New Template' }}
                    </h2>
                    <p class="text-sm text-slate-500 mt-1">Build responsive email templates</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <RouterLink
                        v-if="layout === 'page'"
                        :to="{ name: 'templates', query: { tab: 'email' } }"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-slate-700 border border-slate-300 rounded-lg hover:bg-slate-50"
                    >
                        ← Back to templates
                    </RouterLink>
                    <button
                        v-else
                        type="button"
                        class="text-slate-400 hover:text-slate-600 p-1"
                        @click="$emit('close')"
                    >
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
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
                        <div class="flex items-start gap-2">
                            <input
                                id="skip-brand-footer"
                                v-model="form.content.skip_brand_footer"
                                type="checkbox"
                                class="mt-1 rounded border-slate-300"
                            />
                            <label for="skip-brand-footer" class="text-sm text-slate-700 cursor-pointer">
                                Skip default CRM footer (use when your HTML already includes unsubscribe and company block)
                            </label>
                        </div>
                    </div>

                    <!-- Available Sections -->
                    <div class="flex-1 overflow-y-auto p-4">
                        <h3 class="text-sm font-semibold text-slate-900 mb-3">Add Section</h3>
                        <p class="text-[11px] text-slate-500 mb-2 leading-snug">
                            Add <strong>Custom HTML</strong>, then use <strong>Edit with visual builder</strong> at the top (or in that block).
                        </p>
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

                <!-- Center - Template Builder (scroll whole pane so tall live preview + sections stay reachable) -->
                <div class="flex-1 flex flex-col min-h-0 overflow-y-auto">
                    <!-- Preview Toggle -->
                    <div class="p-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2 bg-slate-50">
                        <div class="flex flex-wrap gap-2 items-center">
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
                            <button
                                v-if="hasRawHtmlSection"
                                type="button"
                                class="px-3 py-1.5 text-sm rounded-lg border border-violet-300 bg-violet-50 text-violet-900 font-medium hover:bg-violet-100 transition-colors"
                                @click="openVisualBuilderModal"
                            >
                                🎨 Edit with visual builder
                            </button>
                        </div>
                        <button
                            @click="loadPrebuiltTemplate"
                            class="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50 shrink-0"
                        >
                            📋 Load Pre-built
                        </button>
                    </div>

                    <!-- Full HTML email preview (iframe — avoids broken v-html when body is a complete HTML document) -->
                    <div
                        v-if="hasRawHtmlSection"
                        class="border-b border-slate-200 bg-slate-800 shrink-0"
                        :class="previewMode === 'mobile' ? 'p-2' : 'p-3'"
                    >
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="text-xs font-medium text-slate-300">Live preview — same render as sent email (sample data)</span>
                            <button
                                type="button"
                                class="text-xs text-blue-400 hover:text-blue-300 shrink-0"
                                @click="runLivePreview"
                            >
                                Refresh
                            </button>
                        </div>
                        <div
                            :class="previewMode === 'mobile' ? 'max-w-sm mx-auto' : 'max-w-3xl mx-auto'"
                            class="rounded-lg border border-slate-600 bg-white shadow-inner overflow-hidden"
                        >
                            <iframe
                                v-if="livePreviewSrcdoc"
                                ref="previewIframeRef"
                                class="w-full min-h-[400px] bg-white border-0 block rounded-b-lg"
                                :style="{ height: livePreviewIframeHeight }"
                                title="Email preview"
                                sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox"
                                :srcdoc="livePreviewSrcdoc"
                                @load="onLivePreviewIframeLoad"
                            />
                            <div v-else-if="livePreviewLoading" class="p-8 text-center text-sm text-slate-500 bg-white">
                                Loading preview…
                            </div>
                            <div v-else-if="livePreviewError" class="p-4 text-sm text-red-600 bg-white">
                                {{ livePreviewError }}
                            </div>
                            <div v-else class="p-4 text-xs text-slate-500 bg-white">
                                Add HTML in the section below or wait for preview to load.
                            </div>
                        </div>
                    </div>

                    <!-- Template Canvas -->
                    <div class="flex-1 min-h-0 overflow-y-auto bg-slate-100 p-6">
                        <div
                            :class="[
                                'bg-white mx-auto shadow-lg min-w-0',
                                previewMode === 'mobile'
                                    ? 'max-w-sm'
                                    : hasRawHtmlSection
                                      ? 'max-w-6xl w-full'
                                      : 'max-w-2xl',
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

                                    <!-- Raw HTML: code here; visual editing opens from top toolbar -->
                                    <div v-if="section.type === 'raw_html'" class="p-4 bg-slate-50 border border-slate-200 rounded-lg space-y-3">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="text-xs text-slate-600 font-medium">Custom HTML</p>
                                            <button
                                                type="button"
                                                class="text-xs font-medium px-3 py-1.5 rounded-lg border border-violet-300 bg-violet-50 text-violet-900 hover:bg-violet-100"
                                                @click.stop="selectedSectionIndex = index; openVisualBuilderModal()"
                                            >
                                                🎨 Open visual builder
                                            </button>
                                        </div>
                                        <textarea
                                            v-model="section.content.html"
                                            rows="14"
                                            class="w-full font-mono text-xs border border-slate-300 rounded-lg px-2 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white resize-y min-h-[200px]"
                                            placeholder="HTML body fragment (merge tags like {{first_name}}). Use “Edit with visual builder” at the top for drag-and-drop."
                                            @click.stop="selectedSectionIndex = index"
                                        />
                                        <p class="text-xs text-slate-500">
                                            Tables and preset blocks are responsive for email (≈600px max width, mobile styles from the newsletter preset). Export HTML from the visual builder to back up or re-import elsewhere.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <div class="p-4 sm:p-6 border-t border-slate-200 flex flex-wrap justify-end gap-3 shrink-0">
                <button
                    type="button"
                    @click="handleBuilderCancel"
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

        <!-- Visual email builder: true full viewport (above app chrome) -->
        <div
            v-if="showVisualBuilderModal"
            class="fixed inset-0 z-[200] flex flex-col bg-white"
            role="dialog"
            aria-modal="true"
            aria-labelledby="visual-builder-title"
        >
            <div class="flex flex-wrap items-center gap-2 p-3 sm:p-4 border-b border-slate-200 shrink-0 bg-white shadow-sm">
                    <h3 id="visual-builder-title" class="text-base font-semibold text-slate-900">
                        Visual email builder
                    </h3>
                    <select
                        v-if="rawHtmlSectionIndices.length > 1"
                        :value="String(visualBuilderTargetIndex)"
                        class="text-sm border border-slate-300 rounded-lg px-2 py-1.5 bg-white max-w-[220px]"
                        @change="onVisualBuilderTargetSelect($event)"
                    >
                        <option v-for="i in rawHtmlSectionIndices" :key="i" :value="String(i)">
                            Custom HTML block {{ rawHtmlSectionIndices.indexOf(i) + 1 }}
                        </option>
                    </select>
                    <div class="flex flex-wrap gap-2 ml-auto items-center">
                        <button
                            type="button"
                            class="px-3 py-1.5 text-xs sm:text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                            @click="pasteHtmlOpen = !pasteHtmlOpen"
                        >
                            {{ pasteHtmlOpen ? 'Hide paste' : 'Paste HTML' }}
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1.5 text-xs sm:text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                            @click="triggerImportHtmlFile"
                        >
                            Import .html file
                        </button>
                        <input
                            ref="importHtmlInputRef"
                            type="file"
                            accept=".html,.htm,.txt,text/html"
                            class="hidden"
                            @change="onImportHtmlFile"
                        />
                        <button
                            type="button"
                            class="px-3 py-1.5 text-xs sm:text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                            @click="exportVisualBuilderHtml"
                        >
                            Export .html
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1.5 text-xs sm:text-sm border border-slate-300 rounded-lg hover:bg-slate-50"
                            @click="copyVisualBuilderHtml"
                        >
                            Copy HTML
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1.5 text-xs sm:text-sm rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50"
                            @click="cancelVisualBuilderModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            class="px-3 py-1.5 text-xs sm:text-sm rounded-lg bg-slate-900 text-white hover:bg-slate-800 font-medium"
                            @click="applyVisualBuilderModal"
                        >
                            Save to template
                        </button>
                        <button
                            type="button"
                            class="p-2 rounded-lg border border-slate-300 text-slate-500 hover:bg-slate-50 lg:ml-1"
                            title="Close visual builder"
                            aria-label="Close visual builder"
                            @click="cancelVisualBuilderModal"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
                <div v-if="pasteHtmlOpen" class="px-3 py-2 border-b border-slate-100 bg-slate-50 shrink-0 space-y-2">
                    <label class="block text-xs font-medium text-slate-600">Paste full HTML or body fragment — replaces the canvas</label>
                    <textarea
                        v-model="pasteHtmlBuffer"
                        rows="5"
                        class="w-full font-mono text-xs border border-slate-300 rounded-lg px-2 py-2"
                        placeholder="&lt;table&gt;… or full &lt;html&gt;…"
                    />
                    <button
                        type="button"
                        class="px-3 py-1.5 text-sm bg-violet-600 text-white rounded-lg hover:bg-violet-700"
                        @click="applyPasteHtml"
                    >
                        Replace canvas from paste
                    </button>
                </div>
                <div class="flex-1 min-h-0 overflow-hidden flex flex-col p-0">
                    <EmailVisualEditor
                        v-if="showVisualBuilderModal && visualBuilderTargetIndex >= 0"
                        :key="`vb-${visualBuilderModalKey}`"
                        ref="visualEditorRef"
                        v-model="visualBuilderDraft"
                        compact
                        :canvas-height="visualBuilderCanvasHeight"
                        class="h-full min-h-0 flex-1 flex flex-col"
                    />
                </div>
                <p class="text-[11px] text-slate-500 px-3 py-2 border-t border-slate-200 bg-slate-50 shrink-0">
                    Save to template applies HTML (inlined when possible). For images, select the image on the canvas → open <strong>Images</strong> / <strong>Assets</strong> on the right → upload (merge tags like
                    <code v-pre class="text-[10px]">{{header_logo_url}}</code>
                    stay in saved HTML until you replace the src).
                    Use <strong>Save Template</strong> on the edit page to store on the server.
                </p>
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
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick, defineAsyncComponent, h } from 'vue';
import axios from 'axios';
import { RouterLink, useRouter } from 'vue-router';
import { useToastStore } from '@/stores/toast';

const EmailVisualEditor = defineAsyncComponent({
    loader: () => import('./EmailVisualEditor.vue'),
    delay: 80,
    loadingComponent: {
        render() {
            return h(
                'div',
                { class: 'text-sm text-slate-500 p-6 text-center border border-dashed border-slate-300 rounded-lg' },
                'Loading visual editor…'
            );
        },
    },
});

const toast = useToastStore();

const props = defineProps({
    template: {
        type: Object,
        default: null,
    },
    /** `modal` = centered overlay (legacy). `page` = full route view under app layout. */
    layout: {
        type: String,
        default: 'modal',
        validator: (v) => ['modal', 'page'].includes(v),
    },
});

const emit = defineEmits(['close', 'saved']);

const router = useRouter();

const builderOuterClass = computed(() =>
    props.layout === 'modal'
        ? 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4'
        : 'w-full flex flex-col flex-1 min-h-[calc(100dvh-9rem)]'
);

const builderInnerClass = computed(() =>
    props.layout === 'modal'
        ? 'bg-white rounded-xl shadow-xl w-full max-w-7xl max-h-[95vh] overflow-hidden flex flex-col'
        : 'flex flex-col flex-1 min-h-0 w-full border border-slate-200 rounded-xl bg-white shadow-sm overflow-hidden'
);

/** GrapesJS height inside full-screen visual builder */
const visualBuilderCanvasHeight = computed(() => 'calc(100dvh - 10.5rem)');

function handleBuilderCancel() {
    if (props.layout === 'page') {
        router.push({ name: 'templates', query: { tab: 'email' } });
    } else {
        emit('close');
    }
}

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

const livePreviewSrcdoc = ref('');
const livePreviewLoading = ref(false);
const livePreviewError = ref('');
const previewIframeRef = ref(null);
/** Full email height inside iframe (was fixed 560px / 640px, which clipped long templates). */
const livePreviewIframeHeight = ref('520px');
let livePreviewDebounce = null;
let previewIframeResizeTimers = [];

/** Full-screen GrapesJS modal */
const showVisualBuilderModal = ref(false);
const visualBuilderDraft = ref('');
const visualBuilderTargetIndex = ref(-1);
const visualBuilderModalKey = ref(0);
/** Snapshot of each Custom HTML block when modal opened (Cancel restores all). */
const visualBuilderSnapshots = ref({});
const visualEditorRef = ref(null);
const importHtmlInputRef = ref(null);
const pasteHtmlOpen = ref(false);
const pasteHtmlBuffer = ref('');

const rawHtmlSectionIndices = computed(() =>
    form.content.sections.map((s, i) => (s.type === 'raw_html' ? i : -1)).filter((i) => i >= 0)
);

function resolveRawHtmlTargetIndex() {
    const sections = form.content.sections;
    const sel = selectedSectionIndex.value;
    if (sel != null && sections[sel]?.type === 'raw_html') {
        return sel;
    }
    return sections.findIndex((s) => s.type === 'raw_html');
}

function flushVisualBuilderToSection() {
    const idx = visualBuilderTargetIndex.value;
    if (idx < 0 || !showVisualBuilderModal.value) {
        return;
    }
    const section = form.content.sections[idx];
    if (!section || section.type !== 'raw_html') {
        return;
    }
    const html = visualEditorRef.value?.exportHtml?.() ?? visualBuilderDraft.value;
    section.content.html = html;
}

function openVisualBuilderModal() {
    const idx = resolveRawHtmlTargetIndex();
    if (idx < 0) {
        toast.error('Add a Custom HTML section first (left sidebar).');
        return;
    }
    const snaps = {};
    rawHtmlSectionIndices.value.forEach((i) => {
        snaps[i] = form.content.sections[i].content?.html ?? '';
    });
    visualBuilderSnapshots.value = snaps;
    visualBuilderTargetIndex.value = idx;
    visualBuilderDraft.value = form.content.sections[idx].content?.html ?? '';
    pasteHtmlOpen.value = false;
    pasteHtmlBuffer.value = '';
    showVisualBuilderModal.value = true;
    visualBuilderModalKey.value += 1;
}

function onVisualBuilderTargetSelect(ev) {
    const newIdx = Number(ev.target?.value);
    if (Number.isNaN(newIdx) || newIdx < 0) {
        return;
    }
    flushVisualBuilderToSection();
    visualBuilderTargetIndex.value = newIdx;
    visualBuilderDraft.value = form.content.sections[newIdx].content?.html ?? '';
    visualBuilderModalKey.value += 1;
}

function cancelVisualBuilderModal() {
    const snaps = visualBuilderSnapshots.value || {};
    Object.keys(snaps).forEach((k) => {
        const i = Number(k);
        const section = form.content.sections[i];
        if (section?.type === 'raw_html') {
            section.content.html = snaps[k];
        }
    });
    showVisualBuilderModal.value = false;
    scheduleLivePreview();
}

function applyVisualBuilderModal() {
    flushVisualBuilderToSection();
    showVisualBuilderModal.value = false;
    scheduleLivePreview();
    toast.success('HTML saved to this template. Use Save Template to store on the server.');
}

function exportVisualBuilderHtml() {
    const html = visualEditorRef.value?.exportHtml?.() ?? visualBuilderDraft.value;
    const safeName = String(form.name || 'email-template').replace(/[^\w\-]+/g, '-').slice(0, 80) || 'email-template';
    const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${safeName}.html`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success('HTML downloaded');
}

async function copyVisualBuilderHtml() {
    const html = visualEditorRef.value?.exportHtml?.() ?? visualBuilderDraft.value;
    try {
        await navigator.clipboard.writeText(html);
        toast.success('HTML copied to clipboard');
    } catch {
        toast.error('Clipboard blocked — use Export .html instead');
    }
}

function triggerImportHtmlFile() {
    importHtmlInputRef.value?.click();
}

function onImportHtmlFile(ev) {
    const file = ev.target?.files?.[0];
    ev.target.value = '';
    if (!file) {
        return;
    }
    const reader = new FileReader();
    reader.onload = () => {
        const text = String(reader.result || '');
        visualBuilderDraft.value = text;
        nextTick(() => visualEditorRef.value?.loadHtml?.(text));
        toast.success('HTML loaded into the editor');
    };
    reader.onerror = () => toast.error('Could not read file');
    reader.readAsText(file);
}

function applyPasteHtml() {
    const text = pasteHtmlBuffer.value || '';
    visualBuilderDraft.value = text;
    nextTick(() => visualEditorRef.value?.loadHtml?.(text));
    pasteHtmlOpen.value = false;
    toast.success('Canvas updated from paste');
}

function clearPreviewIframeResizeTimers() {
    previewIframeResizeTimers.forEach((id) => clearTimeout(id));
    previewIframeResizeTimers = [];
}

/**
 * Match iframe height to rendered document so the full template is visible when you scroll this pane.
 * Re-run briefly after load so images (e.g. partner strip) can affect height.
 */
function onLivePreviewIframeLoad() {
    const iframe = previewIframeRef.value;
    if (!iframe || !livePreviewSrcdoc.value) {
        return;
    }
    try {
        const doc = iframe.contentDocument || iframe.contentWindow?.document;
        if (!doc?.body) {
            return;
        }
        const h = Math.max(
            doc.documentElement?.scrollHeight || 0,
            doc.body?.scrollHeight || 0,
            400
        );
        const capped = Math.min(h + 32, 20000);
        livePreviewIframeHeight.value = `${capped}px`;
    } catch {
        livePreviewIframeHeight.value = previewMode.value === 'mobile' ? '720px' : '600px';
    }
}

function scheduleLivePreviewIframeResize() {
    clearPreviewIframeResizeTimers();
    nextTick(() => {
        onLivePreviewIframeLoad();
        previewIframeResizeTimers = [200, 600, 1600].map((ms) =>
            setTimeout(onLivePreviewIframeLoad, ms)
        );
    });
}

const hasRawHtmlSection = computed(() =>
    (form.content.sections || []).some((s) => s.type === 'raw_html')
);

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
    { type: 'raw_html', label: 'Custom HTML', icon: '🧩' },
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
    '{{company_website}}',
    '{{app_url}}',
    '{{header_logo_url}}',
    '{{email_welcome_dir_url}}',
    '{{logo_src}}',
    '{{social_facebook_url}}',
    '{{social_linkedin_url}}',
    '{{social_instagram_url}}',
    '{{social_tiktok_url}}',
    '{{current_year}}',
    '{{unsubscribe_url}}',
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
        skip_brand_footer: false,
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
    } else if (section.type === 'raw_html') {
        section.content = { html: section.content?.html ?? '' };
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
    content.skip_brand_footer = Boolean(content.skip_brand_footer);
    form.content = content;
}

function scheduleLivePreview() {
    if (!hasRawHtmlSection.value) {
        livePreviewSrcdoc.value = '';
        livePreviewError.value = '';
        livePreviewLoading.value = false;
        return;
    }
    clearTimeout(livePreviewDebounce);
    livePreviewDebounce = setTimeout(runLivePreview, 450);
}

async function runLivePreview() {
    if (!hasRawHtmlSection.value) {
        livePreviewSrcdoc.value = '';
        return;
    }
    livePreviewLoading.value = true;
    livePreviewError.value = '';
    try {
        const { data } = await axios.post('/api/email-templates/preview-html', { content: form.content });
        livePreviewSrcdoc.value = typeof data.html === 'string' ? data.html : '';
    } catch (error) {
        livePreviewSrcdoc.value = '';
        livePreviewError.value =
            error.response?.data?.message || error.message || 'Could not load preview';
    } finally {
        livePreviewLoading.value = false;
        scheduleLivePreviewIframeResize();
    }
}

watch(
    () => form.content,
    () => scheduleLivePreview(),
    { deep: true }
);

watch([livePreviewSrcdoc, previewMode], () => {
    livePreviewIframeHeight.value = previewMode.value === 'mobile' ? '560px' : '480px';
    scheduleLivePreviewIframeResize();
});

onMounted(() => {
    scheduleLivePreview();
    nextTick(() => {
        const idx = form.content.sections.findIndex((s) => s.type === 'raw_html');
        if (idx >= 0 && selectedSectionIndex.value == null) {
            selectedSectionIndex.value = idx;
        }
    });
});

onUnmounted(() => {
    clearTimeout(livePreviewDebounce);
    clearPreviewIframeResizeTimers();
});

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
        raw_html: { html: '' },
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
    } else if (section.type === 'raw_html' && section.content) {
        const selIdx = selectedSectionIndex.value;
        const cur = section.content.html || '';
        const sep = cur.length && !/\s$/.test(cur) ? ' ' : '';
        const next = cur + sep + variable + ' ';
        section.content.html = next;
        if (showVisualBuilderModal.value && visualBuilderTargetIndex.value === selIdx) {
            nextTick(() => visualEditorRef.value?.loadHtml?.(next));
        }
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
        if (props.layout === 'page') {
            router.push({ name: 'templates', query: { tab: 'email' } });
        } else {
            emit('close');
        }
    } catch (error) {
        console.error('Failed to save template:', error);
        toast.error(error.response?.data?.message || 'Failed to save template');
    } finally {
        saving.value = false;
    }
};
</script>

