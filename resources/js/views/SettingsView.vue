<template>
    <div class="max-w-5xl mx-auto p-4 sm:p-6 space-y-6">
        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900">Settings</h1>
                <p class="text-sm text-slate-600 mt-1">Manage application settings and integrations</p>
            </div>
        </div>

        <!-- Loading State -->
        <div v-if="loading" class="flex justify-center py-12">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900"></div>
        </div>

        <div v-else class="space-y-6">
            <!-- Section Navigation - horizontal scroll, mobile responsive -->
            <div class="bg-white rounded-xl shadow-sm p-2 overflow-x-auto scrollbar-thin -mx-1 px-1 sm:mx-0 sm:px-0">
                <div class="flex flex-nowrap gap-2 min-w-0">
                    <button
                        v-for="section in sections"
                        :key="section.id"
                        @click="activeSection = section.id"
                        class="px-4 py-2 rounded-lg text-sm font-medium transition-colors whitespace-nowrap shrink-0"
                        :class="activeSection === section.id 
                            ? 'bg-slate-900 text-white' 
                            : 'text-slate-600 hover:bg-slate-100'"
                    >
                        {{ section.icon }} {{ section.name }}
                    </button>
                </div>
            </div>

            <!-- Branding Section -->
            <div v-show="activeSection === 'branding'" class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                        <span class="text-lg">🎨</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Branding</h2>
                        <p class="text-sm text-slate-500">Logo and company identity</p>
                    </div>
                </div>

                <!-- Logo Upload -->
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Company Logo</label>
                        <p class="text-xs text-slate-500 mb-3">This logo will appear on the login screen and dashboard. Recommended size: 200x60px</p>
                        
                        <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6">
                            <!-- Current Logo Preview -->
                            <div class="w-full sm:w-48 h-24 border-2 border-dashed border-slate-300 rounded-xl flex items-center justify-center bg-slate-50 shrink-0">
                                <img 
                                    v-if="settings.logo_url" 
                                    :src="settings.logo_url" 
                                    alt="Company Logo" 
                                    class="max-w-full max-h-full object-contain p-2"
                                >
                                <span v-else class="text-slate-400 text-sm">No logo uploaded</span>
                            </div>
                            
                            <!-- Upload Controls -->
                            <div class="space-y-3">
                                <label class="block">
                                    <span class="sr-only">Choose logo</span>
                                    <input
                                        type="file"
                                        ref="logoInput"
                                        @change="handleLogoUpload"
                                        accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp"
                                        class="block w-full text-sm text-slate-500
                                            file:mr-4 file:py-2 file:px-4
                                            file:rounded-lg file:border-0
                                            file:text-sm file:font-semibold
                                            file:bg-slate-900 file:text-white
                                            hover:file:bg-slate-800
                                            file:cursor-pointer"
                                    >
                                </label>
                                <button
                                    v-if="settings.logo_url"
                                    @click="deleteLogo"
                                    class="text-sm text-red-600 hover:text-red-700"
                                >
                                    Remove logo
                                </button>
                                <p v-if="uploadingLogo" class="text-sm text-blue-600">Uploading...</p>
                            </div>
                        </div>
                    </div>

                    <!-- Favicon -->
                    <div class="pt-6 border-t border-slate-200">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Favicon</label>
                        <p class="text-xs text-slate-500 mb-3">
                            Shown in the browser tab, sidebar (if no logo), and PWA install prompt. Square PNG or ICO works best (32×32 or 64×64; max 1&nbsp;MB).
                        </p>
                        <div class="flex flex-col sm:flex-row items-start gap-4 sm:gap-6">
                            <div class="w-16 h-16 border-2 border-dashed border-slate-300 rounded-xl flex items-center justify-center bg-slate-50 shrink-0 overflow-hidden">
                                <img
                                    v-if="settings.favicon_url"
                                    :src="settings.favicon_url"
                                    alt=""
                                    class="w-full h-full object-cover"
                                >
                                <span v-else class="text-slate-400 text-xs text-center px-1">No favicon</span>
                            </div>
                            <div class="space-y-3">
                                <label class="block">
                                    <span class="sr-only">Choose favicon</span>
                                    <input
                                        type="file"
                                        ref="faviconInput"
                                        @change="handleFaviconUpload"
                                        accept=".ico,.png,.jpg,.jpeg,.gif,.svg,.webp,image/x-icon,image/png,image/jpeg,image/gif,image/svg+xml,image/webp"
                                        class="block w-full text-sm text-slate-500
                                            file:mr-4 file:py-2 file:px-4
                                            file:rounded-lg file:border-0
                                            file:text-sm file:font-semibold
                                            file:bg-slate-900 file:text-white
                                            hover:file:bg-slate-800
                                            file:cursor-pointer"
                                    >
                                </label>
                                <button
                                    v-if="settings.favicon_url"
                                    type="button"
                                    @click="deleteFavicon"
                                    class="text-sm text-red-600 hover:text-red-700"
                                >
                                    Remove favicon
                                </button>
                                <p v-if="uploadingFavicon" class="text-sm text-blue-600">Uploading...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Company Information Section -->
            <div v-show="activeSection === 'company'" class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-slate-100 rounded-xl flex items-center justify-center">
                        <span class="text-lg">🏢</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Company Information</h2>
                        <p class="text-sm text-slate-500">Basic company details</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Company Name</label>
                        <input
                            v-model="settings.company_name"
                            type="text"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Switch & Save"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Admin Notification Email</label>
                        <input
                            v-model="settings.admin_notification_email"
                            type="email"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="admin@company.com"
                        >
                        <p class="text-xs text-slate-500 mt-1">Admin copy of appointment notifications. Save here and configure SMTP below for emails to send.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Company Email</label>
                        <input
                            v-model="settings.company_email"
                            type="email"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="hello@switch-and-save.uk"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Company Phone</label>
                        <input
                            v-model="settings.company_phone"
                            type="text"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="+44 7340 529757"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Company Website</label>
                        <input
                            v-model="settings.company_website"
                            type="url"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="https://switch-and-save.uk"
                        >
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">CRM URL (for ticket emails)</label>
                        <input
                            v-model="settings.crm_base_url"
                            type="url"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="https://crm.yourdomain.com"
                        >
                        <p class="text-xs text-slate-500 mt-1">
                            Full address where users log in to this CRM (no trailing slash). Ticket notification links use this. If empty, the server <code class="text-xs bg-slate-100 px-1 rounded">APP_URL</code> from <code class="text-xs bg-slate-100 px-1 rounded">.env</code> is used—set this if they differ (e.g. API on one host, CRM on another).
                        </p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Company Registration No.</label>
                        <input
                            v-model="settings.company_registration_no"
                            type="text"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g., 15051352"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">VAT Registration No.</label>
                        <input
                            v-model="settings.company_vat"
                            type="text"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="e.g., GB50915794"
                        >
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Company Address</label>
                        <textarea
                            v-model="settings.company_address"
                            rows="2"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="3A Perry Common Road, Erdington&#10;Birmingham, B23 7AB"
                        ></textarea>
                        <p class="text-xs text-slate-500 mt-1">You can use line breaks for multi-line addresses. Shown in email templates and invoices.</p>
                    </div>

                    <!-- Social Media URLs (shown in email templates) -->
                    <div class="sm:col-span-2 mt-4 pt-4 border-t border-slate-100">
                        <h3 class="text-sm font-semibold text-slate-900 mb-3">Social Media Links</h3>
                        <p class="text-xs text-slate-500 mb-3">These appear as icons in email template footers. Leave blank to hide.</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Facebook</label>
                                <input
                                    v-model="settings.social_facebook_url"
                                    type="url"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                    placeholder="https://facebook.com/yourpage"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Twitter / X</label>
                                <input
                                    v-model="settings.social_twitter_url"
                                    type="url"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                    placeholder="https://twitter.com/yourhandle"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">LinkedIn</label>
                                <input
                                    v-model="settings.social_linkedin_url"
                                    type="url"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                    placeholder="https://linkedin.com/company/yourcompany"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">Instagram</label>
                                <input
                                    v-model="settings.social_instagram_url"
                                    type="url"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                    placeholder="https://instagram.com/yourhandle"
                                >
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-1">TikTok</label>
                                <input
                                    v-model="settings.social_tiktok_url"
                                    type="url"
                                    class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                                    placeholder="https://tiktok.com/@yourhandle"
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Details Section -->
                <div class="mt-8 pt-8 border-t border-slate-200">
                    <h3 class="text-md font-semibold text-slate-900 mb-4">Payment Details (for Invoices)</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Account Name *</label>
                            <input
                                v-model="settings.payment_account_name"
                                type="text"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="SWITCH&SAVE BUSINESS SERVICES LTD"
                            >
                            <p class="text-xs text-slate-500 mt-1">Account name for payment instructions</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Sort Code *</label>
                            <input
                                v-model="settings.payment_sort_code"
                                type="text"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="30-99-50"
                                maxlength="8"
                            >
                            <p class="text-xs text-slate-500 mt-1">Bank sort code (format: XX-XX-XX)</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Account Number *</label>
                            <input
                                v-model="settings.payment_account_number"
                                type="text"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="46776562"
                                maxlength="20"
                            >
                            <p class="text-xs text-slate-500 mt-1">Bank account number</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Payment Terms Note (Optional)</label>
                            <input
                                v-model="settings.payment_terms_note"
                                type="text"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="e.g., Payment due within 30 days"
                            >
                            <p class="text-xs text-slate-500 mt-1">Additional payment terms note (invoice due date will be shown automatically)</p>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        @click="saveCompanySettings"
                        :disabled="saving"
                        class="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
                    >
                        {{ saving ? 'Saving...' : 'Save Changes' }}
                    </button>
                </div>
            </div>

            <!-- Email/SMTP Section -->
            <div v-show="activeSection === 'email'" class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                        <span class="text-lg">📧</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Email / SMTP Settings</h2>
                        <p class="text-sm text-slate-500">Configure SMTP for all outgoing emails (appointments to customer, admin, and assignee; invoices; etc.)</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Host</label>
                        <input
                            v-model="smtpSettings.smtp_host"
                            type="text"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="smtp.gmail.com"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Port</label>
                        <input
                            v-model="smtpSettings.smtp_port"
                            type="number"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="587"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Username</label>
                        <input
                            v-model="smtpSettings.smtp_username"
                            type="text"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="your@email.com"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">SMTP Password</label>
                        <input
                            v-model="smtpSettings.smtp_password"
                            type="password"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="••••••••"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Encryption</label>
                        <select
                            v-model="smtpSettings.smtp_encryption"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="tls">TLS</option>
                            <option value="ssl">SSL</option>
                            <option value="none">None</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">From Email</label>
                        <input
                            v-model="smtpSettings.smtp_from_email"
                            type="email"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="noreply@company.com"
                        >
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">From Name</label>
                        <input
                            v-model="smtpSettings.smtp_from_name"
                            type="text"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Company Name CRM"
                        >
                    </div>
                </div>

                <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Customer welcome email</h3>
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
                            <label class="block text-sm font-medium text-slate-700 mb-1">Template</label>
                            <select
                                v-model="welcomeEmailTemplateId"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                            <p class="mt-1 text-xs text-slate-500">
                                If no custom template is selected, the CRM will send the active template named
                                "Welcome Template (Generic For All User)".
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="saveWelcomeEmailTemplate"
                            :disabled="savingWelcomeEmailTemplate"
                            class="w-full px-4 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50 lg:w-auto"
                        >
                            {{ savingWelcomeEmailTemplate ? 'Saving...' : 'Save welcome template' }}
                        </button>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <button
                        @click="saveSmtpSettings"
                        :disabled="savingSmtp"
                        class="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
                    >
                        {{ savingSmtp ? 'Saving...' : 'Save SMTP Settings' }}
                    </button>
                    
                    <div class="flex items-center gap-2">
                        <input
                            v-model="testEmail"
                            type="email"
                            placeholder="Test email address"
                            class="px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                        >
                        <button
                            @click="testSmtpConnection"
                            :disabled="testingSmtp"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 text-sm"
                        >
                            {{ testingSmtp ? 'Testing...' : 'Send Test' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- SMS Section (VoodooSMS) -->
            <div v-show="activeSection === 'sms'" class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                        <span class="text-lg">📱</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">SMS Settings (VoodooSMS)</h2>
                        <p class="text-sm text-slate-500">Configure VoodooSMS provider for sending messages</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">API Key (UID) *</label>
                        <input
                            v-model="smsSettings.sms_api_key"
                            type="text"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Your VoodooSMS UID"
                        >
                        <p class="text-xs text-slate-500 mt-1">Your VoodooSMS username/UID</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Secret Key (Password) *</label>
                        <input
                            v-model="smsSettings.sms_secret_key"
                            type="password"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="••••••••"
                        >
                        <p class="text-xs text-slate-500 mt-1">Your VoodooSMS password</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Sender Name</label>
                        <input
                            v-model="smsSettings.sms_sender_name"
                            type="text"
                            maxlength="11"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="EPOS"
                        >
                        <p class="text-xs text-slate-500 mt-1">Max 11 characters (appears as sender)</p>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Default Message</label>
                        <textarea
                            v-model="smsSettings.sms_default_message"
                            rows="3"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Default SMS message (optional)"
                        ></textarea>
                        <p class="text-xs text-slate-500 mt-1">Used when no message is provided</p>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-4">
                    <button
                        @click="saveSmsSettings"
                        :disabled="savingSms"
                        class="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
                    >
                        {{ savingSms ? 'Saving...' : 'Save SMS Settings' }}
                    </button>
                    
                    <div class="flex items-center gap-2">
                        <input
                            v-model="testSmsPhone"
                            type="text"
                            placeholder="Test phone number (077... or 447...)"
                            class="px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm"
                        >
                        <button
                            @click="testSmsConnection"
                            :disabled="testingSms"
                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50 text-sm"
                        >
                            {{ testingSms ? 'Sending...' : 'Send Test SMS' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- WhatsApp Section -->
            <div v-show="activeSection === 'whatsapp'" class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
                        <span class="text-lg">☁️</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">WhatsApp Settings</h2>
                        <p class="text-sm text-slate-500">Configure Meta WhatsApp Business Cloud API</p>
                    </div>
                </div>

                <div v-if="whatsappCloudLoading" class="flex justify-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900"></div>
                </div>

                <div v-else class="space-y-4">
                    <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-blue-800">
                            <strong>Note:</strong> This is the single WhatsApp integration used by CRM sends and webhooks.
                            <br>
                            <span class="text-xs">Webhook URL: <code class="bg-blue-100 px-2 py-1 rounded">{{ webhookUrl }}</code></span>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">WABA ID (WhatsApp Business Account ID) *</label>
                            <input
                                v-model="whatsappCloudSettings.waba_id"
                                type="text"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="123456789"
                            >
                            <p class="text-xs text-slate-500 mt-1">From Meta Business Dashboard</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Phone Number ID *</label>
                            <input
                                v-model="whatsappCloudSettings.phone_number_id"
                                type="text"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="987654321"
                            >
                            <p class="text-xs text-slate-500 mt-1">From Meta Business Dashboard</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Meta App ID (optional)</label>
                            <input
                                v-model="whatsappCloudSettings.meta_app_id"
                                type="text"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="From Meta for Developers → App → Basic"
                            >
                            <p class="text-xs text-slate-500 mt-1">Used only so &quot;Test connection&quot; can check if your token may send messages</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Meta App Secret (optional)</label>
                            <input
                                v-model="whatsappCloudSettings.meta_app_secret"
                                type="password"
                                autocomplete="new-password"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="Leave blank to keep existing secret"
                            >
                            <p class="text-xs text-slate-500 mt-1">Stored encrypted like the access token. Same app as above.</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-700 mb-1">Access Token *</label>
                            <input
                                v-model="whatsappCloudSettings.access_token"
                                type="password"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="EAAN..."
                            >
                            <p class="text-xs text-slate-500 mt-1">Permanent access token from Meta</p>
                            <p class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded px-2 py-1.5 mt-2">
                                If sends fail with <strong>(#10) permission</strong>, your token needs <strong>whatsapp_business_messaging</strong> for this Phone Number ID. Fill <strong>Meta App ID</strong> and <strong>Meta App Secret</strong> above, save, then use <strong>Test connection</strong> to confirm send permission (Meta <code class="text-amber-900">debug_token</code>).
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Verify Token</label>
                            <input
                                v-model="whatsappCloudSettings.verify_token"
                                type="text"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="your_secure_token"
                            >
                            <p class="text-xs text-slate-500 mt-1">For webhook verification (set in Meta Dashboard)</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Graph API Version</label>
                            <input
                                v-model="whatsappCloudSettings.graph_version"
                                type="text"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                                placeholder="v20.0"
                            >
                            <p class="text-xs text-slate-500 mt-1">Default: v20.0</p>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="whatsappCloudSettings.is_enabled"
                                    class="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500"
                                >
                                <span class="text-sm font-medium text-slate-700">Enable WhatsApp Cloud API</span>
                            </label>
                            <p class="text-xs text-slate-500 mt-1 ml-6">Enable this to start using WhatsApp Cloud API features</p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center gap-4">
                        <button
                            @click="saveWhatsappCloudSettings"
                            :disabled="savingWhatsappCloud"
                            class="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
                        >
                            {{ savingWhatsappCloud ? 'Saving...' : 'Save Settings' }}
                        </button>
                        
                        <button
                            @click="testWhatsappCloudConnection"
                            :disabled="testingWhatsappCloud"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                        >
                            {{ testingWhatsappCloud ? 'Testing...' : 'Test Connection' }}
                        </button>
                    </div>

                    <div
                        v-if="whatsappCloudTestResult"
                        class="p-4 rounded-lg border"
                        :class="whatsappCloudTestResultBoxClass"
                    >
                        <p class="text-sm whitespace-pre-wrap break-words" :class="whatsappCloudTestResultTextClass">
                            {{ whatsappCloudTestResult.message }}
                        </p>
                        <p
                            v-if="whatsappCloudTestResult.hint"
                            class="mt-2 text-xs whitespace-pre-wrap break-words"
                            :class="whatsappCloudTestResult.success ? 'text-amber-900' : 'text-red-700'"
                        >
                            Hint: {{ whatsappCloudTestResult.hint }}
                        </p>
                        <pre
                            v-if="whatsappCloudTestResult.token_inspection"
                            class="mt-3 text-xs bg-white/60 border border-slate-200 rounded p-2 overflow-x-auto max-h-40 text-slate-700"
                        >{{ JSON.stringify(whatsappCloudTestResult.token_inspection, null, 2) }}</pre>
                    </div>
                </div>
            </div>

            <!-- Facebook Section -->
            <div v-show="activeSection === 'facebook'" class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                        <span class="text-lg">📘</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Facebook / Meta Settings</h2>
                        <p class="text-sm text-slate-500">Configure Facebook integration for leads and ads</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">App ID</label>
                        <input
                            v-model="facebookSettings.facebook_app_id"
                            type="text"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Facebook App ID"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">App Secret</label>
                        <input
                            v-model="facebookSettings.facebook_app_secret"
                            type="password"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="••••••••"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Page ID</label>
                        <input
                            v-model="facebookSettings.facebook_page_id"
                            type="text"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Facebook Page ID"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Pixel ID</label>
                        <input
                            v-model="facebookSettings.facebook_pixel_id"
                            type="text"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Facebook Pixel ID"
                        >
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Access Token</label>
                        <input
                            v-model="facebookSettings.facebook_access_token"
                            type="password"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="••••••••"
                        >
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        @click="saveFacebookSettings"
                        :disabled="savingFacebook"
                        class="px-6 py-2 bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition-colors disabled:opacity-50"
                    >
                        {{ savingFacebook ? 'Saving...' : 'Save Facebook Settings' }}
                    </button>
                </div>
            </div>

            <!-- Cold calling / Google Places -->
            <div v-show="activeSection === 'cold_calling'" class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-emerald-100 rounded-xl flex items-center justify-center">
                        <span class="text-lg">📞</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Cold calling (Google Maps)</h2>
                        <p class="text-sm text-slate-500">API key for postcode business discovery (Geocoding + Places API New)</p>
                    </div>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 space-y-2 mb-6">
                    <p class="font-medium text-slate-800">Google Cloud Console</p>
                    <ul class="list-disc list-inside space-y-1 text-slate-600">
                        <li>Create a project, enable billing, then enable <strong>Geocoding API</strong> and <strong>Places API (New)</strong>. The legacy <strong>Places API</strong> (old) is <em>not</em> enough — Cold calling uses <code class="text-xs bg-white px-1 rounded">places.googleapis.com</code> (SearchNearby, Text Search, Place Details).</li>
                        <li>Credentials → your API key → <strong>API restrictions</strong> must include <strong>Places API (New)</strong> and <strong>Geocoding API</strong> (or “Don’t restrict key” while testing). If SearchNearby returns <code class="text-xs bg-white px-1 rounded">API_KEY_SERVICE_BLOCKED</code>, the new Places API is missing from that list.</li>
                        <li>The app calls Google from your <strong>server</strong>—do not use “HTTP referrers” only (Geocoding returns <code class="text-xs bg-white px-1 rounded">REQUEST_DENIED</code>). Use <strong>IP addresses</strong> or <strong>None</strong> for testing.</li>
                        <li>If Geocoding is denied, the CRM tries <strong>Places Text Search</strong> for the postcode centre (still requires Places API New on the key).</li>
                    </ul>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Google Maps API key</label>
                        <input
                            v-model="coldCallingSettings.google_maps_api_key"
                            type="password"
                            autocomplete="off"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500 font-mono text-sm"
                            placeholder="AIza…"
                        >
                        <p class="text-xs text-slate-500 mt-1">Leave blank when saving to keep the existing key unchanged.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Default search radius (meters)</label>
                        <input
                            v-model.number="coldCallingSettings.cold_calling_default_radius_meters"
                            type="number"
                            min="500"
                            max="50000"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        >
                        <p class="text-xs text-slate-500 mt-1">Max 50&nbsp;000 (Google limit).</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">New businesses per search (max)</label>
                        <input
                            v-model.number="coldCallingSettings.cold_calling_max_places_per_run"
                            type="number"
                            min="1"
                            max="100"
                            class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-emerald-500"
                        >
                        <p class="text-xs text-slate-500 mt-1">
                            Each run adds up to this many <strong>new</strong> Google places (by <code class="text-xs bg-white px-1 rounded">place_id</code>). Rows already in cold calling are skipped for insert and only linked to this postcode. Text search keeps paging until the target is reached or Google has no more results.
                        </p>
                    </div>
                    <div class="sm:col-span-2 rounded-xl border border-sky-200 bg-sky-50/50 p-4 space-y-3">
                        <p class="text-sm font-semibold text-slate-900">Small cafes &amp; independent businesses</p>
                        <p class="text-xs text-slate-600">
                            Google does not expose “company size”. <strong>Nearby Search</strong> is limited to food &amp; drink + high-street retail types (restaurants, cafés, bakeries, bars, takeaways, clothing, gifts, florists, etc.). <strong>Text search</strong> uses an indie restaurant / café / retail query by default. You can still <strong>drop</strong> huge chains via review cap, name blocklist, and excluded place types after Place Details.
                        </p>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Places text search query</label>
                            <textarea
                                v-model="coldCallingSettings.cold_calling_text_search_query"
                                rows="3"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 text-sm"
                                placeholder="Leave empty for built-in indie cafe / small food query. Use {postcode} for the run’s postcode."
                            />
                            <p class="text-xs text-slate-500 mt-1">Empty = default wording (cafes, bakeries, small restaurants, etc.). Max ~480 characters sent to Google.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Skip new row if Google reviews over</label>
                            <input
                                v-model.number="coldCallingSettings.cold_calling_skip_if_reviews_over"
                                type="number"
                                min="0"
                                max="500000"
                                class="w-full max-w-xs px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500"
                            >
                            <p class="text-xs text-slate-500 mt-1"><strong>0</strong> = off. Try <strong>80–200</strong> to reduce huge national chains (imperfect). Busy independents can also have many reviews.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Exclude name contains (comma-separated)</label>
                            <input
                                v-model="coldCallingSettings.cold_calling_discovery_exclude_names"
                                type="text"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 text-sm"
                                placeholder="e.g. Tesco, Sainsbury's, McDonald's, Starbucks"
                            >
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Exclude Google place types</label>
                            <input
                                v-model="coldCallingSettings.cold_calling_discovery_exclude_types"
                                type="text"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-sky-500 font-mono text-sm"
                                placeholder="default"
                            >
                            <p class="text-xs text-slate-500 mt-1">
                                <code class="bg-white px-1 rounded">default</code> or empty = large-format retail (department_store, shopping_mall, supermarket, hypermarket, discount_supermarket, etc.).
                                <code class="bg-white px-1 rounded">none</code> = do not filter by type. Or list types yourself (comma-separated, snake_case).
                            </p>
                        </div>
                    </div>
                    <div class="sm:col-span-2 flex items-start gap-3 p-4 border border-slate-200 rounded-xl bg-amber-50/50">
                        <input
                            id="cold_enrich"
                            v-model="coldCallingSettings.cold_calling_enrich_email"
                            type="checkbox"
                            class="mt-1 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                        >
                        <label for="cold_enrich" class="text-sm text-slate-700 cursor-pointer">
                            <span class="font-medium">Try to find email from business website</span>
                            <span class="block text-slate-500 mt-0.5">Fetches homepage plus common paths like /contact (mailto, tel:, UK numbers in text). Slow; many sites block bots or hide details. Also enable per run under Marketing → Cold calling, or use “Fill from websites” on saved contacts.</span>
                        </label>
                    </div>
                    <div class="sm:col-span-2 rounded-xl border border-violet-200 bg-violet-50/40 p-4 space-y-3">
                        <p class="text-sm font-semibold text-slate-900">Claude AI (Anthropic) — extra email / phone pass</p>
                        <p class="text-xs text-slate-600">
                            After pages are fetched, if email or phone is still missing, the CRM sends <strong>plain text from those pages</strong> to Claude and asks for JSON <code class="text-[11px] bg-white px-1 rounded">email</code> / <code class="text-[11px] bg-white px-1 rounded">phone</code> only. Put your API key in <code class="text-[11px] bg-white px-1 rounded">.env</code> as <code class="text-[11px] bg-white px-1 rounded">ANTHROPIC_API_KEY</code> or below. <strong>Never commit keys to git.</strong> Rotate any key that was pasted into chat or tickets.
                        </p>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Anthropic API key</label>
                            <input
                                v-model="coldCallingSettings.anthropic_api_key"
                                type="password"
                                autocomplete="off"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 font-mono text-sm"
                                placeholder="sk-ant-api03-…"
                            >
                            <p class="text-xs text-slate-500 mt-1">Leave blank when saving to keep the existing key.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Claude model ID</label>
                            <input
                                v-model="coldCallingSettings.anthropic_model"
                                type="text"
                                class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-violet-500 font-mono text-sm"
                                placeholder="claude-sonnet-4-20250514"
                            >
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="coldCallingSettings.cold_calling_use_claude"
                                type="checkbox"
                                class="rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                            >
                            <span class="text-sm text-slate-700">Use Claude when scrape did not find email or phone (uses API credits)</span>
                        </label>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        @click="saveColdCallingSettings"
                        :disabled="savingColdCalling"
                        class="px-6 py-2 bg-emerald-700 text-white rounded-lg hover:bg-emerald-800 transition-colors disabled:opacity-50"
                    >
                        {{ savingColdCalling ? 'Saving…' : 'Save cold calling settings' }}
                    </button>
                </div>
            </div>

            <!-- PWA Settings Section -->
            <div v-show="activeSection === 'pwa'" class="bg-white rounded-xl shadow-sm p-6">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 bg-indigo-100 rounded-xl flex items-center justify-center">
                        <span class="text-lg">📲</span>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Progressive Web App (PWA)</h2>
                        <p class="text-sm text-slate-500">Allow users to install the app on their devices</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <!-- PWA Enable Toggle -->
                    <div class="flex items-center justify-between p-4 bg-slate-50 rounded-xl">
                        <div>
                            <h3 class="font-medium text-slate-900">Enable PWA Install Prompt</h3>
                            <p class="text-sm text-slate-500 mt-0.5">
                                When enabled, users will see an "Install App" button on mobile devices
                            </p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="settings.pwa_enabled"
                                @change="updatePwaSetting"
                                class="sr-only peer"
                            >
                            <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-100 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <!-- PWA Status -->
                    <div class="p-4 border border-slate-200 rounded-xl space-y-3">
                        <h3 class="font-medium text-slate-900">PWA Status</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full" :class="pwaStatus.serviceWorker ? 'bg-green-500' : 'bg-red-500'"></span>
                                <span class="text-slate-600">Service Worker:</span>
                                <span class="font-medium" :class="pwaStatus.serviceWorker ? 'text-green-600' : 'text-red-600'">
                                    {{ pwaStatus.serviceWorker ? 'Registered' : 'Not Registered' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full" :class="pwaStatus.manifest ? 'bg-green-500' : 'bg-red-500'"></span>
                                <span class="text-slate-600">Manifest:</span>
                                <span class="font-medium" :class="pwaStatus.manifest ? 'text-green-600' : 'text-red-600'">
                                    {{ pwaStatus.manifest ? 'Found' : 'Not Found' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full" :class="pwaStatus.https ? 'bg-green-500' : 'bg-amber-500'"></span>
                                <span class="text-slate-600">HTTPS:</span>
                                <span class="font-medium" :class="pwaStatus.https ? 'text-green-600' : 'text-amber-600'">
                                    {{ pwaStatus.https ? 'Enabled' : 'Development Mode' }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full" :class="pwaStatus.installable ? 'bg-green-500' : 'bg-slate-400'"></span>
                                <span class="text-slate-600">Installable:</span>
                                <span class="font-medium" :class="pwaStatus.installable ? 'text-green-600' : 'text-slate-500'">
                                    {{ pwaStatus.installable ? 'Yes' : 'Not Available' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import axios from 'axios';
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
    if (!r.success) return 'bg-red-50 border-red-200';
    if (r.hint || r.token_inspection?.detail === 'missing_app_credentials' || r.token_inspection?.can_send === null) {
        return 'bg-amber-50 border-amber-200';
    }
    return 'bg-green-50 border-green-200';
});

const whatsappCloudTestResultTextClass = computed(() => {
    const r = whatsappCloudTestResult.value;
    if (!r) return '';
    if (!r.success) return 'text-red-800';
    if (r.hint || r.token_inspection?.detail === 'missing_app_credentials' || r.token_inspection?.can_send === null) {
        return 'text-amber-900';
    }
    return 'text-green-800';
});

const savingFacebook = ref(false);
const savingColdCalling = ref(false);
const testingSmtp = ref(false);
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
    { id: 'branding', name: 'Branding', icon: '🎨' },
    { id: 'company', name: 'Company', icon: '🏢' },
    { id: 'email', name: 'Email/SMTP', icon: '📧' },
    { id: 'sms', name: 'SMS', icon: '📱' },
    { id: 'whatsapp', name: 'WhatsApp', icon: '💬' },
    { id: 'facebook', name: 'Facebook', icon: '📘' },
    { id: 'cold_calling', name: 'Cold calling', icon: '📞' },
    { id: 'pwa', name: 'PWA', icon: '📲' },
];

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
