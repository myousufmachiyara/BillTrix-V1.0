<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'product_id', 'unit_id', 'factor', 'quantity', 'base_quantity', 'fulfilled_quantity', 'price',
        'discount_percent', 'discount_amount', 'tax_rate', 'tax_amount', 'amount'];

    protected function casts(): array
    {
        return [
            'factor' => 'decimal:4', 'quantity' => 'decimal:4', 'base_quantity' => 'decimal:4', 'fulfilled_quantity' => 'decimal:4',
            'price' => 'decimal:4', 'discount_percent' => 'decimal:3', 'discount_amount' => 'decimal:2', 'tax_rate' => 'decimal:3',
            'tax_amount' => 'decimal:2', 'amount' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Base quantity still to deliver / receive / invoice. */
    public function getPendingBaseAttribute(): float
    {
        return max(round((float) $this->base_quantity - (float) $this->fulfilled_quantity, 4), 0);
    }

    /** Pending quantity in the ordered unit. */
    public function getPendingAttribute(): float
    {
        return round($this->pending_base / max((float) $this->factor, 0.0001), 4);
    }
}
