<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sellable combination of a product's attribute values ("Red / M").
 * It has its own SKU, barcode, optional prices, average cost and stock.
 */
class ProductVariant extends Model
{
    use Auditable, BelongsToBusiness;

    protected $attributes = ['is_active' => true, 'avg_cost' => 0];

    protected $fillable = ['product_id', 'name', 'sku', 'barcode', 'sale_price', 'purchase_price', 'reorder_level', 'sort', 'is_active'];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:4', 'purchase_price' => 'decimal:4', 'avg_cost' => 'decimal:4',
            'reorder_level' => 'decimal:4', 'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function values(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product_variant');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'variant_id');
    }

    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    public function stockIn(?int $warehouseId = null): float
    {
        return (float) $this->balances()->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))->sum('quantity');
    }

    /** "T-Shirt — Red / M" */
    public function fullName(): string
    {
        return $this->loadMissing('product')->product->name.' — '.$this->name;
    }

    public function salePrice(): float
    {
        return (float) ($this->sale_price ?? $this->loadMissing('product')->product->sale_price);
    }

    public function purchasePrice(): float
    {
        return (float) ($this->purchase_price ?? $this->loadMissing('product')->product->purchase_price);
    }

    public function reorderLevel(): float
    {
        return (float) ($this->reorder_level ?? $this->loadMissing('product')->product->reorder_level);
    }
}
