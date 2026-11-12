<?php

namespace App\Services;

use App\Models\Party;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAdjustment;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Opening stock, adjustments / physical counts, warehouse transfers and party opening balances. */
class StockDocumentService
{
    public function __construct(
        private SequenceService $sequences,
        private LedgerService $ledger,
        private StockService $stock,
    ) {}

    /**
     * @param  array  $data  warehouse_id, date, type (opening|adjustment|count), reason, notes,
     *                       items: [product_id, quantity (signed change) | counted (for count), unit_cost?]
     */
    public function adjust(array $data): StockAdjustment
    {
        return DB::transaction(function () use ($data) {
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);
            $type = $data['type'];
            $adj = StockAdjustment::create([
                'warehouse_id' => $warehouse->id,
                'number' => $this->sequences->next('stock_adjustment'),
                'date' => $data['date'],
                'type' => $type,
                'reason' => $type === 'count' ? 'count' : ($data['reason'] ?? null),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $products = Product::whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');
            $variants = ProductVariant::whereIn('id', array_filter(array_column($data['items'], 'variant_id')))->get()->keyBy('id');
            $plus = 0.0;
            $minus = 0.0;
            $seen = [];

            foreach ($data['items'] as $i => $item) {
                $product = $products[$item['product_id']] ?? throw ValidationException::withMessages(["items.$i.product_id" => __('Invalid product.')]);
                $variant = $this->variantOf($product, $variants->get((int) ($item['variant_id'] ?? 0)), $i);
                $key = $product->id.'-'.($variant?->id ?? 0);
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages(["items.$i.product_id" => __('Each product can appear only once.')]);
                }
                $seen[$key] = true;
                if (! $product->track_stock) {
                    continue;
                }
                $system = $variant ? $this->stock->onHand($product->id, $warehouse->id, $variant->id) : $this->stock->onHand($product->id, $warehouse->id);
                $change = $type === 'count'
                    ? round((float) $item['counted'] - $system, 4)
                    : round((float) $item['quantity'], 4);

                if ($type === 'opening' && $change < 0) {
                    throw ValidationException::withMessages(["items.$i.quantity" => __('Opening stock must be positive.')]);
                }
                if (abs($change) < 0.00001) {
                    continue;
                }

                if ($change > 0) {
                    $holder = $variant ?? $product;
                    $cost = (float) ($item['unit_cost'] ?? 0) ?: (float) ($holder->avg_cost ?: ($variant?->purchasePrice() ?? $product->purchase_price));
                    $this->stock->receive($product, $warehouse->id, $change, $cost, $adj->date->toDateString(), $type === 'opening' ? 'opening' : 'adjustment', $adj, $adj->reason,
                        ['batch_no' => $item['batch_no'] ?? null, 'expiry_date' => $item['expiry_date'] ?? null], $variant);
                    $plus += $change * $cost;
                } else {
                    $cost = $this->stock->issue($product, $warehouse->id, -$change, $adj->date->toDateString(), 'adjustment', $adj, null, $adj->reason, ($item['batch_no'] ?? null) ?: null, $variant);
                    $minus += -$change * $cost;
                }

                $adj->items()->create([
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'system_quantity' => $system,
                    'quantity' => $change,
                    'unit_cost' => $cost,
                    'batch_no' => $product->track_batches ? (($item['batch_no'] ?? null) ?: null) : null,
                    'expiry_date' => $product->track_batches ? (($item['expiry_date'] ?? null) ?: null) : null,
                ]);
            }

            if ($adj->items()->doesntExist()) {
                throw ValidationException::withMessages(['items' => __('Nothing to adjust — quantities match the system.')]);
            }

            $plus = round($plus, 2);
            $minus = round($minus, 2);
            $counter = $type === 'opening' ? 'opening_equity' : 'stock_adjustment';
            $adj->update(['total_value' => round($plus - $minus, 2)]);

            if ($plus > 0 || $minus > 0) {
                $this->ledger->post([
                    'type' => 'stock',
                    'date' => $adj->date->toDateString(),
                    'source' => $adj,
                    'branch_id' => $warehouse->branch_id,
                    'reference' => $adj->number,
                    'narration' => ucfirst($type).' '.$adj->number.($adj->reason ? " ({$adj->reason})" : ''),
                ], [
                    ['account' => 'inventory', 'debit' => $plus, 'credit' => $minus],
                    ['account' => $counter, 'debit' => $minus, 'credit' => $plus],
                ]);
            }

            return $adj;
        });
    }

    public function deleteAdjustment(StockAdjustment $adj): void
    {
        DB::transaction(function () use ($adj) {
            $this->stock->reverse($adj);
            $this->ledger->deleteForSource($adj);
            $adj->delete();
        });
    }

    /** @param array $data from_warehouse_id, to_warehouse_id, date, notes, items: [product_id, unit_id, quantity] */
    public function transfer(array $data): StockTransfer
    {
        if ((int) $data['from_warehouse_id'] === (int) $data['to_warehouse_id']) {
            throw ValidationException::withMessages(['to_warehouse_id' => __('Choose a different destination warehouse.')]);
        }

        return DB::transaction(function () use ($data) {
            $from = Warehouse::findOrFail($data['from_warehouse_id']);
            $to = Warehouse::findOrFail($data['to_warehouse_id']);
            $transfer = StockTransfer::create([
                'from_warehouse_id' => $from->id,
                'to_warehouse_id' => $to->id,
                'number' => $this->sequences->next('stock_transfer'),
                'date' => $data['date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $products = Product::with('units')->whereIn('id', array_column($data['items'], 'product_id'))->get()->keyBy('id');
            $variants = ProductVariant::whereIn('id', array_filter(array_column($data['items'], 'variant_id')))->get()->keyBy('id');

            foreach ($data['items'] as $i => $item) {
                $product = $products[$item['product_id']] ?? throw ValidationException::withMessages(["items.$i.product_id" => __('Invalid product.')]);
                $variant = $this->variantOf($product, $variants->get((int) ($item['variant_id'] ?? 0)), $i);
                $unitId = (int) $item['unit_id'];
                $factor = $unitId === $product->unit_id ? 1.0
                    : (float) ($product->units->firstWhere('unit_id', $unitId)?->factor ?? throw ValidationException::withMessages(["items.$i.unit_id" => __('Invalid unit.')]));
                $qty = round((float) $item['quantity'], 4);

                $this->stock->transfer($product, $from->id, $to->id, round($qty * $factor, 4), $transfer->date->toDateString(), $transfer, $variant);
                $transfer->items()->create(['product_id' => $product->id, 'variant_id' => $variant?->id, 'unit_id' => $unitId, 'factor' => $factor, 'quantity' => $qty]);
            }

            // Inter-branch transfers don't change company-level inventory value, so no GL entry is needed.
            return $transfer;
        });
    }

    public function deleteTransfer(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            $this->stock->reverse($transfer);
            $transfer->delete();
        });
    }

    private function variantOf(Product $product, ?ProductVariant $variant, int $i): ?ProductVariant
    {
        if (! $product->has_variants) {
            return null;
        }
        if (! $variant || (int) $variant->product_id !== (int) $product->id) {
            throw ValidationException::withMessages(["items.$i.variant_id" => __('Choose a variation (size, colour…) for ":name".', ['name' => $product->name])]);
        }

        return $variant;
    }

    /** Opening receivable/payable for a party. Positive = party owes us. */
    public function partyOpening(Party $party, float $amount, string $date): void
    {
        DB::transaction(function () use ($party, $amount, $date) {
            $this->ledger->deleteForSource($party);
            $amount = round($amount, 2);
            if (abs($amount) < 0.005) {
                return;
            }
            $control = $party->type === 'supplier' ? 'payable' : 'receivable';
            $this->ledger->post([
                'type' => 'opening',
                'date' => $date,
                'source' => $party,
                'party_id' => $party->id,
                'narration' => "Opening balance — {$party->name}",
            ], [
                ['account' => $control, 'party_id' => $party->id, 'debit' => max($amount, 0), 'credit' => max(-$amount, 0)],
                ['account' => 'opening_equity', 'debit' => max(-$amount, 0), 'credit' => max($amount, 0)],
            ]);
        });
    }
}
