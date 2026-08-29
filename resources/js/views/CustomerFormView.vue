<template>
    <div class="min-h-screen bg-slate-50 w-full min-w-0 overflow-x-hidden">
        <div class="max-w-4xl mx-auto px-3 sm:px-6 py-6 lg:py-8 w-full min-w-0">
            <!-- Header -->
            <div class="mb-6 lg:mb-8">
                <BaseButton :to="backRoute" variant="ghost" size="sm">
                    <template #icon>
                        <ArrowLeftIcon class="icon" aria-hidden="true" />
                    </template>
                    {{ form.type === 'prospect' ? 'Back to Prospects' : 'Back to Customers' }}
                </BaseButton>
            </div>

            <!-- Form Card -->
            <form id="customer-form" novalidate class="form-card" @submit.prevent="handleSubmit">
                <div class="form-section-head-mint">
                    <h1 class="form-section-title-mint text-2xl sm:text-3xl">
                        {{ isEdit ? (form.type === 'customer' ? 'Edit Customer' : 'Edit Prospect') : (form.type === 'customer' ? 'Add Customer' : 'Add Prospect') }}
                    </h1>
                    <p class="form-section-desc-mint">
                        {{ isEdit ? (form.type === 'customer' ? 'Update customer information' : 'Update prospect information') : (form.type === 'customer' ? 'Fill in the details to add a customer' : 'Fill in the details to add a prospect') }}
                    </p>
                </div>
                <div class="form-body space-y-6 lg:space-y-8">
                    <!-- Validation summary: focused on a failed submit -->
                    <div
                        v-if="error || errorFields.length"
                        ref="errorSummaryRef"
                        class="callout callout-danger focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-danger-600/40"
                        role="alert"
                        tabindex="-1"
                    >
                        <p class="font-semibold">
                            {{ errorFields.length ? 'Please fix the following before saving:' : 'This customer could not be saved' }}
                        </p>
                        <ul v-if="errorFields.length" class="mt-1.5 list-disc pl-5 space-y-0.5">
                            <li v-for="failed in errorFields" :key="failed.field">
                                <span class="font-medium">{{ failed.label }}</span> — {{ failed.message }}
                            </li>
                        </ul>
                        <p v-else class="mt-1">{{ error }}</p>
                    </div>

                    <nav v-if="!isEdit && !isSimpleCustomerCreate" class="mb-2" aria-label="Create customer steps">
                        <div class="flex items-center gap-2 sm:gap-3 overflow-x-auto pb-1">
                            <button
                                v-for="step in createSteps"
                                :key="step.id"
                                type="button"
                                class="inline-flex items-center gap-2 px-3 py-2 rounded-control border text-xs sm:text-sm whitespace-nowrap transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500/40"
                                :class="currentStep === step.id ? 'border-primary-500 bg-primary-50 text-primary-700' : 'border-slate-200 text-slate-600 hover:border-slate-300'"
                                :aria-current="currentStep === step.id ? 'step' : undefined"
                                @click="goToStep(step.id)"
                            >
                                <span
                                    class="w-5 h-5 rounded-full text-[11px] flex items-center justify-center shrink-0"
                                    :class="currentStep === step.id ? 'bg-primary-600 text-white' : 'bg-slate-200 text-slate-700'"
                                    aria-hidden="true"
                                >
                                    {{ step.id }}
                                </span>
                                {{ step.title }}
                            </button>
                        </div>
                    </nav>
                    <!-- Required fields -->
                    <div v-show="isEdit || isSimpleCustomerCreate || currentStep === 1">
                        <h2 class="text-base font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-slate-900 text-white text-xs flex items-center justify-center">1</span>
                            Basic Information
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="sm:col-span-2">
                                <label class="form-label" for="customerformview-customer-name">
                                    Customer Name <span class="form-required" aria-hidden="true">*</span>
                                </label>
                                <input id="customerformview-customer-name"
                                    v-model="form.name"
                                    type="text"
                                    placeholder="Full name or primary contact"
                                    class="form-input"
                                    :aria-invalid="fieldErrors.name ? 'true' : undefined"
                                    :aria-describedby="fieldErrors.name ? 'customerformview-customer-name-error' : undefined"
                                />
                                <p v-if="fieldErrors.name" id="customerformview-customer-name-error" class="form-error">
                                    {{ fieldErrors.name }}
                                </p>
                            </div>
                            <div>
                                <label class="form-label" for="customerformview-business-name">Business Name</label>
                                <input id="customerformview-business-name"
                                    v-model="form.business_name"
                                    type="text"
                                    placeholder="Company or business name"
                                    class="form-input"
                                />
                            </div>
                            <div v-if="isEdit || !isSimpleCustomerCreate">
                                <label class="form-label" for="customerformview-owner-name">Owner Name</label>
                                <input id="customerformview-owner-name"
                                    v-model="form.owner_name"
                                    type="text"
                                    placeholder="Owner or director name"
                                    class="form-input"
                                />
                            </div>
                            <div v-if="isEdit || !isSimpleCustomerCreate">
                                <label class="form-label" for="customerformview-contact-person-2-name">Contact Person 2 Name</label>
                                <input id="customerformview-contact-person-2-name"
                                    v-model="form.contact_person_2_name"
                                    type="text"
                                    placeholder="Second contact name"
                                    class="form-input"
                                />
                            </div>
                            <div v-if="isEdit || !isSimpleCustomerCreate">
                                <label class="form-label" for="customerformview-contact-person-2-phone">Contact Person 2 Phone</label>
                                <input id="customerformview-contact-person-2-phone"
                                    v-model="form.contact_person_2_phone"
                                    type="tel"
                                    placeholder="e.g. 07700900123"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label" for="customerformview-phone">
                                    Phone <span class="form-required" aria-hidden="true">*</span>
                                </label>
                                <input id="customerformview-phone"
                                    v-model="form.phone"
                                    type="tel"
                                    placeholder="e.g. 07700900123"
                                    class="form-input"
                                    :aria-invalid="fieldErrors.phone ? 'true' : undefined"
                                    :aria-describedby="fieldErrors.phone ? 'customerformview-phone-error' : undefined"
                                    @blur="syncPhoneToWhatsApp"
                                />
                                <p v-if="fieldErrors.phone" id="customerformview-phone-error" class="form-error">
                                    {{ fieldErrors.phone }}
                                </p>
                            </div>
                            <div v-if="isEdit || !isSimpleCustomerCreate">
                                <label class="form-label" for="customerformview-customer-whatsapp">Customer WhatsApp</label>
                                <input id="customerformview-customer-whatsapp"
                                    v-model="form.whatsapp_number"
                                    type="tel"
                                    placeholder="e.g. 447700900123"
                                    class="form-input"
                                    aria-describedby="customerformview-customer-whatsapp-hint"
                                    @blur="syncWhatsAppToPhone"
                                />
                                <p id="customerformview-customer-whatsapp-hint" class="form-hint">
                                    Phone and WhatsApp sync when one is empty; you can change either.
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="form-label" for="customerformview-customer-email">Customer Email</label>
                                <input id="customerformview-customer-email"
                                    v-model="form.email"
                                    type="email"
                                    placeholder="customer@example.com"
                                    class="form-input"
                                    :aria-describedby="isSimpleCustomerCreate ? 'customerformview-customer-email-hint' : undefined"
                                />
                                <p v-if="isSimpleCustomerCreate" id="customerformview-customer-email-hint" class="form-hint">
                                    The welcome email will be sent to this address after the customer is created.
                                </p>
                            </div>
                            <div v-if="isSimpleCustomerCreate" class="sm:col-span-2">
                                <label class="form-label" for="customerformview-welcome-email-template">Welcome Email Template</label>
                                <select id="customerformview-welcome-email-template"
                                    v-model="welcomeEmailTemplateId"
                                    class="form-select"
                                    aria-describedby="customerformview-welcome-email-template-hint"
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
                                <p id="customerformview-welcome-email-template-hint" class="form-hint">
                                    Email is optional. If an email is entered, this template will be sent. Leave unchanged to use the generic welcome template.
                                </p>
                            </div>
                            <div v-if="isEdit || !isSimpleCustomerCreate" class="sm:col-span-2">
                                <label class="form-label" for="customerformview-source">Source</label>
                                <select
                                    id="customerformview-source"
                                    v-model="form.source"
                                    class="form-select"
                                >
                                    <option value="">Select Source</option>
                                    <option value="call_center">Call Center</option>
                                    <option value="ground_field">Ground Field</option>
                                    <option value="website">Website</option>
                                    <option value="meta">Meta</option>
                                    <option value="tiktok">TikTok</option>
                                    <option value="google_ads">Google Ads</option>
                                    <option value="organic_lead">Organic Lead</option>
                                </select>
                            </div>
                            <div v-if="isSimpleCustomerCreate" class="sm:col-span-2">
                                <label class="form-label" for="customerformview-address">Address</label>
                                <textarea id="customerformview-address"
                                    v-model="form.address"
                                    rows="2"
                                    placeholder="Street address"
                                    class="form-textarea min-h-0"
                                />
                            </div>
                            <div v-if="isSimpleCustomerCreate">
                                <label class="form-label" for="customerformview-city">City</label>
                                <input id="customerformview-city"
                                    v-model="form.city"
                                    type="text"
                                    placeholder="City"
                                    class="form-input"
                                />
                            </div>
                            <div v-if="isSimpleCustomerCreate">
                                <label class="form-label" for="customerformview-postcode">Postcode</label>
                                <input id="customerformview-postcode"
                                    v-model="form.postcode"
                                    type="text"
                                    placeholder="Postcode"
                                    class="form-input"
                                />
                            </div>
                            <fieldset v-if="isSimpleCustomerCreate" class="form-fieldset sm:col-span-2">
                                <legend class="form-label">Won Product (Optional)</legend>
                                <div class="border border-slate-200 rounded-control p-3 max-h-40 overflow-y-auto bg-white">
                                    <label v-for="p in products" :key="p.id" class="form-choice py-1.5 w-full">
                                        <input v-model="wonProductIds" type="checkbox" :value="p.id" class="form-checkbox" />
                                        <span class="text-sm font-normal">{{ p.name }}</span>
                                    </label>
                                    <p v-if="!products.length" class="text-sm text-slate-500 py-2">Loading products...</p>
                                </div>
                                <p class="form-hint">If selected, these products will be recorded as won after customer creation.</p>
                            </fieldset>
                        </div>
                    </div>

                    <!-- Optional remote/license fields (multiple) -->
                    <div v-show="isEdit || (!isSimpleCustomerCreate && currentStep === 2)">
                        <h2 class="text-base font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-xs flex items-center justify-center">2</span>
                            Remote & License (Optional)
                        </h2>
                        <p class="text-sm text-slate-500 mb-4">Add one or more Remote/License entries. Use + Add to add more.</p>
                        <div
                            v-for="(rl, idx) in form.remote_licenses"
                            :key="idx"
                            class="mb-6 p-4 border border-slate-200 rounded-card bg-slate-50/50 space-y-4"
                        >
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <span class="text-sm font-medium text-slate-700">Entry {{ idx + 1 }}</span>
                                <BaseButton
                                    v-if="form.remote_licenses.length > 1"
                                    variant="ghost"
                                    size="sm"
                                    class="text-danger-700 hover:bg-danger-50 hover:text-danger-800"
                                    @click="removeRemoteLicense(idx)"
                                >
                                    <template #icon>
                                        <TrashIcon class="icon-sm" aria-hidden="true" />
                                    </template>
                                    Remove
                                </BaseButton>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="sm:col-span-2 lg:col-span-1">
                                    <label class="form-label" :for="`customerformview-anydesk-or-rustdesk-${idx}`">Anydesk or Rustdesk</label>
                                    <input :id="`customerformview-anydesk-or-rustdesk-${idx}`"
                                        v-model="rl.anydesk_rustdesk"
                                        type="text"
                                        placeholder="ID or connection details"
                                        class="form-input"
                                    />
                                </div>
                                <div class="sm:col-span-2 lg:col-span-1">
                                    <label class="form-label" :for="`customerformview-passwords-${idx}`">Passwords</label>
                                    <input :id="`customerformview-passwords-${idx}`"
                                        v-model="rl.passwords"
                                        type="text"
                                        placeholder="Relevant passwords"
                                        class="form-input"
                                    />
                                </div>
                                <div>
                                    <label class="form-label" :for="`customerformview-epos-type-${idx}`">ePOS Type</label>
                                    <input :id="`customerformview-epos-type-${idx}`"
                                        v-model="rl.epos_type"
                                        type="text"
                                        placeholder="e.g. TouchBistro, Square"
                                        class="form-input"
                                    />
                                </div>
                                <div>
                                    <label class="form-label" :for="`customerformview-lic-days-optional-${idx}`">Lic-days (Optional)</label>
                                    <input :id="`customerformview-lic-days-optional-${idx}`"
                                        v-model="rl.lic_days"
                                        type="text"
                                        placeholder="e.g. 30, 90, 1 Year"
                                        class="form-input"
                                    />
                                </div>
                            </div>
                        </div>
                        <BaseButton variant="outline" size="sm" @click="addRemoteLicense">
                            <template #icon>
                                <PlusIcon class="icon-sm" aria-hidden="true" />
                            </template>
                            Add Remote &amp; License
                        </BaseButton>
                    </div>

                    <!-- Address -->
                    <div v-show="isEdit || (!isSimpleCustomerCreate && currentStep === 3)">
                        <h2 class="text-base font-semibold text-slate-900 mb-4 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-xs flex items-center justify-center">3</span>
                            Address & Notes
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label class="form-label" for="customerformview-address-2">Address</label>
                                <textarea id="customerformview-address-2"
                                    v-model="form.address"
                                    rows="2"
                                    placeholder="Street address"
                                    class="form-textarea min-h-0"
                                />
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label" for="customerformview-city-2">City</label>
                                    <input id="customerformview-city-2"
                                        v-model="form.city"
                                        type="text"
                                        placeholder="City"
                                        class="form-input"
                                    />
                                </div>
                                <div>
                                    <label class="form-label" for="customerformview-postcode-2">Postcode</label>
                                    <input id="customerformview-postcode-2"
                                        v-model="form.postcode"
                                        type="text"
                                        placeholder="Postcode"
                                        class="form-input"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="form-label" for="customerformview-vat-number">VAT Number</label>
                                <input id="customerformview-vat-number"
                                    v-model="form.vat_number"
                                    type="text"
                                    placeholder="VAT number"
                                    class="form-input"
                                />
                            </div>
                            <div>
                                <label class="form-label" for="customerformview-notes">Notes</label>
                                <textarea id="customerformview-notes"
                                    v-model="form.notes"
                                    rows="3"
                                    placeholder="Additional notes"
                                    class="form-textarea"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Also create: Follow-up / Appointment / Lead (prospect flow only) -->
                    <div v-if="!isEdit && !isSimpleCustomerCreate" v-show="currentStep === 4" ref="quickAddSectionRef" class="border-t border-slate-200 pt-6">
                        <h2 class="text-base font-semibold text-slate-900 mb-3 flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-xs flex items-center justify-center">4</span>
                            Also create (optional)
                        </h2>
                        <p class="text-sm text-slate-500 mb-4">Quickly add a follow-up, appointment, or lead when creating this customer.</p>
                        <fieldset class="form-fieldset mb-4">
                            <legend class="sr-only">What would you like to also create?</legend>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <label
                                    class="flex items-center gap-2 p-3 rounded-card border-2 cursor-pointer transition-all has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500/40"
                                    :class="!quickAddType ? 'border-slate-300 bg-slate-100' : 'border-slate-200 hover:border-slate-300'"
                                >
                                    <input v-model="quickAddType" type="radio" value="" class="sr-only" />
                                    <MinusIcon class="icon-sm text-slate-500" aria-hidden="true" />
                                    <span class="font-medium text-sm">None</span>
                                </label>
                                <label
                                    class="flex items-center gap-2 p-3 rounded-card border-2 cursor-pointer transition-all has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500/40"
                                    :class="quickAddType === 'follow_up' ? 'border-primary-500 bg-primary-50' : 'border-slate-200 hover:border-slate-300'"
                                >
                                    <input v-model="quickAddType" type="radio" value="follow_up" class="sr-only" />
                                    <ArrowPathIcon class="icon-sm text-slate-500" aria-hidden="true" />
                                    <span class="font-medium text-sm">Follow-up</span>
                                </label>
                                <label
                                    class="flex items-center gap-2 p-3 rounded-card border-2 cursor-pointer transition-all has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500/40"
                                    :class="quickAddType === 'appointment' ? 'border-primary-500 bg-primary-50' : 'border-slate-200 hover:border-slate-300'"
                                >
                                    <input v-model="quickAddType" type="radio" value="appointment" class="sr-only" />
                                    <CalendarDaysIcon class="icon-sm text-slate-500" aria-hidden="true" />
                                    <span class="font-medium text-sm">Appointment</span>
                                </label>
                                <label
                                    class="flex items-center gap-2 p-3 rounded-card border-2 cursor-pointer transition-all has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-primary-500/40"
                                    :class="quickAddType === 'lead' ? 'border-primary-500 bg-primary-50' : 'border-slate-200 hover:border-slate-300'"
                                >
                                    <input v-model="quickAddType" type="radio" value="lead" class="sr-only" />
                                    <PlusIcon class="icon-sm text-slate-500" aria-hidden="true" />
                                    <span class="font-medium text-sm">Lead</span>
                                </label>
                            </div>
                        </fieldset>
                        <div v-if="quickAddType" class="space-y-4 p-4 bg-slate-50 rounded-card">
                            <fieldset class="form-fieldset">
                                <legend class="form-label">
                                    Product(s) <span class="form-required" aria-hidden="true">*</span>
                                </legend>
                                <div class="border border-slate-200 rounded-control p-3 max-h-40 overflow-y-auto bg-white">
                                    <label v-for="p in products" :key="p.id" class="form-choice py-1.5 w-full">
                                        <input v-model="quickAddProductIds" type="checkbox" :value="p.id" class="form-checkbox" />
                                        <span class="text-sm font-normal">{{ p.name }}</span>
                                    </label>
                                </div>
                                <p v-if="fieldErrors.quickAddProducts" class="form-error">{{ fieldErrors.quickAddProducts }}</p>
                            </fieldset>
                            <div>
                                <label class="form-label" for="customerformview-quick-add-notes">Notes</label>
                                <textarea
                                    id="customerformview-quick-add-notes"
                                    v-model="quickAddComment"
                                    rows="2"
                                    class="form-textarea min-h-0"
                                    placeholder="Comment or notes..."
                                />
                            </div>
                            <div v-if="quickAddType === 'follow_up'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label" for="customerformview-follow-up-at">
                                        Follow-up Date &amp; Time <span class="form-required" aria-hidden="true">*</span>
                                    </label>
                                    <input
                                        id="customerformview-follow-up-at"
                                        v-model="quickAddFollowUpAt"
                                        type="datetime-local"
                                        class="form-input"
                                        :aria-invalid="fieldErrors.quickAddFollowUpAt ? 'true' : undefined"
                                    />
                                    <p v-if="fieldErrors.quickAddFollowUpAt" class="form-error">{{ fieldErrors.quickAddFollowUpAt }}</p>
                                </div>
                            </div>
                            <div v-if="quickAddType === 'appointment'" class="space-y-4">
                                <div>
                                    <label class="form-label" for="customerformview-assign-to-who-will-attend">
                                        Assign to (who will attend) <span class="form-required" aria-hidden="true">*</span>
                                    </label>
                                    <select
                                        id="customerformview-assign-to-who-will-attend"
                                        v-model="quickAddAssignedUserId"
                                        class="form-select"
                                        :aria-invalid="fieldErrors.quickAddAssignedUserId ? 'true' : undefined"
                                    >
                                        <option value="">Select team member...</option>
                                        <option v-for="u in users" :key="u.id" :value="u.id">{{ u.name }} ({{ u.role?.name || '—' }})</option>
                                    </select>
                                    <p v-if="fieldErrors.quickAddAssignedUserId" class="form-error">{{ fieldErrors.quickAddAssignedUserId }}</p>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                    <div>
                                        <label class="form-label" for="customerformview-appointment-date">
                                            Date <span class="form-required" aria-hidden="true">*</span>
                                        </label>
                                        <input
                                            id="customerformview-appointment-date"
                                            v-model="quickAddAppointmentDate"
                                            type="date"
                                            class="form-input"
                                            :aria-invalid="fieldErrors.quickAddAppointmentDate ? 'true' : undefined"
                                        />
                                        <p v-if="fieldErrors.quickAddAppointmentDate" class="form-error">{{ fieldErrors.quickAddAppointmentDate }}</p>
                                    </div>
                                    <div>
                                        <label class="form-label" for="customerformview-appointment-time">
                                            Time <span class="form-required" aria-hidden="true">*</span>
                                        </label>
                                        <input
                                            id="customerformview-appointment-time"
                                            v-model="quickAddAppointmentTime"
                                            type="time"
                                            class="form-input"
                                            :aria-invalid="fieldErrors.quickAddAppointmentTime ? 'true' : undefined"
                                        />
                                        <p v-if="fieldErrors.quickAddAppointmentTime" class="form-error">{{ fieldErrors.quickAddAppointmentTime }}</p>
                                    </div>
                                </div>
                            </div>
                            <div v-if="quickAddType === 'lead'" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="form-label" for="customerformview-stage">Stage</label>
                                    <select id="customerformview-stage" v-model="quickAddStage" class="form-select">
                                        <option value="follow_up">Follow-up</option>
                                        <option value="lead">Lead</option>
                                        <option value="hot_lead">Hot Lead</option>
                                        <option value="quotation">Quotation</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label" for="customerformview-expected-closing-date">Expected Closing Date</label>
                                    <input id="customerformview-expected-closing-date" v-model="quickAddExpectedDate" type="date" class="form-input" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer actions -->
                <div class="form-actions">
                    <BaseButton :to="backRoute" variant="outline" block-mobile>Cancel</BaseButton>
                    <BaseButton
                        v-if="!isEdit && !isSimpleCustomerCreate && currentStep > 1"
                        variant="outline"
                        block-mobile
                        @click="prevStep"
                    >
                        <template #icon>
                            <ArrowLeftIcon class="icon" aria-hidden="true" />
                        </template>
                        Back
                    </BaseButton>
                    <BaseButton
                        v-if="!isEdit && !isSimpleCustomerCreate && currentStep < 4"
                        variant="soft"
                        block-mobile
                        @click="nextStep"
                    >
                        <template #icon>
                            <ArrowRightIcon class="icon" aria-hidden="true" />
                        </template>
                        Next
                    </BaseButton>
                    <BaseButton
                        v-if="isEdit || isSimpleCustomerCreate || currentStep === 4"
                        variant="primary"
                        type="submit"
                        form="customer-form"
                        block-mobile
                        :loading="loading"
                    >
                        {{ isEdit ? 'Update Customer' : (form.type === 'customer' ? 'Create Customer' : 'Create Prospect') }}
                    </BaseButton>
                </div>
            </form>
        </div>

        <!-- Sale credit (admin/manager): after customer created with won products — notification only -->
        <BaseModal
            v-model="showSaleCreditModal"
            title="Sale Credit"
            :description="saleCreditContextText"
            size="sm"
            :close-on-backdrop="false"
            @close="finishSaleCreditSkip"
        >
            <div class="space-y-3">
                <div>
                    <label class="form-label" for="customerformview-who-should-this-sale-go-on">Who should this sale go on?</label>
                    <select
                        id="customerformview-who-should-this-sale-go-on"
                        v-model="selectedSaleCreditUserId"
                        class="form-select"
                    >
                        <option value="">Select user...</option>
                        <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }} ({{ u.role?.name || '—' }})</option>
                    </select>
                </div>
            </div>
            <template #actions>
                <BaseButton variant="outline" block-mobile @click="finishSaleCreditSkip">Skip</BaseButton>
                <BaseButton
                    variant="success"
                    block-mobile
                    :disabled="!selectedSaleCreditUserId"
                    @click="finishSaleCreditConfirm"
                >
                    Confirm
                </BaseButton>
            </template>
        </BaseModal>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted, watch, nextTick } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from 'axios';
import {
    ArrowLeftIcon,
    ArrowPathIcon,
    ArrowRightIcon,
    CalendarDaysIcon,
    MinusIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline';
import { BaseButton, BaseModal } from '@/components/base';
import { useToastStore } from '@/stores/toast';
import { useAuthStore } from '@/stores/auth';

const route = useRoute();
const router = useRouter();
const toast = useToastStore();
const auth = useAuthStore();

const isEdit = computed(() => !!route.params.id);

/** Create mode: derive type from URL immediately so customer add never flashes prospect UI first. */
function createTypeFromRoute() {
    return route.query.type === 'customer' ? 'customer' : 'prospect';
}

const form = reactive({
    type: isEdit.value ? 'prospect' : createTypeFromRoute(),
    name: '',
    business_name: '',
    owner_name: '',
    contact_person_2_name: '',
    contact_person_2_phone: '',
    phone: '',
    email: '',
    whatsapp_number: '',
    address: '',
    postcode: '',
    city: '',
    vat_number: '',
    notes: '',
    source: '',
    remote_licenses: [{ anydesk_rustdesk: '', passwords: '', epos_type: '', lic_days: null }],
});

const isSimpleCustomerCreate = computed(() => !isEdit.value && form.type === 'customer');

/** Same destination the Cancel link and the back link have always used. */
const backRoute = computed(() =>
    form.type === 'prospect'
        ? { path: '/customers', query: { type: 'prospect' } }
        : { path: '/customers', query: { type: 'customer' } },
);

const isSaleCreditRole = computed(() => {
    const r = auth.user?.role?.name;
    return r === 'Admin' || r === 'System Admin' || r === 'Manager';
});

const showSaleCreditModal = ref(false);
const selectedSaleCreditUserId = ref('');
const saleCreditContextText = ref('');
const saleCreditPendingRoute = ref(null);
const saleCreditLeadId = ref(null);

function addRemoteLicense() {
    form.remote_licenses.push({ anydesk_rustdesk: '', passwords: '', epos_type: '', lic_days: null });
}

function removeRemoteLicense(idx) {
    form.remote_licenses.splice(idx, 1);
}

const loading = ref(false);
const error = ref(null);
const products = ref([]);
const users = ref([]);
const wonProductIds = ref([]);
const emailTemplates = ref([]);
const welcomeEmailTemplateId = ref('');
const quickAddType = ref('');
const quickAddComment = ref('');
const quickAddFollowUpAt = ref('');
const quickAddAssignedUserId = ref('');
const quickAddAppointmentDate = ref('');
const quickAddAppointmentTime = ref('10:00');
const quickAddStage = ref('follow_up');
const quickAddExpectedDate = ref('');
const quickAddProductIds = ref([]);
const quickAddSectionRef = ref(null);
const currentStep = ref(1);
const createSteps = [
    { id: 1, title: 'Basic' },
    { id: 2, title: 'Remote & License' },
    { id: 3, title: 'Address & Notes' },
    { id: 4, title: 'Also Create' },
];

/** Error summary is focused whenever a submit or a step change fails. */
const errorSummaryRef = ref(null);
const errorFields = ref([]);
const fieldErrors = reactive({
    name: '',
    phone: '',
    quickAddProducts: '',
    quickAddFollowUpAt: '',
    quickAddAssignedUserId: '',
    quickAddAppointmentDate: '',
    quickAddAppointmentTime: '',
});

const FIELD_LABELS = {
    name: 'Customer Name',
    phone: 'Phone',
    quickAddProducts: 'Product(s)',
    quickAddFollowUpAt: 'Follow-up Date & Time',
    quickAddAssignedUserId: 'Assign to (who will attend)',
    quickAddAppointmentDate: 'Appointment date',
    quickAddAppointmentTime: 'Appointment time',
};

function clearValidation() {
    Object.keys(fieldErrors).forEach((key) => {
        fieldErrors[key] = '';
    });
    errorFields.value = [];
}

async function focusErrorSummary() {
    await nextTick();
    errorSummaryRef.value?.focus();
}

/** Publishes the failed fields to the summary + the per-field messages, then takes focus. */
async function reportValidation(failures) {
    clearValidation();
    failures.forEach((failure) => {
        if (failure.field in fieldErrors) fieldErrors[failure.field] = failure.message;
    });
    errorFields.value = failures.map((failure) => ({
        ...failure,
        label: FIELD_LABELS[failure.field] || failure.field,
    }));
    await focusErrorSummary();
}

/**
 * Every failure for a step, in the original first-fail order.
 * @param {number} step
 * @returns {{field: string, message: string}[]}
 */
function collectStepErrors(step) {
    const failures = [];
    if (step === 1) {
        if (!form.name?.trim()) {
            failures.push({ field: 'name', message: 'Customer name is required.' });
        }
        if (!form.phone?.trim()) {
            failures.push({ field: 'phone', message: 'Phone is required.' });
        }
        return failures;
    }
    if (step === 2 || step === 3) {
        return failures;
    }
    if (step === 4) {
        if (!quickAddType.value) return failures;
        const prodIds = quickAddProductIds.value.length ? quickAddProductIds.value : (products.value.length ? [products.value[0].id] : []);
        if (!prodIds.length) {
            failures.push({ field: 'quickAddProducts', message: 'Please select at least one product, or add products in the system.' });
        }
        if (quickAddType.value === 'follow_up' && !quickAddFollowUpAt.value) {
            failures.push({ field: 'quickAddFollowUpAt', message: 'Please set follow-up date and time.' });
        }
        if (quickAddType.value === 'appointment') {
            if (!quickAddAssignedUserId.value) {
                failures.push({ field: 'quickAddAssignedUserId', message: 'Please select who will attend this appointment.' });
            }
            if (!quickAddAppointmentDate.value) {
                failures.push({ field: 'quickAddAppointmentDate', message: 'Please set appointment date and time.' });
            }
            if (!quickAddAppointmentTime.value) {
                failures.push({ field: 'quickAddAppointmentTime', message: 'Please set appointment date and time.' });
            }
        }
        return failures;
    }
    return failures;
}

/**
 * @param {number} step
 * @returns {string|null} Error message or null if valid
 */
function validateStep(step) {
    const failures = collectStepErrors(step);
    return failures.length ? failures[0].message : null;
}

function validateAllStepsForCreate() {
    for (let s = 1; s <= 4; s++) {
        const failures = collectStepErrors(s);
        if (failures.length) return { step: s, message: failures[0].message, failures };
    }
    return null;
}

async function goToStep(stepId) {
    if (isEdit.value || isSimpleCustomerCreate.value) return;
    if (stepId === currentStep.value) return;
    if (stepId < currentStep.value) {
        error.value = null;
        clearValidation();
        currentStep.value = stepId;
        return;
    }
    for (let s = currentStep.value; s < stepId; s++) {
        const failures = collectStepErrors(s);
        if (failures.length) {
            error.value = failures[0].message;
            toast.error(failures[0].message);
            currentStep.value = s;
            await reportValidation(failures);
            return;
        }
    }
    error.value = null;
    clearValidation();
    currentStep.value = stepId;
}

async function nextStep() {
    const failures = collectStepErrors(currentStep.value);
    if (failures.length) {
        error.value = failures[0].message;
        toast.error(failures[0].message);
        await reportValidation(failures);
        return;
    }
    error.value = null;
    clearValidation();
    if (currentStep.value < 4) currentStep.value += 1;
}

function prevStep() {
    error.value = null;
    clearValidation();
    if (currentStep.value > 1) currentStep.value -= 1;
}

function normalizeForWhatsApp(phone) {
    const n = (phone || '').replace(/\s/g, '');
    if (n.startsWith('0') && n.length === 11) return '44' + n.slice(1);
    if (n.startsWith('44') && n.length === 12) return n;
    if (n.startsWith('+44')) return '44' + n.slice(3);
    return n;
}
function normalizeForPhone(wa) {
    const n = (wa || '').replace(/\s/g, '');
    if (n.startsWith('44') && n.length === 12) return '0' + n.slice(2);
    if (n.startsWith('+44')) return '0' + n.slice(3);
    return n;
}
// Phone → WhatsApp: when phone is entered and WhatsApp empty
function syncPhoneToWhatsApp() {
    const phone = (form.phone || '').trim();
    const wa = (form.whatsapp_number || '').trim();
    if (phone && !wa) form.whatsapp_number = normalizeForWhatsApp(phone);
}
// WhatsApp → Phone: when WhatsApp is entered and phone empty
function syncWhatsAppToPhone() {
    const wa = (form.whatsapp_number || '').trim();
    const phone = (form.phone || '').trim();
    if (wa && !phone) form.phone = normalizeForPhone(wa);
}

async function loadWelcomeEmailTemplates() {
    if (!isSimpleCustomerCreate.value) return;

    try {
        const [templatesRes, assignmentRes] = await Promise.all([
            axios.get('/api/email-templates-for-sending'),
            axios.get('/api/template-assignments/customer_welcome/email'),
        ]);
        emailTemplates.value = templatesRes.data || [];
        const assignedTemplateId = assignmentRes.data?.template_id ? String(assignmentRes.data.template_id) : '';
        const genericTemplate = emailTemplates.value.find((template) => template.name === 'Welcome Template (Generic For All User)');
        welcomeEmailTemplateId.value = assignedTemplateId || (genericTemplate ? String(genericTemplate.id) : '');
    } catch (err) {
        emailTemplates.value = [];
        welcomeEmailTemplateId.value = '';
    }
}

function finishSaleCreditNavigate() {
    const dest = saleCreditPendingRoute.value;
    showSaleCreditModal.value = false;
    selectedSaleCreditUserId.value = '';
    saleCreditPendingRoute.value = null;
    saleCreditLeadId.value = null;
    if (dest) {
        router.push(dest);
    }
}

function finishSaleCreditSkip() {
    finishSaleCreditNavigate();
}

async function finishSaleCreditConfirm() {
    const selected = users.value.find((u) => String(u.id) === String(selectedSaleCreditUserId.value));
    if (!selected) return;
    if (saleCreditLeadId.value) {
        try {
            await axios.put(`/api/leads/${saleCreditLeadId.value}`, { assigned_to: selected.id });
            await axios.post(`/api/leads/${saleCreditLeadId.value}/activity`, {
                type: 'note',
                description: `Sale credited to ${selected.name} by ${auth.user?.name || 'Admin'}.`,
            });
        } catch (e) {
            toast.error(e?.response?.data?.message || 'Failed to save sale credit.');
            return;
        }
    }
    toast.success(`Sale will go on ${selected.name}.`);
    finishSaleCreditNavigate();
}

const loadCustomer = async () => {
    if (!route.params.id) return;
    loading.value = true;
    try {
        const { data } = await axios.get(`/api/customers/${route.params.id}`);
        const c = data.customer || data;
        form.type = c.type === 'customer' ? 'customer' : 'prospect';
        form.name = c.name || '';
        form.business_name = c.business_name || '';
        form.owner_name = c.owner_name || '';
        form.contact_person_2_name = c.contact_person_2_name || '';
        form.contact_person_2_phone = c.contact_person_2_phone || '';
        form.phone = c.phone || '';
        form.email = c.email || '';
        form.whatsapp_number = c.whatsapp_number || '';
        form.address = c.address || '';
        form.postcode = c.postcode || '';
        form.city = c.city || '';
        form.vat_number = c.vat_number || '';
        form.notes = c.notes || '';
        form.source = c.source || '';
        const rls = c.remote_licenses && c.remote_licenses.length
            ? c.remote_licenses.map(rl => ({
                anydesk_rustdesk: rl.anydesk_rustdesk || '',
                passwords: rl.passwords || '',
                epos_type: rl.epos_type || '',
                lic_days: rl.lic_days ?? null,
            }))
            : (c.anydesk_rustdesk || c.passwords || c.epos_type || c.lic_days != null)
                ? [{ anydesk_rustdesk: c.anydesk_rustdesk || '', passwords: c.passwords || '', epos_type: c.epos_type || '', lic_days: c.lic_days ?? null }]
                : [{ anydesk_rustdesk: '', passwords: '', epos_type: '', lic_days: null }];
        form.remote_licenses = rls;
    } catch (err) {
        toast.error('Failed to load customer');
        router.push({ path: '/customers', query: { type: form.type === 'customer' ? 'customer' : 'prospect' } });
    } finally {
        loading.value = false;
    }
};

const handleSubmit = async () => {
    error.value = null;
    clearValidation();
    if (!auth.initialized) {
        await auth.bootstrap();
    }
    if (isSimpleCustomerCreate.value || isEdit.value) {
        const failures = collectStepErrors(1);
        if (failures.length) {
            error.value = failures[0].message;
            toast.error(failures[0].message);
            await reportValidation(failures);
            return;
        }
    } else {
        const fail = validateAllStepsForCreate();
        if (fail) {
            error.value = fail.message;
            currentStep.value = fail.step;
            toast.error(fail.message);
            await reportValidation(fail.failures);
            return;
        }
    }

    loading.value = true;
    try {
        const payload = {
            ...form,
            type: form.type === 'customer' ? 'customer' : 'prospect',
            welcome_email_template_id: isSimpleCustomerCreate.value && form.email && welcomeEmailTemplateId.value
                ? Number(welcomeEmailTemplateId.value)
                : null,
            won_product_ids: isSimpleCustomerCreate.value ? wonProductIds.value : [],
            remote_licenses: form.remote_licenses.map(rl => ({
                anydesk_rustdesk: rl.anydesk_rustdesk || null,
                passwords: rl.passwords || null,
                epos_type: rl.epos_type || null,
                lic_days: rl.lic_days === '' || rl.lic_days === null ? null : rl.lic_days,
            })).filter(rl => rl.anydesk_rustdesk || rl.passwords || rl.epos_type || rl.lic_days !== null),
        };
        let customerId;
        let deferRedirectForSaleCredit = false;
        if (isEdit.value) {
            await axios.put(`/api/customers/${route.params.id}`, payload);
            toast.success('Customer updated successfully');
        } else {
            const { data } = await axios.post('/api/customers', payload);
            customerId = data.id;
            const wonLeadId = data.won_lead_id || null;
            toast.success('Customer created successfully');
            if (isSimpleCustomerCreate.value && isSaleCreditRole.value && customerId) {
                deferRedirectForSaleCredit = true;
                saleCreditPendingRoute.value = { path: '/customers', query: { type: 'customer' } };
                saleCreditLeadId.value = wonLeadId;
                saleCreditContextText.value = wonProductIds.value.length
                    ? 'This customer was saved with won products. Who should this sale go on?'
                    : 'Customer created. Who should get credit for this customer?';
                selectedSaleCreditUserId.value = '';
                showSaleCreditModal.value = true;
            }
            if (!isSimpleCustomerCreate.value && quickAddType.value && customerId) {
                const prodIds = quickAddProductIds.value.length ? quickAddProductIds.value : (products.value.length ? [products.value[0].id] : []);
                if (quickAddType.value === 'follow_up') {
                    await axios.post('/api/leads/followup-or-lead', {
                        customer_id: customerId,
                        type: 'follow_up',
                        comment: quickAddComment.value || 'Follow-up from customer creation',
                        product_ids: prodIds,
                        follow_up_at: quickAddFollowUpAt.value,
                    });
                } else if (quickAddType.value === 'lead') {
                    await axios.post('/api/leads/followup-or-lead', {
                        customer_id: customerId,
                        type: 'lead',
                        comment: quickAddComment.value || 'Lead from customer creation',
                        product_ids: prodIds,
                        stage: quickAddStage.value,
                        expected_closing_date: quickAddExpectedDate.value || null,
                        source: form.source || null,
                    });
                } else if (quickAddType.value === 'appointment') {
                    const leadRes = await axios.post('/api/leads', {
                        customer_id: customerId,
                        stage: 'follow_up',
                        source: 'appointment',
                        product_ids: prodIds,
                        comment: quickAddComment.value || `Appointment ${quickAddAppointmentDate.value} at ${quickAddAppointmentTime.value}`,
                    });
                    await axios.post(`/api/leads/${leadRes.data.id}/activity`, {
                        type: 'appointment',
                        description: quickAddComment.value || `Appointment scheduled for ${quickAddAppointmentDate.value} at ${quickAddAppointmentTime.value}`,
                        meta: { appointment_date: quickAddAppointmentDate.value, appointment_time: quickAddAppointmentTime.value },
                        assigned_user_id: quickAddAssignedUserId.value || null,
                    });
                }
            }
        }
        if (deferRedirectForSaleCredit) {
            // User confirms or skips in modal — see finishSaleCreditNavigate
        } else if (customerId && !isSimpleCustomerCreate.value && quickAddType.value) {
            router.push(`/customers/${customerId}`);
        } else {
            router.push({ path: '/customers', query: { type: payload.type || 'prospect' } });
        }
    } catch (err) {
        error.value = err.response?.data?.message || err.response?.data?.errors ? Object.values(err.response.data.errors || {}).flat().join(', ') : 'Failed to save';
        await focusErrorSummary();
    } finally {
        loading.value = false;
    }
};

onMounted(async () => {
    if (!auth.initialized) {
        await auth.bootstrap();
    }
    try {
        const [{ data: productsData }, { data: usersData }] = await Promise.all([
            axios.get('/api/products'),
            axios.get('/api/users'),
        ]);
        products.value = productsData || [];
        users.value = Array.isArray(usersData) ? usersData : (usersData?.data ?? []);
    } catch (_) {}
    if (isEdit.value) {
        loadCustomer();
    } else {
        const now = new Date();
        now.setHours(now.getHours() + 1);
        quickAddFollowUpAt.value = now.toISOString().slice(0, 16);
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        quickAddAppointmentDate.value = tomorrow.toISOString().slice(0, 10);
        await loadWelcomeEmailTemplates();
    }
});

watch(quickAddType, async (type) => {
    if (!type || isEdit.value || isSimpleCustomerCreate.value) return;
    if (currentStep.value !== 4) currentStep.value = 4;
    await nextTick();
    quickAddSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

// Same component instance when switching e.g. add prospect ↔ add customer — keep type in sync with URL
watch(
    () => (isEdit.value ? null : String(route.query.type || 'prospect')),
    (t) => {
        if (isEdit.value) return;
        form.type = t === 'customer' ? 'customer' : 'prospect';
        currentStep.value = 1;
        error.value = null;
        clearValidation();
        if (form.type === 'customer') {
            loadWelcomeEmailTemplates();
        } else {
            welcomeEmailTemplateId.value = '';
        }
    },
);
</script>
