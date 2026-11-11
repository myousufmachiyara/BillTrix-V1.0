<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CashSession;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Register shifts: opening float, takings during the shift and the counted cash at close. */
class CashSessionService
{
    public function __construct(private SequenceService $sequences, private LedgerService $ledger) {}

    public function open(User $user, int $accountId, float $openingFloat): CashSession
    {
        return DB::transaction(function () use ($user, $accountId, $openingFloat) {
            if (CashSession::open()->where('user_id', $user->id)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['account_id' => 'You already have an open register. Close it first.']);
            }
            $account = Account::cashOrBank()->where('subtype', 'cash')->find($accountId)
                ?? throw ValidationException::withMessages(['account_id' => 'Choose a cash account for the till.']);

            return CashSession::create([
                'branch_id' => $user->branch_id,
                'user_id' => $user->id,
                'account_id' => $account->id,
                'number' => $this->sequences->next('cash_session'),
                'opened_at' => now(),
                'opening_float' => round(max($openingFloat, 0), 2),
            ]);
        });
    }

    /** Everything that happened in the shift, for the close screen and the Z-report. */
    public function summary(CashSession $session): array
    {
        $invoices = Invoice::where('cash_session_id', $session->id)->get(['id', 'type', 'total', 'discount', 'item_discount', 'tax_total', 'paid_amount', 'payment_mode']);
        $sales = $invoices->where('type', 'sale');
        $returns = $invoices->where('type', 'sale_return');

        // Money in/out per cash or bank account from payments taken on these invoices.
        $byAccount = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.source_type', 'invoice')
            ->whereIn('journal_entries.source_id', $invoices->pluck('id'))
            ->whereIn('journal_entries.type', ['receipt', 'payment'])
            ->whereIn('accounts.subtype', ['cash', 'bank'])
            ->groupBy('journal_lines.account_id', 'accounts.name')
            ->selectRaw('journal_lines.account_id, accounts.name, SUM(journal_lines.debit) as received, SUM(journal_lines.credit) as paid_out')
            ->get();

        $cashNet = (float) ($byAccount->firstWhere('account_id', $session->account_id)?->received ?? 0)
            - (float) ($byAccount->firstWhere('account_id', $session->account_id)?->paid_out ?? 0);

        return [
            'sales_count' => $sales->count(),
            'sales_total' => round($sales->sum('total'), 2),
            'returns_count' => $returns->count(),
            'returns_total' => round($returns->sum('total'), 2),
            'discounts' => round($sales->sum(fn ($i) => (float) $i->discount + (float) $i->item_discount), 2),
            'tax' => round($sales->sum('tax_total') - $returns->sum('tax_total'), 2),
            'on_credit' => round($sales->sum(fn ($i) => (float) $i->total - (float) $i->paid_amount), 2),
            'by_account' => $byAccount,
            'cash_net' => round($cashNet, 2),
            'expected_cash' => round((float) $session->opening_float + $cashNet, 2),
        ];
    }

    /** @param array<int|string,int> $denominations note value => count */
    public function close(CashSession $session, float $counted, array $denominations, ?string $notes, User $by): CashSession
    {
        return DB::transaction(function () use ($session, $counted, $denominations, $notes, $by) {
            $session = CashSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (! $session->isOpen()) {
                throw ValidationException::withMessages(['counted_cash' => 'This register is already closed.']);
            }

            $expected = $this->summary($session)['expected_cash'];
            $counted = round(max($counted, 0), 2);
            $difference = round($counted - $expected, 2);

            if (abs($difference) >= 0.01) {
                $this->ledger->post([
                    'type' => 'journal',
                    'date' => now()->toDateString(),
                    'source' => $session,
                    'branch_id' => $session->branch_id,
                    'reference' => $session->number,
                    'narration' => ($difference > 0 ? 'Cash over' : 'Cash short')." at closing of register {$session->number}",
                ], [
                    ['account' => $session->account_id, 'debit' => max($difference, 0), 'credit' => max(-$difference, 0)],
                    ['account' => 'cash_over_short', 'debit' => max(-$difference, 0), 'credit' => max($difference, 0)],
                ]);
            }

            $session->update([
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $counted,
                'difference' => $difference,
                'denominations' => array_filter(array_map('intval', $denominations)),
                'notes' => $notes,
                'closed_by' => $by->id,
            ]);

            return $session;
        });
    }
}
