<?php

namespace App\Console\Commands;

use App\Mail\AutomatedEmail;
use App\Services\AutomatedEmailSender;
use App\Services\DailyLeadScoreboard;
use Illuminate\Console\Command;

/**
 * The week just gone, for the people who run the place.
 *
 * The daily summary answers "what happened today", which on any single day is
 * mostly noise - one quiet Tuesday tells you nothing. A week is long enough to
 * show a pattern and short enough to still be worth acting on, and Sunday is
 * when there is time to read it.
 */
class SendWeeklyTeamSummary extends Command
{
    protected $signature = 'emails:weekly-team-summary
        {--date= : The UK date to report from, defaults to today}
        {--preview= : Send one example to this address}
        {--dry-run : List what would be sent without sending}';

    protected $description = 'Email management the whole team\'s week';

    public function handle(AutomatedEmailSender $sender, DailyLeadScoreboard $board): int
    {
        $tz = $board->timezone();
        $date = $this->option('date') ?: now($tz)->toDateString();

        [$from, $to] = $board->lastWorkingWeek($date);
        $rows = $board->weekTable($date);
        $chart = $board->weekChart($date);

        if ($rows === []) {
            $this->info('Nobody has a lead target this week — nothing to report.');

            return self::SUCCESS;
        }

        $mail = fn (string $prefix = '') => new AutomatedEmail(
            mailSubject: $prefix.'Team week: '.$from->format('j M').' to '.$to->format('j M'),
            mailView: 'emails.automated.weekly-team-summary',
            mailData: [
                'weekLabel' => $from->format('j F').' to '.$to->format('j F Y'),
                'rows' => $rows,
                'days' => $chart,
                'teamLeads' => array_sum(array_column($rows, 'leads')),
                'teamLeadTarget' => array_sum(array_column($rows, 'lead_target')),
                'teamSalesWeek' => array_sum(array_column($rows, 'sales_week')),
                'teamSalesMonth' => array_sum(array_column($rows, 'sales_month')),
                'teamSalesTarget' => array_sum(array_column($rows, 'sales_target')),
                'monthLabel' => $to->format('F'),
            ],
        );

        if ($preview = $this->option('preview')) {
            $sender->send('preview:weekly-team:'.now()->format('YmdHis'), $preview, $mail('[Preview] '));
            $this->info("Sent a weekly team summary example to {$preview}.");

            return self::SUCCESS;
        }

        $sent = 0;

        foreach ($board->watchers() as $watcher) {
            $email = trim((string) $watcher->email);

            if ($email === '') {
                continue;
            }

            if ($this->option('dry-run')) {
                $this->line(sprintf('%-22s <%s>', $watcher->name, $email));

                continue;
            }

            if ($sender->send('weekly-team:'.$watcher->id.':'.$to->toDateString(), $email, $mail())) {
                $sent++;
            }
        }

        $this->info($this->option('dry-run')
            ? count($rows).' people in the table.'
            : "Sent {$sent} weekly team summary email(s) for the week to {$to->toDateString()}.");

        return self::SUCCESS;
    }
}
