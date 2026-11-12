<?php

namespace App\Models;

use App\Models\Concerns\HasVariantName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItem extends Model
{
    use HasVariantName;

    public $timestamps = false;

    protected $fillable = ['stock_transfer_id', 'product_id', 'variant_id', 'unit_id', 'factor', 'quantity'];

    protected function casts(): array
    {
        return ['factor' => 'decimal:4', 'quantity' => 'decimal:4'];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
