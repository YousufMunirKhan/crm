<?php

namespace App\Modules\Communication\Support;

use App\Modules\Communication\Exceptions\WhatsAppGraphApiException;
use App\Modules\Communication\Jobs\SendCommunicationJob;

class WhatsAppApiErrorResponse
{
    /**
     * @return array{message:string,hint:string,meta_error?:array}
     */
    public static function fromThrowable(\Throwable $e, string $messagePrefix = 'Failed to send WhatsApp message'): array
    {
        $hint = SendCommunicationJob::whatsappMetaUserHint($e->getMessage());

        if ($e instanceof WhatsAppGraphApiException) {
            $graphMsg = $e->graphError['message'] ?? $e->getMessage();

            return [
                'message' => $messagePrefix . ': ' . $graphMsg,
                'hint' => $hint,
                'meta_error' => $e->toMetaErrorArray(),
            ];
        }

        return [
            'message' => $messagePrefix . ': ' . $e->getMessage(),
            'hint' => $hint,
        ];
    }
}
