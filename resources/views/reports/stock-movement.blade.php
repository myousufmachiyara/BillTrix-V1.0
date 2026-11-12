@extends('layouts.app')
@section('title', __('Stock Movement'))
@section('content')
<form class="row g-2 align-items-end mb-3 filters no-print">
    <div class="col-md-4"><label class="form-label small mb-1">{{ __('Product') }}</label><x-product-select :product="$product" class="form-select-sm" /></div>
    @if ($warehouses->count() > 1)
        <div class="col-md-2"><label class="form-label small mb-1">{{ __('Warehouse') }}</label><select name="warehouse_id" class="form-select form-select-sm"><option value="">{{ __('All') }}</option>@foreach ($warehouses as $id => $n)<option value="{{ $id }}" @selected($warehouseId === $id)>{{ $n }}</option>@endforeach</select></div>
    @endif
    <x-date-range :from="$from" :to="$to" />
    <div class="col-auto"><button class="btn btn-sm btn-primary">{{ __('Show') }}</button> <button type="button" onclick="window.print()" class="btn btn-sm btn-default"><i class="fas fa-print"></i></button></div>
</form>
@if ($data)
    @include('reports._header', ['title' => 'Stock movement — '.$product->name, 'period' => \Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')])
    <x-card :body="false">
        <table class="table table-sm mb-0">
            <thead class="table-light"><tr><th>{{ __('Date') }}</th><th>{{ __('Type') }}</th><th>{{ __('Document') }}</th><th>{{ __('Warehouse') }}</th><th class="num">{{ __('In') }}</th><th class="num">{{ __('Out') }}</th><th class="num">{{ __('Balance') }}</th></tr></thead>
            <tbody>
                <tr class="fw-semibold"><td colspan="6">{{ __('Opening') }}</td><td class="num">{{ qty($data['opening']) }}</td></tr>
                @foreach ($data['rows'] as $m)
                    <tr><td>{{ $m->date->format('d-m-Y') }}</td><td>{{ __(\App\Models\StockMovement::TYPES[$m->type] ?? $m->type) }} @if ($m->reason)<small class="text-muted">({{ $m->reason }})</small>@endif</td>
                        <td>{{ $m->source?->number }}</td><td>{{ $m->warehouse->name }}</td>
                        <td class="num text-success">{{ $m->quantity > 0 ? qty($m->quantity) : '' }}</td><td class="num text-danger">{{ $m->quantity < 0 ? qty(-$m->quantity) : '' }}</td><td class="num">{{ qty($m->running) }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td colspan="4">{{ __('Closing (') }}{{ $product->unit->short_name }})</td><td class="num">{{ qty($data['rows']->where('quantity', '>', 0)->sum('quantity')) }}</td><td class="num">{{ qty(-$data['rows']->where('quantity', '<', 0)->sum('quantity')) }}</td><td class="num">{{ qty($data['closing']) }}</td></tr></tfoot>
        </table>
    </x-card>
@else
    <x-card><p class="text-muted mb-0">{{ __('Choose a product.') }}</p></x-card>
@endif
@endsection
