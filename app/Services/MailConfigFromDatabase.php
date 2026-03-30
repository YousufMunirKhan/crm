<?php

namespace App\Services;

use App\Modules\Settings\Models\Setting;

class MailConfigFromDatabase
{
    /**
     * Apply email/SMTP settings from the database to Laravel's mail config.
     * Call this before sending any email so that Mail::send() uses DB settings.
     * If no SMTP settings are stored in DB, config is left unchanged (uses .env/default).
     */
    public static function apply(): void
    {
        $keys = [
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
            'smtp_from_email',
            'smtp_from_name',
        ];

        $settings = Setting::whereIn('key', $keys)->pluck('value', 'key')->all();

        // If host is set in DB, use DB for mail config
        $host = $settings['smtp_host'] ?? null;
        if ($host !== null && $host !== '') {
            $encryption = $settings['smtp_encryption'] ?? null;
            if ($encryption === 'none') {
                $encryption = null;
            }

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) ($settings['smtp_port'] ?? config('mail.mailers.smtp.port')),
                'mail.mailers.smtp.username' => $settings['smtp_username'] ?? config('mail.mailers.smtp.username'),
                'mail.mailers.smtp.password' => $settings['smtp_password'] ?? config('mail.mailers.smtp.password'),
                'mail.mailers.smtp.encryption' => $encryption ?? config('mail.mailers.smtp.encryption'),
                'mail.from.address' => $settings['smtp_from_email'] ?? config('mail.from.address'),
                'mail.from.name' => $settings['smtp_from_name'] ?? config('mail.from.name'),
            ]);

            // Laravel caches MailManager; without this, Mail::send may still use pre-DB config (.env).
            if (app()->bound('mail.manager')) {
                app()->forgetInstance('mail.manager');
            }
        }
    }
}
