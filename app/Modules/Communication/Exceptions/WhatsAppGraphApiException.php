<?php

namespace App\Modules\Communication\Exceptions;

/**
 * Thrown when Meta Graph returns an error body for WhatsApp Cloud API calls.
 */
class WhatsAppGraphApiException extends \Exception
{
    public function __construct(
        public array $graphError,
        public int $httpStatus,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function fromGraphResponse(int $httpStatus, array $responseData): self
    {
        $error = is_array($responseData['error'] ?? null) ? $responseData['error'] : [];
        $errorMessage = $error['message'] ?? 'Unknown error';
        $errorCode = $error['code'] ?? null;
        $errorType = $error['type'] ?? null;
        $errorSubcode = $error['error_subcode'] ?? null;
        $fbTraceId = $error['fbtrace_id'] ?? null;

        $parts = [
            "WhatsApp API HTTP {$httpStatus}",
            $errorType ? "type={$errorType}" : null,
            $errorCode !== null ? "code={$errorCode}" : null,
            $errorSubcode !== null ? "subcode={$errorSubcode}" : null,
            "message={$errorMessage}",
            $fbTraceId ? "fbtrace_id={$fbTraceId}" : null,
        ];
        $parts = array_values(array_filter($parts, fn ($p) => $p !== null && $p !== ''));

        return new self($error, $httpStatus, implode(' | ', $parts));
    }

    /**
     * @return array{http_status:int,message:?string,code:?int,error_subcode:?int,type:?string,fbtrace_id:?string}
     */
    public function toMetaErrorArray(): array
    {
        return [
            'http_status' => $this->httpStatus,
            'message' => isset($this->graphError['message']) ? (string) $this->graphError['message'] : null,
            'code' => $this->graphError['code'] ?? null,
            'error_subcode' => $this->graphError['error_subcode'] ?? null,
            'type' => isset($this->graphError['type']) ? (string) $this->graphError['type'] : null,
            'fbtrace_id' => isset($this->graphError['fbtrace_id']) ? (string) $this->graphError['fbtrace_id'] : null,
        ];
    }
}
