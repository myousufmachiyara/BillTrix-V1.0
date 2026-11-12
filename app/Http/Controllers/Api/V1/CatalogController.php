<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Account;
use App\Models\Category;
use App\Models\PartyPrice;
use App\Models\PriceLevel;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Products, stock and the small lookup lists a mobile app caches. */
class CatalogController extends Controller
{
    public function products(Request $request): AnonymousResourceCollection
    {
        $this->allow('products.view');
        $warehouseId = $request->integer('warehouse_id') ?: null;

        $products = Product::with(['unit', 'units.unit', 'prices', 'taxRate', 'category',
            'variants' => fn ($v) => $v->where('is_active', true)->with(['balances' => fn ($b) => $b->when($warehouseId, fn ($w) => $w->where('warehouse_id', $warehouseId))])])
            ->where('is_active', true)
            ->when($request->query('barcode'), fn ($q, $code) => $q->where(fn ($w) => $w->where('barcode', $code)->orWhere('sku', $code)->orWhereHas('units', fn ($u) => $u->where('barcode', $code))
                ->orWhereHas('variants', fn ($v) => $v->where('barcode', $code)->orWhere('sku', $code))),
                fn ($q) => $q->search($request->query('q')))
            ->when($request->integer('category_id'), fn ($q, $c) => $q->where('category_id', $c))
            ->when($request->query('updated_since'), fn ($q, $d) => $q->where('updated_at', '>=', $d))
            ->withSum(['balances as stock' => fn ($q) => $q->when($warehouseId, fn ($w) => $w->where('warehouse_id', $warehouseId))], 'quantity')
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 50), 200));

        // A customer's own prices replace sale_price when party_id is given.
        if ($request->integer('party_id')) {
            $special = PartyPrice::where('party_id', $request->integer('party_id'))->whereIn('product_id', $products->pluck('id'))->pluck('price', 'product_id');
            $products->getCollection()->each(fn ($p) => isset($special[$p->id]) && $p->setAttribute('sale_price', $special[$p->id]));
        }

        return ProductResource::collection($products);
    }

    public function product(Product $product, StockService $stock): JsonResponse
    {
        $this->allow('products.view');
        $product->load(['unit', 'units.unit', 'prices', 'taxRate', 'category', 'variants.balances']);

        return response()->json([
            'data' => (new ProductResource($product))->resolve(),
            'stock' => StockBalance::where('product_id', $product->id)->get(['warehouse_id', 'variant_id', 'quantity'])
                ->map(fn ($s) => ['warehouse_id' => $s->warehouse_id, 'variant_id' => $s->variant_id ?: null, 'quantity' => (float) $s->quantity]),
            'batches' => $product->track_batches ? $stock->batchStock($product->id)->map(fn ($b) => [
                'batch_no' => $b->batch_no, 'expiry_date' => $b->expiry_date ? substr((string) $b->expiry_date, 0, 10) : null,
                'warehouse_id' => $b->warehouse_id, 'quantity' => (float) $b->quantity,
            ]) : [],
        ]);
    }

    /** Lists that rarely change: categories, units, warehouses, price levels, cash/bank accounts. */
    public function lookups(): JsonResponse
    {
        return response()->json([
            'categories' => Category::orderBy('name')->get(['id', 'parent_id', 'name']),
            'units' => Unit::orderBy('name')->get(['id', 'name', 'short_name', 'allow_decimal']),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->get(['id', 'branch_id', 'name', 'is_default']),
            'price_levels' => PriceLevel::orderBy('id')->get(['id', 'name', 'is_default']),
            'payment_accounts' => Account::cashOrBank()->orderBy('code')->get(['id', 'name', 'subtype']),
        ]);
    }
}
