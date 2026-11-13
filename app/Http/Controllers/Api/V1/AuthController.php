<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Exchange username or email + password for a device token (send as "Authorization: Bearer <token>"). */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required_without:login', 'nullable', 'string', 'max:255'],
            'login' => ['required_without:email', 'nullable', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $login = $data['login'] ?? $data['email'];
        \App\Support\LoginGuard::check($request, $login, 'email');
        $user = User::withoutGlobalScopes()->where(str_contains($login, '@') ? 'email' : 'username', $login)->where('is_active', true)->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            \App\Support\LoginGuard::failed($request, $login);
            throw ValidationException::withMessages(['email' => __('These credentials do not match our records.')]);
        }

        \App\Support\LoginGuard::succeeded($request, $login);
        Tenant::set($user->business_id);
        if (! $user->can('api.use') || ! Tenant::business()->is_active) {
            throw ValidationException::withMessages(['email' => __('This account is not allowed to use the mobile app.')]);
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return response()->json([
            'token' => $user->createToken($data['device_name'])->plainTextToken,
            ...$this->profile($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->profile($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    private function profile(User $user): array
    {
        $b = Tenant::business();

        return [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'branch_id' => $user->branch_id, 'locale' => $user->locale],
            'business' => ['id' => $b->id, 'name' => $b->name, 'currency' => $b->currency, 'category' => $b->category, 'features' => collect($b->allSettings())->filter(fn ($v) => is_bool($v))],
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
