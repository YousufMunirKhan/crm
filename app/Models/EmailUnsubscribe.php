<?php

namespace App\Models;

use App\Services\SuppressionService;
use Illuminate\Database\Eloquent\Model;

/**
 * Legacy email suppression list.
 *
 * The table is retained so historic rows stay readable, but it is no longer
 * the source of truth: reads and writes go to contact_consents via
 * SuppressionService, which covers SMS and WhatsApp as well. Existing call
 * sites keep working unchanged.
 *
 * @deprecated Use SuppressionService directly for new code.
 */
class EmailUnsubscribe extends Model
{
    protected $fillable = ['email', 'unsubscribed_at'];

    protected $casts = [
        'unsubscribed_at' => 'datetime',
    ];

    public static function isUnsubscribed(string $email): bool
    {
        if (trim($email) === '') {
            return false;
        }

        return app(SuppressionService::class)
            ->isSuppressed($email, ContactConsent::CHANNEL_EMAIL);
    }

    public static function unsubscribe(string $email, string $source = 'unsubscribe_link'): bool
    {
        $email = strtolower(trim($email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        // Keep the legacy table in step for anything still reading it directly.
        static::updateOrCreate(['email' => $email], ['unsubscribed_at' => now()]);

        return app(SuppressionService::class)
            ->optOut($email, ContactConsent::CHANNEL_EMAIL, $source);
    }
}
