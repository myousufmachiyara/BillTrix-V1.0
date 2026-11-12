<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use Auditable, BelongsToBusiness;

    protected $attributes = ['track_batches' => false, 'has_variants' => false, 'track_stock' => true, 'is_active' => true];

    protected $fillable = ['category_id', 'unit_id', 'tax_rate_id', 'name', 'sku', 'barcode', 'hs_code', 'track_batches', 'has_variants', 'brand', 'description', 'image_path',
        'purchase_price', 'sale_price', 'min_sale_price', 'reorder_level', 'track_stock', 'is_active'];

    protected function casts(): array
    {
        return [
            'purchase_price' => 'decimal:4', 'sale_price' => 'decimal:4', 'min_sale_price' => 'decimal:4',
            'avg_cost' => 'decimal:4', 'reorder_level' => 'decimal:4',
            'track_stock' => 'boolean', 'track_batches' => 'boolean', 'has_variants' => 'boolean', 'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->orderBy('factor');
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort')->orderBy('id');
    }

    public function balances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    /** Text search on name / SKU / barcode, including pack barcodes. */
    public function scopeSearch(Builder $q, ?string $term): void
    {
        if (! $term = trim((string) $term)) {
            return;
        }
        $q->where(function (Builder $w) use ($term) {
            $w->where('name', 'like', "%{$term}%")
                ->orWhere('sku', $term)
                ->orWhere('barcode', $term)
                ->orWhereHas('units', fn (Builder $u) => $u->where('barcode', $term))
                ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', $term)->orWhere('barcode', $term));
        });
    }

    public function stockIn(?int $warehouseId = null): float
    {
        $q = $this->balances();
        if ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        }

        return (float) $q->sum('quantity');
    }

    /**
     * All selectable units for this product: the base unit plus packs.
     *
     * @return array<int, array{unit_id:int, name:string, factor:float, sale_price:?float, purchase_price:?float, barcode:?string}>
     */
    public function unitOptions(): array
    {
        $this->loadMissing('unit', 'units.unit');
        $options = [[
            'unit_id' => $this->unit_id, 'name' => $this->unit->short_name, 'factor' => 1.0,
            'sale_price' => null, 'purchase_price' => null, 'barcode' => $this->barcode,
        ]];
        foreach ($this->units as $pu) {
            $options[] = [
                'unit_id' => $pu->unit_id, 'name' => $pu->unit->short_name, 'factor' => (float) $pu->factor,
                'sale_price' => $pu->sale_price !== null ? (float) $pu->sale_price : null,
                'purchase_price' => $pu->purchase_price !== null ? (float) $pu->purchase_price : null,
                'barcode' => $pu->barcode,
            ];
        }

        return $options;
    }

    /**
     * The fields the line editors need for this product, or for one of its variations.
     *
     * @return array<string, mixed>
     */
    public function lineFields(?ProductVariant $variant = null): array
    {
        $units = $this->unitOptions();
        if ($variant) {
            $units[0]['barcode'] = $variant->barcode;
        }

        return [
            'product_id' => $this->id,
            'variant_id' => $variant?->id,
            'name' => $variant ? $this->name.' — '.$variant->name : $this->name,
            'units' => $units,
            'avg_cost' => (float) ($variant ?? $this)->avg_cost,
            'base_sale' => $variant ? $variant->salePrice() : (float) $this->sale_price,
            'base_purchase' => $variant ? $variant->purchasePrice() : (float) $this->purchase_price,
            'track_batches' => (bool) $this->track_batches,
        ];
    }

    /**
     * Base-unit sale price: the customer's own price if one is set, then their
     * price level, then the default sale price.
     */
    public function priceFor(?int $priceLevelId, ?float $customerPrice = null, ?ProductVariant $variant = null): float
    {
        if ($customerPrice !== null) {
            return $customerPrice;
        }
        if ($priceLevelId) {
            $this->loadMissing('prices');
            $p = $this->prices->firstWhere('price_level_id', $priceLevelId);
            if ($p) {
                return (float) $p->price;
            }
        }

        return $variant ? $variant->salePrice() : (float) $this->sale_price;
    }
}
