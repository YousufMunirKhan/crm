<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadItem extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'lead_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
        'notes',
        'status',
        'closed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'closed_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_WON = 'won';
    const STATUS_LOST = 'lost';

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $item->total_price = $item->quantity * $item->unit_price;
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

