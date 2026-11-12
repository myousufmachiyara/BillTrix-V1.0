<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'price_level_id', 'price'];

    protected function casts(): array
    {
        return ['price' => 'decimal:4'];
    }

    public function priceLevel(): BelongsTo
    {
        return $this->belongsTo(PriceLevel::class);
    }
}
