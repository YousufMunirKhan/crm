<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ColdCallingContactPostcode extends Model
{
    protected $table = 'cold_calling_contact_postcode';

    protected $fillable = [
        'cold_calling_contact_id',
        'postcode_normalized',
        'cold_calling_run_id',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(ColdCallingContact::class, 'cold_calling_contact_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ColdCallingRun::class, 'cold_calling_run_id');
    }
}
