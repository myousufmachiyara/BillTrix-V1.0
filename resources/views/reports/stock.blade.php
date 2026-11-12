@extends('layouts.app')
@section('title', __('Stock & Valuation'))
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    @if ($warehouses->count() > 1)
        <div class="col-md-3"><label class="form-label small mb-1">{{ __('Warehouse') }}</label><select name="warehouse_id" class="form-select form-select-sm"><option value="">{{ __('All') }}</option>@foreach ($warehouses as $id => $n)<option value="{{ $id }}" @selected((int) request('warehouse_id') === $id)>{{ $n }}</option>@endforeach</select></div>
    @endif
    <div class="col-md-3"><label class="form-label small mb-1">{{ __('Category') }}</label><select name="category_id" class="form-select form-select-sm"><option value="">{{ __('All') }}</option>@foreach ($categories as $id => $n)<option value="{{ $id }}" @selected((int) request('category_id') === $id)>{{ $n }}</option>@endforeach</select></div>
    <div class="col-auto"><div class="form-check"><input class="form-check-input" type="checkbox" name="all" value="1" id="all" @checked(request('all'))><label class="form-check-label" for="all">{{ __('Include zero stock') }}</label></div></div>
    <div class="col-auto"><button class="btn btn-sm btn-primary">{{ __('Show') }}</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button> <a href="{{ request()->fullUrlWithQuery(['export' => 'xlsx']) }}" class="btn btn-sm btn-default" title="{{ __('Excel') }}"><i class="fas fa-file-excel text-success"></i></a></div>
</form>
@include('reports._header', ['title' => 'Stock & Valuation', 'period' => 'As of '.now()->format('d M Y')])
<x-card :body="false">
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light"><tr><th>{{ __('Product') }}</th><th>{{ __('SKU') }}</th><th>{{ __('Category') }}</th><th class="num">{{ __('Qty') }}</th><th class="num">{{ __('Avg. cost') }}</th><th class="num">{{ __('Cost value') }}</th><th class="num">{{ __('Retail value') }}</th></tr></thead>
            <tbody>
            @foreach ($rows as $p)
                <tr data-href="{{ route('reports.stock-movement', ['product_id' => $p->id]) }}" style="cursor:pointer" class="{{ $p->qty < 0 ? 'table-danger' : '' }}">
                    <td>{{ $p->name }}</td><td class="small">{{ $p->sku }}</td><td class="small">{{ $p->category?->name }}</td>
                    <td class="num">{{ qty($p->qty) }} {{ $p->unit->short_name }}</td><td class="num">{{ money($p->avg_cost) }}</td>
                    <td class="num">{{ money($p->value) }}</td><td class="num text-muted">{{ money($p->retail_value) }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><td colspan="5">{{ $rows->count() }} {{ __('products') }}</td><td class="num">{{ money($rows->sum('value')) }}</td><td class="num">{{ money($rows->sum('retail_value')) }}</td></tr></tfoot>
        </table>
    </div>
</x-card>
@endsection
