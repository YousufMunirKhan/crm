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
const testingSmtp = ref(false);
const uploadingLogo = ref(false);
const uploadingFavicon = ref(false);
const testEmail = ref('');
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
    } catch (error) {
        console.error('Failed to load settings:', error);
    } finally {
        loading.value = false;
    }
    
    // Load WhatsApp Cloud API settings
    loadWhatsappCloudSettings();
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

onMounted(() => {
    loadSettings();
    checkPwaStatus();
    setTimeout(checkPwaStatus, 2000);
});
</script>
