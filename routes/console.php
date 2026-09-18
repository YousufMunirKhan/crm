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
    ->timezone(config('app.display_timezone'));

// Accounts-receivable ageing: nothing else ever set the "overdue" status, so
// it was a valid but unreachable state.
Schedule::command('invoices:mark-overdue')
    ->dailyAt('01:00')
    ->timezone(config('app.display_timezone'));

// SLA breaches: sla_due_at was computed and displayed but never read again,
// so a breach was only noticed if somebody happened to be looking.
Schedule::command('tickets:check-sla')
    ->hourly()
    ->timezone(config('app.display_timezone'));

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
    ->timezone(config('app.display_timezone'))
    ->withoutOverlapping();

Schedule::command('marketing:automations')
    ->dailyAt('07:00')
    ->timezone(config('app.display_timezone'));

// Location history past its retention period. Staff movement is the most
// sensitive thing here, so it expires on a schedule rather than on request.
Schedule::command('crm:prune-locations')
    ->dailyAt('02:30')
    ->timezone(config('app.display_timezone'));

// Shifts nobody clocked out of. A quarter of every attendance row was sitting
// open, read as "still on shift" by anything that asks who is working, and the
// count grew every day. Hourly so an abandoned shift stops counting within the
// hour rather than at the end of the day.
Schedule::command('crm:close-abandoned-shifts')
    ->hourly()
    ->timezone(config('attendance.timezone'));

// Start campaigns whose scheduled send time has arrived.
Schedule::command('campaigns:dispatch-due')
    ->everyFiveMinutes();

// Automated email. Each is spaced from the next: the host answers a burst with
// a 450 "too much mail", and these all land in the same few minutes otherwise.
//
// Every time named here is a time a person said out loud, so all of them hang
// off the display timezone. On config('app.timezone') - which is UTC, and stays
// UTC - "seven in the morning" is eight for the seven months of BST.

// Hourly, so a day's notice means a day's notice whatever time the appointment
// is at, rather than everything arriving in one morning batch.
Schedule::command('emails:appointment-reminders')
    ->hourly()
    ->timezone(config('app.display_timezone'));

Schedule::command('emails:rep-day')
    ->dailyAt('07:00')
    ->timezone(config('app.display_timezone'))
    ->withoutOverlapping();

Schedule::command('emails:follow-up-digest')
    ->dailyAt('07:10')
    ->timezone(config('app.display_timezone'))
    ->withoutOverlapping();

Schedule::command('emails:invoice-due')
    ->dailyAt('08:00')
    ->timezone(config('app.display_timezone'))
    ->withoutOverlapping();

// Ten in the morning, so a rep has had the first of the day to put something on
// the board before being told where they are. Not Sundays - the command checks,
// rather than the cron expression, so the rule is stated where the figures are.
Schedule::command('emails:daily-lead-summary')
    ->dailyAt('10:00')
    ->timezone(config('app.display_timezone'))
    ->withoutOverlapping();

// After the due-soon run, and after invoices:mark-overdue at 01:00 has moved
// yesterday's into the overdue status.
Schedule::command('emails:invoice-overdue')
    ->dailyAt('08:20')
    ->timezone(config('app.display_timezone'))
    ->withoutOverlapping();

// Did the scheduler run at all?
//
// Nothing here had ever run, and there was no way to see that: the only signal
// was a Log::info the production log level drops. This records the fact from
// inside the schedule, so it is written whichever way schedule:run was reached
// - the control panel's cron entry or the HTTP endpoint - rather than only by
// the one that happens to have a controller.
Schedule::call(function () {
    \App\Modules\Settings\Models\Setting::updateOrCreate(
        ['key' => 'scheduler_last_run_at'],
        ['value' => now()->toDateTimeString()],
    );
})->everyMinute()->name('scheduler-heartbeat');

// Drain the queue here, because there is nowhere else to drain it.
//
// This host has no cron and no way to keep a daemon alive, so `queue:work` as a
// long-running process is not an option. Everything queued - the template sync
// above, the chunks a campaign send is cut into - therefore sat in the jobs
// table with nothing to pick it up.
//
// --stop-when-empty makes the usual minute, where there is nothing waiting,
// cost almost nothing; --max-time keeps a busy one inside the pinger's request
// rather than running into the next minute's, which withoutOverlapping would
// then have to refuse.
if (config('queue.default') !== 'sync') {
    Schedule::command('queue:work --stop-when-empty --max-time=30 --tries=3')
        ->everyMinute()
        ->withoutOverlapping();
}
