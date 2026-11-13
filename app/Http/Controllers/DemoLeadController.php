<?php

namespace App\Http\Controllers;

use App\Models\DemoLead;
use App\Services\SpreadsheetService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The demo install owner's list of everyone who tried the demo. */
class DemoLeadController extends Controller
{
    public function index(Request $request): View
    {
        $leads = $this->filtered($request)->latest('last_seen_at')->latest('id')->paginate(50)->withQueryString();
        $counts = DemoLead::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $today = DemoLead::where('created_at', '>=', now()->startOfDay())->count();

        return view('demo-leads.index', compact('leads', 'counts', 'today'));
    }

    public function export(Request $request, SpreadsheetService $sheets): StreamedResponse
    {
        $statuses = config('billtrix.demo.statuses');
        $rows = $this->filtered($request)->latest('id')->get()->map(fn (DemoLead $l) => [
            $l->created_at?->format('Y-m-d H:i'), $l->name, $l->phone, $l->business_name, $l->city, $l->business_type,
            $l->source, $l->campaign, $l->visits, $l->last_seen_at?->format('Y-m-d H:i'), $statuses[$l->status] ?? $l->status, $l->notes,
        ]);

        return $sheets->download('billtrix-demo-leads-'.now()->format('Y-m-d'),
            ['First visit', 'Name', 'Phone', 'Business', 'City', 'Business type', 'Source', 'Campaign', 'Visits', 'Last visit', 'Status', 'Notes'],
            $rows, 'BillTrix demo leads');
    }

    public function update(Request $request, DemoLead $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(config('billtrix.demo.statuses')))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $lead->update($data);

        return back()->with('success', __('Lead updated.'));
    }

    private function filtered(Request $request): Builder
    {
        return DemoLead::query()
            ->search($request->query('q'))
            ->when($request->query('status'), fn (Builder $q, $s) => $q->where('status', $s))
            ->when($request->query('source'), fn (Builder $q, $s) => $q->where('source', $s))
            ->when($request->query('from'), fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d));
    }
}
