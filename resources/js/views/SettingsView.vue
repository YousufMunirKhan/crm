<template>
    <div class="page-narrow">
        <p class="page-lead">Manage application settings and integrations</p>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center py-12" role="status" aria-live="polite">
            <span class="spinner" aria-hidden="true"></span>
            <span class="sr-only">Loading settings…</span>
        </div>

        <TabGroup
            v-else
            as="div"
            class="space-y-6"
            :selected-index="tabIndex"
            @change="handleTabChange"
        >
            <!-- Section Navigation - horizontal scroll, mobile responsive -->
            <div class="card">
                <TabList class="tab-list">
                    <Tab
                        v-for="section in sections"
                        :key="section.id"
                        v-slot="{ selected }"
                        as="template"
                    >
                        <button type="button" :class="['tab', selected ? 'tab-active' : '']">
                            <span class="inline-flex items-center gap-2">
                                <component :is="section.icon" class="icon-sm" aria-hidden="true" />
                                {{ section.name }}
                            </span>
                        </button>
                    </Tab>
                </TabList>
            </div>

            <TabPanels>
                <!-- Branding Section -->
                <TabPanel :unmount="false">
                    <BaseCard>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 bg-primary-100 text-primary-700 rounded-xl flex items-center justify-center shrink-0">
                                    <PaintBrushIcon class="icon" aria-hidden="true" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="card-title">Branding</h2>
                                    <p class="card-subtitle">Logo and company identity</p>
                                </div>
                            </div>
                        </template>

                        <!-- Logo Upload -->
                        <div class="space-y-4">
                            <div>
                                <h3 class="subsection-title">Company Logo</h3>
                                <p class="text-xs text-slate-500 mb-3">This logo will appear on the login screen and dashboard. Recommended size: 200x60px</p>

                                <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6">
                                    <!-- Current Logo Preview -->
                                    <div class="w-full sm:w-48 h-24 border-2 border-dashed border-slate-300 rounded-card flex items-center justify-center bg-slate-50 shrink-0">
                                        <img
                                            v-if="settings.logo_url && !logoPreviewFailed"
                                            :src="settings.logo_url"
                                            alt="Company logo"
                                            class="max-w-full max-h-full object-contain p-2"
                                            @error="logoPreviewFailed = true"
                                        >
                                        <span v-else class="text-slate-500 text-sm px-2 text-center">
                                            {{ settings.logo_url ? 'Uploaded file is missing' : 'No logo uploaded' }}
                                        </span>
                                    </div>

                                    <!-- Upload Controls -->
                                    <div class="space-y-3">
                                        <label class="form-label" for="settingsview-logo-file">Choose logo</label>
                                        <input
                                            id="settingsview-logo-file"
                                            type="file"
                                            ref="logoInput"
                                            @change="handleLogoUpload"
                                            accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp"
                                            class="form-file"
                                        >
                                        <BaseButton
                                            v-if="settings.logo_url"
                                            variant="ghost-danger"
                                            size="sm"
                                            @click="deleteLogo"
                                        >
                                            <template #icon>
                                                <TrashIcon class="icon-sm" aria-hidden="true" />
                                            </template>
                                            Remove logo
                                        </BaseButton>
                                        <p v-if="uploadingLogo" class="callout callout-info" role="status" aria-live="polite">Uploading...</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Favicon -->
                            <div class="pt-6 border-t border-slate-200">
                                <h3 class="subsection-title">Favicon</h3>
                                <p class="text-xs text-slate-500 mb-3">
                                    Shown in the browser tab, sidebar (if no logo), and PWA install prompt. Square PNG or ICO works best (32×32 or 64×64; max 1&nbsp;MB).
                                </p>
                                <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6">
                                    <div class="w-16 h-16 border-2 border-dashed border-slate-300 rounded-card flex items-center justify-center bg-slate-50 shrink-0 overflow-hidden">
                                        <img
                                            v-if="settings.favicon_url && !faviconPreviewFailed"
                                            :src="settings.favicon_url"
                                            alt=""
                                            class="w-full h-full object-cover"
                                            @error="faviconPreviewFailed = true"
                                        >
                                        <span v-else class="text-slate-500 text-xs text-center px-1">
                                            {{ settings.favicon_url ? 'File missing' : 'No favicon' }}
                                        </span>
                                    </div>
                                    <div class="space-y-3">
                                        <label class="form-label" for="settingsview-favicon-file">Choose favicon</label>
                                        <input
                                            id="settingsview-favicon-file"
                                            type="file"
                                            ref="faviconInput"
                                            @change="handleFaviconUpload"
                                            accept=".ico,.png,.jpg,.jpeg,.gif,.svg,.webp,image/x-icon,image/png,image/jpeg,image/gif,image/svg+xml,image/webp"
                                            class="form-file"
                                        >
                                        <BaseButton
                                            v-if="settings.favicon_url"
                                            variant="ghost-danger"
                                            size="sm"
                                            @click="deleteFavicon"
                                        >
                                            <template #icon>
                                                <TrashIcon class="icon-sm" aria-hidden="true" />
                                            </template>
                                            Remove favicon
                                        </BaseButton>
                                        <p v-if="uploadingFavicon" class="callout callout-info" role="status" aria-live="polite">Uploading...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </BaseCard>
                </TabPanel>

                <!-- Company Information Section -->
                <TabPanel :unmount="false">
                    <BaseCard>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 bg-slate-100 text-slate-700 rounded-xl flex items-center justify-center shrink-0">
                                    <BuildingOfficeIcon class="icon" aria-hidden="true" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="card-title">Company Information</h2>
                                    <p class="card-subtitle">Basic company details</p>
                                </div>
                            </div>
                        </template>

                        <div class="form-grid-2">
                            <div>
                                <label class="form-label" for="settingsview-company-name">Company Name</label>
                                <input id="settingsview-company-name"
                                    v-model="settings.company_name"
                                    type="text"
                                    class="form-input"
                                    placeholder="Switch & Save"
                                >
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-admin-notification-email">Admin Notification Email</label>
                                <input id="settingsview-admin-notification-email"
                                    v-model="settings.admin_notification_email"
                                    type="email"
                                    class="form-input"
                                    placeholder="admin@company.com"
                                >
                                <p class="form-hint">Admin copy of appointment notifications. Save here and configure SMTP below for emails to send.</p>
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-company-email">Company Email</label>
                                <input id="settingsview-company-email"
                                    v-model="settings.company_email"
                                    type="email"
                                    class="form-input"
                                    placeholder="hello@switch-and-save.uk"
                                >
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-company-phone">Company Phone</label>
                                <input id="settingsview-company-phone"
                                    v-model="settings.company_phone"
                                    type="text"
                                    class="form-input"
                                    placeholder="+44 7340 529757"
                                >
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-company-website">Company Website</label>
                                <input id="settingsview-company-website"
                                    v-model="settings.company_website"
                                    type="url"
                                    class="form-input"
                                    placeholder="https://switch-and-save.uk"
                                >
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" for="settingsview-crm-url-for-ticket-emails">CRM URL (for ticket emails)</label>
                                <input id="settingsview-crm-url-for-ticket-emails"
                                    v-model="settings.crm_base_url"
                                    type="url"
                                    class="form-input"
                                    placeholder="https://crm.yourdomain.com"
                                >
                                <p class="form-hint">
                                    Full address where users log in to this CRM (no trailing slash). Ticket notification links use this. If empty, the server <code class="text-xs bg-slate-100 px-1 rounded">APP_URL</code> from <code class="text-xs bg-slate-100 px-1 rounded">.env</code> is used—set this if they differ (e.g. API on one host, CRM on another).
                                </p>
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-company-registration-no">Company Registration No.</label>
                                <input id="settingsview-company-registration-no"
                                    v-model="settings.company_registration_no"
                                    type="text"
                                    class="form-input"
                                    placeholder="e.g., 15051352"
                                >
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-vat-registration-no">VAT Registration No.</label>
                                <input id="settingsview-vat-registration-no"
                                    v-model="settings.company_vat"
                                    type="text"
                                    class="form-input"
                                    placeholder="e.g., GB50915794"
                                >
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" for="settingsview-company-address">Company Address</label>
                                <textarea id="settingsview-company-address"
                                    v-model="settings.company_address"
                                    rows="2"
                                    class="form-textarea"
                                    placeholder="3A Perry Common Road, Erdington&#10;Birmingham, B23 7AB"
                                ></textarea>
                                <p class="form-hint">You can use line breaks for multi-line addresses. Shown in email templates and invoices.</p>
                            </div>

                            <!-- Social Media URLs (shown in email templates) -->
                            <div class="sm:col-span-2 mt-4 pt-4 border-t border-slate-100">
                                <h3 class="subsection-title">Social Media Links</h3>
                                <p class="text-xs text-slate-500 mb-3">These appear as icons in email template footers. Leave blank to hide.</p>
                                <div class="form-grid-2">
                                    <div>
                                        <label class="form-label" for="settingsview-facebook">Facebook</label>
                                        <input id="settingsview-facebook"
                                            v-model="settings.social_facebook_url"
                                            type="url"
                                            class="form-input"
                                            placeholder="https://facebook.com/yourpage"
                                        >
                                    </div>
                                    <div>
                                        <label class="form-label" for="settingsview-twitter-x">Twitter / X</label>
                                        <input id="settingsview-twitter-x"
                                            v-model="settings.social_twitter_url"
                                            type="url"
                                            class="form-input"
                                            placeholder="https://twitter.com/yourhandle"
                                        >
                                    </div>
                                    <div>
                                        <label class="form-label" for="settingsview-linkedin">LinkedIn</label>
                                        <input id="settingsview-linkedin"
                                            v-model="settings.social_linkedin_url"
                                            type="url"
                                            class="form-input"
                                            placeholder="https://linkedin.com/company/yourcompany"
                                        >
                                    </div>
                                    <div>
                                        <label class="form-label" for="settingsview-instagram">Instagram</label>
                                        <input id="settingsview-instagram"
                                            v-model="settings.social_instagram_url"
                                            type="url"
                                            class="form-input"
                                            placeholder="https://instagram.com/yourhandle"
                                        >
                                    </div>
                                    <div>
                                        <label class="form-label" for="settingsview-tiktok">TikTok</label>
                                        <input id="settingsview-tiktok"
                                            v-model="settings.social_tiktok_url"
                                            type="url"
                                            class="form-input"
                                            placeholder="https://tiktok.com/@yourhandle"
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Details Section -->
                        <div class="mt-8 pt-8 border-t border-slate-200">
                            <h3 class="subsection-title">Payment Details (for Invoices)</h3>
                            <div class="form-grid-2">
                                <div class="sm:col-span-2">
                                    <label class="form-label" for="settingsview-account-name">Account Name <span class="form-required" aria-hidden="true">*</span></label>
                                    <input id="settingsview-account-name"
                                        v-model="settings.payment_account_name"
                                        type="text"
                                        class="form-input"
                                        placeholder="SWITCH&SAVE BUSINESS SERVICES LTD"
                                    >
                                    <p class="form-hint">Account name for payment instructions</p>
                                </div>
                                <div>
                                    <label class="form-label" for="settingsview-sort-code">Sort Code <span class="form-required" aria-hidden="true">*</span></label>
                                    <input id="settingsview-sort-code"
                                        v-model="settings.payment_sort_code"
                                        type="text"
                                        class="form-input"
                                        placeholder="30-99-50"
                                        maxlength="8"
                                    >
                                    <p class="form-hint">Bank sort code (format: XX-XX-XX)</p>
                                </div>
                                <div>
                                    <label class="form-label" for="settingsview-account-number">Account Number <span class="form-required" aria-hidden="true">*</span></label>
                                    <input id="settingsview-account-number"
                                        v-model="settings.payment_account_number"
                                        type="text"
                                        class="form-input"
                                        placeholder="46776562"
                                        maxlength="20"
                                    >
                                    <p class="form-hint">Bank account number</p>
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="form-label" for="settingsview-payment-terms-note-optional">Payment Terms Note (Optional)</label>
                                    <input id="settingsview-payment-terms-note-optional"
                                        v-model="settings.payment_terms_note"
                                        type="text"
                                        class="form-input"
                                        placeholder="e.g., Payment due within 30 days"
                                    >
                                    <p class="form-hint">Additional payment terms note (invoice due date will be shown automatically)</p>
                                </div>
                            </div>
                        </div>

                        <template #footer>
                            <BaseButton
                                variant="primary"
                                :loading="saving"
                                block-mobile
                                @click="saveCompanySettings"
                            >
                                {{ saving ? 'Saving...' : 'Save Changes' }}
                            </BaseButton>
                        </template>
                    </BaseCard>
                </TabPanel>

                <!-- Email/SMTP Section -->
                <TabPanel :unmount="false">
                    <BaseCard>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 bg-primary-100 text-primary-700 rounded-xl flex items-center justify-center shrink-0">
                                    <EnvelopeIcon class="icon" aria-hidden="true" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="card-title">Email / SMTP Settings</h2>
                                    <p class="card-subtitle">Configure SMTP for all outgoing emails (appointments to customer, admin, and assignee; invoices; etc.)</p>
                                </div>
                            </div>
                        </template>

                        <div class="form-grid-2">
                            <div>
                                <label class="form-label" for="settingsview-smtp-host">SMTP Host</label>
                                <input id="settingsview-smtp-host"
                                    v-model="smtpSettings.smtp_host"
                                    type="text"
                                    class="form-input"
                                    placeholder="smtp.gmail.com"
                                >
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-smtp-port">SMTP Port</label>
                                <input id="settingsview-smtp-port"
                                    v-model="smtpSettings.smtp_port"
                                    type="number"
                                    class="form-input"
                                    placeholder="587"
                                >
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-smtp-username">SMTP Username</label>
                                <input id="settingsview-smtp-username"
                                    v-model="smtpSettings.smtp_username"
                                    type="text"
                                    class="form-input"
                                    placeholder="your@email.com"
                                >
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-smtp-password">SMTP Password</label>
                                <input id="settingsview-smtp-password"
                                    v-model="smtpSettings.smtp_password"
                                    type="password"
                                    class="form-input"
                                    placeholder="••••••••"
                                >
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-encryption">Encryption</label>
                                <select id="settingsview-encryption"
                                    v-model="smtpSettings.smtp_encryption"
                                    class="form-select"
                                >
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="none">None</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-from-email">From Email</label>
                                <input id="settingsview-from-email"
                                    v-model="smtpSettings.smtp_from_email"
                                    type="email"
                                    class="form-input"
                                    placeholder="noreply@company.com"
                                >
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" for="settingsview-from-name">From Name</label>
                                <input id="settingsview-from-name"
                                    v-model="smtpSettings.smtp_from_name"
                                    type="text"
                                    class="form-input"
                                    placeholder="Company Name CRM"
                                >
                            </div>
                        </div>

                        <div class="mt-6 rounded-card border border-slate-200 bg-slate-50 p-4">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h3 class="subsection-title">Customer welcome email</h3>
                                    <p class="text-sm text-slate-500">
                                        Sent automatically when a new customer record is created with an email address.
                                    </p>
                                </div>
                                <span class="text-xs font-medium text-slate-500 sm:text-right">
                                    Default: Welcome Template (Generic For All User)
                                </span>
                            </div>

                            <div class="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-[1fr,auto] lg:items-end">
                                <div>
                                    <label class="form-label" for="settingsview-template">Template</label>
                                    <select id="settingsview-template"
                                        v-model="welcomeEmailTemplateId"
                                        class="form-select"
                                    >
                                        <option value="">Use default generic welcome template</option>
                                        <option
                                            v-for="template in emailTemplates"
                                            :key="template.id"
                                            :value="String(template.id)"
                                        >
                                            {{ template.name }}{{ template.subject ? ` - ${template.subject}` : '' }}
                                        </option>
                                    </select>
                                    <p class="form-hint">
                                        If no custom template is selected, the CRM will send the active template named
                                        "Welcome Template (Generic For All User)".
                                    </p>
                                </div>
                                <BaseButton
                                    variant="primary"
                                    block-mobile
                                    :loading="savingWelcomeEmailTemplate"
                                    @click="saveWelcomeEmailTemplate"
                                >
                                    {{ savingWelcomeEmailTemplate ? 'Saving...' : 'Save welcome template' }}
                                </BaseButton>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end">
                            <BaseButton
                                variant="primary"
                                block-mobile
                                :loading="savingSmtp"
                                @click="saveSmtpSettings"
                            >
                                {{ savingSmtp ? 'Saving...' : 'Save SMTP Settings' }}
                            </BaseButton>

                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                <div>
                                    <label class="sr-only" for="settingsview-test-email">Test email address</label>
                                    <input
                                        id="settingsview-test-email"
                                        v-model="testEmail"
                                        type="email"
                                        placeholder="Test email address"
                                        class="form-input"
                                    >
                                </div>
                                <BaseButton
                                    variant="soft"
                                    block-mobile
                                    :loading="testingSmtp"
                                    @click="testSmtpConnection"
                                >
                                    <template #icon>
                                        <PaperAirplaneIcon class="icon-sm" aria-hidden="true" />
                                    </template>
                                    {{ testingSmtp ? 'Testing...' : 'Send Test' }}
                                </BaseButton>
                            </div>
                        </div>
                    </BaseCard>
                </TabPanel>

                <!-- SMS Section (VoodooSMS) -->
                <TabPanel :unmount="false">
                    <BaseCard>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 bg-success-100 text-success-700 rounded-xl flex items-center justify-center shrink-0">
                                    <DevicePhoneMobileIcon class="icon" aria-hidden="true" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="card-title">SMS Settings (VoodooSMS)</h2>
                                    <p class="card-subtitle">Configure VoodooSMS provider for sending messages</p>
                                </div>
                            </div>
                        </template>

                        <div class="form-grid-2">
                            <div>
                                <label class="form-label" for="settingsview-api-key-uid">API Key (UID) <span class="form-required" aria-hidden="true">*</span></label>
                                <input id="settingsview-api-key-uid"
                                    v-model="smsSettings.sms_api_key"
                                    type="text"
                                    class="form-input"
                                    placeholder="Your VoodooSMS UID"
                                >
                                <p class="form-hint">Your VoodooSMS username/UID</p>
                            </div>

                            <div>
                                <label class="form-label" for="settingsview-secret-key-password">Secret Key (Password) <span class="form-required" aria-hidden="true">*</span></label>
                                <input id="settingsview-secret-key-password"
                                    v-model="smsSettings.sms_secret_key"
                                    type="password"
                                    class="form-input"
                                    placeholder="••••••••"
                                >
                                <p class="form-hint">Your VoodooSMS password</p>
                            </div>

                            <div>
                                <label class="form-label" for="settingsview-sender-name">Sender Name</label>
                                <input id="settingsview-sender-name"
                                    v-model="smsSettings.sms_sender_name"
                                    type="text"
                                    maxlength="11"
                                    class="form-input"
                                    placeholder="EPOS"
                                >
                                <p class="form-hint">Max 11 characters (appears as sender)</p>
                            </div>

                            <div class="sm:col-span-2">
                                <label class="form-label" for="settingsview-default-message">Default Message</label>
                                <textarea id="settingsview-default-message"
                                    v-model="smsSettings.sms_default_message"
                                    rows="3"
                                    class="form-textarea"
                                    placeholder="Default SMS message (optional)"
                                ></textarea>
                                <p class="form-hint">Used when no message is provided</p>
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-end">
                            <BaseButton
                                variant="primary"
                                block-mobile
                                :loading="savingSms"
                                @click="saveSmsSettings"
                            >
                                {{ savingSms ? 'Saving...' : 'Save SMS Settings' }}
                            </BaseButton>

                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                <div>
                                    <label class="sr-only" for="settingsview-test-phone">Test phone number</label>
                                    <input
                                        id="settingsview-test-phone"
                                        v-model="testSmsPhone"
                                        type="text"
                                        placeholder="Test phone number (077... or 447...)"
                                        class="form-input"
                                    >
                                </div>
                                <BaseButton
                                    variant="soft"
                                    block-mobile
                                    :loading="testingSms"
                                    @click="testSmsConnection"
                                >
                                    <template #icon>
                                        <PaperAirplaneIcon class="icon-sm" aria-hidden="true" />
                                    </template>
                                    {{ testingSms ? 'Sending...' : 'Send Test SMS' }}
                                </BaseButton>
                            </div>
                        </div>
                    </BaseCard>
                </TabPanel>

                <!-- WhatsApp Section -->
                <TabPanel :unmount="false">
                    <BaseCard>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 bg-success-100 text-success-700 rounded-xl flex items-center justify-center shrink-0">
                                    <ChatBubbleLeftRightIcon class="icon" aria-hidden="true" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="card-title">WhatsApp Settings</h2>
                                    <p class="card-subtitle">Configure Meta WhatsApp Business Cloud API</p>
                                </div>
                            </div>
                        </template>

                        <div v-if="whatsappCloudLoading" class="flex justify-center py-8" role="status" aria-live="polite">
                            <span class="spinner" aria-hidden="true"></span>
                            <span class="sr-only">Loading WhatsApp settings…</span>
                        </div>

                        <div v-else class="space-y-4">
                            <div class="callout callout-info">
                                <p>
                                    <strong>Note:</strong> This is the single WhatsApp integration used by CRM sends and webhooks.
                                    <br>
                                    <span class="text-xs">Webhook URL: <code class="bg-primary-100 px-2 py-1 rounded">{{ webhookUrl }}</code></span>
                                </p>
                            </div>

                            <div class="form-grid-2">
                                <div>
                                    <label class="form-label" for="settingsview-waba-id-whatsapp-business-account-id">WABA ID (WhatsApp Business Account ID) <span class="form-required" aria-hidden="true">*</span></label>
                                    <input id="settingsview-waba-id-whatsapp-business-account-id"
                                        v-model="whatsappCloudSettings.waba_id"
                                        type="text"
                                        class="form-input"
                                        placeholder="123456789"
                                    >
                                    <p class="form-hint">From Meta Business Dashboard</p>
                                </div>

                                <div>
                                    <label class="form-label" for="settingsview-phone-number-id">Phone Number ID <span class="form-required" aria-hidden="true">*</span></label>
                                    <input id="settingsview-phone-number-id"
                                        v-model="whatsappCloudSettings.phone_number_id"
                                        type="text"
                                        class="form-input"
                                        placeholder="987654321"
                                    >
                                    <p class="form-hint">From Meta Business Dashboard</p>
                                </div>

                                <div>
                                    <label class="form-label" for="settingsview-meta-app-id-optional">Meta App ID (optional)</label>
                                    <input id="settingsview-meta-app-id-optional"
                                        v-model="whatsappCloudSettings.meta_app_id"
                                        type="text"
                                        class="form-input"
                                        placeholder="From Meta for Developers → App → Basic"
                                    >
                                    <p class="form-hint">Used only so &quot;Test connection&quot; can check if your token may send messages</p>
                                </div>

                                <div>
                                    <label class="form-label" for="settingsview-meta-app-secret-optional">Meta App Secret (optional)</label>
                                    <input id="settingsview-meta-app-secret-optional"
                                        v-model="whatsappCloudSettings.meta_app_secret"
                                        type="password"
                                        autocomplete="new-password"
                                        class="form-input"
                                        placeholder="Leave blank to keep existing secret"
                                    >
                                    <p class="form-hint">Stored encrypted like the access token. Same app as above.</p>
                                </div>

                                <div class="sm:col-span-2">
                                    <label class="form-label" for="settingsview-access-token">Access Token <span class="form-required" aria-hidden="true">*</span></label>
                                    <input id="settingsview-access-token"
                                        v-model="whatsappCloudSettings.access_token"
                                        type="password"
                                        class="form-input"
                                        placeholder="EAAN..."
                                    >
                                    <p class="form-hint">Permanent access token from Meta</p>
                                    <p class="callout callout-warning text-xs mt-2">
                                        If sends fail with <strong>(#10) permission</strong>, your token needs <strong>whatsapp_business_messaging</strong> for this Phone Number ID. Fill <strong>Meta App ID</strong> and <strong>Meta App Secret</strong> above, save, then use <strong>Test connection</strong> to confirm send permission (Meta <code>debug_token</code>).
                                    </p>
                                </div>

                                <div>
                                    <label class="form-label" for="settingsview-verify-token">Verify Token</label>
                                    <input id="settingsview-verify-token"
                                        v-model="whatsappCloudSettings.verify_token"
                                        type="text"
                                        class="form-input"
                                        placeholder="your_secure_token"
                                    >
                                    <p class="form-hint">For webhook verification (set in Meta Dashboard)</p>
                                </div>

                                <div>
                                    <label class="form-label" for="settingsview-graph-api-version">Graph API Version</label>
                                    <input id="settingsview-graph-api-version"
                                        v-model="whatsappCloudSettings.graph_version"
                                        type="text"
                                        class="form-input"
                                        placeholder="v20.0"
                                    >
                                    <p class="form-hint">Default: v20.0</p>
                                </div>

                                <div class="sm:col-span-2">
                                    <div class="form-choice">
                                        <input
                                            id="settingsview-whatsapp-enabled"
                                            type="checkbox"
                                            v-model="whatsappCloudSettings.is_enabled"
                                            class="form-checkbox w-4 h-4"
                                        >
                                        <label for="settingsview-whatsapp-enabled" class="text-sm font-medium text-slate-700 cursor-pointer">Enable WhatsApp Cloud API</label>
                                    </div>
                                    <p class="form-hint ml-6">Enable this to start using WhatsApp Cloud API features</p>
                                </div>
                            </div>

                            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                                <BaseButton
                                    variant="primary"
                                    block-mobile
                                    :loading="savingWhatsappCloud"
                                    @click="saveWhatsappCloudSettings"
                                >
                                    {{ savingWhatsappCloud ? 'Saving...' : 'Save Settings' }}
                                </BaseButton>

                                <BaseButton
                                    variant="soft"
                                    block-mobile
                                    :loading="testingWhatsappCloud"
                                    @click="testWhatsappCloudConnection"
                                >
                                    <template #icon>
                                        <BoltIcon class="icon-sm" aria-hidden="true" />
                                    </template>
                                    {{ testingWhatsappCloud ? 'Testing...' : 'Test Connection' }}
                                </BaseButton>
                            </div>

                            <div
                                v-if="whatsappCloudTestResult"
                                :class="whatsappCloudTestResultBoxClass"
                                role="status"
                                aria-live="polite"
                            >
                                <p class="whitespace-pre-wrap break-words">
                                    {{ whatsappCloudTestResult.message }}
                                </p>
                                <p
                                    v-if="whatsappCloudTestResult.hint"
                                    class="mt-2 text-xs whitespace-pre-wrap break-words"
                                >
                                    Hint: {{ whatsappCloudTestResult.hint }}
                                </p>
                                <pre
                                    v-if="whatsappCloudTestResult.token_inspection"
                                    class="mt-3 text-xs bg-white/60 border border-slate-200 rounded p-2 overflow-x-auto max-h-40 text-slate-700"
                                >{{ JSON.stringify(whatsappCloudTestResult.token_inspection, null, 2) }}</pre>
                            </div>
                        </div>
                    </BaseCard>
                </TabPanel>

                <!-- Facebook Section -->
                <TabPanel :unmount="false">
                    <BaseCard>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 bg-primary-100 text-primary-700 rounded-xl flex items-center justify-center shrink-0">
                                    <ShareIcon class="icon" aria-hidden="true" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="card-title">Facebook / Meta Settings</h2>
                                    <p class="card-subtitle">Configure Facebook integration for leads and ads</p>
                                </div>
                            </div>
                        </template>

                        <div class="form-grid-2">
                            <div>
                                <label class="form-label" for="settingsview-app-id">App ID</label>
                                <input id="settingsview-app-id"
                                    v-model="facebookSettings.facebook_app_id"
                                    type="text"
                                    class="form-input"
                                    placeholder="Facebook App ID"
                                >
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-app-secret">App Secret</label>
                                <input id="settingsview-app-secret"
                                    v-model="facebookSettings.facebook_app_secret"
                                    type="password"
                                    class="form-input"
                                    placeholder="••••••••"
                                >
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-page-id">Page ID</label>
                                <input id="settingsview-page-id"
                                    v-model="facebookSettings.facebook_page_id"
                                    type="text"
                                    class="form-input"
                                    placeholder="Facebook Page ID"
                                >
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-pixel-id">Pixel ID</label>
                                <input id="settingsview-pixel-id"
                                    v-model="facebookSettings.facebook_pixel_id"
                                    type="text"
                                    class="form-input"
                                    placeholder="Facebook Pixel ID"
                                >
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" for="settingsview-access-token-2">Access Token</label>
                                <input id="settingsview-access-token-2"
                                    v-model="facebookSettings.facebook_access_token"
                                    type="password"
                                    class="form-input"
                                    placeholder="••••••••"
                                >
                            </div>
                        </div>

                        <template #footer>
                            <BaseButton
                                variant="primary"
                                block-mobile
                                :loading="savingFacebook"
                                @click="saveFacebookSettings"
                            >
                                {{ savingFacebook ? 'Saving...' : 'Save Facebook Settings' }}
                            </BaseButton>
                        </template>
                    </BaseCard>
                </TabPanel>

                <!-- Cold calling / Google Places -->
                <TabPanel :unmount="false">
                    <BaseCard>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 bg-success-100 text-success-700 rounded-xl flex items-center justify-center shrink-0">
                                    <PhoneIcon class="icon" aria-hidden="true" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="card-title">Cold calling (Google Maps)</h2>
                                    <p class="card-subtitle">API key for postcode business discovery (Geocoding + Places API New)</p>
                                </div>
                            </div>
                        </template>

                        <div class="rounded-card border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 space-y-2 mb-6">
                            <h3 class="font-medium text-slate-800">Google Cloud Console</h3>
                            <ul class="list-disc list-inside space-y-1 text-slate-600">
                                <li>Create a project, enable billing, then enable <strong>Geocoding API</strong> and <strong>Places API (New)</strong>. The legacy <strong>Places API</strong> (old) is <em>not</em> enough — Cold calling uses <code class="text-xs bg-white px-1 rounded">places.googleapis.com</code> (SearchNearby, Text Search, Place Details).</li>
                                <li>Credentials → your API key → <strong>API restrictions</strong> must include <strong>Places API (New)</strong> and <strong>Geocoding API</strong> (or “Don’t restrict key” while testing). If SearchNearby returns <code class="text-xs bg-white px-1 rounded">API_KEY_SERVICE_BLOCKED</code>, the new Places API is missing from that list.</li>
                                <li>The app calls Google from your <strong>server</strong>—do not use “HTTP referrers” only (Geocoding returns <code class="text-xs bg-white px-1 rounded">REQUEST_DENIED</code>). Use <strong>IP addresses</strong> or <strong>None</strong> for testing.</li>
                                <li>If Geocoding is denied, the CRM tries <strong>Places Text Search</strong> for the postcode centre (still requires Places API New on the key).</li>
                            </ul>
                        </div>

                        <div class="form-grid-2">
                            <div class="sm:col-span-2">
                                <label class="form-label" for="settingsview-google-maps-api-key">Google Maps API key</label>
                                <input id="settingsview-google-maps-api-key"
                                    v-model="coldCallingSettings.google_maps_api_key"
                                    type="password"
                                    autocomplete="off"
                                    class="form-input font-mono"
                                    placeholder="AIza…"
                                >
                                <p class="form-hint">Leave blank when saving to keep the existing key unchanged.</p>
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-default-search-radius-meters">Default search radius (meters)</label>
                                <input id="settingsview-default-search-radius-meters"
                                    v-model.number="coldCallingSettings.cold_calling_default_radius_meters"
                                    type="number"
                                    min="500"
                                    max="50000"
                                    class="form-input"
                                >
                                <p class="form-hint">Max 50&nbsp;000 (Google limit).</p>
                            </div>
                            <div>
                                <label class="form-label" for="settingsview-new-businesses-per-search-max">New businesses per search (max)</label>
                                <input id="settingsview-new-businesses-per-search-max"
                                    v-model.number="coldCallingSettings.cold_calling_max_places_per_run"
                                    type="number"
                                    min="1"
                                    max="100"
                                    class="form-input"
                                >
                                <p class="form-hint">
                                    Each run adds up to this many <strong>new</strong> Google places (by <code class="text-xs bg-white px-1 rounded">place_id</code>). Rows already in cold calling are skipped for insert and only linked to this postcode. Text search keeps paging until the target is reached or Google has no more results.
                                </p>
                            </div>
                            <div class="sm:col-span-2 rounded-card border border-primary-200 bg-primary-50/50 p-4 space-y-3">
                                <h3 class="subsection-title">Small cafes &amp; independent businesses</h3>
                                <p class="text-xs text-slate-600">
                                    Google does not expose “company size”. <strong>Nearby Search</strong> is limited to food &amp; drink + high-street retail types (restaurants, cafés, bakeries, bars, takeaways, clothing, gifts, florists, etc.). <strong>Text search</strong> uses an indie restaurant / café / retail query by default. You can still <strong>drop</strong> huge chains via review cap, name blocklist, and excluded place types after Place Details.
                                </p>
                                <div>
                                    <label class="form-label" for="settingsview-places-text-search-query">Places text search query</label>
                                    <textarea id="settingsview-places-text-search-query"
                                        v-model="coldCallingSettings.cold_calling_text_search_query"
                                        rows="3"
                                        class="form-textarea"
                                        placeholder="Leave empty for built-in indie cafe / small food query. Use {postcode} for the run’s postcode."
                                    />
                                    <p class="form-hint">Empty = default wording (cafes, bakeries, small restaurants, etc.). Max ~480 characters sent to Google.</p>
                                </div>
                                <div>
                                    <label class="form-label" for="settingsview-skip-new-row-if-google-reviews-over">Skip new row if Google reviews over</label>
                                    <input id="settingsview-skip-new-row-if-google-reviews-over"
                                        v-model.number="coldCallingSettings.cold_calling_skip_if_reviews_over"
                                        type="number"
                                        min="0"
                                        max="500000"
                                        class="form-input max-w-xs"
                                    >
                                    <p class="form-hint"><strong>0</strong> = off. Try <strong>80–200</strong> to reduce huge national chains (imperfect). Busy independents can also have many reviews.</p>
                                </div>
                                <div>
                                    <label class="form-label" for="settingsview-exclude-name-contains-comma-separated">Exclude name contains (comma-separated)</label>
                                    <input id="settingsview-exclude-name-contains-comma-separated"
                                        v-model="coldCallingSettings.cold_calling_discovery_exclude_names"
                                        type="text"
                                        class="form-input"
                                        placeholder="e.g. Tesco, Sainsbury's, McDonald's, Starbucks"
                                    >
                                </div>
                                <div>
                                    <label class="form-label" for="settingsview-exclude-google-place-types">Exclude Google place types</label>
                                    <input id="settingsview-exclude-google-place-types"
                                        v-model="coldCallingSettings.cold_calling_discovery_exclude_types"
                                        type="text"
                                        class="form-input font-mono"
                                        placeholder="default"
                                    >
                                    <p class="form-hint">
                                        <code class="bg-white px-1 rounded">default</code> or empty = large-format retail (department_store, shopping_mall, supermarket, hypermarket, discount_supermarket, etc.).
                                        <code class="bg-white px-1 rounded">none</code> = do not filter by type. Or list types yourself (comma-separated, snake_case).
                                    </p>
                                </div>
                            </div>
                            <div class="sm:col-span-2 flex items-start gap-3 p-4 border border-slate-200 rounded-card bg-warning-50/50">
                                <input
                                    id="cold_enrich"
                                    v-model="coldCallingSettings.cold_calling_enrich_email"
                                    type="checkbox"
                                    class="form-checkbox mt-1"
                                >
                                <label for="cold_enrich" class="text-sm text-slate-700 cursor-pointer">
                                    <span class="font-medium">Try to find email from business website</span>
                                    <span class="block text-slate-500 mt-0.5">Fetches homepage plus common paths like /contact (mailto, tel:, UK numbers in text). Slow; many sites block bots or hide details. Also enable per run under Marketing → Cold calling, or use “Fill from websites” on saved contacts.</span>
                                </label>
                            </div>
                            <div class="sm:col-span-2 rounded-card border border-primary-200 bg-primary-50/40 p-4 space-y-3">
                                <h3 class="subsection-title">Claude AI (Anthropic) — extra email / phone pass</h3>
                                <p class="text-xs text-slate-600">
                                    After pages are fetched, if email or phone is still missing, the CRM sends <strong>plain text from those pages</strong> to Claude and asks for JSON <code class="text-[11px] bg-white px-1 rounded">email</code> / <code class="text-[11px] bg-white px-1 rounded">phone</code> only. Put your API key in <code class="text-[11px] bg-white px-1 rounded">.env</code> as <code class="text-[11px] bg-white px-1 rounded">ANTHROPIC_API_KEY</code> or below. <strong>Never commit keys to git.</strong> Rotate any key that was pasted into chat or tickets.
                                </p>
                                <div>
                                    <label class="form-label" for="settingsview-anthropic-api-key">Anthropic API key</label>
                                    <input id="settingsview-anthropic-api-key"
                                        v-model="coldCallingSettings.anthropic_api_key"
                                        type="password"
                                        autocomplete="off"
                                        class="form-input font-mono"
                                        placeholder="sk-ant-api03-…"
                                    >
                                    <p class="form-hint">Leave blank when saving to keep the existing key.</p>
                                </div>
                                <div>
                                    <label class="form-label" for="settingsview-claude-model-id">Claude model ID</label>
                                    <input id="settingsview-claude-model-id"
                                        v-model="coldCallingSettings.anthropic_model"
                                        type="text"
                                        class="form-input font-mono"
                                        placeholder="claude-sonnet-4-20250514"
                                    >
                                </div>
                                <div class="form-choice">
                                    <input
                                        id="settingsview-cold-calling-use-claude"
                                        v-model="coldCallingSettings.cold_calling_use_claude"
                                        type="checkbox"
                                        class="form-checkbox"
                                    >
                                    <label for="settingsview-cold-calling-use-claude" class="text-sm font-normal text-slate-700 cursor-pointer">Use Claude when scrape did not find email or phone (uses API credits)</label>
                                </div>
                            </div>
                        </div>

                        <template #footer>
                            <BaseButton
                                variant="success"
                                block-mobile
                                :loading="savingColdCalling"
                                @click="saveColdCallingSettings"
                            >
                                {{ savingColdCalling ? 'Saving…' : 'Save cold calling settings' }}
                            </BaseButton>
                        </template>
                    </BaseCard>
                </TabPanel>

                <!-- PWA Settings Section -->
                <TabPanel :unmount="false">
                    <BaseCard>
                        <template #header>
                            <div class="flex items-center gap-3">
                                <span class="w-10 h-10 bg-primary-100 text-primary-700 rounded-xl flex items-center justify-center shrink-0">
                                    <ArrowDownOnSquareIcon class="icon" aria-hidden="true" />
                                </span>
                                <div class="min-w-0">
                                    <h2 class="card-title">Progressive Web App (PWA)</h2>
                                    <p class="card-subtitle">Allow users to install the app on their devices</p>
                                </div>
                            </div>
                        </template>

                        <div class="space-y-4">
                            <!-- PWA Enable Toggle -->
                            <div class="flex items-center justify-between gap-4 p-4 bg-slate-50 rounded-card">
                                <div>
                                    <h3 class="subsection-title">Enable PWA Install Prompt</h3>
                                    <p class="text-sm text-slate-500 mt-0.5">
                                        When enabled, users will see an "Install App" button on mobile devices
                                    </p>
                                </div>
                                <Switch
                                    :model-value="settings.pwa_enabled"
                                    :class="[
                                        'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors',
                                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40 focus-visible:ring-offset-2',
                                        settings.pwa_enabled ? 'bg-primary-600' : 'bg-slate-300',
                                    ]"
                                    @update:model-value="setPwaEnabled"
                                >
                                    <span class="sr-only">Enable PWA install prompt</span>
                                    <span
                                        aria-hidden="true"
                                        :class="[
                                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition-transform',
                                            settings.pwa_enabled ? 'translate-x-5' : 'translate-x-0',
                                        ]"
                                    />
                                </Switch>
                            </div>

                            <!-- PWA Status -->
                            <div class="p-4 border border-slate-200 rounded-card space-y-3">
                                <h3 class="subsection-title">PWA Status</h3>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full" :class="pwaStatus.serviceWorker ? 'bg-success-600' : 'bg-danger-600'" aria-hidden="true"></span>
                                        <span class="text-slate-600">Service Worker:</span>
                                        <span class="font-medium" :class="pwaStatus.serviceWorker ? 'text-success-700' : 'text-danger-700'">
                                            {{ pwaStatus.serviceWorker ? 'Registered' : 'Not Registered' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full" :class="pwaStatus.manifest ? 'bg-success-600' : 'bg-danger-600'" aria-hidden="true"></span>
                                        <span class="text-slate-600">Manifest:</span>
                                        <span class="font-medium" :class="pwaStatus.manifest ? 'text-success-700' : 'text-danger-700'">
                                            {{ pwaStatus.manifest ? 'Found' : 'Not Found' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full" :class="pwaStatus.https ? 'bg-success-600' : 'bg-warning-600'" aria-hidden="true"></span>
                                        <span class="text-slate-600">HTTPS:</span>
                                        <span class="font-medium" :class="pwaStatus.https ? 'text-success-700' : 'text-warning-800'">
                                            {{ pwaStatus.https ? 'Enabled' : 'Development Mode' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full" :class="pwaStatus.installable ? 'bg-success-600' : 'bg-slate-400'" aria-hidden="true"></span>
                                        <span class="text-slate-600">Installable:</span>
                                        <span class="font-medium" :class="pwaStatus.installable ? 'text-success-700' : 'text-slate-500'">
                                            {{ pwaStatus.installable ? 'Yes' : 'Not Available' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </BaseCard>
                </TabPanel>
            </TabPanels>
        </TabGroup>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import axios from 'axios';
import {
    Switch,
    Tab,
    TabGroup,
    TabList,
    TabPanel,
    TabPanels,
} from '@headlessui/vue';
import {
    ArrowDownOnSquareIcon,
    BoltIcon,
    BuildingOfficeIcon,
    ChatBubbleLeftRightIcon,
    DevicePhoneMobileIcon,
    EnvelopeIcon,
    PaintBrushIcon,
    PaperAirplaneIcon,
    PhoneIcon,
    ShareIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import { BaseButton, BaseCard } from '@/components/base';
import { usePwaStore } from '@/stores/pwa';
import { useToastStore } from '@/stores/toast';
import { useBrandingStore } from '@/stores/branding';

const pwa = usePwaStore();
const toast = useToastStore();
const branding = useBrandingStore();

const loading = ref(true);
const saving = ref(false);
const savingSmtp = ref(false);
const savingSms = ref(false);
const savingWhatsappCloud = ref(false);
const testingWhatsappCloud = ref(false);
const whatsappCloudLoading = ref(false);
const whatsappCloudTestResult = ref(null);

const whatsappCloudTestResultBoxClass = computed(() => {
    const r = whatsappCloudTestResult.value;
    if (!r) return '';
    if (!r.success) return 'callout callout-danger';
    if (r.hint || r.token_inspection?.detail === 'missing_app_credentials' || r.token_inspection?.can_send === null) {
        return 'callout callout-warning';
    }
    return 'callout callout-success';
});

const savingFacebook = ref(false);
const savingColdCalling = ref(false);
const testingSmtp = ref(false);
const logoPreviewFailed = ref(false);
const faviconPreviewFailed = ref(false);
const uploadingLogo = ref(false);
const uploadingFavicon = ref(false);
const testEmail = ref('');
const emailTemplates = ref([]);
const welcomeEmailTemplateId = ref('');
const savingWelcomeEmailTemplate = ref(false);
const logoInput = ref(null);
const faviconInput = ref(null);
const webhookUrl = ref(`${window.location.origin}/api/whatsapp/webhook`);

const activeSection = ref('branding');

const sections = [
    { id: 'branding', name: 'Branding', icon: PaintBrushIcon },
    { id: 'company', name: 'Company', icon: BuildingOfficeIcon },
    { id: 'email', name: 'Email/SMTP', icon: EnvelopeIcon },
    { id: 'sms', name: 'SMS', icon: DevicePhoneMobileIcon },
    { id: 'whatsapp', name: 'WhatsApp', icon: ChatBubbleLeftRightIcon },
    { id: 'facebook', name: 'Facebook', icon: ShareIcon },
    { id: 'cold_calling', name: 'Cold calling', icon: PhoneIcon },
    { id: 'pwa', name: 'PWA', icon: ArrowDownOnSquareIcon },
];

const tabIndex = computed(() => {
    const index = sections.findIndex((section) => section.id === activeSection.value);
    return index === -1 ? 0 : index;
});

const handleTabChange = (index) => {
    activeSection.value = sections[index]?.id ?? sections[0].id;
};

const settings = reactive({
    pwa_enabled: true,
    company_name: '',
    admin_notification_email: '',
    company_email: '',
    company_phone: '',
    company_website: '',
    crm_base_url: '',
    company_registration_no: '',
    company_vat: '',
    company_address: '',
    logo_url: '',
    favicon_url: '',
    social_facebook_url: '',
    social_twitter_url: '',
    social_linkedin_url: '',
    social_instagram_url: '',
    social_tiktok_url: '',
    payment_account_name: '',
    payment_sort_code: '',
    payment_account_number: '',
    payment_terms_note: '',
});

const smtpSettings = reactive({
    smtp_host: '',
    smtp_port: 587,
    smtp_username: '',
    smtp_password: '',
    smtp_encryption: 'tls',
    smtp_from_email: '',
    smtp_from_name: '',
});

// Default SMS settings - these will be overridden by database values when loaded
// To set defaults, add them to your .env file:
// VOODOOSMS_UID=your_default_uid
// VOODOOSMS_PASS=your_default_password
const smsSettings = reactive({
    sms_api_key: '',
    sms_secret_key: '',
    sms_sender_name: 'EPOS',
    sms_default_message: '',
});

const testSmsPhone = ref('');
const testingSms = ref(false);

const whatsappCloudSettings = reactive({
    waba_id: '',
    phone_number_id: '',
    meta_app_id: '',
    meta_app_secret: '',
    access_token: '',
    verify_token: '',
    graph_version: 'v20.0',
    is_enabled: false,
});

const facebookSettings = reactive({
    facebook_app_id: '',
    facebook_app_secret: '',
    facebook_page_id: '',
    facebook_access_token: '',
    facebook_pixel_id: '',
});

const coldCallingSettings = reactive({
    google_maps_api_key: '',
    cold_calling_default_radius_meters: 5000,
    cold_calling_max_places_per_run: 50,
    cold_calling_enrich_email: false,
    cold_calling_text_search_query: '',
    cold_calling_skip_if_reviews_over: 0,
    cold_calling_discovery_exclude_names: '',
    cold_calling_discovery_exclude_types: 'default',
    anthropic_api_key: '',
    anthropic_model: 'claude-sonnet-4-20250514',
    cold_calling_use_claude: true,
});

const pwaStatus = reactive({
    serviceWorker: false,
    manifest: false,
    https: false,
    installable: false,
});

const loadSettings = async () => {
    try {
        const response = await axios.get('/api/settings');
        const data = response.data;

        // General settings
        settings.pwa_enabled = data.pwa_enabled !== 'false';
        settings.company_name = data.company_name || '';
        settings.admin_notification_email = data.admin_notification_email || '';
        settings.company_email = data.company_email || '';
        settings.company_phone = data.company_phone || '';
        settings.company_website = data.company_website || '';
        settings.crm_base_url = data.crm_base_url || '';
        settings.company_registration_no = data.company_registration_no || '';
        settings.company_vat = data.company_vat || '';
        settings.company_address = data.company_address || '';
        settings.logo_url = data.logo_url || '';
        settings.favicon_url = data.favicon_url || '';
        settings.social_facebook_url = data.social_facebook_url || '';
        settings.social_twitter_url = data.social_twitter_url || '';
        settings.social_linkedin_url = data.social_linkedin_url || '';
        settings.social_instagram_url = data.social_instagram_url || '';
        settings.social_tiktok_url = data.social_tiktok_url || '';
        settings.payment_account_name = data.payment_account_name || '';
        settings.payment_sort_code = data.payment_sort_code || '';
        settings.payment_account_number = data.payment_account_number || '';
        settings.payment_terms_note = data.payment_terms_note || '';

        // SMTP settings
        smtpSettings.smtp_host = data.smtp_host || '';
        smtpSettings.smtp_port = parseInt(data.smtp_port) || 587;
        smtpSettings.smtp_username = data.smtp_username || '';
        smtpSettings.smtp_password = data.smtp_password || '';
        smtpSettings.smtp_encryption = data.smtp_encryption || 'tls';
        smtpSettings.smtp_from_email = data.smtp_from_email || '';
        smtpSettings.smtp_from_name = data.smtp_from_name || '';

        // SMS settings - use database values (will be empty if not set)
        smsSettings.sms_api_key = data.sms_api_key || '';
        smsSettings.sms_secret_key = data.sms_secret_key || '';
        smsSettings.sms_sender_name = data.sms_sender_name || 'EPOS';
        smsSettings.sms_default_message = data.sms_default_message || '';

        // Facebook settings
        facebookSettings.facebook_app_id = data.facebook_app_id || '';
        facebookSettings.facebook_app_secret = data.facebook_app_secret || '';
        facebookSettings.facebook_page_id = data.facebook_page_id || '';
        facebookSettings.facebook_access_token = data.facebook_access_token || '';
        facebookSettings.facebook_pixel_id = data.facebook_pixel_id || '';

        coldCallingSettings.google_maps_api_key = data.google_maps_api_key || '';
        coldCallingSettings.cold_calling_default_radius_meters = parseInt(data.cold_calling_default_radius_meters, 10) || 5000;
        coldCallingSettings.cold_calling_max_places_per_run = parseInt(data.cold_calling_max_places_per_run, 10) || 50;
        coldCallingSettings.cold_calling_enrich_email = data.cold_calling_enrich_email === '1' || data.cold_calling_enrich_email === 'true';
        coldCallingSettings.cold_calling_text_search_query = data.cold_calling_text_search_query || '';
        coldCallingSettings.cold_calling_skip_if_reviews_over = Number.isFinite(parseInt(data.cold_calling_skip_if_reviews_over, 10))
            ? parseInt(data.cold_calling_skip_if_reviews_over, 10)
            : 0;
        coldCallingSettings.cold_calling_discovery_exclude_names = data.cold_calling_discovery_exclude_names || '';
        coldCallingSettings.cold_calling_discovery_exclude_types = data.cold_calling_discovery_exclude_types || 'default';
        coldCallingSettings.anthropic_api_key = data.anthropic_api_key || '';
        coldCallingSettings.anthropic_model = data.anthropic_model || 'claude-sonnet-4-20250514';
        coldCallingSettings.cold_calling_use_claude = data.cold_calling_use_claude === undefined || data.cold_calling_use_claude === null
            ? true
            : (data.cold_calling_use_claude === '1' || data.cold_calling_use_claude === 'true');
    } catch (error) {
        console.error('Failed to load settings:', error);
    } finally {
        loading.value = false;
    }

    // Load WhatsApp Cloud API settings
    loadWhatsappCloudSettings();
};

const loadEmailTemplateSettings = async () => {
    try {
        const [templatesResponse, assignmentResponse] = await Promise.all([
            axios.get('/api/email-templates-for-sending'),
            axios.get('/api/template-assignments/customer_welcome/email'),
        ]);

        emailTemplates.value = templatesResponse.data || [];
        welcomeEmailTemplateId.value = assignmentResponse.data?.template_id
            ? String(assignmentResponse.data.template_id)
            : '';
    } catch (error) {
        console.error('Failed to load email template settings:', error);
        emailTemplates.value = [];
    }
};

const loadWhatsappCloudSettings = async () => {
    whatsappCloudLoading.value = true;
    try {
        const response = await axios.get('/api/whatsapp/settings');
        const data = response.data;
        whatsappCloudSettings.waba_id = data.waba_id || '';
        whatsappCloudSettings.phone_number_id = data.phone_number_id || '';
        whatsappCloudSettings.meta_app_id = data.meta_app_id || '';
        whatsappCloudSettings.meta_app_secret = '';
        whatsappCloudSettings.verify_token = data.verify_token || '';
        whatsappCloudSettings.graph_version = data.graph_version || 'v20.0';
        whatsappCloudSettings.is_enabled = data.is_enabled || false;
        // Note: access_token is not returned for security
    } catch (error) {
        console.error('Failed to load WhatsApp Cloud settings:', error);
    } finally {
        whatsappCloudLoading.value = false;
    }
};

const checkPwaStatus = () => {
    pwaStatus.serviceWorker = pwa.serviceWorkerRegistered;
    pwaStatus.manifest = !!document.querySelector('link[rel="manifest"]');
    pwaStatus.https = location.protocol === 'https:' || location.hostname === 'localhost';
    pwaStatus.installable = pwa.isInstallable;
};

const handleLogoUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    uploadingLogo.value = true;
    const formData = new FormData();
    formData.append('logo', file);

    try {
        const response = await axios.post('/api/settings/logo', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        });
        settings.logo_url = response.data.url;
        await branding.loadPublic(true);
        toast.success('Logo uploaded successfully');
    } catch (error) {
        console.error('Failed to upload logo:', error);
        toast.error(error.response?.data?.message || 'Failed to upload logo');
    } finally {
        uploadingLogo.value = false;
        if (logoInput.value) logoInput.value.value = '';
    }
};

const deleteLogo = async () => {
    try {
        await axios.delete('/api/settings/logo');
        settings.logo_url = '';
        await branding.loadPublic(true);
        toast.success('Logo deleted');
    } catch (error) {
        console.error('Failed to delete logo:', error);
        toast.error('Failed to delete logo');
    }
};

const handleFaviconUpload = async (event) => {
    const file = event.target.files[0];
    if (!file) return;

    uploadingFavicon.value = true;
    const formData = new FormData();
    formData.append('favicon', file);

    try {
        const response = await axios.post('/api/settings/favicon', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        settings.favicon_url = response.data.url;
        await branding.loadPublic(true);
        toast.success('Favicon uploaded successfully');
    } catch (error) {
        console.error('Failed to upload favicon:', error);
        toast.error(error.response?.data?.message || 'Failed to upload favicon');
    } finally {
        uploadingFavicon.value = false;
        if (faviconInput.value) faviconInput.value.value = '';
    }
};

const deleteFavicon = async () => {
    try {
        await axios.delete('/api/settings/favicon');
        settings.favicon_url = '';
        await branding.loadPublic(true);
        toast.success('Favicon deleted');
    } catch (error) {
        console.error('Failed to delete favicon:', error);
        toast.error('Failed to delete favicon');
    }
};

const updatePwaSetting = async () => {
    try {
        await axios.put('/api/settings/pwa', {
            enabled: settings.pwa_enabled
        });
        pwa.pwaEnabled = settings.pwa_enabled;
        toast.success(`PWA install prompt ${settings.pwa_enabled ? 'enabled' : 'disabled'}`);
    } catch (error) {
        console.error('Failed to update PWA setting:', error);
        toast.error('Failed to update PWA setting');
        settings.pwa_enabled = !settings.pwa_enabled;
    }
};

const setPwaEnabled = (value) => {
    settings.pwa_enabled = value;
    updatePwaSetting();
};

const saveCompanySettings = async () => {
    saving.value = true;
    try {
        await axios.put('/api/settings', {
            settings: {
                company_name: settings.company_name,
                admin_notification_email: settings.admin_notification_email,
                company_email: settings.company_email,
                company_phone: settings.company_phone,
                company_website: settings.company_website,
                crm_base_url: settings.crm_base_url,
                company_registration_no: settings.company_registration_no,
                company_vat: settings.company_vat,
                company_address: settings.company_address,
                social_facebook_url: settings.social_facebook_url,
                social_twitter_url: settings.social_twitter_url,
                social_linkedin_url: settings.social_linkedin_url,
                social_instagram_url: settings.social_instagram_url,
                social_tiktok_url: settings.social_tiktok_url,
                payment_account_name: settings.payment_account_name,
                payment_sort_code: settings.payment_sort_code,
                payment_account_number: settings.payment_account_number,
                payment_terms_note: settings.payment_terms_note,
            }
        });
        toast.success('Company settings saved');
    } catch (error) {
        console.error('Failed to save settings:', error);
        toast.error('Failed to save settings');
    } finally {
        saving.value = false;
    }
};

const saveSmtpSettings = async () => {
    savingSmtp.value = true;
    try {
        await axios.put('/api/settings/smtp', smtpSettings);
        toast.success('SMTP settings saved');
    } catch (error) {
        console.error('Failed to save SMTP settings:', error);
        toast.error('Failed to save SMTP settings');
    } finally {
        savingSmtp.value = false;
    }
};

const saveWelcomeEmailTemplate = async () => {
    savingWelcomeEmailTemplate.value = true;
    try {
        await axios.put('/api/template-assignments', {
            function_type: 'customer_welcome',
            template_type: 'email',
            template_id: welcomeEmailTemplateId.value ? Number(welcomeEmailTemplateId.value) : null,
        });
        toast.success('Customer welcome email template saved');
    } catch (error) {
        console.error('Failed to save welcome email template:', error);
        toast.error(error.response?.data?.message || 'Failed to save welcome email template');
    } finally {
        savingWelcomeEmailTemplate.value = false;
    }
};

const testSmtpConnection = async () => {
    if (!testEmail.value) {
        toast.error('Please enter a test email address');
        return;
    }

    testingSmtp.value = true;
    try {
        await axios.post('/api/settings/smtp/test', { test_email: testEmail.value });
        toast.success('Test email sent successfully!');
    } catch (error) {
        console.error('SMTP test failed:', error);
        toast.error(error.response?.data?.message || 'Failed to send test email');
    } finally {
        testingSmtp.value = false;
    }
};

const saveSmsSettings = async () => {
    savingSms.value = true;
    try {
        await axios.put('/api/settings/sms', smsSettings);
        toast.success('SMS settings saved');
    } catch (error) {
        console.error('Failed to save SMS settings:', error);
        toast.error('Failed to save SMS settings');
    } finally {
        savingSms.value = false;
    }
};

const testSmsConnection = async () => {
    if (!testSmsPhone.value.trim()) {
        toast.error('Please enter a test phone number');
        return;
    }

    testingSms.value = true;
    try {
        const response = await axios.post('/api/settings/sms/test', {
            test_phone: testSmsPhone.value.trim(),
            test_message: 'This is a test SMS from your CRM system.',
        });
        toast.success(response.data.message || 'Test SMS sent successfully!');
    } catch (error) {
        console.error('Failed to send test SMS:', error);
        toast.error(error.response?.data?.message || 'Failed to send test SMS');
    } finally {
        testingSms.value = false;
    }
};

const saveWhatsappCloudSettings = async () => {
    savingWhatsappCloud.value = true;
    whatsappCloudTestResult.value = null;
    try {
        await axios.post('/api/whatsapp/settings', whatsappCloudSettings);
        toast.success('WhatsApp Cloud API settings saved');
    } catch (error) {
        console.error('Failed to save WhatsApp Cloud settings:', error);
        toast.error(error.response?.data?.message || 'Failed to save WhatsApp Cloud settings');
    } finally {
        savingWhatsappCloud.value = false;
    }
};

const testWhatsappCloudConnection = async () => {
    testingWhatsappCloud.value = true;
    whatsappCloudTestResult.value = null;
    try {
        const response = await axios.post('/api/whatsapp/settings/test-connection');
        whatsappCloudTestResult.value = {
            success: response.data.success !== false,
            message: response.data.message || 'Connection successful!',
            hint: response.data.hint || '',
            token_inspection: response.data.token_inspection || null,
        };
        if (response.data.hint) {
            toast.success('Test finished — see note below');
        } else {
            toast.success('Connection test successful');
        }
    } catch (error) {
        const d = error.response?.data || {};
        const serverMessage = d.message || 'Connection test failed';
        const serverHint = d.hint || '';
        whatsappCloudTestResult.value = {
            success: false,
            message: serverMessage,
            hint: serverHint,
            token_inspection: d.token_inspection || null,
        };
        toast.error(serverMessage);
    } finally {
        testingWhatsappCloud.value = false;
    }
};

const saveFacebookSettings = async () => {
    savingFacebook.value = true;
    try {
        await axios.put('/api/settings/facebook', facebookSettings);
        toast.success('Facebook settings saved');
    } catch (error) {
        console.error('Failed to save Facebook settings:', error);
        toast.error('Failed to save Facebook settings');
    } finally {
        savingFacebook.value = false;
    }
};

const saveColdCallingSettings = async () => {
    savingColdCalling.value = true;
    try {
        const payload = {
            cold_calling_default_radius_meters: coldCallingSettings.cold_calling_default_radius_meters,
            cold_calling_max_places_per_run: coldCallingSettings.cold_calling_max_places_per_run,
            cold_calling_enrich_email: coldCallingSettings.cold_calling_enrich_email,
            cold_calling_text_search_query: coldCallingSettings.cold_calling_text_search_query ?? '',
            cold_calling_skip_if_reviews_over: coldCallingSettings.cold_calling_skip_if_reviews_over ?? 0,
            cold_calling_discovery_exclude_names: coldCallingSettings.cold_calling_discovery_exclude_names ?? '',
            cold_calling_discovery_exclude_types: coldCallingSettings.cold_calling_discovery_exclude_types ?? 'default',
            cold_calling_use_claude: coldCallingSettings.cold_calling_use_claude,
            anthropic_model: coldCallingSettings.anthropic_model?.trim() || 'claude-sonnet-4-20250514',
        };
        if (coldCallingSettings.google_maps_api_key?.trim()) {
            payload.google_maps_api_key = coldCallingSettings.google_maps_api_key.trim();
        }
        if (coldCallingSettings.anthropic_api_key?.trim()) {
            payload.anthropic_api_key = coldCallingSettings.anthropic_api_key.trim();
        }
        await axios.put('/api/settings/cold-calling', payload);
        toast.success('Cold calling settings saved');
        await loadSettings();
    } catch (error) {
        console.error('Failed to save cold calling settings:', error);
        toast.error(error.response?.data?.message || 'Failed to save cold calling settings');
    } finally {
        savingColdCalling.value = false;
    }
};

onMounted(() => {
    loadSettings();
    loadEmailTemplateSettings();
    checkPwaStatus();
    setTimeout(checkPwaStatus, 2000);
});
</script>
