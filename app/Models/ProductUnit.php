<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'unit_id', 'factor', 'barcode', 'sale_price', 'purchase_price'];

    protected function casts(): array
    {
        return ['factor' => 'decimal:4', 'sale_price' => 'decimal:4', 'purchase_price' => 'decimal:4'];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
