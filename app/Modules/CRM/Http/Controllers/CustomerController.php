<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\CRM\Models\Product;
use App\Modules\CRM\Models\CustomerUserAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class CustomerController extends Controller
{
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

        $query = Customer::with(['leads', 'invoices', 'tickets', 'assignedUsers', 'creator']);

        // For sales agents: show only customers they created, assigned to them, assigned by them, or with their leads
        if ($isSalesAgent) {
            $query->where(function ($q) use ($user) {
                // Customers created by agent
                $q->where('created_by', $user->id)
                // OR customers directly assigned to agent
                ->orWhereHas('assignedUsers', function ($subQuery) use ($user) {
                    $subQuery->where('user_id', $user->id);
                })
                // OR customers this agent has assigned to someone (assignment log)
                ->orWhereHas('assignments', function ($subQuery) use ($user) {
                    $subQuery->where('assigned_by', $user->id);
                })
                // OR customers with leads assigned to agent
                ->orWhereHas('leads', function ($subQuery) use ($user) {
                    $subQuery->where('assigned_to', $user->id);
                });
            });
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
            'communications',
            'assignedUsers.role',
            'assignments.user',
            'assignments.assignedBy',
            'creator'
        ])->findOrFail($id);

        // Check access for sales agents
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        if ($isSalesAgent) {
            $hasAccess = $customer->created_by === $user->id ||
                        $customer->isAssignedTo($user->id) || 
                        $customer->assignments()->where('assigned_by', $user->id)->exists() ||
                        $customer->leads()->where('assigned_to', $user->id)->exists();
            
            if (!$hasAccess) {
                return response()->json(['message' => 'Unauthorized access to this customer'], 403);
            }
        }

        $lead = $customer->leads()->latest()->first();
        $tickets = $customer->tickets()->latest()->get();
        $invoices = $customer->invoices()->latest()->get();

        // Build unified timeline
        $timeline = collect();

        // Add communications
        foreach ($customer->communications()->latest()->get() as $comm) {
            $channelIcons = [
                'whatsapp' => '💬 WhatsApp',
                'email' => '📧 Email',
                'sms' => '📱 SMS',
                'phone' => '📞 Phone Call',
            ];
            $channelLabel = $channelIcons[$comm->channel] ?? ucfirst($comm->channel);
            $directionLabel = $comm->direction === 'outbound' ? 'sent' : 'received';
            
            $timeline->push([
                'id' => 'comm-' . $comm->id,
                'type' => 'communication',
                'title' => $channelLabel . ' ' . $directionLabel,
                'body' => $comm->message,
                'when' => $comm->created_at->diffForHumans(),
                'created_at' => $comm->created_at->toIso8601String(),
                'meta' => 'Status: ' . ucfirst($comm->status),
            ]);
        }

        // Collect appointments from all leads (for "Appointments" section like follow-ups)
        $appointments = collect();
        $leadsWithActivities = $customer->leads()->with(['items.product', 'assignee', 'activities.user', 'activities.assignee'])->get();

        // Add lead events and activities from ALL leads (one entry per meaningful event)
            foreach ($leadsWithActivities as $leadItem) {
            $stageLabels = [
                'follow_up' => 'Follow-up',
                'lead' => 'Lead',
                'hot_lead' => 'Hot Lead',
                'quotation' => 'Quotation',
                'won' => 'Won',
                'lost' => 'Lost',
            ];
            $stageLabel = $stageLabels[$leadItem->stage] ?? ucfirst($leadItem->stage);
            $products = $leadItem->items->pluck('product.name')->filter()->implode(', ') ?: 'No products';
            $metaParts = [];
            if ($leadItem->source) $metaParts[] = 'Source: ' . $leadItem->source;
            if ($leadItem->assignee) $metaParts[] = 'Assigned: ' . $leadItem->assignee->name;
            if ($leadItem->next_follow_up_at) {
                $metaParts[] = 'Next: ' . \Carbon\Carbon::parse($leadItem->next_follow_up_at)->format('d M Y, H:i');
            }

            // Include latest follow-up note (reminder) in the "Lead created / Follow-up created" body if available
            $latestReminderNote = null;
            if ($leadItem->relationLoaded('activities')) {
                $latestReminder = $leadItem->activities
                    ->where('type', 'reminder')
                    ->sortByDesc('created_at')
                    ->first();
            } else {
                $latestReminder = $leadItem->activities()
                    ->where('type', 'reminder')
                    ->latest()
                    ->first();
            }
            if ($latestReminder && $latestReminder->description) {
                $latestReminderNote = $latestReminder->description;
            }

            $leadCreatedBody = $products;
            if ($latestReminderNote) {
                $leadCreatedBody .= "\n" . $latestReminderNote;
            }

            // Single "Lead Created" entry (includes next follow-up + latest follow-up note if set at creation)
            $timeline->push([
                'id' => 'lead-created-' . $leadItem->id,
                'type' => 'lead_created',
                'title' => $stageLabel . ' created',
                'body' => $leadCreatedBody,
                'when' => $leadItem->created_at->diffForHumans(),
                'created_at' => $leadItem->created_at->toIso8601String(),
                'meta' => implode(' · ', $metaParts),
            ]);

            // Add lead activities (skip redundant stage_change and reminder at creation)
            $leadCreatedAt = \Carbon\Carbon::parse($leadItem->created_at);
            foreach ($leadItem->activities()->with('user')->latest()->get() as $activity) {
                // Skip legacy 'followup' type created at the same moment as the lead (already represented by lead_created)
                if ($activity->type === 'followup') {
                    $activityCreatedAt = \Carbon\Carbon::parse($activity->created_at);
                    if ($activityCreatedAt->diffInMinutes($leadCreatedAt) <= 2) {
                        continue;
                    }
                }

                // Skip stage_change that happens at lead creation
                if ($activity->type === 'stage_change') {
                    $meta = is_array($activity->meta) ? $activity->meta : (json_decode($activity->meta, true) ?? []);
                    // Only show stage changes that have both from_stage and to_stage (actual changes, not creation)
                    if (!isset($meta['from_stage']) || !isset($meta['to_stage'])) {
                        continue; // Skip lead creation stage entries
                    }
                    
                    // Format stage change nicely
                    $stageLabels = [
                        'follow_up' => 'Follow-up',
                        'lead' => 'Lead',
                        'hot_lead' => 'Hot Lead',
                        'quotation' => 'Quotation',
                        'won' => 'Won',
                        'lost' => 'Lost',
                    ];
                    $fromStage = $stageLabels[$meta['from_stage']] ?? ucfirst($meta['from_stage']);
                    $toStage = $stageLabels[$meta['to_stage']] ?? ucfirst($meta['to_stage']);
                    
                    $timeline->push([
                        'id' => 'activity-' . $activity->id,
                        'type' => 'stage_change',
                        'title' => '🔄 Stage Changed',
                        'body' => 'Moved from ' . $fromStage . ' → ' . $toStage,
                        'when' => $activity->created_at->diffForHumans(),
                        'created_at' => $activity->created_at->toIso8601String(),
                        'meta' => 'By: ' . ($activity->user->name ?? 'Unknown') . ' | Lead #' . $leadItem->id,
                    ]);
                    continue;
                }
                
                $activityIcons = [
                    'call' => '📞 Call',
                    'meeting' => '🤝 Meeting',
                    'appointment' => '📅 Appointment',
                    'email' => '📧 Email',
                    'visit' => '🏢 Site Visit',
                    'whatsapp' => '💬 WhatsApp',
                    'sms' => '📱 SMS',
                    'quote_sent' => '📄 Quote Sent',
                    'note' => '📝 Note',
                    'reminder' => '⏰ Reminder',
                    'followup' => '📅 Follow-up Scheduled',
                    'other' => '📋 Activity',
                ];
                $activityLabel = $activityIcons[$activity->type] ?? ucfirst(str_replace('_', ' ', $activity->type));
                
                // Parse meta for outcome
                $meta = is_array($activity->meta) ? $activity->meta : (json_decode($activity->meta, true) ?? []);
                $outcome = $meta['outcome'] ?? null;
                $outcomeLabels = [
                    'positive' => '✅ Positive',
                    'neutral' => '➖ Neutral',
                    'negative' => '❌ Negative',
                    'no_answer' => '📵 No Answer',
                ];
                $outcomeLabel = $outcome ? ($outcomeLabels[$outcome] ?? ucfirst($outcome)) : null;
                
                $metaText = 'By: ' . ($activity->user->name ?? 'Unknown');
                if ($outcomeLabel) {
                    $metaText .= ' | Outcome: ' . $outcomeLabel;
                }
                if (isset($meta['duration']) && $meta['duration']) {
                    $metaText .= ' | Duration: ' . $meta['duration'] . ' min';
                }
                
                // Determine timeline type based on activity type
                $timelineType = 'activity';
                if (in_array($activity->type, ['call', 'meeting', 'visit', 'appointment'])) {
                    $timelineType = $activity->type;
                } elseif ($activity->type === 'reminder' || $activity->type === 'followup') {
                    $timelineType = 'reminder';
                } elseif ($activity->type === 'note') {
                    $timelineType = 'note';
                }
                
                $timeline->push([
                    'id' => 'activity-' . $activity->id,
                    'type' => $timelineType,
                    'title' => $activityLabel . ' (Lead #' . $leadItem->id . ')',
                    'body' => $activity->description,
                    'when' => $activity->created_at->diffForHumans(),
                    'created_at' => $activity->created_at->toIso8601String(),
                    'meta' => $metaText,
                ]);

                // Collect appointments for the Appointments section (like follow-ups)
                if ($activity->type === 'appointment') {
                    $actMeta = is_array($activity->meta) ? $activity->meta : (json_decode($activity->meta, true) ?? []);
                    $appointments->push([
                        'id' => $activity->id,
                        'lead_id' => $leadItem->id,
                        'description' => $activity->description ?: 'Appointment',
                        'appointment_date' => $actMeta['appointment_date'] ?? null,
                        'appointment_time' => $actMeta['appointment_time'] ?? '10:00',
                        'appointment_status' => $activity->appointment_status ?? 'pending',
                        'outcome_notes' => $activity->outcome_notes,
                        'assignee' => $activity->assignee,
                        'created_at' => $activity->created_at->toIso8601String(),
                    ]);
                }
            }

            // Add won/lost product events
            foreach ($leadItem->items as $item) {
                if ($item->status === 'won' && $item->closed_at) {
                    $timeline->push([
                        'id' => 'item-won-' . $item->id,
                        'type' => 'won',
                        'title' => '🎉 Product Won',
                        'body' => $item->product->name . ' - Qty: ' . $item->quantity . ' × £' . number_format($item->unit_price, 2) . ' = £' . number_format($item->total_price, 2),
                        'when' => \Carbon\Carbon::parse($item->closed_at)->diffForHumans(),
                        'created_at' => $item->closed_at,
                        'meta' => 'Lead #' . $leadItem->id,
                    ]);
                } elseif ($item->status === 'lost' && $item->closed_at) {
                    $timeline->push([
                        'id' => 'item-lost-' . $item->id,
                        'type' => 'lost',
                        'title' => '❌ Product Lost',
                        'body' => $item->product->name,
                        'when' => \Carbon\Carbon::parse($item->closed_at)->diffForHumans(),
                        'created_at' => $item->closed_at,
                        'meta' => 'Lead #' . $leadItem->id,
                    ]);
                }
            }
        }

        // Add tickets
        foreach ($tickets as $ticket) {
            $timeline->push([
                'id' => 'ticket-' . $ticket->id,
                'type' => 'ticket',
                'title' => '🎫 Ticket: ' . $ticket->subject,
                'body' => $ticket->description,
                'when' => $ticket->created_at->diffForHumans(),
                'created_at' => $ticket->created_at->toIso8601String(),
                'meta' => 'Priority: ' . ucfirst($ticket->priority) . ' | Status: ' . ucfirst(str_replace('_', ' ', $ticket->status)),
            ]);
        }

        // Sort by created_at timestamp (newest first)
        $timeline = $timeline->sortByDesc(function ($item) {
            return $item['created_at'];
        })->values();

        // Sort appointments by date then time (upcoming first)
        $appointments = $appointments->sort(function ($a, $b) {
            $dateA = $a['appointment_date'] ?? '';
            $dateB = $b['appointment_date'] ?? '';
            if ($dateA !== $dateB) {
                return strcmp($dateA, $dateB);
            }
            return strcmp($a['appointment_time'] ?? '00:00', $b['appointment_time'] ?? '00:00');
        })->values();

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
        if ($isSalesAgent) {
            $hasAccess = $customer->created_by === $user->id
                || $customer->isAssignedTo($user->id)
                || $customer->assignments()->where('assigned_by', $user->id)->exists()
                || $customer->leads()->where('assigned_to', $user->id)->exists();
            if (!$hasAccess) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $comms = \App\Modules\Communication\Models\Communication::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($c) {
                return [
                    'id' => 'comm-' . $c->id,
                    'channel' => $c->channel,
                    'subject' => $c->provider_payload['subject'] ?? null,
                    'message' => $c->message,
                    'status' => $c->status,
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


