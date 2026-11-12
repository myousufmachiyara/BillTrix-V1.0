<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\Party;
use App\Models\PartyPrice;
use App\Models\PriceLevel;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\TaxRate;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\StockDocumentService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->allow('products.view');

        $query = Product::with(['category', 'unit', 'taxRate'])
            ->withSum('balances as stock', 'quantity')
            ->withCount(['variants' => fn ($v) => $v->where('is_active', true)])
            ->search($request->query('q'))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false), fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'low', fn ($q) => $q->where('track_stock', true)->whereRaw(
                'COALESCE((SELECT SUM(quantity) FROM stock_balances WHERE stock_balances.product_id = products.id), 0) <= products.reorder_level'
            ))
            ->orderBy('name');

        if ($x = $this->exportIfAsked($request, 'products', ['Name', 'SKU', 'Barcode', 'Category', 'Unit', 'Purchase price', 'Sale price', 'Reorder level', 'HS code', 'Tax rate %', 'Stock', 'Avg cost'],
            fn () => $query->get()->map(fn ($p) => [$p->name, $p->sku, $p->barcode, $p->category?->name, $p->unit->short_name, (float) $p->purchase_price,
                (float) $p->sale_price, (float) $p->reorder_level, $p->hs_code, (float) ($p->taxRate?->rate ?? 0), (float) $p->stock, (float) $p->avg_cost]))) {
            return $x;
        }
        $products = $query->paginate(50)->withQueryString();

        return view('inventory.products.index', [
            'products' => $products,
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function create(): View
    {
        $this->allow('products.create');

        return view('inventory.products.form', $this->formData(new Product(['is_active' => true, 'track_stock' => true,
            'unit_id' => Unit::where('short_name', 'pcs')->value('id')])));
    }

    public function store(Request $request, StockDocumentService $docs): RedirectResponse
    {
        $this->allow('products.create');
        $opening = $request->validate([
            'opening_qty' => ['nullable', 'numeric', 'min:0'],
            'opening_cost' => ['nullable', 'numeric', 'min:0'],
            'opening_warehouse_id' => ['nullable', $this->owned('warehouses')],
        ]);

        $product = DB::transaction(function () use ($request, $opening, $docs) {
            $product = $this->persist(new Product, $request);
            if ($product->has_variants && $product->track_stock) {
                $byCombo = $product->variants()->with('values')->get()->keyBy(fn ($v) => $v->values->pluck('id')->sort()->implode('-'));
                $items = collect($request->input('variants', []))->map(function ($r) use ($byCombo, $opening, $product) {
                    $v = $byCombo->get(collect($r['value_ids'] ?? [])->map(fn ($x) => (int) $x)->sort()->implode('-'));

                    return $v && (float) ($r['opening_qty'] ?? 0) > 0
                        ? ['product_id' => $product->id, 'variant_id' => $v->id, 'quantity' => (float) $r['opening_qty'], 'unit_cost' => $opening['opening_cost'] ?? $v->purchasePrice()]
                        : null;
                })->filter()->values()->all();
                if ($items) {
                    $docs->adjust([
                        'warehouse_id' => $opening['opening_warehouse_id'] ?? Warehouse::where('is_default', true)->value('id') ?? Warehouse::value('id'),
                        'date' => now()->toDateString(), 'type' => 'opening', 'notes' => "Opening stock for {$product->name}", 'items' => $items,
                    ]);
                }
            } elseif ($product->track_stock && (float) ($opening['opening_qty'] ?? 0) > 0) {
                $docs->adjust([
                    'warehouse_id' => $opening['opening_warehouse_id'] ?? Warehouse::where('is_default', true)->value('id') ?? Warehouse::value('id'),
                    'date' => now()->toDateString(),
                    'type' => 'opening',
                    'notes' => "Opening stock for {$product->name}",
                    'items' => [['product_id' => $product->id, 'quantity' => $opening['opening_qty'], 'unit_cost' => $opening['opening_cost'] ?? $product->purchase_price]],
                ]);
            }

            return $product;
        });

        return $request->boolean('save_new')
            ? $this->done(__(':n saved.', ['n' => $product->name]), 'products.create')
            : $this->done(__(':n saved.', ['n' => $product->name]), 'products.show', $product);
    }

    public function show(Product $product): View
    {
        $this->allow('products.view');
        $product->load(['category', 'unit', 'units.unit', 'prices.priceLevel', 'taxRate']);

        return view('inventory.products.show', [
            'product' => $product,
            'stock' => StockBalance::with('warehouse')->where('product_id', $product->id)
                ->selectRaw('warehouse_id, SUM(quantity) as quantity')->groupBy('warehouse_id')->get(),
            'variants' => $product->has_variants ? $product->variants()->with('balances')->get() : collect(),
            'warehouseNames' => Warehouse::pluck('name', 'id'),
            'batches' => $product->track_batches ? app(\App\Services\StockService::class)->batchStock($product->id) : collect(),
            'movements' => StockMovement::with(['warehouse', 'source', 'variant'])->where('product_id', $product->id)->latest('date')->latest('id')->limit(25)->get(),
        ]);
    }

    public function edit(Product $product): View
    {
        $this->allow('products.edit');
        $product->load(['units', 'prices']);

        return view('inventory.products.form', $this->formData($product));
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->allow('products.edit');
        DB::transaction(fn () => $this->persist($product, $request));

        return $this->done(__(':n updated.', ['n' => $product->name]), 'products.show', $product);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->allow('products.delete');
        if ($product->movements()->exists() || InvoiceItem::where('product_id', $product->id)->exists()) {
            $product->update(['is_active' => false]);

            return $this->done(__('This product has transactions, so it was deactivated instead of deleted.'), 'products.index');
        }
        $product->delete();

        return $this->done(__('Product deleted.'), 'products.index');
    }

    /**
     * Product search for invoice lines (select2 + barcode scanner).
     * Returns prices for the customer's price level and stock in the chosen warehouse.
     */
    public function lookup(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $party = Party::find($request->integer('party_id'));
        $levelId = $request->integer('price_level_id') ?: $party?->price_level_id;
        $exact = $request->boolean('barcode');

        $query = Product::with(['unit', 'units.unit', 'prices', 'taxRate', 'variants' => fn ($v) => $v->where('is_active', true)])
            ->where('is_active', true)
            ->when($request->query('ids'), fn ($q, $ids) => $q->whereIn('id', explode(',', $ids)))
            ->when($request->integer('category_id'), fn ($q, $c) => $q->where(fn ($w) => $w->where('category_id', $c)->orWhereIn('category_id', \App\Models\Category::where('parent_id', $c)->pluck('id'))));

        if ($term !== '') {
            $exact
                ? $query->where(fn ($w) => $w->where(fn ($x) => $x->where('has_variants', false)->where(fn ($y) => $y->where('barcode', $term)->orWhere('sku', $term)->orWhereHas('units', fn ($u) => $u->where('barcode', $term))))
                    ->orWhereHas('variants', fn ($v) => $v->where('is_active', true)->where(fn ($y) => $y->where('barcode', $term)->orWhere('sku', $term))))
                : $query->search($term);
        }

        $products = $query->orderBy('name')->limit($exact ? 1 : ($request->integer('category_id') ? 60 : 25))->get();
        $stock = StockBalance::whereIn('product_id', $products->pluck('id'))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('product_id, variant_id, SUM(quantity) as qty')->groupBy('product_id', 'variant_id')->get()
            ->mapWithKeys(fn ($r) => [$r->product_id.'-'.$r->variant_id => (float) $r->qty]);

        $special = $party && Tenant::business()->feature('customer_prices')
            ? PartyPrice::where('party_id', $party->id)->whereIn('product_id', $products->pluck('id'))->pluck('price', 'product_id')
            : collect();

        // Sellable batches (unexpired, in stock) for batch-tracked items, earliest expiry first.
        $batches = \App\Models\BatchStock::query()->join('batches', 'batches.id', '=', 'batch_stocks.batch_id')
            ->whereIn('batches.product_id', $products->where('track_batches', true)->pluck('id'))
            ->when($warehouseId, fn ($q) => $q->where('batch_stocks.warehouse_id', $warehouseId))
            ->where('batch_stocks.quantity', '>', 0)
            ->where(fn ($w) => $w->whereNull('batches.expiry_date')->orWhereDate('batches.expiry_date', '>=', now()->toDateString()))
            ->groupBy('batches.product_id', 'batches.batch_no', 'batches.expiry_date')
            ->orderByRaw('CASE WHEN batches.expiry_date IS NULL THEN 1 ELSE 0 END')->orderBy('batches.expiry_date')
            ->selectRaw('batches.product_id, batches.batch_no, batches.expiry_date, SUM(batch_stocks.quantity) as quantity')
            ->get()
            ->map(fn ($b) => ['product_id' => $b->product_id, 'batch_no' => $b->batch_no, 'expiry_date' => $b->expiry_date ? substr((string) $b->expiry_date, 0, 10) : null, 'quantity' => (float) $b->quantity])
            ->groupBy('product_id');

        // A product with variations is offered once per variation (each with its own SKU, barcode, price and stock).
        $rows = $products->flatMap(function (Product $p) use ($term, $exact) {
            if (! $p->has_variants) {
                return [[$p, null]];
            }
            $variants = $p->variants;
            $hit = $term !== '' ? $variants->filter(fn ($v) => $v->barcode === $term || strcasecmp((string) $v->sku, $term) === 0) : collect();
            if ($exact || $hit->isNotEmpty()) {
                $variants = $hit;
            }

            return $variants->map(fn ($v) => [$p, $v->setRelation('product', $p)])->all();
        })->take($exact ? 1 : 80);

        return response()->json(['results' => $rows->map(function (array $row) use ($stock, $levelId, $term, $exact, $special, $batches) {
            [$p, $v] = $row;
            $units = $p->unitOptions();
            if ($v) {
                $units[0]['barcode'] = $v->barcode;
            }
            $customerPrice = isset($special[$p->id]) ? (float) $special[$p->id] : null;
            if ($customerPrice !== null) {
                // The customer's own price wins over fixed pack prices.
                $units = array_map(fn ($u) => array_merge($u, ['sale_price' => null]), $units);
            }
            $matched = $exact ? collect($units)->first(fn ($u) => $u['barcode'] === $term) : null;

            $sku = $v ? $v->sku : $p->sku;

            return [
                'id' => $v ? $p->id.'-'.$v->id : $p->id,
                'product_id' => $p->id,
                'variant_id' => $v?->id,
                'text' => ($v ? $p->name.' — '.$v->name : $p->name).($sku ? " [{$sku}]" : ''),
                'name' => $v ? $p->name.' — '.$v->name : $p->name,
                'sku' => $sku,
                'barcode' => $v ? $v->barcode : $p->barcode,
                'base_unit_id' => $p->unit_id,
                'units' => $units,
                'unit_id' => $matched['unit_id'] ?? $p->unit_id,
                'sale_price' => $p->priceFor($levelId, $customerPrice, $v),
                'special_price' => $customerPrice !== null,
                'purchase_price' => $v ? $v->purchasePrice() : (float) $p->purchase_price,
                'avg_cost' => (float) ($v ?? $p)->avg_cost,
                'min_sale_price' => (float) $p->min_sale_price,
                'tax_rate' => (float) ($p->taxRate?->rate ?? 0),
                'stock' => $p->track_stock ? (float) ($stock[$p->id.'-'.($v?->id ?? 0)] ?? 0) : null,
                'track_batches' => (bool) $p->track_batches,
                'batches' => $batches->get($p->id, collect())->values(),
                'category_id' => $p->category_id,
            ];
        })->values()]);
    }

    private function formData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => Category::with('parent')->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->full_name])->sort(),
            'units' => Unit::orderBy('name')->get(),
            'taxRates' => TaxRate::where('is_active', true)->orderBy('rate')->get(),
            'priceLevels' => PriceLevel::orderBy('id')->get(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'attributes' => Tenant::business()->feature('variants')
                ? \App\Models\ProductAttribute::with('values')->orderBy('sort')->orderBy('name')->get()
                : collect(),
            'variantRows' => $product->exists
                ? $product->variants()->with('values')->get()->map(fn ($v) => [
                    'id' => $v->id, 'value_ids' => $v->values->pluck('id')->all(), 'name' => $v->name, 'sku' => $v->sku, 'barcode' => $v->barcode,
                    'sale_price' => $v->sale_price !== null ? (float) $v->sale_price : null, 'purchase_price' => $v->purchase_price !== null ? (float) $v->purchase_price : null,
                    'reorder_level' => $v->reorder_level !== null ? (float) $v->reorder_level : null, 'is_active' => $v->is_active, 'stock' => $v->stockIn(),
                ])->all()
                : [],
            'variantsLocked' => $product->exists && $product->movements()->exists(),
        ];
    }

    private function persist(Product $product, Request $request): Product
    {
        $tenant = Tenant::idOrFail();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:50', Rule::unique('products')->where('business_id', $tenant)->ignore($product)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products')->where('business_id', $tenant)->ignore($product)],
            'brand' => ['nullable', 'string', 'max:80'],
            'hs_code' => ['nullable', 'string', 'max:20', 'regex:/^[0-9.]+$/'],
            'category_id' => ['nullable', $this->owned('categories')],
            'unit_id' => ['required', $this->owned('units')],
            'tax_rate_id' => ['nullable', $this->owned('tax_rates')],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'min_sale_price' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', 'image', 'max:2048'],
            'packs' => ['array'],
            'packs.*.unit_id' => ['required', $this->owned('units'), 'distinct', 'different:unit_id'],
            'packs.*.factor' => ['required', 'numeric', 'gt:1'],
            'packs.*.barcode' => ['nullable', 'string', 'max:64'],
            'packs.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'packs.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'prices' => ['array'],
            'prices.*' => ['nullable', 'numeric', 'min:0'],
            'variants' => ['array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.value_ids' => ['required_with:variants', 'array', 'min:1'],
            'variants.*.value_ids.*' => ['integer'],
            'variants.*.sku' => ['nullable', 'string', 'max:80'],
            'variants.*.barcode' => ['nullable', 'string', 'max:64'],
            'variants.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.purchase_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.reorder_level' => ['nullable', 'numeric', 'min:0'],
            'variants.*.opening_qty' => ['nullable', 'numeric', 'min:0'],
            'variants.*.is_active' => ['nullable', 'boolean'],
        ], ['packs.*.unit_id.different' => 'A pack unit must differ from the base unit.', 'packs.*.factor.gt' => 'Pack size must be more than 1 base unit.']);

        if ($product->exists && (int) $data['unit_id'] !== $product->unit_id && $product->movements()->exists()) {
            abort(422, __('The base unit cannot change once the product has stock movements.'));
        }

        $product->fill(collect($data)->only(['name', 'sku', 'barcode', 'hs_code', 'brand', 'category_id', 'unit_id', 'tax_rate_id', 'description'])->all());
        foreach (['purchase_price', 'sale_price', 'min_sale_price', 'reorder_level'] as $f) {
            $product->{$f} = (float) ($data[$f] ?? 0);
        }
        $product->track_stock = $request->boolean('track_stock');
        if ($request->has('track_batches')) {
            if ($product->exists && $product->track_batches !== $request->boolean('track_batches') && $product->movements()->exists()) {
                abort(422, __('Batch tracking cannot be switched once the product has stock movements.'));
            }
            $product->track_batches = $request->boolean('track_batches') && $product->track_stock;
        }
        $product->is_active = $request->boolean('is_active');

        if (Tenant::business()->feature('variants') && $request->has('has_variants_shown')) {
            $wants = $request->boolean('has_variants');
            if ($product->exists && $product->has_variants !== $wants && $product->movements()->exists()) {
                abort(422, __('Variations cannot be switched on or off once the product has stock movements.'));
            }
            if ($wants && $product->track_batches) {
                throw \Illuminate\Validation\ValidationException::withMessages(['has_variants' => __('A product can track batches or have variations, not both.')]);
            }
            $product->has_variants = $wants;
        }

        if ($request->hasFile('image')) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $product->image_path = $request->file('image')->store("products/{$tenant}", 'public');
        }
        $product->save();

        $product->units()->delete();
        foreach ($data['packs'] ?? [] as $pack) {
            $product->units()->create($pack);
        }

        if ($product->has_variants) {
            $rows = collect($data['variants'] ?? [])->map(fn ($r) => $r + ['is_active' => ! empty($r['is_active'])])->values()->all();
            if (! $rows) {
                throw \Illuminate\Validation\ValidationException::withMessages(['variants' => __('Add at least one variation.')]);
            }
            app(\App\Services\VariantService::class)->sync($product, $rows);
        } elseif ($product->variants()->exists() && ! $product->movements()->exists()) {
            $product->variants()->delete();
        }

        $product->prices()->delete();
        foreach ($data['prices'] ?? [] as $levelId => $price) {
            if ($price !== null && $price !== '' && PriceLevel::whereKey($levelId)->exists()) {
                $product->prices()->create(['price_level_id' => $levelId, 'price' => $price]);
            }
        }

        return $product;
    }
}
