<?php

namespace App\Console\Commands;

use App\Mail\AutomatedEmail;
use App\Models\User;
use App\Modules\CRM\Models\Lead;
use App\Services\AutomatedEmailSender;
use Illuminate\Console\Command;

/**
 * Each person's own overdue and due-today follow-ups, once a morning.
 *
 * Overdue comes first and is counted separately: a list that mixes the two
 * reads as one long list, and the whole point is that some of it should have
 * happened already.
 */
class SendFollowUpDigests extends Command
{
    protected $signature = 'emails:follow-up-digest {--dry-run}';

    protected $description = 'Email each person their due and overdue follow-ups';

    public function handle(AutomatedEmailSender $sender): int
    {
        $tz = config('app.display_timezone');
        $today = now($tz)->toDateString();
        $endOfToday = now($tz)->endOfDay();

        $leads = Lead::query()
            ->whereNotNull('next_follow_up_at')
            ->where('next_follow_up_at', '<=', $endOfToday)
            ->whereNotIn('stage', ['won', 'lost'])
            ->whereNotNull('assigned_to')
            ->with(['customer', 'assignee'])
            ->get();

        $sent = 0;

        foreach ($leads->groupBy('assigned_to') as $userId => $theirs) {
            $user = User::find($userId);
            $email = trim((string) ($user->email ?? ''));

            if (! $user || $email === '') {
                continue;
            }

            $overdue = $theirs
                ->filter(fn (Lead $l) => $l->next_follow_up_at->setTimezone($tz)->toDateString() < $today)
                ->sortBy('next_follow_up_at')
                ->map(fn (Lead $l) => $this->row($l, $tz))
                ->values()
                ->all();

            $dueToday = $theirs
                ->filter(fn (Lead $l) => $l->next_follow_up_at->setTimezone($tz)->toDateString() === $today)
                ->sortBy('next_follow_up_at')
                ->map(fn (Lead $l) => $this->row($l, $tz))
                ->values()
                ->all();

            if ($overdue === [] && $dueToday === []) {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line(sprintf('%s <%s>: %d overdue, %d due today', $user->name, $email, count($overdue), count($dueToday)));

                continue;
            }

            $subject = count($overdue)
                ? count($overdue).' overdue follow-up'.(count($overdue) === 1 ? '' : 's').', '.count($dueToday).' due today'
                : count($dueToday).' follow-up'.(count($dueToday) === 1 ? '' : 's').' due today';

            $mail = new AutomatedEmail(
                mailSubject: $subject,
                mailView: 'emails.automated.follow-up-digest',
                mailData: [
                    'companyName' => $sender->companyName(),
                    'repName' => $user->name,
                    'overdue' => $overdue,
                    'dueToday' => $dueToday,
                ],
            );

            if ($sender->send('follow-up-digest:'.$user->id.':'.$today, $email, $mail)) {
                $sent++;
            }
        }

        $this->info($this->option('dry-run')
            ? $leads->count().' follow-up(s) due or overdue across everyone.'
            : "Sent {$sent} follow-up digest(s).");

        return self::SUCCESS;
    }

    /** @return array<string, string|null> */
    private function row(Lead $lead, string $tz): array
    {
        $due = $lead->next_follow_up_at->setTimezone($tz);

        return [
            'customer' => $lead->customer?->business_name ?: ($lead->customer?->name ?: 'Customer'),
            'due' => $due->format('j M, H:i'),
            'stage' => $lead->stage ? ucwords(str_replace('_', ' ', $lead->stage)) : null,
            'phone' => $lead->customer?->phone,
        ];
    }
}
