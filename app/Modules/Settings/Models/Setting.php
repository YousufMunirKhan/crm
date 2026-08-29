<?php

namespace App\Modules\Settings\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    // Settings include the SMTP relay and integration keys.
    use HasAuditLog;

    protected $fillable = ['key', 'value'];

    public $timestamps = false;
}

