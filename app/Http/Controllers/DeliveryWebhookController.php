<?php

namespace App\Http\Controllers;

use App\Models\ContactConsent;
use App\Models\SentCommunication;
use App\Services\SuppressionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Bounce and complaint handling.
 *
 * The existing /api/webhooks/* endpoints only handled inbound replies, so a
 * hard bounce or a spam complaint never entered a suppression list - it was
 * recorded as a status on one row and then repeatedly mailed again on the next
 * campaign, which is how sending reputation is destroyed.
 *
 * Provider-agnostic: accepts a normalised payload, and understands the common
 * SES / Mailgun / Postmark shapes.
 */
class DeliveryWebhookController extends Controller
{
    public function __construct(private SuppressionService $suppression) {}

    public function handle(Request $request)
    {
        $events = $this->normalise($request->all());

        if ($events === []) {
            Log::info('Delivery webhook received with no recognisable events', [
                'keys' => array_keys($request->all()),
            ]);

            return response()->json(['status' => 'ignored'], 202);
        }

        $suppressed = 0;

        foreach ($events as $event) {
            $email = $event['email'] ?? null;
            $type = $event['type'] ?? null;

            if (! $email || ! $type) {
                continue;
            }

            $this->recordOnSend($email, $type, $event['detail'] ?? null);

            // Only permanent failures and complaints suppress. A soft bounce
            // is a mailbox being full, not a person who should never be
            // contacted again.
            if (in_array($type, ['hard_bounce', 'complaint', 'unsubscribe'], true)) {
                $this->suppression->optOut(
                    $email,
                    ContactConsent::CHANNEL_EMAIL,
                    'delivery_webhook_'.$type,
                    $event['detail'] ?? null
                );
                $suppressed++;
            }
        }

        return response()->json([
            'status' => 'ok',
            'events' => count($events),
            'suppressed' => $suppressed,
        ]);
    }

    /**
     * Marks the most recent matching send so the report reflects reality.
     */
    private function recordOnSend(string $email, string $type, ?string $detail): void
    {
        $send = SentCommunication::query()
            ->where('type', 'email')
            ->where('recipient_email', $email)
            ->latest('id')
            ->first();

        if (! $send) {
            return;
        }

        $status = match ($type) {
            'hard_bounce', 'soft_bounce' => 'bounced',
            'complaint' => 'complained',
            default => $send->status,
        };

        $send->forceFill([
            'status' => $status,
            'failure_category' => $type,
            'error_message' => $detail ?: $send->error_message,
        ])->save();
    }

    /**
     * Flattens a provider payload into [['email','type','detail'], ...].
     *
     * @return list<array{email: string, type: string, detail: ?string}>
     */
    private function normalise(array $payload): array
    {
        $events = [];

        // Normalised shape (also what our own tests and any custom relay send).
        if (isset($payload['email'], $payload['event'])) {
            $events[] = [
                'email' => (string) $payload['email'],
                'type' => $this->mapType((string) $payload['event']),
                'detail' => $payload['reason'] ?? $payload['description'] ?? null,
            ];
        }

        // Amazon SES via SNS.
        if (isset($payload['notificationType'])) {
            $type = strtolower((string) $payload['notificationType']);
            $recipients = $payload['bounce']['bouncedRecipients']
                ?? $payload['complaint']['complainedRecipients']
                ?? [];
            $isPermanent = ($payload['bounce']['bounceType'] ?? '') === 'Permanent';

            foreach ($recipients as $recipient) {
                $events[] = [
                    'email' => (string) ($recipient['emailAddress'] ?? ''),
                    'type' => $type === 'complaint'
                        ? 'complaint'
                        : ($isPermanent ? 'hard_bounce' : 'soft_bounce'),
                    'detail' => $recipient['diagnosticCode'] ?? null,
                ];
            }
        }

        // Mailgun.
        if (isset($payload['event-data'])) {
            $data = $payload['event-data'];
            $events[] = [
                'email' => (string) ($data['recipient'] ?? ''),
                'type' => $this->mapType((string) ($data['event'] ?? ''), $data['severity'] ?? null),
                'detail' => $data['delivery-status']['message'] ?? null,
            ];
        }

        // Postmark.
        if (isset($payload['RecordType'], $payload['Email'])) {
            $events[] = [
                'email' => (string) $payload['Email'],
                'type' => $this->mapType((string) $payload['RecordType'], $payload['Type'] ?? null),
                'detail' => $payload['Description'] ?? null,
            ];
        }

        return array_values(array_filter($events, fn ($e) => ($e['email'] ?? '') !== ''));
    }

    private function mapType(string $event, ?string $severity = null): string
    {
        $event = strtolower($event);
        $severity = strtolower((string) $severity);

        return match (true) {
            str_contains($event, 'complaint'), str_contains($event, 'spam') => 'complaint',
            str_contains($event, 'unsubscrib') => 'unsubscribe',
            str_contains($event, 'bounce'), str_contains($event, 'failed') => $severity === 'temporary'
                ? 'soft_bounce'
                : 'hard_bounce',
            str_contains($event, 'delivered') => 'delivered',
            default => $event ?: 'unknown',
        };
    }
}
