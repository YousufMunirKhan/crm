<?php

namespace App\Models;

use App\Modules\CRM\Models\Customer;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One consent/suppression record per (identifier, channel).
 */
class ContactConsent extends Model
{
    // Consent records are the evidence relied on under PECR.
    use HasAuditLog;

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const STATUS_OPT_IN = 'opt_in';
    public const STATUS_OPT_OUT = 'opt_out';
    public const STATUS_UNKNOWN = 'unknown';

    protected $fillable = [
        'identifier',
        'channel',
        'status',
        'opt_in_at',
        'opt_out_at',
        'source',
        'lawful_basis',
        'evidence',
        'customer_id',
    ];

    protected $casts = [
        'opt_in_at' => 'datetime',
        'opt_out_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return list<string>
     */
    public static function channels(): array
    {
        return [self::CHANNEL_EMAIL, self::CHANNEL_SMS, self::CHANNEL_WHATSAPP];
    }
}
