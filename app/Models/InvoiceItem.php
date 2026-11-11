<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['invoice_id', 'product_id', 'unit_id', 'source_item_id', 'order_item_id', 'delivery_item_id', 'factor', 'quantity', 'base_quantity', 'price',
        'discount_percent', 'discount_amount', 'tax_rate', 'tax_amount', 'amount', 'unit_cost'];

    protected function casts(): array
    {
        return [
            'factor' => 'decimal:4', 'quantity' => 'decimal:4', 'base_quantity' => 'decimal:4', 'price' => 'decimal:4',
            'discount_percent' => 'decimal:3', 'discount_amount' => 'decimal:2', 'tax_rate' => 'decimal:3',
            'tax_amount' => 'decimal:2', 'amount' => 'decimal:2', 'unit_cost' => 'decimal:4',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
