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
            'quick' => Product::with(['unit', 'units.unit', 'taxRate'])->where('is_active', true)
                ->withSum(['balances as stock' => fn ($q) => $q->where('warehouse_id', $warehouse?->id)], 'quantity')
                ->withCount(['movements as sold' => fn ($q) => $q->where('type', 'sale')->whereDate('date', '>=', now()->subDays(30)->toDateString())])
                ->orderByDesc('sold')->orderBy('name')->limit(48)->get(),
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
            ->withSum(['balances as stock' => fn ($q) => $q->when($warehouseId, fn ($w) => $w->where('warehouse_id', $warehouseId))], 'quantity')
            ->orderBy('name')->limit(10000)->get();

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'barcode' => $p->barcode, 'category_id' => $p->category_id,
                'base_unit_id' => $p->unit_id, 'unit_id' => $p->unit_id, 'units' => $p->unitOptions(),
                'sale_price' => $p->priceFor($walkInLevel), 'avg_cost' => (float) $p->avg_cost,
                'tax_rate' => (float) ($p->taxRate?->rate ?? 0), 'stock' => $p->track_stock ? (float) ($p->stock ?? 0) : null,
                'track_batches' => (bool) $p->track_batches,
            ])->values(),
        ]);
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
