<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    use BelongsToBusiness;

    public const TYPES = [
        'opening' => 'Opening', 'purchase' => 'Purchase', 'sale' => 'Sale', 'purchase_return' => 'Purchase Return',
        'sale_return' => 'Sale Return', 'adjustment' => 'Adjustment', 'transfer_in' => 'Transfer In', 'transfer_out' => 'Transfer Out', 'delivery' => 'Delivery Challan',
    ];

    protected $fillable = ['product_id', 'warehouse_id', 'batch_id', 'date', 'type', 'quantity', 'unit_cost', 'source_type', 'source_id', 'reason'];

    protected function casts(): array
    {
        return ['date' => 'date', 'quantity' => 'decimal:4', 'unit_cost' => 'decimal:4'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
