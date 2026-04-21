<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * CheckModulePermission
 *
 * Resolves the final Spatie permission string from the route and checks it.
 *
 * Usage in routes:
 *   ->middleware('check.permission:products.index')
 *   ->middleware('check.permission:reports.inventory')   ← dot-notation passes through as-is
 *
 * For standard CRUD routes the loop in web.php already appends the action suffix
 * (e.g. "products.create"), so this middleware simply calls can() on what it receives.
 *
 * For cases where the route action name differs from the permission action name
 * (store → create, update → edit, destroy → delete) the map below normalises them.
 * This matters if you ever register routes manually without appending the suffix.
 */
class CheckModulePermission
{
    /**
     * Map Laravel controller action names → permission action names.
     */
    private const ACTION_MAP = [
        'store'   => 'create',
        'update'  => 'edit',
        'destroy' => 'delete',
    ];

    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        $user = auth()->user();

        if (! $user) {
            abort(403, 'Unauthorized action.');
        }

        // If the permission string already contains a dot it is either:
        //   (a) a fully-qualified "module.action" from the web.php loop  → use as-is
        //   (b) a report gate like "reports.inventory"                    → use as-is
        if (str_contains($permission, '.')) {
            $finalPermission = $permission;
        } else {
            // Bare module name (e.g. "products") — derive action from the route method.
            $action          = $request->route()->getActionMethod();
            $mappedAction    = self::ACTION_MAP[$action] ?? $action;
            $finalPermission = "{$permission}.{$mappedAction}";
        }

        if (! $user->can($finalPermission)) {
            // Return JSON for AJAX requests, redirect for normal requests.
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized.'], 403);
            }

            abort(403, 'Unauthorized action.');
        }

        return $next($request);
    }
}