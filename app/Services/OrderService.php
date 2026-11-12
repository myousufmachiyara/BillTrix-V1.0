<?php

namespace App\Services;

use App\Models\DeliveryItem;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Party;
use App\Models\Warehouse;
use App\Services\Concerns\CalculatesLines;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quotations, sales orders and purchase orders. They don't move stock or post
 * to the ledger; they track how much has been delivered, received or invoiced.
 */
class OrderService
{
    use CalculatesLines;

    public function __construct(private SequenceService $sequences) {}

    public function save(string $type, array $data, ?Order $order = null): Order
    {
        abort_unless(array_key_exists($type, Order::TYPES), 404);

        return DB::transaction(function () use ($type, $data, $order) {
            $party = Party::findOrFail($data['party_id']);
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);
            $lines = $this->calculateLines($data['items']);
            $totals = $this->totals($lines, $data);

            if ($order) {
                $this->guardEditable($order, $lines);
            }

            $order ??= new Order(['type' => $type, 'number' => $this->sequences->next($type), 'created_by' => auth()->id()]);
            $order->fill([
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'date' => $data['date'],
                'due_date' => $data['due_date'] ?? null,
                'party_id' => $party->id,
                'quotation_id' => $data['quotation_id'] ?? $order->quotation_id,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ] + collect($totals)->except('round_off')->all());
            $order->save();

            // Keep existing line ids where possible so delivered/invoiced links survive edits.
            $keep = [];
            foreach (array_values($data['items']) as $i => $item) {
                $values = collect($lines[$i])->except(['product', 'net'])->all();
                $existing = ! empty($item['id']) ? $order->items()->whereKey($item['id'])->first() : null;
                if ($existing) {
                    $existing->update($values);
                    $keep[] = $existing->id;
                } else {
                    $keep[] = $order->items()->create($values)->id;
                }
            }
            $order->items()->whereNotIn('id', $keep)->delete();

            if (! empty($data['quotation_id'])) {
                Order::whereKey($data['quotation_id'])->where('type', 'quotation')->update(['status' => 'converted']);
            }

            $this->refresh($order);

            return $order->fresh(['items.product', 'items.unit', 'party']);
        });
    }

    public function delete(Order $order): void
    {
        if ($this->hasActivity($order)) {
            throw ValidationException::withMessages(['order' => __('Goods have already been delivered, received or invoiced against this order. Cancel it instead.')]);
        }
        DB::transaction(function () use ($order) {
            Order::where('quotation_id', $order->id)->update(['quotation_id' => null]);
            $order->delete();
        });
    }

    public function setCancelled(Order $order, bool $cancelled): void
    {
        $order->update(['status' => $cancelled ? 'cancelled' : 'open']);
        if (! $cancelled) {
            $this->refresh($order);
        }
    }

    /** Base quantity still pending per order line (live, not cached). */
    public function pendingBase(array $orderItemIds): array
    {
        if (! $orderItemIds) {
            return [];
        }
        $done = $this->fulfilledBase($orderItemIds);

        return OrderItem::whereIn('id', $orderItemIds)->get(['id', 'base_quantity'])
            ->mapWithKeys(fn ($oi) => [$oi->id => max(round((float) $oi->base_quantity - ($done[$oi->id] ?? 0), 4), 0)])
            ->all();
    }

    public function refreshMany(array $orderIds): void
    {
        Order::whereIn('id', array_unique(array_filter($orderIds)))->get()->each(fn (Order $o) => $this->refresh($o));
    }

    /** Recompute fulfilled quantities and the order status. */
    public function refresh(Order $order): void
    {
        $items = $order->items()->get();
        $done = $this->fulfilledBase($items->pluck('id')->all());

        foreach ($items as $item) {
            $item->fulfilled_quantity = round($done[$item->id] ?? 0, 4);
            $item->saveQuietly();
        }

        if (in_array($order->status, ['cancelled', 'converted'], true) || $order->type === 'quotation') {
            return;
        }

        $ordered = $items->sum(fn ($i) => (float) $i->base_quantity);
        $fulfilled = $items->sum(fn ($i) => min((float) $i->fulfilled_quantity, (float) $i->base_quantity));
        $order->status = match (true) {
            $fulfilled <= 0 => 'open',
            $fulfilled + 0.00001 >= $ordered => 'completed',
            default => 'partial',
        };
        $order->saveQuietly();
    }

    /**
     * Delivered (challans) + invoiced directly (not through a challan), per order line.
     * For purchase orders only invoices count.
     */
    private function fulfilledBase(array $orderItemIds): array
    {
        $out = [];
        foreach ([
            DeliveryItem::whereIn('order_item_id', $orderItemIds),
            InvoiceItem::whereIn('order_item_id', $orderItemIds)->whereNull('delivery_item_id'),
        ] as $q) {
            foreach ($q->selectRaw('order_item_id, SUM(base_quantity) as q')->groupBy('order_item_id')->pluck('q', 'order_item_id') as $id => $qty) {
                $out[$id] = ($out[$id] ?? 0) + (float) $qty;
            }
        }

        return $out;
    }

    private function hasActivity(Order $order): bool
    {
        $ids = $order->items()->pluck('id');

        return DeliveryItem::whereIn('order_item_id', $ids)->exists() || InvoiceItem::whereIn('order_item_id', $ids)->exists();
    }

    /** A line can't drop below what has already been fulfilled, and fulfilled lines can't be removed. */
    private function guardEditable(Order $order, $lines): void
    {
        if (in_array($order->status, ['cancelled', 'converted'], true)) {
            throw ValidationException::withMessages(['order' => 'This document is '.$order->status.' and can no longer be edited.']);
        }
        $done = $this->fulfilledBase($order->items()->pluck('id')->all());
        if (array_sum($done) > 0) {
            $byProduct = $order->items()->get()->groupBy('product_id')->map(fn ($g) => $g->sum(fn ($i) => $done[$i->id] ?? 0));
            foreach ($byProduct as $productId => $qty) {
                $now = $lines->where('product_id', $productId)->sum('base_quantity');
                if ($qty > 0 && $now + 0.00001 < $qty) {
                    throw ValidationException::withMessages(['items' => __('You cannot order less than has already been delivered or invoiced.')]);
                }
            }
        }
    }
}
