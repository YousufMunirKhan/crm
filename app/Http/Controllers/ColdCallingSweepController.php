<?php

namespace App\Http\Controllers;

use App\Models\ColdCallingRun;
use App\Modules\Settings\Models\Setting;
use App\Services\ColdCallingAreaService;
use App\Services\GmapsScraperImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sweeps of the areas we already have customers in.
 *
 * Two halves, because the scraper cannot run where the CRM does. Staff choose
 * the areas here and they wait as pending runs; the runner on an office PC
 * (scripts/gmaps-sweep/sweep.php) claims them one at a time, scrapes Google
 * Maps, and posts the listings back. The runner half is behind a shared key
 * rather than a login.
 */
class ColdCallingSweepController extends Controller
{
    private const RUNNER_SEEN_SETTING = 'cold_calling_sweep_runner_seen_at';

    private function ensureMarketingAdmin(): void
    {
        $role = Auth::user()?->role?->name;
        if (! in_array($role, ['Admin', 'Manager', 'System Admin'], true)) {
            abort(403, 'Unauthorized');
        }
    }

    public function areas(ColdCallingAreaService $service): JsonResponse
    {
        $this->ensureMarketingAdmin();

        $result = $service->areas();

        return response()->json([
            'data' => $result['areas'],
            'unlocated' => $result['unlocated'],
            'defaults' => [
                'business_types' => config('cold_calling.sweep.business_types'),
                'depth' => (int) config('cold_calling.sweep.depth'),
                'radius_meters' => (int) (Setting::where('key', 'cold_calling_default_radius_meters')->value('value') ?: 5000),
            ],
            'runner' => [
                'key_configured' => (string) config('cold_calling.sweep.runner_key', '') !== '',
                'last_seen_at' => Setting::where('key', self::RUNNER_SEEN_SETTING)->value('value'),
                'pending' => ColdCallingRun::query()
                    ->where('engine', ColdCallingRun::ENGINE_GMAPS_SCRAPER)
                    ->whereIn('status', ['pending', 'processing'])
                    ->count(),
            ],
        ]);
    }

    /**
     * Put areas on the queue for the runner.
     */
    public function queue(Request $request, ColdCallingAreaService $service): JsonResponse
    {
        $this->ensureMarketingAdmin();

        $validated = $request->validate([
            'area_keys' => ['required', 'array', 'min:1', 'max:25'],
            'area_keys.*' => ['required', 'string', 'max:64'],
            'business_types' => ['sometimes', 'array', 'min:1', 'max:15'],
            'business_types.*' => ['required', 'string', 'max:60'],
            'radius_meters' => ['sometimes', 'integer', 'min:500', 'max:50000'],
            'depth' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'email' => ['sometimes', 'boolean'],
        ]);

        $types = array_values(array_unique(array_filter(array_map(
            static fn ($t) => trim((string) $t),
            $validated['business_types'] ?? config('cold_calling.sweep.business_types')
        ))));
        if ($types === []) {
            return response()->json(['message' => 'Give at least one business type to look for.'], 422);
        }

        $radius = (int) ($validated['radius_meters']
            ?? (Setting::where('key', 'cold_calling_default_radius_meters')->value('value') ?: 5000));
        $depth = (int) ($validated['depth'] ?? config('cold_calling.sweep.depth'));

        $known = collect($service->areas()['areas'])->keyBy('key');
        $results = [];
        $queued = 0;

        foreach (array_unique($validated['area_keys']) as $key) {
            $area = $known->get($key);
            if ($area === null) {
                $results[] = ['key' => $key, 'status' => 'unknown_area'];

                continue;
            }

            $waiting = ColdCallingRun::query()
                ->where('engine', ColdCallingRun::ENGINE_GMAPS_SCRAPER)
                ->where('area_key', $key)
                ->whereIn('status', ['pending', 'processing'])
                ->exists();
            if ($waiting) {
                $results[] = ['key' => $key, 'status' => 'already_queued'];

                continue;
            }

            $point = $service->locate($area);
            if ($point === null) {
                $results[] = ['key' => $key, 'status' => 'not_located'];

                continue;
            }

            $place = $area['kind'] === 'district'
                ? trim($area['label'].' '.($area['town'] ?? '')).', UK'
                : $area['label'].', UK';

            ColdCallingRun::query()->create([
                'user_id' => Auth::id(),
                'engine' => ColdCallingRun::ENGINE_GMAPS_SCRAPER,
                'area_key' => $key,
                'postcode_input' => Str::limit($area['label'], 32, ''),
                'postcode_normalized' => $area['filter_key'],
                'radius_meters' => $radius,
                'status' => 'pending',
                'meta' => [
                    'area_label' => trim($area['label'].' '.($area['town'] ?? '')),
                    'lat' => $point['lat'],
                    'lng' => $point['lng'],
                    'geocode_source' => $point['source'],
                    'keywords' => array_map(static fn (string $type) => $type.' in '.$place, $types),
                    'depth' => $depth,
                    'email' => (bool) ($validated['email'] ?? false),
                ],
            ]);

            $queued++;
            $results[] = ['key' => $key, 'status' => 'queued'];
        }

        return response()->json([
            'queued' => $queued,
            'results' => $results,
            'message' => $queued === 0
                ? 'Nothing was queued.'
                : "Queued {$queued} area(s). They are swept the next time the scraper runner is started on the office PC.",
        ], $queued > 0 ? 201 : 200);
    }

    /**
     * Take a sweep back off the queue. Only before the runner has started it -
     * after that the scraper is already working and the rows will arrive.
     */
    public function cancel(int $id): JsonResponse
    {
        $this->ensureMarketingAdmin();

        $run = ColdCallingRun::query()
            ->where('engine', ColdCallingRun::ENGINE_GMAPS_SCRAPER)
            ->findOrFail($id);

        if ($run->status !== 'pending') {
            return response()->json(['message' => 'Only a sweep that has not started can be cancelled.'], 422);
        }

        $run->update(['status' => 'cancelled', 'finished_at' => now()]);

        return response()->json(['message' => 'Sweep cancelled.']);
    }

    /**
     * Load a CSV the scraper already produced - for a sweep somebody ran by
     * hand, or when the runner cannot reach the CRM.
     */
    public function importCsv(Request $request, GmapsScraperImporter $importer): JsonResponse
    {
        $this->ensureMarketingAdmin();

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:csv,txt'],
            'area_key' => ['nullable', 'string', 'max:64'],
        ]);

        $file = $request->file('file');
        $areaKey = $validated['area_key'] ?? null;
        $label = $areaKey !== null && $areaKey !== ''
            ? (string) preg_replace('/^TOWN:/', '', $areaKey)
            : 'CSV upload';

        $run = ColdCallingRun::query()->create([
            'user_id' => Auth::id(),
            'engine' => ColdCallingRun::ENGINE_GMAPS_SCRAPER,
            'area_key' => $areaKey ?: null,
            'postcode_input' => Str::limit($label, 32, ''),
            'postcode_normalized' => $areaKey ? ColdCallingAreaService::filterKey($areaKey) : '',
            'radius_meters' => 0,
            'status' => 'processing',
            'started_at' => now(),
            'meta' => ['via' => 'upload', 'file' => Str::limit($file->getClientOriginalName(), 200, '')],
        ]);

        $counts = $importer->import($run, $importer->rowsFromCsv($file->getRealPath()));

        $run->update(['status' => 'completed', 'finished_at' => now()]);

        return response()->json([
            'run_id' => $run->id,
            'counts' => $counts,
            'message' => "Imported: {$counts['new']} new, {$counts['duplicate']} already saved, {$counts['skipped']} filtered out.",
        ], 201);
    }

    // ---------------------------------------------------------------------
    // Runner (X-Api-Key)
    // ---------------------------------------------------------------------

    /**
     * Hand the runner the next area, oldest first.
     */
    public function claim(): JsonResponse
    {
        Setting::updateOrCreate(
            ['key' => self::RUNNER_SEEN_SETTING],
            ['value' => now()->toIso8601String()],
        );

        $staleBefore = now()->subMinutes((int) config('cold_calling.sweep.stale_after_minutes', 120));

        $run = DB::transaction(function () use ($staleBefore) {
            $run = ColdCallingRun::query()
                ->where('engine', ColdCallingRun::ENGINE_GMAPS_SCRAPER)
                ->where(function ($q) use ($staleBefore) {
                    $q->where('status', 'pending')
                        // Claimed and never finished: the PC was switched off mid-sweep.
                        ->orWhere(function ($stale) use ($staleBefore) {
                            $stale->where('status', 'processing')->where('started_at', '<', $staleBefore);
                        });
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            $run?->update(['status' => 'processing', 'started_at' => now(), 'error_message' => null]);

            return $run;
        });

        if ($run === null) {
            return response()->json(['run' => null]);
        }

        $meta = is_array($run->meta) ? $run->meta : [];

        return response()->json([
            'run' => [
                'id' => $run->id,
                'label' => $meta['area_label'] ?? $run->postcode_input,
                'keywords' => array_values($meta['keywords'] ?? []),
                'lat' => (string) ($meta['lat'] ?? ''),
                'lon' => (string) ($meta['lng'] ?? ''),
                'radius' => (int) $run->radius_meters,
                'depth' => (int) ($meta['depth'] ?? config('cold_calling.sweep.depth')),
                'email' => (bool) ($meta['email'] ?? false),
                'max_time' => (int) config('cold_calling.sweep.max_time'),
            ],
        ]);
    }

    /**
     * A batch of scraper rows for a sweep the runner holds.
     */
    public function rows(Request $request, int $id, GmapsScraperImporter $importer): JsonResponse
    {
        $run = $this->heldRun($id);
        if ($run instanceof JsonResponse) {
            return $run;
        }

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:200'],
            'rows.*' => ['required', 'array'],
        ]);

        return response()->json(['counts' => $importer->import($run, $validated['rows'])]);
    }

    public function finish(Request $request, int $id): JsonResponse
    {
        $run = $this->heldRun($id);
        if ($run instanceof JsonResponse) {
            return $run;
        }

        $validated = $request->validate([
            'status' => ['required', 'in:completed,failed'],
            'error_message' => ['nullable', 'string', 'max:2000'],
            'rows_scraped' => ['nullable', 'integer', 'min:0'],
        ]);

        $meta = is_array($run->meta) ? $run->meta : [];
        $meta['rows_scraped'] = $validated['rows_scraped'] ?? null;

        $run->update([
            'status' => $validated['status'],
            'error_message' => $validated['status'] === 'failed'
                ? ($validated['error_message'] ?? 'The scraper did not finish this area.')
                : null,
            'details_fetched' => (int) ($validated['rows_scraped'] ?? 0),
            'meta' => $meta,
            'finished_at' => now(),
        ]);

        return response()->json(['status' => $run->status]);
    }

    private function heldRun(int $id): ColdCallingRun|JsonResponse
    {
        $run = ColdCallingRun::query()
            ->where('engine', ColdCallingRun::ENGINE_GMAPS_SCRAPER)
            ->find($id);

        if ($run === null) {
            return response()->json(['error' => 'No such sweep.'], 404);
        }
        if ($run->status !== 'processing') {
            return response()->json(['error' => 'This sweep is not in progress (status: '.$run->status.').'], 409);
        }

        return $run;
    }
}
