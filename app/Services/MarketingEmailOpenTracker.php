<?php

namespace App\Services;

use Illuminate\Support\Facades\URL;

class MarketingEmailOpenTracker
{
    public static function signedOpenPixelUrl(int $sentCommunicationId): string
    {
        return URL::signedRoute('email.track.open', ['id' => $sentCommunicationId], absolute: true);
    }

    /**
     * Append a 1×1 tracking pixel before </body> when possible.
     */
    public static function appendPixel(string $html, int $sentCommunicationId): string
    {
        $src = htmlspecialchars(self::signedOpenPixelUrl($sentCommunicationId), ENT_QUOTES, 'UTF-8');
        $pixel = '<img src="' . $src . '" width="1" height="1" alt="" style="display:block;height:1px;width:1px;border:0;" />';

        if (preg_match('#</body\s*>#i', $html)) {
            return preg_replace('#</body\s*>#i', $pixel . '</body>', $html, 1);
        }

        return $html . $pixel;
    }
}
