@extends('layouts.app')
@section('title', 'Transfer '.$transfer->number)
@section('content')
<x-card>
    <x-slot:actions>
        <button onclick="window.print()" class="btn btn-sm btn-success no-print"><i class="fas fa-print"></i> Print</button>
        @can('stock.transfer')<x-delete :action="route('stock-transfers.destroy', $transfer)" label="Reverse" confirm="Reverse this transfer?" />@endcan
    </x-slot:actions>
    <p><strong>Date:</strong> {{ $transfer->date->format('d M Y') }} · <strong>From:</strong> {{ $transfer->fromWarehouse->name }} → <strong>To:</strong> {{ $transfer->toWarehouse->name }}</p>
    @if ($transfer->notes)<p class="text-muted">{{ $transfer->notes }}</p>@endif
    <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>Product</th><th class="num">Quantity</th></tr></thead>
        <tbody>@foreach ($transfer->items as $i)<tr><td>{{ $i->product->name }}</td><td class="num">{{ qty($i->quantity) }} {{ $i->unit->short_name }}</td></tr>@endforeach</tbody>
    </table>
    <div class="d-flex justify-content-between mt-5"><div class="border-top px-4 pt-1">Dispatched by</div><div class="border-top px-4 pt-1">Received by</div></div>
</x-card>
@endsection
