<?php

namespace App\Concerns;

use App\Models\AuditLog;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => static::writeAuditLog($model, 'created', null, $model->getAttributes()));

        static::updated(function ($model) {
            if ($model->wasChanged()) {
                static::writeAuditLog($model, 'updated', $model->getOriginal(), $model->getChanges());
            }
        });

        static::deleted(fn ($model) => static::writeAuditLog($model, 'deleted', $model->getOriginal(), null));
    }

    protected static function writeAuditLog($model, string $action, ?array $old, ?array $new): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()->ip() ?? '0.0.0.0',
        ]);
    }
}
