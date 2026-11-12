<?php

namespace App\Services;

use App\Models\AttributeValue;
use App\Models\InvoiceItem;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates and updates a product's variations. Each row is one combination of
 * attribute values; SKU and barcode are generated when left blank.
 */
class VariantService
{
    public function __construct(private SequenceService $sequences) {}

    /**
     * @param  array<int, array{id?:int|null, value_ids:array<int>, sku?:?string, barcode?:?string, sale_price?:mixed, purchase_price?:mixed, reorder_level?:mixed, is_active?:bool}>  $rows
     */
    public function sync(Product $product, array $rows): void
    {
        DB::transaction(function () use ($product, $rows) {
            $values = AttributeValue::with('attribute')->whereIn('id', collect($rows)->pluck('value_ids')->flatten()->unique())->get()->keyBy('id');
            $existing = $product->variants()->with('values')->get()->keyBy('id');
            $seenCombos = [];
            $keep = [];

            foreach (array_values($rows) as $i => $row) {
                $ids = collect($row['value_ids'] ?? [])->map(fn ($v) => (int) $v)->filter(fn ($v) => $values->has($v))->unique()->values();
                if ($ids->isEmpty()) {
                    throw ValidationException::withMessages(["variants.$i.value_ids" => __('Pick at least one value for each variation.')]);
                }
                // Values must belong to this business and use each attribute at most once.
                $chosen = $ids->map(fn ($id) => $values[$id]);
                if ($chosen->contains(fn ($v) => (int) $v->attribute->business_id !== Tenant::idOrFail())) {
                    throw ValidationException::withMessages(["variants.$i.value_ids" => __('Invalid product.')]);
                }
                if ($chosen->pluck('attribute_id')->duplicates()->isNotEmpty()) {
                    throw ValidationException::withMessages(["variants.$i.value_ids" => __('A variation can have only one value per attribute.')]);
                }
                $chosen = $chosen->sortBy(fn ($v) => [$v->attribute->sort, $v->attribute_id])->values();
                $combo = $ids->sort()->implode('-');
                if (isset($seenCombos[$combo])) {
                    throw ValidationException::withMessages(["variants.$i.value_ids" => __('The variation ":name" is listed twice.', ['name' => $chosen->pluck('value')->implode(' / ')])]);
                }
                $seenCombos[$combo] = true;

                $variant = ! empty($row['id']) ? ($existing->get((int) $row['id']) ?? throw ValidationException::withMessages(["variants.$i.id" => __('Invalid product.')])) : new ProductVariant(['product_id' => $product->id]);
                $variant->fill([
                    'name' => $chosen->pluck('value')->implode(' / '),
                    'sku' => trim((string) ($row['sku'] ?? '')) ?: ($variant->sku ?: $this->sku($product, $chosen)),
                    'barcode' => trim((string) ($row['barcode'] ?? '')) ?: ($variant->barcode ?: $this->sequences->barcode(Tenant::idOrFail())),
                    'sale_price' => $this->money($row['sale_price'] ?? null),
                    'purchase_price' => $this->money($row['purchase_price'] ?? null),
                    'reorder_level' => $this->money($row['reorder_level'] ?? null),
                    'sort' => $i,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ]);
                $this->guardUnique($variant, $i);
                $variant->save();
                $variant->values()->sync($ids->all());
                $keep[] = $variant->id;
            }

            // Variations no longer listed: delete if never used, otherwise just switch them off.
            foreach ($existing->except($keep) as $old) {
                $this->inUse($old) ? $old->update(['is_active' => false]) : $old->delete();
            }

            if ($product->has_variants && $product->variants()->doesntExist()) {
                throw ValidationException::withMessages(['variants' => __('Add at least one variation.')]);
            }
        });
    }

    public function inUse(ProductVariant $variant): bool
    {
        return StockMovement::where('variant_id', $variant->id)->exists()
            || InvoiceItem::where('variant_id', $variant->id)->exists()
            || OrderItem::where('variant_id', $variant->id)->exists();
    }

    /** "TSHIRT" + "RED" + "M" → "TSHIRT-RED-M", made unique within the business. */
    private function sku(Product $product, $values): string
    {
        $base = ($product->sku ?: 'P'.$product->id).'-'.$values->map->skuCode()->implode('-');
        $sku = $base;
        for ($n = 2; ProductVariant::where('sku', $sku)->exists() || Product::where('sku', $sku)->exists(); $n++) {
            $sku = $base.'-'.$n;
        }

        return $sku;
    }

    /** SKU and barcode must be unique across products, pack barcodes and variations. */
    private function guardUnique(ProductVariant $variant, int $i): void
    {
        $taken = fn (string $col, string $value) => ProductVariant::where($col, $value)->when($variant->exists, fn ($q) => $q->whereKeyNot($variant->id))->exists()
            || Product::where($col, $value)->exists();

        if ($taken('sku', $variant->sku)) {
            throw ValidationException::withMessages(["variants.$i.sku" => __('The SKU ":v" is already used.', ['v' => $variant->sku])]);
        }
        if ($variant->barcode && ($taken('barcode', $variant->barcode)
            || ProductUnit::where('barcode', $variant->barcode)->whereHas('product', fn ($q) => $q->where('business_id', Tenant::idOrFail()))->exists())) {
            throw ValidationException::withMessages(["variants.$i.barcode" => __('The barcode ":v" is already used.', ['v' => $variant->barcode])]);
        }
    }

    private function money(mixed $v): ?float
    {
        return $v === null || $v === '' ? null : round((float) $v, 4);
    }
}
