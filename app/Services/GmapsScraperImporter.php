<?php

namespace App\Services;

use App\Models\ColdCallingContact;
use App\Models\ColdCallingContactPostcode;
use App\Models\ColdCallingRun;
use App\Modules\CRM\Models\Customer;
use App\Support\ColdCallingIngestFilters;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns rows from the Google Maps scraper (gosom/google-maps-scraper, as run
 * by scripts/gmaps-sweep) into cold calling contacts.
 *
 * The same business must not appear twice because it was found two ways, so
 * rows are matched on Google's place id - the same id Google Places gives us.
 * A listing that is already one of our customers is linked to that customer
 * rather than offered up as somebody to cold call: sweeping around the book
 * finds the book first.
 */
class GmapsScraperImporter
{
    public const SOURCE = 'gmaps_scraper';

    /** @var array<string, int>|null national phone digits → customer id */
    private ?array $customerPhones = null;

    /**
     * @param  iterable<array<string, mixed>>  $rows  scraper CSV rows keyed by column name
     * @return array{new: int, duplicate: int, skipped: int, errors: int}
     */
    public function import(ColdCallingRun $run, iterable $rows): array
    {
        $filters = ColdCallingIngestFilters::fromSettings();
        $tag = ColdCallingAreaService::filterKey((string) ($run->area_key ?: $run->postcode_normalized));

        $new = 0;
        $duplicate = 0;
        $errors = 0;
        $skipped = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            try {
                $attrs = $this->mapRow($row);
                if ($attrs === null) {
                    $skipped['skipped_no_name'] = ($skipped['skipped_no_name'] ?? 0) + 1;

                    continue;
                }

                $placeId = $attrs['place_id'];
                if (isset($seen[$placeId])) {
                    continue;
                }
                $seen[$placeId] = true;

                $existing = ColdCallingContact::query()->where('place_id', $placeId)->first();
                if ($existing) {
                    $this->refreshExisting($existing, $attrs);
                    $duplicate++;
                    $this->tag($existing->id, $tag, $run->id);

                    continue;
                }

                $reason = $attrs['permanently_closed']
                    ? 'skipped_permanently_closed'
                    : ColdCallingIngestFilters::skipReason($attrs, $filters);
                if ($reason !== null) {
                    $skipped[$reason] = ($skipped[$reason] ?? 0) + 1;

                    continue;
                }
                unset($attrs['permanently_closed']);

                $attrs['crm_customer_id'] = $this->existingCustomerId($attrs['international_phone'] ?: $attrs['phone']);
                $attrs['first_seen_at'] = now();
                $attrs['last_seen_at'] = now();

                $contact = ColdCallingContact::query()->create($attrs);
                $new++;
                $this->tag($contact->id, $tag, $run->id);
            } catch (\Throwable $e) {
                $errors++;
                Log::warning('Cold calling sweep row failed', [
                    'run_id' => $run->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $meta = is_array($run->meta) ? $run->meta : [];
        foreach ($skipped as $reason => $count) {
            $meta['ingest_skipped'][$reason] = ($meta['ingest_skipped'][$reason] ?? 0) + $count;
        }

        $run->update([
            'new_count' => $run->new_count + $new,
            'duplicate_count' => $run->duplicate_count + $duplicate,
            'error_count' => $run->error_count + $errors,
            'meta' => $meta,
        ]);

        return [
            'new' => $new,
            'duplicate' => $duplicate,
            'skipped' => array_sum($skipped),
            'errors' => $errors,
        ];
    }

    /**
     * Rows of a scraper CSV file, keyed by its header. Accepts both the full
     * export and the trimmed "lead fields" file the kit's own scripts write.
     *
     * @return \Generator<int, array<string, string>>
     */
    public function rowsFromCsv(string $path): \Generator
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return;
        }

        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            if (! is_array($header)) {
                return;
            }
            $header = array_map(
                static fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h) ?? '')),
                $header
            );

            while (($line = fgetcsv($handle, null, ',', '"', '')) !== false) {
                if ($line === [null]) {
                    continue;
                }
                $row = [];
                foreach ($header as $i => $column) {
                    if ($column !== '') {
                        $row[$column] = (string) ($line[$i] ?? '');
                    }
                }
                yield $row;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function mapRow(array $row): ?array
    {
        $get = static fn (string $key): string => is_scalar($row[$key] ?? null) ? trim((string) $row[$key]) : '';

        $name = $get('title') ?: $get('name');
        if ($name === '') {
            return null;
        }

        $address = $get('address');
        $phone = $get('phone');
        $category = $get('category');
        $status = $get('status');

        $complete = json_decode($get('complete_address'), true);
        $postcode = is_array($complete) ? (string) ($complete['postal_code'] ?? '') : '';
        if ($postcode === '' && preg_match('/\b([A-Z]{1,2}\d[A-Z\d]?)\s*(\d[A-Z]{2})\b/i', $address, $m)) {
            $postcode = $m[1].$m[2];
        }
        $postcode = ColdCallingContact::normalizeUkPostcode($postcode);

        $lat = is_numeric($get('latitude')) ? (float) $get('latitude') : null;
        $lng = is_numeric($get('longitude')) ? (float) $get('longitude') : null;
        if ($lat === null || $lng === null || abs($lat) > 90 || abs($lng) > 180) {
            $lat = $lng = null;
        }

        $email = $this->firstEmail($get('emails') ?: $get('email'));

        $hours = json_decode($get('open_hours'), true);
        $opening = null;
        if (is_array($hours) && $hours !== []) {
            $opening = [];
            foreach ($hours as $day => $times) {
                $opening[] = $day.': '.(is_array($times) ? implode(', ', $times) : (string) $times);
            }
        }

        return [
            'place_id' => $this->placeId($get('place_id'), $get('cid'), $name, $address),
            'name' => Str::limit($name, 255, ''),
            'phone' => $phone !== '' ? Str::limit($phone, 64, '') : null,
            'international_phone' => str_starts_with($phone, '+') ? Str::limit($phone, 64, '') : null,
            'email' => $email,
            'email_source' => $email !== null ? self::SOURCE : null,
            'website' => $this->website($get('website')),
            'formatted_address' => $address !== '' ? $address : null,
            'postcode_extracted' => $postcode !== '' && strlen($postcode) <= 16 ? $postcode : null,
            'latitude' => $lat,
            'longitude' => $lng,
            'types' => $category !== '' ? [$category] : [],
            'google_maps_uri' => $get('link') !== '' ? Str::limit($get('link'), 2048, '') : null,
            'rating' => is_numeric($get('review_rating')) && (float) $get('review_rating') > 0
                ? min(5.0, (float) $get('review_rating'))
                : null,
            'user_rating_count' => is_numeric($get('review_count')) ? max(0, (int) $get('review_count')) : null,
            'price_level' => $get('price_range') !== '' ? Str::limit($get('price_range'), 48, '') : null,
            'editorial_summary' => $get('descriptions') !== '' ? $get('descriptions') : null,
            'opening_hours_summary' => $opening,
            'extra_payload' => array_filter([
                'cid' => $get('cid'),
                'keyword' => $get('input_id'),
                'status' => $status,
            ]),
            'source' => self::SOURCE,
            'permanently_closed' => stripos($status, 'permanently closed') !== false,
        ];
    }

    /**
     * The trimmed CSV carries no id at all, so a listing from it is keyed on
     * what it is: the same name at the same address is the same business.
     */
    private function placeId(string $placeId, string $cid, string $name, string $address): string
    {
        if ($placeId !== '') {
            return Str::limit($placeId, 255, '');
        }
        if ($cid !== '') {
            return 'cid:'.Str::limit($cid, 200, '');
        }

        return 'gm:'.sha1(mb_strtolower($name).'|'.mb_strtolower($address));
    }

    private function firstEmail(string $raw): ?string
    {
        foreach (preg_split('/[\s,;]+/', trim($raw, " \t\n\r\0\x0B[]\"'")) ?: [] as $candidate) {
            $candidate = strtolower(trim($candidate, " \t\"'<>"));
            if ($candidate !== '' && strlen($candidate) <= 255 && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Maps hands out its own redirect ("/url?q=https://shop.example/…") for
     * some listings; the business's address is the q parameter.
     */
    private function website(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        if (str_contains($url, '/url?')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            if (is_string($query['q'] ?? null) && $query['q'] !== '') {
                $url = $query['q'];
            }
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'http://'.$url;
        }

        return strlen($url) <= 2048 ? $url : null;
    }

    /**
     * A second sighting never overwrites what somebody typed in; it only fills
     * what is still blank.
     *
     * @param  array<string, mixed>  $attrs
     */
    private function refreshExisting(ColdCallingContact $existing, array $attrs): void
    {
        $patch = ['last_seen_at' => now()];

        foreach (['phone', 'international_phone', 'website'] as $field) {
            if (blank($existing->{$field}) && filled($attrs[$field])) {
                $patch[$field] = $attrs[$field];
            }
        }
        if (blank($existing->email) && filled($attrs['email'])) {
            $patch['email'] = $attrs['email'];
            $patch['email_source'] = self::SOURCE;
        }

        $existing->update($patch);
    }

    private function tag(int $contactId, string $tag, int $runId): void
    {
        if ($tag === '') {
            return;
        }

        ColdCallingContactPostcode::query()->firstOrCreate(
            [
                'cold_calling_contact_id' => $contactId,
                'postcode_normalized' => $tag,
            ],
            ['cold_calling_run_id' => $runId]
        );
    }

    private function existingCustomerId(?string $phone): ?int
    {
        $digits = self::nationalDigits((string) $phone);
        if ($digits === null) {
            return null;
        }

        if ($this->customerPhones === null) {
            $this->customerPhones = [];
            Customer::query()
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->toBase()
                ->select(['id', 'phone'])
                ->orderBy('id')
                ->each(function ($customer) {
                    $key = self::nationalDigits((string) $customer->phone);
                    if ($key !== null) {
                        $this->customerPhones[$key] ??= (int) $customer->id;
                    }
                });
        }

        return $this->customerPhones[$digits] ?? null;
    }

    /**
     * "0161 224 1234", "+44 161 224 1234" and "441612241234" are one number.
     * Null when there are too few digits to call it a match.
     */
    private static function nationalDigits(string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '0044')) {
            $digits = substr($digits, 4);
        } elseif (str_starts_with($digits, '44') && strlen($digits) > 10) {
            $digits = substr($digits, 2);
        }
        $digits = ltrim($digits, '0');

        return strlen($digits) >= 9 ? $digits : null;
    }
}
