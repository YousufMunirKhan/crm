<?php

namespace App\Services;

/**
 * Classifies SMTP / transport errors so the UI can block resend on bounces,
 * show bounces separately, and treat rate limits as retryable (not bounces).
 */
class EmailFailureClassifier
{
    /**
     * @return 'validation'|'rate_limit'|'bounce'|'smtp'|'other'
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

        $rateLimitHints = [
            '450 ', '451 ', '452 ',
            'too much mail', 'too many messages', 'too many mail',
            'message rate', 'sending rate', 'rate exceeded',
            'try again later', 'greylist', 'greylisted',
            '4.3.2', '4.7.1', '4.7.0', '4.7.2',
            'throttl', 'quota exceeded', 'limit exceeded',
            'temporarily deferred', 'deferral',
        ];
        foreach ($rateLimitHints as $hint) {
            if (str_contains($m, $hint)) {
                return 'rate_limit';
            }
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

    /**
     * Short text for CSV / API failed_list rows (avoid huge Swift traces).
     */
    public static function failedListSummary(?string $message): string
    {
        $friendly = self::friendlyMessage($message);
        if (strlen($friendly) > 200) {
            return substr($friendly, 0, 197).'...';
        }

        return $friendly;
    }

    /**
     * Human-readable explanation for stored error_message / UI.
     */
    public static function friendlyMessage(?string $message): string
    {
        if ($message === null || $message === '') {
            return 'Unknown error.';
        }

        $category = self::classify($message);

        return match ($category) {
            'rate_limit' => 'The mail server is limiting how fast email can be sent from this server’s address (SMTP 450/451: “too much mail” or similar). Wait several minutes, send in smaller batches, increase the delay between messages, or ask your email host to raise sending limits for this IP.',
            'bounce' => 'The recipient’s mail server rejected delivery to this address (bounce). Do not keep resending until the mailbox issue is fixed.',
            'validation' => $message,
            default => $message,
        };
    }

    /**
     * Full technical line appended under friendly text when we shorten for display.
     */
    public static function friendlyWithTechnical(?string $message, int $technicalMax = 400): string
    {
        $raw = (string) $message;
        $friendly = self::friendlyMessage($raw);
        if (in_array(self::classify($raw), ['rate_limit', 'bounce'], true) && $raw !== '' && $friendly !== $raw) {
            $tail = strlen($raw) > $technicalMax ? substr($raw, 0, $technicalMax).'…' : $raw;

            return $friendly."\n\nTechnical: ".$tail;
        }

        return $friendly;
    }
}
