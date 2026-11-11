<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Support\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * The only way anything is written to the general ledger.
 * Every entry is validated as balanced before it is saved.
 */
class LedgerService
{
    public function __construct(private SequenceService $sequences) {}

    /**
     * @param  array  $lines  each: ['account' => Account|int|string(system key), 'debit' => float, 'credit' => float, 'party_id' => ?int, 'memo' => ?string]
     * @param  array  $meta  type, date, number?, source?, party_id?, branch_id?, reference?, narration?
     */
    public function post(array $meta, array $lines): JournalEntry
    {
        $normalized = $this->normalize($lines);

        $debits = round(array_sum(array_column($normalized, 'debit')), 2);
        $credits = round(array_sum(array_column($normalized, 'credit')), 2);

        if ($debits <= 0 || abs($debits - $credits) > 0.004) {
            throw ValidationException::withMessages([
                'lines' => "Entry is not balanced (debit {$debits}, credit {$credits}).",
            ]);
        }

        /** @var Model|null $source */
        $source = $meta['source'] ?? null;
        $type = $meta['type'];

        $entry = JournalEntry::create([
            'branch_id' => $meta['branch_id'] ?? auth()->user()?->branch_id,
            'number' => $meta['number'] ?? $this->sequences->next($this->sequenceFor($type)),
            'type' => $type,
            'date' => $meta['date'],
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'party_id' => $meta['party_id'] ?? null,
            'reference' => $meta['reference'] ?? null,
            'narration' => $meta['narration'] ?? null,
            'amount' => $debits,
            'created_by' => auth()->id(),
        ]);

        $rows = array_map(fn ($l) => $l + [
            'journal_entry_id' => $entry->id,
            'business_id' => Tenant::idOrFail(),
            'branch_id' => $entry->branch_id,
            'date' => $entry->date->toDateString(),
        ], $normalized);

        JournalLine::insert($rows);

        return $entry;
    }

    /** Remove every ledger entry produced by a source document (used before re-posting or deleting it). */
    public function deleteForSource(Model $source): void
    {
        JournalEntry::where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->get()
            ->each(fn (JournalEntry $e) => $e->delete()); // lines & allocations cascade
    }

    private function normalize(array $lines): array
    {
        $out = [];

        foreach ($lines as $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);

            // Net the two sides; a negative debit simply becomes a credit and vice versa.
            $net = round($debit - $credit, 2);
            if (abs($net) < 0.005) {
                continue;
            }

            $account = $this->resolve($line['account']);

            if ($account->is_group) {
                throw ValidationException::withMessages(['lines' => "\"{$account->name}\" is a group account and cannot be posted to."]);
            }

            $partyId = $line['party_id'] ?? null;
            if ($account->isControlAccount() && ! $partyId) {
                throw ValidationException::withMessages(['lines' => "Select a customer/supplier for \"{$account->name}\"."]);
            }

            $key = $account->id.'|'.($partyId ?? '').'|'.($line['memo'] ?? '');
            $out[$key] ??= ['account_id' => $account->id, 'party_id' => $partyId, 'memo' => $line['memo'] ?? null, 'debit' => 0.0, 'credit' => 0.0];
            $out[$key]['debit'] += max($net, 0);
            $out[$key]['credit'] += max(-$net, 0);
        }

        // Re-net merged lines.
        foreach ($out as &$l) {
            $n = round($l['debit'] - $l['credit'], 2);
            $l['debit'] = max($n, 0);
            $l['credit'] = max(-$n, 0);
        }

        return array_values(array_filter($out, fn ($l) => $l['debit'] > 0 || $l['credit'] > 0));
    }

    private function resolve(Account|int|string $account): Account
    {
        return match (true) {
            $account instanceof Account => $account,
            is_int($account) || ctype_digit((string) $account) => Account::findOrFail((int) $account),
            default => Account::system($account),
        };
    }

    private function sequenceFor(string $type): string
    {
        return match ($type) {
            'invoice' => 'invoice_entry',
            'stock' => 'stock_entry',
            default => $type,
        };
    }
}
