<?php

namespace App\Services;

use App\Models\ContactConsent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Single gate every bulk send must pass through.
 *
 * Before this existed, only email had a suppression list, and it was keyed on
 * an email address - so SMS and WhatsApp had no opt-out mechanism at all and a
 * recipient replying STOP kept receiving messages indefinitely.
 */
class SuppressionService
{
    /**
     * Keywords that opt a recipient out when they reply.
     *
     * @var list<string>
     */
    public const OPT_OUT_KEYWORDS = ['stop', 'unsubscribe', 'optout', 'opt-out', 'opt out', 'cancel', 'end', 'quit'];

    /**
     * Normalises an identifier so lookups match regardless of formatting.
     * Emails lowercase; phone numbers keep digits and a leading +.
     */
    public function normalise(?string $identifier, string $channel): ?string
    {
        $identifier = trim((string) $identifier);
        if ($identifier === '') {
            return null;
        }

        if ($channel === ContactConsent::CHANNEL_EMAIL) {
            return Str::lower($identifier);
        }

        $digits = preg_replace('/[^0-9+]/', '', $identifier) ?? '';
        $digits = ltrim($digits, '+');

        return $digits === '' ? null : '+'.$digits;
    }

    /**
     * True when this recipient must not receive marketing on this channel.
     */
    public function isSuppressed(?string $identifier, string $channel): bool
    {
        $key = $this->normalise($identifier, $channel);
        if ($key === null) {
            // Nothing to send to; treat as suppressed so callers skip it.
            return true;
        }

        return ContactConsent::query()
            ->where('identifier', $key)
            ->where('channel', $channel)
            ->where('status', ContactConsent::STATUS_OPT_OUT)
            ->exists();
    }

    /**
     * Filters a list of identifiers down to those still contactable.
     *
     * One query rather than one per recipient, so a bulk send does not issue
     * thousands of extra lookups.
     *
     * @param  iterable<string|null>  $identifiers
     * @return array<string, true> normalised identifiers that are suppressed
     */
    public function suppressedSet(iterable $identifiers, string $channel): array
    {
        $keys = [];
        foreach ($identifiers as $identifier) {
            $key = $this->normalise($identifier, $channel);
            if ($key !== null) {
                $keys[$key] = true;
            }
        }

        if ($keys === []) {
            return [];
        }

        return ContactConsent::query()
            ->where('channel', $channel)
            ->where('status', ContactConsent::STATUS_OPT_OUT)
            ->whereIn('identifier', array_keys($keys))
            ->pluck('identifier')
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    /**
     * Records an opt-out. Idempotent.
     */
    public function optOut(?string $identifier, string $channel, string $source, ?string $evidence = null, ?int $customerId = null): bool
    {
        $key = $this->normalise($identifier, $channel);
        if ($key === null) {
            return false;
        }

        ContactConsent::updateOrCreate(
            ['identifier' => $key, 'channel' => $channel],
            [
                'status' => ContactConsent::STATUS_OPT_OUT,
                'opt_out_at' => now(),
                'source' => $source,
                'evidence' => $evidence,
                'customer_id' => $customerId,
            ]
        );

        Log::info('Marketing opt-out recorded', ['channel' => $channel, 'source' => $source]);

        return true;
    }

    /**
     * Records an opt-in with the lawful basis being relied on.
     */
    public function optIn(
        ?string $identifier,
        string $channel,
        string $source,
        string $lawfulBasis = 'consent',
        ?string $evidence = null,
        ?int $customerId = null
    ): bool {
        $key = $this->normalise($identifier, $channel);
        if ($key === null) {
            return false;
        }

        ContactConsent::updateOrCreate(
            ['identifier' => $key, 'channel' => $channel],
            [
                'status' => ContactConsent::STATUS_OPT_IN,
                'opt_in_at' => now(),
                'opt_out_at' => null,
                'source' => $source,
                'lawful_basis' => $lawfulBasis,
                'evidence' => $evidence,
                'customer_id' => $customerId,
            ]
        );

        return true;
    }

    /**
     * Opts out across every channel - used for a subject's erasure request.
     */
    public function optOutAllChannels(array $identifiers, string $source, ?int $customerId = null): int
    {
        $count = 0;
        foreach (ContactConsent::channels() as $channel) {
            foreach ($identifiers as $identifier) {
                if ($this->optOut($identifier, $channel, $source, null, $customerId)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * True when an inbound message body is an opt-out request.
     */
    public function isOptOutKeyword(?string $body): bool
    {
        $normalised = Str::lower(trim((string) $body));
        if ($normalised === '') {
            return false;
        }

        // Strip punctuation so "STOP." and "stop!" still match.
        $normalised = trim(preg_replace('/[^a-z\s-]/', '', $normalised) ?? '');

        return in_array($normalised, self::OPT_OUT_KEYWORDS, true);
    }

    /**
     * Signed token so an unsubscribe link identifies its recipient and cannot
     * be used to unsubscribe somebody else.
     */
    public function token(string $identifier, string $channel): string
    {
        $key = $this->normalise($identifier, $channel) ?? '';

        return hash_hmac('sha256', $channel.'|'.$key, (string) config('app.key'));
    }

    public function tokenMatches(string $token, string $identifier, string $channel): bool
    {
        return hash_equals($this->token($identifier, $channel), $token);
    }
}
