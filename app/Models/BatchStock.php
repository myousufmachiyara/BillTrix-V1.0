<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchStock extends Model
{
    public $timestamps = false;

    protected $fillable = ['batch_id', 'warehouse_id', 'quantity'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
