<?php

namespace App\Http\Controllers;

use App\Support\Tenant;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

abstract class Controller
{
    /** Abort with 403 unless the user has the permission. */
    protected function allow(string $permission): void
    {
        abort_unless(auth()->user()?->can($permission), 403);
    }

    /** "exists" rule limited to the current business. */
    protected function owned(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)->where('business_id', Tenant::idOrFail());
    }

    /** When ?export=xlsx is on the URL, return the rows as an Excel download instead of the page. */
    protected function exportIfAsked(\Illuminate\Http\Request $request, string $name, array $headings, callable $rows, ?string $title = null): ?\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if ($request->query('export') !== 'xlsx') {
            return null;
        }

        \App\Support\Audit::event('exported', null, ['report' => $name, 'query' => $request->except('export')]);

        return app(\App\Services\SpreadsheetService::class)->download($name.'-'.now()->format('Y-m-d'), $headings, $rows(), $title);
    }

    protected function done(string $message, string $route, mixed $params = []): \Illuminate\Http\RedirectResponse
    {
        return redirect()->route($route, $params)->with('success', $message);
    }
}
