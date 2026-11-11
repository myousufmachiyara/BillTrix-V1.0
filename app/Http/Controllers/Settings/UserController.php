<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(): View
    {
        $this->allow('users.view');

        return view('settings.users.index', ['users' => User::with(['roles', 'branch'])->orderBy('name')->get()]);
    }

    public function create(): View
    {
        $this->allow('users.create');

        return view('settings.users.form', $this->formData(new User(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow('users.create');
        $data = $this->validated($request);
        $user = User::create(collect($data)->except('role')->all());
        $user->syncRoles([$data['role']]);

        return $this->done('User created.', 'settings.users.index');
    }

    public function edit(User $user): View
    {
        $this->allow('users.edit');

        return view('settings.users.form', $this->formData($user));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->allow('users.edit');
        $data = $this->validated($request, $user);

        if ($user->is(auth()->user()) && (! $data['is_active'] || $data['role'] !== 'Owner') && $user->isOwner()) {
            return back()->with('error', 'You cannot remove your own Owner role or deactivate yourself.');
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $user->update(collect($data)->except('role')->all());
        $user->syncRoles([$data['role']]);

        return $this->done('User updated.', 'settings.users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->allow('users.delete');
        if ($user->is(auth()->user())) {
            return back()->with('error', 'You cannot delete yourself.');
        }
        $user->update(['is_active' => false]);

        return back()->with('success', 'User deactivated.');
    }

    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::where('business_id', \App\Support\Tenant::idOrFail())->orderBy('name')->pluck('name'),
            'branches' => Branch::orderBy('name')->pluck('name', 'id'),
        ];
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'branch_id' => ['required', $this->owned('branches')],
            'role' => ['required', Rule::exists('roles', 'name')->where('business_id', \App\Support\Tenant::idOrFail())],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
