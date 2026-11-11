<?php

namespace App\Http\Controllers\Trade;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CashSession;
use App\Services\CashSessionService;
use App\Support\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Opening and closing the register (daily cash closing). */
class CashSessionController extends Controller
{
    public function __construct(private CashSessionService $service) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()->canAny(['pos.use', 'cash_sessions.view']), 403);
        $mine = CashSession::currentFor($request->user());

        $sessions = CashSession::with(['user', 'account'])
            ->when(! $request->user()->can('cash_sessions.view'), fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($request->query('date'), fn ($q, $d) => $q->whereDate('opened_at', $d))
            ->latest('id')->paginate(30)->withQueryString();

        return view('cash-sessions.index', [
            'mine' => $mine,
            'sessions' => $sessions,
            'cashAccounts' => Account::cashOrBank()->where('subtype', 'cash')->orderBy('code')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow('pos.use');
        $data = $request->validate([
            'account_id' => ['required', $this->owned('accounts')],
            'opening_float' => ['required', 'numeric', 'min:0'],
        ]);
        $this->service->open($request->user(), (int) $data['account_id'], (float) $data['opening_float']);

        return redirect()->route('pos')->with('success', 'Register opened. Happy selling!');
    }

    public function show(CashSession $cashSession): View
    {
        $this->authorizeSession($cashSession);

        return view('cash-sessions.show', ['session' => $cashSession->load(['user', 'account', 'closer']), 'summary' => $this->service->summary($cashSession)]);
    }

    public function closeForm(CashSession $cashSession): View|RedirectResponse
    {
        $this->authorizeSession($cashSession, close: true);
        if (! $cashSession->isOpen()) {
            return redirect()->route('cash-sessions.show', $cashSession);
        }

        return view('cash-sessions.close', ['session' => $cashSession->load(['user', 'account']), 'summary' => $this->service->summary($cashSession)]);
    }

    public function close(Request $request, CashSession $cashSession): RedirectResponse
    {
        $this->authorizeSession($cashSession, close: true);
        $data = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'denominations' => ['array'],
            'denominations.*' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $session = $this->service->close($cashSession, (float) $data['counted_cash'], $data['denominations'] ?? [], $data['notes'] ?? null, $request->user());

        return redirect()->route('cash-sessions.show', $session)->with('success', 'Register closed.')->with('print', route('cash-sessions.print', $session));
    }

    public function print(CashSession $cashSession): View
    {
        $this->authorizeSession($cashSession);

        return view('cash-sessions.print', ['session' => $cashSession->load(['user', 'account', 'closer', 'branch']), 'summary' => $this->service->summary($cashSession), 'b' => Tenant::business()]);
    }

    /** Cashiers see and close their own register; managers can see and close anyone's. */
    private function authorizeSession(CashSession $session, bool $close = false): void
    {
        $user = auth()->user();
        $own = $session->user_id === $user->id;
        abort_unless($close ? ($own && $user->can('pos.use')) || $user->can('cash_sessions.manage') : $own || $user->can('cash_sessions.view'), 403);
    }
}
