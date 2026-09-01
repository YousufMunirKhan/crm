<?php

namespace App\Modules\Reporting\Services;

use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadActivity;
use App\Modules\Ticket\Models\Ticket;
use Illuminate\Support\Facades\DB;

/**
 * The state of the business right now, as opposed to what happened in a window.
 *
 * The owner's dashboard was built entirely out of date ranges - leads this
 * week, won this week, win rate this week - which on a quiet week renders a
 * screen of zeroes and teaches nobody anything. Worse, two of those figures
 * cannot work here at all: prices are deliberately never recorded, so every
 * revenue number is £0 by design; and 391 leads are marked won against 3 lost,
 * so win rate measures how people file rather than how they sell.
 *
 * What is left, and what this returns, is the part of the record that is
 * honest: timestamps, ownership and whether anybody has touched anything. The
 * question it answers is not "how did we do last week" but "what is rotting,
 * and who owns it".
 *
 * Nothing here is date-filtered. A lead nobody has rung for six months is not
 * less true on a Monday.
 */
class BookHealthService
{
    /** An open lead untouched this long is worth saying out loud. */
    private const QUIET_DAYS = 30;

    private const LONG_QUIET_DAYS = 90;

    /**
     * Activity that means somebody actually reached out.
     *
     * Excludes `stage_change`, which the system writes by itself when a card is
     * dragged, and `note` and `reminder`, which cost nothing - counting either
     * would let a lead look worked without anybody having spoken to it.
     */
    private const CONTACT_TYPES = [
        'call', 'meeting', 'visit', 'email', 'whatsapp', 'sms', 'quote_sent', 'appointment',
    ];

    /**
     * Whose book this is about. Null means the whole company.
     *
     * The same questions matter to a salesperson as to the owner - what have I
     * let go quiet, what did I promise and miss - so this is one set of
     * definitions with a scope, rather than a second implementation that would
     * drift and give two different answers to the same question.
     */
    private ?int $userId = null;

    public function forUser(?int $userId): self
    {
        $clone = clone $this;
        $clone->userId = $userId;

        return $clone;
    }

    public function snapshot(): array
    {
        return [
            'leads' => $this->leads(),
            'follow_ups' => $this->followUps(),
            'appointments' => $this->appointments(),
            'tickets' => $this->tickets(),
            'data_quality' => $this->dataQuality(),
            'stalest' => $this->stalest(),
            // A per-person view of "who owns the neglect" is a list of one.
            'by_owner' => $this->userId ? [] : $this->byOwner(),
        ];
    }

    /** SQL for "when did anybody last actually contact this lead". */
    private function lastContactSql(string $leadTable = 'leads'): string
    {
        $types = "'".implode("','", self::CONTACT_TYPES)."'";

        return "COALESCE((SELECT MAX(a.created_at) FROM lead_activities a
                    WHERE a.lead_id = {$leadTable}.id AND a.type IN ({$types})), {$leadTable}.created_at)";
    }

    private function openLeads()
    {
        return Lead::query()
            ->whereNotIn('stage', ['won', 'lost'])
            ->when($this->userId, fn ($q) => $q->where('assigned_to', $this->userId));
    }

    private function leads(): array
    {
        $lastContact = $this->lastContactSql();

        $quietCutoff = now()->subDays(self::QUIET_DAYS)->toDateTimeString();
        $longCutoff = now()->subDays(self::LONG_QUIET_DAYS)->toDateTimeString();

        $types = "'".implode("','", self::CONTACT_TYPES)."'";

        return [
            'open' => $this->openLeads()->count(),
            'quiet_30' => (int) $this->openLeads()->whereRaw("{$lastContact} < ?", [$quietCutoff])->count(),
            'quiet_90' => (int) $this->openLeads()->whereRaw("{$lastContact} < ?", [$longCutoff])->count(),
            // The sharpest number in the set: not neglected since some date -
            // never contacted at all, not once.
            'never_contacted' => (int) $this->openLeads()
                ->whereRaw("NOT EXISTS (SELECT 1 FROM lead_activities a
                    WHERE a.lead_id = leads.id AND a.type IN ({$types}))")
                ->count(),
            'unassigned' => (int) $this->openLeads()->whereNull('assigned_to')->count(),
        ];
    }

    private function followUps(): array
    {
        $overdue = $this->openLeads()
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', now()->startOfDay());

        $oldest = (clone $overdue)->min('next_follow_up_at');

        return [
            'overdue' => (int) (clone $overdue)->count(),
            'due_today' => (int) $this->openLeads()
                ->whereDate('next_follow_up_at', now()->toDateString())
                ->count(),
            // Days, not a date: "waiting 189 days" lands harder than a date in
            // March that the reader has to subtract from today themselves.
            'oldest_days' => $oldest
                ? (int) \Illuminate\Support\Carbon::parse($oldest)->startOfDay()->diffInDays(now()->startOfDay())
                : 0,
        ];
    }

    private function appointments(): array
    {
        return [
            'today' => (int) $this->appointmentsOwned()
                ->whereDate('appointment_date', now()->toDateString())
                ->count(),
            // Past their date with nobody having said whether they happened,
            // which is why held rate and no-show rate cannot be measured.
            'awaiting_outcome' => (int) $this->appointmentsOwned()
                ->whereNotNull('appointment_date')
                ->whereDate('appointment_date', '<', now()->toDateString())
                ->where(fn ($q) => $q->whereNull('appointment_status')->orWhere('appointment_status', 'pending'))
                ->count(),
        ];
    }

    /** Appointments this person owns, or all of them for the company view. */
    private function appointmentsOwned()
    {
        return LeadActivity::where('type', 'appointment')
            ->when($this->userId, fn ($q) => $q->where(
                fn ($inner) => $inner->where('assigned_user_id', $this->userId)
                    ->orWhere('user_id', $this->userId)
            ));
    }

    private function tickets(): array
    {
        $open = Ticket::whereIn('status', ['open', 'in_progress'])
            ->when($this->userId, fn ($q) => $q->where('assigned_to', $this->userId));

        return [
            // Genuinely open. Counting anything not `closed` sweeps in the
            // resolved pile and quadruples the number - which is a mistake this
            // dashboard made in an earlier form.
            'open' => (int) (clone $open)->count(),
            'over_7_days' => (int) (clone $open)->where('created_at', '<', now()->subDays(7))->count(),
            'over_90_days' => (int) (clone $open)->where('created_at', '<', now()->subDays(90))->count(),
            'unassigned' => (int) (clone $open)->whereNull('assigned_to')->count(),
            'sla_breached' => (int) (clone $open)
                ->whereNotNull('sla_due_at')->where('sla_due_at', '<', now())->count(),
            // Fixed and then never closed, because nothing in the product closes
            // them and the status filter does not even offer `resolved`.
            'resolved_not_closed' => (int) Ticket::where('status', 'resolved')
                ->when($this->userId, fn ($q) => $q->where('assigned_to', $this->userId))
                ->count(),
        ];
    }

    private function dataQuality(): array
    {
        return [
            'customers_without_email' => (int) $this->customersInScope()
                ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))
                ->count(),
            'customers_total' => (int) $this->customersInScope()->count(),
            'leads_without_source' => (int) $this->openLeads()
                ->where(fn ($q) => $q->whereNull('source')->orWhere('source', ''))
                ->count(),
            // Losses recorded before the reason picker existed, so the loss
            // report is honest about what it cannot break down.
            'losses_without_reason' => (int) Lead::where('stage', 'lost')
                ->when($this->userId, fn ($q) => $q->where('assigned_to', $this->userId))
                ->whereNull('lost_reason_code')->count(),
        ];
    }

    /**
     * For one person, the customers behind their own open leads - telling a
     * salesperson that 219 customers company-wide have no email is not
     * something they can do anything about.
     */
    private function customersInScope()
    {
        return Customer::query()->when(
            $this->userId,
            fn ($q) => $q->whereHas('leads', fn ($l) => $l->where('assigned_to', $this->userId))
        );
    }

    /**
     * The twenty open leads nobody has touched for longest.
     *
     * The worklist behind the headline number. A count tells an owner the shape
     * of the problem; twenty names with a phone number beside them is the thing
     * somebody can act on before lunch.
     */
    private function stalest(int $limit = 20): array
    {
        $lastContact = $this->lastContactSql();

        return $this->openLeads()
            ->with(['customer:id,name,business_name,phone', 'assignee:id,name'])
            ->select('leads.*')
            ->selectRaw("{$lastContact} AS last_contact_at")
            ->orderByRaw("{$lastContact} ASC")
            ->limit($limit)
            ->get()
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'customer_id' => $lead->customer_id,
                'name' => $lead->customer?->business_name ?: $lead->customer?->name,
                'contact' => $lead->customer?->name,
                'phone' => $lead->customer?->phone,
                'owner' => $lead->assignee?->name,
                'stage' => $lead->stage,
                'days_since_contact' => $lead->last_contact_at
                    ? (int) \Illuminate\Support\Carbon::parse($lead->last_contact_at)->diffInDays(now())
                    : null,
            ])
            ->all();
    }

    /**
     * Each person's book and how much of it has gone quiet.
     *
     * Deliberately a rate rather than a ranking: one rep here holds 191 leads
     * and another holds 1, so a league table of totals says nothing except who
     * was handed the most. The percentage is of their own book, which means
     * adding a junk lead makes their own number worse.
     */
    private function byOwner(): array
    {
        $types = "'".implode("','", self::CONTACT_TYPES)."'";
        $cutoff = now()->subDays(self::QUIET_DAYS)->toDateTimeString();

        return collect(DB::select("
            SELECT u.id, u.name,
                   COUNT(*) AS book,
                   SUM(CASE WHEN COALESCE((SELECT MAX(a.created_at) FROM lead_activities a
                        WHERE a.lead_id = l.id AND a.type IN ({$types})), l.created_at) < ?
                       THEN 1 ELSE 0 END) AS quiet
            FROM leads l
            JOIN users u ON u.id = l.assigned_to
            WHERE l.deleted_at IS NULL AND l.stage NOT IN ('won','lost')
            GROUP BY u.id, u.name
            ORDER BY quiet DESC
        ", [$cutoff]))->map(fn ($row) => [
            'id' => (int) $row->id,
            'name' => $row->name,
            'book' => (int) $row->book,
            'quiet' => (int) $row->quiet,
            'quiet_pct' => $row->book > 0 ? (int) round($row->quiet / $row->book * 100) : 0,
        ])->all();
    }
}
