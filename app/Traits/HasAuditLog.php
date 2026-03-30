<?php

namespace App\Traits;

use App\Services\AuditLogService;

trait HasAuditLog
{
    public static function bootHasAuditLog(): void
    {
        static::created(function ($model) {
            app(AuditLogService::class)->log('created', $model);
        });

        static::updated(function ($model) {
            app(AuditLogService::class)->log('updated', $model);
        });

        static::deleted(function ($model) {
            app(AuditLogService::class)->log('deleted', $model);
        });
    }
}


