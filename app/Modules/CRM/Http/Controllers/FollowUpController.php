<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Models\Lead;
use Illuminate\Http\Request;
use Carbon\Carbon;

class FollowUpController extends Controller
{
    /**
     * List follow-ups for the current user (or all, for admins).
     * Filters:
     * - ?date=YYYY-MM-DD for a specific day
     * - ?from=YYYY-MM-DD&to=YYYY-MM-DD for a date range
     * - ?assigned_to=USER_ID (admin/manager/system admin only)
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $userRole = $user->role->name ?? null;
        $isAdmin = in_array($userRole, ['Admin', 'Manager', 'System Admin'], true);

        $leadQuery = Lead::query();

        // Non-admins: only see their own follow-ups (assigned leads or customers assigned to them)
        if (!$isAdmin) {
            $leadQuery->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                    ->orWhereHas('customer.assignedUsers', function ($subQuery) use ($user) {
                        $subQuery->where('user_id', $user->id);
                    });
            });
        }

        // Only active leads with a follow-up date
        $leadQuery
            ->whereNotIn('stage', ['won', 'lost'])
            ->whereNotNull('next_follow_up_at');

        // Date filters
        $from = $request->get('from');
        $to = $request->get('to');
        $date = $request->get('date');

        if ($from && $to) {
            $fromDate = Carbon::parse($from)->startOfDay();
            $toDate = Carbon::parse($to)->endOfDay();
            $leadQuery->whereBetween('next_follow_up_at', [$fromDate, $toDate]);
        } elseif ($date) {
            $leadQuery->whereDate('next_follow_up_at', Carbon::parse($date)->toDateString());
        }

        // Admins can filter by assignee
        if ($isAdmin && $request->filled('assigned_to')) {
            $leadQuery->where('assigned_to', $request->assigned_to);
        }

        $leads = $leadQuery
            ->with([
                'customer',
                'assignee',
                'items.product',
                'activities' => function ($q) {
                    $q->where('type', 'reminder')->latest();
                },
            ])
            ->orderBy('next_follow_up_at')
            ->get();

        $list = $leads->map(function ($lead) {
            $products = $lead->items->pluck('product.name')->filter()->implode(', ');
            $latestReminder = $lead->activities->first();

            return [
                'id' => $lead->id,
                'customer_id' => $lead->customer_id,
                'customer' => $lead->customer,
                'stage' => $lead->stage,
                'next_follow_up_at' => $lead->next_follow_up_at?->toIso8601String(),
                'next_follow_up_date' => $lead->next_follow_up_at?->toDateString(),
                'next_follow_up_time' => $lead->next_follow_up_at?->format('H:i'),
                'assignee' => $lead->assignee,
                'products' => $products,
                'latest_note' => $latestReminder->description ?? null,
                'latest_note_at' => $latestReminder?->created_at?->toIso8601String(),
            ];
        });

        return response()->json($list);
    }
}

