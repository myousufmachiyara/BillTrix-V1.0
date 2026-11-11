<?php

namespace App\Models\Concerns;

use App\Models\Business;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBusiness
{
    public static function bootBelongsToBusiness(): void
    {
        static::addGlobalScope('business', function (Builder $query) {
            if ($id = Tenant::id()) {
                $query->where($query->getModel()->qualifyColumn('business_id'), $id);
            }
        });

        static::creating(function ($model) {
            $model->business_id ??= Tenant::idOrFail();
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
