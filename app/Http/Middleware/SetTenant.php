<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Binds the signed-in user's business for the rest of the request. */
class SetTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            Tenant::set($user->business_id);
            $user->loadMissing(['roles', 'branch']);
            $business = Tenant::business();

            if (! $user->is_active || ! $business->is_active) {
                Auth::logout();
                $request->session()->invalidate();

                return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated.']);
            }
        }

        return $next($request);
    }
}
