<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\OrderItem;
use App\Models\Party;
use App\Models\PaymentAllocation;
use App\Models\Warehouse;
use App\Services\Concerns\CalculatesLines;
use App\Support\Audit;
use App\Support\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates, updates and deletes sale / purchase / return documents and keeps
 * stock, the general ledger and payment status consistent in one transaction.
 */
class InvoiceService
{
    use CalculatesLines;

    public function __construct(
        private SequenceService $sequences,
        private LedgerService $ledger,
        private StockService $stock,
        private OrderService $orders,
        private FbrService $fbr,
    ) {}

    public function save(string $type, array $data, ?Invoice $invoice = null): Invoice
    {
        abort_unless(array_key_exists($type, Invoice::TYPES), 404);
        if ($invoice) {
            $this->guardFiscalised($invoice, 'edited');
        }

        $saved = DB::transaction(function () use ($type, $data, $invoice) {
            if ($invoice) {
                if ($invoice->returns()->exists()) {
                    throw ValidationException::withMessages(['items' => __('This invoice has returns against it and can no longer be edited.')]);
                }
                $original = $invoice->return_of_id;
                $previousOrders = $this->orderIdsOf($invoice);
                $oldLines = $this->lineSummary($invoice);
                $this->unpost($invoice);
                $invoice->items()->delete();
            }

            $party = Party::findOrFail($data['party_id']);
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);
            $this->guardReturnSource($type, $data['return_of_id'] ?? null, $party);
            $lines = $this->buildLines($data['items'], $type, $data['return_of_id'] ?? null, $invoice);
            $this->guardLinks($type, $party, $lines);
            $totals = $this->withTaxesAndFees($type, $party, $this->totals($lines, $data), $data);

            // Payments: a list of [account_id, amount] (POS split tender) or a single amount + account.
            $payments = collect($data['payments'] ?? [['account_id' => $data['payment_account_id'] ?? null, 'amount' => $data['paid_amount'] ?? 0]])
                ->map(fn ($p) => ['account_id' => (int) ($p['account_id'] ?? 0) ?: Account::system('cash')->id, 'amount' => round(max((float) ($p['amount'] ?? 0), 0), 2)])
                ->filter(fn ($p) => $p['amount'] > 0)->values();
            $paid = round(min($payments->sum('amount'), $totals['total']), 2);
            $mode = $data['payment_mode'] ?? ($paid >= $totals['total'] ? 'cash' : 'credit');

            $this->guardParty($type, $party, $mode, $paid, $totals['total'], $invoice);

            $invoice ??= new Invoice(['type' => $type, 'number' => $this->sequences->next($type), 'created_by' => auth()->id()]);
            $invoice->fill([
                'branch_id' => $warehouse->branch_id,
                'warehouse_id' => $warehouse->id,
                'date' => $data['date'],
                'due_date' => $data['due_date'] ?? ($party->credit_days ? Carbon::parse($data['date'])->addDays($party->credit_days) : null),
                'party_id' => $party->id,
                'client_uuid' => $data['client_uuid'] ?? $invoice->client_uuid,
                'return_of_id' => $data['return_of_id'] ?? null,
                'order_id' => $data['order_id'] ?? $lines->pluck('order_id')->filter()->first(),
                'cash_session_id' => array_key_exists('cash_session_id', $data) ? $data['cash_session_id'] : $invoice->cash_session_id,
                'payment_mode' => $mode,
                'reference' => $data['reference'] ?? null,
                'customer_name' => $data['customer_name'] ?? null,
                'customer_phone' => $data['customer_phone'] ?? null,
                'notes' => $data['notes'] ?? null,
                'paid_amount' => 0,
            ] + $totals);
            $invoice->save();

            $saved = $invoice->items()->createMany($lines->map(fn ($l) => collect($l)->except(['product', 'variant', 'net', 'order_id', 'delivery', 'delivery_cost'])->all())->all());
            $lines = $lines->values()->map(fn ($l, $i) => $l + ['model' => $saved[$i]]);

            $this->post($invoice, $lines, $totals);

            Delivery::whereIn('id', $lines->pluck('delivery.id')->filter()->unique())->update(['invoice_id' => $invoice->id]);

            foreach ($payments as $p) {
                $this->postPayment($invoice, $p['amount'], $p['account_id']);
            }

            $this->orders->refreshMany(array_merge($previousOrders ?? [], $this->orderIdsOf($invoice)));

            $this->refreshPaid($invoice);
            if ($invoice->return_of_id) {
                $this->refreshPaid($invoice->returnOf);
            }
            if (isset($original) && $original && $original !== $invoice->return_of_id) {
                $this->refreshPaid(Invoice::find($original));
            }

            if (isset($oldLines) && $oldLines !== ($newLines = $this->lineSummary($invoice))) {
                Audit::event('lines_changed', $invoice, ['before' => $oldLines, 'after' => $newLines]);
            }

            return $invoice->fresh(['items.product', 'items.unit', 'party']);
        });

        // Report to FBR after the sale is committed; a failure is retried later and never blocks the bill.
        $this->fbr->queue($saved);

        return $saved->fresh(['items.product', 'items.unit', 'party']);
    }

    public function delete(Invoice $invoice): void
    {
        $this->guardFiscalised($invoice, 'deleted');

        if ($invoice->returns()->exists()) {
            throw ValidationException::withMessages(['invoice' => __('Delete the returns made against this invoice first.')]);
        }

        DB::transaction(function () use ($invoice) {
            $original = $invoice->returnOf;
            $orders = $this->orderIdsOf($invoice);
            $this->unpost($invoice);
            $invoice->delete();
            if ($original) {
                $this->refreshPaid($original);
            }
            $this->orders->refreshMany($orders);
        });
    }

    /** Returned goods go back into the batch they were sold from (or the one typed on the return). */
    private function returnBatch(Invoice $invoice, array $line): ?array
    {
        if (! $line['product']->track_batches) {
            return null;
        }
        if ($line['batch_no']) {
            return ['batch_no' => $line['batch_no'], 'expiry_date' => $line['expiry_date']];
        }
        $batch = $invoice->return_of_id
            ? \App\Models\StockMovement::with('batch')->where('source_type', 'invoice')->where('source_id', $invoice->return_of_id)
                ->where('product_id', $line['product_id'])->whereNotNull('batch_id')->latest('id')->first()?->batch
            : null;

        return $batch ? ['batch_no' => $batch->batch_no, 'expiry_date' => $batch->expiry_date?->toDateString()] : null;
    }

    /** Once FBR has issued a fiscal number the bill is final; corrections go through a return. */
    private function guardFiscalised(Invoice $invoice, string $action): void
    {
        if ($invoice->fbr_status === 'synced') {
            throw ValidationException::withMessages(['invoice' => "{$invoice->number} has been reported to FBR ({$invoice->fbr_invoice_number}) and cannot be {$action}. Record a return instead."]);
        }
    }

    /** Recalculate how much of an invoice has been settled (payments + credit notes). */
    public function refreshPaid(?Invoice $invoice): void
    {
        if (! $invoice) {
            return;
        }
        $invoice->paid_amount = round((float) PaymentAllocation::where('invoice_id', $invoice->id)->sum('amount'), 2);
        $invoice->saveQuietly();
    }

    // ── internals ──────────────────────────────────────────────────────────

    private function unpost(Invoice $invoice): void
    {
        $this->stock->reverse($invoice);
        $this->ledger->deleteForSource($invoice);
        Delivery::where('invoice_id', $invoice->id)->update(['invoice_id' => null]);
    }

    /** "2 × Soap @ 120" per line, for the audit trail. */
    private function lineSummary(Invoice $invoice): array
    {
        return InvoiceItem::with('product:id,name', 'variant:id,name')->where('invoice_id', $invoice->id)->orderBy('id')->get()
            ->map(fn ($i) => qty($i->quantity).' × '.$i->product->name.($i->variant ? ' — '.$i->variant->name : '').' @ '.money($i->price).((float) $i->discount_percent ? ' −'.qty($i->discount_percent).'%' : ''))
            ->all();
    }

    /** Orders this invoice draws from (directly or through delivery challans). */
    private function orderIdsOf(Invoice $invoice): array
    {
        return OrderItem::whereIn('id', InvoiceItem::where('invoice_id', $invoice->id)->whereNotNull('order_item_id')->pluck('order_item_id'))
            ->pluck('order_id')->push($invoice->order_id)->filter()->unique()->values()->all();
    }

    private function buildLines(array $items, string $type, ?int $returnOfId, ?Invoice $editing): Collection
    {
        $items = array_values($items);
        $returnable = $returnOfId ? $this->returnableQuantities($returnOfId, $editing) : null;

        $lines = $this->calculateLines($items)->map(function (array $line, int $i) use ($items, $returnable) {
            $item = $items[$i];
            $product = $line['product'];

            if ($returnable !== null) {
                $left = $returnable[(int) ($item['source_item_id'] ?? 0)] ?? null;
                if ($left === null || $left['product_id'] !== $product->id || $left['variant_id'] !== $line['variant_id']) {
                    throw ValidationException::withMessages(["items.$i.product_id" => "\"{$this->lineName($line)}\" is not on the original invoice."]);
                }
                if ($line['base_quantity'] > $left['qty'] + 0.00001) {
                    throw ValidationException::withMessages(["items.$i.quantity" => "Only {$left['qty']} {$product->unit?->short_name} of \"{$this->lineName($line)}\" can still be returned."]);
                }
            }

            return $line + [
                'source_item_id' => $item['source_item_id'] ?? null,
                'order_item_id' => ($item['order_item_id'] ?? null) ?: null,
                'delivery_item_id' => ($item['delivery_item_id'] ?? null) ?: null,
                'batch_no' => $product->track_batches ? (trim((string) ($item['batch_no'] ?? '')) ?: null) : null,
                'expiry_date' => $product->track_batches ? (($item['expiry_date'] ?? null) ?: null) : null,
                'unit_cost' => 0,
            ];
        });

        if ($type === 'purchase') {
            foreach ($lines as $i => $l) {
                if ($l['product']->track_batches && $l['product']->track_stock && ! $l['batch_no']) {
                    throw ValidationException::withMessages(["items.$i.batch_no" => "Enter the batch number for \"{$l['product']->name}\"."]);
                }
            }
        }

        if ($type === 'sale') {
            $this->guardBelowCost($lines);
        }

        return $lines;
    }

    /**
     * Validate lines that come from an order or a delivery challan and attach
     * what posting needs (order id, challan cost). Runs after the invoice's own
     * old lines are gone, so live quantities exclude them.
     */
    private function guardLinks(string $type, Party $party, Collection &$lines): void
    {
        $orderItemIds = $lines->pluck('order_item_id')->filter()->all();
        $deliveryItemIds = $lines->pluck('delivery_item_id')->filter()->all();
        if (! $orderItemIds && ! $deliveryItemIds) {
            return;
        }

        $orderType = ['sale' => 'sale_order', 'purchase' => 'purchase_order'][$type] ?? null;
        $orderItems = OrderItem::with('order')->whereIn('id', $orderItemIds)->get()->keyBy('id');
        $deliveryItems = DeliveryItem::with('delivery')->whereIn('id', $deliveryItemIds)->get()->keyBy('id');
        $pending = $this->orders->pendingBase($orderItemIds);

        $lines = $lines->map(function (array $l, int $i) use ($type, $party, $orderType, $orderItems, $deliveryItems, &$pending) {
            $name = $this->lineName($l);

            if ($l['delivery_item_id']) {
                $di = $deliveryItems->get($l['delivery_item_id']);
                if ($type !== 'sale' || ! $di || $di->delivery->party_id !== $party->id || $di->product_id !== $l['product_id'] || $di->variant_id !== $l['variant_id'] || $di->delivery->invoice_id) {
                    throw ValidationException::withMessages(["items.$i.product_id" => "\"{$name}\" does not match an open delivery challan of this customer."]);
                }
                if (abs((float) $di->base_quantity - $l['base_quantity']) > 0.00001) {
                    throw ValidationException::withMessages(["items.$i.quantity" => "\"{$name}\" must be invoiced for the challan quantity (".qty($di->quantity).').']);
                }
                $l['order_item_id'] = $di->order_item_id;
                $l['order_id'] = $di->delivery->order_id;
                $l['delivery'] = $di->delivery;
                $l['delivery_cost'] = (float) $di->unit_cost;

                return $l;
            }

            if ($l['order_item_id']) {
                $oi = $orderItems->get($l['order_item_id']);
                if (! $oi || $oi->order->type !== $orderType || $oi->order->party_id !== $party->id || $oi->product_id !== $l['product_id'] || $oi->variant_id !== $l['variant_id'] || $oi->order->status === 'cancelled') {
                    throw ValidationException::withMessages(["items.$i.product_id" => "\"{$name}\" does not match an open order of this party."]);
                }
                $left = $pending[$oi->id] ?? 0;
                if ($l['base_quantity'] > $left + 0.00001) {
                    throw ValidationException::withMessages(["items.$i.quantity" => 'Only '.qty($left / max($l['factor'], 0.0001))." of \"{$name}\" is still pending on {$oi->order->number}."]);
                }
                $pending[$oi->id] = $left - $l['base_quantity'];
                $l['order_id'] = $oi->order_id;
            }

            return $l;
        });

        // A challan is invoiced as a whole.
        $lines->pluck('delivery')->filter()->unique('id')->each(function (Delivery $d) use ($lines) {
            $onInvoice = $lines->filter(fn ($l) => ($l['delivery']->id ?? null) === $d->id)->count();
            if ($onInvoice !== $d->items()->count()) {
                throw ValidationException::withMessages(['items' => "Challan {$d->number} must be invoiced with all its items."]);
            }
        });
    }

    /** Base quantity still returnable per original line, keyed by original invoice_item id. */
    private function returnableQuantities(int $originalId, ?Invoice $editing): array
    {
        $original = Invoice::with('items')->findOrFail($originalId);
        $returned = InvoiceItem::query()
            ->whereIn('invoice_id', $original->returns()->when($editing, fn ($q) => $q->whereKeyNot($editing->id))->pluck('id'))
            ->selectRaw('source_item_id, SUM(base_quantity) as qty')
            ->groupBy('source_item_id')
            ->pluck('qty', 'source_item_id');

        return $original->items->mapWithKeys(fn ($it) => [$it->id => [
            'product_id' => $it->product_id,
            'variant_id' => $it->variant_id,
            'qty' => round((float) $it->base_quantity - (float) ($returned[$it->id] ?? 0), 4),
            'unit_cost' => (float) $it->unit_cost,
        ]])->all();
    }

    /**
     * Withholding tax and the FBR POS fee. Both sit on top of the document total:
     * on sales they are collected from the customer, on purchases WHT is deducted
     * from what the supplier is paid. Rates come from Settings unless overridden.
     */
    private function withTaxesAndFees(string $type, Party $party, array $totals, array $data): array
    {
        $business = Tenant::business();
        $saleSide = in_array($type, ['sale', 'sale_return'], true);
        $rate = 0.0;

        if ($business->feature('wht') && ! $party->is_walk_in && ! $party->wht_exempt) {
            $side = $saleSide ? 'sales' : 'purchase';
            $rate = array_key_exists('wht_rate', $data) && $data['wht_rate'] !== null && $data['wht_rate'] !== ''
                ? (float) $data['wht_rate']
                : (float) $business->setting("wht_{$side}_".($party->is_filer ? 'filer' : 'nonfiler'), 0);
        }
        $rate = min(max($rate, 0), 100);
        $wht = round($totals['total'] * $rate / 100, 2);

        $fee = ($type === 'sale' && $business->setting('fbr_mode') === 'pos') ? round((float) $business->setting('fbr_service_fee', 1), 2) : 0.0;

        return array_merge($totals, [
            'wht_rate' => $rate,
            'wht_amount' => $wht,
            'service_fee' => $fee,
            'total' => round($totals['total'] + ($saleSide ? $wht : -$wht) + $fee, 2),
        ]);
    }

    private function guardReturnSource(string $type, ?int $returnOfId, Party $party): void
    {
        if (! $returnOfId) {
            return;
        }
        if (! str_ends_with($type, '_return')) {
            throw ValidationException::withMessages(['return_of_id' => __('Only returns can reference another invoice.')]);
        }
        $original = Invoice::findOrFail($returnOfId);
        if ($original->type !== str_replace('_return', '', $type) || $original->party_id !== $party->id) {
            throw ValidationException::withMessages(['return_of_id' => __('The original invoice does not match this return.')]);
        }
    }

    private function guardParty(string $type, Party $party, string $mode, float $paid, float $total, ?Invoice $editing): void
    {
        $business = Tenant::business();
        $onCredit = round($total - $paid, 2);

        if ($type !== 'sale' || $onCredit <= 0) {
            return;
        }

        if ($party->is_walk_in) {
            throw ValidationException::withMessages(['paid_amount' => __('Walk-in sales must be paid in full. Select a registered customer for credit.')]);
        }

        if (! $business->feature('credit_sales')) {
            throw ValidationException::withMessages(['paid_amount' => __('Credit sales are turned off in Settings.')]);
        }

        $limit = (float) $party->credit_limit;
        if ($business->feature('credit_limits') && $limit > 0 && ! auth()->user()?->can('sales.override_credit_limit')) {
            $current = $party->balance(); // editing: old invoice has already been unposted
            if ($current + $onCredit > $limit + 0.004) {
                throw ValidationException::withMessages(['party_id' => sprintf(
                    'Credit limit exceeded for %s. Limit %s, current balance %s, this invoice adds %s.',
                    $party->name, number_format($limit, 2), number_format($current, 2), number_format($onCredit, 2),
                )]);
            }
        }
    }

    private function guardBelowCost(Collection $lines): void
    {
        if (! Tenant::business()->feature('below_cost_warning') || auth()->user()?->can('sales.below_cost')) {
            return;
        }
        foreach ($lines as $i => $l) {
            $cost = (float) ($l['variant'] ?? $l['product'])->avg_cost;
            if ($cost > 0 && $l['base_quantity'] > 0 && ($l['net'] / $l['base_quantity']) + 0.0001 < $cost) {
                throw ValidationException::withMessages(["items.$i.price" => "\"{$this->lineName($l)}\" is priced below its cost (".number_format($cost * $l['factor'], 2).').']);
            }
        }
    }

    /** Stock movements + the document's journal entry. */
    private function post(Invoice $invoice, Collection $lines, array $t): void
    {
        $date = $invoice->date->toDateString();
        $wh = $invoice->warehouse_id;
        $party = $invoice->party_id;
        $linesNet = round($lines->sum('net'), 2);
        $entry = [];
        $cost = 0.0;

        switch ($invoice->type) {
            case 'sale':
                $fromChallans = 0.0;
                foreach ($lines as $l) {
                    if (isset($l['delivery'])) {
                        // Stock already left on the challan; move its cost from "delivered not invoiced" to COGS.
                        $unitCost = $l['delivery_cost'];
                        $fromChallans += $unitCost * $l['base_quantity'];
                    } else {
                        // A chosen batch is honoured; otherwise the stock service picks earliest expiry first.
                        $unitCost = $this->stock->issue($l['product'], $wh, $l['base_quantity'], $date, 'sale', $invoice, null, null, $l['batch_no'], $l['variant']);
                    }
                    $l['model']->update(['unit_cost' => $unitCost]);
                    $cost += $unitCost * $l['base_quantity'];
                }
                $cost = round($cost, 2);
                $fromChallans = round($fromChallans, 2);
                $entry = [
                    ['account' => 'receivable', 'party_id' => $party, 'debit' => $t['total']],
                    ['account' => 'wht_payable', 'credit' => $t['wht_amount']],
                    ['account' => 'pos_fee_payable', 'credit' => $t['service_fee']],
                    ['account' => 'sales', 'credit' => $linesNet],
                    ['account' => 'tax_output', 'credit' => $t['tax_total']],
                    ['account' => 'sales_discount', 'debit' => $t['discount'] - $t['round_off']],
                    ['account' => 'freight_income', 'credit' => $t['other_charges']],
                    ['account' => 'cogs', 'debit' => $cost],
                    ['account' => 'inventory', 'credit' => $cost - $fromChallans],
                    ['account' => 'gdni', 'credit' => $fromChallans],
                ];
                break;

            case 'sale_return':
                $source = $invoice->return_of_id ? $this->returnableQuantities($invoice->return_of_id, $invoice) : [];
                foreach ($lines as $l) {
                    $unitCost = $source[$l['source_item_id']]['unit_cost'] ?? (float) ($l['variant'] ?? $l['product'])->avg_cost;
                    $this->stock->receive($l['product'], $wh, $l['base_quantity'], $unitCost, $date, 'sale_return', $invoice, null, $this->returnBatch($invoice, $l), $l['variant']);
                    $l['model']->update(['unit_cost' => $unitCost]);
                    $cost += $l['product']->track_stock ? $unitCost * $l['base_quantity'] : 0;
                }
                $cost = round($cost, 2);
                $entry = [
                    ['account' => 'sales_returns', 'debit' => $linesNet],
                    ['account' => 'tax_output', 'debit' => $t['tax_total']],
                    ['account' => 'sales_discount', 'credit' => $t['discount'] - $t['round_off']],
                    ['account' => 'freight_income', 'debit' => $t['other_charges']],
                    ['account' => 'receivable', 'party_id' => $party, 'credit' => $t['total']],
                    ['account' => 'wht_payable', 'debit' => $t['wht_amount']],
                    ['account' => 'pos_fee_payable', 'debit' => $t['service_fee']],
                    ['account' => 'inventory', 'debit' => $cost],
                    ['account' => 'cogs', 'credit' => $cost],
                ];
                break;

            case 'purchase':
            case 'purchase_return':
                $isReturn = $invoice->type === 'purchase_return';
                // Spread invoice discount, freight and round-off over the lines to get the landed cost.
                $landed = round($linesNet - $t['discount'] + $t['other_charges'] + $t['round_off'], 2);
                $k = $linesNet > 0 ? $landed / $linesNet : 1;
                $stockValue = 0.0;
                $expense = 0.0;
                foreach ($lines as $l) {
                    $unitCost = $l['base_quantity'] > 0 ? round($l['net'] * $k / $l['base_quantity'], 4) : 0;
                    $l['model']->update(['unit_cost' => $unitCost]);
                    if ($l['product']->track_stock) {
                        $isReturn
                            ? $this->stock->issue($l['product'], $wh, $l['base_quantity'], $date, 'purchase_return', $invoice, $unitCost, null, $l['batch_no'], $l['variant'])
                            : $this->stock->receive($l['product'], $wh, $l['base_quantity'], $unitCost, $date, 'purchase', $invoice, null, ['batch_no' => $l['batch_no'], 'expiry_date' => $l['expiry_date']], $l['variant']);
                        $stockValue += $l['net'] * $k;
                    } else {
                        $expense += $l['net'] * $k;
                    }
                    if (! $isReturn) {
                        // Remember the last purchase price (on the variation when there is one).
                        ($l['variant'] ?? $l['product'])->forceFill(['purchase_price' => $l['price'] / max($l['factor'], 0.0001)])->saveQuietly();
                    }
                }
                $stockValue = round($stockValue, 2);
                $expense = round($landed - $stockValue, 2); // absorbs rounding
                $cost = $stockValue;
                $d = $isReturn ? 'credit' : 'debit';
                $c = $isReturn ? 'debit' : 'credit';
                $entry = [
                    ['account' => 'inventory', $d => $stockValue],
                    ['account' => 'cogs', $d => $expense],
                    ['account' => 'tax_input', $d => $t['tax_total']],
                    ['account' => 'payable', 'party_id' => $party, $c => $t['total']],
                    ['account' => 'wht_payable', $c => $t['wht_amount']],
                ];
                break;
        }

        $invoice->forceFill(['cost_total' => $cost])->saveQuietly();

        $je = $this->ledger->post([
            'type' => 'invoice',
            'date' => $date,
            'source' => $invoice,
            'party_id' => $party,
            'branch_id' => $invoice->branch_id,
            'reference' => $invoice->number,
            'narration' => "{$invoice->title} {$invoice->number}",
        ], $entry);

        // A return settles the original invoice as a credit note.
        if ($invoice->return_of_id) {
            $original = Invoice::findOrFail($invoice->return_of_id);
            $this->refreshPaid($original);
            $applied = round(min($invoice->total, max($original->fresh()->balance, 0)), 2);
            if ($applied > 0) {
                PaymentAllocation::create(['journal_entry_id' => $je->id, 'invoice_id' => $original->id, 'amount' => $applied]);
                PaymentAllocation::create(['journal_entry_id' => $je->id, 'invoice_id' => $invoice->id, 'amount' => $applied]);
            }
        }
    }

    /** Cash/bank received or paid at the time of the invoice. */
    private function postPayment(Invoice $invoice, float $amount, int $accountId): void
    {
        $account = Account::cashOrBank()->find($accountId)
            ?? throw ValidationException::withMessages(['payment_account_id' => __('Choose a cash or bank account for the payment.')]);
        $inflow = in_array($invoice->type, ['sale', 'purchase_return'], true);
        $control = $invoice->isSaleSide() ? 'receivable' : 'payable';

        $refreshed = $invoice->fresh();
        $amount = round(min($amount, max($refreshed->total - (float) PaymentAllocation::where('invoice_id', $invoice->id)->sum('amount'), 0)), 2);
        if ($amount <= 0) {
            return;
        }

        $je = $this->ledger->post([
            'type' => $inflow ? 'receipt' : 'payment',
            'date' => $invoice->date->toDateString(),
            'source' => $invoice,
            'party_id' => $invoice->party_id,
            'branch_id' => $invoice->branch_id,
            'reference' => $invoice->number,
            'narration' => ($inflow ? 'Received against ' : 'Paid against ').$invoice->number,
        ], [
            ['account' => $account, $inflow ? 'debit' : 'credit' => $amount],
            ['account' => $control, 'party_id' => $invoice->party_id, $inflow ? 'credit' : 'debit' => $amount],
        ]);

        PaymentAllocation::create(['journal_entry_id' => $je->id, 'invoice_id' => $invoice->id, 'amount' => $amount]);
    }
}
