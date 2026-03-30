<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\CRM\Models\LeadAssignmentLog;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\CRM\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');

        $query = Lead::with(['customer', 'assignee', 'activities', 'convertedFromActivity', 'product', 'items.product']);

        // "Leads I assigned" filter: leads that current user assigned to someone else
        if ($request->boolean('assigned_by_me')) {
            $query->whereHas('assignmentLogs', fn ($q) => $q->where('assigned_by', $user->id));
        } elseif ($isSalesAgent) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhereHas('customer.assignedUsers', function ($subQuery) use ($user) {
                        $subQuery->where('user_id', $user->id);
                    });
            });
        } elseif ($request->has('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->has('stage')) {
            $query->where('stage', $request->stage);
        }

        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        // Optional date filters (created_at)
        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $leads = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return response()->json($leads);
    }

    public function show($id)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');

        $lead = Lead::with(['customer', 'assignee', 'activities.user', 'communications', 'convertedFromActivity', 'product', 'items.product', 'assignmentLogs.assignedByUser', 'assignmentLogs.newAssignee'])
            ->findOrFail($id);

        // Check access for sales agents: current assignee, customer assigned to them, or they assigned this lead to someone (so they can see logs/actions)
        if ($isSalesAgent) {
            $hasAccess = $lead->assigned_to === $user->id
                || $lead->customer->isAssignedTo($user->id)
                || $lead->assignmentLogs()->where('assigned_by', $user->id)->exists();

            if (!$hasAccess) {
                return response()->json(['message' => 'Unauthorized access to this lead'], 403);
            }
        }

        return response()->json($lead);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'product_id' => ['nullable', 'exists:products,id'], // Single product (legacy)
            'product_ids' => ['nullable', 'array'], // Multiple products
            'product_ids.*' => ['exists:products,id'],
            'stage' => ['required', 'in:follow_up,lead,hot_lead,quotation,won,lost'],
            'source' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'pipeline_value' => ['nullable', 'numeric', 'min:0'],
            'lost_reason' => ['nullable', 'string'],
            'next_follow_up_at' => ['nullable', 'date'],
            'expected_closing_date' => ['nullable', 'date'],
            'comment' => ['nullable', 'string'], // For initial comment when creating lead
        ]);

        // Get product IDs - support both single and multiple
        $productIds = [];
        if (!empty($data['product_ids'])) {
            $productIds = $data['product_ids'];
        } elseif (!empty($data['product_id'])) {
            $productIds = [$data['product_id']];
        }

        if (empty($productIds)) {
            return response()->json(['message' => 'At least one product is required.'], 422);
        }

        // Ensure creator sees the lead: default assigned_to to current user if not provided
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        if ($isSalesAgent) {
            $customer = \App\Modules\CRM\Models\Customer::findOrFail($data['customer_id']);
            $hasAccess = $customer->created_by === $user->id || 
                        $customer->isAssignedTo($user->id) ||
                        $customer->leads()->where('assigned_to', $user->id)->exists();
            
            if (!$hasAccess) {
                return response()->json([
                    'message' => 'You do not have access to create leads for this customer. Please request assignment first.',
                ], 403);
            }
            $data['assigned_to'] = $data['assigned_to'] ?? $user->id;
        } elseif (empty($data['assigned_to'])) {
            $data['assigned_to'] = $user->id;
        }

        // Set primary product_id (first in list)
        $data['product_id'] = $productIds[0];
        unset($data['product_ids']); // Remove array from lead data

        $lead = Lead::create($data);

        // Create items for all products with pending status
        foreach ($productIds as $productId) {
            LeadItem::create([
                'lead_id' => $lead->id,
                'product_id' => $productId,
                'quantity' => 1,
                'unit_price' => 0,
                'status' => LeadItem::STATUS_PENDING,
            ]);
        }

        // Add initial note/comment if provided (no need to log "lead created" activity - we display that from lead's created_at)
        if (!empty($data['comment'])) {
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => $data['comment'],
            ]);
        }

        // Log initial assignment so assigner can see it in "Leads I assigned"
        if ($lead->assigned_to) {
            LeadAssignmentLog::create([
                'lead_id' => $lead->id,
                'previous_assigned_to' => null,
                'new_assigned_to' => $lead->assigned_to,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ]);
            event(new \App\Events\NewLeadAssigned($lead));
        }

        return response()->json($lead->load(['customer', 'assignee', 'product', 'items.product']), 201);
    }

    public function update(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);
        $oldStage = $lead->stage;

        $data = $request->validate([
            'product_id' => ['sometimes', 'exists:products,id'], // Optional for updates (can change product)
            'stage' => ['sometimes', 'in:follow_up,lead,hot_lead,quotation,won,lost'],
            'source' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'pipeline_value' => ['nullable', 'numeric', 'min:0'],
            'lost_reason' => ['required_if:stage,lost', 'string'],
            'next_follow_up_at' => ['nullable', 'date'],
            'expected_closing_date' => ['nullable', 'date'],
        ]);

        // If changing to "won", require items and update pipeline_value
        if (isset($data['stage']) && $data['stage'] === 'won' && $oldStage !== 'won') {
            $itemsCount = $lead->items()->count();
            if ($itemsCount === 0) {
                return response()->json([
                    'message' => 'Cannot close deal without items. Please add items first.',
                    'errors' => ['items' => ['At least one item is required when closing a deal.']]
                ], 422);
            }
            
            // Calculate pipeline_value from items total
            $totalValue = $lead->items()->sum('total_price');
            $data['pipeline_value'] = $totalValue;
        }

        // If changing to "lost", require lost_reason
        if (isset($data['stage']) && $data['stage'] === 'lost' && empty($data['lost_reason'])) {
            return response()->json([
                'message' => 'Lost reason is required when marking a lead as lost.',
                'errors' => ['lost_reason' => ['Lost reason is required.']]
            ], 422);
        }

        $previousAssignedTo = $lead->assigned_to;
        $lead->update($data);

        // Keep customer type in sync (prospect ↔ customer) when lead stage changes to/from won
        if (isset($data['stage'])) {
            $lead->customer?->syncTypeFromLeads();
        }

        // Log assignment change so assigner and assignee can see history
        if (isset($data['assigned_to']) && $data['assigned_to'] != $previousAssignedTo) {
            LeadAssignmentLog::create([
                'lead_id' => $lead->id,
                'previous_assigned_to' => $previousAssignedTo,
                'new_assigned_to' => $data['assigned_to'],
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ]);
            event(new \App\Events\NewLeadAssigned($lead->fresh()));
        }

        // Log stage change
        if (isset($data['stage']) && $data['stage'] !== $oldStage) {
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'stage_change',
                'description' => "Stage changed from {$oldStage} to {$data['stage']}",
                'meta' => ['from_stage' => $oldStage, 'to_stage' => $data['stage']],
            ]);
        }

        return response()->json($lead->load(['customer', 'assignee', 'assignmentLogs.assignedByUser', 'assignmentLogs.newAssignee', 'product', 'items.product']));
    }

    public function addActivity(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        $data = $request->validate([
            'type' => ['required', 'string', 'in:note,call,meeting,email,visit,whatsapp,sms,quote_sent,stage_change,appointment,other'],
            'description' => ['required', 'string', 'max:5000'],
            'remind_at' => ['nullable', 'date'],
            'meta' => ['nullable', 'array'],
            'meta.activity_at' => ['nullable', 'date'],
            'meta.duration' => ['nullable', 'integer'],
            'meta.outcome' => ['nullable', 'string'],
            'meta.followup_reason' => ['nullable', 'string'],
            'meta.appointment_date' => ['nullable', 'date'],
            'meta.appointment_time' => ['nullable', 'string'],
            'assigned_user_id' => ['nullable', 'exists:users,id'], // For appointment: who will attend
        ]);

        $activityData = [
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => $data['type'],
            'description' => $data['description'],
            'remind_at' => $data['remind_at'] ?? null,
            'meta' => $data['meta'] ?? null,
        ];
        if ($data['type'] === 'appointment' && !empty($data['assigned_user_id'])) {
            $activityData['assigned_user_id'] = $data['assigned_user_id'];
        }
        $activity = LeadActivity::create($activityData);

        // Assign lead to current user if unassigned - so it shows on their dashboard
        if (empty($lead->assigned_to)) {
            $lead->update(['assigned_to' => auth()->id()]);
        }

        // Send appointment emails if this is an appointment
        if ($data['type'] === 'appointment' && isset($data['meta']['appointment_date'])) {
            $this->sendAppointmentEmails($lead, $data, $activity);
        }

        return response()->json($activity->load(['user', 'assignee']), 201);
    }

    /**
     * Send appointment notification emails: customer (if has email), admin, and assigned user.
     * Uses SMTP and admin email from Settings page.
     */
    private function sendAppointmentEmails(Lead $lead, array $data, LeadActivity $activity)
    {
        \App\Services\MailConfigFromDatabase::apply();

        $lead->load('customer');
        $appointmentDate = \Carbon\Carbon::parse($data['meta']['appointment_date'])->format('l, d F Y');
        $rawTime = $data['meta']['appointment_time'] ?? '10:00';
        $appointmentTime = \Carbon\Carbon::parse('2000-01-01 ' . $rawTime)->format('g:i A');
        $notes = $data['description'] ?? '';

        $adminEmail = trim(\App\Modules\Settings\Models\Setting::where('key', 'admin_notification_email')->first()?->value ?? '');

        // Send to customer/prospect only if they have an email (no notes — notes go to employees only)
        if ($lead->customer && $lead->customer->email) {
            try {
                \Illuminate\Support\Facades\Mail::to($lead->customer->email)
                    ->send(new \App\Mail\AppointmentNotification($lead, $appointmentDate, $appointmentTime, '', 'customer'));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send appointment email to customer: ' . $e->getMessage());
            }
        }

        // Send to admin
        if ($adminEmail) {
            try {
                \Illuminate\Support\Facades\Mail::to($adminEmail)
                    ->send(new \App\Mail\AppointmentNotification($lead, $appointmentDate, $appointmentTime, $notes, 'admin'));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send appointment email to admin: ' . $e->getMessage());
            }
        }

        // Send to assigned user (who will attend) so they know to go at that time
        if ($activity->assigned_user_id) {
            $assignee = $activity->assignee;
            if ($assignee && $assignee->email) {
                try {
                    \Illuminate\Support\Facades\Mail::to($assignee->email)
                        ->send(new \App\Mail\AppointmentAssignedNotification($activity));
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to send appointment email to assignee: ' . $e->getMessage());
                }
            }
        }
    }

    public function setFollowUp(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        $data = $request->validate([
            'next_follow_up_at' => ['required', 'date'],
            'comment' => ['nullable', 'string'],
        ]);

        // Convert datetime-local format to proper datetime
        $followUpDate = \Carbon\Carbon::parse($data['next_follow_up_at']);

        $lead->update(['next_follow_up_at' => $followUpDate]);

        $description = 'Follow-up scheduled for ' . $followUpDate->format('d/m/Y H:i');
        if (!empty($data['comment'])) {
            $description .= ' - ' . $data['comment'];
        }

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'reminder',
            'description' => $description,
            'remind_at' => $followUpDate,
        ]);

        return response()->json($lead->fresh()->load(['customer', 'assignee']));
    }

    public function createFollowUpOrLead(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'type' => ['required', 'in:follow_up,lead'],
            'comment' => ['required', 'string'],
            'follow_up_at' => ['required_if:type,follow_up', 'date'],
            'expected_closing_date' => ['nullable', 'date'],
            'stage' => ['required_if:type,lead', 'in:follow_up,lead,hot_lead,quotation'],
            'product_id' => ['nullable', 'exists:products,id'], // Single product (legacy)
            'product_ids' => ['nullable', 'array'], // Multiple products
            'product_ids.*' => ['exists:products,id'],
            'source' => ['nullable', 'string'],
            'pipeline_value' => ['nullable', 'numeric', 'min:0'],
            'lead_id' => ['nullable', 'exists:leads,id'], // For selecting which lead to add follow-up to
            'converted_from_activity_id' => ['nullable', 'exists:lead_activities,id'], // Track which follow-up was converted
        ]);

        // Get product IDs - support both single and multiple
        $productIds = [];
        if (!empty($data['product_ids'])) {
            $productIds = $data['product_ids'];
        } elseif (!empty($data['product_id'])) {
            $productIds = [$data['product_id']];
        }

        if (empty($productIds)) {
            return response()->json(['message' => 'At least one product is required.'], 422);
        }

        $customer = \App\Modules\CRM\Models\Customer::findOrFail($data['customer_id']);

        if ($data['type'] === 'follow_up') {
            // Determine which lead to add follow-up to
            $targetLead = null;
            
            if (!empty($data['lead_id'])) {
                // Use specified lead (must belong to customer) — "follow up this lead"
                $targetLead = $customer->leads()->find($data['lead_id']);
                if (!$targetLead) {
                    return response()->json(['message' => 'Selected lead does not belong to this customer.'], 422);
                }
            } else {
                // No lead selected = first call / simple follow-up — create a new lead
                $targetLead = null;
            }

            // If no lead exists, CREATE a new lead with stage "follow_up"
            if (!$targetLead) {
                $followUpDate = \Carbon\Carbon::parse($data['follow_up_at']);
                
                $targetLead = Lead::create([
                    'customer_id' => $data['customer_id'],
                    'product_id' => $productIds[0], // Primary product
                    'stage' => 'follow_up',
                    'next_follow_up_at' => $followUpDate,
                    'assigned_to' => auth()->id(), // Assign to current user so it shows on their dashboard
                ]);

                // Create items for all products
                foreach ($productIds as $productId) {
                    LeadItem::create([
                        'lead_id' => $targetLead->id,
                        'product_id' => $productId,
                        'quantity' => 1,
                        'unit_price' => 0,
                        'status' => LeadItem::STATUS_PENDING,
                    ]);
                }

                // Log initial creation
                $productNames = Product::whereIn('id', $productIds)->pluck('name')->join(', ');
                LeadActivity::create([
                    'lead_id' => $targetLead->id,
                    'user_id' => auth()->id(),
                    'type' => 'stage_change',
                    'description' => 'Lead created in follow_up stage with products: ' . $productNames,
                    'meta' => ['stage' => 'follow_up', 'product_ids' => $productIds],
                ]);

                // Add the follow-up activity (include date/time + notes in description)
                $followUpDescription = 'Follow-up scheduled for ' . $followUpDate->format('d/m/Y H:i');
                if (!empty($data['comment'])) {
                    $followUpDescription .= ' - ' . $data['comment'];
                }

                LeadActivity::create([
                    'lead_id' => $targetLead->id,
                    'user_id' => auth()->id(),
                    'type' => 'reminder',
                    'description' => $followUpDescription,
                    'remind_at' => $followUpDate,
                ]);

                return response()->json($targetLead->fresh()->load(['customer', 'assignee', 'activities', 'items.product']), 201);
            }

            // Add new products to existing lead if not already present
            $existingProductIds = $targetLead->items()->pluck('product_id')->toArray();
            foreach ($productIds as $productId) {
                if (!in_array($productId, $existingProductIds)) {
                    LeadItem::create([
                        'lead_id' => $targetLead->id,
                        'product_id' => $productId,
                        'quantity' => 1,
                        'unit_price' => 0,
                        'status' => LeadItem::STATUS_PENDING,
                    ]);
                }
            }

            $followUpDate = \Carbon\Carbon::parse($data['follow_up_at']);
            $updateData = ['next_follow_up_at' => $followUpDate];
            // Ensure lead is assigned so it shows on dashboard when user adds follow-up
            if (empty($targetLead->assigned_to)) {
                $updateData['assigned_to'] = auth()->id();
            }
            $targetLead->update($updateData);

            // Add follow-up activity for existing lead (include date/time + notes)
            $followUpDescription = 'Follow-up scheduled for ' . $followUpDate->format('d/m/Y H:i');
            if (!empty($data['comment'])) {
                $followUpDescription .= ' - ' . $data['comment'];
            }

            LeadActivity::create([
                'lead_id' => $targetLead->id,
                'user_id' => auth()->id(),
                'type' => 'reminder',
                'description' => $followUpDescription,
                'remind_at' => $followUpDate,
            ]);

            return response()->json($targetLead->fresh()->load(['customer', 'assignee', 'activities', 'items.product']), 200);
        } else {
            // Create new lead with multiple products; assign to creator so it shows on their board
            $lead = Lead::create([
                'customer_id' => $data['customer_id'],
                'product_id' => $productIds[0], // Primary product
                'converted_from_activity_id' => $data['converted_from_activity_id'] ?? null,
                'stage' => $data['stage'],
                'source' => $data['source'] ?? null,
                'pipeline_value' => $data['pipeline_value'] ?? 0,
                'expected_closing_date' => $data['expected_closing_date'] ? \Carbon\Carbon::parse($data['expected_closing_date']) : null,
                'assigned_to' => auth()->id(),
            ]);

            // Create items for all products
            foreach ($productIds as $productId) {
                LeadItem::create([
                    'lead_id' => $lead->id,
                    'product_id' => $productId,
                    'quantity' => 1,
                    'unit_price' => 0,
                    'status' => LeadItem::STATUS_PENDING,
                ]);
            }

            // If this lead was converted from a follow-up activity, mark that activity
            if (!empty($data['converted_from_activity_id'])) {
                LeadActivity::where('id', $data['converted_from_activity_id'])
                    ->update(['converted_to_lead_id' => $lead->id]);
            }

            $productNames = Product::whereIn('id', $productIds)->pluck('name')->join(', ');
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'stage_change',
                'description' => 'Lead created in ' . $lead->stage . ' stage with products: ' . $productNames . 
                    (!empty($data['converted_from_activity_id']) ? ' (converted from follow-up)' : ''),
                'meta' => ['stage' => $lead->stage, 'product_ids' => $productIds],
            ]);

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'note',
                'description' => $data['comment'],
            ]);

            LeadAssignmentLog::create([
                'lead_id' => $lead->id,
                'previous_assigned_to' => null,
                'new_assigned_to' => $lead->assigned_to,
                'assigned_by' => auth()->id(),
                'assigned_at' => now(),
            ]);
            event(new \App\Events\NewLeadAssigned($lead));

            return response()->json($lead->load(['customer', 'assignee', 'convertedFromActivity', 'items.product']), 201);
        }
    }

    public function destroy($id)
    {
        $lead = Lead::findOrFail($id);
        $lead->delete();

        return response()->noContent();
    }

    public function pipeline(Request $request)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        $stages = ['follow_up', 'lead', 'hot_lead', 'quotation', 'won', 'lost'];
        $pipeline = [];

        $query = Lead::with([
            'customer',
            'assignee',
            'product',
            'items.product',
            'activities' => function ($q) {
                $q->where('type', 'appointment')
                    ->orderBy('created_at', 'desc');
            },
        ]);

        // Date filters - available to all users
        if ($request->has('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->has('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        // "Leads I assigned" filter: leads where current user assigned them
        if ($request->boolean('assigned_by_me')) {
            $query->whereHas('assignmentLogs', function ($q) use ($user) {
                $q->where('assigned_by', $user->id);
            });
        }
        // For sales agents: filter leads where assigned_to OR customer is assigned to agent
        elseif ($isSalesAgent) {
            $query->where(function ($q) use ($user) {
                // Leads directly assigned to agent
                $q->where('assigned_to', $user->id)
                // OR leads for customers assigned to agent
                ->orWhereHas('customer.assignedUsers', function ($subQuery) use ($user) {
                    $subQuery->where('user_id', $user->id);
                });
            });
        } elseif ($request->has('assigned_to')) {
            // For admin/manager: filter by assigned_to if provided
            $query->where('assigned_to', $request->assigned_to);
        }

        $allLeads = (clone $query)
            ->orderBy('created_at', 'desc')
            ->get();

        // One card per customer: show won/lost in Won/Lost only; hide parallel open leads for same contact.
        $visibleIds = array_fill_keys(
            $allLeads->groupBy('customer_id')
                ->map(fn (Collection $leads) => $this->pickRepresentativeLeadIdForPipeline($leads))
                ->values()
                ->all(),
            true,
        );

        foreach ($stages as $stage) {
            $pipeline[$stage] = $allLeads
                ->where('stage', $stage)
                ->filter(fn ($lead) => isset($visibleIds[$lead->id]))
                ->values();
        }

        return response()->json($pipeline);
    }

    /**
     * Choose a single lead per customer for the pipeline board.
     * Priority: any won (newest) → any lost (newest) → furthest active stage, then newest updated.
     */
    protected function pickRepresentativeLeadIdForPipeline(Collection $leads): int
    {
        $stageRank = [
            'follow_up' => 1,
            'lead' => 2,
            'hot_lead' => 3,
            'quotation' => 4,
            'lost' => 10,
            'won' => 11,
        ];

        $won = $leads->filter(fn ($l) => $l->stage === 'won')->sortByDesc('updated_at')->first();
        if ($won) {
            return (int) $won->id;
        }

        $lost = $leads->filter(fn ($l) => $l->stage === 'lost')->sortByDesc('updated_at')->first();
        if ($lost) {
            return (int) $lost->id;
        }

        $picked = $leads->sort(function ($a, $b) use ($stageRank) {
            $ra = $stageRank[$a->stage] ?? 0;
            $rb = $stageRank[$b->stage] ?? 0;
            if ($ra !== $rb) {
                return $rb <=> $ra;
            }

            return $b->updated_at <=> $a->updated_at;
        })->first();

        return (int) $picked->id;
    }

    /**
     * Get all leads for a customer (for selecting which lead to add follow-up to)
     */
    public function getCustomerLeads($customerId)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        $customer = \App\Modules\CRM\Models\Customer::findOrFail($customerId);
        
        // Check access for sales agents
        if ($isSalesAgent) {
            $hasAccess = $customer->isAssignedTo($user->id) || 
                        $customer->leads()->where('assigned_to', $user->id)->exists();
            
            if (!$hasAccess) {
                return response()->json(['message' => 'Unauthorized access to this customer'], 403);
            }
        }
        
        $query = $customer->leads()
            ->with(['assignee', 'product', 'items.product']);
        
        // For sales agents, only show their leads
        if ($isSalesAgent) {
            $query->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('customer.assignedUsers', function ($subQuery) use ($user) {
                      $subQuery->where('user_id', $user->id);
                  });
            });
        }
        
        $leads = $query->orderBy('created_at', 'desc')->get();

        return response()->json($leads);
    }

    /**
     * Get follow-up activities for a customer that can be converted to leads
     */
    public function getConvertibleFollowUps($customerId)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        $customer = \App\Modules\CRM\Models\Customer::findOrFail($customerId);
        
        // Check access for sales agents
        if ($isSalesAgent) {
            $hasAccess = $customer->isAssignedTo($user->id) || 
                        $customer->leads()->where('assigned_to', $user->id)->exists();
            
            if (!$hasAccess) {
                return response()->json(['message' => 'Unauthorized access to this customer'], 403);
            }
        }
        
        // Get all follow-up activities (type='reminder') that haven't been converted yet
        $query = LeadActivity::whereHas('lead', function ($q) use ($customerId, $user, $isSalesAgent) {
                $q->where('customer_id', $customerId);
                
                // For sales agents, filter by their leads only
                if ($isSalesAgent) {
                    $q->where(function ($subQ) use ($user) {
                        $subQ->where('assigned_to', $user->id)
                            ->orWhereHas('customer.assignedUsers', function ($subSubQ) use ($user) {
                                $subSubQ->where('user_id', $user->id);
                            });
                    });
                }
            })
            ->where('type', 'reminder')
            ->whereNull('converted_to_lead_id')
            ->with(['lead', 'user'])
            ->orderBy('remind_at', 'desc');
        
        $followUps = $query->get();

        return response()->json($followUps);
    }

    /**
     * Add items to a lead (when closing deal)
     */
    public function addItems(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.notes' => ['nullable', 'string'],
        ]);

        // Delete existing items if any (to allow re-adding)
        $lead->items()->delete();

        foreach ($data['items'] as $itemData) {
            LeadItem::create([
                'lead_id' => $lead->id,
                'product_id' => $itemData['product_id'],
                'quantity' => $itemData['quantity'],
                'unit_price' => $itemData['unit_price'],
                'notes' => $itemData['notes'] ?? null,
                'status' => LeadItem::STATUS_PENDING,
            ]);
        }

        // Calculate and update pipeline_value from total of all items
        $totalValue = $lead->items()->sum('total_price');
        $lead->update(['pipeline_value' => $totalValue]);

        return response()->json($lead->fresh()->load(['items.product', 'customer', 'assignee', 'product']), 201);
    }

    /**
     * Close an individual item (mark as won or lost)
     */
    public function closeItem(Request $request, $leadId, $itemId)
    {
        $lead = Lead::findOrFail($leadId);
        $item = $lead->items()->findOrFail($itemId);

        $data = $request->validate([
            'status' => ['required', 'in:won,lost'],
            'quantity' => ['required_if:status,won', 'integer', 'min:1'],
            'unit_price' => ['required_if:status,won', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'lost_reason' => ['required_if:status,lost', 'string'],
        ]);

        $item->status = $data['status'];
        $item->closed_at = now();
        
        if ($data['status'] === 'won') {
            $item->quantity = $data['quantity'];
            $item->unit_price = $data['unit_price'];
        }
        
        if (!empty($data['notes'])) {
            $item->notes = $data['notes'];
        }
        
        $item->save();

        // Log activity
        $productName = $item->product->name ?? 'Unknown';
        if ($data['status'] === 'won') {
            $description = "Product '{$productName}' closed as WON - Qty: {$data['quantity']}, Price: £{$data['unit_price']}";
        } else {
            $description = "Product '{$productName}' closed as LOST - Reason: " . ($data['lost_reason'] ?? 'Not specified');
        }
        
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'item_closed',
            'description' => $description,
            'meta' => [
                'item_id' => $item->id,
                'product_id' => $item->product_id,
                'status' => $data['status'],
            ],
        ]);

        // Auto-update lead stage based on items
        $lead->updateStageFromItems();
        $lead->customer?->syncTypeFromLeads();

        // Recalculate pipeline value
        $totalValue = $lead->wonItems()->sum('total_price');
        $lead->update(['pipeline_value' => $totalValue]);

        return response()->json($lead->fresh()->load(['items.product', 'customer', 'assignee', 'product', 'activities']));
    }

    /**
     * Update item details (quantity, price) before closing
     */
    public function updateItem(Request $request, $leadId, $itemId)
    {
        $lead = Lead::findOrFail($leadId);
        $item = $lead->items()->findOrFail($itemId);

        $data = $request->validate([
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'unit_price' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $item->update($data);

        return response()->json($item->fresh()->load('product'));
    }

    /**
     * Complete a follow-up
     */
    public function completeFollowUp(Request $request, $id)
    {
        $user = auth()->user();
        $isSalesAgent = $user->isRole('Sales') || $user->isRole('CallAgent');
        
        $lead = Lead::findOrFail($id);
        
        // Check access for sales agents
        if ($isSalesAgent) {
            $hasAccess = $lead->assigned_to === $user->id || 
                        $lead->customer->isAssignedTo($user->id);
            
            if (!$hasAccess) {
                return response()->json(['message' => 'Unauthorized access to this lead'], 403);
            }
        }

        $data = $request->validate([
            'remarks' => ['required', 'string', 'max:1000'],
            'sale_happened' => ['nullable', 'boolean'],
            'new_stage' => ['nullable', 'in:follow_up,lead,hot_lead,quotation,won,lost'],
        ]);

        // Update follow-up date if needed
        if ($request->has('next_follow_up_at')) {
            $lead->update(['next_follow_up_at' => $request->input('next_follow_up_at')]);
        } else {
            // Clear next follow-up if completed
            $lead->update(['next_follow_up_at' => null]);
        }

        // Update stage if sale happened
        if ($data['sale_happened'] && isset($data['new_stage'])) {
            $lead->update(['stage' => $data['new_stage']]);
            $lead->customer?->syncTypeFromLeads();

            // If lead is now WON, auto-mark any pending items as WON so that
            // sales/revenue and "What customer has" stay in sync even when closing directly here.
            if ($data['new_stage'] === 'won') {
                $pendingItems = $lead->items()->where('status', \App\Modules\CRM\Models\LeadItem::STATUS_PENDING)->get();
                foreach ($pendingItems as $item) {
                    $item->status = \App\Modules\CRM\Models\LeadItem::STATUS_WON;
                    $item->closed_at = now();
                    // Keep existing quantity/unit_price if already set; otherwise default to 1 / 0
                    $item->quantity = $item->quantity ?: 1;
                    $item->unit_price = $item->unit_price ?: 0;
                    $item->save();

                    // Log auto-closure of item
                    $productName = $item->product->name ?? 'Unknown';
                    LeadActivity::create([
                        'lead_id' => $lead->id,
                        'user_id' => auth()->id(),
                        'type' => 'item_closed',
                        'description' => "Product '{$productName}' auto-marked as WON when completing follow-up.",
                        'meta' => [
                            'item_id' => $item->id,
                            'product_id' => $item->product_id,
                            'status' => $item->status,
                            'auto_closed' => true,
                        ],
                    ]);
                }

                // Ensure lead stage and pipeline value reflect won items
                $lead->updateStageFromItems();
                $totalValue = $lead->wonItems()->sum('total_price');
                $lead->update(['pipeline_value' => $totalValue]);
            }
        }

        // Create activity log (guard against accidental duplicate inserts)
        $hasRecentDuplicate = LeadActivity::where('lead_id', $lead->id)
            ->where('type', 'follow_up_completed')
            ->where('description', $data['remarks'])
            ->where('created_at', '>=', now()->subMinutes(2))
            ->exists();

        if (!$hasRecentDuplicate) {
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'follow_up_completed',
                'description' => $data['remarks'],
                'meta' => [
                    'sale_happened' => $data['sale_happened'] ?? false,
                    'new_stage' => $data['new_stage'] ?? null,
                ],
            ]);
        }

        return response()->json($lead->fresh()->load(['customer', 'assignee', 'activities.user']));
    }

    /**
     * Get next products to sell for a customer based on what they already have
     */
    public function getNextProductsToSell($customerId)
    {
        $customer = \App\Modules\CRM\Models\Customer::findOrFail($customerId);
        
        // Get all products customer already has (from won leads with items)
        $wonLeads = $customer->leads()
            ->where('stage', 'won')
            ->with('items.product')
            ->get();

        $ownedProductIds = collect();
        foreach ($wonLeads as $lead) {
            foreach ($lead->items as $item) {
                $ownedProductIds->push($item->product_id);
            }
        }
        $ownedProductIds = $ownedProductIds->unique();

        // Get suggested products based on relationships
        $suggestedProducts = collect();
        foreach ($ownedProductIds as $productId) {
            $product = Product::find($productId);
            if ($product) {
                $suggestions = $product->getSuggestedProducts();
                foreach ($suggestions as $suggestion) {
                    // Only suggest if customer doesn't already have it
                    if (!$ownedProductIds->contains($suggestion->id)) {
                        $suggestedProducts->push([
                            'product' => $suggestion,
                            'suggested_by' => $product->name,
                            'relationship_type' => 'suggest',
                        ]);
                    }
                }
            }
        }

        // Remove duplicates
        $suggestedProducts = $suggestedProducts->unique(function ($item) {
            return $item['product']->id;
        })->values();

        return response()->json([
            'owned_products' => Product::whereIn('id', $ownedProductIds)->get(),
            'suggested_products' => $suggestedProducts,
        ]);
    }
}


