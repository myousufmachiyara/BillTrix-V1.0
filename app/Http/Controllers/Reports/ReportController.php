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

class ReportController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(): View
    {
        return view('reports.index');
    }

    public function trialBalance(Request $request): View
    {
        $this->allow('reports.accounts');
        $asOf = $this->date($request, 'as_of', now());

        return view('reports.trial-balance', ['asOf' => $asOf] + $this->reports->trialBalance($asOf));
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

    public function ledger(Request $request): View
    {
        $this->allow('reports.accounts');
        [$from, $to] = $this->range($request, now()->startOfMonth());
        $account = Account::find($request->integer('account_id'));

        return view('reports.ledger', [
            'from' => $from, 'to' => $to, 'account' => $account,
            'accounts' => Account::postable()->orderBy('code')->get(['id', 'code', 'name']),
            'data' => $account ? $this->reports->ledger($account, null, $from, $to) : null,
        ]);
    }

    public function cashBook(Request $request): View
    {
        $this->allow('reports.accounts');
        [$from, $to] = $this->range($request, now()->startOfMonth());
        $accounts = Account::cashOrBank()->orderBy('code')->get(['id', 'code', 'name']);
        $account = $accounts->firstWhere('id', $request->integer('account_id')) ?? $accounts->first();

        return view('reports.ledger', [
            'from' => $from, 'to' => $to, 'account' => $account, 'accounts' => $accounts, 'cashBook' => true,
            'data' => $account ? $this->reports->ledger($account, null, $from, $to) : null,
        ]);
    }

    public function partyStatement(Request $request): View
    {
        $this->allow('reports.accounts');
        [$from, $to] = $this->range($request, now()->subMonths(3)->startOfMonth());
        $party = Party::find($request->integer('party_id'));

        return view('reports.party-statement', [
            'from' => $from, 'to' => $to, 'party' => $party,
            'data' => $party ? $this->reports->ledger(null, $party, $from, $to) : null,
            'print' => $request->boolean('print'),
        ]);
    }

    public function aging(Request $request): View
    {
        $this->allow('reports.accounts');
        $side = $request->query('side') === 'payable' ? 'payable' : 'receivable';
        $asOf = $this->date($request, 'as_of', now());

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

    public function stock(Request $request): View
    {
        $this->allow('reports.inventory');
        $rows = $this->reports->stock($request->integer('warehouse_id') ?: null, $request->integer('category_id') ?: null, ! $request->boolean('all'));

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
        $product = Product::with('unit')->find($request->integer('product_id'));
        $warehouseId = $request->integer('warehouse_id') ?: null;

        return view('reports.stock-movement', [
            'from' => $from, 'to' => $to, 'product' => $product, 'warehouseId' => $warehouseId,
            'warehouses' => Warehouse::orderBy('name')->pluck('name', 'id'),
            'data' => $product ? $this->reports->stockMovement($product, $warehouseId, $from, $to) : null,
        ]);
    }

    public function lowStock(): View
    {
        $this->allow('reports.inventory');

        return view('reports.low-stock', [
            'rows' => $this->reports->stock(null, null, false)->filter(fn ($p) => (float) $p->reorder_level > 0 && $p->qty <= (float) $p->reorder_level)->values(),
        ]);
    }

    /** Sales / purchase register with totals. */
    public function register(Request $request): View
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

        return view('reports.register', [
            'type' => $type, 'from' => $from, 'to' => $to, 'invoices' => $invoices,
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
            'party' => Party::find($request->integer('party_id')),
        ]);
    }

    public function productSales(Request $request): View
    {
        $this->allow('reports.profit');
        [$from, $to] = $this->range($request, now()->startOfMonth());

        return view('reports.product-sales', [
            'from' => $from, 'to' => $to,
            'rows' => $this->reports->productSales($from, $to, $request->integer('category_id') ?: null),
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
        ]);
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
