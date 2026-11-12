<?php

namespace App\Http\Controllers\Trade;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\FbrService;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** FBR reporting status, with manual retry. */
class FbrController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAny(['fbr.manage', 'sales.view']), 403);
        abort_if(Tenant::business()->setting('fbr_mode', 'off') === 'off', 404);

        $base = Invoice::whereNotNull('fbr_status');

        return view('fbr.index', [
            'counts' => (clone $base)->selectRaw('fbr_status, COUNT(*) as n')->groupBy('fbr_status')->pluck('n', 'fbr_status'),
            'invoices' => (clone $base)->with('party:id,name,is_walk_in')
                ->when($request->query('status', 'failed'), fn ($q, $s) => $s === 'all' ? $q : $q->where('fbr_status', $s))
                ->latest('id')->paginate(50)->withQueryString(),
        ]);
    }

    public function retry(Invoice $invoice, FbrService $fbr): RedirectResponse
    {
        $this->allow('sales.create');
        $ok = $fbr->submit($invoice);

        return back()->with($ok ? 'success' : 'error', $ok
            ? "{$invoice->number} reported. FBR number {$invoice->fresh()->fbr_invoice_number}."
            : "{$invoice->number} was not accepted: ".$invoice->fresh()->fbr_response);
    }

    public function retryAll(FbrService $fbr): RedirectResponse
    {
        $this->allow('fbr.manage');
        $pending = Invoice::whereIn('fbr_status', ['pending', 'failed'])->oldest('id')->limit(200)->get();
        $ok = $pending->filter(fn ($i) => $fbr->submit($i))->count();

        return back()->with($ok === $pending->count() ? 'success' : 'error', "{$ok} of {$pending->count()} invoices reported to FBR.");
    }
}
