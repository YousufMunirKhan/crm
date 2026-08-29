<?php

namespace App\Jobs;

use App\Http\Controllers\EmailManagementController;
use App\Models\EmailTemplate;
use App\Modules\CRM\Models\Customer;
use App\Services\MailConfigFromDatabase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends one chunk of a bulk marketing email campaign.
 *
 * Bulk sends previously ran inline in the HTTP request, pacing at ~1.2s per
 * recipient - so a list of any real size hit the PHP timeout part-way through,
 * with no resume and no record of where it stopped.
 */
class SendBulkEmailChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 900;

    /**
     * @param  list<int>  $customerIds
     */
    public function __construct(
        public array $customerIds,
        public int $templateId,
        public ?int $sentByUserId
    ) {
        $this->customerIds = array_values(array_unique(array_filter(
            array_map('intval', $customerIds),
            fn (int $id) => $id > 0
        )));
    }

    public function handle(EmailManagementController $emailManagement): void
    {
        $template = EmailTemplate::find($this->templateId);

        if (! $template) {
            Log::warning('Bulk email chunk skipped: template missing', ['template_id' => $this->templateId]);

            return;
        }

        MailConfigFromDatabase::apply();

        $delayMs = (int) config('email_send.between_message_delay_ms', 1200);

        Customer::whereIn('id', $this->customerIds)
            ->orderBy('id')
            ->chunkById(50, function ($customers) use ($emailManagement, $template, $delayMs) {
                foreach ($customers as $customer) {
                    $emailManagement->sendTemplateToOneRecipient($template, $customer, $this->sentByUserId);

                    if ($delayMs > 0) {
                        usleep($delayMs * 1000);
                    }
                }
            });
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Bulk email chunk failed', [
            'template_id' => $this->templateId,
            'recipients' => count($this->customerIds),
            'error' => $e->getMessage(),
        ]);
    }
}
