<?php

namespace App\Support;

use App\Modules\Settings\Models\Setting;

/**
 * The "is this a small independent, or a chain / mall / supermarket" rules
 * from Settings → Cold calling.
 *
 * Kept in one place because listings now arrive two ways - Google Places and
 * the Maps scraper - and a blocklist that only one of them honoured would let
 * Tesco back in through the other door.
 */
final class ColdCallingIngestFilters
{
    private const DEFAULT_EXCLUDED_TYPES = [
        'department_store',
        'shopping_mall',
        'supermarket',
        'hypermarket',
        'discount_supermarket',
        'discount_store',
        'warehouse_store',
        'wholesaler',
    ];

    /**
     * @return array{skip_reviews_over: int, exclude_names_csv: string, exclude_types: list<string>}
     */
    public static function fromSettings(): array
    {
        $skipReviews = (int) (Setting::where('key', 'cold_calling_skip_if_reviews_over')->value('value') ?: 0);
        $skipReviews = max(0, min(500_000, $skipReviews));

        $excludeNames = trim((string) (Setting::where('key', 'cold_calling_discovery_exclude_names')->value('value') ?: ''));

        $rawTypes = trim((string) (Setting::where('key', 'cold_calling_discovery_exclude_types')->value('value') ?: ''));
        if ($rawTypes === '' || strcasecmp($rawTypes, 'default') === 0) {
            $excludeTypes = self::DEFAULT_EXCLUDED_TYPES;
        } elseif (strcasecmp($rawTypes, 'none') === 0 || $rawTypes === '-') {
            $excludeTypes = [];
        } else {
            $excludeTypes = array_values(array_unique(array_filter(array_map(
                static fn (string $t) => self::normalizeType($t),
                explode(',', $rawTypes)
            ))));
        }

        return [
            'skip_reviews_over' => $skipReviews,
            'exclude_names_csv' => $excludeNames,
            'exclude_types' => $excludeTypes,
        ];
    }

    /**
     * Why a new listing should not be saved, or null when it should.
     *
     * @param  array<string, mixed>  $attrs
     * @param  array{skip_reviews_over: int, exclude_names_csv: string, exclude_types: list<string>}  $f
     */
    public static function skipReason(array $attrs, array $f): ?string
    {
        $maxR = (int) ($f['skip_reviews_over'] ?? 0);
        if ($maxR > 0) {
            $rc = $attrs['user_rating_count'] ?? null;
            if ($rc !== null && (int) $rc > $maxR) {
                return 'skipped_high_review_count';
            }
        }

        $name = strtolower((string) ($attrs['name'] ?? ''));
        $csv = (string) ($f['exclude_names_csv'] ?? '');
        if ($name !== '' && $csv !== '') {
            foreach (array_filter(array_map('trim', explode(',', $csv))) as $term) {
                if ($term === '' || strlen($term) > 80) {
                    continue;
                }
                if (str_contains($name, strtolower($term))) {
                    return 'skipped_excluded_name';
                }
            }
        }

        $types = $attrs['types'] ?? [];
        $excludeTypes = $f['exclude_types'] ?? [];
        if (is_array($types) && $excludeTypes !== []) {
            $tNormal = array_map(static fn ($t) => self::normalizeType((string) $t), $types);
            foreach ($excludeTypes as $ex) {
                $ex = self::normalizeType((string) $ex);
                if ($ex !== '' && in_array($ex, $tNormal, true)) {
                    return 'skipped_excluded_place_type';
                }
            }
        }

        return null;
    }

    /**
     * Places says "shopping_mall" and the Maps listing says "Shopping mall";
     * they are the same type.
     */
    private static function normalizeType(string $type): string
    {
        return str_replace([' ', '-'], '_', strtolower(trim($type)));
    }
}
