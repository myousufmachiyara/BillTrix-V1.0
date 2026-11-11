<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\JournalLine;
use App\Models\Party;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Support\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Read-only queries behind the financial and inventory reports. Everything is aggregated in SQL. */
class ReportService
{
    /** Debit-minus-credit per account, optionally limited to a date range. */
    public function accountTotals(?string $from, ?string $to): Collection
    {
        return JournalLine::query()
            ->when($from, fn ($q) => $q->whereDate('date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('date', '<=', $to))
            ->selectRaw('account_id, SUM(debit) as dr, SUM(credit) as cr')
            ->groupBy('account_id')
            ->get()
            ->keyBy('account_id');
    }

    public function trialBalance(string $asOf): array
    {
        $totals = $this->accountTotals(null, $asOf);
        $rows = Account::postable()->orderBy('code')->get()
            ->map(function (Account $a) use ($totals) {
                $t = $totals->get($a->id);
                $net = round((float) ($t->dr ?? 0) - (float) ($t->cr ?? 0), 2);

                return (object) ['account' => $a, 'debit' => max($net, 0), 'credit' => max(-$net, 0)];
            })
            ->filter(fn ($r) => $r->debit > 0 || $r->credit > 0)
            ->values();

        return ['rows' => $rows, 'debit' => round($rows->sum('debit'), 2), 'credit' => round($rows->sum('credit'), 2)];
    }

    /** Income and expense grouped under their parent heading. */
    public function profitLoss(string $from, string $to): array
    {
        $totals = $this->accountTotals($from, $to);
        $accounts = Account::with('parent')->whereIn('type', ['income', 'expense'])->where('is_group', false)->orderBy('code')->get();

        $section = function (string $type) use ($accounts, $totals) {
            return $accounts->where('type', $type)
                ->map(function (Account $a) use ($totals, $type) {
                    $t = $totals->get($a->id);
                    $net = (float) ($t->dr ?? 0) - (float) ($t->cr ?? 0);

                    return (object) ['account' => $a, 'amount' => round($type === 'income' ? -$net : $net, 2), 'group' => $a->parent?->name];
                })
                ->filter(fn ($r) => abs($r->amount) >= 0.005)
                ->values();
        };

        $income = $section('income');
        $expense = $section('expense');
        $cogs = $expense->filter(fn ($r) => in_array($r->account->system_key, ['cogs', 'stock_adjustment', 'freight_inward'], true));
        $opex = $expense->reject(fn ($r) => $cogs->contains($r));

        $revenue = round($income->sum('amount'), 2);
        $gross = round($revenue - $cogs->sum('amount'), 2);

        return [
            'income' => $income, 'cogs' => $cogs, 'expenses' => $opex,
            'revenue' => $revenue,
            'cogs_total' => round($cogs->sum('amount'), 2),
            'gross' => $gross,
            'expense_total' => round($opex->sum('amount'), 2),
            'net' => round($gross - $opex->sum('amount'), 2),
        ];
    }

    public function balanceSheet(string $asOf): array
    {
        $totals = $this->accountTotals(null, $asOf);
        $accounts = Account::with('parent')->whereIn('type', ['asset', 'liability', 'equity'])->where('is_group', false)->orderBy('code')->get();

        $section = fn (string $type) => $accounts->where('type', $type)
            ->map(function (Account $a) use ($totals, $type) {
                $t = $totals->get($a->id);
                $net = (float) ($t->dr ?? 0) - (float) ($t->cr ?? 0);

                return (object) ['account' => $a, 'amount' => round($type === 'asset' ? $net : -$net, 2), 'group' => $a->parent?->name];
            })
            ->filter(fn ($r) => abs($r->amount) >= 0.005)->values();

        // Profit not yet closed to retained earnings.
        $pl = Account::whereIn('type', ['income', 'expense'])->pluck('id');
        $profit = round(-$totals->toBase()->only($pl->all())->sum(fn ($t) => (float) $t->dr - (float) $t->cr), 2);

        $assets = $section('asset');
        $liabilities = $section('liability');
        $equity = $section('equity');

        return [
            'assets' => $assets, 'liabilities' => $liabilities, 'equity' => $equity, 'profit' => $profit,
            'asset_total' => round($assets->sum('amount'), 2),
            'liability_total' => round($liabilities->sum('amount'), 2),
            'equity_total' => round($equity->sum('amount') + $profit, 2),
        ];
    }

    /** Account or party ledger with opening balance and running balance. */
    public function ledger(?Account $account, ?Party $party, string $from, string $to): array
    {
        $scope = fn ($q) => $q
            ->when($account, fn ($q) => $q->where('account_id', $account->id))
            ->when($party, fn ($q) => $q->where('party_id', $party->id));

        $opening = (float) $scope(JournalLine::query())->whereDate('date', '<', $from)->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as b')->value('b');

        $lines = $scope(JournalLine::query())
            ->with(['entry:id,number,type,source_type,source_id,narration,reference', 'account:id,code,name'])
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->orderBy('date')->orderBy('journal_entry_id')->orderBy('id')
            ->get();

        $running = $opening;
        foreach ($lines as $l) {
            $running = round($running + (float) $l->debit - (float) $l->credit, 2);
            $l->running = $running;
        }

        return [
            'opening' => round($opening, 2),
            'lines' => $lines,
            'debit' => round($lines->sum('debit'), 2),
            'credit' => round($lines->sum('credit'), 2),
            'closing' => round($running, 2),
        ];
    }

    /** Outstanding invoices bucketed by age. $side: receivable | payable */
    public function aging(string $side, string $asOf): Collection
    {
        $types = $side === 'receivable' ? ['sale', 'purchase_return'] : ['purchase', 'sale_return'];
        $invoices = Invoice::with('party:id,name,phone')
            ->whereIn('type', $types)->whereDate('date', '<=', $asOf)->outstanding()
            ->get(['id', 'party_id', 'type', 'number', 'date', 'due_date', 'total', 'paid_amount']);

        $balances = JournalLine::whereNotNull('party_id')->whereDate('date', '<=', $asOf)
            ->selectRaw('party_id, SUM(debit) - SUM(credit) as bal')->groupBy('party_id')->pluck('bal', 'party_id');

        return $invoices->groupBy('party_id')->map(function ($rows) use ($asOf, $balances) {
            $b = ['current' => 0, 'd30' => 0, 'd60' => 0, 'd90' => 0, 'over' => 0];
            foreach ($rows as $inv) {
                $days = $inv->date->diffInDays(\Illuminate\Support\Carbon::parse($asOf));
                $key = match (true) {
                    $days <= 15 => 'current', $days <= 30 => 'd30', $days <= 60 => 'd60', $days <= 90 => 'd90', default => 'over'
                };
                $b[$key] += $inv->balance;
            }

            return (object) ($b + [
                'party' => $rows->first()->party,
                'total' => round(array_sum($b), 2),
                'ledger' => round((float) ($balances[$rows->first()->party_id] ?? 0), 2),
                'count' => $rows->count(),
            ]);
        })->sortByDesc('total')->values();
    }

    public function stock(?int $warehouseId, ?int $categoryId, bool $onlyInStock): Collection
    {
        $qty = StockBalance::query()
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->selectRaw('product_id, SUM(quantity) as qty')->groupBy('product_id')->pluck('qty', 'product_id');

        return Product::with(['unit:id,short_name', 'category:id,name'])
            ->where('track_stock', true)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($onlyInStock, fn ($q) => $q->whereIn('id', $qty->filter(fn ($v) => (float) $v != 0)->keys()))
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'unit_id', 'category_id', 'avg_cost', 'sale_price', 'reorder_level'])
            ->map(function ($p) use ($qty) {
                $p->qty = round((float) ($qty[$p->id] ?? 0), 4);
                $p->value = round($p->qty * (float) $p->avg_cost, 2);
                $p->retail_value = round($p->qty * (float) $p->sale_price, 2);

                return $p;
            });
    }

    public function stockMovement(Product $product, ?int $warehouseId, string $from, string $to): array
    {
        $scope = fn ($q) => $q->where('product_id', $product->id)->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId));
        $opening = (float) $scope(StockMovement::query())->whereDate('date', '<', $from)->sum('quantity');
        $rows = $scope(StockMovement::query())->with(['warehouse:id,name', 'source'])
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->orderBy('date')->orderBy('id')->get();

        $running = $opening;
        foreach ($rows as $r) {
            $running += (float) $r->quantity;
            $r->running = round($running, 4);
        }

        return ['opening' => round($opening, 4), 'rows' => $rows, 'closing' => round($running, 4)];
    }

    public function productSales(string $from, string $to, ?int $categoryId): Collection
    {
        $sign = "CASE WHEN invoices.type = 'sale' THEN 1 ELSE -1 END";

        return DB::table('invoice_items')
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('products', 'products.id', '=', 'invoice_items.product_id')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->where('invoices.business_id', Tenant::idOrFail())
            ->whereIn('invoices.type', ['sale', 'sale_return'])
            ->whereDate('invoices.date', '>=', $from)->whereDate('invoices.date', '<=', $to)
            ->when($categoryId, fn ($q) => $q->where('products.category_id', $categoryId))
            ->groupBy('products.id', 'products.name', 'units.short_name')
            ->selectRaw("products.id, products.name, units.short_name as unit,
                SUM({$sign} * invoice_items.base_quantity) as qty,
                SUM({$sign} * (invoice_items.amount - invoice_items.tax_amount)) as revenue,
                SUM({$sign} * invoice_items.base_quantity * invoice_items.unit_cost) as cost")
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($r) {
                $r->profit = round($r->revenue - $r->cost, 2);
                $r->margin = $r->revenue > 0 ? round($r->profit / $r->revenue * 100, 1) : 0;

                return $r;
            });
    }

    /** Headline numbers for the dashboard. */
    public function dashboard(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $sum = fn (string $type, string $from, string $to) => (float) Invoice::where('type', $type)->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->sum('total');
        $cashIds = Account::cashOrBank()->pluck('id');
        $bal = fn (string $key) => (float) JournalLine::where('account_id', Account::system($key)->id)->selectRaw('COALESCE(SUM(debit) - SUM(credit),0) as b')->value('b');

        $trend = Invoice::whereIn('type', ['sale', 'sale_return'])
            ->whereDate('date', '>=', now()->subDays(29)->toDateString())
            ->selectRaw("date, SUM(CASE WHEN type='sale' THEN total ELSE -total END) as total")
            ->groupBy('date')->pluck('total', 'date')
            ->mapWithKeys(fn ($v, $k) => [substr((string) $k, 0, 10) => (float) $v]);

        $days = collect(range(29, 0))->mapWithKeys(fn ($i) => [now()->subDays($i)->toDateString() => 0.0])->merge($trend);

        $monthProfit = Invoice::whereIn('type', ['sale', 'sale_return'])->whereDate('date', '>=', $monthStart)->whereDate('date', '<=', $today)
            ->selectRaw("SUM(CASE WHEN type='sale' THEN (total - tax_total - cost_total) ELSE -(total - tax_total - cost_total) END) as p")->value('p');

        return [
            'sales_today' => $sum('sale', $today, $today) - $sum('sale_return', $today, $today),
            'sales_month' => $sum('sale', $monthStart, $today) - $sum('sale_return', $monthStart, $today),
            'purchases_month' => $sum('purchase', $monthStart, $today) - $sum('purchase_return', $monthStart, $today),
            'gross_profit_month' => round((float) $monthProfit, 2),
            'cash' => (float) JournalLine::whereIn('account_id', $cashIds)->selectRaw('COALESCE(SUM(debit) - SUM(credit),0) as b')->value('b'),
            'receivable' => $bal('receivable'),
            'payable' => -$bal('payable'),
            'stock_value' => $bal('inventory'),
            'trend' => $days,
            'low_stock' => Product::with('unit:id,short_name')->where('track_stock', true)->where('is_active', true)->where('reorder_level', '>', 0)
                ->withSum('balances as qty', 'quantity')
                ->whereRaw('COALESCE((SELECT SUM(quantity) FROM stock_balances WHERE stock_balances.product_id = products.id), 0) <= products.reorder_level')
                ->orderBy('name')->limit(8)->get(['id', 'name', 'unit_id', 'reorder_level']),
            'recent' => Invoice::with('party:id,name')->whereIn('type', ['sale', 'purchase'])->latest('id')->limit(8)->get(),
            'top_debtors' => Party::customers()->addSelect(['balance' => JournalLine::selectRaw('COALESCE(SUM(debit) - SUM(credit),0)')->whereColumn('party_id', 'parties.id')])
                ->orderByDesc('balance')->limit(5)->get()->filter(fn ($p) => $p->balance > 0),
        ];
    }
}
