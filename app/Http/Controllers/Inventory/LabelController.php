<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Barcode labels for products and variations, on A4 sheets or label rolls. */
class LabelController extends Controller
{
    public const SIZES = [
        'a4-3x8' => ['A4 sheet — 3 × 8 (70 × 37 mm)', 70, 37],
        'a4-4x10' => ['A4 sheet — 4 × 10 (52 × 29 mm)', 52, 29],
        'roll-38x25' => ['Label roll — 38 × 25 mm', 38, 25],
        'roll-50x25' => ['Label roll — 50 × 25 mm', 50, 25],
    ];

    public function index(Request $request): View
    {
        $this->allow('products.labels');
        $rows = [];
        if ($product = Product::with(['variants' => fn ($v) => $v->where('is_active', true)->with('balances')])->find($request->integer('product_id'))) {
            $rows = $product->has_variants
                ? $product->variants->map(fn ($v) => ['product_id' => $product->id, 'variant_id' => $v->id, 'name' => $product->name.' — '.$v->name,
                    'code' => $v->barcode ?: $v->sku, 'copies' => max(1, (int) ceil((float) $v->balances->sum('quantity')))])->all()
                : [['product_id' => $product->id, 'variant_id' => null, 'name' => $product->name, 'code' => $product->barcode ?: $product->sku,
                    'copies' => max(1, (int) ceil($product->stockIn()))]];
        }

        return view('inventory.labels.index', ['rows' => $rows, 'sizes' => self::SIZES]);
    }

    public function print(Request $request): View
    {
        $this->allow('products.labels');
        $data = $request->validate([
            'size' => ['required', 'in:'.implode(',', array_keys(self::SIZES))],
            'show_price' => ['nullable', 'boolean'],
            'show_name' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', $this->owned('products')],
            'items.*.variant_id' => ['nullable', $this->owned('product_variants')],
            'items.*.copies' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);
        $products = Product::whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');
        $variants = ProductVariant::whereIn('id', array_filter(array_column($data['items'], 'variant_id')))->get()->keyBy('id');
        $level = Party::where('is_walk_in', true)->value('price_level_id');

        $labels = [];
        foreach ($data['items'] as $item) {
            $p = $products[$item['product_id']];
            $v = $p->has_variants ? $variants->get((int) ($item['variant_id'] ?? 0)) : null;
            if ($p->has_variants && (! $v || $v->product_id !== $p->id)) {
                continue;
            }
            $code = $v ? ($v->barcode ?: $v->sku) : ($p->barcode ?: $p->sku);
            if (! $code) {
                continue;
            }
            $label = ['name' => $p->name, 'variant' => $v?->name, 'code' => $code, 'price' => $p->priceFor($level, null, $v?->setRelation('product', $p))];
            for ($n = 0; $n < (int) $item['copies']; $n++) {
                $labels[] = $label;
            }
        }
        abort_if(count($labels) > 5000, 422, __('Print at most 5,000 labels at a time.'));

        [, $w, $h] = self::SIZES[$data['size']];

        return view('inventory.labels.print', [
            'labels' => $labels, 'size' => $data['size'], 'w' => $w, 'h' => $h, 'sheet' => str_starts_with($data['size'], 'a4'),
            'showPrice' => $request->boolean('show_price'), 'showName' => $request->boolean('show_name'), 'business' => Tenant::business(),
        ]);
    }
}
