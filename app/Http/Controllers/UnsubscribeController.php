<?php

namespace App\Http\Controllers;

use App\Models\ContactConsent;
use App\Models\EmailUnsubscribe;
use App\Services\SuppressionService;
use Illuminate\Http\Request;

class UnsubscribeController extends Controller
{
    public function __construct(private SuppressionService $suppression) {}

    /**
     * Public: opt a recipient out of marketing on one channel.
     *
     * Requires the per-recipient token embedded in the unsubscribe link.
     * Without it anyone could unsubscribe anyone else by posting their address.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required_without:phone', 'nullable', 'email'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:32'],
            'channel' => ['nullable', 'in:email,sms,whatsapp'],
            'token' => ['required', 'string'],
        ]);

        $channel = $data['channel'] ?? ContactConsent::CHANNEL_EMAIL;
        $identifier = $channel === ContactConsent::CHANNEL_EMAIL
            ? ($data['email'] ?? null)
            : ($data['phone'] ?? null);

        if (! $identifier) {
            return response()->json(['message' => 'Nothing to unsubscribe.'], 422);
        }

        if (! $this->suppression->tokenMatches($data['token'], $identifier, $channel)) {
            return response()->json([
                'message' => 'This unsubscribe link is not valid. Please use the link from your most recent message.',
            ], 403);
        }

        if ($channel === ContactConsent::CHANNEL_EMAIL) {
            EmailUnsubscribe::unsubscribe($identifier);
        } else {
            $this->suppression->optOut($identifier, $channel, 'unsubscribe_link');
        }

        return response()->json([
            'message' => 'You have been unsubscribed. You will not receive further marketing messages.',
        ]);
    }

    /**
     * Public: current status, so the page can confirm before acting.
     */
    public function show(Request $request)
    {
        $channel = $request->query('channel', ContactConsent::CHANNEL_EMAIL);
        if (! in_array($channel, ContactConsent::channels(), true)) {
            $channel = ContactConsent::CHANNEL_EMAIL;
        }

        $identifier = $channel === ContactConsent::CHANNEL_EMAIL
            ? $request->query('email')
            : $request->query('phone');

        if (! $identifier) {
            return response()->json(['unsubscribed' => false, 'valid' => false]);
        }

        $token = (string) $request->query('token', '');
        $valid = $token !== '' && $this->suppression->tokenMatches($token, $identifier, $channel);

        return response()->json([
            'valid' => $valid,
            'channel' => $channel,
            'unsubscribed' => $this->suppression->isSuppressed($identifier, $channel),
        ]);
    }

    /**
     * RFC 8058 one-click unsubscribe target for the List-Unsubscribe-Post
     * header. Mail clients POST here directly, so there is no confirmation
     * step and the token is the only authentication.
     */
    public function oneClick(Request $request, string $channel, string $token)
    {
        $identifier = (string) $request->query('c', '');

        if (! in_array($channel, ContactConsent::channels(), true)
            || $identifier === ''
            || ! $this->suppression->tokenMatches($token, $identifier, $channel)) {
            return response('Invalid unsubscribe link.', 403);
        }

        if ($channel === ContactConsent::CHANNEL_EMAIL) {
            EmailUnsubscribe::unsubscribe($identifier, 'list_unsubscribe_one_click');
        } else {
            $this->suppression->optOut($identifier, $channel, 'list_unsubscribe_one_click');
        }

        return response('You have been unsubscribed.', 200);
    }
}
