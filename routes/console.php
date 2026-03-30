<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule WhatsApp template sync every 15 minutes
Schedule::job(new \App\Jobs\SyncWhatsAppTemplatesJob)->everyFifteenMinutes();
