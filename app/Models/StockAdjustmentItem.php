<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['stock_adjustment_id', 'product_id', 'system_quantity', 'quantity', 'unit_cost', 'batch_no', 'expiry_date'];

    protected function casts(): array
    {
        return ['system_quantity' => 'decimal:4', 'quantity' => 'decimal:4', 'unit_cost' => 'decimal:4'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
