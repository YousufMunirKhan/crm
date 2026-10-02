<?php

namespace App\Services;

use App\Models\ColdCallingContact;
use App\Models\ColdCallingRun;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Where we already do business, as a list of places worth sweeping.
 *
 * A business next door to an existing customer is the warmest cold call there
 * is - "we look after the takeaway two doors down" - so the areas come from
 * the book itself: everybody who bought, and every prospect a lead was raised
 * for. Prospects with no lead are left out; most of those were found by cold
 * calling in the first place, and counting them would send the sweep back to
 * wherever the last sweep went.
 *
 * An area is a postcode district (the "M14" of "M14 5AB") when a postcode was
 * recorded, and the town as typed when it was not.
 */
class ColdCallingAreaService
{
    private const TOWN_PREFIX = 'TOWN:';

    /**
     * @return array{areas: list<array<string, mixed>>, unlocated: int}
     */
    public function areas(): array
    {
        $areas = [];
        $unlocated = 0;

        Customer::query()
            ->select(['id', 'type', 'postcode', 'city', 'latitude', 'longitude'])
            ->where(function ($q) {
                $q->where('type', Customer::TYPE_CUSTOMER)
                    ->orWhereIn('id', Lead::query()->select('customer_id'));
            })
            ->orderBy('id')
            ->chunk(1000, function ($customers) use (&$areas, &$unlocated) {
                foreach ($customers as $customer) {
                    $town = self::cleanTown($customer->city);
                    $district = self::districtFromPostcode($customer->postcode);

                    if ($district !== null) {
                        $key = $district;
                    } elseif ($town !== null) {
                        $key = Str::limit(self::TOWN_PREFIX.mb_strtoupper($town), 64, '');
                    } else {
                        $unlocated++;

                        continue;
                    }

                    $areas[$key] ??= [
                        'key' => $key,
                        'kind' => $district !== null ? 'district' : 'town',
                        'label' => $district ?? $town,
                        'towns' => [],
                        'customers' => 0,
                        'leads' => 0,
                        'lat_sum' => 0.0,
                        'lng_sum' => 0.0,
                        'located' => 0,
                    ];

                    if ($customer->type === Customer::TYPE_CUSTOMER) {
                        $areas[$key]['customers']++;
                    } else {
                        $areas[$key]['leads']++;
                    }

                    if ($town !== null) {
                        $areas[$key]['towns'][$town] = ($areas[$key]['towns'][$town] ?? 0) + 1;
                    }

                    $lat = $customer->latitude !== null ? (float) $customer->latitude : null;
                    $lng = $customer->longitude !== null ? (float) $customer->longitude : null;
                    if (self::looksLikeUk($lat, $lng)) {
                        $areas[$key]['lat_sum'] += $lat;
                        $areas[$key]['lng_sum'] += $lng;
                        $areas[$key]['located']++;
                    }
                }
            });

        $heldByDistrict = $this->contactsHeldByDistrict();
        $heldBySweep = $this->contactsHeldBySweptArea();
        $lastSweeps = $this->lastSweepByArea();

        $out = [];
        foreach ($areas as $key => $a) {
            arsort($a['towns']);
            $town = array_key_first($a['towns']);
            $last = $lastSweeps[$key] ?? null;

            $out[] = [
                'key' => $key,
                'kind' => $a['kind'],
                'label' => $a['label'],
                'town' => $a['kind'] === 'district' ? $town : null,
                'customers' => $a['customers'],
                'leads' => $a['leads'],
                'total' => $a['customers'] + $a['leads'],
                'contacts' => $a['kind'] === 'district'
                    ? ($heldByDistrict[$key] ?? 0)
                    : ($heldBySweep[$key] ?? 0),
                'filter_key' => self::filterKey($key),
                'centroid' => $a['located'] > 0
                    ? ['lat' => round($a['lat_sum'] / $a['located'], 6), 'lng' => round($a['lng_sum'] / $a['located'], 6)]
                    : null,
                'last_sweep' => $last,
            ];
        }

        usort($out, fn ($x, $y) => [$y['total'], $y['customers'], $x['key']] <=> [$x['total'], $x['customers'], $y['key']]);

        return ['areas' => $out, 'unlocated' => $unlocated];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $key): ?array
    {
        foreach ($this->areas()['areas'] as $area) {
            if ($area['key'] === $key) {
                return $area;
            }
        }

        return null;
    }

    /**
     * The point the scraper searches around.
     *
     * A district is looked up, because its centre is a fact. A town is taken
     * from our own customers there first: "Leeds" is also a village in Kent,
     * and the customers are the reason we are sweeping it at all.
     *
     * @param  array<string, mixed>  $area
     * @return array{lat: float, lng: float, source: string}|null
     */
    public function locate(array $area): ?array
    {
        $centroid = is_array($area['centroid'] ?? null)
            ? ['lat' => (float) $area['centroid']['lat'], 'lng' => (float) $area['centroid']['lng'], 'source' => 'customers']
            : null;

        if ($area['kind'] === 'district') {
            return $this->lookupOutcode($area['key'])
                ?? $centroid
                ?? $this->lookupViaGoogle($area['key']);
        }

        return $centroid
            ?? $this->lookupViaGoogle($area['label'])
            ?? $this->lookupPlace($area['label']);
    }

    /**
     * "M14 5AB", "m145ab" and "M14" all belong to M14. Null when it is not a
     * UK postcode - the column also holds "N/A", phone numbers and street names.
     */
    public static function districtFromPostcode(?string $postcode): ?string
    {
        $pc = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $postcode) ?? '');

        if (preg_match('/^([A-Z]{1,2}\d[A-Z\d]?)\d[A-Z]{2}$/', $pc, $m)) {
            return $m[1];
        }
        if (preg_match('/^[A-Z]{1,2}\d[A-Z\d]?$/', $pc)) {
            return $pc;
        }

        return null;
    }

    /**
     * What a sweep's contacts are tagged with, and so what the Saved contacts
     * postcode filter finds them by. The tag column is 16 characters.
     */
    public static function filterKey(string $areaKey): string
    {
        $bare = str_starts_with($areaKey, self::TOWN_PREFIX)
            ? substr($areaKey, strlen(self::TOWN_PREFIX))
            : $areaKey;

        return Str::limit(ColdCallingContact::normalizeUkPostcode($bare), 16, '');
    }

    private static function cleanTown(?string $city): ?string
    {
        $town = trim(preg_replace('/\s+/', ' ', (string) $city) ?? '', " \t\n\r\0\x0B,.-");
        if ($town === '' || mb_strlen($town) < 2 || preg_match('/\d/', $town)) {
            return null;
        }

        return Str::title(mb_strtolower($town));
    }

    private static function looksLikeUk(?float $lat, ?float $lng): bool
    {
        return $lat !== null && $lng !== null
            && $lat > 49.0 && $lat < 61.5
            && $lng > -9.0 && $lng < 2.5;
    }

    /**
     * @return array<string, int>
     */
    private function contactsHeldByDistrict(): array
    {
        $held = [];

        ColdCallingContact::query()
            ->whereNotNull('postcode_extracted')
            ->selectRaw('postcode_extracted, count(*) as held')
            ->groupBy('postcode_extracted')
            ->toBase()
            ->get()
            ->each(function ($row) use (&$held) {
                $district = self::districtFromPostcode($row->postcode_extracted);
                if ($district !== null) {
                    $held[$district] = ($held[$district] ?? 0) + (int) $row->held;
                }
            });

        return $held;
    }

    /**
     * Towns have no postcode to match a listing against, so they are counted
     * by what their own sweeps brought back.
     *
     * @return array<string, int>
     */
    private function contactsHeldBySweptArea(): array
    {
        return DB::table('cold_calling_contact_postcode as link')
            ->join('cold_calling_runs as run', 'run.id', '=', 'link.cold_calling_run_id')
            ->whereNotNull('run.area_key')
            ->selectRaw('run.area_key as area_key, count(distinct link.cold_calling_contact_id) as held')
            ->groupBy('run.area_key')
            ->pluck('held', 'area_key')
            ->map(fn ($held) => (int) $held)
            ->all();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function lastSweepByArea(): array
    {
        $last = [];

        ColdCallingRun::query()
            ->where('engine', ColdCallingRun::ENGINE_GMAPS_SCRAPER)
            ->whereNotNull('area_key')
            ->orderByDesc('id')
            ->get(['id', 'area_key', 'status', 'new_count', 'duplicate_count', 'error_message', 'finished_at', 'created_at'])
            ->each(function (ColdCallingRun $run) use (&$last) {
                $last[$run->area_key] ??= [
                    'id' => $run->id,
                    'status' => $run->status,
                    'new_count' => $run->new_count,
                    'duplicate_count' => $run->duplicate_count,
                    'error_message' => $run->error_message,
                    'finished_at' => $run->finished_at?->toIso8601String(),
                    'created_at' => $run->created_at?->toIso8601String(),
                ];
            });

        return $last;
    }

    /**
     * @return array{lat: float, lng: float, source: string}|null
     */
    private function lookupOutcode(string $district): ?array
    {
        try {
            $response = Http::timeout(10)->get('https://api.postcodes.io/outcodes/'.rawurlencode($district));
        } catch (\Throwable) {
            return null;
        }

        $lat = $response->json('result.latitude');
        $lng = $response->json('result.longitude');
        if (! $response->successful() || ! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng, 'source' => 'postcodes.io'];
    }

    /**
     * @return array{lat: float, lng: float, source: string}|null
     */
    private function lookupPlace(string $town): ?array
    {
        try {
            $response = Http::timeout(10)->get('https://api.postcodes.io/places', ['q' => $town, 'limit' => 10]);
        } catch (\Throwable) {
            return null;
        }

        $places = $response->json('result');
        if (! $response->successful() || ! is_array($places) || $places === []) {
            return null;
        }

        // The same name is a city in one county and a hamlet in another.
        $rank = ['City' => 0, 'Town' => 1, 'Suburban Area' => 2, 'Village' => 3];
        usort($places, fn ($a, $b) => ($rank[$a['local_type'] ?? ''] ?? 9) <=> ($rank[$b['local_type'] ?? ''] ?? 9));

        $lat = $places[0]['latitude'] ?? null;
        $lng = $places[0]['longitude'] ?? null;
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng, 'source' => 'postcodes.io'];
    }

    /**
     * @return array{lat: float, lng: float, source: string}|null
     */
    private function lookupViaGoogle(string $query): ?array
    {
        $google = GooglePlacesColdCallingService::fromSettings();
        if ($google === null) {
            return null;
        }

        try {
            $geo = $google->geocodeUkPostcode($query);
        } catch (\Throwable) {
            return null;
        }

        return ['lat' => (float) $geo['lat'], 'lng' => (float) $geo['lng'], 'source' => 'google'];
    }
}
