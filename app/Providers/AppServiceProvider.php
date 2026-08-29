<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Model => policy.
     *
     * Registered explicitly because Laravel's convention-based discovery only
     * finds App\Models\X => App\Policies\XPolicy, and most models here live
     * under App\Modules\*\Models. Without this the policies exist but are
     * never consulted.
     *
     * @var array<class-string, class-string>
     */
    private const POLICIES = [
        \App\Modules\CRM\Models\Product::class => \App\Policies\ProductPolicy::class,
        \App\Modules\CRM\Models\ProductCategory::class => \App\Policies\ProductCategoryPolicy::class,
        \App\Modules\CRM\Models\Customer::class => \App\Policies\CustomerPolicy::class,
        \App\Modules\CRM\Models\Lead::class => \App\Policies\LeadPolicy::class,
        \App\Modules\Invoice\Models\Invoice::class => \App\Policies\InvoicePolicy::class,
        \App\Modules\Invoice\Models\InvoicePayment::class => \App\Policies\InvoicePaymentPolicy::class,
        \App\Modules\Ticket\Models\Ticket::class => \App\Policies\TicketPolicy::class,
        \App\Modules\HR\Models\Expense::class => \App\Policies\ExpensePolicy::class,
        \App\Modules\HR\Models\Salary::class => \App\Policies\SalaryPolicy::class,
        \App\Modules\HR\Models\Attendance::class => \App\Policies\AttendancePolicy::class,
        \App\Modules\Settings\Models\Setting::class => \App\Policies\SettingPolicy::class,
        \App\Models\User::class => \App\Policies\UserPolicy::class,
        \App\Models\Role::class => \App\Policies\RolePolicy::class,
        \App\Models\CommissionSale::class => \App\Policies\CommissionSalePolicy::class,
        \App\Models\EmailTemplate::class => \App\Policies\EmailTemplatePolicy::class,
        \App\Models\ContactConsent::class => \App\Policies\ContactConsentPolicy::class,
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        foreach (self::POLICIES as $model => $policy) {
            if (class_exists($model) && class_exists($policy)) {
                Gate::policy($model, $policy);
            }
        }

        /*
         * Point password-reset emails at the SPA route rather than the Laravel
         * default. Without this, the notification calls route('password.reset')
         * - which was never defined - and "Send reset link" threw a
         * RouteNotFoundException instead of sending anything.
         */
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return url('/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset()));
        });
    }
}
