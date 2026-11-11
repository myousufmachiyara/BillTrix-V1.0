<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    use BelongsToBusiness;

    public const REASONS = ['damaged' => 'Damaged', 'expired' => 'Expired', 'lost' => 'Lost / Theft', 'found' => 'Found / Excess',
        'count' => 'Physical Count', 'sample' => 'Sample / Own Use', 'other' => 'Other'];

    protected $fillable = ['warehouse_id', 'number', 'date', 'type', 'reason', 'notes', 'total_value', 'created_by'];

    protected function casts(): array
    {
        return ['date' => 'date', 'total_value' => 'decimal:2'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
