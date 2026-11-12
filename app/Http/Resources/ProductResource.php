<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'hs_code' => $this->hs_code,
            'brand' => $this->brand,
            'category' => $this->whenLoaded('category', fn () => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null),
            'base_unit' => $this->whenLoaded('unit', fn () => ['id' => $this->unit->id, 'name' => $this->unit->name, 'short_name' => $this->unit->short_name]),
            'units' => $this->whenLoaded('units', fn () => $this->unitOptions()),
            'sale_price' => (float) $this->sale_price,
            'min_sale_price' => (float) $this->min_sale_price,
            'purchase_price' => $this->when($request->user()?->can('purchases.view'), (float) $this->purchase_price),
            'prices' => $this->whenLoaded('prices', fn () => $this->prices->map(fn ($p) => ['price_level_id' => $p->price_level_id, 'price' => (float) $p->price])),
            'tax_rate' => (float) ($this->taxRate?->rate ?? 0),
            'stock' => $this->when(isset($this->stock), fn () => (float) $this->stock),
            'reorder_level' => (float) $this->reorder_level,
            'track_stock' => $this->track_stock,
            'track_batches' => $this->track_batches,
            'has_variants' => (bool) $this->has_variants,
            // Each variation is sold on its own: send variant_id with the line.
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($v) => [
                'id' => $v->id, 'name' => $v->name, 'sku' => $v->sku, 'barcode' => $v->barcode,
                'sale_price' => (float) ($v->sale_price ?? $this->sale_price),
                'purchase_price' => $this->when($request->user()?->can('purchases.view'), (float) ($v->purchase_price ?? $this->purchase_price)),
                'stock' => $v->relationLoaded('balances') ? (float) $v->balances->sum('quantity') : null,
            ])->values()),
            'image_url' => $this->image_path ? asset('storage/'.$this->image_path) : null,
            'is_active' => $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
