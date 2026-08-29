<?php

namespace App\Console\Commands;

use App\Models\ContactConsent;
use App\Models\Notification;
use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Services\SuppressionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lifecycle automations.
 *
 * The schema already implied all of these and none of them ran: birthdays were
 * captured, validated, imported and displayed but read by nothing; licence
 * expiry could not even be computed; and lost leads and stale quotations were
 * never followed up.
 *
 * This command finds the candidates and raises internal tasks. It deliberately
 * does not send anything by itself - every outbound message still goes through
 * the campaign console, where suppression and consent are enforced and a human
 * approves the send.
 */
class RunMarketingAutomations extends Command
{
    protected $signature = 'marketing:automations
        {--only= : birthdays|renewals|winback|stale-quotes}
        {--dry-run : Report without creating tasks}';

    protected $description = 'Find birthday, renewal, win-back and stale-quote candidates';

    public function handle(SuppressionService $suppression): int
    {
        $only = $this->option('only');
        $dry = (bool) $this->option('dry-run');

        $results = [];

        if (! $only || $only === 'birthdays') {
            $results['Birthdays today'] = $this->birthdays($suppression, $dry);
        }
        if (! $only || $only === 'renewals') {
            $results['Licences expiring in 30 days'] = $this->renewals($dry);
        }
        if (! $only || $only === 'winback') {
            $results['Lost leads ready for win-back'] = $this->winBack($dry);
        }
        if (! $only || $only === 'stale-quotes') {
            $results['Quotations gone quiet'] = $this->staleQuotes($dry);
        }

        $this->table(
            ['Automation', 'Candidates'],
            collect($results)->map(fn ($n, $label) => [$label, $n])->values()->all()
        );

        if ($dry) {
            $this->warn('Dry run: no tasks created.');
        }

        return self::SUCCESS;
    }

    /**
     * Customers whose birthday is today and who may still be contacted.
     */
    private function birthdays(SuppressionService $suppression, bool $dry): int
    {
        if (! Schema::hasColumn('customers', 'birthday')) {
            return 0;
        }

        $today = now();

        $customers = Customer::query()
            ->whereNotNull('birthday')
            ->whereMonth('birthday', $today->month)
            ->whereDay('birthday', $today->day)
            ->get();

        $count = 0;
        foreach ($customers as $customer) {
            // Respect the opt-out before creating any outreach task.
            $contactable = ! $suppression->isSuppressed($customer->email, ContactConsent::CHANNEL_EMAIL)
                || ! $suppression->isSuppressed($customer->phone, ContactConsent::CHANNEL_SMS);

            if (! $contactable) {
                continue;
            }

            $count++;

            if (! $dry) {
                $this->task(
                    $customer,
                    'marketing_birthday',
                    'Birthday today: '.$customer->name,
                    'Send birthday greetings to '.$customer->name.'.'
                );
            }
        }

        return $count;
    }

    /**
     * Remote licences within 30 days of expiry.
     */
    private function renewals(bool $dry): int
    {
        if (! Schema::hasTable('customer_remote_licenses')
            || ! Schema::hasColumn('customer_remote_licenses', 'expires_at')) {
            return 0;
        }

        $rows = DB::table('customer_remote_licenses')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays(30)])
            ->get();

        if (! $dry) {
            foreach ($rows as $row) {
                $customer = Customer::find($row->customer_id);
                if (! $customer) {
                    continue;
                }

                $this->task(
                    $customer,
                    'marketing_renewal',
                    'Licence expiring: '.$customer->name,
                    'Licence expires on '.$row->expires_at.'. Arrange renewal.'
                );
            }
        }

        return $rows->count();
    }

    /**
     * Leads lost 90+ days ago, worth a fresh approach.
     */
    private function winBack(bool $dry): int
    {
        $leads = Lead::query()
            ->where('stage', 'lost')
            ->where('updated_at', '<=', now()->subDays(90))
            ->with('customer')
            ->limit(200)
            ->get();

        if (! $dry) {
            foreach ($leads as $lead) {
                if (! $lead->customer) {
                    continue;
                }

                $this->task(
                    $lead->customer,
                    'marketing_winback',
                    'Win-back: '.$lead->customer->name,
                    'Lost '.$lead->updated_at->diffForHumans().'. Reason: '.($lead->lost_reason ?: 'not recorded').'.',
                    $lead->assigned_to
                );
            }
        }

        return $leads->count();
    }

    /**
     * Quotations with no movement for 14 days.
     */
    private function staleQuotes(bool $dry): int
    {
        $leads = Lead::query()
            ->where('stage', 'quotation')
            ->where('updated_at', '<=', now()->subDays(14))
            ->with('customer')
            ->limit(200)
            ->get();

        if (! $dry) {
            foreach ($leads as $lead) {
                if (! $lead->customer) {
                    continue;
                }

                $this->task(
                    $lead->customer,
                    'marketing_stale_quote',
                    'Quote gone quiet: '.$lead->customer->name,
                    'No movement since '.$lead->updated_at->format('d M Y').'. Chase the quotation.',
                    $lead->assigned_to
                );
            }
        }

        return $leads->count();
    }

    /**
     * Raises an internal notification, avoiding a duplicate for the same
     * customer and type on the same day.
     */
    private function task(Customer $customer, string $type, string $title, string $message, ?int $userId = null): void
    {
        $recipient = $userId
            ?? $customer->leads()->latest('id')->value('assigned_to')
            ?? $customer->created_by;

        if (! $recipient) {
            return;
        }

        $alreadyRaised = Notification::query()
            ->where('type', $type)
            ->where('notifiable_id', $recipient)
            ->whereDate('created_at', now()->toDateString())
            ->whereJsonContains('data->customer_id', $customer->id)
            ->exists();

        if ($alreadyRaised) {
            return;
        }

        Notification::notifyUser($recipient, $type, $title, $message, ['customer_id' => $customer->id]);
    }
}
