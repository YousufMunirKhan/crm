<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Sends one of every automated email to a single address.
 *
 * Each of them previews itself rather than being rebuilt here, so what arrives
 * is the template as it actually goes out - same builder, same data, same
 * attachments. A preview assembled separately is a preview of the preview.
 */
class PreviewAutomatedEmails extends Command
{
    protected $signature = 'emails:preview {--to= : The address to send every template to}';

    protected $description = 'Send an example of every automated email to one address';

    /** Every command that knows how to show itself. */
    private const COMMANDS = [
        'emails:appointment-reminders',
        'emails:rep-day',
        'emails:follow-up-digest',
        'emails:invoice-due',
        'emails:invoice-overdue',
        'emails:daily-lead-summary',
    ];

    public function handle(): int
    {
        $to = trim((string) $this->option('to'));

        if ($to === '' || ! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Give me an address: --to=someone@example.com');

            return self::FAILURE;
        }

        foreach (self::COMMANDS as $command) {
            $this->line("— {$command}");
            Artisan::call($command, ['--preview' => $to], $this->output);
        }

        $this->newLine();
        $this->info("Every automated email sent to {$to}.");

        return self::SUCCESS;
    }
}
