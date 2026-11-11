<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Product;

/** Rebuild line-editor rows from old input so a validation error never loses what was typed. */
trait ReshowsOldLines
{
    protected function oldLines(): ?array
    {
        $old = old('items');
        if (! is_array($old) || ! $old) {
            return null;
        }
        $products = Product::with(['unit', 'units.unit'])->whereIn('id', array_column($old, 'product_id'))->get()->keyBy('id');

        return collect($old)->filter(fn ($i) => $products->has($i['product_id'] ?? 0))->map(function ($i) use ($products) {
            $p = $products[$i['product_id']];

            return [
                'id' => $i['id'] ?? null,
                'product_id' => $p->id,
                'name' => $p->name,
                'units' => $p->unitOptions(),
                'unit_id' => (int) ($i['unit_id'] ?? $p->unit_id),
                'quantity' => (float) ($i['quantity'] ?? 0),
                'price' => (float) ($i['price'] ?? 0),
                'discount_percent' => (float) ($i['discount_percent'] ?? 0),
                'tax_rate' => (float) ($i['tax_rate'] ?? 0),
                'source_item_id' => $i['source_item_id'] ?? null,
                'order_item_id' => $i['order_item_id'] ?? null,
                'delivery_item_id' => $i['delivery_item_id'] ?? null,
                'lock' => ! empty($i['delivery_item_id']),
                'avg_cost' => (float) $p->avg_cost,
                'base_sale' => (float) $p->sale_price,
                'base_purchase' => (float) $p->purchase_price,
                'stock' => null,
            ];
        })->values()->all();
    }
}
