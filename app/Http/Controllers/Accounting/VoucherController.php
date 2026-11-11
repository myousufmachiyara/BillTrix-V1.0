<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Services\VoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public function __construct(private VoucherService $vouchers) {}

    public function index(Request $request, string $voucherType): View
    {
        $this->allow('vouchers.view');

        $entries = JournalEntry::with(['party', 'creator'])
            ->where('type', $voucherType)
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($request->query('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('number', 'like', "%{$t}%")->orWhere('narration', 'like', "%{$t}%")->orWhere('reference', 'like', "%{$t}%")))
            ->latest('date')->latest('id')
            ->paginate(50)->withQueryString();

        return view('accounting.vouchers.index', ['entries' => $entries, 'type' => $voucherType]);
    }

    public function create(string $voucherType): View
    {
        $this->allow('vouchers.create');

        return view('accounting.vouchers.form', $this->formData($voucherType, new JournalEntry(['date' => now(), 'type' => $voucherType])));
    }

    public function store(Request $request, string $voucherType): RedirectResponse
    {
        $this->allow('vouchers.create');
        $entry = $this->vouchers->save($voucherType, $this->validated($request, $voucherType));

        return $request->boolean('save_new')
            ? $this->done("Voucher {$entry->number} saved.", 'vouchers.create', $voucherType)
            : $this->done("Voucher {$entry->number} saved.", 'vouchers.show', [$voucherType, $entry]);
    }

    public function show(string $voucherType, JournalEntry $entry): View
    {
        $this->allow('vouchers.view');
        abort_unless($entry->type === $voucherType, 404);
        $entry->load(['lines.account', 'lines.party', 'party', 'creator', 'branch', 'allocations.invoice', 'source']);

        return view('accounting.vouchers.show', ['entry' => $entry, 'type' => $voucherType]);
    }

    public function edit(string $voucherType, JournalEntry $entry): View|RedirectResponse
    {
        $this->allow('vouchers.edit');
        abort_unless($entry->type === $voucherType, 404);
        if (! $entry->isVoucher()) {
            return back()->with('error', 'This entry was created by a document. Edit the document instead.');
        }
        $entry->load(['lines.account', 'lines.party', 'allocations']);

        return view('accounting.vouchers.form', $this->formData($voucherType, $entry));
    }

    public function update(Request $request, string $voucherType, JournalEntry $entry): RedirectResponse
    {
        $this->allow('vouchers.edit');
        abort_unless($entry->type === $voucherType && $entry->isVoucher(), 404);
        $entry = $this->vouchers->save($voucherType, $this->validated($request, $voucherType), $entry);

        return $this->done("Voucher {$entry->number} updated.", 'vouchers.show', [$voucherType, $entry]);
    }

    public function destroy(string $voucherType, JournalEntry $entry): RedirectResponse
    {
        $this->allow('vouchers.delete');
        abort_unless($entry->type === $voucherType, 404);
        if (! $entry->isVoucher()) {
            return back()->with('error', 'This entry was created by a document. Delete the document instead.');
        }
        $this->vouchers->delete($entry);

        return $this->done("Voucher {$entry->number} deleted.", 'vouchers.index', $voucherType);
    }

    private function formData(string $type, JournalEntry $entry): array
    {
        $cashLine = $entry->exists && in_array($type, ['receipt', 'payment'], true)
            ? $entry->lines->first(fn ($l) => in_array($l->account->subtype, ['cash', 'bank'], true))
            : null;
        $counterLine = $entry->exists ? $entry->lines->first(fn ($l) => $l->id !== $cashLine?->id) : null;

        return [
            'entry' => $entry,
            'type' => $type,
            'cashAccounts' => Account::cashOrBank()->orderBy('code')->get(),
            'accounts' => Account::postable()->orderBy('code')->get(),
            'branches' => Branch::where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'cashLine' => $cashLine,
            'counterLine' => $counterLine,
        ];
    }

    private function validated(Request $request, string $type): array
    {
        $rules = [
            'date' => ['required', 'date'],
            'branch_id' => ['nullable', $this->owned('branches')],
            'reference' => ['nullable', 'string', 'max:100'],
            'narration' => ['nullable', 'string', 'max:500'],
        ];

        $rules += match ($type) {
            'journal' => [
                'lines' => ['required', 'array', 'min:2'],
                'lines.*.account_id' => ['required', $this->owned('accounts')],
                'lines.*.party_id' => ['nullable', $this->owned('parties')],
                'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
                'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
                'lines.*.memo' => ['nullable', 'string', 'max:150'],
            ],
            'contra' => [
                'from_account_id' => ['required', $this->owned('accounts')],
                'to_account_id' => ['required', $this->owned('accounts'), 'different:from_account_id'],
                'amount' => ['required', 'numeric', 'gt:0'],
            ],
            default => [
                'cash_account_id' => ['required', $this->owned('accounts')],
                'party_id' => ['required_without:counter_account_id', 'nullable', $this->owned('parties')],
                'counter_account_id' => ['required_without:party_id', 'nullable', $this->owned('accounts')],
                'amount' => ['required', 'numeric', 'gt:0'],
                'memo' => ['nullable', 'string', 'max:150'],
                'allocations' => ['array'],
                'allocations.*.invoice_id' => ['required', $this->owned('invoices')],
                'allocations.*.amount' => ['nullable', 'numeric', 'min:0'],
            ],
        };

        return $request->validate($rules, [
            'party_id.required_without' => 'Choose a customer/supplier or an account.',
            'counter_account_id.required_without' => 'Choose a customer/supplier or an account.',
        ]);
    }
}
