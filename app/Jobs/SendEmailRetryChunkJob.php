<?php

namespace App\Jobs;

use App\Http\Controllers\EmailManagementController;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEmailRetryChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    /**
     * @param  int[]  $originalSentCommunicationIds
     */
    public function __construct(
        public array $originalSentCommunicationIds,
        public ?int $sentByUserId
    ) {
        $this->originalSentCommunicationIds = array_values(array_unique(array_filter(
            array_map('intval', $originalSentCommunicationIds),
            fn (int $id) => $id > 0
        )));
    }

    public function handle(EmailManagementController $emailManagement): void
    {
        foreach ($this->originalSentCommunicationIds as $id) {
            $result = $emailManagement->retryFailedSend($id, $this->sentByUserId);
            if (! ($result['ok'] ?? false)) {
                Log::info('Email retry skipped or failed', [
                    'original_sent_communication_id' => $id,
                    'message' => $result['message'] ?? null,
                ]);
            }
        }
    }
}
