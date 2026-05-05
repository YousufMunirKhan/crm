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
