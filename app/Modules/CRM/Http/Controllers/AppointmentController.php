<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadItem;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /**
     * List appointments for the current user (assigned to them or created by them).
     * Query: ?date=YYYY-MM-DD for a specific date, else today.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = LeadActivity::where('type', 'appointment')
            ->where(function ($q) use ($user) {
                $q->where('assigned_user_id', $user->id)
                    ->orWhere('user_id', $user->id);
            })
            ->with(['lead.customer', 'lead.assignee', 'user', 'assignee']);

        $activities = $query->get();

        $date = $request->get('date');
        if ($date) {
            $activities = $activities->filter(function ($a) use ($date) {
                $meta = is_array($a->meta) ? $a->meta : (json_decode($a->meta, true) ?? []);
                return ($meta['appointment_date'] ?? null) === $date;
            })->values();
        }

        $activities = $activities->sortBy(function ($a) {
            $meta = is_array($a->meta) ? $a->meta : (json_decode($a->meta, true) ?? []);
            return ($meta['appointment_date'] ?? '') . ' ' . ($meta['appointment_time'] ?? '00:00');
        })->values();

        $list = $activities->map(function ($a) {
            $meta = is_array($a->meta) ? $a->meta : (json_decode($a->meta, true) ?? []);
            return [
                'id' => $a->id,
                'lead_id' => $a->lead_id,
                'customer' => $a->lead?->customer,
                'description' => $a->description,
                'appointment_date' => $meta['appointment_date'] ?? null,
                'appointment_time' => $meta['appointment_time'] ?? '10:00',
                'appointment_status' => $a->appointment_status ?? 'pending',
                'outcome_notes' => $a->outcome_notes,
                'user' => $a->user,
                'assignee' => $a->assignee,
                'created_at' => $a->created_at?->toIso8601String(),
            ];
        });

        return response()->json($list);
    }

    /**
     * Get today's appointment count for header (current user only).
     */
    public function todayCount()
    {
        $user = auth()->user();
        $todayStr = now()->toDateString();
        $activities = LeadActivity::where('type', 'appointment')
            ->where(function ($q) use ($user) {
                $q->where('assigned_user_id', $user->id)->orWhere('user_id', $user->id);
            })
            ->get();
        $count = $activities->filter(function ($a) use ($todayStr) {
            $meta = is_array($a->meta) ? $a->meta : (json_decode($a->meta, true) ?? []);
            $appDate = $meta['appointment_date'] ?? null;
            return $appDate === $todayStr;
        })->count();
        return response()->json(['count' => $count]);
    }

    /**
     * Show a single appointment. User must be assignee or creator or have lead access.
     */
    public function show($id)
    {
        $user = auth()->user();
        $activity = LeadActivity::with(['lead.customer', 'lead.assignee', 'lead.items.product', 'user', 'assignee'])
            ->where('type', 'appointment')
            ->findOrFail($id);

        $hasAccess = $activity->assigned_user_id === $user->id
            || $activity->user_id === $user->id
            || $activity->lead->assigned_to === $user->id
            || $activity->lead->customer->isAssignedTo($user->id);

        if (!$hasAccess) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $meta = is_array($activity->meta) ? $activity->meta : (json_decode($activity->meta, true) ?? []);
        return response()->json([
            'id' => $activity->id,
            'lead_id' => $activity->lead_id,
            'lead' => $activity->lead,
            'customer' => $activity->lead->customer,
            'description' => $activity->description,
            'appointment_date' => $meta['appointment_date'] ?? null,
            'appointment_time' => $meta['appointment_time'] ?? '10:00',
            'appointment_status' => $activity->appointment_status ?? 'pending',
            'outcome_notes' => $activity->outcome_notes,
            'user' => $activity->user,
            'assignee' => $activity->assignee,
            'created_at' => $activity->created_at?->toIso8601String(),
        ]);
    }

    /**
     * Update appointment: time (meta), status, outcome_notes; optionally set lead to won/lost.
     */
    public function update(Request $request, $id)
    {
        $user = auth()->user();
        $activity = LeadActivity::with('lead')->where('type', 'appointment')->findOrFail($id);

        $hasAccess = $activity->assigned_user_id === $user->id
            || $activity->user_id === $user->id
            || $activity->lead->assigned_to === $user->id
            || $activity->lead->customer->isAssignedTo($user->id);

        if (!$hasAccess) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'appointment_date' => ['nullable', 'date'],
            'appointment_time' => ['nullable', 'string', 'max:20'],
            'appointment_status' => ['nullable', 'string', 'in:pending,completed,cancelled,no_show,rescheduled'],
            'outcome_notes' => ['nullable', 'string', 'max:5000'],
            'lead_stage' => ['nullable', 'string', 'in:won,lost'],
            'lost_reason' => ['nullable', 'string', 'max:500'],
            'won_items' => ['nullable', 'array'],
            'won_items.*.lead_item_id' => ['required_with:won_items', 'integer'],
            'won_items.*.quantity' => ['required_with:won_items', 'integer', 'min:1'],
            'won_items.*.unit_price' => ['required_with:won_items', 'numeric', 'min:0'],
        ]);

        $meta = is_array($activity->meta) ? $activity->meta : (json_decode($activity->meta, true) ?? []);
        if (isset($data['appointment_date'])) {
            $meta['appointment_date'] = $data['appointment_date'];
        }
        if (isset($data['appointment_time'])) {
            $meta['appointment_time'] = $data['appointment_time'];
        }
        $activity->meta = $meta;

        if (isset($data['appointment_status'])) {
            $activity->appointment_status = $data['appointment_status'];
        }
        if (array_key_exists('outcome_notes', $data)) {
            $activity->outcome_notes = $data['outcome_notes'];
        }
        $activity->save();

        if (!empty($data['lead_stage'])) {
            $lead = $activity->lead;
            $update = ['stage' => $data['lead_stage']];
            if ($data['lead_stage'] === 'lost' && !empty($data['lost_reason'])) {
                $update['lost_reason'] = $data['lost_reason'];
            }
            $lead->update($update);
            $lead->customer?->syncTypeFromLeads();

            // When marking as Won, close selected products as won so they appear in "What Customer Has"
            if ($data['lead_stage'] === 'won' && !empty($data['won_items'])) {
                $validItemIds = $lead->items()->where('status', LeadItem::STATUS_PENDING)->pluck('id')->toArray();
                foreach ($data['won_items'] as $wi) {
                    $itemId = (int) ($wi['lead_item_id'] ?? 0);
                    if ($itemId && in_array($itemId, $validItemIds, true)) {
                        $item = LeadItem::find($itemId);
                        if ($item && $item->lead_id === $lead->id && $item->status === LeadItem::STATUS_PENDING) {
                            $qty = max(1, (int) ($wi['quantity'] ?? 1));
                            $price = max(0, (float) ($wi['unit_price'] ?? 0));
                            $item->status = LeadItem::STATUS_WON;
                            $item->quantity = $qty;
                            $item->unit_price = $price;
                            $item->closed_at = now();
                            $item->save();

                            $productName = $item->product->name ?? 'Unknown';
                            LeadActivity::create([
                                'lead_id' => $lead->id,
                                'user_id' => auth()->id(),
                                'type' => 'item_closed',
                                'description' => "Product '{$productName}' closed as WON - Qty: {$qty}, Price: £" . number_format($price, 2),
                                'meta' => ['item_id' => $item->id, 'product_id' => $item->product_id, 'status' => 'won'],
                            ]);
                        }
                    }
                }
                $lead->updateStageFromItems();
                $lead->customer?->syncTypeFromLeads();
                $totalValue = $lead->wonItems()->sum('total_price');
                $lead->update(['pipeline_value' => $totalValue]);
            }
        }

        return response()->json($activity->fresh(['lead.customer', 'lead.items.product', 'user', 'assignee']));
    }
}
