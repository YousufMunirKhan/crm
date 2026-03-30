<?php

namespace App\Http\Controllers;

use App\Modules\CRM\Models\DailyActivity;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\Ticket\Models\Ticket;
use App\Modules\HR\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class DailyActivityController extends Controller
{
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
     * Admin: Today's report - all users' activities, follow-ups, leads, attendance for a date
     */
    public function todaysReport(Request $request)
    {
        $user = auth()->user();
        if (!$user->isRole('Admin') && !$user->isRole('Manager') && !$user->isRole('System Admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $date = $request->get('date', now()->toDateString());
        $targetDate = Carbon::parse($date)->startOfDay();
        $targetDateStr = $targetDate->toDateString();

        // Get users with Sales, CallAgent, Support roles (non-admin roles that do field work)
        $users = \App\Models\User::whereHas('role', function ($q) {
            $q->whereIn('name', ['Sales', 'CallAgent', 'Support']);
        })->with('role')->orderBy('name')->get();

        $report = [];

        foreach ($users as $u) {
            // Daily activities (what they logged)
            $activities = DailyActivity::where('user_id', $u->id)
                ->whereDate('activity_date', $targetDateStr)
                ->orderBy('created_at')
                ->get();

            // Follow-ups due today (assigned to them)
            $followUpsCount = Lead::where('assigned_to', $u->id)
                ->whereDate('next_follow_up_at', $targetDateStr)
                ->whereNotIn('stage', ['won', 'lost'])
                ->count();

            // Leads created today
            $leadsCount = Lead::where('assigned_to', $u->id)
                ->whereDate('created_at', $targetDateStr)
                ->count();

            // Leads won on this date (via stage change activity)
            $wonActivities = \App\Modules\CRM\Models\LeadActivity::whereHas('lead', fn($q) => $q->where('assigned_to', $u->id))
                ->where('type', 'stage_change')
                ->whereDate('created_at', $targetDateStr)
                ->get();
            $wonCount = $wonActivities->filter(fn($a) => ($a->meta['to_stage'] ?? '') === 'won')->count();

            // Attendance
            $attendance = Attendance::where('user_id', $u->id)
                ->whereDate('date', $targetDateStr)
                ->first();

            $report[] = [
                'user_id' => $u->id,
                'user_name' => $u->name,
                'role' => $u->role?->name ?? '-',
                'activities' => $activities,
                'activities_count' => $activities->count(),
                'follow_ups_count' => $followUpsCount,
                'leads_created_count' => $leadsCount,
                'leads_won_count' => $wonCount,
                'attendance' => $attendance ? [
                    'check_in' => $attendance->check_in_at?->format('H:i'),
                    'check_out' => $attendance->check_out_at?->format('H:i'),
                    'work_hours' => $attendance->work_hours,
                ] : null,
            ];
        }

        return response()->json([
            'date' => $targetDateStr,
            'date_label' => $targetDate->format('l, d F Y'),
            'report' => $report,
        ]);
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

        // Build same report data as todaysReport
        $users = \App\Models\User::whereHas('role', function ($q) {
            $q->whereIn('name', ['Sales', 'CallAgent', 'Support']);
        })->with('role')->orderBy('name')->get();

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

            $wonActivities = LeadActivity::whereHas('lead', fn($q) => $q->where('assigned_to', $u->id))
                ->where('type', 'stage_change')
                ->whereDate('created_at', $targetDateStr)
                ->get();
            $wonCount = $wonActivities->filter(fn($a) => ($a->meta['to_stage'] ?? '') === 'won')->count();

            $attendance = Attendance::where('user_id', $u->id)
                ->whereDate('date', $targetDateStr)
                ->first();

            $roleName = $u->role ? $u->role->name : '-';
            $reportLines[] = "--- {$u->name} ({$roleName}) ---";
            $reportLines[] = "Follow-ups due: {$followUpsCount}, Leads created: {$leadsCount}, Leads won: {$wonCount}";
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
                ->whereDate('created_at', $targetDateStr)
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
