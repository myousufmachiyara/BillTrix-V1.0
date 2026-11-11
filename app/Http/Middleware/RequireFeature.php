<?php

namespace App\Http\Middleware;

use App\Support\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks routes for features the business has switched off. Usage: ->middleware('feature:pos') */
class RequireFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(Tenant::business()->feature($feature), 404);

        return $next($request);
    }
}
