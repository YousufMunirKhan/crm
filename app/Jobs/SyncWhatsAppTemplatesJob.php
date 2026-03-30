<?php

namespace App\Jobs;

use App\Modules\Communication\Services\WhatsAppTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWhatsAppTemplatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(WhatsAppTemplateService $templateService): void
    {
        try {
            $result = $templateService->syncTemplates();
            
            Log::info('WhatsApp templates synced', [
                'synced' => $result['synced'],
                'errors' => $result['errors'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync WhatsApp templates', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
