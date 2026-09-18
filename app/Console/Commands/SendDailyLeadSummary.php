<?php

namespace App\Console\Commands;

use App\Mail\AutomatedEmail;
use App\Models\User;
use App\Services\AutomatedEmailSender;
use App\Services\DailyLeadScoreboard;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Where each person stands against the daily lead target, and where everybody
 * stands, once a working morning.
 *
 * Two emails from one command because they are one set of figures: a rep is
 * told their own, management is told the room's, and the two disagreeing would
 * be worse than neither being sent.
 */
class SendDailyLeadSummary extends Command
{
    protected $signature = 'emails:daily-lead-summary
        {--date= : The UK date to report on, defaults to today}
        {--preview= : Send both versions to this address instead of to the team}
        {--dry-run : List what would be sent without sending}';

    protected $description = 'Email each person their lead count against target, and management everybody\'s';

    public function handle(AutomatedEmailSender $sender, DailyLeadScoreboard $board): int
    {
        $tz = $board->timezone();
        $date = $this->option('date') ?: now($tz)->toDateString();
        $day = Carbon::parse($date, $tz);

        // Nobody is on the phones, so a row of zeroes is not a shortfall.
        if ($day->isSunday() && ! $this->option('preview')) {
            $this->info('Sunday — nothing sent.');

            return self::SUCCESS;
        }

        $board->forMonthOf($date);

        $dateLabel = $day->format('l j F');
        $rows = $board->tableFor($date);
        $teamDays = $board->recentDaysForTeam($date);

        if ($preview = $this->option('preview')) {
            return $this->preview($sender, $board, $preview, $date, $dateLabel, $rows, $teamDays);
        }

        $sent = 0;

        foreach ($board->people() as $person) {
            $email = trim((string) $person->email);
            $count = $board->countFor($person->id, $date);
            $target = $board->targetFor($person->id);
            $short = max(0, $target - $count);

            if ($email === '') {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line(sprintf('%-22s %d of %d  <%s>', $person->name, $count, $target, $email));

                continue;
            }

            if ($sender->send(
                'lead-target:'.$person->id.':'.$date,
                $email,
                $this->personalMail($sender, $board, $person, $date, $dateLabel, $count, $short),
            )) {
                $sent++;
            }
        }

        foreach ($board->watchers() as $watcher) {
            $email = trim((string) $watcher->email);

            if ($email === '') {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line(sprintf('%-22s (summary)      <%s>', $watcher->name, $email));

                continue;
            }

            if ($sender->send(
                'lead-summary:'.$watcher->id.':'.$date,
                $email,
                $this->summaryMail($sender, $board, $date, $dateLabel, $rows, $teamDays),
            )) {
                $sent++;
            }
        }

        $this->info($this->option('dry-run')
            ? 'Nothing sent (dry run).'
            : "Sent {$sent} lead summary email(s) for {$date}.");

        return self::SUCCESS;
    }

    /** One of each to a single address, so both can be looked at side by side. */
    private function preview(
        AutomatedEmailSender $sender,
        DailyLeadScoreboard $board,
        string $to,
        string $date,
        string $dateLabel,
        array $rows,
        array $teamDays,
    ): int {
        // Whoever has actually been putting leads on lately, so the example is a
        // chart with something in it rather than a row of empty columns.
        $person = $board->people()
            ->sortByDesc(fn (User $u) => array_sum(array_column($board->recentDaysFor($u->id, $date), 'count')))
            ->first()
            ?? User::query()->where('is_active', true)->first();

        if (! $person) {
            $this->error('No user to build the personal example from.');

            return self::FAILURE;
        }

        $count = $board->countFor($person->id, $date);
        $stamp = now()->format('YmdHis');

        // Marked, like every other preview, so one can never be mistaken in an
        // inbox for the thing it is a picture of.
        $personal = $this->personalMail($sender, $board, $person, $date, $dateLabel, $count, max(0, $board->targetFor($person->id) - $count));
        $personal->mailSubject = '[Preview] '.$personal->mailSubject;

        $summary = $this->summaryMail($sender, $board, $date, $dateLabel, $rows, $teamDays);
        $summary->mailSubject = '[Preview] '.$summary->mailSubject;

        $sender->send('preview:lead-target:'.$stamp, $to, $personal);
        $sender->send('preview:lead-summary:'.$stamp, $to, $summary);

        $this->info("Sent both versions to {$to} (personal example built from {$person->name}).");

        return self::SUCCESS;
    }

    private function personalMail(
        AutomatedEmailSender $sender,
        DailyLeadScoreboard $board,
        User $person,
        string $date,
        string $dateLabel,
        int $count,
        int $short,
    ): AutomatedEmail {
        $days = $board->recentDaysFor($person->id, $date);
        $target = $board->targetFor($person->id);

        // Always shown now: leads are the day's job and sales are the month's,
        // and a rep told only about the first cannot see the second slipping.
        $sales = $board->salesProgressFor($person->id, $date);

        return new AutomatedEmail(
            mailSubject: $short > 0
                ? "{$count} of {$target} leads — {$short} short"
                : "{$count} of {$target} leads — target met",
            mailView: 'emails.automated.lead-target-personal',
            mailData: [
                'companyName' => $sender->companyName(),
                'repName' => $person->name,
                'dateLabel' => $dateLabel,
                'count' => $count,
                'target' => $target,
                'short' => $short,
                'days' => $days,
                'weekTotal' => array_sum(array_column($days, 'count')),
                'weekTarget' => array_sum(array_column($days, 'target')),
                'sales' => $sales,
                'lastSale' => $board->lastSaleFor($person->id),
                'monthEnd' => $board->isMonthEndRun($date),
                'daysLeft' => $board->daysLeftInMonth($date),
                'monthLabel' => Carbon::parse($date, $board->timezone())->format('F'),
            ],
        );
    }

    private function summaryMail(
        AutomatedEmailSender $sender,
        DailyLeadScoreboard $board,
        string $date,
        string $dateLabel,
        array $rows,
        array $teamDays,
    ): AutomatedEmail {
        $teamCount = array_sum(array_column($rows, 'count'));
        // Summed rather than multiplied: people can be asked for different numbers.
        $teamTarget = array_sum(array_column($rows, 'target'));

        $salesRows = $board->isMonthEndRun($date) ? $board->salesTable($date) : [];

        return new AutomatedEmail(
            mailSubject: "Leads {$dateLabel}: {$teamCount} of {$teamTarget}",
            mailView: 'emails.automated.lead-target-admin',
            mailData: [
                'companyName' => $sender->companyName(),
                'dateLabel' => $dateLabel,
                'rows' => $rows,
                'days' => $teamDays,
                'teamCount' => $teamCount,
                'teamTarget' => $teamTarget,
                'teamShort' => max(0, $teamTarget - $teamCount),
                'nobody' => $teamCount === 0,
                'salesRows' => $salesRows,
                'salesAchieved' => array_sum(array_column($salesRows, 'achieved')),
                'salesTarget' => array_sum(array_column($salesRows, 'target')),
                'salesShort' => array_sum(array_column($salesRows, 'short')),
                'daysLeft' => $board->daysLeftInMonth($date),
                'monthLabel' => Carbon::parse($date, $board->timezone())->format('F'),
            ],
        );
    }
}
