<?php

namespace App\Http\Controllers\Trade;

use App\Http\Controllers\Concerns\ReshowsOldLines;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Party;
use App\Models\Warehouse;
use App\Services\OrderService;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Quotations, sales orders and purchase orders. */
class OrderController extends Controller
{
    use ReshowsOldLines;

    /** url segment => [order type, permission module, party type, label, feature switch] */
    public const DOCS = [
        'quotations' => ['quotation', 'quotations', 'customer', 'Quotations', 'quotations'],
        'sales-orders' => ['sale_order', 'sales_orders', 'customer', 'Sales Orders', 'sales_orders'],
        'purchase-orders' => ['purchase_order', 'purchase_orders', 'supplier', 'Purchase Orders', 'purchase_orders'],
    ];

    public function __construct(private OrderService $service) {}

    public static function docFor(string $type): string
    {
        return collect(self::DOCS)->search(fn ($d) => $d[0] === $type);
    }

    public function index(Request $request, string $orderDoc): View
    {
        [$type, $perm] = $this->meta($orderDoc);
        $this->allow("{$perm}.view");

        $orders = Order::with('party')->where('type', $type)
            ->when($request->query('status'), fn ($q, $s) => $s === 'pending' ? $q->whereIn('status', ['open', 'partial']) : $q->where('status', $s))
            ->when($request->query('party_id'), fn ($q, $p) => $q->where('party_id', $p))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', "%{$t}%")->orWhere('reference', 'like', "%{$t}%")))
            ->latest('date')->latest('id')->paginate(50)->withQueryString();

        return view('orders.index', [
            'doc' => $orderDoc, 'meta' => self::DOCS[$orderDoc], 'orders' => $orders,
            'party' => Party::find($request->integer('party_id')),
        ]);
    }

    public function create(Request $request, string $orderDoc): View
    {
        [$type, $perm] = $this->meta($orderDoc);
        $this->allow("{$perm}.create");

        $order = new Order(['type' => $type, 'date' => now(), 'warehouse_id' => $this->defaultWarehouseId(),
            'due_date' => $type === 'quotation' ? now()->addDays(7) : null,
            'terms' => $type === 'quotation' ? Tenant::business()->setting('quotation_terms') : null]);

        // A sales order can start from a quotation.
        if ($type === 'sale_order' && $request->query('quotation')) {
            $quote = Order::with(['items.product.unit', 'items.product.units.unit', 'party'])->where('type', 'quotation')->findOrFail($request->query('quotation'));
            $order->fill(['party_id' => $quote->party_id, 'quotation_id' => $quote->id, 'reference' => $quote->reference,
                'discount' => $quote->discount, 'other_charges' => $quote->other_charges, 'warehouse_id' => $quote->warehouse_id]);
            $order->setRelation('party', $quote->party);
            $order->setRelation('items', $quote->items->map(fn ($i) => $i->replicate(['order_id', 'fulfilled_quantity'])));
        }

        return view('orders.form', $this->formData($orderDoc, $order));
    }

    public function store(Request $request, string $orderDoc): RedirectResponse
    {
        [$type, $perm] = $this->meta($orderDoc);
        $this->allow("{$perm}.create");
        $order = $this->service->save($type, $this->validated($request, $type));

        return $this->done("{$order->number} saved.", 'orders.show', [$orderDoc, $order]);
    }

    public function show(string $orderDoc, Order $order): View
    {
        [$type, $perm] = $this->meta($orderDoc, $order);
        $this->allow("{$perm}.view");
        $order->load(['items.product', 'items.unit', 'party', 'warehouse', 'creator', 'quotation', 'invoices', 'deliveries.invoice']);

        return view('orders.show', [
            'doc' => $orderDoc, 'meta' => self::DOCS[$orderDoc], 'order' => $order,
            'children' => $type === 'quotation' ? Order::where('quotation_id', $order->id)->get() : collect(),
        ]);
    }

    public function edit(string $orderDoc, Order $order): View|RedirectResponse
    {
        [, $perm] = $this->meta($orderDoc, $order);
        $this->allow("{$perm}.edit");
        if (in_array($order->status, ['cancelled', 'converted', 'completed'], true)) {
            return back()->with('error', "This {$order->title} is {$order->status} and can't be edited.");
        }
        $order->load(['items.product.unit', 'items.product.units.unit', 'party']);

        return view('orders.form', $this->formData($orderDoc, $order));
    }

    public function update(Request $request, string $orderDoc, Order $order): RedirectResponse
    {
        [$type, $perm] = $this->meta($orderDoc, $order);
        $this->allow("{$perm}.edit");
        $order = $this->service->save($type, $this->validated($request, $type), $order);

        return $this->done("{$order->number} updated.", 'orders.show', [$orderDoc, $order]);
    }

    public function destroy(string $orderDoc, Order $order): RedirectResponse
    {
        [, $perm] = $this->meta($orderDoc, $order);
        $this->allow("{$perm}.delete");
        $this->service->delete($order);

        return $this->done("{$order->number} deleted.", 'orders.index', $orderDoc);
    }

    public function cancel(Request $request, string $orderDoc, Order $order): RedirectResponse
    {
        [, $perm] = $this->meta($orderDoc, $order);
        $this->allow("{$perm}.edit");
        $cancel = ! $request->boolean('reopen');
        $this->service->setCancelled($order, $cancel);

        return back()->with('success', $cancel ? "{$order->number} cancelled. Nothing more will be delivered or billed against it." : "{$order->number} re-opened.");
    }

    public function print(string $orderDoc, Order $order): View
    {
        [, $perm] = $this->meta($orderDoc, $order);
        $this->allow("{$perm}.view");

        return view('orders.print', ['order' => $order->load(['items.product', 'items.unit', 'party', 'branch', 'creator']), 'b' => Tenant::business()]);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function meta(string $doc, ?Order $order = null): array
    {
        $meta = self::DOCS[$doc];
        abort_unless(Tenant::business()->feature($meta[4]), 404);
        abort_if($order && $order->type !== $meta[0], 404);

        return $meta;
    }

    private function defaultWarehouseId(): ?int
    {
        return Warehouse::where('is_active', true)
            ->orderByRaw('CASE WHEN branch_id = ? THEN 0 ELSE 1 END', [auth()->user()->branch_id])
            ->orderByDesc('is_default')->value('id');
    }

    private function formData(string $doc, Order $order): array
    {
        return [
            'doc' => $doc,
            'meta' => self::DOCS[$doc],
            'order' => $order,
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'lines' => $this->oldLines() ?? ($order->relationLoaded('items') ? $order->items->map(fn ($i) => [
                'id' => $order->exists ? $i->id : null,
                'product_id' => $i->product_id,
                'name' => $i->product->name,
                'units' => $i->product->unitOptions(),
                'unit_id' => $i->unit_id,
                'quantity' => (float) $i->quantity,
                'price' => (float) $i->price,
                'discount_percent' => (float) $i->discount_percent,
                'tax_rate' => (float) $i->tax_rate,
                'avg_cost' => (float) $i->product->avg_cost,
                'base_sale' => (float) $i->product->sale_price,
                'base_purchase' => (float) $i->product->purchase_price,
                'stock' => null,
            ])->values()->all() : []),
        ];
    }

    private function validated(Request $request, string $type): array
    {
        $partyScope = $type === 'purchase_order' ? ['supplier', 'both'] : ['customer', 'both'];

        return $request->validate([
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'party_id' => ['required', $this->owned('parties')->whereIn('type', $partyScope)],
            'warehouse_id' => ['required', $this->owned('warehouses')],
            'quotation_id' => ['nullable', $this->owned('orders')->where('type', 'quotation')],
            'reference' => ['nullable', 'string', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.product_id' => ['required', $this->owned('products')],
            'items.*.unit_id' => ['required', $this->owned('units')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
        ], ['items.required' => 'Add at least one item.']);
    }
}
