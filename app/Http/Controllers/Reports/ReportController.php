<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Party;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(): View
    {
        return view('reports.index');
    }

    public function trialBalance(Request $request): View|StreamedResponse
    {
        $this->allow('reports.accounts');
        $asOf = $this->date($request, 'as_of', now());

        $tb = $this->reports->trialBalance($asOf);
        if ($x = $this->exportIfAsked($request, 'trial-balance', ['Code', 'Account', 'Type', 'Debit', 'Credit'],
            fn () => $tb['rows']->map(fn ($r) => [$r->account->code, $r->account->name, $r->account->type, $r->debit, $r->credit]), 'Trial balance as of '.$asOf)) {
            return $x;
        }

        return view('reports.trial-balance', ['asOf' => $asOf] + $tb);
    }

    public function profitLoss(Request $request): View
    {
        $this->allow('reports.profit');
        [$from, $to] = $this->range($request, now()->startOfMonth());

        return view('reports.profit-loss', compact('from', 'to') + $this->reports->profitLoss($from, $to));
    }

    public function balanceSheet(Request $request): View
    {
        $this->allow('reports.accounts');
        $asOf = $this->date($request, 'as_of', now());

        return view('reports.balance-sheet', ['asOf' => $asOf] + $this->reports->balanceSheet($asOf));
    }

    public function ledger(Request $request): View|StreamedResponse
    {
        $this->allow('reports.accounts');
        [$from, $to] = $this->range($request, now()->startOfMonth());
        $account = Account::find($request->integer('account_id'));

        if ($account && ($x = $this->exportLedger($request, $account->code.' '.$account->name, $this->reports->ledger($account, null, $from, $to)))) {
            return $x;
        }

        return view('reports.ledger', [
            'from' => $from, 'to' => $to, 'account' => $account,
            'accounts' => Account::postable()->orderBy('code')->get(['id', 'code', 'name']),
            'data' => $account ? $this->reports->ledger($account, null, $from, $to) : null,
        ]);
    }

    public function cashBook(Request $request): View|StreamedResponse
    {
        $this->allow('reports.accounts');
        [$from, $to] = $this->range($request, now()->startOfMonth());
        $accounts = Account::cashOrBank()->orderBy('code')->get(['id', 'code', 'name']);
        $account = $accounts->firstWhere('id', $request->integer('account_id')) ?? $accounts->first();

        if ($account && ($x = $this->exportLedger($request, $account->name, $this->reports->ledger($account, null, $from, $to)))) {
            return $x;
        }

        return view('reports.ledger', [
            'from' => $from, 'to' => $to, 'account' => $account, 'accounts' => $accounts, 'cashBook' => true,
            'data' => $account ? $this->reports->ledger($account, null, $from, $to) : null,
        ]);
    }

    public function partyStatement(Request $request): View|StreamedResponse
    {
        $this->allow('reports.accounts');
        [$from, $to] = $this->range($request, now()->subMonths(3)->startOfMonth());
        $party = Party::find($request->integer('party_id'));

        if ($party && ($x = $this->exportLedger($request, 'Statement '.$party->name, $this->reports->ledger(null, $party, $from, $to)))) {
            return $x;
        }

        return view('reports.party-statement', [
            'from' => $from, 'to' => $to, 'party' => $party,
            'data' => $party ? $this->reports->ledger(null, $party, $from, $to) : null,
            'print' => $request->boolean('print'),
        ]);
    }

    public function aging(Request $request): View|StreamedResponse
    {
        $this->allow('reports.accounts');
        $side = $request->query('side') === 'payable' ? 'payable' : 'receivable';
        $asOf = $this->date($request, 'as_of', now());

        $rows = $this->reports->aging($side, $asOf);
        if ($x = $this->exportIfAsked($request, $side.'-aging', ['Party', 'Phone', 'Bills', '0-15', '16-30', '31-60', '61-90', '90+', 'Open bills', 'Ledger balance'],
            fn () => $rows->map(fn ($r) => [$r->party->name, $r->party->phone, $r->count, $r->current, $r->d30, $r->d60, $r->d90, $r->over, $r->total, $r->ledger]), ucfirst($side).' aging as of '.$asOf)) {
            return $x;
        }

        return view('reports.aging', ['side' => $side, 'asOf' => $asOf, 'rows' => $this->reports->aging($side, $asOf)]);
    }

    public function dayBook(Request $request): View
    {
        $this->allow('reports.accounts');
        [$from, $to] = $this->range($request, now());

        $entries = JournalEntry::with(['lines.account:id,code,name', 'lines.party:id,name', 'party:id,name'])
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->orderBy('date')->orderBy('id')
            ->paginate(100)->withQueryString();

        return view('reports.day-book', compact('entries', 'from', 'to'));
    }

    public function stock(Request $request): View|StreamedResponse
    {
        $this->allow('reports.inventory');
        $rows = $this->reports->stock($request->integer('warehouse_id') ?: null, $request->integer('category_id') ?: null, ! $request->boolean('all'));

        if ($x = $this->exportIfAsked($request, 'stock', ['Product', 'SKU', 'Category', 'Qty', 'Unit', 'Avg cost', 'Cost value', 'Retail value'],
            fn () => $rows->map(fn ($p) => [$p->name, $p->sku, $p->category?->name, $p->qty, $p->unit->short_name, (float) $p->avg_cost, $p->value, $p->retail_value]), 'Stock & valuation '.now()->format('d M Y'))) {
            return $x;
        }

        return view('reports.stock', [
            'rows' => $rows,
            'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function stockMovement(Request $request): View
    {
        $this->allow('reports.inventory');
        [$from, $to] = $this->range($request, now()->startOfMonth());
        // The product picker sends "product-variant" for a variation.
        [$productId, $pickedVariant] = array_pad(explode('-', (string) $request->query('product_id')), 2, null);
        $product = Product::with(['unit', 'variants'])->find((int) $productId);
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $variantId = $product?->has_variants ? ($product->variants->firstWhere('id', (int) ($request->query('variant_id') ?: $pickedVariant))?->id) : null;

        return view('reports.stock-movement', [
            'from' => $from, 'to' => $to, 'product' => $product, 'warehouseId' => $warehouseId, 'variantId' => $variantId,
            'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
            'data' => $product ? $this->reports->stockMovement($product, $warehouseId, $from, $to, $variantId) : null,
        ]);
    }

    public function lowStock(): View|StreamedResponse
    {
        $this->allow('reports.inventory');

        $rows = $this->reports->stock(null, null, false)->filter(fn ($p) => (float) $p->reorder_level > 0 && $p->qty <= (float) $p->reorder_level)->values();
        if ($x = $this->exportIfAsked(request(), 'low-stock', ['Product', 'SKU', 'In stock', 'Unit', 'Reorder level', 'Short by'],
            fn () => $rows->map(fn ($p) => [$p->name, $p->sku, $p->qty, $p->unit->short_name, (float) $p->reorder_level, (float) $p->reorder_level - $p->qty]))) {
            return $x;
        }

        return view('reports.low-stock', ['rows' => $rows]);
    }

    /** Sales / purchase register with totals. */
    public function register(Request $request): View|StreamedResponse
    {
        $type = array_key_exists($request->query('type'), Invoice::TYPES) ? $request->query('type') : 'sale';
        $this->allow(in_array($type, ['sale', 'sale_return'], true) ? 'reports.sales' : 'reports.purchases');
        [$from, $to] = $this->range($request, now()->startOfMonth());

        $invoices = Invoice::with('party:id,name')
            ->where('type', $type)->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->when($request->query('party_id'), fn ($q, $p) => $q->where('party_id', $p))
            ->when($request->query('branch_id'), fn ($q, $b) => $q->where('branch_id', $b))
            ->when($request->query('mode'), fn ($q, $m) => $q->where('payment_mode', $m))
            ->orderBy('date')->orderBy('id')->get();

        if ($x = $this->exportIfAsked($request, $type.'-register', ['Date', 'Number', 'Party', 'Mode', 'Gross', 'Discount', 'Tax', 'WHT', 'Total', 'Settled', 'Balance'],
            fn () => $invoices->map(fn ($i) => [$i->date, $i->number, $i->party->name, $i->payment_mode, (float) $i->subtotal, (float) $i->item_discount + (float) $i->discount,
                (float) $i->tax_total, (float) $i->wht_amount, (float) $i->total, (float) $i->paid_amount, $i->balance]), Invoice::TYPES[$type].' register '.$from.' to '.$to)) {
            return $x;
        }

        return view('reports.register', [
            'type' => $type, 'from' => $from, 'to' => $to, 'invoices' => $invoices,
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'party' => Party::find($request->integer('party_id')),
        ]);
    }

    public function productSales(Request $request): View|StreamedResponse
    {
        $this->allow('reports.profit');
        [$from, $to] = $this->range($request, now()->startOfMonth());

        $rows = $this->reports->productSales($from, $to, $request->integer('category_id') ?: null);
        if ($x = $this->exportIfAsked($request, 'product-sales', ['Product', 'Unit', 'Qty sold', 'Revenue', 'Cost', 'Profit', 'Margin %'],
            fn () => $rows->map(fn ($r) => [$r->name, $r->unit, (float) $r->qty, round($r->revenue, 2), round($r->cost, 2), $r->profit, $r->margin]), "Product sales {$from} to {$to}")) {
            return $x;
        }

        return view('reports.product-sales', [
            'from' => $from, 'to' => $to,
            'rows' => $rows,
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
        ]);
    }

    /** Batches that have expired or will expire soon, with the stock still holding them. */
    public function expiry(Request $request): View|StreamedResponse
    {
        $this->allow('reports.inventory');
        $days = $request->integer('days') ?: (int) \App\Support\Tenant::business()->setting('expiry_alert_days', 60);
        $until = now()->addDays($days)->toDateString();

        $rows = \App\Models\BatchStock::query()
            ->join('batches', 'batches.id', '=', 'batch_stocks.batch_id')
            ->join('products', 'products.id', '=', 'batches.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'batch_stocks.warehouse_id')
            ->where('batches.business_id', \App\Support\Tenant::idOrFail())
            ->where('batch_stocks.quantity', '>', 0)
            ->whereNotNull('batches.expiry_date')
            ->whereDate('batches.expiry_date', '<=', $until)
            ->when($request->query('warehouse_id'), fn ($q, $w) => $q->where('batch_stocks.warehouse_id', $w))
            ->orderBy('batches.expiry_date')
            ->get(['batch_stocks.quantity', 'batch_stocks.warehouse_id', 'batches.batch_no', 'batches.expiry_date', 'products.id as product_id',
                'products.name', 'products.avg_cost', 'warehouses.name as warehouse']);

        if ($x = $this->exportIfAsked($request, 'expiry', ['Product', 'Batch', 'Expiry', 'Warehouse', 'Quantity', 'Value'],
            fn () => $rows->map(fn ($r) => [$r->name, $r->batch_no, substr((string) $r->expiry_date, 0, 10), $r->warehouse, (float) $r->quantity, round($r->quantity * $r->avg_cost, 2)]))) {
            return $x;
        }

        return view('reports.expiry', ['rows' => $rows, 'days' => $days, 'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id')]);
    }

    /** Sales tax and withholding tax for the period, with party NTN/CNIC for returns and certificates. */
    public function withholding(Request $request): View|StreamedResponse
    {
        $this->allow('reports.tax');
        [$from, $to] = $this->range($request, now()->startOfMonth());

        $rows = Invoice::with('party:id,name,ntn,cnic,is_filer')
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->where(fn ($q) => $q->where('wht_amount', '>', 0)->orWhere('tax_total', '>', 0))
            ->orderBy('date')->orderBy('id')->get();

        // Tax withheld on receipts and payments (vouchers).
        $vouchers = JournalEntry::with('party:id,name,ntn,cnic,is_filer')->whereIn('type', ['receipt', 'payment'])->where('wht_amount', '>', 0)
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)->orderBy('date')->orderBy('id')->get();

        $sign = fn ($i) => str_ends_with($i->type, '_return') ? -1 : 1;
        $side = fn ($i) => in_array($i->type, ['sale', 'sale_return'], true) ? 'sales' : 'purchases';

        if ($x = $this->exportIfAsked($request, 'withholding', ['Date', 'Invoice', 'Type', 'Party', 'NTN', 'CNIC', 'Filer', 'WHT rate %', 'WHT', 'Sales tax'],
            fn () => $rows->map(fn ($i) => [$i->date, $i->number, $i->type, $i->party->name, $i->party->ntn, $i->party->cnic, $i->party->is_filer ? 'Yes' : 'No',
                (float) $i->wht_rate, $sign($i) * (float) $i->wht_amount, $sign($i) * (float) $i->tax_total])
                ->concat($vouchers->map(fn ($v) => [$v->date, $v->number, $v->type === 'payment' ? 'payment (WHT deducted)' : 'receipt (WHT suffered)', $v->party?->name, $v->party?->ntn, $v->party?->cnic,
                    $v->party ? ($v->party->is_filer ? 'Yes' : 'No') : '', (float) $v->wht_rate, (float) $v->wht_amount, 0])), "Tax {$from} to {$to}")) {
            return $x;
        }

        return view('reports.withholding', [
            'from' => $from, 'to' => $to, 'vouchers' => $vouchers,
            'wht' => $rows->where('wht_amount', '>', 0)->groupBy($side)->map(fn ($g) => $g->groupBy('party_id')->map(fn ($p) => (object) [
                'party' => $p->first()->party,
                'count' => $p->count(),
                'base' => round($p->sum(fn ($i) => $sign($i) * ((float) $i->total + ($side($i) === 'sales' ? -1 : 1) * (float) $i->wht_amount - (float) $i->service_fee)), 2),
                'wht' => round($p->sum(fn ($i) => $sign($i) * (float) $i->wht_amount), 2),
            ])->values()),
            'salesTax' => [
                'output' => round($rows->filter(fn ($i) => $side($i) === 'sales')->sum(fn ($i) => $sign($i) * (float) $i->tax_total), 2),
                'input' => round($rows->filter(fn ($i) => $side($i) === 'purchases')->sum(fn ($i) => $sign($i) * (float) $i->tax_total), 2),
            ],
        ]);
    }

    private function exportLedger(Request $request, string $title, array $data): ?StreamedResponse
    {
        return $this->exportIfAsked($request, 'ledger', ['Date', 'Voucher', 'Description', 'Debit', 'Credit', 'Balance'], fn () => collect([['', '', 'Opening balance', null, null, $data['opening']]])
            ->concat($data['lines']->map(fn ($l) => [$l->date, $l->entry->number, $l->memo ?: $l->entry->narration, (float) $l->debit, (float) $l->credit, $l->running]))
            ->push(['', '', 'Closing balance', $data['debit'], $data['credit'], $data['closing']]), $title);
    }

    private function range(Request $request, $defaultFrom): array
    {
        $from = $this->date($request, 'from', $defaultFrom);
        $to = $this->date($request, 'to', now());

        return $from > $to ? [$to, $from] : [$from, $to];
    }

    private function date(Request $request, string $key, $default): string
    {
        $value = $request->query($key);

        return $value && strtotime($value) ? date('Y-m-d', strtotime($value)) : $default->toDateString();
    }
}
