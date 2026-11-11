<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\StockAdjustment;
use App\Models\Warehouse;
use App\Services\StockDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StockAdjustmentController extends Controller
{
    public function __construct(private StockDocumentService $docs) {}

    public function index(Request $request): View
    {
        $this->allow('stock.view');

        return view('inventory.adjustments.index', [
            'adjustments' => StockAdjustment::with(['warehouse', 'creator'])->withCount('items')
                ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
                ->latest('date')->latest('id')->paginate(50)->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->allow('stock.adjust');
        $type = in_array($request->query('type'), ['opening', 'count'], true) ? $request->query('type') : 'adjustment';

        return view('inventory.adjustments.form', [
            'type' => $type,
            'warehouses' => Warehouse::where('is_active', true)->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow('stock.adjust');
        $type = $request->input('type');
        $data = $request->validate([
            'type' => ['required', Rule::in(['opening', 'adjustment', 'count'])],
            'warehouse_id' => ['required', $this->owned('warehouses')],
            'date' => ['required', 'date'],
            'reason' => [$type === 'adjustment' ? 'required' : 'nullable', Rule::in(array_keys(StockAdjustment::REASONS))],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', $this->owned('products'), 'distinct'],
            'items.*.quantity' => [$type === 'count' ? 'nullable' : 'required', 'numeric', $type === 'opening' ? 'gt:0' : 'not_in:0'],
            'items.*.counted' => [$type === 'count' ? 'required' : 'nullable', 'numeric', 'min:0'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
        ], ['items.*.product_id.distinct' => 'Each product can appear only once.']);

        $adj = $this->docs->adjust($data);

        return $this->done("Stock {$adj->type} {$adj->number} saved.", 'stock-adjustments.show', $adj);
    }

    public function show(StockAdjustment $stockAdjustment): View
    {
        $this->allow('stock.view');

        return view('inventory.adjustments.show', ['adj' => $stockAdjustment->load(['items.product.unit', 'warehouse', 'creator'])]);
    }

    public function destroy(StockAdjustment $stockAdjustment): RedirectResponse
    {
        $this->allow('stock.adjust');
        $this->docs->deleteAdjustment($stockAdjustment);

        return $this->done("{$stockAdjustment->number} deleted and stock restored.", 'stock-adjustments.index');
    }
}
