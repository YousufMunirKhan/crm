<?php

namespace App\Services;

use App\Models\ColdCallingContact;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GooglePlacesColdCallingService
{
    private const PLACES_BASE = 'https://places.googleapis.com/v1';

    /** Nearby Search (New) has no nextPageToken — max 20 places per request. */
    private const NEARBY_FIELD_MASK = 'places.id,places.displayName,places.formattedAddress,places.location,places.types,places.businessStatus';

    /**
     * Table A types for Nearby Search — food & drink + high-street / indie retail (OR match).
     * Not every Table A name is accepted by searchNearby; omit unsupported ones (e.g. fast_food returns HTTP 400).
     * Omits department_store, shopping_mall, supermarket (those are filtered on ingest or excluded here).
     *
     * @var list<string>
     */
    private const NEARBY_INCLUDED_TYPES_FOOD_RETAIL = [
        'restaurant',
        'cafe',
        'coffee_shop',
        'bakery',
        'bar',
        'meal_takeaway',
        'breakfast_restaurant',
        'brunch_restaurant',
        'bistro',
        'clothing_store',
        'shoe_store',
        'womens_clothing_store',
        'convenience_store',
        'florist',
        'book_store',
        'gift_shop',
        'jewelry_store',
        'home_goods_store',
        'pet_store',
        'hardware_store',
        'bicycle_store',
        'electronics_store',
        'furniture_store',
        'tea_store',
        'candy_store',
        'butcher_shop',
        'thrift_store',
        'toy_store',
        'farmers_market',
        'flea_market',
        'cosmetics_store',
        'sporting_goods_store',
        'liquor_store',
        'general_store',
    ];

    /** Text Search (New) supports pagination via nextPageToken. */
    private const TEXT_SEARCH_FIELD_MASK = 'places.id,places.displayName,places.formattedAddress,places.location,places.types,places.businessStatus,nextPageToken';

    private const DETAILS_FIELD_MASK = 'id,displayName,formattedAddress,nationalPhoneNumber,internationalPhoneNumber,websiteUri,googleMapsUri,location,types,businessStatus,shortFormattedAddress,adrFormatAddress,addressComponents,editorialSummary,rating,userRatingCount,priceLevel,currentOpeningHours,regularOpeningHours';

    public function __construct(
        private string $apiKey,
    ) {}

    public static function fromSettings(): ?self
    {
        $key = \App\Modules\Settings\Models\Setting::where('key', 'google_maps_api_key')->value('value');
        $key = $key !== null ? trim($key) : '';
        if ($key === '') {
            return null;
        }

        return new self($key);
    }

    /**
     * @return array{lat: float, lng: float, formatted: ?string, geocode_source: string}
     */
    public function geocodeUkPostcode(string $postcode): array
    {
        $response = Http::timeout(25)->get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $postcode.', United Kingdom',
            'components' => 'country:GB',
            'key' => $this->apiKey,
        ]);

        if (! $response->successful()) {
            $fallback = $this->geocodeViaPlacesTextSearch($postcode);
            if ($fallback !== null) {
                $fallback['geocode_source'] = 'places_text_search';

                return $fallback;
            }
            throw new \RuntimeException('Geocoding HTTP error '.$response->status().'. '.$this->geocodeDeniedHelp());
        }

        $json = $response->json();
        $status = $json['status'] ?? 'UNKNOWN';
        $googleDetail = isset($json['error_message']) && is_string($json['error_message'])
            ? trim($json['error_message'])
            : '';

        if ($status === 'OK' && ! empty($json['results'][0]['geometry']['location'])) {
            $loc = $json['results'][0]['geometry']['location'];

            return [
                'lat' => (float) $loc['lat'],
                'lng' => (float) $loc['lng'],
                'formatted' => $json['results'][0]['formatted_address'] ?? null,
                'geocode_source' => 'geocoding_api',
            ];
        }

        $tryFallbackStatuses = ['REQUEST_DENIED', 'ZERO_RESULTS', 'INVALID_REQUEST', 'OVER_QUERY_LIMIT'];
        if (in_array($status, $tryFallbackStatuses, true)) {
            $fallback = $this->geocodeViaPlacesTextSearch($postcode);
            if ($fallback !== null) {
                $fallback['geocode_source'] = 'places_text_search';

                return $fallback;
            }
        }

        throw new \RuntimeException($this->formatGeocodeFailureMessage($status, $googleDetail));
    }

    /**
     * Fallback when classic Geocoding is denied or returns nothing (common when the key is restricted to Places only or Geocoding API is off).
     *
     * @return array{lat: float, lng: float, formatted: ?string}|null
     */
    private function geocodeViaPlacesTextSearch(string $postcode): ?array
    {
        $query = trim($postcode).', United Kingdom';
        $body = [
            'textQuery' => $query,
            'maxResultCount' => 1,
            'rankPreference' => 'RELEVANCE',
            'languageCode' => 'en-GB',
            'regionCode' => 'GB',
        ];

        $mask = 'places.location,places.formattedAddress';
        $response = $this->placesPost('places:searchText', $body, $mask);
        if (! $response->successful()) {
            return null;
        }

        $data = $response->json();
        $place = $data['places'][0] ?? null;
        if (! is_array($place) || empty($place['location'])) {
            return null;
        }

        $lat = isset($place['location']['latitude']) ? (float) $place['location']['latitude'] : null;
        $lng = isset($place['location']['longitude']) ? (float) $place['location']['longitude'] : null;
        if ($lat === null || $lng === null) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'formatted' => isset($place['formattedAddress']) && is_string($place['formattedAddress'])
                ? $place['formattedAddress']
                : null,
        ];
    }

    private function formatGeocodeFailureMessage(string $status, string $googleDetail): string
    {
        $base = 'Could not resolve this postcode (Geocoding status: '.$status.')';
        if ($googleDetail !== '') {
            $base .= '. Google says: '.$googleDetail;
        }
        $base .= '. '.$this->geocodeDeniedHelp();

        return $base;
    }

    private function geocodeDeniedHelp(): string
    {
        return 'Fix: In Google Cloud Console enable billing; enable Geocoding API and Places API (New) for this project; under Credentials, ensure the API key allows those APIs. If the key uses "HTTP referrers", server-side Geocoding will fail—use "IP addresses" (your server) or "None" for testing. The CRM also tries Places Text Search as a fallback when Geocoding is denied.';
    }

    /**
     * @return list<string> place ids without "places/" prefix for storage consistency
     */
    public function collectPlaceIdsNearby(float $lat, float $lng, float $radiusMeters, int $maxTotal, ?callable $onPage = null): array
    {
        // Places API (New) SearchNearby: single page only, max 20 results, no nextPageToken.
        $count = min(20, max(1, $maxTotal));
        $radius = min(max((float) $radiusMeters, 1.0), 50000.0);

        $body = [
            'maxResultCount' => $count,
            'languageCode' => 'en-GB',
            'regionCode' => 'GB',
            'includedTypes' => self::NEARBY_INCLUDED_TYPES_FOOD_RETAIL,
            'locationRestriction' => [
                'circle' => [
                    'center' => [
                        'latitude' => round($lat, 7),
                        'longitude' => round($lng, 7),
                    ],
                    'radius' => $radius,
                ],
            ],
        ];

        $response = $this->placesPost('places:searchNearby', $body, self::NEARBY_FIELD_MASK);
        $this->assertPlacesHttpOk($response, 'Nearby Search (Places API New)');
        $data = $response->json();

        if ($onPage) {
            $onPage(1, $data);
        }

        $seen = [];
        foreach ($data['places'] ?? [] as $place) {
            $pid = $this->normalizePlaceId($place['id'] ?? $place['name'] ?? '');
            if ($pid !== '') {
                $seen[$pid] = true;
            }
        }

        return array_slice(array_keys($seen), 0, $maxTotal);
    }

    /**
     * One page of Text Search (New), up to 20 places. Pass null for the first page; Google requires ~2s before using next_page_token.
     *
     * @return array{ids: list<string>, next_page_token: ?string}
     */
    /**
     * @param  string  $textQuery  Full Places Text Search query (include area/postcode as you need).
     */
    public function fetchTextSearchPage(float $lat, float $lng, float $radiusMeters, string $textQuery, ?string $pageToken = null): array
    {
        if ($pageToken !== null && $pageToken !== '') {
            sleep(2);
        }

        $body = [
            'textQuery' => $textQuery,
            'maxResultCount' => 20,
            'rankPreference' => 'RELEVANCE',
            'languageCode' => 'en-GB',
            'regionCode' => 'GB',
            'locationBias' => [
                'circle' => [
                    'center' => ['latitude' => $lat, 'longitude' => $lng],
                    'radius' => min($radiusMeters, 50000.0),
                ],
            ],
        ];
        if ($pageToken !== null && $pageToken !== '') {
            $body['pageToken'] = $pageToken;
        }

        $response = $this->placesPost('places:searchText', $body, self::TEXT_SEARCH_FIELD_MASK);
        $this->assertPlacesHttpOk($response, 'Text Search (Places API New)');
        $data = $response->json();

        $ids = [];
        $ordered = [];
        foreach ($data['places'] ?? [] as $place) {
            $pid = $this->normalizePlaceId($place['id'] ?? $place['name'] ?? '');
            if ($pid !== '' && ! isset($ordered[$pid])) {
                $ordered[$pid] = true;
                $ids[] = $pid;
            }
        }

        $next = $data['nextPageToken'] ?? null;
        $next = is_string($next) && $next !== '' ? $next : null;

        return [
            'ids' => $ids,
            'next_page_token' => $next,
        ];
    }

    /**
     * Second pass — different ranking; paginates until maxTotal unique ids or no more pages.
     *
     * @return list<string>
     */
    public function collectPlaceIdsTextSearch(float $lat, float $lng, float $radiusMeters, string $textQuery, int $maxTotal, ?callable $onPage = null): array
    {
        $seen = [];
        $nextPageToken = null;
        $page = 0;

        do {
            $chunk = $this->fetchTextSearchPage($lat, $lng, $radiusMeters, $textQuery, $nextPageToken);
            $page++;
            if ($onPage) {
                $onPage($page, ['places' => array_map(fn ($id) => ['id' => $id], $chunk['ids'])]);
            }
            foreach ($chunk['ids'] as $pid) {
                $seen[$pid] = true;
            }
            $nextPageToken = $chunk['next_page_token'];
        } while ($nextPageToken && count($seen) < $maxTotal);

        return array_slice(array_keys($seen), 0, $maxTotal);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchPlaceDetails(string $placeId): array
    {
        $id = $this->normalizePlaceId($placeId);
        $url = self::PLACES_BASE.'/places/'.rawurlencode($id);

        $response = Http::timeout(30)
            ->withHeaders([
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => self::DETAILS_FIELD_MASK,
            ])
            ->get($url);

        $this->assertPlacesHttpOk($response, 'Place Details (Places API New)');

        return $response->json();
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, mixed>
     */
    public function mapDetailsToContactAttributes(array $details): array
    {
        $name = $this->textFromDisplayName($details['displayName'] ?? null);
        $types = $details['types'] ?? [];
        if (! is_array($types)) {
            $types = [];
        }

        $lat = null;
        $lng = null;
        if (! empty($details['location'])) {
            $lat = isset($details['location']['latitude']) ? (float) $details['location']['latitude'] : null;
            $lng = isset($details['location']['longitude']) ? (float) $details['location']['longitude'] : null;
        }

        $editorial = null;
        if (! empty($details['editorialSummary']['text'])) {
            $editorial = $details['editorialSummary']['text'];
        }

        $opening = null;
        if (! empty($details['currentOpeningHours']['weekdayDescriptions'])) {
            $opening = $details['currentOpeningHours']['weekdayDescriptions'];
        } elseif (! empty($details['regularOpeningHours']['weekdayDescriptions'])) {
            $opening = $details['regularOpeningHours']['weekdayDescriptions'];
        }

        $extra = [
            'adrFormatAddress' => $details['adrFormatAddress'] ?? null,
            'shortFormattedAddress' => $details['shortFormattedAddress'] ?? null,
        ];

        $priceLevel = isset($details['priceLevel']) ? (string) $details['priceLevel'] : null;

        return [
            'name' => $name,
            'phone' => $details['nationalPhoneNumber'] ?? null,
            'international_phone' => $details['internationalPhoneNumber'] ?? null,
            'website' => $details['websiteUri'] ?? null,
            'formatted_address' => $details['formattedAddress'] ?? null,
            'postcode_extracted' => $this->extractUkPostcodeFromComponents($details['addressComponents'] ?? []),
            'latitude' => $lat,
            'longitude' => $lng,
            'types' => array_values($types),
            'business_status' => $details['businessStatus'] ?? null,
            'google_maps_uri' => $details['googleMapsUri'] ?? null,
            'rating' => isset($details['rating']) ? (float) $details['rating'] : null,
            'user_rating_count' => isset($details['userRatingCount']) ? (int) $details['userRatingCount'] : null,
            'price_level' => $priceLevel,
            'editorial_summary' => $editorial,
            'opening_hours_summary' => is_array($opening) ? $opening : null,
            'extra_payload' => array_filter($extra),
        ];
    }

    private function placesPost(string $action, array $body, string $fieldMask): Response
    {
        $url = self::PLACES_BASE.'/'.$action;

        return Http::timeout(60)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Goog-Api-Key' => $this->apiKey,
                'X-Goog-FieldMask' => $fieldMask,
            ])
            ->post($url, $body);
    }

    private function assertPlacesHttpOk(Response $response, string $operation): void
    {
        if ($response->successful()) {
            return;
        }

        $json = $response->json();
        $message = '';
        if (is_array($json) && isset($json['error']['message']) && is_string($json['error']['message'])) {
            $message = trim($json['error']['message']);
        }
        if ($message === '') {
            $message = $response->body();
        }

        $reason = '';
        if (is_array($json) && isset($json['error']['details']) && is_array($json['error']['details'])) {
            foreach ($json['error']['details'] as $d) {
                if (is_array($d) && ($d['@type'] ?? '') === 'type.googleapis.com/google.rpc.ErrorInfo') {
                    $reason = (string) ($d['reason'] ?? '');
                    break;
                }
            }
        }

        $needsKeyHelp = $response->status() === 403
            || str_contains(strtoupper($reason), 'API_KEY')
            || str_contains(strtoupper($message), 'BLOCKED')
            || str_contains(strtoupper($message), 'PERMISSION_DENIED');

        $suffix = $needsKeyHelp ? ' '.$this->placesApiNewUnblockHelp() : '';

        throw new \RuntimeException($operation.' failed (HTTP '.$response->status().'). '.$message.$suffix);
    }

    private function placesApiNewUnblockHelp(): string
    {
        return 'In Google Cloud Console: (1) APIs & Services → Library → enable **Places API (New)** — the old "Places API" alone does not unlock places.googleapis.com SearchNearby. (2) Credentials → your key → API restrictions → include **Places API (New)** (or "Don\'t restrict" for testing). (3) Billing enabled on the project.';
    }

    private function normalizePlaceId(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (Str::startsWith($raw, 'places/')) {
            return substr($raw, strlen('places/'));
        }

        return $raw;
    }

    private function textFromDisplayName(mixed $displayName): ?string
    {
        if (is_string($displayName)) {
            return $displayName !== '' ? $displayName : null;
        }
        if (is_array($displayName) && isset($displayName['text'])) {
            return (string) $displayName['text'];
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $components
     */
    private function extractUkPostcodeFromComponents(array $components): ?string
    {
        foreach ($components as $c) {
            $types = $c['types'] ?? [];
            if (! is_array($types)) {
                continue;
            }
            if (in_array('postal_code', $types, true)) {
                $text = $c['longText'] ?? $c['shortText'] ?? '';
                $text = is_string($text) ? $text : '';

                return ColdCallingContact::normalizeUkPostcode($text) ?: null;
            }
        }

        return null;
    }
}
