<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tracked destination per marketing send, with a click tally.
 */
class CommunicationClick extends Model
{
    protected $fillable = [
        'sent_communication_id',
        'url',
        'url_hash',
        'click_count',
        'first_clicked_at',
        'last_clicked_at',
    ];

    protected $casts = [
        'first_clicked_at' => 'datetime',
        'last_clicked_at' => 'datetime',
    ];

    public function send(): BelongsTo
    {
        return $this->belongsTo(SentCommunication::class, 'sent_communication_id');
    }
}
