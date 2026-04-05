<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Models\CustomerUserAssignment;
use App\Modules\CRM\Services\CustomerTimelineService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class CustomerController extends Controller
{
    public function __construct(
        protected CustomerTimelineService $timelineService
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        if ($request->get('type') === 'prospect' && ! $user->allowsNavSection('prospects')) {
            abort(403, 'You do not have access to Prospects.');
        }
        if ($request->get('type') === 'customer' && ! $user->allowsNavSection('customers')) {
            abort(403, 'You do not have access to Customers.');
        }

        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');

        $query = Customer::with(['leads', 'invoices', 'tickets', 'assignedUsers', 'creator'])
            ->addSelect([
                'won_products_count' => LeadItem::query()
                    ->selectRaw('COALESCE(SUM(lead_items.quantity), 0)')
                    ->join('leads', 'leads.id', '=', 'lead_items.lead_id')
                    ->whereColumn('leads.customer_id', 'customers.id')
                    ->where('lead_items.status', LeadItem::STATUS_WON),
            ]);

        // For sales agents: creator, assignee, person who assigned customer to someone, or lead assignee
        if ($isSalesAgent) {
            $query->forSalesAgent($user->id);
        }

        // General search (searches across name, business name, phone, email, location)
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('business_name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('postcode', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        // Individual field filters
        if ($request->has('name') && $request->name) {
            $query->where('name', 'like', "%{$request->name}%");
        }

        if ($request->has('business_name') && $request->business_name) {
            $query->where('business_name', 'like', "%{$request->business_name}%");
        }

        if ($request->has('phone') && $request->phone) {
            $query->where('phone', 'like', "%{$request->phone}%");
        }

        if ($request->has('email') && $request->email) {
            $query->where('email', 'like', "%{$request->email}%");
        }

        if ($request->has('city') && $request->city) {
            $query->where('city', 'like', "%{$request->city}%");
        }

        if ($request->has('postcode') && $request->postcode) {
            $query->where('postcode', 'like', "%{$request->postcode}%");
        }

        if ($request->has('assigned_to') && $request->assigned_to) {
            $query->whereHas('assignedUsers', function ($q) use ($request) {
                $q->where('user_id', $request->assigned_to);
            });
        }

        if ($request->has('created_by') && $request->created_by) {
            $query->where('created_by', $request->created_by);
        }

        // Prospect vs Customer: use customers.type column (kept in sync when leads are won/lost)
        if ($request->has('type') && $request->type) {
            if ($request->type === 'prospect') {
                $query->where('type', 'prospect');
            } elseif ($request->type === 'customer') {
                $query->where('type', 'customer');
            }
        }

        // Sort options
        $sortField = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $allowedSorts = ['name', 'phone', 'email', 'city', 'postcode', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $customers = $query->paginate($request->get('per_page', 15));

        return response()->json($customers);
    }

    public function show($id)
    {
        $customer = Customer::with([
            'remoteLicenses',
            'leads.assignee',
            'leads.assignmentLogs.assignedByUser',
            'leads.assignmentLogs.newAssignee',
            'leads.product',
            'leads.items.product',
            'invoices.items',
            'tickets.assignee',
            'tickets.assignees',
            'communications',
            'assignedUsers.role',
            'assignments.user',
            'assignments.assignedBy',
            'creator'
        ])->findOrFail($id);

        // Check access for sales agents
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        if ($isSalesAgent && ! $customer->salesAgentHasAccess($user->id)) {
            return response()->json(['message' => 'Unauthorized access to this customer'], 403);
        }

        $lead = $customer->leads()->latest()->first();
        $tickets = $customer->tickets()->latest()->get();
        $invoices = $customer->invoices()->latest()->get();

        $leadsWithActivities = $customer->leads()->with(['items.product', 'assignee', 'activities.user', 'activities.assignee'])->get();

        $timeline = $this->timelineService->buildTimeline($customer, $leadsWithActivities, $tickets, null);
        $appointments = $this->timelineService->collectAppointments($leadsWithActivities);

        // Get items customer has (only WON items from won leads — exclude lost items)
        $customerHasItems = $customer->leads->filter(fn($l) => $l->stage === 'won')
                                        ->flatMap(fn($l) => $l->items)
                                        ->filter(fn($item) => $item->status === \App\Modules\CRM\Models\LeadItem::STATUS_WON)
                                        ->unique('product_id')
                                        ->values();

        // Get next products to sell (suggestions) - inline implementation
        $suggestedProducts = collect();
        try {
            $wonLeads = $customer->leads->filter(fn($l) => $l->stage === 'won');
            $ownedProductIds = $wonLeads->flatMap(function($l) {
                return $l->items ?? collect();
            })->pluck('product_id')->filter()->unique();
            
            foreach ($ownedProductIds as $productId) {
                try {
                    $product = Product::find($productId);
                    if ($product) {
                        $suggestions = $product->getSuggestedProducts();
                        foreach ($suggestions as $suggestion) {
                            if ($suggestion && !$ownedProductIds->contains($suggestion->id)) {
                                $suggestedProducts->push([
                                    'product' => $suggestion,
                                    'suggested_by' => $product->name,
                                    'relationship_type' => 'suggest',
                                ]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Skip this product if there's an error
                    continue;
                }
            }
            $suggestedProducts = $suggestedProducts->unique(function($item) {
                return $item['product']->id ?? null;
            })->values();
        } catch (\Exception $e) {
            // If there's an error, just return empty collection
            $suggestedProducts = collect();
        }

        return response()->json([
            'customer' => $customer,
            'lead' => $lead,
            'tickets' => $tickets,
            'invoices' => $invoices,
            'timeline' => $timeline,
            'appointments' => $appointments,
            'customer_has_items' => $customerHasItems,
            'next_products' => $suggestedProducts,
        ]);
    }

    /**
     * Get communication logs (email, sms, whatsapp) for this customer for the Send Messages section.
     */
    public function communicationLogs($id)
    {
        $customer = Customer::findOrFail($id);

        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        if ($isSalesAgent && ! $customer->salesAgentHasAccess($user->id)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $comms = \App\Modules\Communication\Models\Communication::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($c) {
                $payload = is_array($c->provider_payload) ? $c->provider_payload : [];

                return [
                    'id' => 'comm-' . $c->id,
                    'channel' => $c->channel,
                    'direction' => $c->direction,
                    'subject' => $payload['subject'] ?? null,
                    'message' => $c->message,
                    'status' => $c->status,
                    'failure_hint' => $payload['send_error_friendly'] ?? null,
                    'send_error' => $c->status === 'failed' ? ($payload['send_error'] ?? null) : null,
                    'meta_error' => $c->status === 'failed' ? ($payload['meta_error'] ?? null) : null,
                    'created_at' => $c->created_at->toIso8601String(),
                ];
            });

        $sent = \App\Models\SentCommunication::where('customer_id', $customer->id)
            ->orderBy('sent_at', 'desc')
            ->get()
            ->map(function ($s) {
                return [
                    'id' => 'sent-' . $s->id,
                    'channel' => $s->type,
                    'subject' => $s->subject ?? null,
                    'message' => $s->content,
                    'status' => $s->status,
                    'created_at' => $s->sent_at ? $s->sent_at->toIso8601String() : $s->created_at->toIso8601String(),
                ];
            });

        $all = $comms->concat($sent)->sortByDesc(function ($i) {
            return $i['created_at'];
        })->values();

        $emails = $all->where('channel', 'email')->values()->all();
        $sms = $all->where('channel', 'sms')->values()->all();
        $whatsapp = $all->where('channel', 'whatsapp')->values()->all();

        return response()->json([
            'emails' => array_values($emails),
            'sms' => array_values($sms),
            'whatsapp' => array_values($whatsapp),
        ]);
    }

    /**
     * Merged timeline: communications, template sends, lead activities, tickets (optional lead scope).
     */
    public function unifiedTimeline(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');

        if ($isSalesAgent && ! $customer->salesAgentHasAccess($user->id)) {
            return response()->json(['message' => 'Unauthorized access to this customer'], 403);
        }

        $filterLeadId = $request->query('lead_id');
        $filterLeadId = ($filterLeadId !== null && $filterLeadId !== '') ? (int) $filterLeadId : null;
        if ($filterLeadId && ! $customer->leads()->whereKey($filterLeadId)->exists()) {
            return response()->json(['message' => 'Lead not found for this customer'], 404);
        }

        $fromInclusive = null;
        $toInclusive = null;
        if ($request->filled('on')) {
            $day = Carbon::parse($request->query('on'))->startOfDay();
            $fromInclusive = $day->copy();
            $toInclusive = $day->copy()->endOfDay();
        } else {
            if ($request->filled('from')) {
                $fromInclusive = Carbon::parse($request->query('from'))->startOfDay();
            }
            if ($request->filled('to')) {
                $toInclusive = Carbon::parse($request->query('to'))->endOfDay();
            }
        }

        if ($fromInclusive && $toInclusive && $fromInclusive->gt($toInclusive)) {
            return response()->json(['message' => 'Invalid date range: from is after to'], 422);
        }

        $actorUserId = null;
        if ($request->filled('user_id')) {
            $actorUserId = (int) $request->query('user_id');
        }

        $leadsWithActivities = $customer->leads()->with(['items.product', 'assignee', 'activities.user', 'activities.assignee'])->get();
        $tickets = $customer->tickets()->latest()->get();
        $timeline = $this->timelineService->buildTimeline($customer, $leadsWithActivities, $tickets, $filterLeadId);

        $hasTimeOrUserFilter = $fromInclusive !== null || $toInclusive !== null || $actorUserId !== null;
        if ($hasTimeOrUserFilter) {
            $timeline = $this->timelineService->applyTimelineFilters($timeline, $fromInclusive, $toInclusive, $actorUserId);
        }

        $matchingLeadIds = $timeline
            ->pluck('lead_id')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'timeline' => $timeline,
            'matching_lead_ids' => $matchingLeadIds,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => ['nullable', 'string', 'in:prospect,customer'],
            'name' => ['required', 'string'],
            'business_name' => ['nullable', 'string'],
            'owner_name' => ['nullable', 'string'],
            'contact_person_2_name' => ['nullable', 'string'],
            'contact_person_2_phone' => ['nullable', 'string'],
            'phone' => ['required', 'string'],
            'email' => ['nullable', 'email'],
            'whatsapp_number' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'postcode' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'vat_number' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'remote_licenses' => ['nullable', 'array'],
            'remote_licenses.*.anydesk_rustdesk' => ['nullable', 'string'],
            'remote_licenses.*.passwords' => ['nullable', 'string'],
            'remote_licenses.*.epos_type' => ['nullable', 'string'],
            'remote_licenses.*.lic_days' => ['nullable', 'string'],
            'birthday' => ['nullable', 'date'],
            'category' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $data['type'] = $data['type'] ?? Customer::TYPE_PROSPECT;
        $data['created_by'] = auth()->id();
        $remoteLicenses = $data['remote_licenses'] ?? [];
        unset($data['remote_licenses']);
        $customer = Customer::create($data);

        foreach ($remoteLicenses as $i => $rl) {
            if (!empty(array_filter($rl))) {
                $customer->remoteLicenses()->create([
                    'anydesk_rustdesk' => $rl['anydesk_rustdesk'] ?? null,
                    'passwords' => $rl['passwords'] ?? null,
                    'epos_type' => $rl['epos_type'] ?? null,
                    'lic_days' => isset($rl['lic_days']) && $rl['lic_days'] !== '' ? (string) $rl['lic_days'] : null,
                    'sort_order' => $i,
                ]);
            }
        }

        // Log WhatsApp number info if phone/whatsapp_number exists
        if (($customer->whatsapp_number || $customer->phone) && config('services.whatsapp.access_token')) {
            try {
                $whatsappService = app(\App\Modules\Communication\Services\WhatsAppService::class);
                $phoneNumber = $customer->whatsapp_number ?? $customer->phone;
                $phoneInfo = $whatsappService->getAddPhoneNumberInfo($phoneNumber);
                Log::info('New customer created with phone number', [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'formatted_phone' => $phoneInfo['formatted_number'],
                    'add_url' => $phoneInfo['add_url'],
                    'note' => 'Add this number to Meta Business Account allowed list to enable WhatsApp messaging',
                ]);
            } catch (\Exception $e) {
                // Silently fail - this is just for logging
            }
        }

        return response()->json($customer->load('creator'), 201);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $data = $request->validate([
            'type' => ['nullable', 'string', 'in:prospect,customer'],
            'name' => ['sometimes', 'string'],
            'business_name' => ['nullable', 'string'],
            'owner_name' => ['nullable', 'string'],
            'contact_person_2_name' => ['nullable', 'string'],
            'contact_person_2_phone' => ['nullable', 'string'],
            'phone' => ['sometimes', 'string'],
            'email' => ['nullable', 'email'],
            'whatsapp_number' => ['nullable', 'string'],
            'email_secondary' => ['nullable', 'email'],
            'sms_number' => ['nullable', 'string'],
            'address' => ['nullable', 'string'],
            'postcode' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'vat_number' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'source' => ['nullable', 'string'],
            'remote_licenses' => ['nullable', 'array'],
            'remote_licenses.*.anydesk_rustdesk' => ['nullable', 'string'],
            'remote_licenses.*.passwords' => ['nullable', 'string'],
            'remote_licenses.*.epos_type' => ['nullable', 'string'],
            'remote_licenses.*.lic_days' => ['nullable', 'string'],
            'birthday' => ['nullable', 'date'],
            'category' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $remoteLicenses = $data['remote_licenses'] ?? null;
        unset($data['remote_licenses']);
        $customer->update($data);

        if ($remoteLicenses !== null) {
            $customer->remoteLicenses()->delete();
            foreach ($remoteLicenses as $i => $rl) {
                if (!empty(array_filter($rl))) {
                    $customer->remoteLicenses()->create([
                        'anydesk_rustdesk' => $rl['anydesk_rustdesk'] ?? null,
                        'passwords' => $rl['passwords'] ?? null,
                        'epos_type' => $rl['epos_type'] ?? null,
                        'lic_days' => isset($rl['lic_days']) && $rl['lic_days'] !== '' ? (string) $rl['lic_days'] : null,
                        'sort_order' => $i,
                    ]);
                }
            }
        }

        // Log WhatsApp number info if phone/whatsapp_number was updated
        if (isset($data['whatsapp_number']) || isset($data['phone'])) {
            $phoneNumber = $customer->whatsapp_number ?? $customer->phone;
            if ($phoneNumber && config('services.whatsapp.access_token')) {
                try {
                    $whatsappService = app(\App\Modules\Communication\Services\WhatsAppService::class);
                    $phoneInfo = $whatsappService->getAddPhoneNumberInfo($phoneNumber);
                    Log::info('Customer phone number updated', [
                        'customer_id' => $customer->id,
                        'customer_name' => $customer->name,
                        'formatted_phone' => $phoneInfo['formatted_number'],
                        'add_url' => $phoneInfo['add_url'],
                        'note' => 'Add this number to Meta Business Account allowed list to enable WhatsApp messaging',
                    ]);
                } catch (\Exception $e) {
                    // Silently fail - this is just for logging
                }
            }
        }

        return response()->json($customer);
    }

    public function updateContactMethods(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $data = $request->validate([
            'whatsapp_number' => ['nullable', 'string'],
            'email_secondary' => ['nullable', 'email'],
            'sms_number' => ['nullable', 'string'],
        ]);

        // Remove empty strings and convert to null
        foreach ($data as $key => $value) {
            if ($value === '') {
                $data[$key] = null;
            }
        }

        $customer->update($data);

        return response()->json($customer->fresh());
    }

    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->delete();

        return response()->noContent();
    }

    /**
     * Import customers from Excel/CSV file
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'], // 10MB max
        ]);

        $file = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        
        try {
            // Read file based on extension
            if ($extension === 'csv') {
                $data = $this->readCSV($file);
            } else {
                $data = $this->readExcel($file);
            }

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File is empty or could not be read.',
                ], 422);
            }

            // Auto-detect headers (first row)
            $headers = array_map('trim', array_map('strtolower', $data[0]));
            $rows = array_slice($data, 1);

            // Map common column names to database fields (all customer fields + birthday + category)
            $fieldMap = [
                'name' => ['name', 'customer name', 'full name', 'company name'],
                'phone' => ['phone', 'mobile', 'telephone', 'contact number', 'phone number'],
                'contact_person_2_name' => ['contact person 2 name', 'contact 2 name', 'cp2 name'],
                'contact_person_2_phone' => ['contact person 2 phone', 'contact 2 phone', 'cp2 phone'],
                'email' => ['email', 'e-mail', 'email address'],
                'address' => ['address', 'street', 'street address'],
                'city' => ['city', 'town'],
                'postcode' => ['postcode', 'postal code', 'zip', 'zip code'],
                'vat_number' => ['vat number', 'vat', 'vat no', 'tax id'],
                'business_name' => ['business name', 'company', 'business'],
                'owner_name' => ['owner name', 'owner'],
                'whatsapp_number' => ['whatsapp number', 'whatsapp', 'wa number'],
                'email_secondary' => ['email secondary', 'secondary email', 'email 2'],
                'sms_number' => ['sms number', 'sms'],
                'notes' => ['notes', 'note'],
                'source' => ['source'],
                'anydesk_rustdesk' => ['anydesk', 'rustdesk', 'anydesk rustdesk', 'remote'],
                'passwords' => ['passwords', 'password'],
                'epos_type' => ['epos type', 'epos'],
                'lic_days' => ['lic days', 'licence days', 'license days'],
                'birthday' => ['birthday', 'date of birth', 'dob', 'birth date'],
                'category' => ['category', 'customer category', 'segment'],
            ];

            $columnMap = [];
            foreach ($fieldMap as $dbField => $possibleNames) {
                foreach ($possibleNames as $possibleName) {
                    $index = array_search($possibleName, $headers);
                    if ($index !== false) {
                        $columnMap[$dbField] = $index;
                        break;
                    }
                }
            }

            if (!isset($columnMap['name']) || !isset($columnMap['phone'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Required columns (Name and Phone) not found in file.',
                    'found_headers' => $headers,
                ], 422);
            }

            $products = Product::orderBy('name')->get(['id', 'name']);
            $productHeaderToId = [];
            foreach ($products as $p) {
                $productHeaderToId[strtolower(trim($p->name))] = $p->id;
            }

            $imported = 0;
            $errors = 0;
            $errorDetails = [];

            DB::beginTransaction();

            foreach ($rows as $rowIndex => $row) {
                try {
                    if (empty(array_filter($row))) {
                        continue;
                    }

                    $customerData = [];
                    foreach ($columnMap as $field => $colIndex) {
                        if (isset($row[$colIndex])) {
                            $val = trim($row[$colIndex]);
                            if ($field === 'birthday' && $val !== '') {
                                try {
                                    $customerData[$field] = Carbon::parse($val)->format('Y-m-d');
                                } catch (\Exception $e) {
                                    $customerData[$field] = null;
                                }
                            } elseif ($field === 'lic_days' && $val !== '') {
                                $customerData[$field] = (string) $val;
                            } else {
                                $customerData[$field] = $val !== '' ? $val : null;
                            }
                        }
                    }

                    if (empty($customerData['name']) || empty($customerData['phone'])) {
                        $errors++;
                        $errorDetails[] = "Row " . ($rowIndex + 2) . ": Missing name or phone";
                        continue;
                    }

                    $existing = Customer::where('phone', $customerData['phone'])->first();
                    if ($existing) {
                        $errors++;
                        $errorDetails[] = "Row " . ($rowIndex + 2) . ": Customer with phone {$customerData['phone']} already exists";
                        continue;
                    }

                    $customerData['created_by'] = auth()->id();

                    // Build remote_licenses from anydesk/passwords/epos/lic_days (legacy) + remote_N_* columns
                    $remoteLicensesList = [];
                    $legacyAnydesk = $customerData['anydesk_rustdesk'] ?? null;
                    $legacyPasswords = $customerData['passwords'] ?? null;
                    $legacyEpos = $customerData['epos_type'] ?? null;
                    $legacyLic = $customerData['lic_days'] ?? null;
                    unset($customerData['anydesk_rustdesk'], $customerData['passwords'], $customerData['epos_type'], $customerData['lic_days']);
                    if ($legacyAnydesk || $legacyPasswords || $legacyEpos || $legacyLic !== null) {
                        $remoteLicensesList[] = [
                            'anydesk_rustdesk' => $legacyAnydesk,
                            'passwords' => $legacyPasswords,
                            'epos_type' => $legacyEpos,
                            'lic_days' => $legacyLic !== null && $legacyLic !== '' ? (string) $legacyLic : null,
                        ];
                    }
                    for ($n = 1; $n <= 10; $n++) {
                        $prefix = "remote_{$n}_";
                        $aidx = array_search($prefix . 'anydesk_rustdesk', $headers);
                        if ($aidx === false) {
                            $aidx = array_search($prefix . 'anydesk', $headers);
                        }
                        $pidx = array_search($prefix . 'passwords', $headers);
                        $eidx = array_search($prefix . 'epos_type', $headers);
                        $lidx = array_search($prefix . 'lic_days', $headers);
                        if ($aidx === false && $pidx === false && $eidx === false && $lidx === false) {
                            break;
                        }
                        $arl = [
                            'anydesk_rustdesk' => isset($row[$aidx]) ? trim($row[$aidx]) : null,
                            'passwords' => isset($row[$pidx]) ? trim($row[$pidx]) : null,
                            'epos_type' => isset($row[$eidx]) ? trim($row[$eidx]) : null,
                            'lic_days' => null,
                        ];
                        if (isset($row[$lidx]) && trim((string) $row[$lidx]) !== '') {
                            $arl['lic_days'] = trim((string) $row[$lidx]);
                        }
                        if (!empty(array_filter($arl))) {
                            $remoteLicensesList[] = $arl;
                        }
                    }

                    $customer = Customer::create($customerData);
                    foreach ($remoteLicensesList as $i => $rl) {
                        $customer->remoteLicenses()->create([
                            'anydesk_rustdesk' => $rl['anydesk_rustdesk'] ?? null,
                            'passwords' => $rl['passwords'] ?? null,
                            'epos_type' => $rl['epos_type'] ?? null,
                            'lic_days' => $rl['lic_days'] ?? null,
                            'sort_order' => $i,
                        ]);
                    }

                    // Product columns: header (trim, lower) => product_id; value numeric = purchased, non-numeric = prospect
                    $customerColIndices = array_values($columnMap);
                    foreach ($headers as $colIndex => $headerRaw) {
                        if (in_array($colIndex, $customerColIndices)) {
                            continue;
                        }
                        $headerKey = strtolower(trim($headerRaw));
                        if (!isset($productHeaderToId[$headerKey])) {
                            continue;
                        }
                        $productId = $productHeaderToId[$headerKey];
                        $cellVal = isset($row[$colIndex]) ? trim($row[$colIndex]) : '';
                        if ($cellVal === '') {
                            continue;
                        }
                        $isNumeric = is_numeric($cellVal);
                        $qty = $isNumeric ? (int) $cellVal : 1;
                        if ($qty < 1) {
                            $qty = 1;
                        }

                        if ($isNumeric) {
                            $lead = Lead::create([
                                'customer_id' => $customer->id,
                                'product_id' => $productId,
                                'stage' => 'won',
                                'assigned_to' => auth()->id(),
                                'source' => 'import',
                            ]);
                            LeadItem::create([
                                'lead_id' => $lead->id,
                                'product_id' => $productId,
                                'quantity' => $qty,
                                'unit_price' => 0,
                                'status' => LeadItem::STATUS_WON,
                                'closed_at' => now(),
                            ]);
                        } else {
                            $lead = Lead::create([
                                'customer_id' => $customer->id,
                                'product_id' => $productId,
                                'stage' => 'follow_up',
                                'assigned_to' => auth()->id(),
                                'source' => 'import',
                            ]);
                            LeadItem::create([
                                'lead_id' => $lead->id,
                                'product_id' => $productId,
                                'quantity' => 1,
                                'unit_price' => 0,
                                'status' => LeadItem::STATUS_PENDING,
                            ]);
                        }
                    }

                    $customer->syncTypeFromLeads();
                    $imported++;
                } catch (\Exception $e) {
                    $errors++;
                    $errorDetails[] = "Row " . ($rowIndex + 2) . ": " . $e->getMessage();
                    Log::error("Customer import error row " . ($rowIndex + 2) . ": " . $e->getMessage());
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'imported' => $imported,
                'errors' => $errors,
                'error_details' => $errorDetails,
                'message' => "Successfully imported {$imported} customers" . ($errors > 0 ? " with {$errors} errors" : ''),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Customer import failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download import template: customer columns + one column per product (from DB).
     */
    public function importTemplate(Request $request)
    {
        $customerHeaders = [
            'name', 'phone', 'contact_person_2_name', 'contact_person_2_phone',
            'email', 'address', 'city', 'postcode', 'vat_number',
            'business_name', 'owner_name', 'whatsapp_number', 'email_secondary', 'sms_number',
            'notes', 'source',
            'remote_1_anydesk_rustdesk', 'remote_1_passwords', 'remote_1_epos_type', 'remote_1_lic_days',
            'remote_2_anydesk_rustdesk', 'remote_2_passwords', 'remote_2_epos_type', 'remote_2_lic_days',
            'birthday', 'category',
        ];
        $products = Product::orderBy('name')->get(['name']);
        $productHeaders = $products->pluck('name')->all();
        $headers = array_merge($customerHeaders, $productHeaders);

        $filename = 'customers_import_template_' . date('Y-m-d') . '.csv';
        $headersResponse = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fclose($file);
        };

        return response()->stream($callback, 200, $headersResponse);
    }

    /**
     * Export customers to CSV
     */
    public function export(Request $request)
    {
        $query = Customer::query();

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->get();

        $filename = 'customers_export_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($customers) {
            $file = fopen('php://output', 'w');
            
            // Add headers
            fputcsv($file, ['Name', 'Phone', 'Email', 'Address', 'City', 'Postcode', 'VAT Number']);
            
            // Add data
            foreach ($customers as $customer) {
                fputcsv($file, [
                    $customer->name,
                    $customer->phone,
                    $customer->email ?? '',
                    $customer->address ?? '',
                    $customer->city ?? '',
                    $customer->postcode ?? '',
                    $customer->vat_number ?? '',
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Read CSV file
     */
    private function readCSV($file): array
    {
        $data = [];
        $handle = fopen($file->getRealPath(), 'r');
        
        if ($handle !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                $data[] = $row;
            }
            fclose($handle);
        }
        
        return $data;
    }

    /**
     * Read Excel file using Maatwebsite Excel
     */
    private function readExcel($file): array
    {
        try {
            $data = Excel::toArray([], $file);
            return $data[0] ?? [];
        } catch (\Exception $e) {
            Log::error('Excel read error: ' . $e->getMessage());
            throw new \Exception('Failed to read Excel file: ' . $e->getMessage());
        }
    }

    /**
     * Assign customer to employee(s)
     */
    public function assign(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['required', 'exists:users,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $assignedBy = auth()->id();
        
        // Assign to multiple employees
        $customer->assignTo($data['user_ids'], $assignedBy, $data['notes'] ?? null);

        return response()->json([
            'message' => 'Customer assigned successfully',
            'customer' => $customer->fresh()->load(['assignedUsers', 'leads', 'invoices', 'tickets']),
        ], 200);
    }

    /**
     * Unassign customer from employee
     */
    public function unassign($id, $userId)
    {
        $customer = Customer::findOrFail($id);
        $user = auth()->user();
        
        // Check permission: admin/manager can unassign anyone, or user can unassign if they assigned it
        $assignment = CustomerUserAssignment::where('customer_id', $customer->id)
            ->where('user_id', $userId)
            ->firstOrFail();
        
        $canUnassign = $user->isRole('Admin') || 
                       $user->isRole('Manager') || 
                       $assignment->assigned_by === $user->id;
        
        if (!$canUnassign) {
            return response()->json(['message' => 'Unauthorized to unassign this customer'], 403);
        }

        $assignment->delete();

        return response()->json([
            'message' => 'Customer unassigned successfully',
        ], 200);
    }

    /**
     * Get assigned customers for current user
     */
    public function getAssignedCustomers(Request $request)
    {
        $user = auth()->user();
        
        $query = Customer::whereHas('assignedUsers', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['leads', 'invoices', 'tickets']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate($request->get('per_page', 15));

        return response()->json($customers);
    }
}


