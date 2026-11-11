<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\BusinessProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        abort_unless(config('billtrix.allow_registration'), 404);

        return view('auth.register');
    }

    public function register(Request $request, BusinessProvisioner $provisioner): RedirectResponse
    {
        abort_unless(config('billtrix.allow_registration'), 404);

        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::in(array_keys(config('billtrix.categories')))],
            'phone' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = $provisioner->create(
            ['name' => $data['business_name'], 'category' => $data['category'], 'phone' => $data['phone'] ?? null],
            ['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']],
        );

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welcome to BillTrix! Your business is ready.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function changePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $request->password]);

        return back()->with('success', 'Password changed.');
    }
}
