<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentResource;
use App\Models\CashSession;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceService;
use App\Services\OrderService;
use App\Services\ReportService;
use App\Services\VoucherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/** Invoices, orders and receipts for mobile sales staff. All posting goes through the same services as the web app. */
class DocumentController extends Controller
{
    private const INVOICE_PERMS = ['sale' => 'sales', 'purchase' => 'purchases', 'sale_return' => 'sale_returns', 'purchase_return' => 'purchase_returns'];

    private const ORDER_PERMS = ['quotation' => 'quotations', 'sale_order' => 'sales_orders', 'purchase_order' => 'purchase_orders'];

    public function invoices(Request $request): AnonymousResourceCollection
    {
        $type = $request->query('type', 'sale');
        abort_unless(isset(self::INVOICE_PERMS[$type]), 422, 'Unknown invoice type.');
        $this->allow(self::INVOICE_PERMS[$type].'.view');

        return DocumentResource::collection(Invoice::with('party')->where('type', $type)
            ->when($request->query('party_id'), fn ($q, $p) => $q->where('party_id', $p))
            ->when($request->query('from'), fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($request->boolean('outstanding'), fn ($q) => $q->outstanding())
            ->latest('date')->latest('id')->paginate(min($request->integer('per_page', 50), 200)));
    }

    public function invoice(Invoice $invoice): DocumentResource
    {
        $this->allow(self::INVOICE_PERMS[$invoice->type].'.view');

        return new DocumentResource($invoice->load(['items.product', 'party']));
    }

    public function storeInvoice(Request $request, InvoiceService $service): JsonResponse
    {
        $type = $request->input('type', 'sale');
        abort_unless(isset(self::INVOICE_PERMS[$type]), 422, 'Unknown invoice type.');
        $this->allow(self::INVOICE_PERMS[$type].'.create');

        $data = $this->validatedLines($request, $type, [
            'payment_mode' => ['nullable', Rule::in(['cash', 'credit'])],
            'return_of_id' => ['nullable', $this->owned('invoices')],
            'order_id' => ['nullable', $this->owned('orders')],
            'wht_rate' => ['nullable', 'numeric', 'between:0,100'],
            'payments' => ['array'],
            'payments.*.account_id' => ['required', $this->owned('accounts')->whereIn('subtype', ['cash', 'bank'])],
            'payments.*.amount' => ['required', 'numeric', 'min:0'],
            'items.*.order_item_id' => ['nullable', 'integer'],
            'items.*.source_item_id' => ['nullable', 'integer'],
            'items.*.batch_no' => ['nullable', 'string', 'max:50'],
            'items.*.expiry_date' => ['nullable', 'date'],
        ]);
        if (in_array($type, ['sale', 'sale_return'], true) && ! empty($data['payments'])) {
            $data['cash_session_id'] = CashSession::currentFor($request->user())?->id;
        }

        return (new DocumentResource($service->save($type, $data)->load(['items.product', 'party'])))->response()->setStatusCode(201);
    }

    public function orders(Request $request): AnonymousResourceCollection
    {
        $type = $request->query('type', 'sale_order');
        abort_unless(isset(self::ORDER_PERMS[$type]), 422, 'Unknown order type.');
        $this->allow(self::ORDER_PERMS[$type].'.view');

        return DocumentResource::collection(Order::with('party')->where('type', $type)
            ->when($request->query('party_id'), fn ($q, $p) => $q->where('party_id', $p))
            ->when($request->query('status'), fn ($q, $s) => $s === 'pending' ? $q->whereIn('status', ['open', 'partial']) : $q->where('status', $s))
            ->latest('date')->latest('id')->paginate(min($request->integer('per_page', 50), 200)));
    }

    public function order(Order $order): DocumentResource
    {
        $this->allow(self::ORDER_PERMS[$order->type].'.view');

        return new DocumentResource($order->load(['items.product', 'items.unit', 'party']));
    }

    /** Order taking in the field: sales staff create quotations or sales orders. */
    public function storeOrder(Request $request, OrderService $service): JsonResponse
    {
        $type = $request->input('type', 'sale_order');
        abort_unless(isset(self::ORDER_PERMS[$type]), 422, 'Unknown order type.');
        $this->allow(self::ORDER_PERMS[$type].'.create');

        $data = $this->validatedLines($request, $type, ['terms' => ['nullable', 'string', 'max:2000']]);

        return (new DocumentResource($service->save($type, $data)->load(['items.product', 'items.unit', 'party'])))->response()->setStatusCode(201);
    }

    /** Payment collected from a customer (recovery), optionally applied to invoices. */
    public function storeReceipt(Request $request, VoucherService $vouchers): JsonResponse
    {
        $this->allow('vouchers.create');
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'party_id' => ['required', $this->owned('parties')],
            'cash_account_id' => ['required', $this->owned('accounts')->whereIn('subtype', ['cash', 'bank'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'narration' => ['nullable', 'string', 'max:500'],
            'allocations' => ['array'],
            'allocations.*.invoice_id' => ['required', $this->owned('invoices')],
            'allocations.*.amount' => ['required', 'numeric', 'min:0'],
        ]);
        $entry = $vouchers->save('receipt', $data + ['date' => $data['date'] ?? now()->toDateString(), 'branch_id' => $request->user()->branch_id]);

        return response()->json(['id' => $entry->id, 'number' => $entry->number, 'amount' => (float) $entry->amount], 201);
    }

    public function dashboard(ReportService $reports): JsonResponse
    {
        $this->allow('dashboard.view');
        $d = $reports->dashboard();

        return response()->json(collect($d)->only(['sales_today', 'sales_month', 'purchases_month', 'gross_profit_month', 'cash', 'receivable', 'payable', 'stock_value', 'trend']));
    }

    private function validatedLines(Request $request, string $type, array $extraRules = []): array
    {
        $partyScope = in_array($type, ['purchase', 'purchase_return', 'purchase_order'], true) ? ['supplier', 'both'] : ['customer', 'both'];

        return $request->validate([
            'date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'party_id' => ['required', $this->owned('parties')->whereIn('type', $partyScope)],
            'warehouse_id' => ['required', $this->owned('warehouses')],
            'reference' => ['nullable', 'string', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', $this->owned('products')],
            'items.*.variant_id' => ['nullable', $this->owned('product_variants')],
            'items.*.unit_id' => ['required', $this->owned('units')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'between:0,100'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            ...$extraRules,
        ]) + ['date' => $request->input('date', now()->toDateString())];
    }
}
