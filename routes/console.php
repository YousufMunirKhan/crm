<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule WhatsApp template sync every 15 minutes
Schedule::job(new \App\Jobs\SyncWhatsAppTemplatesJob)->everyFifteenMinutes();

// Monthly commission PDF + emails (1st of month by default; config/commission.php + .env)
Schedule::command('commission:send-monthly-reports')
    ->monthlyOn(
        (int) config('commission.monthly_report_day', 1),
        (string) config('commission.monthly_report_time', '08:00'),
    )
    ->timezone(config('app.timezone'));

// Accounts-receivable ageing: nothing else ever set the "overdue" status, so
// it was a valid but unreachable state.
Schedule::command('invoices:mark-overdue')
    ->dailyAt('01:00')
    ->timezone(config('app.timezone'));

// SLA breaches: sla_due_at was computed and displayed but never read again,
// so a breach was only noticed if somebody happened to be looking.
Schedule::command('tickets:check-sla')
    ->hourly()
    ->timezone(config('app.timezone'));

// Lifecycle automations. Raises internal tasks only - outbound sending still
// goes through the campaign console where consent is enforced.
// Monday morning, so the week's plan is waiting when someone starts. It only
// ever creates a draft - nothing is sent without a person approving it.
Schedule::command('marketing:plan')
    ->weeklyOn(1, '07:30')
    ->withoutOverlapping();

// The worklist nobody was being given. Runs before the marketing automations
// so the first thing waiting is a person's own overdue work, not a campaign.
Schedule::command('crm:daily-worklist')
    ->dailyAt('06:45')
    ->weekdays()
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

Schedule::command('marketing:automations')
    ->dailyAt('07:00')
    ->timezone(config('app.timezone'));

// Start campaigns whose scheduled send time has arrived.
Schedule::command('campaigns:dispatch-due')
    ->everyFiveMinutes();
