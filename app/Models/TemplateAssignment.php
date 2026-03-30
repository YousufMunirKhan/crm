<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplateAssignment extends Model
{
    protected $fillable = [
        'function_type',
        'template_type',
        'template_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function emailTemplate()
    {
        return $this->belongsTo(\App\Models\EmailTemplate::class, 'template_id');
    }

    public function messageTemplate()
    {
        return $this->belongsTo(\App\Models\MessageTemplate::class, 'template_id');
    }

    public function whatsappTemplate()
    {
        return $this->belongsTo(\App\Models\WhatsAppTemplate::class, 'template_id');
    }
}
