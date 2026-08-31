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
    /**
     * Applied already in this process. The settings do not change mid-request,
     * and re-purging the mail manager per recipient would slow a bulk send for
     * no benefit.
     */
    private static bool $applied = false;

    /** Call after saving settings, and in tests. */
    public static function reset(): void
    {
        self::$applied = false;
    }

    public static function apply(bool $force = false): void
    {
        if (self::$applied && ! $force) {
            return;
        }

        self::$applied = true;

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

        // If host is set in DB, use DB for mail config (trim so saved whitespace does not disable SMTP)
        $host = isset($settings['smtp_host']) ? trim((string) $settings['smtp_host']) : '';
        if ($host !== '') {
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

            // Laravel caches the mail manager / resolved SMTP transport; forget so the next send uses DB config.
            if (app()->bound('mail.manager')) {
                $manager = app('mail.manager');
                if (method_exists($manager, 'purge')) {
                    $manager->purge();
                }
                app()->forgetInstance('mail.manager');
            }
        }
    }
}
