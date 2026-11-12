<?php

namespace App\Models\Concerns;

use App\Support\Audit;

/** Records creates, updates and deletes in the audit trail (saveQuietly() is not logged). */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => Audit::model('created', $model));
        static::updated(fn ($model) => Audit::model('updated', $model));
        static::deleted(fn ($model) => Audit::model('deleted', $model));
    }
}
