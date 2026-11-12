<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\PartyResource;
use App\Models\JournalLine;
use App\Models\Party;
use App\Services\ReportService;
use App\Services\SequenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class PartyController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->allow('parties.view');

        return PartyResource::collection(Party::query()
            ->when($request->query('type') === 'supplier', fn ($q) => $q->suppliers(), fn ($q) => $q->when($request->query('type') === 'customer', fn ($q) => $q->customers()))
            ->where('is_active', true)
            ->addSelect(['balance' => JournalLine::selectRaw('COALESCE(SUM(debit) - SUM(credit), 0)')->whereColumn('party_id', 'parties.id')])
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('phone', 'like', "%{$t}%")->orWhere('code', $t)))
            ->orderBy('name')
            ->paginate(min($request->integer('per_page', 50), 200)));
    }

    public function show(Party $party): JsonResponse
    {
        $this->allow('parties.view');
        $party->setAttribute('balance', $party->balance());

        return response()->json([
            'data' => (new PartyResource($party))->resolve(),
            'open_invoices' => DocumentResource::collection($party->invoices()->with('party')->outstanding()->whereIn('type', ['sale', 'purchase'])->orderBy('date')->get())->resolve(),
        ]);
    }

    public function store(Request $request, SequenceService $seq): JsonResponse
    {
        $this->allow('parties.create');
        $data = $request->validate([
            'type' => ['required', Rule::in(['customer', 'supplier', 'both'])],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:60'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'cnic' => ['nullable', 'string', 'max:20'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ]);
        $party = Party::create($data + ['code' => $seq->next($data['type'] === 'supplier' ? 'supplier' : 'customer'), 'credit_limit' => $data['credit_limit'] ?? 0]);

        return response()->json(['data' => (new PartyResource($party))->resolve()], 201);
    }

    public function statement(Request $request, Party $party, ReportService $reports): JsonResponse
    {
        $this->allow('parties.view');
        $from = $request->date('from')?->toDateString() ?? now()->subMonths(3)->startOfMonth()->toDateString();
        $to = $request->date('to')?->toDateString() ?? now()->toDateString();
        $data = $reports->ledger(null, $party, $from, $to);

        return response()->json([
            'from' => $from, 'to' => $to, 'opening' => $data['opening'], 'closing' => $data['closing'],
            'lines' => $data['lines']->map(fn ($l) => [
                'date' => $l->date->toDateString(), 'voucher' => $l->entry->number, 'description' => $l->memo ?: $l->entry->narration,
                'debit' => (float) $l->debit, 'credit' => (float) $l->credit, 'balance' => $l->running,
            ]),
        ]);
    }
}
