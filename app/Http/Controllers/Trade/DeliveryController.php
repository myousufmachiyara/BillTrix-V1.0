<?php

namespace App\Http\Controllers\Trade;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Party;
use App\Models\Warehouse;
use App\Services\DeliveryService;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Delivery challans (outgoing goods, invoiced later). */
class DeliveryController extends Controller
{
    public function __construct(private DeliveryService $service) {}

    public function index(Request $request): View
    {
        $this->allow('deliveries.view');

        $deliveries = Delivery::with(['party', 'order', 'invoice'])->withCount('items')
            ->when($request->query('status') === 'pending', fn ($q) => $q->whereNull('invoice_id'))
            ->when($request->query('status') === 'invoiced', fn ($q) => $q->whereNotNull('invoice_id'))
            ->when($request->query('party_id'), fn ($q, $p) => $q->where('party_id', $p))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->latest('date')->latest('id')->paginate(50)->withQueryString();

        return view('deliveries.index', ['deliveries' => $deliveries, 'party' => Party::find($request->integer('party_id'))]);
    }

    public function create(Request $request): View
    {
        $this->allow('deliveries.create');
        $delivery = new Delivery(['date' => now(), 'warehouse_id' => Warehouse::where('is_active', true)->orderByDesc('is_default')->value('id')]);
        $order = null;

        if ($request->query('order')) {
            $order = Order::with(['items.product.unit', 'items.product.units.unit', 'items.unit', 'party'])->where('type', 'sale_order')->findOrFail($request->query('order'));
            abort_unless($order->isOpen(), 404);
            $delivery->fill(['party_id' => $order->party_id, 'order_id' => $order->id, 'warehouse_id' => $order->warehouse_id]);
            $delivery->setRelation('party', $order->party);
        }

        return view('deliveries.form', $this->formData($delivery, $order));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow('deliveries.create');
        $delivery = $this->service->save($this->validated($request));

        return $this->done(__('Challan :n saved. Stock has been dispatched.', ['n' => $delivery->number]), 'deliveries.show', $delivery);
    }

    public function show(Delivery $delivery): View
    {
        $this->allow('deliveries.view');

        return view('deliveries.show', ['delivery' => $delivery->load(['items.product', 'items.unit', 'party', 'order', 'invoice', 'warehouse', 'creator'])]);
    }

    public function edit(Delivery $delivery): View|RedirectResponse
    {
        $this->allow('deliveries.edit');
        if ($delivery->invoice_id) {
            return back()->with('error', __('This challan has been invoiced and can no longer be edited.'));
        }
        $delivery->load(['items.product.unit', 'items.product.units.unit', 'items.unit', 'party']);
        $order = $delivery->order_id ? Order::with(['items.product.unit', 'items.product.units.unit', 'items.unit'])->find($delivery->order_id) : null;

        return view('deliveries.form', $this->formData($delivery, $order));
    }

    public function update(Request $request, Delivery $delivery): RedirectResponse
    {
        $this->allow('deliveries.edit');
        $delivery = $this->service->save($this->validated($request), $delivery);

        return $this->done(__('Challan :n updated.', ['n' => $delivery->number]), 'deliveries.show', $delivery);
    }

    public function destroy(Delivery $delivery): RedirectResponse
    {
        $this->allow('deliveries.delete');
        $this->service->delete($delivery);

        return $this->done(__('Challan :n deleted and stock returned.', ['n' => $delivery->number]), 'deliveries.index');
    }

    public function print(Delivery $delivery): View
    {
        $this->allow('deliveries.view');

        return view('deliveries.print', ['delivery' => $delivery->load(['items.product', 'items.unit', 'party', 'order', 'branch', 'creator']), 'b' => Tenant::business()]);
    }

    private function formData(Delivery $delivery, ?Order $order): array
    {
        // Rows: every open order line (with what's pending), plus any extra lines already on the challan.
        $existing = $delivery->exists ? $delivery->items : collect();
        $rows = collect();
        if ($order) {
            foreach ($order->items as $oi) {
                $mine = $existing->firstWhere('order_item_id', $oi->id);
                $pendingBase = $oi->pending_base + ($mine ? (float) $mine->base_quantity : 0);
                $rows->push([
                    'order_item_id' => $oi->id, 'product_id' => $oi->product_id, 'name' => $oi->product->name,
                    'units' => $oi->product->unitOptions(), 'unit_id' => $mine?->unit_id ?? $oi->unit_id,
                    'ordered' => qty($oi->quantity).' '.$oi->unit->short_name,
                    'pending' => round($pendingBase / max((float) $oi->factor, 0.0001), 4),
                    'quantity' => $mine ? (float) $mine->quantity : 0,
                ]);
            }
        }
        foreach ($existing->whereNull('order_item_id') as $di) {
            $rows->push(['order_item_id' => null, 'product_id' => $di->product_id, 'name' => $di->product->name,
                'units' => $di->product->unitOptions(), 'unit_id' => $di->unit_id, 'ordered' => null, 'pending' => null, 'quantity' => (float) $di->quantity]);
        }

        return [
            'delivery' => $delivery,
            'order' => $order,
            'rows' => $rows->values()->all(),
            'warehouses' => Warehouse::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'openOrders' => $delivery->party_id ? Order::where('type', 'sale_order')->where('party_id', $delivery->party_id)->whereIn('status', ['open', 'partial'])->pluck('number', 'id') : collect(),
        ];
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'party_id' => ['required', $this->owned('parties')->whereIn('type', ['customer', 'both'])],
            'warehouse_id' => ['required', $this->owned('warehouses')],
            'order_id' => ['nullable', $this->owned('orders')->where('type', 'sale_order')],
            'date' => ['required', 'date'],
            'vehicle_no' => ['nullable', 'string', 'max:30'],
            'driver' => ['nullable', 'string', 'max:80'],
            'ship_to' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', $this->owned('products')],
            'items.*.unit_id' => ['required', $this->owned('units')],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'items.*.order_item_id' => ['nullable', 'integer'],
        ]);
    }
}
