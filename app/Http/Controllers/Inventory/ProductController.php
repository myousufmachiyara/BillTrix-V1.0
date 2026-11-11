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
    public function index(Request $request): View
    {
        $this->allow('products.view');

        $products = Product::with(['category', 'unit'])
            ->withSum('balances as stock', 'quantity')
            ->search($request->query('q'))
            ->when($request->query('category'), fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false), fn ($q) => $q->where('is_active', true))
            ->when($request->query('status') === 'low', fn ($q) => $q->where('track_stock', true)->whereRaw(
                'COALESCE((SELECT SUM(quantity) FROM stock_balances WHERE stock_balances.product_id = products.id), 0) <= products.reorder_level'
            ))
            ->orderBy('name')
            ->paginate(50)->withQueryString();

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
            if ($product->track_stock && (float) ($opening['opening_qty'] ?? 0) > 0) {
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
            ? $this->done("{$product->name} saved.", 'products.create')
            : $this->done("{$product->name} saved.", 'products.show', $product);
    }

    public function show(Product $product): View
    {
        $this->allow('products.view');
        $product->load(['category', 'unit', 'units.unit', 'prices.priceLevel', 'taxRate']);

        return view('inventory.products.show', [
            'product' => $product,
            'stock' => StockBalance::with('warehouse')->where('product_id', $product->id)->get(),
            'movements' => StockMovement::with(['warehouse', 'source'])->where('product_id', $product->id)->latest('date')->latest('id')->limit(25)->get(),
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

        return $this->done("{$product->name} updated.", 'products.show', $product);
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->allow('products.delete');
        if ($product->movements()->exists() || InvoiceItem::where('product_id', $product->id)->exists()) {
            $product->update(['is_active' => false]);

            return $this->done('This product has transactions, so it was deactivated instead of deleted.', 'products.index');
        }
        $product->delete();

        return $this->done('Product deleted.', 'products.index');
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

        $query = Product::with(['unit', 'units.unit', 'prices', 'taxRate'])
            ->where('is_active', true)
            ->when($request->query('ids'), fn ($q, $ids) => $q->whereIn('id', explode(',', $ids)))
            ->when($request->integer('category_id'), fn ($q, $c) => $q->where(fn ($w) => $w->where('category_id', $c)->orWhereIn('category_id', \App\Models\Category::where('parent_id', $c)->pluck('id'))));

        if ($term !== '') {
            $exact
                ? $query->where(fn ($w) => $w->where('barcode', $term)->orWhere('sku', $term)->orWhereHas('units', fn ($u) => $u->where('barcode', $term)))
                : $query->search($term);
        }

        $products = $query->orderBy('name')->limit($exact ? 1 : ($request->integer('category_id') ? 60 : 25))->get();
        $stock = StockBalance::whereIn('product_id', $products->pluck('id'))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('product_id, SUM(quantity) as qty')->groupBy('product_id')->pluck('qty', 'product_id');

        $special = $party && Tenant::business()->feature('customer_prices')
            ? PartyPrice::where('party_id', $party->id)->whereIn('product_id', $products->pluck('id'))->pluck('price', 'product_id')
            : collect();

        return response()->json(['results' => $products->map(function (Product $p) use ($stock, $levelId, $term, $exact, $special) {
            $units = $p->unitOptions();
            $customerPrice = isset($special[$p->id]) ? (float) $special[$p->id] : null;
            if ($customerPrice !== null) {
                // The customer's own price wins over fixed pack prices.
                $units = array_map(fn ($u) => array_merge($u, ['sale_price' => null]), $units);
            }
            $matched = $exact ? collect($units)->first(fn ($u) => $u['barcode'] === $term) : null;

            return [
                'id' => $p->id,
                'text' => $p->name.($p->sku ? " [{$p->sku}]" : ''),
                'name' => $p->name,
                'base_unit_id' => $p->unit_id,
                'units' => $units,
                'unit_id' => $matched['unit_id'] ?? $p->unit_id,
                'sale_price' => $p->priceFor($levelId, $customerPrice),
                'special_price' => $customerPrice !== null,
                'purchase_price' => (float) $p->purchase_price,
                'avg_cost' => (float) $p->avg_cost,
                'min_sale_price' => (float) $p->min_sale_price,
                'tax_rate' => (float) ($p->taxRate?->rate ?? 0),
                'stock' => $p->track_stock ? (float) ($stock[$p->id] ?? 0) : null,
            ];
        })]);
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
        ], ['packs.*.unit_id.different' => 'A pack unit must differ from the base unit.', 'packs.*.factor.gt' => 'Pack size must be more than 1 base unit.']);

        if ($product->exists && (int) $data['unit_id'] !== $product->unit_id && $product->movements()->exists()) {
            abort(422, 'The base unit cannot change once the product has stock movements.');
        }

        $product->fill(collect($data)->only(['name', 'sku', 'barcode', 'brand', 'category_id', 'unit_id', 'tax_rate_id', 'description'])->all());
        foreach (['purchase_price', 'sale_price', 'min_sale_price', 'reorder_level'] as $f) {
            $product->{$f} = (float) ($data[$f] ?? 0);
        }
        $product->track_stock = $request->boolean('track_stock');
        $product->is_active = $request->boolean('is_active');

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

        $product->prices()->delete();
        foreach ($data['prices'] ?? [] as $levelId => $price) {
            if ($price !== null && $price !== '' && PriceLevel::whereKey($levelId)->exists()) {
                $product->prices()->create(['price_level_id' => $levelId, 'price' => $price]);
            }
        }

        return $product;
    }
}
