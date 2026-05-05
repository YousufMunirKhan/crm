<?php

namespace App\Support;

use App\Modules\Settings\Models\Setting;

/**
 * Logo + company footer fields shared by invoice and commission PDFs.
 */
class PdfDocumentBranding
{
    /** @var list<string> */
    public const FOOTER_SETTING_KEYS = [
        'company_name',
        'company_address',
        'company_phone',
        'company_email',
        'company_website',
        'company_registration_no',
        'company_vat',
        'payment_account_name',
        'payment_sort_code',
        'payment_account_number',
        'payment_terms_note',
    ];

    public static function logoUrl(): ?string
    {
        $v = Setting::query()->where('key', 'logo_url')->value('value');

        return ($v !== null && trim((string) $v) !== '') ? trim((string) $v) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function footerSettings(): array
    {
        return Setting::query()
            ->whereIn('key', self::FOOTER_SETTING_KEYS)
            ->pluck('value', 'key')
            ->all();
    }

    /**
     * @return array{logoUrl: ?string, settings: array<string, mixed>}
     */
    public static function package(): array
    {
        return [
            'logoUrl' => self::logoUrl(),
            'settings' => self::footerSettings(),
        ];
    }
}
