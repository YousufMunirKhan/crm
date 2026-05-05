<?php

namespace App\Http\Controllers;

use App\Models\SentCommunication;
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

    private function gifResponse(): Response
    {
        return response(base64_decode(self::GIF_1PX, true) ?: '', 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private',
            'Pragma' => 'no-cache',
        ]);
    }
}
