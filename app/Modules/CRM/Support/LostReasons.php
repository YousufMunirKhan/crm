<?php

namespace App\Modules\CRM\Support;

/**
 * The fixed set of answers to "why did we lose it".
 *
 * Free text was the previous answer and it produced one filled-in row out of
 * 571 leads. Nobody types a sentence fifty times a week, so instead they mark
 * the lead won and it disappears - which is how a reseller ends up with a 99%
 * win rate on paper. A tap costs nothing, so a tap is what this asks for.
 *
 * The list is deliberately short and deliberately about the buyer, not the
 * seller: every option is something a rep can say without it reading as an
 * admission. A list that feels like a confession gets avoided the same way the
 * textarea did.
 */
final class LostReasons
{
    /**
     * Ordered roughly by how often an ePOS and merchant-services reseller
     * actually hits them, because the first two tiles are the ones that get
     * pressed.
     */
    public const REASONS = [
        'price' => [
            'label' => 'Price',
            'hint' => 'Cheaper quote elsewhere, or over their budget.',
        ],
        'competitor' => [
            'label' => 'Went with a competitor',
            'hint' => 'Chose another provider. Say who, if you know.',
        ],
        'in_contract' => [
            'label' => 'Tied into a contract',
            'hint' => 'Locked in with their current provider for now.',
        ],
        'no_response' => [
            'label' => 'Could not reach them',
            'hint' => 'Chased and never got a reply.',
        ],
        'not_interested' => [
            'label' => 'Not interested',
            'hint' => 'Told us no. No further detail needed.',
        ],
        'not_suitable' => [
            'label' => 'Not the right fit',
            'hint' => 'We could not do what they needed.',
        ],
        'timing' => [
            'label' => 'Wrong time',
            'hint' => 'Interested, but not now. Worth a callback later.',
        ],
        'closed_down' => [
            'label' => 'Business closed or sold',
            'hint' => 'There is nobody left to sell to.',
        ],
        'duplicate' => [
            'label' => 'Duplicate or bad data',
            'hint' => 'Never a real opportunity.',
        ],
        'other' => [
            'label' => 'Something else',
            'hint' => 'Please say what happened.',
        ],
    ];

    /**
     * The two that mean "come back to this" rather than "this is over". Kept
     * here so a follow-up list can find them without hard-coding strings.
     */
    public const REVISITABLE = ['timing', 'in_contract'];

    /**
     * Detail is only worth insisting on where the label alone tells you
     * nothing you can act on.
     */
    public const DETAIL_REQUIRED = ['other', 'competitor'];

    public static function codes(): array
    {
        return array_keys(self::REASONS);
    }

    public static function isValid(?string $code): bool
    {
        return $code !== null && array_key_exists($code, self::REASONS);
    }

    public static function label(?string $code): ?string
    {
        return self::REASONS[$code]['label'] ?? null;
    }

    /**
     * What goes in `lost_reason` when the picker is used: the label, plus the
     * detail if any was typed. Keeps the existing free-text column readable to
     * everything that already displays it, while `lost_reason_code` carries
     * the part that can be counted.
     */
    public static function compose(?string $code, ?string $detail): string
    {
        $label = self::label($code) ?? 'Not recorded';
        $detail = trim((string) $detail);

        return $detail === '' ? $label : $label.' - '.$detail;
    }

    /** Shape the frontend picker renders from, so the list lives in one place. */
    public static function forPicker(): array
    {
        return array_map(
            fn (string $code) => [
                'code' => $code,
                'label' => self::REASONS[$code]['label'],
                'hint' => self::REASONS[$code]['hint'],
                'detail_required' => in_array($code, self::DETAIL_REQUIRED, true),
            ],
            self::codes()
        );
    }
}
