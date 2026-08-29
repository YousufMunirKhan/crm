<?php

namespace App\Services;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Uses Claude to infer business email / UK phone from plain website text (after HTTP fetch).
 * Only fills gaps; output is validated locally. Requires API key in env or Settings.
 */
class ClaudeContactExtractionService
{
    public function apiKey(): string
    {
        $k = trim((string) config('anthropic.api_key', ''));
        if ($k !== '') {
            return $k;
        }

        $db = Setting::where('key', 'anthropic_api_key')->value('value');

        return $db !== null ? trim((string) $db) : '';
    }

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    public function model(): string
    {
        $m = trim((string) (Setting::where('key', 'anthropic_model')->value('value') ?? ''));

        return $m !== '' ? $m : (string) config('anthropic.model');
    }

    /**
     * When false, no Claude calls are made even if a key exists.
     */
    public function isEnabledForEnrichment(): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }
        $flag = Setting::where('key', 'cold_calling_use_claude')->value('value');
        if ($flag === '0' || $flag === 'false') {
            return false;
        }

        return true;
    }

    /**
     * @return array{email: ?string, phone: ?string}
     */
    public function extractFromWebsiteText(string $plainTextCorpus, ?string $businessName = null): array
    {
        $plainTextCorpus = trim($plainTextCorpus);
        if ($plainTextCorpus === '' || ! $this->isEnabledForEnrichment()) {
            return ['email' => null, 'phone' => null];
        }

        $plainTextCorpus = Str::limit($plainTextCorpus, 45000, '…');

        $nameLine = $businessName !== null && trim($businessName) !== ''
            ? 'Business name (hint): '.trim($businessName)."\n\n"
            : '';

        $userMessage = $nameLine
            ."You are extracting PUBLIC contact details from website text copied from HTML (footer, contact page, etc.).\n"
            ."Rules:\n"
            ."- Return ONLY a single JSON object, no markdown fences, no explanation.\n"
            ."- Keys exactly: \"email\" and \"phone\" (use null if not clearly present).\n"
            ."- email: a single business contact email (info@, hello@, contact@, etc.). Never noreply@, donotreply@, or example.com.\n"
            ."- phone: UK national format starting with 0 if possible (e.g. 02071234567 or 07700900123). null if not found.\n"
            ."- Do NOT invent or guess. If unsure, use null.\n\n"
            ."WEBSITE TEXT:\n"
            .$plainTextCorpus;

        try {
            $response = Http::timeout((int) config('anthropic.timeout', 60))
                ->withHeaders([
                    'x-api-key' => $this->apiKey(),
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                // No `temperature`: newer models reject it outright with a 400,
                // and it was only ever set to 0 for determinism the prompt
                // already enforces.
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->model(),
                    'max_tokens' => (int) config('anthropic.max_tokens', 512),
                    'messages' => [
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::error('Claude contact extraction HTTP failed', ['message' => $e->getMessage()]);

            return ['email' => null, 'phone' => null];
        }

        if (! $response->successful()) {
            // Logged at error level on purpose. This failed at warning level
            // for months - a retired model id returning 404 on every call -
            // and nothing surfaced it because production logs at error.
            Log::error('Claude API error', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            return ['email' => null, 'phone' => null];
        }

        $json = $response->json();
        $text = '';
        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text' && isset($block['text'])) {
                $text .= $block['text'];
            }
        }
        $text = trim($text);
        if ($text === '') {
            return ['email' => null, 'phone' => null];
        }

        $parsed = $this->parseJsonObject($text);
        if (! is_array($parsed)) {
            Log::warning('Claude response not valid JSON object', ['preview' => Str::limit($text, 200)]);

            return ['email' => null, 'phone' => null];
        }

        $email = isset($parsed['email']) && is_string($parsed['email']) ? trim($parsed['email']) : null;
        $phone = isset($parsed['phone']) && is_string($parsed['phone']) ? trim($parsed['phone']) : null;
        if ($email === '') {
            $email = null;
        }
        if ($phone === '') {
            $phone = null;
        }

        return ['email' => $email, 'phone' => $phone];
    }

    /**
     * @return ?array<string, mixed>
     */
    private function parseJsonObject(string $text): ?array
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $text, $m)) {
            $text = trim($m[1]);
        }
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $slice = substr($text, $start, $end - $start + 1);
            $decoded = json_decode($slice, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }
}
