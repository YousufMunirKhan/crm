<?php

namespace App\Services;

/**
 * Classifies SMTP / transport errors so the UI can block resend on bounces
 * and show bounces in a separate report section.
 */
class EmailFailureClassifier
{
    /**
     * @return 'validation'|'bounce'|'smtp'|'other'
     */
    public static function classify(?string $message): string
    {
        if ($message === null || $message === '') {
            return 'other';
        }

        $m = strtolower($message);
        if (str_starts_with($m, 'skipped:')) {
            return 'validation';
        }

        $bounceHints = [
            '554 ', '550 ', '553 ', '552 ',
            '5.1.1', '5.1.2', '5.1.0', '5.4.4', '5.4.1', '5.2.1', '5.2.2',
            'user unknown', 'unknown user', 'recipient unknown',
            'mailbox unavailable', 'no mailbox',
            'invalid recipient', 'address rejected',
            'undeliverable', 'permanent failure',
            'mailbox not found', 'no such user',
            'recipient address rejected',
            'does not exist', 'not a valid', 'invalid address',
            'recipient rejected', 'rejected by', 'recipient not found',
        ];

        foreach ($bounceHints as $hint) {
            if (str_contains($m, $hint)) {
                return 'bounce';
            }
        }

        if (str_contains($m, 'bounce') || str_contains($m, 'bounced')) {
            return 'bounce';
        }

        if (str_contains($m, 'smtp') || str_contains($m, 'swift_')) {
            return 'smtp';
        }

        return 'other';
    }
}
