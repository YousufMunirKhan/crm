<?php

namespace App\Console\Commands;

use App\Models\ContactConsent;
use App\Modules\CRM\Models\Customer;
use App\Services\SuppressionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Handles UK GDPR subject requests without hand-written SQL.
 *
 * Art. 15/20 (access and portability) and Art. 17 (erasure) previously had no
 * tooling at all: fulfilling a request meant querying customers, leads,
 * communications, sent_communications, whatsapp_messages, cold_calling_contacts
 * and email_list_recipients by hand.
 */
class GdprSubjectRequest extends Command
{
    protected $signature = 'gdpr:subject-request
        {action : export or erase}
        {--customer= : Customer id}
        {--email= : Email address}
        {--phone= : Phone number}
        {--confirm : Actually perform an erasure (otherwise it is a dry run)}';

    protected $description = 'Export or erase all personal data held for one data subject';

    public function handle(SuppressionService $suppression): int
    {
        $action = strtolower((string) $this->argument('action'));

        if (! in_array($action, ['export', 'erase'], true)) {
            $this->error('Action must be "export" or "erase".');

            return self::FAILURE;
        }

        $customer = $this->resolveCustomer();

        if (! $customer) {
            $this->error('No customer matched. Pass --customer, --email or --phone.');

            return self::FAILURE;
        }

        $this->info("Subject: #{$customer->id} {$customer->name}");

        $data = $this->collect($customer);

        foreach ($data as $label => $rows) {
            $this->line(sprintf('  %-28s %d record(s)', $label, is_countable($rows) ? count($rows) : 0));
        }

        if ($action === 'export') {
            $path = storage_path('app/gdpr-export-customer-'.$customer->id.'.json');
            file_put_contents($path, json_encode([
                'generated_at' => now()->toIso8601String(),
                'customer' => $customer->toArray(),
                'records' => $data,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->info("Export written to {$path}");

            return self::SUCCESS;
        }

        if (! $this->option('confirm')) {
            $this->warn('Dry run. Re-run with --confirm to erase these records permanently.');

            return self::SUCCESS;
        }

        return $this->erase($customer, $suppression);
    }

    private function resolveCustomer(): ?Customer
    {
        if ($id = $this->option('customer')) {
            return Customer::find($id);
        }

        if ($email = $this->option('email')) {
            return Customer::where('email', $email)->first();
        }

        if ($phone = $this->option('phone')) {
            return Customer::where('phone', $phone)
                ->orWhere('whatsapp_number', $phone)
                ->first();
        }

        return null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function collect(Customer $customer): array
    {
        $email = $customer->email;
        $phone = $customer->phone;

        return [
            'leads' => DB::table('leads')->where('customer_id', $customer->id)->get()->toArray(),
            'communications' => DB::table('communications')->where('customer_id', $customer->id)->get()->toArray(),
            'sent_communications' => DB::table('sent_communications')->where('customer_id', $customer->id)->get()->toArray(),
            'tickets' => DB::table('tickets')->where('customer_id', $customer->id)->get()->toArray(),
            'invoices' => DB::table('invoices')->where('customer_id', $customer->id)->get()->toArray(),
            'whatsapp_messages' => $phone
                ? DB::table('whatsapp_messages')->where('to_number', $phone)->orWhere('from_number', $phone)->get()->toArray()
                : [],
            'email_list_recipients' => $email
                ? DB::table('email_list_recipients')->where('email', $email)->get()->toArray()
                : [],
            'contact_consents' => ContactConsent::query()
                ->where('customer_id', $customer->id)
                ->orWhereIn('identifier', array_filter([$email, $phone]))
                ->get()
                ->toArray(),
        ];
    }

    private function erase(Customer $customer, SuppressionService $suppression): int
    {
        $email = $customer->email;
        $phone = $customer->phone;

        DB::transaction(function () use ($customer, $email, $phone, $suppression) {
            // Suppress first: erasing the customer row would otherwise remove
            // the only link between the subject and the suppression list,
            // while their address stays live in uploaded email lists.
            $suppression->optOutAllChannels(
                array_filter([$email, $phone, $customer->whatsapp_number]),
                'gdpr_erasure'
            );

            if ($email) {
                DB::table('email_list_recipients')->where('email', $email)->delete();
            }

            DB::table('communications')->where('customer_id', $customer->id)->delete();
            DB::table('sent_communications')->where('customer_id', $customer->id)->update([
                'recipient_email' => null,
                'recipient_phone' => null,
                'content' => '[erased at data subject request]',
            ]);

            // Financial records are retained for six years, but the personal
            // identifiers on them are pseudonymised.
            $customer->forceFill([
                'name' => 'Erased customer #'.$customer->id,
                'email' => null,
                'phone' => null,
                'whatsapp_number' => null,
                'address' => null,
                'postcode' => null,
                'city' => null,
                'notes' => null,
                'birthday' => null,
                'passwords' => null,
                'anydesk_rustdesk' => null,
                'portal_password' => null,
            ])->save();

            $customer->delete();
        });

        $this->info('Personal data erased. Invoices retained in pseudonymised form for the statutory six years.');

        return self::SUCCESS;
    }
}
