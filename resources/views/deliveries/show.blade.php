@extends('layouts.app')
@section('title', 'Delivery Challan '.$delivery->number)
@section('header-actions')
    <a href="{{ route('deliveries.index') }}" class="btn btn-default btn-sm"><i class="fas fa-list"></i> Challans</a>
@endsection
@section('content')
<div class="row"><div class="col-lg-9">
<x-card>
    <x-slot:actions>
        <a href="{{ route('deliveries.print', $delivery) }}" target="_blank" class="btn btn-sm btn-success"><i class="fas fa-print"></i> Print</a>
        @if (! $delivery->invoice_id)
            @can('sales.create')<a href="{{ route('invoices.create', ['sales', 'deliveries' => $delivery->id]) }}" class="btn btn-sm btn-primary"><i class="fas fa-file-invoice"></i> Create invoice</a>@endcan
            @can('deliveries.edit')<a href="{{ route('deliveries.edit', $delivery) }}" class="btn btn-sm btn-default"><i class="fas fa-edit"></i> Edit</a>@endcan
            @can('deliveries.delete')<x-delete :action="route('deliveries.destroy', $delivery)" label="Delete" confirm="Delete this challan? The goods will be returned to stock." />@endcan
        @endif
    </x-slot:actions>
    <div class="row mb-3">
        <div class="col-sm-6">
            <div class="text-muted small">Customer</div>
            <div class="fw-semibold"><a href="{{ route('parties.show', $delivery->party) }}">{{ $delivery->party->name }}</a></div>
            @if ($delivery->ship_to)<div>{{ $delivery->ship_to }}</div>@endif
            @if ($delivery->order)<div class="small mt-1">Against order <a href="{{ route('orders.show', ['sales-orders', $delivery->order]) }}">{{ $delivery->order->number }}</a></div>@endif
        </div>
        <div class="col-sm-6 text-sm-end">
            <div><span class="text-muted">Date:</span> {{ $delivery->date->format('d M Y') }}</div>
            <div><span class="text-muted">From:</span> {{ $delivery->warehouse->name }}</div>
            @if ($delivery->vehicle_no)<div><span class="text-muted">Vehicle:</span> {{ $delivery->vehicle_no }} {{ $delivery->driver }}</div>@endif
            <div>{!! $delivery->invoice ? 'Billed on <a href="'.route('invoices.show', ['sales', $delivery->invoice]).'">'.e($delivery->invoice->number).'</a>' : '<span class="badge bg-warning text-dark">Not billed yet</span>' !!}</div>
        </div>
    </div>
    <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>#</th><th>Item</th><th class="num">Quantity</th>@can('reports.profit')<th class="num text-muted">Cost</th>@endcan</tr></thead>
        <tbody>
        @foreach ($delivery->items as $i)
            <tr><td>{{ $loop->iteration }}</td><td>{{ $i->product->name }}</td><td class="num">{{ qty($i->quantity) }} {{ $i->unit->short_name }}</td>
                @can('reports.profit')<td class="num text-muted">{{ money($i->unit_cost * $i->base_quantity) }}</td>@endcan</tr>
        @endforeach
        </tbody>
    </table>
    @if ($delivery->notes)<p class="text-muted mb-0">{{ $delivery->notes }}</p>@endif
    <p class="small text-muted mt-2 mb-0">Prepared by {{ $delivery->creator?->name }} · {{ $delivery->created_at->format('d M Y H:i') }}</p>
</x-card>
</div></div>
@endsection
