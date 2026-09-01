<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Modules\CRM\Models\Lead;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tells each person, once a morning, what has gone quiet on them.
 *
 * The CRM has never done this. It has a notifications table, four working
 * endpoints and a scheduler that has been running every morning for months,
 * and the sales side is wired to none of it - so a lead going cold produces
 * exactly nothing. The result is visible in the data: of 177 open leads, 169
 * have had no contact in 30 days and 155 have never had a single recorded
 * contact at all, while 33 follow-ups somebody personally promised have gone
 * past their date unnoticed.
 *
 * Nothing here is new information. Every one of these leads is already sitting
 * on a screen somewhere. The difference is that a screen has to be visited and
 * a notification does not.
 *
 * Two rules keep it from becoming wallpaper:
 *
 * - One notification per person per morning, not one per lead. A rep with 60
 *   neglected leads gets one line saying 60 and a link to the list. Sixty
 *   separate rows would be dismissed in a swipe and would teach them to ignore
 *   the bell.
 * - Nothing is raised twice in the same day, so re-running the command - which
 *   a per-minute cron makes easy to do by accident - cannot double up.
 */
class BuildDailyWorklist extends Command
{
    protected $signature = 'crm:daily-worklist
                            {--dry-run : Show what would be raised without writing anything}';

    protected $description = 'Tell each person what has gone quiet: overdue follow-ups, neglected leads, unowned work';

    /**
     * How long an open lead may sit untouched before it is worth saying so.
     * A month is long enough that nobody is being nagged about this week's
     * work, and short enough that it is still worth ringing.
     */
    private const QUIET_DAYS = 30;

    /**
     * Activity that means somebody actually reached out. Deliberately excludes
     * `stage_change`, which the system writes by itself when a card is dragged,
     * and `note` and `reminder`, which cost nothing and would let a lead look
     * worked without anyone having spoken to it.
     */
    private const CONTACT_TYPES = [
        'call', 'meeting', 'visit', 'email', 'whatsapp', 'sms', 'quote_sent', 'appointment',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $raised = 0;

        foreach ($this->overdueFollowUps() as $userId => $rows) {
            $raised += $this->raise(
                $userId,
                'follow_ups.overdue',
                $rows->count().' '.$this->plural($rows->count(), 'follow-up is', 'follow-ups are').' overdue',
                $this->overdueMessage($rows),
                ['route' => '/followups?overdue=1', 'count' => $rows->count()],
                $dryRun
            );
        }

        foreach ($this->quietLeads() as $userId => $count) {
            $raised += $this->raise(
                $userId,
                'leads.quiet',
                $count.' '.$this->plural($count, 'lead has', 'leads have').' gone quiet',
                'No contact recorded in '.self::QUIET_DAYS.' days. Even a "no answer" keeps them on the books '
                    .'properly - a lead nobody has logged looks the same as a lead nobody has rung.',
                ['route' => '/leads?stale_days='.self::QUIET_DAYS, 'count' => $count],
                $dryRun
            );
        }

        foreach ($this->appointmentsAwaitingOutcome() as $userId => $count) {
            $raised += $this->raise(
                $userId,
                'appointments.needs_outcome',
                $count.' '.$this->plural($count, 'appointment needs', 'appointments need').' an outcome',
                'The date has passed and nobody said whether it happened. One tap on the appointments '
                    .'screen closes it - that is what the held rate and the no-show rate are built from.',
                ['route' => '/appointments', 'count' => $count],
                $dryRun
            );
        }

        foreach ($this->managers() as $manager) {
            $unowned = $this->unownedLeadCount();

            if ($unowned > 0) {
                $raised += $this->raise(
                    $manager->id,
                    'leads.unassigned',
                    $unowned.' '.$this->plural($unowned, 'lead has', 'leads have').' no owner',
                    'Nobody is going to pick these up on their own. Assign them, or they will keep ageing quietly.',
                    ['route' => '/leads?assigned_to=unassigned', 'count' => $unowned],
                    $dryRun
                );
            }
        }

        $this->info($dryRun
            ? "Would raise {$raised} notification(s)."
            : "Raised {$raised} notification(s).");

        return self::SUCCESS;
    }

    /**
     * Follow-ups whose date has passed, grouped by the person who set them.
     *
     * These are the sharpest item on the list: nobody assigned them, the rep
     * chose the date themselves and then it went by.
     */
    private function overdueFollowUps()
    {
        return Lead::query()
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<', now()->startOfDay())
            ->whereNotIn('stage', ['won', 'lost'])
            ->whereNotNull('assigned_to')
            ->with('customer:id,name,business_name')
            ->get(['id', 'assigned_to', 'customer_id', 'next_follow_up_at'])
            ->groupBy('assigned_to');
    }

    private function overdueMessage($rows): string
    {
        // Whole days between two dates, not between a date and this instant:
        // diffInDays() is signed and fractional here, which read as "waiting -8
        // days" and lost a day to the time of the run.
        $oldest = \Illuminate\Support\Carbon::parse($rows->min('next_follow_up_at'))->startOfDay();
        $days = (int) $oldest->diffInDays(now()->startOfDay());

        $names = $rows->take(3)
            ->map(fn ($lead) => $lead->customer?->business_name ?: $lead->customer?->name)
            ->filter()
            ->implode(', ');

        $message = $names !== '' ? $names : 'Open them from your follow-ups list';

        if ($rows->count() > 3) {
            $message .= ' and '.($rows->count() - 3).' more';
        }

        return $message.'. The oldest has been waiting '.$days.' '.$this->plural($days, 'day', 'days').'.';
    }

    /**
     * Open leads with no recorded contact in the window, by owner.
     *
     * Keyed off the activity timeline rather than `leads.updated_at`, because
     * `updated_at` moves when somebody edits a phone number and stands still
     * when somebody has a twenty minute conversation.
     */
    private function quietLeads(): array
    {
        $types = "'".implode("','", self::CONTACT_TYPES)."'";
        $cutoff = now()->subDays(self::QUIET_DAYS)->toDateTimeString();

        return collect(DB::select("
            SELECT l.assigned_to AS user_id, COUNT(*) AS c
            FROM leads l
            WHERE l.deleted_at IS NULL
              AND l.stage NOT IN ('won', 'lost')
              AND l.assigned_to IS NOT NULL
              AND COALESCE((
                  SELECT MAX(a.created_at) FROM lead_activities a
                  WHERE a.lead_id = l.id AND a.type IN ({$types})
              ), l.created_at) < ?
            GROUP BY l.assigned_to
        ", [$cutoff]))->pluck('c', 'user_id')->map(fn ($c) => (int) $c)->all();
    }

    /**
     * Appointments whose date has gone by with the status still pending, by the
     * person responsible.
     *
     * 35 of 39 in the system sit here permanently. The appointments screen shows
     * one day at a time, so once the day passes there was no route back to them.
     */
    private function appointmentsAwaitingOutcome(): array
    {
        return \App\Modules\CRM\Models\LeadActivity::query()
            ->where('type', 'appointment')
            ->where(fn ($q) => $q->whereNull('appointment_status')
                ->orWhere('appointment_status', 'pending'))
            ->whereNotNull('appointment_date')
            ->whereDate('appointment_date', '<', now()->toDateString())
            ->get(['id', 'assigned_user_id', 'user_id'])
            ->groupBy(fn ($a) => $a->assigned_user_id ?: $a->user_id)
            ->filter(fn ($rows, $userId) => (bool) $userId)
            ->map->count()
            ->all();
    }

    private function unownedLeadCount(): int
    {
        return Lead::whereNull('assigned_to')->whereNotIn('stage', ['won', 'lost'])->count();
    }

    private function managers()
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($q) => $q->whereIn('name', ['Admin', 'System Admin', 'Manager']))
            ->get(['id']);
    }

    /**
     * @return int 1 if a notification was raised, 0 if one already exists today.
     */
    private function raise(int $userId, string $type, string $title, string $message, array $data, bool $dryRun): int
    {
        $alreadyToday = Notification::where('notifiable_id', $userId)
            ->where('type', $type)
            ->where('created_at', '>=', now()->startOfDay())
            ->exists();

        if ($alreadyToday) {
            return 0;
        }

        if ($dryRun) {
            $this->line("  [{$userId}] {$title} - {$message}");

            return 1;
        }

        Notification::notifyUser($userId, $type, $title, $message, $data);

        return 1;
    }

    private function plural(int $count, string $one, string $many): string
    {
        return $count === 1 ? $one : $many;
    }
}
