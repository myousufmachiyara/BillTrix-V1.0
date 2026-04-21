<?php
 
// ============================================================
// FILE 1: app/Http/Middleware/BranchScope.php
// ============================================================
// Register in bootstrap/app.php (Laravel 11) or Kernel.php (Laravel 10):
//   'branch.scope' => \App\Http\Middleware\BranchScope::class
//
// Apply to any route or group that should be branch-isolated:
//   Route::middleware(['auth', 'branch.scope'])->group(...)
// ============================================================
// 

namespace App\Http\Middleware;
 
use Closure;
use Illuminate\Http\Request;
 
class BranchScope
{
    /**
     * If the authenticated user has a branch_id, share it with all views
     * and bind it into the request so controllers can read it without
     * coupling themselves to auth().
     *
     * This does NOT filter Eloquent queries automatically — use the
     * BranchScopedController trait (below) in individual controllers.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth()->user();
 
        if ($user && $user->branch_id) {
            // Make branch_id available to all Blade views
            view()->share('currentBranchId', $user->branch_id);
 
            // Merge into request so controllers read $request->branch_id
            $request->merge(['branch_id' => $user->branch_id]);
        }
 
        return $next($request);
    }
}