<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Lightweight session-based auth for frontend preview.
 * Replace with Laravel's standard auth middleware once backend is ready.
 */
class FrontendPreviewAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (! session()->has('user_name')) {
            return redirect()->route('login');
        }

        return $next($request);
    }
}
