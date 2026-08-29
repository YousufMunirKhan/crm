<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ColdCallingController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Modules\Commission\Http\Controllers\CommissionManagementController;
use App\Modules\Communication\Http\Controllers\CommunicationController;
use App\Modules\Communication\Http\Controllers\WebhookController;
use App\Modules\CRM\Http\Controllers\CustomerController;
use App\Modules\CRM\Http\Controllers\FollowUpController;
use App\Modules\CRM\Http\Controllers\LeadController;
use App\Modules\CRM\Http\Controllers\ProductController;
use App\Modules\HR\Http\Controllers\ExpenseController;
use App\Modules\HR\Http\Controllers\HrController;
use App\Modules\ImportExport\Http\Controllers\ImportExportController;
use App\Modules\Invoice\Http\Controllers\InvoiceController;
use App\Modules\POS\Http\Controllers\PosController;
use App\Modules\Reporting\Http\Controllers\ReportingController;
use App\Modules\Ticket\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/auth/forgot-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'forgotPassword'])->middleware('throttle:5,10');
Route::post('/auth/reset-password', [\App\Http\Controllers\Auth\PasswordResetController::class, 'resetPassword'])->middleware('throttle:5,10');

// Customer Portal (public)
Route::post('/portal/login', [\App\Http\Controllers\CustomerPortalController::class, 'login'])->middleware('throttle:5,1');

// Legacy WhatsApp webhook endpoint removed. Use /api/whatsapp/webhook only.
Route::middleware('webhook.key')->group(function () {
    // Bounces and complaints. Permanent failures auto-suppress.
    Route::post('/webhooks/delivery', [\App\Http\Controllers\DeliveryWebhookController::class, 'handle']);
    Route::post('/webhooks/email', [WebhookController::class, 'email']);
    Route::post('/webhooks/sms', [WebhookController::class, 'sms']);
});

// WhatsApp Cloud API Webhook (public - Meta calls this)
Route::get('/whatsapp/webhook', [\App\Modules\Communication\Http\Controllers\WhatsAppWebhookController::class, 'verify']);
Route::post('/whatsapp/webhook', [\App\Modules\Communication\Http\Controllers\WhatsAppWebhookController::class, 'handle']);

// Public Settings (logo, company name, PWA)
Route::get('/settings/pwa', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'pwa']);
Route::get('/settings/public', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'publicSettings']);

// Public lead capture for website forms and ad landing pages.
// Throttled; the controller also carries a honeypot field.
Route::post('/public/leads', [\App\Http\Controllers\PublicLeadCaptureController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('public.leads.store');

// Unsubscribe from marketing emails (public - no auth required)
Route::post('/unsubscribe', [\App\Http\Controllers\UnsubscribeController::class, 'store'])->middleware('throttle:20,1');
Route::get('/unsubscribe/check', [\App\Http\Controllers\UnsubscribeController::class, 'show'])->middleware('throttle:60,1');
// RFC 8058 one-click target referenced by the List-Unsubscribe-Post header.
Route::post('/unsubscribe/one-click/{channel}/{token}', [\App\Http\Controllers\UnsubscribeController::class, 'oneClick'])
    ->middleware('throttle:60,1')
    ->name('unsubscribe.one-click');

// Public Expense Template Download
Route::get('/hr/expenses/template/download', [\App\Modules\HR\Http\Controllers\ExpenseController::class, 'downloadTemplate']);

// POS Integration API - shared key in X-Api-Key (see config/pos.php).
// These endpoints create and update customers, tickets and invoices.
Route::prefix('pos')->middleware(['pos.key', 'throttle:120,1'])->group(function () {
    Route::post('/customer', [PosController::class, 'storeCustomer']);
    Route::post('/ticket', [PosController::class, 'storeTicket']);
    Route::post('/sale', [PosController::class, 'storeSale']);
});

// Desktop POS — technical support messages (X-Api-Key)
Route::middleware('pos.support.key')->group(function () {
    Route::post('/pos-support-messages', [\App\Http\Controllers\PosSupportMessageController::class, 'store']);
    Route::get('/pos-support-messages/status', [\App\Http\Controllers\PosSupportMessageController::class, 'status']);
});

// Protected routes
Route::middleware(['auth:sanctum', 'staff'])->group(function () {
    // Auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Dashboard
    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index']);
    Route::get('/dashboard/sales-agent', [\App\Http\Controllers\DashboardController::class, 'salesAgent']);

    // Templates for sending (any authenticated user - used on customer page)
    Route::get('/email-templates-for-sending', [\App\Http\Controllers\EmailTemplateController::class, 'listForSending']);
    Route::get('/message-templates-for-sending', [\App\Http\Controllers\MessageTemplateController::class, 'listForSending']);
    Route::post('/customers/{id}/send-template-email', [\App\Http\Controllers\EmailTemplateController::class, 'sendTemplateToCustomer']);

    // Daily Activities (Today's Activity for non-admin)
    Route::get('/daily-activities', [\App\Http\Controllers\DailyActivityController::class, 'index']);
    Route::post('/daily-activities', [\App\Http\Controllers\DailyActivityController::class, 'store']);
    Route::put('/daily-activities/{id}', [\App\Http\Controllers\DailyActivityController::class, 'update']);
    Route::delete('/daily-activities/{id}', [\App\Http\Controllers\DailyActivityController::class, 'destroy']);
    Route::get('/daily-activities/todays-report', [\App\Http\Controllers\DailyActivityController::class, 'todaysReport']);
    Route::get('/daily-activities/todays-report/export', [\App\Http\Controllers\DailyActivityController::class, 'todaysReportExport']);
    Route::post('/daily-activities/generate-report', [\App\Http\Controllers\DailyActivityController::class, 'generateReportWithGpt']);

    // Users
    Route::get('/users', [\App\Http\Controllers\UserController::class, 'index']);

    // Users Management (specific routes before {user} binding)
    Route::post('/users/reset-all-passwords', [UserController::class, 'resetAllPasswords']);
    Route::apiResource('users', UserController::class);
    Route::get('/roles', [RoleController::class, 'index']);
    Route::patch('/roles/{role}', [RoleController::class, 'update']);

    // CRM – static customer paths first so they are not matched as {customer} id
    Route::get('/customers/import-template', [CustomerController::class, 'importTemplate']);
    Route::post('/customers/import', [CustomerController::class, 'import']);
    Route::get('/customers/export', [CustomerController::class, 'export']);
    Route::get('/customers/assigned', [CustomerController::class, 'getAssignedCustomers']);
    Route::apiResource('customers', CustomerController::class);
    Route::put('/customers/{id}/contact-methods', [CustomerController::class, 'updateContactMethods']);
    Route::post('/customers/{id}/assign', [CustomerController::class, 'assign']);
    Route::delete('/customers/{id}/assign/{userId}', [CustomerController::class, 'unassign']);
    Route::get('/customers/{id}/communication-logs', [CustomerController::class, 'communicationLogs']);
    Route::get('/customers/{id}/unified-timeline', [CustomerController::class, 'unifiedTimeline']);
    Route::get('/leads/stats', [LeadController::class, 'stats']);
    Route::get('/leads/export', [LeadController::class, 'exportCsv']);
    Route::apiResource('leads', LeadController::class);
    Route::get('/products/categories', [ProductController::class, 'categories']);
    Route::post('/products/categories', [ProductController::class, 'storeCategory']);
    Route::apiResource('products', ProductController::class);
    Route::get('/products/{id}/suggested', [ProductController::class, 'getSuggestedProducts']);
    Route::get('/products/{id}/relationships', [ProductController::class, 'relationships']);
    Route::post('/products/{id}/relationships', [ProductController::class, 'storeRelationship'])
        ->middleware('role:Admin,Manager,System Admin');
    Route::delete('/products/{id}/relationships/{toProductId}', [ProductController::class, 'destroyRelationship'])
        ->middleware('role:Admin,Manager,System Admin');
    Route::get('/leads/pipeline/board', [LeadController::class, 'pipeline']);
    Route::get('/appointments/today-count', [\App\Modules\CRM\Http\Controllers\AppointmentController::class, 'todayCount']);
    Route::get('/appointments', [\App\Modules\CRM\Http\Controllers\AppointmentController::class, 'index']);
    Route::post('/appointments', [\App\Modules\CRM\Http\Controllers\AppointmentController::class, 'store']);
    Route::delete('/appointments/{id}', [\App\Modules\CRM\Http\Controllers\AppointmentController::class, 'destroy']);
    Route::get('/appointments/{id}', [\App\Modules\CRM\Http\Controllers\AppointmentController::class, 'show']);
    Route::put('/appointments/{id}', [\App\Modules\CRM\Http\Controllers\AppointmentController::class, 'update']);
    Route::post('/leads/{id}/activity', [LeadController::class, 'addActivity']);
    Route::post('/leads/{id}/followup', [LeadController::class, 'setFollowUp']);
    Route::post('/leads/{id}/complete-followup', [LeadController::class, 'completeFollowUp']);
    Route::post('/leads/{id}/items', [LeadController::class, 'addItems']);
    Route::post('/leads/{leadId}/items/{itemId}/close', [LeadController::class, 'closeItem']);
    Route::put('/leads/{leadId}/items/{itemId}', [LeadController::class, 'updateItem']);
    Route::post('/leads/followup-or-lead', [LeadController::class, 'createFollowUpOrLead']);
    Route::get('/customers/{customerId}/leads', [LeadController::class, 'getCustomerLeads']);
    Route::get('/customers/{customerId}/convertible-followups', [LeadController::class, 'getConvertibleFollowUps']);
    Route::get('/customers/{customerId}/next-products', [LeadController::class, 'getNextProductsToSell']);
    Route::get('/followups', [FollowUpController::class, 'index']);

    // Communications
    Route::apiResource('communications', CommunicationController::class)->only(['index', 'store', 'show']);

    // WhatsApp Cloud API (New Integration)
    Route::prefix('whatsapp')->group(function () {
        // Settings
        Route::get('/settings', [\App\Modules\Communication\Http\Controllers\WhatsAppSettingsController::class, 'index']);
        Route::post('/settings', [\App\Modules\Communication\Http\Controllers\WhatsAppSettingsController::class, 'store']);
        Route::post('/settings/test-connection', [\App\Modules\Communication\Http\Controllers\WhatsAppSettingsController::class, 'testConnection']);

        // Templates
        Route::get('/templates', [\App\Modules\Communication\Http\Controllers\WhatsAppTemplateController::class, 'index']);
        Route::post('/templates', [\App\Modules\Communication\Http\Controllers\WhatsAppTemplateController::class, 'store']);
        Route::post('/templates/preview', [\App\Modules\Communication\Http\Controllers\WhatsAppTemplateController::class, 'preview']);
        Route::get('/templates/{id}/parameter-hints', [\App\Modules\Communication\Http\Controllers\WhatsAppTemplateController::class, 'parameterHints']);
        Route::get('/templates/{id}', [\App\Modules\Communication\Http\Controllers\WhatsAppTemplateController::class, 'show']);
        Route::post('/templates/{id}/resubmit', [\App\Modules\Communication\Http\Controllers\WhatsAppTemplateController::class, 'resubmit']);
        Route::post('/templates/sync', [\App\Modules\Communication\Http\Controllers\WhatsAppTemplateController::class, 'sync']);

        // Messages
        Route::post('/messages/send-text', [\App\Modules\Communication\Http\Controllers\WhatsAppMessageController::class, 'sendText']);
        Route::post('/messages/send-template', [\App\Modules\Communication\Http\Controllers\WhatsAppMessageController::class, 'sendTemplate']);
        Route::get('/phone-info', [\App\Modules\Communication\Http\Controllers\WhatsAppMessageController::class, 'phoneNumberInfo']);
        Route::get('/customers/{customerId}/window-status', [\App\Modules\Communication\Http\Controllers\WhatsAppMessageController::class, 'windowStatus']);
        Route::get('/conversations', [\App\Modules\Communication\Http\Controllers\WhatsAppMessageController::class, 'conversations']);
        Route::get('/conversations/{id}/messages', [\App\Modules\Communication\Http\Controllers\WhatsAppMessageController::class, 'conversationMessages']);
    });

    // Tickets
    Route::apiResource('tickets', TicketController::class);
    Route::post('/tickets/{id}/message', [TicketController::class, 'addMessage']);
    Route::post('/tickets/{id}/messages', [TicketController::class, 'addMessage']);
    Route::post('/tickets/{id}/attachments', [TicketController::class, 'storeAttachments']);
    Route::delete('/tickets/{id}/attachments/{attachmentId}', [TicketController::class, 'destroyAttachment']);

    Route::get('/pos-support-tickets', [\App\Http\Controllers\PosSupportTicketAdminController::class, 'index']);
    Route::patch('/pos-support-tickets/{id}/status', [\App\Http\Controllers\PosSupportTicketAdminController::class, 'updateStatus']);

    // Invoices
    Route::get('/invoices/{id}/pdf', [InvoiceController::class, 'generatePDF'])->name('invoices.pdf');
    Route::post('/invoices/{id}/send-email', [InvoiceController::class, 'sendEmail'])->name('invoices.send-email')->middleware('role:Admin,Manager,System Admin');
    Route::post('/invoices/{id}/payments', [InvoiceController::class, 'storePayment'])->name('invoices.payments.store')->middleware('role:Admin,Manager,System Admin');
    Route::delete('/invoices/{id}/payments/{paymentId}', [InvoiceController::class, 'destroyPayment'])->name('invoices.payments.destroy')->middleware('role:Admin,Manager,System Admin');
    Route::post('/leads/{leadId}/invoice', [InvoiceController::class, 'storeFromLead'])
        ->middleware('role:Admin,Manager,System Admin')
        ->name('leads.invoice');
    Route::apiResource('invoices', InvoiceController::class);

    // HR
    Route::get('/hr/attendance/today', [HrController::class, 'todayStatus']);
    Route::post('/hr/attendance/check-in', [HrController::class, 'checkIn']);
    Route::post('/hr/attendance/check-out', [HrController::class, 'checkOut']);
    Route::get('/hr/attendance/chart-summary', [HrController::class, 'attendanceChartSummary']);
    Route::get('/hr/attendance/monthly-report', [HrController::class, 'attendanceMonthlyReport']);
    Route::get('/hr/attendance', [HrController::class, 'attendance']);
    Route::delete('/hr/attendance/{id}', [HrController::class, 'deleteAttendance']);
    Route::get('/hr/salaries', [HrController::class, 'salaries']);
    Route::get('/hr/salaries/report', [HrController::class, 'salaryReport']);
    Route::get('/hr/salaries/export', [HrController::class, 'exportSalaryReport']);
    Route::post('/hr/salaries', [HrController::class, 'createSalary']);
    Route::get('/hr/salaries/{id}', [HrController::class, 'showSalary']);
    Route::put('/hr/salaries/{id}', [HrController::class, 'updateSalary']);
    Route::delete('/hr/salaries/{id}', [HrController::class, 'deleteSalary']);
    Route::get('/hr/salaries/{id}/slip', [HrController::class, 'generateSalarySlip']);
    Route::post('/hr/salaries/{id}/send-email', [HrController::class, 'sendSalarySlipEmail']);
    Route::get('/hr/employees', [HrController::class, 'employees']);
    Route::get('/hr/employees/{id}/attendance-stats', [HrController::class, 'employeeAttendanceStats']);
    Route::get('/hr/employees/{id}/documents', [HrController::class, 'employeeDocuments']);
    Route::post('/hr/employees/{id}/documents', [HrController::class, 'storeEmployeeDocument']);
    Route::delete('/hr/employees/{id}/documents/{docId}', [HrController::class, 'destroyEmployeeDocument']);
    Route::get('/hr/employee-targets', [HrController::class, 'employeeTargets']);
    Route::put('/hr/employee-targets/{userId}', [HrController::class, 'upsertEmployeeTarget']);

    // Expenses (Admin only)
    Route::get('/hr/expenses', [ExpenseController::class, 'index']);
    Route::post('/hr/expenses', [ExpenseController::class, 'store']);
    Route::post('/hr/expenses/bulk-close', [ExpenseController::class, 'bulkClose']);
    Route::post('/hr/expenses/import', [ExpenseController::class, 'import']);
    Route::get('/hr/expenses/report/monthly', [ExpenseController::class, 'monthlyReport']);
    Route::get('/hr/expenses/{id}', [ExpenseController::class, 'show']);
    Route::put('/hr/expenses/{id}', [ExpenseController::class, 'update']);
    Route::post('/hr/expenses/{id}/attachments', [ExpenseController::class, 'storeAttachments']);
    Route::delete('/hr/expenses/{id}/attachments/{attachmentId}', [ExpenseController::class, 'destroyAttachment']);
    Route::delete('/hr/expenses/{id}', [ExpenseController::class, 'destroy']);

    // Reporting
    Route::get('/reporting/executive', [ReportingController::class, 'executive']);
    Route::get('/reporting/funnel', [ReportingController::class, 'funnel']);
    Route::get('/reporting/geo', [ReportingController::class, 'geo']);
    Route::get('/reporting/communications', [ReportingController::class, 'communications']);
    Route::get('/reporting/agents', [ReportingController::class, 'agents']);
    Route::get('/reporting/all-employees-pipeline', [ReportingController::class, 'allEmployeesPipeline']);
    Route::get('/reporting/todays-followups', [ReportingController::class, 'todaysFollowUps']);
    Route::get('/reporting/sales-performance', [ReportingController::class, 'salesPerformance']);
    Route::get('/reporting/revenue-by-employee', [ReportingController::class, 'revenueByEmployee']);
    Route::get('/reporting/team-location-status', [ReportingController::class, 'teamLocationStatus']);
    Route::get('/reporting/products-sold-by-employee', [ReportingController::class, 'productsSoldByEmployee']);
    Route::get('/reporting/product-performance', [ReportingController::class, 'productPerformance'])
        ->middleware('role:Admin,Manager,System Admin');
    Route::get('/reporting/employee-performance-overview', [ReportingController::class, 'employeePerformanceOverview']);
    Route::get('/reporting/target-vs-achievement', [ReportingController::class, 'targetVsAchievement']);
    Route::get('/reporting/employee-self-report', [ReportingController::class, 'employeeSelfReport']);

    // Import/Export - reads and writes the whole customer/lead database.
    Route::middleware('role:Admin,Manager,System Admin')->group(function () {
        Route::post('/import-export/preview', [ImportExportController::class, 'preview']);
        Route::post('/import-export/import', [ImportExportController::class, 'import']);
        Route::get('/import-export/export', [ImportExportController::class, 'export']);
        Route::get('/import-export/logs', [ImportExportController::class, 'importLogs']);
    });

    // Profile
    Route::get('/profile', [\App\Http\Controllers\ProfileController::class, 'show']);
    Route::put('/profile', [\App\Http\Controllers\ProfileController::class, 'update']);
    Route::post('/profile/password', [\App\Http\Controllers\ProfileController::class, 'updatePassword']);

    // Settings
    Route::get('/settings', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'index']);
    Route::put('/settings', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'update'])->middleware('role:Admin,System Admin');
    Route::get('/settings/{key}', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'get']);
    Route::put('/settings/pwa', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'updatePwa'])->middleware('role:Admin,System Admin');

    // Logo upload
    Route::post('/settings/logo', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'uploadLogo'])->middleware('role:Admin,System Admin');
    Route::delete('/settings/logo', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'deleteLogo'])->middleware('role:Admin,System Admin');
    Route::post('/settings/favicon', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'uploadFavicon'])->middleware('role:Admin,System Admin');
    Route::delete('/settings/favicon', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'deleteFavicon'])->middleware('role:Admin,System Admin');

    // Integration settings
    Route::put('/settings/smtp', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'updateSmtp'])->middleware('role:Admin,System Admin');
    Route::post('/settings/smtp/test', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'testSmtp'])->middleware('role:Admin,System Admin');
    Route::put('/settings/sms', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'updateSms'])->middleware('role:Admin,System Admin');
    Route::post('/settings/sms/test', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'testSms'])->middleware('role:Admin,System Admin');
    Route::put('/settings/facebook', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'updateFacebook'])->middleware('role:Admin,System Admin');
    Route::put('/settings/cold-calling', [\App\Modules\Settings\Http\Controllers\SettingsController::class, 'updateColdCalling'])->middleware('role:Admin,System Admin');

    // Saved audiences for campaign sends
    Route::apiResource('audience-segments', \App\Http\Controllers\AudienceSegmentController::class)
        ->middleware('role:Admin,Manager,System Admin,Marketing');

    // Email Management (filter, export, preview, send bulk, report)
    Route::prefix('email-management')->group(function () {
        Route::get('/smtp-status', [\App\Http\Controllers\EmailManagementController::class, 'smtpStatus']);
        Route::post('/filtered-contacts', [\App\Http\Controllers\EmailManagementController::class, 'getFilteredContacts']);
        Route::post('/export', [\App\Http\Controllers\EmailManagementController::class, 'exportFilteredContacts']);
        Route::get('/preview-template/{templateId}', [\App\Http\Controllers\EmailManagementController::class, 'previewTemplate']);
        Route::post('/send', [\App\Http\Controllers\EmailManagementController::class, 'sendBulk']);
        Route::get('/sent-report', [\App\Http\Controllers\EmailManagementController::class, 'getSentReport']);
        Route::get('/sent-report/export', [\App\Http\Controllers\EmailManagementController::class, 'exportSentReport']);
        Route::post('/sent/{id}/resend', [\App\Http\Controllers\EmailManagementController::class, 'resendSentEmail']);
        Route::post('/retry-queue', [\App\Http\Controllers\EmailManagementController::class, 'queueRetryAll']);
        Route::post('/lists/upload', [\App\Http\Controllers\EmailManagementController::class, 'uploadList']);
        Route::get('/lists', [\App\Http\Controllers\EmailManagementController::class, 'listLists']);
        Route::get('/lists/{id}', [\App\Http\Controllers\EmailManagementController::class, 'getList']);
        Route::get('/lists/{id}/recipients', [\App\Http\Controllers\EmailManagementController::class, 'getListRecipients']);
        Route::post('/send-to-list', [\App\Http\Controllers\EmailManagementController::class, 'sendToList']);
    });

    // WhatsApp Management (Meta template bulk sends — same audience/product filters as email)
    Route::prefix('whatsapp-management')->group(function () {
        Route::get('/whatsapp-status', [\App\Http\Controllers\WhatsAppManagementController::class, 'whatsappStatus']);
        Route::get('/approved-templates', [\App\Http\Controllers\WhatsAppManagementController::class, 'approvedTemplates']);
        Route::post('/filtered-contacts', [\App\Http\Controllers\WhatsAppManagementController::class, 'getFilteredContacts']);
        Route::post('/export', [\App\Http\Controllers\WhatsAppManagementController::class, 'exportFilteredContacts']);
        Route::get('/preview-template/{templateId}', [\App\Http\Controllers\WhatsAppManagementController::class, 'previewTemplate']);
        Route::post('/send', [\App\Http\Controllers\WhatsAppManagementController::class, 'sendBulk']);
        Route::get('/sent-report', [\App\Http\Controllers\WhatsAppManagementController::class, 'getSentReport']);
    });

    // SMS Management (same filters as email, settings from Settings → SMS)
    Route::prefix('sms-management')->group(function () {
        Route::get('/sms-status', [\App\Http\Controllers\SmsManagementController::class, 'smsStatus']);
        Route::post('/filtered-contacts', [\App\Http\Controllers\SmsManagementController::class, 'getFilteredContacts']);
        Route::get('/preview-template/{templateId}', [\App\Http\Controllers\SmsManagementController::class, 'previewTemplate']);
        Route::post('/send', [\App\Http\Controllers\SmsManagementController::class, 'sendBulk']);
        Route::get('/sent-report', [\App\Http\Controllers\SmsManagementController::class, 'getSentReport']);
    });

    // Cold calling (Google Places — API key in Settings → Cold calling)
    Route::prefix('cold-calling')->group(function () {
        Route::get('/settings-status', [ColdCallingController::class, 'settingsStatus']);
        Route::post('/runs', [ColdCallingController::class, 'startRun']);
        Route::get('/runs', [ColdCallingController::class, 'indexRuns']);
        Route::get('/runs/{id}', [ColdCallingController::class, 'showRun']);
        Route::get('/contacts/export', [ColdCallingController::class, 'export']);
        Route::post('/contacts/bulk-enrich-websites', [ColdCallingController::class, 'bulkEnrichFromWebsites']);
        Route::get('/contacts', [ColdCallingController::class, 'indexContacts']);
        Route::put('/contacts/{id}', [ColdCallingController::class, 'updateContact']);
        Route::post('/contacts/{id}/enrich-website', [ColdCallingController::class, 'enrichSingleContactWebsite']);
        Route::post('/contacts/{id}/mark-prospect', [ColdCallingController::class, 'markAsProspect']);
        Route::post('/contacts/{id}/import-to-crm', [ColdCallingController::class, 'importToCrmCustomer']);
        Route::get('/export-logs', [ColdCallingController::class, 'exportLogs']);
    });

    // Commission management (Admin/Manager/System Admin only)
    Route::prefix('commission-management')->group(function () {
        Route::get('/sales', [CommissionManagementController::class, 'sales']);
        Route::get('/report', [CommissionManagementController::class, 'report']);
        Route::get('/report/pdf/user', [CommissionManagementController::class, 'downloadUserCommissionPdf']);
        Route::get('/report/pdf/full', [CommissionManagementController::class, 'downloadFullCommissionPdf']);
        Route::post('/report/send-to-user', [CommissionManagementController::class, 'sendReportToUser']);
        Route::post('/report/send-internal', [CommissionManagementController::class, 'sendInternalReport']);
        Route::get('/users', [CommissionManagementController::class, 'users']);
        Route::patch('/users/{id}/eligibility', [CommissionManagementController::class, 'toggleEligibility']);
        Route::post('/allocations', [CommissionManagementController::class, 'allocate']);
        Route::patch('/allocations/{id}/reassign', [CommissionManagementController::class, 'reassign']);
        Route::get('/summary', [CommissionManagementController::class, 'summary']);
    });

    // Email Templates (Admin only - checked in controller)
    Route::prefix('email-templates')->group(function () {
        Route::get('/', [\App\Http\Controllers\EmailTemplateController::class, 'index']);
        Route::post('/test-send', [\App\Http\Controllers\EmailTemplateController::class, 'testSend']);
        Route::post('/preview-html', [\App\Http\Controllers\EmailTemplateController::class, 'previewHtml']);
        Route::get('/merge-tags', [\App\Http\Controllers\EmailTemplateController::class, 'mergeTagsReference']);
        Route::post('/import-html', [\App\Http\Controllers\EmailTemplateController::class, 'importFromHtml']);
        Route::post('/', [\App\Http\Controllers\EmailTemplateController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\EmailTemplateController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\EmailTemplateController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\EmailTemplateController::class, 'destroy']);
        Route::post('/{id}/duplicate', [\App\Http\Controllers\EmailTemplateController::class, 'duplicate']);
        Route::post('/upload-image', [\App\Http\Controllers\EmailTemplateController::class, 'uploadImage']);
        Route::post('/send', [\App\Http\Controllers\EmailTemplateController::class, 'sendEmail']);
    });

    // SMS/Message Templates (Admin only)
    Route::prefix('message-templates')->group(function () {
        Route::get('/', [\App\Http\Controllers\MessageTemplateController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\MessageTemplateController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\MessageTemplateController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\MessageTemplateController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\MessageTemplateController::class, 'destroy']);
        Route::post('/{id}/duplicate', [\App\Http\Controllers\MessageTemplateController::class, 'duplicate']);
    });

    // WhatsApp Templates (Admin only)
    Route::prefix('whatsapp-templates')->group(function () {
        Route::get('/', [\App\Http\Controllers\WhatsAppTemplateController::class, 'index']);
        Route::post('/', [\App\Http\Controllers\WhatsAppTemplateController::class, 'store']);
        Route::get('/{id}', [\App\Http\Controllers\WhatsAppTemplateController::class, 'show']);
        Route::put('/{id}', [\App\Http\Controllers\WhatsAppTemplateController::class, 'update']);
        Route::delete('/{id}', [\App\Http\Controllers\WhatsAppTemplateController::class, 'destroy']);
        Route::post('/{id}/duplicate', [\App\Http\Controllers\WhatsAppTemplateController::class, 'duplicate']);
        Route::post('/upload-media', [\App\Http\Controllers\WhatsAppTemplateController::class, 'uploadMedia']);
    });

    // Template Assignments (Admin only)
    Route::prefix('template-assignments')->group(function () {
        Route::get('/', [\App\Http\Controllers\TemplateAssignmentController::class, 'index']);
        Route::put('/', [\App\Http\Controllers\TemplateAssignmentController::class, 'update']);
        Route::get('/{functionType}/{templateType}', [\App\Http\Controllers\TemplateAssignmentController::class, 'getAssignment']);
    });

    // Notifications
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [\App\Http\Controllers\NotificationController::class, 'unreadCount']);
    Route::put('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead']);

});

// Customer Portal (customer tokens only - never staff tokens).
Route::prefix('portal')->middleware(['auth:sanctum', 'portal.customer'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\CustomerPortalController::class, 'dashboard']);
    Route::get('/tickets', [\App\Http\Controllers\CustomerPortalController::class, 'tickets']);
    Route::get('/invoices', [\App\Http\Controllers\CustomerPortalController::class, 'invoices']);
    Route::post('/tickets', [\App\Http\Controllers\CustomerPortalController::class, 'createTicket']);
});
