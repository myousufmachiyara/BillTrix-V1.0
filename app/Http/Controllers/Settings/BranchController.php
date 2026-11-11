<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Warehouse;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(): View
    {
        $this->allow('branches.view');

        return view('settings.branches', ['branches' => Branch::with('warehouses')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow('branches.create');
        $branch = Branch::create($this->validated($request));
        $branch->warehouses()->create(['name' => $branch->name.' Store']);

        return back()->with('success', 'Branch added with a default warehouse.');
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $this->allow('branches.edit');
        $branch->update($this->validated($request, $branch));

        return back()->with('success', 'Branch updated.');
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->allow('branches.delete');
        if (Branch::count() === 1) {
            return back()->with('error', 'You need at least one branch.');
        }
        try {
            $branch->warehouses()->delete();
            $branch->delete();
        } catch (\Illuminate\Database\QueryException) {
            return back()->with('error', 'This branch has transactions. Mark it inactive instead.');
        }

        return back()->with('success', 'Branch deleted.');
    }

    public function storeWarehouse(Request $request, Branch $branch): RedirectResponse
    {
        $this->allow('branches.create');
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $branch->warehouses()->create($data);

        return back()->with('success', 'Warehouse added.');
    }

    public function updateWarehouse(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->allow('branches.edit');
        $data = $request->validate(['name' => ['required', 'string', 'max:80']]);
        if ($request->boolean('is_default')) {
            Warehouse::query()->update(['is_default' => false]);
        }
        $warehouse->update($data + ['is_active' => $request->boolean('is_active'), 'is_default' => $request->boolean('is_default')]);

        return back()->with('success', 'Warehouse updated.');
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:10', Rule::unique('branches')->where('business_id', Tenant::idOrFail())->ignore($branch)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
