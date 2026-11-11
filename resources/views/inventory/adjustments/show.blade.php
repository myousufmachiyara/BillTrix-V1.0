@extends('layouts.app')
@section('title', ucfirst($adj->type).' '.$adj->number)
@section('content')
<x-card>
    <x-slot:actions>
        <button onclick="window.print()" class="btn btn-sm btn-success no-print"><i class="fas fa-print"></i> Print</button>
        @can('stock.adjust')<x-delete :action="route('stock-adjustments.destroy', $adj)" label="Delete & reverse" confirm="Delete this document and reverse its stock and accounting effect?" />@endcan
    </x-slot:actions>
    <p>
        <strong>Date:</strong> {{ $adj->date->format('d M Y') }} · <strong>Warehouse:</strong> {{ $adj->warehouse->name }}
        @if ($adj->reason) · <strong>Reason:</strong> {{ \App\Models\StockAdjustment::REASONS[$adj->reason] ?? $adj->reason }}@endif
        · <strong>By:</strong> {{ $adj->creator?->name }}
    </p>
    @if ($adj->notes)<p class="text-muted">{{ $adj->notes }}</p>@endif
    <table class="table table-sm table-bordered">
        <thead class="table-light"><tr><th>Product</th>@if ($adj->type === 'count')<th class="num">System</th>@endif<th class="num">Change</th><th class="num">Unit cost</th><th class="num">Value</th></tr></thead>
        <tbody>
        @foreach ($adj->items as $i)
            <tr><td>{{ $i->product->name }}</td>
                @if ($adj->type === 'count')<td class="num">{{ qty($i->system_quantity) }}</td>@endif
                <td class="num {{ $i->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ qty($i->quantity) }} {{ $i->product->unit->short_name }}</td>
                <td class="num">{{ money($i->unit_cost) }}</td><td class="num">{{ money($i->quantity * $i->unit_cost) }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td colspan="{{ $adj->type === 'count' ? 4 : 3 }}">Net value</td><td class="num">{{ money($adj->total_value) }}</td></tr></tfoot>
    </table>
</x-card>
@endsection
