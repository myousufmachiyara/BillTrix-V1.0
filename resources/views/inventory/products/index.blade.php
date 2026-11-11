@extends('layouts.app')
@section('title', 'Products')
@section('header-actions')
    @can('products.create')<a href="{{ route('products.create') }}" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Add product</a>@endcan
@endsection
@section('content')
<x-card :body="false">
    <div class="card-body pb-0">
        <form class="row g-2 filters">
            <div class="col-md-4"><input type="search" name="q" class="form-control form-control-sm" value="{{ request('q') }}" placeholder="Name, SKU or barcode" autofocus></div>
            <div class="col-md-3">
                <select name="category" class="form-select form-select-sm"><option value="">All categories</option>
                    @foreach ($categories as $id => $name)<option value="{{ $id }}" @selected((int) request('category') === $id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <select name="status" class="form-select form-select-sm">
                    <option value="">Active</option>
                    <option value="low" @selected(request('status') === 'low')>Low stock</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
            </div>
            <div class="col-auto"><button class="btn btn-sm btn-primary">Search</button></div>
        </form>
    </div>
    <div class="table-responsive mt-3">
        <table class="table table-hover mb-0">
            <thead class="table-light"><tr><th>Product</th><th>SKU / Barcode</th><th>Category</th><th class="num">Sale price</th>@can('reports.profit')<th class="num">Avg. cost</th>@endcan<th class="num">Stock</th></tr></thead>
            <tbody>
            @forelse ($products as $p)
                @php $low = $p->track_stock && (float) $p->reorder_level > 0 && (float) $p->stock <= (float) $p->reorder_level; @endphp
                <tr data-href="{{ route('products.show', $p) }}" style="cursor:pointer">
                    <td class="fw-semibold">{{ $p->name }}</td>
                    <td class="small">{{ $p->sku }}<br><span class="text-muted">{{ $p->barcode }}</span></td>
                    <td>{{ $p->category?->name }}</td>
                    <td class="num">{{ money($p->sale_price) }}</td>
                    @can('reports.profit')<td class="num text-muted">{{ money($p->avg_cost) }}</td>@endcan
                    <td class="num {{ $low ? 'text-danger fw-bold' : '' }}">{{ $p->track_stock ? qty($p->stock ?? 0).' '.$p->unit->short_name : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center text-muted py-5">No products found. @can('products.create')<a href="{{ route('products.create') }}">Add your first product</a>.@endcan</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if ($products->hasPages())<div class="card-body">{{ $products->links() }}</div>@endif
</x-card>
@endsection
