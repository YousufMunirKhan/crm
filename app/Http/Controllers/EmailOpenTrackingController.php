<?php

namespace App\Http\Controllers;

use App\Models\CommunicationClick;
use App\Models\SentCommunication;
use App\Services\MarketingEmailClickTracker;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EmailOpenTrackingController extends Controller
{
    private const GIF_1PX = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function pixel(Request $request, int $id): Response
    {
        if (! $request->hasValidSignature()) {
            return $this->gifResponse();
        }

        $row = SentCommunication::query()
            ->whereKey($id)
            ->where('type', 'email')
            ->where('status', 'sent')
            ->first();

        if ($row) {
            if ($row->opened_at === null) {
                $row->opened_at = now();
                $row->save();
            }
            $row->increment('open_count');
        }

        return $this->gifResponse();
    }

    /**
     * Records a click, then forwards the reader to the real destination.
     *
     * Signature-verified: without it, the endpoint would be an open redirect
     * that anyone could point anywhere.
     */
    public function click(Request $request, int $id)
    {
        $url = rawurldecode((string) $request->query('url', ''));

        if (! $request->hasValidSignature() || ! preg_match('#^https?://#i', $url)) {
            return redirect('/');
        }

        $send = SentCommunication::query()
            ->whereKey($id)
            ->where('type', 'email')
            ->first();

        if ($send) {
            $click = CommunicationClick::firstOrNew([
                'sent_communication_id' => $send->id,
                'url_hash' => MarketingEmailClickTracker::hash($url),
            ]);

            if (! $click->exists) {
                $click->url = $url;
                $click->click_count = 0;
                $click->first_clicked_at = now();
            }

            $click->click_count++;
            $click->last_clicked_at = now();
            $click->save();

            // A click is a stronger open signal than the pixel, which Apple
            // Mail Privacy Protection fires whether or not anyone looked.
            if ($send->opened_at === null) {
                $send->forceFill(['opened_at' => now()])->save();
            }
        }

        return redirect()->away($url);
    }

    private function gifResponse(): Response
    {
        return response(base64_decode(self::GIF_1PX, true) ?: '', 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
