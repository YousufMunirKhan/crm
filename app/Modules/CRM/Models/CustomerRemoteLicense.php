<?php

namespace App\Modules\CRM\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerRemoteLicense extends Model
{
    protected $fillable = [
        'customer_id',
        'anydesk_rustdesk',
        'passwords',
        'epos_type',
        'lic_days',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
