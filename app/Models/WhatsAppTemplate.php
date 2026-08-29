<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * WhatsApp message template.
 *
 * Single source of truth for the whatsapp_templates table. There were
 * previously two independent models mapped to it - this one and
 * App\Modules\Communication\Models\WhatsAppTemplate - with different fillable
 * lists and, more seriously, only one of them applying SoftDeletes. The module
 * model therefore returned soft-deleted rows and hard-deleted on delete.
 *
 * The module class now extends this one, so both names resolve to identical
 * behaviour and existing call sites keep working.
 */
class WhatsAppTemplate extends Model
{
    use HasAuditLog, SoftDeletes;

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        // Meta Cloud API fields
        'meta_template_id',
        'language',
        'parameter_format',
        'components_json',
        'status',
        'rejection_reason',

        // Local template fields
        'name',
        'category',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Templates Meta has approved for sending. */
    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'PENDING');
    }

    public function isApproved(): bool
    {
        return $this->status === 'APPROVED';
    }
}
