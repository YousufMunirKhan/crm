<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Modules\Ticket\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Detects tickets that have passed their SLA due date and escalates them.
 *
 * sla_due_at was computed on creation and displayed on the ticket, but nothing
 * ever read it again - so a breach was only ever noticed by a human looking at
 * the right screen at the right moment.
 */
class CheckTicketSlaBreaches extends Command
{
    protected $signature = 'tickets:check-sla {--dry-run : Report without escalating}';

    protected $description = 'Escalate tickets that have breached their SLA';

    /** Statuses that are still the support team\'s problem. */
    private const OPEN_STATUSES = ['open', 'in_progress', 'on_hold'];

    public function handle(): int
    {
        $breached = Ticket::query()
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereNotNull('sla_due_at')
            ->where('sla_due_at', '<', now())
            ->where(function ($q) {
                // Escalate once, not on every run.
                $q->whereNull('sla_breached_at');
            })
            ->with(['customer', 'assignee'])
            ->get();

        if ($breached->isEmpty()) {
            $this->info('No SLA breaches.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->table(
                ['Ticket', 'Subject', 'Priority', 'Due'],
                $breached->map(fn (Ticket $t) => [
                    $t->ticket_number,
                    mb_strimwidth((string) $t->subject, 0, 40, '…'),
                    $t->priority,
                    optional($t->sla_due_at)->format('Y-m-d H:i'),
                ])->all()
            );
            $this->warn($breached->count().' ticket(s) would be escalated.');

            return self::SUCCESS;
        }

        foreach ($breached as $ticket) {
            $ticket->forceFill(['sla_breached_at' => now()])->save();

            // Notify the assignee where there is one; otherwise this is
            // surfaced by the breached list for a manager to pick up.
            if ($ticket->assigned_to) {
                Notification::notifyUser(
                    (int) $ticket->assigned_to,
                    'ticket_sla_breach',
                    'SLA breached: '.$ticket->ticket_number,
                    sprintf(
                        'Ticket "%s" passed its SLA on %s.',
                        $ticket->subject,
                        optional($ticket->sla_due_at)->format('d M Y H:i')
                    ),
                    ['ticket_id' => $ticket->id],
                );
            }

            Log::warning('Ticket SLA breached', [
                'ticket_number' => $ticket->ticket_number,
                'priority' => $ticket->priority,
                'sla_due_at' => optional($ticket->sla_due_at)->toIso8601String(),
            ]);
        }

        $this->info('Escalated '.$breached->count().' breached ticket(s).');

        return self::SUCCESS;
    }
}
