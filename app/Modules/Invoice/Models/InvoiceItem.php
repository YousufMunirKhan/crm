<?php

namespace App\Modules\Invoice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'product_id',
        'description',
        'quantity',
        'unit_price',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * The catalogue product this line was raised from, when known. Nullable:
     * historic lines are free text and were backfilled only on an unambiguous
     * name match.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\CRM\Models\Product::class);
    }
}


