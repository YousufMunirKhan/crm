<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\DailyActivity;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\CRM\Models\LeadItem;
use App\Modules\Reporting\Services\ReportingService;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\HR\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyActivityController extends Controller
{
    public function __construct(
        protected ReportingService $reportingService
    ) {}

    /** Roles included in the daily report (anyone who may log "Today's Activity" or perform CRM work). */
    private const REPORT_ROLES = ['Sales', 'CallAgent', 'Support', 'Admin', 'Manager', 'System Admin'];

    /** Max timeline rows per user in API/CSV (keeps payloads reasonable). */
    private const TIMELINE_LIMIT = 200;

    /**
     * List current user's daily activities (paginated)
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = $user->isRole('Admin') || $user->isRole('Manager') || $user->isRole('System Admin');

        $query = DailyActivity::with('user')->where('user_id', $user->id);

        if ($request->has('date')) {
            $query->whereDate('activity_date', $request->date);
        }

        if ($request->has('from_date')) {
            $query->where('activity_date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->where('activity_date', '<=', $request->to_date);
        }

        $activities = $query->orderBy('activity_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json($activities);
    }

    /**
     * Store a new daily activity (non-admin)
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'activity_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $user = auth()->user();
        $activity = DailyActivity::create([
            'user_id' => $user->id,
            'activity_date' => Carbon::parse($data['activity_date'])->toDateString(),
            'description' => $data['description'],
        ]);

        return response()->json($activity->load('user'), 201);
    }

    /**
     * Update daily activity
     */
    public function update(Request $request, $id)
    {
        $activity = DailyActivity::findOrFail($id);
        if ($activity->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $data = $request->validate([
            'description' => ['required', 'string', 'max:5000'],
        ]);

        $activity->update($data);
        return response()->json($activity->load('user'));
    }

    /**
     * Delete daily activity
     */
    public function destroy($id)
    {
        $activity = DailyActivity::findOrFail($id);
        if ($activity->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $activity->delete();
        return response()->noContent();
    }

    /**
     * Admin: Full daily summary per active team member — CRM activities, sales, tickets, prospects, exportable.
     */
    public function todaysReport(Request $request)
    {
        $user = auth()->user();
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($this->buildTodaysReportPayload($request->get('date', now()->toDateString())));
    }

    /**
     * Same data as {@see todaysReport} as CSV (UTF-8 BOM for Excel).
     */
    public function todaysReportExport(Request $request): StreamedResponse
    {
        $user = auth()->user();
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            abort(403);
        }

        $dateStr = $request->get('date', now()->toDateString());
        $payload = $this->buildTodaysReportPayload($dateStr);
        $safeName = 'todays-report-' . preg_replace('/[^0-9-]/', '', $payload['date']) . '.csv';

        return response()->streamDownload(function () use ($payload) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fprintf($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Summary', $payload['date_label']]);
            fputcsv($out, [
                'User', 'Role', 'Follow-ups due', 'Prospects added', 'Customers added',
                'Leads added (by user)', 'Leads new (assigned to user)', 'Won sales (products)',
                'Tickets resolved', 'Tickets created', 'CRM activities', 'Daily logs',
                'Check-in', 'Check-out', 'Work hours',
            ]);
            foreach ($payload['report'] as $row) {
                $c = $row['counts'];
                $att = $row['attendance'] ?? [];
                fputcsv($out, [
                    $row['user_name'],
                    $row['role'],
                    $c['follow_ups_due'],
                    $c['prospects_added'],
                    $c['customers_added'],
                    $c['leads_added_by_user'],
                    $c['leads_assigned_new_today'],
                    $c['won_sales'],
                    $c['tickets_resolved'],
                    $c['tickets_created'],
                    $c['crm_activities'],
                    $c['daily_logs'],
                    $att['check_in'] ?? '',
                    $att['check_out'] ?? '',
                    $att['work_hours'] ?? '',
                ]);
            }
            fputcsv($out, []);
            fputcsv($out, ['Activity timeline (up to ' . self::TIMELINE_LIMIT . ' events per user)']);
            fputcsv($out, ['User', 'Time (UTC)', 'Category', 'Title', 'Detail']);
            foreach ($payload['report'] as $row) {
                foreach ($row['timeline'] as $ev) {
                    fputcsv($out, [
                        $row['user_name'],
                        $ev['at'],
                        $ev['category'],
                        $ev['title'],
                        $ev['detail'],
                    ]);
                }
            }
            fclose($out);
        }, $safeName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function buildTodaysReportPayload(string $dateInput): array
    {
        $targetDate = Carbon::parse($dateInput)->startOfDay();
        $targetDateStr = $targetDate->toDateString();
        $dayStart = $targetDate->copy();
        $dayEnd = $targetDate->copy()->endOfDay();

        $users = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('name', self::REPORT_ROLES))
            ->with('role')
            ->orderBy('name')
            ->get();

        $userIds = $users->pluck('id')->all();
        if ($userIds === []) {
            return [
                'date' => $targetDateStr,
                'date_label' => $targetDate->format('l, d F Y'),
                'report' => [],
            ];
        }

        $dailyByUser = DailyActivity::query()
            ->whereIn('user_id', $userIds)
            ->whereDate('activity_date', $targetDateStr)
            ->orderBy('created_at')
            ->get()
            ->groupBy('user_id');

        $leadActsByUser = LeadActivity::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->with(['lead.customer:id,name', 'lead:id,customer_id,stage'])
            ->orderBy('created_at')
            ->get()
            ->groupBy('user_id');

        $customersByCreator = Customer::query()
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->whereIn('created_by', $userIds)
            ->get()
            ->groupBy('created_by');

        $leadsByCreator = Lead::query()
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->whereIn('created_by', $userIds)
            ->with('customer:id,name')
            ->get()
            ->groupBy('created_by');

        $ticketsResolved = Ticket::query()
            ->whereBetween('resolved_at', [$dayStart, $dayEnd])
            ->whereNotNull('resolved_at')
            ->with(['assignee:id,name', 'assignees:id', 'customer:id,name'])
            ->where(function ($q) use ($userIds) {
                $q->whereIn('assigned_to', $userIds)
                    ->orWhereHas('assignees', fn ($a) => $a->whereIn('users.id', $userIds));
            })
            ->get();

        $resolvedTicketsByUser = [];
        foreach ($userIds as $id) {
            $resolvedTicketsByUser[$id] = collect();
        }
        foreach ($ticketsResolved as $ticket) {
            $credited = collect([$ticket->assigned_to])
                ->merge($ticket->assignees->pluck('id'))
                ->filter()
                ->unique();
            foreach ($credited as $uid) {
                if (isset($resolvedTicketsByUser[$uid])) {
                    $resolvedTicketsByUser[$uid]->push($ticket);
                }
            }
        }

        $ticketsCreatedByUser = Ticket::query()
            ->whereBetween('created_at', [$dayStart, $dayEnd])
            ->whereIn('created_by', $userIds)
            ->with('customer:id,name')
            ->get()
            ->groupBy('created_by');

        $report = [];
        foreach ($users as $u) {
            $uid = (int) $u->id;
            $activities = $dailyByUser->get($uid, collect());
            $leadActs = $leadActsByUser->get($uid, collect());
            $myCustomers = $customersByCreator->get($uid, collect());
            $myLeadsAdded = $leadsByCreator->get($uid, collect());
            $myTicketsResolved = $resolvedTicketsByUser[$uid] ?? collect();
            $myTicketsCreated = $ticketsCreatedByUser->get($uid, collect());

            $prospectsAdded = $myCustomers->where('type', Customer::TYPE_PROSPECT)->count();
            $customersAdded = $myCustomers->where('type', Customer::TYPE_CUSTOMER)->count();

            $followUpsDue = Lead::query()
                ->where('assigned_to', $uid)
                ->whereDate('next_follow_up_at', $targetDateStr)
                ->whereNotIn('stage', ['won', 'lost'])
                ->count();

            $leadsAssignedNewToday = Lead::query()
                ->where('assigned_to', $uid)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->count();

            $wonSales = $this->reportingService->countWonLeadItemsForAgent($uid, $dayStart, $dayEnd);
            $wonItems = $this->reportingService->wonLeadItemsForAgentOnDate($uid, $targetDate);

            $appointmentsLogged = $leadActs->where('type', 'appointment')->count();

            $attendance = Attendance::query()
                ->where('user_id', $uid)
                ->whereDate('date', $targetDateStr)
                ->first();

            $attendanceOut = $attendance ? [
                'check_in' => $attendance->check_in_at?->format('H:i'),
                'check_out' => $attendance->check_out_at?->format('H:i'),
                'work_hours' => $attendance->work_hours,
            ] : null;

            $counts = [
                'follow_ups_due' => $followUpsDue,
                'prospects_added' => $prospectsAdded,
                'customers_added' => $customersAdded,
                'leads_added_by_user' => $myLeadsAdded->count(),
                'leads_assigned_new_today' => $leadsAssignedNewToday,
                'won_sales' => $wonSales,
                'tickets_resolved' => $myTicketsResolved->count(),
                'tickets_created' => $myTicketsCreated->count(),
                'crm_activities' => $leadActs->count(),
                'appointments_logged' => $appointmentsLogged,
                'daily_logs' => $activities->count(),
            ];

            $timeline = $this->mergeUserTimeline(
                $activities,
                $leadActs,
                $myTicketsResolved,
                $myTicketsCreated,
                $myCustomers,
                $myLeadsAdded,
                $wonItems
            );

            $report[] = [
                'user_id' => $uid,
                'user_name' => $u->name,
                'role' => $u->role?->name ?? '-',
                'counts' => $counts,
                'attendance' => $attendanceOut,
                'activities' => $activities->values(),
                'activities_count' => $activities->count(),
                'follow_ups_count' => $followUpsDue,
                'leads_created_count' => $leadsAssignedNewToday,
                'leads_added_by_user_count' => $myLeadsAdded->count(),
                'leads_won_count' => $wonSales,
                'won_sales_count' => $wonSales,
                'tickets_resolved' => $myTicketsResolved->map(fn (Ticket $t) => [
                    'id' => $t->id,
                    'ticket_number' => $t->ticket_number,
                    'subject' => $t->subject,
                    'status' => $t->status,
                    'customer_name' => $t->customer?->name,
                    'resolved_at' => $t->resolved_at?->toIso8601String(),
                ])->values(),
                'tickets_created' => $myTicketsCreated->map(fn (Ticket $t) => [
                    'id' => $t->id,
                    'ticket_number' => $t->ticket_number,
                    'subject' => $t->subject,
                    'status' => $t->status,
                    'customer_name' => $t->customer?->name,
                ])->values(),
                'prospects_added_list' => $myCustomers->where('type', Customer::TYPE_PROSPECT)->map(fn (Customer $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                ])->values(),
                'leads_added_list' => $myLeadsAdded->map(fn (Lead $lead) => [
                    'id' => $lead->id,
                    'customer_name' => $lead->customer?->name,
                    'stage' => $lead->stage,
                ])->values(),
                'won_sales_list' => $wonItems->map(fn (LeadItem $item) => [
                    'id' => $item->id,
                    'product_name' => $item->product?->name,
                    'customer_name' => $item->lead?->customer?->name,
                    'total_price' => $item->total_price,
                    'closed_at' => $item->closed_at?->toIso8601String(),
                ])->values(),
                'crm_activity_preview' => $leadActs->take(50)->map(fn (LeadActivity $la) => [
                    'id' => $la->id,
                    'type' => $la->type,
                    'description' => $la->description,
                    'created_at' => $la->created_at?->toIso8601String(),
                    'customer_name' => $la->lead?->customer?->name,
                    'lead_id' => $la->lead_id,
                ])->values(),
                'timeline' => $timeline,
            ];
        }

        return [
            'date' => $targetDateStr,
            'date_label' => $targetDate->format('l, d F Y'),
            'report' => $report,
        ];
    }

    private function mergeUserTimeline(
        Collection $dailyActivities,
        Collection $leadActs,
        Collection $ticketsResolved,
        Collection $ticketsCreated,
        Collection $customersCreated,
        Collection $leadsAdded,
        Collection $wonItems
    ): array {
        $rows = [];

        foreach ($dailyActivities as $a) {
            $rows[] = [
                'at' => $a->created_at?->toIso8601String(),
                'category' => 'daily_log',
                'title' => 'Manual activity (Today\'s Activity)',
                'detail' => $this->clip((string) ($a->description ?? '')),
            ];
        }

        foreach ($leadActs as $la) {
            $label = $this->leadActivityTypeLabel($la->type);
            $cust = $la->lead?->customer?->name;
            $prefix = $cust ? "{$cust}: " : '';
            $detail = $la->description
                ?: $this->metaSummary($la->meta ?? []);
            $rows[] = [
                'at' => $la->created_at?->toIso8601String(),
                'category' => 'crm_activity',
                'title' => $label,
                'detail' => $this->clip($prefix . $detail),
            ];
        }

        foreach ($ticketsResolved as $t) {
            $rows[] = [
                'at' => $t->resolved_at?->toIso8601String(),
                'category' => 'ticket_resolved',
                'title' => 'Ticket resolved #' . ($t->ticket_number ?? $t->id),
                'detail' => $this->clip(($t->subject ?? '') . ($t->customer?->name ? ' — ' . $t->customer->name : '')),
            ];
        }

        foreach ($ticketsCreated as $t) {
            $rows[] = [
                'at' => $t->created_at?->toIso8601String(),
                'category' => 'ticket_created',
                'title' => 'Ticket created #' . ($t->ticket_number ?? $t->id),
                'detail' => $this->clip(($t->subject ?? '') . ($t->customer?->name ? ' — ' . $t->customer->name : '')),
            ];
        }

        foreach ($customersCreated as $c) {
            $typeLabel = $c->type === Customer::TYPE_CUSTOMER ? 'Customer' : 'Prospect';
            $rows[] = [
                'at' => $c->created_at?->toIso8601String(),
                'category' => $c->type === Customer::TYPE_CUSTOMER ? 'customer_added' : 'prospect_added',
                'title' => "{$typeLabel} added",
                'detail' => $this->clip((string) ($c->name ?? '')),
            ];
        }

        foreach ($leadsAdded as $lead) {
            $rows[] = [
                'at' => $lead->created_at?->toIso8601String(),
                'category' => 'lead_added',
                'title' => 'Lead added',
                'detail' => $this->clip(($lead->customer?->name ?? 'Lead #' . $lead->id) . ' — stage: ' . ($lead->stage ?? '')),
            ];
        }

        foreach ($wonItems as $item) {
            $p = $item->product?->name ?? 'Product';
            $cust = $item->lead?->customer?->name ?? '';
            $rows[] = [
                'at' => ($item->closed_at ?? $item->created_at)?->toIso8601String(),
                'category' => 'sale_won',
                'title' => 'Sale / product won',
                'detail' => $this->clip($p . ($cust ? " — {$cust}" : '') . ' — £' . $item->total_price),
            ];
        }

        usort($rows, function ($a, $b) {
            return strcmp((string) ($a['at'] ?? ''), (string) ($b['at'] ?? ''));
        });

        return array_slice($rows, 0, self::TIMELINE_LIMIT);
    }

    private function leadActivityTypeLabel(string $type): string
    {
        return match ($type) {
            'appointment' => 'Appointment',
            'stage_change' => 'Stage change',
            'note' => 'Note',
            'call' => 'Call',
            'message' => 'Message',
            'follow_up' => 'Follow-up',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    private function metaSummary(array $meta): string
    {
        if ($meta === []) {
            return '';
        }
        $parts = [];
        if (isset($meta['to_stage'])) {
            $parts[] = '→ ' . $meta['to_stage'];
        }
        if (isset($meta['from_stage'])) {
            $parts[] = 'from ' . $meta['from_stage'];
        }

        return $parts !== [] ? implode(' ', $parts) : json_encode($meta, JSON_UNESCAPED_UNICODE);
    }

    private function clip(string $text, int $max = 500): string
    {
        $t = preg_replace("/[\r\n]+/", ' ', $text);
        if ($t === null) {
            $t = $text;
        }

        return mb_strlen($t) > $max ? mb_substr($t, 0, $max) . '…' : $t;
    }

    /**
     * Generate a concise daily report using GPT. Only allowed for specific users (by email).
     * Sends today's report data (employees, leads, activities, tickets, appointments) to OpenAI and returns a summary.
     */
    public function generateReportWithGpt(Request $request)
    {
        $user = auth()->user();
        $allowedEmails = ['yousufmunir59@gmail.com', 'owaishameed301@gmail.com'];
        if (!in_array($user->email, $allowedEmails, true)) {
            return response()->json(['message' => 'Unauthorized. This action is only available for specific users.'], 403);
        }

        $apiKey = config('services.openai.key');
        if (!$apiKey) {
            return response()->json(['message' => 'OpenAI API key is not configured. Add OPENAI_API_KEY to your .env file.'], 500);
        }

        $date = $request->get('date', now()->toDateString());
        $targetDate = Carbon::parse($date)->startOfDay();
        $targetDateStr = $targetDate->toDateString();

        $users = User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('name', self::REPORT_ROLES))
            ->with('role')
            ->orderBy('name')
            ->get();

        $reportLines = ["Daily Report for {$targetDate->format('l, d F Y')}", ''];

        foreach ($users as $u) {
            $activities = DailyActivity::where('user_id', $u->id)
                ->whereDate('activity_date', $targetDateStr)
                ->orderBy('created_at')
                ->get();

            $followUpsCount = Lead::where('assigned_to', $u->id)
                ->whereDate('next_follow_up_at', $targetDateStr)
                ->whereNotIn('stage', ['won', 'lost'])
                ->count();

            $leadsCount = Lead::where('assigned_to', $u->id)
                ->whereDate('created_at', $targetDateStr)
                ->count();

            $dayStart = $targetDate->copy()->startOfDay();
            $dayEnd = $targetDate->copy()->endOfDay();
            $wonCount = $this->reportingService->countWonLeadItemsForAgent((int) $u->id, $dayStart, $dayEnd);

            $attendance = Attendance::where('user_id', $u->id)
                ->whereDate('date', $targetDateStr)
                ->first();

            $roleName = $u->role ? $u->role->name : '-';
            $reportLines[] = "--- {$u->name} ({$roleName}) ---";
            $reportLines[] = "Follow-ups due: {$followUpsCount}, Leads (assigned new today): {$leadsCount}, Won sales (products): {$wonCount}";
            if ($attendance) {
                $reportLines[] = "Attendance: {$attendance->check_in_at?->format('H:i')} - {$attendance->check_out_at?->format('H:i')}" . ($attendance->work_hours ? " ({$attendance->work_hours}h)" : '');
            } else {
                $reportLines[] = 'Attendance: Not logged';
            }
            $reportLines[] = "Activities logged: {$activities->count()}";
            foreach ($activities as $a) {
                $reportLines[] = "  - " . str_replace(["\r", "\n"], ' ', $a->description ?? '');
            }

            // Lead activities (messages, appointments, notes) for this user on this date
            $leadActivities = LeadActivity::where('user_id', $u->id)
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->with('lead:id,customer_id')
                ->orderBy('created_at')
                ->get();
            $messagesCount = $leadActivities->whereIn('type', ['note', 'call', 'message'])->count();
            $appointmentsCount = $leadActivities->where('type', 'appointment')->count();
            $reportLines[] = "Lead messages/notes: {$messagesCount}, Appointments: {$appointmentsCount}";
            foreach ($leadActivities->take(20) as $la) {
                $desc = $la->description ?? json_encode($la->meta ?? []);
                $reportLines[] = "  [{$la->type}] " . str_replace(["\r", "\n"], ' ', substr($desc, 0, 200));
            }
            $reportLines[] = '';
        }

        // Tickets created or updated on this date
        $tickets = Ticket::whereDate('created_at', $targetDateStr)
            ->orWhereDate('updated_at', $targetDateStr)
            ->with(['assignee:id,name', 'customer:id,name'])
            ->orderBy('created_at')
            ->get();
        $reportLines[] = '--- TICKETS ---';
        $reportLines[] = "Total tickets (created/updated this day): {$tickets->count()}";
        foreach ($tickets->take(30) as $t) {
            $reportLines[] = "  #{$t->ticket_number} {$t->subject} - {$t->status} (Assignee: " . ($t->assignee?->name ?? '-') . ")";
        }
        $reportLines[] = '';

        $rawReport = implode("\n", $reportLines);

        $response = Http::withToken($apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a concise business report writer. Given raw daily CRM data (team activities, leads, follow-ups, tickets, appointments), produce a clear, professional daily summary in plain text. Use short paragraphs and bullet points where helpful. Do not invent data; only summarize what is provided.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Create a concise daily report from this data:\n\n" . $rawReport,
                    ],
                ],
                'max_tokens' => 1500,
            ]);

        if (!$response->successful()) {
            $body = $response->json();
            $error = $body['error']['message'] ?? $response->body();
            $code = $body['error']['code'] ?? null;
            // User-friendly message for quota/billing errors
            if (stripos($error, 'quota') !== false || stripos($error, 'billing') !== false || $code === 'insufficient_quota') {
                $error = 'OpenAI API quota exceeded. Please check your plan and billing at https://platform.openai.com/account/billing, or try again later.';
            } else {
                $error = 'OpenAI request failed: ' . $error;
            }
            return response()->json(['message' => $error], 502);
        }

        $choices = $response->json('choices');
        $content = $choices[0]['message']['content'] ?? '';

        return response()->json([
            'date' => $targetDateStr,
            'date_label' => $targetDate->format('l, d F Y'),
            'generated_report' => trim($content),
            'raw_data_sent_to_gpt' => $rawReport,
            'system_prompt' => 'You are a concise business report writer. Given raw daily CRM data (team activities, leads, follow-ups, tickets, appointments), produce a clear, professional daily summary in plain text. Use short paragraphs and bullet points where helpful. Do not invent data; only summarize what is provided.',
        ]);
    }
}
