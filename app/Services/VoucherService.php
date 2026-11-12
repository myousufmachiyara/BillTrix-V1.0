<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Receipt, payment, journal and contra vouchers. */
class VoucherService
{
    public function __construct(private LedgerService $ledger, private InvoiceService $invoices) {}

    public function save(string $type, array $data, ?JournalEntry $existing = null): JournalEntry
    {
        abort_unless(array_key_exists($type, JournalEntry::VOUCHER_TYPES), 404);

        return DB::transaction(function () use ($type, $data, $existing) {
            $touched = [];
            if ($existing) {
                abort_unless($existing->isVoucher() && $existing->type === $type, 403);
                $touched = $existing->allocations()->pluck('invoice_id')->all();
                $existing->delete();
            }

            [$lines, $partyId, $amount] = $this->lines($type, $data);

            $entry = $this->ledger->post([
                'type' => $type,
                'date' => $data['date'],
                'number' => $existing?->number,
                'party_id' => $partyId,
                'branch_id' => $data['branch_id'] ?? $existing?->branch_id,
                'reference' => $data['reference'] ?? null,
                'narration' => $data['narration'] ?? null,
            ], $lines);

            if ($wht = round((float) ($data['wht_amount'] ?? 0), 2)) {
                $entry->forceFill(['wht_amount' => $wht, 'wht_rate' => round((float) ($data['wht_rate'] ?? 0), 3)])->saveQuietly();
            }

            if (in_array($type, ['receipt', 'payment'], true) && $partyId) {
                $touched = array_merge($touched, $this->allocate($entry, $type, $partyId, $amount, $data['allocations'] ?? []));
            }

            foreach (array_unique($touched) as $invoiceId) {
                $this->invoices->refreshPaid(Invoice::find($invoiceId));
            }

            return $entry;
        });
    }

    public function delete(JournalEntry $entry): void
    {
        abort_unless($entry->isVoucher(), 403, 'This entry belongs to a document; edit the document instead.');

        DB::transaction(function () use ($entry) {
            $ids = $entry->allocations()->pluck('invoice_id')->all();
            $entry->delete();
            foreach ($ids as $id) {
                $this->invoices->refreshPaid(Invoice::find($id));
            }
        });
    }

    private function lines(string $type, array $data): array
    {
        if ($type === 'journal') {
            $lines = collect($data['lines'] ?? [])->map(fn ($l) => [
                'account' => (int) $l['account_id'],
                'party_id' => $l['party_id'] ?? null,
                'debit' => (float) ($l['debit'] ?? 0),
                'credit' => (float) ($l['credit'] ?? 0),
                'memo' => $l['memo'] ?? null,
            ])->all();

            return [$lines, null, 0];
        }

        $amount = round((float) $data['amount'], 2);

        if ($type === 'contra') {
            $from = Account::cashOrBank()->findOrFail($data['from_account_id']);
            $to = Account::cashOrBank()->findOrFail($data['to_account_id']);
            if ($from->is($to)) {
                throw ValidationException::withMessages(['to_account_id' => __('Choose two different accounts.')]);
            }

            return [[['account' => $to, 'debit' => $amount], ['account' => $from, 'credit' => $amount]], null, $amount];
        }

        // receipt / payment: cash or bank on one side, a party or another account on the other.
        $cash = Account::cashOrBank()->findOrFail($data['cash_account_id']);
        $partyId = $data['party_id'] ?? null;

        if ($partyId) {
            $party = \App\Models\Party::findOrFail($partyId);
            // Receipts normally come from customers, payments go to suppliers; "both" uses the direction.
            $counter = match (true) {
                $type === 'receipt' && $party->isCustomer() => 'receivable',
                $type === 'payment' && $party->isSupplier() => 'payable',
                $party->isCustomer() => 'receivable',
                default => 'payable',
            };
            $counter = ['account' => $counter, 'party_id' => $party->id];
        } else {
            $acc = Account::postable()->findOrFail($data['counter_account_id']);
            if ($acc->isControlAccount() || $acc->is($cash)) {
                throw ValidationException::withMessages(['counter_account_id' => __('Pick a customer/supplier, or a different account.')]);
            }
            $counter = ['account' => $acc];
        }

        // Withholding tax: a customer may keep back income tax when paying us (we receive less,
        // the rest is an advance tax credit); we deduct it when paying a supplier (we owe it to FBR).
        $wht = round(max((float) ($data['wht_amount'] ?? 0), 0), 2);
        $gross = round($amount + $wht, 2);

        $memo = $data['memo'] ?? null;
        $lines = $type === 'receipt'
            ? [['account' => $cash, 'debit' => $amount, 'memo' => $memo], ['account' => 'wht_receivable', 'debit' => $wht, 'memo' => 'Tax withheld by payer'], $counter + ['credit' => $gross, 'memo' => $memo]]
            : [$counter + ['debit' => $gross, 'memo' => $memo], ['account' => $cash, 'credit' => $amount, 'memo' => $memo], ['account' => 'wht_payable', 'credit' => $wht, 'memo' => 'Withholding tax deducted']];

        return [$lines, $partyId, $gross];
    }

    /** Apply a receipt/payment to specific open invoices. Returns the invoice ids touched. */
    private function allocate(JournalEntry $entry, string $type, int $partyId, float $amount, array $allocations): array
    {
        $types = $type === 'receipt' ? ['sale', 'purchase_return'] : ['purchase', 'sale_return'];
        $remaining = $amount;
        $ids = [];

        foreach ($allocations as $i => $a) {
            $value = round((float) ($a['amount'] ?? 0), 2);
            if ($value <= 0) {
                continue;
            }
            $invoice = Invoice::where('party_id', $partyId)->whereIn('type', $types)->findOrFail($a['invoice_id']);
            $this->invoices->refreshPaid($invoice);

            if ($value > $invoice->balance + 0.004) {
                throw ValidationException::withMessages(["allocations.$i.amount" => "{$invoice->number} only has ".number_format($invoice->balance, 2).' outstanding.']);
            }
            if ($value > $remaining + 0.004) {
                throw ValidationException::withMessages(['allocations' => __('Allocated amounts exceed the voucher amount.')]);
            }

            PaymentAllocation::create(['journal_entry_id' => $entry->id, 'invoice_id' => $invoice->id, 'amount' => $value]);
            $remaining -= $value;
            $ids[] = $invoice->id;
        }

        return $ids;
    }
}
