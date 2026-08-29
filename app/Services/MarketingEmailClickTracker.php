<?php

namespace App\Services;

use Illuminate\Support\Facades\URL;

/**
 * Rewrites links in a marketing email through a signed redirect so clicks can
 * be attributed to the send that produced them.
 */
class MarketingEmailClickTracker
{
    /** Schemes and targets that must never be rewritten. */
    private const SKIP_PREFIXES = ['mailto:', 'tel:', '#', 'data:', 'javascript:'];

    public static function hash(string $url): string
    {
        return hash('sha256', $url);
    }

    public static function signedClickUrl(int $sentCommunicationId, string $url): string
    {
        return URL::signedRoute(
            'email.track.click',
            ['id' => $sentCommunicationId, 'url' => rawurlencode($url)],
            absolute: true
        );
    }

    /**
     * Rewrites every eligible href in the html.
     *
     * The unsubscribe link is deliberately left alone: routing an opt-out
     * through a tracker would record a "click" for someone leaving, and adds a
     * failure point to the one link that must always work.
     */
    public static function rewriteLinks(string $html, int $sentCommunicationId): string
    {
        return preg_replace_callback(
            '/href\s*=\s*(["\'])(.*?)\1/i',
            function (array $m) use ($sentCommunicationId): string {
                $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');

                if (! self::shouldRewrite($url)) {
                    return $m[0];
                }

                $tracked = self::signedClickUrl($sentCommunicationId, $url);

                return 'href='.$m[1].htmlspecialchars($tracked, ENT_QUOTES, 'UTF-8').$m[1];
            },
            $html
        ) ?? $html;
    }

    private static function shouldRewrite(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        foreach (self::SKIP_PREFIXES as $prefix) {
            if (stripos($url, $prefix) === 0) {
                return false;
            }
        }

        // Only outbound http(s) links are worth tracking.
        if (! preg_match('#^https?://#i', $url)) {
            return false;
        }

        // Never break the opt-out path.
        if (stripos($url, '/unsubscribe') !== false) {
            return false;
        }

        return true;
    }
}
