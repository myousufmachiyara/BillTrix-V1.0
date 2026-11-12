<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use BelongsToBusiness;

    public const UPDATED_AT = null;

    protected $fillable = ['business_id', 'user_id', 'event', 'auditable_type', 'auditable_id', 'label', 'old_values', 'new_values', 'ip_address', 'user_agent'];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Changed fields as [field => [old, new]]. */
    public function changes(): array
    {
        $old = $this->old_values ?? [];
        $new = $this->new_values ?? [];
        $out = [];
        foreach (array_unique([...array_keys($old), ...array_keys($new)]) as $k) {
            $out[$k] = [$old[$k] ?? null, $new[$k] ?? null];
        }

        return $out;
    }
}
