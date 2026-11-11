<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Keeps the stock ledger, per-warehouse balances and the moving weighted
 * average cost in step. Always call inside a DB transaction.
 */
class StockService
{
    /** Bring stock in. Recalculates the product's average cost. */
    public function receive(Product $product, int $warehouseId, float $qty, float $unitCost, string $date, string $type, Model $source, ?string $reason = null): void
    {
        if ($qty <= 0 || ! $product->track_stock) {
            return;
        }

        $product = $this->lockProduct($product);
        $onHand = $this->totalOnHand($product->id);

        // Moving weighted average. If stock was zero/negative, the new cost wins.
        $product->avg_cost = $onHand > 0
            ? round((($onHand * (float) $product->avg_cost) + ($qty * $unitCost)) / ($onHand + $qty), 4)
            : round($unitCost, 4);
        $product->saveQuietly();

        $this->move($product, $warehouseId, $qty, $unitCost, $date, $type, $source, $reason);
    }

    /**
     * Take stock out at the current average cost (or a given cost for purchase returns).
     * Returns the unit cost used.
     */
    public function issue(Product $product, int $warehouseId, float $qty, string $date, string $type, Model $source, ?float $unitCost = null, ?string $reason = null): float
    {
        if ($qty <= 0 || ! $product->track_stock) {
            return 0.0;
        }

        $product = $this->lockProduct($product);
        $balance = $this->lockBalance($product->id, $warehouseId);

        if (! Tenant::business()->feature('allow_negative_stock') && (float) $balance->quantity + 0.00001 < $qty) {
            throw ValidationException::withMessages([
                'items' => sprintf('Not enough stock for "%s": %s available, %s required.',
                    $product->name, $this->fmt((float) $balance->quantity), $this->fmt($qty)),
            ]);
        }

        $cost = $unitCost ?? (float) $product->avg_cost;

        // Issuing at a cost other than average (purchase return) shifts the average of what remains.
        if ($unitCost !== null) {
            $onHand = $this->totalOnHand($product->id);
            $remaining = $onHand - $qty;
            if ($remaining > 0) {
                $product->avg_cost = max(0, round((($onHand * (float) $product->avg_cost) - ($qty * $unitCost)) / $remaining, 4));
                $product->saveQuietly();
            }
        }

        $this->move($product, $warehouseId, -$qty, $cost, $date, $type, $source, $reason);

        return $cost;
    }

    /** Move stock between warehouses; cost is unchanged. */
    public function transfer(Product $product, int $fromId, int $toId, float $qty, string $date, Model $source): void
    {
        $this->issue($product, $fromId, $qty, $date, 'transfer_out', $source);
        $this->move($this->lockProduct($product), $toId, $qty, (float) $product->avg_cost, $date, 'transfer_in', $source, null);
    }

    /**
     * Undo every movement produced by a source document, restoring balances
     * and unwinding its effect on average cost, then delete the movements.
     */
    public function reverse(Model $source): void
    {
        $movements = StockMovement::where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->orderByDesc('id')
            ->get();

        foreach ($movements as $m) {
            $product = $this->lockProduct(Product::findOrFail($m->product_id));
            $qty = (float) $m->quantity;
            $cost = (float) $m->unit_cost;

            if (! in_array($m->type, ['transfer_in', 'transfer_out'], true)) {
                $onHand = $this->totalOnHand($product->id);
                $after = $onHand - $qty;
                if ($after > 0) {
                    $product->avg_cost = max(0, round((($onHand * (float) $product->avg_cost) - ($qty * $cost)) / $after, 4));
                    $product->saveQuietly();
                }
            }

            $balance = $this->lockBalance($m->product_id, $m->warehouse_id);
            $balance->quantity = (float) $balance->quantity - $qty;
            $balance->save();

            $m->delete();
        }
    }

    public function onHand(int $productId, ?int $warehouseId = null): float
    {
        $q = StockBalance::where('product_id', $productId);
        if ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        }

        return (float) $q->sum('quantity');
    }

    private function move(Product $product, int $warehouseId, float $qty, float $unitCost, string $date, string $type, Model $source, ?string $reason): void
    {
        $balance = $this->lockBalance($product->id, $warehouseId);
        $balance->quantity = (float) $balance->quantity + $qty;
        $balance->save();

        StockMovement::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouseId,
            'date' => $date,
            'type' => $type,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'reason' => $reason,
        ]);
    }

    private function lockProduct(Product $product): Product
    {
        return Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
    }

    private function lockBalance(int $productId, int $warehouseId): StockBalance
    {
        $balance = StockBalance::where('product_id', $productId)->where('warehouse_id', $warehouseId)->lockForUpdate()->first();

        return $balance ?? StockBalance::create(['product_id' => $productId, 'warehouse_id' => $warehouseId, 'quantity' => 0]);
    }

    private function totalOnHand(int $productId): float
    {
        return (float) StockBalance::where('product_id', $productId)->sum('quantity');
    }

    private function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ','), '0'), '.');
    }
}
