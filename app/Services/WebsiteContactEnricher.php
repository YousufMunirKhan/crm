<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Best-effort extraction of public email/phone from a business site (homepage, discovered links, common paths, footer, JSON-LD).
 * Optional second pass: Claude (Anthropic) on combined plain text when regex/scrape missed email or phone.
 * Only same-origin paths; bounded requests/timeouts. Not a substitute for licensed data or opt-in leads.
 */
class WebsiteContactEnricher
{
    public function __construct(
        private readonly ClaudeContactExtractionService $claude,
    ) {}

    private const MAX_PAGES = 14;

    private const MAX_DISCOVERED_FROM_HOME = 8;

    private const TIMEOUT_SECONDS = 12;

    private const MAX_BODY_BYTES = 2_000_000;

    private const BLOCKED_EMAIL_PREFIXES = ['noreply', 'no-reply', 'donotreply', 'mailer-daemon', 'postmaster'];

    private const BLOCKED_EMAIL_DOMAINS = [
        'example.com', 'example.org', 'test.com', 'sentry.io', 'google.com', 'facebook.com',
        'twitter.com', 'instagram.com', 'wix.com', 'squarespace.com', 'schema.org',
    ];

    /** @var list<string> */
    private const CONTACT_PATHS = [
        '/',
        '/contact',
        '/contact/',
        '/contact-us',
        '/contact-us/',
        '/contactus',
        '/contact.php',
        '/contact.html',
        '/pages/contact',
        '/en/contact',
        '/en/contact-us',
        '/about',
        '/about-us',
        '/about-us/',
        '/get-in-touch',
        '/get-in-touch/',
        '/reach-us',
        '/enquiries',
        '/enquiry',
        '/support',
        '/help',
        '/locations',
        '/location',
        '/visit-us',
        '/connect',
        '/say-hello',
        '/imprint',
        '/legal',
        '/privacy',
        '/privacy-policy',
        '/cookie-policy',
    ];

    /** Words in path or anchor text that suggest a contact page */
    private const CONTACT_LINK_HINTS = 'contact|enquir|touch|reach|about|support|help|location|visit|write|email|call|offices?|find-us|get-in|connect|imprint|legal|privacy';

    /**
     * @return array{email: ?string, phone: ?string, ai_enriched_email: bool, ai_enriched_phone: bool}
     */
    public function enrich(?string $url, ?string $businessName = null): array
    {
        $origin = $this->normalizeOrigin($url);
        if ($origin === null) {
            return [
                'email' => null,
                'phone' => null,
                'ai_enriched_email' => false,
                'ai_enriched_phone' => false,
            ];
        }

        $emails = [];
        $phones = [];
        $corpusParts = [];
        $pagesFetched = 0;
        $queued = $this->initialUrlQueue($origin);
        $fetchedUrls = [];
        $homeLinksQueued = false;

        while ($queued !== [] && $pagesFetched < self::MAX_PAGES) {
            $fullUrl = array_shift($queued);
            if (isset($fetchedUrls[$fullUrl])) {
                continue;
            }
            $fetchedUrls[$fullUrl] = true;

            $html = $this->fetchHtml($fullUrl);
            if ($html === null) {
                continue;
            }
            $pagesFetched++;

            foreach ($this->extractEmailsFromHtml($html) as $e) {
                $emails[$e] = true;
            }
            foreach ($this->extractPhonesFromHtml($html) as $p) {
                $phones[$p] = true;
            }

            $snippet = $this->htmlToPlainCorpus($html);
            if ($snippet !== '') {
                $corpusParts[] = Str::limit($snippet, 12000, '…');
            }

            if (! $homeLinksQueued && $this->urlIsHomepage($fullUrl, $origin)) {
                $homeLinksQueued = true;
                $prepend = [];
                foreach ($this->discoverContactUrlsFromHtml($html, $origin) as $extra) {
                    if (! isset($fetchedUrls[$extra]) && ! in_array($extra, $queued, true)) {
                        $prepend[] = $extra;
                    }
                }
                if ($prepend !== []) {
                    $queued = array_merge($prepend, $queued);
                }
            }
        }

        $email = $this->pickBestEmail(array_keys($emails));
        $phone = $this->pickFirstPhone(array_keys($phones));
        $aiEnrichedEmail = false;
        $aiEnrichedPhone = false;

        if ($this->claude->isEnabledForEnrichment()
            && ($email === null || $phone === null)
            && $corpusParts !== []) {
            $joined = Str::limit(implode("\n\n---PAGE---\n\n", $corpusParts), 45000, '…');
            $ai = $this->claude->extractFromWebsiteText($joined, $businessName);
            if ($email === null) {
                $candidate = $this->sanitizeEmail(trim((string) ($ai['email'] ?? '')));
                if ($candidate) {
                    $email = $candidate;
                    $aiEnrichedEmail = true;
                }
            }
            if ($phone === null) {
                $candidatePhone = $this->normalizeUkPhone((string) ($ai['phone'] ?? ''));
                if ($candidatePhone) {
                    $phone = $candidatePhone;
                    $aiEnrichedPhone = true;
                }
            }
        }

        return [
            'email' => $email,
            'phone' => $phone,
            'ai_enriched_email' => $aiEnrichedEmail,
            'ai_enriched_phone' => $aiEnrichedPhone,
        ];
    }

    private function htmlToPlainCorpus(string $html): string
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $forPlain = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html) ?? $html;
        $forPlain = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $forPlain) ?? $forPlain;
        $forPlain = preg_replace('#<noscript\b[^>]*>.*?</noscript>#is', ' ', $forPlain) ?? $forPlain;
        $text = strip_tags($forPlain);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return list<string>
     */
    private function initialUrlQueue(string $origin): array
    {
        $out = [];
        foreach (self::CONTACT_PATHS as $path) {
            $out[] = $this->joinUrl($origin, $path);
        }

        return array_values(array_unique($out));
    }

    private function urlIsHomepage(string $fullUrl, string $origin): bool
    {
        $path = parse_url($fullUrl, PHP_URL_PATH) ?? '';

        return rtrim($path, '/') === '' || rtrim($path, '/') === '/index' || rtrim($path, '/') === '/index.php' || rtrim($path, '/') === '/home';
    }

    /**
     * Same-origin links from homepage that look like contact/about pages.
     *
     * @return list<string>
     */
    private function discoverContactUrlsFromHtml(string $html, string $origin): array
    {
        $host = parse_url($origin, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return [];
        }

        $found = [];
        if (preg_match_all('/<a\s[^>]*href\s*=\s*(["\'])([^"\']+)\1/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $href = trim(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($href === '' || str_starts_with($href, '#') || str_starts_with(strtolower($href), 'javascript:')) {
                    continue;
                }
                if (preg_match('/^mailto:/i', $href)) {
                    continue;
                }
                if (preg_match('/^tel:/i', $href)) {
                    continue;
                }

                $absolute = $this->resolveUrl($href, $origin);
                if ($absolute === null) {
                    continue;
                }
                $h = parse_url($absolute, PHP_URL_HOST);
                if (! is_string($h) || strtolower($h) !== strtolower($host)) {
                    continue;
                }
                $path = parse_url($absolute, PHP_URL_PATH) ?? '/';
                $pathLower = strtolower($path);
                if (! preg_match('/('.self::CONTACT_LINK_HINTS.')/i', $pathLower)) {
                    continue;
                }
                if (preg_match('/\.(jpg|jpeg|png|gif|webp|svg|pdf|zip)(\?|$)/i', $pathLower)) {
                    continue;
                }
                $found[$absolute] = true;
                if (count($found) >= self::MAX_DISCOVERED_FROM_HOME) {
                    break;
                }
            }
        }

        return array_keys($found);
    }

    private function resolveUrl(string $href, string $origin): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            $scheme = parse_url($origin, PHP_URL_SCHEME) ?? 'https';

            return $scheme.':'.$href;
        }

        return $this->joinUrl($origin, $href[0] === '/' ? $href : '/'.$href);
    }

    private function normalizeOrigin(?string $url): ?string
    {
        if ($url === null || trim($url) === '') {
            return null;
        }
        $url = trim($url);
        if (! Str::startsWith($url, ['http://', 'https://'])) {
            $url = 'https://'.$url;
        }
        $parts = parse_url($url);
        if (empty($parts['host'])) {
            return null;
        }
        $scheme = ($parts['scheme'] ?? 'https') === 'http' ? 'http' : 'https';

        return $scheme.'://'.$parts['host'];
    }

    private function joinUrl(string $origin, string $pathOrRelative): string
    {
        if ($pathOrRelative === '') {
            return rtrim($origin, '/');
        }
        if ($pathOrRelative[0] === '/') {
            return rtrim($origin, '/').$pathOrRelative;
        }

        return rtrim($origin, '/').'/'.$pathOrRelative;
    }

    private function fetchHtml(string $url): ?string
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withOptions(['allow_redirects' => ['track_redirects' => true]])
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'en-GB,en-US;q=0.9,en;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate',
                    'Upgrade-Insecure-Requests' => '1',
                    'Sec-Fetch-Dest' => 'document',
                    'Sec-Fetch-Mode' => 'navigate',
                    'Sec-Fetch-Site' => 'none',
                    'Cache-Control' => 'max-age=0',
                ])
                ->get($url);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();
        if ($body === '') {
            return null;
        }
        if (strlen($body) > self::MAX_BODY_BYTES) {
            $body = substr($body, 0, self::MAX_BODY_BYTES);
        }

        return $body;
    }

    /**
     * @return list<string>
     */
    private function extractEmailsFromHtml(string $html): array
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $out = [];

        foreach ($this->extractEmailsFromJsonLd($html) as $e) {
            $out[] = $e;
        }

        foreach ($this->extractEmailsFromDom($html) as $e) {
            $out[] = $e;
        }

        foreach ($this->extractMailtoFromHtml($html) as $e) {
            $out[] = $e;
        }

        foreach ($this->extractDataEmailAttributes($html) as $e) {
            $out[] = $e;
        }

        foreach ($this->extractEmailsFromMetaTags($html) as $e) {
            $out[] = $e;
        }

        $forPlain = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html) ?? $html;
        $forPlain = preg_replace('#<style\b[^>]*>.*?</style>#is', ' ', $forPlain) ?? $forPlain;
        $forPlain = preg_replace('#<noscript\b[^>]*>.*?</noscript>#is', ' ', $forPlain) ?? $forPlain;

        $text = strip_tags($forPlain);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match_all('/\b[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}\b/', $text, $m2)) {
            foreach ($m2[0] as $c) {
                $e = $this->sanitizeEmail($c);
                if ($e) {
                    $out[] = $e;
                }
            }
        }

        foreach ($this->extractObfuscatedEmails($text) as $e) {
            $out[] = $e;
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * @return list<string>
     */
    private function extractEmailsFromJsonLd(string $html): array
    {
        $out = [];
        if (! preg_match_all('#<script[^>]+type\s*=\s*["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $blocks)) {
            return [];
        }
        foreach ($blocks[1] as $json) {
            $json = trim(html_entity_decode($json, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($json === '') {
                continue;
            }
            if (preg_match_all('/"email"\s*:\s*"([^"\\\\]+(?:\\\\.[^"\\\\]*)*)"/i', $json, $m)) {
                foreach ($m[1] as $raw) {
                    $e = $this->sanitizeEmail(stripslashes($raw));
                    if ($e) {
                        $out[] = $e;
                    }
                }
            }
            if (preg_match_all("/'email'\s*:\s*'([^'\\\\]+)'/i", $json, $m2)) {
                foreach ($m2[1] as $raw) {
                    $e = $this->sanitizeEmail($raw);
                    if ($e) {
                        $out[] = $e;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Footer and obvious contact regions + mailto anchors (DOM when ext-libxml available).
     *
     * @return list<string>
     */
    private function extractEmailsFromDom(string $html): array
    {
        $out = [];
        if (! class_exists(\DOMDocument::class)) {
            return [];
        }

        $wrapped = '<?xml encoding="UTF-8">'.$html;
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        if (@$dom->loadHTML($wrapped, LIBXML_NOWARNING | LIBXML_NOERROR | LIBXML_NONET) === false) {
            libxml_clear_errors();

            return [];
        }
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        $mailtoAnchors = $xpath->query('//a[starts-with(translate(@href, "MAILTO", "mailto"), "mailto:")]');
        if ($mailtoAnchors !== false) {
            foreach ($mailtoAnchors as $a) {
                $href = $a->getAttribute('href');
                if (preg_match('/mailto:\s*([^?#"\'\s]+)/i', $href, $mm)) {
                    $addr = rawurldecode($mm[1]);
                    $e = $this->sanitizeEmail($addr);
                    if ($e) {
                        $out[] = $e;
                    }
                }
            }
        }

        $dataNodes = $xpath->query('//*[@data-email or @data-mail or @data-contact-email]');
        if ($dataNodes !== false) {
            foreach ($dataNodes as $el) {
                foreach (['data-email', 'data-mail', 'data-contact-email'] as $attr) {
                    $v = $el->getAttribute($attr);
                    if ($v !== '') {
                        $e = $this->sanitizeEmail(html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        if ($e) {
                            $out[] = $e;
                        }
                    }
                }
            }
        }

        $footerQueries = [
            '//footer//text()',
            '//*[contains(translate(@class, "FOOTER", "footer"), "footer")]//text()',
            '//*[contains(translate(@id, "FOOTER", "footer"), "footer")]//text()',
        ];
        foreach ($footerQueries as $q) {
            $textNodes = $xpath->query($q);
            if ($textNodes === false) {
                continue;
            }
            foreach ($textNodes as $textNode) {
                $chunk = $textNode->textContent;
                if (! is_string($chunk) || strlen($chunk) < 5) {
                    continue;
                }
                if (preg_match_all('/\b[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}\b/', $chunk, $tm)) {
                    foreach ($tm[0] as $c) {
                        $e = $this->sanitizeEmail($c);
                        if ($e) {
                            $out[] = $e;
                        }
                    }
                }
                foreach ($this->extractObfuscatedEmails($chunk) as $e) {
                    $out[] = $e;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function extractMailtoFromHtml(string $html): array
    {
        $out = [];
        $patterns = [
            '/href\s*=\s*["\']mailto:\s*([^"\'?#]+)/i',
            '/<a[^>]+href\s*=\s*["\']mailto:\s*([^"\'?#]+)/i',
        ];
        foreach ($patterns as $re) {
            if (preg_match_all($re, $html, $m)) {
                foreach ($m[1] as $raw) {
                    $raw = html_entity_decode(trim($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $raw = rawurldecode($raw);
                    foreach (preg_split('/\s*,\s*/', $raw) as $part) {
                        $part = trim($part);
                        if (preg_match('/<([^>]+)>/', $part, $mm)) {
                            $part = trim($mm[1]);
                        }
                        $e = $this->sanitizeEmail($part);
                        if ($e) {
                            $out[] = $e;
                        }
                    }
                }
            }
        }

        if (preg_match_all('/mailto:\s*([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/i', $html, $m2)) {
            foreach ($m2[1] as $c) {
                $e = $this->sanitizeEmail($c);
                if ($e) {
                    $out[] = $e;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function extractEmailsFromMetaTags(string $html): array
    {
        $out = [];
        if (preg_match_all('/<meta\b[^>]*\bcontent\s*=\s*["\']([^"\']+)["\'][^>]*>/i', $html, $m)) {
            foreach ($m[1] as $raw) {
                if (! str_contains($raw, '@')) {
                    continue;
                }
                if (preg_match_all('/\b[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}\b/i', $raw, $em)) {
                    foreach ($em[0] as $addr) {
                        $e = $this->sanitizeEmail(html_entity_decode($addr, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                        if ($e) {
                            $out[] = $e;
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function extractDataEmailAttributes(string $html): array
    {
        $out = [];
        if (preg_match_all('/\bdata-(?:email|mail|e-mail)\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            foreach ($m[1] as $raw) {
                $e = $this->sanitizeEmail(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($e) {
                    $out[] = $e;
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function extractObfuscatedEmails(string $text): array
    {
        $out = [];
        $patterns = [
            '/\b([a-zA-Z0-9._%+\-]+)\s*\[?\s*@\s*\]?\s*([a-zA-Z0-9.\-]+)\s*\[?\s*\.\s*\]?\s*([a-zA-Z]{2,})\b/i',
            '/\b([a-zA-Z0-9._%+\-]+)\s*\(\s*at\s*\)\s*([a-zA-Z0-9.\-]+)\s*\(\s*dot\s*\)\s*([a-zA-Z]{2,})\b/i',
            '/\b([a-zA-Z0-9._%+\-]+)\s+at\s+([a-zA-Z0-9.\-]+)\s+dot\s+([a-zA-Z]{2,})\b/i',
            '/\b([a-zA-Z0-9._%+\-]+)\s*\{\s*at\s*\}\s*([a-zA-Z0-9.\-]+)\s*\{\s*dot\s*\}\s*([a-zA-Z]{2,})\b/i',
        ];
        foreach ($patterns as $re) {
            if (preg_match_all($re, $text, $m, PREG_SET_ORDER)) {
                foreach ($m as $row) {
                    $candidate = strtolower($row[1].'@'.$row[2].'.'.$row[3]);
                    $e = $this->sanitizeEmail($candidate);
                    if ($e) {
                        $out[] = $e;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function extractPhonesFromHtml(string $html): array
    {
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $out = [];

        if (preg_match_all('/href\s*=\s*["\']tel:([^"\'>&]+)/i', $html, $m)) {
            foreach ($m[1] as $raw) {
                $raw = rawurldecode(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $n = $this->normalizeUkPhone($raw);
                if ($n) {
                    $out[] = $n;
                }
            }
        }

        $forPlain = preg_replace('#<script\b[^>]*>.*?</script>#is', ' ', $html) ?? $html;
        $text = strip_tags($forPlain);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $patterns = [
            '/\+44\s?[\s\-]?(?:\(0\))?[\d\s\-]{10,16}/',
            '/\b0\d{2,4}[\s\-]?\d{3,4}[\s\-]?\d{3,4}\b/',
            '/\(\s*0\d{2,4}\s*\)[\s\-]?\d{3,4}[\s\-]?\d{3,4}/',
        ];
        foreach ($patterns as $re) {
            if (preg_match_all($re, $text, $m)) {
                foreach ($m[0] as $chunk) {
                    $n = $this->normalizeUkPhone($chunk);
                    if ($n) {
                        $out[] = $n;
                    }
                }
            }
        }

        return array_values(array_unique($out));
    }

    private function sanitizeEmail(string $raw): ?string
    {
        $email = strtolower(trim($raw));
        $email = preg_replace('/\s+/', '', $email) ?? $email;
        $email = str_replace(['&#64;', '&#x40;', '&#000064;', '(at)', '[at]', '{at}'], '@', $email);
        $email = str_replace(['(dot)', '[dot]', '{dot}'], '.', $email);
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        $domain = substr(strrchr($email, '@') ?: '', 1);
        foreach (self::BLOCKED_EMAIL_DOMAINS as $bad) {
            if ($domain === $bad || str_ends_with($domain, '.'.$bad)) {
                return null;
            }
        }
        $local = explode('@', $email, 2)[0] ?? '';
        foreach (self::BLOCKED_EMAIL_PREFIXES as $p) {
            if (str_starts_with($local, $p)) {
                return null;
            }
        }
        if (str_contains($email, 'example.com') || str_ends_with($email, '.png') || str_ends_with($email, '.jpg')) {
            return null;
        }

        return $email;
    }

    /**
     * @param  list<string>  $emails
     */
    private function pickBestEmail(array $emails): ?string
    {
        if ($emails === []) {
            return null;
        }
        $priority = [
            'contact@', 'enquiries@', 'enquiry@', 'info@', 'hello@', 'sales@', 'office@', 'admin@',
            'reception@', 'bookings@', 'booking@', 'team@', 'mail@', 'studio@', 'uk@',
        ];
        usort($emails, function (string $a, string $b) use ($priority): int {
            $sa = $this->emailPriorityScore($a, $priority);
            $sb = $this->emailPriorityScore($b, $priority);

            return $sa <=> $sb;
        });

        return $emails[0];
    }

    /**
     * @param  list<string>  $priorityPrefixes
     */
    private function emailPriorityScore(string $email, array $priorityPrefixes): int
    {
        $lower = strtolower($email);
        foreach ($priorityPrefixes as $i => $prefix) {
            if (str_starts_with($lower, $prefix)) {
                return $i;
            }
        }

        return 100;
    }

    /**
     * @param  list<string>  $phones
     */
    private function pickFirstPhone(array $phones): ?string
    {
        foreach ($phones as $p) {
            if ($p !== '') {
                return $p;
            }
        }

        return null;
    }

    private function normalizeUkPhone(string $raw): ?string
    {
        $s = trim($raw);
        $s = str_replace(["\xC2\xA0", '–', '—'], [' ', '-', '-'], $s);
        $digits = preg_replace('/[^\d+]/', '', $s) ?? '';

        if ($digits === '' || strlen($digits) < 10) {
            return null;
        }

        if (str_starts_with($digits, '+44')) {
            $rest = substr($digits, 3);
            if (strlen($rest) >= 9 && strlen($rest) <= 10) {
                return '0'.$rest;
            }
        }
        if (str_starts_with($digits, '44') && strlen($digits) >= 11) {
            $rest = substr($digits, 2);
            if (strlen($rest) >= 9 && strlen($rest) <= 10) {
                return '0'.$rest;
            }
        }
        if (str_starts_with($digits, '0') && strlen($digits) >= 10 && strlen($digits) <= 11) {
            return $digits;
        }

        return null;
    }
}
