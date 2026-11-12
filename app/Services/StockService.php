<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Keeps the stock ledger, per-warehouse balances and the moving weighted
 * average cost in step. Always call inside a DB transaction.
 *
 * Products with batch tracking also keep stock per batch: receipts go into the
 * given batch, issues pick the earliest expiry first (FEFO) and never sell an
 * expired batch. Cost stays the product's moving average.
 *
 * Products with variations keep stock and average cost per variant: pass the
 * variant to every call. Balances use variant_id 0 for products without variants.
 */
class StockService
{
    /** Bring stock in. Recalculates the product's average cost. */
    /** @param array{batch_no?:?string, expiry_date?:?string}|null $batch */
    public function receive(Product $product, int $warehouseId, float $qty, float $unitCost, string $date, string $type, Model $source, ?string $reason = null, ?array $batch = null, ?ProductVariant $variant = null): void
    {
        if ($qty <= 0 || ! $product->track_stock) {
            return;
        }

        $product = $this->lockProduct($product);
        $variant = $this->checkVariant($product, $variant);
        $holder = $variant ?? $product;
        $onHand = $this->totalOnHand($product->id, $variant?->id);

        // Moving weighted average. If stock was zero/negative, the new cost wins.
        $holder->avg_cost = $onHand > 0
            ? round((($onHand * (float) $holder->avg_cost) + ($qty * $unitCost)) / ($onHand + $qty), 4)
            : round($unitCost, 4);
        $holder->saveQuietly();

        $batchId = $product->track_batches ? $this->batch($product, $batch['batch_no'] ?? null, $batch['expiry_date'] ?? null)->id : null;
        $this->move($product, $warehouseId, $qty, $unitCost, $date, $type, $source, $reason, $batchId, $variant);
        $this->refreshProductCost($product, $variant);
    }

    /**
     * Take stock out at the current average cost (or a given cost for purchase returns).
     * Returns the unit cost used.
     */
    public function issue(Product $product, int $warehouseId, float $qty, string $date, string $type, Model $source, ?float $unitCost = null, ?string $reason = null, ?string $batchNo = null, ?ProductVariant $variant = null): float
    {
        if ($qty <= 0 || ! $product->track_stock) {
            return 0.0;
        }

        $product = $this->lockProduct($product);
        $variant = $this->checkVariant($product, $variant);
        $holder = $variant ?? $product;
        $balance = $this->lockBalance($product->id, $warehouseId, $variant?->id);

        if (! Tenant::business()->feature('allow_negative_stock') && (float) $balance->quantity + 0.00001 < $qty) {
            throw ValidationException::withMessages([
                'items' => sprintf('Not enough stock for "%s": %s available, %s required.',
                    $this->label($product, $variant), $this->fmt((float) $balance->quantity), $this->fmt($qty)),
            ]);
        }

        $cost = $unitCost ?? (float) $holder->avg_cost;

        // Issuing at a cost other than average (purchase return) shifts the average of what remains.
        if ($unitCost !== null) {
            $onHand = $this->totalOnHand($product->id, $variant?->id);
            $remaining = $onHand - $qty;
            if ($remaining > 0) {
                $holder->avg_cost = max(0, round((($onHand * (float) $holder->avg_cost) - ($qty * $unitCost)) / $remaining, 4));
                $holder->saveQuietly();
            }
        }

        foreach ($this->allocate($product, $warehouseId, $qty, $type, $batchNo) as [$batchId, $part]) {
            $this->move($product, $warehouseId, -$part, $cost, $date, $type, $source, $reason, $batchId, $variant);
        }
        $this->refreshProductCost($product, $variant);

        return $cost;
    }

    /** Move stock between warehouses; cost is unchanged and batches travel with the goods. */
    public function transfer(Product $product, int $fromId, int $toId, float $qty, string $date, Model $source, ?ProductVariant $variant = null): void
    {
        $product = $this->lockProduct($product);
        $variant = $this->checkVariant($product, $variant);
        $cost = (float) ($variant ?? $product)->avg_cost;
        $balance = $this->lockBalance($product->id, $fromId, $variant?->id);
        if (! Tenant::business()->feature('allow_negative_stock') && (float) $balance->quantity + 0.00001 < $qty) {
            throw ValidationException::withMessages(['items' => sprintf('Not enough stock for "%s": %s available, %s required.',
                $this->label($product, $variant), $this->fmt((float) $balance->quantity), $this->fmt($qty))]);
        }
        foreach ($this->allocate($product, $fromId, $qty, 'transfer_out', null) as [$batchId, $part]) {
            $this->move($product, $fromId, -$part, $cost, $date, 'transfer_out', $source, null, $batchId, $variant);
            $this->move($product, $toId, $part, $cost, $date, 'transfer_in', $source, null, $batchId, $variant);
        }
    }

    /** Stock per batch for one product, earliest expiry first. */
    public function batchStock(int $productId, ?int $warehouseId = null)
    {
        return BatchStock::query()
            ->join('batches', 'batches.id', '=', 'batch_stocks.batch_id')
            ->where('batches.product_id', $productId)
            ->when($warehouseId, fn ($q) => $q->where('batch_stocks.warehouse_id', $warehouseId))
            ->where('batch_stocks.quantity', '>', 0)
            ->orderByRaw('CASE WHEN batches.expiry_date IS NULL THEN 1 ELSE 0 END')->orderBy('batches.expiry_date')->orderBy('batches.id')
            ->get(['batch_stocks.*', 'batches.batch_no', 'batches.expiry_date']);
    }

    /**
     * Split an issue across batches: a named batch, otherwise FEFO. Sales and
     * deliveries skip expired batches. Returns [[batch_id|null, qty], ...].
     */
    private function allocate(Product $product, int $warehouseId, float $qty, string $type, ?string $batchNo): array
    {
        if (! $product->track_batches) {
            return [[null, $qty]];
        }

        $selling = in_array($type, ['sale', 'delivery'], true);
        $rows = BatchStock::query()->join('batches', 'batches.id', '=', 'batch_stocks.batch_id')
            ->where('batches.product_id', $product->id)->where('batch_stocks.warehouse_id', $warehouseId)
            ->where('batch_stocks.quantity', '>', 0)
            ->when($batchNo, fn ($q) => $q->where('batches.batch_no', $batchNo))
            ->when($selling, fn ($q) => $q->where(fn ($w) => $w->whereNull('batches.expiry_date')->orWhereDate('batches.expiry_date', '>=', now()->toDateString())))
            ->orderByRaw('CASE WHEN batches.expiry_date IS NULL THEN 1 ELSE 0 END')->orderBy('batches.expiry_date')->orderBy('batches.id')
            ->lockForUpdate()->get(['batch_stocks.batch_id', 'batch_stocks.quantity']);

        $slices = [];
        $left = $qty;
        foreach ($rows as $row) {
            if ($left <= 0.00001) {
                break;
            }
            $take = min($left, (float) $row->quantity);
            $slices[] = [$row->batch_id, round($take, 4)];
            $left = round($left - $take, 4);
        }

        if ($left > 0.00001) {
            if (! Tenant::business()->feature('allow_negative_stock')) {
                $what = $batchNo ? "batch {$batchNo}" : ($selling ? 'unexpired batches' : 'its batches');
                throw ValidationException::withMessages(['items' => sprintf('Not enough stock of "%s" in %s: %s short.', $product->name, $what, $this->fmt($left))]);
            }
            $slices[] = [$this->batch($product, null, null)->id, $left];
        }

        return $slices;
    }

    /** Find or create a batch; stock received without a batch number goes to "UNBATCHED". */
    private function batch(Product $product, ?string $batchNo, ?string $expiry): Batch
    {
        $batchNo = trim((string) $batchNo) ?: 'UNBATCHED';
        $batch = Batch::firstOrCreate(['product_id' => $product->id, 'batch_no' => $batchNo], ['expiry_date' => $expiry ?: null]);
        if ($expiry && ! $batch->expiry_date) {
            $batch->update(['expiry_date' => $expiry]);
        }

        return $batch;
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
            $variant = $m->variant_id ? ProductVariant::whereKey($m->variant_id)->lockForUpdate()->firstOrFail() : null;
            $holder = $variant ?? $product;
            $qty = (float) $m->quantity;
            $cost = (float) $m->unit_cost;

            if (! in_array($m->type, ['transfer_in', 'transfer_out'], true)) {
                $onHand = $this->totalOnHand($product->id, $variant?->id);
                $after = $onHand - $qty;
                if ($after > 0) {
                    $holder->avg_cost = max(0, round((($onHand * (float) $holder->avg_cost) - ($qty * $cost)) / $after, 4));
                    $holder->saveQuietly();
                }
            }

            $balance = $this->lockBalance($m->product_id, $m->warehouse_id, $m->variant_id);
            $balance->quantity = (float) $balance->quantity - $qty;
            $balance->save();
            if ($m->batch_id) {
                $this->adjustBatch($m->batch_id, $m->warehouse_id, -$qty);
            }

            $m->delete();
            $this->refreshProductCost($product, $variant);
        }
    }

    public function onHand(int $productId, ?int $warehouseId = null, ?int $variantId = null): float
    {
        $q = StockBalance::where('product_id', $productId)->when($variantId, fn ($w) => $w->where('variant_id', $variantId));
        if ($warehouseId) {
            $q->where('warehouse_id', $warehouseId);
        }

        return (float) $q->sum('quantity');
    }

    private function move(Product $product, int $warehouseId, float $qty, float $unitCost, string $date, string $type, Model $source, ?string $reason, ?int $batchId = null, ?ProductVariant $variant = null): void
    {
        $balance = $this->lockBalance($product->id, $warehouseId, $variant?->id);
        $balance->quantity = (float) $balance->quantity + $qty;
        $balance->save();
        if ($batchId) {
            $this->adjustBatch($batchId, $warehouseId, $qty);
        }

        StockMovement::create([
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'warehouse_id' => $warehouseId,
            'batch_id' => $batchId,
            'date' => $date,
            'type' => $type,
            'quantity' => $qty,
            'unit_cost' => $unitCost,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'reason' => $reason,
        ]);
    }

    private function adjustBatch(int $batchId, int $warehouseId, float $qty): void
    {
        $row = BatchStock::where('batch_id', $batchId)->where('warehouse_id', $warehouseId)->lockForUpdate()->first()
            ?? BatchStock::create(['batch_id' => $batchId, 'warehouse_id' => $warehouseId, 'quantity' => 0]);
        $row->quantity = round((float) $row->quantity + $qty, 4);
        $row->save();
    }

    private function lockProduct(Product $product): Product
    {
        return Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
    }

    private function lockBalance(int $productId, int $warehouseId, ?int $variantId = null): StockBalance
    {
        $variantId = (int) $variantId;
        $balance = StockBalance::where('product_id', $productId)->where('variant_id', $variantId)->where('warehouse_id', $warehouseId)->lockForUpdate()->first();

        return $balance ?? StockBalance::create(['product_id' => $productId, 'variant_id' => $variantId, 'warehouse_id' => $warehouseId, 'quantity' => 0]);
    }

    private function totalOnHand(int $productId, ?int $variantId = null): float
    {
        return (float) StockBalance::where('product_id', $productId)->where('variant_id', (int) $variantId)->sum('quantity');
    }

    /** A product with variations needs one of its own variants; other products take none. */
    private function checkVariant(Product $product, ?ProductVariant $variant): ?ProductVariant
    {
        if (! $product->has_variants) {
            return null;
        }
        if (! $variant || (int) $variant->product_id !== (int) $product->id) {
            throw ValidationException::withMessages(['items' => __('Choose a variation (size, colour…) for ":name".', ['name' => $product->name])]);
        }

        return ProductVariant::whereKey($variant->id)->lockForUpdate()->firstOrFail()->setRelation('product', $product);
    }

    /** For products with variations the product's own average cost is the stock-weighted average of its variants (for display). */
    private function refreshProductCost(Product $product, ?ProductVariant $variant): void
    {
        if (! $variant) {
            return;
        }
        $row = StockBalance::query()->join('product_variants', 'product_variants.id', '=', 'stock_balances.variant_id')
            ->where('stock_balances.product_id', $product->id)->where('stock_balances.quantity', '>', 0)
            ->selectRaw('SUM(stock_balances.quantity) AS qty, SUM(stock_balances.quantity * product_variants.avg_cost) AS value')->first();
        $product->avg_cost = (float) $row?->qty > 0 ? round((float) $row->value / (float) $row->qty, 4) : (float) $variant->avg_cost;
        $product->saveQuietly();
    }

    private function label(Product $product, ?ProductVariant $variant): string
    {
        return $variant ? $product->name.' — '.$variant->name : $product->name;
    }

    private function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ','), '0'), '.');
    }
}
