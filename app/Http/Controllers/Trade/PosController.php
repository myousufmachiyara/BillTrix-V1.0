<?php

namespace App\Http\Controllers\Trade;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\Party;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InvoiceService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Full-screen counter billing. Saves ordinary sale invoices through InvoiceService. */
class PosController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $this->allow('pos.use');
        $user = $request->user();
        $session = CashSession::currentFor($user);

        if (! $session && Tenant::business()->feature('pos_shifts')) {
            return redirect()->route('cash-sessions.index')->with('error', __('Open your register before you start billing.'));
        }

        $warehouse = Warehouse::where('is_active', true)
            ->orderByRaw('CASE WHEN branch_id = ? THEN 0 ELSE 1 END', [$user->branch_id])
            ->orderByDesc('is_default')->first();

        return view('pos.index', [
            'session' => $session?->load('account'),
            'warehouse' => $warehouse,
            'walkIn' => Party::where('is_walk_in', true)->first(),
            'categories' => Category::whereHas('products', fn ($q) => $q->where('is_active', true))->orderBy('name')->get(['id', 'name']),
            // Quick-pick grid: the products sold most in the last 30 days, then the rest by name.
            'quick' => $this->rows(Product::with(['unit', 'units.unit', 'taxRate', 'prices'])->where('is_active', true)
                ->withCount(['movements as sold' => fn ($q) => $q->where('type', 'sale')->whereDate('date', '>=', now()->subDays(30)->toDateString())])
                ->orderByDesc('sold')->orderBy('name')->limit(48)->get(), $warehouse?->id)->take(60)->values(),
            'accounts' => Account::cashOrBank()->orderByRaw("CASE WHEN subtype = 'cash' THEN 0 ELSE 1 END")->orderBy('code')->get(['id', 'name', 'subtype']),
        ]);
    }

    public function store(Request $request, InvoiceService $invoices): JsonResponse
    {
        $this->allow('pos.use');
        $data = $request->validate([
            'party_id' => ['required', $this->owned('parties')->whereIn('type', ['customer', 'both'])],
            'warehouse_id' => ['required', $this->owned('warehouses')],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'round_off' => ['nullable', 'numeric', 'between:-100,100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', $this->owned('products')],
            'items.*.variant_id' => ['nullable', $this->owned('product_variants')],
            'items.*.unit_id' => ['required', $this->owned('units')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'payments' => ['array'],
            'payments.*.account_id' => ['required', $this->owned('accounts')->whereIn('subtype', ['cash', 'bank'])],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            // Offline POS: the device's id for the bill and when it was rung up.
            'client_uuid' => ['nullable', 'uuid'],
            'sold_at' => ['nullable', 'date', 'before_or_equal:today', 'after_or_equal:'.now()->subDays(7)->toDateString()],
        ]);

        // A bill re-sent by an offline device is answered with the one already saved.
        if (! empty($data['client_uuid']) && ($existing = Invoice::where('client_uuid', $data['client_uuid'])->first())) {
            return $this->saved($existing, duplicate: true);
        }

        $session = CashSession::currentFor($request->user());
        if (! $session && Tenant::business()->feature('pos_shifts')) {
            return response()->json(['message' => 'Your register is closed. Open it to continue.'], 409);
        }

        $paid = collect($data['payments'] ?? [])->sum('amount');
        try {
            $invoice = $invoices->save('sale', collect($data)->except('sold_at')->all() + [
                'date' => ! empty($data['sold_at']) ? substr($data['sold_at'], 0, 10) : now()->toDateString(),
                'cash_session_id' => $session?->id,
                'payment_mode' => $paid > 0 ? 'cash' : 'credit',
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Two copies of the same offline bill arrived together; the other one won.
            $invoice = Invoice::where('client_uuid', $data['client_uuid'] ?? '')->first() ?? throw $e;

            return $this->saved($invoice, duplicate: true);
        }

        return $this->saved($invoice);
    }

    private function saved(Invoice $invoice, bool $duplicate = false): JsonResponse
    {
        return response()->json([
            'id' => $invoice->id,
            'number' => $invoice->number,
            'total' => (float) $invoice->total,
            'paid' => (float) $invoice->paid_amount,
            'fbr_invoice_number' => $invoice->fbr_invoice_number,
            'duplicate' => $duplicate,
            'print' => route('invoices.print', ['sales', $invoice, 'layout' => 'thermal']),
        ]);
    }

    /**
     * Everything the POS needs to keep selling offline: active products with
     * their packs, walk-in prices, tax rate, barcodes and stock in this warehouse.
     */
    public function catalog(Request $request): JsonResponse
    {
        $this->allow('pos.use');
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $walkInLevel = Party::where('is_walk_in', true)->value('price_level_id');

        $products = Product::with(['unit', 'units.unit', 'prices', 'taxRate'])->where('is_active', true)
            ->orderBy('name')->limit(10000)->get();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'products' => $this->rows($products, $warehouseId, $walkInLevel)->values(),
        ]);
    }

    /**
     * POS rows: one per product, or one per active variation for products with
     * variations. Each has its own SKU, barcode, walk-in price and stock.
     */
    private function rows($products, ?int $warehouseId, ?int $level = null)
    {
        $level ??= Party::where('is_walk_in', true)->value('price_level_id');
        $products->loadMissing(['variants' => fn ($v) => $v->where('is_active', true)]);
        $stock = \App\Models\StockBalance::whereIn('product_id', $products->pluck('id'))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('product_id, variant_id, SUM(quantity) as qty')->groupBy('product_id', 'variant_id')->get()
            ->mapWithKeys(fn ($r) => [$r->product_id.'-'.$r->variant_id => (float) $r->qty]);

        return $products->flatMap(fn (Product $p) => collect($p->has_variants ? $p->variants->all() : [null])->map(function ($v) use ($p, $level, $stock) {
            $units = $p->unitOptions();
            if ($v) {
                $units[0]['barcode'] = $v->barcode;
                $v->setRelation('product', $p);
            }

            return [
                'id' => $v ? $p->id.'-'.$v->id : $p->id, 'product_id' => $p->id, 'variant_id' => $v?->id,
                'name' => $v ? $p->name.' — '.$v->name : $p->name, 'sku' => $v ? $v->sku : $p->sku, 'barcode' => $v ? $v->barcode : $p->barcode,
                'category_id' => $p->category_id, 'base_unit_id' => $p->unit_id, 'unit_id' => $p->unit_id, 'units' => $units,
                'sale_price' => $p->priceFor($level, null, $v), 'avg_cost' => (float) ($v ?? $p)->avg_cost,
                'tax_rate' => (float) ($p->taxRate?->rate ?? 0), 'stock' => $p->track_stock ? (float) ($stock[$p->id.'-'.($v?->id ?? 0)] ?? 0) : null,
                'track_batches' => (bool) $p->track_batches,
                'image' => $p->image_path ? asset('storage/'.$p->image_path) : null,
            ];
        }));
    }

    /** The cashier's recent bills, for reprint or return. */
    public function recent(Request $request): JsonResponse
    {
        $this->allow('pos.use');
        $session = CashSession::currentFor($request->user());

        return response()->json(Invoice::with('party:id,name,is_walk_in')->where('type', 'sale')
            ->when($session, fn ($q) => $q->where('cash_session_id', $session->id), fn ($q) => $q->where('created_by', $request->user()->id))
            ->when($request->query('q'), fn ($q, $t) => $q->where('number', 'like', "%{$t}%"))
            ->latest('id')->limit(15)->get()
            ->map(fn ($i) => [
                'number' => $i->number, 'time' => $i->created_at->format('H:i'), 'total' => (float) $i->total,
                'customer' => $i->party->is_walk_in ? ($i->customer_name ?: 'Walk-in') : $i->party->name,
                'print' => route('invoices.print', ['sales', $i, 'layout' => 'thermal']),
                'return' => auth()->user()->can('sale_returns.create') ? route('invoices.create', ['sale-returns', 'from' => $i->id]) : null,
            ]));
    }
}
