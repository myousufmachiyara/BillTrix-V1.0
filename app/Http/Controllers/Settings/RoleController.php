<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->allow('roles.view');

        return view('settings.roles.index', ['roles' => Role::where('business_id', Tenant::idOrFail())->withCount(['permissions', 'users'])->orderBy('name')->get()]);
    }

    public function create(): View
    {
        $this->allow('roles.create');

        return view('settings.roles.form', ['role' => new Role, 'granted' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow('roles.create');
        $data = $this->validated($request);
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web', 'business_id' => Tenant::idOrFail()]);
        $role->syncPermissions(Permission::whereIn('name', $data['permissions'] ?? [])->get());

        return $this->done(__('Role created.'), 'settings.roles.index');
    }

    public function edit(Role $role): View
    {
        $this->allow('roles.edit');
        $this->ensureOwned($role);

        return view('settings.roles.form', ['role' => $role, 'granted' => $role->permissions->pluck('name')->all()]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->allow('roles.edit');
        $this->ensureOwned($role);
        if ($role->name === 'Owner') {
            return back()->with('error', __('The Owner role always has every permission.'));
        }
        $data = $this->validated($request, $role);
        $role->update(['name' => $data['name']]);
        $role->syncPermissions(Permission::whereIn('name', $data['permissions'] ?? [])->get());

        return $this->done(__('Role updated.'), 'settings.roles.index');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->allow('roles.delete');
        $this->ensureOwned($role);
        if ($role->name === 'Owner' || $role->users()->exists()) {
            return back()->with('error', __('This role is in use and cannot be deleted.'));
        }
        $role->delete();

        return back()->with('success', __('Role deleted.'));
    }

    private function ensureOwned(Role $role): void
    {
        abort_unless((int) $role->business_id === Tenant::idOrFail(), 404);
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50', Rule::unique('roles')->where('business_id', Tenant::idOrFail())->ignore($role)],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);
    }
}
