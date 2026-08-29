<?php

namespace App\Modules\Marketing\Services;

use App\Models\ContactConsent;
use App\Models\SentCommunication;
use App\Modules\CRM\Models\Customer;
use App\Services\SuppressionService;
use Illuminate\Support\Carbon;

/**
 * The rules that decide whether a proposed message may actually be sent.
 *
 * These are deliberately here and not in the prompt. A model asked nicely to
 * respect consent will respect it almost always, and "almost always" across
 * five hundred contacts is a complaint to the ICO. The planner is free to
 * suggest anyone; nothing leaves without passing through this class.
 *
 * Blocked items are kept rather than dropped, so the review screen can say why
 * a list is shorter than expected instead of silently showing fewer rows.
 */
class MarketingGuardrails
{
    /** Nobody hears from us more often than this, on any channel. */
    public const MIN_DAYS_BETWEEN_MESSAGES = 30;

    /** Hard ceiling per plan. Insurance against a bug, not a target. */
    public const WEEKLY_RECIPIENT_CAP = 50;

    /**
     * Cheapest first. A model given a free choice will happily pick WhatsApp
     * for five hundred people and generate a bill.
     */
    public const CHANNEL_PREFERENCE = ['email', 'sms', 'whatsapp'];

    public function __construct(private SuppressionService $suppression) {}

    /**
     * @return array{allowed: bool, reason: ?string}
     */
    public function check(Customer $customer, string $channel): array
    {
        $identifier = $this->identifierFor($customer, $channel);

        if ($identifier === null) {
            return $this->deny('No '.$this->channelNoun($channel).' on file');
        }

        if ($this->suppression->isSuppressed($identifier, $channel)) {
            return $this->deny('Unsubscribed from '.$channel);
        }

        if (! $this->hasLawfulBasis($customer, $channel, $identifier)) {
            return $this->deny('No consent recorded for '.$channel);
        }

        $lastSent = $this->lastMarketingMessageAt($customer);

        if ($lastSent !== null && $lastSent->diffInDays(now()) < self::MIN_DAYS_BETWEEN_MESSAGES) {
            $days = self::MIN_DAYS_BETWEEN_MESSAGES - (int) $lastSent->diffInDays(now());

            return $this->deny("Contacted recently - eligible again in {$days} day(s)");
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Existing customers are covered by the soft opt-in for similar products,
     * so an explicit opt-in row is not required for them - but an explicit
     * opt-out always wins, and that is handled by the suppression check above.
     *
     * Prospects are a different matter: they need a positive record.
     */
    private function hasLawfulBasis(Customer $customer, string $channel, string $identifier): bool
    {
        $consent = ContactConsent::query()
            ->where('channel', $channel)
            ->where(function ($q) use ($identifier, $customer) {
                $q->where('identifier', $identifier)->orWhere('customer_id', $customer->id);
            })
            ->orderByDesc('id')
            ->first();

        if ($consent?->status === ContactConsent::STATUS_OPT_OUT) {
            return false;
        }

        if ($consent?->status === ContactConsent::STATUS_OPT_IN) {
            return true;
        }

        // No record either way: allowed for an existing customer under soft
        // opt-in, refused for a prospect.
        return $customer->type === Customer::TYPE_CUSTOMER;
    }

    /**
     * Last marketing message on any channel. Counting per channel would let a
     * contact get an email, a text and a WhatsApp in the same week and each
     * one would look compliant on its own.
     */
    private function lastMarketingMessageAt(Customer $customer): ?Carbon
    {
        $at = SentCommunication::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('campaign_id')
            ->max('sent_at');

        return $at ? Carbon::parse($at) : null;
    }

    /**
     * The best channel this customer can actually be reached on, cheapest
     * first, or null if none of them pass.
     *
     * @return array{channel: ?string, reasons: array<string, string>}
     */
    public function bestChannel(Customer $customer, ?string $preferred = null): array
    {
        $order = $preferred !== null
            ? array_merge([$preferred], array_diff(self::CHANNEL_PREFERENCE, [$preferred]))
            : self::CHANNEL_PREFERENCE;

        $reasons = [];

        foreach ($order as $channel) {
            $result = $this->check($customer, $channel);

            if ($result['allowed']) {
                return ['channel' => $channel, 'reasons' => $reasons];
            }

            $reasons[$channel] = $result['reason'];
        }

        return ['channel' => null, 'reasons' => $reasons];
    }

    private function identifierFor(Customer $customer, string $channel): ?string
    {
        $raw = match ($channel) {
            'email' => $customer->email,
            'sms' => $customer->sms_number ?: $customer->phone,
            'whatsapp' => $customer->whatsapp_number ?: $customer->phone,
            default => null,
        };

        $raw = trim((string) $raw);

        return $raw === '' ? null : $this->suppression->normalise($raw, $channel) ?? $raw;
    }

    private function channelNoun(string $channel): string
    {
        return match ($channel) {
            'email' => 'email address',
            'sms' => 'mobile number',
            'whatsapp' => 'WhatsApp number',
            default => $channel,
        };
    }

    /** @return array{allowed: bool, reason: string} */
    private function deny(string $reason): array
    {
        return ['allowed' => false, 'reason' => $reason];
    }
}
