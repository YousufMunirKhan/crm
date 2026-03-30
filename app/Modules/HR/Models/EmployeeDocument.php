<?php

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasAuditLog;

class EmployeeDocument extends Model
{
    use HasAuditLog;

    protected $fillable = [
        'user_id',
        'name',
        'file_path',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}

