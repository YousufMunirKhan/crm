<?php

namespace App\Modules\Communication\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'meta_template_id',
        'name',
        'category',
        'language',
        'components_json',
        'status',
        'rejection_reason',
        // Legacy columns from existing table
        'message',
        'media_url',
        'media_type',
        'variables',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'components_json' => 'array',
        'variables' => 'array',
        'is_active' => 'boolean',
    ];

    /**
     * Get approved templates only
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    /**
     * Get pending templates
     */
    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    /**
     * Check if template is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }
}

