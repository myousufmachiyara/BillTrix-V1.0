<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function __construct(private StockDocumentService $docs) {}

    public function index(): View
    {
        $this->allow('stock.view');

        return view('inventory.transfers.index', [
            'transfers' => StockTransfer::with(['fromWarehouse', 'toWarehouse'])->withCount('items')->latest('date')->latest('id')->paginate(50),
        ]);
    }

    public function create(): View
    {
        $this->allow('stock.transfer');

        return view('inventory.transfers.form', [
            'warehouses' => Warehouse::with('branch')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow('stock.transfer');
        $data = $request->validate([
            'from_warehouse_id' => ['required', $this->owned('warehouses')],
            'to_warehouse_id' => ['required', $this->owned('warehouses'), 'different:from_warehouse_id'],
            'date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', $this->owned('products')],
            'items.*.unit_id' => ['required', $this->owned('units')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);

        $transfer = $this->docs->transfer($data);

        return $this->done("Transfer {$transfer->number} saved.", 'stock-transfers.show', $transfer);
    }

    public function show(StockTransfer $stockTransfer): View
    {
        $this->allow('stock.view');

        return view('inventory.transfers.show', ['transfer' => $stockTransfer->load(['items.product', 'items.unit', 'fromWarehouse', 'toWarehouse'])]);
    }

    public function destroy(StockTransfer $stockTransfer): RedirectResponse
    {
        $this->allow('stock.transfer');
        $this->docs->deleteTransfer($stockTransfer);

        return $this->done("{$stockTransfer->number} reversed.", 'stock-transfers.index');
    }
}
