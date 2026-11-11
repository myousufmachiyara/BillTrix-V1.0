<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalLine;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(): View
    {
        $this->allow('accounts.view');

        $balances = JournalLine::where('business_id', Tenant::idOrFail())
            ->selectRaw('account_id, SUM(debit) - SUM(credit) as bal')
            ->groupBy('account_id')
            ->pluck('bal', 'account_id');

        $accounts = Account::orderBy('code')->get();

        return view('accounting.accounts.index', [
            'tree' => $accounts->groupBy(fn ($a) => $a->parent_id ?? 0),
            'balances' => $balances,
        ]);
    }

    public function create(Request $request): View
    {
        $this->allow('accounts.create');
        $parent = Account::find($request->integer('parent'));

        return view('accounting.accounts.form', [
            'account' => new Account(['parent_id' => $parent?->id, 'type' => $parent?->type ?? 'expense', 'is_active' => true]),
            'groups' => $this->groups(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow('accounts.create');
        Account::create($this->validated($request));

        return $this->done('Account created.', 'accounts.index');
    }

    public function edit(Account $account): View
    {
        $this->allow('accounts.edit');

        return view('accounting.accounts.form', ['account' => $account, 'groups' => $this->groups($account)]);
    }

    public function update(Request $request, Account $account): RedirectResponse
    {
        $this->allow('accounts.edit');
        $data = $this->validated($request, $account);

        if ($account->system_key) {
            // System accounts keep their type, nature and place in posting rules.
            $data = array_intersect_key($data, array_flip(['name', 'code', 'description']));
        }
        if ($account->is_group !== ($data['is_group'] ?? $account->is_group) && ($account->lines()->exists() || $account->children()->exists())) {
            return back()->with('error', 'An account with postings or children cannot change between group and ledger.');
        }
        $account->update($data);

        return $this->done('Account updated.', 'accounts.index');
    }

    public function destroy(Account $account): RedirectResponse
    {
        $this->allow('accounts.delete');
        if ($account->system_key || $account->lines()->exists() || $account->children()->exists()) {
            return back()->with('error', 'System accounts, accounts with postings and groups with sub-accounts cannot be deleted. Deactivate it instead.');
        }
        $account->delete();

        return back()->with('success', 'Account deleted.');
    }

    private function groups(?Account $except = null)
    {
        return Account::where('is_group', true)->when($except, fn ($q) => $q->whereKeyNot($except->id))->orderBy('code')->get();
    }

    private function validated(Request $request, ?Account $account = null): array
    {
        $data = $request->validate([
            'parent_id' => ['required', $this->owned('accounts')->where('is_group', true)],
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts')->where('business_id', Tenant::idOrFail())->ignore($account)],
            'name' => ['required', 'string', 'max:120'],
            'subtype' => ['nullable', Rule::in(['cash', 'bank'])],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $parent = Account::findOrFail($data['parent_id']);

        return $data + [
            'type' => $parent->type, // an account always shares its group's nature
            'is_group' => $request->boolean('is_group'),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
