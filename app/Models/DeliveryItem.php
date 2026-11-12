<?php

namespace App\Models;

use App\Models\Concerns\HasVariantName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryItem extends Model
{
    use HasVariantName;

    public $timestamps = false;

    protected $fillable = ['delivery_id', 'order_item_id', 'product_id', 'variant_id', 'unit_id', 'factor', 'quantity', 'base_quantity', 'unit_cost'];

    protected function casts(): array
    {
        return ['factor' => 'decimal:4', 'quantity' => 'decimal:4', 'base_quantity' => 'decimal:4', 'unit_cost' => 'decimal:4'];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
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

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
