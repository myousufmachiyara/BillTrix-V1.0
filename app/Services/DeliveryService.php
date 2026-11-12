<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Party;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Delivery challans. Goods leave stock at their average cost and sit in
 * "Goods Delivered Not Invoiced" until the challan is billed on a sale invoice.
 */
class DeliveryService
{
    public function __construct(
        private SequenceService $sequences,
        private StockService $stock,
        private LedgerService $ledger,
        private OrderService $orders,
    ) {}

    /** @param array $data party_id, warehouse_id, date, order_id?, vehicle_no?, driver?, ship_to?, notes?, items: [product_id, unit_id, quantity, order_item_id?] */
    public function save(array $data, ?Delivery $delivery = null): Delivery
    {
        return DB::transaction(function () use ($data, $delivery) {
            $affectedOrders = [];
            if ($delivery) {
                if ($delivery->invoice_id) {
                    throw ValidationException::withMessages(['delivery' => __('This challan has been invoiced. Edit or delete the invoice first.')]);
                }
                $affectedOrders[] = $delivery->order_id;
                $this->unpost($delivery);
                $delivery->items()->delete();
            }

            $party = Party::findOrFail($data['party_id']);
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);
            $order = ! empty($data['order_id']) ? Order::where('type', 'sale_order')->findOrFail($data['order_id']) : null;
            if ($order && ($order->party_id !== $party->id || $order->status === 'cancelled')) {
                throw ValidationException::withMessages(['order_id' => __('The order does not belong to this customer or is cancelled.')]);
            }

            $items = collect(array_values($data['items']))->filter(fn ($i) => (float) ($i['quantity'] ?? 0) > 0)->values();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['items' => __('Enter a quantity for at least one item.')]);
            }

            $products = Product::with('units')->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');
            $variants = \App\Models\ProductVariant::whereIn('id', $items->pluck('variant_id')->filter())->get()->keyBy('id');
            $orderItems = OrderItem::whereIn('id', $items->pluck('order_item_id')->filter())->get()->keyBy('id');
            $pending = $this->orders->pendingBase($orderItems->keys()->all());

            $delivery ??= new Delivery(['number' => $this->sequences->next('delivery'), 'created_by' => auth()->id()]);
            $delivery->fill([
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'party_id' => $party->id,
                'order_id' => $order?->id,
                'date' => $data['date'],
                'vehicle_no' => $data['vehicle_no'] ?? null,
                'driver' => $data['driver'] ?? null,
                'ship_to' => $data['ship_to'] ?? null,
                'notes' => $data['notes'] ?? null,
            ])->save();

            $cost = 0.0;
            foreach ($items as $i => $item) {
                $product = $products[$item['product_id']] ?? throw ValidationException::withMessages(["items.$i.product_id" => __('Invalid product.')]);
                $variant = $product->has_variants ? $variants->get((int) ($item['variant_id'] ?? 0)) : null;
                if ($product->has_variants && (! $variant || (int) $variant->product_id !== $product->id)) {
                    throw ValidationException::withMessages(["items.$i.variant_id" => __('Choose a variation (size, colour…) for ":name".', ['name' => $product->name])]);
                }
                $unitId = (int) $item['unit_id'];
                $factor = $unitId === $product->unit_id ? 1.0
                    : (float) ($product->units->firstWhere('unit_id', $unitId)?->factor ?? throw ValidationException::withMessages(["items.$i.unit_id" => "Unit not set up for \"{$product->name}\"."]));
                $qty = round((float) $item['quantity'], 4);
                $base = round($qty * $factor, 4);

                $orderItemId = ($item['order_item_id'] ?? null) ?: null;
                if ($orderItemId) {
                    $oi = $orderItems->get($orderItemId);
                    if (! $oi || $oi->order_id !== $order?->id || $oi->product_id !== $product->id || $oi->variant_id !== $variant?->id) {
                        throw ValidationException::withMessages(["items.$i.product_id" => "\"{$product->name}\" is not on the selected order."]);
                    }
                    if ($base > ($pending[$oi->id] ?? 0) + 0.00001) {
                        throw ValidationException::withMessages(["items.$i.quantity" => 'Only '.qty(($pending[$oi->id] ?? 0) / $factor)." of \"{$product->name}\" is still pending on the order."]);
                    }
                    $pending[$oi->id] -= $base;
                }

                $unitCost = $this->stock->issue($product, $warehouse->id, $base, $delivery->date->toDateString(), 'delivery', $delivery, variant: $variant);
                $delivery->items()->create([
                    'order_item_id' => $orderItemId, 'product_id' => $product->id, 'variant_id' => $variant?->id, 'unit_id' => $unitId, 'factor' => $factor,
                    'quantity' => $qty, 'base_quantity' => $base, 'unit_cost' => $unitCost,
                ]);
                $cost += $unitCost * $base;
            }

            $cost = round($cost, 2);
            $delivery->forceFill(['cost_total' => $cost])->saveQuietly();

            if ($cost > 0) {
                $this->ledger->post([
                    'type' => 'stock',
                    'date' => $delivery->date->toDateString(),
                    'source' => $delivery,
                    'party_id' => $party->id,
                    'branch_id' => $delivery->branch_id,
                    'reference' => $delivery->number,
                    'narration' => "Delivery challan {$delivery->number} — {$party->name}",
                ], [
                    ['account' => 'gdni', 'debit' => $cost],
                    ['account' => 'inventory', 'credit' => $cost],
                ]);
            }

            $this->orders->refreshMany([...$affectedOrders, $order?->id]);

            return $delivery->fresh(['items.product', 'items.unit', 'party']);
        });
    }

    public function delete(Delivery $delivery): void
    {
        if ($delivery->invoice_id) {
            throw ValidationException::withMessages(['delivery' => __('This challan has been invoiced. Delete the invoice first.')]);
        }
        DB::transaction(function () use ($delivery) {
            $orderId = $delivery->order_id;
            $this->unpost($delivery);
            $delivery->delete();
            $this->orders->refreshMany([$orderId]);
        });
    }

    private function unpost(Delivery $delivery): void
    {
        $this->stock->reverse($delivery);
        $this->ledger->deleteForSource($delivery);
    }
}
