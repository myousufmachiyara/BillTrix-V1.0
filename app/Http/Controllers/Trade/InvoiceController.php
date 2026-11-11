<?php

namespace App\Http\Controllers\Trade;

use App\Http\Controllers\Concerns\ReshowsOldLines;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CashSession;
use App\Models\Delivery;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Party;
use App\Models\PaymentAllocation;
use App\Models\Warehouse;
use App\Services\InvoiceService;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Sales, purchases and their returns. The URL segment picks the document type. */
class InvoiceController extends Controller
{
    use ReshowsOldLines;

    /** url segment => [invoice type, permission module, party type, label] */
    public const DOCS = [
        'sales' => ['sale', 'sales', 'customer', 'Sale Invoices'],
        'purchases' => ['purchase', 'purchases', 'supplier', 'Purchase Invoices'],
        'sale-returns' => ['sale_return', 'sale_returns', 'customer', 'Sale Returns'],
        'purchase-returns' => ['purchase_return', 'purchase_returns', 'supplier', 'Purchase Returns'],
    ];

    public function __construct(private InvoiceService $service) {}

    public function index(Request $request, string $doc): View
    {
        [$type, $perm] = self::DOCS[$doc];
        $this->allow("{$perm}.view");

        $base = Invoice::where('type', $type)
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($request->query('party_id'), fn ($q, $p) => $q->where('party_id', $p))
            ->when($request->query('branch_id'), fn ($q, $b) => $q->where('branch_id', $b))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', "%{$t}%")->orWhere('reference', 'like', "%{$t}%")->orWhere('customer_name', 'like', "%{$t}%")))
            ->when($request->query('status') === 'unpaid', fn ($q) => $q->outstanding());

        return view('trade.index', [
            'doc' => $doc,
            'meta' => self::DOCS[$doc],
            'invoices' => (clone $base)->with('party')->latest('date')->latest('id')->paginate(50)->withQueryString(),
            'sum' => (clone $base)->selectRaw('COUNT(*) as n, COALESCE(SUM(total),0) as total, COALESCE(SUM(total - paid_amount),0) as due')->first(),
            'party' => $request->query('party_id') ? Party::find($request->query('party_id')) : null,
        ]);
    }

    public function create(Request $request, string $doc): View
    {
        [$type, $perm] = self::DOCS[$doc];
        $this->allow("{$perm}.create");

        $invoice = new Invoice([
            'type' => $type,
            'date' => now(),
            'payment_mode' => $type === 'sale' ? 'cash' : 'credit',
            'warehouse_id' => $this->defaultWarehouseId(),
        ]);

        // A return can start from the original invoice.
        if ($request->query('from') && str_ends_with($type, '_return')) {
            $original = Invoice::with(['items.product', 'items.unit', 'party'])->where('type', str_replace('_return', '', $type))->findOrFail($request->query('from'));
            $invoice->fill(['party_id' => $original->party_id, 'return_of_id' => $original->id, 'warehouse_id' => $original->warehouse_id]);
            $invoice->setRelation('returnOf', $original);
            $invoice->setRelation('party', $original->party);
        } elseif (in_array($type, ['sale', 'purchase'], true) && $request->query('order')) {
            $prefill = $this->linesFromOrder($invoice, $type === 'sale' ? 'sale_order' : 'purchase_order', (int) $request->query('order'));
        } elseif ($type === 'sale' && $request->query('deliveries')) {
            $prefill = $this->linesFromChallans($invoice, array_map('intval', explode(',', $request->query('deliveries'))));
        } elseif ($type === 'sale' && $request->query('quotation')) {
            $prefill = $this->linesFromOrder($invoice, 'quotation', (int) $request->query('quotation'), link: false);
        } elseif ($type === 'sale') {
            $walkIn = Party::where('is_walk_in', true)->first();
            $invoice->party_id = $walkIn?->id;
            $invoice->setRelation('party', $walkIn);
        }

        return view('trade.form', $this->formData($doc, $invoice, $prefill ?? null));
    }

    /** Pending lines of an order (or every line of a quotation) as invoice lines. */
    private function linesFromOrder(Invoice $invoice, string $orderType, int $orderId, bool $link = true): array
    {
        $order = Order::with(['items.product.unit', 'items.product.units.unit', 'party'])->where('type', $orderType)->findOrFail($orderId);
        abort_if($link && ! $order->isOpen(), 404);
        $invoice->fill(['party_id' => $order->party_id, 'warehouse_id' => $order->warehouse_id, 'reference' => $order->reference ?: $order->number,
            'payment_mode' => 'credit', 'discount' => $link ? 0 : $order->discount, 'other_charges' => $link ? 0 : $order->other_charges]);
        if ($link) {
            $invoice->order_id = $order->id;
            $invoice->setRelation('order', $order);
        }
        $invoice->setRelation('party', $order->party);

        return $order->items
            ->filter(fn ($oi) => ! $link || $oi->pending_base > 0)
            ->map(fn ($oi) => $this->lineData($oi->product, [
                'unit_id' => $oi->unit_id, 'quantity' => $link ? $oi->pending : (float) $oi->quantity, 'price' => (float) $oi->price,
                'discount_percent' => (float) $oi->discount_percent, 'tax_rate' => (float) $oi->tax_rate,
                'order_item_id' => $link ? $oi->id : null, 'max_quantity' => $link ? $oi->pending : null,
            ]))->values()->all();
    }

    /** Un-invoiced challans of one customer as invoice lines (quantities fixed). */
    private function linesFromChallans(Invoice $invoice, array $ids): array
    {
        $challans = Delivery::with(['items.product.unit', 'items.product.units.unit', 'items.orderItem', 'party', 'items.product.prices'])
            ->whereIn('id', $ids)->whereNull('invoice_id')->orderBy('date')->get();
        abort_if($challans->isEmpty() || $challans->pluck('party_id')->unique()->count() > 1, 404);
        $party = $challans->first()->party;
        $invoice->fill(['party_id' => $party->id, 'warehouse_id' => $challans->first()->warehouse_id, 'payment_mode' => 'credit',
            'reference' => $challans->pluck('number')->join(', '), 'order_id' => $challans->first()->order_id]);
        $invoice->setRelation('party', $party);
        $special = $party->prices()->whereIn('product_id', $challans->flatMap->items->pluck('product_id'))->pluck('price', 'product_id');

        return $challans->flatMap->items->map(function ($di) use ($party, $special) {
            $oi = $di->orderItem;
            $price = $oi
                ? (float) $oi->price / max((float) $oi->factor, 0.0001) * (float) $di->factor
                : $di->product->priceFor($party->price_level_id, isset($special[$di->product_id]) ? (float) $special[$di->product_id] : null) * (float) $di->factor;

            return $this->lineData($di->product, [
                'unit_id' => $di->unit_id, 'quantity' => (float) $di->quantity, 'price' => round($price, 4),
                'discount_percent' => (float) ($oi?->discount_percent ?? 0), 'tax_rate' => (float) ($oi?->tax_rate ?? 0),
                'delivery_item_id' => $di->id, 'lock' => true, 'note' => $di->delivery->number,
            ]);
        })->values()->all();
    }

    private function lineData($product, array $values): array
    {
        return $values + [
            'product_id' => $product->id,
            'name' => $product->name,
            'units' => $product->loadMissing('unit', 'units.unit')->unitOptions(),
            'avg_cost' => (float) $product->avg_cost,
            'base_sale' => (float) $product->sale_price,
            'base_purchase' => (float) $product->purchase_price,
            'stock' => null,
        ];
    }

    public function store(Request $request, string $doc): RedirectResponse|JsonResponse
    {
        [$type, $perm] = self::DOCS[$doc];
        $this->allow("{$perm}.create");
        $data = $this->validated($request, $type);
        if (in_array($type, ['sale', 'sale_return'], true) && (float) ($data['paid_amount'] ?? 0) > 0) {
            $data['cash_session_id'] = CashSession::currentFor($request->user())?->id;
        }
        $invoice = $this->service->save($type, $data);

        if ($request->expectsJson()) {
            return response()->json(['id' => $invoice->id, 'number' => $invoice->number, 'print' => route('invoices.print', [$doc, $invoice])]);
        }

        if ($request->input('after') === 'print') {
            return redirect()->route('invoices.create', $doc)->with('success', "{$invoice->number} saved.")->with('print', route('invoices.print', [$doc, $invoice]));
        }

        return $request->input('after') === 'new'
            ? $this->done("{$invoice->number} saved.", 'invoices.create', $doc)
            : $this->done("{$invoice->number} saved.", 'invoices.show', [$doc, $invoice]);
    }

    public function show(string $doc, Invoice $invoice): View
    {
        $this->authorizeDoc($doc, $invoice, 'view');
        $invoice->load(['items.product', 'items.unit', 'party', 'warehouse', 'branch', 'creator', 'returnOf', 'returns', 'allocations.entry', 'order', 'deliveries']);

        return view('trade.show', ['doc' => $doc, 'meta' => self::DOCS[$doc], 'invoice' => $invoice]);
    }

    public function edit(string $doc, Invoice $invoice): View|RedirectResponse
    {
        $this->authorizeDoc($doc, $invoice, 'edit', fallback: 'create');
        if ($invoice->returns()->exists()) {
            return back()->with('error', 'This invoice has returns against it and can no longer be edited.');
        }
        $invoice->load(['items.product.unit', 'items.product.units.unit', 'items.unit', 'party', 'returnOf.items.product', 'returnOf.items.unit', 'allocations', 'order']);

        return view('trade.form', $this->formData($doc, $invoice));
    }

    public function update(Request $request, string $doc, Invoice $invoice): RedirectResponse
    {
        $this->authorizeDoc($doc, $invoice, 'edit', fallback: 'create');
        $invoice = $this->service->save($invoice->type, $this->validated($request, $invoice->type, $invoice), $invoice);

        return $this->done("{$invoice->number} updated.", 'invoices.show', [$doc, $invoice]);
    }

    public function destroy(string $doc, Invoice $invoice): RedirectResponse
    {
        $this->authorizeDoc($doc, $invoice, 'delete');
        $this->service->delete($invoice);

        return $this->done("{$invoice->number} deleted. Stock and accounts have been reversed.", 'invoices.index', $doc);
    }

    public function print(Request $request, string $doc, Invoice $invoice): View
    {
        $this->authorizeDoc($doc, $invoice, 'view');
        $invoice->load(['items.product', 'items.unit', 'party', 'branch', 'creator', 'returnOf']);
        $layout = $request->query('layout', Tenant::business()->setting('invoice_layout'));

        return view($layout === 'thermal' ? 'trade.print-thermal' : 'trade.print-a4', [
            'invoice' => $invoice,
            'b' => Tenant::business(),
            'currentBalance' => $current = ($invoice->party->is_walk_in ? null : $invoice->party->balance()),
            'previousBalance' => $current === null ? null : round($current - $this->effectOnBalance($invoice), 2),
        ]);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function authorizeDoc(string $doc, Invoice $invoice, string $action, ?string $fallback = null): void
    {
        [$type, $perm] = self::DOCS[$doc];
        abort_unless($invoice->type === $type, 404);
        // Returns have no separate "edit" permission; whoever can create them can correct them.
        if ($fallback && ! in_array($action, config("billtrix.permissions.{$perm}", []), true)) {
            $action = $fallback;
        }
        $this->allow("{$perm}.{$action}");
    }

    /** Amount received/paid on the invoice itself (not through later vouchers or returns). */
    private function paidAtInvoice(Invoice $invoice): float
    {
        return (float) PaymentAllocation::where('invoice_id', $invoice->id)
            ->whereHas('entry', fn ($q) => $q->where('source_type', 'invoice')->where('source_id', $invoice->id)->whereIn('type', ['receipt', 'payment']))
            ->sum('amount');
    }

    /** What this invoice did to the party's balance (positive = increased what they owe us). */
    private function effectOnBalance(Invoice $invoice): float
    {
        $net = (float) $invoice->total - $this->paidAtInvoice($invoice);

        return match ($invoice->type) {
            'sale', 'purchase_return' => $net,
            default => -$net,
        };
    }

    private function defaultWarehouseId(): ?int
    {
        $branchId = auth()->user()->branch_id;

        return Warehouse::where('is_active', true)
            ->orderByRaw('CASE WHEN branch_id = ? THEN 0 ELSE 1 END', [$branchId])
            ->orderByDesc('is_default')
            ->value('id');
    }

    private function formData(string $doc, Invoice $invoice, ?array $prefill = null): array
    {
        $paidNow = $invoice->exists ? $this->paidAtInvoice($invoice) : null;

        return [
            'doc' => $doc,
            'meta' => self::DOCS[$doc],
            'invoice' => $invoice,
            'paidNow' => $paidNow,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'cashAccounts' => Account::cashOrBank()->orderBy('code')->get(['id', 'code', 'name', 'subtype']),
            'lines' => $this->formLines($invoice, $prefill),
        ];
    }

    /** Line data for the JS editor (existing lines or the original invoice's lines for a return). */
    private function formLines(Invoice $invoice, ?array $prefill = null): array
    {
        $map = fn ($item, bool $fromOriginal) => [
            'product_id' => $item->product_id,
            'name' => $item->product->name,
            'units' => $item->product->loadMissing('unit', 'units.unit')->unitOptions(),
            'unit_id' => $item->unit_id,
            'quantity' => $fromOriginal ? 0 : (float) $item->quantity,
            'max_quantity' => $fromOriginal ? (float) $item->quantity : null,
            'price' => (float) $item->price,
            'discount_percent' => (float) $item->discount_percent,
            'tax_rate' => (float) $item->tax_rate,
            'source_item_id' => $fromOriginal ? $item->id : $item->source_item_id,
            'order_item_id' => $fromOriginal ? null : $item->order_item_id,
            'delivery_item_id' => $fromOriginal ? null : $item->delivery_item_id,
            'lock' => ! $fromOriginal && $item->delivery_item_id,
            'avg_cost' => (float) $item->product->avg_cost,
            'base_sale' => (float) $item->product->sale_price,
            'base_purchase' => (float) $item->product->purchase_price,
            'stock' => null,
        ];

        // Re-show what the user typed after a validation error.
        if ($old = $this->oldLines()) {
            return $old;
        }

        if ($invoice->exists) {
            return $invoice->items->map(fn ($i) => $map($i, false))->all();
        }
        if ($prefill !== null) {
            return $prefill;
        }
        if ($invoice->relationLoaded('returnOf') && $invoice->returnOf) {
            return $invoice->returnOf->items->map(fn ($i) => $map($i, true))->all();
        }

        return [];
    }

    private function validated(Request $request, string $type, ?Invoice $invoice = null): array
    {
        $partyScope = in_array($type, ['sale', 'sale_return'], true) ? ['customer', 'both'] : ['supplier', 'both'];

        $data = $request->validate([
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'party_id' => ['required', $this->owned('parties')->whereIn('type', $partyScope)],
            'warehouse_id' => ['required', $this->owned('warehouses')],
            'return_of_id' => ['nullable', $this->owned('invoices')],
            'payment_mode' => ['nullable', Rule::in(['cash', 'credit'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'round_off' => ['nullable', 'numeric', 'between:-100,100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'paid_amount' => ['nullable', 'numeric', 'min:0'],
            'payment_account_id' => ['nullable', $this->owned('accounts')->whereIn('subtype', ['cash', 'bank'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', $this->owned('products')],
            'items.*.unit_id' => ['required', $this->owned('units')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.source_item_id' => ['nullable', 'integer'],
            'items.*.order_item_id' => ['nullable', 'integer'],
            'items.*.delivery_item_id' => ['nullable', 'integer'],
            'order_id' => ['nullable', $this->owned('orders')],
        ], [
            'items.required' => 'Add at least one item.',
            'items.*.quantity.gt' => 'Quantity must be more than zero.',
            'party_id.exists' => 'Choose a valid '.(in_array($type, ['sale', 'sale_return'], true) ? 'customer' : 'supplier').'.',
        ]);

        $data['items'] = array_values($data['items']);

        return $data;
    }
}
