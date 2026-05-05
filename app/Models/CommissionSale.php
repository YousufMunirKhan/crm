<?php

namespace App\Models;

use App\Modules\CRM\Models\Customer;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Models\LeadItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionSale extends Model
{
    protected $fillable = [
        'lead_id',
        'lead_item_id',
        'customer_id',
        'credited_user_id',
        'assigned_by_user_id',
        'commission_amount',
        'commission_currency',
        'commission_role',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'commission_amount' => 'decimal:2',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function leadItem(): BelongsTo
    {
        return $this->belongsTo(LeadItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function creditedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'credited_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
