<?php

namespace App\Services;

use App\Modules\Settings\Models\Setting;

class CrmPublicUrl
{
    /**
     * Public origin where staff open the CRM in the browser (SPA).
     * Prefer Settings "crm_base_url"; otherwise Laravel APP_URL.
     */
    public static function base(): string
    {
        $fromSetting = Setting::where('key', 'crm_base_url')->value('value');
        if (is_string($fromSetting) && trim($fromSetting) !== '') {
            return rtrim(trim($fromSetting), '/');
        }

        return rtrim((string) config('app.url', ''), '/');
    }

    public static function ticket(int $ticketId): string
    {
        return self::base() . '/tickets/' . $ticketId;
    }
}
