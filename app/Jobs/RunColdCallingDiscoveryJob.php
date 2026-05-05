<?php

namespace App\Jobs;

use App\Models\ColdCallingContact;
use App\Models\ColdCallingContactPostcode;
use App\Models\ColdCallingRun;
use App\Modules\Settings\Models\Setting;
use App\Services\GooglePlacesColdCallingService;
use App\Services\WebsiteContactEnricher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RunColdCallingDiscoveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Safety cap: text search pages × ~2s delay can make runs long. */
    private const MAX_TEXT_SEARCH_PAGES = 30;

    public int $timeout = 900;

    public function __construct(
        public int $runId,
    ) {}

    public function handle(WebsiteContactEnricher $enricher): void
    {
        $run = ColdCallingRun::query()->find($this->runId);
        if (! $run) {
            return;
        }

        $service = GooglePlacesColdCallingService::fromSettings();
        if ($service === null) {
            $this->failRun($run, 'Google Maps API key is not configured. Add it in Settings → Cold calling.');

            return;
        }

        /** Max *new* rows to insert this run; existing place_id rows are never duplicated (only postcode link + last_seen). */
        $targetNew = (int) (Setting::where('key', 'cold_calling_max_places_per_run')->value('value') ?: 50);
        $targetNew = max(1, min(100, $targetNew));

        $preservedMeta = is_array($run->meta) ? $run->meta : [];
        $enrichEmail = filter_var(
            Setting::where('key', 'cold_calling_enrich_email')->value('value') ?: '0',
            FILTER_VALIDATE_BOOLEAN
        ) || (($preservedMeta['enrich_email'] ?? false) === true);

        $run->update([
            'status' => 'processing',
            'started_at' => now(),
            'error_message' => null,
        ]);

        $newCount = 0;
        $dupCount = 0;
        $errCount = 0;
        $detailsFetched = 0;
        $seenThisRun = [];
        $ingestFilters = $this->buildSmallBusinessIngestFilters();

        $meta = array_merge($preservedMeta, [
            'nearby_pages' => 0,
            'text_pages' => 0,
            'target_new_per_run' => $targetNew,
            'stopped_reason' => null,
            'errors' => [],
            'ingest_skipped' => [],
        ]);

        try {
            $geo = $service->geocodeUkPostcode($run->postcode_input);
            $meta['geocode_source'] = $geo['geocode_source'] ?? 'unknown';
            $lat = $geo['lat'];
            $lng = $geo['lng'];

            $pc = trim($run->postcode_input);
            $tpl = Setting::where('key', 'cold_calling_text_search_query')->value('value');
            if ($tpl !== null && trim($tpl) !== '') {
                $textQuery = str_replace(['{postcode}', '{POSTCODE}'], [$pc, strtoupper($pc)], trim($tpl));
            } else {
                $textQuery = 'independent restaurant cafe coffee shop bakery brunch breakfast lunch bistro pub bar takeaway deli sandwich retail boutique gift shop florist books clothing convenience store near '.$pc.' United Kingdom';
            }
            $textQuery = Str::limit(trim($textQuery), 480, '');
            $meta['text_search_query'] = $textQuery;

            $nearbyIds = $service->collectPlaceIdsNearby(
                $lat,
                $lng,
                (float) $run->radius_meters,
                20,
                function (int $page, $data = null) use (&$meta) {
                    $meta['nearby_pages'] = $page;
                }
            );

            foreach ($nearbyIds as $placeId) {
                $this->processPlaceId(
                    $placeId,
                    $run,
                    $service,
                    $enricher,
                    $enrichEmail,
                    $targetNew,
                    $seenThisRun,
                    $newCount,
                    $dupCount,
                    $errCount,
                    $detailsFetched,
                    $meta,
                    $ingestFilters
                );
            }

            try {
                $nextToken = null;
                $textPage = 0;
                while ($textPage < self::MAX_TEXT_SEARCH_PAGES) {
                    if ($newCount >= $targetNew) {
                        $meta['stopped_reason'] = 'new_target_reached';

                        break;
                    }

                    $page = $service->fetchTextSearchPage(
                        $lat,
                        $lng,
                        (float) $run->radius_meters,
                        $textQuery,
                        $nextToken
                    );
                    $textPage++;
                    $meta['text_pages'] = $textPage;

                    foreach ($page['ids'] as $placeId) {
                        $this->processPlaceId(
                            $placeId,
                            $run,
                            $service,
                            $enricher,
                            $enrichEmail,
                            $targetNew,
                            $seenThisRun,
                            $newCount,
                            $dupCount,
                            $errCount,
                            $detailsFetched,
                            $meta,
                            $ingestFilters
                        );
                    }

                    $nextToken = $page['next_page_token'];
                    if ($nextToken === null || $nextToken === '') {
                        if ($meta['stopped_reason'] === null) {
                            $meta['stopped_reason'] = 'text_search_exhausted';
                        }

                        break;
                    }

                    if ($newCount >= $targetNew) {
                        $meta['stopped_reason'] = 'new_target_reached';

                        break;
                    }
                }

                if ($textPage >= self::MAX_TEXT_SEARCH_PAGES && $newCount < $targetNew && $meta['stopped_reason'] === null) {
                    $meta['stopped_reason'] = 'max_text_pages_cap';
                }
            } catch (\Throwable $e) {
                $meta['text_search_skipped'] = $e->getMessage();
                Log::warning('Cold calling text search stopped', ['exception' => $e->getMessage()]);
            }

            $run->update([
                'status' => 'completed',
                'new_count' => $newCount,
                'duplicate_count' => $dupCount,
                'error_count' => $errCount,
                'details_fetched' => $detailsFetched,
                'meta' => $meta,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->failRun($run, $e->getMessage());
            Log::error('Cold calling run failed', ['run_id' => $run->id, 'exception' => $e]);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function processPlaceId(
        string $placeId,
        ColdCallingRun $run,
        GooglePlacesColdCallingService $service,
        WebsiteContactEnricher $enricher,
        bool $enrichEmail,
        int $targetNew,
        array &$seenThisRun,
        int &$newCount,
        int &$dupCount,
        int &$errCount,
        int &$detailsFetched,
        array &$meta,
        array $ingestFilters,
    ): void {
        if (isset($seenThisRun[$placeId])) {
            return;
        }
        $seenThisRun[$placeId] = true;

        try {
            $existing = ColdCallingContact::query()->where('place_id', $placeId)->first();

            if ($existing) {
                $existing->update(['last_seen_at' => now()]);
                $dupCount++;
                $this->attachPostcode($existing->id, $run->postcode_normalized, $run->id);

                return;
            }

            if ($newCount >= $targetNew) {
                return;
            }

            $details = $service->fetchPlaceDetails($placeId);
            $detailsFetched++;
            $attrs = $service->mapDetailsToContactAttributes($details);
            $attrs['place_id'] = $placeId;
            $attrs['source'] = 'google_places';
            $attrs['first_seen_at'] = now();
            $attrs['last_seen_at'] = now();

            if ($enrichEmail && ! empty($attrs['website'])) {
                $found = $enricher->enrich($attrs['website'], $attrs['name'] ?? null);
                if (empty($attrs['email'] ?? null) && $found['email']) {
                    $attrs['email'] = $found['email'];
                    $attrs['email_source'] = ($found['ai_enriched_email'] ?? false) ? 'enrichment_claude' : 'enrichment';
                }
                if (empty($attrs['phone'] ?? null) && $found['phone']) {
                    $attrs['phone'] = $found['phone'];
                }
            }

            $skipReason = $this->ingestFilterSkipReason($attrs, $ingestFilters);
            if ($skipReason !== null) {
                $meta['ingest_skipped'][$skipReason] = ($meta['ingest_skipped'][$skipReason] ?? 0) + 1;

                return;
            }

            $contact = ColdCallingContact::query()->create($attrs);
            $newCount++;
            $this->attachPostcode($contact->id, $run->postcode_normalized, $run->id);
        } catch (\Throwable $e) {
            $errCount++;
            $meta['errors'][] = Str::limit($placeId.': '.$e->getMessage(), 500);
            Log::warning('Cold calling place failed', [
                'place_id' => $placeId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{skip_reviews_over: int, exclude_names_csv: string, exclude_types: list<string>}
     */
    private function buildSmallBusinessIngestFilters(): array
    {
        $skipReviews = (int) (Setting::where('key', 'cold_calling_skip_if_reviews_over')->value('value') ?: 0);
        $skipReviews = max(0, min(500_000, $skipReviews));

        $excludeNames = trim((string) (Setting::where('key', 'cold_calling_discovery_exclude_names')->value('value') ?: ''));

        $rawTypes = trim((string) (Setting::where('key', 'cold_calling_discovery_exclude_types')->value('value') ?: ''));
        if ($rawTypes === '' || strcasecmp($rawTypes, 'default') === 0) {
            $excludeTypes = [
                'department_store',
                'shopping_mall',
                'supermarket',
                'hypermarket',
                'discount_supermarket',
                'discount_store',
                'warehouse_store',
                'wholesaler',
            ];
        } elseif (strcasecmp($rawTypes, 'none') === 0 || $rawTypes === '-') {
            $excludeTypes = [];
        } else {
            $excludeTypes = array_values(array_unique(array_filter(array_map(
                static fn (string $t) => strtolower(trim($t)),
                explode(',', $rawTypes)
            ))));
        }

        return [
            'skip_reviews_over' => $skipReviews,
            'exclude_names_csv' => $excludeNames,
            'exclude_types' => $excludeTypes,
        ];
    }

    /**
     * Drop new rows that look like large chains / malls (after Place Details — API cost already paid).
     *
     * @param  array<string, mixed>  $attrs
     * @param  array{skip_reviews_over: int, exclude_names_csv: string, exclude_types: list<string>}  $f
     */
    private function ingestFilterSkipReason(array $attrs, array $f): ?string
    {
        $maxR = (int) ($f['skip_reviews_over'] ?? 0);
        if ($maxR > 0) {
            $rc = $attrs['user_rating_count'] ?? null;
            if ($rc !== null && (int) $rc > $maxR) {
                return 'skipped_high_review_count';
            }
        }

        $name = strtolower((string) ($attrs['name'] ?? ''));
        $csv = (string) ($f['exclude_names_csv'] ?? '');
        if ($name !== '' && $csv !== '') {
            foreach (array_filter(array_map('trim', explode(',', $csv))) as $term) {
                if ($term === '' || strlen($term) > 80) {
                    continue;
                }
                if (str_contains($name, strtolower($term))) {
                    return 'skipped_excluded_name';
                }
            }
        }

        $types = $attrs['types'] ?? [];
        $excludeTypes = $f['exclude_types'] ?? [];
        if (is_array($types) && $excludeTypes !== []) {
            $tLower = array_map(static fn ($t) => strtolower((string) $t), $types);
            foreach ($excludeTypes as $ex) {
                $ex = strtolower(trim((string) $ex));
                if ($ex !== '' && in_array($ex, $tLower, true)) {
                    return 'skipped_excluded_place_type';
                }
            }
        }

        return null;
    }

    private function attachPostcode(int $contactId, string $normalized, int $runId): void
    {
        ColdCallingContactPostcode::query()->firstOrCreate(
            [
                'cold_calling_contact_id' => $contactId,
                'postcode_normalized' => $normalized,
            ],
            ['cold_calling_run_id' => $runId]
        );
    }

    private function failRun(ColdCallingRun $run, string $message): void
    {
        $run->update([
            'status' => 'failed',
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }
}
