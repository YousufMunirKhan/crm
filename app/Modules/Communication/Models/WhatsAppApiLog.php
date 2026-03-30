<?php

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppApiLog extends Model
{
    protected $table = 'whatsapp_api_logs';

    protected $fillable = [
        'correlation_id',
        'direction',
        'endpoint',
        'method',
        'status_code',
        'request_json',
        'response_json',
        'error',
    ];

    protected $casts = [
        'request_json' => 'array',
        'response_json' => 'array',
        'status_code' => 'integer',
    ];

    /**
     * Redact sensitive data from request/response
     */
    public static function redactSensitiveData(array $data): array
    {
        $sensitiveKeys = ['access_token', 'token', 'password', 'secret', 'authorization'];
        
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $data[$key] = self::redactSensitiveData($value);
            }
        }

        return $data;
    }
}

