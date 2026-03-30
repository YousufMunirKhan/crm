<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function log(string $action, ?Model $model = null, array $oldValues = [], array $newValues = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model?->getMorphClass(),
            'auditable_id' => $model?->getKey(),
            'old_values' => !empty($oldValues) ? $oldValues : ($model?->getOriginal() ?? []),
            'new_values' => !empty($newValues) ? $newValues : ($model?->getAttributes() ?? []),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }
}


