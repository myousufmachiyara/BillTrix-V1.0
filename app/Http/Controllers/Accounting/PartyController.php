<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Party;
use App\Models\PriceLevel;
use App\Services\SequenceService;
use App\Services\StockDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PartyController extends Controller
{
    public function index(Request $request): View
    {
        $this->allow('parties.view');
        $type = $request->query('type', 'customer') === 'supplier' ? 'supplier' : 'customer';

        $parties = Party::query()
            ->{$type === 'customer' ? 'customers' : 'suppliers'}()
            ->addSelect(['balance' => JournalLine::selectRaw('COALESCE(SUM(debit) - SUM(credit), 0)')->whereColumn('party_id', 'parties.id')])
            ->when($request->query('q'), fn (Builder $q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")->orWhere('code', $term)))
            ->when($request->boolean('due'), fn ($q) => $q->whereRaw('(SELECT COALESCE(SUM(debit) - SUM(credit), 0) FROM journal_lines WHERE journal_lines.party_id = parties.id) '.($type === 'customer' ? '>' : '<').' 0'))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('accounting.parties.index', compact('parties', 'type'));
    }

    public function create(Request $request): View
    {
        $this->allow('parties.create');
        $type = in_array($request->query('type'), ['customer', 'supplier', 'both'], true) ? $request->query('type') : 'customer';

        return view('accounting.parties.form', [
            'party' => new Party(['type' => $type, 'is_active' => true, 'price_level_id' => PriceLevel::where('is_default', true)->value('id')]),
            'opening' => 0,
            'priceLevels' => PriceLevel::orderBy('id')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, SequenceService $seq, StockDocumentService $docs): RedirectResponse|JsonResponse
    {
        $this->allow('parties.create');
        $data = $this->validated($request);

        $party = DB::transaction(function () use ($data, $seq, $docs) {
            $party = Party::create(collect($data)->except(['opening_balance', 'opening_date', 'opening_side'])->all() + [
                'code' => $data['code'] ?? $seq->next($data['type'] === 'supplier' ? 'supplier' : 'customer'),
            ]);
            $docs->partyOpening($party, $this->signedOpening($party, $data), $data['opening_date']);

            return $party;
        });

        if ($request->expectsJson()) {
            return response()->json(['id' => $party->id, 'text' => $party->name, 'credit_limit' => (float) $party->credit_limit, 'price_level_id' => $party->price_level_id]);
        }

        return $this->done('Saved.', 'parties.show', $party);
    }

    public function show(Party $party): View
    {
        $this->allow('parties.view');

        return view('accounting.parties.show', [
            'party' => $party->load('priceLevel'),
            'balance' => $party->balance(),
            'invoices' => $party->invoices()->latest('date')->latest('id')->limit(15)->get(),
            'open' => $party->invoices()->outstanding()->whereIn('type', ['sale', 'purchase'])->orderBy('date')->get(),
            'specialPrices' => \App\Support\Tenant::business()->feature('customer_prices') && $party->isCustomer()
                ? $party->prices()->with('product.unit')->get()->sortBy('product.name') : collect(),
            'pendingChallans' => \App\Models\Delivery::where('party_id', $party->id)->whereNull('invoice_id')->orderBy('date')->get(),
        ]);
    }

    public function edit(Party $party): View
    {
        $this->allow('parties.edit');
        $opening = JournalEntry::where('source_type', 'party')->where('source_id', $party->id)->first();

        return view('accounting.parties.form', [
            'party' => $party,
            'opening' => $opening ? abs((float) $opening->amount) : 0,
            'openingDate' => $opening?->date?->toDateString(),
            'openingSide' => $opening ? ((float) $opening->lines()->where('party_id', $party->id)->value('debit') > 0 ? 'dr' : 'cr') : null,
            'priceLevels' => PriceLevel::orderBy('id')->pluck('name', 'id'),
        ]);
    }

    public function update(Request $request, Party $party, StockDocumentService $docs): RedirectResponse
    {
        $this->allow('parties.edit');
        $data = $this->validated($request, $party);

        DB::transaction(function () use ($party, $data, $docs) {
            $party->update(collect($data)->except(['opening_balance', 'opening_date', 'opening_side'])->all());
            $docs->partyOpening($party, $this->signedOpening($party, $data), $data['opening_date']);
        });

        return $this->done('Saved.', 'parties.show', $party);
    }

    public function destroy(Party $party): RedirectResponse
    {
        $this->allow('parties.delete');
        if ($party->is_walk_in || $party->invoices()->exists() || $party->lines()->whereHas('entry', fn ($q) => $q->where('type', '!=', 'opening'))->exists()) {
            $party->update(['is_active' => false]);

            return $this->done('This party has transactions, so it was deactivated instead of deleted.', 'parties.index', ['type' => $party->isCustomer() ? 'customer' : 'supplier']);
        }
        $type = $party->isCustomer() ? 'customer' : 'supplier';
        DB::transaction(function () use ($party) {
            JournalEntry::where('source_type', 'party')->where('source_id', $party->id)->delete();
            $party->delete();
        });

        return $this->done('Deleted.', 'parties.index', ['type' => $type]);
    }

    /** Select2 search endpoint. */
    public function lookup(Request $request): JsonResponse
    {
        $q = Party::where('is_active', true)
            ->when($request->query('type') === 'supplier', fn ($q) => $q->suppliers(), fn ($q) => $q->when($request->query('type') === 'customer', fn ($q) => $q->customers()))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('phone', 'like', "%{$t}%")->orWhere('code', $t)))
            ->orderByDesc('is_walk_in')->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'phone', 'credit_limit', 'price_level_id', 'is_walk_in']);

        return response()->json(['results' => $q->map(fn ($p) => [
            'id' => $p->id,
            'text' => $p->name.($p->phone ? " · {$p->phone}" : ''),
            'price_level_id' => $p->price_level_id,
            'credit_limit' => (float) $p->credit_limit,
            'walk_in' => $p->is_walk_in,
        ])]);
    }

    /** Outstanding invoices for allocating a receipt or payment. */
    public function openInvoices(Request $request, Party $party): JsonResponse
    {
        $types = $request->query('for') === 'payment' ? ['purchase', 'sale_return'] : ['sale', 'purchase_return'];

        return response()->json([
            'balance' => $party->balance(),
            'invoices' => Invoice::where('party_id', $party->id)->whereIn('type', $types)->outstanding()
                ->orderBy('date')->get(['id', 'number', 'date', 'total', 'paid_amount', 'type'])
                ->map(fn ($i) => ['id' => $i->id, 'number' => $i->number, 'date' => $i->date->format('d-m-Y'), 'total' => (float) $i->total, 'balance' => $i->balance]),
        ]);
    }

    /** Opening amount as "party owes us" (+) / "we owe the party" (−). */
    private function signedOpening(Party $party, array $data): float
    {
        $amount = abs((float) ($data['opening_balance'] ?? 0));
        $side = $data['opening_side'] ?? ($party->type === 'supplier' ? 'cr' : 'dr');

        return $side === 'cr' ? -$amount : $amount;
    }

    private function validated(Request $request, ?Party $party = null): array
    {
        return array_merge($request->validate([
            'type' => ['required', Rule::in(['customer', 'supplier', 'both'])],
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:20'],
            'contact_person' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:60'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'cnic' => ['nullable', 'string', 'max:20'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'credit_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'price_level_id' => ['nullable', $this->owned('price_levels')],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'opening_side' => ['nullable', Rule::in(['dr', 'cr'])],
            'opening_date' => ['required', 'date'],
        ]), [
            'is_active' => $request->boolean('is_active', true),
            'credit_limit' => (float) $request->input('credit_limit', $party?->credit_limit ?? 0),
            'credit_days' => (int) $request->input('credit_days', $party?->credit_days ?? 0),
        ]);
    }
}
