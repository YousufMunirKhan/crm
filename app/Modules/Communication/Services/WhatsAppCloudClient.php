<?php

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Models\WhatsAppApiLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class WhatsAppCloudClient
{
    private string $baseUrl = 'https://graph.facebook.com';
    private ?string $graphVersion;
    private ?string $accessToken;
    private int $timeout = 30;

    public function __construct(?string $accessToken = null, ?string $graphVersion = null)
    {
        $this->accessToken = $accessToken;
        $this->graphVersion = $graphVersion ?? config('services.whatsapp.graph_version', 'v20.0');
    }

    /**
     * Set access token
     */
    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    /**
     * Set graph version
     */
    public function setGraphVersion(string $version): self
    {
        $this->graphVersion = $version;
        return $this;
    }

    /**
     * Send message to WhatsApp
     */
    public function sendMessage(string $phoneNumberId, array $payload): array
    {
        $endpoint = "/{$this->graphVersion}/{$phoneNumberId}/messages";
        return $this->makeRequest('POST', $endpoint, $payload);
    }

    /**
     * Create template
     */
    public function createTemplate(string $wabaId, array $payload): array
    {
        $endpoint = "/{$this->graphVersion}/{$wabaId}/message_templates";
        return $this->makeRequest('POST', $endpoint, $payload);
    }

    /**
     * List templates
     */
    public function listTemplates(string $wabaId, array $params = []): array
    {
        $endpoint = "/{$this->graphVersion}/{$wabaId}/message_templates";
        return $this->makeRequest('GET', $endpoint, $params);
    }

    /**
     * Make HTTP request to Meta API
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        if (!$this->accessToken) {
            throw new \Exception('WhatsApp access token not configured');
        }

        $url = $this->baseUrl . $endpoint;
        $correlationId = Str::uuid()->toString();

        // Redact sensitive data for logging
        $logData = WhatsAppApiLog::redactSensitiveData($data);

        try {
            $http = Http::withHeaders([
                'Authorization' => "Bearer {$this->accessToken}",
                'Content-Type' => 'application/json',
            ])->timeout($this->timeout);

            if ($method === 'GET') {
                $response = $http->get($url, $data);
            } else {
                $response = $http->post($url, $data);
            }

            $responseData = $response->json();
            $statusCode = $response->status();

            // Log the request/response
            WhatsAppApiLog::create([
                'correlation_id' => $correlationId,
                'direction' => 'OUTBOUND',
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => $statusCode,
                'request_json' => $logData,
                'response_json' => $responseData,
                'error' => $response->failed() ? ($responseData['error']['message'] ?? 'Unknown error') : null,
            ]);

            if ($response->failed()) {
                $errorMessage = $responseData['error']['message'] ?? 'Unknown error';
                throw new \Exception("WhatsApp API error: {$errorMessage}");
            }

            return $responseData;
        } catch (\Exception $e) {
            // Log error
            WhatsAppApiLog::create([
                'correlation_id' => $correlationId,
                'direction' => 'OUTBOUND',
                'endpoint' => $endpoint,
                'method' => $method,
                'status_code' => null,
                'request_json' => $logData,
                'response_json' => null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

