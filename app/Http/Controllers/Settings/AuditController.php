<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The audit trail: who created, changed or deleted what, plus sign-ins and exports. */
class AuditController extends Controller
{
    public const TYPES = [
        'invoice' => 'Invoices', 'journal_entry' => 'Vouchers / entries', 'party' => 'Customers & suppliers', 'product' => 'Products',
        'order' => 'Orders & quotations', 'delivery' => 'Delivery challans', 'stock_adjustment' => 'Stock adjustments',
        'stock_transfer' => 'Stock transfers', 'cash_session' => 'Registers', 'account' => 'Chart of accounts', 'user' => 'Users',
        'business' => 'Business settings', 'branch' => 'Branches', 'warehouse' => 'Warehouses', 'party_price' => 'Customer prices',
        'tax_rate' => 'Tax rates', 'price_level' => 'Price levels', 'category' => 'Categories', 'unit' => 'Units',
    ];

    public const EVENTS = ['created' => 'Created', 'updated' => 'Changed', 'lines_changed' => 'Lines changed', 'deleted' => 'Deleted',
        'login' => 'Signed in', 'logout' => 'Signed out', 'login_failed' => 'Failed sign-in', 'exported' => 'Exported'];

    public function index(Request $request): View
    {
        $this->allow('audit.view');

        $logs = AuditLog::with('user:id,name')
            ->when($request->query('user_id'), fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->query('event'), fn ($q, $e) => $q->where('event', $e))
            ->when($request->query('type'), fn ($q, $t) => $q->where('auditable_type', $t))
            ->when($request->query('id'), fn ($q, $id) => $q->where('auditable_id', $id))
            ->when($request->query('q'), fn ($q, $t) => $q->where('label', 'like', "%{$t}%"))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest('id')->paginate(50)->withQueryString();

        return view('settings.audit', [
            'logs' => $logs,
            'users' => User::orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
