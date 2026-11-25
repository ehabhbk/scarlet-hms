<?php

namespace App\Support\Audit;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model) {
            app(AuditLogger::class)->log('created', $model, [], $model->getAttributes());
        });

        static::updated(function ($model) {
            app(AuditLogger::class)->log(
                'updated',
                $model,
                $model->getOriginal(),
                $model->getChanges()
            );
        });

        static::deleted(function ($model) {
            app(AuditLogger::class)->log('deleted', $model, $model->getAttributes(), []);
        });
    }
}
