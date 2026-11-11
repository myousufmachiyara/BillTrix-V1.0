@extends('layouts.app')
@section('title', $product->name)
@section('header-actions')
    <a href="{{ route('products.index') }}" class="btn btn-default btn-sm"><i class="fas fa-list"></i> Products</a>
@endsection
@section('content')
<div class="row">
    <div class="col-lg-4">
        <x-card>
            <x-slot:actions>
                @can('products.edit')<a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-default"><i class="fas fa-edit"></i> Edit</a>@endcan
                @can('products.delete')<x-delete :action="route('products.destroy', $product)" label="Delete" />@endcan
            </x-slot:actions>
            @if ($product->image_path)<img src="{{ asset('storage/'.$product->image_path) }}" class="img-fluid rounded mb-3" alt="">@endif
            <dl class="row small mb-0">
                <dt class="col-5">SKU</dt><dd class="col-7">{{ $product->sku ?: '—' }}</dd>
                <dt class="col-5">Barcode</dt><dd class="col-7">{{ $product->barcode ?: '—' }}</dd>
                <dt class="col-5">Category</dt><dd class="col-7">{{ $product->category?->name ?: '—' }}</dd>
                <dt class="col-5">Brand</dt><dd class="col-7">{{ $product->brand ?: '—' }}</dd>
                <dt class="col-5">Base unit</dt><dd class="col-7">{{ $product->unit->name }}</dd>
                <dt class="col-5">Sale price</dt><dd class="col-7">{{ money($product->sale_price) }}</dd>
                <dt class="col-5">Purchase price</dt><dd class="col-7">{{ money($product->purchase_price) }}</dd>
                @can('reports.profit')<dt class="col-5">Average cost</dt><dd class="col-7">{{ money($product->avg_cost, 4) }}</dd>@endcan
                @if ($product->taxRate)<dt class="col-5">Tax</dt><dd class="col-7">{{ $product->taxRate->name }}</dd>@endif
                <dt class="col-5">Reorder level</dt><dd class="col-7">{{ qty($product->reorder_level) }}</dd>
                @foreach ($product->prices as $p)<dt class="col-5">{{ $p->priceLevel->name }}</dt><dd class="col-7">{{ money($p->price) }}</dd>@endforeach
            </dl>
            @if ($product->units->isNotEmpty())
                <hr><h6>Packing</h6>
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
                    <tr><td class="text-muted">{{ $product->track_stock ? 'No stock yet.' : 'Stock is not tracked for this item.' }}</td></tr>
                @endforelse
                </tbody>
                @if ($stock->count() > 1)<tfoot><tr><td>Total</td><td class="num">{{ qty($stock->sum('quantity')) }} {{ $product->unit->short_name }}</td></tr></tfoot>@endif
            </table>
        </x-card>
        <x-card title="Recent movements" :body="false">
            <x-slot:actions>
                @can('reports.inventory')<a href="{{ route('reports.stock-movement', ['product_id' => $product->id]) }}" class="btn btn-sm btn-default">Full history</a>@endcan
            </x-slot:actions>
            <table class="table table-sm mb-0">
                <thead class="table-light"><tr><th>Date</th><th>Type</th><th>Document</th><th>Warehouse</th><th class="num">Qty</th>@can('reports.profit')<th class="num">Cost</th>@endcan</tr></thead>
                <tbody>
                @forelse ($movements as $m)
                    <tr>
                        <td>{{ $m->date->format('d-m-Y') }}</td>
                        <td>{{ \App\Models\StockMovement::TYPES[$m->type] ?? $m->type }} @if ($m->reason)<span class="text-muted small">({{ $m->reason }})</span>@endif</td>
                        <td>{{ $m->source?->number }}</td><td>{{ $m->warehouse->name }}</td>
                        <td class="num {{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ qty($m->quantity) }}</td>
                        @can('reports.profit')<td class="num text-muted">{{ money($m->unit_cost) }}</td>@endcan
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted text-center py-3">No movements.</td></tr>
                @endforelse
                </tbody>
            </table>
        </x-card>
    </div>
</div>
@endsection
