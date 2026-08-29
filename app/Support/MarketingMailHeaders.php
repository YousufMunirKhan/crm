<?php

namespace App\Support;

use App\Models\ContactConsent;
use App\Services\SuppressionService;
use Illuminate\Mail\Message;

/**
 * Adds the List-Unsubscribe headers required of bulk senders.
 *
 * Gmail and Yahoo require a one-click unsubscribe (RFC 8058) on marketing
 * mail, and PECR expects a simple means of refusal in every message. Without
 * these headers the only opt-out was a link buried in the footer.
 */
final class MarketingMailHeaders
{
    public static function apply(Message $message, ?string $recipientEmail): void
    {
        $recipientEmail = trim((string) $recipientEmail);
        if ($recipientEmail === '') {
            return;
        }

        /** @var SuppressionService $suppression */
        $suppression = app(SuppressionService::class);
        $token = $suppression->token($recipientEmail, ContactConsent::CHANNEL_EMAIL);

        $oneClick = url('/api/unsubscribe/one-click/email/'.$token).'?c='.rawurlencode($recipientEmail);

        $targets = ['<'.$oneClick.'>'];

        $mailbox = config('mail.unsubscribe_mailbox') ?: config('mail.from.address');
        if ($mailbox) {
            $targets[] = '<mailto:'.$mailbox.'?subject=unsubscribe>';
        }

        $headers = $message->getSymfonyMessage()->getHeaders();

        if (! $headers->has('List-Unsubscribe')) {
            $headers->addTextHeader('List-Unsubscribe', implode(', ', $targets));
        }

        if (! $headers->has('List-Unsubscribe-Post')) {
            $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }
    }

    /**
     * The recipient-specific unsubscribe URL for use inside email bodies.
     */
    public static function unsubscribeUrl(string $recipientEmail): string
    {
        /** @var SuppressionService $suppression */
        $suppression = app(SuppressionService::class);
        $token = $suppression->token($recipientEmail, ContactConsent::CHANNEL_EMAIL);

        return url('/unsubscribe').'?email='.rawurlencode($recipientEmail).'&channel=email&token='.$token;
    }
}
