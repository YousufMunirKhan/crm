<?php

namespace App\Http\Controllers;

use App\Jobs\RunColdCallingDiscoveryJob;
use App\Models\ColdCallingContact;
use App\Models\ColdCallingExportLog;
use App\Models\ColdCallingRun;
use App\Modules\CRM\Models\Customer;
use App\Modules\Settings\Models\Setting;
use App\Services\WebsiteContactEnricher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ColdCallingController extends Controller
{
    private function ensureMarketingAdmin(): void
    {
        $role = Auth::user()?->role?->name;
        if (! in_array($role, ['Admin', 'Manager', 'System Admin'], true)) {
            abort(403, 'Unauthorized');
        }
    }

    public function settingsStatus()
    {
        $this->ensureMarketingAdmin();
        $key = Setting::where('key', 'google_maps_api_key')->value('value');
        $configured = $key !== null && trim($key) !== '';

        $claudeKey = trim((string) config('anthropic.api_key', ''));
        if ($claudeKey === '') {
            $claudeKey = trim((string) (Setting::where('key', 'anthropic_api_key')->value('value') ?? ''));
        }
        $claudeOff = Setting::where('key', 'cold_calling_use_claude')->value('value');
        $claudeEnabled = $claudeKey !== '' && ! in_array($claudeOff, ['0', 'false'], true);

        return response()->json([
            'configured' => $configured,
            'message' => $configured
                ? 'Google Maps / Places API key is configured.'
                : 'Add your Google Maps API key in Settings → Cold calling.',
            'claude_configured' => $claudeKey !== '',
            'claude_enrichment_enabled' => $claudeEnabled,
            'claude_message' => $claudeKey === ''
                ? 'Optional: add Anthropic API key in Settings → Cold calling or ANTHROPIC_API_KEY in .env for AI email/phone extraction after scraping.'
                : ($claudeEnabled
                    ? 'Claude AI enrichment is on (runs after page fetch if email/phone still missing).'
                    : 'Claude API key is saved but AI enrichment is turned off in settings.'),
        ]);
    }

    public function startRun(Request $request)
    {
        $this->ensureMarketingAdmin();

        $validated = $request->validate([
            'postcode' => ['required', 'string', 'max:32'],
            'radius_meters' => ['sometimes', 'integer', 'min:500', 'max:50000'],
            'enrich_email' => ['sometimes', 'boolean'],
        ]);

        if (! $this->googleApiConfigured()) {
            return response()->json([
                'message' => 'Google Maps API key is not configured. Open Settings → Cold calling.',
            ], 422);
        }

        $defaultRadius = (int) (Setting::where('key', 'cold_calling_default_radius_meters')->value('value') ?: 5000);
        $radius = (int) ($validated['radius_meters'] ?? $defaultRadius);
        $radius = max(500, min(50000, $radius));

        $normalized = ColdCallingContact::normalizeUkPostcode($validated['postcode']);
        if (strlen($normalized) < 5) {
            return response()->json(['message' => 'Please enter a valid UK postcode.'], 422);
        }

        $run = ColdCallingRun::query()->create([
            'user_id' => Auth::id(),
            'postcode_input' => trim($validated['postcode']),
            'postcode_normalized' => $normalized,
            'radius_meters' => $radius,
            'status' => 'pending',
            'meta' => [
                'enrich_email' => (bool) ($validated['enrich_email'] ?? false),
            ],
        ]);

        if (config('cold_calling.run_sync')) {
            RunColdCallingDiscoveryJob::dispatchSync($run->id);
        } else {
            RunColdCallingDiscoveryJob::dispatch($run->id);
        }

        return response()->json([
            'run' => $this->runToArray($run->fresh()),
            'message' => config('cold_calling.run_sync')
                ? 'Discovery finished for this run.'
                : 'Discovery queued. Run `php artisan queue:work` or wait for your worker — status will update when the job runs.',
        ], 201);
    }

    public function showRun(int $id)
    {
        $this->ensureMarketingAdmin();
        $run = ColdCallingRun::query()->with('user:id,name')->findOrFail($id);

        return response()->json(['run' => $this->runToArray($run)]);
    }

    public function indexRuns(Request $request)
    {
        $this->ensureMarketingAdmin();
        $perPage = min(50, max(5, (int) $request->input('per_page', 20)));

        $runs = ColdCallingRun::query()
            ->with('user:id,name')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $runs->getCollection()->map(fn ($r) => $this->runToArray($r)),
            'meta' => [
                'current_page' => $runs->currentPage(),
                'last_page' => $runs->lastPage(),
                'per_page' => $runs->perPage(),
                'total' => $runs->total(),
            ],
        ]);
    }

    public function indexContacts(Request $request)
    {
        $this->ensureMarketingAdmin();
        $perPage = min(100, max(10, (int) $request->input('per_page', 25)));

        $request->validate($this->coldCallingListFilterRules());

        $q = $this->coldCallingContactsFilteredQuery($request);

        // Newest-first for outreach: when we first saw the place (not last Google re-hit).
        $contacts = $q->orderByDesc('first_seen_at')->orderByDesc('id')->paginate($perPage);

        return response()->json([
            'data' => $contacts->items(),
            'meta' => [
                'current_page' => $contacts->currentPage(),
                'last_page' => $contacts->lastPage(),
                'per_page' => $contacts->perPage(),
                'total' => $contacts->total(),
            ],
        ]);
    }

    public function updateContact(Request $request, int $id)
    {
        $this->ensureMarketingAdmin();
        $contact = ColdCallingContact::query()->findOrFail($id);

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        if (array_key_exists('email', $data)) {
            $contact->email = $data['email'];
            $contact->email_source = $data['email'] ? 'manual' : null;
        }
        if (array_key_exists('notes', $data)) {
            $contact->notes = $data['notes'];
        }
        $contact->save();

        return response()->json(['contact' => $contact->fresh()]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->ensureMarketingAdmin();

        $request->validate($this->coldCallingListFilterRules());

        $query = $this->coldCallingContactsFilteredQuery($request)
            ->orderByDesc('first_seen_at')
            ->orderByDesc('id');

        $count = $query->count();
        $filters = $this->coldCallingFiltersSnapshot($request);

        ColdCallingExportLog::query()->create([
            'user_id' => Auth::id(),
            'format' => 'csv',
            'row_count' => $count,
            'filters' => $filters,
        ]);

        $filename = 'cold-calling-export-'.date('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'ID', 'Business name', 'Phone', 'International phone', 'Email', 'Email source',
                'Website', 'Address', 'Postcode (extracted)', 'Types', 'Rating', 'Reviews', 'Google Maps', 'Place ID', 'Notes',
                'CC prospect at', 'CC stage', 'CRM customer ID',
            ]);
            $query->chunk(200, function ($chunk) use ($out) {
                foreach ($chunk as $c) {
                    fputcsv($out, [
                        $c->id,
                        $c->name,
                        $c->phone,
                        $c->international_phone,
                        $c->email,
                        $c->email_source,
                        $c->website,
                        $c->formatted_address,
                        $c->postcode_extracted,
                        is_array($c->types) ? implode('; ', $c->types) : '',
                        $c->rating,
                        $c->user_rating_count,
                        $c->google_maps_uri,
                        $c->place_id,
                        $c->notes,
                        $c->prospect_marked_at,
                        $c->prospect_stage,
                        $c->crm_customer_id,
                    ]);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportLogs(Request $request)
    {
        $this->ensureMarketingAdmin();
        $logs = ColdCallingExportLog::query()
            ->with('user:id,name')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $logs]);
    }

    /**
     * Scrape homepage + common /contact paths for email and UK phone (best-effort).
     */
    public function bulkEnrichFromWebsites(Request $request, WebsiteContactEnricher $enricher)
    {
        $this->ensureMarketingAdmin();

        $validated = $request->validate(array_merge(
            [
                'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            ],
            $this->coldCallingListFilterRules()
        ));

        $limit = (int) ($validated['limit'] ?? 25);

        $query = $this->coldCallingContactsFilteredQuery($request)
            ->whereNotNull('website')
            ->where('website', '!=', '')
            ->where(function ($q) {
                $q->where(function ($a) {
                    $a->whereNull('email')->orWhere('email', '');
                })->orWhere(function ($b) {
                    $b->whereNull('phone')->orWhere('phone', '');
                });
            })
            ->orderByDesc('first_seen_at')
            ->orderByDesc('id')
            ->limit($limit);

        $checked = 0;
        $updated = 0;
        foreach ($query->get() as $contact) {
            $checked++;
            $found = $enricher->enrich($contact->website, $contact->name);
            $dirty = false;
            if (($contact->email === null || $contact->email === '') && $found['email']) {
                $contact->email = $found['email'];
                $contact->email_source = ($found['ai_enriched_email'] ?? false) ? 'enrichment_claude' : 'enrichment';
                $dirty = true;
            }
            if (($contact->phone === null || $contact->phone === '') && $found['phone']) {
                $contact->phone = $found['phone'];
                $dirty = true;
            }
            if ($dirty) {
                $contact->save();
                $updated++;
            }
        }

        return response()->json([
            'checked' => $checked,
            'updated' => $updated,
            'message' => $checked === 0
                ? 'No contacts with a website and missing email/phone matched your filters.'
                : "Checked {$checked} site(s); filled email or phone on {$updated} contact(s).",
        ]);
    }

    /**
     * Scrape one contact’s website and save email/phone when found (same enricher as bulk).
     */
    public function enrichSingleContactWebsite(int $id, WebsiteContactEnricher $enricher)
    {
        $this->ensureMarketingAdmin();
        $contact = ColdCallingContact::query()->findOrFail($id);

        $website = trim((string) $contact->website);
        if ($website === '') {
            return response()->json([
                'message' => 'No website on this listing — add a website URL before scraping.',
                'contact' => $contact->fresh(),
                'saved' => false,
                'found' => ['email' => null, 'phone' => null],
            ], 422);
        }

        $found = $enricher->enrich($contact->website, $contact->name);

        $hadEmptyEmail = $contact->email === null || trim((string) $contact->email) === '';
        $hadEmptyPhone = $contact->phone === null || trim((string) $contact->phone) === '';

        $dirty = false;
        if ($hadEmptyEmail && $found['email']) {
            $contact->email = $found['email'];
            $contact->email_source = ($found['ai_enriched_email'] ?? false) ? 'enrichment_claude' : 'enrichment';
            $dirty = true;
        }
        if ($hadEmptyPhone && $found['phone']) {
            $contact->phone = $found['phone'];
            $dirty = true;
        }
        if ($dirty) {
            $contact->save();
        }

        $contact = $contact->fresh();

        $savedBits = [];
        if ($hadEmptyEmail && $found['email']) {
            $savedBits[] = 'email';
        }
        if ($hadEmptyPhone && $found['phone']) {
            $savedBits[] = 'phone';
        }

        $message = $savedBits !== []
            ? 'Saved '.implode(' and ', $savedBits).' from the website.'
            : 'No email or phone found on the site — add manually if you have them.';

        return response()->json([
            'contact' => $contact,
            'saved' => $dirty,
            'found' => [
                'email' => $found['email'],
                'phone' => $found['phone'],
            ],
            'message' => $message,
        ]);
    }

    /**
     * Convert cold-calling row into a CRM prospect: creates customers row (type prospect), links crm_customer_id,
     * and sets cold-calling prospect fields.
     */
    public function markAsProspect(Request $request, int $id)
    {
        $this->ensureMarketingAdmin();
        $contact = ColdCallingContact::query()->findOrFail($id);

        $result = $this->syncColdCallingContactToCrmProspect($contact);
        if ($result instanceof JsonResponse) {
            return $result;
        }

        $code = $result['status'] === 'created' ? 201 : 200;
        $message = $result['status'] === 'created'
            ? 'Converted to prospect: saved in CRM (customers) and linked from cold calling.'
            : 'Already linked to CRM prospect/customer #'.$result['customer']->id.'.';

        return response()->json([
            'message' => $message,
            'customer' => $result['customer'],
            'contact' => $result['contact'],
        ], $code);
    }

    /**
     * Same as mark-prospect (idempotent). Kept for API clients that call import explicitly.
     */
    public function importToCrmCustomer(Request $request, int $id)
    {
        return $this->markAsProspect($request, $id);
    }

    /**
     * @return JsonResponse|array{customer: Customer, contact: ColdCallingContact, status: 'created'|'linked'}
     */
    private function syncColdCallingContactToCrmProspect(ColdCallingContact $contact): JsonResponse|array
    {
        $contact = $contact->fresh();

        if ($contact->crm_customer_id !== null) {
            $patch = [];
            if ($contact->prospect_marked_at === null) {
                $patch['prospect_marked_at'] = now();
                $patch['prospect_stage'] = $contact->prospect_stage ?: 'new';
                $patch['assigned_to'] = $contact->assigned_to ?? Auth::id();
            }
            if ($patch !== []) {
                $contact->update($patch);
                $contact = $contact->fresh();
            }

            $customer = Customer::query()->find($contact->crm_customer_id);
            if ($customer === null) {
                return response()->json(['message' => 'Linked CRM customer record is missing. Re-link by fixing data or contact support.'], 422);
            }

            return ['customer' => $customer, 'contact' => $contact, 'status' => 'linked'];
        }

        $phone = trim((string) ($contact->international_phone ?: $contact->phone));
        if ($phone === '') {
            return response()->json(['message' => 'Add a phone number before converting to prospect.'], 422);
        }
        if (Customer::query()->where('phone', $phone)->exists()) {
            return response()->json(['message' => 'A prospect/customer with this phone already exists in CRM.'], 422);
        }

        $customer = Customer::query()->create(array_merge(
            $this->coldCallingContactToProspectAttributes($contact),
            ['created_by' => Auth::id()]
        ));

        $contact->update([
            'crm_customer_id' => $customer->id,
            'prospect_marked_at' => $contact->prospect_marked_at ?? now(),
            'prospect_stage' => $contact->prospect_stage ?: 'new',
            'assigned_to' => $contact->assigned_to ?? Auth::id(),
        ]);

        return ['customer' => $customer, 'contact' => $contact->fresh(), 'status' => 'created'];
    }

    /**
     * @return array<string, mixed>
     */
    private function coldCallingListFilterRules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'postcode' => ['nullable', 'string', 'max:32'],
            'prospect' => ['nullable', 'string', 'max:8'],
            'missing_email' => ['nullable', 'string', 'max:8'],
            'has_website' => ['nullable', 'string', 'max:8'],
            'max_reviews' => ['nullable', 'integer', 'min:0', 'max:50000'],
            'exclude_name' => ['nullable', 'string', 'max:500'],
        ];
    }

    private function coldCallingContactsFilteredQuery(Request $request): \Illuminate\Database\Eloquent\Builder
    {
        return ColdCallingContact::query()
            ->search($request->input('q'))
            ->forPostcode($request->input('postcode'))
            ->prospectFilter($request->input('prospect'))
            ->filterMissingEmail($request->input('missing_email'))
            ->filterHasWebsite($request->input('has_website'))
            ->filterMaxReviews($request->input('max_reviews'))
            ->excludeNameContains($request->input('exclude_name'));
    }

    /**
     * @return array<string, mixed>
     */
    private function coldCallingFiltersSnapshot(Request $request): array
    {
        return [
            'q' => $request->input('q'),
            'postcode' => $request->input('postcode'),
            'prospect' => $request->input('prospect'),
            'missing_email' => $request->input('missing_email'),
            'has_website' => $request->input('has_website'),
            'max_reviews' => $request->input('max_reviews'),
            'exclude_name' => $request->input('exclude_name'),
        ];
    }

    private function coldCallingContactToProspectAttributes(ColdCallingContact $contact): array
    {
        $phone = trim((string) ($contact->international_phone ?: $contact->phone));

        return [
            'type' => Customer::TYPE_PROSPECT,
            'name' => $contact->name ?: 'Business',
            'business_name' => $contact->name,
            'phone' => $phone,
            'email' => $contact->email,
            'address' => $contact->formatted_address,
            'postcode' => $contact->postcode_extracted,
            'city' => null,
            'source' => 'cold_calling',
            'category' => is_array($contact->types) ? implode(', ', array_slice($contact->types, 0, 5)) : null,
            'latitude' => $contact->latitude,
            'longitude' => $contact->longitude,
            'notes' => trim(
                implode("\n\n", array_filter([
                    $contact->notes,
                    $contact->website ? 'Website: '.$contact->website : null,
                    $contact->google_maps_uri ? 'Maps: '.$contact->google_maps_uri : null,
                ]))
            ) ?: null,
        ];
    }

    private function runToArray(ColdCallingRun $run): array
    {
        return [
            'id' => $run->id,
            'user_id' => $run->user_id,
            'user' => $run->relationLoaded('user') && $run->user ? ['id' => $run->user->id, 'name' => $run->user->name] : null,
            'postcode_input' => $run->postcode_input,
            'postcode_normalized' => $run->postcode_normalized,
            'radius_meters' => $run->radius_meters,
            'status' => $run->status,
            'new_count' => $run->new_count,
            'duplicate_count' => $run->duplicate_count,
            'error_count' => $run->error_count,
            'details_fetched' => $run->details_fetched,
            'error_message' => $run->error_message,
            'meta' => $run->meta,
            'started_at' => $run->started_at?->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'created_at' => $run->created_at?->toIso8601String(),
        ];
    }

    private function googleApiConfigured(): bool
    {
        $key = Setting::where('key', 'google_maps_api_key')->value('value');

        return $key !== null && trim($key) !== '';
    }
}
