<?php

namespace App\Services\Concerns;

use App\Models\Product;
use App\Support\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/** Line and document totals shared by invoices and orders (the browser editor mirrors this). */
trait CalculatesLines
{
    /**
     * @param  array  $items  product_id, unit_id, quantity, price, discount_percent?, tax_rate?
     * @return Collection<int, array> lines with 'product' model, amounts and 'net' (excl. tax, after line discount)
     */
    protected function calculateLines(array $items): Collection
    {
        $business = Tenant::business();
        $taxOn = $business->feature('tax');
        $inclusive = $business->feature('prices_include_tax');
        $products = Product::with('units', 'unit', 'taxRate')->whereIn('id', array_column($items, 'product_id'))->get()->keyBy('id');

        $lines = collect(array_values($items))->map(function ($item, $i) use ($products, $taxOn, $inclusive) {
            $product = $products->get($item['product_id']) ?? throw ValidationException::withMessages(["items.$i.product_id" => 'Invalid product.']);
            $factor = $this->factorFor($product, (int) $item['unit_id'], $i);

            $qty = round((float) $item['quantity'], 4);
            $price = round((float) $item['price'], 4);
            $gross = $qty * $price;
            $discPct = min(max((float) ($item['discount_percent'] ?? 0), 0), 100);
            $disc = round($gross * $discPct / 100, 2);
            $taxable = $gross - $disc;
            $rate = $taxOn ? (float) ($item['tax_rate'] ?? $product->taxRate?->rate ?? 0) : 0.0;
            $tax = $inclusive ? round($taxable * $rate / (100 + $rate), 2) : round($taxable * $rate / 100, 2);
            $amount = round($inclusive ? $taxable : $taxable + $tax, 2);

            return [
                'product' => $product,
                'product_id' => $product->id,
                'unit_id' => (int) $item['unit_id'],
                'factor' => $factor,
                'quantity' => $qty,
                'base_quantity' => round($qty * $factor, 4),
                'price' => $price,
                'discount_percent' => $discPct,
                'discount_amount' => $disc,
                'tax_rate' => $rate,
                'tax_amount' => $tax,
                'amount' => $amount,
                'net' => round($amount - $tax, 2),
            ];
        });

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Add at least one item.']);
        }

        return $lines;
    }

    protected function factorFor(Product $product, int $unitId, int $i): float
    {
        if ($unitId === $product->unit_id) {
            return 1.0;
        }
        $pu = $product->units->firstWhere('unit_id', $unitId)
            ?? throw ValidationException::withMessages(["items.$i.unit_id" => "Unit not set up for \"{$product->name}\"."]);

        return (float) $pu->factor;
    }

    protected function totals(Collection $lines, array $data): array
    {
        $linesTotal = round($lines->sum('amount'), 2);
        $discount = round(max((float) ($data['discount'] ?? 0), 0), 2);
        $other = round(max((float) ($data['other_charges'] ?? 0), 0), 2);
        $roundOff = round((float) ($data['round_off'] ?? 0), 2);
        $total = round($linesTotal - $discount + $other + $roundOff, 2);

        if ($total < 0) {
            throw ValidationException::withMessages(['discount' => 'Discount cannot exceed the document amount.']);
        }

        return [
            'subtotal' => round($lines->sum(fn ($l) => $l['quantity'] * $l['price']), 2),
            'item_discount' => round($lines->sum('discount_amount'), 2),
            'discount' => $discount,
            'tax_total' => round($lines->sum('tax_amount'), 2),
            'other_charges' => $other,
            'round_off' => $roundOff,
            'total' => $total,
        ];
    }
}
