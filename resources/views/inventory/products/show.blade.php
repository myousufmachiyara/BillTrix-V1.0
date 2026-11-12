@extends('layouts.app')
@section('title', $product->name)
@section('header-actions')
    <a href="{{ route('products.index') }}" class="btn btn-default btn-sm"><i class="fas fa-list"></i> {{ __('Products') }}</a>
@endsection
@section('content')
<div class="row">
    <div class="col-lg-4">
        <x-card>
            <x-slot:actions>
                @can('audit.view')<a href="{{ route('settings.audit', ['type' => 'product', 'id' => $product->id]) }}" class="btn btn-sm btn-default" title="{{ __('History') }}"><i class="fas fa-history"></i></a>@endcan
                @can('products.edit')<a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-default"><i class="fas fa-edit"></i> {{ __('Edit') }}</a>@endcan
                @can('products.delete')<x-delete :action="route('products.destroy', $product)" label="Delete" />@endcan
            </x-slot:actions>
            @if ($product->image_path)<img src="{{ asset('storage/'.$product->image_path) }}" class="img-fluid rounded mb-3" alt="">@endif
            <dl class="row small mb-0">
                <dt class="col-5">{{ __('SKU') }}</dt><dd class="col-7">{{ $product->sku ?: '—' }}</dd>
                <dt class="col-5">{{ __('Barcode') }}</dt><dd class="col-7">{{ $product->barcode ?: '—' }}</dd>
                <dt class="col-5">{{ __('Category') }}</dt><dd class="col-7">{{ $product->category?->name ?: '—' }}</dd>
                <dt class="col-5">{{ __('Brand') }}</dt><dd class="col-7">{{ $product->brand ?: '—' }}</dd>
                <dt class="col-5">{{ __('Base unit') }}</dt><dd class="col-7">{{ $product->unit->name }}</dd>
                <dt class="col-5">{{ __('Sale price') }}</dt><dd class="col-7">{{ money($product->sale_price) }}</dd>
                <dt class="col-5">{{ __('Purchase price') }}</dt><dd class="col-7">{{ money($product->purchase_price) }}</dd>
                @can('reports.profit')<dt class="col-5">{{ __('Average cost') }}</dt><dd class="col-7">{{ money($product->avg_cost, 4) }}</dd>@endcan
                @if ($product->taxRate)<dt class="col-5">{{ __('Tax') }}</dt><dd class="col-7">{{ $product->taxRate->name }}</dd>@endif
                <dt class="col-5">{{ __('Reorder level') }}</dt><dd class="col-7">{{ qty($product->reorder_level) }}</dd>
                @foreach ($product->prices as $p)<dt class="col-5">{{ $p->priceLevel->name }}</dt><dd class="col-7">{{ money($p->price) }}</dd>@endforeach
            </dl>
            @if ($product->units->isNotEmpty())
                <hr><h6>{{ __('Packing') }}</h6>
                <ul class="small mb-0">@foreach ($product->units as $u)<li>1 {{ $u->unit->name }} = {{ qty($u->factor) }} {{ $product->unit->short_name }} @if ($u->barcode)<span class="text-muted">· {{ $u->barcode }}</span>@endif</li>@endforeach</ul>
            @endif
        </x-card>
    </div>
    <div class="col-lg-8">
        <x-card title="Stock by warehouse" :body="false">
            <table class="table table-sm mb-0">
                <tbody>
                @forelse ($stock as $s)
                    <tr><td>{{ $s->warehouse->name }}</td><td class="num">{{ qty($s->quantity) }} {{ $product->unit->short_name }}</td>
                        @foreach ($product->units as $u)<td class="num text-muted small">{{ qty(floor((float) $s->quantity / (float) $u->factor)) }} {{ $u->unit->short_name }} + {{ qty(fmod((float) $s->quantity, (float) $u->factor)) }}</td>@endforeach
                    </tr>
                @empty
                    <tr><td class="text-muted">{{ $product->track_stock ? __('No stock yet.') : __('Stock is not tracked for this item.') }}</td></tr>
                @endforelse
                </tbody>
                @if ($stock->count() > 1)<tfoot><tr><td>{{ __('Total') }}</td><td class="num">{{ qty($stock->sum('quantity')) }} {{ $product->unit->short_name }}</td></tr></tfoot>@endif
            </table>
        </x-card>
        @if ($product->has_variants)
            @php $whIds = $variants->flatMap->balances->pluck('warehouse_id')->unique()->sort()->values(); @endphp
            <x-card title="Variations" :body="false">
                <x-slot:actions>
                    @can('products.labels')<a href="{{ route('labels.index', ['product_id' => $product->id]) }}" class="btn btn-sm btn-default"><i class="fas fa-barcode"></i> {{ __('Print labels') }}</a>@endcan
                </x-slot:actions>
                <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>{{ __('Variation') }}</th><th>{{ __('SKU') }}</th><th>{{ __('Barcode') }}</th><th class="num">{{ __('Sale price') }}</th>
                        @can('reports.profit')<th class="num">{{ __('Avg. cost') }}</th>@endcan
                        @if ($whIds->count() > 1)@foreach ($whIds as $w)<th class="num">{{ $warehouseNames[$w] ?? '' }}</th>@endforeach @endif
                        <th class="num">{{ __('In stock') }}</th></tr></thead>
                    <tbody>
                    @foreach ($variants as $v)
                        @php $qty = (float) $v->balances->sum('quantity'); $low = $product->track_stock && $qty <= $v->reorderLevel(); @endphp
                        <tr class="{{ $v->is_active ? '' : 'text-muted' }}">
                            <td>{{ $v->name }} @unless ($v->is_active)<span class="badge bg-secondary">{{ __('Inactive') }}</span>@endunless</td>
                            <td>{{ $v->sku }}</td><td>{{ $v->barcode }}</td><td class="num">{{ money($v->salePrice()) }}</td>
                            @can('reports.profit')<td class="num text-muted">{{ money($v->avg_cost) }}</td>@endcan
                            @if ($whIds->count() > 1)@foreach ($whIds as $w)<td class="num">{{ qty($v->balances->where('warehouse_id', $w)->sum('quantity')) }}</td>@endforeach @endif
                            <td class="num fw-semibold {{ $low ? 'text-danger' : '' }}">{{ qty($qty) }} {{ $product->unit->short_name }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            </x-card>
        @endif
        @if ($product->track_batches)
            <x-card title="Batches in stock" :body="false">
                @php $wh = $stock->pluck('warehouse.name', 'warehouse_id'); @endphp
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>{{ __('Batch') }}</th><th>{{ __('Expiry') }}</th><th>{{ __('Warehouse') }}</th><th class="num">{{ __('Quantity') }}</th></tr></thead>
                    <tbody>
                    @forelse ($batches as $b)
                        @php $exp = $b->expiry_date ? \Carbon\Carbon::parse($b->expiry_date) : null; @endphp
                        <tr class="{{ $exp && $exp->isPast() ? 'table-danger' : ($exp && $exp->lte(now()->addDays((int) $business->setting('expiry_alert_days', 60))) ? 'table-warning' : '') }}">
                            <td>{{ $b->batch_no }}</td><td>{{ $exp?->format('d-m-Y') ?? '—' }}</td><td>{{ $wh[$b->warehouse_id] ?? '' }}</td><td class="num">{{ qty($b->quantity) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">{{ __('No batch stock.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </x-card>
        @endif
        <x-card title="Recent movements" :body="false">
            <x-slot:actions>
                @can('reports.inventory')<a href="{{ route('reports.stock-movement', ['product_id' => $product->id]) }}" class="btn btn-sm btn-default">{{ __('Full history') }}</a>@endcan
            </x-slot:actions>
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Document') }}</th><th>{{ __('Warehouse') }}</th><th class="num">{{ __('Qty') }}</th>@can('reports.profit')<th class="num">{{ __('Cost') }}</th>@endcan</tr></thead>
                <tbody>
                @forelse ($movements as $m)
                    <tr>
                        <td>{{ $m->date->format('d-m-Y') }}</td>
                        <td>{{ __(\App\Models\StockMovement::TYPES[$m->type] ?? $m->type) }} @if ($m->reason)<span class="text-muted small">({{ $m->reason }})</span>@endif</td>
                        <td>{{ $m->source?->number }}@if ($m->variant) <span class="badge bg-light text-dark border">{{ $m->variant->name }}</span>@endif</td><td>{{ $m->warehouse->name }}</td>
                        <td class="num {{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ qty($m->quantity) }}</td>
                        @can('reports.profit')<td class="num text-muted">{{ money($m->unit_cost) }}</td>@endcan
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted text-center py-3">{{ __('No movements.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
    </div>
</div>
@endsection
