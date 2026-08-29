<?php

namespace App\Support;

/**
 * Canonical lead sources.
 *
 * The list previously existed only inside three duplicated Vue dropdowns with
 * no server-side validation, so values drifted and could not be trusted for
 * reporting.
 */
final class LeadSources
{
    /** @return array<string, string> value => label */
    public static function labels(): array
    {
        return [
            'call_center' => 'Call centre',
            'ground_field' => 'Ground / field',
            'website' => 'Website',
            'meta' => 'Meta (Facebook / Instagram)',
            'tiktok' => 'TikTok',
            'google_ads' => 'Google Ads',
            'organic_lead' => 'Organic',
            'referral' => 'Referral',
            'cold_calling' => 'Cold calling',
            'other' => 'Other',
        ];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::labels());
    }

    public static function isValid(?string $source): bool
    {
        return $source !== null && in_array($source, self::values(), true);
    }

    /**
     * Best-effort mapping of a UTM source onto a canonical lead source.
     */
    public static function fromUtm(?string $utmSource, ?string $utmMedium = null): string
    {
        $source = strtolower(trim((string) $utmSource));
        $medium = strtolower(trim((string) $utmMedium));

        return match (true) {
            str_contains($source, 'google') && str_contains($medium, 'cpc') => 'google_ads',
            str_contains($source, 'google') => 'google_ads',
            str_contains($source, 'facebook'), str_contains($source, 'instagram'), str_contains($source, 'meta') => 'meta',
            str_contains($source, 'tiktok') => 'tiktok',
            $medium === 'referral' => 'referral',
            $source === '' => 'website',
            default => 'organic_lead',
        };
    }
}
